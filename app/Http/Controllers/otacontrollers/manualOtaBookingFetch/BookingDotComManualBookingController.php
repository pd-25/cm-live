<?php
namespace App\Http\Controllers\otacontrollers\manualOtaBookingFetch;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Input;
use Validator;
use App\CmOtaDetails;
use App\CmOtaAllAutoPush;
use App\CmOtaBookingPushBucket;
use App\CmOtaBooking;
use App\Http\Controllers\CmOtaBookingInvStatusService;
use App\Http\Controllers\CommonServiceController;
use App\Http\Controllers\otacontrollers\BookingDataInsertationController;
use App\Http\Controllers\otacontrollers\InstantBucketController;
use App\Http\Controllers\otacontrollers\CurlController;
use App\Http\Controllers\IpAddressService;
use App\Http\Controllers\Controller;
use DB;

/**
* Booking.com Manual booking retrival and save.
*created by ranjit
*@08/02/19
*/
class BookingDotComManualBookingController extends Controller
{
    protected $bookingData,$curlcall,$instantbucket;
    protected $commonService,$ipService;
    protected $cmOtaBookingInvStatusService;
    public function __construct(CommonServiceController $commonService,CmOtaBookingInvStatusService $cmOtaBookingInvStatusService,IpAddressService $ipService,BookingDataInsertationController $bookingData,CurlController $curlcall,InstantBucketController $instantbucket)
    {
        $this->commonService = $commonService;
        $this->cmOtaBookingInvStatusService=$cmOtaBookingInvStatusService;
        $this->ipService=$ipService;
        $this->bookingData=$bookingData;
        $this->curlcall=$curlcall;
        $this->instantbucket=$instantbucket;
    }
    public function manualBookingActionIndex($hotel_id,$booking_id)
    {
        $OtaAllAutoPushModel          = new CmOtaAllAutoPush();
        $headers = array (
        'Content-Type: application/xml',
        );

        $xml ='<?xml version="1.0" encoding="UTF-8"?>
        <request>
        <username>Bookingjini-channelmanager</username>
        <password>wSznWO?2wy/^-j/hfUK^MCq?:A*EK)BBXSMK-.*)</password>
        <id>'.$booking_id.'</id>
        <hotel_id>'.$hotel_id.'</hotel_id>
        </request>';
        $url = 'https://secure-supply-xml.booking.com/hotels/xml/reservations';
        $array_details = $this->curlcall->curlRequest($url,$headers,$xml);//used for cURL request
        $array_data=$array_details['array_data'];
        $result=$array_details['rlt'];
        if(isset($array_details['array_data']['fault'])){
            return isset($array_details['array_data']['fault']['@attributes']) ?  array("status"=>0,"message"=>$array_details['array_data']['fault']['@attributes']['string']) : array("status"=>0,"message"=>$array_details['array_data']['fault']['string']);
        }
        if(isset($array_data['reservation']))
        {
            $OtaAllAutoPushModel->respones_xml = trim($result);
            $OtaAllAutoPushModel->save();
            $reservation_datas  = $array_data['reservation'];
            $isMultidimensional_reservation_datas = $this->commonService->isMultidimensionalArray($reservation_datas);
            /*---if candition for checking is multidimentional array or not here---*/
            if($isMultidimensional_reservation_datas)
            {
                foreach ($reservation_datas as $reservation_data) 
                {   
                    //This block used for restrict the unavailable booking to modify or cancel.
                    if($reservation_data['status'] != 'new')  
                    {
                        if(!$this->cmOtaBookingInvStatusService->checkBookingId($reservation_data['id'])){
                            continue;
                        }
                    }
                    //check for cancel status
                    if($reservation_data['status'] == 'cancelled')
                    {
                       $this->cancelBooking($reservation_data);
                    }
                    else{
                        $this->saveBookingDetails($reservation_data,$result);
                    } 
                }
            }
            else
            {
                //This block used for restrict the unavailable booking to modify or cancel.
                if($reservation_datas['status'] != 'new')  
                {
                    if(!$this->cmOtaBookingInvStatusService->checkBookingId($reservation_datas['id'])){
                        return array("status"=>0,"message"=>"This booking is not available with us from booking.com");
                    }
                }
                //check for cancel status
                if($reservation_datas['status'] == 'cancelled')
                {
                    return $this->cancelBooking($reservation_datas);
                }
                else{
                    return $this->saveBookingDetails($reservation_datas,$result);
                } 
            }
        }
        else
        {
            return array("status"=>0,"message"=>"No Booking Data Availabale");
        } // else close isset $array_data['reservation'] avaliable or not
    }
    public function saveBookingDetails($reservation_data,$result)
    {
        $channel_name   = 'Booking.com';
        //To get the hotel id from OTA hotel code
        $ota_details_model  = new CmOtaDetails();
        $otaHotelCode       = $reservation_data['hotel_id'];
        $ota_hotel_details  = $ota_details_model
        ->where('ota_hotel_code', '=' ,$otaHotelCode)
        ->first();
        //Check if hotel is exist with us
        if($ota_hotel_details->hotel_id)
        {
                $booking_status = $reservation_data['status']; 
                $UniqueID       = $reservation_data['id'];
                $booking_date   = $reservation_data['date'];
                $customerDetail = is_array($reservation_data['customer']['first_name']) ? '': $reservation_data['customer']['first_name']." ".is_array($reservation_data['customer']['last_name']) ? '': $reservation_data['customer']['last_name'].',';
                $customerDetail.= is_array($reservation_data['customer']['email']) ? 'NA,': $reservation_data['customer']['email'].',';
                $customerDetail.= is_array($reservation_data['customer']['telephone'])? 'NA' : $reservation_data['customer']['telephone'] ;    
                $amount         = $reservation_data['totalprice'];
                $currency       = $reservation_data['currencycode'];
                $payment_status = 'Pay at hotel';
                
                /*-----------Fetch Rate Plan------------------*/
                $xml            = simplexml_load_string($result);
                
                foreach($xml as $room_rate_xml)
                {
                    if($room_rate_xml->status=='new' || $room_rate_xml->status=='modified' )
                    {
                        $roomRateNode   = $room_rate_xml->children()->room;
                    }
                }

                $p=0;
                $extra_amount=0;
                foreach ($roomRateNode as $key => $value) 
                {
                    $rt_code[] = $value->children()->price->attributes()->rate_id;
                    $p++;
                }
                $rt_code   =  array_unique($rt_code, SORT_REGULAR);

                //Check for the multiple rooms
                $isMultidimensional_rooms = $this->commonService->isMultidimensionalArray($reservation_data['room']);
                if($isMultidimensional_rooms)
                {
                    $UniqueRooms = $this->commonService->uniqueMultidimArray($reservation_data['room'],'id');
                    $gestdetails =  $reservation_data['room'];
                    $rta         = array_column($reservation_data['room'], 'id');
                    $room_array_id_occurrences = array_count_values($rta);
                    $tax_amount=0;
                    for($i=0;$i<$p;$i++)
                    {     if(isset($reservation_data['room'][$i]['price_details']['hotel']['extracomponent']))
                        {
                            $ex_size=sizeof($reservation_data['room'][$i]['price_details']['hotel']['extracomponent']);
                            if($ex_size>1)
                            {
                                for($e=0;$e<$ex_size;$e++)
                                {
                                    if(isset($reservation_data['room'][$i]['price_details']['hotel']['extracomponent'][$e]))
                                    {    
                                        if($reservation_data['room'][$i]['price_details']['hotel']['extracomponent'][$e]['@attributes']['text']=='Goods & services tax')
                                        {
                                            $tax_amount+=$reservation_data['room'][$i]['price_details']['hotel']['extracomponent'][$e]['@attributes']['amount'];
                                        }else{
                                            $extra_amount+= $reservation_data['room'][$i]['price_details']['hotel']['extracomponent'][$e]['@attributes']['amount'];      
                                        }
                                    }
                                }
                            }
                            else
                            {
                                if(isset($reservation_data['room'][$i]['price_details']['hotel']['extracomponent']))
                                {
                                    if($reservation_data['room'][$i]['price_details']['hotel']['extracomponent']['@attributes']['text']=='Goods & services tax')
                                        {
                                            $tax_amount+=$reservation_data['room'][$i]['price_details']['hotel']['extracomponent']['@attributes']['amount'];
                                        }else{
                                            $extra_amount+=$reservation_data['room'][$i]['price_details']['hotel']['extracomponent']['@attributes']['amount'];
                                        }
                                }
                            }
                        }
                    }             
                    $adults=array();
                    $children=array();
                    $i++;
                    foreach ($UniqueRooms as $UniqueRoom)
                    {
                        $rm_type[]    = $UniqueRoom['id'];
                        $rm_qty[]     = $room_array_id_occurrences[$UniqueRoom['id']];
                        $checkin_at   = $UniqueRoom['arrival_date'];
                        $checkout_at  = $UniqueRoom['departure_date'];
                    }
                    foreach ($gestdetails as $UniqueRoom)
                    {
                        $adults[$i]       = $UniqueRoom["numberofguests"];
                        $children[$i]     = $UniqueRoom["max_children"];
                        $i++;
                    }
                    $rooms_qty = implode(',', $rm_qty);
                    $room_type = implode(',', $rm_type);
                    $rate_code = implode(',', $rt_code);
                    $no_of_adult = implode(',', $adults);
                    $no_of_child = implode(',', $children);
                }
                else
                {
                    $tax_amount=0;
                    $rooms_qty   = 1;
                    $room_type   = $reservation_data['room']['id'];
                    $checkin_at  = $reservation_data['room']['arrival_date'];
                    $checkout_at = $reservation_data['room']['departure_date'];
                    $no_of_adult = $reservation_data['room']["numberofguests"];
                    $no_of_child = $reservation_data['room']["max_children"];
                    $rate_code = implode(',', $rt_code);
                    if(isset($reservation_data['room']['price_details']['hotel']['extracomponent'])){
                        $ex_size=sizeof($reservation_data['room']['price_details']['hotel']['extracomponent']);
                    }
                    if($ex_size>1)
                    {
                        for($e=0;$e<$ex_size;$e++)
                        {
                            if(isset($reservation_data['room']['price_details']['hotel']['extracomponent'][$e]))
                            {
                                if($reservation_data['room']['price_details']['hotel']['extracomponent'][$e]['@attributes']['text']=='Goods & services tax'){
                                    $tax_amount+=$reservation_data['room']['price_details']['hotel']['extracomponent'][$e]['@attributes']['amount'];
                                }else{
                                    $extra_amount+=$reservation_data['room']['price_details']['hotel']['extracomponent'][$e]['@attributes']['amount'];
                                }
                            }
                        }
                    }
                    else
                    {
                        if(isset($reservation_data['room']['price_details']['hotel']['extracomponent']))
                        {
                            if($reservation_data['room']['price_details']['hotel']['extracomponent']['@attributes']['text']=='Goods & services tax'){
                                $tax_amount+=$reservation_data['room']['price_details']['hotel']['extracomponent']['@attributes']['amount'];
                            }else{
                                $extra_amount+=$reservation_data['room']['price_details']['hotel']['extracomponent']['@attributes']['amount'];
                            }
                        }
                    }
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
                $push_by='Booking.com';
                $bookingDetails = array('UniqueID'=>$UniqueID,'customerDetail'=>$customerDetail,'booking_status'=>$booking_status,'rooms_qty'=>$rooms_qty,'room_type'=>$room_type,'checkin_at'=>$checkin_at,'checkout_at'=>$checkout_at,'booking_date'=>$booking_date,'amount'=>$amount,'payment_status'=>$payment_status,'rate_code'=>$rate_code,'rlt'=>$result,'currency'=>$currency,'channel_name'=>$channel_name,'tax_amount'=>$tax_amount,'no_of_adult'=>$no_of_adult,'no_of_child'=>$no_of_child,'inclusion'=>'NA');
                $bookinginfo  = $this->bookingData->cmOtaBooking($bookingDetails,$ota_hotel_details);//this function call used for insert/update booking in database
                $db_status  = $bookinginfo['db_status'];
                $ota_booking_tabel_id =$bookinginfo['ota_booking_tabel_id'];  
                if($db_status){
                    $this->instantbucket->bucketEngineUpdate($booking_status,$push_by,$ota_hotel_details,$ota_booking_tabel_id);//this function is used for booking bucket data updation
                    return array("status"=>1,"message"=>"Success");
                }else{
                    return array("status"=>0,"message"=>"Booking already with us!");
               }  
            }
        else
        {
            return "This hotel is not exits in Bookingjini Database! Thank you for contat with us.";
        } // else for $ota_hotel_details->hotel_id not exit
    }
    // booking cancelation
    public function cancelBooking($reservation_data)
    {
        $otaBookingModel = new CmOtaBooking();
        $ota_details_model  = new CmOtaDetails();
        $otaHotelCode       = $reservation_data['hotel_id'];
        $ota_hotel_details  = $ota_details_model
        ->where('ota_hotel_code', '=' ,$otaHotelCode)
        ->first();
        $UniqueID       = $reservation_data['id'];

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
        
       /*----------------- Fetch booking values -------------------*/ 
       $push_by='Booking.com';
       $bookingDetails = array('UniqueID'=>$UniqueID,'customerDetail'=>$customerDetail,'booking_status'=>'Cancel','rooms_qty'=>$rooms_qty,'room_type'=>$room_type,'checkin_at'=>$checkin_at,'checkout_at'=>$checkout_at,'booking_date'=>$booking_date,'amount'=>$amount,'payment_status'=>'pay at hotel','rate_code'=>$rate_code,'rlt'=>'NA','currency'=>'INR','channel_name'=>'Booking.com','tax_amount'=>0,'no_of_adult'=>0,'no_of_child'=>0,'inclusion'=>'NA');
       $bookinginfo  = $this->bookingData->cmOtaBooking($bookingDetails,$ota_hotel_details);//this function call used for insert/update booking in database
       $db_status  = $bookinginfo['db_status'];
       $ota_booking_tabel_id =$bookinginfo['ota_booking_tabel_id'];  
       if($db_status){
           $this->instantbucket->bucketEngineUpdate($booking_status,$push_by,$ota_hotel_details,$ota_booking_tabel_id);//this function is used for booking bucket data updation
            return array("status"=>1,"message"=>"Success");
        }else{
            return array("status"=>0,"message"=>"Error occured during the DB saving");
       }  
    }
    public function noShowPush(Request $request)
    {
        $logModel = new LogTable();
        $data=$request->all();
        $inventory_client_ip=$this->ipService->getIPAddress();
        $user_id  = $request->auth->admin_id;
        $ota_details_data             	= CmOtaDetails::select('*')
        ->where('hotel_id', '=' ,$data['hotel_id'])
        ->where('ota_id', '=' ,$data['ota_id'])
        ->first(); 	
        $commonUrl      				= $ota_details_data->url;

        $log_data               	= [
            "action_id"          => 4,
            "hotel_id"           => $data['hotel_id'],
            "ota_id"      		 => $data['ota_id'],
            "booking_ref_id"     => '',
            "inventory_ref_id"   => $data['inventory_ref_id'],
            "rate_ref_id"        => '',
            "user_id"            => $user_id,
            "request_msg"        => '',
            "response_msg"       => '',
            "request_url"        => '',
            "status"         	 => 2,
            "ip"         		 => $inventory_client_ip,
            "comment"			 => "Processing for update "
            ];

            $headers = array (
            'Content-Type: application/xml'
            );
        
        $xml          = '<request>
        <username>Bookingjini-channelmanager</username>
        <password>wSznWO?2wy/^-j/hfUK^MCq?:A*EK)BBXSMK-.*)</password>
        <reservation_id>'.$data['unique_id'].'</reservation_id>
        <report waived_fees="yes">is_no_show</report>
        </request>';
        $log_request_msg = $xml;
        $url         	 = $commonUrl.'reporting';
        $logModel->fill($log_data)->save();
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml);
        $result = curl_exec($ch);
        curl_close($ch);
        
        $resultXml=simplexml_load_string($result);
        $array=json_decode(json_encode((array)$resultXml), TRUE);
        if($resultXml)
        {
            if(strpos($result, '<status>' ) !== false)
            {
                DB::table('log_table')->where('id', $logModel->id)
                ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                return response()->json(array('status'=>1,'message'=>"No show successful!"));	
            }
            else if(strpos($result, '<reporting>' ) !== false)
            {
                DB::table('log_table')->where('id', $logModel->id)
                ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                $zero=0;
                return response()->json(array('status'=>0,'message'=>$array['fault']['string']));	
            }
        }
    }

} // class closed.