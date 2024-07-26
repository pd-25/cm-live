<?php
namespace App\Http\Controllers\otacontrollers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\CmOtaDetails;//class name from model
use App\CmOtaAllAutoPush;//class name from model
use App\CmBookingConfirmationResponse;//class name from model
use DB;
use App\Http\Controllers\otacontrollers\BookingDataInsertationController;
use App\Http\Controllers\otacontrollers\InstantBucketController;
use App\Http\Controllers\otacontrollers\CurlController;
use App\Http\Controllers\Controller;

/**
 * CleartripController implements booking for CleartripController model.
 * @ modify by ranjit
 * @ 24/01/19 
 */

class CleartripController extends Controller
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
        $OtaAllAutoPushModel           = new CmOtaAllAutoPush();
        $postdata                      = $request->getContent();
        $request_ip                    = $_SERVER['REMOTE_ADDR'];
        if($postdata!='')
        {
            $OtaAllAutoPushModel->respones_xml = trim($postdata);
            $OtaAllAutoPushModel->save();
            $array_data           = json_decode(json_encode(simplexml_load_string($postdata)), true);
            $UniqueID             = $array_data['confirmation-no'];
            if(isset($array_data['guest-details']['guest-email'])&&isset($array_data['guest-details']['guest-phone']))
            {
                $customerDetail       = $array_data['guest-details']['guest-name'].','.$array_data['guest-details']['guest-email'].','.$array_data['guest-details']['guest-phone'];
            }
            else{
                $customerDetail       = $array_data['guest-details']['guest-name'].','.'NA'.','.'NA';
            }
       
            $Hotel_Code           = $array_data['hotel-id'];
            $booking_status       = $array_data['@attributes']['type'];
            $BookingStatus        = $array_data['@attributes']['type'];
            $rooms_qty            = $array_data['no-of-rooms'];
            $room_type            = $array_data['room-id'];
            $amount               = $array_data['net-payable'];
            $currency             = $array_data['currency'];
            $tax_amount           = $array_data["hotel-gst"];

            if(sizeof($array_data["inclusions"])==0)
            {
                $inclusion            = 'NA';
            }
            else if(is_array($array_data["inclusions"])){
                $inclusion            = implode(',',$array_data["inclusions"]);
            }
            else{
                $inclusion = $array_data["inclusions"];
            }
            $adult=array();
            $child=array();
            $i=0;
            if(isset($array_data["guest-details"]["guest-detail"][0]))
            {
                foreach($array_data["guest-details"]["guest-detail"] as $gest)
                {
                    $adult[$i]  = $gest['adults-total'];
                    $child[$i]  = $gest['children-total'];  
                    $i++;
                }
                $no_of_adult=implode(',',$adult);
                $no_of_child=implode(',',$child);
            }
            else{
                $no_of_adult=$array_data["guest-details"]["guest-detail"]['adults-total'];
                $no_of_child=$array_data["guest-details"]["guest-detail"]['children-total'];
            }
           
            $payment_status       = $array_data["pay-at-hotel"]=='true'?'Pay at hotel':'Paid';
            $checkin_at           = date('Y-m-d', strtotime(strtr($array_data['check-in-date'], '/', '-')));
            $checkout_at          = date('Y-m-d', strtotime(strtr($array_data['check-out-date'], '/', '-')));
            $booking_date         = date('Y-m-d', strtotime(strtr($array_data['booked-on'], '/', '-'))); 
            $rate_code            = $array_data['rate-id'];
           
            $ota_hotel_details    = $ota_details_model
                                    ->where('ota_hotel_code' , '='  ,$Hotel_Code)
                                    ->first();
            $curent_datetimeStamp = date('d/m/Y H:m:s');
            if(!$ota_hotel_details)
            {
                 $rtn_xml='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><hotel-bookings timestamp="'.$curent_datetimeStamp.'" type="'.$BookingStatus.'" xmlns="http://www.cleartrip.com/extranet/hotel-bookings-response"><hotel-id>'.$Hotel_Code.'</hotel-id><room-id>'.$room_type.'</room-id><rate-id>'.$rate_code.'</rate-id><confirmation-no>'.$UniqueID.'</confirmation-no><status><code>B002</code><description>Hotel code is not in sync!</description></hotel-bookings>';
            }
            else
            {   
                $rlt=$postdata;
                if($booking_status == 'BOOK')
                {
                    $booking_status  = 'Commit';
                }
                if($booking_status == 'CANCEL')
                {
                    $booking_status  = 'Cancel';
                }
                $bookingDetails = array('UniqueID'=>$UniqueID,'customerDetail'=>$customerDetail,'booking_status'=>$booking_status,'rooms_qty'=>$rooms_qty,'room_type'=>$room_type,'checkin_at'=>$checkin_at,'checkout_at'=>$checkout_at,'booking_date'=>$booking_date,'amount'=>$amount,'payment_status'=>$payment_status,'rate_code'=>$rate_code,'rlt'=>$rlt,'currency'=>$currency,'channel_name'=>'Cleartrip','tax_amount'=>$tax_amount,'no_of_adult'=>$no_of_adult,'no_of_child'=>$no_of_child,'inclusion'=>$inclusion);
                $bookinginfo  = $this->bookingData->cmOtaBooking($bookingDetails,$ota_hotel_details);//this function call used for insert/update booking in database
                $db_status  = $bookinginfo['db_status'];
                $ota_booking_tabel_id =$bookinginfo['ota_booking_tabel_id'];
                $push_by = "Cleartrip";
                $cm_confirmation_id    = strtotime("now").$ota_hotel_details->ota_id.date("ymd").$bookinginfo['ota_booking_tabel_id'];
                $this->bookingConfirmation($ota_hotel_details,$UniqueID,$ota_booking_tabel_id,$cm_confirmation_id);
                if($db_status)
                {
                    $this->instantbucket->bucketEngineUpdate($booking_status,$push_by,$ota_hotel_details,$bookinginfo['ota_booking_tabel_id']);//this function is used for booking bucket data updation
                    $rtn_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><hotel-bookings timestamp="'.$curent_datetimeStamp.'" type="'.$BookingStatus.'" xmlns="http://www.cleartrip.com/extranet/hotel-bookings-response"><hotel-id>'.$Hotel_Code.'</hotel-id><room-id>'.$room_type.'</room-id><rate-id>'.$rate_code.'</rate-id><confirmation-no>'.$UniqueID.'</confirmation-no><status><code>B001</code><description>Request was processed successfully</description></status></hotel-bookings>';
                }
                else
                {
                $rtn_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><hotel-bookings timestamp="'.$curent_datetimeStamp.'" type="'.$BookingStatus.'" xmlns="http://www.cleartrip.com/extranet/hotel-bookings-response"><hotel-id>'.$Hotel_Code.'</hotel-id><room-id>'.$room_type.'</room-id><rate-id>'.$rate_code.'</rate-id><confirmation-no>'.$UniqueID.'</confirmation-no><status><code>B002</code><description>Request processing has failed or booking already with us</description></status></hotel-bookings>';
                }
            }
            echo $rtn_xml;
        } 
        else
        {
            echo $rtn_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><hotel-bookings timestamp="'.$curent_datetimeStamp.'" type="'.$BookingStatus.'" xmlns="http://www.cleartrip.com/extranet/hotel-bookings-response"><hotel-id>'.$Hotel_Code.'</hotel-id><room-id>'.$room_type.'</room-id><rate-id>'.$rate_code.'</rate-id><confirmation-no>'.$UniqueID.'</confirmation-no><status><code>B002</code><description>No Bookings data to process!</description></status></hotel-bookings>';
        }
    } //function close

        public function bookingConfirmation($ota_hotel_details,$UniqueID,$booking_id,$cm_confirmation_id)
        {
            $cmBookingConfirmationResponseModel = new CmBookingConfirmationResponse();
            $auth_parameter                     = json_decode($ota_hotel_details->auth_parameter);
            $api_key                            = trim($auth_parameter->api_key);
            $headers  = array (
            //Regulates versioning of the XML interface for the API
            'Content-Type: application/xml',
            'X-CT-SOURCETYPE: API',
            'X-CT-API-KEY: '.$api_key,
            );
            $xml = '<?xml version="1.0" encoding="UTF-8"?>
                    <hotel-booking-confirmation xmlns="http://www.cleartrip.com/extranet/hotel-booking-confirmation">
                    <trip-ref>'.$UniqueID.'</trip-ref>
                    <pms-confirmation-no>'.$cm_confirmation_id.'</pms-confirmation-no>
                    <ds-confirmation-no>'.$cm_confirmation_id.'</ds-confirmation-no>
                    </hotel-booking-confirmation>';
            
            $url = 'http://apistaging.cleartrip.com/chmm/service/reconfirmBookings';
            $array_details = $this->curlcall->curlRequest($url,$headers,$xml);//used for cURL request
            $array_data=$array_details['array_data'];
            $rlt=$array_details['rlt'];
            $cmBookingConfirmationResponseModel->booking_id         = trim($booking_id);
            $cmBookingConfirmationResponseModel->cm_confirmation_id = $cm_confirmation_id;
            $cmBookingConfirmationResponseModel->xml                = trim($rlt);
            $cmBookingConfirmationResponseModel->save();                
        }
}