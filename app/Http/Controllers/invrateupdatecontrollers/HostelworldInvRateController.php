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
use App\OtaInventory;//new model for single ota inv push
use App\OtaRatePlan;//new model for single ota rate push
use App\CmOtaRoomTypeSynchronize;
use App\CmOtaRatePlanSynchronize;
use App\DynamicPricingCurrentInventory;
use App\Http\Controllers\Controller;
use App\Http\Controllers\IpAddressService;
use App\Http\Controllers\invrateupdatecontrollers\GetDataForRateController;
/**
 * This controller is used for hostel world single,bulk,sync and block of inv and rate
 * @auther ranjit
 * created date 22/10/21.
 */
class HostelworldInvRateController extends Controller
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
       
            $ota_room_type = DB::table('cm_ota_room_type_synchronize')
            ->where('hotel_id', '=', $hotel_id)
            ->where('room_type_id', '=', $inventory['room_type_id'])
            ->where('ota_type_id', '=', $ota_id)
            ->value('ota_room_type');

            $xml='<request><availability>';

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
                $get_details = $cmOtaRoomTypeSynchronizeModel->getOtaRoomTypeDetails($hotel_id,$inventory['room_type_id'],$ota_id);
                $basic_type = '';
                if($get_details){
                    $beds = $get_details->beds;
                    if($beds <= 0){
                        $beds = 1;
                    }
                    $basic_type = $get_details->basictype;
                }
                $startDate                  = date('Y-m-d',strtotime($inv['date']));
                $endDate                    = date('Y-m-d',strtotime($inv['date']));
                $inventory_los              = $inv['los'];
                $room_qtys                  = $inv["no_of_rooms"];
                $room_qtys                  = $room_qtys*$beds;
                $inv['room_type_id']       = $inventory['room_type_id'];
                $inv['block_status']       = $inv["block_status"];
                $inv['channel']            = 'Hostelworld';
                $inv['ota_id']             = $ota_id;
                $inv['user_id']            = $bucket_data["bucket_user_id"];
                $inv['client_ip']          = $bucket_data["bucket_client_ip"];
                $inv['hotel_id']           = $hotel_id;
                $inv['date_from']          = date('Y-m-d',strtotime($inv['date']));
                $inv['date_to']            = date('Y-m-d',strtotime($inv['date']));
                $inv['multiple_days']      = '{"Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1","Sun":"1"}';

                try{
                        $otainventory->fill($inv)->save();
                        $current_inv = array(
                            "hotel_id"      =>$inv['hotel_id'],
                            "room_type_id"  =>$inv['room_type_id'],
                            "ota_id"        =>$inv['ota_id'],
                            "stay_day"      =>$inv['date'],
                            "no_of_rooms"   =>$inv['no_of_rooms'],
                            "ota_name"      =>"Hostelworld"
                        );
                        $cur_inv = DynamicPricingCurrentInventory::updateOrInsert(
                            [
                                'hotel_id' => $inv['hotel_id'],
                                'room_type_id' => $inv['room_type_id'],
                                'ota_id'=>$inv['ota_id'],
                                'stay_day'=>$inv['date'],
                                'ota_name'=>"Hostelworld"
                            ],
                        $current_inv
                        );
                        
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

                            $xml.='<roomtype roomtypeid="'.$room_code.'"><beds start="'.$startDate.'" end="'.$endDate.'">'.$room_qtys.'</beds></roomtype>';
                        }
                    }
                    else
                    {
                      $xml.='<roomtype roomtypeid="'.$room_code.'"><beds start="'.$startDate.'" end="'.$endDate.'">0</beds></roomtype>';
                    }
                }
            }
            $xml.='</availability></request>';
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

                    $hostelworld_xml =$xml;
                    $log_request_msg = $hostelworld_xml;
                    $logModel->fill($log_data)->save();//saving pre logdata
                    $consumer_key           = trim($auth_parameter->consumer_key);
                    $consumer_signature     = trim($auth_parameter->consumer_signature);
                    $url = "https://property.xsapi.webresint.com/2.0/setbeds/".$ota_hotel_code.".xml?consumer_key=".$consumer_key."&consumer_signature=".$consumer_signature."";
                    $ch = curl_init();
                    curl_setopt( $ch, CURLOPT_URL, $url );
                    curl_setopt( $ch, CURLOPT_POST, true );
                    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
                    curl_setopt( $ch, CURLOPT_POSTFIELDS, $hostelworld_xml);
                    $ota_rlt = curl_exec($ch);
                    curl_close($ch);
                   
                   
                $resultXml=simplexml_load_string($ota_rlt);
                if($resultXml){
                    $array_data = json_decode(json_encode($resultXml), true);
                    if(isset($array_data["success"])){
                        DB::table('log_table')->where('id', $logModel->id)
                        ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
                        $rlt=array('status'=>1,'ota_name'=>'hostelworld','response_msg'=>"Inventory updation succssfully");
                    }
                    else{
                        DB::table('log_table')->where('id', $logModel->id)
                        ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
                        $rlt=array('status'=>0,'ota_name'=>'hostelworld','response_msg'=>$array_data["errors"]["error"]);
                    }
                }
                else{
                    $rlt=array('status'=>0,'ota_name'=>'hostelworld','response_msg'=>$ota_rlt);
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
               $rlt=array('status'=>0,'ota_name'=>'hostelworld','response_msg'=>"Roomtype should be sync");
           }
            return $rlt;
    }
    public function blockInventoryUpdate($bucket_data,$room_type_id,$data,$auth_parameter,$commonUrl)
    {
        $blocklog                   = new LogTable();
        $hotel_id                   = $bucket_data["bucket_hotel_id"];
        $ota_id                     = $bucket_data["bucket_ota_id"];
        $ota_hotel_code             = $bucket_data["bucket_ota_hotel_code"];
        $data['channel']            = 'Hostelworld';
        $rlt                        = array();
        $xml                        = '';
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
                $otainventory               = new OtaInventory();     
                $startDate       = date('Y-m-d',strtotime($start_date));
                $endDate         = date('Y-m-d',strtotime($start_date));
                $getCurrentInventory = OtaInventory::select('no_of_rooms')
                                        ->where('hotel_id',$hotel_id)
                                        ->where('room_type_id',$room_type_id)
                                        ->where('channel','Hostelworld')
                                        ->where('date_from','<=',$start_date)
                                        ->where('date_to','>=',$start_date)
                                        ->orderBy('inventory_id','DESC')
                                        ->first();
                $room_qtys = isset($getCurrentInventory->no_of_rooms)?$getCurrentInventory->no_of_rooms:0;
                $data['no_of_rooms'] =  $room_qtys;
                $data['date_from'] = $start_date;
                $data['date_to'] = $start_date;
                try{
                    $otainventory->fill($data)->save();
                    $from_date = date('Y-m-d',strtotime($data['date_from'])); 
                $to_date = date('Y-m-d',strtotime($data['date_to'].'+1 day'));
                $p_start = $from_date;
                $p_end = $to_date;
                $period     = new \DatePeriod(
                    new \DateTime($p_start),
                    new \DateInterval('P1D'),
                    new \DateTime($p_end)
                );
                foreach($period as $key => $value ){
                    $index = $value->format('Y-m-d');
                    $current_inv = array(
                        "hotel_id"      =>$data['hotel_id'],
                        "room_type_id"  =>$data['room_type_id'],
                        "ota_id"        =>$data['ota_id'],
                        "stay_day"      =>$index,
                        "no_of_rooms"   =>0,
                        "block_status"  =>1,
                        "ota_name"      =>"Hostelworld"
                    );
                    $cur_inv = DynamicPricingCurrentInventory::updateOrInsert(
                        [
                            'hotel_id' => $data['hotel_id'],
                            'room_type_id' => $data['room_type_id'],
                            'ota_id'=>$data['ota_id'],
                            'stay_day'=>$index,
                            'ota_name'=>"Hostelworld"
                        ],
                        $current_inv
                    );
                }
                }
                catch(\Exception $e){
                    $success_flag=0;
                }
                $inventoryId  = $otainventory->inventory_id;
                if(!empty($room_code))
                {
                    $flag = 1;
                    $xml='<request><availability><roomtype roomtypeid="'.$room_code.'"><beds start="'.$startDate.'" end="'.$endDate.'">0</beds></roomtype></availability></request>';
                           
                    $invId             = $inventoryId;

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
                    $hostelworld_xml =$xml;
                    $log_request_msg = $hostelworld_xml;
                    $consumer_key           = trim($auth_parameter->consumer_key);
                    $consumer_signature     = trim($auth_parameter->consumer_signature);
                    $url = "https://property.xsapi.webresint.com/2.0/setbeds/".$ota_hotel_code.".xml?consumer_key=".$consumer_key."&consumer_signature=".$consumer_signature."";
                    $ch = curl_init();
                    curl_setopt( $ch, CURLOPT_URL, $url );
                    curl_setopt( $ch, CURLOPT_POST, true );
                    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
                    curl_setopt( $ch, CURLOPT_POSTFIELDS, $hostelworld_xml);
                    $ota_rlt = curl_exec($ch);
                    curl_close($ch);
                    $resultXml=simplexml_load_string($ota_rlt);
                    if($resultXml){
                        $array_data = json_decode(json_encode($resultXml), true);
                        if(isset($array_data["success"])){
                            DB::table('log_table')->where('id', $blocklog->id)
                            ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
                            $rlt=array('status'=>1,'ota_name'=>'hostelworld','response_msg'=>"Inventory updation succssfully");
                        }
                        else{
                            DB::table('log_table')->where('id', $blocklog->id)
                            ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
                            $rlt=array('status'=>0,'ota_name'=>'hostelworld','response_msg'=>$array_data["errors"]["error"]);
                        }
                    }
                    else{
                        $rlt=array('status'=>0,'ota_name'=>'hostelworld','response_msg'=>$ota_rlt);
                    }
                }
                else{
                    $log_data                = [
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
                    $rlt[]=array('status' => 0,'ota_name'=>'hostelworld','response_msg'=> 'Roomtype should be Sync');
                    $update = OtaInventory::where('inventory_id',$inventoryId)->update(['block_status'=>0]);
                }
                $start_date = date ("Y-m-d", strtotime("+1 days", strtotime($start_date)));
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
        $xml='<request><availability>';
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
                $get_details = $cmOtaRoomTypeSynchronizeModel->getOtaRoomTypeDetails($hotel_id,$invs['room_type_id'],$ota_id);
                $basic_type = '';
                if($get_details){
                    $beds = $get_details->beds;
                    if($beds <= 0){
                        $beds = 1;
                    }
                    $basic_type = $get_details->basictype;
                }
                $startDate                  = date('Y-m-d',strtotime($inv['date']));
                $endDate                    = date('Y-m-d',strtotime($inv['date']));
                $inventory_los              = $inv['los'];
                $room_qtys                  = $inv['no_of_rooms'];
                $room_qtys                  = $room_qtys*$beds;

                $inv['room_type_id']       = $invs['room_type_id'];
                $inv['block_status']       = 0;
                $inv['channel']            = 'Hostelworld';
                $inv['ota_id']             = $ota_id;
                $inv['user_id']            = $bucket_data["bucket_user_id"];
                $inv['client_ip']          = $bucket_data["bucket_client_ip"];
                $inv['hotel_id']           = $hotel_id;
                $inv['date_from']          = $inv['date'];
                $inv['date_to']            = $inv['date'];
                $success_flag=1;
                try{
                    $otainventory->fill($inv)->save();
                    $current_inv = array(
                        "hotel_id"      =>$inv['hotel_id'],
                        "room_type_id"  =>$inv['room_type_id'],
                        "ota_id"        =>$inv['ota_id'],
                        "stay_day"      =>$inv['date'],
                        "no_of_rooms"   =>$inv['no_of_rooms'],
                        "ota_name"      =>"Hostelworld"
                    );
                    $cur_inv = DynamicPricingCurrentInventory::updateOrInsert(
                        [
                            'hotel_id' => $inv['hotel_id'],
                            'room_type_id' => $inv['room_type_id'],
                            'ota_id'=>$inv['ota_id'],
                            'stay_day'=>$inv['date'],
                            'ota_name'=>"Hostelworld"
                        ],
                        $current_inv
                    );
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
                            $xml.='<roomtype roomtypeid="'.$room_code.'"><beds start="'.$startDate.'" end="'.$endDate.'">'.$room_qtys.'</beds></roomtype>';
                        }
                    }
                }
            }
        }
        $xml.=' </availability></request>';
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

            $hostelworld_xml =$xml;
            $log_request_msg = $hostelworld_xml;
            $logModel->fill($log_data)->save();//saving pre logdata
            $consumer_key           = trim($auth_parameter->consumer_key);
            $consumer_signature     = trim($auth_parameter->consumer_signature);
            $url = "https://property.xsapi.webresint.com/2.0/setbeds/".$ota_hotel_code.".xml?consumer_key=".$consumer_key."&consumer_signature=".$consumer_signature."";
            $ch = curl_init();
            curl_setopt( $ch, CURLOPT_URL, $url );
            curl_setopt( $ch, CURLOPT_POST, true );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
            curl_setopt( $ch, CURLOPT_POSTFIELDS, $hostelworld_xml);
            $ota_rlt = curl_exec($ch);
            curl_close($ch);
            $resultXml=simplexml_load_string($ota_rlt);
            if($resultXml){
                $array_data = json_decode(json_encode($resultXml), true);
                if(isset($array_data["success"])){
                    DB::table('log_table')->where('id', $logModel->id)
                    ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
                    $rlt=array('status'=>1,'ota_name'=>'hostelworld','response_msg'=>"Inventory updation succssfully");
                }
                else{
                    DB::table('log_table')->where('id', $logModel->id)
                    ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
                    $rlt=array('status'=>0,'ota_name'=>'hostelworld','response_msg'=>$array_data["errors"]["error"]);
                }
            }
            else{
                $rlt=array('status'=>0,'ota_name'=>'hostelworld','response_msg'=>$ota_rlt);
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
                $rlt=array('status'=>0,'ota_name'=>'hostelworld','response_msg'=>"Roomtype should be sync");
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
        $data['channel']                = 'Hostelworld';
        $rlt=array();
        $success_flag=1;
        try{
            $otainventory->fill($data)->save();
            $from_date = date('Y-m-d',strtotime($data['date_from'])); 
                    $to_date = date('Y-m-d',strtotime($data['date_to'].'+1 day'));
                    $p_start = $from_date;
                    $p_end = $to_date;
                    $period     = new \DatePeriod(
                        new \DateTime($p_start),
                        new \DateInterval('P1D'),
                        new \DateTime($p_end)
                    );
                    foreach($period as $key => $value ){
                        $index = $value->format('Y-m-d');
                        $current_inv = array(
                            "hotel_id"      =>$data['hotel_id'],
                            "room_type_id"  =>$data['room_type_id'],
                            "ota_id"        =>$data['ota_id'],
                            "stay_day"      =>$index,
                            "no_of_rooms"   =>$data['no_of_rooms'],
                            "ota_name"      =>"Hostelworld"
                        );
                        $cur_inv = DynamicPricingCurrentInventory::updateOrInsert(
                            [
                                'hotel_id' => $data['hotel_id'],
                                'room_type_id' => $data['room_type_id'],
                                'ota_id'=>$data['ota_id'],
                                'stay_day'=>$index,
                                'ota_name'=>"Hostelworld"
                            ],
                            $current_inv
                        );
                    }
        }
        catch(\Exception $e){
            $success_flag=0;
        }
        if($success_flag)
        {
            $invtefid           = $otainventory->inventory_id;
            $room_code 		    = $cmOtaRoomTypeSynchronizeModel->getOtaRoomType($data['room_type_id'],$ota_id);
            $get_details        = $cmOtaRoomTypeSynchronizeModel->getOtaRoomTypeDetails($hotel_id,$data['room_type_id'],$ota_id);
            $basic_type = '';
            if($get_details){
                $beds = $get_details->beds;
                if($beds <= 0){
                    $beds = 1;
                }
                $basic_type = $get_details->basictype;
            }
            $startDate          = date('Y-m-d',strtotime($data['date_from']));
            $endDate            = date('Y-m-d',strtotime($data['date_to']));
            $inventory_los      = $data['los'];
            $room_qtys          = $data['no_of_rooms'];
            $room_qtys          = $room_qtys*$beds;
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

                $hostelworld_xml ='<request><availability><roomtype roomtypeid="'.$room_code.'"><beds start="'.$startDate.'" end="'.$endDate.'">'.$room_qtys.'</beds></roomtype></availability></request>';
                $log_request_msg = $hostelworld_xml;
                $logModel->fill($log_data)->save();//saving pre logdata
                $consumer_key           = trim($auth_parameter->consumer_key);
                $consumer_signature     = trim($auth_parameter->consumer_signature);
                $url = "https://property.xsapi.webresint.com/2.0/setbeds/".$ota_hotel_code.".xml?consumer_key=".$consumer_key."&consumer_signature=".$consumer_signature."";
                dd($url, $hostelworld_xml);
                $ch = curl_init();
                curl_setopt( $ch, CURLOPT_URL, $url );
                curl_setopt( $ch, CURLOPT_POST, true );
                curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
                curl_setopt( $ch, CURLOPT_POSTFIELDS, $hostelworld_xml);
                $ota_rlt = curl_exec($ch);
                curl_close($ch);
                $resultXml=simplexml_load_string($ota_rlt);
                if($resultXml){
                    $array_data = json_decode(json_encode($resultXml), true);
                    if(isset($array_data["success"])){
                        DB::table('log_table')->where('id', $logModel->id)
                        ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
                        $rlt=array('status'=>1,'ota_name'=>'hostelworld','response_msg'=>"Inventory updation succssfully");
                    }
                    else{
                        DB::table('log_table')->where('id', $logModel->id)
                        ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
                        $rlt=array('status'=>0,'ota_name'=>'hostelworld','response_msg'=>$array_data["errors"]["error"]);
                    }
                }
                else{
                    $rlt=array('status'=>0,'ota_name'=>'hostelworld','response_msg'=>"Inventory updation fails");
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
                    $rlt=array('status'=>0,'ota_name'=>'hostelworld','response_msg'=>'Roomtype should be sync');
            }
            return $rlt;
        }
    }
    public function rateSyncUpdate($rate_bucket_data,$rates_data,$auth_parameter,$commonUrl,$from_date,$to_date)
    {
        $cmOtaRatePlanSynchronizeModel 	= new CmOtaRatePlanSynchronize();
        $cmOtaRoomTypeSynchronizeModel  = new CmOtaRoomTypeSynchronize();
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

        $xml='<request><rates currency="INR">';
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
                $rateplan_multiple_days_data = $this->getdata_curlreq->getDaysUpdate($rateplan_data['rateplan_multiple_days'],"Hostelworld");
                $currency=$this->getdata_curlreq->getCurrency($hotel_id);

                $occupency=$this->getdata_curlreq->decideOccupencyPrice($max_adult,$rateplan_data['rateplan_bar_price'],$rates['multiple_occupancy']);
              
                $result 					 = $cmOtaRatePlanSynchronizeModel->getOtaRoomIdFromRatePlanSynch($rateplan_data['rateplan_room_type_id'],$ota_id,$rateplan_data['rateplan_rate_plan_id']);
                if(isset($result[0])){
                    $room_code                   = $result[0]['ota_room_type_id'];
                    $rate_code                   = $result[0]['ota_rate_plan_id'];
                }
                else{
                    continue;
                }
                $startDate                   =  date('Y-m-d',strtotime($rateplan_data['rateplan_date_from']));
                $endDate                     =  date('Y-m-d',strtotime($rateplan_data['rateplan_date_to']));
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
                $rates['channel']               = 'Hostelworld';
                $rates['multiple_occupancy']    = json_encode($rates['multiple_occupancy']);
                try{
                    $otaRatePlan->fill($rates)->save();
                }
                catch(Exception $e){

                }
                if(!empty($rate_code)){

                    $flag=1;
                    $xml.= '<roomtype id="'.$room_code.'"><rate minnightsstay="'.$los.'" rateplanid="'.$rate_code.'" start="'.$startDate.'" end="'.$endDate.'">'.$data['bar_price'].'</rate></roomtype>';
                }
            }
            $xml.= '</rates></request>';
            if($flag == 1){
               
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
                $logModel->fill($log_data)->save();
                $consumer_key           = trim($auth_parameter->consumer_key);
                $consumer_signature     = trim($auth_parameter->consumer_signature);
                $url = "https://property.xsapi.webresint.com/2.0/setrates/".$ota_hotel_code.".xml?consumer_key=".$consumer_key."&consumer_signature=".$consumer_signature."";
                $ch = curl_init();
                curl_setopt( $ch, CURLOPT_URL, $url );
                curl_setopt( $ch, CURLOPT_POST, true );
                curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
                curl_setopt( $ch, CURLOPT_POSTFIELDS, $hostelworld_rate_xml);
                $ota_rlt = curl_exec($ch);
                curl_close($ch);
                $resultXml=simplexml_load_string($ota_rlt);
                if($resultXml){
                    $array_data = json_decode(json_encode($resultXml), true);
                    if(isset($array_data["success"])){
                        DB::table('log_table')->where('id', $logModel->id)
                        ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
                        $rlt=array('status'=>1,'ota_name'=>'hostelworld','response_msg'=>"Rate updation succssfully");
                    }
                    else{
                        DB::table('log_table')->where('id', $logModel->id)
                        ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
                        $rlt=array('status'=>0,'ota_name'=>'hostelworld','response_msg'=>$array_data["errors"]["error"]);
                    }
                }
                else{
                    $rlt=array('status'=>0,'ota_name'=>'hostelworld','response_msg'=>'Rate updation fails');
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
                $rlt=array('status'=>0,'ota_name'=>'hostelworld','response_msg'=>"Rateplan should be sync");
            }
            return $rlt;
    }
    public function singleRateUpdate($rate_bucket_data,$rates_data,$auth_parameter,$commonUrl)
    {
        $cmOtaRatePlanSynchronizeModel 	= new CmOtaRatePlanSynchronize();
        $cmOtaRoomTypeSynchronizeModel  = new CmOtaRoomTypeSynchronize();
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
        $xml='<request><rates currency="INR">';
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
                "extra_adult_price"           => isset($rates['extra_adult_price'])?$rates['extra_adult_price']:0,
                "extra_child_price"           => isset($rates['extra_child_price'])?$rates['extra_child_price']:0,
                "max_adult"                   => $max_adult
                ];

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
                $rates['channel']               = 'Hostelworld';
                $min_max_status =   $this->getdata_curlreq->checkMinMaxPrice($rates['room_type_id'],$rates['rate_plan_id'],$rates['bar_price'],$rates['multiple_occupancy'],$rates['hotel_id'],$rates['date'],$rates['channel']);
                if(isset($min_max_status["status"])){
                    if($count == 0){
                        $rlt = $min_max_status;
                    }
                    continue;
                }
                else{
                    $count = 1;
                }
                $rateplan_multiple_days_data = $this->getdata_curlreq->getDaysUpdate($rateplan_data['rateplan_multiple_days'],"Hostelworld");
                $currency=$this->getdata_curlreq->getCurrency($hotel_id);

                $occupency=$this->getdata_curlreq->decideOccupencyPrice($max_adult,$rateplan_data['rateplan_bar_price'],$rates['multiple_occupancy']);

                $result 					 = $cmOtaRatePlanSynchronizeModel->getOtaRoomIdFromRatePlanSynch($rateplan_data['rateplan_room_type_id'],$ota_id,$rateplan_data['rateplan_rate_plan_id']);
                if(isset($result[0])){
                    $room_code                   = $result[0]['ota_room_type_id'];
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
                        $xml.= '<roomtype id="'.$room_code.'"><rate minnightsstay="'.$los.'" rateplanid="'.$rate_code.'" start="'.$startDate.'" end="'.$endDate.'">'.$data['bar_price'].'</rate></roomtype>';
                    }
                }
                else{
                    $rateplanId=0;
                }
            }
        }
        $xml.= '</rates></request>';
        if($flag == 1){
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
          $logModel->fill($log_data)->save();
          $consumer_key           = trim($auth_parameter->consumer_key);
          $consumer_signature     = trim($auth_parameter->consumer_signature);
          $url = "https://property.xsapi.webresint.com/2.0/setrates/".$ota_hotel_code.".xml?consumer_key=".$consumer_key."&consumer_signature=".$consumer_signature."";
          $ch = curl_init();
          curl_setopt( $ch, CURLOPT_URL, $url );
          curl_setopt( $ch, CURLOPT_POST, true );
          curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
          curl_setopt( $ch, CURLOPT_POSTFIELDS, $hostelworld_rate_xml);
          $ota_rlt = curl_exec($ch);
          curl_close($ch);
          $resultXml=simplexml_load_string($ota_rlt);
          if($resultXml){
            $array_data = json_decode(json_encode($resultXml), true);
            if(isset($array_data["success"])){
                DB::table('log_table')->where('id', $logModel->id)
                ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
                $rlt=array('status'=>1,'ota_name'=>'hostelworld','response_msg'=>"Rate updation succssfully");
            }
            else{
                DB::table('log_table')->where('id', $logModel->id)
                ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
                $rlt=array('status'=>0,'ota_name'=>'hostelworld','response_msg'=>$array_data["errors"]["error"]);
            }
        }
        else{
            $rlt=array('status'=>0,'ota_name'=>'hostelworld','response_msg'=>'Rate updation fails');
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
            $rlt=array('status'=>0,'ota_name'=>'hostelworld','response_msg'=>"Rateplan should be sync");
        }
        return $rlt;
    }
    public function bulkRateUpdate($rate_bucket_data,$data,$auth_parameter,$commonUrl)
    {
        $cmOtaRatePlanSynchronizeModel 	= new CmOtaRatePlanSynchronize();
        $cmOtaRoomTypeSynchronizeModel  = new CmOtaRoomTypeSynchronize();
        $roomTypeModel                  = new MasterRoomType();
        $logModel                       = new RateUpdateLog();
        $otaRatePlan                    = new OtaRatePlan();//ota rate table insertion
        $base_rate                      = new BaseRate();
        $rateplanId                     = '';
        $xml_all_data                   = '';
        $hotel_id                       = $rate_bucket_data['bucket_hotel_id'];
        $ota_id                         = $rate_bucket_data['bucket_ota_id'];
        $ota_hotel_code                 = $rate_bucket_data['bucket_ota_hotel_code'];
        $max_adult=$roomTypeModel->select('max_people')->where('room_type_id',$data['room_type_id'])->first()->max_people;
        $rlt=array();
        $los                         = $data['los'];
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
        "rateplan_los"                => $los,
        "extra_adult_price"           => $data['extra_adult_price'],
        "extra_child_price"           => $data['extra_child_price'],
        "max_adult"                   => $max_adult
        ];
        $multi_days                  =  $data['multiple_days'];
        $data['multiple_days']       = json_encode($data['multiple_days']);
        $currency=$this->getdata_curlreq->getCurrency($hotel_id);
        $rateplan_multiple_days_data = $this->getdata_curlreq->getDaysUpdate($data['multiple_days'],"Hostelworld");
        $occupency=$this->getdata_curlreq->decideOccupencyPrice($max_adult,$rateplan_data['rateplan_bar_price'],$data['multiple_occupancy']);
        $extra_adult_price			 = $rateplan_data['extra_adult_price'];
        $extra_child_price			 = $rateplan_data['extra_child_price'];
        $result 					 = $cmOtaRatePlanSynchronizeModel->getOtaRoomIdFromRatePlanSynch($rateplan_data['rateplan_room_type_id'],$ota_id,$rateplan_data['rateplan_rate_plan_id']);
        if(isset($result[0])){
            $room_code                   = $result[0]['ota_room_type_id'];
            $rate_code                   = $result[0]['ota_rate_plan_id'];
        }
        else{
            $rlt=array('status'=>0,'ota_name'=>'hostelworld','response_msg'=>"Room type & Rateplan should be sync");
            return $rlt;
        }
        $startDate                   = date('Y-m-d',strtotime($rateplan_data['rateplan_date_from']));
        $endDate                     = date('Y-m-d',strtotime($rateplan_data['rateplan_date_to']));
        $data['multiple_occupancy']  = json_encode($data['multiple_occupancy']);
        $data['channel']             = 'Hostelworld';
        $xml_data='';

        $data['from_date']           = date('Y-m-d',strtotime($data['from_date']));
        $data['to_date']             = date('Y-m-d',strtotime($data['to_date']));
        $data['user_id']             = $rate_bucket_data["bucket_user_id"];
        $data['client_ip']           = $rate_bucket_data["bucket_client_ip"];
        $success_flag=1;
                try{
                    $otaRatePlan->fill($data)->save();
                    // $base_rate->fill($data)->save();
                }
                catch(\Exception $e){
                   $success_flag=0;
                }
        if($success_flag)
        {
            $multiple_days = json_decode($data['multiple_days']);
            $rateplanId   = $otaRatePlan->rate_plan_log_id;
            $get_details        = $cmOtaRoomTypeSynchronizeModel->getOtaRoomTypeDetails($hotel_id,$data['room_type_id'],$ota_id);
            $beds = 0;
            $basic_type = '';
            if($get_details){
                $beds = $get_details->beds;
                $basic_type = $get_details->basictype;
            }
            $bar_price = $data['bar_price'] / $beds;
            if(!empty($rate_code))
            {
                $multidays = array();
                foreach($multi_days as $key => $multi){
                    if($key == 'Mon' && $multi == 1){
                        $multidays[]=1;
                    }
                    if($key == 'Tue' && $multi == 1){
                        $multidays[]=2;
                    }
                    if($key == 'Wed' && $multi == 1){
                        $multidays[]=3;
                    }
                    if($key == 'Thu' && $multi == 1){
                        $multidays[]=4;
                    }
                    if($key == 'Fri' && $multi == 1){
                        $multidays[]=5;
                    }
                    if($key == 'Sat' && $multi == 1){
                        $multidays[]=6;
                    }
                    if($key == 'Sun' && $multi == 1){
                        $multidays[]=7;
                    }
                }
                $multidays = implode(',',$multidays);
                $flag=1;
                $xml= '<request><rates currency="EUR"><roomtype id="'.$room_code.'"><rate rateplanid="'.$rate_code.'" start="'.$startDate.'" end="'.$endDate.'" days="'.$multidays.'">'.$bar_price.'</rate></roomtype></rates ></request>';
                $xml_data.= $xml;
                $json_los_data = '{"StartDate":"'.$startDate.'","EndDate":"'.$endDate.'","RoomTypeId":'.$room_code.',"RatePlanId":'.$rate_code.',"LengthsOfStay":[{"Type":"SetMinLOS","Time":'.$los.'}]}';
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
            $hostelworld_rate_xml =  $xml_data;
            $log_request_msg = $hostelworld_rate_xml;
            $logModel->fill($log_data)->save();
            $consumer_key           = trim($auth_parameter->consumer_key);
            $consumer_signature     = trim($auth_parameter->consumer_signature);
            $url = "https://property.xsapi.webresint.com/2.0/setrates/".$ota_hotel_code.".xml?consumer_key=".$consumer_key."&consumer_signature=".$consumer_signature."";
            $los_url = "https://property.xsapi.webresint.com/2.0/properties/".$ota_hotel_code."/availability";
            dd($url, $hostelworld_rate_xml);
            $ch = curl_init();
            curl_setopt( $ch, CURLOPT_URL, $url );
            curl_setopt( $ch, CURLOPT_POST, true );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
            curl_setopt( $ch, CURLOPT_POSTFIELDS, $hostelworld_rate_xml);
            $ota_rlt = curl_exec($ch);
            curl_close($ch);
            $resultXml=simplexml_load_string($ota_rlt);
            $los_request = $this->updateLOS($json_los_data,$los_url,$consumer_key,$consumer_signature);
            if($resultXml){
                $array_data = json_decode(json_encode($resultXml), true);
                if(isset($array_data["success"])){
                    DB::table('log_table')->where('id', $logModel->id)
                    ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
                    $rlt=array('status'=>1,'ota_name'=>'hostelworld','response_msg'=>"Rate updation succssfully");
                }
                else{
                    DB::table('log_table')->where('id', $logModel->id)
                    ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
                    $rlt=array('status'=>0,'ota_name'=>'hostelworld','response_msg'=>$array_data["errors"]["error"]);
                }
            }
            else{
                $rlt=array('status'=>0,'ota_name'=>'hostelworld','response_msg'=>'Rate updation fails');
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
            $rlt=array('status'=>0,'ota_name'=>'hostelworld','response_msg'=>"Rateplan should be sync");
        }
        return $rlt;
    }
    public function blockRateUpdate($rate_bucket_data,$rooms,$data,$auth_parameter,$commonUrl)
    {
        $cmOtaRatePlanSynchronizeModel = new CmOtaRatePlanSynchronize();
        $cmOtaRoomTypeSynchronizeModel  = new CmOtaRoomTypeSynchronize();
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
            $cond = array('hotel_id'=>$data['hotel_id'],'room_type_id'=>$rooms,'rate_plan_id'=>$data['rate_plan_id'],'channel'=>'Hostelworld');
            $getRateDetails = OtaRatePlan::select('*')
                                ->where($cond)->where('from_date','<=',$data['date_from'])
                                ->where('to_date','>=',$data['date_to'])
                                ->orderBy('rate_plan_log_id','DESC')
                                ->first();
                $rate_data = [
                    'hotel_id'          => $getRateDetails->hotel_id,
                    'channel'           => 'Hostelworld',
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
                $rateplan_multiple_days_data = $this->getdata_curlreq->getDaysUpdate($multiple_days,"Hostelworld");
              
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

                    $rateplanId   = $otaRatePlan->rate_plan_log_id;
                    if(!empty($rate_code)){

                      $xml= '{"StartDate":"'.$startDate.'","EndDate":"'.$endDate.'","RoomTypeId":'.$room_code.',"RatePlanId":'.$rate_code.',"RestrictionStatus":{"Status":"Closed"}}';

                        
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
                        $rate_block_data = $xml;
                        $log_request_msg = $rate_block_data;
                        $logModel->fill($log_data)->save();
                        $consumer_key           = trim($auth_parameter->consumer_key);
                        $consumer_signature     = trim($auth_parameter->consumer_signature);
                        $block_url = "https://property.xsapi.webresint.com/2.0/properties/".$ota_hotel_code."/availability";
                        $headers = array(
                                    "content-type: application/json",
                                    "consumer_key:".$consumer_key,
                                    "consumer_signature:".$consumer_signature
                                );
                        $curl = curl_init();

                        curl_setopt_array($curl, array(
                        CURLOPT_URL => $block_url,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => '',
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => 'PUT',
                        CURLOPT_POSTFIELDS =>$rate_block_data,
                        CURLOPT_HTTPHEADER =>$headers,
                        ));

                        $response = curl_exec($curl);

                        curl_close($curl);
                        if($block_rlt){
                            $array_data = json_decode($block_rlt);
                            if(isset($array_data["success"])){
                                DB::table('log_table')->where('id', $logModel->id)
                                ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$block_rlt]);
                                $rlt=array('status'=>1,'ota_name'=>'hostelworld','response_msg'=>"Inventory updation succssfully");
                            }
                            else{
                                DB::table('log_table')->where('id', $logModel->id)
                                ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$block_rlt]);
                                $rlt=array('status'=>0,'ota_name'=>'hostelworld','response_msg'=>$array_data["errors"]["error"]);
                            }
                        }
                        else{
                            $rlt=array('status'=>0,'ota_name'=>'hostelworld','response_msg'=>'Rate updation fails');
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
                        $rate_update = RateUpdateLog::where('rate_plan_log_id',$rateplanId)->update(['block_status'=>0]);
                        $rlt=array('status'=>0,'ota_name'=>'hostelworld','response_msg'=>"Rateplan should be sync");
                    }
                }
        if(empty($rlt)){
            $rlt=array('status'=>0,'ota_name'=>'hostelworld','response_msg'=>"Rateplan should be mapped");
        }
        return $rlt;
    }
    public function updateLOS($los_data,$los_url,$consumer_key,$consumer_signature){
        $headers = array(
            "Content-Type: application/json",
            "consumer_key:".$consumer_key,
            "consumer_signature:".$consumer_signature
        );
        $curl = curl_init();
        curl_setopt_array($curl, array(
        CURLOPT_URL => $los_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_POSTFIELDS =>$los_data,
        CURLOPT_HTTPHEADER =>$headers,
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }
}