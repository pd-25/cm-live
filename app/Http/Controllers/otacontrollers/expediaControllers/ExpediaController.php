<?php
namespace App\Http\Controllers\otacontrollers\expediaControllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Input;
use Validator;
use App\CmOtaDetails;
use App\CmOtaAllAutoPush;
use App\CmBookingConfirmationResponse;
use DB;
use App\Http\Controllers\otacontrollers\BookingDataInsertationController;
use App\Http\Controllers\otacontrollers\InstantBucketController;
use App\Http\Controllers\otacontrollers\CurlController;
use App\Http\Controllers\otacontrollers\expediaControllers\ExpediaModifyController;
use App\Http\Controllers\otacontrollers\expediaControllers\ExpediaCancelationController;
use App\Http\Controllers\Controller;
/**
 * ExpediaController implements the expedia booking.
 * @modify by ranjit
 * @29/01/19
 */

class ExpediaController extends Controller
{
	protected $bookingData,$curlcall,$instantbucket,$expcancel,$expmodify;
	public function __construct(BookingDataInsertationController $bookingData,CurlController $curlcall,InstantBucketController $instantbucket,
	ExpediaModifyController $expmodify,ExpediaCancelationController $expcancel)
    {
      $this->bookingData=$bookingData;
      $this->curlcall=$curlcall;
	  $this->instantbucket=$instantbucket;
	  $this->expmodify=$expmodify;
	  $this->expcancel=$expcancel;
    }
    public function actionIndex(Request $request)
    {
			$ota_details_model              = new CmOtaDetails();
			$OtaAllAutoPushModel            = new CmOtaAllAutoPush();
			$postdata 						= $request->getContent();
			$request_ip = $_SERVER['REMOTE_ADDR'];
			if($postdata!='')
			{
				$OtaAllAutoPushModel->respones_xml = trim($postdata);
				$OtaAllAutoPushModel->save();
				$xml							= simplexml_load_string($postdata);
				$parser 						= $xml->children('soap-env', true);
				$payloadInfo					= $parser->Header->children()
												->Interface->children()
												->PayloadInfo->children();
				$payloadAttributesInfo			= (array) $parser->Header->children()
												->Interface->children()
												->PayloadInfo->attributes();
				if($payloadAttributesInfo['@attributes']['ResponderId'] != "EQCBookingJini")
				{
					return '<?xml version="1.0" encoding="UTF-8"?>
					<soap-env:Envelope xmlns:soap-env="http://schemas.xmlsoap.org/soap/envelope/">
					<soap-env:Header/>
					<soap-env:Body>
					<soap-env:Fault>
					<faultcode>3202</faultcode>
					<faultstring>The ResponderId Identifier is missing or invalid.</faultstring>
					<faultactor>The ResponderId is invalid.</faultactor>
					</soap-env:Fault>
					</soap-env:Body>
					</soap-env:Envelope>';
				}

				$requestType					= $parser->Header->children()
												->Interface->children()
												->PayloadInfo->children()
												->PayloadDescriptor->attributes()->Name;

				$supplierHotelCode 				= $parser->Header->children()
												->Interface->children()
												->PayloadInfo->children()
												->PayloadDescriptor->children()
												->PayloadReference->attributes()->SupplierHotelCode;

				$ota_details_data               = CmOtaDetails::select('*')
													->where('ota_hotel_code','=', $supplierHotelCode)
													->where('is_status', 1)
													->first();

				if(!empty($ota_details_data))
				{   //New Booking
					if($requestType == 'OTA_HotelResNotifRQ')
					{
						$root 							= $parser->Body->children()
														->OTA_HotelResNotifRQ->children()
														->HotelReservations->children()
														->HotelReservation->children();

						$booking_root 					= $parser->Body->children()
														->OTA_HotelResNotifRQ->children()
														->HotelReservations->children()
														->HotelReservation->attributes();

						$booking_date 					= date('Y-m-d H:i:s',strtotime($booking_root->CreateDateTime));
						$UniqueID 						= $root->UniqueID->attributes()->ID;
						$customer_root 					= $parser->Body->children()
														->OTA_HotelResNotifRQ->children()
														->HotelReservations->children()
														->HotelReservation->children()
														->ResGuests->children()
														->ResGuest->children()
														->Profiles->children()
														->ProfileInfo->children()
														->Profile->children()
														->Customer->children();

						$customerName				= $customer_root->PersonName->GivenName." ".$customer_root->PersonName->Surname;
						$customerPhone 				= $customer_root->Telephone->attributes()->CountryAccessCode.' '.$customer_root->Telephone->attributes()->AreaCityCode.' '.$customer_root->Telephone->attributes()->PhoneNumber;
						$customerDetail = $customerName.',';
						$customerDetail.= $customer_root->Email == '' ? 'NA,': $customer_root->Email.',';
						$customerDetail.= $customerPhone == '' ? 'NA' : $customerPhone;

						$booking_status					= "New";
						$roomType_data 					= $root->RoomStays->children()
														->RoomStay->children()
														->RoomTypes->children();
						$rooms_qty						= count($roomType_data);

						$roomRateDateInfo 				= $root->RoomStays->children()
														->RoomStay->children()
														->RoomRates->children();
						$room_type 						= array();
						$rate_code 						= array();

						$no_of_adult					=	$root->RoomStays->children()
															->RoomStay->children()
															->GuestCounts->children()
															->GuestCount->attributes()->Count;
						$channel_name					=	'Expedia';
						$tax_amount						= 	$root->RoomStays->children()
															->RoomStay->children()
															->Total->children()
															->Taxes->children();
						$tax_amount						= 	$tax_amount->Tax->attributes()->Amount;

						foreach ($roomRateDateInfo as $key => $value)
						{
							$hatt 							= $value->attributes();
							$rooms_qty_array[]  			= $hatt->NumberOfUnits;
							$room_typ						= array_values((array) $hatt->RoomTypeCode);
							$rate_cod						= array_values((array) str_replace('A', '',$hatt->RatePlanCode));

							if(!in_array($room_typ, $room_type)){
							$room_type[]			= $room_typ;
							}
							if(!in_array($rate_cod, $rate_code)){
							$rate_code[]			= $rate_cod;
							}
						}
						rsort($rooms_qty_array);
						$rooms_qty 				= $rooms_qty_array[0];
						foreach ($room_type as $k => $v) {
						$room_type 				= implode(',' , $v);
						}
						foreach ($rate_code as $k => $v) {
						$rate_code 				= implode(',' , $v);
						}

						$room_type = $room_type;
						$rate_cod  = $rate_code;
						$checkin_at = $root->RoomStays->children()
									->RoomStay->children()
									->TimeSpan->attributes()->Start;
						$checkout_at = $root->RoomStays->children()
										->RoomStay->children()
										->TimeSpan->attributes()->End;
						$channel_name =	'Expedia';
						$tax_amount = $root->RoomStays->children()
										->RoomStay->children()
										->Total->children()
										->Taxes->children()->Tax->attributes()->Amount;
						$amount 	= $root->RoomStays->children()
										->RoomStay->children()
										->Total->attributes()->AmountAfterTax;
						//Amount Before Tax
						$amount= (float)$amount-(float)$tax_amount;
						$currency  = $root->RoomStays->children()
										->RoomStay->children()
										->Total->attributes()->CurrencyCode;

						$payment_status						= "NA";
						$bookinglist_result					= trim($postdata);

						$responsedata						= simplexml_load_string($postdata);
						$responsedata_parser 				= $responsedata->children('soap-env', true);
						$responsedata_header				= $responsedata_parser->Header->children();
						$responsedata_payloadInfo			= $responsedata_header->Interface->PayloadInfo->attributes();
						$requestId  						= $responsedata_payloadInfo->RequestId;
						$requestorId  						= $responsedata_payloadInfo->RequestorId;
						$responderId  						= $responsedata_payloadInfo->ResponderId;
						$location  							= $responsedata_payloadInfo->Location;
						$responsedata_commDescriptor		= $responsedata_header->Interface->PayloadInfo->children()->CommDescriptor->attributes();
						$sourceId 							= $responsedata_commDescriptor->SourceId;
						$destinationId 						= $responsedata_commDescriptor->DestinationId;
						$retryIndicator 					= $responsedata_commDescriptor->RetryIndicator;
						$responsedata_payloadDescriptor		= $responsedata_header->Interface->PayloadInfo->children()->PayloadDescriptor->attributes();
						$payloadDescriptorName				= $responsedata_payloadDescriptor->Name;
						$payloadDescriptorVersion			= $responsedata_payloadDescriptor->Version;
						$responsedata_payloadReference		= $responsedata_header->Interface->PayloadInfo->children()->PayloadDescriptor->children()->PayloadReference->attributes();


						$supplierHotelCode 					= $responsedata_payloadReference->SupplierHotelCode;
						$responsedata_body 					= $parser->Body->children()
															->OTA_HotelResNotifRQ->attributes();
						$response_timeStamp 				= date("Y-m-d\TH:i:sP");
						$target 							= $responsedata_body->Target;
						$version 							= $responsedata_body->Version;
						$primaryLangID 						= $responsedata_body->PrimaryLangID;
						$resStatus 							= $responsedata_body->ResStatus;

						$responsedata_body 					= $parser->Body->children()
																->OTA_HotelResNotifRQ->attributes();

						/*--- sender means Expedia and reciver means Bookingjini Channel Manager ---*/
						$responsedata_resGlobalInfo  	= 		$responsedata_parser->Body->children()
																->OTA_HotelResNotifRQ->children()
																->HotelReservations->children()
																->HotelReservation->children()
																->ResGlobalInfo->children()
																->HotelReservationIDs->children()
																->HotelReservationID->attributes();

						$responsedata_sender_ResID_Type 	= $responsedata_resGlobalInfo->ResID_Type;
						$responsedata_sender_ResID_Value 	= $responsedata_resGlobalInfo->ResID_Value;
						$responsedata_sender_ResID_Source 	= $responsedata_resGlobalInfo->ResID_Source;
						$responsedata_sender_ResID_Date 	= $responsedata_resGlobalInfo->ResID_Date;
						$responsedata_reciver_ResID_Type 	= "3";
						$responsedata_reciver_ResID_Source 	= $responsedata_payloadInfo->ResponderId;
						$responsedata_reciver_ResID_Date 	= date("Y-m-d\TH:i:sP");
						/*-------- cheching date format and valid date ----------------- */
						$todayDate = date("Y-m-d");
						if(preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $checkin_at))
						{
							if(strtotime($checkin_at) < strtotime($todayDate))
							{
								$rtn_xml='<soap-env:Envelope xmlns:soap-env="http://schemas.xmlsoap.org/soap/envelope/">
								<soap-env:Header>
								<Interface xmlns="http://www.newtrade.com/expedia/R14/header" Name="ExpediaDirectConnect" Version="4.0">
								<PayloadInfo RequestId="'.$requestId.'" RequestorId="'.$requestorId.'" ResponderId="'.$responderId.'" Location="'.$location.'">
								<CommDescriptor SourceId="'.$destinationId.'" DestinationId="'.$sourceId.'" RetryIndicator="'.$retryIndicator.'"/>
								<PayloadDescriptor Name="OTA_HotelResNotifRS" Version="'.$payloadDescriptorVersion.'">
								<PayloadReference SupplierHotelCode="'.$supplierHotelCode.'"/>
								</PayloadDescriptor>
								</PayloadInfo>
								</Interface>
								</soap-env:Header>
								<soap-env:Body>
								<soap-env:Fault>
								<faultcode>3206</faultcode>
								<faultstring>The Check-in date is missing or invalid.</faultstring>
								<faultactor>The Check-in-date is not past date.</faultactor>
								</soap-env:Fault>
								</soap-env:Body>
								</soap-env:Envelope>';
							}
						}
						else
						{
							$rtn_xml='<soap-env:Envelope xmlns:soap-env="http://schemas.xmlsoap.org/soap/envelope/">
							<soap-env:Header>
							<Interface xmlns="http://www.newtrade.com/expedia/R14/header" Name="ExpediaDirectConnect" Version="4.0">
							<PayloadInfo RequestId="'.$requestId.'" RequestorId="'.$requestorId.'" ResponderId="'.$responderId.'" Location="'.$location.'">
							<CommDescriptor SourceId="'.$destinationId.'" DestinationId="'.$sourceId.'" RetryIndicator="'.$retryIndicator.'"/>
							<PayloadDescriptor Name="OTA_HotelResNotifRS" Version="'.$payloadDescriptorVersion.'">
							<PayloadReference SupplierHotelCode="'.$supplierHotelCode.'"/>
							</PayloadDescriptor>
							</PayloadInfo>
							</Interface>
							</soap-env:Header>
							<soap-env:Body>
							<soap-env:Fault>
							<faultcode>3206</faultcode>
							<faultstring>The Check-in date is missing or invalid.</faultstring>
							<faultactor>The Check-in-date is missing or invalid.</faultactor>
							</soap-env:Fault>
							</soap-env:Body>
							</soap-env:Envelope>';
						}
						$booking_status="Commit";//New Booking status
						$push_by       = "Expedia";
						$rlt=$postdata ;
						$bookingDetails = array('UniqueID'=>$UniqueID,'customerDetail'=>$customerDetail,'booking_status'=>$booking_status,'rooms_qty'=>$rooms_qty,'room_type'=>$room_type,'checkin_at'=>$checkin_at,'checkout_at'=>$checkout_at,'booking_date'=>$booking_date,'amount'=>$amount,'payment_status'=>$payment_status,'rate_code'=>$rate_code,'rlt'=>$rlt,'currency'=>$currency,'channel_name'=>$channel_name,'tax_amount'=>$tax_amount,'no_of_adult'=>$no_of_adult,'no_of_child'=>'NA','inclusion'=>'NA');
						$bookinginfo  = $this->bookingData->cmOtaBooking($bookingDetails,$ota_details_data);//this function call used for insert/update booking in database
						$db_status  = $bookinginfo['db_status'];
						$cm_confirmation_id    = strtotime("now").$ota_details_data->ota_id.date("ymd").$bookinginfo['ota_booking_tabel_id'];
						$responsedata_reciver_ResID_Value 	= $cm_confirmation_id;
						if($db_status){
							$this->instantbucket->bucketEngineUpdate($booking_status,$push_by,$ota_details_data,$bookinginfo['ota_booking_tabel_id']);//this function is used for booking bucket data updation
						}
						$booking_id	= $bookinginfo['ota_booking_tabel_id'];

						/*--- sender means Expedia and reciver means Bookingjini Channel Manager ---*/
						$responsedata_reciver_ResID_Type 	= "3";
						$responsedata_reciver_ResID_Source 	= $responsedata_payloadInfo->ResponderId;
						$responsedata_reciver_ResID_Value 	= $cm_confirmation_id;
						$responsedata_reciver_ResID_Date 	= date("Y-m-d\TH:i:sP");

						$rtn_xml='<soap-env:Envelope xmlns:soap-env="http://schemas.xmlsoap.org/soap/envelope/">
						<soap-env:Header>
						<Interface xmlns="http://www.newtrade.com/expedia/R14/header" Name="ExpediaDirectConnect" Version="4.0">
						<PayloadInfo RequestId="'.$requestId.'" RequestorId="'.$requestorId.'" ResponderId="'.$responderId.'" Location="'.$location.'">
						<CommDescriptor SourceId="'.$destinationId.'" DestinationId="'.$sourceId.'" RetryIndicator="'.$retryIndicator.'"/>
						<PayloadDescriptor Name="OTA_HotelResNotifRS" Version="'.$payloadDescriptorVersion.'">
						<PayloadReference SupplierHotelCode="'.$supplierHotelCode.'"/>
						</PayloadDescriptor>
						</PayloadInfo>
						</Interface>
						</soap-env:Header>
						<soap-env:Body>
						<OTA_HotelResNotifRS xmlns="http://www.opentravel.org/OTA/2003/05" Version="'.$version.'" ResResponseType="'.$resStatus.'" TimeStamp="'.$response_timeStamp.'" Target="'.$target.'" PrimaryLangID="'.$primaryLangID.'">
						<Success/>
						<HotelReservations>
						<HotelReservation>
						<ResGlobalInfo>
						<HotelReservationIDs>
						<HotelReservationID ResID_Type="'.$responsedata_reciver_ResID_Type.'" ResID_Value="'.$responsedata_reciver_ResID_Value.'" ResID_Date="'.$responsedata_reciver_ResID_Date.'" ResID_Source="'.$responsedata_reciver_ResID_Source.'"/>
						<HotelReservationID ResID_Type="'.$responsedata_sender_ResID_Type.'" ResID_Value="'.$responsedata_sender_ResID_Value.'" ResID_Source="'.$responsedata_sender_ResID_Source.'" ResID_Date="'.$responsedata_sender_ResID_Date.'"/>
						</HotelReservationIDs>
						</ResGlobalInfo>
						</HotelReservation>
						</HotelReservations>
						</OTA_HotelResNotifRS>
						</soap-env:Body>
						</soap-env:Envelope>';
					//Saving the response message for reference
					$this->bookingConfirmation($booking_id,$cm_confirmation_id,$rtn_xml);
					//Return response
					return $rtn_xml;
				}
				//Modified booking
				if($requestType == 'OTA_HotelResModifyNotifRQ')
				{
					$rm=$this->expmodify->expediaModify($parser,$postdata,$ota_details_data);
					return $rm;
				}
				//Cancelled booking
				if($requestType == 'OTA_CancelRQ')
				{
					$rc=$this->expcancel->expediaCancelation($parser,$postdata,$ota_details_data);
					return $rc;
				}
			}
			else{
				return '<?xml version="1.0" encoding="UTF-8"?>
					<soap-env:Envelope xmlns:soap-env="http://schemas.xmlsoap.org/soap/envelope/">
					<soap-env:Header/>
					<soap-env:Body>
					<soap-env:Fault>
					<faultcode>3202</faultcode>
					<faultstring>Supplier code is invalid.</faultstring>
					<faultactor>Supplier code is invalid.</faultactor>
					</soap-env:Fault>
					</soap-env:Body>
					</soap-env:Envelope>';
			}
		}
		else
		{		// Check Hotel Code Avaliable or Not
			$rtn_xml = '<?xml version="1.0" encoding="UTF-8"?>
			<soap-env:Envelope xmlns:soap-env="http://schemas.xmlsoap.org/soap/envelope/">
			<soap-env:Header/>
			<soap-env:Body>
			<soap-env:Fault>
			<faultcode>3202</faultcode>
			<faultstring>The property Identifier is missing or invalid.</faultstring>
			<faultactor>The property Identifier is missing or invalid.</faultactor>
			</soap-env:Fault>
			</soap-env:Body>
			</soap-env:Envelope>';
				return $rtn_xml;
		}
	} // Index Function End

	public function bookingConfirmation($booking_id,$cm_confirmation_id,$xml){
        $cmBookingConfirmationResponseModel = new CmBookingConfirmationResponse();
        $cmBookingConfirmationResponseModel->booking_id         = trim($booking_id);
        $cmBookingConfirmationResponseModel->cm_confirmation_id = trim($cm_confirmation_id);
        $cmBookingConfirmationResponseModel->xml                = trim($xml);
        $cmBookingConfirmationResponseModel->save();
     }
}// Class End
