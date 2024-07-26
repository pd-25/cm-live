<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Validator;
use DB;
use App\CmOtaRoomTypeFetch;//class name from model
use App\CmOtaRateTypeFetch;//class name from model
use App\CmOtaDetails;
use App\CmOtaAllAutoPush;
use App\LogTable;
/**
* Booking.com Controller implements bookings for Booking.com model.
*modified by ranjit
*@24/01/2019
*/
class CleartripHyperGuestController extends Controller
{
    public function removeNamespaceFromXML( $xml )
    {
        // Because I know all of the the namespaces that will possibly appear in
        // in the XML string I can just hard code them and check for
        // them to remove them
        $toRemove = ['rap', 'turss', 'crim', 'cred', 'j', 'rap-code', 'evic'];
        // This is part of a regex I will use to remove the namespace declaration from string
        $nameSpaceDefRegEx = '(\S+)=["\']?((?:.(?!["\']?\s+(?:\S+)=|[>"\']))+.)["\']?';

        // Cycle through each namespace and remove it from the XML string
       foreach( $toRemove as $remove ) {
            // First remove the namespace from the opening of the tag
            $xml = str_replace('<' . $remove . ':', '<', $xml);
            // Now remove the namespace from the closing of the tag
            $xml = str_replace('</' . $remove . ':', '</', $xml);
            // This XML uses the name space with CommentText, so remove that too
            $xml = str_replace($remove . ':commentText', 'commentText', $xml);
            // Complete the pattern for RegEx to remove this namespace declaration
            $pattern = "/xmlns:{$remove}{$nameSpaceDefRegEx}/";
            // Remove the actual namespace declaration using the Pattern
            $xml = preg_replace($pattern, '', $xml, 1);
        }

        // Return sanitized and cleaned up XML with no namespaces
        return $xml;
    }
    public function actionIndex(Request $request)
    {
      
      $logpath = storage_path("logs/booking.com.log".date("Y-m-d"));
      $logfile = fopen($logpath, "a+");
      fwrite($logfile,"Processing starts at: ".date("Y-m-d H:i:s")."\n");
      fclose($logfile);

      $OtaAllAutoPushModel          = new CmOtaAllAutoPush();
      $ota_details_model            = new CmOtaDetails();
      $otalog                       = new LogTable();
      $res_count                    = 0;
      $postdata = trim($request->getcontent());
      $response = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $postdata);
      $array = json_decode(json_encode(simplexml_load_string($this->removeNamespaceFromXML($response))), true); 
      $soap_body = $array['soapBody']['OTA_HotelResNotifRQ'];
      //echo "<pre>";print_r($soap_body);exit;
      if(isset($soap_body['HotelReservations'])){
        $reservation_datas  = $soap_body['HotelReservations'];
         $OtaAllAutoPushModel->respones_xml = trim($response);
          $OtaAllAutoPushModel->save();
          $isMultidimensional_reservation_datas = $this->isMultidimensionalArray($reservation_datas);
            if($isMultidimensional_reservation_datas)
            {
                foreach ($reservation_datas as $reservation_data)
                {
                   //echo "<pre>";print_r($reservation_data);
                  if($soap_body['@attributes']['ResStatus'] == 'Cancelled'){
                        $modify_status = 'Cancelled';
                       //$this->cancelBooking($reservation_data,$modify_status);
                  }else if($soap_body['@attributes']['ResStatus'] == 'Modified'){
                        $modify_status = $soap_body['@attributes']['ResStatus'];
                        // if($this->cancelBooking($reservation_data,$modify_status)){
                        //     $this->modifyBookingDetails($reservation_data,$result);
                        // }
                  }else{
                      $this->saveBookingDetails($reservation_data,$soap_body);
                  }
                  $res_count++;
                }
            }else{
                if($soap_body['@attributes']['ResStatus'] == 'Cancelled'){
                    $modify_status = 'Cancelled';
                    return $this->cancelBooking($reservation_datas,$modify_status);
                }else if($soap_body['@attributes']['ResStatus'] == 'Modified'){
                    $modify_status = $soap_body['@attributes']['ResStatus'];
                    if($this->cancelBooking($reservation_datas,$modify_status)){
                        $this->saveBookingDetails($reservation_datas,$result);
                    }
                }else{
                    return $this->saveBookingDetails($reservation_datas,$result);
                }
            }
      }else{
        echo "No Reservation <br>";
      }
        $logfile = fopen($logpath, "a+");
        fwrite($logfile,"Reservations processed: ".$res_count."\n");
        fwrite($logfile,"Processing ends at: ".date("Y-m-d H:i:s")."\n");
        fclose($logfile);

    }
    public function saveBookingDetails($reservation_data,$result)
    {
        $channel_name   = 'ClearTrip';
        //To get the hotel id from OTA hotel code
         if(isset($reservation_data['RoomStays']['RoomStay']['RatePlans'])){
            $properties = $reservation_data['RoomStays']['RoomStay']['BasicPropertyInfo'];
            $otaHotelCode       = 665420;//$properties['@attributes']['HotelCode'];
            $ota_details_model  = new CmOtaDetails();
            $ota_hotel_details  = $ota_details_model
            ->where('ota_hotel_code', '=' ,$otaHotelCode)
            ->where('is_status',1)
            ->first();
            if(isset($ota_hotel_details->hotel_id) && !empty($ota_hotel_details->hotel_id))
            {
                $booking_status = $result['@attributes']['ResStatus'];
                $UniqueID       = $reservation_data['UniqueID']['@attributes']['ID'];
                $booking_date   = $reservation_data['@attributes']['CreateDateTime'];
                $res_guest = $reservation_data['ResGuests']['ResGuest']['Profiles']['ProfileInfo']['Profile']['Customer'];
               
                if(isset($res_guest['PersonName']['GivenName']) && isset($res_guest['PersonName']['Surname'])){
                  $first_name = $res_guest['PersonName']['GivenName'];
                  $last_name = $res_guest['PersonName']['Surname'];
                }elseif(!isset($res_guest['PersonName']['GivenName']) && isset($res_guest['PersonName']['Surname'])){
                  $first_name = '';
                  $last_name = $res_guest['PersonName']['Surname'];
                }elseif (isset($res_guest['PersonName']['Surname']) && !isset($res_guest['PersonName']['Surname'])) {
                  $last_name = $res_guest['PersonName']['Surname'];
                  $last_name = '';
                }
                $customerDetail = $first_name." ".$last_name.',';
                $customerDetail = $res_guest['Email'];
                $customerDetail = $res_guest['Telephone']['@attributes']['PhoneNumber'];

                $amount         = $reservation_data['ResGlobalInfo']['Total']['@attributes']['AmountBeforeTax'];
                $currency       = $reservation_data['ResGlobalInfo']['Total']['@attributes']['CurrencyCode'];
                $payment_status = 'Pay at hotel';

                /*-----------Fetch Rate Plan------------------*/
                 //echo "<pre>";print_r($reservation_data);exit;
                if(isset($reservation_data['RoomStays']['RoomStay'])){
                  $rate_code = $reservation_data['RoomStays']['RoomStay']['RatePlans']['RatePlan']['@attributes']['RatePlanCode'];
                  $room_type   = $reservation_data['RoomStays']['RoomStay']['RoomRates']['RoomRate']['@attributes']['RoomTypeCode'];
                  $checkin_at  = $reservation_data['RoomStays']['RoomStay']['TimeSpan']['@attributes']['Start'];
                  $checkout_at = $reservation_data['RoomStays']['RoomStay']['TimeSpan']['@attributes']['End'];
                  $no_of_adult = $reservation_data['RoomStays']['RoomStay']["GuestCounts"]['@attributes']['Count'];
                  $no_of_child = 0;
                  $rooms_qty   = $reservation_data['RoomStays']['RoomStay']['RoomRates']['RoomRate']['@attributes']['NumberOfUnits'];
                  $extra_amount = '';
                  $amount = $reservation_data['ResGlobalInfo']['Total']['Taxes']['Tax']['@attributes']['Amount'];
                }else{
                  echo "<pre>";print_r($reservation_data['RoomStays']);exit;
                }
                $amount+=$extra_amount;
                //Checking for the status
                if($booking_status == "new"){
                    $booking_status =   'Commit';
                }
                else if($booking_status == "modified"){
                    $booking_status =   'Modify';
                }
                else if($booking_status == "cancelled"){
                    $booking_status =   'Cancel';
                }
                exit;
                $push_by='ClearTrip';
                $bookingDetails = array('UniqueID'=>$UniqueID,'customerDetail'=>$customerDetail,'booking_status'=>$booking_status,'rooms_qty'=>$rooms_qty,'room_type'=>$room_type,'checkin_at'=>$checkin_at,'checkout_at'=>$checkout_at,'booking_date'=>$booking_date,'amount'=>$amount,'payment_status'=>$payment_status,'rate_code'=>$rate_code,'rlt'=>$result,'currency'=>$currency,'channel_name'=>$channel_name,'tax_amount'=>$tax_amount,'no_of_adult'=>$no_of_adult,'no_of_child'=>$no_of_child,'inclusion'=>'NA',
                'special_information'=>$special_info,
                'cancel_policy'=>$cancel_policy);
                $bookinginfo  = $this->bookingData->cmOtaBooking($bookingDetails,$ota_hotel_details);//this function call used for insert/update booking in database
                $db_status  = $bookinginfo['db_status'];
                $ota_booking_tabel_id =$bookinginfo['ota_booking_tabel_id'];
                if($db_status){
                    $this->instantbucket->bucketEngineUpdate($booking_status,$push_by,$ota_hotel_details,$ota_booking_tabel_id);//this function is used for booking bucket data updation
                }
            }else{
              echo  "This hotel is not exits in Bookingjini Database! Thank you for contat with us.";
            } // else for $ota_hotel_details->hotel_id not exit
          }else{
            foreach ($reservation_data['RoomStays']['RoomStay'] as $singleroom_type){
              $properties = $singleroom_type['BasicPropertyInfo'];
              $otaHotelCode       = 665420;//$properties['@attributes']['HotelCode'];
              $ota_details_model  = new CmOtaDetails();
              $ota_hotel_details  = $ota_details_model
              ->where('ota_hotel_code', '=' ,$otaHotelCode)
              ->where('is_status',1)
              ->first();
              // echo "<pre>";print_r($singleroom_type);exit;
                if(isset($ota_hotel_details->hotel_id) && !empty($ota_hotel_details->hotel_id))
                {
                    $booking_status = $result['@attributes']['ResStatus'];
                    $UniqueID       = $reservation_data['UniqueID']['@attributes']['ID'];
                    $booking_date   = $reservation_data['@attributes']['CreateDateTime'];
                    $res_guest = $reservation_data['ResGuests']['ResGuest']['Profiles']['ProfileInfo']['Profile']['Customer'];
                    //echo "<pre>";print_r($res_guest);exit;
                    if(isset($res_guest['PersonName']['GivenName']) && isset($res_guest['PersonName']['Surname'])){
                    $first_name = $res_guest['PersonName']['GivenName'];
                    $last_name = $res_guest['PersonName']['Surname'];
                    }elseif(!isset($res_guest['PersonName']['GivenName']) && isset($res_guest['PersonName']['Surname'])){
                    $first_name = '';
                    $last_name = $res_guest['PersonName']['Surname'];
                    }elseif (isset($res_guest['PersonName']['Surname']) && !isset($res_guest['PersonName']['Surname'])) {
                    $last_name = $res_guest['PersonName']['Surname'];
                    $last_name = '';
                    }
                    $customerDetail = $first_name." ".$last_name.',';
                    $customerDetail = $res_guest['Email'];
                    $customerDetail = $res_guest['Telephone']['@attributes']['PhoneNumber'];

                    $amount         = $reservation_data['ResGlobalInfo']['Total']['@attributes']['AmountBeforeTax'];
                    $currency       = $reservation_data['ResGlobalInfo']['Total']['@attributes']['CurrencyCode'];
                    $payment_status = 'Pay at hotel';

                    /*-----------Fetch Rate Plan------------------*/
                    //echo "<pre>";print_r($reservation_data);exit;
                    if(isset($singleroom_type)){
                      $rate_code = $singleroom_type['RatePlans']['RatePlan']['@attributes']['RatePlanCode'];
                      $room_type   = $singleroom_type['RoomRates']['RoomRate']['@attributes']['RoomTypeCode'];
                      $checkin_at  = $singleroom_type['TimeSpan']['@attributes']['Start'];
                      $checkout_at = $singleroom_type['TimeSpan']['@attributes']['End'];
                      $no_of_adult = $singleroom_type["GuestCounts"]['GuestCount']['@attributes']['Count'];
                      $no_of_child = 0;
                      $rooms_qty   = $singleroom_type['RoomRates']['RoomRate']['@attributes']['NumberOfUnits'];
                      $amount = $reservation_data['ResGlobalInfo']['Total']['Taxes']['Tax']['@attributes']['Amount'];
                    }
                    // else{
                    // echo "<pre>"."else";print_r($reservation_data['RoomStays']);exit;
                    // }
                    //Checking for the status
                    if($booking_status == "new"){
                        $booking_status =   'Commit';
                    }
                    else if($booking_status == "modified"){
                        $booking_status =   'Modify';
                    }
                    else if($booking_status == "cancelled"){
                        $booking_status =   'Cancel';
                    }
                    //exit;
                    $push_by='ClearTrip';
                    $bookingDetails = array('UniqueID'=>$UniqueID,'customerDetail'=>$customerDetail,'booking_status'=>$booking_status,'rooms_qty'=>$rooms_qty,'room_type'=>$room_type,'checkin_at'=>$checkin_at,'checkout_at'=>$checkout_at,'booking_date'=>$booking_date,'amount'=>$amount,'payment_status'=>$payment_status,'rate_code'=>$rate_code,'rlt'=>$result,'currency'=>$currency,'channel_name'=>$channel_name,'tax_amount'=>$tax_amount,'no_of_adult'=>$no_of_adult,'no_of_child'=>$no_of_child,'inclusion'=>'NA',
                    'special_information'=>$special_info,
                    'cancel_policy'=>$cancel_policy);
                    $bookinginfo  = $this->bookingData->cmOtaBooking($bookingDetails,$ota_hotel_details);//this function call used for insert/update booking in database
                    $db_status  = $bookinginfo['db_status'];
                    $ota_booking_tabel_id =$bookinginfo['ota_booking_tabel_id'];
                    if($db_status){
                        $this->instantbucket->bucketEngineUpdate($booking_status,$push_by,$ota_hotel_details,$ota_booking_tabel_id);//this function is used for booking bucket data updation
                    }
                }else{
                  echo  "This hotel is not exits in Bookingjini Database! Thank you for contat with us.";
                } // else for $ota_hotel_details->hotel_id not exit
            }
          }exit;
        //Check if hotel is exist with us
        //echo "<pre>";print_r($result);exit;ResGlobalInfo
          
    }
    public function isMultidimensionalArray($array)
    {
        foreach($array as $v)
        {

            if(is_array($v))
            {
               return true;
            }
            else
            {
                return false;                
            }
        }
    }
    public function cancelBooking($reservation_data,$modify_status)
    {
        $otaBookingModel = new CmOtaBooking();
        $ota_details_model  = new CmOtaDetails();
        $properties = $reservation_data['RoomStays']['RoomStay']['BasicPropertyInfo'];
        $otaHotelCode       = 665420;//$properties['@attributes']['HotelCode'];
        $ota_hotel_details  = $ota_details_model
        ->where('ota_hotel_code', '=' ,$otaHotelCode)
        ->where('is_status',1)
        ->first();
        $UniqueID       = $reservation_data['id'];
        if($ota_hotel_details){
            $otaBookingUpdateModel = $otaBookingModel
            ->where('unique_id' ,'=', trim($UniqueID) )
            ->first();
            /*----------------- Fetch booking values -------------------*/
            $room_type      = $otaBookingUpdateModel->room_type;
            $rooms_qty      = $otaBookingUpdateModel->rooms_qty;
            $rate_code      = $otaBookingUpdateModel->rate_code;
            $amount         = $otaBookingUpdateModel->amount;
            $checkin_at     = $otaBookingUpdateModel->checkin_at ;
            $checkout_at    = $otaBookingUpdateModel->checkout_at;
            $booking_date   = $otaBookingUpdateModel->booking_date;
            $customerDetail = $otaBookingUpdateModel->customerDetail;
            $booking_status ='Cancel';

            if($modify_status == 'modified'){
                $mod_status = 1;
            }else{
                $mod_status = 0;
            }
            /*----------------- Fetch booking values -------------------*/
            $push_by='ClearTrip';
            $bookingDetails = array('UniqueID'=>$UniqueID,'customerDetail'=>$customerDetail,'booking_status'=>'Cancel','rooms_qty'=>$rooms_qty,'room_type'=>$room_type,'checkin_at'=>$checkin_at,'checkout_at'=>$checkout_at,'booking_date'=>$booking_date,'amount'=>$amount,'payment_status'=>'pay at hotel','rate_code'=>$rate_code,'rlt'=>'NA','currency'=>'INR','channel_name'=>'Booking.com','tax_amount'=>0,'no_of_adult'=>0,'no_of_child'=>0,'inclusion'=>'NA','modify_status'=>$mod_status);
            $bookinginfo  = $this->bookingData->cmOtaBooking($bookingDetails,$ota_hotel_details);//this function call used for insert/update booking in database
            $db_status  = $bookinginfo['db_status'];
            $ota_booking_tabel_id =$bookinginfo['ota_booking_tabel_id'];
            if($db_status){
                $this->instantbucket->bucketEngineUpdate($booking_status,$push_by,$ota_hotel_details,$ota_booking_tabel_id);//this function is used for booking bucket data updation
                return array("status"=>1,"message"=>"Success");
            }else{
                return array("status"=>0,"message"=>"Error occured during the DB saving");
            }
        }else{
            return "This hotel is not exits in Bookingjini Database! Thank you for contat with us.";
        } 
    }
} // class closed.