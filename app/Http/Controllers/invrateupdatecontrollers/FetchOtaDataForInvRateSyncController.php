<?php
namespace App\Http\Controllers\invrateupdatecontrollers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Validator;
use DB;
use App\OtaInventory;//new model for single ota inv push
use App\OtaRatePlan;//new model for single ota rate push
use App\HotelInformation;
use App\CompanyDetails;
use App\CmOtaRoomTypeSynchronize;
use App\CmOtaRatePlanSynchronize;
use App\Http\Controllers\Controller;
use App\Http\Controllers\IpAddressService;
/**
 * This controller is used for Fetching the inventory and rate for a specific ota.
 * @auther ranjit
 * created date 25/06/2020.
 */
class FetchOtaDataForInvRateSyncController extends Controller{
    public function getInventoryBySourceOta(string $source_ota_name,int $room_type_id ,string $date_from ,string $date_to,int $mindays){
        $filtered_inventory=array();
        $inventory=new OtaInventory();
        $date1=date_create($date_from);
        $date2=date_create($date_to);
        $date3=date_create(date('Y-m-d'));
        $diff=date_diff($date1,$date2);
        $diff=$diff->format("%a");
        $diff1=date_diff($date1,$date3);
        $diff1=$diff1->format("%a");
        if($diff1<=$mindays && $mindays!=0){
            $d=$date_from;
            $timestamp = strtotime($d);
            $day = date('D', $timestamp);
            $array=array('no_of_rooms'=>0,'block_status'=>1,'los'=>1,'room_type_id'=>$room_type_id,'date'=>$date_from,'day'=>$day);
            array_push($filtered_inventory,$array);
        }
        else{
        for($i=1;$i<=$diff; $i++ ){
          $d=$date_from;
          $timestamp = strtotime($d);
          $day = date('D', $timestamp);
          $inventory_details= $inventory
                              ->where('room_type_id' , '=' , $room_type_id)
                              ->where('date_from' , '<=' , $d)
                              ->where('date_to' , '>=' , $d)
                              ->where('channel', '=' ,$source_ota_name)
                              ->orderBy('inventory_id', 'desc')
                              ->first();
           if(empty($inventory_details)){
                  continue;
           }
           else{
                $block_status           = trim($inventory_details->block_status);
                $los                    = trim($inventory_details->los);
                if($block_status==1){
                    $inv_rooms = $inventory
                                ->select('no_of_rooms')
                                ->where('room_type_id' , '=' , $room_type_id)
                                ->where('date_from' , '<=' , $date_from)
                                ->where('date_to' , '>=' , $date_from)
                                ->where('channel',$source_ota_name)
                                ->where('block_status' , '=' , 0)
                                ->orderBy('inventory_id', 'desc')
                                ->first();
                    if(empty($inv_rooms)){
                        continue;
                    }
                    else if(!empty($inv_rooms)){
                        $array=array('no_of_rooms'=>$inv_rooms['no_of_rooms'],'block_status'=>1,'los'=>$los,'room_type_id'=>$room_type_id,'date'=>$date_from,'day'=>$day);
                        array_push($filtered_inventory,$array);
                    }
                }
                else{
                    $inv_rooms = $inventory
                                 ->select('no_of_rooms')
                                ->where('room_type_id' , '=' , $room_type_id)
                                ->where('date_from' , '<=' , $date_from)
                                ->where('date_to' , '>=' , $date_from)
                                ->where('channel',$source_ota_name)
                                ->orderBy('inventory_id', 'desc')
                                ->first();
                    if($inv_rooms){
                        $array=array('no_of_rooms'=>$inv_rooms['no_of_rooms'],'block_status'=>0,'los'=>$los,'room_type_id'=>$room_type_id,'date'=>$date_from,'day'=>$day);
                        array_push($filtered_inventory,$array);
                    }
                }
            }
           $date_from=date('Y-m-d', strtotime($d . ' +1 day'));
          }
      }
      return $filtered_inventory;
    }
      public function getRateBySourceOta(string $source_ota_name,int $room_type_id ,int $rate_plan_id,string $date_from ,string $date_to){
        $filtered_rate=array();
        $date1=date_create($date_from);
        $date2=date_create($date_to);
        $date3=date_create(date('Y-m-d'));
        $diff=date_diff($date1,$date2);
        $diff=$diff->format("%a");
        $rateplanlog = new OtaRatePlan();
        $room_rate_plan_data = $rateplanlog->where(['rate_plan_id' => $rate_plan_id])
                                ->where(['room_type_id' => $room_type_id])
                                ->where('channel',$source_ota_name)
                                ->select('hotel_id')
                                ->first();
        $hotel_id=$room_rate_plan_data->hotel_id;
        $hotel_info=HotelInformation::where('hotel_id',$hotel_id)->first();
        $comp_info=CompanyDetails::where('company_id',$hotel_info->company_id)->first();
        $hex_code=$comp_info->hex_code;
        $currency=$comp_info->currency;
        for($i=1;$i<=$diff; $i++ ){
            $d=$date_from;
            $timestamp = strtotime($d);
            $day = date('D', $timestamp);

            $room_rate_plan_details = $rateplanlog
                                    ->where(['rate_plan_id' => $rate_plan_id])
                                    ->where(['room_type_id' => $room_type_id])
                                    ->where('channel',$source_ota_name)
                                    ->where('from_date' , '<=' , $d)
                                    ->where('to_date' , '>=' , $d)
                                    ->first();
            if(!isset($room_rate_plan_details->rate_plan_id)){
                $rate_plan_details = $masterhotelrateplan
                ->where(['rate_plan_id' => $rate_plan_id])
                ->where(['room_type_id' => $room_type_id])
                ->orderBy('created_at', 'desc')
                ->first();
            }
            else{
                $rate_plan_details=$room_rate_plan_details;
            }
            $bar_price = $rate_plan_details['bar_price'];
            $multiple_occupancy = $rate_plan_details['multiple_occupancy'];
            $bookingjini_price = $rate_plan_details['bookingjini_price'];
            $extra_adult_price = $rate_plan_details['extra_adult_price'];
            $extra_child_price = $rate_plan_details['extra_child_price'];
            $before_days_offer = $rate_plan_details['before_days_offer'];
            $stay_duration_offer = $rate_plan_details['stay_duration_offer'];
            $lastminute_offer = $rate_plan_details['lastminute_offer'];
            $rate_plan_log_details = $rateplanlog
                                    ->select('bar_price','multiple_occupancy','multiple_days','block_status','extra_adult_price','extra_child_price')
                                    ->where(['room_type_id' => $room_type_id])
                                    ->where('rate_plan_id' , '=' , $rate_plan_id)
                                    ->where('channel',$source_ota_name)
                                    ->where('from_date' , '<=' , $d)
                                    ->where('to_date' , '>=' , $d)
                                    ->orderBy('rate_plan_log_id', 'desc')
                                    ->first();
            if(empty($rate_plan_log_details)){
                $array=array(
                'bar_price'=>$bar_price ,
                'multiple_occupancy'=>json_decode($multiple_occupancy),
                'bookingjini_price' => $bookingjini_price,
                'extra_adult_price' => $extra_adult_price,
                'extra_child_price' => $extra_child_price,
                'before_days_offer' => $before_days_offer,
                'stay_duration_offer' => $stay_duration_offer,
                'lastminute_offer' => $lastminute_offer,
                'rate_plan_id'=>$rate_plan_id,
                'room_type_id'=>$room_type_id,
                'date'=>$date_from,
                'day'=>$day,
                'hex_code'=>$hex_code,
                'block_status'=>0,
                'currency'=>$currency

            );
                array_push($filtered_rate,$array);
            }
        else{
          $multiple_days=json_decode($rate_plan_log_details->multiple_days);
          $block_status     = $rate_plan_log_details['block_status'];
          if($multiple_days!=null){
            if($multiple_days->$day==0){
            $rate_plan_log_details1 = $rateplanlog
                                    ->select('bar_price','multiple_occupancy', 'multiple_days','block_status','extra_adult_price','extra_child_price')
                                    ->where(['room_type_id' => $room_type_id])
                                    ->where('rate_plan_id' , '=' , $rate_plan_id)
                                    ->where('from_date' , '<=' , $d)
                                    ->where('to_date' , '>=' , $d)
                                    ->where('channel',$source_ota_name)
                                    ->orderBy('rate_plan_log_id', 'desc')
                                    ->skip(1)
                                    ->take(2)
                                    ->get();
            if(empty($rate_plan_log_details1[0])){
                    $array=array(
                    'bar_price'=>$bar_price ,
                    'multiple_occupancy'=>json_decode($multiple_occupancy),
                    'bookingjini_price' => $bookingjini_price,
                    'extra_adult_price' => $extra_adult_price,
                    'extra_child_price' => $extra_child_price,
                    'before_days_offer' => $before_days_offer,
                    'stay_duration_offer' => $stay_duration_offer,
                    'lastminute_offer' => $lastminute_offer,
                    'rate_plan_id'=>$rate_plan_id,
                    'room_type_id'=>$room_type_id,
                    'date'=>$date_from,
                    'day'=>$day,
                    'hex_code'=>$hex_code,
                    'block_status'=>$block_status,
                    'currency'=>$currency
                    );
            }
            else{

                $multiple_days1=json_decode($rate_plan_log_details1[0]->multiple_days);
                $block_status1=$rate_plan_log_details1[0]['block_status'];
               if($multiple_days1!=null){
                  if($multiple_days1->$day==0){
                        $rate_plan_log_details2 = $rateplanlog
                                                ->select('bar_price','multiple_occupancy','block_status','extra_adult_price','extra_child_price')
                                                ->where(['room_type_id' => $room_type_id])
                                                ->where('rate_plan_id' , '=' , $rate_plan_id)
                                                ->where('from_date' , '<=' , $d)
                                                ->where('to_date' , '>=' , $d)
                                                ->where('channel',$source_ota_name)
                                                ->orderBy('rate_plan_log_id', 'desc')
                                                ->skip(2)
                                                ->take(3)
                                                ->get();
                        if(empty($rate_plan_log_details2[0])){
                            $array=array(
                            'bar_price'=>$bar_price ,
                            'multiple_occupancy'=>json_decode($multiple_occupancy),
                            'bookingjini_price' => $bookingjini_price,
                            'extra_adult_price' => $extra_adult_price,
                            'extra_child_price' => $extra_child_price,
                            'before_days_offer' => $before_days_offer,
                            'stay_duration_offer' => $stay_duration_offer,
                            'lastminute_offer' => $lastminute_offer,
                            'rate_plan_id'=>$rate_plan_id,
                            'room_type_id'=>$room_type_id,
                            'date'=>$date_from,
                            'day'=>$day,
                            'hex_code'=>$hex_code,
                            'block_status'=>$block_status1,
                            'currency'=>$currency
                            );
                        }
                        else{
                              $block_status2=$rate_plan_log_details2[0]['block_status'];
                                   $array=array(
                                  'bar_price'=>$rate_plan_log_details2[0]->bar_price,
                                  'multiple_occupancy'=>json_decode($rate_plan_log_details2[0]->multiple_occupancy),
                                  'bookingjini_price' => $bookingjini_price,
                                  'extra_adult_price' => $rate_plan_log_details2[0]->extra_adult_price,
                                  'extra_child_price' => $rate_plan_log_details2[0]->extra_child_price,
                                  'before_days_offer' => $before_days_offer,
                                  'stay_duration_offer' => $stay_duration_offer,
                                  'lastminute_offer' => $lastminute_offer,
                                  'rate_plan_id'=>$rate_plan_id,
                                  'room_type_id'=>$room_type_id,
                                  'date'=>$date_from,
                                  'day'=>$day,
                                  'hex_code'=>$hex_code,
                                  'block_status'=>$block_status2,
                                  'currency'=>$currency
                              );
                          }
                      }
                      else{
                           $array=array(
                          'bar_price'=>$rate_plan_log_details1[0]->bar_price,
                          'multiple_occupancy'=>json_decode($rate_plan_log_details1[0]->multiple_occupancy),
                          'bookingjini_price' => $bookingjini_price,
                          'extra_adult_price' => $rate_plan_log_details1[0]->extra_adult_price,
                          'extra_child_price' => $rate_plan_log_details1[0]->extra_child_price,
                          'before_days_offer' => $before_days_offer,
                          'stay_duration_offer' => $stay_duration_offer,
                          'lastminute_offer' => $lastminute_offer,
                          'rate_plan_id'=>$rate_plan_id,
                          'room_type_id'=>$room_type_id,
                          'date'=>$date_from,
                          'day'=>$day,
                          'block_status'=>$block_status1,
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
                            'bookingjini_price' => $bookingjini_price,
                            'extra_adult_price' => $rate_plan_log_details['extra_adult_price'],
                            'extra_child_price' => $rate_plan_log_details['extra_child_price'],
                            'before_days_offer' => $before_days_offer,
                            'stay_duration_offer' => $stay_duration_offer,
                            'lastminute_offer' => $lastminute_offer,
                            'rate_plan_id'=>$rate_plan_id,
                            'room_type_id'=>$room_type_id,
                            'date'=>$date_from,
                            'day'=>$day,
                            'block_status'=>$block_status1,
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
                        'bookingjini_price' => $bookingjini_price,
                        'extra_adult_price' => $rate_plan_log_details['extra_adult_price'],
                        'extra_child_price' => $rate_plan_log_details['extra_child_price'],
                        'before_days_offer' => $before_days_offer,
                        'stay_duration_offer' => $stay_duration_offer,
                        'lastminute_offer' => $lastminute_offer,
                        'rate_plan_id'=>$rate_plan_id,
                        'room_type_id'=>$room_type_id,
                        'date'=>$date_from,
                        'day'=>$day,
                        'block_status'=>$block_status,
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
                        'bookingjini_price' => $bookingjini_price,
                        'extra_adult_price' => $rate_plan_log_details['extra_adult_price'],
                        'extra_child_price' => $rate_plan_log_details['extra_child_price'],
                        'before_days_offer' => $before_days_offer,
                        'stay_duration_offer' => $stay_duration_offer,
                        'lastminute_offer' => $lastminute_offer,
                        'rate_plan_id'=>$rate_plan_id,
                        'room_type_id'=>$room_type_id,
                        'date'=>$date_from,
                        'day'=>$day,
                        'block_status'=>$block_status,
                        'hex_code'=>$hex_code,
                        'currency'=>$currency
                    );
                }
              array_push($filtered_rate,$array);
              }
            $date_from=date('Y-m-d', strtotime($d . ' +1 day'));
          }
          return $filtered_rate;
      }
}
