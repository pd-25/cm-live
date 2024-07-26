<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Validator;
use App\UserCredential;
use App\Inventory;//class name from model
use App\MasterRoomType;//class name from model
use App\MasterHotelRatePlan;//class name from model
use App\RatePlanLog;//class name from model
use App\LogTable;
use App\ErrorLog;
use App\Invoice;
use DB;
use App\Http\Controllers\invrateupdatecontrollers\GetDataForRateController;
use App\Http\Controllers\BeConfirmBookingInvUpdateRedirectingController;

class UpdateInventoryService extends Controller
{
    protected $getdata_curlreq;
    protected $beConfBookingInvUpdate;
    public function __construct(GetDataForRateController $getdata_curlreq,BeConfirmBookingInvUpdateRedirectingController $beConfBookingInvUpdate){
       $this->getdata_curlreq                       = $getdata_curlreq;
       $this->beConfBookingInvUpdate                = $beConfBookingInvUpdate;
    }
     public function updateInv($inv_data,$invoice_details){
        $hotel_inventory= new Inventory();
        $result=array();
        $result['ota_status']=array();
        $ota_id=$inv_data['ota_id'];
            try{
                $invoice_id = $inv_data["invoice_id"];
                $invoiceData=Invoice::where('invoice_id',$invoice_id)->first();
                if($invoiceData->is_cm!=1){
                    $resp = $this->updateInvBe($inv_data); 
                }
                else{
                    $get_ota_room = CmOtaRoomTypeSynchronizeRead::
                    join("cm_ota_details",function($join){
                        $join->on("cm_ota_details.ota_id","=","cm_ota_room_type_synchronize.ota_type_id")
                            ->on("cm_ota_details.hotel_id","=","cm_ota_room_type_synchronize.hotel_id");
                    })->where('cm_ota_details.is_active',1)->where('cm_ota_room_type_synchronize.hotel_id',$inv_data['hotel_id'])
                    ->where('cm_ota_room_type_synchronize.room_type_id',$inv_data["room_type_id"])->first();
                    if(!$get_ota_room){
                        $resp = $this->updateInvBe($inv_data); 
                    }
                }
                if($invoice_details['ids_re_id'] > 0){
                     $otaDataPush = array('ids_re_id'=>$invoice_details['ids_re_id'],'invoice_id'=>$inv_data['invoice_id']);
                     $url = 'https://cm.bookingjini.com/inv/push-inv-to-ota';
                     $ch = curl_init();
                     curl_setopt( $ch, CURLOPT_URL, $url );
                     curl_setopt( $ch, CURLOPT_POST, true );
                     curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
                     curl_setopt( $ch, CURLOPT_POSTFIELDS, $otaDataPush);
                     $inventoryPush = curl_exec($ch);
                     curl_close($ch);
                } 
                if($invoice_details['winhms_re_id'] > 0){
                     $otaDataPush = array('winhms_re_id'=>$invoice_details['winhms_re_id'],'invoice_id'=>$inv_data['invoice_id']);
                     $url = 'https://cm.bookingjini.com/inv/push-inv-to-ota';
                     $ch = curl_init();
                     curl_setopt( $ch, CURLOPT_URL, $url );
                     curl_setopt( $ch, CURLOPT_POST, true );
                     curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
                     curl_setopt( $ch, CURLOPT_POSTFIELDS, $otaDataPush);
                     $inventoryPush = curl_exec($ch);
                     curl_close($ch);
                }   
            }
            catch(Exception $e){
              $error_log = new ErrorLog();
               $storeError = array(
                  'hotel_id'      => $inv_data['hotel_id'],
                  'function_name' => 'UpdateInventoryService.updateInv',
                  'error_string'  => $e
               );
               $insertError = $error_log->fill($storeError)->save();
            }
    }
    public function updateInvBe($inv_data){
        $hotel_inventory= new Inventory();
        $result=array();
        $result['ota_status']=array();
        if($hotel_inventory->fill($inv_data)->save()){
            if($this->googleHotelStatus($inv_data['hotel_id'])){
                $dataArr=array("inventory"=>$inv_data);
                $dataArr=json_encode($dataArr);
                $url=env('API-URL')."/manage_inventory/inventory_update";
                $headers = array ('Content-Type: application/json');
                $this->getdata_curlreq->cUrlCall($url,$headers,$dataArr);
            }
            return  "Inventory update successfull";
        }
        else{
            return false;
        }
    }
    public function googleHotelStatus($hotel_id){
        $getHotelDetails = DB::table('meta_search_engine_settings')->select('hotels')->where('name','google-hotel')->first();
        $hotel_ids = explode(',',$getHotelDetails->hotels);
        if(in_array($hotel_id,$hotel_ids)){
            return true;
        }
        else{
            return false;
        }
    }
}
