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
 * This controller is used for Agoda single,bulk,sync and block of inv and rate
 * @auther ranjit
 * created date 05/03/19.
 * modification due to display problem @ 12/03/19 by ranjit(new model added).
 */
class AgodaInvRateController extends Controller
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
        $rlt                            = array();
       
        $ota_room_type = DB::table('cm_ota_room_type_synchronize')
        ->where('hotel_id', '=', $hotel_id)
        ->where('room_type_id', '=', $inventory['room_type_id'])
        ->where('ota_type_id', '=', $ota_id)
        ->value('ota_room_type');
        $xml_data='';

        foreach($inventory['inv'] as $inv)
        {
            $otainventory                   = new OtaInventory();
            $fmonth=explode('-',$inv['date']);//for removing extra o from month and remove this code after mobile app update
            if(strlen($fmonth[1]) == 3)
            {
                $fmonth[1]=ltrim($fmonth[1],0);
            }
            $inv['date']=implode('-',$fmonth);
            $room_code = $cmOtaRoomTypeSynchronizeModel->getOtaRoomType($inventory['room_type_id'],$ota_id);
            $startDate                  = $inv['date'];
            $endDate                    = $inv['date'];
            $inventory_los              = $inv['los'];
            $room_qtys                  = $inv['no_of_rooms'];

            $inv['room_type_id']       = $inventory['room_type_id'];
            $inv['block_status']       = $inv["block_status"];
            $inv['channel']            = 'Agoda';
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
                if($inv['block_status']==0)
                {
                    if($room_qtys >= 0)
                    {
                        $xml='<?xml version="1.0" encoding="UTF-8"?>
                        <request timestamp="1436931804" type="1">
                        <criteria property_id="'.$ota_hotel_code.'">
                        <inventory>
                        <update room_id="'.$room_code.'">
                        <date_range from="'.$startDate.'" to="'.$endDate.'">
                        <dow>1</dow>
                        <dow>2</dow>
                        <dow>3</dow>
                        <dow>4</dow>
                        <dow>5</dow>
                        <dow>6</dow>
                        <dow>7</dow>
                        </date_range>
                        <allotment>'.$room_qtys.'</allotment>
                            <restrictions>
                        <closed>false</closed>
                        <ctd>false</ctd>
                        <cta>false</cta>
                        </restrictions>
                        </update>
                        </inventory></criteria></request>';

                    }
                }
                else
                {
                    $xml='<?xml version="1.0" encoding="UTF-8"?>
                    <request timestamp="1436931804" type="1">
                    <criteria property_id="'.$ota_hotel_code.'">
                    <inventory>
                    <update room_id="'.$room_code.'">
                    <date_range from="'.$startDate.'" to="'.$endDate.'">
                    </date_range>
                    <restrictions>
                    <closed>true</closed>
                    <ctd>false</ctd>
                    <cta>false</cta>
                    </restrictions>
                    </update>
                    </inventory></criteria></request>';

                }
            $log_data               = [
                "action_id"          => 4,
                "hotel_id"           => $hotel_id,
                "ota_id"      	=> $ota_id,
                "inventory_ref_id"   => $bucket_data["bucket_inventory_table_id"],
                "user_id"            => $bucket_data["bucket_user_id"],
                "request_msg"        => '',
                "response_msg"       => '',
                "request_url"        => '',
                "status"         => 2,
                "ip"         => $bucket_data["bucket_client_ip"],
                "comment"	=> "Processing for update "
                ];

            $agoda_xml =$xml;
            $headers = array ('Content-Type: application/xml');
            $log_request_msg = $agoda_xml;
            $logModel->fill($log_data)->save();//saving pre logdata
            $apiKey = trim($auth_parameter->apiKey);
            $url = $commonUrl.'api?apiKey='.$apiKey;

            $result=$this->getdata_curlreq->cUrlCall($url,$headers,$agoda_xml);//for curl call
            // $resultXml=simplexml_load_string($result);
            if($result != strip_tags($result)){
                $resultXml=simplexml_load_string($result);
            }
            else{
                $resultXml = '';
            }
            if($resultXml)
            {
                $array_data = json_decode(json_encode($resultXml), true);
                if(!isset($array_data['errors'])){
                    DB::table('log_table')->where('id', $logModel->id)
                    ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                    $rlt=array('status'=>1,'ota_name'=>'agoda','response_msg'=>"updated successfully");
                }else{
                    DB::table('log_table')->where('id', $logModel->id)
                    ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                    $rlt=array('status'=>0,'ota_name'=>'agoda','response_msg'=>isset($array_data['errors']['property']['error']) ? $array_data['errors']['property']['error']['@attributes']['description']:$array_data['errors']['error']['@attributes']['description']);
                }
            }
            else{
                $rlt=array('status'=>0,'ota_name'=>'agoda','response_msg'=>$result);
                }
        }
        else{
            // set log for Booking Room Type is not synch with hotel Room Type.
            $log_data                 = [
                                        "action_id"          => 2,
                                        "hotel_id"           => $hotel_id,
                                        "ota_id"      	=> $ota_id,
                                        "inventory_ref_id"   => $bucket_data["bucket_inventory_table_id"],
                                        "user_id"            => $bucket_data["bucket_user_id"],
                                        "request_msg"        => '',
                                        "response_msg"       => '',
                                        "request_url"        => '',
                                        "status"         => 0,
                                        "ip"         => $bucket_data["bucket_client_ip"],
                                        "comment"	=> 'Roomrate type is not mapped'
                                        ];
                $logModel->fill($log_data)->save();
                $rlt=array('status'=>0,'ota_name'=>'agoda','response_msg'=>"Roomtype should be sync");
            }

        }
        if(sizeof($rlt)==0){
            $rlt=array('status'=>0,'ota_name'=>'agoda','response_msg'=>"Roomtype should be mapped");
        }
        return $rlt;
    }
    public function blockInventoryUpdate($bucket_data,$room_type_id,$data,$auth_parameter,$commonUrl)
    {
        $blocklog                       = new LogTable();
        $hotel_id                       = $bucket_data["bucket_hotel_id"];
        $ota_id                         = $bucket_data["bucket_ota_id"];
        $ota_hotel_code                 = $bucket_data["bucket_ota_hotel_code"];
        $data['channel']                = 'Agoda';
        $inventoryId                    = array();
        $xml_data                       = '';
        $flag                           = 0;
        $count                          = 0;
        $rlt                            = array();
            $success_flag=1;
            $otainventory                   = new OtaInventory();
                $data['room_type_id']=$room_type_id;
                $room_code       = DB::table('cm_ota_room_type_synchronize')
                                       ->where('hotel_id', '=', $hotel_id)
                                       ->where('room_type_id', '=', $room_type_id)
                                       ->where('ota_type_id', '=', $ota_id)
                                       ->value('ota_room_type');

               $data['ota_id']             = $ota_id;
               $data['user_id']            = $bucket_data["bucket_user_id"];
               $data['client_ip']          = $bucket_data["bucket_client_ip"];
               $data['hotel_id']           = $hotel_id;

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
                $startDate       = $data['date_from'];
                $endDate         = $data['date_to'];
                if($room_code)
                {
                    $xml='<?xml version="1.0" encoding="UTF-8"?>
                    <request timestamp="1436931804" type="1">
                    <criteria property_id="'.$ota_hotel_code.'">
                    <inventory>
                    <update room_id="'.$room_code.'">
                    <date_range from="'.$startDate.'" to="'.$endDate.'">
                    </date_range>
                    <restrictions>
                    <closed>true</closed>
                    <ctd>false</ctd>
                    <cta>false</cta>
                    </restrictions>
                    </update>
                    </inventory></criteria></request>';

                    $apiKey = trim($auth_parameter->apiKey);
                    $url                        = $commonUrl.'api?apiKey='.$apiKey;
                    $invId                      = implode(',',$inventoryId);

                    $log_data                 = [
                        "action_id"          => 3,
                        "hotel_id"           => $hotel_id,
                        "ota_id"      	=> $ota_id,
                        "inventory_ref_id"   => $invId,
                        "user_id"            => $bucket_data["bucket_user_id"],
                        "request_msg"        =>  '',
                        "response_msg"       =>  '',
                        "request_url"        =>  '',
                        "status"         => 2,
                        "ip"         => $bucket_data["bucket_client_ip"],
                        "comment"	=> "Processing for update"
                        ];

                    $blocklog->fill($log_data)->save();
                    $agoda_xml=$xml;
                    $log_request_msg=$agoda_xml;
                    $headers = array ('Content-Type: application/xml');
                    $result=$this->getdata_curlreq->cUrlCall($url,$headers,$agoda_xml);//for curl call
                    if($result != strip_tags($result)){
                        $resultXml=simplexml_load_string($result);
                    }
                    else{
                        $resultXml = '';
                    }
                    if($resultXml)
                    {
                        $array_data = json_decode(json_encode($resultXml), true);
                        if(!isset($array_data['errors'])){
                            DB::table('log_table')->where('id', $blocklog->id)
                            ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                            $rlt=array('status'=>1,'ota_name'=>'agoda','response_msg'=>"updated successfully");
                        }else{
                            DB::table('log_table')->where('id', $blocklog->id)
                            ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                            $rlt=array('status'=>0,'ota_name'=>'agoda','response_msg'=>isset($array_data['errors']['property']['error']) ? $array_data['errors']['property']['error']['@attributes']['description']:$array_data['errors']['error']['@attributes']['description']);
                        }
                    }
                    else{
                        $rlt=array('status'=>0,'ota_name'=>'agoda','response_msg'=>$result);
                        }
                }
                else{
                    $log_data                 = [
                        "action_id"          => 3,
                        "hotel_id"           => $hotel_id,
                        "ota_id"      	=> $ota_id,
                        "inventory_ref_id"   => $bucket_data["bucket_inventory_table_id"],
                        "user_id"            => $bucket_data["bucket_user_id"],
                        "request_msg"        =>  '',
                        "response_msg"       =>  '',
                        "request_url"        =>  '',
                        "status"         => 2,
                        "ip"         => $bucket_data["bucket_client_ip"],
                        "comment"	=> "Roomrate type is not mapped"
                        ];

                    $blocklog->fill($log_data)->save();
                    $rlt=array('status' => 1,'ota_name'=>'agoda','response'=> 'Roomtype should be Sync');
                }

            }
        if(empty($rlt)){
            $rlt=array('status'=>0,'ota_name'=>'agoda','response_msg'=>"Roomtype should be mapped");
        }
        return $rlt;
    }
    public function singleInvUpdate($bucket_data,$inventory,$auth_parameter,$commonUrl)
    {
        $cmOtaRoomTypeSynchronizeModel  = new CmOtaRoomTypeSynchronize();
        $logModel                       = new LogTable();
        $xml                            = '';
        $hotel_id                       = $bucket_data["bucket_hotel_id"];
        $ota_id                         = $bucket_data["bucket_ota_id"];
        $ota_hotel_code                 = $bucket_data["bucket_ota_hotel_code"];
        $inventoryId                    = array();
        $flag                           = 0;
        $count                          = 0;
        $rlt                            = array();
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
                $otainventory                   = new OtaInventory();//used for insert in to ota inv table
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
                $inv['channel']            = 'Agoda';
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
                    $inventoryId[]   = $otainventory->inventory_id;
                    if($room_code)
                    {
                        $flag=1;
                        if($room_qtys >= 0)
                        {
                            $xml='<?xml version="1.0" encoding="UTF-8"?>
                            <request timestamp="1436931804" type="1">
                            <criteria property_id="'.$ota_hotel_code.'">
                            <inventory>
                            <update room_id="'.$room_code.'">
                            <date_range from="'.$startDate.'" to="'.$endDate.'">
                            <dow>1</dow>
                            <dow>2</dow>
                            <dow>3</dow>
                            <dow>4</dow>
                            <dow>5</dow>
                            <dow>6</dow>
                            <dow>7</dow>
                            </date_range>
                            <allotment>'.$room_qtys.'</allotment>
                             <restrictions>
                            <closed>false</closed>
                            <ctd>false</ctd>
                            <cta>false</cta>
                            </restrictions>
                            </update>
                            </inventory></criteria></request>';

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

                            $agoda_xml =$xml;
                            $headers = array ('Content-Type: application/xml');
                            $log_request_msg = $agoda_xml;
                            $logModel->fill($log_data)->save();//saving pre log data
                            $apiKey 					= trim($auth_parameter->apiKey);
                            $url = $commonUrl.'api?apiKey='.$apiKey;

                            $result=$this->getdata_curlreq->cUrlCall($url,$headers,$agoda_xml);//for curl call


                            if($result != strip_tags($result)){
                                $resultXml=simplexml_load_string($result);
                            }
                            else{
                                $resultXml = '';
                            }

                            if($resultXml)
                            {
                                $array_data = json_decode(json_encode($resultXml), true);
                                if(!isset($array_data['errors'])){
                                    DB::table('log_table')->where('id', $logModel->id)
                                    ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                                    $rlt=array('status'=>1,'ota_name'=>'agoda','response_msg'=>"updated successfully");
                                }else{
                                    DB::table('log_table')->where('id', $logModel->id)
                                    ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                                  $rlt=array('status'=>0,'ota_name'=>'agoda','response_msg'=>isset($array_data['errors']['property']['error']) ? $array_data['errors']['property']['error']['@attributes']['description']:$array_data['errors']['error']['@attributes']['description']);
                                }
                            }
                            else{
                                $rlt=array('status'=>0,'ota_name'=>'agoda','response_msg'=>$result);
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
                                $rlt=array('status'=>0,'ota_name'=>'agoda','response_msg'=>"Roomtype should be sync");
                        }
                    }
                }
            }
        }
        if(sizeof($rlt)==0){
            $rlt=array('status'=>0,'ota_name'=>'agoda','response_msg'=>"Roomtype should be mapped");
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
        $data['channel']                = 'Agoda';
        $rlt                            = array();
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


                $agoda_xml ='<?xml version="1.0" encoding="UTF-8"?>
                <request timestamp="1436931804" type="1">
                <criteria property_id="'.$ota_hotel_code.'">
                <inventory>
                <update room_id="'.$room_code.'">
                <date_range from="'.$startDate.'" to="'.$endDate.'">
                <dow>1</dow>
                <dow>2</dow>
                <dow>3</dow>
                <dow>4</dow>
                <dow>5</dow>
                <dow>6</dow>
                <dow>7</dow>
                </date_range>
                <allotment>'.$room_qtys.'</allotment>
                 <restrictions>
                <closed>false</closed>
                <ctd>false</ctd>
                <cta>false</cta>
                </restrictions>
                </update>
                </inventory>
                </criteria>
                </request>';

                $headers = array ('Content-Type: application/xml');
                $log_request_msg = $agoda_xml;
                $logModel->fill($log_data)->save();//saving pre logdata
                $apiKey 					= trim($auth_parameter->apiKey);
                $url = $commonUrl.'api?apiKey='.$apiKey;

                $result=$this->getdata_curlreq->cUrlCall($url,$headers,$agoda_xml);//for curl call
                if($result != strip_tags($result)){
                    $resultXml=simplexml_load_string($result);
                }
                else{
                    $resultXml = '';
                }
                if($resultXml)
                {
                    $array_data = json_decode(json_encode($resultXml), true);
                    if(!isset($array_data['errors'])){
                        DB::table('log_table')->where('id', $logModel->id)
                        ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                        $rlt=array('status'=>1,'ota_name'=>'agoda','response_msg'=>"updated successfully");
                    }else{
                        DB::table('log_table')->where('id', $logModel->id)
                        ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                        $rlt=array('status'=>0,'ota_name'=>'agoda','response_msg'=>isset($array_data['errors']['property']['error']) ? $array_data['errors']['property']['error']['@attributes']['description']:$array_data['errors']['error']['@attributes']['description']);
                    }
                }
                else{
                    $rlt=array('status'=>0,'ota_name'=>'agoda','response_msg'=>$result);
                    }
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
                    $rlt=array('status'=>0,'ota_name'=>'agoda','response_msg'=>'Roomtype should be sync');
            }
            if(sizeof($rlt)==0){
                $rlt=array('status'=>0,'ota_name'=>'agoda','response_msg'=>"Roomtype should be mapped");
            }
            return $rlt;
    }
    public function rateSyncUpdate($rate_bucket_data,$rates_data,$auth_parameter,$commonUrl,$from_date,$to_date)
    {
        $cmOtaRatePlanSynchronizeModel 	= new CmOtaRatePlanSynchronize();
        $roomTypeModel                  = new MasterRoomType();
        $logModel                       = new RateUpdateLog();
       
        $date 							= new \DateTime();
        $dateTimestamp 					= $date->getTimestamp();
        $multiple_days                  = '{"Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1","Sun":"1"}';
        $hotel_id                       = $rate_bucket_data['bucket_hotel_id'];
        $ota_id                         = $rate_bucket_data['bucket_ota_id'];
        $ota_hotel_code                 = $rate_bucket_data['bucket_ota_hotel_code'];
        $xml                            = '';
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
            $rateplan_multiple_days_data = $this->getdata_curlreq->getDaysUpdate($multiple_days,"Agoda");
            $currency=$this->getdata_curlreq->getCurrency($hotel_id);
            $extra_adult_price			 = $rates['extra_adult_price'];
            $extra_child_price			 = $rates['extra_child_price'];
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
            $result 					 = $cmOtaRatePlanSynchronizeModel->getOtaRoomIdFromRatePlanSynch($rates['room_type_id'],$ota_id,$rates['rate_plan_id']);
            if(isset($result[0])){
                $room_code                   = $result[0]['ota_room_type_id'];
                $rate_code                   = $result[0]['ota_rate_plan_id'];
            }
            else{
                continue;
            }
            $startDate                      = $rates['date'];
            $endDate                        = $rates['date'];
            $rateplan_los                   = 1;
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
            $rates['channel']               = 'Agoda';
            $rates['multiple_occupancy']    = json_encode($rates['multiple_occupancy']);
            try{
                $otaRatePlan->fill($rates)->save();
            }
            catch(Exception $e){

            }
            if($rate_code)
            {
                $xml='<?xml version="1.0" encoding="UTF-8"?>
                <request timestamp="'.$dateTimestamp.'" type="1">
                <criteria property_id="'.$ota_hotel_code.'">
                <rate>
                <update room_id="'.$room_code.'" rateplan_id="'.$rate_code.'">
                <date_range from="'.$startDate.'" to="'.$endDate.'">
                '.$rateplan_multiple_days_data.'
                </date_range>
                <prices currency="'.$currency.'">
                <occupancy>';
                if($rateplan_single_price){
                $xml .='<single>'.$rateplan_single_price.'</single>';
                }
                if($rateplan_double_price){
                $xml .='<double>'.$rateplan_double_price.'</double>';
                }
                if($max_adult>2)
                {
                    $xml .='<full>'.$rateplan_triple_price.'</full>';
                }
                $xml .='</occupancy>';
                if($extra_adult_price!=0 && $extra_adult_price!='')
                    {
                        $xml .='<extra_bed>'.$extra_adult_price.'</extra_bed>';
                    }
                $xml .='</prices>
                <restrictions>
                <closed>false</closed>
                <los>
                <min>'.$rateplan_los.'</min>
                </los>
                </restrictions>
                </update>
                </rate></criteria>
                </request>';

                $headers = array (
                    //Regulates versioning of the XML interface for the API
                    'Content-Type: application/xml'
                    );
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
                $agoda_rate_xml =$xml;
                $log_request_msg = $agoda_rate_xml;
                $apiKey 					= trim($auth_parameter->apiKey);
                $url = $commonUrl.'api?apiKey='.$apiKey;
                $logModel->fill($log_data)->save();
                $ota_rlt=$this->getdata_curlreq->cUrlCall($url,$headers,$agoda_rate_xml);//for curl call
                if($result != strip_tags($ota_rlt)){
                    $resultXml=simplexml_load_string($ota_rlt);
                }
                else{
                    $resultXml = '';
                }
                if($resultXml)
                {
                    $array_data = json_decode(json_encode($resultXml), true);
                    if(!isset($array_data['errors'])){
                        DB::table('rate_update_logs')->where('id', $logModel->id)
                        ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
                        $rlt=array('status'=>1,'ota_name'=>'agoda','response_msg'=>"updated successfully");
                    }else{
                        DB::table('rate_update_logs')->where('id', $logModel->id)
                        ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
                        if(isset($array_data['errors']['property']["error"])){
                            $resp_ag = $array_data['errors']['property']["error"]["@attributes"]["description"];
                        }
                        else{
                            if(isset($array_data['errors']["error"])){
                                $resp_ag = $array_data['errors']["error"]["@attributes"]["description"];
                            }
                            else{
                                $resp_ag = $array_data['errors']['property']["error"]['@attributes']["description"];
                            }
                        }
                        $rlt=array('status'=>0,'ota_name'=>'agoda','response_msg'=>$resp_ag);
                    }
                }
                else{
                    $rlt=array('status'=>0,'ota_name'=>'agoda','response_msg'=>$ota_rlt);
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
                $rlt=array('status'=>0,'ota_name'=>'agoda','response_msg'=>"Rateplan should be sync");
            }
        }
        if(sizeof($rlt)==0){
            $rlt=array('status'=>0,'ota_name'=>'agoda','response_msg'=>"Rateplan should be mapped");
        }
        return $rlt;
    }
    public function singleRateUpdate($rate_bucket_data,$rates_data,$auth_parameter,$commonUrl)
    {
        $cmOtaRatePlanSynchronizeModel 	= new CmOtaRatePlanSynchronize();
        $roomTypeModel                  = new MasterRoomType();
        $logModel                       = new RateUpdateLog();
        $date 							= new \DateTime();
        $dateTimestamp 					= $date->getTimestamp();
        $xml                  = '';
        $multiple_days                  = '{"Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1","Sun":"1"}';
        $hotel_id                       = $rate_bucket_data['bucket_hotel_id'];
        $ota_id                         = $rate_bucket_data['bucket_ota_id'];
        $ota_hotel_code                 = $rate_bucket_data['bucket_ota_hotel_code'];
        $rateplanId                     = array();
        $count                          = 0;
        $flag                           = 0;
        $rlt                            = array();

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
                $rates['channel']               = 'Agoda';
                $min_max_status =   $this->getdata_curlreq->checkMinMaxPrice($rates['room_type_id'],$rates['rate_plan_id'],$rates['bar_price'],$rates['multiple_occupancy'],$rates['hotel_id'],$rates['date'],$rates['channel']);
                if($min_max_status){
                    $rlt = $min_max_status;
                    continue;
                }
                $rateplan_multiple_days_data = $this->getdata_curlreq->getDaysUpdate($rates['multiple_days'],"Agoda");
                $currency=$this->getdata_curlreq->getCurrency($hotel_id);
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
                $result 					 = $cmOtaRatePlanSynchronizeModel->getOtaRoomIdFromRatePlanSynch($rates['room_type_id'],$ota_id,$rates['rate_plan_id']);
                if(isset($result[0])){
                    $room_code                   = $result[0]['ota_room_type_id'];
                    $rate_code                   = $result[0]['ota_rate_plan_id'];
                }
                else{
                    continue;
                }
                $startDate                   =  $rates['from_date'];
                $endDate                     =  $rates['to_date'];
                $rateplan_los                = $rates['los'];
                $extra_adult_price           = '';
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
                    $rateplanId[]   = $otaRatePlan->rate_plan_log_id;
                    if($rate_code)
                    {
                        $flag=1;
                        $xml='<?xml version="1.0" encoding="UTF-8"?>
                        <request timestamp="'.$dateTimestamp.'" type="1">
                        <criteria property_id="'.$ota_hotel_code.'">
                        <rate>
                        <update room_id="'.$room_code.'" rateplan_id="'.$rate_code.'">
                        <date_range from="'.$startDate.'" to="'.$endDate.'">
                        '.$rateplan_multiple_days_data.'
                        </date_range>
                        <prices currency="'.$currency.'">
                        <occupancy>';
                        if($rateplan_single_price){
                        $xml .='<single>'.$rateplan_single_price.'</single>';
                        }
                        if($rateplan_double_price){
                        $xml .='<double>'.$rateplan_double_price.'</double>';
                        }
                        if($max_adult>2)
                        {
                            $xml .='<full>'.$rateplan_triple_price.'</full>';
                        }
                        $xml .='</occupancy>';

                        if($extra_adult_price!=0 && $extra_adult_price!='')
                         {
                             $xml .='<extra_bed>'.$extra_adult_price.'</extra_bed>';

                         }
                        $xml .='</prices>
                        <restrictions>
                        <closed>false</closed>
                        <los>
                        <min>'.$rateplan_los.'</min>
                        </los>
                        </restrictions>
                        </update>
                        </rate></criteria>
                        </request>';

                        $headers = array (
                            'Content-Type: application/xml'
                            );
                        $apiKey 					= trim($auth_parameter->apiKey);
                        $url = $commonUrl.'api?apiKey='.$apiKey;
                        $rateplanids                     = implode(',',$rateplanId);

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
                            $agoda_rate_xml =$xml;

                        $log_request_msg = $xml;
                        $logModel->fill($log_data)->save();
                        $ota_rlt=$this->getdata_curlreq->cUrlCall($url,$headers,$agoda_rate_xml);//for curl call
                        if($result != strip_tags($ota_rlt)){
                            $resultXml=simplexml_load_string($ota_rlt);
                        }
                        else{
                            $resultXml = '';
                        }
                        if($resultXml)
                        {
                            $array_data = json_decode(json_encode($resultXml), true);
                            if(!isset($array_data['errors'])){
                                DB::table('rate_update_logs')->where('id', $logModel->id)
                                ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
                                $rlt=array('status'=>1,'ota_name'=>'agoda','response_msg'=>"updated successfully");
                            }else{
                                DB::table('rate_update_logs')->where('id', $logModel->id)
                                ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
                                if(isset($array_data['errors']['property']["error"])){
                                    $resp_ag = $array_data['errors']['property']["error"]["@attributes"]["description"];
                                }
                                else{
                                    if(isset($array_data['errors']["error"])){
                                        $resp_ag = $array_data['errors']["error"]["@attributes"]["description"];
                                    }
                                    else{
                                        $resp_ag = $array_data['errors']['property']["error"]['@attributes']["description"];
                                    }
                                }
                                $rlt=array('status'=>0,'ota_name'=>'agoda','response_msg'=>$resp_ag);
                            }
                        }
                        else{
                            $rlt=array('status'=>0,'ota_name'=>'agoda','response_msg'=>$ota_rlt);
                            }
                    }
                    else{
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
                            "comment"			 => "Roomrate type is not mapped"
                            ];
                        $logModel->fill($log_data)->save();
                        $rlt=array('status'=>0,'ota_name'=>'agoda','response_msg'=>"Rateplan should be sync");
                    }
                }
                else{
                    $rateplanId=0;
                }
            }
        }

        if(sizeof($rlt)==0){
            $rlt=array('status'=>0,'ota_name'=>'agoda','response_msg'=>"Rateplan should be mapped");
        }
        return $rlt;
    }
    public function bulkRateUpdate($rate_bucket_data,$data,$auth_parameter,$commonUrl)
    {
        $cmOtaRatePlanSynchronizeModel 	= new CmOtaRatePlanSynchronize();
        $roomTypeModel                  = new MasterRoomType();
        $logModel                       = new RateUpdateLog();
        $otaRatePlan                    = new OtaRatePlan();//ota rate table insertion
        $date 							= new \DateTime();
        $dateTimestamp 					= $date->getTimestamp();
        $rateplanId                     = '';
        $xml_data                       = '';
        $multiple_days                  = '{"Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1","Sun":"1"}';
        $hotel_id                       = $rate_bucket_data['bucket_hotel_id'];
        $ota_id                         = $rate_bucket_data['bucket_ota_id'];
        $ota_hotel_code                 = $rate_bucket_data['bucket_ota_hotel_code'];
        $rlt                            = array();
        $flag                           = 0;
        $data['multiple_days']       = json_encode($data['multiple_days']);

        $max_adult=$roomTypeModel->select('max_people')->where('room_type_id',$data['room_type_id'])->first()->max_people;

        $currency=$this->getdata_curlreq->getCurrency($hotel_id);
        $rateplan_multiple_days_data = $this->getdata_curlreq->getDaysUpdate($data['multiple_days'],"Agoda");
        $occupency=$this->getdata_curlreq->decideOccupencyPrice($max_adult,$data['bar_price'],$data['multiple_occupancy']);
        $rateplan_single_price=0;
        $rateplan_double_price=0;
        $rateplan_triple_price=0;
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
            $rlt=array('status'=>0,'ota_name'=>'agoda','response_msg'=>"Room type or Rateplan should be sync");
            return $rlt;
        }
        $startDate                   = date('Y-m-d',strtotime($data['from_date']));
        $endDate                     = date('Y-m-d',strtotime($data['to_date']));
        $data['multiple_occupancy']  = json_encode($data['multiple_occupancy']);
        $rateplan_los                = $data['los'];
        $data['channel']             = 'Agoda';
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
                $xml='<rate>
                <update room_id="'.$room_code.'" rateplan_id="'.$rate_code.'">
                <date_range from="'.$startDate.'" to="'.$endDate.'">
                '.$rateplan_multiple_days_data.'
                </date_range>
                <prices currency="'.$currency.'">
                <occupancy>';
                if($rateplan_single_price){
                $xml .='<single>'.$rateplan_single_price.'</single>';
                }
                if($rateplan_double_price){
                $xml .='<double>'.$rateplan_double_price.'</double>';
                }
                if($max_adult>2)
                {
                    $xml .='<full>'.$rateplan_triple_price.'</full>';
                }
                $xml .='</occupancy>';

                if($extra_adult_price!=0 && $extra_adult_price!='')
                 {
                     $xml .='<extra_bed>'.$extra_adult_price.'</extra_bed>';
                 }
                $xml .='</prices>
                <restrictions>
                <closed>false</closed>
                <los>
                <min>'.$rateplan_los.'</min>
                </los>
                </restrictions>
                </update>
                </rate>';
                $xml_data.= $xml;
            }
            else{
                $flag=0;
            }
        }
        else{
            $rateplanId=0;
        }
        if($flag==1)
        {
            $headers = array (
                //Regulates versioning of the XML interface for the API
                'Content-Type: application/xml'
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
            $agoda_rate_xml ='<?xml version="1.0" encoding="UTF-8"?>
            <request timestamp="'.$dateTimestamp.'" type="1">
            <criteria property_id="'.$ota_hotel_code.'">';
            $agoda_rate_xml.= $xml_data.'</criteria>
            </request>';
            $log_request_msg = $agoda_rate_xml;
            $apiKey 					= trim($auth_parameter->apiKey);
            $url = $commonUrl.'api?apiKey='.$apiKey;
            $logModel->fill($log_data)->save();
            $ota_rlt=$this->getdata_curlreq->cUrlCall($url,$headers,$agoda_rate_xml);//for curl call
            if($result != strip_tags($ota_rlt)){
                $resultXml=simplexml_load_string($ota_rlt);
            }
            else{
                $resultXml = '';
            }
			if($resultXml)
			{
				$array_data = json_decode(json_encode($resultXml), true);
				if(!isset($array_data['errors'])){
					DB::table('rate_update_logs')->where('id', $logModel->id)
					->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
					$rlt=array('status'=>1,'ota_name'=>'agoda','response_msg'=>"updated successfully");
				}else{
					DB::table('rate_update_logs')->where('id', $logModel->id)
					->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
                    if(isset($array_data['errors']['property']["error"])){
                        if(isset($array_data['errors']['property']["error"][0])){
                            $resp_ag = '';
                            foreach($array_data['errors']['property']["error"] as $error_info){
                                $resp_ag.= $error_info["@attributes"]["description"];
                            }
                        }
                        else{
                            $resp_ag = $array_data['errors']['property']["error"]["@attributes"]["description"];
                        }
                    }
                    else{
                        if(isset($array_data['errors']["error"])){
                            $resp_ag = $array_data['errors']["error"]["@attributes"]["description"];
                        }
                        else{
                            $resp_ag = $array_data['errors']['property']["error"]['@attributes']["description"];
                        }
                    }
                    $rlt=array('status'=>0,'ota_name'=>'agoda','response_msg'=>$resp_ag);
				}
			}
			else{
				$rlt=array('status'=>0,'ota_name'=>'agoda','response_msg'=>$ota_rlt);
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
            $rlt=array('status'=>0,'ota_name'=>'agoda','response_msg'=>"Rateplan should be sync");
        }
        if(sizeof($rlt)==0){
            $rlt=array('status'=>0,'ota_name'=>'agoda','response_msg'=>"Rateplan should be mapped");
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
        $date = new \DateTime();
        $dateTimestamp = $date->getTimestamp();
        $max_adult=$roomTypeModel->select('max_people')->where('room_type_id',$data['room_type_id'])->first()->max_people;

            $data['date_from'] = date('Y-m-d',strtotime($data['date_from']));
            $data['date_to'] = date('Y-m-d',strtotime($data['date_to']));
            $cond = array('hotel_id'=>$data['hotel_id'],'room_type_id'=>$rooms,'rate_plan_id'=>$data['rate_plan_id'],'channel'=>'agoda');
            $getRateDetails = OtaRatePlan::select('*')
                                ->where($cond)->where('from_date','<=',$data['date_from'])
                                ->where('to_date','>=',$data['date_to'])
                                ->orderBy('rate_plan_log_id','DESC')
                                ->first();
                $rate_data = [
                    'hotel_id'          => $getRateDetails->hotel_id,
                    'channel'           => 'Agoda',
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
                $rateplan_multiple_days_data = $this->getdata_curlreq->getDaysUpdate($multiple_days,"agoda");
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
                    if($rate_code){
                        $xml='<?xml version="1.0" encoding="UTF-8"?>
                        <request timestamp="'.$dateTimestamp.'" type="1">
                        <criteria property_id="'.$ota_hotel_code.'">
                        <rate>
                        <update room_id="'.$room_code.'" rateplan_id="'.$rate_code.'">
                        <date_range from="'.$startDate.'" to="'.$endDate.'">
                        '.$rateplan_multiple_days_data.'
                        </date_range>
                        <prices currency="'.$currency.'">
                        <occupancy>';
                        if($rateplan_single_price){
                        $xml .='<single>'.$rateplan_single_price.'</single>';
                        }
                        if($rateplan_double_price){
                        $xml .='<double>'.$rateplan_double_price.'</double>';
                        }
                        if($max_adult>2)
                        {
                            $xml .='<full>'.$rateplan_triple_price.'</full>';
                        }
                        $xml .='</occupancy>';

                        if($extra_adult_price!=0 && $extra_adult_price!='')
                         {
                             $xml .='<extra_bed>'.$extra_adult_price.'</extra_bed>';
                         }
                        $xml .='</prices>
                        <restrictions>
                        <closed>True</closed>
                        <los>
                        <min>0</min>
                        </los>
                        </restrictions>
                        </update>
                        </rate></criteria>
                        </request>';

                        $headers = array (
                            'Content-Type: application/xml'
                            );
                        $apiKey = trim($auth_parameter->apiKey);
                        $url                        = $commonUrl.'api?apiKey='.$apiKey;
                        $rateplanids                = implode(',',$rateplanId);

                        $log_data               = [
                            "action_id"          => 2,
                            "hotel_id"           => $hotel_id,
                            "ota_id"      	=> $ota_id,
                            "rate_ref_id"        => $rateplanids,
                            "user_id"            => $rate_bucket_data['bucket_user_id'],
                            "request_msg"        => '',
                            "response_msg"       => '',
                            "request_url"        => '',
                            "status"         => 1,
                            "ip"         => $rate_bucket_data['bucket_client_ip'],
                            "comment"	=> "Processing for update "
                            ];
                            $agoda_rate_xml =$xml;

                        $log_request_msg = $xml;
                        $logModel->fill($log_data)->save();
                        $ota_rlt=$this->getdata_curlreq->cUrlCall($url,$headers,$agoda_rate_xml);//for curl call
                        if($result != strip_tags($ota_rlt)){
                            $resultXml=simplexml_load_string($ota_rlt);
                        }
                        else{
                            $resultXml = '';
                        }
                        if($resultXml)
                        {
                            $array_data = json_decode(json_encode($resultXml), true);
                            if(!isset($array_data['errors'])){
                                DB::table('log_table')->where('id', $logModel->id)
                                ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
                                $rlt=array('status'=>1,'ota_name'=>'agoda','response_msg'=>"updated successfully");
                            }else{
                                DB::table('log_table')->where('id', $logModel->id)
                                ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
                                if(isset($array_data['errors']['property']["error"])){
                                    $resp_ag = $array_data['errors']['property']["error"]["@attributes"]["description"];
                                }
                                else{
                                    if(isset($array_data['errors']["error"])){
                                        $resp_ag = $array_data['errors']["error"]["@attributes"]["description"];
                                    }
                                    else{
                                        $resp_ag = $array_data['errors']['property']["error"]['@attributes']["description"];
                                    }
                                }
                                $rlt=array('status'=>0,'ota_name'=>'agoda','response_msg'=>$resp_ag);
                            }
                        }
                        else{
                            $rlt=array('status'=>0,'ota_name'=>'agoda','response_msg'=>$ota_rlt);
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
                        $rlt=array('status'=>0,'ota_name'=>'agoda','response_msg'=>"Rateplan should be sync");
                    }
                }
        if(empty($rlt)){
            $rlt=array('status'=>0,'ota_name'=>'agoda','response_msg'=>"Rateplan should be mapped");
        }

        return $rlt;
    }
}
