<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\OtaInventory;
use App\CmOtaDetails;
use App\CmOtaRoomTypeSynchronize;
use App\CmOtaBooking;
use App\OtaRatePlan;
use DB;
use App\Http\Controllers\Controller;

class OtaInventoryBookingFetchController extends Controller
{
    public function getInventoryDetails(int $ota_id,int $hotel_id ,string $date_from ,string $date_to,int $mindays,Request $request){
        $date_from=date('Y-m-d',strtotime($date_from));
        $date_to=date('Y-m-d',strtotime($date_to));
        $from = strtotime($date_from);
        $to = strtotime($date_to);
        $dif_dates=array();
        $date1=date_create($date_from);
        $date2=date_create($date_to);
        $diff=date_diff($date1,$date2);
        $diff=$diff->format("%a");
        $j=0;
        $k=0;
        for ($i=$from; $i<=$to; $i+=86400) {
            $dif_dates[$j]= date("Y-m-d", $i);
            $j++;
        }
        $conditions=array('hotel_id'=>$hotel_id,'is_trash'=>0);
        $room_types=DB::table('kernel.room_type_table')->select('room_type','room_type_id')->where($conditions)->orderBy('room_type_table.room_type_id','ASC')->get();
        if($room_types)
        {
            foreach($room_types as $room)
            {
                $data=$this->getInventoryByRoomType($room->room_type_id,$date_from, $date_to,$ota_id,$hotel_id,$mindays);
                $room->inv=$data;
                $data2=$this->getBookingByRoomType($hotel_id,$room->room_type_id,$date_from,$date_to,$ota_id);
                $room->bookings=$data2;
            }
            for($i=0;$i<$diff;$i++)
            {
                $sum=0;
                foreach($room_types as $room)
                {
                    if($room->inv[$i]['date']==$dif_dates[$i] && $room->inv[$i]['block_status']==0)
                    {
                        $sum+=$room->inv[$i]['no_of_rooms'];
                    }
                }
                $count[$k]=$sum;
                $k++;
            }
            $res=array('status'=>1,'message'=>"Hotel inventory retrieved successfully ",'data'=>$room_types,'count'=>$count);
            return response()->json($res);
        }
        else
        {
            $res=array('status'=>1,'message'=>"Hotel inventory retrieval failed");
        }
    }
    public function getInventoryByRoomType($room_type_id,$date_from,$date_to,$ota_id,$hotel_id,$mindays){
        $filtered_inventory=array();
        $date1=date_create($date_from);
        $date2=date_create($date_to);
        $date3=date_create(date('Y-m-d'));
        $diff=date_diff($date1,$date2);
        $diff=$diff->format("%a");
        $diff1=date_diff($date1,$date3);
        $diff1=$diff1->format("%a");
        $inv_rooms='';
        $los=1;
        $j=0;
        $k=0;
        if($diff1<=$mindays && $mindays!=0)
        {   $d=$date_from;
            $timestamp = strtotime($d);
            $day = date('D', $timestamp);
            $array=array('no_of_rooms'=>0,'block_status'=>1,'los'=>1,'room_type_id'=>$room_type_id,'date'=>$date_from,'day'=>$day);
            array_push($filtered_inventory,$array);
        }
        else
        {
            $get_ota_name = CmOtaDetails::select('ota_name')->where('ota_id',$ota_id)->first();
            for($i=1;$i<=$diff; $i++ )
            {
                $d=$date_from;
                $timestamp = strtotime($d);
                $day = date('D', $timestamp);
                $blk_status=0;
                $inventory_details= OtaInventory::select('*')
                                    ->where('room_type_id' , '=' , $room_type_id)
                                    ->where('hotel_id',$hotel_id)
                                    ->where('channel',$get_ota_name->ota_name)
                                    ->where('date_from' , '<=' , $d)
                                    ->where('date_to' , '>=' , $d)
                                    ->orderBy('inventory_id', 'desc')
                                    ->first();
                if(empty($inventory_details))
                {
                    $array=array('no_of_rooms'=>0,'block_status'=>0,'los'=>1,'room_type_id'=>$room_type_id,'date'=>$date_from,'day'=>$day);
                    array_push($filtered_inventory,$array);
                }
                else
                {
                    if($inventory_details->multiple_days == "sync"){
                        $inv_rooms = DB::connection('be')->table('inventory_table')->select('*')
                                ->where('room_type_id' , '=' , $room_type_id)
                                ->where('hotel_id' , '=' , $hotel_id)
                                ->where('date_from' , '<=' , $date_from)
                                ->where('date_to' , '>=' , $date_from)
                                ->orderBy('inventory_id', 'desc')
                                ->first();

                        if($inv_rooms){
                            if($inv_rooms->block_status == 0){
                                $array=array('no_of_rooms'=>$inv_rooms['no_of_rooms'],'block_status'=>0,'los'=>$los,'room_type_id'=>$room_type_id,'date'=>$date_from,'day'=>$day);
                                array_push($filtered_inventory,$array);
                            }
                            else{
                                $array=array('no_of_rooms'=>$inv_rooms['no_of_rooms'],'block_status'=>1,'los'=>$los,'room_type_id'=>$room_type_id,'date'=>$date_from,'day'=>$day);
                                array_push($filtered_inventory,$array);
                            }
                        }
                    }
                    else{
                        $block_status           = trim($inventory_details->block_status);
                        $los                    = trim($inventory_details->los);
                        if($block_status==1)
                        {
                            $blk_status=1;
                            $inv_rooms =OtaInventory::
                                        select('no_of_rooms','multiple_days')
                                        ->where('room_type_id' , '=' , $room_type_id)
                                        ->where('hotel_id',$hotel_id)
                                        ->where('channel',$get_ota_name->ota_name)
                                        ->where('date_from' , '<=' , $date_from)
                                        ->where('date_to' , '>=' , $date_from)
                                        ->where('block_status' , '=' , 0)
                                        ->orderBy('inventory_id', 'desc')
                                        ->first();
                            if(empty($inv_rooms))
                            {
                                $array=array('no_of_rooms'=>0,'block_status'=>1,'los'=>1,'room_type_id'=>$room_type_id,'date'=>$date_from,'day'=>$day);
                                array_push($filtered_inventory,$array);
                            }
                            else if(!empty($inv_rooms))
                            {
                                $multiple_days=json_decode($inv_rooms->multiple_days);
                                if($multiple_days != null){
                                    if($multiple_days->$day == 0){
                                        $inv_rooms1 = OtaInventory::
                                                    select('no_of_rooms','multiple_days')
                                                    ->where('room_type_id' , '=' , $room_type_id)
                                                    ->where('hotel_id',$hotel_id)
                                                    ->where('channel',$get_ota_name->ota_name)
                                                    ->where('date_from' , '<=' , $date_from)
                                                    ->where('date_to' , '>=' , $date_from)
                                                    ->where('block_status' , '=' , 0)
                                                    ->orderBy('inventory_id', 'desc')
                                                    ->skip(1)
                                                    ->take(2)
                                                    ->get();
                                        if(empty($inv_rooms1[0])){
                                            $array=array('no_of_rooms'=>$inv_rooms['no_of_rooms'],'block_status'=>1,'los'=>$los,'room_type_id'=>$room_type_id,'date'=>$date_from,'day'=>$day);
                                            array_push($filtered_inventory,$array);
                                        }
                                        else{
                                            $multiple_days1=json_decode($inv_rooms1[0]->multiple_days);
                                            if($multiple_days1->$day==0){
                                                $inv_rooms2 = OtaInventory::
                                                                select('no_of_rooms','multiple_days')
                                                                ->where('room_type_id' , '=' , $room_type_id)
                                                                ->where('hotel_id',$hotel_id)
                                                                ->where('channel',$get_ota_name->ota_name)
                                                                ->where('date_from' , '<=' , $date_from)
                                                                ->where('date_to' , '>=' , $date_from)
                                                                ->where('block_status' , '=' , 0)
                                                                ->orderBy('inventory_id', 'desc')
                                                                ->skip(2)
                                                                ->take(3)
                                                                ->get();
                                                if(empty($inv_rooms2[0])){
                                                    $array=array('no_of_rooms'=>$inv_rooms['no_of_rooms'],'block_status'=>1,'los'=>$los,'room_type_id'=>$room_type_id,'date'=>$date_from,'day'=>$day);
                                                    array_push($filtered_inventory,$array);
                                                }
                                                else{
                                                    $array=array('no_of_rooms'=>$inv_rooms2[0]['no_of_rooms'],'block_status'=>1,'los'=>$los,'room_type_id'=>$room_type_id,'date'=>$date_from,'day'=>$day);
                                                    array_push($filtered_inventory,$array);
                                                }
                                            }
                                            else{
                                                $array=array('no_of_rooms'=>$inv_rooms1[0]['no_of_rooms'],'block_status'=>1,'los'=>$los,'room_type_id'=>$room_type_id,'date'=>$date_from,'day'=>$day);
                                                array_push($filtered_inventory,$array);
                                            }
                                        }
                                    }
                                    else{
                                        $array=array('no_of_rooms'=>$inv_rooms['no_of_rooms'],'block_status'=>1,'action_status'=>1,'los'=>$los,'room_type_id'=>$room_type_id,'date'=>$date_from,'day'=>$day);
                                        array_push($filtered_inventory,$array);
                                    }
                                }
                                else{
                                    $array=array('no_of_rooms'=>$inv_rooms['no_of_rooms'],'block_status'=>1,'action_status'=>1,'los'=>$los,'room_type_id'=>$room_type_id,'date'=>$date_from,'day'=>$day);
                                    array_push($filtered_inventory,$array);
                                }
                            }
                        }
                        else
                        {
                            $inv_rooms =OtaInventory::
                                        select('no_of_rooms','multiple_days')
                                        ->where('room_type_id' , '=' , $room_type_id)
                                        ->where('hotel_id',$hotel_id)
                                        ->where('channel',$get_ota_name->ota_name)
                                        ->where('date_from' , '<=' , $date_from)
                                        ->where('date_to' , '>=' , $date_from)
                                        ->orderBy('inventory_id', 'desc')
                                        ->first();
                            if(!empty($inv_rooms))
                            {
                                $multiple_days=json_decode($inv_rooms->multiple_days);
                                if($multiple_days != null){
                                    if($multiple_days->$day == 0){
                                        $inv_rooms1 = OtaInventory::
                                                    select('no_of_rooms','multiple_days')
                                                    ->where('room_type_id' , '=' , $room_type_id)
                                                    ->where('hotel_id',$hotel_id)
                                                    ->where('channel',$get_ota_name->ota_name)
                                                    ->where('date_from' , '<=' , $date_from)
                                                    ->where('date_to' , '>=' , $date_from)
                                                    ->orderBy('inventory_id', 'desc')
                                                    ->skip(1)
                                                    ->take(2)
                                                    ->get();

                                        if(empty($inv_rooms1[0])){
                                            $array=array('no_of_rooms'=>$inv_rooms['no_of_rooms'],'block_status'=>0,'los'=>$los,'room_type_id'=>$room_type_id,'date'=>$date_from,'day'=>$day);
                                            array_push($filtered_inventory,$array);
                                        }
                                        else{
                                            $multiple_days1=json_decode($inv_rooms1[0]->multiple_days);
                                            if($multiple_days1->$day==0){
                                                $inv_rooms2 = OtaInventory::
                                                                select('no_of_rooms','multiple_days')
                                                                ->where('room_type_id' , '=' , $room_type_id)
                                                                ->where('hotel_id',$hotel_id)
                                                                ->where('channel',$get_ota_name->ota_name)
                                                                ->where('date_from' , '<=' , $date_from)
                                                                ->where('date_to' , '>=' , $date_from)
                                                                ->orderBy('inventory_id', 'desc')
                                                                ->skip(2)
                                                                ->take(3)
                                                                ->get();

                                                if(empty($inv_rooms2[0])){
                                                    $array=array('no_of_rooms'=>$inv_rooms['no_of_rooms'],'block_status'=>0,'los'=>$los,'room_type_id'=>$room_type_id,'date'=>$date_from,'day'=>$day);
                                                    array_push($filtered_inventory,$array);
                                                }
                                                else{
                                                    $array=array('no_of_rooms'=>$inv_rooms2[0]['no_of_rooms'],'block_status'=>0,'los'=>$los,'room_type_id'=>$room_type_id,'date'=>$date_from,'day'=>$day);
                                                    array_push($filtered_inventory,$array);
                                                }
                                            }
                                            else{
                                                $array=array('no_of_rooms'=>$inv_rooms1[0]['no_of_rooms'],'block_status'=>0,'los'=>$los,'room_type_id'=>$room_type_id,'date'=>$date_from,'day'=>$day);
                                                array_push($filtered_inventory,$array);
                                            }
                                        }
                                    }
                                    else{
                                        $array=array('no_of_rooms'=>$inv_rooms['no_of_rooms'],'block_status'=>0,'los'=>$los,'room_type_id'=>$room_type_id,'date'=>$date_from,'day'=>$day);
                                        array_push($filtered_inventory,$array);
                                    }
                                }
                                else{
                                    $array=array('no_of_rooms'=>$inv_rooms['no_of_rooms'],'block_status'=>0,'los'=>$los,'room_type_id'=>$room_type_id,'date'=>$date_from,'day'=>$day);
                                    array_push($filtered_inventory,$array);
                                }
                            }
                        }
                    }
                }
                $date_from=date('Y-m-d', strtotime($d . ' +1 day'));
            }
        }
        return $filtered_inventory;
    }
    public function getBookingByRoomType($hotel_id,$room_type_id,$date_from,$date_to,$ota_id){
        $filtered_booking=array();
        $cmort=new CmOtaRoomTypeSynchronize();
        $cm_booking=new CmOtaBooking();
        $date1=date_create($date_from);
        $date2=date_create($date_to);
        $date3=date_create(date('Y-m-d'));
        $diff=date_diff($date1,$date2);
        $diff=$diff->format("%a");
        for($i=1;$i<=$diff; $i++ )
        {
            $ota_booking=0;
            $d=$date_from;
            $cm_booking_details= $cm_booking
                                ->select('rooms_qty', 'room_type','channel_name')
                                ->where('hotel_id' , '=' , $hotel_id)
                                ->where('checkin_at' , '<=' , $d)
                                ->where('checkout_at' , '>' , $d)
                                ->where('confirm_status' , '=' , 1)
                                ->where('cancel_status' , '=' , 0)
                                ->get();
            foreach ($cm_booking_details as $cmb)
            {
                $room_type=explode(',',$cmb['room_type']);
                $room_qty=explode(',',$cmb['rooms_qty']);
                if(sizeof( $room_type)!=sizeof($room_qty))
                {
                    $ota_booking=0;
                }
                else
                {
                    for($r=0;$r<sizeof($room_type);$r++)
                    {
                        $ota_room_type=$room_type[$r];
                        $hotelroom_id=$cmort->getSingleHotelRoomIdFromRoomSynch($ota_room_type,$hotel_id);
                        if($hotelroom_id==$room_type_id)
                        {
                            $ota_booking=$ota_booking+$room_qty[$r];
                        }
                    }
                }
            }
            $array=array(
                'booking'=>$ota_booking,
                'date'=> $d
            );
            array_push($filtered_booking,$array);
            $date_from=date('Y-m-d', strtotime($d . ' +1 day'));
        }
        return $filtered_booking;
    }
    public function getRatePlan(int $ota_id,int $hotel_id,string $date_from,string $date_to){
        $date_from=date('Y-m-d',strtotime($date_from));
        $date_to=date('Y-m-d',strtotime($date_to));
        $room_type_n_rate_plans=DB::table('kernel.room_rate_plan')
            ->join('kernel.rate_plan_table as a', 'room_rate_plan.rate_plan_id', '=', 'a.rate_plan_id')
            ->join('kernel.room_type_table as b','room_rate_plan.room_type_id', '=', 'b.room_type_id')
            ->select('b.room_type_id','room_type','a.rate_plan_id','plan_type','plan_name','master_plan_status')
            ->where('room_rate_plan.hotel_id',$hotel_id)->where('room_rate_plan.is_trash',0)
            ->distinct()
            ->get();
        if($room_type_n_rate_plans){
            foreach($room_type_n_rate_plans as $all_types){
                if($all_types->rate_plan_id){
                    $data=$this->getRatesByRoomnRatePlan($ota_id, $date_from, $date_to, $all_types->room_type_id,$all_types->rate_plan_id);
                    $all_types->rates=$data;
                }
                else{
                    unset($all_types);
                }
            }
            $res=array('status'=>1,'message'=>"Hotel room rates retrieved successfully ",'data'=>$room_type_n_rate_plans);
            return response()->json($res);
        }
        else{
            $res=array('status'=>1,'message'=>"Hotel room rates retrieval failed");
        }
    }
    public function getRatesByRoomnRatePlan(int $ota_id,string $date_from ,string $date_to,int $room_type_id,int $rate_plan_id)
    {
        $filtered_rate=array();
        $date1=date_create($date_from);
        $date2=date_create($date_to);
        $date3=date_create(date('Y-m-d'));
        $diff=date_diff($date1,$date2);
        $diff=$diff->format("%a");
        $room_rate_plan_data =DB::table('kernel.room_rate_plan')->where(['rate_plan_id' => $rate_plan_id])
                                ->where(['room_type_id' => $room_type_id])
                                ->select('hotel_id')
                                ->first();
        if(empty($room_rate_plan_data)){
            return $resp = array('status'=>0,'message'=>"Provided room type and rate plan don't belongs to any hotel");
        }
        $hotel_id=$room_rate_plan_data->hotel_id;
        $hotel_info=DB::table('kernel.hotels_table')->where('hotel_id',$hotel_id)->first();
        $comp_info=DB::table('kernel.company_table')->where('company_id',$hotel_info->company_id)->first();
        $hex_code=$comp_info->hex_code;
        $currency=$comp_info->currency;
        $j=0;
        for($i=1;$i<=$diff; $i++ )
        {
            $d=$date_from;
            $timestamp = strtotime($d);
            $day = date('D', $timestamp);
            $get_ota_name = CmOtaDetails::select('ota_name')->where('ota_id',$ota_id)->first();
            $rate_plan_log_details = OtaRatePlan::
                                    select('bar_price','multiple_occupancy','multiple_days','block_status','extra_adult_price','extra_child_price')
                                    ->where('hotel_id',$hotel_id)
                                    ->where('channel','=',$get_ota_name->ota_name)
                                    ->where('room_type_id' ,'=', $room_type_id)
                                    ->where('rate_plan_id' , '=' , $rate_plan_id)
                                    ->where('from_date' , '<=' , $d)
                                    ->where('to_date' , '>=' , $d)
                                    ->orderBy('rate_plan_log_id', 'desc')
                                    ->first();
            if(empty($rate_plan_log_details))
            {
                $array=array(
                'bar_price'=>0,
                'multiple_occupancy'=>0,
                'extra_adult_price' => 0,
                'extra_child_price' => 0,
                'rate_plan_id'=>$rate_plan_id,
                'room_type_id'=>$room_type_id,
                'block_status'=>0,
                'date'=>$date_from,
                'day'=>$day,
                'hex_code'=>$hex_code,
                'currency'=>$currency
                );
                array_push($filtered_rate,$array);
            }
            else
            {
                $multiple_days=json_decode($rate_plan_log_details->multiple_days);
                $block_status = $rate_plan_log_details->block_status;
                if($multiple_days!=null)
                {
                    if($multiple_days->$day==0)
                    {
                        $rate_plan_log_details1 = OtaRatePlan::
                                                select('bar_price','multiple_occupancy', 'multiple_days','block_status','extra_adult_price','extra_child_price')
                                                ->where('room_type_id', $room_type_id)
                                                ->where('rate_plan_id' , '=' , $rate_plan_id)
                                                ->where('from_date' , '<=' , $d)
                                                ->where('to_date' , '>=' , $d)
                                                ->orderBy('rate_plan_log_id', 'desc')
                                                ->skip(1)
                                                ->take(2)
                                                ->get();
                        if(empty($rate_plan_log_details1[0]))
                        {
                                $array=array(
                                'bar_price'=>0,
                                'multiple_occupancy'=>0,
                                'extra_adult_price' => $extra_adult_price,
                                'extra_child_price' => $extra_child_price,
                                'rate_plan_id'=>$rate_plan_id,
                                'room_type_id'=>$room_type_id,
                                'block_status'=>$block_status,
                                'date'=>$date_from,
                                'day'=>$day,
                                'block_status'=>$block_status,
                                'hex_code'=>$hex_code,
                                'currency'=>$currency
                                );
                        }
                        else
                        {
                            $multiple_days1=json_decode($rate_plan_log_details1[0]->multiple_days);
                            if($multiple_days1!=null)
                            {
                                if($multiple_days1->$day==0)
                                {
                                    $rate_plan_log_details2 = OtaRatePlan::
                                                            select('bar_price','multiple_occupancy','block_status','extra_adult_price','extra_child_price')
                                                            ->where('room_type_id',$room_type_id)
                                                            ->where('rate_plan_id' , '=' , $rate_plan_id)
                                                            ->where('from_date' , '<=' , $d)
                                                            ->where('to_date' , '>=' , $d)
                                                            ->orderBy('rate_plan_log_id', 'desc')
                                                            ->skip(2)
                                                            ->take(3)
                                                            ->get();
                                    if(empty($rate_plan_log_details2[0]))
                                    {
                                            $array=array(
                                            'bar_price'=>$rate_plan_log_details->bar_price ,
                                            'multiple_occupancy'=>json_decode($rate_plan_log_details->multiple_occupancy),
                                            'extra_adult_price' => $rate_plan_log_details->extra_adult_price,
                                            'extra_child_price' => $rate_plan_log_details->extra_child_price,
                                            'rate_plan_id'=>$rate_plan_id,
                                            'room_type_id'=>$room_type_id,
                                            'block_status'=>$rate_plan_log_details->block_status,
                                            'date'=>$date_from,
                                            'day'=>$day,
                                            'hex_code'=>$hex_code,
                                            'currency'=>$currency
                                            );
                                    }
                                    else
                                    {
                                            $array=array(
                                            'bar_price'=>$rate_plan_log_details2[0]->bar_price,
                                            'multiple_occupancy'=>json_decode($rate_plan_log_details2[0]->multiple_occupancy),
                                            'extra_adult_price' => $rate_plan_log_details2[0]->extra_adult_price,
                                            'extra_child_price' => $rate_plan_log_details2[0]->extra_child_price,
                                            'rate_plan_id'=>$rate_plan_id,
                                            'room_type_id'=>$room_type_id,
                                            'block_status'=>$rate_plan_log_details2[0]['block_status'],
                                            'date'=>$date_from,
                                            'day'=>$day,
                                            'hex_code'=>$hex_code,
                                            'currency'=>$currency
                                        );
                                    }
                                }
                                else
                                {
                                    $array=array(
                                    'bar_price'=>$rate_plan_log_details1[0]->bar_price,
                                    'multiple_occupancy'=>json_decode($rate_plan_log_details1[0]->multiple_occupancy),
                                    'extra_adult_price' => $rate_plan_log_details1[0]->extra_adult_price,
                                    'extra_child_price' => $rate_plan_log_details1[0]->extra_child_price,
                                    'rate_plan_id'=>$rate_plan_id,
                                    'room_type_id'=>$room_type_id,
                                    'block_status'=>$rate_plan_log_details1[0]['block_status'],
                                    'date'=>$date_from,
                                    'day'=>$day,
                                    'hex_code'=>$hex_code,
                                    'currency'=>$currency
                                    );
                                }
                            }
                            else
                            {
                                $array=array(
                                    'bar_price'=>$rate_plan_log_details['bar_price'],
                                    'multiple_occupancy'=>json_decode($rate_plan_log_details['multiple_occupancy']),
                                    'extra_adult_price' => $rate_plan_log_details['extra_adult_price'],
                                    'extra_child_price' => $rate_plan_log_details['extra_child_price'],
                                    'rate_plan_id'=>$rate_plan_id,
                                    'room_type_id'=>$room_type_id,
                                    'block_status'=>$block_status,
                                    'date'=>$date_from,
                                    'day'=>$day,
                                    'hex_code'=>$hex_code,
                                    'currency'=>$currency
                                );
                            }
                        }
                    }
                    else
                    {
                        $array=array(
                            'bar_price'=>$rate_plan_log_details['bar_price'],
                            'multiple_occupancy'=>json_decode($rate_plan_log_details['multiple_occupancy']),
                            'extra_adult_price' => $rate_plan_log_details['extra_adult_price'],
                            'extra_child_price' => $rate_plan_log_details['extra_child_price'],
                            'rate_plan_id'=>$rate_plan_id,
                            'room_type_id'=>$room_type_id,
                            'block_status'=>$block_status,
                            'date'=>$date_from,
                            'day'=>$day,
                            'hex_code'=>$hex_code,
                            'currency'=>$currency
                        );
                    }
                }
                else
                {
                    $array=array(
                        'bar_price'=>$rate_plan_log_details['bar_price'],
                        'multiple_occupancy'=>json_decode($rate_plan_log_details['multiple_occupancy']),
                        'extra_adult_price' => $rate_plan_log_details['extra_adult_price'],
                        'extra_child_price' => $rate_plan_log_details['extra_child_price'],
                        'rate_plan_id'=>$rate_plan_id,
                        'room_type_id'=>$room_type_id,
                        'block_status'=>$block_status,
                        'date'=>$date_from,
                        'day'=>$day,
                        'hex_code'=>$hex_code,
                        'currency'=>$currency
                    );
                }
                array_push($filtered_rate,$array);
            }
            $date_from=date('Y-m-d', strtotime($d . '+1 day'));
        }
        return $filtered_rate;
    }
}
