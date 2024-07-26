<?php
namespace App\Http\Controllers\otacontrollers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Input;
use Validator;
use App\CmOtaDetails;
use App\CmOtaBooking;
use App\CmOtaAllAutoPush; 
use App\CmOtaBookingPushBucket;
use App\CmBookingConfirmationResponse;
use DB;
use App\Http\Controllers\CmOtaBookingInvStatusService;
use App\Http\Controllers\otacontrollers\BookingDataInsertationController;
use App\Http\Controllers\otacontrollers\InstantBucketController;
use App\Http\Controllers\Controller;


/**
 * GoibiboController implements the CRUD actions for GoibiboController model.
 */
class GoomoController extends Controller
{
    protected $cmOtaBookingInvStatusService,$bookingData,$instantbucket;
    public function __construct(CmOtaBookingInvStatusService $cmOtaBookingInvStatusService,BookingDataInsertationController $bookingData,InstantBucketController $instantbucket)
    {
      $this->cmOtaBookingInvStatusService=$cmOtaBookingInvStatusService;
      $this->bookingData=$bookingData;
      $this->instantbucket=$instantbucket;
    } 
      
    public function index(Request $request)
    {     
        $ota_details_model          = new CmOtaDetails();
        $otaBookingModel            = new CmOtaBooking();
        $OtaAllAutoPushModel        = new CmOtaAllAutoPush();
        $our_api_key="a6e768a3-a499-4277-bba4-460e424dc3ec";//Constant
        $flag=false;
        $header_apiKey = $request->header('apiKey');
        if($header_apiKey!=$our_api_key)
        {
            $res=array('status'=>0,"message"=>"Api key is missing");
            return response()->json($res);
        }
        $data=$request->all();
        if($data['bookingStatus'] != 'Confirmed')
        {
            $booking_id =   $data['confirmationNo'];
            $details    =   $this->cmOtaBookingInvStatusService->checkBookingId($booking_id);
            if($details == false)
            {
                $res  =   "This booking is not available";
                return $res;
            }
        }
        if(!isset($data) || sizeof($data)==0)
        {
          $res=array('status'=>0,"message"=>"Please post the proper data");
          return response()->json($res);
        }
        if(!isset($data['bookingStatus']))
        { 
          $res=array('status'=>0,"message"=>"Booking status should not be empty");
          return response()->json($res); 
        }
        if(isset($data['bookingStatus']))
        { 
          if($data['bookingStatus']!="Confirmed" && $data['bookingStatus'] !="Modified" && ['bookingStatus']!="Cancelled" )
          {
            $res=array('status'=>0,"message"=>"Booking status should other than Confirmed OR Modified OR Cancelled ");
            return response()->json($res);
          } 
        }
        if($data['bookingStatus']=="Confirmed" || $data['bookingStatus']=="Modified")
        {
            $hotel_Code     = $data['hotelId'];
            $ota_hotel_details  = $ota_details_model
            ->where('ota_hotel_code' ,'=', $hotel_Code )
            ->where('is_status', 1)
            ->first();
            if(!$ota_hotel_details)
            {
                $res=array('status'=>0,"message"=>"Hotel id not available");
                return response()->json($res);
            }
            $UniqueID       = $data['confirmationNo'];
            $booking_status = $data['bookingStatus'];
            $customerDetail = $data['bookedBy'].' '.$data['customerEmail'].' '.$data['phoneNo'];
            $checkin_at     = $data['checkInDate'];
            $checkout_at    = $data['checkOutDate'];
            $booking_date   = $data['bookedTime']; 
            
            $amount         = $data['totalAmount'];
            $currency       = "INR";
            $payment_status = 'NA';

            $prefix="";
            $room_type="";
            $rooms_qty="";
            $rate_code="";
            foreach($data['roomStays'] as $room)
            {
              $room_type.= $prefix.$room['roomId'];
              $rooms_qty.= $prefix.'1';
              $rate_code.= $prefix.$room['rateId'];
              $prefix=',';
            }
            $bookingDetails = array('UniqueID'=>$UniqueID,'customerDetail'=>$customerDetail,'booking_status'=>$booking_status,'rooms_qty'=>$rooms_qty,'room_type'=>$room_type,'checkin_at'=>$checkin_at,'checkout_at'=>$checkout_at,'booking_date'=>$booking_date,'amount'=>$amount,'payment_status'=>$payment_status,'rate_code'=>$rate_code,'rlt'=>$rlt,'currency'=>$currency,'channel_name'=>$channel_name,'tax_amount'=>$tax_amount,'no_of_adult'=>$no_of_adult,'no_of_child'=>$no_of_child,'inclusion'=>$inclusion);
            $bookinginfo  = $this->bookingData->bookingData($bookingDetails,$ota_hotel_details);//this function call used for insert/update booking in database
            $bookingStatus  = $bookinginfo['bookingStatus'];
            $ota_booking_tabel_id =$bookinginfo['ota_booking_tabel_id'];
            if($bookingStatus)
            {
                $flag=true;
                $checking_status = '';
                if($booking_status == 'Confirmed'){
                $booking_status = 'Commit';
                }
                if($booking_status == 'Commit'){
                $checking_status  = $otaBookingModel
                          ->where('unique_id' ,'=', trim($UniqueID))
                          ->where('confirm_status' ,'=', 0)
                          ->first();
                }
                /*------- Sending Instances to bucket -----------------*/
                if($checking_status){ 
                  $this->instantbucket->instantBucket($bookingStatus,$push_by,$ota_hotel_details,$checking_status,$ota_booking_tabel_id);//this function is used for booking bucket data updation         
                }
              /*---------- sending Booking details to Booking Engine -----------*/
              if($booking_status == "Commit"){
                $be_status = 1;
                $actionLog = "newBookingPush";
              }
              if($booking_status == "Modify"){
                $be_status        = 6;
                $actionLog        = "modifyBookingPush";
                $checking_status  = true;
              }
              if($booking_status == 'Commit'){
                $checking_status  = $otaBookingModel
                ->where('unique_id' ,'=' ,trim($UniqueID))
                ->where('confirm_status','=', 0)
                ->first();
                if($checking_status){
                  $checking_status->confirm_status = 1;
                  $checking_status->save();
                }
              }
            }
        }     
        else
        {
          $UniqueID       = $data['confirmationNo'];
          $otaBookingUpdateModel  = $otaBookingModel
          ->where('unique_id' ,'=',trim($UniqueID))
          ->first();
          $ota_hotel_details  = $ota_details_model
          ->where('hotel_id' ,'=',$otaBookingUpdateModel->hotel_id )
          ->first();
          if(!$otaBookingUpdateModel){
            $res=array('status'=>0,"message"=>"No booking found");
            return response()->json($res);
          }
      
          $booking_status = $data['bookingStatus'];
          $customerDetail = $data['bookedBy'];
          $checkin_at     = $data['checkInDate'];
          $checkout_at    = $data['checkOutDate'];
          $booking_date   = $data['bookedTime'];
          $currency       = "INR";
          $payment_status = 'NA';
      
          if($otaBookingUpdateModel){
              $otaBookingUpdateModel->unique_id = trim($UniqueID);
              $otaBookingUpdateModel->checkin_at = trim($checkin_at);
              $otaBookingUpdateModel->checkout_at = trim($checkout_at);
              $otaBookingUpdateModel->booking_date = trim($booking_date);
              $otaBookingUpdateModel->customer_details  = trim($customerDetail);
              $otaBookingUpdateModel->booking_status =trim($booking_status);
              if($bookingStatus = $otaBookingUpdateModel->save()){
              $ota_booking_tabel_id = $otaBookingUpdateModel->id;
              $this->cmOtaBookingInvStatusService->saveCurrentInvStatus($ota_booking_tabel_id,$ota_hotel_details->ota_id,$ota_hotel_details->hotel_id,$checkin_at,$checkout_at,$otaBookingUpdateModel->room_type);
              }
          }
          $checking_status="";
          if($booking_status == 'Cancelled'){
            $booking_status = 'Cancel';
          }
          if($bookingStatus)
          {
            $flag=true;
            if($booking_status == 'Cancel'){
              $checking_status  = $otaBookingModel
                                ->where('unique_id' ,'=', trim($UniqueID))
                                ->where('cancel_status' ,'=', 0)
                                ->first();
              }
              /*------- Sending Instances to bucket -----------------*/
              if($checking_status){
                $this->instantbucket->instantBucket($bookingStatus,$push_by,$ota_hotel_details,$checking_status,$ota_booking_tabel_id);//this function is used for booking bucket data updation         
                }
                if($booking_status == 'Cancel'){
                  $checking_status  = $otaBookingModel
                        ->where('unique_id' ,'=' ,trim($UniqueID))
                        ->where('cancel_status','=', 0)
                        ->first();
                      
                    if($checking_status){
                      $checking_status->cancel_status = 1;
                      $checking_status->save();
                    }
                } 
          }
        }
        if($flag)
        {
          $res=array('status'=>1,"message"=>"Bookings has been pushed successfully!");
          return response()->json($res);
        }
        else
        {
          $res=array('status'=>0,"message"=>"Bookings push process failed!");
          return response()->json($res);
        } 
    } //function close
  }