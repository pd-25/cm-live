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
 * This controller is used for IRCTS single,bulk,sync and block of inventory and rate updates
 * @author Siri
 * created date 08/03/2021.
 */

class IrctcInvRateController extends Controller
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
        $otainventory                   = new OtaInventory();
        $xml_all_data                   = '';
        $hotel_id                       = $bucket_data["bucket_hotel_id"];
        $ota_id                         = $bucket_data["bucket_ota_id"];
        $ota_hotel_code                 = $bucket_data["bucket_ota_hotel_code"];
        $flag                           = 0;
        $count                          = 0;
        $rlt                            = array();
        $count_inv                      = 0;
        $time_stamp = date("Y-m-d h:i:s");
            $ota_room_type = DB::table('cmlive.cm_ota_room_type_synchronize')
            ->where('hotel_id', '=', $hotel_id)
            ->where('room_type_id', '=', $inventory['room_type_id'])
            ->where('ota_type_id', '=', $ota_id)
            ->value('ota_room_type');
            $xml_data='';
            $password    = trim($auth_parameter->password);
            $username    = trim($auth_parameter->username);
            $room_code 					= $cmOtaRoomTypeSynchronizeModel->getOtaRoomType($inventory['room_type_id'],$ota_id);
            
            $xml='{"OTA_HotelInvCountNotifRQ": {
                    "EchoToken": "abc13dd23",
                    "TimeStamp": '.'"'.$time_stamp.'"'.',
                    "Target": "Production",
                    "Version": "",
                    "HotelCode": '.'"'.$ota_hotel_code.'"'.',
                    "POS": {
                        "RequestorID": {
                            "Password": '.'"'.$password.'"'.',
                            "User": '.'"'.$username.'"'.',
                            "ID_Context": "CKLive"
                        }
                    },
                    "Inventories": [';
            foreach($inventory['inv'] as $inv)
            {
                $fmonth=explode('-',$inv['date']);//for removing extra o from month and remove this code after mobile app update
                if(strlen($fmonth[1]) == 3)
                {
                    $fmonth[1]=ltrim($fmonth[1],0);
                }
                $inv['date']=implode('-',$fmonth);
                $startDate                  = $inv['date'];
                $endDate                    = $inv['date'];
                $room_qtys                  = $inv['no_of_rooms'];

                $url                       = $commonUrl.'/update-inv';
                $inv['room_type_id']       = $inventory['room_type_id'];
                $inv['block_status']       = $inv["block_status"];
                $inv['channel']            = 'IRCTC';
                $inv['ota_id']             = $ota_id;
                $inv['user_id']            = $bucket_data["bucket_user_id"];
                $inv['client_ip']          = $bucket_data["bucket_client_ip"];
                $inv['hotel_id']           = $hotel_id;
                $inv['date_from']          = $from_date;
                $inv['date_to']            = $to_date;
                $inv['multiple_days']      = '{"Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1","Sun":"1"}';

                try{
                    if($count_inv == 0){
                        $otainventory->fill($inv)->save();
                        $count_inv++;
                    }
                }
                catch(Exception $e){

                }
                if($room_code)
                {
                    $flag=1;
                    if($inv['block_status']==0)
                    {
                        if($room_qtys >= 0)
                        {
                            $xml .='{
                                        "StatusApplicationControl": {
                                            "Start": "'.$startDate.'",
                                            "End": "'.$endDate.'",
                                            "InvTypeCode": "'.$room_code.'"
                                        },
                                        "InvCounts": {
                                            "Days": [{
                                                "Mon": "True",
                                                "Tue": "True",
                                                "Weds": "True",
                                                "Thur": "True",
                                                "Fri": "True",
                                                "Sat": "True",
                                                "Sun": "True"
                                            }],
                                            "InvCount": "'.$room_qtys.'",
                                            "CutOff": "3",
                                            "StopSell": "False"
                                        }
                                    }';
                        }
                    }
                    else
                    {
                        $xml .='{
                                    "StatusApplicationControl": {
                                        "Start": "'.$startDate.'",
                                        "End": "'.$endDate.'",
                                        "InvTypeCode": "'.$room_code.'"
                                    },
                                    "InvCounts": {
                                        "Days": [{
                                            "Mon": "True",
                                            "Tue": "True",
                                            "Weds": "True",
                                            "Thur": "True",
                                            "Fri": "True",
                                            "Sat": "True",
                                            "Sun": "True"
                                        }],
                                        "InvCount": "'.$room_qtys.'",
                                        "CutOff": "3",
                                        "StopSell": "True"
                                    }
                                }';

                    }
                }
            }
            $xml.= ']}}';
            $xml = str_replace("}{","},{",$xml);
            $irctc_xml = $xml;
            if($flag == 1){
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

                $headers = array ('Content-Type: application/json');
                $log_request_msg = $irctc_xml;
                $logModel->fill($log_data)->save();//saving pre logdata
                $result=$this->getdata_curlreq->cUrlCall($url,$headers,$irctc_xml);//for curl call
                $array_data = json_decode($result,true);
                if(isset($array_data['OTA_HotelInvCountNotifRS']['Status'])){
                    if($array_data['OTA_HotelInvCountNotifRS']['Status'] == "Success"){
                        DB::table('cmlive.log_table')->where('id', $logModel->id)
                        ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                        $rlt=array('status' => 1,'ota_name'=>'IRCTC','response_msg'=> 'sync successfully');
                    }
                    else{
                        DB::table('cmlive.log_table')->where('id', $logModel->id)
                        ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                        $rlt=array('status' => 0,'ota_name'=>'IRCTC','response_msg'=> $result);
                    }
                }
                else{
                   
                    DB::table('cmlive.log_table')->where('id', $logModel->id)
                    ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                    $rlt=array('status' => 0,'ota_name'=>'IRCTC','response_msg'=> $result);
                }
            }
            else{
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
                    $rlt=array('status'=>0,'ota_name'=>'IRCTC','response_msg'=>"Roomtype should be sync");
            }
            if(sizeof($rlt)==0){
                $rlt=array('status'=>0,'ota_name'=>'IRCTC','response_msg'=>"Roomtype should be mapped");
            }
        return $rlt;
    }

    public function blockInventoryUpdate($bucket_data,$room_types,$data,$auth_parameter,$commonUrl)
    {
        $blocklog                       = new LogTable();
        $hotel_id                       = $bucket_data["bucket_hotel_id"];
        $ota_id                         = $bucket_data["bucket_ota_id"];
        $ota_hotel_code                 = $bucket_data["bucket_ota_hotel_code"];
        $data['channel']                = 'IRCTC';
        $inventoryId                    = array();
        $xml_data                       = '';
        $flag                           = 0;
        $count                          = 0;
        $rlt                            = array();
        $otainventory               = new OtaInventory();
        $data['room_type_id']       = $room_types;
        $data['ota_id']             = $ota_id;
        $data['user_id']            = $bucket_data["bucket_user_id"];
        $data['client_ip']          = $bucket_data["bucket_client_ip"];
        $data['hotel_id']           = $hotel_id;
        $time_stamp = date("Y-m-d h:i:s");
        $success_flag=1;
            try{
                $otainventory->fill($data)->save();
            }
            catch(\Exception $e){
               $success_flag=0;
            }
            
        if($success_flag)
        {
            $inventoryId[]   = $otainventory->inventory_id;
            $hotel_id        = $otainventory->hotel_id;
            $room_code       = DB::table('cmlive.cm_ota_room_type_synchronize')
                               ->where('hotel_id', '=', $hotel_id)
                               ->where('room_type_id', '=', $room_types)
                               ->where('ota_type_id', '=', $ota_id)
                               ->value('ota_room_type');
            $startDate       = $data['date_from'];
            $endDate         = $data['date_to'];
            $password    = trim($auth_parameter->password);
            $username    = trim($auth_parameter->username);
            $url             = $commonUrl.'/update-inv';
            if($room_code)
            {
                $flag = 1;
                $xml = '{
                    "OTA_HotelInvCountNotifRQ": {
                        "EchoToken": "abc13dd23",
                        "TimeStamp": '.'"'.$time_stamp.'"'.',
                        "Target": "Production",
                        "Version": "",
                        "HotelCode": '.'"'.$ota_hotel_code.'"'.',
                        "POS": {
                            "RequestorID": {
                                "Password": '.'"'.$password.'"'.',
                                "User": '.'"'.$username.'"'.',
                                "ID_Context": "CKLive"
                            }
                        },
                        "Inventories": [{
                            "StatusApplicationControl": {
                                "Start": "'.$startDate.'",
                                "End": "'.$endDate.'",
                                "InvTypeCode": "'.$room_code.'"
                            },
                            "InvCounts": {
                                "Days": [{
                                    "Mon": "True",
                                    "Tue": "True",
                                    "Weds": "True",
                                    "Thur": "True",
                                    "Fri": "True",
                                    "Sat": "True",
                                    "Sun": "True"
                                }],
                                "InvCount": 0,
                                "CutOff": "3",
                                "StopSell": "True"
                            }
                        }]
                    }
                }';
            $invId             = implode(',',$inventoryId);
            $log_data                 		= [
                "action_id"          => 3,
                "hotel_id"           => $hotel_id,
                "ota_id"      		 => $ota_id,
                "inventory_ref_id"   => $invId,
                "user_id"            => $bucket_data["bucket_user_id"],
                "request_msg"        =>  '',
                "response_msg"       =>  '',
                "request_url"        =>  '',
                "status"         	 => 2,
                "ip"         		 => $bucket_data["bucket_client_ip"],
                "comment"			 => "Processing for update"
                ];

            $blocklog->fill($log_data)->save();
            $irctc_xml = $xml;
            $log_request_msg= $irctc_xml;
            $headers = array ('Content-Type: application/json');
            $result=$this->getdata_curlreq->cUrlCall($url,$headers,$irctc_xml);//for curl call
            $array_data = json_decode($result,true);
            if(isset($array_data['OTA_HotelInvCountNotifRS']['Status'])){
                if(isset($array_data['OTA_HotelInvCountNotifRS']['Status']) == "Success"){
                    DB::table('cmlive.log_table')->where('id', $blocklog->id)
                    ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                    $rlt=array('status' => 1,'ota_name'=>'IRCTC','response_msg'=> 'Blocked successfully');
                    }
                    else{
                        DB::table('cmlive.log_table')->where('id', $blocklog->id)
                        ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                        $rlt=array('status' => 0,'ota_name'=>'IRCTC','response_msg'=> $result);
                    }
                }
                else{
                    DB::table('cmlive.log_table')->where('id', $blocklog->id)
                    ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                    $rlt=array('status' => 0,'ota_name'=>'IRCTC','response_msg'=> $result);
                }
            }
            else{
                $log_data = [
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
                $rlt=array('status' => 0,'ota_name'=>'IRCTC','response'=> 'Roomtype should be Sync');
            }
        }
        if(sizeof($rlt)==0){
            $rlt=array('status'=>0,'ota_name'=>'IRCTC','response_msg'=>"Roomtype should be mapped");
        }
        return $rlt;

    }

    public function singleInvUpdate($bucket_data,$inventory,$auth_parameter,$commonUrl)
    {
        $cmOtaRoomTypeSynchronizeModel  = new CmOtaRoomTypeSynchronize();
        $logModel                       = new LogTable();
        $xml_all_data                   = '';
        $hotel_id                       = $bucket_data["bucket_hotel_id"];
        $ota_id                         = $bucket_data["bucket_ota_id"];
        $ota_hotel_code                 = $bucket_data["bucket_ota_hotel_code"];
        $inventoryId                    = array();
        $flag                           = 0;
        $count                          = 0;
        $rlt                            = array();
        $password    = trim($auth_parameter->password);
        $username    = trim($auth_parameter->username);
        $time_stamp = date("Y-m-d h:i:s");
        foreach($inventory as $invs)
        {
            $ota_room_type = DB::table('cmlive.cm_ota_room_type_synchronize')
            ->where('hotel_id', '=', $hotel_id)
            ->where('room_type_id', '=', $invs['room_type_id'])
            ->where('ota_type_id', '=', $ota_id)
            ->value('ota_room_type');
            $xml_data='';
            $room_code = $cmOtaRoomTypeSynchronizeModel->getOtaRoomType($invs['room_type_id'],$ota_id);

            $xml='{
                    "OTA_HotelInvCountNotifRQ": {
                    "EchoToken": "abc13dd23",
                    "TimeStamp": '.'"'.$time_stamp.'"'.',
                    "Target": "Production",
                    "Version": "",
                    "HotelCode": '.'"'.$ota_hotel_code.'"'.',
                    "POS": {
                        "RequestorID": {
                            "Password": '.'"'.$password.'"'.',
                            "User": '.'"'.$username.'"'.',
                            "ID_Context": "CKLive"
                        }
                    },
                    "Inventories": [';
            foreach($invs['inv'] as $inv)
            {
                $otainventory  = new OtaInventory();
                $fmonth=explode('-',$inv['date']);//for removing extra o from month and remove this code after mobile app update
                if(strlen($fmonth[1]) == 3)
                {
                    $fmonth[1]=ltrim($fmonth[1],0);
                }
                $inv['date']=implode('-',$fmonth);
                $startDate                  = $inv['date'];
                $endDate                    = $inv['date'];
                $inventory_los              = $inv['los'];
                $room_qtys                  = $inv['no_of_rooms'];

                $inv['room_type_id']       = $invs['room_type_id'];
                $inv['block_status']       = 0;
                $inv['channel']            = 'IRCTC';
                $inv['ota_id']             = $ota_id;
                $inv['user_id']            = $bucket_data["bucket_user_id"];
                $inv['client_ip']          = $bucket_data["bucket_client_ip"];
                $inv['hotel_id']           = $hotel_id;
                $inv['date_from']          = $inv['date'];
                $inv['date_to']            = $inv['date'];

                $url                       = $commonUrl.'/update-inv';
                $success_flag=1;
                try{
                    $otainventory->fill($inv)->save();
                }
                catch(\Exception $e){
                   $success_flag=0;
                }

                if($success_flag)
                {
                    $inventoryId[]   = $otainventory->inventory_id;
                    if($room_code)
                    {
                        $flag=1;
                        if($room_qtys >= 0)
                        {
                            $xml .='{
                                        "StatusApplicationControl": {
                                            "Start": "'.$startDate.'",
                                            "End": "'.$endDate.'",
                                            "InvTypeCode": "'.$room_code.'"
                                        },
                                        "InvCounts": {
                                            "Days": [{
                                                "Mon": "True",
                                                "Tue": "True",
                                                "Weds": "True",
                                                "Thur": "True",
                                                "Fri": "True",
                                                "Sat": "True",
                                                "Sun": "True"
                                            }],
                                            "InvCount": "'.$room_qtys.'",
                                            "CutOff": "3",
                                            "StopSell": "False"
                                        }
                                    }';
                        }
                    }
                }
            }
            $xml.= ']}}';
            $xml = str_replace("}{","},{",$xml);
            $irctc_xml = $xml;
            if($flag == 1){
                $invId             = implode(',',$inventoryId);
                $log_data               	= [
                    "action_id"          => 4,
                    "hotel_id"           => $hotel_id,
                    "ota_id"      		 => $ota_id,
                    "inventory_ref_id"   => $invId,
                    "user_id"            => $bucket_data["bucket_user_id"],
                    "request_msg"        => '',
                    "response_msg"       => '',
                    "request_url"        => '',
                    "status"         	 => 2,
                    "ip"         		 => $bucket_data["bucket_client_ip"],
                    "comment"			 => "Processing for update "
                    ];

                $headers = array ('Content-Type: application/json');
                $log_request_msg = $irctc_xml;
                $logModel->fill($log_data)->save();//saving pre logdata
                $result=$this->getdata_curlreq->cUrlCall($url,$headers,$irctc_xml);//for curl call
                $array_data = json_decode($result,true);
                if(isset($array_data['OTA_HotelInvCountNotifRS']['Status'])){
                    if(isset($array_data['OTA_HotelInvCountNotifRS']['Status']) == "Success"){
                        DB::table('cmlive.log_table')->where('id', $logModel->id)
                        ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                        $rlt=array('status' => 1,'ota_name'=>'IRCTC','response_msg'=> 'update successfully');
                    }
                    else{
                        DB::table('cmlive.log_table')->where('id', $logModel->id)
                        ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                        $rlt=array('status' => 0,'ota_name'=>'IRCTC','response_msg'=> $result);
                    }
                }
                else{
                    DB::table('cmlive.log_table')->where('id', $logModel->id)
                    ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                    $rlt=array('status' => 0,'ota_name'=>'IRCTC','response_msg'=> $result);
                }
            }else{
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
                    $rlt=array('status'=>0,'ota_name'=>'IRCTC','response_msg'=>"Roomtype should be sync");
            }
        }
        if(sizeof($rlt)==0){
            $rlt=array('status'=>0,'ota_name'=>'IRCTC','response_msg'=>"Roomtype should be mapped");
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
        $data['channel']                = 'IRCTC';
        $rlt                            = array();
        $time_stamp = date("Y-m-d h:i:s");
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
                $log_data               	= [
                    "action_id"          => 4,
                    "hotel_id"           => $hotel_id,
                    "ota_id"      		 => $ota_id,
                    "inventory_ref_id"   => $invtefid ,
                    "user_id"            => $bucket_data["bucket_user_id"],
                    "request_msg"        => '',
                    "response_msg"       => '',
                    "request_url"        => '',
                    "status"         	 => 2,
                    "ip"         		 => $bucket_data["bucket_client_ip"],
                    "comment"			 => "Processing for update "
                    ];

                $password    = trim($auth_parameter->password);
                $username    = trim($auth_parameter->username);
                $url                    = $commonUrl.'/update-inv';
                
                $xml = '{
                        "OTA_HotelInvCountNotifRQ": 
                        {
                            "EchoToken": "abc13dd23",
                            "TimeStamp": '.'"'.$time_stamp.'"'.',
                            "Target": "Production",
                            "Version": "",
                            "HotelCode": '.'"'.$ota_hotel_code.'"'.',
                            "POS": {
                                "RequestorID": {
                                    "Password": '.'"'.$password.'"'.',
                                    "User": '.'"'.$username.'"'.',
                                    "ID_Context": "CKLive"
                                }
                            },
                            "Inventories": [{
                                "StatusApplicationControl": {
                                    "Start": "'.$startDate.'",
                                    "End": "'.$endDate.'",
                                    "InvTypeCode": "'.$room_code.'"
                                },
                                "InvCounts": {
                                    "Days": [{
                                        "Mon": "True",
                                        "Tue": "True",
                                        "Weds": "True",
                                        "Thur": "True",
                                        "Fri": "True",
                                        "Sat": "True",
                                        "Sun": "True"
                                    }],
                                    "InvCount": '.$room_qtys.',
                                    "CutOff": "3",
                                    "StopSell": "False"
                                }
                            }]
                        }
                    }';
                
                $headers = array ('Content-Type: application/json');
                $log_request_msg = $xml;
                $logModel->fill($log_data)->save();//saving pre logdata
                $result=$this->getdata_curlreq->cUrlCall($url,$headers,$xml);//for curl call
                $array_data = json_decode($result,true);
                if(isset($array_data['OTA_HotelInvCountNotifRS']['Status'])){
                    if(isset($array_data['OTA_HotelInvCountNotifRS']['Status']) == "Success"){
                        DB::table('cmlive.log_table')->where('id', $logModel->id)
                        ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                        $rlt=array('status' => 1,'ota_name'=>'IRCTC','response_msg'=> 'update successfully');
                        }
                        else{
                            DB::table('cmlive.log_table')->where('id', $logModel->id)
                            ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                            $rlt=array('status' => 0,'ota_name'=>'IRCTC','response_msg'=> $result);

                        }
                    }
                    else{
                        DB::table('cmlive.log_table')->where('id', $logModel->id)
                        ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                        $rlt=array('status' => 0,'ota_name'=>'IRCTC','response_msg'=> $result);

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
                    "comment"			 => " This roomrate type is not mapped"
                    ];
                    $logModel->fill($log_data)->save();//saving pre logdata
                    $rlt=array('status'=>0,'ota_name'=>'IRCTC','response_msg'=>'Roomtype should be sync');
            }
            if(sizeof($rlt)==0){
                $rlt=array('status'=>0,'ota_name'=>'IRCTC','response_msg'=>"Roomtype should be mapped");
            }
            return $rlt;
        }
    }

    public function rateSyncUpdate($rate_bucket_data,$rates_data,$auth_parameter,$commonUrl,$from_date,$to_date)
    {
        $cmOtaRatePlanSynchronizeModel 	= new CmOtaRatePlanSynchronize();
        $roomTypeModel                  = new MasterRoomType();
        $logModel                       = new RateUpdateLog();
        $otaRatePlan                    = new OtaRatePlan();
        $xml_all_data                   = '';
        $multiple_days                  = '{"Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1","Sun":"1"}';
        $hotel_id                       = $rate_bucket_data['bucket_hotel_id'];
        $ota_id                         = $rate_bucket_data['bucket_ota_id'];
        $ota_hotel_code                 = $rate_bucket_data['bucket_ota_hotel_code'];
        $count                          = 0;
        $flag                           = 0;
        $rlt                            = array();
        $count_rate                      = 0;
        $password    = trim($auth_parameter->password);
        $username    = trim($auth_parameter->username);
        $result 					 = $cmOtaRatePlanSynchronizeModel->getOtaRoomIdFromRatePlanSynch($rates_data['room_type_id'],
        $ota_id,$rates_data['rate_plan_id']);
        if(isset($result[0])){
        $room_code                   = $result[0]['ota_room_type_id'];
        $rate_code                   = $result[0]['ota_rate_plan_id'];
        $time_stamp = date("Y-m-d h:i:s");
        }

            $xml = '{
                "OTA_HotelRateAmountNotifRQ": {
                    "EchoToken": "abc1323",
                    "TimeStamp": '.'"'.$time_stamp.'"'.',
                    "Target": "Production",
                    "Version": "",
                    "HotelCode": '.'"'.$ota_hotel_code.'"'.',
                    "POS": {
                        "RequestorID": {
                            "Password": '.'"'.$password.'"'.',
                            "User": '.'"'.$username.'"'.',
                            "ID_Context": "CKLive"
                        }
                    },
                "RateAmountMessages": [';
            foreach($rates_data['rates'] as $rates)
            {
                $fmonth=explode('-',$rates['date']);//for removing extra o from month and remove this code after mobile app update
                if(strlen($fmonth[1]) == 3)
                {
                    $fmonth[1]=ltrim($fmonth[1],0);
                }
                $rates['date']=implode('-',$fmonth);
                $max_adult=$roomTypeModel->select('max_people')->where('room_type_id',$rates_data['room_type_id'])->first()->max_people;
                $currency=$this->getdata_curlreq->getCurrency($hotel_id);
                $extra_adult_price			 = $rates['extra_adult_price'];
                $extra_child_price			 = $rates['extra_child_price'];
                $los                         = 0;
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
                }
                $startDate                      =  $rates['date'];
                $endDate                        =  $rates['date'];

                $url                            = $commonUrl.'/update-rate';
                $rates['room_type_id']          = $rates_data['room_type_id'];
                $rates['rate_plan_id']          = $rates_data['rate_plan_id'];
                $rates['from_date']             = $from_date;
                $rates['to_date']               = $to_date;
                $rates['block_status']          = 0;
                $rates['los']                   = 1;
                $rates['multiple_days']         = $multiple_days;
                $rates['client_ip']             = $rate_bucket_data['bucket_client_ip'];
                $rates['user_id']               = $rate_bucket_data['bucket_user_id'];
                $rates['hotel_id']              = $hotel_id;
                $rates['channel']               = 'IRCTC';
                $rates['multiple_occupancy']    = json_encode($rates['multiple_occupancy']);
                try{
                    if($count_rate == 0){
                        $otaRatePlan->fill($rates)->save();
                        $count_rate++;
                    }
                }
                catch(Exception $e){

                }
                if($rate_code)
                {
                    $flag=1;
                    $xml.='{
                        "StatusApplicationControl": {
                            "InvTypeCode": "'.$room_code.'",
                            "RatePlanCode": "'.$rate_code.'",
                            "Start": "'.$startDate.'",
                            "End": "'.$endDate.'"
                        },
                        "Rates": {
                            "Days": [
                                {
                                "Mon": "True",
                                "Tue": "True",
                                "Weds": "True",
                                "Thur": "True",
                                "Fri": "True",
                                "Sat": "True",
                                "Sun": "True"
                                }
                            ],
                            "AdditionalGuestAmounts": [
                                {
                                "GuestType": "ExtraAdult1",
                                "Amount": "'.$extra_adult_price.'"
                                },
                                {
                                "GuestType": "Child1",
                                "Amount": "'.$extra_child_price.'"
                                }
                            ],
                            "MinStay": "'.$los.'",
                            "MaxStay": "'.$los.'",
                            "StopSell": "False"
                        }
                    }';
                    $headers = array (
                        'Content-Type: application/json'
                        );
                }
            }
            $xml .= ']}}';
            $xml = str_replace("}{","},{",$xml);
            if($flag == 1){
                $irctc_rate_xml = $xml;
                $log_request_msg = $irctc_rate_xml;
                $result=$this->getdata_curlreq->cUrlCall($url,$headers,$irctc_rate_xml);//for curl call
                $array_data =json_decode($result,true);
                
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
                $logModel->fill($log_data)->save();
                if($array_data['OTA_HotelRateAmountNotifRS']['Status'] == "Success"){
                    DB::table('cmlive.rate_update_logs')->where('id', $logModel->id)
                    ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                    $rlt=array('status'=>1,'ota_name'=>'IRCTC','response_msg'=>$result);
                }
                else{
                    DB::table('cmlive.rate_update_logs')->where('id', $logModel->id)
                    ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                    $rlt=array('status'=>0,'ota_name'=>'IRCTC','response_msg'=>$result);
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
                $rlt=array('status'=>0,'ota_name'=>'IRCTC','response_msg'=>"Rateplan should be sync");
            }
        if(sizeof($rlt)==0){
            $rlt=array('status'=>0,'ota_name'=>'IRCTC','response_msg'=>"Rateplan should be mapped");
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
        $rlt                            = array();
        $log_data = '';
        $time_stamp = date("Y-m-d h:i:s");
        foreach($rates_data as $rate)
        {
            $xml_data                    = '';
            $password                    = trim($auth_parameter->password);
            $username                    = trim($auth_parameter->username); 
            $result 					 = $cmOtaRatePlanSynchronizeModel->getOtaRoomIdFromRatePlanSynch($rate['room_type_id'],$ota_id,$rate['rate_plan_id']);
            if(isset($result[0])){
            $room_code                   = $result[0]['ota_room_type_id'];
            $rate_code                   = $result[0]['ota_rate_plan_id'];
            }
            else{
                continue;
            }
            $xml = '{
                "OTA_HotelRateAmountNotifRQ": {
                    "EchoToken": "abc1323",
                    "TimeStamp": '.'"'.$time_stamp.'"'.',
                    "Target": "Production",
                    "Version": "",
                    "HotelCode": '.'"'.$ota_hotel_code.'"'.',
                    "POS": {
                        "RequestorID": {
                            "Password": '.'"'.$password.'"'.',
                            "User": '.'"'.$username.'"'.',
                            "ID_Context": "CKLive"
                        }
                    },
                "RateAmountMessages": [';
            foreach($rate['rates'] as $rates)
            {
                $otaRatePlan = new OtaRatePlan();//ota rate table insertion
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
                $rates['channel']               = 'IRCTC';
                $min_max_status =   $this->getdata_curlreq->checkMinMaxPrice($rates['room_type_id'],$rates['rate_plan_id'],$rates['bar_price'],$rates['multiple_occupancy'],$rates['hotel_id'],$rates['date'],$rates['channel']);
                if($min_max_status){
                    $rlt = $min_max_status;
                    continue;
                }
                $rateplan_multiple_days_data = $this->getdata_curlreq->getDaysUpdate($rates['multiple_days'],"IRCTC");
                $currency=$this->getdata_curlreq->getCurrency($hotel_id);
                $los                         = $rates['los'];
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
                    $rateplan_triple_price       = $occupency[2];
                }
                $startDate                   =  $rates['from_date'];
                $endDate                     =  $rates['to_date'];
                $rates['multiple_occupancy']    = json_encode($rates['multiple_occupancy']);
                $url                    = $commonUrl.'/update-rate';
                if($otaRatePlan->fill($rates)->save())
                {
                    $rateplanId[]   = $otaRatePlan->rate_plan_log_id;
                    $rateplanids    = implode(',',$rateplanId);
                    if($rate_code)
                    {
                        $flag=1;
                        $xml.='{
                                "StatusApplicationControl": {
                                    "InvTypeCode": "'.$room_code.'",
                                    "RatePlanCode": "'.$rate_code.'",
                                    "Start": "'.$startDate.'",
                                    "End": "'.$endDate.'"
                                },
                                "Rates": {
                                    "Days": [
                                        {
                                        "Mon": "True",
                                        "Tue": "True",
                                        "Weds": "True",
                                        "Thur": "True",
                                        "Fri": "True",
                                        "Sat": "True",
                                        "Sun": "True"
                                        }
                                    ],
                                    "BaseByGuestAmts": [
                                        {
                                            "Amount": '.$rateplan_single_price.',
                                            "NumberOfGuests": "1"
                                        },
                                        {
                                            "Amount": '.$rateplan_double_price.',
                                            "NumberOfGuests": "2"
                                        },
                                        {
                                            "Amount": '.$rateplan_triple_price.',
                                            "NumberOfGuests": "3"
                                        }
                                    ],
                                    "AdditionalGuestAmounts": [
                                        {
                                        "GuestType": "ExtraAdult1",
                                        "Amount": "'.$extra_adult_price.'"
                                        },
                                        {
                                        "GuestType": "Child1",
                                        "Amount": "'.$extra_child_price.'"
                                        }
                                    ],
                                    "MinStay": '.$los.',
                                    "MaxStay": '.$los.',
                                    "StopSell": "False"
                                }
                            }';
                            $headers = array (
                                'Content-Type: application/json'
                                );
                    }
                }
                $xml .= ']}}';
                $xml = str_replace("}{","},{",$xml);
                if($flag == 1)
                {
                    $irctc_rate_xml = $xml;
                    $log_request_msg = $irctc_rate_xml;
                    $log_data               	= [
                        "action_id"          => 2,
                        "hotel_id"           => $hotel_id,
                        "ota_id"      		 => $ota_id,
                        "rate_ref_id"        => $rateplanids,
                        "user_id"            => $rate_bucket_data['bucket_user_id'],
                        "request_msg"        => '',
                        "response_msg"       => '',
                        "request_url"        => '',
                        "status"         	 => 2,
                        "ip"         		 => $rate_bucket_data['bucket_client_ip'],
                        "comment"			 => "Processing for update "
                        ];

                    $logModel->fill($log_data)->save();
                    $result=$this->getdata_curlreq->cUrlCall($url,$headers,$irctc_rate_xml);//for curl call
                    $array_data =json_decode($result,true);
                    if($array_data['OTA_HotelRateAmountNotifRS']['Status'] == "Success"){
                        DB::table('cmlive.rate_update_logs')->where('id', $logModel->id)
                        ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                        $rlt=array('status'=>1,'ota_name'=>'IRCTC','response_msg'=>$result);
                    }
                    else{
                        DB::table('cmlive.rate_update_logs')->where('id', $logModel->id)
                        ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                        $rlt=array('status'=>0,'ota_name'=>'IRCTC','response_msg'=>$result);
                    }
                }
                else{
                    $log_data               	= [
                        "action_id"          => 2,
                        "hotel_id"           => $hotel_id,
                        "ota_id"      		 => $ota_id,
                        "rate_ref_id"        => 0,
                        "user_id"            => $rate_bucket_data['bucket_user_id'],
                        "request_msg"        => '',
                        "response_msg"       => '',
                        "request_url"        => '',
                        "status"         	 => 2,
                        "ip"         		 => $rate_bucket_data['bucket_client_ip'],
                        "comment"			 => "Roomrate type is not mapped"
                        ];
                    $logModel->fill($log_data)->save();
                    $rlt=array('status'=>0,'ota_name'=>'IRCTC','response_msg'=>"Rateplan should be sync");
                }
            }
        }
        if(sizeof($rlt)==0){
            $rlt=array('status'=>0,'ota_name'=>'IRCTC','response_msg'=>"Rateplan should be mapped");
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
        $xml_data                       = '';
        $multiple_days                  = '{"Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1","Sun":"1"}';
        $hotel_id                       = $rate_bucket_data['bucket_hotel_id'];
        $ota_id                         = $rate_bucket_data['bucket_ota_id'];
        $ota_hotel_code                 = $rate_bucket_data['bucket_ota_hotel_code'];
        $rlt                            = array();
        $time_stamp = date("Y-m-d h:i:s");
        $max_adult=$roomTypeModel->select('max_people')->where('room_type_id',$data['room_type_id'])->first()->max_people;
        $data['multiple_days']       = $multiple_days;
        $currency=$this->getdata_curlreq->getCurrency($hotel_id);
        // $rateplan_multiple_days_data = $this->getdata_curlreq->getDaysUpdate($data['multiple_days'],"IRCTC");
        $occupency=$this->getdata_curlreq->decideOccupencyPrice($max_adult,$data['bar_price'],$data['multiple_occupancy']);
        $rateplan_single_price=0;
        $rateplan_double_price=0;
        $rateplan_triple_price=0;
        $los                         = $data['los'];
        $extra_adult_price			 = $data['extra_adult_price'];
        $extra_child_price			 = $data['extra_child_price'];
        if($extra_adult_price == ''){
          $extra_adult_price = 0;
        }
        if($extra_child_price == ''){
          $extra_child_price = 0;
        }
        if($occupency)
        {
            $rateplan_single_price       = $occupency[0];
            $rateplan_double_price       = $occupency[1];
            $rateplan_triple_price       = isset($occupency[2])?$occupency[2]:0;
        }
        $result 					 = $cmOtaRatePlanSynchronizeModel->getOtaRoomIdFromRatePlanSynch($data['room_type_id'],$ota_id,$data['rate_plan_id']);
        if(isset($result[0])){
        $room_code                   = $result[0]['ota_room_type_id'];
        $rate_code                   = $result[0]['ota_rate_plan_id'];
        }
        else{
            $rlt=array('status'=>0,'ota_name'=>'IRCTC','response_msg'=>"Room type or Rateplan should be sync");
            return $rlt;
        }
        $startDate                   = date('Y-m-d',strtotime($data['from_date']));
        $endDate                     = date('Y-m-d',strtotime($data['to_date']));
        $data['multiple_occupancy']  = json_encode($data['multiple_occupancy']);
        $data['channel']             = 'IRCTC';
        $password                    = trim($auth_parameter->password);
        $username                    = trim($auth_parameter->username); 
        $url                         = $commonUrl.'/update-rate';

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
            if($rate_code)
            {
                $flag=1;
                $xml='{
                    "OTA_HotelRateAmountNotifRQ": {
                        "EchoToken": "abc1323",
                        "TimeStamp": '.'"'.$time_stamp.'"'.',
                        "Target": "Production",
                        "Version": "",
                        "HotelCode": '.'"'.$ota_hotel_code.'"'.',
                        "POS": {
                            "RequestorID": {
                                "Password": '.'"'.$password.'"'.',
                                "User": '.'"'.$username.'"'.',
                                "ID_Context": "CKLive"
                            }
                        },
                    "RateAmountMessages": [
                        {
                            "StatusApplicationControl": {
                                "InvTypeCode": '.$room_code.',
                                "RatePlanCode": '.$rate_code.',
                                "Start": "'.$startDate.'",
                                "End": "'.$endDate.'"
                            },
                            "Rates": {
                                "Days": [
                                    {
                                    "Mon": "True",
                                    "Tue": "True",
                                    "Weds": "True",
                                    "Thur": "True",
                                    "Fri": "True",
                                    "Sat": "True",
                                    "Sun": "True"
                                    }
                                ],
                                "BaseByGuestAmts": [
                                    {
                                        "Amount": '.$rateplan_single_price.',
                                        "NumberOfGuests": "1"
                                    },
                                    {
                                        "Amount": '.$rateplan_double_price.',
                                        "NumberOfGuests": "2"
                                    },
                                    {
                                        "Amount": '.$rateplan_triple_price.',
                                        "NumberOfGuests": "3"
                                    }
                                ],
                                "AdditionalGuestAmounts": [
                                    {
                                    "GuestType": "ExtraAdult1",
                                    "Amount": "'.$extra_adult_price.'"
                                    },
                                    {
                                    "GuestType": "Child1",
                                    "Amount": "'.$extra_child_price.'"
                                    }
                                ],
                                "MinStay": '.$los.',
                                "MaxStay": '.$los.',
                                "StopSell": "False"
                            }
                        }]
                    }}';
                $xml_data.= $xml;
            }
            else{
                $flag=0;
            }
        }
        else{
            $rateplanId=$rateplan_data['rateplan_rate_plan_log_id'];
        }
        if($flag==1)
        {
            $headers = array (
                'Content-Type: application/json'
                );
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
                "comment"			 => "Processing for update "
                ];

            $irctc_rate_xml = $xml_data;
            $log_request_msg = $irctc_rate_xml;
            $logModel->fill($log_data)->save();
            $result=$this->getdata_curlreq->cUrlCall($url,$headers,$irctc_rate_xml);//for curl call
            $array_data =json_decode($result,true);
            if($array_data['OTA_HotelRateAmountNotifRS']['Status'] == "Success"){
              DB::table('cmlive.rate_update_logs')->where('id', $logModel->id)
              ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
              $rlt=array('status'=>1,'ota_name'=>'IRCTC','response_msg'=>$result);
          }
          else{
              DB::table('cmlive.rate_update_logs')->where('id', $logModel->id)
              ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
              $rlt=array('status'=>0,'ota_name'=>'IRCTC','response_msg'=>$result);
          }
        }
        else{
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
            $rlt=array('status'=>0,'ota_name'=>'IRCTC','response_msg'=>"Rateplan should be sync");
        }
        if(sizeof($rlt)==0){
            $rlt=array('status'=>0,'ota_name'=>'IRCTC','response_msg'=>"Rateplan should be mapped");
        }
        return $rlt;
    }

    public function blockRateUpdate($rate_bucket_data,$rooms,$data,$auth_parameter,$commonUrl)
    {
        $cmOtaRatePlanSynchronizeModel  = new CmOtaRatePlanSynchronize();
        $logModel                       = new RateUpdateLog();
        $roomTypeModel                  = new MasterRoomType();
        $otaRatePlan                    = new OtaRatePlan();//ota rate table insertion
        $hotel_id                       = $rate_bucket_data['bucket_hotel_id'];
        $ota_id                         = $rate_bucket_data['bucket_ota_id'];
        $ota_hotel_code                 = $rate_bucket_data['bucket_ota_hotel_code'];
        $multiple_days                  = '{"Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1","Sun":"1"}';
        $rlt                            = array();
        $rateplanId                     = array();
        $time_stamp = date("Y-m-d h:i:s");
        $max_adult=$roomTypeModel->select('max_people')->where('room_type_id',$data['room_type_id'])->first()->max_people;

            $data['date_from'] = date('Y-m-d',strtotime($data['date_from']));
            $data['date_to'] = date('Y-m-d',strtotime($data['date_to']));
            $cond = array('hotel_id'=>$data['hotel_id'],'room_type_id'=>$rooms,'rate_plan_id'=>$data['rate_plan_id'],'channel'=>'IRCTC');
            $getRateDetails = OtaRatePlan::select('*')
                                ->where($cond)->where('from_date','<=',$data['date_from'])
                                ->where('to_date','>=',$data['date_to'])
                                ->orderBy('rate_plan_log_id','DESC')
                                ->first();
                $rate_data = [
                    'hotel_id'          => $getRateDetails->hotel_id,
                    'channel'           => 'IRCTC',
                    'room_type_id'      => $getRateDetails->room_type_id,
                    'rate_plan_id'      => $getRateDetails->rate_plan_id,
                    'bar_price'         => $getRateDetails->bar_price,
                    'multiple_occupancy'=> $getRateDetails->multiple_occupancy,
                    'multiple_days'     => $getRateDetails->multiple_days,
                    'from_date'         => $data['date_from'],
                    'to_date'           => $data['date_to'],
                    'block_status'      => 1,
                    'los'               => $getRateDetails->los,
                    'client_ip'         => $getRateDetails->client_ip,
                    'user_id'           => $getRateDetails->user_id,
                    'extra_adult_price' => $getRateDetails->extra_adult_price,
                    'extra_child_price' => $getRateDetails->extra_child_price,
                ];
                $currency=$this->getdata_curlreq->getCurrency($hotel_id);
                $multiple_occupancy = json_decode($rate_data['multiple_occupancy']);
                $occupency=$this->getdata_curlreq->decideOccupencyPrice($max_adult,$rate_data['bar_price'],$multiple_occupancy);
                $multiple_days  =  $getRateDetails->multiple_days;
                // $rateplan_multiple_days_data = $this->getdata_curlreq->getDaysUpdate($multiple_days,"Heg");
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
                    $room_code                   = $result[0]['ota_room_type_id'];
                    $rate_code                   = $result[0]['ota_rate_plan_id'];
                }
                $startDate          =   $data['date_from'];
                $endDate            =   $data['date_to'];
                $los                = $rate_data['los'];
                $extra_child_price  =   $rate_data['extra_child_price'];
                $extra_adult_price  =   $rate_data['extra_adult_price'];
                $password                    = trim($auth_parameter->password);
                $username                    = trim($auth_parameter->username); 
                $url                = $commonUrl.'/update-rate';
                $success_flag=1;
                try{
                    $otaRatePlan->fill($rate_data)->save();
                }
                catch(\Exception $e){
                   $success_flag=0;
                }
                if($success_flag){
                    $rateplanId[]   = $otaRatePlan->rate_plan_log_id;
                    $rateplanids                     = implode(',',$rateplanId);
                    if($rate_code){
                        $xml='{
                            "OTA_HotelRateAmountNotifRQ": {
                                "EchoToken": "abc1323",
                                "TimeStamp": '.'"'.$time_stamp.'"'.',
                                "Target": "Production",
                                "Version": "",
                                "HotelCode": '.'"'.$ota_hotel_code.'"'.',
                                "POS": {
                                    "RequestorID": {
                                        "Password": '.'"'.$password.'"'.',
                                        "User": '.'"'.$username.'"'.',
                                        "ID_Context": "CKLive"
                                    }
                                },
                            "RateAmountMessages": [
                                {
                                    "StatusApplicationControl": {
                                        "InvTypeCode": '.$room_code.',
                                        "RatePlanCode": '.$rate_code.',
                                        "Start": "'.$startDate.'",
                                        "End": "'.$endDate.'"
                                    },
                                    "Rates": {
                                        "Days": [
                                            {
                                            "Mon": "True",
                                            "Tue": "True",
                                            "Weds": "True",
                                            "Thur": "True",
                                            "Fri": "True",
                                            "Sat": "True",
                                            "Sun": "True"
                                            }
                                        ],
                                        "BaseByGuestAmts": [
                                            {
                                                "Amount": '.$rateplan_single_price.',
                                                "NumberOfGuests": "1"
                                            },
                                            {
                                                "Amount": '.$rateplan_double_price.',
                                                "NumberOfGuests": "2"
                                            },
                                            {
                                                "Amount": '.$rateplan_triple_price.',
                                                "NumberOfGuests": "3"
                                            }
                                        ],
                                        "AdditionalGuestAmounts": [
                                            {
                                            "GuestType": "ExtraAdult1",
                                            "Amount": "'.$extra_adult_price.'"
                                            },
                                            {
                                            "GuestType": "Child1",
                                            "Amount": "'.$extra_child_price.'"
                                            }
                                        ],
                                        "MinStay": "'.$los.'",
                                        "MaxStay": "'.$los.'",
                                        "StopSell": "True"
                                    }
                                }
                            ]}}';
                            $headers = array (
                                'Content-Type: application/json'
                                );
                            $log_data               = [
                                "action_id"          => 2,
                                "hotel_id"           => $hotel_id,
                                "ota_id"      	     => $ota_id,
                                "rate_ref_id"        => $rateplanids,
                                "user_id"            => $rate_bucket_data['bucket_user_id'],
                                "request_msg"        => '',
                                "response_msg"       => '',
                                "request_url"        => '',
                                "status"         => 2,
                                "ip"         => $rate_bucket_data['bucket_client_ip'],
                                "comment"	=> "Processing for update "
                                ];
                            $heg_rate_xml =$xml;
                            $log_request_msg = $heg_rate_xml;
                            $logModel->fill($log_data)->save();
                            $result=$this->getdata_curlreq->cUrlCall($url,$headers,$heg_rate_xml);//for curl call
                            $array_data =json_decode($result,true);
                            if($array_data['OTA_HotelRateAmountNotifRS']['Status'] == "Success"){
                              DB::table('cmlive.log_table')->where('id', $logModel->id)
                              ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                              $rlt=array('status'=>1,'ota_name'=>'IRCTC','response_msg'=>$result);
                          }
                          else{
                              DB::table('cmlive.log_table')->where('id', $logModel->id)
                              ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                              $rlt=array('status'=>0,'ota_name'=>'IRCTC','response_msg'=>$result);
                          }
                    }
                    else{
                        $log_data               = [
                            "action_id"          => 2,
                            "hotel_id"           => $hotel_id,
                            "ota_id"      	     => $ota_id,
                            "rate_ref_id"        => 0,
                            "user_id"            => $rate_bucket_data['bucket_user_id'],
                            "request_msg"        => '',
                            "response_msg"       => '',
                            "request_url"        => '',
                            "status"         => 1,
                            "ip"         => $rate_bucket_data['bucket_client_ip'],
                            "comment"	=> "Roomrate type is not mapped"
                            ];
                        $logModel->fill($log_data)->save();
                        $rlt=array('status'=>0,'ota_name'=>'IRCTC','response_msg'=>"Rateplan should be sync");
                    }
                }
        if(empty($rlt)){
            $rlt=array('status'=>0,'ota_name'=>'IRCTC','response_msg'=>"Rateplan should be mapped");
        }
        return $rlt;
    }
}

?>
