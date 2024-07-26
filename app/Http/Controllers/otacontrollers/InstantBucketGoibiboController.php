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

/**
 * This controller used for pushing into cm ota bucket
 * @auther Ranjit
 * @date-23/01/2019
 */
class InstantBucketGoibiboController extends Controller
{
    public function bucketEngineUpdate($booking_status,$push_by,$ota_hotel_details,$ota_booking_tabel_id)
    {

        $ota_details_model          = new CmOtaDetails(); 
        $current_ota_details        = $ota_details_model
                                      ->where('hotel_id' ,'=', $ota_hotel_details->hotel_id)
                                      ->where('ota_id', '=', $ota_hotel_details->ota_id)
                                      ->where('is_active' ,'=' ,1)
                                      ->first();
                                      
        if($current_ota_details)
        { 
            /*-------------------Split for Bookingjini-----------------*/
            $cmOtaBookingPushBucketModel                       = new CmOtaBookingPushBucket();
            $cmOtaBookingPushBucketModel->hotel_id             = $ota_hotel_details->hotel_id;
            $cmOtaBookingPushBucketModel->ota_booking_tabel_id = $ota_booking_tabel_id;
            $cmOtaBookingPushBucketModel->ota_id               = 0;
            $cmOtaBookingPushBucketModel->ota_hotel_code       = 0;
            $cmOtaBookingPushBucketModel->ota_name             = "Bookingjini";
            $cmOtaBookingPushBucketModel->is_update            = 5;
            $cmOtaBookingPushBucketModel->booking_status       = $booking_status;
            $cmOtaBookingPushBucketModel->push_by              = $push_by;

                        
            $cmOtaBookingPushBucketModel->save();          
        
            $for_bucket_hotel_details   = $ota_details_model
                                        ->where('hotel_id' ,'=', $ota_hotel_details->hotel_id)
                                        ->where('is_active' ,'=' ,1)
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
                    $cmOtaBookingPushBucketModel->is_update            = 5;
                    $cmOtaBookingPushBucketModel->booking_status       = $booking_status;
                    $cmOtaBookingPushBucketModel->push_by              = $push_by;              
                    $cmOtaBookingPushBucketModel->save();
                    /*--------push request in cm_ota_booking_push_bucket End-----------------*/
                //}
            }
        }            
    }
}
