<?php
namespace App\Http\Controllers\otacontrollers\expediaControllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Input;
use Validator;
use DB;
use App\Http\Controllers\otacontrollers\BookingDataInsertationController;
use App\Http\Controllers\otacontrollers\InstantBucketController;
use App\Http\Controllers\otacontrollers\CurlController;
use App\Http\Controllers\Controller;
/**
 * ExpediaModifyController implements the modify booking of expedia.
 * @modify by ranjit
 * @29/01/19
 */
class ExpediaModifyController extends Controller
{
    protected $bookingData,$curlcall,$instantbucket;
	public function __construct(BookingDataInsertationController $bookingData,CurlController $curlcall,InstantBucketController $instantbucket)
    {
      $this->bookingData=$bookingData;
      $this->curlcall=$curlcall;
	  $this->instantbucket=$instantbucket;
    }
    public function expediaModify($parser,$postdata,$ota_details_data)
    {

        $root 					= $parser->Body->children()
                                ->OTA_HotelResModifyNotifRQ->children()
                                ->HotelResModifies->children()
                                ->HotelResModify->children();

        $booking_root 			= $parser->Body->children()
                                ->OTA_HotelResModifyNotifRQ->children()
                                ->HotelResModifies->children()
                                ->HotelResModify->attributes();

        $booking_date 			= date('Y-m-d H:i:s',strtotime($booking_root->CreateDateTime));
        $UniqueID 				= $root->UniqueID->attributes()->ID;

        $customer_root 			= $parser->Body->children()
                                ->OTA_HotelResModifyNotifRQ->children()
                                ->HotelResModifies->children()
                                ->HotelResModify->children()
                                ->ResGuests->children()
                                ->ResGuest->children()
                                ->Profiles->children()
                                ->ProfileInfo->children()
                                ->Profile->children()
                                ->Customer->children();
        $customerName			= $customer_root->PersonName->GivenName." ".$customer_root->PersonName->Surname;
        $customerPhone 			= $customer_root->Telephone->attributes()->CountryAccessCode.' '.$customer_root->Telephone->attributes()->AreaCityCode.' '.$customer_root->Telephone->attributes()->PhoneNumber;
        $customerDetail = $customerName.',';
        $customerDetail.= $customer_root->Email == '' ? 'NA,': $customer_root->Email.',';
        $customerDetail.= $customerPhone == '' ? 'NA' : $customerPhone;
        $booking_status			= "Modify";

        $roomType_data 			= $root->RoomStays->children()
                                ->RoomStay->children()
                                ->RoomTypes->children();
        $rooms_qty				= count($roomType_data);
        $roomRateDateInfo 		= $root->RoomStays->children()
                                ->RoomStay->children()
                                ->RoomRates->children();
        $room_type 				= array();
        $rate_code 				= array();

        foreach ($roomRateDateInfo as $key => $value)
        {
            $hatt 					= $value->attributes();
            $effectiveDate[] 		= $hatt->EffectiveDate;
            $expireDate[]	 		= $hatt->ExpireDate;
            $rooms_qty_array[]  	= $hatt->NumberOfUnits;
            $room_typ				= array_values((array) $hatt->RoomTypeCode);
            $rate_cod				= array_values((array) $hatt->RatePlanCode);

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

        $room_type					= $room_type;
        $rate_code 					= $rate_code;
        $checkin_at = $root->RoomStays->children()
									->RoomStay->children()
									->TimeSpan->attributes()->Start;
		$checkout_at	= $root->RoomStays->children()
                                    ->RoomStay->children()
                                    ->TimeSpan->attributes()->End;
        $no_of_adult		    	= $root->RoomStays->children()
                                    ->RoomStay->children()
                                    ->GuestCounts->children()
                                    ->GuestCount->attributes()->Count;

        $channel_name						=	'Expedia';
        $tax_amount							= 	$root->RoomStays->children()
                                                ->RoomStay->children()
                                                ->Total->children()
                                                ->Taxes->children()
                                                ->Tax->attributes()->Amount;

        $amount 				= $root->RoomStays->children()
                                ->RoomStay->children()
                                ->Total->attributes()->AmountAfterTax;
        $currency 				= $root->RoomStays->children()
                                    ->RoomStay->children()
                                    ->Total->attributes()->CurrencyCode;
        $payment_status			= "NA";

        $bookinglist_result		= trim($postdata);

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



        $responsedata_attribute_body 		= $responsedata_parser->Body->children()
                                            ->OTA_HotelResModifyNotifRQ->attributes();


        $response_timeStamp 				= date("Y-m-d\TH:i:sP");

        $target 							= $responsedata_attribute_body->Target;
        $version 							= $responsedata_attribute_body->Version;
        $primaryLangID 						= $responsedata_attribute_body->PrimaryLangID;
        $resStatus 							= $responsedata_attribute_body->ResStatus;

       /*--- sender means Expedia and reciver means Bookingjini Channel Manager ---*/
        $responsedata_resGlobalInfo 		= $responsedata_parser->Body->children()
                                            ->OTA_HotelResModifyNotifRQ->children()
                                            ->HotelResModifies->children()
                                            ->HotelResModify->children()
                                            ->ResGlobalInfo->children()
                                            ->HotelReservationIDs->children()
                                            ->HotelReservationID->attributes();


        $responsedata_sender_ResID_Type 	= $responsedata_resGlobalInfo->ResID_Type;
        $responsedata_sender_ResID_Value 	= $responsedata_resGlobalInfo->ResID_Value;
        $responsedata_sender_ResID_Source 	= $responsedata_resGlobalInfo->ResID_Source;
        $responsedata_sender_ResID_Date 	= $responsedata_resGlobalInfo->ResID_Date;

        $bookingDetails = array('UniqueID'=>$UniqueID,'customerDetail'=>$customerDetail,'booking_status'=>$booking_status,'rooms_qty'=>$rooms_qty,'room_type'=>$room_type,'checkin_at'=>$checkin_at,'checkout_at'=>$checkout_at,'booking_date'=>$booking_date,'amount'=>$amount,'payment_status'=>$payment_status,'rate_code'=>$rate_code,'rlt'=>$postdata,'currency'=>$currency,'channel_name'=>$channel_name,'tax_amount'=>$tax_amount,'no_of_adult'=>$no_of_adult,'no_of_child'=>'NA','inclusion'=>'NA');
        $bookinginfo  = $this->bookingData->cmOtaBooking($bookingDetails,$ota_details_data );//this function call used for insert/update booking in database
        $db_status  = $bookinginfo['db_status'];
		$cm_confirmation_id    = strtotime("now").$ota_details_data->ota_id.date("ymd").$bookinginfo['ota_booking_tabel_id'];
        $booking_id	=$bookinginfo['ota_booking_tabel_id'];

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
            <PayloadDescriptor Name="OTA_HotelResModifyNotifRS" Version="'.$payloadDescriptorVersion.'">
            </PayloadDescriptor>
            </PayloadInfo>
            </Interface>
            </soap-env:Header>
            <soap-env:Body>
            <OTA_HotelResModifyNotifRS xmlns="http://www.opentravel.org/OTA/2003/05" Version="'.$version.'" ResResponseType="'.$resStatus.'" TimeStamp="'.$response_timeStamp.'" Target="'.$target.'" PrimaryLangID="'.$primaryLangID.'">
            <Success/>
            <HotelResModifies>
            <HotelResModify>
            <ResGlobalInfo>
            <HotelReservationIDs>
            <HotelReservationID ResID_Type="'.$responsedata_reciver_ResID_Type.'" ResID_Value="'.$responsedata_reciver_ResID_Value.'" ResID_Date="'.$responsedata_reciver_ResID_Date.'" ResID_Source="'.$responsedata_reciver_ResID_Source.'"/>
            <HotelReservationID ResID_Type="'.$responsedata_sender_ResID_Type.'" ResID_Value="'.$responsedata_sender_ResID_Value.'" ResID_Source="'.$responsedata_sender_ResID_Source.'" ResID_Date="'.$responsedata_sender_ResID_Date.'"/>
            </HotelReservationIDs>
            </ResGlobalInfo>
            </HotelResModify>
            </HotelResModifies>
            </OTA_HotelResModifyNotifRS>
            </soap-env:Body>
            </soap-env:Envelope>';
        app('App\Http\Controllers\otacontrollers\expediaControllers\ExpediaController')->bookingConfirmation($booking_id,$cm_confirmation_id,$rtn_xml);
        return $rtn_xml;
    }
}
