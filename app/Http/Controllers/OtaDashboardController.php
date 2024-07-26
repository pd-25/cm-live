<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use DB;
use App\CmOtaBookingRead;
use App\CmOtaRoomTypeSynchronizeRead;
use App\CmOtaRatePlanSynchronizeRead;
/**
* This Controller is made for giving details about Ota dashboard
*@author Ranjit Date- 03-03-2020
*/
class OtaDashboardController extends Controller
{
    //Get ota earning
    public function selectInvoice(int $hotel_id,$from_date,$to_date,Request $request)
    {
        $from_date = date('Y-m-d',strtotime($from_date));
        $to_date = date('Y-m-d',strtotime($to_date));
        if($hotel_id)
        {
            $OTA_amount=CmOtaBookingRead::select('amount')
                        ->where([['hotel_id','=', $hotel_id],
                        ['confirm_status','=', 1],
                        ['cancel_status','=',0]])
                        ->whereDate('checkin_at','>=',$from_date)
                        ->whereDate('checkout_at','<=',$to_date)
                        ->get();
            $amount=0;
            foreach ($OTA_amount  as $amt)
            {

            if (strpos($amt->amount, 'Indian rupee') !== false)
                {
                    $amount=$amount+str_replace("Indian rupee ","",$amt->amount);
                }
                else if(strpos($amt->amount, 'INR') !== false)
                {

                    $amount=$amount+str_replace("INR","",$amt->amount);
                }
                else
                {
                    $amount=$amount+$amt->amount;
                }
            }
            $amount = round($amount);
            if($amount)
            {
                $res=array('status'=>1,'message'=>'OTA amount retrieve successfully','OTA_amount'=>$amount);
                return response()->json($res);
            }
            else{
                $res=array('status'=>0,'message'=>'OTA amount retrieve fails');
                return response()->json($res);
            }
        }
        else{
            $res=array('status'=>0,'message'=>'OTA amount retrieve fails');
                return response()->json($res);
        }
    }
    public function getRoomNightsByDateRange(int $hotel_id,string $checkin,string $checkout,Request $request){
        $checkin=date('Y-m-d',strtotime($checkin));
        $checkout=date('Y-m-d',strtotime($checkout));
        $getTotalNight=CmOtaBookingRead::select(DB::raw('SUM(DATEDIFF(checkout_at,checkin_at)) as nights'), 'channel_name')->where('hotel_id',$hotel_id)->where('checkin_at','>=',$checkin)->where('checkin_at','<=',$checkout)
        ->where('confirm_status',1)->where('cancel_status',0)->groupBy('channel_name')->get();
        $numberOfNights=0;
        foreach($getTotalNight as $details){
            $numberOfNights=$numberOfNights + (int)$details->nights;
        }
        if($numberOfNights){
            $resp=array('status'=>1,'message'=>'Number of nights fetched successfully','data'=>$numberOfNights);
            return response()->json($resp);
        }
        else{
            $resp=array('status'=>0,'message'=>'Number of nights fetching fails');
            return response()->json($resp);
        }
    }
    public function averageStay(int $hotel_id,string $checkin,string $checkout,Request $request){
        $checkin=date('Y-m-d',strtotime($checkin));
        $checkout=date('Y-m-d',strtotime($checkout));
        $getOtaAverageStay=CmOtaBookingRead::select(DB::raw('count(id) as no_of_bookings'),DB::raw('SUM(DATEDIFF(checkout_at,checkin_at)) as nights'),'channel_name')->where('hotel_id',$hotel_id)->where('checkin_at','>=',$checkin)->where('checkin_at','<=',$checkout)
        ->where('confirm_status',1)->where('cancel_status',0)->groupBy('channel_name')->get();
        $no_of_booking=0;
        $no_of_nights = 0;
        foreach($getOtaAverageStay as $details){
            if($details->no_of_bookings != 0){
                $no_of_nights=$no_of_nights + $details->nights;
                $no_of_booking=$no_of_booking + $details->no_of_bookings;
            }
        }
        if($no_of_nights > 0 && $no_of_booking > 0){
            $resp=array('status'=>1,'message'=>'Average stay fetched successfully','no_of_nights'=>$no_of_nights,'no_of_booking'=>$no_of_booking);
            return response()->json($resp);
        }
        else{
            $resp=array('status'=>0,'message'=>'Average stay fetching fails');
            return response()->json($resp);
        }
    }
    public function ratePlanPerformance(int $hotel_id,string $checkin,string $checkout,Request $request){
        $checkin=date('Y-m-d',strtotime($checkin));
        $checkout=date('Y-m-d',strtotime($checkout));
        $getOtaRatePlanPerformance=CmOtaBookingRead::select('*')->where('hotel_id',$hotel_id)->whereDate('booking_date','>=',$checkin)->whereDate('booking_date','<=',$checkout)
        ->where('confirm_status',1)->where('cancel_status',0)->get();
        $ratePlanPerformance=array();
        $ratecode=array();
        if(count($getOtaRatePlanPerformance)>0){
            foreach($getOtaRatePlanPerformance as $details){
                $rate_code=explode(',',$details->rate_code);
                foreach($rate_code as $exp){
                    $ratecode[]=$exp;
                }
            }
            $ratecode = array_count_values($ratecode);
            $getratekey = array_keys($ratecode,max($ratecode));
            if($getratekey[0] != " "){
                $hotel_rateplan_code=CmOtaRatePlanSynchronizeRead::join('kernel.rate_plan_table','cm_ota_rate_plan_synchronize.hotel_rate_plan_id','=','rate_plan_table.rate_plan_id')->join('kernel.room_type_table','cm_ota_rate_plan_synchronize.hotel_room_type_id','=','room_type_table.room_type_id')->select('room_type_table.room_type','rate_plan_table.plan_type')->where('cm_ota_rate_plan_synchronize.ota_rate_plan_id',$getratekey[0])->where('cm_ota_rate_plan_synchronize.hotel_id',$hotel_id)->first();
                if($hotel_rateplan_code){
                    $ratePlanPerformance=$hotel_rateplan_code->room_type .'('.$hotel_rateplan_code->plan_type.')';
                }
            }
        }
        if($ratePlanPerformance){
            $resp=array('status'=>1,'message'=>'Total bookings fetched successfully','data'=>$ratePlanPerformance);
            return response()->json($resp);
        }
        else{
            $resp=array('status'=>0,'message'=>'Total bookings fetching fails');
            return response()->json($resp);
        }
    }
    public function dashboardBookingDetails(int $hotel_id,$from_date,$to_date, Request $request)
    {
        $from_date  = date('Y-m-d',strtotime($from_date));
        $to_date    = date('Y-m-d',strtotime($to_date));
        $bookings = array();
        $otaBooking  = DB::select('CALL otaBookingGraph(?,?,?)',["$hotel_id","$from_date","$to_date"]);
        if(count($otaBooking)>0)
        {
          $i = 0;
          while (strtotime($from_date) <= strtotime($to_date)) {
            foreach ($otaBooking as $value) {
                  if($from_date == $value->index_date){
                    $bookings[$i]["bookings"] = $value->bookings;
                    $bookings[$i]["index_date"] = date("d-M-Y",strtotime($value->index_date));
                  }
                  else{
                    $bookings[$i]["bookings"] = 0;
                    $bookings[$i]["index_date"] = date("d-M-Y",strtotime($from_date));
                  }
              }
              $from_date = date ("Y-m-d", strtotime("+1 days", strtotime($from_date)));
              $i++;
          }
            $res=array("status"=>1,"message"=>"booking details retrive sucessfully","otaBooking"=>$bookings);
            return response()->json($res);
        }
        else
        {
          $i = 0;
          while (strtotime($from_date) <= strtotime($to_date)) {
                $from_date = date ("Y-m-d", strtotime("+1 days", strtotime($from_date)));
                $bookings[$i]["bookings"] = 0;
                $bookings[$i]["index_date"] = date("d-M-Y",strtotime($from_date));
                $i++;
            }
            $res=array("status"=>0,"message"=>"booking details retrive fails","otaBooking"=>$bookings);
            return response()->json($res);
        }
    }
    public function getOtaDetailsCheckOut(int $hotel_id,Request $request)
    {
        $today=date("Y-m-d");
        $otadetails=CmOtaBookingRead::join('cm_ota_details','cm_ota_booking.ota_id','=','cm_ota_details.ota_id')
                    ->join( 'cm_ota_credential_parameter','cm_ota_details.ota_name','=','cm_ota_credential_parameter.ota_name')
                    ->join('kernel.image_table','cm_ota_credential_parameter.ota_logo','=','image_table.image_id')
                    ->select('cm_ota_booking.id','cm_ota_booking.ota_id','unique_id','customer_details','booking_date','booking_date','checkin_at','amount','ota_logo','checkout_at','image_name')
                    ->where('cm_ota_booking.confirm_status',1)
                    ->where('cm_ota_booking.cancel_status',0)
                    ->where('checkout_at',$today)->where('cm_ota_booking.hotel_id',$hotel_id)
                    ->get();
            if(count($otadetails)>0)
            {
                $res = array('status'=>1,'message'=>'Data retrieve successfully','data'=>$otadetails);
                return response()->json($res);
            }
            else
            {
                $res = array('status'=>0,'message'=>"ota details retrieve fails");
                return response()->json($res);
            }
    }
    public function getOtaDetails(int $hotel_id,Request $request)
    {
        $today=date("Y-m-d");
        $otadetails=CmOtaBookingRead::join('cm_ota_details','cm_ota_booking.ota_id','=','cm_ota_details.ota_id')
                    ->join( 'cm_ota_credential_parameter','cm_ota_details.ota_name','=','cm_ota_credential_parameter.ota_name')
                    ->join('kernel.image_table','cm_ota_credential_parameter.ota_logo','=','image_table.image_id')
                    ->select('cm_ota_booking.id','cm_ota_booking.ota_id','unique_id','customer_details','booking_date','checkin_at','amount','image_name','checkout_at')
                    ->where('cm_ota_booking.confirm_status',1)
                    ->where('cm_ota_booking.cancel_status',0)
                    ->where('checkin_at',$today)->where('cm_ota_booking.hotel_id',$hotel_id)
                    ->get();
            if(count($otadetails)>0)
            {
                $res = array('status'=>1,'message'=>'Data retrieve successfully','data'=>$otadetails);
                return response()->json($res);
            }
            else
            {
                $res = array('status'=>0,'message'=>"ota details fetch fails");
                return response()->json($res);
            }
    }
}
