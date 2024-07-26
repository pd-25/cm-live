<?php
namespace App\Http\Controllers\otacontrollers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Input;
use Validator;
use App\CmOtaDetailsRead;
use App\CmOtaBooking;
use App\CmOtaBookingRead;
use App\CmOtaAllAutoPush;
use App\HotelInformation;
use App\CompanyDetails;
use App\CmBookingConfirmationResponse;
use App\AirbnbRefereshToken;
use DB;
use App\Http\Controllers\CmOtaBookingInvStatusService;
use App\Http\Controllers\otacontrollers\BookingDataInsertationController;
use App\Http\Controllers\otacontrollers\InstantBucketController;
use App\Http\Controllers\Controller;
use App\CmOtaBookingPushBucket;
use App\CmOtaRoomTypeSynchronize;
use App\LogTable;
use App\AirbnbListingDetails;
/**
*   AirBnbController implements the reservation from Airbnb
**/
class AirBnbController extends Controller
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
        $company   = new CmOtaDetailsRead();
        $otalog    = new LogTable();
        $cmOtaRoomTypeSynchronizeModel=new CmOtaRoomTypeSynchronize();
        date_default_timezone_set("Asia/Kolkata");
        $request_ip                   = $_SERVER['REMOTE_ADDR'];
        $ota_details_datas            = CmOtaDetailsRead::select('*')
                                              ->where('ota_name' ,'=', 'Airbnb')
                                              ->where('is_active', 1)
                                              ->where('is_status', 1)
                                              ->get();

        foreach ($ota_details_datas as $ota_details_data)
        {
            $hotel_id                     = $ota_details_data->hotel_id;
            $ota_hotel_code               = $ota_details_data->ota_hotel_code;
            $ota_id                       = $ota_details_data->ota_id;

            $auth_parameter               = json_decode($ota_details_data->auth_parameter);
            $api_key                      = trim($auth_parameter->X_Airbnb_API_Key);
            $commonUrl                    = $ota_details_data->url;
            $hotel_info=HotelInformation::where('hotel_id',$hotel_id)->first();
            $airbnbModel=new AirbnbListingDetails();
            $apiKey=env('AIRBNB-APIKEY');
            $comp_details=AirbnbRefereshToken::where('company_id',$hotel_info->company_id)->select('airbnb_refresh_token')->first();
            $refresh_token=$comp_details->airbnb_refresh_token;
            $auth=$airbnbModel->getAirBnbToken($refresh_token);
            $date                         = new \DateTime();
            $dateTimestamp                = $date->getTimestamp();

            $fromDate  = date("Y-m-d");
            $toDate    = date("Y-m-d");
            $room_types=$cmOtaRoomTypeSynchronizeModel->where(array("hotel_id"=>$hotel_id,"ota_type_id"=>$ota_id))->get();
            foreach ($room_types as $room_type)
            {
                $OtaAllAutoPushModel          = new CmOtaAllAutoPush();
                $url        = $commonUrl.'reservations/?listing_id='.$room_type->ota_room_type.'&start_date='.$fromDate.'&end_date='.$toDate;
                $ch = curl_init();

                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");


                $headers = array();
                $headers[] = "X-Airbnb-Oauth-Token: $auth";
                $headers[] = "X-Airbnb-Api-Key: $api_key";
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

                $result = curl_exec($ch);
                if (curl_errno($ch)) {
                    echo 'Error:' . curl_error($ch);
                }
                curl_close ($ch);
                // $result='{
                //     "metadata": {},
                //     "reservations": [{
                //         "cancellation_policy_category": "super_strict_30",
                //         "confirmation_code": "XEPATD",
                //         "created_at": "2017-12-18T11:50:58+00:00",
                //         "updated_at": "2017-12-28T16:40:23+00:00",
                //         "booked_at": "2017-12-18T11:56:35+00:00",
                //         "end_date": "2018-01-03",
                //         "expected_payout_amount": 1102,
                //         "expected_payout_amount_accurate": "1102.00",
                //         "guest_email": "nick-owme5321y975kcat@guest.airbnb.com",
                //         "guest_first_name": "Nick",
                //         "guest_id": 1603069,
                //         "guest_last_name": "Yu",
                //         "guest_phone_numbers": [
                //             "555-555-5555"
                //         ],
                //         "guest_preferred_locale": "en",
                //         "host_currency": "USD",
                //         "host_id": 1743298,
                //         "listing_base_price": 1136,
                //         "listing_base_price_accurate": "1136.00",
                //         "listing_cancellation_host_fee": 0,
                //         "listing_cancellation_host_fee_accurate": "0.00",
                //         "listing_cancellation_payout": 0,
                //         "listing_cancellation_payout_accurate": "0.00",
                //         "listing_host_fee": 34,
                //         "listing_host_fee_accurate": "34.00",
                //         "listing_id": 4525291,
                //         "nights": 2,
                //         "number_of_guests": 1,
                //         "occupancy_tax_amount_paid_to_host": 0,
                //         "occupancy_tax_amount_paid_to_host_accurate": "0.00",
                //         "start_date": "2018-01-01",
                //         "status_type": "accept",
                //         "standard_fees_details": [{
                //                 "amount_native": 10.45,
                //                 "fee_type": "PASS_THROUGH_RESORT_FEE"
                //             },
                //             {
                //                 "amount_native": 10.00,
                //                 "fee_type": "PASS_THROUGH_LINEN_FEE"
                //             }
                //         ],
                //         "thread_id": 435023593,
                //         "total_paid_amount": 120,
                //         "total_paid_amount_accurate": "120.00",
                //         "transient_occupancy_tax_paid_amount": 20,
                //         "transient_occupancy_tax_paid_amount_accurate": "20.45",
                //         "transient_occupancy_tax_details": [{
                //                 "amount_usd": 10.00,
                //                 "name": "State Occupancy Tax"
                //             },
                //             {
                //                 "amount_usd": 10.45,
                //                 "name": "City Lodging Tax"
                //             }
                //         ],
                //         "cancellation_policy": "super_strict_30"
                //     }]
                // }';
                $array_data = json_decode($result,true);
                if(isset($array_data['reservations']))
                {
                    $OtaAllAutoPushModel->respones_xml = trim($result);
                    $OtaAllAutoPushModel->save();
                    $hotelReservation_data = $array_data['reservations'];
                if($hotelReservation_data)
                {
                    foreach ($hotelReservation_data as $ki => $vi)
                    {
                        $otaBookingModel              = new CmOtaBooking();
                        $uniqueID               = $vi['confirmation_code'];
                        $hotel_Code             = $ota_hotel_code;
                        $booking_status         = $vi['status_type'];
                        $rooms_qty           = 1;
                        $room_type           = $vi['listing_id'];
                        $rate_code           = "";
                        $checkin_at          = date('Y-m-d', strtotime($vi['start_date']));
                        $checkout_at         = date('Y-m-d', strtotime($vi['end_date']));
                        $booking_date        = date('Y-m-d H:i:s', strtotime($vi['booked_at']));

                        $customerDetail =$vi['guest_first_name'].' '.$vi['guest_last_name'].' '.$vi['guest_email'].' '.$vi['guest_phone_numbers'][0];
                        $amount              =  $vi['listing_base_price']+$vi['listing_host_fee']+$vi['occupancy_tax_amount_paid_to_host'];
                        $payment_status      = 'NA';
                        $currency=$vi['host_currency'];
                        $ota_hotel_details   = CmOtaDetailsRead::select('*')
                                      ->where('ota_hotel_code' ,'=', $hotel_Code)
                                      ->first();
                        $otalog->ota_id =  $ota_hotel_details->ota_id;
                        $otalog->hotel_id =  $ota_hotel_details->hotel_id;
                        $otalog->request_msg =  $url;
                        $otalog->booking_ref_id = $uniqueID;
                        $otalog->response_msg = $result;
                        $otalog->save();
                        if($booking_status == 'accept' || $booking_status == 'cancelled_by_guest')
                        {
                          if($ota_hotel_details->hotel_id)
                          {
                            $bookingDetails = array('UniqueID'=>$UniqueID,'customerDetail'=>$customerDetail,'booking_status'=>$booking_status,'rooms_qty'=>$rooms_qty,'room_type'=>$room_type,'checkin_at'=>$checkin_at,'checkout_at'=>$checkout_at,'booking_date'=>$booking_date,'amount'=>$amount,'payment_status'=>$payment_status,'rate_code'=>$rate_code,'rlt'=>$result,'currency'=>$currency,'channel_name'=>$channel_name,'tax_amount'=>$tax_amount,'no_of_adult'=>$no_of_adult,'no_of_child'=>$no_of_child,'inclusion'=>$inclusion);
                            $bookinginfo  = $this->bookingData->bookingData($bookingDetails,$ota_hotel_details);//this function call used for insert/update booking in database
                            $bookingStatus  = $bookinginfo['bookingStatus'];
                            $ota_booking_tabel_id =$bookinginfo['ota_booking_tabel_id'];

                              /*-----------------------is Inserting Booking data---------------------- */
                              if(isset($bookingStatus))
                              {
                                  $push_by = "Airbnb";
                                  $checking_status = ' ';
                                  if($booking_status == 'accept'){
                                  $booking_status1 = 'Commit';
                                  }
                                  if($booking_status == 'cancelled_by_guest'){
                                  $booking_status1 = 'Cancel';
                                  }

                                  if($booking_status1 == 'Commit'){
                                  $checking_status  = CmOtaBookingRead::select('*')
                                  ->where('unique_id' ,'=', trim($uniqueID) )
                                  ->where('confirm_status' ,'=', 0 )
                                  ->first();
                                  }
                                  if($booking_status1 == 'Cancel'){
                                  $checking_status  =CmOtaBookingRead::select('*')
                                                    ->where('unique_id' ,'=', trim($uniqueID) )
                                                    ->where('cancel_status' ,'=', 0 )
                                                    ->first();
                                  }
                                  /*------- Sending Instances to bucket -----------------*/
                                  if($checking_status){
                                    $this->instantbucket->instantBucket($bookingStatus,$push_by,$ota_hotel_details,$checking_status,$ota_booking_tabel_id);//this function is used for booking bucket data updation
                                  }
                                 /*---------- sending Booking details to Booking Engine -----------*/
                                if($booking_status1 == "Commit"){
                                    $be_status = 1;
                                    $actionLog = "newBookingPush";
                                }
                                if($booking_status1 == "Modify"){
                                    $be_status        = 6;
                                    $actionLog        = "modifyBookingPush";
                                    $checking_status  = true;
                                }
                                if($booking_status1 == "Cancel"){
                                    $be_status = 3;
                                    $actionLog = "cancelBookingPush";
                                }

                                if($booking_status1 == 'Commit'){
                                  $checking_status  = CmOtaBookingRead::select('*')
                                  ->where('unique_id' ,'=', trim($uniqueID) )
                                  ->where('confirm_status' ,'=', 0 )
                                  ->first();
                                    if($checking_status){
                                      $checking_status->confirm_status = 1;
                                      $checking_status->save();
                                    }
                                }
                                if($booking_status1 == 'Cancel'){
                                  $checking_status  =CmOtaBookingRead::select('*')
                                                    ->where('unique_id' ,'=', trim($uniqueID) )
                                                    ->where('cancel_status' ,'=', 0 )
                                                    ->first();
                                    if($checking_status){
                                      $checking_status->cancel_status = 1;
                                      $checking_status->save();
                                        }
                                  }
                              } // is inserting booking data
                            }
                          } // if closed for checking Reserved and Cancelled Booking.
                      } // foreach $hotelReservation_data
                  } // else if closed for $hotelReservation_data['@attributes']
                } // if closed for $array_data['Success']
                else{
                  return "No bookings/cancellation found for your search criteria";
                }
            } //ota details closed here
        }
    } //function close
    public function getAirBnbToken()
    {
        //$code=env('AIRBNB-CODE');
        $auth='28nb6aej5cji9vsnqbh22di8y:1db1dhdo8pwcsbq816bcb8uk6';
      // Generated by curl-to-PHP: http://incarnate.github.io/curl-to-php/
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://api.airbnb.com/v2/oauth2/authorizations?_unwrapped=true");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "{ \"code\": \"3l9110kxcn8g8tvkkma4qu9js\" }");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_USERPWD, "28nb6aej5cji9vsnqbh22di8y" . ":" . "1db1dhdo8pwcsbq816bcb8uk6");

        $headers = array();
        $headers[] = "Content-Type: application/json";
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }
        curl_close ($ch);
        $result=json_decode($result);
        $result->access_token;
    }
    public function testReservation(Request $request)
    {
      $res['available']=true;
      return response()->json( $res);
    }
    public function testMakeReservation(Request $request)
    {
      $res['succeed']=true;
      return response()->json( $res);
    }
    public function testUpdateReservation($confirmation_code="",Request $request)
    {
      $res=array();
      if($confirmation_code){
        $res['succeed']=true;
      }
      else{
        $res['succeed']=false;
      }
      return response()->json( $res);
    }
    public function testNotify(Request $request)
    {
      $res['succeed']=true;
      return response()->json( $res);
    }
}
