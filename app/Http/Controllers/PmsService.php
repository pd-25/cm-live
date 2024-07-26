<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\HotelInformation;
use App\MasterRoomType;
use App\MasterRatePlan;
use App\MasterHotelRatePlan;
use App\PmsRequest;
use App\Booking;
use App\RatePlanLog;
use App\CmOtaManageInventoryBucket;
use App\CmOtaDetails;
use App\PmsDetails;
use App\Inventory;
use DB;
use App\User;
use App\PmsRoom;
use App\PmsRate;
class PmsService extends Controller
{
    protected $inventoryBucketEngineService;
    public function __construct(InventoryBucketEngine $inventoryBucketEngineService)
    {
       $this->inventoryBucketEngineService = $inventoryBucketEngineService;
    }  
     //Service request logs
     public function serviceRequest($api_key, $hotel_id, $ip, $request_for)
     {
         $serviceRequestModel= new PmsRequest();
 
          //Save Request Log
             $serviceRequestModel->api_key     = $api_key;
             $serviceRequestModel->hotel_id    = $hotel_id;
             $serviceRequestModel->ip          = $ip;
             $serviceRequestModel->request_for = $request_for;
             $serviceRequestModel->requested_date = date('Y-m-d');
             $serviceRequestModel->save();
     }
    /**
     * getHotelInfo method return hotel information against one hotel id.
     * One parameter hotel id
     * Author : Godti Vinod.
     * @return query object
     */
    public function getHotelInfo(int $hotel_id)
    {   
        if($hotel_id)
        {   
            $hotel_info=HotelInformation::select('hotel_id','hotel_name')->where('hotel_id',$hotel_id)->first();
            return $hotel_info;
        }
        else{
            return "Invalid hotel id";
        }
    }
    /**
     * getRoomTypes method return Room Types information against one hotel id.
     * One parameter hotel id
     * Author : Godti Vinod.
     * @return query object
     */
    public function getRoomTypes(int $hotel_id)
    {   
        if($hotel_id)
        {   
            $room_type_info=MasterRoomType::select('room_type_id','room_type','total_rooms')->where('hotel_id',$hotel_id)->get();
            return $room_type_info;
        }
        else{
            return "Invalid hotel id";
        }
    }
    /**
     * getRatePlans method return Rate plan information against one hotel id and rooom type id.
     * One parameter hotel id other one id room type id
     * Author : Godti Vinod.
     * @return query object
     */
    public function getRatePlans(int $room_type_id,int $hotel_id)
    {   
        if($hotel_id && $room_type_id)
        {   
            $hotel_info=MasterHotelRatePlan::select('room_rate_plan_id','bar_price','extra_adult_price','extra_child_price','before_days_offer','stay_duration_offer','lastminute_offer','rate_plan_table.rate_plan_id','room_type_id','from_date','to_date','multiple_occupancy','rate_plan_table.plan_type','rate_plan_table.plan_name')
            ->join('rate_plan_table','room_rate_plan.rate_plan_id','rate_plan_table.rate_plan_id')
            ->where('room_type_id',$room_type_id)->first();
            return $hotel_info;
        }
        else{
            return "Invalid room_type_id";
        }
    }
    // Fetch Booking details Hotel id wise

    public  function searchAllBookings($hotel_id, $last_booking_id)
    {
        if($last_booking_id!='')
        {
            $booking_id = substr($last_booking_id, 6);
        }
        else
        {
            $booking_id=0;
        }
        
        
        $res=DB::select("SELECT DISTINCT (b.user_id), a.room_type, a.ref_no, a.extra_details, a.booking_date, a.invoice_id, a.total_amount, a.paid_amount, a.hotel_name, a.hotel_id, a.invoice FROM invoice_table a, hotel_booking b where a.hotel_id=$hotel_id AND a.invoice_id=b.invoice_id AND a.booking_status=1 AND a.invoice_id>$booking_id AND a.ref_no!='offline'  ORDER BY a.invoice_id asc");

        $booked_room_details =  json_decode(json_encode($res), true);
        return $booked_room_details;
        
    }

    // Fetch OTA Booking details Hotel id wise
     public  function searchAllOTABookings($hotel_id, $booking_date)
    { 
        if($booking_date=='')
        {
        	$res=DB::select("SELECT a.id, b.ota_name, a.customer_details, a.rooms_qty, a.checkin_at, a.checkout_at, a.booking_date, a.amount, a.room_type, a.rate_code FROM cm_ota_booking a, cm_ota_details b where a.hotel_id=$hotel_id AND a.ota_id=b.ota_id AND a.confirm_status=1 AND a.cancel_status=0 AND DATE(booking_date)=CURDATE()  ORDER BY id asc");
        }
        else
        {
            $res=DB::select("SELECT a.id, b.ota_name, a.customer_details, a.rooms_qty, a.checkin_at, a.checkout_at, a.booking_date, a.amount, a.room_type, a.rate_code FROM cm_ota_booking a, cm_ota_details b where a.hotel_id=$hotel_id AND a.ota_id=b.ota_id AND a.confirm_status=1 AND a.cancel_status=0 AND DATE(booking_date)='$booking_date'  ORDER BY id asc");
        }
       

        $booked_room_details = json_decode(json_encode($res), true);
        return $booked_room_details;
        
    }

     // Fetch No of Room Booked details Invoice id wise

    public  function NoOfBookings($invoice_id)
    {
        $model = new Booking();
        $booked_room_details = Booking::
                                join('room_type_table','hotel_booking.room_type_id','room_type_table.room_type_id')
                                ->where('hotel_booking.invoice_id' ,'=', $invoice_id)
                                ->get();
        return $booked_room_details;
    }
    // Fetch User details user id wise
    public  function UserInfo($user_id)
    {
        $UserInformation = User::select('first_name', 'last_name', 'mobile', 'email_id')
                                 ->where('user_id', '=',$user_id)
                                 ->first();
        return $UserInformation;
    }
    public static function getHotelRatePlanIdFromRatePlanSynch($id, $hotel_id) { 
        
        $cmOtaRatePlanSynchronizeModel   = new CmOtaRatePlanSynchronize();
        $rateids                         = explode(",", $id);

        $hotel_rate_names                 = array();
        foreach ($rateids as $rateid) {
        $cmOtaRatePlanSynchronizeDetails = CmOtaRatePlanSynchronize::
                                            where('ota_rate_plan_id','=',$rateid)
                                            ->where('hotel_id','=',$hotel_id)
                                            ->first();
        if($cmOtaRatePlanSynchronizeDetails){
        $hotel_rate_names[]              = $cmOtaRatePlanSynchronizeDetails->ota_rate_plan_name;              
        }            
        }        

        return $hotel_rate_names ;     
    }

    public static function getHotelRoomIdFromRoomSynch($id, $hotel_id) {

        $cmOtaRoomTypeSynchModel   = new CmOtaRoomTypeSynchronize();
        $roomids                   = explode(",", $id);

        $hotel_rooms    = array();
        foreach ($roomids as $roomid) {
        $cmOtaRoomTypeSynchDetails = CmOtaRoomTypeSynchronize::where('ota_room_type','=',$roomid)
                                        ->where('hotel_id','=',$hotel_id)
                                        ->first();
        if($cmOtaRoomTypeSynchDetails){
        $hotel_rooms[]          = [$cmOtaRoomTypeSynchDetails->room_type_id=>$cmOtaRoomTypeSynchDetails->ota_room_type_name];          
        }            
        }        

    return $hotel_rooms;       
        
    }
    // Update Inventory Room Type Wise
    public function UpdateInventory($inventory)
    {
        date_default_timezone_set("Asia/Calcutta"); 
        $model                    = new Inventory();
        $cmOtaDetailsModel        = new PmsDetails();
        
        $date                     = new \DateTime();
        $dateTimestamp            =$date->format('Y-m-d H:i:s');

        $hotel_id                 = $inventory['hotel_id'];
        $room_type_id             = $inventory['room_type_id'];

        $date_from                = date("Y-m-d", strtotime($inventory['date_from']));                          
        $date_to                  = date("Y-m-d", strtotime($inventory['date_to']));
        $no_of_rooms              = $inventory['no_of_rooms'];
        $update_cm                = $inventory['update_cm'];
        $ip                       = $inventory['ip'];
        $api_key                  = $inventory['api_key'];

        $model['hotel_id']          = $hotel_id;
        $model['room_type_id']      = $room_type_id;
        $model['date_from']         = $date_from;
        $model['date_to']           = $date_to;
        $model['no_of_rooms']       = $no_of_rooms;
        $model['client_ip']         = $ip;
        $model['user_id']          = $user_id =strtotime("now");//For PMS ,user_id is time
        if($model->save())
        {
        //return true;
        if($update_cm=='yes')
        {
            $for_bucket_hotel_details   = CmOtaDetails::
                                            where('hotel_id', $hotel_id)
                                            ->where('is_active' ,'=',1)
                                            ->get();
                    $ota_update_status = 0;
                  foreach ($for_bucket_hotel_details as $key => $value){
                  //--------push request in cm_ota_manage_inventory_bucket Start--------/
                  $cmOtaManageInventoryBucket                       = new CmOtaManageInventoryBucket();
                  $cmOtaManageInventoryBucket->hotel_id             = $hotel_id;
                  $cmOtaManageInventoryBucket->inventory_table_id   = $model->inventory_id;
                  $cmOtaManageInventoryBucket->ota_id               = $value->ota_id;
                  $cmOtaManageInventoryBucket->ota_hotel_code       = $value->ota_hotel_code;
                  $cmOtaManageInventoryBucket->ota_name             = $value->ota_name;
                  $cmOtaManageInventoryBucket->is_update            = 0;
                  $cmOtaManageInventoryBucket->save();

                  $ota_update_status = $ota_update_status+1;
                  //--------push request in cm_ota_booking_push_bucket End-----------------/
                  }

                  $ota_booking_status = 0;
                  
                  foreach ($for_bucket_hotel_details as $key => $value)
                  {
                  $ota_inventory_status= $this->inventoryBucketEngineService->RunBucketEngine($hotel_id,$model->inventory_id,"inventory",$user_id,0);
                  if($ota_inventory_status)
                  {
                      $ota_booking_status = $ota_booking_status+1;
                  }      
                  }

                  if($ota_booking_status==$ota_update_status)
                  {
                  return true;
                  }
                  else
                  {
                  return false;
                  }
        }
        else if($update_cm=='no')
        {
            return true;
        }
      }
        else
        {
            return false;
        }
    }
   public function UpdateRate($rate)
    {
        $rate_plan_log = new RatePlanLog();
        $cm_ota_manage_inventory_bucket = new CmOtaManageInventoryBucket();
        $ota_details = new CmOtaDetails();
        $ota_data=array();
       
        $data=$rate;
        $data['from_date']=date('Y-m-d',strtotime($data['date_from']));
        $data['to_date']=date('Y-m-d',strtotime($data['date_to']));
        $data['multiple_occupancy']=json_encode($data['multiple_occupancy']);
        $data['multiple_days']=json_encode($data['multiple_days']);
        $data['user_id']=0;
        $user_id=$data['user_id'];
        $update_cm=$data['update_cm'];
        $ota_booking_status = 0;
        $ota_update_status = 0;
        $rate_ota_data=array();
        if($rate_plan_log->fill($data)->save())
        { 
            $ota_update_status = $ota_update_status+1;
            $ota_data=CmOtaDetails::select('ota_id','ota_hotel_code','ota_name')->where('hotel_id','=',$data['hotel_id'])->where('is_active','=',1)->get()->toArray();
            if($ota_data)
            {
                if(is_array($ota_data[0]))///For Multiple OTA
                {
                    foreach($ota_data as $ota)
                    {
                        if (is_array($ota)) {
                            $ota['hotel_id']=$data['hotel_id'];
                            $ota['rate_plan_log_table_id']=$rate_plan_log->rate_plan_log_id;
                            $ota['is_update']=0;//0 for run push bucket
                        }
                    }
                }
                else//For single OTA
                {
                    $ota_data['hotel_id']=$data['hotel_id'];
                    $ota['rate_plan_log_table_id']=$rate_plan_log->rate_plan_log_id;
                    $ota_data['is_update']=0;//0 for run push bucket
                }
            }
            if($update_cm=='yes')
            {
                CmOtaManageInventoryBucket::insert($ota_data);
                
               foreach($ota_data as $data)
               {
                $ota_rateplan_status=$this->inventoryBucketEngineService->RunBucketEngine($data['hotel_id'],$rate_plan_log->rate_plan_log_id,"roomrate",$user_id);
                if($ota_rateplan_status)
                {
                     $ota_booking_status = $ota_booking_status+1;
                 }  
               }
               if($ota_booking_status==$ota_update_status)
                 {
                    return true;
                }
                else
                {
                     return false;
                }
            }
            else if($update_cm=='no')
            {
                return true;
            }
        } 
        else
        {
            return false;
        }  
    }
    public function pmsRoom($hotel_id,$room_code)
    {
       $pms_room = PmsRoom::select('room_type_id')
                              ->where('hotel_id',$hotel_id)
                              ->where('pms_room_type_code' ,'=', $room_code)
                              ->first();
       return  $pms_room['room_type_id'];
    }
    
    public function ratePlan($rate_plan_id)
    {
       $plan = MasterRatePlan::select('plan_type')
                              ->where('rate_plan_id','=',$rate_plan_id)
                              ->first();
       return  $plan['plan_type'];
    }
    public function pmsRoomCode($room_type_id)
    {
       $pms_room = PmsRoom::select('pms_room_type_code')
                              ->where('room_type_id','=',$room_type_id)
                              ->first();
       return  $pms_room['pms_room_type_code'];
    }
 
    public function pmsHotel($hotel_code)
    {
        
       $pms_hotel = PmsRoom::select('hotel_id')
                              ->where('pms_hotel_code','=',$hotel_code)
                              ->first();
       return  $pms_hotel['hotel_id'];
    }
    public function pmsHotelCode($hotel_id)
    {
       $pms_hotel = PmsRoom::select('pms_hotel_code')
                              ->where('hotel_id' ,'=',$hotel_id)
                              ->first();
       return  $pms_hotel['pms_hotel_code'];
    }
    public function pmsRate($pms_room_type_id,$pms_rate_plan)
    {
        $pms_rate_plan_id=PmsRate::select('hotel_rate_plan_id')->where('hotel_room_type_id',$pms_room_type_id)->where('pms_rate_plan_name',$pms_rate_plan)->first();
        return $pms_rate_plan_id['hotel_rate_plan_id'];
    }
}