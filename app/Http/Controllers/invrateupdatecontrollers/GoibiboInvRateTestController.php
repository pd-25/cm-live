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
use App\CmOtaDetails;
use App\OtaInventory;//new model for single ota inv push
use App\OtaRatePlan;//new model for single ota rate push
use App\CmOtaRoomTypeSynchronize;
use App\CmOtaRatePlanSynchronize;
use App\Http\Controllers\Controller;
use App\Http\Controllers\IpAddressService;
use App\Http\Controllers\invrateupdatecontrollers\GetDataForRateController;
/**
 * This controller is used for goibibo single,bulk,sync and block of inv and rate
 * @auther ranjit
 * created date 21/02/19.
 * modification due to display problem @ 12/03/19 by ranjit(new model added)
 */
class GoibiboInvRateTestController extends Controller
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
        $xml                            = '';
        $hotel_id                       = $bucket_data["bucket_hotel_id"];
        $ota_id                         = $bucket_data["bucket_ota_id"];
        $ota_hotel_code                 = $bucket_data["bucket_ota_hotel_code"];
        $flag                           ='';
        $rlt                            =array();
        $index_id                       = 1;
        $count_inv                      = 0;

            $ota_room_type = DB::table('cm_ota_room_type_synchronize')
            ->where('hotel_id', '=', $hotel_id)
            ->where('room_type_id', '=', $inventory['room_type_id'])
            ->where('ota_type_id', '=', $ota_id)
            ->value('ota_room_type');

            $xml='<?xml version="1.0" encoding="UTF-8" ?>
            <AvailRateUpdateRQ hotelCode="'.$ota_hotel_code.'" timeStamp="">';

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
                $startDate                  = date('Y-m-d',strtotime($inv['date']));
                $endDate                    = date('Y-m-d',strtotime($inv['date']));
                $inventory_los              = $inv['los'];
                $room_qtys                  = $inv["no_of_rooms"];
                $inv['room_type_id']       = $inventory['room_type_id'];
                $inv['block_status']       = $inv["block_status"];
                $inv['channel']            = 'Goibibo';
                $inv['ota_id']             = $ota_id;
                $inv['user_id']            = $bucket_data["bucket_user_id"];
                $inv['client_ip']          = $bucket_data["bucket_client_ip"];
                $inv['hotel_id']           = $hotel_id;
                $inv['date_from']          = date('Y-m-d',strtotime($inv['date']));
                $inv['date_to']            = date('Y-m-d',strtotime($inv['date']));
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

                            $xml.='<AvailRateUpdate locatorID="'.$index_id.'"><DateRange from="'.$startDate.'" to="'.$endDate.'"/>
                               <Availability code="'.$room_code.'" count="'.$room_qtys.'" closed="false" /></AvailRateUpdate>';
                               $index_id ++;
                        }
                    }
                    else
                    {
                      $xml.='<AvailRateUpdate locatorID="'.$index_id.'"><DateRange from="'.$startDate.'" to="'.$endDate.'"/>
                         <Availability code="'.$room_code.'" count="'.$room_qtys.'" closed="true" /></AvailRateUpdate>';
                         $index_id ++;
                    }
                }
            }
            $xml.='</AvailRateUpdateRQ>';
            if($flag == 1){
                $log_data               	= [
                    "action_id"          => 4,
                    "hotel_id"           => $hotel_id,
                    "ota_id"      		   => $ota_id,
                    "inventory_ref_id"   => $bucket_data["bucket_inventory_table_id"],
                    "user_id"            => $bucket_data["bucket_user_id"],
                    "request_msg"        => '',
                    "response_msg"       => '',
                    "request_url"        => '',
                    "status"         	 => 2,
                    "ip"         		 => $bucket_data["bucket_client_ip"],
                    "comment"			 => "Processing for update "
                    ];

                    $goibibo_xml =$xml;
                    $bearer_token 					= trim($auth_parameter->bearer_token);
                    $channel_token 					= trim($auth_parameter->channel_token);
                    $headers = array(
                              "Content-Type: application/xml",
                              "channel-token:".$channel_token,
                              "bearer-token:".$bearer_token
                            );
                    $log_request_msg = $goibibo_xml;
                    $logModel->fill($log_data)->save();//saving pre logdata
                    $url = "https://partners-connect.goibibo.com/api/chm/v3/ari";

                    $ota_rlt=$this->getdata_curlreq->cUrlCall($url,$headers,$goibibo_xml);//for curl call
                $resultXml=simplexml_load_string($ota_rlt);
                if($ota_rlt=='OAuth Authorization Required'){
                    DB::table('log_table')->where('id', $logModel->id)
                        ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
                        $rlt=array('status'=>0,'ota_name'=>'goibibo','response_msg'=>$ota_rlt);

                }
                if($resultXml){
                    $array_data = json_decode(json_encode($resultXml), true);
                    if(!isset($array_data['Error'])){
                        DB::table('log_table')->where('id', $logModel->id)
                        ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
                        $rlt=array('status'=>1,'ota_name'=>'goibibo','response_msg'=>$ota_rlt);
                    }
                    else{
                        DB::table('log_table')->where('id', $logModel->id)
                        ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
                        $rlt=array('status'=>0,'ota_name'=>'goibibo','response_msg'=>$ota_rlt);
                    }
                }
                else{
                    $rlt=array('status'=>0,'ota_name'=>'goibibo','response_msg'=>$ota_rlt);
                }
            }
            else{
                // set log for Booking Room Type is not synch with hotel Room Type.
                $log_data                 	= [
                   "action_id"          => 2,
                   "hotel_id"           => $hotel_id,
                   "ota_id"      		    => $ota_id,
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
               $rlt=array('status'=>0,'ota_name'=>'goibibo','response_msg'=>"Roomtype should be sync");
           }
            return $rlt;
    }
    public function blockInventoryUpdate($bucket_data,$room_type_id,$data,$auth_parameter,$commonUrl)
    {
        $blocklog                       = new LogTable();
        $otainventory                   = new OtaInventory();
        $hotel_id                       = $bucket_data["bucket_hotel_id"];
        $ota_id                         = $bucket_data["bucket_ota_id"];
        $ota_hotel_code                 = $bucket_data["bucket_ota_hotel_code"];
        $data['channel']                = 'Goibibo';
        $inventoryId                    = array();
        $rlt=array();
        $xml                            = '';
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
            $inventoryId[]   = $otainventory->inventory_id;
            $hotel_id        = $otainventory->hotel_id;
            $room_code       = DB::table('cm_ota_room_type_synchronize')
                               ->where('hotel_id', '=', $hotel_id)
                               ->where('room_type_id', '=', $room_type_id)
                               ->where('ota_type_id', '=', $ota_id)
                               ->value('ota_room_type');
            $startDate       = date('Y-m-d',strtotime($data['date_from']));
            $endDate         = date('Y-m-d',strtotime($data['date_to']));
            $room_qtys = 1;
            if(!empty($room_code))
            {
                $flag = 1;
                $xml='<?xml version="1.0" encoding="UTF-8" ?>
                        <AvailRateUpdateRQ hotelCode="'.$ota_hotel_code.'" timeStamp="">
                        <AvailRateUpdate locatorID="1">
                            <DateRange from="'.$startDate.'" to="'.$endDate.'"/>
                            <Availability code="'.$room_code.'" count="'.$room_qtys.'" closed="true" />
                        </AvailRateUpdate>
                        </AvailRateUpdateRQ>';
                $bearer_token      = trim($auth_parameter->bearer_token);
                $channel_token     = trim($auth_parameter->channel_token);
                $invId             = implode(',',$inventoryId);

                $log_data                 		= [
                    "action_id"          => 3,
                    "hotel_id"           => $hotel_id,
                    "ota_id"      		   => $ota_id,
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
                $goibibo_xml =$xml;
                $url = "https://partners-connect.goibibo.com/api/chm/v3/ari";
                $headers = array(
                          "Content-Type: application/xml",
                          "channel-token:".$channel_token,
                          "bearer-token:".$bearer_token
                        );
                $response=$this->getdata_curlreq->cUrlCall($url,$headers,$goibibo_xml);//for curl call
                $resultXml=simplexml_load_string($response);
                if($resultXml=='OAuth Authorization Required'){
                    DB::table('log_table')->where('id', $logModel->id)
                        ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
                        $rlt=array('status'=>0,'ota_name'=>'goibibo','response_msg'=>$resultXml);

                }
                if($resultXml){
                    $array_data = json_decode(json_encode($resultXml), true);
                    if(!isset($array_data['Error'])){
                        DB::table('log_table')->where('id',  $blocklog->id)
                        ->update(['status' => 1,'request_msg'=>$goibibo_xml,'request_url'=>$url,'response_msg'=> $response]);
                        $rlt=array('status' => 1,'ota_name'=>'goibibo','response_msg'=> ' blocked successfully');
                    }
                    else
                    {
                        DB::table('log_table')->where('id', $blocklog->id)
                        ->update(['status' => 0,'request_msg'=>$goibibo_xml,'request_url'=>$url,'response_msg'=>$response]);
                        $rlt=array('status' => 0,'ota_name'=>'goibibo','response_msg'=> $response);
                    }
                }
                else{
                    $rlt=array('status' => 0,'ota_name'=>'goibibo','response_msg'=> $response);
                }
            }
            else{
                $log_data                 		= [
                    "action_id"          => 3,
                    "hotel_id"           => $hotel_id,
                    "ota_id"      		   => $ota_id,
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
                $rlt[]=array('status' => 0,'ota_name'=>'goibibo','response_msg'=> 'Roomtype should be Sync');
            }
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
        $inventoryId                    = array();
        $flag                           = '';
        $xml                            = '';
        $index_id                       = 1;
        $rlt=array();
        $xml='<?xml version="1.0" encoding="UTF-8" ?>
        <AvailRateUpdateRQ hotelCode="'.$ota_hotel_code.'" timeStamp="">';
        foreach($inventory as $invs)
        {
            $ota_room_type = DB::table('cm_ota_room_type_synchronize')
            ->where('hotel_id', '=', $hotel_id)
            ->where('room_type_id', '=', $invs['room_type_id'])
            ->where('ota_type_id', '=', $ota_id)
            ->value('ota_room_type');

            foreach($invs['inv'] as $inv)
            {
                $otainventory                   = new OtaInventory();
                $fmonth=explode('-',$inv['date']);//for removing extra o from month and remove this code after mobile app update
                if(strlen($fmonth[1]) == 3)
                {
                    $fmonth[1]=ltrim($fmonth[1],0);
                }
                $inv['date']=implode('-',$fmonth);
                $room_code = $cmOtaRoomTypeSynchronizeModel->getOtaRoomType($invs['room_type_id'],$ota_id);
                $startDate                  = date('Y-m-d',strtotime($inv['date']));
                $endDate                    = date('Y-m-d',strtotime($inv['date']));
                $inventory_los              = $inv['los'];
                $room_qtys                  = $inv['no_of_rooms'];

                $inv['room_type_id']       = $invs['room_type_id'];
                $inv['block_status']       = 0;
                $inv['channel']            = 'Goibibo';
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
                        if($room_qtys >= 0)
                        {
                            $flag = 1;
                            $xml.='
                            <AvailRateUpdate locatorID="'.$index_id.'">
                            <DateRange from="'.$startDate.'" to="'.$endDate.'"/>
                               <Availability code="'.$room_code.'" count="'.$room_qtys.'" closed="false" />
                               </AvailRateUpdate>';
                               $index_id ++;
                        }
                    }
                }
            }
        }
        $xml.='</AvailRateUpdateRQ>';
        if($flag == 1){
            $invId             = implode(',',$inventoryId);
            $log_data               = [
                "action_id"          => 4,
                "hotel_id"           => $hotel_id,
                "ota_id"      	     => $ota_id,
                "inventory_ref_id"   => $invId,
                "user_id"            => $bucket_data["bucket_user_id"],
                "request_msg"        => '',
                "response_msg"       => '',
                "request_url"        => '',
                "status"         => 2,
                "ip"         => $bucket_data["bucket_client_ip"],
                "comment"	=> "Processing for update "
                ];

            $goibibo_xml =$xml;
            $bearer_token = trim($auth_parameter->bearer_token);
            $channel_token = trim($auth_parameter->channel_token);
            $headers = array(
                      "Content-Type: application/xml",
                      "channel-token:".$channel_token,
                      "bearer-token:".$bearer_token
                    );
            $log_request_msg = $goibibo_xml;
            $logModel->fill($log_data)->save();//saving pre logdata
            $url = "https://partners-connect.goibibo.com/api/chm/v3/ari";
            $ota_rlt=$this->getdata_curlreq->cUrlCall($url,$headers,$goibibo_xml);//for curl call
            $resultXml=simplexml_load_string($ota_rlt);
            if($ota_rlt=='OAuth Authorization Required'){
                DB::table('log_table')->where('id', $logModel->id)
                    ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
                    $rlt=array('status'=>0,'ota_name'=>'goibibo','response_msg'=>$ota_rlt);

            }
            if($resultXml){
                $array_data = json_decode(json_encode($resultXml), true);
                if(!isset($array_data['Error'])){
                    DB::table('log_table')->where('id', $logModel->id)
                    ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
                    $rlt=array('status'=>1,'ota_name'=>'goibibo','response_msg'=>$ota_rlt);
                }
                else{
                    DB::table('log_table')->where('id', $logModel->id)
                    ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
                    $rlt=array('status'=>0,'ota_name'=>'goibibo','response_msg'=>$ota_rlt);
                }
            }
            else{
                $rlt=array('status'=>0,'ota_name'=>'goibibo','response_msg'=>$ota_rlt);
            }
        }
        else{
            $log_data               = [
                "action_id"          => 4,
                "hotel_id"           => $hotel_id,
                "ota_id"      	     => $ota_id,
                "inventory_ref_id"   => $bucket_data["bucket_inventory_table_id"],
                "user_id"            => $bucket_data["bucket_user_id"],
                "request_msg"        => '',
                "response_msg"       => '',
                "request_url"        => '',
                "status"         => 2,
                "ip"         => $bucket_data["bucket_client_ip"],
                "comment"	=> "Roomrate type is not mapped"
                ];
                $logModel->fill($log_data)->save();
                $rlt=array('status'=>0,'ota_name'=>'goibibo','response_msg'=>"Roomtype should be sync");
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
        $data['channel']                = 'Goibibo';
        $rlt=array();
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
            $startDate          = date('Y-m-d',strtotime($data['date_from']));
            $endDate            = date('Y-m-d',strtotime($data['date_to']));
            $inventory_los      = $data['los'];
            $room_qtys          = $data['no_of_rooms'];
            if(!empty($room_code))
            {
                $log_data               	= [
                    "action_id"          => 4,
                    "hotel_id"           => $hotel_id,
                    "ota_id"      		   => $ota_id,
                    "inventory_ref_id"   => $invtefid ,
                    "user_id"            => $bucket_data["bucket_user_id"],
                    "request_msg"        => '',
                    "response_msg"       => '',
                    "request_url"        => '',
                    "status"         	 => 2,
                    "ip"         		 => $bucket_data["bucket_client_ip"],
                    "comment"			 => "Processing for update "
                    ];

                $goibibo_xml ='<?xml version="1.0" encoding="UTF-8" ?>
                        <AvailRateUpdateRQ hotelCode="'.$ota_hotel_code.'" timeStamp="">
                        <AvailRateUpdate locatorID="1">
                            <DateRange from="'.$startDate.'" to="'.$endDate.'"/>
                            <Availability code="'.$room_code.'" count="'.$room_qtys.'" closed="false" />
                        </AvailRateUpdate>
                        </AvailRateUpdateRQ>';
                $log_request_msg = $goibibo_xml;
                $logModel->fill($log_data)->save();//saving pre logdata

                $bearer_token 					= trim($auth_parameter->bearer_token);
                $channel_token 					= trim($auth_parameter->channel_token);
                $headers = array(
                          "Content-Type: application/xml",
                          "channel-token:".$channel_token,
                          "bearer-token:".$bearer_token
                        );
                $url = "https://partners-connect.goibibo.com/api/chm/v3/ari";
                $ota_rlt=$this->getdata_curlreq->cUrlCall($url,$headers,$goibibo_xml);//for curl call
                $resultXml=simplexml_load_string($ota_rlt);
                if($ota_rlt=='OAuth Authorization Required'){
                    DB::table('log_table')->where('id', $logModel->id)
                        ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
                        $rlt=array('status'=>0,'ota_name'=>'goibibo','response_msg'=>$ota_rlt);
                        return $rlt;
                }
                if($resultXml){
                    $array_data = json_decode(json_encode($resultXml), true);
                    if(!isset($array_data['Error'])){
                        DB::table('log_table')->where('id', $logModel->id)
                        ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
                        $rlt=array('status'=>1,'ota_name'=>'goibibo','response_msg'=>$ota_rlt);
                    }
                    else{
                        DB::table('log_table')->where('id', $logModel->id)
                        ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
                        $rlt=array('status'=>0,'ota_name'=>'goibibo','response_msg'=>$ota_rlt);
                    }
                }
                else{
                    $rlt=array('status'=>0,'ota_name'=>'goibibo','response_msg'=>$ota_rlt);
                }
            }
            else{
                $log_data               	= [
                    "action_id"          => 4,
                    "hotel_id"           => $hotel_id,
                    "ota_id"      		   => $ota_id,
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
                    $rlt=array('status'=>0,'ota_name'=>'goibibo','response_msg'=>'Roomtype should be sync');
            }
            return $rlt;
        }
    }
    public function rateSyncUpdate($rate_bucket_data,$rates_data,$auth_parameter,$commonUrl,$from_date,$to_date)
    {
        $cmOtaRatePlanSynchronizeModel 	= new CmOtaRatePlanSynchronize();
        $roomTypeModel                  = new MasterRoomType();
        $logModel                       = new RateUpdateLog();
        
        $xml_data                       = '';
        $multiple_days                  = '{"Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1","Sun":"1"}';
        $hotel_id                       = $rate_bucket_data['bucket_hotel_id'];
        $ota_id                         = $rate_bucket_data['bucket_ota_id'];
        $ota_hotel_code                 = $rate_bucket_data['bucket_ota_hotel_code'];
        $flag                           = '';
        $rlt                            =array();
        $count_rate                      = 0;
        $index_id                       = 1;

        $xml='<?xml version="1.0" encoding="UTF-8" ?>
        <AvailRateUpdateRQ hotelCode="'.$ota_hotel_code.'" timeStamp="">';
            foreach($rates_data['rates'] as $rates){
                $otaRatePlan                    = new OtaRatePlan();
                $fmonth=explode('-',$rates['date']);//for removing extra o from month and remove this code after mobile app update
                if(strlen($fmonth[1]) == 3){
                    $fmonth[1]=ltrim($fmonth[1],0);
                }
                $rates['date']=implode('-',$fmonth);
                $max_adult=$roomTypeModel->select('max_people')->where('room_type_id',$rates_data['room_type_id'])->first()->max_people;
                $rateplan_data               = [
                "rateplan_rate_plan_log_id"   => 0,
                "rateplan_hotel_id"           => $hotel_id,
                "rateplan_room_type_id"       => $rates_data['room_type_id'],
                "rateplan_rate_plan_id"       => $rates_data['rate_plan_id'],
                "rateplan_bar_price"          => $rates['bar_price'],
                "rateplan_multiple_occupancy" => json_encode($rates['multiple_occupancy']),
                "rateplan_date_from"          => $rates['date'],
                "rateplan_date_to"            => $rates['date'],
                "rateplan_client_ip"          => $rate_bucket_data['bucket_client_ip'],
                "rateplan_multiple_days"      => $multiple_days,
                "rateplan_los"                => 1,
                "extra_adult_price"           => $rates['extra_adult_price'],
                "extra_child_price"           => $rates['extra_child_price'],
                "max_adult"                   => $max_adult
                ];
                $rateplan_multiple_days_data = $this->getdata_curlreq->getDaysUpdate($rateplan_data['rateplan_multiple_days'],"Goibibo");
                $currency=$this->getdata_curlreq->getCurrency($hotel_id);

                $occupency=$this->getdata_curlreq->decideOccupencyPrice($max_adult,$rateplan_data['rateplan_bar_price'],$rates['multiple_occupancy']);
                $rateplan_single_price=0;
                $rateplan_double_price=0;
                $rateplan_triple_price=0;
                $rateplan_fourth_price=0;
                if($occupency){
                    $rateplan_single_price       = $occupency[0];
                    $rateplan_double_price       = $occupency[1];
                    $rateplan_triple_price       = isset($occupency[2])?$occupency[2]:0;
                    $rateplan_fourth_price       = isset($occupency[3])?$occupency[3]:0;
                }
                $result 					 = $cmOtaRatePlanSynchronizeModel->getOtaRoomIdFromRatePlanSynch($rateplan_data['rateplan_room_type_id'],$ota_id,$rateplan_data['rateplan_rate_plan_id']);
                if(isset($result[0])){
                    $rate_code                   = $result[0]['ota_rate_plan_id'];
                }
                else{
                    continue;
                }
                $startDate                   =  date('Y-m-d',strtotime($rateplan_data['rateplan_date_from']));
                $endDate                     =  date('Y-m-d',strtotime($rateplan_data['rateplan_date_to']));
                $rates['room_type_id']          = $rates_data['room_type_id'];
                $rates['rate_plan_id']          = $rates_data['rate_plan_id'];
                $rates['from_date']             = date('Y-m-d',strtotime($rateplan_data['rateplan_date_from']));
                $rates['to_date']               = date('Y-m-d',strtotime($rateplan_data['rateplan_date_from']));
                $rates['block_status']          = 0;
                $rates['los']                   = 1;
                $rates['multiple_days']         = $multiple_days;
                $rates['client_ip']             = $rate_bucket_data['bucket_client_ip'];
                $rates['user_id']               = $rate_bucket_data['bucket_user_id'];
                $rates['hotel_id']              = $hotel_id;
                $rates['channel']               = 'Goibibo';
                $rates['multiple_occupancy']    = json_encode($rates['multiple_occupancy']);
                try{
                    $otaRatePlan->fill($rates)->save();
                }
                catch(Exception $e){

                }
                if(!empty($rate_code)){

                    $flag=1;
                    $xml.= ' <AvailRateUpdate locatorID="'.$index_id.'"><DateRange from="'.$startDate.'" to="'.$endDate.'"/>
                    <Rate currencyCode="'.$currency.'" code="'.$rate_code.'" rateType="b2c">
                       <PerOccupancyRates>';
                         if($rateplan_single_price!=0){
                          $xml.= '<PerOccupancy occupancy="1" rate="'.$rateplan_single_price.'" />';
                        }
                        if($rateplan_double_price!=0){
                          $xml.= '<PerOccupancy occupancy="2" rate="'.$rateplan_double_price.'" />';
                        }
                        if($rateplan_triple_price!=0){
                          $xml.= '<PerOccupancy occupancy="3" rate="'.$rateplan_triple_price.'" />';
                        }
                        if($rateplan_fourth_price!=0){
                            $xml.= '<PerOccupancy occupancy="4" rate="'.$rateplan_fourth_price.'" />';
                        }
                       $xml.= '</PerOccupancyRates>
                    </Rate>
                    </AvailRateUpdate>';
                    $index_id ++;
                }
            }
            $xml.= '</AvailRateUpdateRQ>';
            if($flag == 1){
                $bearer_token 					= trim($auth_parameter->bearer_token);
                $channel_token 					= trim($auth_parameter->channel_token);
                $headers = array(
                          "Content-Type: application/xml",
                          "channel-token:".$channel_token,
                          "bearer-token:".$bearer_token
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
                $goibibo_rate_xml =$xml;
                $log_request_msg = $goibibo_rate_xml;
                $url = "https://partners-connect.goibibo.com/api/chm/v3/ari";
                $logModel->fill($log_data)->save();
                $ota_rlt=$this->getdata_curlreq->cUrlCall($url,$headers,$goibibo_rate_xml);//for curl call
                $resultXml=simplexml_load_string($ota_rlt);
                if($ota_rlt=='OAuth Authorization Required'){
                    DB::table('rate_update_logs')->where('id', $logModel->id)
                        ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
                        $rlt=array('status'=>0,'ota_name'=>'goibibo','response_msg'=>$ota_rlt);

                }
                if($resultXml){
                    $array_data = json_decode(json_encode($resultXml), true);
                    if(!isset($array_data['Error'])){
                        DB::table('rate_update_logs')->where('id', $logModel->id)
                        ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
                        $rlt=array('status'=>1,'ota_name'=>'goibibo','response_msg'=>$ota_rlt);
                    }
                    else
                    {
                        DB::table('rate_update_logs')->where('id', $logModel->id)
                        ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
                        $rlt=array('status'=>0,'ota_name'=>'goibibo','response_msg'=>$ota_rlt);
                    }
                }
                else{
                    $rlt=array('status'=>0,'ota_name'=>'goibibo','response_msg'=>$ota_rlt);
                }
            }
            else{
                $log_data               	= [
                    "action_id"          => 2,
                    "hotel_id"           => $hotel_id,
                    "ota_id"      		   => $ota_id,
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
                $rlt=array('status'=>0,'ota_name'=>'goibibo','response_msg'=>"Rateplan should be sync");
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
        $flag                           = '';
        $index_id                       = 1;
        $rlt=array();
        $xml='<?xml version="1.0" encoding="UTF-8" ?>
        <AvailRateUpdateRQ hotelCode="'.$ota_hotel_code.'" timeStamp="">';
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
                $rateplan_data               = [
                "rateplan_rate_plan_log_id"   => 0,
                "rateplan_hotel_id"           => $hotel_id,
                "rateplan_room_type_id"       => $rate['room_type_id'],
                "rateplan_rate_plan_id"       => $rate['rate_plan_id'],
                "rateplan_bar_price"          => $rates['bar_price'],
                "rateplan_multiple_occupancy" => $rates['multiple_occupancy'],
                "rateplan_date_from"          => $rates['date'],
                "rateplan_date_to"            => $rates['date'],
                "rateplan_client_ip"          => $rate_bucket_data['bucket_client_ip'],
                "rateplan_multiple_days"      => $multiple_days,
                "rateplan_los"                => 1,
                "extra_adult_price"           => 0,
                "extra_child_price"           => 0,
                "max_adult"                   => $max_adult
                ];

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
                $rates['channel']               = 'Goibibo';
                $min_max_status =   $this->getdata_curlreq->checkMinMaxPrice($rates['room_type_id'],$rates['rate_plan_id'],$rates['bar_price'],$rates['multiple_occupancy'],$rates['hotel_id'],$rates['date'],$rates['channel']);
                if($min_max_status){
                    $rlt = $min_max_status;
                    continue;
                }

                $rateplan_multiple_days_data = $this->getdata_curlreq->getDaysUpdate($rateplan_data['rateplan_multiple_days'],"Goibibo");
                $currency=$this->getdata_curlreq->getCurrency($hotel_id);

                $occupency=$this->getdata_curlreq->decideOccupencyPrice($max_adult,$rateplan_data['rateplan_bar_price'],$rates['multiple_occupancy']);

                $rateplan_single_price=0;
                $rateplan_double_price=0;
                $rateplan_triple_price=0;
                $rateplan_fourth_price=0;
                if($occupency)
                {
                    $rateplan_single_price       = $occupency[0];
                    $rateplan_double_price       = $occupency[1];
                    $rateplan_triple_price       = isset($occupency[2])?$occupency[2]:0;
                    $rateplan_fourth_price       = isset($occupency[3])?$occupency[3]:0;
                $result 					 = $cmOtaRatePlanSynchronizeModel->getOtaRoomIdFromRatePlanSynch($rateplan_data['rateplan_room_type_id'],$ota_id,$rateplan_data['rateplan_rate_plan_id']);
                if(isset($result[0])){
                    $rate_code                   = $result[0]['ota_rate_plan_id'];
                }
                else{
                    continue;
                }
                $startDate                   =  date('Y-m-d',strtotime($rateplan_data['rateplan_date_from']));
                $endDate                     =  date('Y-m-d',strtotime($rateplan_data['rateplan_date_to']));
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
                    if(!empty($rate_code))
                    {
                        $flag=1;
                        $xml.= '<AvailRateUpdate locatorID="'.$index_id.'"><DateRange from="'.$startDate.'" to="'.$endDate.'"/>
                        <Rate currencyCode="'.$currency.'" code="'.$rate_code.'" rateType="b2c">
                           <PerOccupancyRates>';
                             if($rateplan_single_price!=0){
                              $xml.= '<PerOccupancy occupancy="1" rate="'.$rateplan_single_price.'" />';
                            }
                            if($rateplan_double_price!=0){
                              $xml.= '<PerOccupancy occupancy="2" rate="'.$rateplan_double_price.'" />';
                            }
                            if($rateplan_triple_price!=0){
                              $xml.= '<PerOccupancy occupancy="3" rate="'.$rateplan_triple_price.'" />';
                            }
                            if($rateplan_fourth_price!=0){
                                $xml.= '<PerOccupancy occupancy="4" rate="'.$rateplan_fourth_price.'" />';
                              }
                           $xml.= '</PerOccupancyRates>
                             <Restrictions closed="false"/>
                        </Rate></AvailRateUpdate>
                        ';
                        $index_id ++;
                    }
                }
                else{
                    $rateplanId=0;
                }
            }
        }
    }
        $xml.= '</AvailRateUpdateRQ>';
        if($flag == 1){
          $bearer_token 					= trim($auth_parameter->bearer_token);
          $channel_token 					= trim($auth_parameter->channel_token);
          $headers = array(
                    "Content-Type: application/xml",
                    "channel-token:".$channel_token,
                    "bearer-token:".$bearer_token
                  );
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
          $goibibo_rate_xml =$xml;
          $log_request_msg = $goibibo_rate_xml;
          $url = "https://partners-connect.goibibo.com/api/chm/v3/ari";
          $logModel->fill($log_data)->save();
          $ota_rlt=$this->getdata_curlreq->cUrlCall($url,$headers,$goibibo_rate_xml);//for curl call
          $resultXml=simplexml_load_string($ota_rlt);
          if($ota_rlt=='OAuth Authorization Required'){
              DB::table('rate_update_logs')->where('id', $logModel->id)
                  ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
                  $rlt=array('status'=>0,'ota_name'=>'goibibo','response_msg'=>$ota_rlt);
          }
          if($resultXml){
              $array_data = json_decode(json_encode($resultXml), true);
              if(!isset($array_data['Error'])){
                  DB::table('rate_update_logs')->where('id', $logModel->id)
                  ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
                  $rlt=array('status'=>1,'ota_name'=>'goibibo','response_msg'=>$ota_rlt);
              }
              else
              {
                  DB::table('rate_update_logs')->where('id', $logModel->id)
                  ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
                  $rlt=array('status'=>0,'ota_name'=>'goibibo','response_msg'=>$ota_rlt);
              }
          }
          else{
              $rlt=array('status'=>0,'ota_name'=>'goibibo','response_msg'=>$ota_rlt);
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
            $rlt=array('status'=>0,'ota_name'=>'goibibo','response_msg'=>"Rateplan should be sync");
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
        $hotel_id                       = $rate_bucket_data['bucket_hotel_id'];
        $ota_id                         = $rate_bucket_data['bucket_ota_id'];
        $ota_hotel_code                 = $rate_bucket_data['bucket_ota_hotel_code'];
        $max_adult=$roomTypeModel->select('max_people')->where('room_type_id',$data['room_type_id'])->first()->max_people;
        $rlt=array();
        if(!$data['extra_adult_price']){
            $data['extra_adult_price'] = 0;
        }
        if(!$data['extra_child_price']){
            $data['extra_child_price'] = 0;
        }
        $rateplan_data               = [
        "rateplan_rate_plan_log_id"   => 0,
        "rateplan_hotel_id"           => $hotel_id,
        "rateplan_room_type_id"       => $data['room_type_id'],
        "rateplan_rate_plan_id"       => $data['rate_plan_id'],
        "rateplan_bar_price"          => $data['bar_price'],
        "rateplan_multiple_occupancy" => json_encode($data['multiple_occupancy']),
        "rateplan_date_from"          => $data['from_date'],
        "rateplan_date_to"            => $data['to_date'],
        "rateplan_client_ip"          => $rate_bucket_data['bucket_client_ip'],
        "rateplan_multiple_days"      => json_encode($data['multiple_days']),
        "rateplan_los"                => 1,
        "extra_adult_price"           => $data['extra_adult_price'],
        "extra_child_price"           => $data['extra_child_price'],
        "max_adult"                   => $max_adult
        ];
        $data['multiple_days']       = json_encode($data['multiple_days']);
        $currency=$this->getdata_curlreq->getCurrency($hotel_id);
        $rateplan_multiple_days_data = $this->getdata_curlreq->getDaysUpdate($data['multiple_days'],"Goibibo");
        $occupency=$this->getdata_curlreq->decideOccupencyPrice($max_adult,$rateplan_data['rateplan_bar_price'],$data['multiple_occupancy']);
        $rateplan_single_price=0;
        $rateplan_double_price=0;
        $rateplan_triple_price=0;
        $rateplan_fourth_price=0;
        $extra_adult_price			 = $rateplan_data['extra_adult_price'];
        $extra_child_price			 = $rateplan_data['extra_child_price'];
        if($occupency)
        {
            $rateplan_single_price       = $occupency[0];
            $rateplan_double_price       = $occupency[1];
            $rateplan_triple_price       = isset($occupency[2])?$occupency[2]:0;
            $rateplan_fourth_price       = isset($occupency[3])?$occupency[3]:0;
        }
        $result 					 = $cmOtaRatePlanSynchronizeModel->getOtaRoomIdFromRatePlanSynch($rateplan_data['rateplan_room_type_id'],$ota_id,$rateplan_data['rateplan_rate_plan_id']);
        if(isset($result[0])){
            $rate_code                   = $result[0]['ota_rate_plan_id'];
        }
        else{
            $rlt=array('status'=>0,'ota_name'=>'goibibo','response_msg'=>"Room type & Rateplan should be sync");
            return $rlt;
        }
        $startDate                   = date('Y-m-d',strtotime($rateplan_data['rateplan_date_from']));
        $endDate                     = date('Y-m-d',strtotime($rateplan_data['rateplan_date_to']));

        $data['multiple_occupancy']  = json_encode($data['multiple_occupancy']);
        $data['channel']             = 'Goibibo';
        $xml_data='';

        $data['from_date']           = date('Y-m-d',strtotime($data['from_date']));
        $data['to_date']             = date('Y-m-d',strtotime($data['to_date']));
        $data['user_id']             = $rate_bucket_data["bucket_user_id"];
        $data['client_ip']           = $rate_bucket_data["bucket_client_ip"];
        $success_flag=1;
                try{
                    $otaRatePlan->fill($data)->save();
                }
                catch(\Exception $e){
                   $success_flag=0;
                }
        if($success_flag)
        {
            $multiple_days = json_decode($data['multiple_days']);
            $rateplanId   = $otaRatePlan->rate_plan_log_id;
            if(!empty($rate_code))
            {

                $flag=1;
                $xml= '<?xml version="1.0" encoding="UTF-8" ?>
                        <AvailRateUpdateRQ hotelCode="'.$ota_hotel_code.'" timeStamp="">
                          <AvailRateUpdate locatorID="1">
                            <DateRange from="'.$startDate.'" to="'.$endDate.'" />
                              <Rate currencyCode="'.$currency.'" code="'.$rate_code.'" rateType="b2c">
                                 <PerOccupancyRates>';
                                   if($rateplan_single_price!=0){
                                    $xml.= '<PerOccupancy occupancy="1" rate="'.$rateplan_single_price.'" />';
                                  }
                                  if($rateplan_double_price!=0){
                                    $xml.= '<PerOccupancy occupancy="2" rate="'.$rateplan_double_price.'" />';
                                  }
                                  if($rateplan_triple_price!=0){
                                    $xml.= '<PerOccupancy occupancy="3" rate="'.$rateplan_triple_price.'" />';
                                  }
                                  if($rateplan_fourth_price!=0){
                                    $xml.= '<PerOccupancy occupancy="4" rate="'.$rateplan_fourth_price.'" />';
                                  }
                         $xml.= '</PerOccupancyRates>
                                <Restrictions closed="false"/>
                              </Rate>
                        </AvailRateUpdate>
                    </AvailRateUpdateRQ>';
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
            $bearer_token 					= trim($auth_parameter->bearer_token);
            $channel_token 					= trim($auth_parameter->channel_token);
            $headers = array(
                      "Content-Type: application/xml",
                      "channel-token:".$channel_token,
                      "bearer-token:".$bearer_token
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
            $goibibo_rate_xml =  $xml_data;

            $log_request_msg = $goibibo_rate_xml;
            $url = "https://partners-connect.goibibo.com/api/chm/v3/ari";
            $logModel->fill($log_data)->save();
            $ota_rlt=$this->getdata_curlreq->cUrlCall($url,$headers,$goibibo_rate_xml);//for curl call
            $resultXml=simplexml_load_string($ota_rlt);
            if($ota_rlt=='OAuth Authorization Required'){
                DB::table('rate_update_logs')->where('id', $logModel->id)
                    ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
                    $rlt=array('status'=>0,'ota_name'=>'goibibo','response_msg'=>$ota_rlt);
                    return $rlt;
            }
            if($resultXml){
                $array_data = json_decode(json_encode($resultXml), true);
                if(!isset($array_data['Error'])){
                    DB::table('rate_update_logs')->where('id', $logModel->id)
                    ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
                    $rlt=array('status'=>1,'ota_name'=>'goibibo','response_msg'=>$ota_rlt);
                }
                else
                {
                    DB::table('rate_update_logs')->where('id', $logModel->id)
                    ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
                    $rlt=array('status'=>0,'ota_name'=>'goibibo','response_msg'=>$ota_rlt);
                }
            }
            else{
                $rlt=array('status'=>0,'ota_name'=>'goibibo','response_msg'=>$ota_rlt);
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
            $rlt=array('status'=>0,'ota_name'=>'goibibo','response_msg'=>"Rateplan should be sync");
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
            $cond = array('hotel_id'=>$data['hotel_id'],'room_type_id'=>$rooms,'rate_plan_id'=>$data['rate_plan_id'],'channel'=>'goibibo');
            $getRateDetails = OtaRatePlan::select('*')
                                ->where($cond)->where('from_date','<=',$data['date_from'])
                                ->where('to_date','>=',$data['date_to'])
                                ->orderBy('rate_plan_log_id','DESC')
                                ->first();
                $rate_data = [
                    'hotel_id'          => $getRateDetails->hotel_id,
                    'channel'           => 'Goibibo',
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
                $rateplan_multiple_days_data = $this->getdata_curlreq->getDaysUpdate($multiple_days,"Goibibo");
                $rateplan_single_price=0;
                $rateplan_double_price=0;
                $rateplan_triple_price=0;
                $rateplan_fourth_price=0;
                if($occupency)
                {
                    $rateplan_single_price       = $occupency[0];
                    $rateplan_double_price       = $occupency[1];
                    $rateplan_triple_price       = isset($occupency[2])?$occupency[2]:0;
                    $rateplan_fourth_price       = isset($occupency[3])?$occupency[3]:0;
                }
                $result = $cmOtaRatePlanSynchronizeModel->getOtaRoomIdFromRatePlanSynch($rooms,$ota_id,$data['rate_plan_id']);
                if(isset($result[0])){
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
                    $rateplanId = implode(',',$rateplanId);
                    if(!empty($rate_code)){

                      $xml= '<?xml version="1.0" encoding="UTF-8" ?>
                              <AvailRateUpdateRQ hotelCode="'.$ota_hotel_code.'" timeStamp="">
                                <AvailRateUpdate locatorID="1">
                                  <DateRange from="'.$startDate.'" to="'.$endDate.'"/>
                                    <Rate currencyCode="'.$currency.'" code="'.$rate_code.'" rateType="b2c">
                                       <PerOccupancyRates>';
                                         if($rateplan_single_price!=0){
                                          $xml.= '<PerOccupancy occupancy="1" rate="'.$rateplan_single_price.'" />';
                                        }
                                        if($rateplan_double_price!=0){
                                          $xml.= '<PerOccupancy occupancy="2" rate="'.$rateplan_double_price.'" />';
                                        }
                                        if($rateplan_triple_price!=0){
                                          $xml.= '<PerOccupancy occupancy="3" rate="'.$rateplan_triple_price.'" />';
                                        }
                                        if($rateplan_fourth_price!=0){
                                            $xml.= '<PerOccupancy occupancy="4" rate="'.$rateplan_fourth_price.'" />';
                                          }
                               $xml.= '</PerOccupancyRates>
                                      <Restrictions closed="true"/>
                                    </Rate>
                              </AvailRateUpdate>
                          </AvailRateUpdateRQ>';

                        $bearer_token = trim($auth_parameter->bearer_token);
                        $channel_token = trim($auth_parameter->channel_token);
                        $headers = array(
                                  "Content-Type: application/xml",
                                  "channel-token:".$channel_token,
                                  "bearer-token:".$bearer_token
                                );
                        $log_data               = [
                            "action_id"          => 2,
                            "hotel_id"           => $hotel_id,
                            "ota_id"      	     => $ota_id,
                            "rate_ref_id"        => $rateplanId,
                            "user_id"            => $rate_bucket_data['bucket_user_id'],
                            "request_msg"        => '',
                            "response_msg"       => '',
                            "request_url"        => '',
                            "status"         => 2,
                            "ip"         => $rate_bucket_data['bucket_client_ip'],
                            "comment"	=> "Processing for update "
                            ];
                        $goibibo_rate_xml = $xml;
                        $log_request_msg = $goibibo_rate_xml;
                        $url = "https://partners-connect.goibibo.com/api/chm/v3/ari";
                        $logModel->fill($log_data)->save();
                        $ota_rlt=$this->getdata_curlreq->cUrlCall($url,$headers,$goibibo_rate_xml);//for curl call
                        $resultXml=simplexml_load_string($ota_rlt);
                        if($ota_rlt=='OAuth Authorization Required'){
                            DB::table('log_table')->where('id', $logModel->id)
                                ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
                                $rlt=array('status'=>0,'ota_name'=>'goibibo','response_msg'=>$ota_rlt);

                        }
                        if($resultXml){
                            $array_data = json_decode(json_encode($resultXml), true);
                            if(!isset($array_data['Error'])){
                                DB::table('log_table')->where('id', $logModel->id)
                                ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
                                $rlt=array('status'=>1,'ota_name'=>'goibibo','response_msg'=>$ota_rlt);
                            }
                            else
                            {
                                DB::table('log_table')->where('id', $logModel->id)
                                ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
                                $rlt=array('status'=>0,'ota_name'=>'goibibo','response_msg'=>$ota_rlt);
                            }
                        }
                        else{
                            $rlt=array('status'=>0,'ota_name'=>'goibibo','response_msg'=>$ota_rlt);
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
                            "status"         => 2,
                            "ip"         => $rate_bucket_data['bucket_client_ip'],
                            "comment"	=> "Roomrate type is not mapped"
                            ];
                        $logModel->fill($log_data)->save();
                        $rlt=array('status'=>0,'ota_name'=>'goibibo','response_msg'=>"Rateplan should be sync");
                    }
                }
        if(empty($rlt)){
            $rlt=array('status'=>0,'ota_name'=>'goibibo','response_msg'=>"Rateplan should be mapped");
        }
        return $rlt;
    }
    public function testOtaUpdate(){
        $url = "https://services.expediapartnercentral.com/eqc/ar";
        $headers = array (
            //Regulates versioning of the XML interface for the API
            'Content-Type: application/xml'
            );
        $xml = '<AvailRateUpdateRQ xmlns="http://www.expediaconnect.com/EQC/AR/2011/06">
        <Authentication username="EQC_Bookingjini" password="Bookingjini@March2020" />
        <Hotel id="36929220" />
        <AvailRateUpdate>
            <DateRange from="2021-02-26" to="2021-02-26" />
            <RoomType id="218153341">
                <Inventory totalInventoryAvailable="10" />
            </RoomType>
        </AvailRateUpdate>
    </AvailRateUpdateRQ>';
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml);
        $ota_rlt = curl_exec($ch);
        curl_close($ch);
        return $ota_rlt;
    }
    public function promotionalApiGoibibo(){
        $url = "https://ppin.goibibo.com/api/chm/v1/offer";
        $data = '<?xml version="1.0" encoding="UTF-8"?>
                <Website Name="ingoibibo" HotelCode="1000103321">
                <OfferName>Test offer</OfferName>
                <OfferCategory>earlybird</OfferCategory>
                <NonRefundable>true</NonRefundable>
                <Restrictions>
                    <BookingDate>
                        <Start>2021-07-01</Start>
                        <End>2021-07-30</End>
                    </BookingDate>
                    <StayDate>
                        <Start>2021-07-02</Start>
                        <End>2021-07-15</End>
                    </StayDate>
                    <StayBlackoutRanges>
                        <Range>
                            <Start>2021-07-03</Start>
                            <End>2021-07-04</End>
                        </Range>
                        <Range>
                            <Start>2021-07-10</Start>
                            <End>2021-07-10</End>
                        </Range>
                    </StayBlackoutRanges>
                    <NotApplicableStayWeekday>
                        <Day>Mon</Day>
                        <Day>Fri</Day>
                    </NotApplicableStayWeekday>
                    <MinimumNights>3</MinimumNights>
                    <MinCutoff>2</MinCutoff>
                    <MaxCutoff>4</MaxCutoff>
                    <PayAtHotel>false</PayAtHotel>
                </Restrictions>
                <ApplicableToList>
                    <ApplicableTo>
                        <Type>Hotel</Type>
                        <Code>1000103321</Code>
                    </ApplicableTo>
                </ApplicableToList>
                <OfferCondition>all</OfferCondition>
                <OfferValueList>
                    <OfferValueObject>
                        <OfferBasis>discount</OfferBasis>
                        <OfferValue>38</OfferValue>
                        <OfferType>percentage</OfferType>
                        <Segment>all</Segment>
                    </OfferValueObject>
                    <OfferValueObject>
                        <OfferBasis>discount</OfferBasis>
                        <OfferValue>45</OfferValue>
                        <OfferType>percentage</OfferType>
                        <Segment>loggedin</Segment>
                    </OfferValueObject>
                </OfferValueList>
                </Website>';
        $headers = array (
            'Content-Type: application/xml',
            "channel-token:95de1f96be",
            "bearer-token:fc3b8034b4"
            );
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $data);
        $ota_rlt = curl_exec($ch);
        curl_close($ch);
        return $ota_rlt;
    }

    public function createOffer(Request $request){

        $data = $request->all();
        $hotel_code = $data['hotel_code'];
        $offer_name = $data['offer_name'];
        $offer_category = $data['offer_category'];
        $refundable = $data['refundable'];
        $booking_date = ($data['book_date']) ? $data['book_date'] : '';
        $stay_date = ($data['stay_date']) ? $data['stay_date'] : '';
        $stay_backout_date = ($data['stay_backout']) ? $booking_date : '';
        $not_applicable_days = ($data['not_applicable_days']) ? $data['not_applicable_days'] : '';
        $minimum_nights = $data['minimum_nights'];
        $offer_value = $data['offer_value'];
         //Checking the ota hotel is present in our system
        // $ota_hotel_details  = CmOtaDetails::where('ota_hotel_code' ,'=', $hotel_code )->first();
        // if(!$ota_hotel_details){
        //     return '<?xml version="1.0" encoding="UTF-8"
        //     <Error>This hotel is not with us!</Error>';
        // }
        //Checking the auth parameter
        // $auth_parameter     = json_decode($ota_hotel_details->auth_parameter);
        // if($auth_parameter){
        //     $bearer_token       = trim($auth_parameter->bearer_token);
        //     $channel_token      = trim($auth_parameter->channel_token);
        // }else
        //     return '<?xml version="1.0" encoding="UTF-8"
        //     <Error>This hotel is not with us!</Error>'; 
        // }

        $bearer_token       = 'fc3b8034b4';
        $channel_token      = '95de1f96be';
      
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
                <Website Name="ingoibibo" HotelCode="'.$hotel_code.'">
                <OfferName>'.$offer_name.'</OfferName>
                <OfferCategory>'.$offer_category.'</OfferCategory>
                <NonRefundable>'.$refundable.'</NonRefundable>
                <Restrictions>';
                    if($booking_date){
                        $xml .= '<BookingDate>
                                    <Start>'.$booking_date['start'].'</Start>
                                    <End>'.$booking_date['end'].'</End>
                                </BookingDate>';
                    }
                    if($stay_date){
                        $xml .= '<StayDate>
                                    <Start>'.$stay_date['start'].'</Start>
                                    <End>'.$stay_date['end'].'</End>
                                </StayDate>';
                    }
                    if($stay_backout_date){
                        $xml .= '<StayDate>
                                    <Start>'.$stay_backout_date['start'].'</Start>
                                    <End>'.$stay_backout_date['end'].'</End>
                                </StayDate>';
                    }
                    if($not_applicable_days){
                        $xml .=  '<NotApplicableStayWeekday>';
                        for($i=0;$i<sizeof($not_applicable_days);$i++){
                            $xml .= '<Day>'.$not_applicable_days[$i].'</Day>';
                        }
                        $xml .=  '</NotApplicableStayWeekday>';
                    }
                    if($minimum_nights != ''){
                        $xml .= '<MinimumNights>'.$minimum_nights.'</MinimumNights>
                                <MinCutoff>2</MinCutoff>
                                <MaxCutoff>4</MaxCutoff>
                                <PayAtHotel>True</PayAtHotel>';
                    }else{
                        $xml .= '<MinimumNights>1</MinimumNights>
                                <MinCutoff>2</MinCutoff>
                                <MaxCutoff>4</MaxCutoff>
                                <PayAtHotel>True</PayAtHotel>';
                    }
                    $xml .= '</Restrictions>
                            <ApplicableToList>
                                <ApplicableTo>
                                    <Type>Hotel</Type>
                                    <Code>'.$hotel_code.'</Code>
                                </ApplicableTo>
                            </ApplicableToList>
                            <OfferCondition>all</OfferCondition>
                            <OfferValueList>
                                <OfferValueObject>
                                    <OfferValue>'.$offer_value.'</OfferValue>
                                    <OfferType>percentage</OfferType>
                                    <Segment>all</Segment>
                                </OfferValueObject>
                            </OfferValueList></Website>';
                           //dd($xml);
        $url = 'https://ppin.goibibo.com/api/chm/v1/offer';
        $headers = array ('Content-Type: application/xml',"channel-token:".$channel_token,"bearer-token:".$bearer_token);
        //cURL request
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml);
        $rlt = curl_exec($ch);
        dd($rlt);
        curl_close($ch);
        $array_data = json_decode(json_encode(simplexml_load_string($rlt)), true);
        $res=array('array_data'=>$array_data,'rlt'=>$rlt);
        return $res;
    }
}
