<?php
namespace App\Http\Controllers\invrateupdatecontrollers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Validator;
use DB;
use App\Inventory;
use App\LogTable;
use App\RateUpdateLog;
use App\MasterRoomType;
use App\HotelInformation;
use App\CompanyDetails;
use App\RatePlanLog;
use App\OtaInventory;//new model for single ota inv push
use App\OtaRatePlan;//new model for single ota rate push
use App\CmOtaRoomTypeSynchronize;
use App\CmOtaRatePlanSynchronize;
use App\Http\Controllers\Controller;
use App\Http\Controllers\IpAddressService;
use App\Http\Controllers\invrateupdatecontrollers\GetDataForRateController;
/**
 * This controller is used for Goomo single,bulk,sync and block of inv and rate
 * @auther ranjit
 * created date 08/03/19.
 * modification due to display problem @ 12/03/19 by ranjit(new model added)
 */
class GoomoInvRateController extends Controller
{
    protected $getdata_curlreq;
    public function __construct(GetDataForRateController $getdata_curlreq)
    {
       $this->getdata_curlreq = $getdata_curlreq;
    }
    public function inventorySycUpdate($bucket_data,$inventory,$auth_parameter,$commonUrl,$from_date,$to_date)
    {
        $cmOtaRoomTypeSynchronizeModel  = new CmOtaRoomTypeSynchronize();
        $logModel                       = new LogTable();
        $hotel_id                       = $bucket_data["bucket_hotel_id"];
        $ota_id                         = $bucket_data["bucket_ota_id"];
        $ota_hotel_code                 = $bucket_data["bucket_ota_hotel_code"];
        $post_data                      = '';
        $rlt                            =array();

        $ota_room_type = DB::table('cm_ota_room_type_synchronize')
            ->where('hotel_id', '=', $hotel_id)
            ->where('room_type_id', '=', $inventory['room_type_id'])
            ->where('ota_type_id', '=', $ota_id)
            ->value('ota_room_type');

            foreach($inventory['inv'] as $inv)
            {
                $otainventory                   = new OtaInventory();
                $fmonth=explode('-',$inv['date']);//for removing extra o from month and remove this code after mobile app update
                if(strlen($fmonth[1]) == 3)
                {
                    $fmonth[1]=ltrim($fmonth[1],0);
                }
                $inv['date']=implode('-',$fmonth);
                $room_code 					= $cmOtaRoomTypeSynchronizeModel->getOtaRoomType($inventory['room_type_id'],$ota_id);
                $startDate                  = $inv['date'];
                $endDate                    = $inv['date'];
                $room_qtys                  = $inv['no_of_rooms'];
                $inv['room_type_id']       = $inventory['room_type_id'];
                $inv['block_status']       = $inv["block_status"];
                $inv['channel']            = 'Goomo';
                $inv['ota_id']             = $ota_id;
                $inv['user_id']            = $bucket_data["bucket_user_id"];
                $inv['client_ip']          = $bucket_data["bucket_client_ip"];
                $inv['hotel_id']           = $hotel_id;
                $inv['date_from']          = $inv['date'];
                $inv['date_to']            = $inv['date'];
                $inv['multiple_days']      = '{"Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1","Sun":"1"}';

                try{
                        $otainventory->fill($inv)->save();
                      
                }
                catch(Exception $e){

                }
                if($room_code)
                {
                    $headers = array('Content-Type:application/json', 'Expect:');
                    $log_data               	= [
                        "action_id"          => 4,
                        "hotel_id"           => $hotel_id,
                        "ota_id"      		 => $ota_id,
                        "inventory_ref_id"   => $bucket_data["bucket_inventory_table_id"],
                        "user_id"            => $bucket_data["bucket_user_id"],
                        "request_msg"        => '',
                        "response_msg"       => '',
                        "request_url"        => '',
                        "status"         	 => 2,
                        "ip"         		 => $bucket_data["bucket_client_ip"],
                        "comment"			 => "Processing for update "
                        ];
                    $apiKey               			= trim($auth_parameter->apiKey);
                    $channelId               		= trim($auth_parameter->channelId);
                    $accessToken               		= trim($auth_parameter->accessToken);
                    if($inv['block_status']==0)
                    {
                        if($room_qtys >= 0)
                        {
                            $days=array('true', 'true', 'true','true','true','true','true');
                            $post_data=array("available" => $room_qtys, "block"=>false, "days"=>$days, "channelName"=> "Bookingjini",
                            "startDate" => $startDate,
                            "endDate" =>$endDate,
                            "roomId" => $room_code,
                            "productId"=>$ota_hotel_code);
                            $post_data=json_encode($post_data);
                        }
                    }
                    else
                    {
                        $days=array('true', 'true', 'true','true','true','true','true');
                        $post_data=array("available" => 0, "block"=>true, "days"=>$days, "channelName"=> "Bookingjini",
                        "startDate" => $startDate,
                        "endDate" =>$endDate,
                        "roomId" => $room_code,
                        "productId"=>$ota_hotel_code);
                        $post_data=json_encode($post_data);
                    }
                    $log_request_msg=$commonUrl.'/updateInventory'.$post_data;
                    $url=$commonUrl."/updateInventory";
                    $headers = array();
                    $headers[] = "apiKey: $apiKey";
                    $headers[] = "channelId: $channelId";
                    $headers[] = "accessToken: $accessToken";
                    $headers[] = "Content-Type: application/json";
                    $logModel->fill($log_data)->save();
                    $result=$this->getdata_curlreq->cUrlCall($url,$headers,$post_data);//for curl call
                    $array_data = json_decode($result, true);
                    if(!isset($array_data['Error'])){
                        DB::table('log_table')->where('id', $logModel->id)
                        ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                        $rlt=array('status'=>1,'ota_name'=>'goomo','response_msg'=>$result);
                    }
                    else
                    {
                        DB::table('log_table')->where('id', $logModel->id)
                        ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                        $rlt=array('status'=>0,'ota_name'=>'goomo','response_msg'=>$result);
                    }
                }
                else
                {
                    // set log for Booking Room Type is not synch with hotel Room Type.
                    $log_data                 	= [
                                                "action_id"          => 2,
                                                "hotel_id"           => $hotel_id,
                                                "ota_id"      		 => $ota_id,
                                                "inventory_ref_id"   => $bucket_data["bucket_inventory_table_id"],
                                                "user_id"            => $bucket_data["bucket_user_id"],
                                                "request_msg"        => '',
                                                "response_msg"       => '',
                                                "request_url"        => '',
                                                "status"         	 => 0,
                                                "ip"         		 => $bucket_data["bucket_client_ip"],
                                                "comment"			 => 'Roomrate type is not mapped'
                                                ];
                    $logModel->fill($log_data)->save();
                    $rlt=array('status'=>0,'ota_name'=>'goomo','response_msg'=>"Roomtype should be sync");
                }
            }
            if(sizeof($rlt)==0){
                $rlt=array('status'=>0,'ota_name'=>'goomo','response_msg'=>"Roomtype should be mapped");
            }
        return $rlt;
    }
    public function blockInventoryUpdate($bucket_data,$room_type_id,$data,$auth_parameter,$commonUrl)
    {
        $blocklog                       = new LogTable();
        $hotel_id                       = $bucket_data["bucket_hotel_id"];
        $ota_id                         = $bucket_data["bucket_ota_id"];
        $ota_hotel_code                 = $bucket_data["bucket_ota_hotel_code"];
        $data['channel']                = 'Goomo';
        $rlt                            =array();
        $otainventory               = new OtaInventory();
        $data['room_type_id']       = $room_type_id;
        $data['ota_id']             = $ota_id;
        $data['user_id']            = $bucket_data["bucket_user_id"];
        $data['client_ip']          = $bucket_data["bucket_client_ip"];
        $data['hotel_id']           = $hotel_id;
        $success_flag=1;
            try{
                $otainventory->fill($data)->save();
            }
            catch(\Exception $e){
               $success_flag=0;
            }
        if($success_flag)
        {
            $inventoryId     = $otainventory->inventory_id;
            $hotel_id        = $otainventory->hotel_id;
            $room_code       = DB::table('cm_ota_room_type_synchronize')
                               ->where('hotel_id', '=', $hotel_id)
                               ->where('room_type_id', '=', $room_type_id)
                               ->where('ota_type_id', '=', $ota_id)
                               ->value('ota_room_type');
            $startDate       = $data['date_from'];
            $endDate         = $data['date_to'];
            if($room_code)
            {
                $headers = array('Content-Type:application/json', 'Expect:');
                $log_data                 		= [
                    "action_id"          => 3,
                    "hotel_id"           => $hotel_id,
                    "ota_id"      		 => $ota_id,
                    "inventory_ref_id"   => $inventoryId,
                    "user_id"            => $bucket_data["bucket_user_id"],
                    "request_msg"        =>  '',
                    "response_msg"       =>  '',
                    "request_url"        =>  '',
                    "status"         	 => 2,
                    "ip"         		 => $bucket_data["bucket_client_ip"],
                    "comment"			 => "Processing for update"
                ];
                $apiKey               			= trim($auth_parameter->apiKey);
                $channelId               		= trim($auth_parameter->channelId);
                $accessToken               		= trim($auth_parameter->accessToken);
                $days=array('true', 'true', 'true','true','true','true','true');
                $post_data=array("available" => 0, "block"=>true, "days"=>$days, "channelName"=> "Bookingjini",
                "startDate" => $startDate,
                "endDate" =>$endDate,
                "roomId" => $room_code,
                "productId"=>$ota_hotel_code);
                $post_data=json_encode($post_data);
                $blocklog->fill($log_data)->save();
                $log_request_msg=$commonUrl.'/updateInventory'.$post_data;
                $url=$commonUrl."/updateInventory";
                $headers = array();
                $headers[] = "apiKey: $apiKey";
                $headers[] = "channelId: $channelId";
                $headers[] = "accessToken: $accessToken";
                $headers[] = "Content-Type: application/json";
                $result=$this->getdata_curlreq->cUrlCall($url,$headers,$post_data);//for curl call
                $array_data = json_decode($result, true);
                if(!isset($array_data['Error'])){
                    DB::table('log_table')->where('id', $blocklog->id)
                    ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                    $rlt=array('status'=>1,'ota_name'=>'goomo','response_msg'=>$result);
                }
                else
                {
                    DB::table('log_table')->where('id', $blocklog->id)
                    ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                    $rlt=array('status'=>0,'ota_name'=>'goomo','response_msg'=>$result);

                }
            }
            else{
                $log_data                 		= [
                    "action_id"          => 3,
                    "hotel_id"           => $hotel_id,
                    "ota_id"      		 => $ota_id,
                    "inventory_ref_id"   => $bucket_data["bucket_inventory_table_id"],
                    "user_id"            => $bucket_data["bucket_user_id"],
                    "request_msg"        =>  '',
                    "response_msg"       =>  '',
                    "request_url"        =>  '',
                    "status"         	 => 2,
                    "ip"         		 => $bucket_data["bucket_client_ip"],
                    "comment"			 => "Roomrate type is not mapped"
                    ];

                $blocklog->fill($log_data)->save();
                $rlt=array('status' => 0,'ota_name'=>'goomo','response'=> 'Roomtype should be Sync');
            }
        }
        if(sizeof($rlt)==0){
            $rlt=array('status'=>0,'ota_name'=>'goomo','response_msg'=>"Roomtype should be mapped");
        }
       return $rlt;
    }
    public function singleInvUpdate($bucket_data,$inventory,$auth_parameter,$commonUrl)
    {
        $cmOtaRoomTypeSynchronizeModel  = new CmOtaRoomTypeSynchronize();
        $logModel                       = new LogTable();
        $hotel_id                       = $bucket_data["bucket_hotel_id"];
        $ota_id                         = $bucket_data["bucket_ota_id"];
        $ota_hotel_code                 = $bucket_data["bucket_ota_hotel_code"];
        $rlt                            =array();

        foreach($inventory as $invs)
        {
            $ota_room_type = DB::table('cm_ota_room_type_synchronize')
            ->where('hotel_id', '=', $hotel_id)
            ->where('room_type_id', '=', $invs['room_type_id'])
            ->where('ota_type_id', '=', $ota_id)
            ->value('ota_room_type');
            $xml_data='';

            foreach($invs['inv'] as $inv)
            {
                $otainventory                   = new OtaInventory();
                $fmonth=explode('-',$inv['date']);//for removing extra o from month and remove this code after mobile app update
                if(strlen($fmonth[1]) == 3)
                {
                    $fmonth[1]=ltrim($fmonth[1],0);
                }
                $inv['date']=implode('-',$fmonth);
                $room_code 					= $cmOtaRoomTypeSynchronizeModel->getOtaRoomType($invs['room_type_id'],$ota_id);
                $startDate                  = $inv['date'];
                $endDate                    = $inv['date'];
                $inventory_los              = $inv['los'];
                $room_qtys                  = $inv['no_of_rooms'];

                $inv['room_type_id']       = $invs['room_type_id'];
                $inv['block_status']       = 0;
                $inv['channel']            = 'Goomo';
                $inv['ota_id']             = $ota_id;
                $inv['user_id']            = $bucket_data["bucket_user_id"];
                $inv['client_ip']          = $bucket_data["bucket_client_ip"];
                $inv['hotel_id']           = $hotel_id;
                $inv['date_from']          = $inv['date'];
                $inv['date_to']            = $inv['date'];
                $success_flag=1;
                try{
                    $otainventory->fill($inv)->save();
                }
                catch(\Exception $e){
                   $success_flag=0;
                }
                if($success_flag)
                {
                    $inventoryId   = $otainventory->inventory_id;
                    if($room_code)
                    {
                        $headers = array('Content-Type:application/json', 'Expect:');
                        $log_data               	= [
                            "action_id"          => 4,
                            "hotel_id"           => $hotel_id,
                            "ota_id"      		 => $ota_id,
                            "inventory_ref_id"   => $inventoryId,
                            "user_id"            => $bucket_data["bucket_user_id"],
                            "request_msg"        => '',
                            "response_msg"       => '',
                            "request_url"        => '',
                            "status"         	 => 2,
                            "ip"         		 => $bucket_data["bucket_client_ip"],
                            "comment"			 => "Processing for update "
                            ];
                        $apiKey               			= trim($auth_parameter->apiKey);
                        $channelId               		= trim($auth_parameter->channelId);
                        $accessToken               		= trim($auth_parameter->accessToken);
                        $days=array('true', 'true', 'true','true','true','true','true');
                        $post_data=array("available" => $room_qtys, "block"=>false, "days"=>$days, "channelName"=> "Bookingjini",
                        "startDate" => $startDate,
                        "endDate" =>$endDate,
                        "roomId" => $room_code,
                        "productId"=>$ota_hotel_code);
                        $post_data=json_encode($post_data);
                        $logModel->fill($log_data)->save();
                        $log_request_msg=$commonUrl.'/updateInventory'.$post_data;
                        $url=$commonUrl."/updateInventory";
                        $headers = array();
                        $headers[] = "apiKey: $apiKey";
                        $headers[] = "channelId: $channelId";
                        $headers[] = "accessToken: $accessToken";
                        $headers[] = "Content-Type: application/json";
                        $result=$this->getdata_curlreq->cUrlCall($url,$headers,$post_data);//for curl call
                        $array_data = json_decode($result, true);
                        if(!isset($array_data['Error'])){
                            DB::table('log_table')->where('id', $logModel->id)
                            ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                            $rlt=array('status'=>1,'ota_name'=>'goomo','response_msg'=>$result);
                        }
                        else
                        {
                            DB::table('log_table')->where('id', $logModel->id)
                            ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                            $rlt=array('status'=>0,'ota_name'=>'goomo','response_msg'=>$result);
                        }
                    }
                    else{
                        $log_data               	= [
                            "action_id"          => 4,
                            "hotel_id"           => $hotel_id,
                            "ota_id"      		 => $ota_id,
                            "inventory_ref_id"   => $bucket_data["bucket_inventory_table_id"],
                            "user_id"            => $bucket_data["bucket_user_id"],
                            "request_msg"        => '',
                            "response_msg"       => '',
                            "request_url"        => '',
                            "status"         	 => 2,
                            "ip"         		 => $bucket_data["bucket_client_ip"],
                            "comment"			 => "Roomrate type is not mapped"
                            ];
                            $logModel->fill($log_data)->save();
                            $rlt=array('status'=>0,'ota_name'=>'goomo','response_msg'=>"Roomtype should be sync");
                    }
                }
            }
        }
        if(sizeof($rlt)==0){
            $rlt=array('status'=>0,'ota_name'=>'goomo','response_msg'=>"Roomtype should be mapped");
        }
        return $rlt;
    }
    public function bulkInvUpdate($bucket_data,$data,$auth_parameter,$commonUrl)
    {
        $logModel                       = new LogTable();
        $otainventory                   = new OtaInventory();//used for insert in to ota inv table
        $cmOtaRoomTypeSynchronizeModel  = new CmOtaRoomTypeSynchronize();
        $hotel_id                       = $bucket_data["bucket_hotel_id"];
        $ota_id                         = $bucket_data["bucket_ota_id"];
        $ota_hotel_code                 = $bucket_data["bucket_ota_hotel_code"];
        $data['ota_id']                 = $ota_id;
        $data['user_id']                = $bucket_data["bucket_user_id"];
        $data['client_ip']              = $bucket_data["bucket_client_ip"];
        $data['channel']                = 'Goomo';
        $rlt                            =array();
        $success_flag=1;
                try{
                    $otainventory->fill($data)->save();
                }
                catch(\Exception $e){
                   $success_flag=0;
                }
        if($success_flag)
        {
            $invtefid           = $otainventory->inventory_id;
            $room_code 		    = $cmOtaRoomTypeSynchronizeModel->getOtaRoomType($data['room_type_id'],$ota_id);
            $startDate          = $data['date_from'];
            $endDate            = $data['date_to'];
            $inventory_los      = $data['los'];
            $room_qtys          = $data['no_of_rooms'];
            if($room_code)
            {
                $headers = array('Content-Type:application/json', 'Expect:');
                $log_data               	= [
                    "action_id"          => 4,
                    "hotel_id"           => $hotel_id,
                    "ota_id"      		 => $ota_id,
                    "inventory_ref_id"   => $invtefid,
                    "user_id"            => $bucket_data["bucket_user_id"],
                    "request_msg"        => '',
                    "response_msg"       => '',
                    "request_url"        => '',
                    "status"         	 => 2,
                    "ip"         		 => $bucket_data["bucket_client_ip"],
                    "comment"			 => "Processing for update "
                    ];
                    $apiKey               			= trim($auth_parameter->apiKey);
                    $channelId               		= trim($auth_parameter->channelId);
                    $accessToken               		= trim($auth_parameter->accessToken);
                    $days=array('true', 'true', 'true','true','true','true','true');
                    $post_data=array("available" => $room_qtys, "block"=>false, "days"=>$days, "channelName"=> "Bookingjini",
                    "startDate" => $startDate,
                    "endDate" =>$endDate,
                    "roomId" => $room_code,
                    "productId"=>$ota_hotel_code);
                    $post_data=json_encode($post_data);
                    $logModel->fill($log_data)->save();
                    $log_request_msg=$commonUrl.'/updateInventory'.$post_data;
                    $url=$commonUrl."/updateInventory";
                    $headers = array();
                    $headers[] = "apiKey: $apiKey";
                    $headers[] = "channelId: $channelId";
                    $headers[] = "accessToken: $accessToken";
                    $headers[] = "Content-Type: application/json";
                    $result=$this->getdata_curlreq->cUrlCall($url,$headers,$post_data);//for curl call
                    $array_data = json_decode($result, true);
                    if(!isset($array_data['Error'])){
                        DB::table('log_table')->where('id', $logModel->id)
                        ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                        $rlt=array('status'=>1,'ota_name'=>'goomo','response_msg'=>$result);
                    }
                    else
                    {
                        DB::table('log_table')->where('id', $logModel->id)
                        ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                        $rlt=array('status'=>0,'ota_name'=>'goomo','response_msg'=>$result);

                    }
            }
            else{
                $log_data               	= [
                    "action_id"          => 4,
                    "hotel_id"           => $hotel_id,
                    "ota_id"      		 => $ota_id,
                    "inventory_ref_id"   => $bucket_data["bucket_inventory_table_id"],
                    "user_id"            => $bucket_data["bucket_user_id"],
                    "request_msg"        => '',
                    "response_msg"       => '',
                    "request_url"        => '',
                    "status"         	 => 2,
                    "ip"         		 => $bucket_data["bucket_client_ip"],
                    "comment"			 => "Roomrate type is not mapped"
                    ];
                    $logModel->fill($log_data)->save();
                    $rlt=array('status'=>0,'ota_name'=>'goomo','response_msg'=>"Roomtype should be sync");
            }
            if(sizeof($rlt)==0){
                $rlt=array('status'=>0,'ota_name'=>'goomo','response_msg'=>"Roomtype should be mapped");
            }
            return $rlt;
        }
    }
    public function rateSyncUpdate($rate_bucket_data,$rates_data,$auth_parameter,$commonUrl,$from_date,$to_date)
    {
        $cmOtaRatePlanSynchronizeModel 	= new CmOtaRatePlanSynchronize();
        $roomTypeModel                  = new MasterRoomType();
        $logModel                       = new RateUpdateLog();
        
        $xml_all_data                   = '';
        $multiple_days                  = '{"Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1","Sun":"1"}';
        $hotel_id                       = $rate_bucket_data['bucket_hotel_id'];
        $ota_id                         = $rate_bucket_data['bucket_ota_id'];
        $ota_hotel_code                 = $rate_bucket_data['bucket_ota_hotel_code'];
        $count                          = 0;
        $flag                           = 0;
        $rlt                            = array();
        $count_rate                      = 0;
            foreach($rates_data['rates'] as $rates)
            {
                $otaRatePlan                    = new OtaRatePlan();
                $fmonth=explode('-',$rates['date']);//for removing extra o from month and remove this code after mobile app update
                if(strlen($fmonth[1]) == 3)
                {
                    $fmonth[1]=ltrim($fmonth[1],0);
                }
                $rates['date']=implode('-',$fmonth);
                $max_adult=$roomTypeModel->select('max_people')->where('room_type_id',$rates_data['room_type_id'])->first()->max_people;

                $rateplan_multiple_days_data = $this->getdata_curlreq->goomoDays($multiple_days);
                $currency=$this->getdata_curlreq->getCurrency($hotel_id);
                $extra_adult_price			 = 0;
                $extra_child_price			 = 0;
                $occupency=$this->getdata_curlreq->decideOccupencyPrice($max_adult,$rates['bar_price'],$rates['multiple_occupancy']);
                $rateplan_single_price=0;
                $rateplan_double_price=0;
                $rateplan_triple_price=0;
                $rateplan_fourth_price=0;
                if($occupency)
                {
                    $rateplan_single_price       = $occupency[0];
                    $rateplan_double_price       = $occupency[1];
                    $rateplan_triple_price       = $occupency[2];
                    if(isset($occupency[3]))
                    {
                        $rateplan_fourth_price       = $occupency[3];
                    }
                }

                $result 					 =  $cmOtaRatePlanSynchronizeModel->getOtaRoomIdFromRatePlanSynch($rates['room_type_id'],$ota_id,$rates['rate_plan_id']);
                if(isset($result[0])){
                    $ota_room_id                 =  $result[0]['ota_room_type_id'];
                    $ota_rate_plan_id            =  $result[0]['ota_rate_plan_id'];
                }
                else{
                    continue;
                }
                $startDate                   =  $rates['date'];
                $endDate                     =  $rates['date'];
                $rates['room_type_id']          = $rates_data['room_type_id'];
                $rates['rate_plan_id']          = $rates_data['rate_plan_id'];
                $rates['from_date']             = $rates['date'];
                $rates['to_date']               = $rates['date'];
                $rates['block_status']          = 0;
                $rates['los']                   = 1;
                $rates['multiple_days']         = $multiple_days;
                $rates['client_ip']             = $rate_bucket_data['bucket_client_ip'];
                $rates['user_id']               = $rate_bucket_data['bucket_user_id'];
                $rates['hotel_id']              = $hotel_id;
                $rates['channel']               = 'Goomo';
                $rates['multiple_occupancy']    = json_encode($rates['multiple_occupancy']);;
                try{
                    $otaRatePlan->fill($rates)->save();  
                }
                catch(Exception $e){

                }
                if($ota_rate_plan_id)
                {
                    $headers = array('Content-Type:application/json', 'Expect:');
                    $log_data               	= [
                        "action_id"          => 2,
                        "hotel_id"           => $hotel_id,
                        "ota_id"      		 => $ota_id,
                        "rate_ref_id"        => $rate_bucket_data['bucket_rate_plan_log_table_id'],
                        "user_id"            => $rate_bucket_data['bucket_user_id'],
                        "request_msg"        => '',
                        "response_msg"       => '',
                        "request_url"        => '',
                        "status"         	 => 2,
                        "ip"         		 => $rate_bucket_data['bucket_client_ip'],
                        "comment"			 => "Processing for update "
                        ];
                        if($extra_adult_price!=0 && $extra_child_price!=0)
                        {
                            $sellRate= [
                                "Double"  => $rateplan_double_price,
                                "Extra_adult"  =>  $extra_adult_price,
                                "Extra_child" =>  $extra_child_price,
                                "Infant"     => '',
                                "Single"   => $rateplan_single_price
                                ];
                        }
                        else
                        {
                            $sellRate= [
                                "Double"  => $rateplan_double_price,
                                "Extra_adult"  =>  "",
                                "Extra_child" =>  "",
                                "Infant"     => '',
                                "Single"   => $rateplan_single_price
                                ];
                        }
                        $roomRateId=$ota_room_id.'_'.$ota_rate_plan_id;
                        $post_data=array(
                        "channelName"=> "Bookingjini",
                        "contractType"=>"WEB",
                        "days"=>$rateplan_multiple_days_data,
                        "endDate" =>$endDate,
                        "productId"=>$ota_hotel_code,
                        "roomRateId" => $roomRateId,
                        "sellRate"=>$sellRate,
                        "startDate" => $startDate);
                        $post_data=json_encode($post_data);
                        $log_request_msg=$commonUrl.'/updatePrice'.$post_data;
                        $apiKey               			= trim($auth_parameter->apiKey);
                        $channelId               		= trim($auth_parameter->channelId);
                        $accessToken               		= trim($auth_parameter->accessToken);
                        $headers = array();
                        $headers[] = "apiKey: $apiKey";
                        $headers[] = "channelId: $channelId";
                        $headers[] = "accessToken: $accessToken";
                        $headers[] = "Content-Type: application/json";
                        $url=$commonUrl.'/updatePrice';
                        $logModel->fill($log_data)->save();
                        $result=$this->getdata_curlreq->cUrlCall($url,$headers,$post_data);//for curl call
                        $array_data = json_decode($result, true);

                        if(!isset($array_data['Error'])){
                            DB::table('rate_update_logs')->where('id', $logModel->id)
                            ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                            $rlt=array('status'=>1,'ota_name'=>'goomo','response_msg'=>$result);
                        }
                        else
                        {
                            DB::table('rate_update_logs')->where('id', $logModel->id)
                            ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                            $rlt=array('status'=>0,'ota_name'=>'goomo','response_msg'=>$result);
                        }
                }
                else{
                        $log_data               	= [
                            "action_id"          => 2,
                            "hotel_id"           => $hotel_id,
                            "ota_id"      		 => $ota_id,
                            "rate_ref_id"        => $rate_bucket_data['bucket_rate_plan_log_table_id'],
                            "user_id"            => $rate_bucket_data['bucket_user_id'],
                            "request_msg"        => '',
                            "response_msg"       => '',
                            "request_url"        => '',
                            "status"         	 => 2,
                            "ip"         		 => $rate_bucket_data['bucket_client_ip'],
                            "comment"			 => "Roomrate type is not mapped"
                            ];
                        $logModel->fill($log_data)->save();
                        $rlt=array('status'=>0,'ota_name'=>'goomo','response_msg'=>"Rateplan should be sync");
                    }
            }
            if(sizeof($rlt)==0){
                $rlt=array('status'=>0,'ota_name'=>'goomo','response_msg'=>"Rateplan should be mapped");
            }
        return $rlt;
    }
    public function singleRateUpdate($rate_bucket_data,$rates_data,$auth_parameter,$commonUrl)
    {
        $cmOtaRatePlanSynchronizeModel 	= new CmOtaRatePlanSynchronize();
        $roomTypeModel                  = new MasterRoomType();
        $logModel                       = new RateUpdateLog();
        $xml_all_data                   = '';
        $multiple_days                  = '{"Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1","Sun":"1"}';
        $hotel_id                       = $rate_bucket_data['bucket_hotel_id'];
        $ota_id                         = $rate_bucket_data['bucket_ota_id'];
        $ota_hotel_code                 = $rate_bucket_data['bucket_ota_hotel_code'];
        $rateplanId                     = array();
        $count                          = 0;
        $flag                           = 0;
        $rlt                            =array();


        foreach($rates_data as $rate)
        {
            $xml_data       = '';

            foreach($rate['rates'] as $rates)
            {
                $otaRatePlan                    = new OtaRatePlan();//ota rate table insertion
                $fmonth=explode('-',$rates['date']);//for removing extra o from month and remove this code after mobile app update
                if(strlen($fmonth[1]) == 3)
                {
                    $fmonth[1]=ltrim($fmonth[1],0);
                }
                $rates['date']=implode('-',$fmonth);
                $max_adult=$roomTypeModel->select('max_people')->where('room_type_id',$rate['room_type_id'])->first()->max_people;

                $rates['extra_adult_price']     = 0;
                $rates['extra_child_price']     = 0;
                $rates['room_type_id']          = $rate['room_type_id'];
                $rates['rate_plan_id']          = $rate['rate_plan_id'];
                $rates['from_date']             = $rates['date'];
                $rates['to_date']               = $rates['date'];
                $rates['block_status']          = 0;
                $rates['los']                   = 1;
                $rates['multiple_days']         = $multiple_days;
                $rates['client_ip']             = $rate_bucket_data['bucket_client_ip'];
                $rates['user_id']               = $rate_bucket_data['bucket_user_id'];
                $rates['hotel_id']              = $hotel_id;
                $rates['channel']               = 'Goomo';
                $min_max_status =   $this->getdata_curlreq->checkMinMaxPrice($rates['room_type_id'],$rates['rate_plan_id'],$rates['bar_price'],$rates['multiple_occupancy'],$rates['hotel_id'],$rates['date'],$rates['channel']);
                if($min_max_status){
                    $rlt = $min_max_status;
                    continue;
                }
                $rateplan_multiple_days_data = $this->getdata_curlreq->goomoDays($rates['multiple_days']);
                $currency=$this->getdata_curlreq->getCurrency($hotel_id);
                $extra_adult_price			 = 0;
                $extra_child_price			 = 0;
                $occupency=$this->getdata_curlreq->decideOccupencyPrice($max_adult,$rates['bar_price'],$rates['multiple_occupancy']);
                $rateplan_single_price=0;
                $rateplan_double_price=0;
                $rateplan_triple_price=0;
                if($occupency)
                {
                    $rateplan_single_price       = $occupency[0];
                    $rateplan_double_price       = $occupency[1];
                    $rateplan_triple_price       = isset($occupency[2])?$occupency[2]:0;
                    if(isset($occupency[3]))
                    {
                        $rateplan_fourth_price       = $occupency[3];
                    }
                }

                $result 					 = $cmOtaRatePlanSynchronizeModel->getOtaRoomIdFromRatePlanSynch($rates['room_type_id'],$ota_id,$rates['rate_plan_id']);
                if(isset($result[0])){
                $ota_room_id                   = $result[0]['ota_room_type_id'];
                $ota_rate_plan_id                   = $result[0]['ota_rate_plan_id'];
                }
                else{
                    continue;
                }
                $startDate                   =  $rates['from_date'];
                $endDate                     =  $rates['to_date'];
                $rates['multiple_occupancy']    = json_encode($rates['multiple_occupancy']);
                $success_flag=1;
                try{
                    $otaRatePlan->fill($rates)->save();
                }
                catch(\Exception $e){
                   $success_flag=0;
                }
                if($success_flag)
                {
                    $rateplanId   = $otaRatePlan->rate_plan_log_id;
                    if($ota_rate_plan_id)
                    {
                        $headers = array('Content-Type:application/json', 'Expect:');
                        $log_data  = [
                            "action_id"          => 2,
                            "hotel_id"           => $hotel_id,
                            "ota_id"      		 => $ota_id,
                            "rate_ref_id"        => $rateplanId,
                            "user_id"            => $rate_bucket_data['bucket_user_id'],
                            "request_msg"        => '',
                            "response_msg"       => '',
                            "request_url"        => '',
                            "status"         	 => 2,
                            "ip"         		 => $rate_bucket_data['bucket_client_ip'],
                            "comment"			 => "Processing for update "
                            ];
                            if($extra_adult_price!=0 && $extra_child_price!=0)
                            {
                                $sellRate= [
                                    "Double"  => $rateplan_double_price,
                                    "Extra_adult"  =>  $extra_adult_price,
                                    "Extra_child" =>  $extra_child_price,
                                    "Infant"     => '',
                                    "Single"   => $rateplan_single_price
                                    ];
                            }
                            else
                            {
                                $sellRate= [
                                    "Double"  => $rateplan_double_price,
                                    "Extra_adult"  =>  "",
                                    "Extra_child" =>  "",
                                    "Infant"     => '',
                                    "Single"   => $rateplan_single_price
                                    ];
                            }
                            $roomRateId=$ota_room_id.'_'.$ota_rate_plan_id;
                            $post_data=array(
                            "channelName"=> "Bookingjini",
                            "contractType"=>"WEB",
                            "days"=>$rateplan_multiple_days_data,
                            "endDate" =>$endDate,
                            "productId"=>$ota_hotel_code,
                            "roomRateId" => $roomRateId,
                            "sellRate"=>$sellRate,
                            "startDate" => $startDate);
                            $post_data=json_encode($post_data);
                            $log_request_msg=$commonUrl.'/updatePrice'.$post_data;
                            $apiKey               			= trim($auth_parameter->apiKey);
                            $channelId               		= trim($auth_parameter->channelId);
                            $accessToken               		= trim($auth_parameter->accessToken);
                            $headers = array();
                            $headers[] = "apiKey: $apiKey";
                            $headers[] = "channelId: $channelId";
                            $headers[] = "accessToken: $accessToken";
                            $headers[] = "Content-Type: application/json";
                            $url=$commonUrl.'/updatePrice';
                            $logModel->fill($log_data)->save();
                            $result=$this->getdata_curlreq->cUrlCall($url,$headers,$post_data);//for curl call
                            $array_data = json_decode($result, true);

                            if(isset($array_data['status'])){
                                if($array_data['status']=="Success"){
                                DB::table('rate_update_logs')->where('id', $logModel->id)
                                ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                                $rlt=array('status'=>1,'ota_name'=>'goomo','response_msg'=>$result);
                                }
                            }
                            else
                            {
                                DB::table('rate_update_logs')->where('id', $logModel->id)
                                ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                                $rlt=array('status'=>0,'ota_name'=>'goomo','response_msg'=>$result);
                            }
                    }
                    else{
                        $rateplanId=$rateplan_data['rateplan_rate_plan_log_id'];
                        $log_data               	= [
                            "action_id"          => 2,
                            "hotel_id"           => $hotel_id,
                            "ota_id"      		 => $ota_id,
                            "rate_ref_id"        => $rateplanId,
                            "user_id"            => $rate_bucket_data['bucket_user_id'],
                            "request_msg"        => '',
                            "response_msg"       => '',
                            "request_url"        => '',
                            "status"         	 => 2,
                            "ip"         		 => $rate_bucket_data['bucket_client_ip'],
                            "comment"			 => "Roomrate type is not mapped"
                            ];
                        $logModel->fill($log_data)->save();
                        $rlt=array('status'=>0,'ota_name'=>'goomo','response_msg'=>"Rateplan should be sync");
                    }
                }
            }
        }
        if(sizeof($rlt)==0){
            $rlt=array('status'=>0,'ota_name'=>'goomo','response_msg'=>"Rateplan should be mapped");
        }
        return $rlt;
    }
    public function bulkRateUpdate($rate_bucket_data,$data,$auth_parameter,$commonUrl)
    {
        $cmOtaRatePlanSynchronizeModel 	= new CmOtaRatePlanSynchronize();
        $roomTypeModel                  = new MasterRoomType();
        $logModel                       = new RateUpdateLog();
        $otaRatePlan                    = new OtaRatePlan();//ota rate table insertion
        $rateplanId                     = '';
        $xml_all_data                   = '';
        $rlt                            =array();

        $multiple_days                  = '{"Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1","Sun":"1"}';
        $hotel_id                       = $rate_bucket_data['bucket_hotel_id'];
        $ota_id                         = $rate_bucket_data['bucket_ota_id'];
        $ota_hotel_code                 = $rate_bucket_data['bucket_ota_hotel_code'];
        $max_adult=$roomTypeModel->select('max_people')->where('room_type_id',$data['room_type_id'])->first()->max_people;
        $data['multiple_days']       = json_encode($data['multiple_days']);

        $currency=$this->getdata_curlreq->getCurrency($hotel_id);
        $rateplan_multiple_days_data = $this->getdata_curlreq->goomoDays($data['multiple_days']);
        $occupency=$this->getdata_curlreq->decideOccupencyPrice($max_adult,$data['bar_price'],$data['multiple_occupancy']);
        $extra_adult_price			 = $data['extra_adult_price'];
        $extra_child_price			 = $data['extra_child_price'];
        if($extra_adult_price == ''){
          $extra_adult_price = 0;
        }
        if($extra_child_price == ''){
          $extra_child_price = 0;
        }
        $rateplan_bar_price          = $data['bar_price'];
        $rateplan_single_price=0;
        $rateplan_double_price=0;
        $rateplan_triple_price=0;
        if($occupency)
        {
            $rateplan_single_price       = $occupency[0];
            $rateplan_double_price       = $occupency[1];
            $rateplan_triple_price       = $occupency[2];
            if(isset($occupency[3]))
            {
                $rateplan_fourth_price       = $occupency[3];
            }
        }
        $result 					 = $cmOtaRatePlanSynchronizeModel->getOtaRoomIdFromRatePlanSynch($data['room_type_id'],$ota_id,$data['rate_plan_id']);
        if(isset($result[0])){
            $ota_room_id                 = $result[0]['ota_room_type_id'];
            $ota_rate_plan_id            = $result[0]['ota_rate_plan_id'];
        }
        else{
            $rlt=array('status'=>0,'ota_name'=>'goomo','response_msg'=>"Roomtype or Rateplan should be sync");
            return $rlt;
        }
        $startDate                   = date('Y-m-d',strtotime($data['from_date']));
        $endDate                     = date('Y-m-d',strtotime($data['to_date']));
        $data['multiple_occupancy']  = json_encode($data['multiple_occupancy']);
        $data['channel']             = 'Goomo';

        $data['from_date']           = date('Y-m-d',strtotime($data['from_date']));
        $data['to_date']             = date('Y-m-d',strtotime($data['to_date']));
        $data['extra_adult_price']   = $extra_adult_price;
        $data['extra_child_price']   = $extra_child_price;
        $data['client_ip']   = $rate_bucket_data['bucket_client_ip'];
        if($data['admin_id']){
          $data['user_id']   =  $data['admin_id'];
        }
        else{
          $data['user_id']   = 0;
        }
         $success_flag=1;
                try{
                    $otaRatePlan->fill($data)->save();
                }
                catch(\Exception $e){
                   $success_flag=0;
                }
        if($success_flag)
        {
            $rateplanId   = $otaRatePlan->rate_plan_log_id;
            if($ota_rate_plan_id)
            {
                $headers = array('Content-Type:application/json', 'Expect:');
                $log_data  = [
                    "action_id"          => 2,
                    "hotel_id"           => $hotel_id,
                    "ota_id"      		 => $ota_id,
                    "rate_ref_id"        => $rateplanId,
                    "user_id"            => $rate_bucket_data['bucket_user_id'],
                    "request_msg"        => '',
                    "response_msg"       => '',
                    "request_url"        => '',
                    "status"         	 => 2,
                    "ip"         		 => $rate_bucket_data['bucket_client_ip'],
                    "comment"			 => "Processing for update "
                    ];
                    if($extra_adult_price!=0 && $extra_child_price!=0)
                    {
                        $sellRate= [
                            "Double"  => $rateplan_double_price,
                            "Extra_adult"  =>  $extra_adult_price,
                            "Extra_child" =>  $extra_child_price,
                            "Infant"     => '',
                            "Single"   => $rateplan_single_price
                            ];
                    }
                    else
                    {
                        $sellRate= [
                            "Double"  => $rateplan_double_price,
                            "Extra_adult"  =>  "",
                            "Extra_child" =>  "",
                            "Infant"     => '',
                            "Single"   => $rateplan_single_price
                            ];
                    }
                    $roomRateId=$ota_room_id.'_'.$ota_rate_plan_id;
                    $post_data=array(
                    "channelName"=> "Bookingjini",
                    "contractType"=>"WEB",
                    "days"=>$rateplan_multiple_days_data,
                    "endDate" =>$endDate,
                    "productId"=>$ota_hotel_code,
                    "roomRateId" => $roomRateId,
                    "sellRate"=>$sellRate,
                    "startDate" => $startDate);
                    $post_data=json_encode($post_data);
                    $log_request_msg=$commonUrl.'/updatePrice'.$post_data;
                    $apiKey               			= trim($auth_parameter->apiKey);
                    $channelId               		= trim($auth_parameter->channelId);
                    $accessToken               		= trim($auth_parameter->accessToken);
                    $headers = array();
                    $headers[] = "apiKey: $apiKey";
                    $headers[] = "channelId: $channelId";
                    $headers[] = "accessToken: $accessToken";
                    $headers[] = "Content-Type: application/json";
                    $url=$commonUrl.'/updatePrice';
                    $logModel->fill($log_data)->save();
                    $result=$this->getdata_curlreq->cUrlCall($url,$headers,$post_data);//for curl call
                    $array_data = json_decode($result, true);

                    if(!isset($array_data['Error'])){
                        DB::table('rate_update_logs')->where('id', $logModel->id)
                        ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                        $rlt=array('status'=>1,'ota_name'=>'goomo','response_msg'=>$result);
                    }
                    else
                    {
                        DB::table('rate_update_logs')->where('id', $logModel->id)
                        ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                        $rlt=array('status'=>0,'ota_name'=>'goomo','response_msg'=>$result);
                    }
            }
            else{
                $rateplanId=$rateplan_data['rateplan_rate_plan_log_id'];
                $log_data               	= [
                    "action_id"          => 2,
                    "hotel_id"           => $hotel_id,
                    "ota_id"      		 => $ota_id,
                    "rate_ref_id"        => $rateplanId,
                    "user_id"            => $rate_bucket_data['bucket_user_id'],
                    "request_msg"        => '',
                    "response_msg"       => '',
                    "request_url"        => '',
                    "status"         	 => 2,
                    "ip"         		 => $rate_bucket_data['bucket_client_ip'],
                    "comment"			 => "Roomrate type is not mapped"
                    ];
                $logModel->fill($log_data)->save();
                $rlt=array('status'=>0,'ota_name'=>'goomo','response_msg'=>"Rateplan should be sync");
            }
        }
        if(sizeof($rlt)==0){
            $rlt=array('status'=>0,'ota_name'=>'goomo','response_msg'=>"Rateplan should be mapped");
        }
        return $rlt;
    }
    public function blockRateUpdate($rate_bucket_data,$rooms,$data,$auth_parameter,$commonUrl)
    {
        $cmOtaRatePlanSynchronizeModel = new CmOtaRatePlanSynchronize();
        $logModel                       = new RateUpdateLog();
        $roomTypeModel                  = new MasterRoomType();
        $otaRatePlan                    = new OtaRatePlan();//ota rate table insertion
        $hotel_id                       = $rate_bucket_data['bucket_hotel_id'];
        $ota_id                         = $rate_bucket_data['bucket_ota_id'];
        $ota_hotel_code                 = $rate_bucket_data['bucket_ota_hotel_code'];
        $rlt                            = array();
        $rateplanId                     = array();
        $max_adult=$roomTypeModel->select('max_people')->where('room_type_id',$data['room_type_id'])->first()->max_people;

            $data['date_from'] = date('Y-m-d',strtotime($data['date_from']));
            $data['date_to'] = date('Y-m-d',strtotime($data['date_to']));
            $cond = array('hotel_id'=>$data['hotel_id'],'room_type_id'=>$rooms,'rate_plan_id'=>$data['rate_plan_id'],'channel'=>'Goomo');
            $getRateDetails = OtaRatePlan::select('*')
                                ->where($cond)->where('from_date','<=',$data['date_from'])
                                ->where('to_date','>=',$data['date_to'])
                                ->orderBy('rate_plan_log_id','DESC')
                                ->first();

                $rate_data = [
                    'hotel_id'          => $getRateDetails->hotel_id,
                    'channel'           => 'Goomo',
                    'room_type_id'      => $getRateDetails->room_type_id,
                    'rate_plan_id'      => $getRateDetails->rate_plan_id,
                    'bar_price'         => $getRateDetails->bar_price,
                    'multiple_occupancy'=> $getRateDetails->multiple_occupancy,
                    'multiple_days'     => $getRateDetails->multiple_days,
                    'from_date'         => $data['date_from'],
                    'to_date'           => $data['date_to'],
                    'block_status'      => 1,
                    'los'               => 0,
                    'client_ip'         => $getRateDetails->client_ip,
                    'user_id'           => $getRateDetails->user_id,
                    'extra_adult_price' => $getRateDetails->extra_adult_price,
                    'extra_child_price' => $getRateDetails->extra_child_price,
                ];
                $currency=$this->getdata_curlreq->getCurrency($hotel_id);
                $multiple_occupancy = json_decode($rate_data['multiple_occupancy']);
                $occupency=$this->getdata_curlreq->decideOccupencyPrice($max_adult,$rate_data['bar_price'],$multiple_occupancy);
                $multiple_days  =  $getRateDetails->multiple_days;
                $rateplan_multiple_days_data = $this->getdata_curlreq->getDaysUpdate($multiple_days,"Goomo");
                $rateplan_single_price=0;
                $rateplan_double_price=0;
                $rateplan_triple_price=0;
                if($occupency)
                {
                    $rateplan_single_price       = $occupency[0];
                    $rateplan_double_price       = $occupency[1];
                    $rateplan_triple_price       = $occupency[2];
                }
                $result = $cmOtaRatePlanSynchronizeModel->getOtaRoomIdFromRatePlanSynch($rooms,$ota_id,$data['rate_plan_id']);

                if(isset($result[0])){
                $ota_room_id                   = $result[0]['ota_room_type_id'];
                $ota_rate_plan_id                   = $result[0]['ota_rate_plan_id'];
                }
                $startDate          =   $data['date_from'];
                $endDate            =   $data['date_to'];
                $extra_child_price  =   $rate_data['extra_child_price'];
                $extra_adult_price  =   $rate_data['extra_adult_price'];
                $success_flag=1;
                try{
                    $otaRatePlan->fill($rate_data)->save();
                }
                catch(\Exception $e){
                   $success_flag=0;
                }
                if($success_flag){
                    $rateplanId[]   = $otaRatePlan->rate_plan_log_id;
                    $rateplan_Id    = implode(',',$rateplanId);
                    if($ota_rate_plan_id){
                        $headers = array('Content-Type:application/json', 'Expect:');
                        $log_data  = [
                            "action_id"          => 2,
                            "hotel_id"           => $hotel_id,
                            "ota_id"      	=> $ota_id,
                            "rate_ref_id"        => $rateplan_Id,
                            "user_id"            => $rate_bucket_data['bucket_user_id'],
                            "request_msg"        => '',
                            "response_msg"       => '',
                            "request_url"        => '',
                            "status"         => 1,
                            "ip"         => $rate_bucket_data['bucket_client_ip'],
                            "comment"	=> "Processing for update "
                            ];
                            if($extra_adult_price!=0 && $extra_child_price!=0)
                            {
                                $sellRate= [
                                    "Double"  => 0,
                                    "Extra_adult"  =>  0,
                                    "Extra_child" =>  0,
                                    "Infant"     => '',
                                    "Single"   => 0
                                    ];
                            }
                            else
                            {
                                $sellRate= [
                                    "Double"  => 0,
                                    "Extra_adult"  =>  "",
                                    "Extra_child" =>  "",
                                    "Infant"     => '',
                                    "Single"   => 0
                                    ];
                            }
                            $roomRateId=$ota_room_id.'_'.$ota_rate_plan_id;
                            $post_data=array(
                            "channelName"=> "Bookingjini",
                            "contractType"=>"WEB",
                            "days"=>$rateplan_multiple_days_data,
                            "endDate" =>$endDate,
                            "productId"=>$ota_hotel_code,
                            "roomRateId" => $roomRateId,
                            "sellRate"=>$sellRate,
                            "startDate" => $startDate);
                            $post_data=json_encode($post_data);
                            $log_request_msg=$commonUrl.'/updatePrice'.$post_data;
                            $apiKey               = trim($auth_parameter->apiKey);
                            $channelId               = trim($auth_parameter->channelId);
                            $accessToken               = trim($auth_parameter->accessToken);
                            $headers = array();
                            $headers[] = "apiKey: $apiKey";
                            $headers[] = "channelId: $channelId";
                            $headers[] = "accessToken: $accessToken";
                            $headers[] = "Content-Type: application/json";
                            $url=$commonUrl.'/updatePrice';
                            $logModel->fill($log_data)->save();
                            $result=$this->getdata_curlreq->cUrlCall($url,$headers,$post_data);//for curl call
                            $array_data = json_decode($result, true);

                            if(isset($array_data['status'])){
                                if($array_data['status']=="Success"){
                                DB::table('log_table')->where('id', $logModel->id)
                                ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                                $rlt=array('status'=>1,'ota_name'=>'goomo','response_msg'=>$result);
                                }
                            }
                            else
                            {
                                DB::table('log_table')->where('id', $logModel->id)
                                ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                                $rlt=array('status'=>0,'ota_name'=>'goomo','response_msg'=>$result);
                            }
                    }
                    else{
                        $log_data               = [
                            "action_id"          => 2,
                            "hotel_id"           => $hotel_id,
                            "ota_id"      	=> $ota_id,
                            "rate_ref_id"        => 0,
                            "user_id"            => $rate_bucket_data['bucket_user_id'],
                            "request_msg"        => '',
                            "response_msg"       => '',
                            "request_url"        => '',
                            "status"         => 2,
                            "ip"         => $rate_bucket_data['bucket_client_ip'],
                            "comment"	=> "Roomrate type is not mapped"
                            ];
                        $logModel->fill($log_data)->save();
                        $rlt=array('status'=>0,'ota_name'=>'goomo','response_msg'=>"Rateplan should be sync");
                    }
                }
        if(empty($rlt)){
            $rlt=array('status'=>0,'ota_name'=>'goomo','response_msg'=>"Rateplan should be mapped");
        }
        return $rlt;
    }
}
