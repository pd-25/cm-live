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
        // dd($invoice_details['ids_re_id']);
        $hotel_inventory= new Inventory();
        $result=array();
        $result['ota_status']=array();
        $ota_id=$inv_data['ota_id'];
        if($ota_id == -1){
            // if($hotel_inventory->fill($inv_data)->save()){
                if($this->googleHotelStatus($inv_data['hotel_id'])){
                    $dataArr=array("inventory"=>$inv_data);
                    $dataArr=json_encode($dataArr);
                    $url=env('API-URL')."/manage_inventory/inventory_update";
                    $headers = array ('Content-Type: application/json');
                    $this->getdata_curlreq->cUrlCall($url,$headers,$dataArr);
                }
                $res=array('status'=>1,'message'=>'inventory update sucessfully in Booking engine','be'=>'be');
                return $res;
            // }
            // else{
            //     $res=array('status'=>1,'message'=>'inventory update unsucessfully in Booking engine','be'=>'be');
            //     return $res;
            // }
        }
        // if($hotel_inventory->fill($inv_data)->save()){
            if($this->googleHotelStatus($inv_data['hotel_id'])){
                $dataArr=array("inventory"=>$inv_data);
                $dataArr=json_encode($dataArr);
                $url=env('API-URL')."/manage_inventory/inventory_update";
                $headers = array ('Content-Type: application/json');
                $this->getdata_curlreq->cUrlCall($url,$headers,$dataArr);
            }
            $result['be_status']="Inventory update successfull";
            try{
                $otaDataPush = array('ids_re_id'=>$invoice_details['ids_re_id'],'invoice_id'=>$inv_data['invoice_id']);
                $otaDataPush = http_build_query($otaDataPush);
                $pushInvurl1 = 'https://cm.bookingjini.com/inv/push-inv-to-ota';
                $pushInvurl = 'https://api.bookingjini.com/api/inv/push-inv-to-ota';
                $pushInvch = curl_init();
                curl_setopt( $pushInvch, CURLOPT_URL, $pushInvurl);
                curl_setopt( $pushInvch, CURLOPT_POST, true );
                curl_setopt( $pushInvch, CURLOPT_RETURNTRANSFER, true );
                curl_setopt( $pushInvch, CURLOPT_POSTFIELDS, $otaDataPush);
                $inventoryPush = curl_exec($pushInvch);
                curl_close($pushInvch);
                
                // $url = 'https://api.bookingjini.com/api/crs_cancel_push_to_ids';
                // $url1 = 'https://cm.bookingjini.com/crs_cancel_push_to_ids';
                // $ch = curl_init();
                // curl_setopt( $ch, CURLOPT_URL, $url );
                // curl_setopt( $ch, CURLOPT_POST, true );
                // curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
                // curl_setopt( $ch, CURLOPT_POSTFIELDS, $invoice_id);
                // $status = curl_exec($ch);
                // curl_close($ch);
                $invoiceData=Invoice::where('invoice_id',$inv_data['invoice_id'])->first();
			    // $hotelBookingData=HotelBooking::where('invoice_id',$inv_data['invoice_id'])->get();
			    if($invoiceData->is_cm==1){
				    // foreach ($hotelBookingData as $key => $hotelBooking) {
				      $booking_details=[];
				      $booking_details['checkin_at'] =$inv_data['check_in'];
				      $booking_details['checkout_at'] =$inv_data['check_out'];
				      $room_type[]=$inv_data['room_type_id'];
				      $rooms_qty[]=$inv_data['no_of_rooms'];
				      $bucketupdate=$this->beConfBookingInvUpdate->bookingConfirm($inv_data['invoice_id'],$inv_data['hotel_id'],$booking_details,$room_type,$rooms_qty);
				    // }      
			    }
                else{
                    $this->updateInvBe($inv_data);               
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

        // }
        // else{
        //     return 0;
        // }
    }
    public function updateWinhmsInv($inv_data,$invoice_details){
        // dd($invoice_details['ids_re_id']);
        $hotel_inventory= new Inventory();
        $result=array();
        $result['ota_status']=array();
        $ota_id=$inv_data['ota_id'];
        if($ota_id == -1){
            // if($hotel_inventory->fill($inv_data)->save()){
                if($this->googleHotelStatus($inv_data['hotel_id'])){
                    $dataArr=array("inventory"=>$inv_data);
                    $dataArr=json_encode($dataArr);
                    $url=env('API-URL')."/manage_inventory/inventory_update";
                    $headers = array ('Content-Type: application/json');
                    $this->getdata_curlreq->cUrlCall($url,$headers,$dataArr);
                }
                $res=array('status'=>1,'message'=>'inventory update sucessfully in Booking engine','be'=>'be');
                return $res;
            // }
            // else{
            //     $res=array('status'=>1,'message'=>'inventory update unsucessfully in Booking engine','be'=>'be');
            //     return $res;
            // }
        }
        // if($hotel_inventory->fill($inv_data)->save()){
            if($this->googleHotelStatus($inv_data['hotel_id'])){
                $dataArr=array("inventory"=>$inv_data);
                $dataArr=json_encode($dataArr);
                $url=env('API-URL')."/manage_inventory/inventory_update";
                $headers = array ('Content-Type: application/json');
                $this->getdata_curlreq->cUrlCall($url,$headers,$dataArr);
            }
            $result['be_status']="Inventory update successfull";
            try{
                $otaDataPush = array('winhms_re_id'=>$invoice_details['winhms_re_id'],'invoice_id'=>$inv_data['invoice_id']);
                $otaDataPush = http_build_query($otaDataPush);
                $pushInvurl1 = 'https://cm.bookingjini.com/inv/push-inv-to-ota';
                $pushInvurl = 'https://api.bookingjini.com/api/inv/push-inv-to-ota';
                $pushInvch = curl_init();
                curl_setopt( $pushInvch, CURLOPT_URL, $pushInvurl);
                curl_setopt( $pushInvch, CURLOPT_POST, true );
                curl_setopt( $pushInvch, CURLOPT_RETURNTRANSFER, true );
                curl_setopt( $pushInvch, CURLOPT_POSTFIELDS, $otaDataPush);
                $inventoryPush = curl_exec($pushInvch);
                curl_close($pushInvch);
                
                // $url = 'https://api.bookingjini.com/api/crs_cancel_push_to_ids';
                // $url1 = 'https://cm.bookingjini.com/crs_cancel_push_to_ids';
                // $ch = curl_init();
                // curl_setopt( $ch, CURLOPT_URL, $url );
                // curl_setopt( $ch, CURLOPT_POST, true );
                // curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
                // curl_setopt( $ch, CURLOPT_POSTFIELDS, $invoice_id);
                // $status = curl_exec($ch);
                // curl_close($ch);
                $invoiceData=Invoice::where('invoice_id',$inv_data['invoice_id'])->first();
                // $hotelBookingData=HotelBooking::where('invoice_id',$inv_data['invoice_id'])->get();
                if($invoiceData->is_cm==1){
                    // foreach ($hotelBookingData as $key => $hotelBooking) {
                      $booking_details=[];
                      $booking_details['checkin_at'] =$inv_data['check_in'];
                      $booking_details['checkout_at'] =$inv_data['check_out'];
                      $room_type[]=$inv_data['room_type_id'];
                      $rooms_qty[]=$inv_data['no_of_rooms'];
                      $bucketupdate=$this->beConfBookingInvUpdate->bookingConfirm($inv_data['invoice_id'],$inv_data['hotel_id'],$booking_details,$room_type,$rooms_qty);
                    // }      
                }
                else{
                    $this->updateInvBe($inv_data);               
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

        // }
        // else{
        //     return 0;
        // }
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
