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
 * This controller is used for Expedia single,bulk,sync and block of inv and rate
 * @auther ranjit
 * created date 05/03/19.
 * modification due to display problem @ 12/03/19 by ranjit(new model added)
 */
class ExpediaInvRateController extends Controller
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
        $xml_all_data                   = '';
        $hotel_id                       = $bucket_data["bucket_hotel_id"];
        $ota_id                         = $bucket_data["bucket_ota_id"];
        $ota_hotel_code                 = $bucket_data["bucket_ota_hotel_code"];
        $flag                           = 0;
        $count                          = 0;
        $rlt                            =array();

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
                $room_code 					= $cmOtaRoomTypeSynchronizeModel->getOtaRoomType($inventory['room_type_id'],$ota_id);
                $startDate                  = $inv['date'];
                $endDate                    = $inv['date'];
                $inventory_los              = $inv['los'];
                $room_qtys                  = $inv['no_of_rooms'];
                $inv['room_type_id']       = $inventory['room_type_id'];
                $inv['block_status']       = $inv["block_status"];
                $inv['channel']            = 'Expedia';
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
                    $flag=1;
                    if($inv['block_status']==0)
                    {
                        if($room_qtys >= 0)
                        {
                            $xml='<AvailRateUpdate>
                            <DateRange from="'.$startDate.'" to="'.$endDate.'"/>
                            <RoomType id="'.$room_code.'" closed="false">
                            <Inventory totalInventoryAvailable="'.$room_qtys.'"/>
                            </RoomType>
                            </AvailRateUpdate>';
                            $xml_data.= $xml;
                        }
                    }
                    else
                    {
                        $xml=' <AvailRateUpdate>
                        <DateRange from="'.$startDate.'" to="'.$endDate.'"/>
                        <RoomType id="'.$room_code.'" closed="true">
                        </RoomType>
                        </AvailRateUpdate>';
                        $xml_data.= $xml;
                    }
                }
                else{
                    $flag=0;
                }
            }
            $count = $count+$flag;
            $xml_all_data.= $xml_data;
        if($count >= 1)
        {
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
            $username 						= trim($auth_parameter->username);
            $password 						= trim($auth_parameter->password);
            $url  = $commonUrl.'eqc/ar';

            $expedia_xml ='<?xml version="1.0" encoding="UTF-8"?>
			<AvailRateUpdateRQ xmlns="http://www.expediaconnect.com/EQC/AR/2011/06">
			<Authentication username="'.$username.'" password="'.$password.'"/>
			<Hotel id="'.$ota_hotel_code.'"/>';
            $expedia_xml.=$xml_all_data.'</AvailRateUpdateRQ>';
            $headers = array ('Content-Type: application/xml');
            $log_request_msg = $expedia_xml;
            $logModel->fill($log_data)->save();//saving pre logdata
           
            $result=$this->getdata_curlreq->cUrlCall($url,$headers,$expedia_xml);//for curl call
            $resultXml=simplexml_load_string($result);
            if($resultXml){
                $array_data = json_decode(json_encode($resultXml), true);
                if(!isset($array_data['Error'])){
                    DB::table('log_table')->where('id', $logModel->id)
                    ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                    $rlt=array('status'=>1,'ota_name'=>'expedia','response_msg'=>'updated successfully');
                }
                else{
                    DB::table('log_table')->where('id', $logModel->id)
                    ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                    $rlt=array('status'=>0,'ota_name'=>'expedia','response_msg'=>$result);
                }
            }
            else{
                $rlt=array('status'=>0,'ota_name'=>'expedia','response_msg'=>$result);
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
                $rlt=array('status'=>0,'ota_name'=>'expedia','response_msg'=>"Roomtype should be sync");
            }
            if(sizeof($rlt)==0){
                $rlt=array('status'=>0,'ota_name'=>'expedia','response_msg'=>"Roomtype should be sync");
            }
        return $rlt;
    }
    public function blockInventoryUpdate($bucket_data,$room_type_id,$data,$auth_parameter,$commonUrl)
    {
        $blocklog                       = new LogTable();
        $hotel_id                       = $bucket_data["bucket_hotel_id"];
        $ota_id                         = $bucket_data["bucket_ota_id"];
        $ota_hotel_code                 = $bucket_data["bucket_ota_hotel_code"];
        $data['channel']                = 'Expedia';
        $inventoryId                    = array();
        $xml_data                       = '';
        $flag                           = 0;
        $count                          = 0;
        $rlt                            = array();
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
                $inventoryId[]   = $otainventory->inventory_id;
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
                    $flag = 1;
                    $xml=' <AvailRateUpdate>
                    <DateRange from="'.$startDate.'" to="'.$endDate.'"/>
                    <RoomType id="'.$room_code.'" closed="true">
                    </RoomType>
                    </AvailRateUpdate>';
                    $xml_data.=$xml;
                }
                else{
                    $flag = 0;
                }
            }
            $count=$count+$flag;
        if($count >= 1)
        {
            $username 						= trim($auth_parameter->username);
			$password 						= trim($auth_parameter->password);
            $invId                          = implode(',',$inventoryId);

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
            $expedia_xml ='<?xml version="1.0" encoding="UTF-8"?>
			<AvailRateUpdateRQ xmlns="http://www.expediaconnect.com/EQC/AR/2011/06">
			<Authentication username="'.$username.'" password="'.$password.'"/>
			<Hotel id="'.$ota_hotel_code.'"/>';
            $expedia_xml.=$xml_data.'</AvailRateUpdateRQ>';
            $log_request_msg=$expedia_xml;
            $url  = $commonUrl.'eqc/ar';
            $headers = array ('Content-Type: application/xml');
            $result=$this->getdata_curlreq->cUrlCall($url,$headers,$expedia_xml);//for curl call
            $resultXml=simplexml_load_string($result);
            if($resultXml){
                $array_data = json_decode(json_encode($resultXml), true);
                if(!isset($array_data['Error'])){
                    DB::table('log_table')->where('id', $blocklog->id)
                    ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                    $rlt=array('status'=>1,'ota_name'=>'expedia','response_msg'=>'updated successfully');
                }
                else{
                    DB::table('log_table')->where('id', $blocklog->id)
                    ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                    $rlt=array('status'=>0,'ota_name'=>'expedia','response_msg'=>$result);
                }
            }
            else{
                $rlt=array('status'=>0,'ota_name'=>'expedia','response_msg'=>$result);
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
            $rlt=array('status' => 0,'ota_name'=>'expedia','response'=> 'Roomtype should be Sync');
        }
        if(sizeof($rlt)==0){
            $rlt=array('status'=>0,'ota_name'=>'expedia','response_msg'=>"Roomtype should be sync");
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
                $inv['channel']            = 'Expedia';
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
                            $xml='<AvailRateUpdate>
                            <DateRange from="'.$startDate.'" to="'.$endDate.'"/>
                            <RoomType id="'.$room_code.'" closed="false">
                            <Inventory totalInventoryAvailable="'.$room_qtys.'"/>
                            </RoomType>
                            </AvailRateUpdate>';
                            $xml_data.= $xml;
                        }
                    }
                    else{
                        $flag=0;
                    }
                }
            }
            $count = $count+$flag;
            $xml_all_data.= $xml_data;
        }
        if($count >= 1)
        {
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
            $username 						= trim($auth_parameter->username);
            $password 						= trim($auth_parameter->password);
            $url  = $commonUrl.'eqc/ar';
            $expedia_xml ='<?xml version="1.0" encoding="UTF-8"?>
            <AvailRateUpdateRQ xmlns="http://www.expediaconnect.com/EQC/AR/2011/06">
            <Authentication username="'.$username.'" password="'.$password.'"/>
            <Hotel id="'.$ota_hotel_code.'"/>';
            $expedia_xml.=$xml_all_data.'</AvailRateUpdateRQ>';
            $headers = array ('Content-Type: application/xml');
            $log_request_msg = $expedia_xml;
            $logModel->fill($log_data)->save();//saving pre logdata

            $result=$this->getdata_curlreq->cUrlCall($url,$headers,$expedia_xml);//for curl call
            $resultXml=simplexml_load_string($result);
            if($resultXml){
                $array_data = json_decode(json_encode($resultXml), true);
                if(!isset($array_data['Error'])){
                    DB::table('log_table')->where('id', $logModel->id)
                    ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                    $rlt=array('status'=>1,'ota_name'=>'expedia','response_msg'=>'updated successfully');
                }
                else{
                    DB::table('log_table')->where('id', $logModel->id)
                    ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                    $rlt=array('status'=>0,'ota_name'=>'expedia','response_msg'=>$result);
                }
            }
            else{
                $rlt=array('status'=>0,'ota_name'=>'expedia','response_msg'=>$result);
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
                $rlt=array('status'=>0,'ota_name'=>'expedia','response_msg'=>"Roomtype should be sync");
        }
        if(sizeof($rlt)==0){
            $rlt=array('status'=>0,'ota_name'=>'expedia','response_msg'=>"Roomtype should be sync");
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
        $data['channel']                = 'Expedia';
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

                $username 					= trim($auth_parameter->username);
                $password 					= trim($auth_parameter->password);
                $url  = $commonUrl.'eqc/ar';

                $expedia_xml ='<?xml version="1.0" encoding="UTF-8"?>
                                    <AvailRateUpdateRQ xmlns="http://www.expediaconnect.com/EQC/AR/2011/06">
                                        <Authentication username="'.$username.'" password="'.$password.'"/>
                                            <Hotel id="'.$ota_hotel_code.'"/>
                                                <AvailRateUpdate>
                                                    <DateRange from="'.$startDate.'" to="'.$endDate.'"/>
                                                        <RoomType id="'.$room_code.'" closed="false">
                                                        <Inventory totalInventoryAvailable="'.$room_qtys.'"/>
                                                    </RoomType>
                                                </AvailRateUpdate>
                                </AvailRateUpdateRQ>';
                $headers = array ('Content-Type: application/xml');
                $log_request_msg = $expedia_xml;
                $logModel->fill($log_data)->save();//saving pre logdata

                $result=$this->getdata_curlreq->cUrlCall($url,$headers,$expedia_xml);//for curl call
                $resultXml=simplexml_load_string($result);
                if($resultXml){
					$array_data = json_decode(json_encode($resultXml), true);
					if(!isset($array_data['Error'])){
						DB::table('log_table')->where('id', $logModel->id)
						->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
						$rlt=array('status'=>1,'ota_name'=>'expedia','response_msg'=>'updated successfully');
					}
					else{
						DB::table('log_table')->where('id', $logModel->id)
						->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
						$rlt=array('status'=>0,'ota_name'=>'expedia','response_msg'=>$result);
					}
				}
				else{
					$rlt=array('status'=>0,'ota_name'=>'expedia','response_msg'=>$result);
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
                    $rlt=array('status'=>0,'ota_name'=>'expedia','response_msg'=>'Roomtype should be sync');
            }
            if(sizeof($rlt)==0){
                $rlt=array('status'=>0,'ota_name'=>'expedia','response_msg'=>"Roomtype should be sync");
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
        $rlt                            =array();
        $count_rate                      = 0;

            $xml_data       = '';
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

                $rateplan_multiple_days_data = $this->getdata_curlreq->getDaysUpdate($multiple_days,"Expedia");
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
                    $rateplan_triple_price       = isset($occupency[2])?$occupency[2]:0;
                    $rateplan_quadruple_price    = isset($occupency[3])?$occupency[3]:0;
                }

                $result 					 = $cmOtaRatePlanSynchronizeModel->getOtaRoomIdFromRatePlanSynch($rates['room_type_id'],$ota_id,$rates['rate_plan_id']);
                if(isset($result[0])){
                    $room_code                   = $result[0]['ota_room_type_id'];
                    $rate_code                   = $result[0]['ota_rate_plan_id'];
                }
                else{
                    continue;
                }
                $rateplan_los                = 1;
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
                $rates['channel']               = 'Expedia';
                $rates['multiple_occupancy']    = json_encode($rates['multiple_occupancy']);
                try{
                    $otaRatePlan->fill($rates)->save();   
                }
                catch(Exception $e){

                }
                if($rate_code)
                {
                    $flag=1;
                    $xml='<AvailRateUpdate>
                    <DateRange from="'.$startDate.'" to="'.$endDate.'" '.$rateplan_multiple_days_data.' />
                    <RoomType id="'.$room_code.'">
                    <RatePlan id="'.$rate_code.'" closed="false">
                    <Rate currency="'.$currency.'">';
                    if($rateplan_single_price!=0){
                    $xml .='<PerOccupancy rate="'.$rateplan_single_price.'" occupancy="1"/>';

                    }
                    if($rateplan_double_price!=0){
                    $xml .='<PerOccupancy rate="'.$rateplan_double_price.'" occupancy="2"/>';
                    }
                    if($rateplan_triple_price!=0){
                        $xml .='<PerOccupancy rate="'.$rateplan_double_price.'" occupancy="3"/>';
                    }
                    if($rateplan_quadruple_price!=0){
                        $xml .='<PerOccupancy rate="'.$rateplan_quadruple_price.'" occupancy="4"/>';
                    }
                    $xml .='</Rate>
                    <Restrictions minLOS="'.$rateplan_los.'" />

                    </RatePlan>
                    </RoomType>
                    </AvailRateUpdate>';
                    $xml_data.= $xml;
                }
                else{
                    $flag=0;
                }
            }
            $count  =   $count+$flag;
            $xml_all_data.= $xml_data;
        if($count>=1)
        {
            $headers = array (
                //Regulates versioning of the XML interface for the API
                'Content-Type: application/xml'
                );
            $username 						= trim($auth_parameter->username);
			$password 						= trim($auth_parameter->password);
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
            $expedia_rate_xml ='<?xml version="1.0" encoding="UTF-8"?>
			<AvailRateUpdateRQ xmlns="http://www.expediaconnect.com/EQC/AR/2011/06">
			<Authentication username="'.$username.'" password="'.$password.'"/>
			<Hotel id="'.$ota_hotel_code.'"/>';
            $expedia_rate_xml.= $xml_all_data.'</AvailRateUpdateRQ>';
            $log_request_msg = $expedia_rate_xml;
            $url  = $commonUrl.'eqc/ar';
            $logModel->fill($log_data)->save();
            $result=$this->getdata_curlreq->cUrlCall($url,$headers,$expedia_rate_xml);//for curl call
            $resultXml=simplexml_load_string($result);

            if($resultXml){
				$array_data = json_decode(json_encode($resultXml), true);
				if(!isset($array_data['Error'])){
				DB::table('rate_update_logs')->where('id', $logModel->id)
					->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
					$rlt=array('status'=>1,'ota_name'=>'expedia','response_msg'=>'updated successfully');
				}else{
					DB::table('rate_update_logs')->where('id', $logModel->id)
					->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
					$rlt=array('status'=>0,'ota_name'=>'expedia','response_msg'=>$result);
				}
			}
			else{
				$rlt=array('status'=>0,'ota_name'=>'expedia','response_msg'=>$result);
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
            $rlt=array('status'=>0,'ota_name'=>'expedia','response_msg'=>"Rateplan should be sync");
        }
        if(sizeof($rlt)==0){
            $rlt=array('status'=>0,'ota_name'=>'expedia','response_msg'=>"Rateplan should be sync");
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
                $rates['channel']               = 'Expedia';
                $min_max_status =   $this->getdata_curlreq->checkMinMaxPrice($rates['room_type_id'],$rates['rate_plan_id'],$rates['bar_price'],$rates['multiple_occupancy'],$rates['hotel_id'],$rates['date'],$rates['channel']);
                if($min_max_status){
                    $rlt = $min_max_status;
                    continue;
                }
                $rateplan_multiple_days_data = $this->getdata_curlreq->getDaysUpdate($rates['multiple_days'],"Expedia");
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
                    $rateplan_quadruple_price    = isset($occupency[3])?$occupency[3]:0;
                }
                $result 					 = $cmOtaRatePlanSynchronizeModel->getOtaRoomIdFromRatePlanSynch($rates['room_type_id'],$ota_id,$rates['rate_plan_id']);
                if(isset($result[0])){
                    $room_code                   = $result[0]['ota_room_type_id'];
                    $rate_code                   = $result[0]['ota_rate_plan_id'];
                }
                else{
                    continue;
                }
                $rateplan_los                = $rates['los'];
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
                    $rateplanId[]   = $otaRatePlan->rate_plan_log_id;
                    if($rate_code)
                    {
                        $flag=1;
                        $xml='<AvailRateUpdate>
                        <DateRange from="'.$startDate.'" to="'.$endDate.'" '.$rateplan_multiple_days_data.' />
                        <RoomType id="'.$room_code.'">
                        <RatePlan id="'.$rate_code.'" closed="false">
                        <Rate currency="'.$currency.'">';
                        if($rateplan_single_price!=0){
                        $xml .='<PerOccupancy rate="'.$rateplan_single_price.'" occupancy="1"/>';

                        }
                        if($rateplan_double_price!=0){
                        $xml .='<PerOccupancy rate="'.$rateplan_double_price.'" occupancy="2"/>';
                        }
                        if($rateplan_triple_price!=0){
                            $xml .='<PerOccupancy rate="'.$rateplan_double_price.'" occupancy="3"/>';
                        }
                        if($rateplan_quadruple_price!=0){
                            $xml .='<PerOccupancy rate="'.$rateplan_quadruple_price.'" occupancy="4"/>';
                        }

                        $xml .='</Rate>
                        <Restrictions minLOS="'.$rateplan_los.'" />

                        </RatePlan>
                        </RoomType>
                        </AvailRateUpdate>';
                        $xml_data.= $xml;
                    }
                    else{
                        $flag=0;
                    }
                }
                else{
                    $rateplanId=0;
                }
            }
            $count = $count+$flag;
            $xml_all_data.= $xml_data;
        }
        $rateplanids                     = implode(',',$rateplanId);
        if($count>=1)
        {
            $headers = array (
                //Regulates versioning of the XML interface for the API
                'Content-Type: application/xml'
                );
           $username 						= trim($auth_parameter->username);
			$password 						= trim($auth_parameter->password);
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
            $expedia_rate_xml ='<?xml version="1.0" encoding="UTF-8"?>
            <AvailRateUpdateRQ xmlns="http://www.expediaconnect.com/EQC/AR/2011/06">
            <Authentication username="'.$username.'" password="'.$password.'"/>
            <Hotel id="'.$ota_hotel_code.'"/>';
            $expedia_rate_xml.= $xml_all_data.'</AvailRateUpdateRQ>';
            $log_request_msg = $expedia_rate_xml;
           $url  = $commonUrl.'eqc/ar';
            $logModel->fill($log_data)->save();
            $result=$this->getdata_curlreq->cUrlCall($url,$headers,$expedia_rate_xml);//for curl call
            $resultXml=simplexml_load_string($result);

            if($resultXml){
				$array_data = json_decode(json_encode($resultXml), true);
				if(!isset($array_data['Error'])){
				DB::table('rate_update_logs')->where('id', $logModel->id)
					->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
					$rlt=array('status'=>1,'ota_name'=>'expedia','response_msg'=>'updated successfully');
				}else{
					DB::table('rate_update_logs')->where('id', $logModel->id)
					->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
					$rlt=array('status'=>0,'ota_name'=>'expedia','response_msg'=>$result);
				}
			}
			else{
				$rlt=array('status'=>0,'ota_name'=>'expedia','response_msg'=>$result);
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
            $rlt=array('status'=>0,'ota_name'=>'expedia','response_msg'=>"Rateplan should be sync");
        }
        if(sizeof($rlt)==0){
            $rlt=array('status'=>0,'ota_name'=>'expedia','response_msg'=>"Rateplan should be sync");
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
        $multiple_days                  = $data['multiple_days'];
        $hotel_id                       = $rate_bucket_data['bucket_hotel_id'];
        $ota_id                         = $rate_bucket_data['bucket_ota_id'];
        $ota_hotel_code                 = $rate_bucket_data['bucket_ota_hotel_code'];
        $rlt                            =array();
        $flag                           =0;
        $data['multiple_days']       = json_encode($data['multiple_days']);
        $max_adult=$roomTypeModel->select('max_people')->where('room_type_id',$data['room_type_id'])->first()->max_people;
        $getMaxOccupancy = $roomTypeModel->select('max_people','extra_person')->where('room_type_id',$data['room_type_id'])->first();
        if($getMaxOccupancy->max_people < 3){
            $totalCapacity=$getMaxOccupancy->max_people + $getMaxOccupancy->extra_person;
        }
        else{
            $totalCapacity= $getMaxOccupancy->max_people;
        }
        $currency=$this->getdata_curlreq->getCurrency($hotel_id);
        $rateplan_multiple_days_data = $this->getdata_curlreq->getDaysUpdate($data['multiple_days'],"Expedia");
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
            $rateplan_quadruple_price    = isset($occupency[3])?$occupency[3]:0;
        }
        if($rateplan_triple_price == 0 && $extra_adult_price != 0 && $totalCapacity==3){
            $rateplan_triple_price = $data['bar_price'] + $data['extra_adult_price'];
        }
        $result 					 = $cmOtaRatePlanSynchronizeModel->getOtaRoomIdFromRatePlanSynch($data['room_type_id'],$ota_id,$data['rate_plan_id']);
        if(isset($result[0])){
            $room_code                   = $result[0]['ota_room_type_id'];
            $rate_code                   = $result[0]['ota_rate_plan_id'];
        }
        else{
            $rlt=array('status'=>0,'ota_name'=>'expedia','response_msg'=>"Room type & Rateplan should be sync");
            return $rlt;
        }
        $rateplan_los                = $data['los'];
        $startDate                   = date('Y-m-d',strtotime($data['from_date']));
        $endDate                     = date('Y-m-d',strtotime($data['to_date']));
        $data['multiple_occupancy']  = json_encode($data['multiple_occupancy']);
        $data['channel']             = 'Expedia';

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
                $xml='<AvailRateUpdate>
                <DateRange from="'.$startDate.'" to="'.$endDate.'" '.$rateplan_multiple_days_data.' />
                <RoomType id="'.$room_code.'">
                <RatePlan id="'.$rate_code.'" closed="false">
                <Rate currency="'.$currency.'">';
                if($rateplan_single_price!=0){
                $xml .='<PerOccupancy rate="'.$rateplan_single_price.'" occupancy="1"/>';

                }
                if($rateplan_double_price!=0){
                $xml .='<PerOccupancy rate="'.$rateplan_double_price.'" occupancy="2"/>';
                }
                if($rateplan_triple_price!=0){
                    $xml .='<PerOccupancy rate="'.$rateplan_triple_price.'" occupancy="3"/>';
                }
                if($rateplan_quadruple_price!=0){
                    $xml .='<PerOccupancy rate="'.$rateplan_quadruple_price.'" occupancy="4"/>';
                }
                $xml .='</Rate>
                <Restrictions minLOS="'.$rateplan_los.'" />
                </RatePlan>
                </RoomType>
                </AvailRateUpdate>';
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
           $username 						= trim($auth_parameter->username);
			$password 						= trim($auth_parameter->password);
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
            $expedia_rate_xml ='<?xml version="1.0" encoding="UTF-8"?>
            <AvailRateUpdateRQ xmlns="http://www.expediaconnect.com/EQC/AR/2011/06">
            <Authentication username="'.$username.'" password="'.$password.'"/>
            <Hotel id="'.$ota_hotel_code.'"/>';
            $expedia_rate_xml.= $xml_data.'</AvailRateUpdateRQ>';
            $log_request_msg = $expedia_rate_xml;
           $url  = $commonUrl.'eqc/ar';
            $logModel->fill($log_data)->save();
            $result=$this->getdata_curlreq->cUrlCall($url,$headers,$expedia_rate_xml);//for curl call
            $resultXml=simplexml_load_string($result);

            if($resultXml){
				$array_data = json_decode(json_encode($resultXml), true);
				if(!isset($array_data['Error'])){
				DB::table('rate_update_logs')->where('id', $logModel->id)
					->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
					$rlt=array('status'=>1,'ota_name'=>'expedia','response_msg'=>'updated successfully');
				}else{
					DB::table('rate_update_logs')->where('id', $logModel->id)
					->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
					$rlt=array('status'=>0,'ota_name'=>'expedia','response_msg'=>$result);
				}
			}
			else{
				$rlt=array('status'=>0,'ota_name'=>'expedia','response_msg'=>$result);
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
            $rlt=array('status'=>0,'ota_name'=>'expedia','response_msg'=>"Rateplan should be sync");
        }
        if(sizeof($rlt)==0){
            $rlt=array('status'=>0,'ota_name'=>'expedia','response_msg'=>"Rateplan should be sync");
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
        $rateplan_Id                    = '';
        $max_adult=$roomTypeModel->select('max_people')->where('room_type_id',$data['room_type_id'])->first()->max_people;

            $data['date_from'] = date('Y-m-d',strtotime($data['date_from']));
            $data['date_to'] = date('Y-m-d',strtotime($data['date_to']));
            $cond = array('hotel_id'=>$data['hotel_id'],'room_type_id'=>$rooms,'rate_plan_id'=>$data['rate_plan_id'],'channel'=>'Expedia');
            $getRateDetails = OtaRatePlan::select('*')
                                ->where($cond)->where('from_date','<=',$data['date_from'])
                                ->where('to_date','>=',$data['date_to'])
                                ->orderBy('rate_plan_log_id','DESC')
                                ->first();

                $rate_data = [
                    'hotel_id'          => $getRateDetails->hotel_id,
                    'channel'           => 'Expedia',
                    'room_type_id'      => $getRateDetails->room_type_id,
                    'rate_plan_id'      => $getRateDetails->rate_plan_id,
                    'bar_price'         => $getRateDetails->bar_price,
                    'multiple_occupancy'=> $getRateDetails->multiple_occupancy,
                    'multiple_days'     => $getRateDetails->multiple_days,
                    'from_date'         => $data['date_from'],
                    'to_date'           =>  $data['date_to'],
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
                if($occupency)
                {
                    $rateplan_single_price       = $occupency[0];
                    $rateplan_double_price       = $occupency[1];
                    $rateplan_triple_price       = $occupency[2];
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
                    $rateplan_Id = implode(',',$rateplanId);
                    if($rate_code){
                        $xml='<RatePlan>
                        <RatePlanCode>'.$rate_code.'</RatePlanCode>
                        <StartDate Format="yyyy-mm-dd">'.$startDate.'</StartDate>
                        <EndDate Format="yyyy-mm-dd">'.$endDate.'</EndDate>';
                        if($rateplan_single_price!=0)
                        {
                            $xml .='<SingleOccupancyRates>
                                    <SellRate>'.$rateplan_single_price.'</SellRate>
                                </SingleOccupancyRates>';
                        }
                        if($rateplan_double_price!=0)
                        {
                            $xml .='<DoubleOccupancyRates>
                                <SellRate>'.$rateplan_double_price.'</SellRate>
                            </DoubleOccupancyRates>';
                        }
                        if($rateplan_triple_price!=0)
                        {
                            $xml .='<TripleOccupancyRates>
                                <SellRate>'.$rateplan_triple_price.'</SellRate>
                            </TripleOccupancyRates>';
                        }

                        if($extra_adult_price && $extra_child_price)
                        {
                            $xml .='<ExtraChildCharge>'.$extra_child_price.'</ExtraChildCharge>
                                    <ExtraAdultCharge>'.$extra_adult_price.'</ExtraAdultCharge>';
                        }
                            $xml .='<DaysOfWeek'.$rateplan_multiple_days_data.'></DaysOfWeek>
                                <Currency>'.$currency.'</Currency>
                                <StopSell>True</StopSell>
                        </RatePlan>';
                        $headers = array (
                            //Regulates versioning of the XML interface for the API
                            'Content-Type: application/xml'
                            );
                            $username 						= trim($auth_parameter->username);
                       $password 						= trim($auth_parameter->password);

                        $log_data               = [
                            "action_id"          => 2,
                            "hotel_id"           => $hotel_id,
                            "ota_id"      	     => $ota_id,
                            "rate_ref_id"        => $rateplan_Id,
                            "user_id"            => $rate_bucket_data['bucket_user_id'],
                            "request_msg"        => '',
                            "response_msg"       => '',
                            "request_url"        => '',
                            "status"         => 2,
                            "ip"         => $rate_bucket_data['bucket_client_ip'],
                            "comment"	=> "Processing for update "
                            ];

                            $expedia_rate_xml ='<?xml version="1.0" encoding="UTF-8"?>
                            <AvailRateUpdateRQ xmlns="http://www.expediaconnect.com/EQC/AR/2011/06">
                            <Authentication username="'.$username.'" password="'.$password.'"/>
                            <Hotel id="'.$ota_hotel_code.'"/>';
                            $expedia_rate_xml.= $xml.'</AvailRateUpdateRQ>';
                            $log_request_msg = $expedia_rate_xml;
                            $url  = $commonUrl.'eqc/ar';
                            $logModel->fill($log_data)->save();
                            $result=$this->getdata_curlreq->cUrlCall($url,$headers,$expedia_rate_xml);//for curl call
                            $resultXml=simplexml_load_string($result);

                            if($resultXml){
                				$array_data = json_decode(json_encode($resultXml), true);
                				if(!isset($array_data['Error'])){
                				DB::table('rate_update_logs')->where('id', $logModel->id)
                					->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                					$rlt=array('status'=>1,'ota_name'=>'expedia','response_msg'=>'updated successfully');
                				}else{
                					DB::table('rate_update_logs')->where('id', $logModel->id)
                					->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                					$rlt=array('status'=>0,'ota_name'=>'expedia','response_msg'=>$result);
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
                            "status"         => 1,
                            "ip"         => $rate_bucket_data['bucket_client_ip'],
                            "comment"	=> "Roomrate type is not mapped"
                            ];
                        $logModel->fill($log_data)->save();
                        $rlt=array('status'=>0,'ota_name'=>'expedia','response_msg'=>"Rateplan should be sync");
                    }
                }
              }
        if(empty($rlt)){
            $rlt=array('status'=>0,'ota_name'=>'expedia','response_msg'=>"Rateplan should be mapped");
        }
        return $rlt;
    }
}
