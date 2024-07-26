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
class ClearTripInvRateController extends Controller
{
	protected $getdata_curlreq;
    public function __construct(GetDataForRateController $getdata_curlreq)
    {
       $this->getdata_curlreq = $getdata_curlreq;
    }
    public function inventorySycUpdate($bucket_data,$inventory,$auth_parameter,$commonUrl,$from_date,$to_date){
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
}