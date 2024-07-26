<?php
namespace App\Http\Controllers\otacontrollers\expediaControllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Input;
use Validator;
use DB;
use App\CmOtaBooking;
use App\Http\Controllers\otacontrollers\BookingDataInsertationController;
use App\Http\Controllers\otacontrollers\InstantBucketController;
use App\Http\Controllers\otacontrollers\CurlController;
use App\Http\Controllers\CmOtaBookingInvStatusService;
use App\Http\Controllers\Controller;
/**
 * ExpediaCancelationController implements the modify booking of expedia.
 * @modify by ranjit
 * @29/01/19
 */

class ExpediaCancelationController extends Controller
{	
    protected $bookingData,$curlcall,$instantbucket,$cmOtaBookingInvStatusService;
	public function __construct(CmOtaBookingInvStatusService $cmOtaBookingInvStatusService,BookingDataInsertationController $bookingData,CurlController $curlcall,InstantBucketController $instantbucket)
    {
      $this->bookingData=$bookingData;
      $this->curlcall=$curlcall;
      $this->instantbucket=$instantbucket;
      $this->cmOtaBookingInvStatusService=$cmOtaBookingInvStatusService;
    } 
    public function expediaCancelation($parser,$postdata,$ota_details_data)
    {
        $root 					 = $parser->Body->children()
                                    ->OTA_CancelRQ->children();
        $UniqueID 			     = $root->UniqueID->attributes()->ID;	
        $booking_status		  	 = "Cancel";	

        $responsedata_parser 	 = $parser;
        $responsedata_header	 = $responsedata_parser->Header->children();
        $responsedata_payloadInfo			       = $responsedata_header->Interface->PayloadInfo->attributes();

        $requestId  							   = $responsedata_payloadInfo->RequestId;
        $requestorId  							   = $responsedata_payloadInfo->RequestorId;
        $responderId  							   = $responsedata_payloadInfo->ResponderId;
        $location  								   = $responsedata_payloadInfo->Location;
        $ExperyDateTimeStamp 					   = date("Y-m-d\TH:i:sP");
        $responsedata_commDescriptor			   = $responsedata_header->Interface->PayloadInfo->children()->CommDescriptor->attributes();
        $sourceId 								   = $responsedata_commDescriptor->SourceId;
        $destinationId 						   	   = $responsedata_commDescriptor->DestinationId;
        $retryIndicator 					 	   = $responsedata_commDescriptor->RetryIndicator;

        $responsedata_payloadDescriptor		 	   = $responsedata_header->Interface->PayloadInfo->children()->PayloadDescriptor->attributes();


        $payloadDescriptorName				= $responsedata_payloadDescriptor->Name;
        $payloadDescriptorVersion			= $responsedata_payloadDescriptor->Version;
        $responsedata_payloadReference		= $responsedata_header->Interface->PayloadInfo->children()->PayloadDescriptor->children()->PayloadReference->attributes();
        $supplierHotelCode 					= $responsedata_payloadReference->SupplierHotelCode;

        $responsedata_attribute_body 		= $parser->Body->children()
                                            ->OTA_CancelRQ->attributes();
        $response_timeStamp 				= date("Y-m-d\TH:i:sP");
        $target 							= $responsedata_attribute_body->Target;
        $version 							= $responsedata_attribute_body->Version;
        $primaryLangID 						= $responsedata_attribute_body->PrimaryLangID;
        $resStatus 							= $responsedata_attribute_body->ResStatus;

        $responsedata_UniqueID 				= $parser->Body->children()
                                            ->OTA_CancelRQ->children()
                                                ->UniqueID;
        
        foreach ($responsedata_UniqueID as $key => $value)
        {
            //print_r($value->attributes());
            $type 			  = $value->attributes()->Type;
            $id   			  = $value->attributes()->ID;
            $companyname = $value->children()->CompanyName;
            /*--- Sender means Expedia and Reciver means Bookingjini Channel Manager ---*/
            if($type == "14"){
            $UniqueID_Type_sender   		= $type;
            $UniqueID_ID_sender   			= $id;
            $UniqueID_CompanyName_sender 	= $companyname;
            }
            if($type == "10"){
            $UniqueID_Type_reciver 			= $type;
            $UniqueID_ID_reciver   			= $id;
            $UniqueID_CompanyName_reciver 	= $companyname;
            }
        }
        $otaBookingUpdateModel   = CmOtaBooking::select('*')
                                    ->where('unique_id', '=' ,trim($UniqueID) )
                                    ->first();
        $room_type=$otaBookingUpdateModel->room_type;
        $checkin_at=$otaBookingUpdateModel->checkin_at;
        $checkout_at=$otaBookingUpdateModel->checkout_at;
        $push_by              = "Expedia";
        $bookingDetails = array('UniqueID'=>$UniqueID,'customerDetail'=>'NA','booking_status'=>$booking_status,'rooms_qty'=>0,'room_type'=>$room_type,'checkin_at'=>$checkin_at,'checkout_at'=>$checkout_at,'booking_date'=>'NA','amount'=>0,'payment_status'=>'NA','rate_code'=>'NA','rlt'=>'NA','currency'=>'INR','channel_name'=>'NA','tax_amount'=>0,'no_of_adult'=>0,'no_of_child'=>'NA','inclusion'=>'NA');
        $bookinginfo  = $this->bookingData->cmOtaBooking($bookingDetails,$ota_details_data );//this function call used for insert/update booking in database
        $db_status  = $bookinginfo['db_status'];
		$cm_confirmation_id    = strtotime("now").$ota_details_data->ota_id.date("ymd").$bookinginfo['ota_booking_tabel_id'];
        $booking_id	= $bookinginfo['ota_booking_tabel_id'];
        if($db_status)
        {
            $this->instantbucket->bucketEngineUpdate($booking_status,$push_by,$ota_details_data,$bookinginfo['ota_booking_tabel_id']);//this function is used for booking bucket data updation  
        }
        $rtn_xml='<soap-env:Envelope xmlns:soap-env="http://schemas.xmlsoap.org/soap/envelope/">
            <soap-env:Header>
            <Interface xmlns="http://www.newtrade.com/expedia/R14/header" Name="ExpediaDirectConnect" Version="4.0">
            <PayloadInfo RequestId="'.$requestId.'" RequestorId="'.$requestorId.'" ResponderId="'.$responderId.'" Location="'.$location.'" ExpirationDateTime="'.$ExperyDateTimeStamp.'" >
            <CommDescriptor SourceId="'.$destinationId.'" DestinationId="'.$sourceId.'" RetryIndicator="'.$retryIndicator.'"/>
            <PayloadDescriptor Name="OTA_CancelRS" Version="'.$payloadDescriptorVersion.'">
            <PayloadReference SupplierHotelCode="'.$supplierHotelCode.'"/>
            </PayloadDescriptor>
            </PayloadInfo>
            </Interface>
            </soap-env:Header>
            <soap-env:Body>
            <OTA_CancelRS xmlns="http://www.opentravel.org/OTA/2003/05" Status="Cancelled" Version="'.$version.'"  TimeStamp="'.$response_timeStamp.'" Target="'.$target.'" PrimaryLangID="'.$primaryLangID.'">
            <Success/>
            <UniqueID ID="'.$UniqueID_ID_sender.'" Type="'.$UniqueID_Type_sender.'">
            <CompanyName>"'.$UniqueID_CompanyName_sender.'"</CompanyName>
            </UniqueID>
            <UniqueID ID="'.$UniqueID_ID_reciver.'" Type="'.$UniqueID_Type_reciver.'">
            <CompanyName>"'.$UniqueID_CompanyName_reciver.'"</CompanyName>
            </UniqueID>
            <CancelInfoRS>
            <UniqueID ID="'.$UniqueID_ID_reciver.'" Type="'.$UniqueID_Type_reciver.'">
            <CompanyName>"'.$UniqueID_CompanyName_reciver.'"</CompanyName>
            </UniqueID>
            </CancelInfoRS>
            </OTA_CancelRS>
            </soap-env:Body>
            </soap-env:Envelope>';
            //Saving the response message for reference
            app('App\Http\Controllers\otacontrollers\expediaControllers\ExpediaController')->bookingConfirmation($booking_id,$cm_confirmation_id,$rtn_xml);
            //Return response
            return $rtn_xml;
    }
}