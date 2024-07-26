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
use App\LogTable;
use App\Http\Controllers\CmOtaBookingInvStatusService;
use App\Http\Controllers\CommonServiceController;
use App\Http\Controllers\otacontrollers\BookingDataInsertationController;
use App\Http\Controllers\otacontrollers\InstantBucketController;
use App\Http\Controllers\otacontrollers\CurlController;
use App\Http\Controllers\IpAddressService;
use App\Http\Controllers\Controller;
use DB;

/**
* Booking.com Controller implements bookings for Booking.com model.
*modified by ranjit
*@24/01/2019
*/
class BookingDotComControllerTest extends Controller
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
        $headers = array (
        'Content-Type: application/xml',
        );

        $result = '<reservations>
        <reservation>
          <commissionamount>1050</commissionamount>
          <currencycode>INR</currencycode>
          <customer>
            <address></address>
            <cc_cvc></cc_cvc>
            <cc_expiration_date></cc_expiration_date>
            <cc_name></cc_name>
            <cc_number></cc_number>
            <cc_type></cc_type>
            <city></city>
            <company></company>
            <countrycode>in</countrycode>
            <dc_issue_number></dc_issue_number>
            <dc_start_date></dc_start_date>
            <email>smohan.395381@guest.booking.com</email>
            <first_name>Sritam</first_name>
            <last_name>Mohanty</last_name>
            <remarks></remarks>
            <telephone>+91 96208 28038</telephone>
            <zip></zip>
          </customer>
          <date>2021-03-30</date>
          <hotel_id>7121565</hotel_id>
          <hotel_name>The Club Krishna Sea Sight Resorts</hotel_name>
          <id>3363802685</id>
          <room>
            <arrival_date>2021-04-01</arrival_date>
            <commissionamount>525</commissionamount>
            <currencycode>INR</currencycode>
            <departure_date>2021-04-02</departure_date>
            <extra_info>This suite has a balcony, air conditioning and electric kettle.</extra_info>
            <facilities>Tea/Coffee maker, Shower, Safe, TV, Telephone, Air conditioning, Hairdryer, Iron, Balcony, Ironing facilities, Free toiletries, Fan, Toilet, Private Bathroom, Bathtub or shower, Laptop safe, Flat-screen TV, View, Alarm clock, Dining area, Electric kettle, Dryer, Wardrobe or closet, Ocean view, Garden view, Pool view, Terrace, Towels, Upper floors accessible by elevator, Entire unit wheelchair accessible, Clothes rack, Child safety socket covers, Raised toilet, Lower sink, Emergency cord in bathroom, Bottle of water, Chocolate/Cookies, Toothbrush, Shampoo, Conditioner, Body soap, Accessible by elevator, Quiet street view, Smoke Alarm, Fire extinguisher, Hand sanitizer</facilities>
            <guest_name>Sritam Mohanty</guest_name>
            <id>712156501</id>
            <info>Breakfast is included in the room rate. Children and Extra Bed Policy: All children are welcome. One child under 13 years is charged INR 1000  per night in an extra bed. One older child or adult is charged INR 1000  per night in an extra bed. The maximum number of extra beds in a room is 1. The maximum number of total guests in a room is 2. There is no capacity for cribs in the room.  Deposit Policy: No prepayment is needed.  Cancellation Policy: The guest can cancel free of charge until 3 days before arrival. The guest will be charged the cost of the first night if they cancel in the 3 days before arrival. </info>
            <max_children>0</max_children>
            <meal_plan>Breakfast is included in the room rate.</meal_plan>
            <name>Junior Suite</name>
            <numberofguests>2</numberofguests>
            <price date="2021-04-01"
                   genius_rate="no"
                   rate_id="21154728"
                   rewritten_from_id="0"
                   rewritten_from_name="">3500</price>
            <price_details>
              <guest>
                <extracomponent amount="420"
                                currency="INR"
                                included="no"
                                per_night="no"
                                per_person="no"
                                percentage="no"
                                text="Goods &amp; services tax" />
                <total>3500</total>
              </guest>
              <hotel>
                <extracomponent amount="420"
                                currency="INR"
                                included="no"
                                per_night="no"
                                per_person="no"
                                percentage="no"
                                text="Goods &amp; services tax" />
                <total>3500</total>
              </hotel>
            </price_details>
            <remarks></remarks>
            <roomreservation_id>3120666312</roomreservation_id>
            <smoking></smoking>
            <totalprice>3500</totalprice>
          </room>
          <room>
            <arrival_date>2021-04-01</arrival_date>
            <commissionamount>525</commissionamount>
            <currencycode>INR</currencycode>
            <departure_date>2021-04-02</departure_date>
            <extra_info>This suite has a balcony, air conditioning and electric kettle.</extra_info>
            <facilities>Tea/Coffee maker, Shower, Safe, TV, Telephone, Air conditioning, Hairdryer, Iron, Balcony, Ironing facilities, Free toiletries, Fan, Toilet, Private Bathroom, Bathtub or shower, Laptop safe, Flat-screen TV, View, Alarm clock, Dining area, Electric kettle, Dryer, Wardrobe or closet, Ocean view, Garden view, Pool view, Terrace, Towels, Upper floors accessible by elevator, Entire unit wheelchair accessible, Clothes rack, Child safety socket covers, Raised toilet, Lower sink, Emergency cord in bathroom, Bottle of water, Chocolate/Cookies, Toothbrush, Shampoo, Conditioner, Body soap, Accessible by elevator, Quiet street view, Smoke Alarm, Fire extinguisher, Hand sanitizer</facilities>
            <guest_name>Sritam Mohanty</guest_name>
            <id>712156501</id>
            <info>Breakfast is included in the room rate. Children and Extra Bed Policy: All children are welcome. One child under 13 years is charged INR 1000  per night in an extra bed. One older child or adult is charged INR 1000  per night in an extra bed. The maximum number of extra beds in a room is 1. The maximum number of total guests in a room is 2. There is no capacity for cribs in the room.  Deposit Policy: No prepayment is needed.  Cancellation Policy: The guest can cancel free of charge until 3 days before arrival. The guest will be charged the cost of the first night if they cancel in the 3 days before arrival. </info>
            <max_children>0</max_children>
            <meal_plan>Breakfast is included in the room rate.</meal_plan>
            <name>Junior Suite</name>
            <numberofguests>2</numberofguests>
            <price date="2021-04-01"
                   genius_rate="no"
                   rate_id="21154728"
                   rewritten_from_id="0"
                   rewritten_from_name="">3500</price>
            <price_details>
              <guest>
                <extracomponent amount="420"
                                currency="INR"
                                included="no"
                                per_night="no"
                                per_person="no"
                                percentage="no"
                                text="Goods &amp; services tax" />
                <total>3500</total>
              </guest>
              <hotel>
                <extracomponent amount="420"
                                currency="INR"
                                included="no"
                                per_night="no"
                                per_person="no"
                                percentage="no"
                                text="Goods &amp; services tax" />
                <total>3500</total>
              </hotel>
            </price_details>
            <remarks></remarks>
            <roomreservation_id>3120666320</roomreservation_id>
            <smoking></smoking>
            <totalprice>3500</totalprice>
          </room>
          <status>new</status>
          <time>14:10:01</time>
          <totalprice>7000</totalprice>
        </reservation>
      </reservations>
      <!-- RUID: [UmFuZG9tSVYkc2RlIyh9Yet2OsCMkQVzzgApeg7R5p0Oqx0JCuo7b+q8kp9dJM0cXq6sKmmrEVINmIn9XQGrB1RQnyxckfJE] -->';

      $array_data = json_decode(json_encode(simplexml_load_string($result)), true);

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
                    if($reservation_data['status'] == 'cancelled'){
                        $modify_status = '';
                       $this->cancelBooking($reservation_data,$modify_status);
                    }
                    else if($reservation_data['status'] == 'modified'){
                        $modify_status = $reservation_data['status'];
                        if($this->cancelBooking($reservation_data,$modify_status)){
                            $this->modifyBookingDetails($reservation_data,$result);
                        }
                    }
                    else{
                        $this->saveBookingDetails($reservation_data,$result);
                    }
                    $res_count++;
                }
            }
            else
            {
                if($reservation_datas['status'] == 'cancelled'){
                    $modify_status = '';
                    return $this->cancelBooking($reservation_datas,$modify_status);
                }
                else if($reservation_datas['status'] == 'modified'){
                    $modify_status = $reservation_datas['status'];
                    if($this->cancelBooking($reservation_datas,$modify_status)){
                        $this->modifyBookingDetails($reservation_datas,$result);
                    }
                }
                else{
                    return $this->saveBookingDetails($reservation_datas,$result);
                }
            }
        }
        else
        {
            echo "No Reservation <br>";
        } // else close isset $array_data['reservation'] avaliable or not
        $logfile = fopen($logpath, "a+");
        fwrite($logfile,"Reservations processed: ".$res_count."\n");
        fwrite($logfile,"Processing ends at: ".date("Y-m-d H:i:s")."\n");
        fclose($logfile);
    }
    public function saveBookingDetails($reservation_data,$result)
    {

        $channel_name   = 'Booking.com';
        //To get the hotel id from OTA hotel code
        $otaHotelCode       = $reservation_data['hotel_id'];
        $ota_details_model  = new CmOtaDetails();
        $ota_hotel_details  = $ota_details_model
        ->where('ota_hotel_code', '=' ,$otaHotelCode)
        ->where('is_status',1)
        ->first();
        //Check if hotel is exist with us
        if($ota_hotel_details->hotel_id)
        {
                $booking_status = $reservation_data['status'];
                $UniqueID       = $reservation_data['id'];
                $booking_date   = $reservation_data['date'];
                $first_name     = is_array($reservation_data['customer']['first_name']) ? '': $reservation_data['customer']['first_name'];
                $last_name      = is_array($reservation_data['customer']['last_name']) ? '': $reservation_data['customer']['last_name'];
                $customerDetail = $first_name." ".$last_name.',';
                $customerDetail.= is_array($reservation_data['customer']['email']) ? 'NA,': $reservation_data['customer']['email'].',';
                $customerDetail.= is_array($reservation_data['customer']['telephone'])? 'NA' : $reservation_data['customer']['telephone'];

                $amount         = $reservation_data['totalprice'];
                $currency       = $reservation_data['currencycode'];
                $payment_status = 'Pay at hotel';

                /*-----------Fetch Rate Plan------------------*/
                $xml            = simplexml_load_string($result);
                foreach($xml as $room_rate_xml)
                {
                    if($room_rate_xml->status=='new'|| $room_rate_xml->status=='modified')
                    {
                        if($reservation_data['id'] == $room_rate_xml->children()->id){
                            $roomRateNode   = $room_rate_xml->children()->room;
                        }
                    }
                }
                $p=0;
                $extra_amount=0;
                $special_info="";
                $meal_plan="";
                $facilities="";
                $cancel_policy="";
                $rewritten_from_name="";
                $genius_rate="";
                $smoking="";
                foreach ($roomRateNode as $key => $value)
                {
                    $rt_code[] = $value->children()->price->attributes()->rate_id;
                    $rewritten_from_name .= $rewritten_from_name ? $value->children()->price->attributes()->rewritten_from_name : "";
                    $genius_rate .= $genius_rate ? $value->children()->price->attributes()->genius_rate : "" ;
                    $meal_plan .= $meal_plan == "" ? $value->children()->meal_plan : "";
                    $facilities .= $facilities == "" ? $value->children()->extra_info : "";
                    $cancel_policy.= $cancel_policy === "" ? $value->children()->info : "";
                    $smoking.= $smoking === "" ? $value->children()->smoking : "";
                    $p++;
                }
                $rt_code   =  array_unique($rt_code, SORT_REGULAR);
                //Prepare the special information
                if($smoking==""){
                    $smoking="No Smoking";
                }
                $special_info= $smoking;

                if($meal_plan!=""){
                    $special_info.=" Meal Plan:".$meal_plan;
                }
                if($facilities!=""){
                    $special_info.="Facilities:".$facilities;
                }
                if($rewritten_from_name!=""){
                    $special_info.= " rewritten_from_name:".$rewritten_from_name;
                }
                if($genius_rate!=""){
                    $special_info.=" genius_rate:".$genius_rate;
                }
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
                                        if($reservation_data['room'][$i]['price_details']['hotel']['extracomponent'][$e]['@attributes']['text']=='Goods & services tax' || $reservation_data['room'][$i]['price_details']['hotel']['extracomponent'][$e]['@attributes']['text']=='VAT')
                                        {
                                            $tax_amount+=$reservation_data['room'][$i]['price_details']['hotel']['extracomponent'][$e]['@attributes']['included']=='yes' ? 0: $reservation_data['room'][$i]['price_details']['hotel']['extracomponent'][$e]['@attributes']['amount'];
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
                                    if($reservation_data['room'][$i]['price_details']['hotel']['extracomponent']['@attributes']['text']=='Goods & services tax' || $reservation_data['room'][$i]['price_details']['hotel']['extracomponent']['@attributes']['text']=='VAT')
                                        {
                                            $tax_amount+=$reservation_data['room'][$i]['price_details']['hotel']['extracomponent']['@attributes']['included']=='yes' ? 0 : $reservation_data['room'][$i]['price_details']['hotel']['extracomponent']['@attributes']['amount'];
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
                        //$children[$i]     = $UniqueRoom["max_children"];
                        $i++;
                    }
                    $rooms_qty = implode(',', $rm_qty);
                    $room_type = implode(',', $rm_type);
                    $rate_code = implode(',', $rt_code);
                    $no_of_adult = implode(',', $adults);
                    //$no_of_child = implode(',', $children);
                }
                else
                {
                    $tax_amount=0;
                    $rooms_qty   = 1;
                    $room_type   = $reservation_data['room']['id'];
                    $checkin_at  = $reservation_data['room']['arrival_date'];
                    $checkout_at = $reservation_data['room']['departure_date'];
                    $no_of_adult = $reservation_data['room']["numberofguests"];
                    //$no_of_child = $reservation_data['room']["max_children"];
                    $rate_code = implode(',', $rt_code);

                    if(isset($reservation_data['room']['price_details']['hotel']['extracomponent']))
                        {
                          $ex_size=sizeof($reservation_data['room']['price_details']['hotel']['extracomponent']);

                    if($ex_size>1)
                    {
                        for($e=0;$e<$ex_size;$e++)
                        {
                            if(isset($reservation_data['room']['price_details']['hotel']['extracomponent'][$e]))
                            {
                                if($reservation_data['room']['price_details']['hotel']['extracomponent'][$e]['@attributes']['text']=='Goods & services tax' || $reservation_data['room']['price_details']['hotel']['extracomponent'][$e]['@attributes']['text']=='VAT'){
                                    $tax_amount+=$reservation_data['room']['price_details']['hotel']['extracomponent'][$e]['@attributes']['included']=='yes' ? 0 : $reservation_data['room']['price_details']['hotel']['extracomponent'][$e]['@attributes']['amount'];
                                }else{
                                    $extra_amount+=$reservation_data['room']['price_details']['hotel']['extracomponent'][$e]['@attributes']['amount'];
                                }
                            }
                        }
                    }
                    else
                    {
                            if($reservation_data['room']['price_details']['hotel']['extracomponent']['@attributes']['text']=='Goods & services tax' || $reservation_data['room']['price_details']['hotel']['extracomponent']['@attributes']['text']=='VAT'){
                                $tax_amount+=$reservation_data['room']['price_details']['hotel']['extracomponent']['@attributes']['included']=='yes' ? 0 : $reservation_data['room']['price_details']['hotel']['extracomponent']['@attributes']['amount'];
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
                $bookingDetails = array('UniqueID'=>$UniqueID,'customerDetail'=>$customerDetail,'booking_status'=>$booking_status,'rooms_qty'=>$rooms_qty,'room_type'=>$room_type,'checkin_at'=>$checkin_at,'checkout_at'=>$checkout_at,'booking_date'=>$booking_date,'amount'=>$amount,'payment_status'=>$payment_status,'rate_code'=>$rate_code,'rlt'=>$result,'currency'=>$currency,'channel_name'=>$channel_name,'tax_amount'=>$tax_amount,'no_of_adult'=>$no_of_adult,'no_of_child'=>0,'inclusion'=>'NA',
                'special_information'=>$special_info,
                'cancel_policy'=>$cancel_policy);
                $bookinginfo  = $this->bookingData->cmOtaBooking($bookingDetails,$ota_hotel_details);//this function call used for insert/update booking in database
                $db_status  = $bookinginfo['db_status'];
                $ota_booking_tabel_id =$bookinginfo['ota_booking_tabel_id'];
                if($db_status){
                    $this->instantbucket->bucketEngineUpdate($booking_status,$push_by,$ota_hotel_details,$ota_booking_tabel_id);//this function is used for booking bucket data updation
                }
            }
        else
        {
            return "This hotel is not exits in Bookingjini Database! Thank you for contat with us.";
        } // else for $ota_hotel_details->hotel_id not exit
    }

    // booking cancelation
    public function cancelBooking($reservation_data,$modify_status)
    {
        $otaBookingModel = new CmOtaBooking();
        $ota_details_model  = new CmOtaDetails();
        $otaHotelCode       = $reservation_data['hotel_id'];
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
            $push_by='Booking.com';
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
        }
        else
        {
            return "This hotel is not exits in Bookingjini Database! Thank you for contat with us.";
        } 
    }


    public function noShowPush(Request $request)
    {
        $logModel = new LogTable();
        $data=$request->all();
        $inventory_client_ip=$this->ipService->getIPAddress();$payment_status = 'Pay at hotel';
        $user_id  = $request->auth->admin_id;
        $ota_details_data             	= CmOtaDetails::select('*')
        ->where('hotel_id', '=' ,$data['hotel_id'])
        ->where('ota_id', '=' ,$data['ota_id'])
        ->first();
        $commonUrl      				= $ota_details_data->url;

        $log_data               	= [
            "action_id"          => 4,
            "hotel_id"           => $data['hotel_id'],
            "ota_id"      		   => $data['ota_id'],
            "inventory_ref_id"   => $data['unique_id'],
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
     /**
     * This function is used for modifying the booking for booking.com
     * @author SIRI DATE : 26-02-2021
     */
    public function modifyBookingDetails($reservation_data,$result)
    {
        $channel_name   = 'Booking.com';
        //To get the hotel id from OTA hotel code
        $otaHotelCode       = $reservation_data['hotel_id'];
        $ota_details_model  = new CmOtaDetails();
        $ota_hotel_details  = $ota_details_model
        ->where('ota_hotel_code', '=' ,$otaHotelCode)
        ->first();
        //Check if hotel is exist with us
        if($ota_hotel_details->hotel_id)
        {
                $booking_status = $reservation_data['status']; 
                $UniqueID       = $reservation_data['id'];
                $booking_date   = $reservation_data['date'];
                $amount         = $reservation_data['totalprice'];
                $currency       = $reservation_data['currencycode'];
                $payment_status = 'Pay at hotel';
                
                /*-----------Fetch Rate Plan------------------*/
                $xml            = simplexml_load_string($result);
                foreach($xml as $room_rate_xml)
                {
                    if($room_rate_xml->status=='new'|| $room_rate_xml->status=='modified')
                    {
                        if($reservation_data['id'] == $room_rate_xml->children()->id){
                            $roomRateNode   = $room_rate_xml->children()->room;
                        } 
                    }
                }
                $p=0;
                $extra_amount=0;
                $special_info="";
                $meal_plan="";
                $facilities="";
                $cancel_policy="";
                $rewritten_from_name="";
                $genius_rate="";
                $smoking="";
                foreach ($roomRateNode as $key => $value) 
                {
                    $rt_code[] = $value->children()->price->attributes()->rate_id;
                    $rewritten_from_name .= $rewritten_from_name ? $value->children()->price->attributes()->rewritten_from_name : "";
                    $genius_rate .= $genius_rate ? $value->children()->price->attributes()->genius_rate : "" ;
                    $meal_plan .= $meal_plan == "" ? $value->children()->meal_plan : "";
                    $facilities .= $facilities == "" ? $value->children()->extra_info : "";
                    $cancel_policy.= $cancel_policy === "" ? $value->children()->info : "";
                    $smoking.= $smoking === "" ? $value->children()->smoking : "";
                    $p++;
                }
                $rt_code   =  array_unique($rt_code, SORT_REGULAR);
                //Prepare the special information 
                if($smoking==""){
                    $smoking="No Smoking";
                }
                $special_info= $smoking;

                if($meal_plan!=""){
                    $special_info.=" Meal Plan:".$meal_plan;
                }
                if($facilities!=""){
                    $special_info.="Facilities:".$facilities;
                }
                if($rewritten_from_name!=""){
                    $special_info.= " rewritten_from_name:".$rewritten_from_name;
                }
                if($genius_rate!=""){
                    $special_info.=" genius_rate:".$genius_rate;
                } 
                //Check for the multiple rooms
                $isMultidimensional_rooms = $this->commonService->isMultidimensionalArray($reservation_data['room']);
                if($isMultidimensional_rooms)
                {
                    $tax_amount=0;
                    $UniqueRooms = $this->commonService->uniqueMultidimArray($reservation_data,'id');
                    $gestdetails =  $reservation_data['room'];
                    $adults=array();
                    $children=array();
                    $getsDetails=array();
                   
                    if($p == 1){
                        $rm_type[] = $reservation_data['room']['id'];
                        $price[] = $reservation_data['room']['totalprice'];
                        $rta[]         = $reservation_data['room']['id'];
                        $room_array_id_occurrences = array_count_values($rta);
                        $rm_qty     = $room_array_id_occurrences;
                        $checkin_at  = $reservation_data['room']['arrival_date'];
                        $checkout_at = $reservation_data['room']['departure_date'];
                        $getsDetails[0] = $reservation_data['room']['guest_name'];
                        $adults[0]  = $reservation_data['room']['numberofguests'];
                    }
                    else{
                        for($i=0;$i<$p;$i++)
                        {
                            $rm_type[] = $reservation_data['room'][$i]['id'];
                            $price[] = $reservation_data['room'][$i]['totalprice'];
                            $rta[]         = $reservation_data['room'][$i]['id'];
                            $room_array_id_occurrences = array_count_values($rta);
                            $rm_qty     = $room_array_id_occurrences;
                            $checkin_at  = $reservation_data['room'][$i]['arrival_date'];
                            $checkout_at = $reservation_data['room'][$i]['departure_date'];
                        }
                       
                        foreach ($gestdetails as $UniqueRoom)
                        {
                            if(isset($UniqueRoom["guest_name"])){
                                $getsDetails[$i]      = $UniqueRoom["guest_name"];
                            }
                            $adults[$i]       = $UniqueRoom["numberofguests"];
                            $i++;
                        }
                    }
                    
                    $rooms_qty      = implode(',', $rm_qty);
                    $room_type      = implode(',', $rm_type);
                    $rate_code      = implode(',', $rt_code);
                    $no_of_adult    = implode(',', $adults);
                    $guest_info     = implode(',',$getsDetails);
                    $room_price     = implode(',',$price);
                    $customerDetail = $guest_info.",".$reservation_data['customer']['email'].",".$reservation_data['customer']['telephone']; 
                }
                else
                {
                    $tax_amount=0;
                    $rooms_qty   = 1;
                    $room_price = $reservation_data['room']['totalprice'];
                    $room_type   = $reservation_data['room']['id'];
                    $checkin_at  = $reservation_data['room']['arrival_date'];
                    $checkout_at = $reservation_data['room']['departure_date'];
                    $no_of_adult = $reservation_data['room']["numberofguests"];
                    $guest_info     = $reservation_data['room']["guest_name"];
                    $customerDetail = $guest_info.",".$reservation_data['customer']['email'].",".$reservation_data['customer']['telephone'];    
                    $rate_code = implode(',', $rt_code);
                }  
                
                $amount+=$extra_amount;
                //Checking for the status
                if($booking_status == "modified"){
                    $booking_status =   'Modify';
                }
                $push_by='Booking.com';
                $bookingDetails = array('UniqueID'=>$UniqueID,'customerDetail'=>$customerDetail,'booking_status'=>$booking_status,'rooms_qty'=>$rooms_qty,'room_type'=>$room_type,'checkin_at'=>$checkin_at,'checkout_at'=>$checkout_at,'booking_date'=>$booking_date,'amount'=>$amount,'payment_status'=>$payment_status,'rate_code'=>$rate_code,'rlt'=>$result,'currency'=>$currency,'channel_name'=>$channel_name,'no_of_adult'=>$no_of_adult,'no_of_child'=>0,'inclusion'=>'NA','room_price' =>$room_price,'special_information'=>$special_info,'cancel_policy'=>$cancel_policy,'tax_amount'=>$tax_amount,'modify_status'=>2);
                $bookinginfo  = $this->bookingData->cmOtaBooking($bookingDetails,$ota_hotel_details);//this function call used for insert/update booking in database
                $db_status  = $bookinginfo['db_status'];
                $ota_booking_tabel_id =$bookinginfo['ota_booking_tabel_id'];  
                
                if($db_status){
                    $this->instantbucket->bucketEngineUpdate($booking_status,$push_by,$ota_hotel_details,$ota_booking_tabel_id);//this function is used for booking bucket data updation
                }  
            }
        else
        {
            return "This hotel is not exits in Bookingjini Database! Thank you for contact with us.";
        } // else for $ota_hotel_details->hotel_id not exit
    }

} // class closed.
