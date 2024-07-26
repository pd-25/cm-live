<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Input;
use Validator;
use App\CmOtaDetails;
use App\CmOtaBooking;
use App\HotelBooking;
use App\CmOtaBucketTrackerTest;
use App\CmOtaAllAutoPush;
use App\CmOtaBookingPushBucket;
use App\Http\Controllers\OtaAutoPushUpdateTestControllers2; 
use DB;
/**
 * CmOtaBookingPushBucketController implements the CRUD actions for CmOtaBookingPushBucket model.
 */
class CmOtaBookingPushBucketTestControllers2 extends Controller
{
    protected $otaAutoPushUpdate;
    public function __construct(OtaAutoPushUpdateTestControllers2 $otaAutoPushUpdate)
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
        // $stockTrackerData = DB::select(DB::raw("select * FROM cmlive.cm_ota_bucket_tracker_test where created_at <= now()-interval 5 minute;"));
        // foreach($stockTrackerData as $status){
        //     $updateStatus = CmOtaBookingPushBucket::where('id',$status->bucket_id)->update(['is_update' => 5,'is_processed'=>5]);
        //     $dltstatus = CmOtaBucketTrackerTest::where('hotel_id',$status->hotel_id)->delete();
        // }
        $cmOtaBookingPushBucketDetail =$cmOtaBookingPushBucketModel->
                                         where('is_update','=',5)
                                        ->orderBy('push_at','ASC')
                                        ->limit(10)
                                        ->get();  
            
      if(sizeof($cmOtaBookingPushBucketDetail)>0)
      {
        try{
            foreach($cmOtaBookingPushBucketDetail as $process){
                $getStatus = CmOtaBucketTrackerTest::select('hotel_id','created_at')
                ->where('hotel_id',$process->hotel_id)
                ->first();
                if($getStatus){
                    continue;
                }
                else{
                    foreach($cmOtaBookingPushBucketDetail as $key => $process1){
                        if($process1->hotel_id == $process->hotel_id){
                            $process1->is_update = 2;
                            $process1->is_processed = 1;
                            $process1->save();

                            $bucket_data_processing_array[]=$cmOtaBookingPushBucketDetail[$key];//array assign

                            $tracker_data = array('hotel_id'=>$process1->hotel_id,'bucket_id'=>$process1->id,'is_processed'=>1);
                            $insert_data_to_tracker = CmOtaBucketTrackerTest::insert($tracker_data);
                        }
                    }
                }
            }
        }
        catch(Exception $e){
            echo 'Message: ' .$e->getMessage();
        }

        if(isset($bucket_data_processing_array) && sizeof($bucket_data_processing_array)>0){
            foreach ($bucket_data_processing_array as $cmOtaBookingPushBucketDetails)
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
            /*--------------------Fetching Booking Details--------------------------*/
            $cmOtaBookingdetails          = $cmOtaBookingModel->
                                        where('id' ,'=', $bucket_ota_booking_tabel_id)
                                        ->first();
            if($cmOtaBookingdetails){
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
                $modify_status                = $cmOtaBookingdetails->modify_status;
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
                                            "booking_tax_amount"              => $cmOtaBookingdetails->tax_amount,
                                            "booking_channel"             => $booking_channel,
                                            "booking_source"              => 'ota',
                                            "ids_re_id"                   => $cmOtaBookingdetails->ids_re_id,
                                            "modify_status"               => $modify_status
                                            ];
            }
            else{
                $get_invoice_data = HotelBooking::join('invoice_table','hotel_booking.invoice_id','=','invoice_table.invoice_id')
                ->join('kernel.user_table','invoice_table.user_id','=','user_table.user_id')
                ->select('hotel_booking.room_type_id','hotel_booking.booking_date','hotel_booking.check_in','hotel_booking.rooms','hotel_booking.check_out','invoice_table.total_amount','user_table.first_name','user_table.last_name','user_table.email_id','user_table.mobile','invoice_table.booking_source')
                ->where('hotel_booking.invoice_id',$bucket_ota_booking_tabel_id)->where('hotel_booking.hotel_id',$bucket_hotel_id)->first();
                if(!$get_invoice_data){
                    continue;
                }
                $customer_details = $get_invoice_data->first_name.','.$get_invoice_data->last_name.','.$get_invoice_data->email_id.','.$get_invoice_data->mobile;
                $booking_data                 = [
                "booking_ota_id"              => $bucket_ota_id,
                "booking_unique_id"           => $bucket_ota_booking_tabel_id,
                "booking_booking_status"      => $bucket_booking_status,
                "booking_hotel_id"            => $bucket_hotel_id,
                "booking_room_type"           => $get_invoice_data->room_type_id,
                "booking_rooms_qty"           => $get_invoice_data->rooms,
                "booking_checkin_at"          => $get_invoice_data->check_in,
                "booking_checkout_at"         => $get_invoice_data->check_out,
                "booking_rate_code"           => '',
                "booking_amount"              => $get_invoice_data->total_amount,
                "booking_ip"                  => '1.1.1.1',
                "booking_date"                => $get_invoice_data->booking_date,
                "booking_customer_details"    => $customer_details,
                "booking_channel"             => 'Bookingjini',
                "booking_amount"              => $get_invoice_data->total_amount,
                "booking_source"              => $get_invoice_data->booking_source,
                "modify_status"               => 0
                ];
            }
        
       /*----------- Call Indivisula OtaUpdate Function-------------*/
            if($bucket_ota_id !=0){
            $cmOtaDetails           = $cmOtaDetailsModel
                                        ->where('hotel_id', '=', $bucket_hotel_id)
                                        ->where('ota_id' ,'=' ,$bucket_ota_id)
                                        ->where('is_active', '=', 1)
                                        ->first();
            if($cmOtaDetails){
                $logfile = fopen($logpath, "a+");
                fwrite($logfile,"OTA Processing starts at: ".date("Y-m-d H:i:s")."\n");
                fclose($logfile);
                switch ($cmOtaDetails->ota_name) {
                    case "Agoda":
                                $return_status   =  $this->otaAutoPushUpdate->agodaUpdate($bucket_data,$booking_data);
                                if($return_status){
                                    $updete_data = array('is_update' =>1,'is_processed'=>2);
                                    DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
                                    ->update($updete_data);//Success
                                    $delete_data_from_tracker = CmOtaBucketTrackerTest::where('bucket_id',$bucket_id)->delete();//deletion from bucket tracker
                                   
                                    }else{
                                        $updete_data = array('is_update' =>3,'is_processed'=>2);
                                        DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
                                        ->update($updete_data);//Failed
                                        $delete_data_from_tracker = CmOtaBucketTrackerTest::where('bucket_id',$bucket_id)->delete();//deletion from bucket tracker
                                       
                                    }
                                break;
                    case "Goibibo":
                                $return_status   = $this->otaAutoPushUpdate->goibiboUpdate($bucket_data,$booking_data);
                                if($return_status){
                                        $updete_data = array('is_update' =>1,'is_processed'=>2);
                                        DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
                                        ->update($updete_data);//Success
                                        $delete_data_from_tracker = CmOtaBucketTrackerTest::where('bucket_id',$bucket_id)->delete();//deletion from bucket tracker
                                       
                                    }else{
                                        $updete_data = array('is_update' =>3,'is_processed'=>2);
                                        DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
                                        ->update($updete_data);//Failed
                                        $delete_data_from_tracker = CmOtaBucketTrackerTest::where('bucket_id',$bucket_id)->delete();//deletion from bucket tracker
                                       
                                    }
                                break;
                    case "Expedia":
                                $return_status   = $this->otaAutoPushUpdate->expediaUpdate($bucket_data,$booking_data);
                                if($return_status){
                                    $updete_data = array('is_update' =>1,'is_processed'=>2);
                                        DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
                                        ->update($updete_data);//Success
                                        $delete_data_from_tracker = CmOtaBucketTrackerTest::where('bucket_id',$bucket_id)->delete();//deletion from bucket tracker
                                       
                                    }else{
                                        $updete_data = array('is_update' =>3,'is_processed'=>2);
                                        DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
                                        ->update($updete_data);//Failed
                                        $delete_data_from_tracker = CmOtaBucketTrackerTest::where('bucket_id',$bucket_id)->delete();//deletion from bucket tracker
                                       
                                }
                                break;
                    case "Booking.com":
                                $return_status   = $this->otaAutoPushUpdate->bookingdotcomUpdate($bucket_data,$booking_data);
                                if($return_status){
                                    $updete_data = array('is_update' =>1,'is_processed'=>2);
                                        DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
                                        ->update($updete_data);//Success
                                        $delete_data_from_tracker = CmOtaBucketTrackerTest::where('bucket_id',$bucket_id)->delete();//deletion from bucket tracker
                                       
                                    }else{
                                        $updete_data = array('is_update' =>3,'is_processed'=>2);
                                        DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
                                        ->update($updete_data);//Failed
                                        $delete_data_from_tracker = CmOtaBucketTrackerTest::where('bucket_id',$bucket_id)->delete();//deletion from bucket tracker
                                       
                                }
                                break;
                    case "Via.com":
                                $return_status =  $this->otaAutoPushUpdate->viadotcomUpdate($bucket_data,$booking_data); 
                                if($return_status){
                                    $updete_data = array('is_update' =>1,'is_processed'=>2);
                                        DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
                                        ->update($updete_data);//Success
                                        $delete_data_from_tracker = CmOtaBucketTrackerTest::where('bucket_id',$bucket_id)->delete();//deletion from bucket tracker
                                       
                                    }else{
                                        $updete_data = array('is_update' =>3,'is_processed'=>2);
                                        DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
                                        ->update($updete_data);//Failed
                                        $delete_data_from_tracker = CmOtaBucketTrackerTest::where('bucket_id',$bucket_id)->delete();//deletion from bucket tracker
                                       
                                }
                                break;
                    case "Travelguru":
                                $return_status =  $this->otaAutoPushUpdate->travelguruUpdate($bucket_data,$booking_data);
                                if($return_status){
                                    $updete_data = array('is_update' =>1,'is_processed'=>2);
                                        DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
                                        ->update($updete_data);//Success
                                        $delete_data_from_tracker = CmOtaBucketTrackerTest::where('bucket_id',$bucket_id)->delete();//deletion from bucket tracker
                                       
                                    }else{
                                        $updete_data = array('is_update' =>3,'is_processed'=>2);
                                        DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
                                        ->update($updete_data);//Failed
                                        $delete_data_from_tracker = CmOtaBucketTrackerTest::where('bucket_id',$bucket_id)->delete();//deletion from bucket tracker
                                       
                                }
                                break;
                    case "EaseMyTrip":
                                $return_status =  $this->otaAutoPushUpdate->easemytripUpdate($bucket_data,$booking_data);
                                if($return_status){
                                    $updete_data = array('is_update' =>1,'is_processed'=>2);
                                    DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
                                    ->update($updete_data);//Success
                                    $delete_data_from_tracker = CmOtaBucketTrackerTest::where('bucket_id',$bucket_id)->delete();//deletion from bucket tracker
                                   
                                    }else{
                                        $updete_data = array('is_update' =>3,'is_processed'=>2);
                                    DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
                                    ->update($updete_data);//Failed
                                    $delete_data_from_tracker = CmOtaBucketTrackerTest::where('bucket_id',$bucket_id)->delete();//deletion from bucket tracker
                                   
                                }
                                break;
                    case "Paytm":
                                $return_status =  $this->otaAutoPushUpdate->paytmUpdate($bucket_data,$booking_data);
                                if($return_status){
                                    $updete_data = array('is_update' =>1,'is_processed'=>2);
                                        DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
                                        ->update($updete_data);//Success
                                        $delete_data_from_tracker = CmOtaBucketTrackerTest::where('bucket_id',$bucket_id)->delete();//deletion from bucket tracker
                                       
                                    }else{
                                        $updete_data = array('is_update' =>3,'is_processed'=>2);
                                        DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
                                        ->update($updete_data);//Failed
                                        $delete_data_from_tracker = CmOtaBucketTrackerTest::where('bucket_id',$bucket_id)->delete();//deletion from bucket tracker
                                       
                                }
                                break;
                    case "Goomo":
                            $return_status =  $this->otaAutoPushUpdate->goomoUpdate($bucket_data,$booking_data);
                            if($return_status){
                                $updete_data = array('is_update' =>1,'is_processed'=>2);
                                    DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
                                    ->update($updete_data);//Success
                                    $delete_data_from_tracker = CmOtaBucketTrackerTest::where('bucket_id',$bucket_id)->delete();//deletion from bucket tracker
                                   
                                }else{
                                    $updete_data = array('is_update' =>3,'is_processed'=>2);
                                    DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
                                    ->update($updete_data);//Failed
                                    $delete_data_from_tracker = CmOtaBucketTrackerTest::where('bucket_id',$bucket_id)->delete();//deletion from bucket tracker
                                   
                                }
                            break;
                    case "HappyEasyGo":
                            $return_status =  $this->otaAutoPushUpdate->happyEasyGoUpdate($bucket_data,$booking_data);
            
                            if($return_status){
                                $updete_data = array('is_update' =>1,'is_processed'=>2);
                                    DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
                                    ->update($updete_data);//Success
                                    $delete_data_from_tracker = CmOtaBucketTrackerTest::where('bucket_id',$bucket_id)->delete();//deletion from bucket tracker
                                   
                                }else{
                                    $updete_data = array('is_update' =>3,'is_processed'=>2);
                                    DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
                                    ->update($updete_data);//Failed
                                    $delete_data_from_tracker = CmOtaBucketTrackerTest::where('bucket_id',$bucket_id)->delete();//deletion from bucket tracker
                                   
                                }
                            break;
                    case "IRCTC":
                            $return_status =  $this->otaAutoPushUpdate->irctcUpdate($bucket_data,$booking_data);
            
                            if($return_status){
                                $updete_data = array('is_update' =>1,'is_processed'=>2);
                                    DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
                                    ->update($updete_data);//Success
                                    $delete_data_from_tracker = CmOtaBucketTracker::where('bucket_id',$bucket_id)->delete();//deletion from bucket tracker
                                    
                                }else{
                                    $updete_data = array('is_update' =>3,'is_processed'=>2);
                                    DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
                                    ->update($updete_data);//Failed
                                    $delete_data_from_tracker = CmOtaBucketTracker::where('bucket_id',$bucket_id)->delete();//deletion from bucket tracker
                                    
                                }
                            break;
                    default:
                    echo "No ota found";
                }
                $logfile = fopen($logpath, "a+");
                fwrite($logfile,"Ota processed:".$cmOtaDetails->ota_name."\n");
                fwrite($logfile,"Processing ends at: ".date("Y-m-d H:i:s")."\n");
                fclose($logfile);
            }
        }else{
            $logfile = fopen($logpath, "a+");
            fwrite($logfile,"Be Processing starts at: ".date("Y-m-d H:i:s")."\n");
            fclose($logfile);
            //echo "BookingJini update";
            $return_status   = $this->otaAutoPushUpdate->bookingjiniUpdate($bucket_data,$booking_data);
                
                if($return_status){
                    $updete_data = array('is_update' =>1,'is_processed'=>2);
                    DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
                    ->update($updete_data);//Success
                    $delete_data_from_tracker = CmOtaBucketTrackerTest::where('bucket_id',$bucket_id)->delete();//deletion from bucket tracker
                   
                }else{
                    $updete_data = array('is_update' =>3,'is_processed'=>2);
                    DB::table('cm_ota_booking_push_bucket')->where('id', $bucket_id )
                    ->update($updete_data);//Failed
                    $delete_data_from_tracker = CmOtaBucketTrackerTest::where('bucket_id',$bucket_id)->delete();//deletion from bucket tracker
                   
                }
                $logfile = fopen($logpath, "a+");
                fwrite($logfile,"be processed:BE \n");
                fwrite($logfile,"be Processing ends at: ".date("Y-m-d H:i:s")."\n");
                fclose($logfile);
        } 
        } // bucket result not null;
    }    
    } 
    $logfile = fopen($logpath, "a+");
    fwrite($logfile,"Inventory processed: 7 \n");
    fwrite($logfile,"Processing ends at: ".date("Y-m-d H:i:s")."\n");
    fclose($logfile);
    }// bucket enginee closed here
    public function checkBucketRunningStatus(){
        $today = date('Y-m-d H:i:s');
        $getBucketData = CmOtaBookingPushBucket::select('*')
                        ->where('is_update',0)
                        ->orderBy('push_at','ASC')
                        ->first();
        $start = strtotime($getBucketData->push_at);
        $end = strtotime($today);
        $diff = $end - $start;
        $push_at = $getBucketData->push_at;
        $minutes=intval((floor($diff/60)));
        if($minutes >= 3){
            $smsURL = "https://apps.sandeshlive.com/API/WebSMS/Http/v1.0a/index.php?userid=1135&password=F4lKwI80ROA51fyq&sender=BKJINI&to=8328869476,7008633177,9778624577&message=The booking processing has been stopped, last booking processed at: ".$push_at.". Regards,BKJINI&reqid=1&format={json|text}&route_id=3";
            $ch = curl_init(); // initialize CURL
            curl_setopt($ch, CURLOPT_POST, false); // Set CURL Post Data
            curl_setopt($ch, CURLOPT_URL, $smsURL);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $output = curl_exec($ch);
            curl_close($ch);

            // $runBucket = $this->runBucketAfterStop();
        }
    }
    // public function runBucketAfterStop(){
    //     $today = date('Y-m-d');
    //     $getBuckettracker = CmOtaBucketTracker::select('*')
    //                         ->get();
    //     foreach($getBuckettracker as $bucket_tracker){
    //         $hotel_id = $bucket_tracker->hotel_id;
    //         $getStopedBucketDate = CmOtaBookingPushBucket::select('*')
    //                             ->where('is_update',2)
    //                             ->where('hotel_id',$hotel_id)
    //                             ->whereDate('push_at',$today)
    //                             ->orderBy('push_at','ASC')
    //                             ->get();
    //         foreach($getStopedBucketDate as $bucket_data){

    //         }
    //     }
    // }
}
