<?php
namespace App\Http\Controllers\TTDCMMW;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Mail;
use Validator;
use DB;
use App\TTDCRoom;
use App\CmOtaBooking;
use App\CmOtaRoomTypeFetchSync;
use App\TtdcReservation;
use App\Http\Controllers\Controller;

class TTDCReservationController extends Controller{
    public function ttdcBookings($hotel_id,$type,$booking_data,$customer_data,$booking_status,$from_date,$to_date,$booking_channel){
        $change_from_date = date('d-m-Y',strtotime($from_date));
        $change_to_date = date('d-m-Y',strtotime($to_date));
        $from_date = str_replace('-','/',$change_from_date);
        $to_date = str_replace('-','/',$change_to_date);
        $ttdc_hotel_code=$this->getTtdcHotel($hotel_id);
        if($customer_data['email_id'] != 'NA'){
            $email = $customer_data['email_id'];
        }
        else{
            $email = '';
        }
        if($customer_data['mobile'] != 'NA'){
            $mobile = $customer_data['mobile'];
        }
        else{
            $mobile = '';
        }
        $customer_name=$customer_data['first_name'].' '.$customer_data['last_name'];
        $total_booking_amount = round($booking_data['total_booking_amount']); 
        $total_gst = round($booking_data['booking_tax_amount']);
        if($booking_channel == 'Goibibo' || $booking_channel == 'MakeMyTrip'){
            $total_amount = $total_booking_amount;
        }
        else{
            $total_amount = $total_booking_amount + $total_gst;
        }
        $pay_ref_id = rand();
        $ttdc_response_xml='<?xml version="1.0" encoding="utf-8"?><BookingjiniRequest Key="418367c9-d295-b842-b6a5-60539369811b" UserID="Bookingjini" PaymentReferenceID="'.$pay_ref_id.'"><GuestDetails Title="Mr/Mrs." Name="'.$customer_name.'" Address1="" Country="India" Pin="" EmailId="'.$email.'" MobileNo="'.$mobile.'" ChannelName="'.$booking_channel.'"/><Property ID="'.$ttdc_hotel_code.'" CheckInDate="'.$from_date.'" CheckInTime="10" CheckOutDate="'.$to_date.'" CheckOutTime="10" TotalPax="'.$booking_data["display_pax"].'" TotalAmount="'.$total_amount.'">';
                foreach($booking_data["room_stay"] as $room_details){
                    $room_type_id = $room_details["room_type_id"];
                    $get_room_code = TTDCRoom::select('ttdc_room_type_code')->
                                        where('hotel_id',$hotel_id)->where('room_type_id',$room_type_id)->first();
                    if(isset($get_room_code->ttdc_room_type_code)){
                        $ttdc_room_type_code = $get_room_code->ttdc_room_type_code;
                    }
                    else{
                        return true;
                    }
                    $total_adult_no  = $room_details["adults"];
                    $total_child_no  = $room_details["children"];
                    $no_of_nights = $room_details["no_of_nights"];
                    $total_adult_dlt = isset($room_details["total_adult"])?$room_details["total_adult"]:0;
                    $total_child_dlt = isset($room_details["total_child"])?$room_details["total_child"]:0;
                    $room_qty = isset($room_details['room_qty'])?$room_details['room_qty']:0;
                    $total_adult = 0;
                    $total_child = 0;
                    $ttdc_response_xml.= '<RoomType ID="'.$ttdc_room_type_code.'" NoOfRooms="'.$room_qty.'" NoOfAdults="'.$total_adult_dlt.'" NoOfChildren="'.$total_child_dlt.'" NoOfRoomNights ="'.$no_of_nights.'" >';
                    foreach($room_details["rates"] as $rates){
                        $room_amount = round($rates["amount"]);
                        $gst = round($rates["tax_amount"]);
                        if($booking_channel == 'Goibibo' || $booking_channel == 'MakeMyTrip'){
                            $room_amount = $room_amount - $gst;
                        }
                        else{
                            $room_amount = $room_amount;
                        }
                        $change_date = date('d-m-Y',strtotime($rates["from_date"]));
                        $check_in_date = str_replace('-','/',$change_date);
                        $ttdc_response_xml.='<Rates Date="'.$check_in_date.'" MealPlan="0" MealPlanID="0" RoomRate="'.$room_amount.'" Taxes="'.$gst.'" />';
                    }
                    $ttdc_response_xml.='</RoomType>';
                }
                if($booking_status == 'Commit'){
                    $ttdc_response_xml.='</Property><Action Action="N" Remarks="" BookingID="'.$booking_data['booking_id'].'"/></BookingjiniRequest>'; 
                }
                else if($booking_status == 'Modify'){
                    $ttdc_response_xml.='</Property><Action Action="M" Remarks="" BookingID="'.$booking_data['booking_id'].'"/></BookingjiniRequest>'; 
                }
                
        $ttdc=new TtdcReservation();
        $data['hotel_id']=$hotel_id;
        $data['ttdc_hotel_code']=$ttdc_hotel_code;
        $data['booking_id']=$booking_data['booking_id'];
        $data['ttdc_string']=$ttdc_response_xml;
        if($ttdc->fill($data)->save())
        {
            return $ttdc->id;
        }
        else
        {
            return false;
        }
    }
    public function ttdcModifyBooking($hotel_id,$type,$booking_data,$customer_data,$booking_status,$from_date,$to_date,$booking_channel,$ttdc_re_id){
        $change_from_date = date('d-m-Y',strtotime($from_date));
        $change_to_date = date('d-m-Y',strtotime($to_date));
        $from_date = str_replace('-','/',$change_from_date);
        $to_date = str_replace('-','/',$change_to_date);
        $ttdc_hotel_code=$this->getTtdcHotel($hotel_id);
        if($customer_data['email_id'] != 'NA'){
            $email = $customer_data['email_id'];
        }
        else{
            $email = '';
        }
        if($customer_data['mobile'] != 'NA'){
            $mobile = $customer_data['mobile'];
        }
        else{
            $mobile = '';
        }
        $customer_name=$customer_data['first_name'].' '.$customer_data['last_name'];
        $total_booking_amount = round($booking_data['total_booking_amount']); 
        $total_gst = round($booking_data['booking_tax_amount']);
        if($booking_channel == 'Goibibo' || $booking_channel == 'MakeMyTrip'){
            $total_amount = $total_booking_amount;
        }
        else{
            $total_amount = $total_booking_amount + $total_gst;
        }
        $pay_ref_id = rand();
        $ttdc_response_xml='<?xml version="1.0" encoding="utf-8"?><BookingjiniRequest Key="418367c9-d295-b842-b6a5-60539369811b" UserID="Bookingjini" PaymentReferenceID="'.$pay_ref_id.'"><GuestDetails Title="Mr/Mrs." Name="'.$customer_name.'" Address1="" Country="India" Pin="" EmailId="'.$email.'" MobileNo="'.$mobile.'" ChannelName="'.$booking_channel.'"/><Property ID="'.$ttdc_hotel_code.'" CheckInDate="'.$from_date.'" CheckInTime="10" CheckOutDate="'.$to_date.'" CheckOutTime="10" TotalPax="'.$booking_data["display_pax"].'" TotalAmount="'.$total_amount.'">';
                foreach($booking_data["room_stay"] as $room_details){
                    $room_type_id = $room_details["room_type_id"];
                    $get_room_code = TTDCRoom::select('ttdc_room_type_code')->
                                        where('hotel_id',$hotel_id)->where('room_type_id',$room_type_id)->first();
                    if(isset($get_room_code->ttdc_room_type_code)){
                        $ttdc_room_type_code = $get_room_code->ttdc_room_type_code;
                    }
                    else{
                        return true;
                    }
                    $total_adult_no  = $room_details["adults"];
                    $total_child_no  = $room_details["children"];
                    $no_of_nights = $room_details["no_of_nights"];
                    $total_adult_dlt = isset($room_details["total_adult"])?$room_details["total_adult"]:0;
                    $total_child_dlt = isset($room_details["total_child"])?$room_details["total_child"]:0;
                    $room_qty = isset($room_details['room_qty'])?$room_details['room_qty']:0;
                    $total_adult = 0;
                    $total_child = 0;
                    $ttdc_response_xml.= '<RoomType ID="'.$ttdc_room_type_code.'" NoOfRooms="'.$room_qty.'" NoOfAdults="'.$total_adult_dlt.'" NoOfChildren="'.$total_child_dlt.'" NoOfRoomNights ="'.$no_of_nights.'" >';
                    foreach($room_details["rates"] as $rates){
                        $room_amount = round($rates["amount"]);
                        $gst = round($rates["tax_amount"]);
                        if($booking_channel == 'Goibibo' || $booking_channel == 'MakeMyTrip'){
                            $room_amount = $room_amount - $gst;
                        }
                        else{
                            $room_amount = $room_amount;
                        }
                        $change_date = date('d-m-Y',strtotime($rates["from_date"]));
                        $check_in_date = str_replace('-','/',$change_date);
                        $ttdc_response_xml.='<Rates Date="'.$check_in_date.'" MealPlan="0" MealPlanID="0" RoomRate="'.$room_amount.'" Taxes="'.$gst.'" />';
                    }
                    $ttdc_response_xml.='</RoomType></Property><Action Action="M" Remarks="" BookingID="'.$ttdc_re_id.'"/></BookingjiniRequest>'; 
                
        $ttdc=new TtdcReservation();
        $ttdc_booking_update = DB::table('ttdc_reservation')->where('ttdc_re_id', $ttdc_re_id)->update(['ttdc_modify_booking_string' => $ttdc_response_xml]);
        if($ttdc_re_id)
        {
            return $ttdc_re_id;
        }
        else
        {
            return false;
        }
    }
    public function pushReservations($xml,$ttdc_id)
    {
        $resp=TtdcReservation::where('id',$ttdc_id)->select('ttdc_hotel_code','booking_id')->first();
        if(!$resp){
            return $resp;
        }
        $url="http://103.39.133.51/TTDCBookingAPI/Booking/api/NewRoomBooking?NewBookingXML=";
        $xml = rawurlencode($xml);
        $url = $url.$xml;
        $curl = curl_init();

        curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_POSTFIELDS => array(),
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => array(
            'Accept: application/xml',
            'Authorization: 02Iu6Bc/NpFO5vb5/g59vLgZGmMwqz7bQI1H1p8Hpc2wZoWrqsmNkZ6KCbk2Gw0fuFUleMlRgvYLQzIgqZVB6qrSMjcjbaaM7q2QccKmEbg='
        ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        dd($response,$url);
        // $ttdc_data = json_decode(json_encode(simplexml_load_string($result)), true);
    }
    public function ttdcModifyBooking($xml,$ttdc_id)
    {
        $resp=TtdcReservation::where('id',$ttdc_id)->select('ttdc_hotel_code','booking_id')->first();
        if(!$resp){
            return $resp;
        }
        $url="http://103.39.133.51/TTDCBookingAPI/Booking/api/NewRoomBooking?NewBookingXML=";
        $xml = rawurlencode($xml);
        $url = $url.$xml;
        $curl = curl_init();

        curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_POSTFIELDS => array(),
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => array(
            'Accept: application/xml',
            'Authorization: 02Iu6Bc/NpFO5vb5/g59vLgZGmMwqz7bQI1H1p8Hpc2wZoWrqsmNkZ6KCbk2Gw0fuFUleMlRgvYLQzIgqZVB6qrSMjcjbaaM7q2QccKmEbg='
        ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        dd($response,$url);
        // $ttdc_data = json_decode(json_encode(simplexml_load_string($result)), true);
    }
    public function ttdcCancelBooking($booking_id){

        $property_details = DB::table('ttdc_reservation')->where('booking_id',$booking_id)->first();
        $property_code = $property_details->ttdc_hotel_code;
        $tax = 0;
        $cm_ota_booking = CmOtaBooking::select('room_type')->where('unique_id',$booking_id)->first();
        $cm_room_type = $cm_ota_booking->room_type;
        $cm_room_type_sync = CmOtaRoomTypeFetchSync::select('room_type_id')->where('ota_room_type',$cm_room_type)->first();
        $room_type_id = $cm_room_type_sync->room_type_id;
        $room_type_details = DB::table('ttdc_room')->where(['hotel_id'=>$property_details->hotel_id,'ttdc_hotel_code'=>$property_code,'room_type_id'=>$room_type_id])->first();
        $room_type = $room_type_details->ttdc_room_type_code;

        $xml = '<?xml version="1.0" encoding="utf-8"?>
        <CancellationRequest Key="418367c9-d295-b842-b6a5-60539369811b" UserID="Bookingjini"
        BookingID="'.$booking_id.'" Message="cancel booking" PropertyCode="'.$property_code.'" TAX="'.$tax.'" RoomType="'.$room_type.' / >';

        $url="http://103.39.133.51/TTDCBookingAPI/Booking/api/CancelRoomBooking";
        $xml = rawurlencode($xml);
        $url = $url.$xml;
        $curl = curl_init();

        curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_POSTFIELDS => array(),
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => array(
            'Accept: application/xml',
            'Authorization: 02Iu6Bc/NpFO5vb5/g59vLgZGmMwqz7bQI1H1p8Hpc2wZoWrqsmNkZ6KCbk2Gw0fuFUleMlRgvYLQzIgqZVB6qrSMjcjbaaM7q2QccKmEbg='
        ),
        ));

        $response;exit; = curl_exec($curl);
        curl_close($curl);
        echo $response;exit;
    }
    public function getTtdcHotel($hotel_id)
    {
        $resp=TTDCRoom::where('hotel_id',$hotel_id)->select('ttdc_hotel_code')->first();
        if($resp)
        {
            return $resp->ttdc_hotel_code;
        }
        else
        {
            return false;
        }
    }
    public function getTtdcStatus($hotel_id)
    {
        $resp=DB::table('pms_account')->where('name','TTDC')->select('hotels')->first();
         if(strpos($resp->hotels, "$hotel_id") >= 0){
             return true;
         }else{
            return false;
         }
    }
    //To get the ttdc XML string
    public function getTtdcString($ttdc_id)
    {
        $resp=TtdcReservation::where('id',$ttdc_id)->select('ttdc_string')->first();
        if(isset($resp->ttdc_string))
        {
            return $resp->ttdc_string;
        }
        else
        {
            return false;
        }
    }
}