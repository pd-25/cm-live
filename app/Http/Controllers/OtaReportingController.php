<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Validator;
use DB;
use App\OTABooking;
use App\CmOtaDetails;
use App\CmOtaRatePlanSynchronize;
use App\Http\Controllers\Controller;
/**
* Used for new report implementation
* @author Ranjit kumar dash
* date 03/03/2020
*/
class OtaReportingController extends Controller
{
    public function getRoomNightsByDateRange(int $hotel_id,string $checkin,string $checkout,Request $request){
        $checkin=date('Y-m-d',strtotime($checkin));
        $checkout=date('Y-m-d',strtotime($checkout));
        $getTotalNight=OTABooking::select(DB::raw('SUM(DATEDIFF(checkout_at,checkin_at)) as nights'), 'channel_name')->where('hotel_id',$hotel_id)->where('checkin_at','>=',$checkin)->where('checkin_at','<=',$checkout)
        ->where('confirm_status',1)->where('cancel_status',0)->groupBy('channel_name')->get();
        $numberOfNights=array();
        foreach($getTotalNight as $details){
            $numberOfNights[$details->channel_name]=(int)$details->nights;
        }
        if(sizeof($numberOfNights)>0){
            $resp=array('status'=>1,'message'=>'Number of nights fetched successfully','data'=>$numberOfNights);
            return response()->json($resp);
        }
        else{
            $numberOfNights['NA']=0;
            $resp=array('status'=>0,'message'=>'Number of nights fetching fails','data'=>$numberOfNights);
            return response()->json($resp);
        }
    }
    public function totalRevenueOtaWise(int $hotel_id,$checkin,$checkout,Request $request){
        $checkin=date('Y-m-d',strtotime($checkin));
        $checkout=date('Y-m-d',strtotime($checkout));
        $getTotalAmount=OTABooking::select(DB::raw('SUM(amount) as amt'), 'channel_name')->where('hotel_id',$hotel_id)->where('checkin_at','>=',$checkin)->where('checkin_at','<=',$checkout)
        ->where('confirm_status',1)->where('cancel_status',0)->groupBy('channel_name')->get();
        $totalAmount=array();
        foreach($getTotalAmount as $details){
            $totalAmount[$details->channel_name]=round($details->amt);
        }
        if(sizeof($totalAmount)>0){
            $resp=array('status'=>1,'message'=>'Total amount fetched successfully','data'=>$totalAmount);
            return response()->json($resp);
        }
        else{
            $totalAmount['NA']=0;
            $resp=array('status'=>0,'message'=>'Total amount fetching fails','data'=>$totalAmount);
            return response()->json($resp);
        }
    }
    public function numberOfBookings(int $hotel_id,string $checkin,string $checkout,Request $request){
        $checkin=date('Y-m-d',strtotime($checkin));
        $checkout=date('Y-m-d',strtotime($checkout));
        $getTotalOtaBookings=OTABooking::select(DB::raw('count(id) as no_of_bookings'), 'channel_name')->where('hotel_id',$hotel_id)->whereDate('booking_date','>=',$checkin)->whereDate('booking_date','<=',$checkout)
        ->where('confirm_status',1)->where('cancel_status',0)->groupBy('channel_name')->get();
        $totalBookings=array();
        foreach($getTotalOtaBookings as $details){
            $totalBookings[$details->channel_name]=round($details->no_of_bookings);
        }
        if(sizeof($totalBookings)>0){
            $resp=array('status'=>1,'message'=>'Total bookings fetched successfully','data'=>$totalBookings);
            return response()->json($resp);
        }
        else{
            $totalBookings['NA']=0;
            $resp=array('status'=>0,'message'=>'Total bookings fetching fails','data'=>$totalBookings);
            return response()->json($resp);
        }
    }
    public function averageStay(int $hotel_id,string $checkin,string $checkout,Request $request){
        $checkin=date('Y-m-d',strtotime($checkin));
        $checkout=date('Y-m-d',strtotime($checkout));
        $getOtaAverageStay=OTABooking::select(DB::raw('count(id) as no_of_bookings'),DB::raw('SUM(DATEDIFF(checkout_at,checkin_at)) as nights'),'channel_name')->where('hotel_id',$hotel_id)->where('checkin_at','>=',$checkin)->where('checkin_at','<=',$checkout)
        ->where('confirm_status',1)->where('cancel_status',0)->groupBy('channel_name')->get();
        $averageStay=array();
        foreach($getOtaAverageStay as $details){
            if($details->no_of_bookings != 0){
                $avg_stay=$details->nights/$details->no_of_bookings;
                $averageStay[$details->channel_name]=number_format((float)$avg_stay, 2, '.', '');
            }
        }
        if(sizeof($averageStay)>0){
            $resp=array('status'=>1,'message'=>'Average stay fetched successfully','data'=>$averageStay);
            return response()->json($resp);
        }
        else{
            $averageStay['NA']=0;
            $resp=array('status'=>0,'message'=>'Average stay fetching fails','data'=>$averageStay);
            return response()->json($resp);
        }
    }
    public function ratePlanPerformance(int $hotel_id,string $checkin,string $checkout,Request $request){
        $checkin=date('Y-m-d',strtotime($checkin));
        $checkout=date('Y-m-d',strtotime($checkout));
        $getOtaRatePlanPerformance=OTABooking::select('*')->where('hotel_id',$hotel_id)->whereDate('booking_date','>=',$checkin)->whereDate('booking_date','<=',$checkout)
        ->where('confirm_status',1)->where('cancel_status',0)->get();
        $ratePlanPerformance=array();
        $ratecode=array();
        if(sizeof($getOtaRatePlanPerformance)>0){
            foreach($getOtaRatePlanPerformance as $details){
                $rate_code=explode(',',$details->rate_code);
                foreach($rate_code as $exp){
                    $ratecode[]=$exp;
                }
            }
            $ratecode = array_count_values($ratecode);
            $tooltip = array();
            foreach($ratecode as $key => $val){
                if($key != " "){
                    $hotel_rateplan_code=CmOtaRatePlanSynchronize::join('kernel.rate_plan_table','cm_ota_rate_plan_synchronize.hotel_rate_plan_id','=','rate_plan_table.rate_plan_id')->join('kernel.room_type_table','cm_ota_rate_plan_synchronize.hotel_room_type_id','=','room_type_table.room_type_id')->select('room_type_table.room_type','rate_plan_table.plan_type')->where('cm_ota_rate_plan_synchronize.ota_rate_plan_id',$key)->where('cm_ota_rate_plan_synchronize.hotel_id',$hotel_id)->first();
                    if($hotel_rateplan_code){
                        $room_type_name = substr($hotel_rateplan_code->room_type,0,6);
                        $ratePlanPerformance[$room_type_name.'...'.'('.$hotel_rateplan_code->plan_type.')']=$val;
                        $tooltip[]= $hotel_rateplan_code->room_type.'('.$hotel_rateplan_code->plan_type.')';
                    }
                }
            }
        }
        if(sizeof($ratePlanPerformance)>0){
            $resp=array('status'=>1,'message'=>'Total bookings fetched successfully','data'=>$ratePlanPerformance,'tooltip'=>$tooltip);
            return response()->json($resp);
        }
        else{
            $ratePlanPerformance['NA']=0;
            $resp=array('status'=>0,'message'=>'Total bookings fetching fails','data'=>$ratePlanPerformance);
            return response()->json($resp);
        }
    }
}
