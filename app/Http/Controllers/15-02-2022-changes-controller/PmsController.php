<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Validator;
use App\HotelInformation;
use App\MasterRoomType;
use App\MasterRatePlan;
use App\MasterHotelRatePlan;
use App\Http\Controllers\PmsService;
use DB;
class PmsController extends Controller
{
    //Validation rules
    private $rules=array(
        'hotel_id'=>'required | numeric',
        'api_key'=>'required',
        'client_ip'=>'required'    
    );
    private $messages=array(
        'hotel_id.required'=>'Hotel id required',
        'api_key.required'=>'Api key is required',
        'client_ip.required'=>'Client ip is required'    
    );
    protected $pmsService;
    public function __construct(PmsService $pmsService)
    {
       $this->pmsService = $pmsService;
    }
     /**
     * HotelDetails meethod return hotel details against one hotel id.
     * There are one request parameters which is "hotel_id" on this GET request.
     * Author : Godti Vinod.
     * @return jsonObject.
     */
    public function hotelDetails(string $key,int $hotel_id,Request $request)
    {
        $data=array();
        $ip=$data['client_ip']= $_SERVER['REMOTE_ADDR'];
        $data['api_key']=$key;
        $data['hotel_id']=$hotel_id;
        $failure_message='Hotel details retrival failed';
        $validator = Validator::make($data,$this->rules,$this->messages);
        if ($validator->fails())
        {
            return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
        }
        //Implemnet API Auth 
        /////Here//////

        ///Api Auth ends Here

        //Save Service logs
        $this->pmsService->serviceRequest($key,$hotel_id,$ip,"Fetch Hotels");
        //call to the pms Service
        $hotel_details=$this->pmsService->getHotelInfo($hotel_id);
        $room_types=$this->pmsService->getRoomTypes($hotel_id);

        foreach($room_types as $room_type)
        {
            $ratePlans=$this->pmsService->getRatePlans($room_type['room_type_id'],$hotel_id);
            $roomTypes[] = array(
                "room_type_id"   =>$room_type['room_type_id'],
                "room_type_name" => $room_type['room_type'],
                "no_of_rooms"    => $room_type['total_rooms'],
                "PricePlan"      => $ratePlans,
                );
        }
        $result = array();
        $result[] = array(
            'HotelDetails' => $hotel_details,
            'RoomTypes'     => $roomTypes
            );
        
        if($result)
        {
            $res=array('data'=>$result,'status'=>'yes');
            return response()->json($res);
        } 
        else
        {
            $res=array('status'=>'no');
            return response()->json($res);
        }   
    }
     /**
     * bookingDetails method return boooking details against one hotel id.
     * There are one request parameters which is "hotel_id" on this GET request.
     * Author : Godti Vinod.
     * @return jsonObject.
     */
    public function bookingDetails(Request $request)
    {
        $data=$request->all();
        $api_key    = $data['api_key'];
        $hotel_id   = $data['hotel_id'];
        $last_booking_id = $data['last_id'];
        $booking_date_i = $data['booking_date'];
        $ip             = $data['client_ip'];
        $failure_message='Hotel details retrival failed';
        $validator = Validator::make($data,$this->rules,$this->messages);
        if ($validator->fails())
        {
            return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
        }
        //Implemnet API Auth 
        /////Here//////

        ///Api Auth ends Here

        //Save Service logs
        $this->pmsService->serviceRequest($api_key,$hotel_id,$ip,"Fetch Bookings");
        //call to the pms Service
        $bookingDetails =  $this->pmsService->searchAllBookings($hotel_id, $last_booking_id);
        
       
        $all_bookings = array();
        foreach ($bookingDetails as $bd) {
            $rooms          =array();
            $user_id        =$bd['user_id'];
            $invoice_id     =$bd['invoice_id'];
            $ref_no         =$bd['ref_no'];

            $user_Details   = $this->pmsService->UserInfo($user_id);
            $booked_Rooms   =$this->pmsService->NoOfBookings($invoice_id);
            $date1=date_create($booked_Rooms[0]['check_out']);
            $date2=date_create($booked_Rooms[0]['check_in']);
            $diff=date_diff($date1,$date2);
            $no_of_nights=$diff->format("%a");

            $booking_date   =$bd['booking_date']; 
            $booking_id     =date("dmy", strtotime($booking_date)).str_pad($invoice_id, 4, '0', STR_PAD_LEFT);

            if($ref_no=='offline')
            {
            $mode_of_payment='Offline';
            }
            else
            {
            $mode_of_payment='Online';
            }
             $room_type_plan=explode(",", $bd['room_type']);
             $plan= array();
             for($i=0; $i<sizeof($room_type_plan); $i++)
             {
                 $plan[]=substr($room_type_plan[$i], -5, -2);
             }

             $extra=json_decode($bd['extra_details']);


             $k=0;
            foreach ($booked_Rooms  as $br) {

                 $adult=0;
                 $child=0;
                foreach($extra as $key=>$value)
                {
                    
                  if(trim($br['room_type_id'])==trim($key))
                  {
                      
                      for($j=0;$j<$br['rooms'];$j++)
                      {
                       $adult=$adult+$value[$j][0];
                       $child=$child+$value[$j][1];
                      }
                  }
                }


                $rooms[] = array(
                "room_type_id"          => $br['room_type_id'],
                "room_type_name"        => $br['room_type'],
                "no_of_rooms"           => $br['rooms'],
                "room_rate"             => ($bd['total_amount']/$br['rooms'])/$no_of_nights,
                "plan"                  => trim($plan[$k]),
                "adult"                 => $adult,
                "child"                 => $child
                );
                $k++;
            }

            $user_info = array(
                "user_name"             => $user_Details['first_name'].' '.$user_Details['last_name'],
                "mobile"                => $user_Details['mobile'],
                "email"                 => $user_Details['email_id'],
                );

            $bookings = array(
                "date_of_booking"       => $booking_date,
                "hotel_id"              => $bd['hotel_id'],
                "hotel_name"            => $bd['hotel_name'],
                "check_in"              => $booked_Rooms[0]['check_in'],
                "check_out"             => $booked_Rooms[0]['check_out'],
                "booking_id"            => $booking_id,
                "mode_of_payment"       => $mode_of_payment,
                "grand_total"           => $bd['total_amount'],
                "paid_amount"           => $bd['paid_amount'],
                "channel"               => "Bookingjini",
                "status"                => "confirmed"
                );


            $all_bookings[] = array(
            'UserDetails'               => $user_info,
            'bookingsDetails'           => $bookings,
            'RoomDetails'               => $rooms
            );
           
        }                
        
        $OTABookingDetails =$this->pmsService->searchAllOTABookings($hotel_id, $booking_date_i);
        $ota_all_bookings = array();
        foreach ($OTABookingDetails as $otabd)
        {
            $ota_rooms          =array();
            $ota_invoice_id     =$otabd['id'];
            $room_qty           =explode(",",$otabd['rooms_qty']);
            $total_amount       =$otabd['amount'];
            $date1=date_create($otabd['checkout_at']);
            $date2=date_create($otabd['checkin_at']);
            $diff=date_diff($date1,$date2);
            $no_of_nights=$diff->format("%a");
            $amount=0;
            if (strpos($total_amount, 'Indian rupee') !== false) 
            {
                 $amount=str_replace("Indian rupee ","",$total_amount);
            }
            else if(strpos($amt['amount'], 'INR') !== false)
            {
                $amount=str_replace("INR","",$total_amount);
            }
            else
            {
                $amount=$total_amount;
            }
            $ota_booking_date   =$otabd['booking_date']; 
            $ota_booking_id     =date("dmy", strtotime($ota_booking_date)).str_pad($ota_invoice_id, 4, '0', STR_PAD_LEFT);
           
            $plans=$this->pmsService->getHotelRatePlanIdFromRatePlanSynch($otabd['rate_code'], $hotel_id);
            $rooms1=$this->pmsService->getHotelRoomIdFromRoomSynch($otabd['room_type'], $hotel_id);
             $k=0;
            foreach ($rooms1[$k]  as $key=>$value) {

                 $adult=0;
                 $child=0;

                $ota_rooms[] = array(
                "room_type_id"          => $key,
                "room_type_name"        => $value,
                "no_of_rooms"           => $room_qty[$k],
                "room_rate"             => ($amount/$room_qty[$k])/$no_of_nights,
                "plan"                  => trim($plans[$k]),
                "adult"                 => $adult,
                "child"                 => $child
                );
                $k++;
            }
                 $ota_user_info = array(
                "user_name"             => $otabd['customer_details'],
                "mobile"                => 'NA',
                "email"                 => 'NA',
                );

            $ota_bookings = array(
                "date_of_booking"       => $ota_booking_date,
                "hotel_id"              => $hotel_id,
                "hotel_name"            => 'NA',
                "check_in"              => $otabd['checkin_at'],
                "check_out"             => $otabd['checkout_at'],
                "booking_id"            => $ota_booking_id,
                "mode_of_payment"       => "Online",
                "grand_total"           => $amount,
                "paid_amount"           => $amount,
                "channel"               => $otabd['ota_name'],
                "status"                => "confirmed"
                );


            $ota_all_bookings[] = array(
            'UserDetails'               => $ota_user_info,
            'BookingsDetails'           => $ota_bookings,
            'RoomDetails'               => $ota_rooms
            );
        }         
        $bookings=array_merge($all_bookings,$ota_all_bookings); 
        if($bookings)
        {
            $res=array('data'=>$bookings,'b_status'=>'yes');
            return response()->json($res);
        } 
        else
        {
            if($last_booking_id!='')
            {
            $res=array("status"=>"No more booking is there","code"=>"401","message"=>"Last booking id is not valid or no more booking is there.");
            return response()->json($res);
            }
            else
            {
            $res=array("status"=>"Error","code"=>"401","message"=>"Wrong username/password or no hotel assign to this user");
            return response()->json($res);  
            }
        }   
    }
    /**
     * updateInventory method updates the inventory.
     * Author : Godti Vinod.
     * @return jsonObject.
     */
    public function updateInventory(Request $request)
    {
        $data=$request->all();
        $api_key        = $data['api_key'];
        $hotel_id       = $data['hotel_id'];
        $room_type_id   = $data['room_type_id'];
        $date_from      = $data['date_from'];
        $date_to        = $data['date_to'];
        $no_of_rooms    = $data['no_of_rooms'];
        $update_cm      = $data['update_cm'];
        $ip             = $data['client_ip'];
        $failure_message='Hotel details retrival failed';
        $validator = Validator::make($data,$this->rules,$this->messages);
        if ($validator->fails())
        {
            return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
        }
        $inventory      = array(
            "hotel_id"          => $hotel_id,
            "room_type_id"      => $room_type_id,
            "date_from"         => $date_from,
            "date_to"           => $date_to,
            "no_of_rooms"       => $no_of_rooms,
            "update_cm"         => $update_cm,
            "ip"                => $ip,
            );
            if(strtotime($date_to)>=strtotime($date_from))
            {
                //Save Service Request Log
                $this->pmsService->serviceRequest($api_key,$hotel_id,$ip,"Update Inventory");
                //Update Inventory
                $UpdateInventory =  $this->pmsService->UpdateInventory($inventory);
                            
                if($UpdateInventory)
                {
                    $res=array('data'=>$UpdateInventory, 'status'=>'yes');
                    return response()->json($res);
                }
                else
                {
                    $res=array("status"=>"Error","message"=>"Wrong username/password or no hotel assign to this user");
                    return response()->json($res);
                }
            }
            else
            {
                $res=array("status"=>"Error","code"=>"401","message"=>"Invalid Date");
                return response()->json($res);
            }
    }
}