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
use App\Http\Controllers\otacontrollers\BookingDataInsertationController;
use App\Http\Controllers\otacontrollers\InstantBucketController;
use App\Http\Controllers\otacontrollers\CurlController;
use App\Http\Controllers\Controller;

/**
 * PaytmController used for booking from Paytm.
 * @author Siri
 * @date-14/08/2020
 */

class HegController extends Controller
{
    protected $bookingData,$curlcall,$instantbucket;
    public function __construct(BookingDataInsertationController $bookingData,CurlController $curlcall,InstantBucketController $instantbucket)
    {
      $this->bookingData=$bookingData;
      $this->curlcall=$curlcall;
      $this->instantbucket=$instantbucket;
    }
    public function actionIndex(Request $request)
    {
        $ota_details_model             = new CmOtaDetails();
        $otaBookingModel               = new CmOtaBooking();
        $OtaAllAutoPushModel           = new CmOtaAllAutoPush();
        $postdata 					   = $request->getContent();
        $request_ip                    = $_SERVER['REMOTE_ADDR'];
        $array_data                    = (array) json_decode($postdata);
        if($postdata!='')
        {
            if(isset($array_data["accessKey"]))
            {
                if($array_data["accessKey"] == '827ccb0eea8a706c4c34a16891f84e7b')
                {
                    $OtaAllAutoPushModel->respones_xml = trim($postdata);
                    $OtaAllAutoPushModel->save();

                    $UniqueID             = $array_data['confirmationNo'];
                    $otaHotelCode         = $array_data['hotelId'];
                    //return $otaHotelCode;
                    $ota_hotel_details    = CmOtaDetails::select('*')
                                            ->where('ota_hotel_code','=', $otaHotelCode)
                                            ->where('is_status', 1)
                                            ->first();
                    if(!$ota_hotel_details)
                    {
                     echo $rtn_xml='{"reference":"'.$UniqueID.'", "status":"Not Confirmed", "confirmationId":"", "description":"Invalid ProductId"}';
                    }
                    else
                    {
                        $booking_status       = $array_data['bookingStatus'];
                        $rooms_qty            = 0;
                        $roomDetails          = $array_data['roomStays'];
                        $inclusion            = 'NA';
                        $adults               = array();
                        $child                = array();
                        $customerDetail       = $array_data['bookedBy'].','.$array_data['customerEmail'].','.$array_data['phoneNo'];
    
                        foreach ($roomDetails as $key => $value)
                        {
                          $room_type_ids[]      = $value->roomId;
                          $rate_plan_ids[]      = $value->rateId;
                          $adults[]             = $value->guestDetails->adults;
                          $child[]              = $value->guestDetails->children;
                        }
                        $rooms_qty            = sizeof($roomDetails);
                        $no_of_adult          = implode(',',$adults);
                        $no_of_child          = implode(',',$child);
                        $room_type            = implode(',', $room_type_ids);
                        $rate_code            = implode(',', $rate_plan_ids);
    
                        $amount               = $array_data['totalAmount'];
                        $tax_amount           = $array_data['taxes'];
                        $currency             = "INR";
                        $payment_status       = $array_data['paymentStatus'];
    
                        $checkin_at           = $array_data['checkInDate'];
                        $checkout_at          = $array_data['checkOutDate'];
                        $booking_date         = $array_data['bookedTime'];
                        
                        $rlt=$postdata;
                        if($booking_status == 'confirmed'){
                            $booking_status  = 'Commit';
                        }
                        if($booking_status == 'cancelled'){
                            $booking_status  = 'Cancel';
                        }
                        if($payment_status == 0){
                            $payment_status = 'Prepaid';
                        }
                        if($payment_status == 1){
                            $payment_status = 'Pay At Hotel';
                        }
                        if($payment_status == 2){
                            $payment_status = 'Partial Payment';
                        }

                        $bookingDetails = array('UniqueID'=>$UniqueID,'customerDetail'=>$customerDetail,'booking_status'=>$booking_status,'rooms_qty'=>$rooms_qty,'room_type'=>$room_type,'checkin_at'=>$checkin_at,'checkout_at'=>$checkout_at,'booking_date'=>$booking_date,'amount'=>$amount,'payment_status'=>$payment_status,'rate_code'=>$rate_code,'rlt'=>$rlt,'currency'=>$currency,'channel_name'=>'HappyEasyGo','tax_amount'=>$tax_amount,'no_of_adult'=>$no_of_adult,'no_of_child'=>$no_of_child,'inclusion'=>$inclusion);

                        $bookinginfo  = $this->bookingData->cmOtaBooking($bookingDetails,$ota_hotel_details);//this function call used for insert/update booking in database
                        $db_status  = $bookinginfo['db_status'];
                        $push_by = "HappyEasyGo";
                        if($db_status)
                        {
                            $this->instantbucket->bucketEngineUpdate($booking_status,$push_by,$ota_hotel_details,$bookinginfo['ota_booking_tabel_id']);//this function is used for booking bucket data updation
                            $rtn_xml = '{"message": "Successfully updated","status": "success"}';
                        }
                        else
                        {
                            $rtn_xml = '{"message": "Invalid ProductId or booking already exist","status": "failour"}';
                        }
                    }
                    echo $rtn_xml;
                }
                else{
                    echo "Please Provide Correct AccessKey";
                }
            } //is AccessKey not present
            else{
                echo "Please provide AccessKey";
            }
        }// post data not null
        else{
            echo "Please Provide Booking Data";
        }
    } //function close
}
