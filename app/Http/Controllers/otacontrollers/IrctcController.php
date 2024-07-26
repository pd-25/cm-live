<?php
namespace App\Http\Controllers\otacontrollers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Input;
use Validator;
use App\CmOtaDetails;
use App\CmOtaBooking;
use App\CmOtaAllAutoPush;
use App\CmOtaBookingPushBucket;
use App\CmBookingConfirmationResponse;
use DB;
use App\Http\Controllers\otacontrollers\BookingDataInsertationController;
use App\Http\Controllers\otacontrollers\InstantBucketController;
use App\Http\Controllers\otacontrollers\CurlController;
use App\Http\Controllers\Controller;

/**
 * IrctcController used for booking from IRCTC (IRCTC push the bookings to Bookingjini).
 * @author Siri
 * @date-16/03/2021
 **/

 class IrctcController extends controller
 {
    protected $bookingData,$curlcall,$instantbucket;
    public function __construct(BookingDataInsertationController $bookingData,CurlController $curlcall,InstantBucketController $instantbucket)
    {
      $this->bookingData=$bookingData;
      $this->curlcall=$curlcall;
      $this->instantbucket=$instantbucket;
    }

    public function actionIndex(Request $request)
    {
      $ota_details_model             = new CmOtaDetails();
      $otaBookingModel               = new CmOtaBooking();
      $OtaAllAutoPushModel           = new CmOtaAllAutoPush();
      $postdata 					           = $request->getContent();
      $request_ip                    = $_SERVER['REMOTE_ADDR'];
      $array_data                    = (array) json_decode($postdata);
      
      if($postdata!='')  
      {
        $OtaAllAutoPushModel->respones_xml = trim($postdata);
        $OtaAllAutoPushModel->save();

        $reservations = $array_data['OTA_HotelResNotifRQ']->HotelReservations->HotelReservation[0];
        $roomStays = $reservations->RoomStays->RoomStay[0]; 
        $UniqueID = $reservations->UniqueID->ID;
        $echo_token = $array_data['OTA_HotelResNotifRQ']->EchoToken;
        $otaHotelCode = $roomStays->BasicPropertyInfo->HotelCode;
        
        $ota_hotel_details    = CmOtaDetails::select('*')->where('ota_hotel_code','=', $otaHotelCode)->where('is_status', 1)->first();
        if(!$ota_hotel_details)
        {
          $rtn_xml='{"reference":"'.$UniqueID.'", "status":"Not Confirmed", "confirmationId":"", "description":"Invalid ProductId"}';
        }
        else
        {
          $booking_status = $reservations->ResStatus;
          $inclusion = 'NA';
          $adults = array();
          $child  = array();
          $customer_name = $reservations->ResGuests->ResGuest[0]->Profiles->ProfileInfo->Profile->Customer->PersonName->GivenName;
          $customer_email = $reservations->ResGuests->ResGuest[0]->Profiles->ProfileInfo->Profile->Customer->Email;
          $customerDetail  = $customer_name.','.$customer_email;

          $roomDetails = $roomStays->RoomTypes;
          $rateDetails = $roomStays->RatePlans;
          foreach($roomDetails as $key => $value){
            $room_type_ids[]      = $value->RoomTypeCode;
          }
          foreach($rateDetails as $key => $value){
            $rate_plan_ids[]      = $value->RatePlanCode;
          }
          $rooms_qty = $roomDetails->RoomType->NumberOfUnits;
          $room_type = implode(',', $room_type_ids);
          $rate_code = implode(',', $rate_plan_ids);

          $no_of_adult     = $roomStays->GuestCounts->GuestCount[0]->Count;
          $no_of_child     = $roomStays->GuestCounts->GuestCount[1]->Count;
        
          $amount     = $roomStays->Total->Amount;
          $tax_amount = $reservations->ResGlobalInfo->Total->TotalTax;
          $currency   = $roomStays->Total->CurrencyCode;
          $checkin_at = $roomStays->TimeSpan->Start;
          $checkout_at = $roomStays->TimeSpan->End;
          $booking_date = $reservations->CreateDateTime;
          $payment_status = $reservations->PayAtHotel;
          
          $rlt=$postdata;
          if($booking_status == 'Confirm'){
            $booking_status  = 'Commit';
          }
          if($booking_status == 'Cancel'){
            $booking_status  = 'Cancel';
          }
          if($booking_status == 'Modify'){
            $booking_status  = 'Modify';
          }
          if($payment_status == 'N'){
              $payment_status = 'Prepaid';
          }

          $bookingDetails = array('UniqueID'=>$UniqueID,'customerDetail'=>$customerDetail,'booking_status'=>$booking_status,'rooms_qty'=>$rooms_qty,'room_type'=>$room_type,'checkin_at'=>$checkin_at,'checkout_at'=>$checkout_at,'booking_date'=>$booking_date,'amount'=>$amount,'payment_status'=>$payment_status,'rate_code'=>$rate_code,'rlt'=>$rlt,'currency'=>$currency,'channel_name'=>'IRCTC','tax_amount'=>$tax_amount,'no_of_adult'=>$no_of_adult,'no_of_child'=>$no_of_child,'inclusion'=>$inclusion,'modify_status'=>0);
          $bookinginfo  = $this->bookingData->cmOtaBooking($bookingDetails,$ota_hotel_details);//this function call used for insert/update booking in database
          $db_status  = $bookinginfo['db_status'];
          $time_stamp = date("Y-m-d h:i:s");
          if($db_status)
          {
            $push_by = "irctc";
            $this->instantbucket->bucketEngineUpdate($booking_status,$push_by,$ota_hotel_details,$bookinginfo['ota_booking_tabel_id']);//this function is used for booking bucket data updation
            // $rtn_xml = '{"message": "Successfully updated","status": "success"}';
            $rtn_xml = '{
              "OTA_HotelResNotifRS": {
              "EchoToken": "'.$echo_token.'",
              "TimeStamp": "'.$time_stamp.'",
              "Target": "Bookingjini",
              "Version": "",
              "Status": "Success",
              "Remark": "Booking Successful"
              }
            }';
            
          }
          else
          {
            // $rtn_xml = '{"message": "Invalid ProductId or booking already exist","status": "failure"}';
            $rtn_xml = '{
              "OTA_HotelResNotifRS": {
              "EchoToken": "'.$echo_token.'",
              "TimeStamp": "'.$time_stamp.'",
              "Target": "Bookingjini",
              "Version": "",
              "Status": "Failure",
              "Remark": "Booking Failure"
              }
            }';
          }
        } 
        echo $rtn_xml;
      }
      else{
        $rtn_xml = '{
          "OTA_HotelResNotifRS": {
          "EchoToken": "'.$echo_token.'",
          "TimeStamp": "'.$time_stamp.'",
          "Target": "Bookingjini",
          "Version": "",
          "Status": "Failure",
          "Remark": "Booking Failure"
          }
        }';
        echo  $rtn_xml;
      }
    }
 }
?>