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
use App\Jobs\RunBucket;
/**
 * GoibiboController used for booking from goibibo.
 * @auther Ranjit (modified by)
 * @date-23/01/2019
 */
class GoibiboTestController extends Controller
{
    protected $goibibo_bookingData,$curlcall,$instantbucket;
    public function __construct(BookingDataInsertationController $goibibo_bookingData,CurlController $curlcall,InstantBucketController $instantbucket)
    {
      $this->goibibo_bookingData=$goibibo_bookingData;
      $this->curlcall=$curlcall;
      $this->instantbucket=$instantbucket;
    }
    public function actionIndex(Request $request)
    {
        $ota_details_model          = new CmOtaDetails();
        $otaBookingModel            = new CmOtaBooking();
        $OtaAllAutoPushModel        = new CmOtaAllAutoPush();

        $postdata = $request->getContent();
        $request_ip = $_SERVER['REMOTE_ADDR'];
        $headers = apache_request_headers();
        if (isset($headers['Authorization']))
        {
          $key = $headers['Authorization'];
          if($key=='3CBAB7FD66D4E8FDA39F9398595C7')
          {
            if($postdata!='')
            {
              $OtaAllAutoPushModel->respones_xml = trim($postdata);
              $OtaAllAutoPushModel->save();
              $postdata=preg_replace('/&(?!#?[a-z0-9]+;)/', '&amp;', $postdata);
              $push_array_data    = json_decode(json_encode(simplexml_load_string($postdata)), true);
              //Fething hotel code and and Unique id
              if(isset($push_array_data['CancelledItem'])){
                $Hotel_Code         = $push_array_data['@attributes']['HotelCode'];
                $UniqueID           = $push_array_data['CancelledItem']['BookingId'];
                }else{
                $Hotel_Code         = $push_array_data['@attributes']['HotelCode'];
                $UniqueID           = $push_array_data['BookingId'];
              }
              //Checking the ota hotel is present in our system
              $ota_hotel_details  = $ota_details_model
                                    ->where('ota_hotel_code' ,'=', $Hotel_Code )
                                    ->first();
              if(!$ota_hotel_details)
              {
                return '<?xml version="1.0" encoding="UTF-8"?>
                <Error>This hotel is not with us!</Error>';
              }
              //Checking the auth parameter
              $auth_parameter     = json_decode($ota_hotel_details->auth_parameter);
              if($auth_parameter){
                $bearer_token       = trim($auth_parameter->bearer_token);
                $channel_token      = trim($auth_parameter->channel_token);
              }else{
                return '<?xml version="1.0" encoding="UTF-8"?>
                <Error>This hotel is not with us!</Error>';
              }
              /*Preparing the xml data to call goibibo for booking details
              using previously fethed hotel code and uniqueid*/
              $xml ='<?xml version="1.0" encoding="UTF-8" ?>
                          <Website Name="ingoibibo" HotelCode="'.$Hotel_Code.'">
                              <BookingId>'.$UniqueID.'</BookingId>
                      </Website>';
              $url = 'https://in.goibibo.com/api/chmv2/getbookingdetail/?bearer_token='.$bearer_token.'&channel_token='.$channel_token;
              //cURL request
              $array_details = $this->curlcall->curlRequest($url,$headers,$xml);
              $array_data=$array_details['array_data'];
              $rlt=$array_details['rlt'];
              //Getting the booking details

              dd($array_data);
              if(isset($array_data['Booking']["GuestPhoneNo"])){$phn=$array_data['Booking']["GuestPhoneNo"];}else{$phn='NA';}

              $booking_status = $array_data['Booking']['BookingStatus'];
              $room_type      = $array_data['Booking']['RoomTypeCode'];
              $rooms_qty      = $array_data['Booking']['NumberofRooms'];
              $customerDetail = $array_data['Booking']['GuestName'].','.'NA'.','.$phn;
              $checkin_at     = $array_data['Booking']['CheckInDate'];
              $checkout_at    = $array_data['Booking']['CheckoutDate'];
              $booking_date   = $array_data['Booking']['BookingDate'];
              $rate_code      = $array_data['Booking']['RatePlanCode'];
              $amount         = $array_data['Booking']['Price'];
              $currency       = $array_data['Booking']['Currency']['@attributes']['short'];
              $channel_name   = $array_data['Booking']["BookingVendorName"];
              $tax_amount     = $array_data['Booking']["PriceDetails"]["GST"];
              for($i=0;$i<$rooms_qty;$i++){
                $price[] = ($amount/$rooms_qty);
              }
              $room_price     = implode(',',$price);
              $adult          = array();
              $child          = array();
              $i              = 0;
              if(isset($array_data['Booking']["RoomStay"]["Room"][0]))
              {
                foreach($array_data['Booking']["RoomStay"]["Room"] as $rooms)
                {
                    $adult[$i]  = $rooms["Adult"];
                    $child[$i]  = $rooms["Child"];
                    $i++;
                }
              }
              else
              {
                foreach($array_data['Booking']["RoomStay"] as $rooms)
                {
                    $adult[$i]  = $rooms["Adult"];
                    $child[$i]  = $rooms["Child"];
                    $i++;
                }
              }
              $no_of_adult    = implode(',',$adult);
              $no_of_child    = implode(',',$child);
              $include     = array();
              $j=0;
              if(isset($array_data['Booking']["Inclusions"]["Inclusion"]) && is_array($array_data['Booking']["Inclusions"]["Inclusion"]))
              {
                foreach($array_data['Booking']["Inclusions"]["Inclusion"] as $inclus)
                {
                    $include[$j]=$inclus;
                    $j++;
                }
                $inclusion = implode(',',$include);
              }
              else
              {
                $inclusion = "";

              }
              $payment_status = strtolower($array_data['Booking']['PayAtHotelFlag'])=='false' ?  'Paid':'Pay at hotel';
              if(!$ota_hotel_details->hotel_id)
              {
                    $rtn_xml='<?xml version="1.0" encoding="UTF-8"?>
                              <Error>This hotel is not with us</Error>';
              }
              else
              {
              /*-----------------------Inserting Booking data---------------------- */
              //Checking the booking status of goibibo and setting our booking status
                if($booking_status == 'confirmed' || $booking_status == 'pending' )
                {
                    $booking_status = 'Commit';
                }elseif($booking_status == 'cancelled'){
                  $booking_status = 'Cancel';
                }
                $bookingDetails = array('UniqueID'=>$UniqueID,'customerDetail'=>$customerDetail,'booking_status'=>$booking_status,'rooms_qty'=>$rooms_qty,'room_type'=>$room_type,'checkin_at'=>$checkin_at,'checkout_at'=>$checkout_at,'booking_date'=>$booking_date,'amount'=>$amount,'payment_status'=>$payment_status,'rate_code'=>$rate_code,'room_price'=>$room_price,'rlt'=>$rlt,'currency'=>$currency,'channel_name'=>$channel_name,'tax_amount'=>$tax_amount,'no_of_adult'=>$no_of_adult,'no_of_child'=>$no_of_child,'inclusion'=>$inclusion);
                $bookinginfo  = $this->goibibo_bookingData->cmOtaBooking($bookingDetails,$ota_hotel_details);//this function call used for insert/update booking in database
                $db_status  = $bookinginfo['db_status'];
                $ota_booking_tabel_id =$bookinginfo['ota_booking_tabel_id'];
                //Checking db status
                if($db_status)
                {
                    $push_by = "Goibibo";
                      $this->instantbucket->bucketEngineUpdate($booking_status,$push_by,$ota_hotel_details,$ota_booking_tabel_id);//this function is used for booking bucket data updation
                      $rtn_xml='<?xml version="1.0" encoding="UTF-8" ?>
                      <Success>Thank You</Success>';
                }
                else
                {
                  $rtn_xml='<?xml version="1.0" encoding="UTF-8"?>
                  <Error>This Booking is already exist</Error>';
                }
              }
            } // post data not null
            else
            {
              $rtn_xml='<?xml version="1.0" encoding="UTF-8"?>
              <Error>Data Missing</Error>';
            }
            //$job=new RunBucket();
            //dispatch($job);
            echo $rtn_xml;
        }else{
          echo 'Invalid api key!';
        }
      }else{
        echo 'Invalid request!';
      }
    } //function close
}
