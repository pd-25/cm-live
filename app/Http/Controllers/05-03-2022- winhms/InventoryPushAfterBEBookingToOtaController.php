<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Validator;
use DB;
use App\CmOtaManageInventoryBucket;
use App\CmOtaDetails;
use App\Invoice;
use App\Http\Controllers\IpAddressService;
use App\Http\Controllers\IdsXmlCreationAndExecutionController;
use App\Http\Controllers\invrateupdatecontrollers\GoibiboInvRateController;
use App\Http\Controllers\invrateupdatecontrollers\BookingEngineInvRateController;
use App\Http\Controllers\invrateupdatecontrollers\BookingdotcomInvRateController;
use App\Http\Controllers\invrateupdatecontrollers\EaseMyTripInvRateController;
use App\Http\Controllers\invrateupdatecontrollers\PaytmInvRateController;
use App\Http\Controllers\invrateupdatecontrollers\AgodaInvRateController;
use App\Http\Controllers\invrateupdatecontrollers\ExpediaInvRateController;
use App\Http\Controllers\invrateupdatecontrollers\ViaInvRateController;
use App\Http\Controllers\invrateupdatecontrollers\GoomoInvRateController;
use App\Http\Controllers\invrateupdatecontrollers\TravelguruInvRateController;
use App\Http\Controllers\invrateupdatecontrollers\AirbnbInvRateController;
use App\Http\Controllers\BeConfirmBookingInvUpdateRedirectingController;

class InventoryPushAfterBEBookingToOtaController extends Controller{
  protected $idsPush;
  protected $goibiboInventoryRatePushUpdate,$ipService,$bookingEngineInventoryRatePushUpdate,$bookingdotcomInventoryRatePushUpdate,$viaInventoryRatePushUpdate;
  protected $easemytripInventoryRatePushUpdate,$paytmInventoryRatePushUpdate,$agodaInventoryRatePushUpdate;
  protected $expediaInventoryRatePushUpdate,$goomoInventoryRatePushUpdate,$travelguruInventoryRatePushUpdate,$airbnbInventoryRatePushUpdate;
  protected $beConfBookingInvUpdate;
  public function __construct(GoibiboInvRateController $goibiboInventoryRatePushUpdate,
  IpAddressService $ipService,
  BookingdotcomInvRateController $bookingdotcomInventoryRatePushUpdate,
  EaseMyTripInvRateController $easemytripInventoryRatePushUpdate,
  PaytmInvRateController $paytmInventoryRatePushUpdate,
  AgodaInvRateController $agodaInventoryRatePushUpdate,
  ExpediaInvRateController $expediaInventoryRatePushUpdate,
  ViaInvRateController $viaInventoryRatePushUpdate,
  GoomoInvRateController $goomoInventoryRatePushUpdate,
  TravelguruInvRateController  $travelguruInventoryRatePushUpdate,
  AirbnbInvRateController $airbnbInventoryRatePushUpdate,
  IdsXmlCreationAndExecutionController $idsPush,BeConfirmBookingInvUpdateRedirectingController $beConfBookingInvUpdate)
  {
     $this->goibiboInventoryRatePushUpdate        = $goibiboInventoryRatePushUpdate;
     $this->ipService                             = $ipService;
     $this->bookingdotcomInventoryRatePushUpdate  = $bookingdotcomInventoryRatePushUpdate;
     $this->easemytripInventoryRatePushUpdate     = $easemytripInventoryRatePushUpdate;
     $this->paytmInventoryRatePushUpdate          = $paytmInventoryRatePushUpdate;
     $this->agodaInventoryRatePushUpdate          = $agodaInventoryRatePushUpdate;
     $this->expediaInventoryRatePushUpdate        = $expediaInventoryRatePushUpdate;
     $this->viaInventoryRatePushUpdate            = $viaInventoryRatePushUpdate;
     $this->goomoInventoryRatePushUpdate          = $goomoInventoryRatePushUpdate;
     $this->travelguruInventoryRatePushUpdate     = $travelguruInventoryRatePushUpdate;
     $this->airbnbInventoryRatePushUpdate         = $airbnbInventoryRatePushUpdate;
     $this->idsPush                               = $idsPush;
     $this->beConfBookingInvUpdate                = $beConfBookingInvUpdate;

  }
  public function getDetails(Request $request){
     $info = $request->getContent();
     parse_str($info,$data);
     // $inv_data = array('hotel_id'=>$data['hotel_id'],'room_type_id'=>$data['room_type_id'],'user_id'=>$data['user_id'],'date_from'=>$data['date_from'],'date_to'=>$data['date_to'],'no_of_rooms'=>$data['no_of_rooms'],'client_ip'=>$data['client_ip'],'ota_id'=>$data['ota_id']);
     // $resp = $this->pushToOta($data['invoice_id']);
     if(isset($data['ids_re_id']) && $data['ids_re_id']!=NULL ){
          $this->idsPush->pushIds($data['invoice_id']);
     }
  }
  public function getWinhmsDetails(Request $request){
     $info = $request->getContent();
     parse_str($info,$data);
     // $inv_data = array('hotel_id'=>$data['hotel_id'],'room_type_id'=>$data['room_type_id'],'user_id'=>$data['user_id'],'date_from'=>$data['date_from'],'date_to'=>$data['date_to'],'no_of_rooms'=>$data['no_of_rooms'],'client_ip'=>$data['client_ip'],'ota_id'=>$data['ota_id']);
     // $resp = $this->pushToOta($data['invoice_id']);
     if(isset($data['winhms_re_id']) && $data['winhms_re_id']!=NULL ){
          $this->idsPush->pushWinhms($data['invoice_id']);
     }
  }
  public function pushToOta($invoice_id){
    $invoiceData=Invoice::where('invoice_id',$invoice_id)->first();
    $hotelBookingData=HotelBooking::where('invoice_id',$invoice_id)->get();
    if($invoiceData->is_cm==1){
    foreach ($hotelBookingData as $key => $hotelBooking) {
      $booking_details=[];
      $booking_details['checkin_at'] =$hotelBooking->check_in;
      $booking_details['checkout_at'] =$hotelBooking->check_out;
      $room_type[]=$hotelBooking->room_type_id;
      $rooms_qty[]=$hotelBooking->rooms;
      $bucketupdate=$this->beConfBookingInvUpdate->bookingConfirm($invoice_id,$hotelBooking->hotel_id,$booking_details,$room_type,$rooms_qty);
    }      
    return 1;
    }
    public function impDate($data)
    {
        $hotel_id = $data['hotel_id'];
        $ota_details = CmOtaDetails::select('*')
        ->where('hotel_id', '=' ,$hotel_id)
        ->where('is_active', '=' ,1)
        ->get();
        $ota_id = $ota_details;
        $client_ip=$this->ipService->getIPAddress();//get client ip
        $user_id=0;
        return array('hotel_id'=>$hotel_id,'ota_id'=>$ota_id,'client_ip'=>$client_ip,'user_id'=>$user_id);
    }
    public function creatingBucketdata($ota_details,$hotel_id,$client_ip,$user_id)
    {
        if($ota_details)
        {
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
