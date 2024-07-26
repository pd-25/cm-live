<?php
namespace App\Http\Controllers\otacontrollers\manualOtaBookingFetch;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Input;
use Validator;
use DB;
use App\CmOtaAllAutoPush;
use App\CmOtaDetails;
use App\Http\Controllers\Controller;
use App\Http\Controllers\otacontrollers\manualOtaBookingFetch\AgodaManualBookingController;
use App\Http\Controllers\otacontrollers\manualOtaBookingFetch\BookingDotComManualBookingController;
use App\Http\Controllers\otacontrollers\manualOtaBookingFetch\TravelguruManualBookingController;
use App\Http\Controllers\otacontrollers\CleartripController;
use App\Http\Controllers\otacontrollers\GoibiboController;
use App\Http\Controllers\otacontrollers\ViadotcomController;
use App\Http\Controllers\otacontrollers\expediaControllers\ExpediaController;

/**
* Master controller to fetch the bookings mannualy
* created by ranjit
* @08/02/19
*/
class ManualBookingMasterController extends Controller
{
    protected $bookingdotcomData,$travelguruData,$agodaData,$goibiboData,$cleartripData,$expediaData,$viaData;
    public function __construct(AgodaManualBookingController $agodaData,BookingDotComManualBookingController $bookingdotcomData,TravelguruManualBookingController $travelguruData,CleartripController $cleartripData,GoibiboController $goibiboData,ViadotcomController $viaData,ExpediaController $expediaData)
    {
      $this->bookingdotcomData=$bookingdotcomData;
      $this->travelguruData=$travelguruData;
      $this->agodaData=$agodaData;
      $this->goibiboData=$goibiboData;
      $this->cleartripData=$cleartripData;
      $this->expediaData=$expediaData;
      $this->viaData=$viaData;
    }
    //TO get the respones_xml data from cm_ota_all_auto_push table 
    public function getXmlData($booking_id){
        $get_response_xml = CmOtaAllAutoPush::select('respones_xml')
        ->where('respones_xml', 'like', '%' . $booking_id . '%')
        ->first();
        return  $get_response_xml = $get_response_xml?$get_response_xml->respones_xml:''; 
    }
    //Manually fetch the bookings from respective ota
    public function manualBookingFetch(int $hotel_id,$booking_id,string $ota_name,$booking_date,Request $request)
    {   
        
        switch ($ota_name) {
            case "Goibibo":
                                $booking_xml=$this->getXmlData($booking_id);
                                if(!$booking_xml){
                                    return response()->json(array("status"=>0,"message"=>"Data not available"));
                                }
                                $headers    =   array ('Content-Type: application/xml','Authorization:3CBAB7FD66D4E8FDA39F9398595C7');
                                $url = 'https://admin.bookingjini.com/v3/api/goibibo-reservations';
                                $ch = curl_init();
                                curl_setopt( $ch, CURLOPT_URL, $url );
                                curl_setopt( $ch, CURLOPT_POST, true );
                                curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
                                curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
                                curl_setopt( $ch, CURLOPT_POSTFIELDS, $booking_xml);
                                $booking_result = curl_exec($ch);
                                $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                                curl_close($ch);
                                return  $http_status==200 ? response()->json(array("status"=>1,"message"=>"Success")) :  response()->json(array("status"=>0,"message"=>"Error"));
                                 break;
            case "Booking.com":
                                $ota_details_datas            = CmOtaDetails::select('*')
                                                                ->where('ota_name' ,'=', 'Booking.com')
                                                                ->where('hotel_id','=',$hotel_id)
                                                                ->where('is_active' ,'=', 1)
                                                                ->first();
                                if(!$ota_details_datas)
                                {
                                    return response()->json(array('status'=>0,'message'=>'Hotel is not in sync with Booking.com'));
                                }
                                $hotel_code                    = $ota_details_datas->ota_hotel_code;
                                $resp=$this->bookingdotcomData->manualBookingActionIndex($hotel_code,$booking_id);
                                if(isset($resp['status'])){
                                    return   $resp['status']==1 ? response()->json(array("status"=>1,"message"=>"Success")) :  response()->json(array("status"=>0,"message"=>$resp['message']));
                                }
                                else{
                                     return response()->json(array("status"=>0,"message"=>$resp));
                                }
                                break;
            case "Travelguru":
                                $resp=$this->travelguruData->manualBookingActionIndex($hotel_id,$booking_id,$booking_date);
                                if(isset($resp['status'])){
                                    return   $resp['status']==1 ? response()->json(array("status"=>1,"message"=>"Success")) :  response()->json(array("status"=>0,"message"=>$resp['message']));
                                }
                                else{
                                     return response()->json(array("status"=>0,"message"=>$resp));
                                }
                                break;
            case "Agoda":
                                $resp=$this->agodaData->manualBookingActionIndex($hotel_id,$booking_id);
                                if(isset($resp['status'])){
                                    return   $resp['status']==1 ? response()->json(array("status"=>1,"message"=>"Success")) :  response()->json(array("status"=>0,"message"=>$resp['message']));
                                }
                                else{
                                     return response()->json(array("status"=>0,"message"=>$resp));
                                }
                                break;
            case "Expedia":
                                $booking_xml=$this->getXmlData($booking_id);
                                if(!$booking_xml){
                                    return response()->json(array("status"=>0,"message"=>"Data not available"));
                                }
                                $headers    =   array ('Content-Type: application/xml');
                                $url = 'https://admin.bookingjini.com/v3/api/expedia-reservations';
                                $ch = curl_init();
                                curl_setopt( $ch, CURLOPT_URL, $url );
                                curl_setopt( $ch, CURLOPT_POST, true );
                                curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
                                curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
                                curl_setopt( $ch, CURLOPT_POSTFIELDS, $booking_xml);
                                $booking_result = curl_exec($ch);
                                $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                                curl_close($ch);
                                $xml							= simplexml_load_string($booking_result);
                                $parser 						= $xml->children('soap-env', true);
                                $data               =  $parser->Body->children()
                                                        ->OTA_HotelResNotifRS->children();
                                return  isset($data->Success) ? response()->json(array("status"=>1,"message"=>"Success")) :  response()->json(array("status"=>0,"message"=>"Error"));
                                break;
            case "Via.com":
                                $booking_xml=$this->getXmlData($booking_id);
                                if(!$booking_xml){
                                    return response()->json(array("status"=>0,"message"=>"Data not available"));
                                }
                                $headers    =   array ('Content-Type: application/json');
                                $url = 'https://admin.bookingjini.com/v3/api/viadotcom-reservations';
                                $ch = curl_init();
                                curl_setopt( $ch, CURLOPT_URL, $url );
                                curl_setopt( $ch, CURLOPT_POST, true );
                                curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
                                curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
                                curl_setopt( $ch, CURLOPT_POSTFIELDS, $booking_xml);
                                $booking_result = curl_exec($ch);
                                $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                                curl_close($ch);
                                $result=json_decode($booking_result,true);
                                return  $result['status']=="Confirmed" ? response()->json(array("status"=>1,"message"=>"Success")) :  response()->json(array("status"=>0,"message"=>$result['description']));
                                break;
            case "EaseMyTrip":
                                    $booking_xml=$this->getXmlData($booking_id);
                                    if(!$booking_xml){
                                        return response()->json(array("status"=>0,"message"=>"Data not available"));
                                    }
                                    $headers    =   array ('Content-Type: application/json');
                                    $url = 'https://admin.bookingjini.com/v3/api/easemytrip-reservations';
                                    $ch = curl_init();
                                    curl_setopt( $ch, CURLOPT_URL, $url );
                                    curl_setopt( $ch, CURLOPT_POST, true );
                                    curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
                                    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
                                    curl_setopt( $ch, CURLOPT_POSTFIELDS, $booking_xml);
                                    $booking_result = curl_exec($ch);
                                    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                                    curl_close($ch);
                                    return  $http_status==200 ? response()->json(array("status"=>1,"message"=>"Success")) :  response()->json(array("status"=>0,"message"=>"Error"));
                                    break;
                case "Paytm":
                                    $booking_xml=$this->getXmlData($booking_id);
                                    if(!$booking_xml){
                                        return response()->json(array("status"=>0,"message"=>"Data not available"));
                                    }
                                    $headers    =   array ('Content-Type: application/json');
                                    $url = 'https://admin.bookingjini.com/v3/api/paytm-reservations';
                                    $ch = curl_init();
                                    curl_setopt( $ch, CURLOPT_URL, $url );
                                    curl_setopt( $ch, CURLOPT_POST, true );
                                    curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
                                    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
                                    curl_setopt( $ch, CURLOPT_POSTFIELDS, $booking_xml);
                                    $booking_result = curl_exec($ch);
                                    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                                    curl_close($ch);
                                    return  $http_status==200 ? response()->json(array("status"=>1,"message"=>"Success")) :  response()->json(array("status"=>0,"message"=>"Error"));
                                    break;
    
                case "HappyEasyGo":
                                    $booking_xml=$this->getXmlData($booking_id);
                                    if(!$booking_xml){
                                        return response()->json(array("status"=>0,"message"=>"Data not available"));
                                    }
                                    $headers    =   array ('Content-Type: application/json');
                                    $url = 'http://cm.bookingjini.com/v3/api/heg-reservations';
                                    $ch = curl_init();
                                    curl_setopt( $ch, CURLOPT_URL, $url );
                                    curl_setopt( $ch, CURLOPT_POST, true );
                                    curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
                                    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
                                    curl_setopt( $ch, CURLOPT_POSTFIELDS, $booking_xml);
                                    $booking_result = curl_exec($ch);
                                    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                                    curl_close($ch);
                                    return  $http_status==200 ? response()->json(array("status"=>1,"message"=>"Success")) :  response()->json(array("status"=>0,"message"=>"Error"));
                                    break;
            default:
                return response()->json(array("status"=>0,"message"=>"Provide the OTA")) ;
        }
    }
}
 