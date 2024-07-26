<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Validator;
use DB;
use App\CmOtaDetails;
use App\PmsInvPush;
use App\PmsRatePush;
use App\Inventory;
use App\RatePlanLog;
use App\IdsInvPush;
use App\KtdcInvPush;
use App\KtdcInvBucketPush;
use App\KtdcInventoryBucketProcessing;
use App\KtdcInventoryBucketProcessingAfterStop;
use App\TtdcInvPush;
use App\TtdcInventoryBucketProcessing;
use App\WinHmsInventoryPush;
use App\WinhmsInventoryBucketProcessing;
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
use App\Http\Controllers\invrateupdatecontrollers\IrctcInvRateController;
use App\Http\Controllers\invrateupdatecontrollers\AkbarInvRateController;
use App\Http\Controllers\invrateupdatecontrollers\HegInvRateController;

class PmsPushDataToOtaController extends Controller
{
    /**
     * This controller is used to push inventory and rate to OTA and BE by cron job.
     * @author Ranjit Date of creation: 05-06-2019
     */
    protected $goibiboInventoryRatePushUpdate,$bookingdotcomInventoryRatePushUpdate;
    protected $easemytripInventoryRatePushUpdate,$paytmInventoryRatePushUpdate,$agodaInventoryRatePushUpdate;
    protected $expediaInventoryRatePushUpdate,$goomoInventoryRatePushUpdate,$travelguruInventoryRatePushUpdate,$irctcInventoryRatePushUpdate,$akbarInventoryRatePushUpdate,$hegInventoryRatePushUpdate;

    public function __construct(GoibiboInvRateController $goibiboInventoryRatePushUpdate,
    BookingdotcomInvRateController $bookingdotcomInventoryRatePushUpdate,
    EaseMyTripInvRateController $easemytripInventoryRatePushUpdate,
    PaytmInvRateController $paytmInventoryRatePushUpdate,
    AgodaInvRateController $agodaInventoryRatePushUpdate,
    ExpediaInvRateController $expediaInventoryRatePushUpdate,
    ViaInvRateController $viaInventoryRatePushUpdate,
    GoomoInvRateController $goomoInventoryRatePushUpdate,
    TravelguruInvRateController  $travelguruInventoryRatePushUpdate,
    AirbnbInvRateController $airbnbInventoryRatePushUpdate,
    AkbarInvRateController $akbarInventoryRatePushUpdate,
    IrctcInvRateController $irctcInventoryRatePushUpdate,
    HegInvRateController $hegInventoryRatePushUpdate)
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
       $this->akbarInventoryRatePushUpdate          = $akbarInventoryRatePushUpdate;
       $this->irctcInventoryRatePushUpdate          = $irctcInventoryRatePushUpdate;
       $this->hegInventoryRatePushUpdate            = $hegInventoryRatePushUpdate;
    }
    public function pushInventoryToOta(Request $request){
        $getInventoryDetails=PmsInvPush::select('*')
                                ->where('hotel_id','!=',1151)
                                ->where('hotel_id','!=',867)
                                ->where('push_status',0)
                                ->take(5)
                                ->get();
        $updateBE=array();
        $updateOTA=array();
        $getOtas=array();
        if(sizeof($getInventoryDetails)>0){
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
                if(sizeof($updateBE)>=0 && sizeof($updateOTA)>=0){
                    $updatePmsStatus=PmsInvPush::where('inventory_id',$details->inventory_id)->update(['push_status'=>1]);
                }
             }
             array_push($updateOTA,$updateBE);
             if(sizeof($updateOTA)>0){
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
                case "Akbar":
                                    try{
                                        if($details->block_status == 1){
                                        $resp[]  =   $this->akbarInventoryRatePushUpdate->blockInventoryUpdate($bucketdata,$details->room_type_id,$inv_data,$auth_parameter,$ota->url);
                                        }
                                        else{
                                        $resp[]  =   $this->akbarInventoryRatePushUpdate->bulkInvUpdate($bucketdata,$inv_data,$auth_parameter,$ota->url);
                                        }
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                case "IRCTC":
                                    try{
                                        if($details->block_status == 1){
                                        $resp[]  =   $this->irctcInventoryRatePushUpdate->blockInventoryUpdate($bucketdata,$details->room_type_id,$inv_data,$auth_parameter,$ota->url);
                                        }
                                        else{
                                        $resp[]  =   $this->irctcInventoryRatePushUpdate->bulkInvUpdate($bucketdata,$inv_data,$auth_parameter,$ota->url);
                                        }
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                case "HappyEasyGo":
                                        try{
                                            if($details->block_status == 1){
                                            $resp[]  =   $this->hegInventoryRatePushUpdate->blockInventoryUpdate($bucketdata,$details->room_type_id,$inv_data,$auth_parameter,$ota->url);
                                            }
                                            else{
                                            $resp[]  =   $this->hegInventoryRatePushUpdate->bulkInvUpdate($bucketdata,$inv_data,$auth_parameter,$ota->url);
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
        if(sizeof($getRateDetails)>0){
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
                 if(sizeof($updateBE)>=0 && sizeof($updateOTA)>=0){
                     $updatePmsStatus=PmsRatePush::where('rate_plan_log_id',$details->rate_plan_log_id)->update(['push_status'=>1]);
                 }
             }
             array_push($updateOTA,$updateBE);
             if(sizeof($updateOTA)>0){
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
                case "IRCTC":
                                    try{
                                        $resp[] =   $this->irctcInventoryRatePushUpdate->bulkRateUpdate($bucketdata,$rate_data,$auth_parameter,$ota->url);
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                case "Akbar":
                                    try{
                                        $resp[] =   $this->akbarInventoryRatePushUpdate->bulkRateUpdate($bucketdata,$rate_data,$auth_parameter,$ota->url);
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                case "HappyEasyGo":
                                    try{
                                        $resp[] =   $this->hegInventoryRatePushUpdate->bulkRateUpdate($bucketdata,$rate_data,$auth_parameter,$ota->url);
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
    public function pushIdsInventoryToOta(Request $request){
        $today = date('Y-m-d');
        // $cur_date = date('Y-m-d',strtotime($today.'-1 day'));
        $getInventoryDetails=IdsInvPush::select('*')
                                ->where('push_status','=',0)
                                ->where('hotel_id','!=',1151)
                                ->where('hotel_id','!=',867)
                                ->where('hotel_id','!=',2316)
                                ->where('hotel_id','!=',1731)
                                ->where('hotel_id','!=',2867)
                                ->whereDate('date_to','>=',$today)
                                ->take(20)
                                ->get();
        $updateBE=array();
        $updateOTA=array();
        $getOtas=array();
        if(sizeof($getInventoryDetails)>0){
            foreach($getInventoryDetails as $details){
                $update_push_status = IdsInvPush::where('inventory_id',$details->inventory_id)->update(['push_status'=>2]);
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
                if(sizeof($updateBE)>=0 && sizeof($updateOTA)>=0){
                    $updatePmsStatus=IdsInvPush::where('inventory_id',$details->inventory_id)->update(['push_status'=>1]);
                }
             }
             array_push($updateOTA,$updateBE);
             if(sizeof($updateOTA)>0){
                 return $updateOTA;
             }
        }
    }
    public function pushKtdcInventoryToOta(Request $request){
        $today = date('Y-m-d');
        // $getInventoryDetails=KtdcInvPush::select('*')
        //                         ->where('push_status',0)
        //                         ->orderBy('inventory_id','ASC')
        //                         ->take(10)
        //                         ->get();
        $getInventoryDetails=DB::select(DB::raw('SELECT * FROM ktdc_inventory_push where push_status = 0 and hotel_id not in (select hotel_id from ktdc_inventory_push where push_status = 2) order by inventory_id ASC limit 10'));

        $updateBE=array();
        $updateOTA=array();
        $getOtas=array();
        if(sizeof($getInventoryDetails)>0){
            try{
                foreach($getInventoryDetails as $process){
                    $getStatus = KtdcInventoryBucketProcessing::select('hotel_id')
                    ->where(['hotel_id'=>$process->hotel_id,'room_type_id'=>$process->room_type_id])
                    ->where('is_processed',1)
                    ->first();
                    if($getStatus){
                        continue;
                    }
                    else{
                        foreach($getInventoryDetails as $key => $process1){
                            if($process1->hotel_id == $process->hotel_id){
                                // $process1->push_status = 2;
                                // $process1->save();
                                $update = KtdcInvPush::where('inventory_id',$process1->inventory_id)->update(['push_status'=>2]);
    
                                $bucket_data_processing_array[]=$getInventoryDetails[$key];//array assign
    
                                $tracker_data = array('hotel_id'=>$process1->hotel_id,'room_type_id'=>$process1->room_type_id,'bucket_id'=>$process1->inventory_id,'is_processed'=>1);
                                $insert_data_to_tracker = KtdcInventoryBucketProcessing::insert($tracker_data);
                            }
                        }
                    }
                }
            }
            catch(Exception $e){
                echo 'Message: ' .$e->getMessage();
            }
            if(isset($bucket_data_processing_array) && sizeof($bucket_data_processing_array)>0){
                foreach($bucket_data_processing_array as $details){
                    $ota_name=explode(',',$details->ota_details);
                    $bucket_id = $details->inventory_id;
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
                    if(sizeof($updateBE)>=0 && sizeof($updateOTA)>=0){
                        $updatePmsStatus=KtdcInvPush::where('inventory_id',$details->inventory_id)->update(['push_status'=>1]);
                        $delete_data_from_tracker = KtdcInventoryBucketProcessing::where('bucket_id',$bucket_id)->delete();//deletion from bucket tracker
                    }
                 }
                 array_push($updateOTA,$updateBE);
                 if(sizeof($updateOTA)>0){
                     return $updateOTA;
                 }
            }
        }
    }
    /**
     * below function send a message and mail if the ktdc inventory processing showing error.
     * @author ranjit Date 29-12-2021
     */
    public function sendMsgAndMail(){
        $today = date('Y-m-d H:i:s');
        $check_status = KtdcInvPush::select('*')
                        ->where('push_status',0)
                        ->first();
        $start = strtotime($check_status->created_at);
        $end = strtotime($today);
        $diff = $end - $start;
        $minutes=intval((floor($diff/60)));
        if($minutes >= 10){
             $runBucket = $this->ktdcRunBucketAfterStop();
        }
        if($minutes >= 15){
            $msgContent = "KTDCCRSprocessingstoppedforid";
            $smsURL = "https://apps.sandeshlive.com/API/WebSMS/Http/v1.0a/index.php?userid=1135&password=F4lKwI80ROA51fyq&sender=BKJINI&to=8328869476,7008633177&message=The booking processing has been stopped, last booking processed at: ".$msgContent.". Regards,BKJINI&reqid=1&format={json|text}&route_id=3";
            $ch = curl_init();
            curl_setopt($ch,CURLOPT_URL, $smsURL);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
            curl_setopt($ch,CURLOPT_HEADER, false); 
            curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, false); 
            curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, false);   
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET'); 
            $output=curl_exec($ch);

            $mail_array=['ranjit.dash@5elements.co.in','abhijith.g@bookingjini.com'];
            $data = array('sir_email' =>'manoj.pandia@5elements.co.in','subject'=>"Ktdc CRS inventory update stopped",'mail_array'=>$mail_array);
            Mail::send([],[],function ($message) use ($data)
            {
                $message->to($data['sir_email'])
                ->cc($data['mail_array'])
                ->from(env("MAIL_FROM"))
                ->subject( $data['subject']);
            });
        }
    }
    public function ktdcRunBucketAfterStop(){
        $today = date('Y-m-d');
        $getInventoryDetails=KtdcInvPush::select('*')
                                ->where('push_status',2)
                                ->where('date_to','>=',$today)
                                ->orderBy('inventory_id','ASC')
                                ->take(10)
                                ->get();
        $updateBE=array();
        $updateOTA=array();
        $getOtas=array();
        if(sizeof($getInventoryDetails)>0){
            try{
                foreach($getInventoryDetails as $process){
                    $getStatus = KtdcInventoryBucketProcessingAfterStop::select('hotel_id')
                    ->where(['hotel_id'=>$process->hotel_id,'room_type_id'=>$process->room_type_id])
                    ->where('is_processed',1)
                    ->first();
                    if($getStatus){
                        continue;
                    }
                    else{
                        foreach($getInventoryDetails as $key => $process1){
                            if($process1->hotel_id == $process->hotel_id){
                                $process1->push_status = 5;
                                $process1->save();
    
                                $bucket_data_processing_array[]=$getInventoryDetails[$key];//array assign
    
                                $tracker_data = array('hotel_id'=>$process1->hotel_id,'bucket_id'=>$process1->inventory_id,'is_processed'=>1,'room_type_id'=>$process1->room_type_id);
                                $insert_data_to_tracker = KtdcInventoryBucketProcessingAfterStop::insert($tracker_data);
                            }
                        }
                    }
                }
            }
            catch(Exception $e){
                echo 'Message: ' .$e->getMessage();
            }
            if(isset($bucket_data_processing_array) && sizeof($bucket_data_processing_array)>0){
                foreach($bucket_data_processing_array as $details){
                    $ota_name=explode(',',$details->ota_details);
                    $bucket_id = $details->inventory_id;
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
                    if(sizeof($updateBE)>=0 && sizeof($updateOTA)>=0){
                        $updatePmsStatus=KtdcInvPush::where('inventory_id',$details->inventory_id)->update(['push_status'=>1]);
                        $delete_data_from_tracker = KtdcInventoryBucketProcessingAfterStop::where('bucket_id',$bucket_id)->delete();//deletion from bucket tracker
                        $delete_data_from_tracker = KtdcInventoryBucketProcessing::where('bucket_id',$bucket_id)->delete();//deletion from bucket tracker
                    }
                 }
                 array_push($updateOTA,$updateBE);
                 if(sizeof($updateOTA)>0){
                     return $updateOTA;
                 }
            }
        }
    }
    public function pushTtdcInventoryToOta(Request $request){
        $today = date('Y-m-d');
        // $cur_date = date('Y-m-d',strtotime($today.'-1 day'));
        $getInventoryDetails=TtdcInvPush::select('*')
                                ->where('push_status',0)
                                ->whereDate('date_to','>=',$today)
                                ->orderBy('inventory_id','ASC')
                                ->take(12)
                                ->get();
        $updateBE=array();
        $updateOTA=array();
        $getOtas=array();
        if(sizeof($getInventoryDetails)>0){
            try{
                foreach($getInventoryDetails as $process){
                    $getStatus = TtdcInventoryBucketProcessing::select('hotel_id')
                    ->where(['hotel_id'=>$process->hotel_id,'room_type_id'=>$process->room_type_id])
                    ->where('is_processed',1)
                    ->first();
                    if($getStatus){
                        continue;
                    }
                    else{
                        foreach($getInventoryDetails as $key => $process1){
                            if($process1->hotel_id == $process->hotel_id){
                                $process1->push_status = 2;
                                $process1->save();
    
                                $bucket_data_processing_array[]=$getInventoryDetails[$key];//array assign
    
                                $tracker_data = array('hotel_id'=>$process1->hotel_id,'bucket_id'=>$process1->inventory_id,'is_processed'=>1,'room_type_id'=>$process1->room_type_id);
                                $insert_data_to_tracker = TtdcInventoryBucketProcessing::insert($tracker_data);
                            }
                        }
                    }
                }
            }
            catch(Exception $e){
                echo 'Message: ' .$e->getMessage();
            }
            if(isset($bucket_data_processing_array) && sizeof($bucket_data_processing_array)>0){
                foreach($bucket_data_processing_array as $details){
                    $ota_name=explode(',',$details->ota_details);
                    $bucket_id = $details->inventory_id;
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
                    if(sizeof($updateBE)>=0 && sizeof($updateOTA)>=0){
                        $updatePmsStatus=TtdcInvPush::where('inventory_id',$details->inventory_id)->update(['push_status'=>1]);
                        $delete_data_from_tracker = TtdcInventoryBucketProcessing::where('bucket_id',$bucket_id)->delete();//deletion from bucket tracker
                    }
                 }
                 array_push($updateOTA,$updateBE);
                 if(sizeof($updateOTA)>0){
                     return $updateOTA;
                 }
            }
        }
    }
    public function pushWinHmsInventoryToOta(Request $request){
        $today = date('Y-m-d');
        $getInventoryDetails=WinHmsInventoryPush::select('*')
                                ->where('push_status',0)
                                ->whereDate('date_to','>=',$today)
                                ->orderBy('inventory_id','ASC')
                                ->take(12)
                                ->get();
        $updateBE=array();
        $updateOTA=array();
        $getOtas=array();
        if(sizeof($getInventoryDetails)>0){
            try{
                foreach($getInventoryDetails as $process){
                    $getStatus = WinhmsInventoryBucketProcessing::select('hotel_id')
                    ->where(['hotel_id'=>$process->hotel_id,'room_type_id'=>$process->room_type_id])
                    ->where('is_processed',1)
                    ->first();
                    if($getStatus){
                        continue;
                    }
                    else{
                        foreach($getInventoryDetails as $key => $process1){
                            if($process1->hotel_id == $process->hotel_id){
                                $process1->push_status = 2;
                                $process1->save();
    
                                $bucket_data_processing_array[]=$getInventoryDetails[$key];//array assign
    
                                $tracker_data = array('hotel_id'=>$process1->hotel_id,'bucket_id'=>$process1->inventory_id,'is_processed'=>1,'room_type_id'=>$process1->room_type_id);
                                $insert_data_to_tracker = WinhmsInventoryBucketProcessing::insert($tracker_data);
                            }
                        }
                    }
                }
            }
            catch(Exception $e){
                echo 'Message: ' .$e->getMessage();
            }
            if(isset($bucket_data_processing_array) && sizeof($bucket_data_processing_array)>0){
                foreach($bucket_data_processing_array as $details){
                    $ota_name=explode(',',$details->ota_details);
                    $bucket_id = $details->inventory_id;
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
                    if(sizeof($updateBE)>=0 && sizeof($updateOTA)>=0){
                        $updatePmsStatus=WinHmsInventoryPush::where('inventory_id',$details->inventory_id)->update(['push_status'=>1]);
                        $delete_data_from_tracker = WinhmsInventoryBucketProcessing::where('bucket_id',$bucket_id)->delete();//deletion from bucket tracker
                    }
                 }
                 array_push($updateOTA,$updateBE);
                 if(sizeof($updateOTA)>0){
                     return $updateOTA;
                 }
            }
        }
    }
    /**
     * This below function used to change the status of pushstatus if the date_to less than today.
     */
    public function makeUpdateStatusAsTwoForIDS(){
        $today = date('Y-m-d');
        // $cur_date = date('Y-m-d',strtotime($today.'-1 day'));
        $getInventoryDetails=IdsInvPush::select('*')
                                ->where('push_status','=',0)
                                ->whereDate('date_to','<',$today)
                                ->take(20)
                                ->get();
        if(sizeof($getInventoryDetails)>0){
            foreach($getInventoryDetails as $details){
                $update_push_status = IdsInvPush::where('inventory_id',$details->inventory_id)->update(['push_status'=>2]);
            }
        }
    }
     /**
     * This below function used to change the status of pushstatus if the date_to less than today.
     */
    public function makeUpdateStatusAsTwoForKTDC(){
        $today = date('Y-m-d');
        // $cur_date = date('Y-m-d',strtotime($today.'-1 day'));
        $getInventoryDetails=KtdcInvPush::select('*')
                                ->where('push_status',0)
                                ->whereDate('date_to','<',$today)
                                ->take(20)
                                ->get();
        if(sizeof($getInventoryDetails)>0){
            foreach($getInventoryDetails as $details){
                $update_push_status = KtdcInvPush::where('inventory_id',$details->inventory_id)->update(['push_status'=>2]);
            }
        }
    }
}
