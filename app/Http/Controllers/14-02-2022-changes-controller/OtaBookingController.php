<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\UserCredential;
use App\ManageUserTable;//class name from model
use App\CmOtaBooking;//class name from model
use App\CmOtaRoomTypeSynchronize;
use App\CmOtaRatePlanSynchronize;
use DB;
//create a new class OfflineBookingController
class OtaBookingController extends Controller
{   
    public  function getOtaBookingsDateWise($from_date, $to_date, $date_type, $ota, $booking_status,$hotel_id,$booking_id) 
    {
      $bet_date="";
      $from_date=date('Y-m-d',strtotime($from_date));
      $to_date=date('Y-m-d',strtotime($to_date . ' +1 day'));
      $today=date('Y-m-d');
      
      if($date_type==2)
      {
          $bet_date='checkin_at';
      }
      else if($date_type==1)
      {
          $bet_date='booking_date';
      }
      else
      {
          $bet_date='checkin_at';
      }
      $query= DB::table('cm_ota_booking')
      ->select('id','ota_id','hotel_id','unique_id','customer_details','booking_status','channel_name','payment_status','inclusion','no_of_adult','no_of_child','rooms_qty','room_type','checkin_at','tax_amount','checkout_at','booking_date','rate_code','amount','currency','payment_status','confirm_status','cancel_status','response_xml','ip','ids_re_id','created_at','updated_at')
      ->where('hotel_id', '=', $hotel_id)
      ->whereBetween($bet_date, array($from_date, $to_date));

      if($ota!=0)
      {
        $query->where('ota_id', '=', $ota) ;
      }
     
      if($booking_status=='cancelled')
      {
        $query->where('confirm_status', '=', 1) ;
        $query->where('cancel_status', '=', 1) ;
      }
      else
      {
        $query->where('cancel_status', '=', 0) ;
        $query->where('confirm_status', '=', 1) ;
      }
      if($booking_id && $booking_id!="NA")
      {
        $query->where('unique_id', '=', $booking_id) ;
      }
      
      $data=$query->get();
      
      foreach($data as $ota_booking_data)
      {
        $ota_booking_data->inclusion = explode(',',$ota_booking_data->inclusion);
        $customer_data=explode(',',$ota_booking_data->customer_details);
        $ota_booking_data->username=$customer_data[0];
        if(isset($customer_data[1]))
        {
          $ota_booking_data->email=$customer_data[1];
        }
        else{
          $ota_booking_data->email='NA';
        }
        if(isset($customer_data[2]))
        {
          $ota_booking_data->contact=$customer_data[2];
        }
        else{
          $ota_booking_data->contact='NA';
        }
        $adult_data=explode(',',$ota_booking_data->no_of_adult);
        $child_data=explode(',',$ota_booking_data->no_of_child);
        $child_sum=0;
        $adult_sum=0;
        foreach($adult_data as $adult)
        {
          $adult_sum += $adult;
        }
        foreach($child_data as $child)
        {
          $child_sum += $child;
        }
        $ota_booking_data->no_of_adult=$adult_sum;
        $ota_booking_data->no_of_child=$child_sum;
        $ota_booking_data->rate_code=$this->getRate_plan($ota_booking_data->room_type,$ota_booking_data->ota_id,$ota_booking_data->rate_code);
        $ota_booking_data->room_type=$this->getRoom_types($ota_booking_data->room_type,$ota_booking_data->ota_id);
        if(strpos($ota_booking_data->response_xml,'<?xml')!==false){
          $ota_booking_data->response_xml=json_decode(json_encode(simplexml_load_string($ota_booking_data->response_xml)), true);
        }
        else{
          $ota_booking_data->response_xml=json_decode($ota_booking_data->response_xml);
        }
        $noshow_date=date('Y-m-d',strtotime($ota_booking_data->checkin_at . ' +2 day'));
        if($today >= date('Y-m-d',strtotime($ota_booking_data->checkin_at)) && $today <= $noshow_date && $ota_booking_data->confirm_status==1 && $ota_booking_data->cancel_status==0)
        {
          $ota_booking_data->no_show=1;
        }
        else{
          $ota_booking_data->no_show=0;
        }
      }
      if(sizeof($data)>0)
      {
          $res=array("status"=>1,"message"=>"Booking data retrived successfully!","data"=>$data);
          return response()->json($res);
      }
      else
      {
          $res=array("status"=>0,"message"=>"Booking data retrival failed!");
          return response()->json($res);
      }
  }
  public function getRoom_types($room_type,$ota_id)
  {
    $cmOtaRoomTypeSynchronize= new CmOtaRoomTypeSynchronize();
    $room_types=explode(',',$room_type);
    $hotel_room_type=array();
    foreach($room_types as $ota_room_type)
    {
      $room=$cmOtaRoomTypeSynchronize->getRoomType($ota_room_type,$ota_id);
      //print_r($room);
      if($room === 0)
      {
        array_push($hotel_room_type,"Room type is not synced with OTA");
      }
      else
      {
        array_push($hotel_room_type,$room);
      }
    }
    return implode(',',$hotel_room_type);
  }
  public function getRate_plan($ota_room_type,$ota_id,$rate_plan_id)
  {
    $cmOtaRatePlanSynchronize= new CmOtaRatePlanSynchronize();
    $rate_plan_ids=explode(',',$rate_plan_id);
    $hotel_rate_plan=array();
    foreach($rate_plan_ids as $ota_rate_plan_id)
    {
     array_push($hotel_rate_plan,$cmOtaRatePlanSynchronize->getRoomRatePlan($ota_id,$ota_rate_plan_id));
    }

    return implode(',',$hotel_rate_plan);
  }
}