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
use App\Http\Controllers\IpAddressService;
use App\Http\Controllers\PmsComponentController;
#otaautopushcontroller
class   extends Controller
{
    protected $PmsComponents,$ipService;
    public function __construct(PmsComponentController $PmsComponents,IpAddressService $ipService)
    {
       $this->ipService  = $ipService;
       $this->PmsComponents = $PmsComponents;
    }
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
    //To get Winhms Status (Exists or Not)
    public function getWinhmsStatus($hotel_id)
    {
        $resp=DB::table('pms_account')->where('name','Winhms')->select('hotels')->first();

         if(strpos($resp->hotels, "$hotel_id") > 0){
             return true;
         } else{
            return false;
         }
    }
     //To get the Winhms XML string
    public function getWinhmsString($ids_id)
    {
        $resp = WinhmsReservation::where('id',$ids_id)->select('ids_string')->first();
        if($resp->ids_string)
        {
            return $resp->ids_string;
        }
        else
        {
            return false;
        }
    }
     //Get Winhms status (Exist or not)

    //Preparing the Winhms XML string and save this to ids reservation table
    public function winhmsBookings($hotel_id,$type,$booking_data,$customer_data,$booking_status)
    {
        $ids_hotel_code=$this->getIdsHotel($hotel_id);
        $data["hotel_name"] = HotelInformation::select('hotel_name,hotel_id')->where('hotel_id', $hotel_id)->first();
        $hotel_name = $data["hotel_name"]->hotel_name;
        $push_bookings_xml_1 = '<?xml version="1.0" encoding="UTF-8"?>
        <OTA_HotelResNotifRQ xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"
            xmlns="http://www.opentravel.org/OTA/2003/05" EchoToken="" TimeStamp="'.date('Y-m-d').'T00:00:00.00+05:30"
            Version="3.002" ResStatus="Commit">
            <POS>
                <Source>
                <RequestorID ID="'.$booking_data['booking_id'].'" Type="'.$booking_data['booking_id'].'" />
                <BookingChannel Type="'.$type.'">
                    <CompanyName>'.$type.'</CompanyName>
                </BookingChannel>
                </Source>
            </POS>
        <HotelReservations>
        <HotelReservation CreateDateTime="">
            <UniqueID ID="'.$booking_data[" booking_id"].'" Type="14" ID_Context="'.$booking_data[" booking_id"].'" />
            <RoomStays>';
                $tot_amt=0;
                $tax_amt=0;
                $i = 0;
                foreach($booking_data['room_stay'] as $room_data)
                {
                $rate_plan_id = $room_data['rate_plan_id'];
                $ids_room_type = $this->winhmsRoomCode($room_data['room_type_id']);
                $ids_plan_type= $this->RatePlan($room_data['rate_plan_id']);
                $get_rate_plan =
                MasterRatePlan::select('plan_type,plan_name')->where('rate_plan_id',$rate_plan_id)->first();
                $wimhms_plan_type = $get_rate_plan->plan_type;
                $wimhms_plan_name = $get_rate_plan->plan_name;

                $push_bookings_xml_1 .= '<RoomStay MarketCode="'.$ids_room_type.'" SourceOfBusiness=""
                    IndexNumber="'.$i.'">
                    <BasicPropertyInfo HotelCode="'.$ids_hotel_code.'" HotelName="'.$hotel_name.'" />
                    <RatePlans>
                        <RatePlan RatePlanCode="'.$wimhms_plan_type.'" RatePlanName="'.$ids_plan_type.'" />
                    </RatePlans>
                    <RoomTypes>
                        <RoomType NumberOfUnits="'.$room_data[" no_of_rooms"].'" RoomTypeCode="'.$ids_room_type.'">
                            <RoomDescription Name="" />
                        </RoomType>
                    </RoomTypes>
                    <GuestCounts>
                        <GuestCount AgeQualifyingCode="10" Count="'.$room_data['adults'].'" />
                    </GuestCounts>
                    <ResGuestRPHs>
                        <ResGuestRPH RPH="0" />
                    </ResGuestRPHs>
                    <TimeSpan Start="" End="" />
                    <RoomRates>';
                        $tot_amt=0;
                        $tax_amt=0;
                        foreach($room_data['rates'] as $book_data)
                        {

                        $tot_amt+=$book_data['amount'];
                        $tax_amt+=$book_data['tax_amount'];

                        $push_bookings_xml_1 = '<RoomRate>
                            <Rates>
                                <Rate EffectiveDate="">
                                    <Base AmountBeforeTax="'.$book_data['amount'].'"
                                        AmountAfterTax="'.($book_data['amount']+$book_data['tax_amount']).'"
                                        CurrencyCode="INR" />
                                </Rate>
                            </Rates>]';
                            }
                            $push_bookings_xml_1 = '
                        </RoomRate>
                    </RoomRates>
                    <Total AmountBeforeTax="'.$tot_amt.'" AmountAfterTax="'.($tot_amt+$tax_amt).'" CurrencyCode="INR" />
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
                $push_bookings_xml_1 .= '</RoomStays>
                        <ResGuests>
                            <ResGuest ResGuestRPH="">
                                <Profiles>
                                    <ProfileInfo>
                                        <UniqueID ID="'.$ota_id.'" Type="'.$ota_id.'" ID_Context="Guest Profile ID" />
                                        <Profile ProfileType="">
                                            <Customer>
                                                <PersonName>
                                                    <NameTitle></NameTitle>
                                                    <GivenName>'.$customer_data['first_name'].'</GivenName>
                                                    <Surname>'.$customer_data['last_name'].'</Surname>
                                                </PersonName>
                                                <Telephone PhoneNumber="" PhoneTechType="1" />
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
                            <Total AmountBeforeTax="'.$tot_amt.'" AmountIncludingMarkup="'.($tot_amt+$tax_amt).'"
                                CurrencyCode="INR">
                                <Taxes>
                                    <Tax Amount="'.($tax_amt).'" CurrencyCode="INR" />
                                </Taxes>
                            </Total>
                        </ResGlobalInfo>
                    </HotelReservation>
                </HotelReservations>
            </OTA_HotelResNotifRQ>';
            $ids=new WinhmsReservation();
            $data['hotel_id']=$hotel_id;
            $data['ids_string']=$push_bookings_xml_1;

            if($ids->fill($data)->save()){
                return $ids->id;
            }else{
                return false;
            }
    }
public function pushIDSDetails(){
        $xml = '
        <?xml version="1.0" encoding="UTF-8"?>
        <OTA_HotelResModifyNotifRQ xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
            xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns="http://www.opentravel.org/OTA/2003/05" EchoToken="" TimeStamp=""
            Version="" ResStatus="Modify">
            <POS>
                <Source>
                <RequestorID ID="" Type="" />
                <BookingChannel Type="">
                    <CompanyName>Channel Manager/OTA Name</CompanyName>
                </BookingChannel>
                </Source>
            </POS>
            <HotelResModifies>
                <HotelResModify CreateDateTime="">
                    <UniqueID ID="" Type="14" ID_Context="Channel Manager ID" />
                    <RoomStays>
                        <RoomStay MarketCode="" SourceOfBusiness="" IndexNumber="">
                            <BasicPropertyInfo HotelCode="" HotelName="" />
                            <RatePlans>
                                <RatePlan RatePlanCode="" RatePlanName="" />
                            </RatePlans>
                            <RoomTypes>
                                <RoomType NumberOfUnits="" RoomTypeCode="">
                                    <RoomDescription Name="" />
                                </RoomType>
                            </RoomTypes>
                            <GuestCounts>
                                <GuestCount AgeQualifyingCode="" Count="" />
                                <GuestCount AgeQualifyingCode="" Count="" />
                            </GuestCounts>
                            <ResGuestRPHs>
                                <ResGuestRPH RPH="0" />
                            </ResGuestRPHs>
                            <TimeSpan Start="" End="" />
                            <RoomRates>
                                <RoomRate>
                                    <Rates>
                                        <Rate EffectiveDate="">
                                            <Base AmountBeforeTax="" AmountAfterTax="" CurrencyCode="" />
                                        </Rate>
                                        <Rate EffectiveDate="">
                                            <Base AmountBeforeTax="" AmountAfterTax="" CurrencyCode="" />
                                        </Rate>
                                    </Rates>
                                </RoomRate>
                            </RoomRates>
                            <Total AmountBeforeTax="" AmountAfterTax="" CurrencyCode="" />
                            <DepositPayments>
                                <GuaranteePayment>
                                    <AmountPercent Amount="" CurrencyCode="" />
                                </GuaranteePayment>
                            </DepositPayments>
                            <Comments>
                                <Comment Name="">
                                    <Text></Text>
                                </Comment>
                            </Comments>
                        </RoomStay>
                    </RoomStays>
                    <ResGuests>
                        <ResGuest ResGuestRPH="">
                            <Profiles>
                                <ProfileInfo>
                                    <UniqueID ID="" Type="" ID_Context="Guest Profile ID" />
                                    <Profile ProfileType="">
                                        <Customer>
                                            <PersonName>
                                                <NameTitle></NameTitle>
                                                <GivenName></GivenName>
                                                <Surname></Surname>
                                            </PersonName>
                                            <Telephone PhoneNumber="" PhoneTechType="1" />
                                            <Telephone PhoneNumber="" PhoneTechType="5" />
                                            <Email></Email>
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
                        <Total AmountBeforeTax="" AmountIncludingMarkup="" CurrencyCode="">
                            <Taxes>
                                <Tax Amount="" CurrencyCode="" />
                            </Taxes>
                        </Total>
                    </ResGlobalInfo>
                </HotelResModify>
            </HotelResModifies>
        </OTA_HotelResModifyNotifRQ>';
        $ids_id = 52896;
        $res = $this->pushReservations($xml,$ids_id);
        var_dump($xml,' ',$res);
    }
    public function pushReservations($xml,$ids_id)
    {
        $url="http://idsnextchannelmanagerapi.azurewebsites.net/BookingJini/ReservationDelivery";
        $headers = array (
        //Regulates versioning of the XML interface for the API
        'Content-Type: application/xml',
        'Accept: application/xml',
        'Authorization:Basic aWRzbmV4dEJvb2tpbmdqaW5pQGlkc25leHQuY29tOmlkc25leHRCb29raW5namluaUAwNzExMjAxNw=='
        );
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml);
        $result = curl_exec($ch);
        curl_close($ch);
        $array_data = json_decode(json_encode(simplexml_load_string($result)), true);
        var_dump($result);
        if(isset($array_data['Success'])){
            if(IdsReservation::where('id',$ids_id)->update(['ids_confirm'=>1])){
                return true;
            }
            else{
                return false;
            }
        }else{
            return false;
        }
    }
    public function winhmsRoomCode($room_type_id)
    {
        $ids_room = WinhmsRoom::select('ids_room_type_code')->where('room_type_id','=',$room_type_id)->first();
        return $ids_room['ids_room_type_code'];
    }
    public function ratePlan($rate_plan_id)
    {
        $plan = MasterRatePlan::select('plan_type')->where('rate_plan_id','=',$rate_plan_id)->first();
        return $plan['plan_type'];
    }
    //Get IDS status (Exist or not)
    public function getWinhmsHotel($hotel_id)
    {
        $resp=WinhmsRoom::where('hotel_id',$hotel_id)->select('ids_hotel_code')->first();
        if($resp)
        {
            return $resp->ids_hotel_code;
        }
        else
        {
            return false;
        }
    }
    public function actionUpdateInventory(Request $request)
    {
        $postdata = trim($request->getcontent());
        $push_array_data = json_decode(json_encode(simplexml_load_string($postdata)), true);
        $api_key = $request->header('key');
        $version = $push_array_data['@attributes']['Version'];
        $timestamp = $push_array_data['@attributes']['TimeStamp'];
        $token = $push_array_data['@attributes']['EchoToken'];
        $hotel_id = $this->PmsComponents->idsHotel($push_array_data['Inventories']['@attributes']['HotelCode']);
        $update_cm = 'yes';
        $ip = $_SERVER['REMOTE_ADDR'];
        $cur_date = date('Y-m-d');
        $inv_content ='';
        //print_r($push_array_data);exit;

        //POST METHOD CHECK
        if($api_key!='' && $hotel_id!='')
        {
            $checkApi = $this->PmsComponents->checkApi($api_key);
            $hotels = $checkApi->hotels;
            $hotels = explode(',',$hotels);
        if(in_array($hotel_id, $hotels))
        {
            $flag=0;
            $inven=$push_array_data['Inventories']['Inventory'];
            $inv_array = '<OTA_HotelInvCountNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05"
                xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                EchoToken="'.$api_key.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
                <Inventories HotelCode="'.$hotel_id.'">';

            if(isset($inven[0]))
            {
            foreach($inven as $Inv)
            {
                $date_from = $Inv['StatusApplicationControl']['@attributes']['Start'];
                $date_to = $Inv['StatusApplicationControl']['@attributes']['End'];
                $chk_inv=$this->PmsComponents->checkInvTypeCode($Inv['StatusApplicationControl']['@attributes']['InvTypeCode'],
                $push_array_data['Inventories']['@attributes']['HotelCode']);
            if($chk_inv!='')
            {
                $room_type_id = $chk_inv;
            if(isset($Inv['InvCounts'][0]))
            {
                $no_of_rooms = $Inv['InvCounts'][0]['@attributes']['Count'];
            }
            else
            {
                $no_of_rooms = $Inv['InvCounts']['InvCount']['@attributes']['Count'];
            }
            if(strtotime($date_to)>=strtotime($date_from) && strtotime($date_to)>=strtotime($cur_date))
            {
                $inv_content.='<Inventory>
                    <StatusApplicationControl Start="'.$date_from.'" End="'.$date_to.'" InvTypeCode="'.$room_type_id.'" />
                    <InvCounts>
                        <InvCount Count="'.$no_of_rooms.'" />
                    </InvCounts>
                </Inventory>';
            }
            else
            {
                $return_array = '<OTA_HotelInvCountNotifRS xmlns="http://www.opentravel.org/OTA/2003/05"
                    xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                    EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
                    <Error>End date should be greater than Start Date or current date</Error>
                </OTA_HotelInvCountNotifRS>';
                echo $return_array;
            }
            }
            else
            {
                $return_array = '<OTA_HotelInvCountNotifRS xmlns="http://www.opentravel.org/OTA/2003/05"
                    xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                    EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
                    <Error>Above InvTypeCode not configured.</Error>
                </OTA_HotelInvCountNotifRS>';
                echo $return_array;
            }
            }
            }
            else
            {
                $date_from = $inven['StatusApplicationControl']['@attributes']['Start'];
                $date_to = $inven['StatusApplicationControl']['@attributes']['End'];
                $chk_inv=$this->PmsComponents->checkInvTypeCode($inven['StatusApplicationControl']['@attributes']['InvTypeCode'],
                $push_array_data['Inventories']['@attributes']['HotelCode']);
            if($chk_inv!='')
            {
                $room_type_id = $chk_inv;
            if(isset($inven['InvCounts'][0]))
            {
                $no_of_rooms = $inven['InvCounts'][0]['@attributes']['Count'];
            }
            else
            {
                $no_of_rooms = $inven['InvCounts']['InvCount']['@attributes']['Count'];
            }
            if(strtotime($date_to)>=strtotime($date_from) && strtotime($date_to)>=strtotime($cur_date))
            {
                $inv_content='<Inventory>
                    <StatusApplicationControl Start="'.$date_from.'" End="'.$date_to.'" InvTypeCode="'.$room_type_id.'" />
                    <InvCounts>
                        <InvCount Count="'.$no_of_rooms.'" />
                    </InvCounts>
                </Inventory>';
            }
            else
            {
                $return_array = '<OTA_HotelInvCountNotifRS xmlns="http://www.opentravel.org/OTA/2003/05"
                    xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                    EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
                    <Error>End Date should be equal or greater than current date or start date</Error>
                </OTA_HotelInvCountNotifRS>';
                echo $return_array;
            }
            }
            else
            {
                $return_array = '<OTA_HotelInvCountNotifRS xmlns="http://www.opentravel.org/OTA/2003/05"
                    xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                    EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
                    <Error>Above InvTypeCode not configured.</Error>
                </OTA_HotelInvCountNotifRS>';
                echo $return_array;
            }

            }

            $inv=$inv_array.$inv_content.'</Inventories>
    </OTA_HotelInvCountNotifRQ>';
    $url='https://cm.bookingjini.com/ids/update-inv';
    $da=$this->curlPost($url,$inv);
    if($da)
    {
        $return_array = '<OTA_HotelInvCountNotifRS xmlns="http://www.opentravel.org/OTA/2003/05"
            xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
            EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
            <Success />
        </OTA_HotelInvCountNotifRS>';
        echo $return_array;
    }
    else{
        $return_array = '<OTA_HotelInvCountNotifRS xmlns="http://www.opentravel.org/OTA/2003/05"
            xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
            EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
            <Failure />
        </OTA_HotelInvCountNotifRS>';
        echo $return_array;
    }

    }
    else
    {
        $return_array = '<OTA_HotelInvCountNotifRS xmlns="http://www.opentravel.org/OTA/2003/05"
            xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
            EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
            <Error>Hotel Not Found</Error>
        </OTA_HotelInvCountNotifRS>';
        echo $return_array;
    }
    }else{
        $return_array = '<OTA_HotelInvCountNotifRS xmlns="http://www.opentravel.org/OTA/2003/05"
            xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
            EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
            <Error>Please set key in header</Error>
        </OTA_HotelInvCountNotifRS>';
        echo $return_array;
    }

}// actionUpdateInventory closed here.
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