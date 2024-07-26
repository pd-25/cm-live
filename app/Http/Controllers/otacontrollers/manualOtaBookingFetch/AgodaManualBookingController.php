<?php
namespace App\Http\Controllers\otacontrollers\manualOtaBookingFetch;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Input;
use Validator;
use DB;
use App\CmOtaDetails;
use App\CmOtaBooking;
use App\CmOtaAllAutoPush;
use App\CmOtaBookingPushBucket;
use App\Http\Controllers\Controller;
use App\Http\Controllers\otacontrollers\BookingDataInsertationController;
use App\Http\Controllers\otacontrollers\InstantBucketController;
use App\Http\Controllers\otacontrollers\CurlController;
/**
* Agoda Manual booking retrival and save.
* created by ranjit
* @08/02/19
*/
class AgodaManualBookingController extends Controller
{
    protected $bookingData,$curlcall,$instantbucket;
    public function __construct(BookingDataInsertationController $bookingData,CurlController $curlcall,InstantBucketController $instantbucket)
    {
      $this->bookingData=$bookingData;
      $this->curlcall=$curlcall;
      $this->instantbucket=$instantbucket;
    }
    public function manualBookingActionIndex($hotel_id,$booking_id)
    {
      $otaBookingModel              = new CmOtaBooking();
      $OtaAllAutoPushModel          = new CmOtaAllAutoPush();
      $request_ip                   = $_SERVER['REMOTE_ADDR'];

      $ota_details_model            = new CmOtaDetails();
      $ota_details_datas            = $ota_details_model
                                        ->where('ota_name' ,'=', 'Agoda')
                                        ->where('hotel_id','=',$hotel_id)
                                        ->where('is_active' ,'=', 1)
                                        ->first();
      if(!$ota_details_datas)
      {
        return array('status'=>0,'message'=>'Hotel is not sync with Agoda');
      }
      $auth_parameter               = json_decode($ota_details_datas->auth_parameter);
      $apiKey                       = trim($auth_parameter->apiKey);
      $date                         = new \DateTime();
      $dateTimestamp                = $date->getTimestamp();
      $bookingDetails_xml="";
      $bookingDetails_xml ='<?xml version="1.0" encoding="UTF-8"?>
      <request timestamp="'.$dateTimestamp.'" type="4">
      <criteria language="EN">
      <property id="'.$ota_details_datas->ota_hotel_code.'">
        <booking id="'.$booking_id.'"/>
        </property>
      </criteria>
      </request>';
      $headers = array (
        'Content-Type: application/xml',
        );
      if($bookingDetails_xml!="")
      {
        $bookingDetails_url = 'https://supply.agoda.com/api?apiKey='.$apiKey;
        $bookingDetails = $this->curlcall->curlRequest($bookingDetails_url,$headers,$bookingDetails_xml);//used for cURL request
        if($bookingDetails['array_data']["bookings"]["@attributes"]["count"] == '0')
        {
          return array('status'=>0,'message'=>"No bookings available!");
        }
        //dd($bookingDetails);
        $bookingDetails_array_datas= $bookingDetails['array_data'];
        $rlt= $bookingDetails['rlt'];
        $OtaAllAutoPushModel->respones_xml = trim($rlt);
        $OtaAllAutoPushModel->save();
        if($bookingDetails_array_datas['bookings']['@attributes']['count']==1)
        {
          $bookingDetails_array_datas['bookings']['booking']=array($bookingDetails_array_datas['bookings']['booking']);
        }
        if(isset($bookingDetails_array_datas['bookings']['booking']))
        {
          foreach ($bookingDetails_array_datas['bookings']['booking'] as $key => $bookingDetails_data)
          {
            if(isset($bookingDetails_data))
            {
              $otaBookingModel              = new CmOtaBooking();
              $uniqueID =$bookingDetails_data['@attributes']['booking_id'];
              $hotel_Code = $bookingDetails_data['@attributes']['property_id'];
              $booking_status = $bookingDetails_data['@attributes']['status'];
              $rooms_qty = $bookingDetails_data['@attributes']['room_count'];
              $no_of_adult = $bookingDetails_data['@attributes']['adults'];
              $no_of_child = $bookingDetails_data['@attributes']['children'];
              $room_type = $bookingDetails_data['@attributes']['room_id'];
              $checkin_at = $bookingDetails_data['@attributes']['arrival'];
              $checkout_at = $bookingDetails_data['@attributes']['departure'];
              $booking_date = date('Y-m-d H:i:s',strtotime($bookingDetails_data['@attributes']['booking_date']));
              $rate_code = $bookingDetails_data['@attributes']['rateplan_id'];
              if(isset($bookingDetails_data['customer']['@attributes']['email']) && isset($bookingDetails_data['customer']['@attributes']['phone']))
              {
                $customerDetail = $bookingDetails_data['customer']['@attributes']['first_name'].' '.$bookingDetails_data['customer']['@attributes']['last_name'].','.$bookingDetails_data['customer']['@attributes']['email'].','.$bookingDetails_data['customer']['@attributes']['phone'];
              }
              else if(isset($bookingDetails_data['customer']['@attributes']['email']) && !isset($bookingDetails_data['customer']['@attributes']['phone']))
              {
                $bookingDetails_data['customer']['@attributes']['phone']="NA";
                $customerDetail = $bookingDetails_data['customer']['@attributes']['first_name'].' '.$bookingDetails_data['customer']['@attributes']['last_name'].','.$bookingDetails_data['customer']['@attributes']['email'].','.$bookingDetails_data['customer']['@attributes']['phone'];
              }
              else if(!isset($bookingDetails_data['customer']['@attributes']['email']) && isset($bookingDetails_data['customer']['@attributes']['phone']))
              {
              $bookingDetails_data['customer']['@attributes']['email']="NA";
              $customerDetail = $bookingDetails_data['customer']['@attributes']['first_name'].' '.$bookingDetails_data['customer']['@attributes']['last_name'].','.$bookingDetails_data['customer']['@attributes']['email'].','.$bookingDetails_data['customer']['@attributes']['phone'];
              }
              else
              {
                $customerDetail = $bookingDetails_data['customer']['@attributes']['first_name'].' '.$bookingDetails_data['customer']['@attributes']['last_name'].','.'NA,'.','.'NA';
              }
              $amount =  $bookingDetails_data['prices']['@attributes']['net_inclusive_amt'];
              $tax_amount =isset($bookingDetails_data['prices']['@attributes']['tax_amt']) ?
                            $bookingDetails_data['prices']['@attributes']['tax_amt'] : 0 ;
              $currency =  $bookingDetails_data['prices']['@attributes']['currency'];
              }
            }
            //Saveing the booking data
            $channel_name='Agoda';
            $payment_status = 'Paid';
            $ota_hotel_details = $ota_details_model
              ->where('ota_hotel_code' ,'=', $hotel_Code)
              ->first();
            if($ota_hotel_details->hotel_id)
            {
              //agoda booking save in cmotabooking
              if($booking_status == 'ConfirmBooking' || $booking_status == 'AmendBooking'){
                $booking_status = 'Commit';
                }
                if($booking_status == 'CancelBooking'){
                  $booking_status = 'Cancel';
                }
              $bookingDetails = array('UniqueID'=>$uniqueID,'customerDetail'=>$customerDetail,'booking_status'=>$booking_status,'rooms_qty'=>$rooms_qty,'room_type'=>$room_type,'checkin_at'=>$checkin_at,'checkout_at'=>$checkout_at,'booking_date'=>$booking_date,'amount'=>$amount,'payment_status'=>$payment_status,'rate_code'=>$rate_code,'rlt'=>$rlt,'currency'=>$currency,'channel_name'=>$channel_name,'tax_amount'=>$tax_amount,'no_of_adult'=>$no_of_adult,'no_of_child'=>$no_of_child,'inclusion'=>'NA');
              $bookinginfo  = $this->bookingData->cmOtaBooking($bookingDetails,$ota_hotel_details);//this function call used for insert/update booking in database
              $db_status  = $bookinginfo['db_status'];
              $ota_booking_tabel_id =$bookinginfo['ota_booking_tabel_id'];
              $push_by = "Agoda";
              //after saving booking data call to bucket engine.
              if($db_status)
              {
                $this->instantbucket->bucketEngineUpdate($booking_status,$push_by,$ota_hotel_details,$ota_booking_tabel_id);//this function is used for booking bucket data updation
                return array("status"=>1,"message"=>"Success");
              }else{
                return array("status"=>0,"message"=>"This booking is already with us!");
              }
            }
            /*-----------------------is Inserting Booking data---------------------- */
          }
      }//end og agoda booking
      if( $bookingDetails_xml="")
      {
        return array('status'=>0,'message'=>"No bookings available!");
      }
  }
}
