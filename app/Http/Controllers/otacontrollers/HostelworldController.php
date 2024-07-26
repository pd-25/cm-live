<?php
namespace App\Http\Controllers\otacontrollers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Input;
use Validator;
use App\CmOtaDetailsRead;
use App\CmOtaAllAutoPush;
use App\CmOtaBooking;
use DB;
use App\Http\Controllers\otacontrollers\BookingDataInsertationController;
use App\Http\Controllers\otacontrollers\InstantBucketController;
use App\Http\Controllers\otacontrollers\CurlController;
use App\Http\Controllers\Controller;
use App\Jobs\RunBucket;
/**
 * HostelworldController used for getting the confirm,cancel and modify booking from hostelworld.
 * @auther Ranjit 
 * @date-29/10/2021
 */
class HostelworldController extends Controller
{
    protected $hostelworld_bookingData,$curlcall,$instantbucket;
    public function __construct(BookingDataInsertationController $hostelworld_bookingData,CurlController $curlcall,InstantBucketController $instantbucket)
    {
      $this->hostelworld_bookingData=$hostelworld_bookingData;
      $this->curlcall=$curlcall;
      $this->instantbucket=$instantbucket;
    }
    public function actionIndex(Request $request)
    {
        $ota_details_model          = new CmOtaDetailsRead();
        $OtaAllAutoPushModel        = new CmOtaAllAutoPush();


        $getOtaHotelCodeInfo = CmOtaDetailsRead::select('*')
                          ->where('ota_name','Hostelworld')
                          ->where('is_active',1)
                          ->where('is_status',1)
                          ->orderBy('ota_id','desc')
                          ->get();
        foreach($getOtaHotelCodeInfo as $info){
          $new_booking_count     = $this->getNewBookingCount($info->auth_parameter,$info->ota_hotel_code);
          if(isset($new_booking_count["api"]["@attributes"]["status"]) && $new_booking_count["api"]["@attributes"]["status"]=="Success"){
            if(isset($new_booking_count["properties"]["property"])){
              $property_details = $new_booking_count["properties"]["property"];
              foreach($property_details as $info){
                $ota_hotel_code         = isset($info["@attributes"]["number"])?$info["@attributes"]["number"]:$info["number"];
                $booking_number         = isset($info["@attributes"]["bookings"])?$info["@attributes"]["bookings"]:$info["bookings"];
                $booking_number         = (int)$booking_number;
                if($booking_number > 0){
                    $getOtaHotelCode        = CmOtaDetailsRead::select('*')
                                            ->where('ota_hotel_code', $ota_hotel_code)
                                            ->where('is_active',1)
                                            ->where('is_status',1)
                                            ->orderBy('ota_id','desc')
                                            ->first();
                    $auth_parameter         = json_decode($getOtaHotelCode->auth_parameter);
                    $consumer_key           = trim($auth_parameter->consumer_key);
                    $consumer_signature     = trim($auth_parameter->consumer_signature);
                    $url = "https://property.xsapi.webresint.com/2.0/getnewbookings/".$ota_hotel_code.".xml?consumer_key=".$consumer_key."&consumer_signature=".$consumer_signature."";
                    $ch = curl_init();
                    curl_setopt($ch,CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_HEADER, 0);
                    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
                    curl_setopt($ch,CURLOPT_HEADER, false); 
                    curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, false); 
                    curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, false);   
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET'); 
                    $reservations=curl_exec($ch);
                    curl_close($ch);
                    $OtaAllAutoPushModel->respones_xml = trim($reservations);
                    $OtaAllAutoPushModel->ota_name = 'HostelWorld';
                    $OtaAllAutoPushModel->save();
                    $rlt          = $reservations;
                    $reservations = stripslashes($reservations);
                    $reservations = preg_replace("/<programbeskrivelse>[\d\D]*?<\/provision>/", "", $reservations);
                    $reservations = str_replace(array("&amp;", "&"), array("&", "&amp;"), $reservations);
                    $reservations_array = json_decode(json_encode(simplexml_load_string($reservations)),true);
                    if(isset($reservations_array["api"]["@attributes"]["status"]) && $reservations_array["api"]["@attributes"]["status"]=="Success"){
                    if($reservations_array["bookings"]["booking"]){
                      $res_array            = $reservations_array["bookings"]["booking"];
                      $Hotel_Code           = $ota_hotel_code;
                      if(isset($res_array[0])){
                        foreach($res_array as $booking_info){
                          $UniqueID             = $booking_info["@attributes"]["ref"];
                          $first_name           = isset($booking_info["firstname"])?$booking_info["firstname"]:'NA';
                          $last_name            = isset($booking_info["lastname"])?$booking_info["lastname"]:'NA';
                          $email_id             = isset($booking_info["email"])?$booking_info["email"]:'NA';
                          $phone_no             = isset($booking_info["tel"])?$booking_info["tel"]:'NA';
                          $customerDetail       = $first_name.','.$last_name.','.$email_id.','.$phone_no;
                          $booking_status       = 'Commit';
                          $booking_date         = date('Y-m-d h:i:s',strtotime($booking_info["creationdatetime"]));
                          $channel_name         = 'Hostelworld';
                          $amount               = 0;
                          $collection_amount    = $booking_info["bill"]["@attributes"]["due"];
                          $room_type_info       = array();
                          $no_of_adult_info     = array();
                          $rooms_qty_info       = array();
                          $rate_code_info       = array();
                          foreach($booking_info["nights"] as $key => $rooms){
                            $room_type_info[] = $rooms["@attributes"]["roomtypeid"];
                            $get_size = sizeof($booking_info["nights"]["roomtype"]["night"]);
                            foreach($rooms["night"] as $key => $rate_info){
                                if($key == 0){
                                  $checkin_at = $rate_info["@attributes"]["date"];
                                  $rate_code_info[]= $rate_info["@attributes"]["rateplanid"];
                                  $no_of_adult_info[] = $rate_info["@attributes"]["pax"];
                                  if(isset($rate_info["@attributes"]["rooms"])){
                                    $rooms_qty_info =  $rate_info["@attributes"]["rooms"];
                                  }
                                  if(!isset($rate_info["@attributes"]["rooms"])){
                                    $rooms_qty_info[]  =  1;
                                  }
                                }
                                if($key == $get_size-1){
                                  $checkout_at = date('Y-m-d',strtotime($rate_info["@attributes"]["date"].'+1 day'));
                                }
                                $price_info = (int)$rate_info["@attributes"]["price"];
                                $amount  = $amount + $price_info;
                            }
                          }
                          $room_type = implode(',',$room_type_info);
                          $rate_code = implode(',',$rate_code_info);
                          $no_of_adult = implode(',',$no_of_adult_info);
                          $no_of_child = 0;
                          $inclusion = 'NA';
                          $rooms_qty = implode(',',$rooms_qty_info);
                          $room_price = $amount;
                          $payment_status = isset($booking_info["bill"]["@attributes"]["paymenttype"])?$booking_info["bill"]["@attributes"]["paymenttype"]:'NA';
                          $tax_amount = 0;
                          $currency = $booking_info["bill"]["@attributes"]["currency"];
        
                          $bookingDetails = array('UniqueID'=>$UniqueID,'customerDetail'=>$customerDetail,'booking_status'=>$booking_status,'rooms_qty'=>$rooms_qty,'room_price' =>$room_price,'room_type'=>$room_type,'checkin_at'=>$checkin_at,'checkout_at'=>$checkout_at,'booking_date'=>$booking_date,'amount'=>$amount,'collection_amount'=>$collection_amount,'payment_status'=>$payment_status,'rate_code'=>$rate_code,'rlt'=>$rlt,'currency'=>$currency,'channel_name'=>$channel_name,'tax_amount'=>$tax_amount,'no_of_adult'=>$no_of_adult,'no_of_child'=>$no_of_child,'inclusion'=>$inclusion);
                          $bookinginfo  = $this->hostelworld_bookingData->cmOtaBooking($bookingDetails,$getOtaHotelCode);//this function call used for insert/update booking in database
                          $db_status  = $bookinginfo['db_status'];
                          $ota_booking_tabel_id =$bookinginfo['ota_booking_tabel_id'];
                          //Checking db status
                          
                          if($db_status)
                          {
                              $push_by = "Hostelworld";
                                $this->instantbucket->bucketEngineUpdate($booking_status,$push_by,$getOtaHotelCode,$ota_booking_tabel_id,0);//this function is used for booking bucket data updation
                                //booking ack bookings for hostel world
                                $this->ackBookings($UniqueID,$ota_hotel_code,$consumer_key,$consumer_signature);
                          }
                        }
                      }
                      else{
                        $UniqueID             = $res_array["@attributes"]["ref"];
                        $first_name           = isset($res_array["firstname"])?$res_array["firstname"]:'NA';
                        $last_name            = isset($res_array["lastname"])?$res_array["lastname"]:'NA';
                        $email_id             = isset($res_array["email"])?$res_array["email"]:'NA';
                        $phone_no             = isset($res_array["tel"])?$res_array["tel"]:'NA';
                        $customerDetail       = $first_name.','.$last_name.','.$email_id.','.$phone_no;
                        $booking_status       = 'Commit';
                        $booking_date         = date('Y-m-d h:i:s',strtotime($res_array["creationdatetime"]));
                        $channel_name         = 'Hostelworld';
                        $amount               = 0;
                        $collection_amount    = $res_array["bill"]["@attributes"]["due"];
                        $room_type_id_checker = array();
                        foreach($res_array["nights"] as $key => $rooms){
                          $room_type_info[] = $rooms["@attributes"]["roomtypeid"];
                          $get_size = sizeof($res_array["nights"]["roomtype"]["night"]);
                          foreach($rooms["night"] as $key => $rate_info){
                              if($key == 0){
                                  $checkin_at = $rate_info["@attributes"]["date"];
                                  $rate_code_info[]= $rate_info["@attributes"]["rateplanid"];
                                  $no_of_adult_info[] = $rate_info["@attributes"]["pax"];
                                  if(isset($rate_info["@attributes"]["rooms"])){
                                    $rooms_qty_info =  $rate_info["@attributes"]["rooms"];
                                  }
                                  if(!isset($rate_info["@attributes"]["rooms"])){
                                    $rooms_qty_info[]  =  1;
                                  }
                              }
                              if($key == $get_size-1){
                                $checkout_at = date('Y-m-d',strtotime($rate_info["@attributes"]["date"].'+1 day'));
                              }
                              $price_info = (int)$rate_info["@attributes"]["price"];
                              $amount  = $amount + $price_info;
                          }
                        }
                        $room_type = implode(',',$room_type_info);
                        $rate_code = implode(',',$rate_code_info);
                        $no_of_adult = implode(',',$no_of_adult_info);
                        $no_of_child = 0;
                        $inclusion = '';
                        $rooms_qty = implode(',',$rooms_qty_info);
                        $room_price = $amount;
                        $payment_status = isset($res_array["bill"]["@attributes"]["paymenttype"])?$res_array["bill"]["@attributes"]["paymenttype"]:'NA';
                        $tax_amount = 0;
                        $currency = $res_array["bill"]["@attributes"]["currency"];
        
                        $bookingDetails = array('UniqueID'=>$UniqueID,'customerDetail'=>$customerDetail,'booking_status'=>$booking_status,'rooms_qty'=>$rooms_qty,'room_price' =>$room_price,'room_type'=>$room_type,'checkin_at'=>$checkin_at,'checkout_at'=>$checkout_at,'booking_date'=>$booking_date,'amount'=>$amount,'collection_amount'=>$collection_amount,'payment_status'=>$payment_status,'rate_code'=>$rate_code,'rlt'=>$rlt,'currency'=>$currency,'channel_name'=>$channel_name,'tax_amount'=>$tax_amount,'no_of_adult'=>$no_of_adult,'no_of_child'=>$no_of_child,'inclusion'=>$inclusion);
                        $bookinginfo  = $this->hostelworld_bookingData->cmOtaBooking($bookingDetails,$getOtaHotelCode);//this function call used for insert/update booking in database
                        $db_status  = $bookinginfo['db_status'];
                        $ota_booking_tabel_id =$bookinginfo['ota_booking_tabel_id'];
                        //Checking db status
                        
                        if($db_status)
                        {
                            $push_by = "Hostelworld";
                              $this->instantbucket->bucketEngineUpdate($booking_status,$push_by,$getOtaHotelCode,$ota_booking_tabel_id,0);//this function is used for booking bucket data updation
                              //booking ack bookings for hostel world
                              $this->ackBookings($UniqueID,$ota_hotel_code,$consumer_key,$consumer_signature);
                        }
                      }
                    }
                  }
                }
              }
            }
            else{
              $resp = array('status'=>0,'message'=>'No Booking found');
              return response()->json($resp);
            }
          }
          else{
            $resp = array('status'=>0,'message'=>'No Booking found');
            return response()->json($resp);
          }
      }
    }
    public function ackBookings($UniqueID,$ota_hotel_code,$consumer_key,$consumer_signature){
      $url = "https://property.xsapi.webresint.com/2.0/ackbookings/".$ota_hotel_code.".xml?consumer_key=".$consumer_key."&consumer_signature=".$consumer_signature."";
      $hostelworld_xml = '<request><bookings><confirm ref="'.$UniqueID.'"/></bookings></request>';
      $ch = curl_init();
      curl_setopt( $ch, CURLOPT_URL, $url );
      curl_setopt( $ch, CURLOPT_POST, true );
      curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
      curl_setopt( $ch, CURLOPT_POSTFIELDS, $hostelworld_xml);
      $ack_bookings = curl_exec($ch);
      curl_close($ch);
      $ack_bookings = json_decode(json_encode(simplexml_load_string($ack_bookings)),true);

    }
    public function getNewBookingCount($auth_parameter,$ota_hotel_code){
      $auth_parameter         = json_decode($auth_parameter);
      $consumer_key           = trim($auth_parameter->consumer_key);
      $consumer_signature     = trim($auth_parameter->consumer_signature);
      $url = "https://property.xsapi.webresint.com/2.0/getnewbookingscount/".$ota_hotel_code.".xml?consumer_key=".$consumer_key."&consumer_signature=".$consumer_signature."";
      $ch = curl_init();
      curl_setopt($ch,CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_HEADER, 0);
      curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
      curl_setopt($ch,CURLOPT_HEADER, false); 
      curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, false); 
      curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, false);   
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET'); 
      $new_bookings=curl_exec($ch);
      $new_bookings = json_decode(json_encode(simplexml_load_string($new_bookings)),true);
      return $new_bookings;
    }
    public function setCancellation(){
      $hotel_id = 1953;
      $unique_id="311449-522366177";
      $reason = "Plan is cancelled";
      $getOtaHotelCode  = CmOtaDetailsRead::select('*')
                          ->where('hotel_id', $hotel_id)
                          ->where('ota_name','Hostelworld')
                          ->where('is_active',1)
                          ->where('is_status',1)
                          ->orderBy('ota_id','desc')
                          ->first();
      $ota_hotel_code       = $getOtaHotelCode->ota_hotel_code;
      $auth_parameter       = json_decode($getOtaHotelCode->auth_parameter);
      $consumer_key         = trim($auth_parameter->consumer_key);
      $consumer_signature   = trim($auth_parameter->consumer_signature);
      $url = "https://property.xsapi.webresint.com/2.0/setcancellations/".$ota_hotel_code.".xml?consumer_key=".$consumer_key."&consumer_signature=".$consumer_signature."";
      $hostelworld_xml = '<request><cancellations><cancel ref="'.$unique_id.'">'.$reason.'</cancel></cancellations></request>';
      $ch = curl_init();
      curl_setopt( $ch, CURLOPT_URL, $url );
      curl_setopt( $ch, CURLOPT_POST, true );
      curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
      curl_setopt( $ch, CURLOPT_POSTFIELDS, $hostelworld_xml);
      $set_cancellation = curl_exec($ch);
      curl_close($ch);
    }
    public function makeTestBooking(){
      $ota_hotel_code = 311449;
      $consumer_key ='bookingjini';
      $consumer_signature ='8d509819c687f8dbc48e680e0b8ea21afed3d935';
      $url = "https://property.xsapi.webresint.com/2.0/addtestbookings/".$ota_hotel_code.".xml?consumer_key=".$consumer_key."&consumer_signature=".$consumer_signature."&bookings=1&cancellations=0";
      $ch = curl_init();
      curl_setopt($ch,CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_HEADER, 0);
      curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
      curl_setopt($ch,CURLOPT_HEADER, false); 
      curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, false); 
      curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, false);   
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET'); 
      $reservations=curl_exec($ch);
      dd($reservations);
    }
}
