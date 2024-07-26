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
use Illuminate\Support\Facades\DB as FacadesDB;

class SimplotelInvRateController extends Controller
{

    protected $getdata_curlreq;
    public function __construct(GetDataForRateController $getdata_curlreq)
    {
       $this->getdata_curlreq = $getdata_curlreq;
    }

    public function singleInvUpdate($bucket_data,$inventory,$auth_parameter,$commonUrl){


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
                $hotelCode 					= $cmOtaRoomTypeSynchronizeModel->getOtaRoomType($invs['hotel_id'],$ota_id);
                $startDate                  = $inv['date'];
                $endDate                    = date('Y-m-d',strtotime($inv['date'].'+1day'));
                $inventory_los              = $inv['los'];
                $room_qtys                  = $inv['no_of_rooms'];
                $ota_room_type_id                  = $inv['InvTypeCode'];
                $id =              trim($auth_parameter->ID);
                $iD_Context =      trim($auth_parameter->ID_Context);
                $messagePassword = trim($auth_parameter->MessagePassword);
                $type =            trim($auth_parameter->Type); 
                

                $ratePlanTypeSynchronizeData  = CmOtaRatePlanSynchronize::
                select('*')
                ->where('ota_room_type_id', '=' ,$hotelCode)
                ->first();

                $inv['room_type_id']       = $invs['room_type_id'];
                $inv['block_status']       = 0;
                $inv['channel']            = 'Simplotel';
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
                    if($hotelCode && $ratePlanTypeSynchronizeData)
                    {
                        $rateplan_code = $ratePlanTypeSynchronizeData->ota_rate_plan_id;
                        $flag=1;
                        if($room_qtys >= 0)
                        {
                         $xml =    '<AvailStatusMessages HotelCode="'.$hotelCode .'">
                         <AvailStatusMessage BookingLimit="25">
                         <StatusApplicationControl Start="'.$startDate.'" End="'.$endDate.'"
                         InvTypeCode="'.$ota_room_type_id.'" Mon="1" Tue="1" Weds="0" Thur="0" Fri="1" Sat="0" Sun="1" />
                         </AvailStatusMessage>
                         </AvailStatusMessages>
                         </OTA_HotelAvailNotifRQ>'
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
                $id =              trim($auth_parameter->ID);
                $iD_Context =      trim($auth_parameter->ID_Context);
                $messagePassword = trim($auth_parameter->MessagePassword);
                $type =            trim($auth_parameter->Type);
                $url         	 = $commonUrl.'update_inventory_and_restrictions';
            $simplotel_xml ='<OTA_HotelAvailNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05" Version="1.0"
                TimeStamp="2017-09-10T09:30:47Z"
                MessageContentCode="1">
                <POS>
                <Source>
                <RequestorID ID="'.$id.'" ID_Context="'.$iD_Context.'" MessagePassword="'.$messagePassword.'"
                Type="'.$type.'" />
                </Source>
                </POS>';
            $simplotel_xml.=$xml_all_data.' </request>';
            $headers = array ('Content-Type: application/xml');
            $log_request_msg = $simplotel_xml;
            $logModel->fill($log_data)->save();//saving pre logdata

            $result=$this->getdata_curlreq->cUrlCall($url,$headers,$simplotel_xml);//for curl call
            $resultXml=simplexml_load_string($result);
            if($resultXml){
				if(strpos($result, '<error>' ) !== false){
					DB::table('log_table')->where('id', $logModel->id)
					->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
					$rlt=array('status'=>0,'ota_name'=>'Simplotel','response_msg'=>$result);
				}
				else if(strpos($result, '<warning>' ) !== false){
					DB::table('log_table')->where('id', $logModel->id)
					->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
					$rlt=array('status'=>0,'ota_name'=>'Simplotel','response_msg'=>$result);
                }
            }
            else{
                if(strpos($result, '<ok>' ) !== false)
                {
                    DB::table('log_table')->where('id', $logModel->id)
                    ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                    $rlt=array('status'=>1,'ota_name'=>'Simplotel','response_msg'=>"updated sucessfully");
                }
                else{
                    $rlt=array('status'=>0,'ota_name'=>'Simplotel','response_msg'=>$result);
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
                $rlt=array('status'=>0,'ota_name'=>'Simplotel','response_msg'=>"Roomtype should be sync");
        }
        if(sizeof($rlt)==0){
            $rlt=array('status'=>0,'ota_name'=>'Simplotel','response_msg'=>"Roomtype should be mapped");
        }
        return $rlt;
    }

    //sync
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
            $hotelCode 					= $cmOtaRoomTypeSynchronizeModel->getOtaRoomType($invs['hotel_id'],$ota_id);
            

            $ratePlanTypeSynchronizeData  = CmOtaRatePlanSynchronize::
            select('*')
            ->where('ota_room_type_id', '=' ,$hotelCode)
            ->first();

            $inv['room_type_id']       = $inventory['room_type_id'];
            $inv['block_status']       = $inv["block_status"];
            $inv['channel']            = 'Simplotel';
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

            if($hotelCode && $ratePlanTypeSynchronizeData)
            {
                $rateplan_code = $ratePlanTypeSynchronizeData->ota_rate_plan_id;
                $flag=1;
                if($inv['block_status']==0)
                {
                    if($room_qtys >= 0)
                    {

                       $xml =  '<HotelAvailRequests>
                        <HotelAvailRequest>
                        <DateRange Start='.$from_date.' End='.$to_date.' />
                        <HotelRef HotelCode='.$hotelCode.' />
                        </HotelAvailRequest>
                        </HotelAvailRequests>
                        </OTA_HotelAvailGetRQ>
                        ';
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

                $id =              trim($auth_parameter->ID);
                $iD_Context =      trim($auth_parameter->ID_Context);
                $messagePassword = trim($auth_parameter->MessagePassword);
                $type =            trim($auth_parameter->Type);
                $url         	                = $commonUrl.'fetch_inventory';

            $siplotel_xml ='<OTA_HotelAvailGetRQ xmlns="http://www.opentravel.org/OTA/2003/05" Version="1.100"
            EchoToken="a373f6ee-d91a-40bb-8ce9-82bcca253a6e"
            TimeStamp="2017-09-21T10:50:08.9014485Z">
            <POS>
            <Source>
            <RequestorID ID="'.$id.'" ID_Context="'.$iD_Context.'"
            MessagePassword="'.$messagePassword.'" Type="'.$type.'" />
            </Source>
            </POS>';
            $siplotel_xml.=$xml_all_data.' </request>';
            $headers = array ('Content-Type: application/xml');
            $log_request_msg = $siplotel_xml;
            $logModel->fill($log_data)->save();//saving pre logdata

            $result=$this->getdata_curlreq->cUrlCall($url,$headers,$siplotel_xml);//for curl call
            $resultXml=simplexml_load_string($result);
            if($resultXml){
				if(strpos($result, '<error>' ) !== false){
					DB::table('log_table')->where('id', $logModel->id)
					->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
					$rlt=array('status'=>0,'ota_name'=>'Simplotel','response_msg'=>$result);
				}
				else if(strpos($result, '<warning>' ) !== false){
					DB::table('log_table')->where('id', $logModel->id)
					->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
					$rlt=array('status'=>0,'ota_name'=>'Simplotel','response_msg'=>$result);
                }
            }
            else{
                if(strpos($result, '<ok>' ) !== false)
                {
                    DB::table('log_table')->where('id', $logModel->id)
                    ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                    $rlt=array('status'=>1,'ota_name'=>'Simplotel','response_msg'=>"updated sucessfully");
                }
                else{
                    $rlt=array('status'=>0,'ota_name'=>'Simplotel','response_msg'=>$result);
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
                $rlt=array('status'=>0,'ota_name'=>'Simplotel','response_msg'=>"Roomtype should be sync");
            }
            if(sizeof($rlt)==0){
                $rlt=array('status'=>0,'ota_name'=>'Simplotel','response_msg'=>"Roomtype should be mapped");
            }
        return $rlt;
    }

    //bukkinvupdate

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
        $data['channel']                = 'Simplotel';
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
            $endDate            = date('Y-m-d',strtotime($data['date_to'].'+1day'));
            $inventory_los      = $data['los'];
            $room_qtys          = $data['no_of_rooms'];

            $ratePlanTypeSynchronizeData  = CmOtaRatePlanSynchronize::
            select('*')
            ->where('ota_room_type_id', '=' ,$room_code)
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

                    $id =              trim($auth_parameter->ID);
                    $iD_Context =      trim($auth_parameter->ID_Context);
                    $messagePassword = trim($auth_parameter->MessagePassword);
                    $type =            trim($auth_parameter->Type);
                $url         	                = $commonUrl.'availability';
                $txm='';
               
                //
                $headers           = array (
                    //Regulates versioning of the XML interface for the API
                    'Content-Type: application/xml',
                    );
        
                    $siplotel_xml = "<?xml version='1.0' encoding='UTF-8'?>
                    <request>
                    <Source>
                    <RequestorID ID='$id' ID_Context='$iD_Context' MessagePassword='$messagePassword'
                    Type='$type' />
                    </Source>
                    <request>
                    ";
                //
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

                $siplotel_xml=$siplotel_xml.$txm.'</request>';
                $headers = array ('Content-Type: application/xml');
                $log_request_msg = $siplotel_xml;
                $logModel->fill($log_data)->save();//saving pre logdata

                $result=$this->getdata_curlreq->cUrlCall($url,$headers,$siplotel_xml);//for curl call
                $resultXml=simplexml_load_string($result);
                if($resultXml){
                    if(strpos($result, '<error>' ) !== false){
                        DB::table('log_table')->where('id', $logModel->id)
                        ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                        $rlt=array('status'=>0,'ota_name'=>'Simplotel','response_msg'=>$result);
                    }
                    else if(strpos($result, '<warning>' ) !== false){
                        DB::table('log_table')->where('id', $logModel->id)
                        ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                        $rlt=array('status'=>0,'ota_name'=>'Simplotel','response_msg'=>$result);
                    }
                }
                else{
                    if(strpos($result, '<ok>' ) !== false)
                    {
                        DB::table('log_table')->where('id', $logModel->id)
                        ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                        $rlt=array('status'=>1,'ota_name'=>'Simplotel','response_msg'=>"updated sucessfully");
                    }
                    else{
                        $rlt=array('status'=>0,'ota_name'=>'Simplotel','response_msg'=>$result);
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
                    $rlt=array('status'=>0,'ota_name'=>'Simplotel','response_msg'=>'Roomtype should be sync');
            }
            if(sizeof($rlt)==0){
                $rlt=array('status'=>0,'ota_name'=>'Simplotel','response_msg'=>"Roomtype should be mapped");
            }
            return $rlt;
        }
    }
    

    
}