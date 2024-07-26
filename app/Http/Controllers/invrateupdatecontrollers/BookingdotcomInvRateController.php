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
 * This controller is used for Bookingdotcom single,bulk,sync and block of inv and rate
 * @auther ranjit
 * created date 28/02/19.
 * modification due to display problem @ 12/03/19 by ranjit(new model added)
 */
class BookingdotcomInvRateController extends Controller
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
        $flag                           = '';
        $count                          = 0;
        $rlt                            = array();
        $inventory_id                   = array();
       
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
            $endDate                    = date('Y-m-d',strtotime($inv['date'].'+1day'));
            $inventory_los              = $inv['los'];
            $room_qtys                  = $inv['no_of_rooms'];

            $ratePlanTypeSynchronizeData  = CmOtaRatePlanSynchronize::
            select('*')
            ->where('ota_room_type_id', '=' ,$room_code)
            ->where('ota_type_id', '=' ,$ota_id)
            ->where('hotel_id',$hotel_id)
            ->first();

            $inv['room_type_id']       = $inventory['room_type_id'];
            $inv['block_status']       = $inv["block_status"];
            $inv['channel']            = 'Booking.com';
            $inv['ota_id']             = $ota_id;
            $inv['user_id']            = $bucket_data["bucket_user_id"];
            $inv['client_ip']          = $bucket_data["bucket_client_ip"];
            $inv['hotel_id']           = $hotel_id;
            $inv['date_from']          = $inv['date'];
            $inv['date_to']            = $inv['date'];
            $inv['multiple_days']      = '{"Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1","Sun":"1"}';

            try{
                $otainventory->fill($inv)->save();
                $current_inv = array(
                    "hotel_id"      =>$inv['hotel_id'],
                    "room_type_id"  =>$inv['room_type_id'],
                    "ota_id"        =>$inv['ota_id'],
                    "stay_day"      =>$inv['date'],
                    "no_of_rooms"   =>$inv['no_of_rooms'],
                    "ota_name"      =>"Booking.com"
                );
                $cur_inv = DynamicPricingCurrentInventory::updateOrInsert(
                    [
                        'hotel_id' => $inv['hotel_id'],
                        'room_type_id' => $inv['room_type_id'],
                        'ota_id'=>$inv['ota_id'],
                        'stay_day'=>$inv['date'],
                        'ota_name'=>"Booking.com"
                    ],
                $current_inv
                );
            }
            catch(Exception $e){

            }
            $inventory_id[] = $otainventory->inventory_id;
            if($room_code && $ratePlanTypeSynchronizeData)
            {
                $rateplan_code = $ratePlanTypeSynchronizeData->ota_rate_plan_id;
                $flag=1;
                if($inv['block_status']==0)
                {
                    if($room_qtys >= 0)
                    {

                        $xml = '<room id="'.$room_code.'">
                        <date from="'.$startDate.'" to="'.$endDate.'">
                        <roomstosell>'.$room_qtys.'</roomstosell>
                        <rate id="'.$rateplan_code.'"/>
                        <minimumstay>'.$inventory_los.'</minimumstay>
                        <closed>0</closed>
                        </date>
                        </room>';
                        $xml_data.= $xml;
                    }
                }
                else
                {
                    $xml=' <room id="'.$room_code.'" >
                    <date from="'.$startDate.'" to="'.$endDate.'">
                    <rate id="'.$rateplan_code.'"/>
                    <closed>1</closed>
                    </date>
                    </room>';
                    $xml_data.= $xml;
                }
            }
            else{
                $flag=0;
            }
        }
        $count  =   $count+$flag;
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
            $url         	                = $commonUrl.'availability';

            $bookingdotcom_xml ='<?xml version="1.0" encoding="UTF-8" ?>
            <request>
            <username>'.$username.'</username>
            <password>'.$password.'</password>
            <hotel_id>'.$ota_hotel_code.'</hotel_id>
            <version>1.0</version>';
            $bookingdotcom_xml.=$xml_all_data.' </request>';
            $headers = array ('Content-Type: application/xml');
            $log_request_msg = $bookingdotcom_xml;
            $logModel->fill($log_data)->save();//saving pre logdata

            $result=$this->getdata_curlreq->cUrlCall($url,$headers,$bookingdotcom_xml);//for curl call
            $resultXml=simplexml_load_string($result);
            if($resultXml){
				if(strpos($result, '<error>' ) !== false){
					DB::table('log_table')->where('id', $logModel->id)
					->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                    $logpath = storage_path("logs/bookingDotcomInventoryUpdate.log".date("Y-m-d"));
                    $logfile = fopen($logpath, "a+");
                    fwrite($logfile,"Processing starts at: ".date("Y-m-d H:i:s")."\n");
                    fclose($logfile);
                    foreach($inventory_id as $value){
                        $dlt_status = OtaInventory::where('inventory_id',$value)->delete();
                    }
                    $logfile = fopen($logpath, "a+");
                    fwrite($logfile,"inventory request: ".$log_request_msg."\n");
                    fwrite($logfile,"inventory response: ".$result."\n");
                    fwrite($logfile,"log table id: ".$logModel->id."\n");
                    fwrite($logfile,"hotel id: ".$hotel_id."\n");
                    fwrite($logfile,"ota id: ".$ota_id."\n");
                    fwrite($logfile,"Processing ends at: ".date("Y-m-d H:i:s")."\n");
                    fclose($logfile);
					$rlt=array('status'=>0,'ota_name'=>'booking.com','response_msg'=>$result);
				}
				else if(strpos($result, '<warning>' ) !== false){
					DB::table('log_table')->where('id', $logModel->id)
					->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                    $logpath = storage_path("logs/bookingDotcomInventoryUpdate.log".date("Y-m-d"));
                    $logfile = fopen($logpath, "a+");
                    fwrite($logfile,"Processing starts at: ".date("Y-m-d H:i:s")."\n");
                    fclose($logfile);
                    foreach($inventory_id as $value){
                        $dlt_status = OtaInventory::where('inventory_id',$value)->delete();
                    }
                    $logfile = fopen($logpath, "a+");
                    fwrite($logfile,"inventory request: ".$log_request_msg."\n");
                    fwrite($logfile,"inventory response: ".$result."\n");
                    fwrite($logfile,"log table id: ".$logModel->id."\n");
                    fwrite($logfile,"hotel id: ".$hotel_id."\n");
                    fwrite($logfile,"ota id: ".$ota_id."\n");
                    fwrite($logfile,"Processing ends at: ".date("Y-m-d H:i:s")."\n");
                    fclose($logfile);
					$rlt=array('status'=>0,'ota_name'=>'booking.com','response_msg'=>$result);
                }
            }
            else{
                if(strpos($result, '<ok>' ) !== false)
                {
                    DB::table('log_table')->where('id', $logModel->id)
                    ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                    $rlt=array('status'=>1,'ota_name'=>'booking.com','response_msg'=>"updated sucessfully");
                }
                else{
                    $rlt=array('status'=>0,'ota_name'=>'booking.com','response_msg'=>$result);
                    $logpath = storage_path("logs/bookingDotcomInventoryUpdate.log".date("Y-m-d"));
                    $logfile = fopen($logpath, "a+");
                    fwrite($logfile,"Processing starts at: ".date("Y-m-d H:i:s")."\n");
                    fclose($logfile);
                    foreach($inventory_id as $value){
                        $dlt_status = OtaInventory::where('inventory_id',$value)->delete();
                    }
                    $logfile = fopen($logpath, "a+");
                    fwrite($logfile,"inventory response: ".$result."\n");
                    fwrite($logfile,"hotel id: ".$hotel_id."\n");
                    fwrite($logfile,"ota id: ".$ota_id."\n");
                    fwrite($logfile,"Processing ends at: ".date("Y-m-d H:i:s")."\n");
                    fclose($logfile);
                }
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
                $logpath = storage_path("logs/bookingDotcomInventoryUpdate.log".date("Y-m-d"));
                $logfile = fopen($logpath, "a+");
                fwrite($logfile,"Processing starts at: ".date("Y-m-d H:i:s")."\n");
                fclose($logfile);
                foreach($inventory_id as $value){
                    $dlt_status = OtaInventory::where('inventory_id',$value)->delete();
                }
                $logfile = fopen($logpath, "a+");
                fwrite($logfile,"inventory response: Roomrate type is not mapped \n");
                fwrite($logfile,"hotel id: ".$hotel_id."\n");
                fwrite($logfile,"ota id: ".$ota_id."\n");
                fwrite($logfile,"Processing ends at: ".date("Y-m-d H:i:s")."\n");
                fclose($logfile);
                $rlt=array('status'=>0,'ota_name'=>'booking.com','response_msg'=>"Roomtype should be sync");
            }
            if(sizeof($rlt)==0){
                $rlt=array('status'=>0,'ota_name'=>'booking.com','response_msg'=>"Roomtype should be mapped");
            }
        return $rlt;
    }
    public function blockInventoryUpdate($bucket_data,$room_type_id,$data,$auth_parameter,$commonUrl)
    {
        $blocklog                       = new LogTable();
        $hotel_id                       = $bucket_data["bucket_hotel_id"];
        $ota_id                         = $bucket_data["bucket_ota_id"];
        $ota_hotel_code                 = $bucket_data["bucket_ota_hotel_code"];
        $data['channel']                = 'Booking.com';
        $inventoryId                    = array();
        $xml_data                       = '';
        $flag                           = '';
        $return_resp                    = array();
            $otainventory               = new OtaInventory();//used for insert in to ota inv table
            $data['room_type_id']       = $room_type_id;
            $data['ota_id']             = $ota_id;
            $data['user_id']            = $bucket_data["bucket_user_id"];
            $data['client_ip']          = $bucket_data["bucket_client_ip"];
            $data['hotel_id']           = $hotel_id;
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
                            "no_of_rooms"   =>0,
                            "block_status"  =>1,
                            "ota_name"      =>"Booking.com"
                        );
                        $cur_inv = DynamicPricingCurrentInventory::updateOrInsert(
                            [
                                'hotel_id' => $data['hotel_id'],
                                'room_type_id' => $data['room_type_id'],
                                'ota_id'=>$data['ota_id'],
                                'stay_day'=>$index,
                                'ota_name'=>"Booking.com"
                            ],
                            $current_inv
                        );
                    }
                }
                catch(\Exception $e){
                   $success_flag=0;
                }
                $inventoryId   = $otainventory->inventory_id;
            if($success_flag)
            {
                
                $hotel_id       = $otainventory->hotel_id;
                $room_code = DB::table('cm_ota_room_type_synchronize')
                                ->where('hotel_id', '=', $hotel_id)
                                ->where('room_type_id', '=', $room_type_id)
                                ->where('ota_type_id', '=', $ota_id)
                                ->value('ota_room_type');
                $startDate      = $data['date_from'];
                $endDate        = date('Y-m-d',strtotime($data['date_to'].'+1day'));
                $ratePlanTypeSynchronizeData  = CmOtaRatePlanSynchronize::
                select('*')
                ->where('ota_room_type_id', '=' ,$room_code)
                ->where('ota_type_id', '=' ,$ota_id)
                ->where('hotel_id',$hotel_id)
                ->get();

                foreach($ratePlanTypeSynchronizeData as $rtsd)
                {
                    if($room_code && $rtsd )
                    {
                    $rateplan_code = $rtsd->ota_rate_plan_id;
                    $flag = 1;
                    $xml=' <room id="'.$room_code.'" >
                    <date from="'.$startDate.'" to="'.$endDate.'">
                    <rate id="'.$rateplan_code.'"/>
                    <closed>1</closed>
                    </date>
                    </room>';

                    $xml_data.=$xml;
                    }
                    else{
                    $flag = 0;
                    }
                }
            }
        if($flag == 1)
        {
            $username 			= trim($auth_parameter->username);
			$password 		    = trim($auth_parameter->password);

            $log_data = [
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

            $blocklog->fill($log_data)->save();
            $bookingdotcom_xml ='<?xml version="1.0" encoding="UTF-8" ?>
            <request>
            <username>'.$username.'</username>
            <password>'.$password.'</password>
            <hotel_id>'.$ota_hotel_code.'</hotel_id>
            <version>1.0</version>';
            $bookingdotcom_xml.= $xml_data.' </request>';
            $url         	   = $commonUrl.'availability';
            $headers           = array ('Content-Type: application/xml');
            $response=$this->getdata_curlreq->cUrlCall($url,$headers,$bookingdotcom_xml);//for curl call
            $resultXml=simplexml_load_string($response);
            $response_string="";
            $requestXml=$bookingdotcom_xml;
            $success_status=true;
            if(strpos($response, '<error>' ) !== false){
                $success_status =$success_status && false;
                $response_string.=$response;
             }
            else if(strpos($response, '<warning>' ) !== false){
                $success_status =$success_status && false;
                $response_string.=$response;
            }
            else{
                if(strpos($response, '<ok>' ) !== false)
                {
                    $success_status =$success_status && true;
                    $response_string.=$response;
                }
            }
            if($success_status){
                    DB::table('log_table')->where('id', $blocklog->id)
                    ->update(['status' => 1,'request_msg'=>$requestXml,'request_url'=>$url,'response_msg'=>$response_string]);
                $return_resp=array('status' => 1,'ota_name'=>'booking.com','response_msg'=> ' blocked successfully');
                return $return_resp;
            }else{
                    DB::table('log_table')->where('id', $blocklog->id)
                    ->update(['status' => 0,'request_msg'=>$requestXml,'request_url'=>$url,'response_msg'=>$response_string]);
                    $logpath = storage_path("logs/bookingDotcomInventoryUpdate.log".date("Y-m-d"));
                    $logfile = fopen($logpath, "a+");
                    fwrite($logfile,"Processing starts at: ".date("Y-m-d H:i:s")."\n");
                    fclose($logfile);
                     $dlt_status = OtaInventory::where('inventory_id',$inventoryId)->delete();
                     $logfile = fopen($logpath, "a+");
                    fwrite($logfile,"inventory request: ".$requestXml."\n");
                    fwrite($logfile,"inventory response: ".$response_string."\n");
                    fwrite($logfile,"log table id: ".$blocklog->id."\n");
                    fwrite($logfile,"hotel id: ".$hotel_id."\n");
                    fwrite($logfile,"ota id: ".$ota_id."\n");
                    fwrite($logfile,"Processing ends at: ".date("Y-m-d H:i:s")."\n");
                    fclose($logfile);
                $return_resp=array('status' => 0,'ota_name'=>'booking.com','response_msg'=> $response_string);
                return $return_resp;
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
            $logpath = storage_path("logs/bookingDotcomInventoryUpdate.log".date("Y-m-d"));
            $logfile = fopen($logpath, "a+");
            fwrite($logfile,"Processing starts at: ".date("Y-m-d H:i:s")."\n");
            fclose($logfile);
             $dlt_status = OtaInventory::where('inventory_id',$inventoryId)->delete();
             $logfile = fopen($logpath, "a+");
             fwrite($logfile,"inventory response: Roomtype should be Sync \n");
             fwrite($logfile,"hotel id: ".$hotel_id."\n");
             fwrite($logfile,"ota id: ".$ota_id."\n");
             fwrite($logfile,"Processing ends at: ".date("Y-m-d H:i:s")."\n");
             fclose($logfile);
            $return_resp=array('status' => 0,'ota_name'=>'booking.com','response_msg'=> 'Roomtype should be Sync');
        }
        if(sizeof($return_resp)==0){
            $return_resp=array('status'=>0,'ota_name'=>'booking.com','response_msg'=>"Roomtype should be mapped");
        }
        return $return_resp;
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
        $flag                           = '';
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
                $endDate                    = date('Y-m-d',strtotime($inv['date'].'+1day'));
                $inventory_los              = $inv['los'];
                $room_qtys                  = $inv['no_of_rooms'];

                $ratePlanTypeSynchronizeData  = CmOtaRatePlanSynchronize::
                select('*')
                ->where('ota_room_type_id', '=' ,$room_code)
                ->where('ota_type_id', '=' ,$ota_id)
                ->where('hotel_id',$hotel_id)
                ->first();

                $inv['room_type_id']       = $invs['room_type_id'];
                $inv['block_status']       = 0;
                $inv['channel']            = 'Booking.com';
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
                        "ota_name"      =>"Booking.com"
                    );
                    $cur_inv = DynamicPricingCurrentInventory::updateOrInsert(
                        [
                            'hotel_id' => $inv['hotel_id'],
                            'room_type_id' => $inv['room_type_id'],
                            'ota_id'=>$inv['ota_id'],
                            'stay_day'=>$inv['date'],
                            'ota_name'=>"Booking.com"
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
                    if($room_code && $ratePlanTypeSynchronizeData)
                    {
                        $rateplan_code = $ratePlanTypeSynchronizeData->ota_rate_plan_id;
                        $flag=1;
                        if($room_qtys >= 0)
                        {
                            $xml = '<room id="'.$room_code.'">
                            <date from="'.$startDate.'" to="'.$endDate.'"> 
                            <roomstosell>'.$room_qtys.'</roomstosell>
                            <rate id="'.$rateplan_code.'"/>
                            <minimumstay>'.$inventory_los.'</minimumstay>
                            <closed>0</closed>
                            </date>
                            </room>';
                            $xml_data.= $xml;
                        }
                    }
                    else{
                        $flag=0;
                    }
                }
            }
            $count  =   $count+$flag;
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
            $url         	                = $commonUrl.'availability';
            $bookingdotcom_xml ='<?xml version="1.0" encoding="UTF-8" ?>
            <request>
            <username>'.$username.'</username>
            <password>'.$password.'</password>
            <hotel_id>'.$ota_hotel_code.'</hotel_id>
            <version>1.0</version>';
            $bookingdotcom_xml.=$xml_all_data.' </request>';
            $headers = array ('Content-Type: application/xml');
            $log_request_msg = $bookingdotcom_xml;
            $logModel->fill($log_data)->save();//saving pre logdata

            $result=$this->getdata_curlreq->cUrlCall($url,$headers,$bookingdotcom_xml);//for curl call
            $resultXml=simplexml_load_string($result);
            if($resultXml){
				if(strpos($result, '<error>' ) !== false){
					DB::table('log_table')->where('id', $logModel->id)
					->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                    $logpath = storage_path("logs/bookingDotcomInventoryUpdate.log".date("Y-m-d"));
                    $logfile = fopen($logpath, "a+");
                    fwrite($logfile,"Processing starts at: ".date("Y-m-d H:i:s")."\n");
                    fclose($logfile);
                    foreach($inventoryId as $value){
                        $dlt_status = OtaInventory::where('inventory_id',$value)->delete();
                    }
                    $logfile = fopen($logpath, "a+");
                    fwrite($logfile,"inventory request: ".$log_request_msg."\n");
                    fwrite($logfile,"inventory response: ".$result."\n");
                    fwrite($logfile,"log table id: ".$logModel->id."\n");
                    fwrite($logfile,"hotel id: ".$hotel_id."\n");
                    fwrite($logfile,"ota id: ".$ota_id."\n");
                    fwrite($logfile,"Processing ends at: ".date("Y-m-d H:i:s")."\n");
                    fclose($logfile);
					$rlt=array('status'=>0,'ota_name'=>'booking.com','response_msg'=>$result);
				}
				else if(strpos($result, '<warning>' ) !== false){
					DB::table('log_table')->where('id', $logModel->id)
					->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                    $logpath = storage_path("logs/bookingDotcomInventoryUpdate.log".date("Y-m-d"));
                    $logfile = fopen($logpath, "a+");
                    fwrite($logfile,"Processing starts at: ".date("Y-m-d H:i:s")."\n");
                    fclose($logfile);
                    foreach($inventoryId as $value){
                        $dlt_status = OtaInventory::where('inventory_id',$value)->delete();
                    }
                    $logfile = fopen($logpath, "a+");
                    fwrite($logfile,"inventory request: ".$log_request_msg."\n");
                    fwrite($logfile,"inventory response: ".$result."\n");
                    fwrite($logfile,"log table id: ".$logModel->id."\n");
                    fwrite($logfile,"hotel id: ".$hotel_id."\n");
                    fwrite($logfile,"ota id: ".$ota_id."\n");
                    fwrite($logfile,"Processing ends at: ".date("Y-m-d H:i:s")."\n");
                    fclose($logfile);
					$rlt=array('status'=>0,'ota_name'=>'booking.com','response_msg'=>$result);
                }
            }
            else{
                if(strpos($result, '<ok>' ) !== false)
                {
                    DB::table('log_table')->where('id', $logModel->id)
                    ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                    $rlt=array('status'=>1,'ota_name'=>'booking.com','response_msg'=>"updated sucessfully");
                }
                else{
                    $rlt=array('status'=>0,'ota_name'=>'booking.com','response_msg'=>$result);
                    $logpath = storage_path("logs/bookingDotcomInventoryUpdate.log".date("Y-m-d"));
                    $logfile = fopen($logpath, "a+");
                    fwrite($logfile,"Processing starts at: ".date("Y-m-d H:i:s")."\n");
                    fclose($logfile);
                    foreach($inventoryId as $value){
                        $dlt_status = OtaInventory::where('inventory_id',$value)->delete();
                    }
                    $logfile = fopen($logpath, "a+");
                    fwrite($logfile,"inventory response: ".$result."\n");
                    fwrite($logfile,"hotel id: ".$hotel_id."\n");
                    fwrite($logfile,"ota id: ".$ota_id."\n");
                    fwrite($logfile,"Processing ends at: ".date("Y-m-d H:i:s")."\n");
                    fclose($logfile);
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
                "comment"			 => "Roomrate type is not mapped"
                ];
                $logModel->fill($log_data)->save();
                $logpath = storage_path("logs/bookingDotcomInventoryUpdate.log".date("Y-m-d"));
                $logfile = fopen($logpath, "a+");
                fwrite($logfile,"Processing starts at: ".date("Y-m-d H:i:s")."\n");
                fclose($logfile);
                foreach($inventoryId as $value){
                    $dlt_status = OtaInventory::where('inventory_id',$value)->delete();
                }
                $logfile = fopen($logpath, "a+");
                fwrite($logfile,"inventory response: Roomtype should be sync \n");
                fwrite($logfile,"hotel id: ".$hotel_id."\n");
                fwrite($logfile,"ota id: ".$ota_id."\n");
                fwrite($logfile,"Processing ends at: ".date("Y-m-d H:i:s")."\n");
                fclose($logfile);
                $rlt=array('status'=>0,'ota_name'=>'booking.com','response_msg'=>"Roomtype should be sync");
        }
        if(sizeof($rlt)==0){
            $rlt=array('status'=>0,'ota_name'=>'booking.com','response_msg'=>"Roomtype should be mapped");
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
        $data['channel']                = 'Booking.com';
        $rlt                            = array();
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
                            "ota_name"      =>"Booking.com"
                        );
                        $cur_inv = DynamicPricingCurrentInventory::updateOrInsert(
                            [
                                'hotel_id' => $data['hotel_id'],
                                'room_type_id' => $data['room_type_id'],
                                'ota_id'=>$data['ota_id'],
                                'stay_day'=>$index,
                                'ota_name'=>"Booking.com"
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
            $startDate          = $data['date_from'];
            $endDate            = date('Y-m-d',strtotime($data['date_to'].'+1day'));
            $inventory_los      = $data['los'];
            $room_qtys          = $data['no_of_rooms'];

            $ratePlanTypeSynchronizeData  = CmOtaRatePlanSynchronize::
            select('*')
            ->where('ota_room_type_id', '=' ,$room_code)
            ->where('ota_type_id', '=' ,$ota_id)
            ->where('hotel_id',$hotel_id)
            ->get();

            if($room_code && $ratePlanTypeSynchronizeData)
            {
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

                $username 						= trim($auth_parameter->username);
                $password 						= trim($auth_parameter->password);
                $url         	                = $commonUrl.'availability';
                $txm='';
                $bookingdotcom_xml = '<?xml version="1.0" encoding="UTF-8" ?>
                <request>
                <username>'.$username.'</username>
                <password>'.$password.'</password>
                <hotel_id>'.$ota_hotel_code.'</hotel_id>
                <version>1.0</version>';
                 foreach($ratePlanTypeSynchronizeData as $rtsd)
                {
                    $rateplan_code = $rtsd->ota_rate_plan_id;
                    $txm.='<room id="'.$room_code.'">
                <date from="'.$startDate.'" to="'.$endDate.'">
                <roomstosell>'.$room_qtys.'</roomstosell>
                <rate id="'.$rateplan_code.'"/>
                <minimumstay>'.$inventory_los.'</minimumstay>
                <closed>0</closed>
                </date>
                </room>';
                }

                $bookingdotcom_xml=$bookingdotcom_xml.$txm.'</request>';
                $headers = array ('Content-Type: application/xml');
                $log_request_msg = $bookingdotcom_xml;
                $logModel->fill($log_data)->save();//saving pre logdata

                $result=$this->getdata_curlreq->cUrlCall($url,$headers,$bookingdotcom_xml);//for curl call
                $resultXml=simplexml_load_string($result);
                if(strpos($result, '<error>' ) !== false){
                    DB::table('log_table')->where('id', $logModel->id)
                    ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                    $logpath = storage_path("logs/bookingDotcomInventoryUpdate.log".date("Y-m-d"));
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
                    $rlt=array('status'=>0,'ota_name'=>'booking.com','response_msg'=>$result);
                }
                else if(strpos($result, '<warning>' ) !== false){
                    DB::table('log_table')->where('id', $logModel->id)
                    ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                    $logpath = storage_path("logs/bookingDotcomInventoryUpdate.log".date("Y-m-d"));
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
                    $rlt=array('status'=>0,'ota_name'=>'booking.com','response_msg'=>$result);
                }
                if(strpos($result, '<ok>' ) !== false)
                {
                    DB::table('log_table')->where('id', $logModel->id)
                    ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                    $rlt=array('status'=>1,'ota_name'=>'booking.com','response_msg'=>"updated sucessfully");
                }
                else{
                    $rlt=array('status'=>0,'ota_name'=>'booking.com','response_msg'=>$result);
                    $logpath = storage_path("logs/bookingDotcomInventoryUpdate.log".date("Y-m-d"));
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
                    "comment"			 => " This roomrate type is not mapped"
                    ];
                    $logModel->fill($log_data)->save();//saving pre logdata
                    $logpath = storage_path("logs/bookingDotcomInventoryUpdate.log".date("Y-m-d"));
                    $logfile = fopen($logpath, "a+");
                    fwrite($logfile,"Processing starts at: ".date("Y-m-d H:i:s")."\n");
                    fclose($logfile);
                    $dlt_status = OtaInventory::where('inventory_id',$invtefid)->delete();
                    $logfile = fopen($logpath, "a+");
                    fwrite($logfile,"inventory response: This roomrate type is not mapped \n");
                    fwrite($logfile,"hotel id: ".$hotel_id."\n");
                    fwrite($logfile,"ota id: ".$ota_id."\n");
                    fwrite($logfile,"Processing ends at: ".date("Y-m-d H:i:s")."\n");
                    fclose($logfile);
                    $rlt=array('status'=>0,'ota_name'=>'booking.com','response_msg'=>'Roomtype should be sync');
            }
            if(sizeof($rlt)==0){
                $rlt=array('status'=>0,'ota_name'=>'booking.com','response_msg'=>"Roomtype should be mapped");
            }
            return $rlt;
        }
    }
    public function rateSyncUpdate($rate_bucket_data,$rates_data,$auth_parameter,$commonUrl,$from_date,$to_date)
    {
        $cmOtaRatePlanSynchronizeModel = new CmOtaRatePlanSynchronize();
        $roomTypeModel                  = new MasterRoomType();
        $logModel                       = new RateUpdateLog();
       
        $xml_all_data                   = '';
        $xml_all_data1                  = '';
        $multiple_days                  = '{"Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1","Sun":"1"}';
        $hotel_id                       = $rate_bucket_data['bucket_hotel_id'];
        $ota_id                         = $rate_bucket_data['bucket_ota_id'];
        $ota_hotel_code                 = $rate_bucket_data['bucket_ota_hotel_code'];
        $xml_data                       = '';
        $xml_data1                      = '';
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
            $rateplan_multiple_days_data = $this->getdata_curlreq->getDaysUpdate($multiple_days,"Booking.com");
            $currency=$this->getdata_curlreq->getCurrency($hotel_id);
            $extra_adult_price	= $rates['extra_adult_price'];
            $extra_child_price	= $rates['extra_child_price'];
            $rateplan_bar_price          = $rates['bar_price'];
            $occupency=$this->getdata_curlreq->decideOccupencyPrice($max_adult,$rates['bar_price'],$rates['multiple_occupancy']);
            $rateplan_single_price=0;
            $rateplan_double_price=0;
            $rateplan_triple_price=0;
            if($occupency)
            {
                $rateplan_single_price       = $occupency[0];
                $rateplan_double_price       = isset($occupency[1])?$occupency[1]:0;
                $rateplan_triple_price       = isset($occupency[2])?$occupency[2]:0;
            }
            $result = $cmOtaRatePlanSynchronizeModel->getOtaRoomIdFromRatePlanSynch($rates['room_type_id'],$ota_id,$rates['rate_plan_id']);
            if(isset($result[0])){
                $room_code                   = $result[0]['ota_room_type_id'];
                $rate_code                   = $result[0]['ota_rate_plan_id'];
            }
            else{
                continue;
            }
            $startDate                   =  $rates['date'];
            $endDate                     =  date('Y-m-d',strtotime($rates['date'].'+1day'));
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
            $rates['channel']               = 'Booking.com';
            $rates['multiple_occupancy']    = json_encode($rates['multiple_occupancy']);
            try{
                $otaRatePlan->fill($rates)->save();  
                $base_rate     = new BaseRate();
                $insertBaseRate = $base_rate->fill($rates)->save();
            }
            catch(Exception $e){

            }
            if($rate_code && $room_code)
            {
                $flag=1;
                $xml='<room id="'.$room_code.'">
                <date from="'.$startDate.'" to="'.$endDate.'">
                <currencycode>'.$currency.'</currencycode>
                    <rate id="'.$rate_code.'"/>';
                if($rateplan_single_price!=0){
                    $xml .='<price1>'.$rateplan_single_price.'</price1>';
                    }
                if($rateplan_bar_price!=0){
                $xml .='<price>'.$rateplan_bar_price.'</price>';
                }
                $xml .='</date>
                </room>';
                $xml_data.= $xml;
            }
            else{
                $flag=0;
            }
        }
        $xml_all_data.= $xml_data;
        $xml_all_data1.= $xml_data1;
        if($flag==1)
        {
            $headers = array (
                //Regulates versioning of the XML interface for the API
                'Content-Type: application/xml'
                );
            $username = trim($auth_parameter->username);
            $password = trim($auth_parameter->password);
            $url                        = $commonUrl.'availability';
            $log_data               = [
                "action_id"          => 2,
                "hotel_id"           => $hotel_id,
                "ota_id"      	     => $ota_id,
                "rate_ref_id"        => $rate_bucket_data['bucket_rate_plan_log_table_id'],
                "user_id"            => $rate_bucket_data['bucket_user_id'],
                "request_msg"        => '',
                "response_msg"       => '',
                "request_url"        => '',
                "status"             => 2,
                "ip"         => $rate_bucket_data['bucket_client_ip'],
                "comment"	=> "Processing for update "
                ];
            $bookingdotcom_rate_xml ='<?xml version="1.0" encoding="UTF-8" ?>
            <request>
            <username>'.$username.'</username>
            <password>'.$password.'</password>
            <hotel_id>'.$ota_hotel_code.'</hotel_id>
            <version>1.0</version>';
            $bookingdotcom_rate_xml.= $xml_all_data.'</request>';
            $logModel->fill($log_data)->save();
            $ota_rlt=$this->getdata_curlreq->cUrlCall($url,$headers,$bookingdotcom_rate_xml);//for curl call
            $resultXml=simplexml_load_string($ota_rlt);
            $resp=$ota_rlt;
            $log_request_msg = $bookingdotcom_rate_xml;

            if($resultXml){
                $array_data = json_decode(json_encode(simplexml_load_string($ota_rlt)), true);
                if(strpos($ota_rlt, '<error>' ) !== false){
                DB::table('rate_update_logs')->where('id', $logModel->id)
                ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$resp]);
                $rlt=array('status'=>0,'ota_name'=>'booking.com','response_msg'=>$resp);
                                }else if(strpos($ota_rlt, '<error>' ) !== false){
                                    DB::table('rate_update_logs')->where('id', $logModel->id)
                ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$resp]);
                $rlt=array('status'=>0,'ota_name'=>'booking.com','response_msg'=>$resp);
                                }
                                else if(strpos($ota_rlt, '<warning>' ) !== false){
                DB::table('rate_update_logs')->where('id', $logModel->id)
                ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$resp]);
                $rlt=array('status'=>0,'ota_name'=>'booking.com','response_msg'=>$resp);
                                    }
                }
            else{
                if(strpos($ota_rlt, '<ok>' ) !== false)
                {
                    DB::table('rate_update_logs')->where('id', $logModel->id)
                    ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$resp]);
                    $rlt=array('status'=>1,'ota_name'=>'booking.com','response_msg'=>"updated sucessfully");
                }
                else{
                    $rlt=array('status'=>0,'ota_name'=>'booking.com','response_msg'=>$resp);
                }
            }
        }
        else{
            $log_data               = [
                "action_id"          => 2,
                "hotel_id"           => $hotel_id,
                "ota_id"      	=> $ota_id,
                "rate_ref_id"        => $rate_bucket_data['bucket_rate_plan_log_table_id'],
                "user_id"            => $rate_bucket_data['bucket_user_id'],
                "request_msg"        => '',
                "response_msg"       => '',
                "request_url"        => '',
                "status"         => 2,
                "ip"         => $rate_bucket_data['bucket_client_ip'],
                "comment"	=> "Roomrate type is not mapped"
                ];
            $logModel->fill($log_data)->save();
            $rlt=array('status'=>0,'ota_name'=>'booking.com','response_msg'=>"Rateplan should be sync");
        }
        if(sizeof($rlt)==0){
            $rlt=array('status'=>0,'ota_name'=>'booking.com','response_msg'=>"Rateplan should be mapped");
        }
        return $rlt;
    }
    public function singleRateUpdate($rate_bucket_data,$rates_data,$auth_parameter,$commonUrl)
    {
        $cmOtaRatePlanSynchronizeModel 	= new CmOtaRatePlanSynchronize();
        $roomTypeModel                  = new MasterRoomType();
        $logModel                       = new RateUpdateLog();
        $multiple_days                  = '{"Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1","Sun":"1"}';
        $hotel_id                       = $rate_bucket_data['bucket_hotel_id'];
        $ota_id                         = $rate_bucket_data['bucket_ota_id'];
        $ota_hotel_code                 = $rate_bucket_data['bucket_ota_hotel_code'];
        $rateplanId                     = array();
        $xml_all_data                   = '';
        $count                          = 0;
        $rlt                            =  array();
        $flag="";
        foreach($rates_data as $rate)
        {
            $xml_data = '';
            $xml_data1 = '';
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
                $rates['channel']               = 'Booking.com';
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
                $rateplan_multiple_days_data = $this->getdata_curlreq->getDaysUpdate($rates['multiple_days'],"Booking.com");
                $currency=$this->getdata_curlreq->getCurrency($hotel_id);
                $rateplan_bar_price          = $rates['bar_price'];
                $occupency=$this->getdata_curlreq->decideOccupencyPrice($max_adult,$rates['bar_price'],$rates['multiple_occupancy']);
                $rateplan_single_price=0;
                $rateplan_double_price=0;
                $rateplan_triple_price=0;
                if($occupency)
                {
                    $rateplan_single_price       = $occupency[0];
                    $rateplan_double_price       = isset($occupency[1])?$occupency[1]:0;
                    $rateplan_triple_price       = isset($occupency[2])?$occupency[2]:0;
                }
                $extra_adult_price           = $rates['extra_adult_price'];
                if($extra_adult_price == ''){
                  $extra_adult_price = 0;
                }
                $getroom_details=DB::connection('kernel')->table('room_type_table')->select('max_people','max_room_capacity','extra_person')->where('room_type_id',$rates['room_type_id'])->first();
                $total_persons=$getroom_details->extra_person+$getroom_details->max_people;
                $getrate_details=DB::connection('kernel')->table('room_rate_plan')->select('extra_adult_price')->where('hotel_id', $hotel_id)->where('room_type_id',$rates['room_type_id'])->where('rate_plan_id',$rates['rate_plan_id'])->orderBy('room_rate_plan_id','DESC')->first();
                $result 					 = $cmOtaRatePlanSynchronizeModel->getOtaRoomIdFromRatePlanSynch($rates['room_type_id'],$ota_id,$rates['rate_plan_id']);
                $extra_persons=$getroom_details->max_room_capacity-$getroom_details->max_people;
                if(isset($result[0])){
                    $room_code                   = $result[0]['ota_room_type_id'];
                    $rate_code                   = $result[0]['ota_rate_plan_id'];
                }
                else{
                    continue;
                }
                $startDate                   =  $rates['from_date'];
                $endDate                     =  date('Y-m-d',strtotime($rates['to_date'].'+1day'));
                $rates['multiple_occupancy'] = json_encode($rates['multiple_occupancy']);
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
                    $rateplanId[]   = $otaRatePlan->rate_plan_log_id;
                    if($rate_code && $room_code)
                    {
                        $flag=1;
                        $xml='<room id="'.$room_code.'">
                        <date from="'.$startDate.'" to="'.$endDate.'">
                        <currencycode>'.$currency.'</currencycode>
                        <rate id="'.$rate_code.'" />';
                        if($rateplan_single_price!=0){
                            $xml .='<price1>'.$rateplan_single_price.'</price1>';
                            }
                        if($rateplan_bar_price!=0){
                        $xml .='<price>'.$rateplan_bar_price.'</price>';
                        }
                        $xml .='</date>
                        </room>';
                        $xml_data.= $xml;
                        if($total_persons && $extra_adult_price){
                            $xml1 = ' <room id="'.$room_code.'">
                                        <rates>
                                        <rate id="'.$rate_code.'" leading_occupancy="'.$getroom_details->max_people.'">';
                                        for($i=3;$i<=$total_persons;$i++){
                                            if($extra_adult_price != 0){
                                                $xml1.= '<occupancy persons="'.$i.'" additional="'.$extra_adult_price.'" />';
                                            }
                                        }
                            $xml1.='</rate>
                                        </rates>
                                    </room>';
                            $xml_data1.= $xml1;
                        }
                    }
                    else{
                        $flag=0;
                    }
                }
                else{
                    $rateplanId=0;
                }
            }
            $xml_all_data.= $xml_data;
        }
        $rateplanids                     = implode(',',$rateplanId);
        if($flag==1)
        {
            $headers = array (
                //Regulates versioning of the XML interface for the API
                'Content-Type: application/xml'
                );
            $username 						= trim($auth_parameter->username);
            $password 						= trim($auth_parameter->password);
            $url         	                = $commonUrl.'availability';
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
            $bookingdotcom_rate_xml ='<?xml version="1.0" encoding="UTF-8" ?>
            <request>
            <username>'.$username.'</username>
            <password>'.$password.'</password>
            <hotel_id>'.$ota_hotel_code.'</hotel_id>
            <version>1.0</version>';
            $bookingdotcom_rate_xml.= $xml_all_data.'</request>';
            $ota_rlt=$this->getdata_curlreq->cUrlCall($url,$headers,$bookingdotcom_rate_xml);//for curl call
            $ota_rlt1='';
            $bookingdotcom_extra_rate_xml='';
            if(strpos($ota_rlt, '<ok>' ) !== false){
                if($total_persons && $extra_adult_price){
                    $url1=$commonUrl.'derivedprices';
                    $bookingdotcom_extra_rate_xml='<?xml version="1.0" encoding="UTF-8" ?>
                    <request>
                    <username>'.$username.'</username>
                    <password>'.$password.'</password>
                    <rooms>';
                    $bookingdotcom_extra_rate_xml.=$xml_data1.'</rooms></request>';
                    $ota_rlt1=$this->getdata_curlreq->cUrlCall($url1,$headers,$bookingdotcom_extra_rate_xml);//for curl call
                }
            }
            $resultXml=simplexml_load_string($ota_rlt);
            // $resp=$ota_rlt;
            // $log_request_msg = $bookingdotcom_rate_xml;
            $resp=$ota_rlt.'  '.$ota_rlt1;
            $log_request_msg = $bookingdotcom_rate_xml.'  '.$bookingdotcom_extra_rate_xml;
            if($resultXml){
				$array_data = json_decode(json_encode(simplexml_load_string($ota_rlt)), true);
				if(strpos($ota_rlt, '<error>' ) !== false ){
					DB::table('rate_update_logs')->where('id', $logModel->id)
					->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$resp]);
					$rlt=array('status'=>0,'ota_name'=>'booking.com','response_msg'=>$resp);
                }else if(strpos($ota_rlt, '<error>' ) !== false){
                    DB::table('rate_update_logs')->where('id', $logModel->id)
					->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$resp]);
					$rlt=array('status'=>0,'ota_name'=>'booking.com','response_msg'=>$resp);
                }
                else if(strpos($ota_rlt, '<warning>' ) !== false){
					DB::table('rate_update_logs')->where('id', $logModel->id)
					->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$resp]);
					$rlt=array('status'=>0,'ota_name'=>'booking.com','response_msg'=>$resp);
                    }
                }
            else{
                if(strpos($ota_rlt, '<ok>' ) !== false)
                {
                    DB::table('rate_update_logs')->where('id', $logModel->id)
                    ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$resp]);
                    $rlt=array('status'=>1,'ota_name'=>'booking.com','response_msg'=>"updated sucessfully");
                }
                else{
                    $rlt=array('status'=>0,'ota_name'=>'booking.com','response_msg'=>$resp);
                }
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
            $rlt=array('status'=>0,'ota_name'=>'booking.com','response_msg'=>"Rateplan should be sync");
        }
        if(sizeof($rlt)==0){
            $rlt=array('status'=>0,'ota_name'=>'booking.com','response_msg'=>"Rateplan should be mapped");
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
        $xml_data1                      = '';
        $rlt                            = array();
        $multiple_days                  = '{"Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1","Sun":"1"}';
        $hotel_id                       = $rate_bucket_data['bucket_hotel_id'];
        $ota_id                         = $rate_bucket_data['bucket_ota_id'];
        $ota_hotel_code                 = $rate_bucket_data['bucket_ota_hotel_code'];
        $data['multiple_days']          = json_encode($data['multiple_days']);
        $flag="";
        $max_adult=$roomTypeModel->select('max_people')->where('room_type_id',$data['room_type_id'])->first()->max_people;
        $currency=$this->getdata_curlreq->getCurrency($hotel_id);
        $rateplan_multiple_days_data = $this->getdata_curlreq->getDaysUpdate($data['multiple_days'],"Booking.com");
        $occupency=$this->getdata_curlreq->decideOccupencyPrice($max_adult,$data['bar_price'],$data['multiple_occupancy']);
        $rateplan_single_price=0;
        $rateplan_double_price=0;
        $rateplan_triple_price=0;
        if($occupency)
        {
            $rateplan_single_price       = $occupency[0];
            $rateplan_double_price       = isset($occupency[1])?$occupency[1]:0;
            $rateplan_triple_price       = isset($occupency[2])?$occupency[2]:0;
        }
        $getroom_details=$roomTypeModel->select('max_people','extra_person','max_people')->where('room_type_id',$data['room_type_id'])->first();
        $total_persons=$getroom_details->extra_person+$getroom_details->max_people;
        $result 					 = $cmOtaRatePlanSynchronizeModel->getOtaRoomIdFromRatePlanSynch($data['room_type_id'],$ota_id,$data['rate_plan_id']);
       
        if(isset($result[0])){
            $room_code                   = $result[0]['ota_room_type_id'];
            $rate_code                   = $result[0]['ota_rate_plan_id'];
        }
        else{
            $rlt=array('status'=>0,'ota_name'=>'booking.com','response_msg'=>"Room type or Rateplan should be sync");
            return $rlt;
        }
        $startDate                   = date('Y-m-d',strtotime($data['from_date']));
        $extra_adult_price           = $data['extra_adult_price'];
        if($extra_adult_price == ''){
          $extra_adult_price = 0;
        }
        $endDate                     = date('Y-m-d',strtotime($data['to_date'].'+1day'));
        $data['multiple_occupancy']  = json_encode($data['multiple_occupancy']);
        $rateplan_bar_price          = $data['bar_price'];
        $data['channel']             = 'Booking.com';

        $data['from_date']           = date('Y-m-d',strtotime($data['from_date']));
        $data['to_date']             = date('Y-m-d',strtotime($data['to_date']));
        $data['extra_adult_price']   = $extra_adult_price;
        $data['extra_child_price']   = 0;
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
            
            if($result && $room_code)
            {
                $flag=1;
                
                $xml='<room id="'.$room_code.'">
                <date from="'.$startDate.'" to="'.$endDate.'">
                <currencycode>'.$currency.'</currencycode>
                <rate id="'.$rate_code.'"/>';
                if($rateplan_bar_price!=0){
                    $xml .='<price>'.$rateplan_bar_price.'</price>';
                    }
                if($rateplan_single_price!=0){
                    $xml .='<price1>'.$rateplan_single_price.'</price1>';
                    }
                
                $xml .='</date>
                </room>';
                $xml_data.= $xml;
                if($total_persons && $extra_adult_price && $extra_adult_price >0){
                    $xml1 = ' <room id="'.$room_code.'">
                                <rates>
                                <rate id="'.$rate_code.'" leading_occupancy="'.$getroom_details->max_people.'">';
                                for($i=3;$i<=$total_persons;$i++){
                                    if($extra_adult_price != 0){
                                        $xml1.= '<occupancy persons="'.$i.'" additional="'.$extra_adult_price.'" />';
                                    }
                                }
                    $xml1.='</rate>
                                </rates>
                            </room>';
                    $xml_data1.= $xml1;
                }
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
            $url         	                = $commonUrl.'availability';
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
            $bookingdotcom_rate_xml ='<?xml version="1.0" encoding="UTF-8" ?>
            <request>
            <username>'.$username.'</username>
            <password>'.$password.'</password>
            <hotel_id>'.$ota_hotel_code.'</hotel_id>
            <version>1.0</version>';
            $bookingdotcom_rate_xml.= $xml_data.'</request>';
            $logModel->fill($log_data)->save();
            $ota_rlt=$this->getdata_curlreq->cUrlCall($url,$headers,$bookingdotcom_rate_xml);//for curl call
            $ota_rlt1='';
            $bookingdotcom_extra_rate_xml='';
            if(strpos($ota_rlt, '<ok>' ) !== false){
                if($total_persons && $extra_adult_price){
                    $url1=$commonUrl.'derivedprices';
                    $bookingdotcom_extra_rate_xml='<?xml version="1.0" encoding="UTF-8" ?>
                    <request>
                    <username>'.$username.'</username>
                    <password>'.$password.'</password>
                    <rooms>';
                    $bookingdotcom_extra_rate_xml.=$xml_data1.'</rooms></request>';
                    $ota_rlt1=$this->getdata_curlreq->cUrlCall($url1,$headers,$bookingdotcom_extra_rate_xml);//for curl call
                }
            }
            $resultXml=simplexml_load_string($ota_rlt);
            $resp=$ota_rlt.'  '.$ota_rlt1;
            $log_request_msg = $bookingdotcom_rate_xml.'  '.$bookingdotcom_extra_rate_xml;
				$array_data = json_decode(json_encode(simplexml_load_string($ota_rlt)), true);
				if(strpos($ota_rlt, '<error>' ) !== false ){
					DB::table('rate_update_logs')->where('id', $logModel->id)
					->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$resp]);
					$rlt=array('status'=>0,'ota_name'=>'booking.com','response_msg'=>$resp);
                }else if(strpos($ota_rlt, '<error>' ) !== false){
                    DB::table('rate_update_logs')->where('id', $logModel->id)
					->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$resp]);
					$rlt=array('status'=>0,'ota_name'=>'booking.com','response_msg'=>$resp);
                }
                else if(strpos($ota_rlt, '<warning>' ) !== false){
					DB::table('rate_update_logs')->where('id', $logModel->id)
					->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$resp]);
					$rlt=array('status'=>0,'ota_name'=>'booking.com','response_msg'=>$resp);
                }
                if(strpos($ota_rlt,'<ok>') !== false )
                {
                    DB::table('rate_update_logs')->where('id', $logModel->id)
                    ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$resp]);
                    $rlt=array('status'=>1,'ota_name'=>'booking.com','response_msg'=>"updated sucessfully");
                }
                else{
                    $rlt=array('status'=>0,'ota_name'=>'booking.com','response_msg'=>$resp);
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
                "status"         	 => 1,
                "ip"         		 => $rate_bucket_data['bucket_client_ip'],
                "comment"			 => "Roomrate type is not mapped"
                ];
            $logModel->fill($log_data)->save();
            $rlt=array('status'=>0,'ota_name'=>'booking.com','response_msg'=>"Rateplan should be sync");
        }
       
        if(sizeof($rlt)==0){
            $rlt=array('status'=>0,'ota_name'=>'booking.com','response_msg'=>"Rateplan should be mapped");
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
        $xml_all_data                   = '';
        $flag                           = '';
        $max_adult=$roomTypeModel->select('max_people')->where('room_type_id',$data['room_type_id'])->first()->max_people;

            $xml_data       = '';
            $data['date_from'] = date('Y-m-d',strtotime($data['date_from']));
            $data['date_to'] = date('Y-m-d',strtotime($data['date_to']));
            $cond = array('hotel_id'=>$data['hotel_id'],'room_type_id'=>$rooms,'rate_plan_id'=>$data['rate_plan_id'],'channel'=>'Booking.com');
            $getRateDetails = OtaRatePlan::select('*')
                                ->where($cond)->where('from_date','<=',$data['date_from'])
                                ->where('to_date','>=',$data['date_to'])
                                ->orderBy('rate_plan_log_id','DESC')
                                ->first();
                if(!$getRateDetails){
                    $rlt=array('status'=>0,'ota_name'=>'booking.com','response_msg'=>"rate not available");
                    return $rlt;
                }
                $rate_data = [
                    'hotel_id'          => $getRateDetails->hotel_id,
                    'channel'           => 'Booking.com',
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
                $rateplan_multiple_days_data = $this->getdata_curlreq->getDaysUpdate($multiple_days,"Booking.com");
                $rateplan_bar_price=$rate_data['bar_price'];
                $rateplan_single_price=0;
                $rateplan_double_price=0;
                $rateplan_triple_price=0;
                if($occupency)
                {
                    $rateplan_single_price       = $occupency[0];
                    $rateplan_double_price       = $occupency[1];
                    $rateplan_triple_price       = (isset($occupency[2])) ? $occupency[2] : 0;
                }
                $result = $cmOtaRatePlanSynchronizeModel->getOtaRoomIdFromRatePlanSynch($rooms,$ota_id,$data['rate_plan_id']);
               
                if(isset($result[0])){
                    $room_code                   = $result[0]['ota_room_type_id'];
                    $rate_code                   = $result[0]['ota_rate_plan_id'];
                }
                $startDate          =   $data['date_from'];
                $endDate            =   date('Y-m-d',strtotime($data['date_to'].'+1day'));
                $extra_child_price  =   $rate_data['extra_child_price'];
                $extra_adult_price  =   $rate_data['extra_adult_price'];
                $success_flag=1;
                try{
                    $otaRatePlan->fill($rate_data)->save();
                    $base_rate     = new BaseRate();
                    $insertBaseRate = $base_rate->fill($rate_data)->save();
                }
                catch(\Exception $e){
                   $success_flag=0;
                }
                if($success_flag){
                    $rateplanId   = $otaRatePlan->rate_plan_log_id;
                    if(!empty($rate_code)){
                        $flag=1;
                        $xml='<room id="'.$room_code.'">
                        <date from="'.$startDate.'" to="'.$endDate.'">
                        <currencycode>'.$currency.'</currencycode>
                        <rate id="'.$rate_code.'" />';
                        if($rateplan_single_price!=0){
                            $xml .='<price1>'.$rateplan_single_price.'</price1>';
                            }
                        if($rateplan_bar_price!=0){
                        $xml .='<price>'.$rateplan_bar_price.'</price>';
                        }
                        $xml .='<closed>1</closed></date>
                        </room>';
                        $xml_data.= $xml;
                    }
                    else{
                        $flag=0;
                    }
                }
                else{
                    $rateplanId=0;
                }
            $xml_all_data.= $xml_data;
        if($flag==1)
        {
            $headers = array (
                //Regulates versioning of the XML interface for the API
                'Content-Type: application/xml'
                );
            $username = trim($auth_parameter->username);
            $password = trim($auth_parameter->password);
            $url                        = $commonUrl.'availability';
            $log_data               = [
                "action_id"          => 2,
                "hotel_id"           => $hotel_id,
                "ota_id"      	=> $ota_id,
                "rate_ref_id"        => $rateplanId,
                "user_id"            => $rate_bucket_data['bucket_user_id'],
                "request_msg"        => '',
                "response_msg"       => '',
                "request_url"        => '',
                "status"         => 2,
                "ip"         => $rate_bucket_data['bucket_client_ip'],
                "comment"	=> "Processing for update "
                ];
            $bookingdotcom_rate_xml ='<?xml version="1.0" encoding="UTF-8" ?>
            <request>
            <username>'.$username.'</username>
            <password>'.$password.'</password>
            <hotel_id>'.$ota_hotel_code.'</hotel_id>
            <version>1.0</version>';
            $bookingdotcom_rate_xml.= $xml_all_data.'</request>';
            $ota_rlt=$this->getdata_curlreq->cUrlCall($url,$headers,$bookingdotcom_rate_xml);//for curl call
            $resultXml=simplexml_load_string($ota_rlt);
            $resp=$ota_rlt;
            $log_request_msg = $bookingdotcom_rate_xml;
            if($resultXml){
                    $array_data = json_decode(json_encode(simplexml_load_string($ota_rlt)), true);
                    if(strpos($ota_rlt, '<error>' ) !== false ){
                    DB::table('log_table')->where('id', $logModel->id)
                    ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$resp]);
                    // $rate_update = RateUpdateLog::where('rate_plan_log_id',$rateplanId)->update(['block_status'=>0]);
                    $rlt=array('status'=>0,'ota_name'=>'booking.com','response_msg'=>$resp);
                                    }else if(strpos($ota_rlt, '<error>' ) !== false){
                                        DB::table('log_table')->where('id', $logModel->id)
                    ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$resp]);
                    // $rate_update = RateUpdateLog::where('rate_plan_log_id',$rateplanId)->update(['block_status'=>0]);
                    $rlt=array('status'=>0,'ota_name'=>'booking.com','response_msg'=>$resp);
                                    }
                                    else if(strpos($ota_rlt, '<warning>' ) !== false){
                    DB::table('log_table')->where('id', $logModel->id)
                    ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$resp]);
                    // $rate_update = RateUpdateLog::where('rate_plan_log_id',$rateplanId)->update(['block_status'=>0]);
                    $rlt=array('status'=>0,'ota_name'=>'booking.com','response_msg'=>$resp);
                                        }
                }
            else{
                if(strpos($ota_rlt, '<ok>' ) !== false)
                {
                    DB::table('log_table')->where('id', $logModel->id)
                    ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$resp]);
                    $rlt=array('status'=>1,'ota_name'=>'booking.com','response_msg'=>"updated sucessfully");
                }
                else{
                    $rlt=array('status'=>0,'ota_name'=>'booking.com','response_msg'=>$resp);
                    // $rate_update = RateUpdateLog::where('rate_plan_log_id',$rateplanId)->update(['block_status'=>0]);
                }
            }
        }
        else{
            $log_data               = [
                "action_id"          => 2,
                "hotel_id"           => $hotel_id,
                "ota_id"      	=> $ota_id,
                "rate_ref_id"        => $rateplanids,
                "user_id"            => $rate_bucket_data['bucket_user_id'],
                "request_msg"        => '',
                "response_msg"       => '',
                "request_url"        => '',
                "status"         => 2,
                "ip"         => $rate_bucket_data['bucket_client_ip'],
                "comment"	=> "Roomrate type is not mapped"
                ];
            $logModel->fill($log_data)->save();
            // $rate_update = RateUpdateLog::where('rate_plan_log_id',$rateplanId)->update(['block_status'=>0]);
            $rlt=array('status'=>0,'ota_name'=>'booking.com','response_msg'=>"Rateplan should be sync");
        }
        if(empty($rlt)){
            $rlt=array('status'=>0,'ota_name'=>'booking.com','response_msg'=>"Rateplan should be mapped");
        }
        return $rlt;
    }
}
