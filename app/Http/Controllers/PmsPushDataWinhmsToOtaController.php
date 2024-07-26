<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Validator;
use DB;
use App\CmOtaDetails;
use App\WinHms;
use App\WinhmsRatePush;
use App\PmsInvPush;
use App\PmsRatePush;
use App\Inventory;
use App\RatePlanLog;
use App\Http\Controllers\invrateupdatecontrollers\GoibiboInvRateController;
use App\Http\Controllers\invrateupdatecontrollers\BookingdotcomInvRateController;
use App\Http\Controllers\invrateupdatecontrollers\EaseMyTripInvRateController;
use App\Http\Controllers\invrateupdatecontrollers\PaytmInvRateController;
use App\Http\Controllers\invrateupdatecontrollers\AgodaInvRateController;
use App\Http\Controllers\invrateupdatecontrollers\ExpediaInvRateController;
use App\Http\Controllers\invrateupdatecontrollers\ViaInvRateController;
use App\Http\Controllers\invrateupdatecontrollers\GoomoInvRateController;
use App\Http\Controllers\invrateupdatecontrollers\TravelguruInvRateController;
use App\Http\Controllers\invrateupdatecontrollers\AirbnbInvRateController;

class PmsPushDataWinhmsToOtaController extends Controller
{
    /**
     * This controller is used to push inventory and rate to OTA and BE by cron job.
     * @author Ranjit Date of creation: 05-06-2019
     */
    protected $goibiboInventoryRatePushUpdate,$bookingdotcomInventoryRatePushUpdate;
    protected $easemytripInventoryRatePushUpdate,$paytmInventoryRatePushUpdate,$agodaInventoryRatePushUpdate;
    protected $expediaInventoryRatePushUpdate,$goomoInventoryRatePushUpdate,$travelguruInventoryRatePushUpdate;

    public function __construct(GoibiboInvRateController $goibiboInventoryRatePushUpdate,
    BookingdotcomInvRateController $bookingdotcomInventoryRatePushUpdate,
    EaseMyTripInvRateController $easemytripInventoryRatePushUpdate,
    PaytmInvRateController $paytmInventoryRatePushUpdate,
    AgodaInvRateController $agodaInventoryRatePushUpdate,
    ExpediaInvRateController $expediaInventoryRatePushUpdate,
    ViaInvRateController $viaInventoryRatePushUpdate,
    GoomoInvRateController $goomoInventoryRatePushUpdate,
    TravelguruInvRateController  $travelguruInventoryRatePushUpdate,
    AirbnbInvRateController $airbnbInventoryRatePushUpdate)
    {
       $this->goibiboInventoryRatePushUpdate        = $goibiboInventoryRatePushUpdate;
       $this->bookingdotcomInventoryRatePushUpdate  = $bookingdotcomInventoryRatePushUpdate;
       $this->easemytripInventoryRatePushUpdate     = $easemytripInventoryRatePushUpdate;
       $this->paytmInventoryRatePushUpdate          = $paytmInventoryRatePushUpdate;
       $this->agodaInventoryRatePushUpdate          = $agodaInventoryRatePushUpdate;
       $this->expediaInventoryRatePushUpdate        = $expediaInventoryRatePushUpdate;
       $this->viaInventoryRatePushUpdate            = $viaInventoryRatePushUpdate;
       $this->goomoInventoryRatePushUpdate          = $goomoInventoryRatePushUpdate;
       $this->travelguruInventoryRatePushUpdate     = $travelguruInventoryRatePushUpdate;
       $this->airbnbInventoryRatePushUpdate         = $airbnbInventoryRatePushUpdate;
    }
    public function pushInventoryToOta(Request $request){
        $getInventoryDetails=PmsInvPush::select('*')
                                ->where('hotel_id','!=',1151)
                                ->where('push_status',0)
                                ->take(5)
                                ->get();
        $updateBE=array();
        $updateOTA=array();
        $getOtas=array();
        if(count($getInventoryDetails)>0){
            foreach($getInventoryDetails as $details){
                $update_push_status = PmsInvPush::where('inventory_id',$details->inventory_id)->update(['push_status'=>2]);
                $ota_name=explode(',',$details->ota_details);
                foreach($ota_name as $ota){
                    if($ota == 'all'){
                        $getOtas=CmOtaDetails::select('*')
                        ->where('hotel_id',$details->hotel_id)
                        ->where('is_active',1)
                        ->get();
                        $updateBE=$this->beUpdate($details);
                        $updateOTA=$this->otaUpdate($details,$getOtas);
                    }
                    else{
                        $ota = ucfirst($ota);
                        $getOtas=CmOtaDetails::select('*')
                        ->where('hotel_id',$details->hotel_id)
                        ->where('ota_name',$ota)
                        ->where('is_active',1)
                        ->get();

                        $updateOTA=$this->otaUpdate($details,$getOtas);
                        if($ota == 'Be'){
                            $updateBE=$this->beUpdate($details);
                        }
                    }
                }
                if(count($updateBE)>=0 && count($updateOTA)>=0){
                    $updatePmsStatus=PmsInvPush::where('inventory_id',$details->inventory_id)->update(['push_status'=>1]);
                }
             }
             array_push($updateOTA,$updateBE);
             if(count($updateOTA)>0){
                 return $updateOTA;
             }
        }
    }
    public function beUpdate($details){
        $inventory= new Inventory();
        if($details->restriction_status == 'CTA'){
            $res_status=1;
        }
        else if($details->restriction_status == 'CTD'){
            $res_status=2;
        }
        else if($details->restriction_status == 'CTM'){
            $res_status=3;
        }
        else{
            $res_status=0;
        }
        $inv_data=array('hotel_id'=>$details->hotel_id,'room_type_id'=>$details->room_type_id,'no_of_rooms'=>$details->no_of_rooms,'date_from'=>$details->date_from,'date_to'=>$details->date_to,'client_ip'=>$details->client_ip,'user_id'=>$details->user_id,'block_status'=>$details->block_status,'los'=>$details->los,'multiple_days'=>$details->multiple_days,'restriction_status'=>$res_status);
        if($inventory->fill($inv_data)->save()){
            return array('status'=>1,'message'=>"update successfully",'be'=>'BE');
        }
        else{
            return array('status'=>0,'message'=>"update fails",'be'=>'BE');
        }
    }
    public function otaUpdate($details,$getOtas){
        $resp=array();
        $bucketdata =array();
        $otaName='';
       foreach($getOtas as $ota){
        $bucketdata                   = [
            "bucket_id"                      => 0,
            "bucket_hotel_id"                => $details->hotel_id,
            "bucket_inventory_table_id"      => 0,
            "bucket_ota_id"                  => $ota->ota_id,
            "bucket_ota_name"                => $ota->ota_name,
            "bucket_rate_plan_log_table_id"  => 0,
            "bucket_ota_hotel_code"          => $ota->ota_hotel_code,
            "bucket_client_ip"               => $details->client_ip,
            "bucket_user_id"                 => $details->user_id
            ];
            $auth_parameter=json_decode($ota->auth_parameter);
            if(isset($ota->ota_name)){
                $otaName=$ota->ota_name;
            }
            else{
                $otaName=$ota;
            }
        $inv_data=array('hotel_id'=>$details->hotel_id,'room_type_id'=>$details->room_type_id,'no_of_rooms'=>$details->no_of_rooms,'date_from'=>$details->date_from,'date_to'=>$details->date_to,'client_ip'=>$details->client_ip,'user_id'=>$details->user_id,'block_status'=>$details->block_status,'los'=>$details->los,'multiple_days'=>$details->multiple_days,'restriction_status'=>$details->restriction_status);
            switch($otaName){


                case "Goibibo":
                                    try{
                                       if($details->block_status == 1){
                                         $resp[]  =   $this->goibiboInventoryRatePushUpdate->blockInventoryUpdate($bucketdata,$details->room_type_id,$inv_data,$auth_parameter,$ota->url);
                                       }
                                       else{
                                        $resp[]  =   $this->goibiboInventoryRatePushUpdate->bulkInvUpdate($bucketdata,$inv_data,$auth_parameter,$ota->url);
                                      }
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                case "Booking.com":
                                    try{
                                      if($details->block_status == 1){
                                        $resp[]  =   $this->bookingdotcomInventoryRatePushUpdate->blockInventoryUpdate($bucketdata,$details->room_type_id,$inv_data,$auth_parameter,$ota->url);
                                      }
                                      else{
                                       $resp[] =   $this->bookingdotcomInventoryRatePushUpdate->bulkInvUpdate($bucketdata,$inv_data,$auth_parameter,$ota->url);
                                     }
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                case "EaseMyTrip":
                                    try{
                                        if($details->block_status == 1){
                                          $resp[]  =   $this->easemytripInventoryRatePushUpdate->blockInventoryUpdate($bucketdata,$details->room_type_id,$inv_data,$auth_parameter,$ota->url);
                                        }
                                        else{
                                         $resp[]  =   $this->easemytripInventoryRatePushUpdate->bulkInvUpdate($bucketdata,$inv_data,$auth_parameter,$ota->url);
                                       }
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                case "Paytm":
                                    try{
                                      if($details->block_status == 1){
                                        $resp[]  =   $this->paytmInventoryRatePushUpdate->blockInventoryUpdate($bucketdata,$details->room_type_id,$inv_data,$auth_parameter,$ota->url);
                                      }
                                      else{
                                       $resp[]  =   $this->paytmInventoryRatePushUpdate->bulkInvUpdate($bucketdata,$inv_data,$auth_parameter,$ota->url);
                                     }
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                case "Agoda":
                                    try{
                                      if($details->block_status == 1){
                                        $resp[]  =   $this->agodaInventoryRatePushUpdate->blockInventoryUpdate($bucketdata,$details->room_type_id,$inv_data,$auth_parameter,$ota->url);
                                      }
                                      else{
                                       $resp[]  =   $this->agodaInventoryRatePushUpdate->bulkInvUpdate($bucketdata,$inv_data,$auth_parameter,$ota->url);
                                     }
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                case "Expedia":
                                    try{
                                      if($details->block_status == 1){
                                        $resp[]  =   $this->expediaInventoryRatePushUpdate->blockInventoryUpdate($bucketdata,$details->room_type_id,$inv_data,$auth_parameter,$ota->url);
                                      }
                                      else{
                                       $resp[]  =   $this->expediaInventoryRatePushUpdate->bulkInvUpdate($bucketdata,$inv_data,$auth_parameter,$ota->url);
                                     }
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                case "Via.com":
                                    try{
                                      if($details->block_status == 1){
                                        $resp[]  =   $this->viaInventoryRatePushUpdate->blockInventoryUpdate($bucketdata,$details->room_type_id,$inv_data,$auth_parameter,$ota->url);
                                      }
                                      else{
                                       $resp[]  =   $this->viaInventoryRatePushUpdate->bulkInvUpdate($bucketdata,$inv_data,$auth_parameter,$ota->url);
                                     }
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                case "Goomo":
                                    try{
                                      if($details->block_status == 1){
                                        $resp[]  =   $this->goomoInventoryRatePushUpdate->blockInventoryUpdate($bucketdata,$details->room_type_id,$inv_data,$auth_parameter,$ota->url);
                                      }
                                      else{
                                       $resp[]  =   $this->goomoInventoryRatePushUpdate->bulkInvUpdate($bucketdata,$inv_data,$auth_parameter,$ota->url);
                                     }
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                case "Travelguru":
                                    try{
                                      if($details->block_status == 1){
                                        $resp[]  =   $this->travelguruInventoryRatePushUpdate->blockInventoryUpdate($bucketdata,$details->room_type_id,$inv_data,$auth_parameter,$ota->url);
                                      }
                                      else{
                                       $resp[]  =   $this->travelguruInventoryRatePushUpdate->bulkInvUpdate($bucketdata,$inv_data,$auth_parameter,$ota->url);
                                     }
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                case "Airbnb":
                                    try{
                                      if($details->block_status == 1){
                                        $resp[]  =   $this->airbnbInventoryRatePushUpdate->blockInventoryUpdate($bucketdata,$details->room_type_id,$inv_data,$auth_parameter,$ota->url);
                                      }
                                      else{
                                       $resp[]  =   $this->airbnbInventoryRatePushUpdate->bulkInvUpdate($bucketdata,$inv_data,$auth_parameter,$ota->url);
                                     }
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                default       :     $resp[] = array("status"=>0,"message"=>'No ota chosen for sync please choose!');
            }
       }
       return $resp;
    }
    public function pushRateToOta(Request $request){
        $getRateDetails=PmsRatePush::select('*')
                                ->where('push_status',0)
                                ->take(5)
                                ->get();
        $updateBE=array();
        $updateOTA=array();
        $getOtas=array();
        if(count($getRateDetails)>0){
            foreach($getRateDetails as $details){
                $ota_name=explode(',',$details->ota_details);
                foreach($ota_name as $ota){
                    if($ota == 'all'){
                        $getOtas=CmOtaDetails::select('*')
                        ->where('hotel_id',$details->hotel_id)
                        ->where('is_active',1)
                        ->get();
                        $updateBE=$this->beRateUpdate($details);
                        $updateOTA=$this->otaRateUpdate($details,$getOtas);
                    }
                    else{
                        $getOtas=CmOtaDetails::select('*')
                        ->where('hotel_id',$details->hotel_id)
                        ->where('ota_name',$ota)
                        ->where('is_active',1)
                        ->get();
                        $updateOTA=$this->otaRateUpdate($details,$getOtas);
                    }
                }
                 if(count($updateBE)>=0 && count($updateOTA)>=0){
                     $updatePmsStatus=PmsRatePush::where('rate_plan_log_id',$details->rate_plan_log_id)->update(['push_status'=>1]);
                 }
             }
             array_push($updateOTA,$updateBE);
             if(count($updateOTA)>0){
                 return $updateOTA;
             }
        }
    }
    public function beRateUpdate($details){
        $rate= new RatePlanLog();
        $rate_data=array('hotel_id'=>$details->hotel_id,'room_type_id'=>$details->room_type_id,'rate_plan_id'=>$details->rate_plan_id,'bar_price'=>$details->bar_price,
        'multiple_occupancy'=>$details->multiple_occupancy,'multiple_days'=>$details->multiple_days,'from_date'=>$details->from_date,'to_date'=>$details->to_date,'client_ip'=>$details->client_ip,'user_id'=>$details->user_id,'block_status'=>$details->block_status,'los'=>$details->los,'extra_adult_price'=>$details->extra_adult_price,'extra_child_price'=>$details->extra_child_price);
        if($rate->fill($rate_data)->save()){
            return array('status'=>1,'message'=>"update successfully",'be'=>'BE');
        }
        else{
            return array('status'=>0,'message'=>"update fails",'be'=>'BE');
        }
    }
    public function otaRateUpdate($details,$getOtas){
        $bucketdata =array();
        $resp=array();
       foreach($getOtas as $ota){

        $bucketdata                   = [
            "bucket_id"                      => 0,
            "bucket_hotel_id"                => $details->hotel_id,
            "bucket_inventory_table_id"      => 0,
            "bucket_ota_id"                  => $ota->ota_id,
            "bucket_ota_name"                => $ota->ota_name,
            "bucket_rate_plan_log_table_id"  => 0,
            "bucket_ota_hotel_code"          => $ota->ota_hotel_code,
            "bucket_client_ip"               => $details->client_ip,
            "bucket_user_id"                 => $details->user_id
            ];
            $rate_data=array('hotel_id'=>$details->hotel_id,'room_type_id'=>$details->room_type_id,'rate_plan_id'=>$details->rate_plan_id,'bar_price'=>$details->bar_price,
            'multiple_occupancy'=>json_decode($details->multiple_occupancy),'multiple_days'=>json_decode($details->multiple_days),'from_date'=>$details->from_date,'to_date'=>$details->to_date,'client_ip'=>$details->client_ip,'user_id'=>$details->user_id,'block_status'=>$details->block_status,'los'=>$details->los,'extra_adult_price'=>$details->extra_adult_price,'extra_child_price'=>$details->extra_child_price);
            if(isset($ota->ota_name)){
                $otaName=$ota->ota_name;
            }
            else{
                $otaName=$ota;
            }
            $auth_parameter=json_decode($ota->auth_parameter);
            switch($otaName){

                case "Goibibo":
                                    try{
                                        $resp[]  =   $this->goibiboInventoryRatePushUpdate->bulkRateUpdate($bucketdata,$rate_data,$auth_parameter,$ota->url);
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                case "Booking.com":
                                    try{
                                        $resp[] =   $this->bookingdotcomInventoryRatePushUpdate->bulkRateUpdate($bucketdata,$rate_data,$auth_parameter,$ota->url);
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                case "EaseMyTrip":
                                    try{
                                        $resp[] =   $this->easemytripInventoryRatePushUpdate->bulkRateUpdate($bucketdata,$rate_data,$auth_parameter,$ota->url);
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                case "Paytm":
                                    try{
                                        $resp[] =   $this->paytmInventoryRatePushUpdate->bulkRateUpdate($bucketdata,$rate_data,$auth_parameter,$ota->url);
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                case "Agoda":
                                    try{
                                        $resp[] =   $this->agodaInventoryRatePushUpdate->bulkRateUpdate($bucketdata,$rate_data,$auth_parameter,$ota->url);
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                case "Expedia":
                                    try{
                                        $resp[] =   $this->expediaInventoryRatePushUpdate->bulkRateUpdate($bucketdata,$rate_data,$auth_parameter,$ota->url);
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                case "Via.com":
                                    try{
                                        $resp[] =   $this->viaInventoryRatePushUpdate->bulkRateUpdate($bucketdata,$rate_data,$auth_parameter,$ota->url);
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                case "Goomo":
                                    try{
                                        $resp[] =   $this->goomoInventoryRatePushUpdate->bulkRateUpdate($bucketdata,$rate_data,$auth_parameter,$ota->url);
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                case "Travelguru":
                                    try{
                                        $resp[] =   $this->travelguruInventoryRatePushUpdate->bulkRateUpdate($bucketdata,$rate_data,$auth_parameter,$ota->url);
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                case "Airbnb":
                                    try{
                                        $resp[] =   $this->airbnbInventoryRatePushUpdate->bulkRateUpdate($bucketdata,$rate_data,$auth_parameter,$ota->url);
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                default       :     $resp[] = array("status"=>0,"message"=>'No ota chosen for sync please choose!');
            }
       }
       return $resp;
    }
    //changes by subash
    public function pushWinhmsInventoryToOta(Request $request){
        $today = date('Y-m-d');
        // $cur_date = date('Y-m-d',strtotime($today.'-1 day'));
        $getInventoryDetails=WinHms::select('*')
                                ->where('push_status',0)
                                ->whereDate('date_to','>=',$today)
                                ->take(20)
                                ->get();
        $updateBE=array();
        $updateOTA=array();
        $getOtas=array();
        if(count($getInventoryDetails)>0){
            foreach($getInventoryDetails as $details){
                $update_push_status = WinHms::where('inventory_id',$details->inventory_id)->update(['push_status'=>2]);
                $ota_name=explode(',',$details->ota_details);
                foreach($ota_name as $ota){
                    if($ota == 'all'){
                        $getOtas=CmOtaDetails::select('*')
                        ->where('hotel_id',$details->hotel_id)
                        ->where('is_active',1)
                        ->get();
                        $updateBE=$this->beUpdate($details);
                        $updateOTA=$this->otaUpdate($details,$getOtas);
                    }
                    else{
                        $ota = ucfirst($ota);
                        $getOtas=CmOtaDetails::select('*')
                        ->where('hotel_id',$details->hotel_id)
                        ->where('ota_name',$ota)
                        ->where('is_active',1)
                        ->get();

                        $updateOTA=$this->otaUpdate($details,$getOtas);
                        if($ota == 'Be'){
                            $updateBE=$this->beUpdate($details);
                        }
                    }
                }
                if(count($updateBE)>=0 && count($updateOTA)>=0){
                    $updatePmsStatus=WinHms::where('inventory_id',$details->inventory_id)->update(['push_status'=>1]);
                }
             }
             array_push($updateOTA,$updateBE);
             if(count($updateOTA)>0){
                 return $updateOTA;
             }
        }
    }
    /**
     * This below function used to change the status of pushstatus if the date_to less than today.
     */
    public function makeUpdateStatusAsTwoForWinhms(){
        $today = date('Y-m-d');
        // $cur_date = date('Y-m-d',strtotime($today.'-1 day'));
        $getInventoryDetails=WinHms::select('*')
                                ->where('push_status',0)
                                ->whereDate('date_to','<',$today)
                                ->take(20)
                                ->get();
        if(count($getInventoryDetails)>0){
            foreach($getInventoryDetails as $details){
                $update_push_status = WinHms::where('inventory_id',$details->inventory_id)->update(['push_status'=>2]);
            }
        }
    }
}