<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Input;
use Validator;
use App\CmOtaDetails;
use App\CmOtaBooking;
use App\CmOtaAllAutoPush;
use App\CmOtaBookingPushBucket;
use App\Http\Controllers\OtaAutoPushUpdateController; 
use DB;
/**
 * CmOtaBookingPushBucketController implements the CRUD actions for CmOtaBookingPushBucket model.
 */
class CmOtaBookingPushBucketController extends Controller
{
    protected $otaAutoPushUpdate;
    public function __construct(OtaAutoPushUpdateController $otaAutoPushUpdate)
    {
       $this->otaAutoPushUpdate = $otaAutoPushUpdate;
    }
    public function actionBookingbucketengine()
    {
        $logpath = storage_path("logs/bucket.log".date("Y-m-d"));
        $logfile = fopen($logpath, "a+");
        fwrite($logfile,"Processing starts at: ".date("Y-m-d H:i:s")."\n");
        fclose($logfile);

        $cmOtaDetailsModel             = new CmOtaDetails();
        $cmOtaBookingModel             = new CmOtaBooking();
        $cmOtaBookingPushBucketModel   = new CmOtaBookingPushBucket();
        /*--------------------Fetching Booking Push Bucket Details--------------------------*/
        $cmOtaBookingPushBucketDetail =$cmOtaBookingPushBucketModel->
                                         where('is_update','=',0)
                                        ->where('hotel_id','!=',2142)
                                        ->where('hotel_id','!=',2156)
                                        ->where('hotel_id','!=',2155)
                                        ->where('hotel_id','!=',2154)
                                        ->where('hotel_id','!=',2150)
                                        ->where('hotel_id','!=',2149)
                                        ->where('hotel_id','!=',1931)
                                        ->limit(5)
                                        ->orderBy('push_at','=', SORT_ASC)
                                        ->get();  
            
      if(sizeof($cmOtaBookingPushBucketDetail)>0)
      {
        foreach ($cmOtaBookingPushBucketDetail as $cmOtaBookingPushBucketDetails)
        {
        $bucket_id                     = $cmOtaBookingPushBucketDetails->id;
        $bucket_hotel_id               = $cmOtaBookingPushBucketDetails->hotel_id;
        $bucket_ota_id                 = $cmOtaBookingPushBucketDetails->ota_id;
        $bucket_ota_name               = $cmOtaBookingPushBucketDetails->ota_name;
        $bucket_ota_booking_tabel_id   = $cmOtaBookingPushBucketDetails->ota_booking_tabel_id;
        $bucket_ota_hotel_code         = $cmOtaBookingPushBucketDetails->ota_hotel_code;
        $bucket_booking_status         = $cmOtaBookingPushBucketDetails->booking_status;
        
        $bucket_data                   = [
                                        "bucket_id"                   => $bucket_id,
                                        "bucket_hotel_id"             => $bucket_hotel_id,
                                        "bucket_ota_id"               => $bucket_ota_id,
                                        "bucket_ota_name"             => $bucket_ota_name,
                                        "bucket_ota_booking_tabel_id" => $bucket_ota_booking_tabel_id,
                                        "bucket_ota_hotel_code"       => $bucket_ota_hotel_code,
                                        "bucket_booking_status"       => $bucket_booking_status,
                                        "bucket_booking_push_by"      => $cmOtaBookingPushBucketDetails->push_by  
                                        ];
        try{
            $cmOtaBookingPushBucketDetails->is_update = 2;
            $cmOtaBookingPushBucketDetails->is_processed = 1;
            $cmOtaBookingPushBucketDetails->save();
        }catch(Exception $e){
            echo 'Message: ' .$e->getMessage();
        }
        /*--------------------Fetching Booking Details--------------------------*/
        $cmOtaBookingdetails          = $cmOtaBookingModel->
                                    where('id' ,'=', $bucket_ota_booking_tabel_id)
                                    ->first();
        $booking_ota_id               = $cmOtaBookingdetails->ota_id;
        $booking_unique_id            = $cmOtaBookingdetails->unique_id;     
        $booking_booking_status       = $cmOtaBookingdetails->booking_status;
        $booking_hotel_id             = $cmOtaBookingdetails->hotel_id;
        $booking_room_type            = $cmOtaBookingdetails->room_type;
        $booking_rooms_qty            = $cmOtaBookingdetails->rooms_qty;
        $booking_checkin_at           = $cmOtaBookingdetails->checkin_at;
        $booking_checkout_at          = $cmOtaBookingdetails->checkout_at;
        $booking_rate_code            = $cmOtaBookingdetails->rate_code;
        $booking_amount               = $cmOtaBookingdetails->amount;
        $booking_ip                   = $cmOtaBookingdetails->ip;
        $booking_channel              = $cmOtaBookingdetails->channel_name;
        $booking_data                 = [
                                        "booking_ota_id"              => $booking_ota_id,
                                        "booking_unique_id"           => $booking_unique_id,
                                        "booking_booking_status"      => $booking_booking_status,
                                        "booking_hotel_id"            => $booking_hotel_id,
                                        "booking_room_type"           => $booking_room_type,
                                        "booking_rooms_qty"           => $booking_rooms_qty,
                                        "booking_checkin_at"          => $booking_checkin_at,
                                        "booking_checkout_at"         => $booking_checkout_at,
                                        "booking_rate_code"           => $booking_rate_code,
                                        "booking_amount"              => $booking_amount,
                                        "booking_ip"                  => $booking_ip,
                                        "booking_customer_details"    => $cmOtaBookingdetails->customer_details,
                                        "booking_amount"              => $cmOtaBookingdetails->amount,
                                        "booking_channel"             => $booking_channel,
                                        ];
        
       /*----------- Call Indivisula OtaUpdate Function-------------*/
        if($bucket_ota_id !=0){
        $cmOtaDetails           = $cmOtaDetailsModel
                                    ->where('hotel_id', '=', $bucket_hotel_id)
                                    ->where('ota_id' ,'=' ,$bucket_ota_id)
                                    ->where('is_active', '=', 1)
                                    ->first();
        if($cmOtaDetails){
        if($cmOtaDetails->ota_name == "Cleartrip"){
        $return_status   = $this->otaAutoPushUpdate->cleartripUpdate($bucket_data,$booking_data);
           
        if($return_status){
            $updete_data = array('is_update' =>1,'is_processed'=>2);
                DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
                ->update($updete_data);//Success
               
            }else{
                DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
                ->update(['is_update' =>3]);//Failed
            }
        }
        if($cmOtaDetails->ota_name == "Agoda"){
        $return_status   =  $this->otaAutoPushUpdate->agodaUpdate($bucket_data,$booking_data);
        
        if($return_status){
            $updete_data = array('is_update' =>1,'is_processed'=>2);
            DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
            ->update($updete_data);//Success
            }else{
                DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
                ->update(['is_update' =>3]);//Failed
            }
        }
        if($cmOtaDetails->ota_name == "Goibibo"){
            
        $return_status   = $this->otaAutoPushUpdate->goibiboUpdate($bucket_data,$booking_data);
       
        if($return_status){
                $updete_data = array('is_update' =>1,'is_processed'=>2);
                DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
                ->update($updete_data);//Success
            }else{
                DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
                ->update(['is_update' =>3]);//Failed
            }
        }
       if($cmOtaDetails->ota_name == "Expedia"){
        $return_status   = $this->otaAutoPushUpdate->expediaUpdate($bucket_data,$booking_data);
        
        if($return_status){
            $updete_data = array('is_update' =>1,'is_processed'=>2);
                DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
                ->update($updete_data);//Success
            }else{
                DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
                ->update(['is_update' =>3]);//Failed
            }
        }
        if($cmOtaDetails->ota_name == "Booking.com"){
        $return_status   = $this->otaAutoPushUpdate->bookingdotcomUpdate($bucket_data,$booking_data);
       
        if($return_status){
            $updete_data = array('is_update' =>1,'is_processed'=>2);
                DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
                ->update($updete_data);//Success
            }else{
                DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
                ->update(['is_update' =>3]);//Failed
            }
        }
        if($cmOtaDetails->ota_name == "Via.com"){
        $return_status =  $this->otaAutoPushUpdate->viadotcomUpdate($bucket_data,$booking_data);
             
        if($return_status){
            $updete_data = array('is_update' =>1,'is_processed'=>2);
                DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
                ->update($updete_data);//Success
            }else{
                DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
                ->update(['is_update' =>3]);//Failed
        }
        }
        if($cmOtaDetails->ota_name == "Travelguru"){
       $return_status =  $this->otaAutoPushUpdate->travelguruUpdate($bucket_data,$booking_data);
            
       if($return_status){
        $updete_data = array('is_update' =>1,'is_processed'=>2);
            DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
            ->update($updete_data);//Success
           }else{
            DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
            ->update(['is_update' =>3]);//Failed
           }
       }
       if($cmOtaDetails->ota_name == "EaseMyTrip"){
        $return_status =  $this->otaAutoPushUpdate->easemytripUpdate($bucket_data,$booking_data);
             
        if($return_status){
            $updete_data = array('is_update' =>1,'is_processed'=>2);
             DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
             ->update($updete_data);//Success
            }else{
             DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
             ->update(['is_update' =>3]);//Failed
            }
        }
        if($cmOtaDetails->ota_name == "Paytm"){
        $return_status =  $this->otaAutoPushUpdate->paytmUpdate($bucket_data,$booking_data);
                
        if($return_status){
            $updete_data = array('is_update' =>1,'is_processed'=>2);
                DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
                ->update($updete_data);//Success
            }else{
                DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
                ->update(['is_update' =>3]);//Failed
            }
        }
        if($cmOtaDetails->ota_name == "Goomo"){
        $return_status =  $this->otaAutoPushUpdate->goomoUpdate($bucket_data,$booking_data);
                
        if($return_status){
            $updete_data = array('is_update' =>1,'is_processed'=>2);
                DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
                ->update($updete_data);//Success
            }else{
                DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
                ->update(['is_update' =>3]);//Failed
            }
        }
        if($cmOtaDetails->ota_name == "HappyEasyGo"){
            $return_status =  $this->otaAutoPushUpdate->happyEasyGoUpdate($bucket_data,$booking_data);
    
            if($return_status){
                $updete_data = array('is_update' =>1,'is_processed'=>2);
                    DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
                    ->update($updete_data);//Success
                }else{
                    DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
                    ->update(['is_update' =>3]);//Failed
                }
            }
     } // cmOtaDetails closed here.
     }else{
        //echo "BookingJini update";
           $return_status   = $this->otaAutoPushUpdate->bookingjiniUpdate($bucket_data,$booking_data);
            
            if($return_status){
                $updete_data = array('is_update' =>1,'is_processed'=>2);
                DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
                ->update($updete_data);//Success
            }else{
                DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
                ->update(['is_update' =>3]);//Failed
            }
     } 
    } // bucket result not null;    
    } 
    $logfile = fopen($logpath, "a+");
    fwrite($logfile,"Inventory processed: 10 \n");
    fwrite($logfile,"Processing ends at: ".date("Y-m-d H:i:s")."\n");
    fclose($logfile);
    }// bucket enginee closed here
}
