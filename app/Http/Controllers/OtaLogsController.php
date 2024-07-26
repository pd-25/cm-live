<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use DB;
use App\CmOtaDetails;
use App\CmOtaDetailsRead;
use App\LogTable;
use App\RateUpdateLog;
use App\BookingLog;
use App\CmOtaRoomTypeSynchronize;
use App\CmOtaRatePlanSynchronize;
use App\CmOtaRoomTypeSynchronizeRead;
use App\CmOtaRatePlanSynchronizeRead;
class OtaLogsController extends Controller
{
    public function otaInventoryDetails(int $hotel_id,string $from_date,string $to_date,int $room_type_id,int $selected_be_ota_id,Request $request){
        $from_date=date("Y-m-d", strtotime($from_date));
        $to_date=date("Y-m-d", strtotime($to_date));
        if($selected_be_ota_id > 0){
            $inventory_info=LogTable::join('cm_ota_details','log_table.ota_id','=','cm_ota_details.ota_id')
                ->join('ota_inventory','log_table.inventory_ref_id','=','ota_inventory.inventory_id')
                ->join('kernel.admin_table','ota_inventory.user_id','=','admin_table.admin_id')
                ->join('kernel.room_type_table','ota_inventory.room_type_id','=','room_type_table.room_type_id')
                ->select('cm_ota_details.ota_name','admin_table.first_name','admin_table.last_name','log_table.updated_at','log_table.status','log_table.user_id','ota_inventory.date_from','ota_inventory.date_to','ota_inventory.no_of_rooms','room_type_table.room_type')
                ->whereDate('log_table.created_at','>=',$from_date)
                ->whereDate('log_table.created_at','<=',$to_date)
                ->where('ota_inventory.room_type_id',$room_type_id)
                ->where("log_table.hotel_id",$hotel_id)
                ->where('log_table.inventory_ref_id','>', 0)
                ->where('log_table.ota_id', $selected_be_ota_id)
                ->orderBy('log_table.id','DESC')
                ->paginate(25);

        }
        else{
            $res=array('status'=>0,'message'=>'Please Provide Ota details');
            return response()->json($res);
        }

        if(sizeof($inventory_info)<=0){
            $res=array('status'=>0,'message'=>'details retrive fails');
            return response()->json($res);
        }
        $res=array('status'=>1,'message'=>'details retrive sucessfully','data'=> $inventory_info);
        return response()->json($res);
    }
    public function OtaRateplanDetails(int $hotel_id,string $from_date,string $to_date,int $rate_plan_id,int $selected_be_ota_id,int $room_type_id,Request $request){
        $from_date=date("Y-m-d", strtotime($from_date));
        $to_date=date("Y-m-d", strtotime($to_date));

        if($selected_be_ota_id > 0){
            $rate_plan=CmOtaDetailsRead::
            join('rate_update_logs','cm_ota_details.ota_id','=','rate_update_logs.ota_id')
            ->join('ota_rateupdate','rate_update_logs.rate_ref_id','=','ota_rateupdate.rate_plan_log_id')
            ->leftJoin('kernel.admin_table','rate_update_logs.user_id','=','admin_table.admin_id')
            ->join('kernel.rate_plan_table','ota_rateupdate.rate_plan_id','=','rate_plan_table.rate_plan_id')
            ->join('kernel.room_type_table','ota_rateupdate.room_type_id','=','room_type_table.room_type_id')
            ->select('cm_ota_details.ota_name','admin_table.first_name','admin_table.last_name','rate_update_logs.updated_at','rate_update_logs.status','rate_update_logs.user_id','ota_rateupdate.from_date','ota_rateupdate.to_date','ota_rateupdate.bar_price','ota_rateupdate.multiple_occupancy','room_type_table.room_type','rate_plan_table.plan_type','ota_rateupdate.rate_plan_id')
            ->whereDate('rate_update_logs.created_at','>=',$from_date)
            ->whereDate('rate_update_logs.created_at','<=',$to_date)
            ->where('ota_rateupdate.rate_plan_id',$rate_plan_id)
            ->where("rate_update_logs.hotel_id",$hotel_id)
            ->where('rate_update_logs.rate_ref_id','>', 0)
            ->where('rate_update_logs.ota_id', $selected_be_ota_id)
            ->orderBy('rate_update_logs.id','SORT_DESC')
            ->paginate(25);
        }
        else{
            $res=array('status'=>0,'message'=>'Please Provide Ota details');
            return response()->json($res);
        }
        foreach($rate_plan as $plan){
            $data=explode(',',$plan->multiple_occupancy);
            $plan->multiple_occupancy=$data[0];
            if(!$plan->first_name){
                $plan->first_name="Administrator";
            }
        }
        if(sizeof($rate_plan)<=0){
            $res=array('status'=>0,'message'=>'details retrive fails');
            return response()->json($res);
        }
        $res=array('status'=>1,'message'=>'details retrive sucessfully','data'=>$rate_plan);
        return response()->json($res);
    }
    /**
    * Lists Bookings Log models.
    * @return mixed
    * @auther ranjit
    */
    public function bookingDetails(int $hotel_id,string $from_date,string $to_date,Request $request){
        $from_date=date("Y-m-d", strtotime($from_date));
        $to_date=date("Y-m-d", strtotime($to_date));
        $logModel          = new BookingLog();
        $booking_details   = $logModel
                                ->join('cm_ota_booking','booking_logs.booking_ref_id','=','cm_ota_booking.id')
                                ->join('cm_ota_booking_push_bucket','cm_ota_booking.id','=','cm_ota_booking_push_bucket.ota_booking_tabel_id')
                                ->select('booking_logs.status','cm_ota_booking_push_bucket.ota_booking_tabel_id','cm_ota_booking_push_bucket.ota_name','cm_ota_booking_push_bucket.push_by','cm_ota_booking.booking_status','cm_ota_booking.ota_id','cm_ota_booking.rate_code','cm_ota_booking.rooms_qty','cm_ota_booking.room_type','cm_ota_booking.checkin_at','cm_ota_booking.checkout_at','cm_ota_booking.booking_date')
                                ->whereDate('booking_logs.created_at','>=',$from_date)
                                ->whereDate('booking_logs.created_at','<=',$to_date)
                                ->where("booking_logs.hotel_id",$hotel_id)
                                ->Where('booking_logs.booking_ref_id','>', 0)
                                ->orderBy('booking_logs.id','SORT_DESC')
                                ->paginate(25);

        if(sizeof($booking_details) === 0 ){
            $booking_details   = LogTable::
            join('cm_ota_booking','log_table.booking_ref_id','=','cm_ota_booking.id')
            //->join('cm_ota_booking_push_bucket','cm_ota_booking.id','=','cm_ota_booking_push_bucket.ota_booking_tabel_id')
            //->select('log_table.status','cm_ota_booking_push_bucket.ota_booking_tabel_id','cm_ota_booking_push_bucket.ota_name','cm_ota_booking_push_bucket.push_by','cm_ota_booking.booking_status','cm_ota_booking.ota_id','cm_ota_booking.rate_code','cm_ota_booking.rooms_qty','cm_ota_booking.room_type','cm_ota_booking.checkin_at','cm_ota_booking.checkout_at','cm_ota_booking.booking_date')
            ->whereDate('log_table.created_at','>=',$from_date)
            ->whereDate('log_table.created_at','<=',$to_date)
            ->where("log_table.hotel_id",$hotel_id)
            ->Where('log_table.booking_ref_id','>', 0)
            ->orderBy('log_table.id','SORT_DESC')
            ->paginate(25);
        }
        foreach($booking_details as $booking){
            $booking->rate_code=$this->getRate_plan($booking->room_type,$booking->ota_id,$booking->rate_code);
            $booking->room_type=$this->getRoom_types($booking->room_type,$booking->ota_id);
        }
        if(sizeof($booking_details)<=0){
            $res=array('status'=>0,'message'=>'details retrive fails');
            return response()->json($res);
        }
        $res=array('status'=>1,'message'=>'details retrive sucessfully','data'=>$booking_details);
        return response()->json($res);
    }
    /**
    * returning room details
    * @auther ranjit
    */
    public function getRoom_types($room_type,$ota_id){
        $cmOtaRoomTypeSynchronize= new CmOtaRoomTypeSynchronizeRead();
        $room_types=explode(',',$room_type);
        $hotel_room_type=array();
        foreach($room_types as $ota_room_type){
            array_push($hotel_room_type,$cmOtaRoomTypeSynchronize->getRoomType($ota_room_type,$ota_id));
        }
        return implode(',',$hotel_room_type);
    }
    /**
    * returning rate plan details
    * @auther ranjit
    */
    public function getRate_plan($ota_room_type,$ota_id,$rate_plan_id){
        $cmOtaRatePlanSynchronize= new CmOtaRatePlanSynchronizeRead();
        $rate_plan_ids=explode(',',$rate_plan_id);
        $hotel_rate_plan=array();
        foreach($rate_plan_ids as $ota_rate_plan_id){
        array_push($hotel_rate_plan,$cmOtaRatePlanSynchronize->getRoomRatePlan($ota_id,$ota_rate_plan_id));
        }
        return implode(',',$hotel_rate_plan);
    }
}
