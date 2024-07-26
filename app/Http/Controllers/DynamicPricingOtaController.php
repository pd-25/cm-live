<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use DB;
use App\Http\Controllers\Controller;
use App\Http\Controllers\invrateupdatecontrollers\GoibiboInvRateController;
use App\Http\Controllers\invrateupdatecontrollers\BookingdotcomInvRateController;
use App\Http\Controllers\invrateupdatecontrollers\EaseMyTripInvRateController;
use App\Http\Controllers\invrateupdatecontrollers\PaytmInvRateController;
use App\Http\Controllers\invrateupdatecontrollers\AgodaInvRateController;
use App\Http\Controllers\invrateupdatecontrollers\CleartripInvRateController;
use App\Http\Controllers\invrateupdatecontrollers\ExpediaInvRateController;
use App\Http\Controllers\invrateupdatecontrollers\ViaInvRateController;
use App\Http\Controllers\invrateupdatecontrollers\GoomoInvRateController;
use App\Http\Controllers\invrateupdatecontrollers\TravelguruInvRateController;
use App\Http\Controllers\invrateupdatecontrollers\AirbnbInvRateController;
/**
* This Controller is use for all functionality of dynamic pricing
*@author Ranjit Date- 26-03-2020
*/
class DynamicPricingOtaController extends Controller
{
    protected $goibiboInventoryRatePushUpdate,$bookingdotcomInventoryRatePushUpdate,$easemytripInventoryRatePushUpdate,$paytmInventoryRatePushUpdate,$agodaInventoryRatePushUpdate,
    $expediaInventoryRatePushUpdate,$goomoInventoryRatePushUpdate,$travelguruInventoryRatePushUpdate,$airbnbInventoryRatePushUpdate,$viaInventoryRatePushUpdate;
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
    public function pushToOta(){
      $resp=array();
      foreach($imp_data['ota_id'] as $otaid){
          $ota_details = CmOtaDetails::select('*')
          ->where('hotel_id', '=' ,$imp_data['hotel_id'])
          ->where('ota_id','=',$otaid)
          ->where('is_active', '=' ,1)
          ->first();
          if($ota_details){
              $bucketdata = $this->creatingBucketdata($ota_details,$imp_data['hotel_id'],$imp_data['client_ip'],$imp_data['user_id']);
          }
        switch($info['rate_bucket_data']['bucket_ota_name']){

            case "Goibibo":
                                try{
                                    $resp  =   $this->goibiboInventoryRatePushUpdate->bulkInvUpdate($info['rate_bucket_data'],$info['data'],$info['auth_parameter'],$info['commonUrl']);
                                }
                                catch(Exception $resp){

                                }
                                break;
            case "Booking.com":
                                try{
                                    $resp =   $this->bookingdotcomInventoryRatePushUpdate->bulkInvUpdate($info['bucket_data'],$info['data'],$info['auth_parameter'],$info['commonUrl']);
                                }
                                catch(Exception $resp)  {

                                }
                                break;
            case "EaseMyTrip":
                                try{
                                    $resp =   $this->easemytripInventoryRatePushUpdate->bulkInvUpdate($info['bucket_data'],$info['data'],$info['auth_parameter'],$info['commonUrl']);
                                }
                                catch(Exception $resp){

                                }
                                break;
            case "Paytm":
                                try{
                                    $resp =   $this->paytmInventoryRatePushUpdate->bulkInvUpdate($info['bucket_data'],$info['data'],$info['auth_parameter'],$info['commonUrl']);
                                }
                                catch(Exception $resp){

                                }
                                break;
            case "Agoda":
                                try{
                                    $resp =   $this->agodaInventoryRatePushUpdate->bulkInvUpdate($info['bucket_data'],$info['data'],$info['auth_parameter'],$info['commonUrl']);
                                }
                                catch(Exception $resp){

                                }
                                break;
            case "Expedia":
                                try{
                                    $resp =   $this->expediaInventoryRatePushUpdate->bulkInvUpdate($info['bucket_data'],$info['data'],$info['auth_parameter'],$info['commonUrl']);
                                }
                                catch(Exception $resp){

                                }
                                break;
            case "Via.com":
                                try{
                                    $resp =   $this->viaInventoryRatePushUpdate->bulkInvUpdate($info['bucket_data'],$info['data'],$info['auth_parameter'],$info['commonUrl']);
                                }
                                catch(Exception $resp){

                                }
                                break;
            case "Goomo":
                                try{
                                    $resp =   $this->goomoInventoryRatePushUpdate->bulkInvUpdate($info['bucket_data'],$info['data'],$info['auth_parameter'],$info['commonUrl']);
                                }
                                catch(Exception $resp){

                                }
                                break;
            case "Travelguru":
                                try{
                                    $resp =   $this->travelguruInventoryRatePushUpdate->bulkInvUpdate($info['bucket_data'],$info['data'],$info['auth_parameter'],$info['commonUrl']);
                                }
                                catch(Exception $resp){

                                }
                                break;
            case "Airbnb":
                                try{
                                    $resp =   $this->airbnbInventoryRatePushUpdate->bulkInvUpdate($info['bucket_data'],$info['data'],$info['auth_parameter'],$info['commonUrl']);
                                }
                                catch(Exception $resp){

                                }
                                break;
            default       :     $resp = array("status"=>0,"message"=>'No ota choosen for rate update please choose!');
        }
        return $resp;
    }
    public function creatingBucketdata($ota_details,$hotel_id,$client_ip,$user_id){
        if($ota_details){
            $bucket_data                   = [
                "bucket_id"                      => 0,
                "bucket_hotel_id"                => $hotel_id,
                "bucket_inventory_table_id"      => 0,
                "bucket_ota_id"                  => $ota_details['ota_id'],
                "bucket_ota_name"                => $ota_details['ota_name'],
                "bucket_rate_plan_log_table_id"  => 0,
                "bucket_ota_hotel_code"          => $ota_details['ota_hotel_code'],
                "bucket_client_ip"               => $client_ip,
                "bucket_user_id"                 => $user_id
                ];

            $ota_name            = $ota_details['ota_name'];
            $auth_parameter      = json_decode($ota_details['auth_parameter']);
            $commonUrl           = $ota_details['url'];
            return array('bucket_data'=>$bucket_data,'ota_name'=>$ota_name,'auth_parameter'=>$auth_parameter,'commonUrl'=>$commonUrl);
        }
    }
}
