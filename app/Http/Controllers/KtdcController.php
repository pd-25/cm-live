<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Validator;
use DB;
use Ixudra\Curl\Facades\Curl;
use App\KtdcReservation;
use App\KtdcRoom;
use App\MasterRatePlan;
class KtdcController extends Controller
{

    /**
     * This ktdc controller is used to push the confirm,cancel and modify booking to ktdc
     * @auther ranjit date: 31/03/2021
     */
    
    public function actionResponse(Request $request)
    {
        $postdata=$request->all();
        $push_array_data    = json_decode(json_encode(simplexml_load_string($postdata)), true);
        $api_key        = "";
        $version        = $push_array_data['@attributes']['Version'];
        $timestamp      = $push_array_data['@attributes']['TimeStamp'];
        $token          = $push_array_data['@attributes']['EchoToken'];
        if(isset($push_array_data['Success']))
        {
           $booking_id=$push_array_data['NotifDetails']['HotelNotifReport']['HotelReservations']['HotelReservation']['UniqueID']['@attributes']['ID'];

           $return_array = '<OTA_HotelResNotifRS xmlns="http://www.opentravel.org/OTA/2003/05" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
						<Success>'.$booking_id.'</Success> </OTA_HotelResNotifRS>';
        }
        else
        {
        	$return_array = '<OTA_HotelResNotifRS xmlns="http://www.opentravel.org/OTA/2003/05" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
						<Success></Success> </OTA_HotelResNotifRS>';
        }

        return response()->json($return_array,200);
    }
    public function getStatus(Request $request){
        $this->getktdcStatus(47);
    }
    //Get ktdc status (Exist or not)
    public function getKtdcStatus($hotel_id)
    {
        $resp=DB::table('pms_account')->where('name','KTDC')->select('hotels')->first();
         if(strpos($resp->hotels, "$hotel_id") >= 0){
             return true;
         }else{
            return false;
         }
    }
    //To get the ktdc XML string
    public function getKtdcString($ktdc_id)
    {
        $resp=KtdcReservation::where('id',$ktdc_id)->select('ktdc_string')->first();
        if($resp->ktdc_string)
        {
            return $resp->ktdc_string;
        }
        else
        {
            return false;
        }
    }
     //Get ktdc status (Exist or not)
     public function getKtdcHotel($hotel_id)
     {
         $resp=KtdcRoom::where('hotel_id',$hotel_id)->select('ktdc_hotel_code')->first();
         if($resp)
         {
             return $resp->ktdc_hotel_code;
         }
         else
         {
             return false;
         }
     }
    //Preparing the ktdc XML string and save this to ktdc reservation table
    public function ktdcBookings($hotel_id,$type,$booking_data,$customer_data,$booking_status,$from_date,$to_date,$booking_channel)
    {
        $change_from_date = date('d-m-Y',strtotime($from_date));
        $change_to_date = date('d-m-Y',strtotime($to_date));
        $from_date = str_replace('-','/',$change_from_date);
        $to_date = str_replace('-','/',$change_to_date);
        $ktdc_hotel_code=$this->getKtdcHotel($hotel_id);
        $agent_code_details = DB::table('ktdc_agent_code')->where('ota_name',$booking_channel)->first();
        if($agent_code_details){
            $agent_code = $agent_code_details->agent_code;
        }
        else{
            $agent_code = '';
        }
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
        $total_amount = $total_booking_amount + $total_gst;
        $ktdc_response_xml='<?xml version="1.0" encoding="utf-8"?>
        <SoftBookRequest Key="418367c9-d295-b842-b6a5-60539369811b" UserID="Bookingjini">
            <GuestDetails Title="Mr." Name="'.$customer_name.'" Address1="" Address2=""
            Address3="" Country="India" Pin="" EmailId="'.$email.'"
            LandPhoneNo="" MobileNo="'.$mobile.'" AgentCode="'.$agent_code.'" Instructions=""
            AllInclusiveRates="Yes"/>
            <Property ID="'.$ktdc_hotel_code.'" CheckInDate="'.$from_date.'" CheckInTime="" CheckOutDate="'.$to_date.'"
            CheckOutTime="" PlanId="" TotalPax="'.$booking_data["display_pax"].'" Female="0" Children="0" Infants="0"
            Foreigner="0" TotalAmount="'.$total_amount.'">';
                foreach($booking_data["room_stay"] as $room_details){
                    $room_type_id = $room_details["room_type_id"];
                    $get_room_code = KtdcRoom::select('ktdc_room_type_code')->
                                        where('hotel_id',$hotel_id)->where('room_type_id',$room_type_id)->first();
                    $ktdc_room_type_code = $get_room_code->ktdc_room_type_code;
                    $rate_plan_id = $room_details["rate_plan_id"];
                    $get_rate_plan = MasterRatePlan::select('plan_type')->where('rate_plan_id',$rate_plan_id)->first();
                    $ktdc_plan_type = $get_rate_plan->plan_type;
                    $total_adult  = $room_details["adults"];
                    $no_of_nights = $room_details["no_of_nights"];
                    if($total_adult == 1){
                        $single = $total_adult;
                        $ktdc_response_xml.= '<RoomType ID="'.$ktdc_room_type_code.'" Single="'.$single.'" Double="0" Twin ="0" Adult="0" Child1="0" Child2="0" Infant="0"
                        NoOfRoomNights ="'.$no_of_nights.'" >';
                    }
                    else if($total_adult >1){
                        $get_per = $total_adult%2;
                        $adult = 0;
                        $double = 0;
                        if($get_per > 0){
                            $adult = $get_per;
                        }
                        else{
                            $double = $total_adult/2;
                        }
                        $ktdc_response_xml.= '<RoomType ID="'.$ktdc_room_type_code.'" Single="0" Double="'.$double.'" Twin ="0" Adult="'.$adult.'" Child1="0" Child2="0" Infant="0"
                        NoOfRoomNights ="'.$no_of_nights.'" >';
                    }
                    foreach($room_details["rates"] as $rates){
                        $room_amount = round($rates["amount"]);
                        $gst = round($rates["tax_amount"]);
                        $change_date = date('d-m-Y',strtotime($rates["from_date"]));
                        $check_in_date = str_replace('-','/',$change_date);
                        $ktdc_response_xml.='<Rates Date="'.$check_in_date.'" MealPlan="'.$ktdc_plan_type.'" RoomRate="'.$room_amount.'" Taxes="'.$gst.'" />';
                    }
                    $ktdc_response_xml.='</RoomType>';
                }
                if($booking_status == 'Commit'){
                    $ktdc_response_xml.='</Property>
                    <Action Action="N" ConfirmId="" Remarks=""
                    CMId="'.$booking_data['booking_id'].'"/>
                    </SoftBookRequest>'; 
                }
                else if($booking_status == 'Modify'){
                    $getId  = DB::table('ktdc_softbooking')
                    ->select('soft_booking_id')
                    ->where('booking_id',$booking_data['booking_id'])
                    ->first();
                    $ConfirmId = $getId->soft_booking_id;

                    $ktdc_response_xml.='</Property>
                    <Action Action="M" ConfirmId="'.$ConfirmId.'" Remarks=""
                    CMId="'.$booking_data['booking_id'].'"/>
                    </SoftBookRequest>'; 
                }
                
        $ktdc=new KtdcReservation();
        $data['hotel_id']=$hotel_id;
        $data['ktdc_hotel_code']=$ktdc_hotel_code;
        $data['booking_id']=$booking_data['booking_id'];
        $data['ktdc_string']=$ktdc_response_xml;
        if($ktdc->fill($data)->save())
        {
            return $ktdc->id;
        }
        else
        {
            return false;
        }
    }
    public function pushReservations($xml,$ktdc_id)
    {
        $resp=KtdcReservation::where('id',$ktdc_id)->select('ktdc_hotel_code','booking_id')->first();
        $url="http://103.133.180.101:16005";
        $ch 	= curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml);
        $result = curl_exec($ch);
        curl_close($ch);
        $ktdc_data = json_decode(json_encode(simplexml_load_string($result)), true);
        if($ktdc_id == 134){
            dd($ktdc_data);
        }
        if($ktdc_data["@attributes"]["SoftBookingSucess"] == "True"){
            $softBookId = $ktdc_data["@attributes"]["SoftBookId"];
            $softbooking['ktdc_hotel_code'] = $resp->ktdc_hotel_code;
            $softbooking['ktdc_booking_id'] = $ktdc_id;
            $softbooking['soft_booking_id'] = $softBookId;
            $softbooking['booking_id']      = $resp->booking_id;
            $updateSoftBooking = DB::table('ktdc_softbooking')->insert($softbooking);
            
            $conf_booking = $this->ktdcConfirmBooking($softBookId,$resp->ktdc_hotel_code,$ktdc_id);
            $update = KtdcReservation::where('id',$ktdc_id)->update(['ktdc_confirm'=>1]);
        }else{
            return false;
        }
    }
    public function ktdcConfirmBooking($softBookId,$ktdc_hotel,$ktdc_id){
        $xml = '<?xml version="1.0" encoding="utf-8"?>
                <ConfirmSoftBookRequest Key="418367c9-d295-b842-b6a5-60539369811b" UserID="Bookingjini"
                PropertyID="'.$ktdc_hotel.'" SoftBookId="'.$softBookId.'" TransactionId="" Amount="">
                <Advance ResponseCode="" TransactionId=""
                ProcessingFeeAmount="" TransactionAmount="" GST="" TotalAmount=""
                TransactionDate="" InterchangeValue="" TDR=""
                PaymentMode="" SubMerchantId="" ReferenceNo=""
                ID="" RS="" TPS=""
                MandatoryFields ="" OptionalFields=""
                RSV=""/>
                </ConfirmSoftBookRequest>';

        $url="http://103.133.180.101:16005";

        $ch    = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml);
        $result = curl_exec($ch);
        curl_close($ch);
        $ktdc_data = json_decode(json_encode(simplexml_load_string($result)), true);
        if($ktdc_data["@attributes"]["BookingSuccess"] == "True"){
            return true;
        }else{
            return false;
        }
    }
    public function ktdcCancelBooking($booking_id){
        $getId  = DB::table('ktdc_softbooking')
                    ->select('soft_booking_id','ktdc_booking_id')
                    ->where('booking_id',$booking_id)
                    ->first();
        $ConfirmId = $getId->soft_booking_id;

        $xml = '<?xml version="1.0" encoding="utf-8"?>
            <CancellationRequest Key="418367c9-d295-b842-b6a5-60539369811b" UserID="Bookingjini"
            ConfirmId="'.$ConfirmId.'" Message="cancel booking" / >';
            
            $url="http://103.133.180.101:16005";

            $ch    = curl_init();
            curl_setopt( $ch, CURLOPT_URL, $url );
            curl_setopt( $ch, CURLOPT_POST, true );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
            curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml);
            $result = curl_exec($ch);
            curl_close($ch);
            $ktdc_data = json_decode(json_encode(simplexml_load_string($result)), true);
            if($ktdc_data["@attributes"]["Status"] == "True"){
                return $getId->ktdc_booking_id;
            }else{
                return false;
            }
    }
    public function ktdcRoomCode($room_type_id)
    {
       $ktdc_room = KtdcRoom::select('ktdc_room_type_code')
                              ->where('room_type_id','=',$room_type_id)
                              ->first();
       return  $ktdc_room['ktdc_room_type_code'];
    }
    public function ratePlan($rate_plan_id)
    {
       $plan = MasterRatePlan::select('plan_type')
                              ->where('rate_plan_id','=',$rate_plan_id)
                              ->first();
       return  $plan['plan_type'];
    }
    public function testPushBooking(){
    	$xml='<?xml version="1.0" encoding="utf-8"?>
                <SoftBookRequest Key="418367c9-d295-b842-b6a5-60539369811b" UserID="Bookingjini">
                <GuestDetails Title="Mr." Name="Shinto M" Address1="" Address2=""
                Address3="" Country="India" Pin="" EmailId="f1yw1a2mg10r0c87hbqcawt7nrj0@agoda-messaging.com"
                LandPhoneNo="" MobileNo="9605343479" AgentCode="A0065" Instructions=""
                AllInclusiveRates="Yes"/>
                <Property ID="HS" CheckInDate="07/05/2021" CheckInTime="" CheckOutDate="09/05/2021"
                CheckOutTime="" PlanId="" TotalPax="2" Female="0" Children="0" Infants="0"
                Foreigner="0" TotalAmount="5712">
                <RoomType ID="01" Single="0" Double="1" Twin ="0" Adult="0" Child1="0" Child2="0" Infant="0" NoOfRoomNights ="1" >
                <Rates Date="07/05/2021" MealPlan="CP" RoomRate="2856" Taxes="0" />
                <Rates Date="08/05/2021" MealPlan="CP" RoomRate="2856" Taxes="0" />
                </RoomType>
                </Property>
                <Action Action="N" ConfirmId="" Remarks="" CMId="560023257"/>
            </SoftBookRequest>';
        $url="http://103.133.180.101:16005";
        
        $ch    = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml);
        $result = curl_exec($ch);
        curl_close($ch);
        dd($result);

        $ktdc_data = json_decode(json_encode(simplexml_load_string($result)), true);
        // $sendSoftBooking = $this->ktdcConfirmBooking($ktdc_data);
    }
} // KtdcController closed here