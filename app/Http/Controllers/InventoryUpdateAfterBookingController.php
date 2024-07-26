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
use App\HotelBooking;
use App\Http\Controllers\InventoryStoreInOtaInventoryController; 
use DB;
/**
 * CmOtaBookingPushBucketController implements the CRUD actions for CmOtaBookingPushBucket model.
 */
class InventoryUpdateAfterBookingController extends Controller
{
    protected $otaAutoPushUpdate;
    public function __construct(InventoryStoreInOtaInventoryController $otaAutoPushUpdate)
    {
       $this->otaAutoPushUpdate = $otaAutoPushUpdate;
    }
    public function test(){
        $cmOtaBookingPushBucketModel   = new CmOtaBookingPushBucket(); 
        $cmOtaBookingPushBucket =$cmOtaBookingPushBucketModel->
        where('is_update','=',5)
       ->orderBy('push_at','=', SORT_ASC)
       ->first();  
       $resp = $this->actionBookingbucketengine($cmOtaBookingPushBucket);
    }
    public function actionBookingbucketengine($cmOtaBookingPushBucket)
    {
        $logpath = storage_path("logs/test_bucket.log".date("Y-m-d"));
        $logfile = fopen($logpath, "a+");
        fwrite($logfile,"Processing starts at: ".date("Y-m-d H:i:s")."\n");
        fclose($logfile);

        $cmOtaDetailsModel             = new CmOtaDetails();
        $cmOtaBookingModel             = new CmOtaBooking();
   
        $bucket_id                     = $cmOtaBookingPushBucket->id;
        $bucket_hotel_id               = $cmOtaBookingPushBucket->hotel_id;
        $bucket_ota_id                 = $cmOtaBookingPushBucket->ota_id;
        $bucket_ota_name               = $cmOtaBookingPushBucket->ota_name;
        $bucket_ota_booking_tabel_id   = $cmOtaBookingPushBucket->ota_booking_tabel_id;
        $bucket_ota_hotel_code         = $cmOtaBookingPushBucket->ota_hotel_code;
        $bucket_booking_status         = $cmOtaBookingPushBucket->booking_status;
        
        $bucket_data                   = [
                                        "bucket_id"                   => $bucket_id,
                                        "bucket_hotel_id"             => $bucket_hotel_id,
                                        "bucket_ota_id"               => $bucket_ota_id,
                                        "bucket_ota_name"             => $bucket_ota_name,
                                        "bucket_ota_booking_tabel_id" => $bucket_ota_booking_tabel_id,
                                        "bucket_ota_hotel_code"       => $bucket_ota_hotel_code,
                                        "bucket_booking_status"       => $bucket_booking_status,
                                        "bucket_booking_push_by"      => $cmOtaBookingPushBucket->push_by  
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
                                            "booking_source"              => 'ota'
                                            ];
        }
        else{
            $get_invoice_data = HotelBooking::join('invoice_table','hotel_booking.invoice_id','=','invoice_table.invoice_id')
            ->join('kernel.user_table','invoice_table.user_id','=','user_table.user_id')
            ->select('hotel_booking.room_type_id','hotel_booking.booking_date','hotel_booking.check_in','hotel_booking.rooms','hotel_booking.check_out','invoice_table.total_amount','user_table.first_name','user_table.last_name','user_table.email_id','user_table.mobile','invoice_table.booking_source')
            ->where('hotel_booking.invoice_id',$bucket_ota_booking_tabel_id)->where('hotel_booking.hotel_id',$bucket_hotel_id)->first();
            
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
                "booking_amount"    => $get_invoice_data->total_amount,
                "booking_source"              => $get_invoice_data->booking_source,
                ];
        }  
       /*----------- Call Indivisula OtaUpdate Function-------------*/
        if($bucket_ota_id !=0){
        $cmOtaDetails           = $cmOtaDetailsModel
                                    ->where('hotel_id', '=', $bucket_hotel_id)
                                    ->where('ota_id' ,'=' ,$bucket_ota_id)
                                    ->where('is_active', '=', 1)
                                    ->where('is_status', '=', 1)
                                    ->first();
                                                       
        if($cmOtaDetails){
            switch ($cmOtaDetails->ota_name) {
                case "Agoda":
                            $return_status   =  $this->otaAutoPushUpdate->agodaUpdate($bucket_data,$booking_data);
                            break;
                case "Goibibo":
                            $return_status   = $this->otaAutoPushUpdate->goibiboUpdate($bucket_data,$booking_data);
                            break;
                case "Expedia":
                            $return_status   = $this->otaAutoPushUpdate->expediaUpdate($bucket_data,$booking_data);
                            break;
                case "Booking.com":
                            $return_status   = $this->otaAutoPushUpdate->bookingdotcomUpdate($bucket_data,$booking_data);
                            break;
                case "Via.com":
                            $return_status =  $this->otaAutoPushUpdate->viadotcomUpdate($bucket_data,$booking_data); 
                            break;
                case "Travelguru":
                            $return_status =  $this->otaAutoPushUpdate->travelguruUpdate($bucket_data,$booking_data);
                            break;
                case "EaseMyTrip":
                            $return_status =  $this->otaAutoPushUpdate->easemytripUpdate($bucket_data,$booking_data);
                            break;
                case "Paytm":
                            $return_status =  $this->otaAutoPushUpdate->paytmUpdate($bucket_data,$booking_data);
                            break;
                case "Goomo":
                        $return_status =  $this->otaAutoPushUpdate->goomoUpdate($bucket_data,$booking_data);
                        break;
                case "HappyEasyGo":
                        $return_status =  $this->otaAutoPushUpdate->happyEasyGoUpdate($bucket_data,$booking_data);
                        break;
                case "IRCTC":
                        $return_status =  $this->otaAutoPushUpdate->irctcUpdate($bucket_data,$booking_data);
                    break;
                default:
                  echo "No ota found";
              }
        } // cmOtaDetails closed here.
     }else{
        //echo "BookingJini update";
           $return_status   = $this->otaAutoPushUpdate->bookingjiniUpdate($bucket_data,$booking_data);
     } 
    $logfile = fopen($logpath, "a+");
    fwrite($logfile,$return_status ."Inventory processed: 1 \n");
    fwrite($logfile,"Processing ends at: ".date("Y-m-d H:i:s")."\n");
    fclose($logfile);
    }// bucket enginee closed here
}
