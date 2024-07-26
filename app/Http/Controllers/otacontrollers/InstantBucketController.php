<?php
namespace App\Http\Controllers\otacontrollers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Input;
use Validator;
use App\CmOtaDetails;
use App\CmOtaBookingPushBucket;
use DB;
use App\Http\Controllers\Controller;
use App\Http\Controllers\InventoryUpdateAfterBookingController; 

/**
 * This controller used for pushing into cm ota bucket
 * @auther Ranjit
 * @date-23/01/2019
 */
class InstantBucketController extends Controller
{
    protected $InventoryUpdate;
    public function __construct(InventoryUpdateAfterBookingController $InventoryUpdate)
    {
       $this->InventoryUpdate = $InventoryUpdate;
    }
    public function bucketEngineUpdate($booking_status,$push_by,$ota_hotel_details,$ota_booking_tabel_id)
    {
        // $get_data = CmOtaBookingPushBucket::select('*')
        // ->where('ota_booking_tabel_id',$ota_booking_tabel_id)
        // ->where('booking_status',$booking_status)
        // ->first();
        // if(isset($get_data) && $get_data->ota_booking_tabel_id != ''){
        //     return 1;
        // }
        $ota_details_model          = new CmOtaDetails();
        $current_ota_details        = $ota_details_model
                                      ->where('hotel_id' ,'=', $ota_hotel_details->hotel_id)
                                      ->where('ota_id', '=', $ota_hotel_details->ota_id)
                                      ->where('is_active' ,'=' ,1)
                                      ->where('is_status' ,'=' ,1)
                                      ->first();
       
        if($current_ota_details)
        {
           
            $for_bucket_hotel_details   = $ota_details_model
                                        ->where('hotel_id' ,'=', $ota_hotel_details->hotel_id)
                                        ->where('is_active' ,'=' ,1)
                                        ->where('is_status' ,'=' ,1)
                                        ->get();
            
            foreach ($for_bucket_hotel_details as $key => $value)
            {

                //if($value->ota_id != $ota_hotel_details->ota_id){
                    /*--------push request in cm_ota_booking_push_bucket Start--------------*/
                    $cmOtaBookingPushBucketModel                       = new CmOtaBookingPushBucket();
                    $cmOtaBookingPushBucketModel->hotel_id             = $ota_hotel_details->hotel_id;
                    $cmOtaBookingPushBucketModel->ota_booking_tabel_id = $ota_booking_tabel_id;
                    $cmOtaBookingPushBucketModel->ota_id               = $value->ota_id;
                    $cmOtaBookingPushBucketModel->ota_hotel_code       = $value->ota_hotel_code;
                    $cmOtaBookingPushBucketModel->ota_name             = $value->ota_name;
                    $cmOtaBookingPushBucketModel->is_update            = 0;
                    $cmOtaBookingPushBucketModel->is_processed         = 0;
                    $cmOtaBookingPushBucketModel->booking_status       = $booking_status;
                    $cmOtaBookingPushBucketModel->push_by              = $push_by;
                    $cmOtaBookingPushBucketModel->save();
                    $resp =$this->InventoryUpdate->actionBookingbucketengine($cmOtaBookingPushBucketModel);
                    /*--------push request in cm_ota_booking_push_bucket End-----------------*/
                //}
            }
            /*-------------------Split for Bookingjini-----------------*/
            $cmOtaBookingPushBucketModel                       = new CmOtaBookingPushBucket();
            $cmOtaBookingPushBucketModel->hotel_id             = $ota_hotel_details->hotel_id;
            $cmOtaBookingPushBucketModel->ota_booking_tabel_id = $ota_booking_tabel_id;
            $cmOtaBookingPushBucketModel->ota_id               = 0;
            $cmOtaBookingPushBucketModel->ota_hotel_code       = 0;
            $cmOtaBookingPushBucketModel->ota_name             = "Bookingjini";
            $cmOtaBookingPushBucketModel->is_update            = 0;
            $cmOtaBookingPushBucketModel->is_processed         = 0;
            $cmOtaBookingPushBucketModel->booking_status       = $booking_status;
            $cmOtaBookingPushBucketModel->push_by              = $push_by;


            $cmOtaBookingPushBucketModel->save();
            $resp =$this->InventoryUpdate->actionBookingbucketengine($cmOtaBookingPushBucketModel);
        }
    }
}
