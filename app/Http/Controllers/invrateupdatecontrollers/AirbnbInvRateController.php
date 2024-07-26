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
use App\AirbnbListingDetails;
use App\CmOtaRoomTypeSynchronize;
use App\CmOtaRatePlanSynchronize;
use App\Http\Controllers\Controller;
use App\Http\Controllers\IpAddressService;
use App\Http\Controllers\invrateupdatecontrollers\GetDataForRateController;
/**
 * This controller is used for Airbnb single,bulk,sync and block of inv and rate
 * @auther ranjit
 * created date 09/03/19.
 * modification due to display problem @ 12/03/19 by ranjit(new model added)
 */
class AirbnbInvRateController extends Controller
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
        $hotel_info                     = HotelInformation::where('hotel_id',$hotel_id)->first();
        $airbnbModel                    = new AirbnbListingDetails();
        $company                        = new CompanyDetails();
        $comp_details                   = $company->where('company_id',$hotel_info->company_id)->select('airbnb_refresh_token')->first();
        $refresh_token                  = $comp_details->airbnb_refresh_token;
        $oauth_Token                    = $airbnbModel->getAirBnbToken($refresh_token);
        $api_key               			    = trim($auth_parameter->X_Airbnb_API_Key);
        $rlt                            = array();
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
            $inv['channel']            = 'Airbnb';
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
                if($inv['block_status']==0)
                {
                    if($room_qtys >= 0)
                    {
                        $post_data=array();
                        $post_data['listing_id']=$room_code;
                        $operations=array();
                        $operations['dates']=array($startDate .":".$endDate );
                        $operations['availability']="available";
                        $operations['available_count']=$room_qtys;
                        $post_data['operations']=array($operations);
                        $post_data=json_encode($post_data);
                    }
                }
                else
                {
                    $post_data=array();
                    $post_data['listing_id']=$room_code;
                    $operations=array();
                    $operations['dates']=array($startDate .":".$endDate );
                    $operations['availability']="unavailable";
                    $post_data['operations']=array($operations);
                    $post_data=json_encode($post_data);
                }
                $log_request_msg=$post_data;
                $url=$commonUrl."/calendar_operations?_allow_dates_overlap=true";
                $headers = array();
                $headers[] = "X-Airbnb-Api-Key: $api_key";
                $headers[] = "X-Airbnb-Oauth-Token: $oauth_Token";
                $headers[] = "Content-Type: application/json";
                $logModel->fill($log_data)->save();
                $result=$this->getdata_curlreq->cUrlCall($url,$headers,$post_data);//for curl call
                $array_data = json_decode($result, true);
                if(!isset($array_data['Error'])){
                    DB::table('log_table')->where('id', $logModel->id)
                    ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                    $rlt=array('status'=>1,'ota_name'=>'airbnb','response_msg'=>$result);
                }
                else{
                    DB::table('log_table')->where('id', $logModel->id)
                    ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                    $rlt=array('status'=>0,'ota_name'=>'airbnb','response_msg'=>$result);

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
                $rlt=array('status'=>0,'ota_name'=>'airbnb','response_msg'=>"Roomtype should be sync");
            }
        }
        if(sizeof($rlt)==0){
            $rlt=array('status'=>0,'ota_name'=>'airbnb','response_msg'=>"Roomtype should be mapped");
        }
        return $rlt;
    }
    public function blockInventoryUpdate($bucket_data,$room_type_id,$data,$auth_parameter,$commonUrl)
    {
        $blocklog                       = new LogTable();
        $hotel_id                       = $bucket_data["bucket_hotel_id"];
        $ota_id                         = $bucket_data["bucket_ota_id"];
        $ota_hotel_code                 = $bucket_data["bucket_ota_hotel_code"];
        $data['channel']                = 'airbnb';
        $data['ota_id']                 = $ota_id;
        $data['user_id']                = $bucket_data["bucket_user_id"];
        $data['client_ip']              = $bucket_data["bucket_client_ip"];
        $data['hotel_id']               = $hotel_id;
        $hotel_info                     = HotelInformation::where('hotel_id',$hotel_id)->first();
        $airbnbModel                    = new AirbnbListingDetails();
        $company                        = new CompanyDetails();
        $comp_details                   = $company->where('company_id',$hotel_info->company_id)->select('airbnb_refresh_token')->first();
        $refresh_token                  = $comp_details->airbnb_refresh_token;
        $oauth_Token                    = $airbnbModel->getAirBnbToken($refresh_token);
        $api_key               			= trim($auth_parameter->X_Airbnb_API_Key);
        $rlt                            = array();
            $otainventory                   = new OtaInventory();
            $data['room_type_id']=$room_type_id;
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
                    $post_data=array();
                    $post_data['listing_id']=$room_code;
                    $operations=array();
                    $operations['dates']=array($startDate .":".$endDate );
                    $operations['availability']="unavailable";
                    $post_data['operations']=array($operations);
                    $post_data=json_encode($post_data);
                    $blocklog->fill($log_data)->save();
                    $log_request_msg=$post_data;
                    $url=$commonUrl."/calendar_operations?_allow_dates_overlap=true";
                    $headers = array();
                    $headers[] = "X-Airbnb-Api-Key: $api_key";
                    $headers[] = "X-Airbnb-Oauth-Token: $oauth_Token";
                    $headers[] = "Content-Type: application/json";
                    $result=$this->getdata_curlreq->cUrlCall($url,$headers,$post_data);//for curl call
                    $array_data = json_decode($result, true);
                    if(!isset($array_data['Error'])){
                        DB::table('log_table')->where('id', $blocklog->id)
                        ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                        $rlt=array('status'=>1,'ota_name'=>'airbnb','response_msg'=>$result);
                    }
                    else
                    {
                        DB::table('log_table')->where('id', $blocklog->id)
                        ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                        $rlt=array('status'=>0,'ota_name'=>'airbnb','response_msg'=>$result);

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
                    $rlt=array('status' => 0,'ota_name'=>'airbnb','response'=> 'Roomtype should be Sync');
                }
            }
        if(sizeof($rlt)==0){
            $rlt=array('status'=>0,'ota_name'=>'airbnb','response_msg'=>"Roomtype should be mapped");
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
        $hotel_info                     = HotelInformation::where('hotel_id',$hotel_id)->first();
        $airbnbModel                    = new AirbnbListingDetails();
        $company                        = new CompanyDetails();
        $comp_details                   = $company->where('company_id',$hotel_info->company_id)->select('airbnb_refresh_token')->first();
        $refresh_token                  = $comp_details->airbnb_refresh_token;
        $oauth_Token                    = $airbnbModel->getAirBnbToken($refresh_token);
        $api_key               			= trim($auth_parameter->X_Airbnb_API_Key);
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
                $inv['channel']            = 'airbnb';
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
                        $post_data=array();
                        $post_data['listing_id']=$room_code;
                        $operations=array();
                        $operations['dates']=array($startDate .":".$endDate );
                        $operations['availability']="available";
                        $operations['available_count']=$room_qtys;
                        $post_data['operations']=array($operations);
                        $post_data=json_encode($post_data);
                        $log_request_msg=$post_data;
                        $url=$commonUrl."/calendar_operations?_allow_dates_overlap=true";
                        $headers = array();
                        $headers[] = "X-Airbnb-Api-Key: $api_key";
                        $headers[] = "X-Airbnb-Oauth-Token: $oauth_Token";
                        $headers[] = "Content-Type: application/json";
                        $logModel->fill($log_data)->save();

                        $result=$this->getdata_curlreq->cUrlCall($url,$headers,$post_data);//for curl call
                        $array_data = json_decode($result, true);
                        if(!isset($array_data['Error'])){
                            DB::table('log_table')->where('id', $logModel->id)
                            ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                            $rlt=array('status'=>1,'ota_name'=>'airbnb','response_msg'=>$result);
                        }
                        else{
                            DB::table('log_table')->where('id', $logModel->id)
                            ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                            $rlt=array('status'=>0,'ota_name'=>'airbnb','response_msg'=>$result);
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
                            $rlt=array('status'=>0,'ota_name'=>'airbnb','response_msg'=>"Roomtype should be sync");
                    }
                }
            }
        }
        if(sizeof($rlt)==0){
            $rlt=array('status'=>0,'ota_name'=>'airbnb','response_msg'=>"Roomtype should be mapped");
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
        $data['channel']                = 'airbnb';
        $hotel_info                     = HotelInformation::where('hotel_id',$hotel_id)->first();
        $airbnbModel                    = new AirbnbListingDetails();
        $company                        = new CompanyDetails();
        $comp_details                   = $company->where('company_id',$hotel_info->company_id)->select('airbnb_refresh_token')->first();
        $refresh_token                  = $comp_details->airbnb_refresh_token;
        $oauth_Token                    = $airbnbModel->getAirBnbToken($refresh_token);
        $api_key               			= trim($auth_parameter->X_Airbnb_API_Key);
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
                    $post_data=array();
                    $post_data['listing_id']=$room_code;
                    $operations=array();
                    $operations['dates']=array($startDate .":".$endDate );
                    $operations['availability']="available";
                    $operations['available_count']=$room_qtys;
                    $post_data['operations']=array($operations);
                    $post_data=json_encode($post_data);
                    $log_request_msg=$post_data;
                    $url=$commonUrl."/calendar_operations?_allow_dates_overlap=true";
                    $headers = array();
                    $headers[] = "X-Airbnb-Api-Key: $api_key";
                    $headers[] = "X-Airbnb-Oauth-Token: $oauth_Token";
                    $headers[] = "Content-Type: application/json";
                    $logModel->fill($log_data)->save();
                    $result=$this->getdata_curlreq->cUrlCall($url,$headers,$post_data);//for curl call
                    $array_data = json_decode($result, true);
                    if(!isset($array_data['Error'])){
                        DB::table('log_table')->where('id', $logModel->id)
                        ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                        $rlt=array('status'=>1,'ota_name'=>'airbnb','response_msg'=>$result);
                    }
                    else
                    {
                        DB::table('log_table')->where('id', $logModel->id)
                        ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                        $rlt=array('status'=>0,'ota_name'=>'airbnb','response_msg'=>$result);
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
                    $rlt=array('status'=>0,'ota_name'=>'airbnb','response_msg'=>"Roomtype should be sync");
            }
            if(sizeof($rlt)==0){
                $rlt=array('status'=>0,'ota_name'=>'airbnb','response_msg'=>"Roomtype should be mapped");
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
        
        $xml_all_data                   = '';
        $multiple_days                  = '{"Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1","Sun":"1"}';
        $hotel_id                       = $rate_bucket_data['bucket_hotel_id'];
        $ota_id                         = $rate_bucket_data['bucket_ota_id'];
        $ota_hotel_code                 = $rate_bucket_data['bucket_ota_hotel_code'];
        $hotel_info                     = HotelInformation::where('hotel_id',$hotel_id)->first();
        $airbnbModel                    = new AirbnbListingDetails();
        $company                        = new CompanyDetails();
        $comp_details                   = $company->where('company_id',$hotel_info->company_id)->select('airbnb_refresh_token')->first();
        $refresh_token                  = $comp_details->airbnb_refresh_token;
        $oauth_Token                    = $airbnbModel->getAirBnbToken($refresh_token);
        $api_key               			= trim($auth_parameter->X_Airbnb_API_Key);
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

                $rateplan_multiple_days_data = $this->getdata_curlreq->getDaysUpdate($multiple_days,"EaseMyTrip");
                $currency=$this->getdata_curlreq->getCurrency($hotel_id);
                $extra_adult_price			 = $rates['extra_adult_price'];
                $extra_child_price			 = $rates['extra_child_price'];
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

                $room_type_name=$this->getRoomTypeName($rates_data['room_type_id']);
                $room_code 				= $cmOtaRoomTypeSynchronizeModel->getOtaRoomType($rates_data['room_type_id'],$ota_id);

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
                $rates['channel']               = 'Airbnb';
                $rates['multiple_occupancy']    = json_encode($rates['multiple_occupancy']);
                try{
                    $otaRatePlan->fill($rates)->save();
                }
                catch(Exception $e){

                }

                if($room_code)
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
                        if($extra_adult_price)
                        {
                            $air_bnb_data=array("price_per_extra_person"=>$extra_adult_price);
                            $this->airbnbPriceSettings($air_bnb_data,$room_code,$oauth_Token,$api_key);
                        }

                        $post_data=array('availability'=>'available',"daily_price"=>$rateplan_double_price);
                        $post_data=json_encode($post_data);
                        $log_request_msg=$post_data;
                        $ch = curl_init();
                        $room_code = trim($room_code);
                        curl_setopt($ch, CURLOPT_URL, "https://api.airbnb.com/v2/calendars/$room_code/$startDate/$endDate");
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                        $headers = array();
                        $headers[] = "X-Airbnb-Api-Key: $api_key";
                        $headers[] = "X-Airbnb-Oauth-Token: $oauth_Token";
                        $headers[] = "Content-Type: application/json";
                        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                        $result = curl_exec($ch);
                        if (curl_errno($ch)) {
                            echo 'Error:' . curl_error($ch);
                        }
                        curl_close ($ch);
                        $logModel->fill($log_data)->save();
                        $array_data = json_decode($result, true);
                        if(!isset($array_data['Error'])){
                            DB::table('rate_update_logs')->where('id', $logModel->id)
                            ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                            $rlt=array('status'=>1,'ota_name'=>'airbnb','response_msg'=>$result);
                        }
                        else
                        {
                            DB::table('rate_update_logs')->where('id', $logModel->id)
                            ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                            $rlt=array('status'=>0,'ota_name'=>'airbnb','response_msg'=>$result);
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
                        $rlt=array('status'=>0,'ota_name'=>'airbnb','response_msg'=>"Rateplan should be sync");
                    }
            }
            if(sizeof($rlt)==0){
                $rlt=array('status'=>0,'ota_name'=>'airbnb','response_msg'=>"Rateplan should be mapped");
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
        $hotel_info                     = HotelInformation::where('hotel_id',$hotel_id)->first();
        $airbnbModel                    = new AirbnbListingDetails();
        $company                        = new CompanyDetails();
        $comp_details                   = $company->where('company_id',$hotel_info->company_id)->select('airbnb_refresh_token')->first();
        $refresh_token                  = $comp_details->airbnb_refresh_token;
        $oauth_Token                    = $airbnbModel->getAirBnbToken($refresh_token);
        $api_key               			= trim($auth_parameter->X_Airbnb_API_Key);
        $rlt                            = array();
        foreach($rates_data as $rate)
        {
            $xml_data       = '';

            foreach($rate['rates'] as $rates)
            {
                $otaRatePlan                    = new OtaRatePlan();
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
                $rates['channel']               = 'Airbnb';
                $min_max_status =   $this->getdata_curlreq->checkMinMaxPrice($rates['room_type_id'],$rates['rate_plan_id'],$rates['bar_price'],$rates['multiple_occupancy'],$rates['hotel_id'],$rates['date'],$rates['channel']);
                if($min_max_status){
                    $rlt = $min_max_status;
                    continue;
                }
                $rateplan_multiple_days_data = $this->getdata_curlreq->getDaysUpdate($rates['multiple_days'],"EaseMyTrip");
                $currency=$this->getdata_curlreq->getCurrency($hotel_id);
                $getrate_details=DB::table('room_rate_plan')->select('extra_adult_price','extra_child_price')->where('hotel_id', $hotel_id)->where('room_type_id',$rates['room_type_id'])->where('rate_plan_id',$rates['rate_plan_id'])->orderBy('room_rate_plan_id','DESC')->first();
                $extra_adult_price			 = $getrate_details->extra_adult_price;
                $extra_child_price			 = $getrate_details->extra_child_price;
                $occupency=$this->getdata_curlreq->decideOccupencyPrice($max_adult,$rates['bar_price'],$rates['multiple_occupancy']);
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
                $room_type_name=$this->getRoomTypeName($rates['room_type_id']);
                $room_code 				= $cmOtaRoomTypeSynchronizeModel->getOtaRoomType($rates['room_type_id'],$ota_id);

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
                    if($room_code)
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
                            if($extra_adult_price)
                            {
                                $air_bnb_data=array("price_per_extra_person"=>$extra_adult_price);
                                $this->airbnbPriceSettings($air_bnb_data,$room_code,$oauth_Token,$api_key);
                            }

                            $post_data=array('availability'=>'available',"daily_price"=>$rateplan_double_price);
                            $post_data=json_encode($post_data);
                            $log_request_msg=$post_data;
                            $ch = curl_init();
                            $room_code = trim($room_code);
                            curl_setopt($ch, CURLOPT_URL, "https://api.airbnb.com/v2/calendars/$room_code/$startDate/$endDate");
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
                            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                            $headers = array();
                            $headers[] = "X-Airbnb-Api-Key: $api_key";
                            $headers[] = "X-Airbnb-Oauth-Token: $oauth_Token";
                            $headers[] = "Content-Type: application/json";
                            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                            $result = curl_exec($ch);
                            if (curl_errno($ch)) {
                                echo 'Error:' . curl_error($ch);
                            }
                            curl_close ($ch);
                            $logModel->fill($log_data)->save();
                            $array_data = json_decode($result, true);

                            if(!isset($array_data['Error'])){
                                DB::table('rate_update_logs')->where('id', $logModel->id)
                                ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                                $rlt=array('status'=>1,'ota_name'=>'airbnb','response_msg'=>$result);
                            }
                            else
                            {
                                DB::table('rate_update_logs')->where('id', $logModel->id)
                                ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                                $rlt=array('status'=>0,'ota_name'=>'airbnb','response_msg'=>$result);
                            }
                    }
                    else{
                        $rateplanId=0;
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
                        $rlt=array('status'=>0,'ota_name'=>'airbnb','response_msg'=>"Rateplan should be sync");
                    }
                }
            }
        }
        if(sizeof($rlt)==0){
            $rlt=array('status'=>0,'ota_name'=>'airbnb','response_msg'=>"Rateplan should be mapped");
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
        $rateplanId                     = '';
        $multiple_days                  = '{"Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1","Sun":"1"}';
        $hotel_id                       = $rate_bucket_data['bucket_hotel_id'];
        $ota_id                         = $rate_bucket_data['bucket_ota_id'];
        $ota_hotel_code                 = $rate_bucket_data['bucket_ota_hotel_code'];
        $max_adult=$roomTypeModel->select('max_people')->where('room_type_id',$data['room_type_id'])->first()->max_people;
        $hotel_info                     = HotelInformation::where('hotel_id',$hotel_id)->first();
        $airbnbModel                    = new AirbnbListingDetails();
        $company                        = new CompanyDetails();
        $comp_details                   = $company->where('company_id',$hotel_info->company_id)->select('airbnb_refresh_token')->first();
        $refresh_token                  = $comp_details->airbnb_refresh_token;
        $oauth_Token                    = $airbnbModel->getAirBnbToken($refresh_token);
        $api_key               			= trim($auth_parameter->X_Airbnb_API_Key);
        $rlt                            = array();
        $data['multiple_days']       = json_encode($data['multiple_days']);

        $currency=$this->getdata_curlreq->getCurrency($hotel_id);
        $rateplan_multiple_days_data = $this->getdata_curlreq->getDaysUpdate($data['multiple_days'],"EaseMyTrip");
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
            $rateplan_triple_price       = isset($occupency[2])?$occupency[2]:0;
            if(isset($occupency[3]))
            {
                $rateplan_fourth_price       = $occupency[3];
            }
        }
        $room_type_name=$this->getRoomTypeName($data['room_type_id']);
        $room_code 				= $cmOtaRoomTypeSynchronizeModel->getOtaRoomType($data['room_type_id'],$ota_id);

        $startDate                   = date('Y-m-d',strtotime($data['from_date']));
        $endDate                     = date('Y-m-d',strtotime($data['to_date']));
        $data['multiple_occupancy']  = json_encode($data['multiple_occupancy']);
        $data['channel']             = 'airbnb';

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
            if($room_code)
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
                    if($extra_adult_price)
                    {
                        $air_bnb_data=array("price_per_extra_person"=>$extra_adult_price);
                        $this->airbnbPriceSettings($air_bnb_data,$room_code,$oauth_Token,$api_key);
                    }

                    $post_data=array('availability'=>'available',"daily_price"=>$rateplan_double_price);
                    $post_data=json_encode($post_data);
                    $log_request_msg=$post_data;
                    $ch = curl_init();
                    $room_code = trim($room_code);
                    curl_setopt($ch, CURLOPT_URL, "https://api.airbnb.com/v2/calendars/$room_code/$startDate/$endDate");
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                    $headers = array();
                    $headers[] = "X-Airbnb-Api-Key: $api_key";
                    $headers[] = "X-Airbnb-Oauth-Token: $oauth_Token";
                    $headers[] = "Content-Type: application/json";
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

                    $result = curl_exec($ch);
                    if (curl_errno($ch)) {
                        echo 'Error:' . curl_error($ch);
                    }
                    curl_close ($ch);
                    $logModel->fill($log_data)->save();
                    $array_data = json_decode($result, true);
                    if(!isset($array_data['Error'])){
                        DB::table('rate_update_logs')->where('id', $logModel->id)
                        ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                        $rlt=array('status'=>1,'ota_name'=>'airbnb','response_msg'=>$result);
                    }
                    else
                    {
                        DB::table('rate_update_logs')->where('id', $logModel->id)
                        ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                        $rlt=array('status'=>0,'ota_name'=>'airbnb','response_msg'=>$result);
                    }
            }
            else{
                $rateplanId=0;
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
                $rlt=array('status'=>0,'ota_name'=>'airbnb','response_msg'=>"Rateplan should be sync");
            }
        }
        if(sizeof($rlt)==0){
            $rlt=array('status'=>0,'ota_name'=>'airbnb','response_msg'=>"Rateplan should be mapped");
        }
        return $rlt;
    }
    /*------------------------get room type name---------------------------------*/
		public function getRoomTypeName($room_type)
		{
			$getRoomType=DB::table('kernel.room_type_table')->select('room_type')->where('room_type_id',$room_type)->first();
			return $getRoomType->room_type;
		}
        /*-------------------------------end-----------------------------------------*/
        public function airbnbPriceSettings($air_bnb_data,$airbnb_listing_id,$oauth_Token,$api_key)
        {

            $post_data=array(
                    "price_per_extra_person" => $air_bnb_data['price_per_extra_person']
                    );
            $post_data=json_encode($post_data);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://api.airbnb.com/v2/pricing_settings/$airbnb_listing_id");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");

            $headers = array();
            $headers[] = "X-Airbnb-Api-Key: $api_key";
            $headers[] = "X-Airbnb-Oauth-Token: $oauth_Token";
            $headers[] = "Content-Type: application/json";
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $result = curl_exec($ch);
            if (curl_errno($ch)) {
                echo 'Error:' . curl_error($ch);
            }
            curl_close ($ch);
            $result=json_decode($result);
            if(!isset($result->error))
            {
                return true;
            }
        }
}
