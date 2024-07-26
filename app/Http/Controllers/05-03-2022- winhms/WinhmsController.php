<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Validator;
//use App\Http\Controllers\PmsService;
use DB;
use Ixudra\Curl\Facades\Curl;
//use App\PmsReservation;
//use App\PmsRoom;
use App\IdsReservation;
use App\WinhmsReservation;
use App\WinhmsRoom;
use App\MasterRatePlan;
use App\WinHms;
use App\WinhmsRatePush;
use App\WinhmsBookingTracker;
use App\HotelInformation;
use App\PmsAccount;
// use App\Http\Controllers\IpAddressService;
use App\Http\Controllers\PmsComponentController;

class WinhmsController extends Controller
{
    protected $PmsComponents;
    public function __construct(PmsComponentController $PmsComponents)
    {
    //    $this->ipService  = $ipService;
       $this->PmsComponents = $PmsComponents;
    }
    // public function actionResponse(Request $request)
    // {
    //     $postdata=$request->all();
    //     $push_array_data    = json_decode(json_encode(simplexml_load_string($postdata)), true);
    //     $api_key        = "";
    //     $version        = $push_array_data['@attributes']['Version'];
    //     $timestamp      = $push_array_data['@attributes']['TimeStamp'];
    //     $token          = $push_array_data['@attributes']['EchoToken'];
    //     if(isset($push_array_data['Success']))
    //     {
    //        $booking_id=$push_array_data['NotifDetails']['HotelNotifReport']['HotelReservations']['HotelReservation']['UniqueID']['@attributes']['ID'];

    //        $return_array = '<OTA_HotelResNotifRS xmlns="http://www.opentravel.org/OTA/2003/05" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
    //                     <Success>'.$booking_id.'</Success> </OTA_HotelResNotifRS>';
    //     }
    //     else
    //     {
    //         $return_array = '<OTA_HotelResNotifRS xmlns="http://www.opentravel.org/OTA/2003/05" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
    //                     <Success></Success> </OTA_HotelResNotifRS>';
    //     }

    //     return response()->json($return_array,200);
    // }
    //To get Winhms Status (Exists or Not)
    public function getWinhmsStatus($hotel_id)
    {
        $resp=PmsAccount::where('name','Winhms')->select('hotels')->first();

         if(strpos($resp->hotels, "$hotel_id") >= 0){
             return true;
         } else{
            return false;
         }
    }
     //To get the Winhms XML string
    public function getWinhmsString($winhms_id)
    {
        $resp = WinhmsReservation::where('id',$winhms_id)->select('winhms_string')->first();
        if(isset($resp->winhms_string))
        {
            return $resp->winhms_string;
        }
        else
        {
            return false;
        }
    }
     //Get Winhms status (Exist or not)

    //Preparing the Winhms XML string and save this to wonhms reservation table
    public function winhmsBookings($hotel_id,$type,$booking_data,$customer_data,$booking_status)
    {
        $winhms_hotel_code = $this->getWinhmsHotel($hotel_id);
        $data["hotel_name"] = HotelInformation::select('hotel_name','hotel_id')->where('hotel_id', $hotel_id)->first();
        $hotel_name = $data["hotel_name"]->hotel_name;
        if($booking_status == 'Commit'){
                $status = 'Commit';
                $type_of_book = 'OTA_HotelResNotifRQ';//for new booking
                $hotel_reservations = 'HotelReservations';
                $hotel_reservation = 'HotelReservation';
                $send_status = 'new';
        }else if($booking_status == 'Modify'){
                $status = 'Modify';
                $type_of_book = 'OTA_HotelResModifyNotifRQ';//for modified booking 
                $hotel_reservations = 'HotelResModifies';
                $hotel_reservation = 'HotelResModify';
                $send_status = 'modified';
        }
        // name should be proper
        $push_bookings_xml = '<?xml version="1.0" encoding="UTF-8"?> 
                                <'.$type_of_book.' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns="http://www.opentravel.org/OTA/2003/05" EchoToken="3ac8497f-3a21-4925-b5de-08af72cdc5fd" TimeStamp="'.date('Y-m-d').'T00:00:00.00+05:30" Version="1.0" ResStatus="'.$status.'">
                                    <POS>
                                        <Source>
                                        <RequestorID ID="CHANNEL MANAGER" />
                                        <BookingChannel Type="'.$type.'">
                                            <CompanyName>'.$type.'</CompanyName>
                                        </BookingChannel>
                                        </Source>
                                    </POS>
                                    <'.$hotel_reservations .'>
                                        <'.$hotel_reservation.' CreateDateTime="'.date('Y-m-d H:i:s').'">
                                            <UniqueID ID="'.$booking_data["booking_id"].'" Type="14" ID_Context="'.$booking_data["booking_id"].'" />
                                            <RoomStays>';
                $tot_amt=0;
                $tax_amt=0;
                $i = 0;
                foreach($booking_data['room_stay'] as $room_data)
                {
                $rate_plan_id = $room_data['rate_plan_id'];
                $winhms_room_type_db = $this->winhmsRoomCode($room_data['room_type_id'],$winhms_hotel_code);
                $winhms_room_type = (empty($winhms_room_type_db)) ? 'NA' : $winhms_room_type_db;
                $cond = ['rate_plan_id'=>$rate_plan_id,'hotel_id'=>$hotel_id];
                $get_rate_plan = DB::table('cmlive.winhms_rate_plan')->select('plan_type','plan_name')->where($cond)->first();
                $wimhms_plan_type = (empty($get_rate_plan->plan_type)) ? 'NA' : $get_rate_plan->plan_type; 
                $wimhms_plan_name = (empty($get_rate_plan->plan_name)) ? 'NA' : $get_rate_plan->plan_name;
                $book_id_array[] = $booking_data['booking_id'].$i;
                $push_bookings_xml .= '<RoomStay roomstaystatus="'.$send_status.'" roomreservation_id="'.$booking_data['booking_id'].$i.'" MarketCode="FIT" SourceOfBusiness="OTA">
                    <BasicPropertyInfo HotelCode="'.$winhms_hotel_code.'" HotelName="'.$hotel_name.'" />
                    <RatePlans>
                        <RatePlan RatePlanCode="'.$wimhms_plan_type.'" RatePlanName="'.$wimhms_plan_name.'" />
                    </RatePlans>
                    <RoomTypes>
                        <RoomType NumberOfUnits="'.$room_data["no_of_rooms"].'" RoomTypeCode="'.$winhms_room_type.'">
                            <RoomDescription Name="" /> 
                        </RoomType>
                    </RoomTypes>
                    <GuestCounts>
                        <GuestCount AgeQualifyingCode="10" Count="'.$room_data['adults'].'" />
                    </GuestCounts>
                    <ResGuestRPHs>
                        <ResGuestRPH RPH="0" />
                    </ResGuestRPHs>
                    <TimeSpan Start="'.$room_data['from_date'].'" End="'.$room_data['to_date'].'" />
                    <RoomRates><RoomRate><Rates>';
                        $tot_amt=0;
                        $tax_amt=0;
                        foreach($room_data['rates'] as $book_data)
                        {
                            if($type == "Booking.com" || $type == "Expedia"){
                                $before_paid_amount = $book_data['amount'];
                                $paid_amount = $book_data['amount'] + $book_data['tax_amount'];
                            }else{
                                $paid_amount = $book_data['amount'];
                                $before_paid_amount = $book_data['amount'] - $book_data['tax_amount'];//before tax
                            }
                            $tot_amt+=$paid_amount;
                            $tax_amt+=$book_data['tax_amount'];
                            // need to put +/- for booking.com and expedia the amount is not containing gst but for goibibo and rest amount contain gst also they do not provide room type wise price you have to calculate.
                            $push_bookings_xml .= '<Rate EffectiveDate="'.$book_data['from_date'].'">
                                        <Base AmountBeforeTax="'.$before_paid_amount.'"
                                            AmountAfterTax="'.$paid_amount.'"
                                            CurrencyCode="INR" />
                                    </Rate>';
                        }
                        $amt_before_tax = $tot_amt-$tax_amt;
                        // need to put +/- for booking.com and expedia the amount is not containing gst but for goibibo and rest amount contain gst 

                            $push_bookings_xml .= '</Rates></RoomRate></RoomRates>
                                                    <Total AmountBeforeTax="'.$amt_before_tax.'" AmountAfterTax="'.$tot_amt.'" CurrencyCode="INR" />
                                                    <DepositPayments>
                                                        <GuaranteePayment>
                                                            <AmountPercent Amount="" CurrencyCode="INR" />
                                                        </GuaranteePayment>
                                                    </DepositPayments>
                                                    <Comments>
                                                        <Comment Name="">
                                                            <Text></Text>
                                                        </Comment>
                                                    </Comments>
                                                </RoomStay>';
                                                $i++;
                }
                $push_bookings_xml .= '</RoomStays>
                        <ResGuests>
                            <ResGuest ResGuestRPH="0">
                                <Profiles>
                                    <ProfileInfo>
                                        <UniqueID ID="1" ID_Context="'.$type.'" />
                                        <Profile ProfileType="1">
                                            <Customer>
                                                <PersonName>
                                                    <NameTitle></NameTitle>
                                                    <GivenName>'.$customer_data['first_name'].'</GivenName>
                                                    <Surname>'.$customer_data['last_name'].'</Surname>
                                                </PersonName>
                                                <Telephone PhoneNumber="'.$customer_data['mobile'].'" PhoneTechType="1" />
                                                <Email>'.$customer_data['email_id'].'</Email>
                                                <Address>
                                                    <AddressLine></AddressLine>
                                                    <CityName></CityName>
                                                    <CountryName Code=""></CountryName>
                                                    <PostalCode></PostalCode>
                                                </Address>
                                                <PaymentForm>
                                                    <PaymentCard CardCode="" ExpireDate="">
                                                        <CardHolderName></CardHolderName>
                                                        <CardType></CardType>
                                                        <CardNumber></CardNumber>
                                                    </PaymentCard>
                                                </PaymentForm>
                                            </Customer>
                                        </Profile>
                                    </ProfileInfo>
                                </Profiles>
                            </ResGuest>
                        </ResGuests>
                        <ResGlobalInfo>
                            <Comments>
                                <Comment>
                                    <Text></Text>
                                </Comment>
                            </Comments>
                            <Total AmountBeforeTax="'.$amt_before_tax.'" AmountAfterTax="'.$tot_amt.'" CurrencyCode="INR" >
                                <Taxes>
                                    <Tax Amount="'.$tot_amt.'" CurrencyCode="INR" />
                                </Taxes>
                            </Total>
                        </ResGlobalInfo>
                    </'.$hotel_reservation.'>
                    </'.$hotel_reservations.'>
                    </'.$type_of_book.'>';
        $winhms=new WinhmsReservation();
        // $winhms_book_tracker = new WinhmsBookingTracker();
        // $data_tracker['booking_id'] = $booking_data['booking_id'];
        // $data_tracker['room_reserv_id'] = implode(',',$book_id_array);
        // $book_track_data_save = $winhms_book_tracker->fill($data_tracker)->save();
        $data['winhms_hotel_code'] = $winhms_hotel_code;
        $data['booking_id'] = $booking_data['booking_id'];
        $data['hotel_id']=$hotel_id;
        $data['winhms_string']=$push_bookings_xml;
        $data['winhms_cancellation_string'] = '';
        $data['winhms_confirm']=0;
        if($winhms->fill($data)->save())
        {
            return $winhms->id;
        }else{
            return false;
        }
    }
    public function pushReservations($xml,$winhms_id)
    {
        $url="https://wincloudbkgs.winsarclients.com/Booking.aspx";
        $headers = array (
        //Regulates versioning of the XML interface for the API
        'Content-Type: application/xml',
        'Accept: application/xml'
        );
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml);
        $result = curl_exec($ch);
        curl_close($ch);
        //$array_data = json_decode(json_encode(simplexml_load_string($result)), true);
        dd($result,$xml);
        if(isset($array_data['Success'])){
            if(WinhmsReservation::where('id',$winhms_id)->update(['winhms_confirm'=>1])){
                return true;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }
    public function winhmsRoomCode($room_type_id,$hotel_code)
    {
        $winhms_room = WinhmsRoom::select('winhms_room_type_code')->where(['room_type_id'=>$room_type_id,'winhms_hotel_code'=>$hotel_code])->first();
        return $winhms_room['winhms_room_type_code'];
    }
    public function ratePlan($rate_plan_id)
    {
        $plan = MasterRatePlan::select('plan_type')->where('rate_plan_id','=',$rate_plan_id)->first();
        return $plan['plan_type'];
    }
    //Get IDS status (Exist or not)
    public function getWinhmsHotel($hotel_id)
    {
        $resp=WinhmsRoom::where('hotel_id',$hotel_id)->select('winhms_hotel_code')->first();
        if($resp)
        {
            return $resp->winhms_hotel_code;
        }else{
            return false;
        }
    }
    public static function checkInvTypeCode($room_code, $hotel_code){
        $model = new WinhmsRoom();
         $winhms_room = $model->select('room_type_id')
                                ->where('winhms_room_type_code',$room_code)
                                ->where('winhms_hotel_code',$hotel_code)
                                ->first();
          if(isset($winhms_room->room_type_id))
          {
            return  $winhms_room->room_type_id;
          }
          else
          {
            return '';
          }

    }
    public static function WinhmsHotel($hotel_code){
        $model = new WinhmsRoom();
         $winhms_hotel = $model->select('hotel_id')
                                ->where('winhms_hotel_code',$hotel_code)
                                ->first();
            if(!isset($winhms_hotel->hotel_id)){
                $hotel_id = '';
            }else{
                $hotel_id = $winhms_hotel->hotel_id;
            }
         return  $hotel_id;
    }
// public function actionUpdateInventory(Request $request)
// {
//     $postdata = trim($request->getcontent());
//     $push_array_data = json_decode(json_encode(simplexml_load_string($postdata)), true);
//     $api_key = $request->header('key');
//     $version = $push_array_data['@attributes']['Version'];
//     $timestamp = $push_array_data['@attributes']['TimeStamp'];
//     $token = $push_array_data['@attributes']['EchoToken'];
//     $hotel_id = $this->PmsComponents->idsHotel($push_array_data['Inventories']['@attributes']['HotelCode']);
//     $update_cm = 'yes';
//     $ip = $_SERVER['REMOTE_ADDR'];
//     $cur_date = date('Y-m-d');
//     $inv_content ='';
//     //print_r($push_array_data);exit;

//     //POST METHOD CHECK
//     if($api_key!='' && $hotel_id!='')
//     {
//     $checkApi = $this->PmsComponents->checkApi($api_key);
//     $hotels = $checkApi->hotels;
//     $hotels = explode(',',$hotels);
//     if(in_array($hotel_id, $hotels))
//     {
//     $flag=0;
//     $inven=$push_array_data['Inventories']['Inventory'];
//     $inv_array = '<OTA_HotelInvCountNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05"
//         xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
//         EchoToken="'.$api_key.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
//         <Inventories HotelCode="'.$hotel_id.'">';

//             if(isset($inven[0]))
//             {
//                 foreach($inven as $Inv)
//                 {
//                     $date_from = $Inv['StatusApplicationControl']['@attributes']['Start'];
//                     $date_to = $Inv['StatusApplicationControl']['@attributes']['End'];
//                     $chk_inv=$this->PmsComponents->checkInvTypeCode($Inv['StatusApplicationControl']['@attributes']['InvTypeCode'],
//                     $push_array_data['Inventories']['@attributes']['HotelCode']);
//                     if($chk_inv!='')
//                     {
//                         $room_type_id = $chk_inv;
//                         if(isset($Inv['InvCounts'][0]))
//                         {
//                             $no_of_rooms = $Inv['InvCounts'][0]['@attributes']['Count'];
//                         }else{
//                             $no_of_rooms = $Inv['InvCounts']['InvCount']['@attributes']['Count'];
//                         }
//                         if(strtotime($date_to)>=strtotime($date_from) && strtotime($date_to)>=strtotime($cur_date)){
//                             $inv_content.='<Inventory>
//                                 <StatusApplicationControl Start="'.$date_from.'" End="'.$date_to.'" InvTypeCode="'.$room_type_id.'" />
//                                 <InvCounts>
//                                     <InvCount Count="'.$no_of_rooms.'" />
//                                 </InvCounts>
//                             </Inventory>';
//                         }else{
//                             $return_array = '<OTA_HotelInvCountNotifRS xmlns="http://www.opentravel.org/OTA/2003/05"
//                             xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
//                             EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
//                             <Error>End date should be greater than Start Date or current date</Error>
//                             </OTA_HotelInvCountNotifRS>';
//                             echo $return_array;
//                         }
//                     }else{
//                         $return_array = '<OTA_HotelInvCountNotifRS xmlns="http://www.opentravel.org/OTA/2003/05"
//                             xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
//                             EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
//                             <Error>Above InvTypeCode not configured.</Error>
//                         </OTA_HotelInvCountNotifRS>';
//                         echo $return_array;
//                     }
//                 }
//             }else{
//                 $date_from = $inven['StatusApplicationControl']['@attributes']['Start'];
//                 $date_to = $inven['StatusApplicationControl']['@attributes']['End'];
//                 $chk_inv=$this->PmsComponents->checkInvTypeCode($inven['StatusApplicationControl']['@attributes']['InvTypeCode'],
//                 $push_array_data['Inventories']['@attributes']['HotelCode']);
//                 if($chk_inv!=''){
//                     $room_type_id = $chk_inv;
//                     if(isset($inven['InvCounts'][0])){
//                         $no_of_rooms = $inven['InvCounts'][0]['@attributes']['Count'];
//                     }else{
//                         $no_of_rooms = $inven['InvCounts']['InvCount']['@attributes']['Count'];
//                     }
//                     if(strtotime($date_to)>=strtotime($date_from) && strtotime($date_to)>=strtotime($cur_date))
//                     {
//                     $inv_content='<Inventory>
//                         <StatusApplicationControl Start="'.$date_from.'" End="'.$date_to.'" InvTypeCode="'.$room_type_id.'" />
//                         <InvCounts>
//                             <InvCount Count="'.$no_of_rooms.'" />
//                         </InvCounts>
//                         </Inventory>';
//                     }else{
//                         $return_array = '<OTA_HotelInvCountNotifRS xmlns="http://www.opentravel.org/OTA/2003/05"
//                         xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
//                         EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
//                         <Error>End Date should be equal or greater than current date or start date</Error>
//                         </OTA_HotelInvCountNotifRS>';
//                         echo $return_array;
//                     }
//                 }else{
//                     $return_array = '<OTA_HotelInvCountNotifRS xmlns="http://www.opentravel.org/OTA/2003/05"
//                     xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
//                     EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
//                     <Error>Above InvTypeCode not configured.</Error>
//                     </OTA_HotelInvCountNotifRS>';
//                     echo $return_array;
//                 }

//             }

//             $inv=$inv_array.$inv_content.'</Inventories></OTA_HotelInvCountNotifRQ>';
//             $url='https://cm.bookingjini.com/ids/update-inv';
//             $da=$this->curlPost($url,$inv);
//             if($da){
//                 $return_array = '<OTA_HotelInvCountNotifRS xmlns="http://www.opentravel.org/OTA/2003/05"
//                 xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
//                 EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
//                 <Success />
//                 </OTA_HotelInvCountNotifRS>';
//                 echo $return_array;
//             }else{
//                 $return_array = '<OTA_HotelInvCountNotifRS xmlns="http://www.opentravel.org/OTA/2003/05"
//                     xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
//                     EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
//                     <Failure />
//                 </OTA_HotelInvCountNotifRS>';
//                 echo $return_array;
//             }

//         }else{
//             $return_array = '<OTA_HotelInvCountNotifRS xmlns="http://www.opentravel.org/OTA/2003/05"
//                 xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
//                 EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
//                 <Error>Hotel Not Found</Error>
//             </OTA_HotelInvCountNotifRS>';
//             echo $return_array;
//         }
//     }else{
//         $return_array = '<OTA_HotelInvCountNotifRS xmlns="http://www.opentravel.org/OTA/2003/05"
//             xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
//             EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
//             <Error>Please set key in header</Error>
//             </OTA_HotelInvCountNotifRS>';
//             echo $return_array;
//     }

//     }// actionUpdateInventory closed here.
//cancel booking  details should be come from cm ota booking table not from user table invoice id for booking engine not for cm.
    public function winhmsCancelBooking($hotel_id,$type,$booking_data,$customer_data,$booking_status){
                $winhms_hotel_code = $this->getWinhmsHotel($hotel_id);
                $data["hotel_name"] = HotelInformation::select('hotel_name','hotel_id')->where('hotel_id', $hotel_id)->first();
                $hotel_name = $data["hotel_name"]->hotel_name;
                $booking_id = $booking_data['booking_id'];
                $token = '3ac8497f-3a21-4925-b5de-08af72cdc5fd';
                $timestamp = date('Y-m-d\TH:i:sP');
                foreach($booking_data['room_stay'] as $room_data)
                {
                    $from_date = $room_data['from_date'];
                    $to_date = $room_data['to_date'];
                }
                $push_cancell_xml = '<?xml version="1.0" encoding="UTF-8" ?>
                            <OTA_CancelRQ xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                            xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns="http://www.opentravel.org/OTA/2003/05"
                            EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="0" CancelType="Cancel">
                            <POS>
                            <Source>
                            <RequestorID ID="'.$booking_id.'" Type="CRSConfirmNumber" />
                            <BookingChannel Type="Winhms">
                            <CompanyName>'.$type.'</CompanyName>
                            </BookingChannel>
                            </Source>
                            </POS>
                            <UniqueID ID="'.$booking_id.'" Type="14" ID_Context="CRSConfirmNumber" />
                            <Verification>
                            <PersonName>
                            <GivenName>'.$customer_data['first_name'].'</GivenName>
                            <Surname>'.$customer_data['last_name'].'</Surname>
                            </PersonName>
                            <ReservationTimeSpan Start="'.$from_date.'" End="'.$to_date.'"/>
                            <TPA_Extensions>
                            <BasicPropertyInfo HotelCode="'.$winhms_hotel_code.'" HotelName="'.$hotel_name.'" />
                            </TPA_Extensions>
                            </Verification>
                            </OTA_CancelRQ>';
                $winhms=new WinhmsReservation();
                // $winhms_book_tracker = new WinhmsBookingTracker();
                // $data_tracker['booking_id'] = $booking_data['booking_id'];
                // $data_tracker['room_reserv_id'] = implode(',',$book_id_array);
                // $book_track_data_save = $winhms_book_tracker->fill($data_tracker)->save();
                $data['winhms_hotel_code'] = $winhms_hotel_code;
                $data['booking_id'] = $booking_id;
                $data['hotel_id'] = $hotel_id;
                $data['winhms_string'] = '';
                $data['winhms_confirm'] = 0;
                $data['winhms_cancellation_string'] = $push_cancell_xml;
                if($winhms->fill($data)->save())
                {
                    return $winhms->id;
                }else{
                    return false;
                }
        }
    public function curlPost($URL,$data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $URL);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/xml'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }
} // PmsController closed here