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
use App\BaseRate;
use App\DynamicPricingCurrentInventory;
use App\OtaInventory;//new model for single ota inv push
use App\OtaRatePlan;//new model for single ota rate push
use App\CmOtaRoomTypeSynchronize;
use App\CmOtaRatePlanSynchronize;
use App\Http\Controllers\Controller;
use App\Http\Controllers\IpAddressService;
use App\Http\Controllers\invrateupdatecontrollers\GetDataForRateController;
/**
 * This controller is used for Travelguru single,bulk,sync and block of inv and rate
 * @auther ranjit
 * created date 08/03/19.
 * modification due to display problem @ 12/03/19 by ranjit(new model added)
 */
class TravelguruInvRateController extends Controller
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
        $rlt                            = array();
        

            $room_code = DB::table('cm_ota_room_type_synchronize')
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
                $inv['channel']            = 'Travelguru';
                $inv['ota_id']             = $ota_id;
                $inv['user_id']            = $bucket_data["bucket_user_id"];
                $inv['client_ip']          = $bucket_data["bucket_client_ip"];
                $inv['hotel_id']           = $hotel_id;
                $inv['date_from']          = $inv['date'];
                $inv['date_to']            = $inv['date'];
                $inv['multiple_days']      = '{"Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1","Sun":"1"}';

                try{
                    $otainventory->fill($inv)->save();
                    // $current_inv = array(
                    //     "hotel_id"      =>$inv['hotel_id'],
                    //     "room_type_id"  =>$inv['room_type_id'],
                    //     "ota_id"        =>$inv['ota_id'],
                    //     "stay_day"      =>$inv['date'],
                    //     "no_of_rooms"   =>$inv['no_of_rooms'],
                    //     "ota_name"      =>"Travelguru"
                    // );
                    // $check_inv_exist = DynamicPricingCurrentInventory::select('*')
                    // ->where('hotel_id',$inv['hotel_id'])
                    // ->where('room_type_id',$inv['room_type_id'])
                    // ->where('ota_id',$inv['ota_id'])
                    // ->where('stay_day',$inv['date'])
                    // ->first();
                    // if($check_inv_exist){
                    //     $update_cur_inv = DynamicPricingCurrentInventory::where('hotel_id',$inv['hotel_id'])
                    //                     ->where('room_type_id',$inv['room_type_id'])
                    //                     ->where('ota_id',$inv['ota_id'])
                    //                     ->where('stay_day',$inv['date'])
                    //                     ->update(["no_of_rooms" => $inv['no_of_rooms']]);
                    // }
                    // else{
                    //     $insert_cur_inv =  DynamicPricingCurrentInventory::insert($current_inv);
                    // }
                }
                catch(Exception $e){

                }
                $inventory_id = $otainventory->inventory_id;
                if($room_code)
                {
                    $headers = array ('Content-Type: application/xml');
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
                    $MessagePassword      			= trim($auth_parameter->MessagePassword);
                    $ID                   			= trim($auth_parameter->ID);
                    if($inv['block_status']==0)
                    {
                        if($room_qtys >= 0)
                        {
                            $xml = '<OTA_HotelAvailNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05">
                                <POS>
                                <Source>
                                <RequestorID MessagePassword="'.$MessagePassword.'" ID="'.$ID.'" Type="CHM"/>
                                </Source>
                                </POS>
                                <AvailStatusMessages HotelCode="'.$ota_hotel_code.'">
                                <AvailStatusMessage BookingLimit="'.$room_qtys.'">
                                <StatusApplicationControl Mon="true" Tue="true" Weds="true" Thur="true" Sun="true" Sat="true"  Fri="true" Start="'.$startDate.'" End="'.$endDate.'" InvCode="'.$room_code.'"/>
                                <RestrictionStatus SellThroughOpenIndicator="false"/>
                                </AvailStatusMessage>
                                </AvailStatusMessages>
                                </OTA_HotelAvailNotifRQ>';
                        }
                    }
                    else
                    {
                        $xml = '<OTA_HotelAvailNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05">
                            <POS>
                            <Source>
                            <RequestorID MessagePassword="'.$MessagePassword.'" ID="'.$ID.'" Type="CHM"/>
                            </Source>
                            </POS>
                            <AvailStatusMessages HotelCode="'.$ota_hotel_code.'">
                            <AvailStatusMessage BookingLimit="0">
                            <StatusApplicationControl Mon="true" Tue="true" Weds="true" Thur="true" Sun="true" Sat="true"  Fri="true" Start="'.$startDate.'" End="'.$endDate.'" InvCode="'.$room_code.'"/>
                            <RestrictionStatus SellThroughOpenIndicator="true"/>
                            </AvailStatusMessage>
                            </AvailStatusMessages>
                            </OTA_HotelAvailNotifRQ>';
                    }
                    $log_request_msg = $xml;
                    $url         	 = $commonUrl.'availability/update';
                    $logModel->fill($log_data)->save();
                    $result=$this->getdata_curlreq->cUrlCall($url,$headers,$xml);//for curl call
                    $resultXml=simplexml_load_string($result);
                        if($resultXml)
                        {
                            $array_data = json_decode(json_encode($resultXml), true);
                            if(!isset($array_data['Errors'])){
                                DB::table('log_table')->where('id', $logModel->id)
                                ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                                $rlt=array('status'=>1,'ota_name'=>'travelguru','response_msg'=>"updated successfully");
                            }else{
                                DB::table('log_table')->where('id', $logModel->id)
                                ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                                $logpath = storage_path("logs/travelguruInventoryUpdate.log".date("Y-m-d"));
                                $logfile = fopen($logpath, "a+");
                                fwrite($logfile,"Processing starts at: ".date("Y-m-d H:i:s")."\n");
                                fclose($logfile);
                                $dlt_status = OtaInventory::where('inventory_id',$inventory_id)->delete();
                                $logfile = fopen($logpath, "a+");
                                fwrite($logfile,"inventory request: ".$log_request_msg."\n");
                                fwrite($logfile,"inventory response: ".$result."\n");
                                fwrite($logfile,"log table id: ".$logModel->id."\n");
                                fwrite($logfile,"hotel id: ".$hotel_id."\n");
                                fwrite($logfile,"ota id: ".$ota_id."\n");
                                fwrite($logfile,"Processing ends at: ".date("Y-m-d H:i:s")."\n");
                                fclose($logfile);
                                $rlt=array('status'=>0,'ota_name'=>'travelguru','response_msg'=>$array_data['errors']);
                            }
                        }
                        else{
                            $logpath = storage_path("logs/travelguruInventoryUpdate.log".date("Y-m-d"));
                            $logfile = fopen($logpath, "a+");
                            fwrite($logfile,"Processing starts at: ".date("Y-m-d H:i:s")."\n");
                            fclose($logfile);
                            $dlt_status = OtaInventory::where('inventory_id',$inventory_id)->delete();
                            $logfile = fopen($logpath, "a+");
                            fwrite($logfile,"inventory response: ".$result."\n");
                            fwrite($logfile,"hotel id: ".$hotel_id."\n");
                            fwrite($logfile,"ota id: ".$ota_id."\n");
                            fwrite($logfile,"Processing ends at: ".date("Y-m-d H:i:s")."\n");
                            fclose($logfile);
                            $rlt=array('status'=>0,'ota_name'=>'travelguru','response_msg'=>$result);
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
                    $logpath = storage_path("logs/travelguruInventoryUpdate.log".date("Y-m-d"));
                    $logfile = fopen($logpath, "a+");
                    fwrite($logfile,"Processing starts at: ".date("Y-m-d H:i:s")."\n");
                    fclose($logfile);
                    $dlt_status = OtaInventory::where('inventory_id',$inventory_id)->delete();
                    $logfile = fopen($logpath, "a+");
                    fwrite($logfile,"inventory response: Room rate type is not mapped\n");
                    fwrite($logfile,"hotel id: ".$hotel_id."\n");
                    fwrite($logfile,"ota id: ".$ota_id."\n");
                    fwrite($logfile,"Processing ends at: ".date("Y-m-d H:i:s")."\n");
                    fclose($logfile);
                    $rlt=array('status'=>0,'ota_name'=>'travelguru','response_msg'=>"Roomtype should be sync");
                }
            }
            if(sizeof($rlt)==0){
                 $rlt=array('status'=>0,'ota_name'=>'travelguru','response_msg'=>"Roomtype should be mapped");
            }
        return $rlt;
    }
    public function blockInventoryUpdate($bucket_data,$room_type_id,$data,$auth_parameter,$commonUrl)
    {
        $blocklog                   = new LogTable();
        $hotel_id                   = $bucket_data["bucket_hotel_id"];
        $ota_id                     = $bucket_data["bucket_ota_id"];
        $ota_hotel_code             = $bucket_data["bucket_ota_hotel_code"];
        $data['channel']            = 'Travelguru';
        $rlt                        = array();
        $data['room_type_id']       = $room_type_id;
        $data['ota_id']             = $ota_id;
        $data['user_id']            = $bucket_data["bucket_user_id"];
        $data['client_ip']          = $bucket_data["bucket_client_ip"];
        $data['hotel_id']           = $hotel_id;
        $success_flag=1;
        if($success_flag)
        {              
            $room_code       = DB::table('cm_ota_room_type_synchronize')
                               ->where('hotel_id', '=', $hotel_id)
                               ->where('room_type_id', '=', $room_type_id)
                               ->where('ota_type_id', '=', $ota_id)
                               ->value('ota_room_type');
            $start_date     = $data['date_from'];
            $end_date       = $data['date_to'];

            while (strtotime($start_date) <= strtotime($end_date)) {  
                $otainventory    = new OtaInventory();     
                $startDate       = date('Y-m-d',strtotime($start_date));
                $endDate         = date('Y-m-d',strtotime($start_date));
                $getCurrentInventory = OtaInventory::select('no_of_rooms')
                                        ->where('hotel_id',$hotel_id)
                                        ->where('room_type_id',$room_type_id)
                                        ->where('channel','Travelguru')
                                        ->where('date_from','<=',$start_date)
                                        ->where('date_to','>=',$start_date)
                                        ->orderBy('inventory_id','DESC')
                                        ->first();
                $room_qtys = isset($getCurrentInventory->no_of_rooms)?$getCurrentInventory->no_of_rooms:0;
                $data['no_of_rooms']    = $room_qtys;
                $data['date_from']      = $start_date;
                $data['date_to']        = $start_date;

                try{
                    $otainventory->fill($data)->save();
                    // $from_date = date('Y-m-d',strtotime($data['date_from'])); 
                    // $to_date = date('Y-m-d',strtotime($data['date_to'].'+1 day'));
                    // $p_start = $from_date;
                    // $p_end = $to_date;
                    // $period     = new \DatePeriod(
                    //     new \DateTime($p_start),
                    //     new \DateInterval('P1D'),
                    //     new \DateTime($p_end)
                    // );
                    // foreach($period as $key => $value ){
                    //     $index = $value->format('Y-m-d');
                    //     $current_inv = array(
                    //         "hotel_id"      =>$data['hotel_id'],
                    //         "room_type_id"  =>$data['room_type_id'],
                    //         "ota_id"        =>$data['ota_id'],
                    //         "stay_day"      =>$index,
                    //         "no_of_rooms"   =>0,
                    //         "block_status"  =>1,
                    //         "ota_name"      =>"Travelguru"
                    //     );
                    //     $check_inv_exist = DynamicPricingCurrentInventory::select('*')
                    //     ->where('hotel_id',$data['hotel_id'])
                    //     ->where('room_type_id',$data['room_type_id'])
                    //     ->where('ota_id',$data['ota_id'])
                    //     ->where('stay_day',$index)
                    //     ->first();
                    //     if($check_inv_exist){
                    //         $update_cur_inv = DynamicPricingCurrentInventory::where('hotel_id',$data['hotel_id'])
                    //                         ->where('room_type_id',$data['room_type_id'])
                    //                         ->where('ota_id',$data['ota_id'])
                    //                         ->where('stay_day',$index)
                    //                         ->update(["no_of_rooms" => $data['no_of_rooms'],"block_status"=>1]);
                    //     }
                    //     else{
                    //         $insert_cur_inv =  DynamicPricingCurrentInventory::insert($current_inv);
                    //     }
                    // }
                    
                }
                catch(\Exception $e){
                    $success_flag=0;
                }
                $inventoryId     = $otainventory->inventory_id;
                if($room_code){
                    $headers = array ('Content-Type: application/xml');
                    $MessagePassword      			= trim($auth_parameter->MessagePassword);
                    $ID                   			= trim($auth_parameter->ID);
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
                    $headers = array ('Content-Type: application/xml');
                    $MessagePassword      			= trim($auth_parameter->MessagePassword);
                    $ID                   			= trim($auth_parameter->ID);
                    $xml = '<OTA_HotelAvailNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05">
                        <POS>
                        <Source>
                        <RequestorID MessagePassword="'.$MessagePassword.'" ID="'.$ID.'" Type="CHM"/>
                        </Source>
                        </POS>
                        <AvailStatusMessages HotelCode="'.$ota_hotel_code.'">
                        <AvailStatusMessage BookingLimit="'.$room_qtys.'">
                        <StatusApplicationControl Mon="true" Tue="true" Weds="true" Thur="true" Sun="true" Sat="true"  Fri="true" Start="'.$startDate.'" End="'.$endDate.'" InvCode="'.$room_code.'"/>
                        <RestrictionStatus SellThroughOpenIndicator="true"/>
                        </AvailStatusMessage>
                        </AvailStatusMessages>
                        </OTA_HotelAvailNotifRQ>';
                        $log_request_msg = $xml;
                        $url         	 = $commonUrl.'availability/update';
                        $blocklog->fill($log_data)->save();
                        $result=$this->getdata_curlreq->cUrlCall($url,$headers,$xml);//for curl call
                        $resultXml=simplexml_load_string($result);
                        if($resultXml){
                            $array_data = json_decode(json_encode($resultXml), true);
                            if(!isset($array_data['Errors'])){
                                DB::table('log_table')->where('id', $blocklog->id)
                                ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                                $rlt=array('status'=>1,'ota_name'=>'travelguru','response_msg'=>"Blocked successfully");
                            }else{
                                $error = isset($array_data['Errors'])?$array_data['Errors']['Error']:$array_data['errors'];
                                DB::table('log_table')->where('id', $blocklog->id)
                                ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                                $logpath = storage_path("logs/travelguruInventoryUpdate.log".date("Y-m-d"));
                                $logfile = fopen($logpath, "a+");
                                fwrite($logfile,"Processing starts at: ".date("Y-m-d H:i:s")."\n");
                                fclose($logfile);
                                $dlt_status = OtaInventory::where('inventory_id',$inventoryId)->delete();
                                $logfile = fopen($logpath, "a+");
                                fwrite($logfile,"inventory request: ".$log_request_msg."\n");
                                fwrite($logfile,"inventory response: ".$result."\n");
                                fwrite($logfile,"log table id: ".$blocklog->id."\n");
                                fwrite($logfile,"hotel id: ".$hotel_id."\n");
                                fwrite($logfile,"ota id: ".$ota_id."\n");
                                fwrite($logfile,"Processing ends at: ".date("Y-m-d H:i:s")."\n");
                                fclose($logfile);
                                $rlt=array('status'=>0,'ota_name'=>'travelguru','response_msg'=>$error);
                            }
                        }
                        else{
                            $rlt=array('status'=>0,'ota_name'=>'travelguru','response_msg'=>$result);
                            $logpath = storage_path("logs/travelguruInventoryUpdate.log".date("Y-m-d"));
                            $logfile = fopen($logpath, "a+");
                            fwrite($logfile,"Processing starts at: ".date("Y-m-d H:i:s")."\n");
                            fclose($logfile);
                            $dlt_status = OtaInventory::where('inventory_id',$inventoryId)->delete();
                            $logfile = fopen($logpath, "a+");
                            fwrite($logfile,"inventory response: ".$result."\n");
                            fwrite($logfile,"hotel id: ".$hotel_id."\n");
                            fwrite($logfile,"ota id: ".$ota_id."\n");
                            fwrite($logfile,"Processing ends at: ".date("Y-m-d H:i:s")."\n");
                            fclose($logfile);
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
                    $rlt=array('status' => 0,'ota_name'=>'travelguru','response'=> 'Roomtype should be Sync');
                    $logpath = storage_path("logs/travelguruInventoryUpdate.log".date("Y-m-d"));
                    $logfile = fopen($logpath, "a+");
                    fwrite($logfile,"Processing starts at: ".date("Y-m-d H:i:s")."\n");
                    fclose($logfile);
                    $dlt_status = OtaInventory::where('inventory_id',$inventoryId)->delete();
                    $logfile = fopen($logpath, "a+");
                    fwrite($logfile,"inventory response: Roomtype should be mapped\n");
                    fwrite($logfile,"hotel id: ".$hotel_id."\n");
                    fwrite($logfile,"ota id: ".$ota_id."\n");
                    fwrite($logfile,"Processing ends at: ".date("Y-m-d H:i:s")."\n");
                    fclose($logfile);
                }
                $start_date = date ("Y-m-d", strtotime("+1 days", strtotime($start_date)));
            }
        }
        if(sizeof($rlt)==0){
            $rlt=array('status'=>0,'ota_name'=>'travelguru','response_msg'=>"Roomtype should be mapped");
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
        $rlt                            = array();
        foreach($inventory as $invs)
        {
            $room_code = DB::table('cm_ota_room_type_synchronize')
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
                $inv['channel']            = 'Travelguru';
                $inv['ota_id']             = $ota_id;
                $inv['user_id']            = $bucket_data["bucket_user_id"];
                $inv['client_ip']          = $bucket_data["bucket_client_ip"];
                $inv['hotel_id']           = $hotel_id;
                $inv['date_from']          = $inv['date'];
                $inv['date_to']            = $inv['date'];
                $success_flag=1;
                try{
                    $otainventory->fill($inv)->save();
                    // $current_inv = array(
                    //     "hotel_id"      =>$inv['hotel_id'],
                    //     "room_type_id"  =>$inv['room_type_id'],
                    //     "ota_id"        =>$inv['ota_id'],
                    //     "stay_day"      =>$inv['date'],
                    //     "no_of_rooms"   =>$inv['no_of_rooms'],
                    //     "ota_name"      =>"Travelguru"
                    // );
                    // $check_inv_exist = DynamicPricingCurrentInventory::select('*')
                    // ->where('hotel_id',$inv['hotel_id'])
                    // ->where('room_type_id',$inv['room_type_id'])
                    // ->where('ota_id',$inv['ota_id'])
                    // ->where('stay_day',$inv['date'])
                    // ->first();
                    // if($check_inv_exist){
                    //     $update_cur_inv = DynamicPricingCurrentInventory::where('hotel_id',$inv['hotel_id'])
                    //                     ->where('room_type_id',$inv['room_type_id'])
                    //                     ->where('ota_id',$inv['ota_id'])
                    //                     ->where('stay_day',$inv['date'])
                    //                     ->update(["no_of_rooms" => $inv['no_of_rooms']]);
                    // }
                    // else{
                    //     $insert_cur_inv =  DynamicPricingCurrentInventory::insert($current_inv);
                    // }
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
                            $headers = array ('Content-Type: application/xml');
                            $MessagePassword      			= trim($auth_parameter->MessagePassword);
                            $ID                   			= trim($auth_parameter->ID);
                            $xml = '<OTA_HotelAvailNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05">
                                <POS>
                                <Source>
                                <RequestorID MessagePassword="'.$MessagePassword.'" ID="'.$ID.'" Type="CHM"/>
                                </Source>
                                </POS>
                                <AvailStatusMessages HotelCode="'.$ota_hotel_code.'">
                                <AvailStatusMessage BookingLimit="'.$room_qtys.'">
                                <StatusApplicationControl Mon="true" Tue="true" Weds="true" Thur="true" Sun="true" Sat="true"  Fri="true" Start="'.$startDate.'" End="'.$endDate.'" InvCode="'.$room_code.'"/>
                                <RestrictionStatus SellThroughOpenIndicator="false"/>
                                </AvailStatusMessage>
                                </AvailStatusMessages>
                                </OTA_HotelAvailNotifRQ>';
                                $log_request_msg = $xml;
                                $url         	 = $commonUrl.'availability/update';
                                $logModel->fill($log_data)->save();
                                $result=$this->getdata_curlreq->cUrlCall($url,$headers,$xml);//for curl call
                                $resultXml=simplexml_load_string($result);
                                    if($resultXml)
                                    {
                                        $array_data = json_decode(json_encode($resultXml), true);
                                        if(!isset($array_data['Errors'])){
                                            DB::table('log_table')->where('id', $logModel->id)
                                            ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                                            $rlt=array('status'=>1,'ota_name'=>'travelguru','response_msg'=>"updated successfully");
                                        }else{
                                            DB::table('log_table')->where('id', $logModel->id)
                                            ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                                            $logpath = storage_path("logs/travelguruInventoryUpdate.log".date("Y-m-d"));
                                            $logfile = fopen($logpath, "a+");
                                            fwrite($logfile,"Processing starts at: ".date("Y-m-d H:i:s")."\n");
                                            fclose($logfile);
                                            $dlt_status = OtaInventory::where('inventory_id',$inventoryId)->delete();
                                            $logfile = fopen($logpath, "a+");
                                            fwrite($logfile,"inventory request: ".$log_request_msg."\n");
                                            fwrite($logfile,"inventory response: ".$result."\n");
                                            fwrite($logfile,"log table id: ".$logModel->id."\n");
                                            fwrite($logfile,"hotel id: ".$hotel_id."\n");
                                            fwrite($logfile,"ota id: ".$ota_id."\n");
                                            fwrite($logfile,"Processing ends at: ".date("Y-m-d H:i:s")."\n");
                                            fclose($logfile);
                                            $rlt=array('status'=>0,'ota_name'=>'travelguru','response_msg'=>$array_data['errors']);
                                        }
                                    }
                                    else{
                                        $rlt=array('status'=>0,'ota_name'=>'travelguru','response_msg'=>$result);
                                        $logpath = storage_path("logs/travelguruInventoryUpdate.log".date("Y-m-d"));
                                        $logfile = fopen($logpath, "a+");
                                        fwrite($logfile,"Processing starts at: ".date("Y-m-d H:i:s")."\n");
                                        fclose($logfile);
                                        $dlt_status = OtaInventory::where('inventory_id',$inventoryId)->delete();
                                        $logfile = fopen($logpath, "a+");
                                        fwrite($logfile,"inventory response: ".$result."\n");
                                        fwrite($logfile,"hotel id: ".$hotel_id."\n");
                                        fwrite($logfile,"ota id: ".$ota_id."\n");
                                        fwrite($logfile,"Processing ends at: ".date("Y-m-d H:i:s")."\n");
                                        fclose($logfile);
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
                            $logpath = storage_path("logs/travelguruInventoryUpdate.log".date("Y-m-d"));
                            $logfile = fopen($logpath, "a+");
                            fwrite($logfile,"Processing starts at: ".date("Y-m-d H:i:s")."\n");
                            fclose($logfile);
                            $dlt_status = OtaInventory::where('inventory_id',$inventoryId)->delete();
                            $logfile = fopen($logpath, "a+");
                            fwrite($logfile,"inventory response: Room rate type is not mapped\n");
                            fwrite($logfile,"hotel id: ".$hotel_id."\n");
                            fwrite($logfile,"ota id: ".$ota_id."\n");
                            fwrite($logfile,"Processing ends at: ".date("Y-m-d H:i:s")."\n");
                            fclose($logfile);
                            $rlt=array('status'=>0,'ota_name'=>'travelguru','response_msg'=>"Roomtype should be sync");
                    }
                }
            }
        }
        if(sizeof($rlt)==0){
            $rlt=array('status'=>0,'ota_name'=>'travelguru','response_msg'=>"Roomtype should be mapped");
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
        $data['channel']                = 'Travelguru';
        $rlt                            = array();
        $success_flag=1;
                try{
                    $otainventory->fill($data)->save();
                    // $from_date = date('Y-m-d',strtotime($data['date_from'])); 
                    // $to_date = date('Y-m-d',strtotime($data['date_to'].'+1 day'));
                    // $p_start = $from_date;
                    // $p_end = $to_date;
                    // $period     = new \DatePeriod(
                    //     new \DateTime($p_start),
                    //     new \DateInterval('P1D'),
                    //     new \DateTime($p_end)
                    // );
                    // foreach($period as $key => $value ){
                    //     $index = $value->format('Y-m-d');
                    //     $current_inv = array(
                    //         "hotel_id"      =>$data['hotel_id'],
                    //         "room_type_id"  =>$data['room_type_id'],
                    //         "ota_id"        =>$data['ota_id'],
                    //         "stay_day"      =>$index,
                    //         "no_of_rooms"   =>$data['no_of_rooms'],
                    //         "ota_name"      =>"Travelguru"
                    //     );
                    //     $check_inv_exist = DynamicPricingCurrentInventory::select('*')
                    //     ->where('hotel_id',$data['hotel_id'])
                    //     ->where('room_type_id',$data['room_type_id'])
                    //     ->where('ota_id',$data['ota_id'])
                    //     ->where('stay_day',$index)
                    //     ->first();
                    //     if($check_inv_exist){
                    //         $update_cur_inv = DynamicPricingCurrentInventory::where('hotel_id',$data['hotel_id'])
                    //                         ->where('room_type_id',$data['room_type_id'])
                    //                         ->where('ota_id',$data['ota_id'])
                    //                         ->where('stay_day',$index)
                    //                         ->update(["no_of_rooms" => $data['no_of_rooms']]);
                    //     }
                    //     else{
                    //         $insert_cur_inv =  DynamicPricingCurrentInventory::insert($current_inv);
                    //     }
                    // }
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
                    $headers = array ('Content-Type: application/xml');
                    $MessagePassword      			= trim($auth_parameter->MessagePassword);
                    $ID                   			= trim($auth_parameter->ID);
                    $xml = '<OTA_HotelAvailNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05">
                        <POS>
                        <Source>
                        <RequestorID MessagePassword="'.$MessagePassword.'" ID="'.$ID.'" Type="CHM"/>
                        </Source>
                        </POS>
                        <AvailStatusMessages HotelCode="'.$ota_hotel_code.'">
                        <AvailStatusMessage BookingLimit="'.$room_qtys.'">
                        <StatusApplicationControl Mon="true" Tue="true" Weds="true" Thur="true" Sun="true" Sat="true"  Fri="true" Start="'.$startDate.'" End="'.$endDate.'" InvCode="'.$room_code.'"/>
                        <RestrictionStatus SellThroughOpenIndicator="false"/>
                        </AvailStatusMessage>
                        </AvailStatusMessages>
                        </OTA_HotelAvailNotifRQ>';
                        $log_request_msg = $xml;

                        $url         	 = $commonUrl.'availability/update';
                        $logModel->fill($log_data)->save();
                        $result=$this->getdata_curlreq->cUrlCall($url,$headers,$xml);//for curl call
                        $resultXml=simplexml_load_string($result);
                            if($resultXml)
                            {
                                $array_data = json_decode(json_encode($resultXml), true);
                                if(!isset($array_data['Errors'])){
                                    DB::table('log_table')->where('id', $logModel->id)
                                    ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                                    $rlt=array('status'=>1,'ota_name'=>'travelguru','response_msg'=>"updated successfully");
                                }else{
                                    DB::table('log_table')->where('id', $logModel->id)
                                    ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                                    $logpath = storage_path("logs/travelguruInventoryUpdate.log".date("Y-m-d"));
                                    $logfile = fopen($logpath, "a+");
                                    fwrite($logfile,"Processing starts at: ".date("Y-m-d H:i:s")."\n");
                                    fclose($logfile);
                                    $dlt_status = OtaInventory::where('inventory_id',$invtefid)->delete();
                                    $logfile = fopen($logpath, "a+");
                                    fwrite($logfile,"inventory request: ".$log_request_msg."\n");
                                    fwrite($logfile,"inventory response: ".$result."\n");
                                    fwrite($logfile,"log table id: ".$logModel->id."\n");
                                    fwrite($logfile,"hotel id: ".$hotel_id."\n");
                                    fwrite($logfile,"ota id: ".$ota_id."\n");
                                    fwrite($logfile,"Processing ends at: ".date("Y-m-d H:i:s")."\n");
                                    fclose($logfile);
                                    $error = isset($array_data['Errors']["Error"])?$array_data['Errors']["Error"]:$array_data['Errors'];
                                    $rlt=array('status'=>0,'ota_name'=>'travelguru','response_msg'=>$error);

                                }
                            }
                            else{
                                $rlt=array('status'=>0,'ota_name'=>'travelguru','response_msg'=>$result);
                                $logpath = storage_path("logs/travelguruInventoryUpdate.log".date("Y-m-d"));
                                $logfile = fopen($logpath, "a+");
                                fwrite($logfile,"Processing starts at: ".date("Y-m-d H:i:s")."\n");
                                fclose($logfile);
                                $dlt_status = OtaInventory::where('inventory_id',$invtefid)->delete();
                                $logfile = fopen($logpath, "a+");
                                fwrite($logfile,"inventory response: ".$result."\n");
                                fwrite($logfile,"hotel id: ".$hotel_id."\n");
                                fwrite($logfile,"ota id: ".$ota_id."\n");
                                fwrite($logfile,"Processing ends at: ".date("Y-m-d H:i:s")."\n");
                                fclose($logfile);
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
                    $logpath = storage_path("logs/travelguruInventoryUpdate.log".date("Y-m-d"));
                    $logfile = fopen($logpath, "a+");
                    fwrite($logfile,"Processing starts at: ".date("Y-m-d H:i:s")."\n");
                    fclose($logfile);
                    $rlt=array('status'=>0,'ota_name'=>'travelguru','response_msg'=>"Roomtype should be sync");
                    $dlt_status = OtaInventory::where('inventory_id',$invtefid)->delete();
                    $logfile = fopen($logpath, "a+");
                    fwrite($logfile,"inventory response: Roomtype should be sync \n");
                    fwrite($logfile,"hotel id: ".$hotel_id."\n");
                    fwrite($logfile,"ota id: ".$ota_id."\n");
                    fwrite($logfile,"Processing ends at: ".date("Y-m-d H:i:s")."\n");
                    fclose($logfile);
            }
            if(sizeof($rlt)==0){
                $rlt=array('status'=>0,'ota_name'=>'travelguru','response_msg'=>"Roomtype should be mapped");
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

                $rateplan_multiple_days_data = $this->getdata_curlreq->getDaysUpdate($multiple_days,"Travelguru");

                $currency=$this->getdata_curlreq->getCurrency($hotel_id);
                $extra_adult_price			 = 0;
                $extra_child_price			 = 0;
                $rateplan_bar_price          = $rates['bar_price'];
                $rateplan_los                = 1;
                $occupency=$this->getdata_curlreq->decideOccupencyPrice($max_adult,$rates['bar_price'],$rates['multiple_occupancy']);
                $rateplan_single_price=0;
                $rateplan_double_price=0;
                $rateplan_triple_price=0;
                $rateplan_fourth_price=0;
                if($occupency)
                {
                    $rateplan_single_price       = $occupency[0];
                    $rateplan_double_price       = isset($occupency[1])?$occupency[1]:0;
                    $rateplan_triple_price       = isset($occupency[2])?$occupency[2]:0;
                    if(isset($occupency[3]))
                    {
                        $rateplan_fourth_price       = $occupency[3];
                    }
                }
                $getroom_details=$roomTypeModel->select('max_people','max_child','extra_person')->where('room_type_id',$rates['room_type_id'])->first();
                $result 					 =  $cmOtaRatePlanSynchronizeModel->getOtaRoomIdFromRatePlanSynch($rates['room_type_id'],$ota_id,$rates['rate_plan_id']);
                if(isset($result[0])){
                $room_code                   =  $result[0]['ota_room_type_id'];
                $rate_code                   =  $result[0]['ota_rate_plan_id'];
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
                $rates['channel']               = 'Travelguru';
                $rates['multiple_occupancy']    = json_encode($rates['multiple_occupancy']);
                try{
                    $otaRatePlan->fill($rates)->save();
                    $base_rate     = new BaseRate();
                    $insertBaseRate = $base_rate->fill($rates)->save();
                }
                catch(Exception $e){

                }
                if($rate_code)
                {
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
                $MessagePassword  				= trim($auth_parameter->MessagePassword);
                $ID               				= trim($auth_parameter->ID);
                $headers = array ('Content-Type: application/xml');
                $xml = '<OTA_HotelRateAmountNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05">
                    <POS>
                    <Source>
                    <RequestorID MessagePassword="'.$MessagePassword.'" ID="'.$ID.'" Type="CHM"/>
                    </Source>
                    </POS>
                    <SellableProducts HotelCode="'.$ota_hotel_code.'">
                        <SellableProduct Start="'.$startDate.'" End="'.$endDate.'">
                            <GuestRoom>
                                <Room RoomID="'.$room_code.'" Quantity="1"/>
                                <RoomLevelFees>
                                    <Fee Amount="'.$rateplan_bar_price.'" Type="Inclusive"></Fee>
                                </RoomLevelFees>
                                <AdditionalGuestAmount>';
                                if($extra_adult_price != 0){
                                    $xml.=' <Amount AdditionalFeesExcludedIndicator="true"
                                    <AmountAfterTax="'.$extra_adult_price.'"></Amount>';
                                }
                               $xml.='</AdditionalGuestAmount>
                            </GuestRoom>
                        </SellableProduct>
                    </SellableProducts>
                    <RateAmountMessages>
                    <RateAmountMessage>
                    <StatusApplicationControl RatePlanCode="'.$rate_code.'" RatePlanType="SEL" End="'.$endDate.'" Start="'.$startDate.'" InvCode="'.$room_code.'"
                    '.$rateplan_multiple_days_data.'/>
                    <Rates>
                    <Rate NumberOfUnits="0" MinLos="'.$rateplan_los.'"  End="'.$endDate.'" Start="'.$startDate.'">
                        <BaseByGuestAmts>
                        <BaseByGuestAmt AmountAfterTax="'.$rateplan_bar_price.'" CurrencyCode="INR"/>
                        </BaseByGuestAmts>
                    </Rate>
                    </Rates>
                    </RateAmountMessage>
                    </RateAmountMessages>
                    </OTA_HotelRateAmountNotifRQ>';
                $log_request_msg = $xml;
                $url  = $commonUrl.'rateAmount/update';
                $logModel->fill($log_data)->save();
                $result=$this->getdata_curlreq->cUrlCall($url,$headers,$xml);//for curl call
                $resultXml=simplexml_load_string($result);
                if($resultXml)
                {
                    $array_data = json_decode(json_encode($resultXml), true);
                if(!isset($array_data['Errors'])){
                    DB::table('rate_update_logs')->where('id', $logModel->id)
                    ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                    $rlt=array('status'=>1,'ota_name'=>'travelguru','response_msg'=>"update successfully");
                }
                else
                {
                    DB::table('rate_update_logs')->where('id', $logModel->id)
                    ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                    $rlt=array('status'=>0,'ota_name'=>'travelguru','response_msg'=>$result);
                }
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
                        $rlt=array('status'=>0,'ota_name'=>'travelguru','response_msg'=>"Rateplan should be sync");
                    }
                }
        if(sizeof($rlt)==0){
            $rlt=array('status'=>0,'ota_name'=>'travelguru','response_msg'=>"Rateplan should be mapped");
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

                $rates['extra_adult_price']     = isset($rates['extra_adult_price'])?$rates['extra_adult_price']:0;
                $rates['extra_child_price']     = isset($rates['extra_child_price'])?$rates['extra_child_price']:0;
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
                $rates['channel']               = 'Travelguru';
                $min_max_status =   $this->getdata_curlreq->checkMinMaxPrice($rates['room_type_id'],$rates['rate_plan_id'],$rates['bar_price'],$rates['multiple_occupancy'],$rates['hotel_id'],$rates['date'],$rates['channel']);
                if($min_max_status){
                    $rlt = $min_max_status;
                    continue;
                }
                $rateplan_multiple_days_data = $this->getdata_curlreq->getDaysUpdate($rates['multiple_days'],"Travelguru");
                $currency=$this->getdata_curlreq->getCurrency($hotel_id);
                $extra_adult_price			 = isset($rates['extra_adult_price'])?$rates['extra_adult_price']:0;;
                $extra_child_price			 = isset($rates['extra_child_price'])?$rates['extra_child_price']:0;
                $rateplan_bar_price          = $rates['bar_price'];
                $rateplan_los                = $rates['los'];
                $occupency=$this->getdata_curlreq->decideOccupencyPrice($max_adult,$rates['bar_price'],$rates['multiple_occupancy']);
                $rateplan_single_price=0;
                $rateplan_double_price=0;
                $rateplan_triple_price=0;
                if($occupency)
                {
                    $rateplan_single_price       = $occupency[0];
                    $rateplan_double_price       = isset($occupency[1])?$occupency[1]:0;
                    $rateplan_triple_price       = isset($occupency[2])?$occupency[2]:0;
                    if(isset($occupency[3]))
                    {
                        $rateplan_fourth_price       = $occupency[3];
                    }
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
                $rates['multiple_occupancy']    = json_encode($rates['multiple_occupancy']);
                $success_flag=1;
                try{
                    $otaRatePlan->fill($rates)->save();
                    $base_rate     = new BaseRate();
                    $insertBaseRate = $base_rate->fill($rates)->save();
                }
                catch(\Exception $e){
                   $success_flag=0;
                }
                if($success_flag)
                {
                    $rateplanId   = $otaRatePlan->rate_plan_log_id;
                    if($rate_code)
                    {

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
                            $MessagePassword  				= trim($auth_parameter->MessagePassword);
                            $ID               				= trim($auth_parameter->ID);
                            $headers = array ('Content-Type: application/xml');
                            $xml = '<OTA_HotelRateAmountNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05">
                                <POS>
                                <Source>
                                <RequestorID MessagePassword="'.$MessagePassword.'" ID="'.$ID.'" Type="CHM"/>
                                </Source>
                                </POS>
                                <SellableProducts HotelCode="'.$ota_hotel_code.'">
                                        <SellableProduct Start="'.$startDate.'" End="'.$endDate.'">
                                            <GuestRoom>
                                                <Room RoomID="'.$room_code.'" Quantity="1"/>
                                                <RoomLevelFees>
                                                    <Fee Amount="'.$rateplan_bar_price.'" Type="Inclusive"></Fee>
                                                </RoomLevelFees>
                                                <AdditionalGuestAmount Type="EA1">
                                                    <Amount AdditionalFeesExcludedIndicator="true"
                                                    AmountAfterTax="'.$extra_adult_price.'"></Amount>
                                                </AdditionalGuestAmount>
                                                <AdditionalGuestAmount Type="EC1">
                                                    <Amount AdditionalFeesExcludedIndicator="true"
                                                    AmountAfterTax="'.$extra_child_price.'"></Amount>
                                                </AdditionalGuestAmount>
                                            </GuestRoom>
                                        </SellableProduct>
                                    </SellableProducts>
                                <RateAmountMessages>
                                <RateAmountMessage>
                                <StatusApplicationControl RatePlanCode="'.$rate_code.'" RatePlanType="SEL" End="'.$endDate.'" Start="'.$startDate.'" InvCode="'.$room_code.'"
                                '.$rateplan_multiple_days_data.'/>
                                <Rates>
                                <Rate NumberOfUnits="0" MinLos="'.$rateplan_los.'"  End="'.$endDate.'" Start="'.$startDate.'">
                                    <BaseByGuestAmts>
                                    <BaseByGuestAmt AmountAfterTax="'.$rateplan_bar_price.'" CurrencyCode="INR"/>
                                    </BaseByGuestAmts>
                                </Rate>
                                </Rates>
                                </RateAmountMessage>
                                </RateAmountMessages>
                                </OTA_HotelRateAmountNotifRQ>';
                            $log_request_msg = $xml;

                            $url  = $commonUrl.'rateAmount/update';
                            $logModel->fill($log_data)->save();
                            $result=$this->getdata_curlreq->cUrlCall($url,$headers,$xml);//for curl call
                            $resultXml=simplexml_load_string($result);
                            if($resultXml)
                            {
                                $array_data = json_decode(json_encode($resultXml), true);
                            if(!isset($array_data['Errors'])){
                                DB::table('rate_update_logs')->where('id', $logModel->id)
                                ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                                $rlt=array('status'=>1,'ota_name'=>'travelguru','response_msg'=>'Update successfully');
                            }
                            else
                            {
                                DB::table('rate_update_logs')->where('id', $logModel->id)
                                ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                                $rlt=array('status'=>0,'ota_name'=>'travelguru','response_msg'=>$result);
                            }
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
                        $rlt=array('status'=>0,'ota_name'=>'travelguru','response_msg'=>"Rateplan should be sync");
                    }
                }
            }
        }
        if(sizeof($rlt)==0){
            $rlt=array('status'=>0,'ota_name'=>'travelguru','response_msg'=>"Rateplan should be mapped");
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
        $multiple_days                  = '{"Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1","Sun":"1"}';
        $hotel_id                       = $rate_bucket_data['bucket_hotel_id'];
        $ota_id                         = $rate_bucket_data['bucket_ota_id'];
        $ota_hotel_code                 = $rate_bucket_data['bucket_ota_hotel_code'];
        $rlt                            = array();
        $max_adult=$roomTypeModel->select('max_people')->where('room_type_id',$data['room_type_id'])->first()->max_people;
        $data['multiple_days']       = json_encode($data['multiple_days']);
        $currency=$this->getdata_curlreq->getCurrency($hotel_id);
        $rateplan_multiple_days_data = $this->getdata_curlreq->getDaysUpdate($data['multiple_days'],"Travelguru");
        $occupency=$this->getdata_curlreq->decideOccupencyPrice($max_adult,$data['bar_price'],$data['multiple_occupancy']);
        $getroom_details=$roomTypeModel->select('max_people','extra_person','max_child')->where('room_type_id',$data['room_type_id'])->first();
        $extra_adult_price			 = $data['extra_adult_price'];
        $extra_child_price			 = $data['extra_child_price'];
        if($extra_adult_price == ''){
          $extra_adult_price = 0;
        }
        if($extra_child_price == ''){
          $extra_child_price = 0;
        }
        $rateplan_bar_price          = $data['bar_price'];
        $rateplan_los                = $data['los'];
        $rateplan_single_price=0;
        $rateplan_double_price=0;
        $rateplan_triple_price=0;
        if($occupency)
        {
            $rateplan_single_price       = $occupency[0];
            $rateplan_double_price       = isset($occupency[1])?$occupency[1]:0;
            $rateplan_triple_price       = isset($occupency[2])?$occupency[2]:0;
            if(isset($occupency[3]))
            {
                $rateplan_fourth_price       = $occupency[3];
            }
        }
        $result 					 = $cmOtaRatePlanSynchronizeModel->getOtaRoomIdFromRatePlanSynch($data['room_type_id'],$ota_id,$data['rate_plan_id']);
        if(isset($result[0])){
        $room_code                   = $result[0]['ota_room_type_id'];
        $rate_code                   = $result[0]['ota_rate_plan_id'];
        }
        else{
            $rlt=array('status'=>0,'ota_name'=>'travelguru','response_msg'=>"Room type or Rateplan should be sync");
            return $rlt;
        }
        $startDate                   = date('Y-m-d',strtotime($data['from_date']));
        $endDate                     = date('Y-m-d',strtotime($data['to_date']));
        $data['multiple_occupancy']  = json_encode($data['multiple_occupancy']);
        $data['channel']             = 'Travelguru';

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
                    if(!isset($data['dp_status'])){
                        $base_rate     = new BaseRate();
                        $insertBaseRate = $base_rate->fill($data)->save();
                    }
                }
                catch(\Exception $e){
                   $success_flag=0;
                }
        if($success_flag)
        {
            $rateplanId   = $otaRatePlan->rate_plan_log_id;
            if($rate_code)
            {

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
                    $MessagePassword  				= trim($auth_parameter->MessagePassword);
                    $ID               				= trim($auth_parameter->ID);
                    $headers = array ('Content-Type: application/xml');
                    $xml = '<OTA_HotelRateAmountNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05">
                        <POS>
                        <Source>
                        <RequestorID MessagePassword="'.$MessagePassword.'" ID="'.$ID.'" Type="CHM"/>
                        </Source>
                        </POS>
                        <SellableProducts HotelCode="'.$ota_hotel_code.'">
                                        <SellableProduct Start="'.$startDate.'" End="'.$endDate.'">
                                            <GuestRoom>
                                                <Occupancy MinOccupancy="'.$getroom_details->max_people.'" AgeQualifyingCode="10"/>
                                                <Occupancy MinOccupancy="'.$getroom_details->max_child.'" AgeQualifyingCode="8"/>
                                                <Room RoomID="'.$room_code.'" Quantity="1"/>
                                                <RoomLevelFees>
                                                    <Fee Amount="'.$rateplan_bar_price.'" Type="Inclusive"></Fee>
                                                </RoomLevelFees>
                                                <AdditionalGuestAmount Type="EA1">
                                                    <Amount AdditionalFeesExcludedIndicator="true"
                                                    AmountAfterTax="'.$extra_adult_price.'"></Amount>
                                                </AdditionalGuestAmount>
                                                <AdditionalGuestAmount Type="EC1">
                                                    <Amount AdditionalFeesExcludedIndicator="true"
                                                    AmountAfterTax="'.$extra_child_price.'"></Amount>
                                                </AdditionalGuestAmount>
                                            </GuestRoom>
                                        </SellableProduct>
                                    </SellableProducts>
                        <RateAmountMessages>
                        <RateAmountMessage>
                        <StatusApplicationControl RatePlanCode="'.$rate_code.'" RatePlanType="SEL" End="'.$endDate.'" Start="'.$startDate.'" InvCode="'.$room_code.'"
                        '.$rateplan_multiple_days_data.'/>
                        <Rates>
                        <Rate NumberOfUnits="0" MinLos="'.$rateplan_los.'"  End="'.$endDate.'" Start="'.$startDate.'">
                            <BaseByGuestAmts>
                            <BaseByGuestAmt AmountAfterTax="'.$rateplan_bar_price.'" CurrencyCode="INR"/>
                            </BaseByGuestAmts>
                        </Rate>
                        </Rates>
                        </RateAmountMessage>
                        </RateAmountMessages>
                        </OTA_HotelRateAmountNotifRQ>';
                    $log_request_msg = $xml;
                    $url  = $commonUrl.'rateAmount/update';
                    $logModel->fill($log_data)->save();
                    $result=$this->getdata_curlreq->cUrlCall($url,$headers,$xml);//for curl call
                    $resultXml=simplexml_load_string($result);
                    if($resultXml)
                    {
                        $array_data = json_decode(json_encode($resultXml), true);
                        if(!isset($array_data['Errors'])){
                        DB::table('rate_update_logs')->where('id', $logModel->id)
                        ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                        $rlt=array('status'=>1,'ota_name'=>'travelguru','response_msg'=>'update successfully');
                        }
                        else
                        {
                            DB::table('rate_update_logs')->where('id', $logModel->id)
                            ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                            $rlt=array('status'=>0,'ota_name'=>'travelguru','response_msg'=>$result);
                        }
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
                $rlt=array('status'=>0,'ota_name'=>'travelguru','response_msg'=>"Rateplan should be sync");
            }
        }
        if(sizeof($rlt)==0){
            $rlt=array('status'=>0,'ota_name'=>'travelguru','response_msg'=>"Rateplan should be mapped");
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
            $cond = array('hotel_id'=>$data['hotel_id'],'room_type_id'=>$rooms,'rate_plan_id'=>$data['rate_plan_id'],'channel'=>'Travelguru');
            $getRateDetails = OtaRatePlan::select('*')
                                ->where($cond)->where('from_date','<=',$data['date_from'])
                                ->where('to_date','>=',$data['date_to'])
                                ->orderBy('rate_plan_log_id','DESC')
                                ->first();
                $rate_data = [
                    'hotel_id'          => $getRateDetails->hotel_id,
                    'channel'           => 'Travelguru',
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
                $rateplan_multiple_days_data = $this->getdata_curlreq->getDaysUpdate($multiple_days,"Travelguru");
                $rateplan_single_price=0;
                $rateplan_double_price=0;
                $rateplan_triple_price=0;
                if($occupency)
                {
                    $rateplan_single_price       = $occupency[0];
                    $rateplan_double_price       = isset($occupency[1])?$occupency[1]:0;
                    $rateplan_triple_price       = isset($occupency[2])?$occupency[2]:0;
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
                $rateplan_bar_price =   $rate_data['bar_price'];
                $success_flag=1;
                try{
                    $otaRatePlan->fill($rate_data)->save();
                }
                catch(\Exception $e){
                   $success_flag=0;
                }
                if($otaRatePlan->fill($rate_data)->save()){
                    $base_rate     = new BaseRate();
                    $insertBaseRate = $base_rate->fill($rate_data)->save();
                    $rateplanId   = $otaRatePlan->rate_plan_log_id;
                    $rateplan_Id  = implode(',',$rateplanId);
                    if($rate_code){
                        $log_data  = [
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
                            $MessagePassword  	= trim($auth_parameter->MessagePassword);
                            $ID               = trim($auth_parameter->ID);
                            $headers = array ('Content-Type: application/xml');
                            $xml = '<OTA_HotelRateAmountNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05">
                                <POS>
                                <Source>
                                <RequestorID MessagePassword="'.$MessagePassword.'" ID="'.$ID.'" Type="CHM"/>
                                </Source>
                                </POS>
                                <SellableProducts HotelCode="'.$ota_hotel_code.'">
                                        <SellableProduct Start="'.$startDate.'" End="'.$endDate.'">
                                            <GuestRoom>
                                                <Room RoomID="'.$room_code.'" Quantity="1"/>
                                                <RoomLevelFees>
                                                    <Fee Amount="'.$rateplan_bar_price.'" Type="Inclusive"></Fee>
                                                </RoomLevelFees>
                                                <AdditionalGuestAmount>
                                                    <Amount AdditionalFeesExcludedIndicator="true"';
                                                    if($extra_adult_price != 0){
                                                        $xml.='AmountAfterTax="0"></Amount>';
                                                    }
                                                    $xml.= '</AdditionalGuestAmount>
                                            </GuestRoom>
                                        </SellableProduct>
                                    </SellableProducts>
                                <RateAmountMessages>
                                <RateAmountMessage>
                                <StatusApplicationControl RatePlanCode="'.$rate_code.'" RatePlanType="SEL" End="'.$endDate.'" Start="'.$startDate.'" InvCode="'.$room_code.'"
                                '.$rateplan_multiple_days_data.'/>
                                <Rates>
                                <Rate NumberOfUnits="0" MinLos="0"  End="'.$endDate.'" Start="'.$startDate.'">
                                    <BaseByGuestAmts>
                                    <BaseByGuestAmt AmountAfterTax="0" CurrencyCode="INR"/>
                                    </BaseByGuestAmts>
                                </Rate>
                                </Rates>
                                </RateAmountMessage>
                                </RateAmountMessages>
                                </OTA_HotelRateAmountNotifRQ>';
                            $log_request_msg = $xml;
                            $url  = $commonUrl.'rateAmount/update';
                            $logModel->fill($log_data)->save();
                            $result=$this->getdata_curlreq->cUrlCall($url,$headers,$xml);//for curl call
                            $resultXml=simplexml_load_string($result);
                            if($resultXml)
                            {
                                $array_data = json_decode(json_encode($resultXml), true);
                                if(!isset($array_data['Errors'])){
                                    DB::table('log_table')->where('id', $logModel->id)
                                    ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                                    $rlt=array('status'=>1,'ota_name'=>'travelguru','response_msg'=>$result);
                                }
                                else
                                {
                                    DB::table('log_table')->where('id', $logModel->id)
                                    ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                                    $rate_update = RateUpdateLog::where('rate_plan_log_id',$rateplanId)->update(['block_status'=>0]);
                                    $rlt=array('status'=>0,'ota_name'=>'travelguru','response_msg'=>$result);
                                }
                            }
                    }
                    else{
                        $rateplanId=0;
                        $log_data               = [
                            "action_id"          => 2,
                            "hotel_id"           => $hotel_id,
                            "ota_id"      	     => $ota_id,
                            "rate_ref_id"        => $rateplanId,
                            "user_id"            => $rate_bucket_data['bucket_user_id'],
                            "request_msg"        => '',
                            "response_msg"       => '',
                            "request_url"        => '',
                            "status"         => 1,
                            "ip"         => $rate_bucket_data['bucket_client_ip'],
                            "comment"	=> "Roomrate type is not mapped"
                            ];
                        $logModel->fill($log_data)->save();
                        $rate_update = RateUpdateLog::where('rate_plan_log_id',$rateplanId)->update(['block_status'=>0]);
                        $rlt=array('status'=>0,'ota_name'=>'travelguru','response_msg'=>"Rateplan should be sync");
                    }
                }
        if(empty($rlt)){
            $rlt=array('status'=>0,'ota_name'=>'travelguru','response_msg'=>"Rateplan should be mapped");
        }
        return $rlt;
    }
}
