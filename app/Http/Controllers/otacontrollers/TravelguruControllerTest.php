<?php
namespace App\Http\Controllers\otacontrollers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Input;
use Validator;
use App\CmOtaDetails;
use App\CmOtaBooking;
use App\CmOtaAllAutoPush;
use App\CmBookingConfirmationResponse;
use DB;
use App\Http\Controllers\CmOtaBookingInvStatusService;
use App\CmOtaBookingPushBucket;
use App\Http\Controllers\otacontrollers\BookingDataInsertationController;
use App\Http\Controllers\otacontrollers\InstantBucketController;
use App\Http\Controllers\otacontrollers\CurlController;
use App\Http\Controllers\Controller;
/**
*   TravelguruController implements the travel guru booking.
*   @modify by ranjit
*   @29/01/19
**/
class TravelguruControllerTest extends Controller
{
    protected $cmOtaBookingInvStatusService;
    protected $bookingData,$curlcall,$instantbucket;
    public function __construct(CmOtaBookingInvStatusService $cmOtaBookingInvStatusService,BookingDataInsertationController $bookingData,CurlController $curlcall,InstantBucketController $instantbucket)
    {
      $this->cmOtaBookingInvStatusService=$cmOtaBookingInvStatusService;
      $this->bookingData=$bookingData;
      $this->curlcall=$curlcall;
      $this->instantbucket=$instantbucket;
    }
    public function actionIndex(Request $request)
    {
        /*$request_ip                   = $_SERVER['REMOTE_ADDR'];

        $ota_details_datas            = CmOtaDetails::select('*')
                                              ->where('ota_name' ,'=', 'Travelguru')
                                              ->get();

        for($i=0;$i<sizeof($ota_details_datas);$i++)
        {
            $ota_details_data=$ota_details_datas[$i];
            $OtaAllAutoPushModel          = new CmOtaAllAutoPush();
            $hotel_id                     = $ota_details_data->hotel_id;
            $ota_hotel_code               = $ota_details_data->ota_hotel_code;
            $auth_parameter               = json_decode($ota_details_data->auth_parameter);
            $MessagePassword              = trim($auth_parameter->MessagePassword);
            $ID                           = trim($auth_parameter->ID);
            $commonUrl                    = $ota_details_data->url;

            $headers = array (
            'Content-Type: application/xml',
            );
            $fromDate  = date("Y-m-d");
            $toDate    = date("Y-m-d");
            //Preparing XML data to pull the bookings
            $xml       = '<?xml version="1.0" encoding="UTF-8"?>
            <OTA_ReadRQ Version="5.001" xmlns="http://www.opentravel.org/OTA/2003/05">
            <POS>
            <Source>
            <RequestorID MessagePassword="'.$MessagePassword.'" Type="CHM" ID="'.$ID.'"/>
            </Source>
            </POS>
            <UniqueID ID="" ID_Context="Both" />
            <ReadRequests>
            <HotelReadRequest HotelCode="'.$ota_hotel_code.'">
            <SelectionCriteria Start="'.$fromDate.'T00:00:00" End="'.$toDate.'T00:00:00"/>
            </HotelReadRequest>
            </ReadRequests>
            </OTA_ReadRQ>';
            $url        = $commonUrl.'reservations/resRetrieve';
            $array_details = $this->curlcall->curlRequest($url,$headers,$xml);*///used for cURL request
            $result=$request->getcontent();
            $array_data=$array_data = json_decode(json_encode(simplexml_load_string($result)), true);


            $OtaAllAutoPushModel          = new CmOtaAllAutoPush();
            if(isset($array_data['Success']) && isset($array_data['ReservationsList']['HotelReservation']))
            {
              $OtaAllAutoPushModel->respones_xml = trim($result);
              $OtaAllAutoPushModel->save();
              $hotelReservation_data = $array_data['ReservationsList']['HotelReservation'];
              if(isset($hotelReservation_data['@attributes']))
              {
                $this->saveBookingData($hotelReservation_data,$result);
              }
              else //Multiple booking execution
              {
                foreach ($hotelReservation_data as $ki => $vi)
                {
                  $this->saveBookingData($vi,$result);
                } // foreach $hotelReservation_data
              } // else if closed for $hotelReservation_data['@attributes']
            } // if closed for $array_data['Success']
            else{
            echo "No bookings/cancellation found for your search criteria";
            }
        //} //ota details closed here
    } //function close
  public function saveBookingData($hotelReservation_data,$result)
  {
      $otaBookingModel        = new CmOtaBooking();
      $uniqueID               = $hotelReservation_data['@attributes']['CreatorID'];

      $hotel_Code             = $hotelReservation_data['RoomStays']['RoomStay']['BasicPropertyInfo']['@attributes']['HotelCode'];
      $booking_status         = $hotelReservation_data['@attributes']['ResStatus'];
      if(strtoupper($booking_status)=='CANCELLED')
      {
        $uniqueID=$hotelReservation_data['ResGlobalInfo']['HotelReservationIDs']['HotelReservationID']['@attributes']['ResID_Value'];
        $otaBookingData=CmOtaBooking::where('unique_id',$uniqueID)->where('confirm_status',1)->where('cancel_status',1)->first();
        if(!$otaBookingData)
        {
          $this->cancelBooking($hotelReservation_data,$hotel_Code);
        }
      }else{
      $roomRates              = $hotelReservation_data['RoomStays']['RoomStay']['RoomRates']['RoomRate'];
      if(isset($roomRates['@attributes']))
      {
          $rooms_qty= $roomRates['@attributes']['NumberOfUnits'];
          $room_type  = $roomRates['@attributes']['RoomID'];
          $rate_code  = $roomRates['@attributes']['RatePlanID'];
          $checkin_at          = date('Y-m-d', strtotime($hotelReservation_data['ResGlobalInfo']['TimeSpan']['@attributes']['Start']));
          $checkout_at         = date('Y-m-d', strtotime($hotelReservation_data['ResGlobalInfo']['TimeSpan']['@attributes']['End']));
          $booking_date_array  = explode('T', $hotelReservation_data['@attributes']['CreateDateTime']);
          $booking_date        = $booking_date_array[0];
          $customer_info_array = $hotelReservation_data['ResGlobalInfo']['Profiles']['ProfileInfo']['Profile']['Customer'];

          if(isset($customer_info_array['ContactPerson'][7]['@attributes']['ContactType']))
          {
            $customerDetail      = $customer_info_array['@attributes']['Text'].','.str_replace("Email:","",$customer_info_array['ContactPerson'][7]['@attributes']['ContactType']).','.str_replace("Phone:","",$customer_info_array['ContactPerson'][0]['@attributes']['ContactType']);
          }
          else
          {
            $customerDetail      = $customer_info_array['@attributes']['Text'].','.'NA'.','.str_replace("Phone:","",$customer_info_array['ContactPerson'][0]['@attributes']['ContactType']);
          }

          $channel_name        = "Travelguru";
            $no_of_adult         = $hotelReservation_data["ResGlobalInfo"]["GuestCounts"]["@attributes"]["AdultCount"];
            $no_of_child         = $hotelReservation_data["ResGlobalInfo"]["GuestCounts"]["@attributes"]["ChildCount"];
            $gst                 = $hotelReservation_data['RoomStays']['RoomStay']['TPA_Extensions']['GST'];
          $amount  = 0;
          foreach ($hotelReservation_data['RoomStays']['RoomStay']['Total'] as $k => $v)
          {
            if(isset($v["AmountAfterTax"]))
            {
              $amount              =  number_format((float)$v['AmountAfterTax'],2,'.','');
            }
          }

        $currency            =  $hotelReservation_data['RoomStays']['RoomStay']["RoomRates"]["RoomRate"]["Rates"]["Rate"]["Base"]["@attributes"]["CurrencyCode"];
        //Payment status
        $payment_status      = "NA"; //$hotelReservation_data['RoomStays']['RoomStay']['TPA_Extensions']['TotalPrepayAmount'] > 0 ? 'Paid('.$hotelReservation_data['RoomStays']['RoomStay']['TPA_Extensions']['TotalPrepayAmount'].')':'Pay at hotel';

        $ota_hotel_details   = CmOtaDetails::select('*')
                                ->where('ota_hotel_code' ,'=', $hotel_Code)
                                ->first();
        //Call the booking data insert and ota push bucket data saving
        if($booking_status == 'Reserved' || $booking_status == 'Waitlisted' ){
          $booking_status = 'Commit';
        }
        $bookingDetails = array('UniqueID'=>$uniqueID,'customerDetail'=>$customerDetail,'booking_status'=>$booking_status,'rooms_qty'=>$rooms_qty,'room_type'=>$room_type,'checkin_at'=>$checkin_at,'checkout_at'=>$checkout_at,'booking_date'=>$booking_date,'amount'=>$amount,'payment_status'=>$payment_status,'rate_code'=>$rate_code,'rlt'=>$result,'currency'=>$currency,'channel_name'=>$channel_name,'tax_amount'=>$gst,'no_of_adult'=>$no_of_adult,'no_of_child'=>$no_of_child,'inclusion'=>'NA');
        $bookinginfo  = $this->bookingData->cmOtaBooking($bookingDetails,$ota_hotel_details);//this function call used for insert/update booking in database
        $db_status  = $bookinginfo['db_status'];
        $ota_booking_tabel_id =$bookinginfo['ota_booking_tabel_id'];
        $push_by = "Travelguru";
        if($db_status){
          $this->instantbucket->bucketEngineUpdate($booking_status,$push_by,$ota_hotel_details,$ota_booking_tabel_id);//this function is used for booking bucket data updation
        }

      }
      else
      {
        //Intialisation of rooms_qty_array
        /*$rooms_qty_array=array();
        $room_type_array=array();
        $room_type_array=array();
        foreach ($roomRates as $key => $value)
        {
          $rooms_qty_array  = $value['@attributes']['NumberOfUnits'];
          $room_type_array  = $value['@attributes']['RoomID'];
          $rate_code_array  = $value['@attributes']['RatePlanID'];
        }
        $rooms_qty           = implode(',', $rooms_qty_array);
        $room_type           = implode(',', $room_type_array);
        $rate_code           = implode(',', $rate_code_array); */

        $rooms_qty  = $roomRates[0]['@attributes']['NumberOfUnits'];
        $room_type  = $roomRates[0]['@attributes']['RoomID'];
        $rate_code  = $roomRates[0]['@attributes']['RatePlanID'];


        $checkin_at          = date('Y-m-d', strtotime($hotelReservation_data['ResGlobalInfo']['TimeSpan']['@attributes']['Start']));
        $checkout_at         = date('Y-m-d', strtotime($hotelReservation_data['ResGlobalInfo']['TimeSpan']['@attributes']['End']));
        $booking_date_array  = explode('T', $hotelReservation_data['@attributes']['CreateDateTime']);
        $booking_date        = $booking_date_array[0];
        $customer_info_array = $hotelReservation_data['ResGlobalInfo']['Profiles']['ProfileInfo']['Profile']['Customer'];

        if(isset($customer_info_array['ContactPerson'][7]['@attributes']['ContactType']))
        {
          $customerDetail      = $customer_info_array['@attributes']['Text'].','.str_replace("Email:","",$customer_info_array['ContactPerson'][7]['@attributes']['ContactType']).','.str_replace("Phone:","",$customer_info_array['ContactPerson'][0]['@attributes']['ContactType']);
        }
        else{
          $customerDetail      = $customer_info_array['@attributes']['Text'].','.'NA'.','.str_replace("Phone:","",$customer_info_array['ContactPerson'][0]['@attributes']['ContactType']);
        }

        $channel_name        = "Travelguru";

        $no_of_adult         = $hotelReservation_data["ResGlobalInfo"]["GuestCounts"]["@attributes"]["AdultCount"];
        $no_of_child         = $hotelReservation_data["ResGlobalInfo"]["GuestCounts"]["@attributes"]["ChildCount"];
        $gst                 = $hotelReservation_data['RoomStays']['RoomStay']['TPA_Extensions']['GST'];
        $amount = 0;
        foreach ($hotelReservation_data['RoomStays']['RoomStay']['Total'] as $k => $v)
        {
          if(isset($v["AmountAfterTax"])){
          $amount              =  number_format((float)$v['AmountAfterTax'],2,'.','');
          }
        }
        $currency="";
        foreach($hotelReservation_data['RoomStays']['RoomStay']["RoomRates"]["RoomRate"] as $cur)
        {
          if(isset($cur["Rates"]))
          {
            $currency=$cur["Rates"]["Rate"]["Base"]["@attributes"]["CurrencyCode"];
          }
          else{
            $currency=$hotelReservation_data['RoomStays']['RoomStay']["RoomRates"]["RoomRate"]["Rates"]["Rate"]["Base"]["@attributes"]["CurrencyCode"];
          }

        }
          //Payment status
        $payment_status      = "NA"; // $hotelReservation_data['RoomStays']['RoomStay']['TPA_Extensions']['TotalPrepayAmount'] > 0 ? 'Paid('.$hotelReservation_data['RoomStays']['RoomStay']['TPA_Extensions']['TotalPrepayAmount'].')':'Pay at hotel';
        $ota_hotel_details   = CmOtaDetails::select('*')
                              ->where('ota_hotel_code' ,'=', $hotel_Code)
                              ->first();

        //Call the booking data insert and ota push bucket data saving
        if($booking_status == 'Reserved' || $booking_status == 'Waitlisted' ){
          $booking_status = 'Commit';
        }

        $bookingDetails = array('UniqueID'=>$uniqueID,'customerDetail'=>$customerDetail,'booking_status'=>$booking_status,'rooms_qty'=>$rooms_qty,'room_type'=>$room_type,'checkin_at'=>$checkin_at,'checkout_at'=>$checkout_at,'booking_date'=>$booking_date,'amount'=>$amount,'payment_status'=>$payment_status,'rate_code'=>$rate_code,'rlt'=>$result,'currency'=>$currency,'channel_name'=>$channel_name,'tax_amount'=>$gst,'no_of_adult'=>$no_of_adult,'no_of_child'=>$no_of_child,'inclusion'=>'NA');
        $bookinginfo  = $this->bookingData->cmOtaBooking($bookingDetails,$ota_hotel_details);//this function call used for insert/update booking in database
        $db_status  = $bookinginfo['db_status'];
        $ota_booking_tabel_id =$bookinginfo['ota_booking_tabel_id'];
        $push_by = "Travelguru";
        if($db_status){
          // $this->instantbucket->bucketEngineUpdate($booking_status,$push_by,$ota_hotel_details,$ota_booking_tabel_id);//this function is used for booking bucket data updation
        }
      }
    }//End of else of cancel status
    }
 public function cancelBooking($hotelReservation_data,$hotel_Code){
  $uniqueID=$hotelReservation_data['ResGlobalInfo']['HotelReservationIDs']['HotelReservationID']['@attributes']['ResID_Value'];
  //Call the cancelation of travelguru
   $otaBookingUpdateModel   = CmOtaBooking::select('*')
   ->where('unique_id', '=' ,trim($uniqueID) )
   ->first();
   $ota_details_data   = CmOtaDetails::select('*')
   ->where('ota_hotel_code' ,'=', $hotel_Code)
   ->first();
   if($otaBookingUpdateModel){
   $room_type=$otaBookingUpdateModel->room_type;
   $checkin_at=$otaBookingUpdateModel->checkin_at;
   $checkout_at=$otaBookingUpdateModel->checkout_at;
   $push_by              = "Travelguru";
   $booking_status="Cancel";
   $bookingDetails = array('UniqueID'=>$uniqueID,'customerDetail'=>'NA','booking_status'=>$booking_status,'rooms_qty'=>0,'room_type'=>$room_type,'checkin_at'=>$checkin_at,'checkout_at'=>$checkout_at,'booking_date'=>'NA','amount'=>0,'payment_status'=>'NA','rate_code'=>'NA','rlt'=>'NA','currency'=>'INR','channel_name'=>'NA','tax_amount'=>0,'no_of_adult'=>0,'no_of_child'=>'NA','inclusion'=>'NA');
   $bookinginfo  = $this->bookingData->cmOtaBooking($bookingDetails,$ota_details_data );
   //this function call used for insert/update booking in database
   $db_status  = $bookinginfo['db_status'];
   if($db_status)
   {
     var_dump("hello");
   $this->instantbucket->bucketEngineUpdate($booking_status,$push_by,$ota_details_data,$bookinginfo['ota_booking_tabel_id']);//this function is used for booking bucket data updation
   }
  }else{
    echo "No cancellation found for your search criteria";
  }
 }
}
