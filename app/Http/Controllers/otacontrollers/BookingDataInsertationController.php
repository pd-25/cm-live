<?php
namespace App\Http\Controllers\otacontrollers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Input;
use Validator;
use App\CmOtaBooking;
use App\CmOtaBookingRead;
use DB;
use App\Http\Controllers\CmOtaBookingInvStatusService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
/**
 * This controller is used for inserting booking from ota
 * @auther Ranjit
 * @date-23/01/2019
 */
class BookingDataInsertationController extends Controller
{
    protected $cmOtaBookingInvStatusService;
    public function __construct(CmOtaBookingInvStatusService $cmOtaBookingInvStatusService)
    {
      $this->cmOtaBookingInvStatusService=$cmOtaBookingInvStatusService;
    }

    public function cmOtaBooking($bookingDetails,$ota_hotel_details)
    {
      Log::info('API called.', ['File' => __FILE__, 'Method'=>__METHOD__, 'line' => __LINE__,'BookingDetails'=>$bookingDetails,'otaHotelDetails'=>$ota_hotel_details]);
            $otaBookingModel            = new CmOtaBooking();
            $otaBookingUpdateModel  = $otaBookingModel
            ->where('unique_id' ,'=',trim($bookingDetails['UniqueID']))
            ->first();
            // if($bookingDetails['UniqueID'] == 3017660938){
            //   dd($bookingDetails['booking_status']);
            // }
            if(isset($otaBookingUpdateModel) && $otaBookingUpdateModel->unique_id != '')
            {
              if($bookingDetails['booking_status']=='Modify'){
                  return $this->updateBooking($bookingDetails,$ota_hotel_details,$otaBookingUpdateModel);
              }elseif($bookingDetails['booking_status']=='Cancel'){
                      return $this->cancelBooking($bookingDetails,$ota_hotel_details,$otaBookingUpdateModel);
              }
            }
            else
            {
              if($bookingDetails['booking_status']=='Commit'){
                  return $this->newBooking($bookingDetails,$ota_hotel_details,$otaBookingModel);
                }
            }
            //Checking our booking status and call respective action
            // if($bookingDetails['booking_status']=='Commit' && isset($otaBookingUpdateModel) && ){
            //     return $this->newBooking($bookingDetails,$ota_hotel_details,$otaBookingModel);
            // }elseif($bookingDetails['booking_status']=='Modify' && $otaBookingUpdateModel){
            //     return $this->updateBooking($bookingDetails,$ota_hotel_details,$otaBookingUpdateModel);
            // }elseif($bookingDetails['booking_status']=='Cancel' && $otaBookingUpdateModel){
            //         return $this->cancelBooking($bookingDetails,$ota_hotel_details,$otaBookingUpdateModel);
            // }
            // else{
            //    return 0;
            // }
    }
    //Method to save New Booking
    public function newBooking($bookingDetails,$ota_hotel_details,$otaBookingModel){
        $room_price = isset($bookingDetails['room_price'])?$bookingDetails['room_price']:0;
        $otaBookingModel->ota_id                = trim($ota_hotel_details->ota_id);
        $otaBookingModel->hotel_id              = trim($ota_hotel_details->hotel_id);
        $otaBookingModel->unique_id             = trim($bookingDetails['UniqueID']);
        $otaBookingModel->customer_details      = trim($bookingDetails['customerDetail']);
        $otaBookingModel->booking_status        = trim($bookingDetails['booking_status']);
        $otaBookingModel->rooms_qty             = trim($bookingDetails['rooms_qty']);
        $otaBookingModel->room_price            = trim($room_price);
        $otaBookingModel->room_type             = trim($bookingDetails['room_type']);
        $otaBookingModel->checkin_at            = trim($bookingDetails['checkin_at']);
        $otaBookingModel->checkout_at           = trim($bookingDetails['checkout_at']);
        $otaBookingModel->booking_date          = trim($bookingDetails['booking_date']);
        $otaBookingModel->amount                = number_format((float)$bookingDetails['amount'], 2, '.', '');
        $otaBookingModel->payment_status        = $bookingDetails['payment_status'];
        $otaBookingModel->rate_code             = trim($bookingDetails['rate_code']);
        $otaBookingModel->response_xml          = trim($bookingDetails['rlt']);
        $otaBookingModel->currency              = trim($bookingDetails['currency']);
        $otaBookingModel->channel_name          = trim($bookingDetails['channel_name']);
        $otaBookingModel->tax_amount            = number_format((float)trim($bookingDetails['tax_amount']), 2, '.', '');
        $otaBookingModel->no_of_adult           = trim($bookingDetails['no_of_adult']);
        $otaBookingModel->no_of_child           = trim($bookingDetails['no_of_child']);
        $otaBookingModel->inclusion             = $bookingDetails['inclusion'];
        $otaBookingModel->ip                    = '1.1.1.1';
        // if(isset($bookingDetails['special_information'])){
        //     $otaBookingModel->special_information=$bookingDetails['special_information'];
        // }
        // if(isset($bookingDetails['cancel_policy'])){
        //     $otaBookingModel->cancel_policy=$bookingDetails['cancel_policy'];
        // }
        $otaBookingModel->special_information   = isset($bookingDetails['special_information']) ? $bookingDetails['special_information'] : "NA";
        $otaBookingModel->cancel_policy        = isset($bookingDetails['cancel_policy']) ? $bookingDetails['cancel_policy'] : "NA";
        $otaBookingModel->confirm_status        = 1; //New booking status
        if($db_status = $otaBookingModel->save())

        {
            $ota_booking_tabel_id = $otaBookingModel->id;
            $this->cmOtaBookingInvStatusService->saveCurrentInvStatus($ota_booking_tabel_id,$ota_hotel_details->ota_id,$ota_hotel_details->hotel_id,$bookingDetails['checkin_at'],$bookingDetails['checkout_at'],$bookingDetails['room_type'],$otaBookingModel->booking_status,$otaBookingModel->rooms_qty);
        }
        $res=array('db_status'=>$db_status,'ota_booking_tabel_id'=>$ota_booking_tabel_id);
        return $res;
    }
    //Modify booking
    public function updateBooking($bookingDetails,$ota_hotel_details,$otaBookingUpdateModel){
            $room_price = isset($bookingDetails['room_price'])?$bookingDetails['room_price']:0;
            $otaBookingUpdateModel->ota_id            = trim($ota_hotel_details->ota_id);
            $otaBookingUpdateModel->hotel_id          = trim($ota_hotel_details->hotel_id);
            $otaBookingUpdateModel->unique_id         = trim($bookingDetails['UniqueID']);
            $otaBookingUpdateModel->customer_details  = trim($bookingDetails['customerDetail']);
            $otaBookingUpdateModel->booking_status    = trim($bookingDetails['booking_status']);
            $otaBookingUpdateModel->rooms_qty         = trim($bookingDetails['rooms_qty']);
            $otaBookingUpdateModel->room_price        = trim($room_price);
            $otaBookingUpdateModel->room_type         = trim($bookingDetails['room_type']);
            $otaBookingUpdateModel->checkin_at        = trim($bookingDetails['checkin_at']);
            $otaBookingUpdateModel->checkout_at       = trim($bookingDetails['checkout_at']);
            $otaBookingUpdateModel->booking_date      = trim($bookingDetails['booking_date']);
            $otaBookingUpdateModel->amount            = number_format((float)$bookingDetails['amount'], 2, '.', '');
            $otaBookingUpdateModel->payment_status    = $bookingDetails['payment_status'];
            $otaBookingUpdateModel->rate_code         = trim($bookingDetails['rate_code']);
            $otaBookingUpdateModel->response_xml      = trim($bookingDetails['rlt']);
            $otaBookingUpdateModel->currency          = trim($bookingDetails['currency']);
            $otaBookingUpdateModel->channel_name      = trim($bookingDetails['channel_name']);
            $otaBookingUpdateModel->tax_amount        = number_format((float)trim($bookingDetails['tax_amount']), 2, '.', '');
            $otaBookingUpdateModel->no_of_adult       = trim($bookingDetails['no_of_adult']);
            $otaBookingUpdateModel->no_of_child       = trim($bookingDetails['no_of_child']);
            $otaBookingUpdateModel->inclusion         = trim($bookingDetails['inclusion']);
            $otaBookingUpdateModel->ip                = '1.1.1.1';
            $otaBookingUpdateModel->cancel_status     = 0;
            // if(isset($bookingDetails['special_information'])){
            //     $otaBookingUpdateModel->special_information=$bookingDetails['special_information'];
            // }
            // if(isset($bookingDetails['cancel_policy'])){
            //     $otaBookingUpdateModel->cancel_policy=$bookingDetails['cancel_policy'];
            // }
            $otaBookingUpdateModel->special_information   = isset($bookingDetails['special_information']) ? $bookingDetails['special_information'] : "NA";
            $otaBookingUpdateModel->cancel_policy        = isset($bookingDetails['cancel_policy']) ? $bookingDetails['cancel_policy'] : "NA";
            if($db_status = $otaBookingUpdateModel->save()){
                $ota_booking_tabel_id = $otaBookingUpdateModel->id;
                $this->cmOtaBookingInvStatusService->saveCurrentInvStatus($ota_booking_tabel_id,$ota_hotel_details->ota_id,$ota_hotel_details->hotel_id,$bookingDetails['checkin_at'],$bookingDetails['checkout_at'],$bookingDetails['room_type'],$otaBookingUpdateModel->booking_status,$otaBookingUpdateModel->rooms_qty);
              }
            $res=array('db_status'=>$db_status,'ota_booking_tabel_id'=>$ota_booking_tabel_id);
            return $res;
    }
    //Cancel Booking
    public function cancelBooking($bookingDetails,$ota_hotel_details,$otaBookingUpdateModel){
        $otaBookingUpdateModel->booking_status= 'Cancel';
        $otaBookingUpdateModel->cancel_status= 1;//Updating the cancel status
        $otaBookingUpdateModel->modify_status=isset($bookingDetails['modify_status'])?$bookingDetails['modify_status']:0;
        $uniqueID=trim($bookingDetails['UniqueID']);
        $otaBookingData=CmOtaBookingRead::where('unique_id',$uniqueID)->where('confirm_status',1)->where('cancel_status',1)->first();
        $ota_booking_tabel_id = $otaBookingUpdateModel->id;

        if($otaBookingData){
            $res=array('db_status'=>false,'ota_booking_tabel_id'=>$ota_booking_tabel_id);
        }else{
            if($db_status = $otaBookingUpdateModel->save()){
                $this->cmOtaBookingInvStatusService->saveCurrentInvStatus($ota_booking_tabel_id,$ota_hotel_details->ota_id,$ota_hotel_details->hotel_id,$bookingDetails['checkin_at'],$bookingDetails['checkout_at'],$bookingDetails['room_type'],$otaBookingUpdateModel->booking_status,$otaBookingUpdateModel->rooms_qty);
            }
            $res=array('db_status'=>$db_status,'ota_booking_tabel_id'=>$ota_booking_tabel_id);
        }
       return $res;
    }
}
