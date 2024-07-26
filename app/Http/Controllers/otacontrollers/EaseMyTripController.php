<?php
namespace App\Http\Controllers\otacontrollers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Input;
use Validator;
use App\CmOtaDetailsRead;
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
 * EaseMyTripController used for booking from EaseMyTrip.
 * @auther Ranjit (modified by)
 * @date-31/01/2019
 */
class EaseMyTripController extends Controller
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
        $ota_details_model             = new CmOtaDetailsRead();
        $otaBookingModel               = new CmOtaBooking();
        $OtaAllAutoPushModel           = new CmOtaAllAutoPush();
        $postdata 						=$request->getContent();
        $request_ip = $_SERVER['REMOTE_ADDR'];
        $array_data           = (array) json_decode($postdata);
        if($postdata!='')
        {
            if(isset($array_data["accessKey"]))
            {
                if($array_data["accessKey"] == 'b1efd926014341e0b427000abf298cee')
                {
                    $OtaAllAutoPushModel->respones_xml = trim($postdata);
                    $OtaAllAutoPushModel->save();

                    $UniqueID             = $array_data['confirmationNo'];
                    $otaHotelCode         = $array_data['hotelId'];
                    //return $otaHotelCode;
                    $ota_hotel_details    = CmOtaDetailsRead::select('*')
                                            ->where('ota_hotel_code','=', $otaHotelCode)
                                            ->where('is_status', 1)
                                            ->first();

                    if(!$ota_hotel_details)
                    {
                     $rtn_xml='{
                                "message":"Invalid Product id(This product id no longer with us)",
                                "status":"failure"
                                }';
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
                        $rooms_qty            = (int)$rooms_qty+$value->noOfUnits;
                        }
                        $no_of_adult          = implode(',',$adults);
                        $no_of_child          = implode(',',$child);
                        $room_type            = implode(',', $room_type_ids);
                        $rate_code            = implode(',', $rate_plan_ids);

                        $amount               = $array_data['totalAmount'];
                        $tax_amount           = $array_data['taxes'];
                        $currency             = "INR";
                        if($array_data['paymentStatus'] == 0){
                            $payment_status =   'Prepaid';
                        }
                        else if($array_data['paymentStatus'] == 1){
                            $payment_status =   'Pay at hotel';
                        }
                        else if($array_data['paymentStatus'] == 2){
                            $payment_status =   'Partial payment';
                        }

                        $checkin_at           = $array_data['checkInDate'];
                        $checkout_at          = $array_data['checkOutDate'];
                        $booking_date         = $array_data['bookedTime'];
                        
                        $rlt=$postdata;
                        if($booking_status == 'confirmed'){
                        $booking_status  = 'Commit';
                        }
                        if($booking_status == 'Cancelled'){
                        $booking_status  = 'Cancel';
                        }
                        $bookingDetails = array('UniqueID'=>$UniqueID,'customerDetail'=>$customerDetail,'booking_status'=>$booking_status,'rooms_qty'=>$rooms_qty,'room_type'=>$room_type,'checkin_at'=>$checkin_at,'checkout_at'=>$checkout_at,'booking_date'=>$booking_date,'amount'=>$amount,'payment_status'=>$payment_status,'rate_code'=>$rate_code,'rlt'=>$rlt,'currency'=>$currency,'channel_name'=>'EaseMyTrip','tax_amount'=>$tax_amount,'no_of_adult'=>$no_of_adult,'no_of_child'=>$no_of_child,'inclusion'=>$inclusion);
                        $bookinginfo  = $this->bookingData->cmOtaBooking($bookingDetails,$ota_hotel_details);//this function call used for insert/update booking in database
                        $db_status  = $bookinginfo['db_status'];
                        $cm_confirmation_id =$bookinginfo['ota_booking_tabel_id'];
                        $push_by = "EaseMyTrip";
                        if($db_status)
                        {
                            $this->instantbucket->bucketEngineUpdate($booking_status,$push_by,$ota_hotel_details,$cm_confirmation_id);//this function is used for booking bucket data updation
                            $rtn_xml = '{
                                "message":"message for success",
                                "status":"success"
                                }';
                        }
                        else
                        {
                            $rtn_xml = '{
                                "message":"Booking already exist",
                                "status":"failure"
                                }';
                        }
                      }

                }
                else{

                    $rtn_xml = '{
                                "message":"Please Provide Correct AccessKey",
                                "status":"success"
                                }';
                }
            } //is AccessKey not present
            else{
                $rtn_xml = '{
                                "message":"Please provide AccessKey",
                                "status":"success"
                                }';
            }
        }// post data not null
        else{
            $rtn_xml = '{
                                "message":"Please Provide Booking Data",
                                "status":"success"
                                }';
        }
        return $rtn_xml;
    } //function close
}
