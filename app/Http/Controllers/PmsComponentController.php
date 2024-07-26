<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Validator;
use App\HotelInformation;
use App\MasterRoomType;
use App\MasterRatePlan;
use App\MasterHotelRatePlan;
use App\PmsAccount;
use App\PmsRequest;
use App\User;
use App\IdsRoom;
use App\CmOtaRatePlanSynchronize;
use App\CmOtaRoomTypeSynchronize;
use App\Http\Controllers\PmsService;
use DB;
class PmsComponentController extends Controller{
    public static function checkApi($api_key){
        $apiDetaiils=PmsAccount::where('api_key',$api_key)->first();
        return $apiDetaiils;
    }
    public static function serviceRequest($api_key, $hotel_id, $ip, $request_for){
        $serviceRequestModel= new PmsRequest();

            $serviceRequestModel->api_key     = $api_key;
            $serviceRequestModel->hotel_id    = $hotel_id;
            $serviceRequestModel->ip          = $ip;
            $serviceRequestModel->request_for = $request_for;
            $serviceRequestModel->save();
    }
    //Fetch Hotel Details Id wise
    public static function searchHotelIdwise($id){
      $hotel_details = HotelInformation::select('hotel_id','hotel_name')
                             ->where('hotel_id',$id)
                             ->first();
      return $hotel_details;
    }
     // Fetch Room Type details Hotel id wise
    public static function searchRoomtypeHotelwise($hotel_id){
        $roomtype_details = MasterRoomType::select('room_type_id', 'room_type', 'total_rooms')
                                  ->where('hotel_id',$hotel_id)
                                  ->where('is_trash',0)
                                  ->get();
        return $roomtype_details;
    }
    public static function SearchRoomratePlanHotelWise($room_type_id){
        $inventory_details=DB::connection('kernel')->select(DB::raw("select t1.room_rate_plan_id, t1.bar_price, t1.extra_adult_price, t1.extra_child_price, t1.before_days_offer, t1.stay_duration_offer, t1.lastminute_offer, t1.rate_plan_id, t1.room_type_id, t1.from_date, t1.to_date, t1.multiple_occupancy, t3.plan_type, t3.plan_name from (select room_type_id, rate_plan_id, max(updated_at) AS MaxCreated from room_rate_plan WHERE room_type_id=$room_type_id group by room_type_id,rate_plan_id) t2, room_rate_plan t1, rate_plan_table t3 WHERE t2.room_type_id = t1.room_type_id and t2.rate_plan_id = t1.rate_plan_id and t2.MaxCreated = t1.updated_at AND t1.rate_plan_id=t3.rate_plan_id AND t1.room_type_id=$room_type_id"));
        return $inventory_details;
    }
    public static function searchAllBookings($hotel_id, $last_booking_id, $booking_date){
        if($last_booking_id!=''){
            $booking_id = substr($last_booking_id, 6);
            if($booking_id == false){
              $booking_id = 0;
            }
        }
        else{
            $booking_id=0;
        }
        $booked_room_details = DB::connection('be')->select(DB::raw("SELECT DISTINCT (b.user_id), a.room_type, a.ref_no, a.extra_details, a.booking_date, a.invoice_id, a.total_amount, a.paid_amount, a.hotel_name, a.hotel_id, a.invoice FROM invoice_table a, hotel_booking b where a.hotel_id=$hotel_id AND a.invoice_id=b.invoice_id AND a.booking_status=1 AND a.invoice_id>$booking_id AND a.is_booking_push_to_pms = 0  AND a.ref_no!='offline' AND DATE(a.booking_date)='$booking_date'  ORDER BY a.invoice_id asc"));
        foreach($booked_room_details as $booking){
            DB::connection('be')->table('invoice_table')->where('invoice_id',$booking->invoice_id)->update(['is_booking_push_to_pms'=>1]);
        }
        return $booked_room_details;

    }
    public static function searchAllOTABookings($hotel_id, $booking_date){
       if($booking_date==''){
          $booked_room_details = DB::select(DB::raw("SELECT id, cancel_status, unique_id, channel_name, customer_details, rooms_qty, checkin_at, checkout_at, booking_date, amount, room_type, rate_code, no_of_adult, no_of_child, payment_status FROM cm_ota_booking where hotel_id=$hotel_id AND confirm_status=1 AND is_booking_push_to_pms = 0 AND DATE(booking_date)=CURDATE()  ORDER BY id asc"));
       }
       else{
          $booked_room_details = DB::select(DB::raw("SELECT id, cancel_status, unique_id, channel_name, customer_details, rooms_qty, checkin_at, checkout_at, booking_date, amount, room_type, rate_code, no_of_adult, no_of_child, payment_status FROM cm_ota_booking where hotel_id=$hotel_id AND is_booking_push_to_pms = 0 AND confirm_status=1 AND DATE(booking_date)='$booking_date'  ORDER BY id asc"));
       }
        foreach($booked_room_details as $booking){
            DB::table('cm_ota_booking')->where('id',$booking->id)->update(['is_booking_push_to_pms'=>1]);
        }
       return $booked_room_details;
   }
   public static function getHotelRatePlanIdFromRatePlanSynch($rid, $id, $hotel_id) {
     $cmOtaRatePlanSynchronizeModel   = new CmOtaRatePlanSynchronize();
     $rtplmodel= new MasterRatePlan();
     $rrtplmodel= new MasterHotelRatePlan();
     $rateids                         = explode(",", $id);
     $roomids                         = explode(",", $rid);

     $hotel_rate_names                 = array();
       $r=0;
     foreach ($roomids as $roomid) {
       $cmOtaRatePlanSynchronizeDetails = $cmOtaRatePlanSynchronizeModel->select('*')
                                         ->where('ota_rate_plan_id',$rateids[$r])
                                         ->where('ota_room_type_id', $roomid)
                                         ->where('hotel_id', $hotel_id)
                                         ->first();
       if($cmOtaRatePlanSynchronizeDetails){
         $rdid=$cmOtaRatePlanSynchronizeDetails->hotel_rate_plan_id;
         $rmid=$cmOtaRatePlanSynchronizeDetails->hotel_room_type_id;
         $rds=$rtplmodel->select('*')
           ->where('rate_plan_id', $rdid)
           ->first();
         $rmds=$rrtplmodel->select('*')
           ->where('rate_plan_id',$rdid)
           ->where('room_type_id',$rmid)
           ->where('is_trash',0)
           ->first();

          $plan_type =    isset($rds->plan_type)?$rds->plan_type:'NA';
          $room_rate_plan = isset($rmds->room_rate_plan_id)?$rmds->room_rate_plan_id:'NA';
         $hotel_rate_names[]              = [$plan_type, $room_rate_plan];
       }
         $r++;
     }
     return $hotel_rate_names ;
   }
   public static function getHotelRoomIdFromRoomSynch($id, $hotel_id) {

        $cmOtaRoomTypeSynchModel   = new CmOtaRoomTypeSynchronize();
        $roomids                   = explode(",", $id);

        $hotel_rooms    = array();
        foreach ($roomids as $roomid) {
          $cmOtaRoomTypeSynchDetails = $cmOtaRoomTypeSynchModel->select('*')
                                       ->where('ota_room_type', $roomid)
                                       ->where('hotel_id',$hotel_id)
                                       ->first();
          if($cmOtaRoomTypeSynchDetails){
          $hotel_rooms[]          = [$cmOtaRoomTypeSynchDetails->room_type_id=>$cmOtaRoomTypeSynchDetails->ota_room_type_name];
          }
        }

      return $hotel_rooms;

  }
  public static function idsHotel($hotel_code){
      $model = new IdsRoom();
     $ids_hotel = $model->select('hotel_id')
                            ->where('ids_hotel_code',$hotel_code)
                            ->first();
     return  $ids_hotel->hotel_id;
  }
  public static function checkInvTypeCode($room_code, $hotel_code){
      $model = new IdsRoom();
     $ids_room = $model->select('room_type_id')
                            ->where('ids_room_type_code',$room_code)
                            ->where('ids_hotel_code',$hotel_code)
                            ->first();
      if(isset($ids_room->room_type_id))
      {
        return  $ids_room->room_type_id;
      }
      else
      {
        return '';
      }

  }
  public static function UserInfo($user_id)
  {
      $model = new User();
      $UserInformation = $model->select('*')
                               ->where('user_id',$user_id)
                               ->first();
      return $UserInformation;
  }
  public static function NoOfBookings($invoice_id)
  {
      $booked_room_details = DB::connection('be')->table('hotel_booking')->join('invoice_table','hotel_booking.invoice_id','=','invoice_table.invoice_id')
                              ->where('hotel_booking.invoice_id',$invoice_id)
                              ->get();
      return $booked_room_details;
  }
}
