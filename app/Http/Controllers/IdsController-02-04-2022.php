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
use App\IdsRoom;
use App\MasterRatePlan;
use App\Http\Controllers\IpAddressService;
use App\Http\Controllers\PmsComponentController;
class IdsController extends Controller
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
    //Get IDS status (Exist or not)
    public function getIdsStatus($hotel_id)
    {
        $resp=DB::table('pms_account')->where('name','IDS NEXT')->select('hotels')->first();

         if(strpos($resp->hotels, "$hotel_id") > 0){
             return true;
         } else{
            return false;
         }
    }
    //To get the IDS XML string
    public function getIdsString($ids_id)
    {
        $resp=IdsReservation::where('id',$ids_id)->select('ids_string')->first();
        if($resp->ids_string)
        {
            return $resp->ids_string;
        }
        else
        {
            return false;
        }
    }
     //Get IDS status (Exist or not)

    //Preparing the IDS XML string and save this to ids reservation table
    public function idsBookings($hotel_id,$type,$booking_data,$customer_data,$booking_status)
    {
        $ids_hotel_code=$this->getIdsHotel($hotel_id);
         $push_bookings_xml='<OTA_HotelResNotifRQ xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns="http://www.opentravel.org/OTA/2003/05" EchoToken="f65b2d0b-b772-4496-a138-45267d1c3ae9" TimeStamp="'.date('Y-m-d').'T00:00:00.00+05:30" Version="3.002" ResStatus="'.$booking_status.'">
                        <POS>
                          <Source>
                            <RequestorID Type="22" ID="Bookingjini" />
                            <BookingChannel Type="CHANNEL">
                              <CompanyName Code="BKNG">'.$type.'</CompanyName>
                            </BookingChannel>
                          </Source>
                        </POS>
                        <HotelReservations>
                          <HotelReservation CreateDateTime="'.date('Y-m-d').'T00:00:00.00+05:30">
                            <UniqueID Type="14" ID="'.$booking_data['booking_id'].'" ID_Context="Bookingjini" />
                            <RoomStays>';
                            $tot_amt=0;
                            $tax_amt=0;
                            foreach($booking_data['room_stay'] as $room_data)
                            {
                                $ids_room_type= $this->idsRoomCode($room_data['room_type_id']);
                                $ids_plan_type= $this->RatePlan($room_data['rate_plan_id']);
                                $push_bookings_xml.='<RoomStay>
                                    <RoomTypes>
                                        <RoomType NumberOfUnits="1" RoomTypeCode="'.$ids_room_type.'" />
                                    </RoomTypes>
                                    <RatePlans>
                                    <RatePlan RatePlanCode="1" MealPlanCode="'.$ids_plan_type.'" />
                                    </RatePlans>
                                    <RoomRates>
                                        <RoomRate RoomTypeCode="'.$ids_room_type.'" RatePlanCode="1">';
                                        $tot_amt=0;
                                        $tax_amt=0;
                                        foreach($room_data['rates'] as $book_data)
                                        {

                                            $tot_amt+=$book_data['amount'];
                                            $tax_amt+=$book_data['tax_amount'];
                                            $push_bookings_xml.='<Rates>
                                                <Rate EffectiveDate="'.$book_data["from_date"].'" ExpireDate="'.$book_data["to_date"].'" RateTimeUnit="Day" UnitMultiplier="1">
                                                    <Base AmountAfterTax="'.($book_data["amount"]+$book_data["tax_amount"]).'" AmountBeforeTax="'.$book_data["amount"].'" CurrencyCode="INR">
                                                    <Taxes Amount="'.$book_data["tax_amount"].'" CurrencyCode="INR" />
                                                    </Base>
                                                </Rate>
                                            </Rates>';
                                            // if($hotel_id == 2142){
                                            //   var_dump('rate');
                                            // }
                                        }

                                        $push_bookings_xml.='</RoomRate>
                                    </RoomRates>
                                <GuestCounts IsPerRoom="true">
                                  <GuestCount AgeQualifyingCode="10" Count="'.$room_data["adults"].'" />
                                </GuestCounts>
                                <TimeSpan Start="'.$room_data["from_date"].'" End="'.$room_data["to_date"].'" />
                                <Total AmountIncludingMarkup="'.($tot_amt+$tax_amt).'" AmountAfterTax="'.($tot_amt+$tax_amt).'" AmountBeforeTax="'.$tot_amt.'" CurrencyCode="INR">
                                  <Taxes Amount="'.$tax_amt.'" CurrencyCode="INR" />
                                </Total>
                                <BasicPropertyInfo HotelCode="'.$ids_hotel_code.'" />
                                <ResGuestRPHs>
                                  <ResGuestRPH RPH="1" />
                                </ResGuestRPHs>
                              </RoomStay>';
                              // if($hotel_id == 2142){
                              //   var_dump('log_h');
                              // }
                            }
                                $push_bookings_xml.='</RoomStays>
                            <ResGuests>
                              <ResGuest ResGuestRPH="1">
                                <Profiles>
                                  <ProfileInfo>
                                    <Profile ProfileType="1">
                                      <Customer>
                                        <PersonName>
                                          <GivenName>'.$customer_data['first_name'].'</GivenName>
                                          <Surname>'.$customer_data['last_name'].'</Surname>
                                        </PersonName>
                                        <Telephone PhoneTechType="1" PhoneNumber="'.$customer_data['mobile'].'" FormattedInd="false" DefaultInd="true" />
                                        <Email EmailType="1">'.$customer_data['email_id'].'</Email>
                                        <Address>
                                          <AddressLine>NA</AddressLine>
                                          <CityName>NA</CityName>
                                          <CountryName Code="na">NA</CountryName>
                                        </Address>
                                      </Customer>
                                    </Profile>
                                  </ProfileInfo>
                                </Profiles>
                              </ResGuest>
                            </ResGuests>
                          </HotelReservation>
                        </HotelReservations>
                      </OTA_HotelResNotifRQ>';
                      $ids=new IdsReservation();
                      $data['hotel_id']=$hotel_id;
                      $data['ids_string']=$push_bookings_xml;
                     
                      if($ids->fill($data)->save())
                      {
                            return $ids->id;
                      }
                      else
                      {
                          return false;
                      }
    }
    public function pushIDSDetails(){
      $xml = '<OTA_HotelResNotifRQ xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns="http://www.opentravel.org/OTA/2003/05" EchoToken="f65b2d0b-b772-4496-a138-45267d1c3ae9" TimeStamp="2021-04-19T00:00:00.00+05:30" Version="3.002" ResStatus="Modify">
        <POS>
          <Source>
            <RequestorID Type="22" ID="Bookingjini" />
            <BookingChannel Type="CHANNEL">
              <CompanyName Code="BKNG">Bookingjini</CompanyName>
            </BookingChannel>
          </Source>
        </POS>
        <HotelReservations>
          <HotelReservation CreateDateTime="2021-04-19T00:00:00.00+05:30">
            <UniqueID Type="14" ID="19042129124" ID_Context="Bookingjini" />
            <RoomStays><RoomStay>
                    <RoomTypes>
                        <RoomType NumberOfUnits="1" RoomTypeCode="DLD" />
                    </RoomTypes>
                    <RatePlans>
                    <RatePlan RatePlanCode="1" MealPlanCode="CP" />
                    </RatePlans>
                    <RoomRates>
                        <RoomRate RoomTypeCode="DLD" RatePlanCode="1"><Rates>
                                <Rate EffectiveDate="2021-04-21" ExpireDate="2021-04-22" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="7280" AmountBeforeTax="6500" CurrencyCode="INR">
                                    <Taxes Amount="780" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-04-22" ExpireDate="2021-04-23" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="7280" AmountBeforeTax="6500" CurrencyCode="INR">
                                    <Taxes Amount="780" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-04-23" ExpireDate="2021-04-24" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="7280" AmountBeforeTax="6500" CurrencyCode="INR">
                                    <Taxes Amount="780" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates></RoomRate>
                    </RoomRates>
                <GuestCounts IsPerRoom="true">
                  <GuestCount AgeQualifyingCode="10" Count="2" />
                </GuestCounts>
                <TimeSpan Start="2021-04-21" End="2021-04-24" />
                <Total AmountIncludingMarkup="21840" AmountAfterTax="21840" AmountBeforeTax="19500" CurrencyCode="INR">
                  <Taxes Amount="2340" CurrencyCode="INR" />
                </Total>
                <BasicPropertyInfo HotelCode="3930" />
                <ResGuestRPHs>
                  <ResGuestRPH RPH="1" />
                </ResGuestRPHs>
              </RoomStay><RoomStay>
                    <RoomTypes>
                        <RoomType NumberOfUnits="1" RoomTypeCode="DLD" />
                    </RoomTypes>
                    <RatePlans>
                    <RatePlan RatePlanCode="1" MealPlanCode="CP" />
                    </RatePlans>
                    <RoomRates>
                        <RoomRate RoomTypeCode="DLD" RatePlanCode="1"><Rates>
                                <Rate EffectiveDate="2021-04-21" ExpireDate="2021-04-22" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="7280" AmountBeforeTax="6500" CurrencyCode="INR">
                                    <Taxes Amount="780" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-04-22" ExpireDate="2021-04-23" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="7280" AmountBeforeTax="6500" CurrencyCode="INR">
                                    <Taxes Amount="780" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-04-23" ExpireDate="2021-04-24" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="7280" AmountBeforeTax="6500" CurrencyCode="INR">
                                    <Taxes Amount="780" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates></RoomRate>
                    </RoomRates>
                <GuestCounts IsPerRoom="true">
                  <GuestCount AgeQualifyingCode="10" Count="2" />
                </GuestCounts>
                <TimeSpan Start="2021-04-21" End="2021-04-24" />
                <Total AmountIncludingMarkup="21840" AmountAfterTax="21840" AmountBeforeTax="19500" CurrencyCode="INR">
                  <Taxes Amount="2340" CurrencyCode="INR" />
                </Total>
                <BasicPropertyInfo HotelCode="3930" />
                <ResGuestRPHs>
                  <ResGuestRPH RPH="1" />
                </ResGuestRPHs>
              </RoomStay><RoomStay>
                    <RoomTypes>
                        <RoomType NumberOfUnits="1" RoomTypeCode="DLD" />
                    </RoomTypes>
                    <RatePlans>
                    <RatePlan RatePlanCode="1" MealPlanCode="CP" />
                    </RatePlans>
                    <RoomRates>
                        <RoomRate RoomTypeCode="DLD" RatePlanCode="1"><Rates>
                                <Rate EffectiveDate="2021-04-21" ExpireDate="2021-04-22" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="7280" AmountBeforeTax="6500" CurrencyCode="INR">
                                    <Taxes Amount="780" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-04-22" ExpireDate="2021-04-23" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="7280" AmountBeforeTax="6500" CurrencyCode="INR">
                                    <Taxes Amount="780" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-04-23" ExpireDate="2021-04-24" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="7280" AmountBeforeTax="6500" CurrencyCode="INR">
                                    <Taxes Amount="780" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates></RoomRate>
                    </RoomRates>
                <GuestCounts IsPerRoom="true">
                  <GuestCount AgeQualifyingCode="10" Count="2" />
                </GuestCounts>
                <TimeSpan Start="2021-04-21" End="2021-04-24" />
                <Total AmountIncludingMarkup="21840" AmountAfterTax="21840" AmountBeforeTax="19500" CurrencyCode="INR">
                  <Taxes Amount="2340" CurrencyCode="INR" />
                </Total>
                <BasicPropertyInfo HotelCode="3930" />
                <ResGuestRPHs>
                  <ResGuestRPH RPH="1" />
                </ResGuestRPHs>
              </RoomStay><RoomStay>
                    <RoomTypes>
                        <RoomType NumberOfUnits="1" RoomTypeCode="DLD" />
                    </RoomTypes>
                    <RatePlans>
                    <RatePlan RatePlanCode="1" MealPlanCode="CP" />
                    </RatePlans>
                    <RoomRates>
                        <RoomRate RoomTypeCode="DLD" RatePlanCode="1"><Rates>
                                <Rate EffectiveDate="2021-04-21" ExpireDate="2021-04-22" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="7280" AmountBeforeTax="6500" CurrencyCode="INR">
                                    <Taxes Amount="780" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-04-22" ExpireDate="2021-04-23" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="7280" AmountBeforeTax="6500" CurrencyCode="INR">
                                    <Taxes Amount="780" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-04-23" ExpireDate="2021-04-24" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="7280" AmountBeforeTax="6500" CurrencyCode="INR">
                                    <Taxes Amount="780" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates></RoomRate>
                    </RoomRates>
                <GuestCounts IsPerRoom="true">
                  <GuestCount AgeQualifyingCode="10" Count="2" />
                </GuestCounts>
                <TimeSpan Start="2021-04-21" End="2021-04-24" />
                <Total AmountIncludingMarkup="21840" AmountAfterTax="21840" AmountBeforeTax="19500" CurrencyCode="INR">
                  <Taxes Amount="2340" CurrencyCode="INR" />
                </Total>
                <BasicPropertyInfo HotelCode="3930" />
                <ResGuestRPHs>
                  <ResGuestRPH RPH="1" />
                </ResGuestRPHs>
              </RoomStay><RoomStay>
                    <RoomTypes>
                        <RoomType NumberOfUnits="1" RoomTypeCode="DLD" />
                    </RoomTypes>
                    <RatePlans>
                    <RatePlan RatePlanCode="1" MealPlanCode="CP" />
                    </RatePlans>
                    <RoomRates>
                        <RoomRate RoomTypeCode="DLD" RatePlanCode="1"><Rates>
                                <Rate EffectiveDate="2021-04-21" ExpireDate="2021-04-22" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="7280" AmountBeforeTax="6500" CurrencyCode="INR">
                                    <Taxes Amount="780" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-04-22" ExpireDate="2021-04-23" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="7280" AmountBeforeTax="6500" CurrencyCode="INR">
                                    <Taxes Amount="780" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-04-23" ExpireDate="2021-04-24" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="7280" AmountBeforeTax="6500" CurrencyCode="INR">
                                    <Taxes Amount="780" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates></RoomRate>
                    </RoomRates>
                <GuestCounts IsPerRoom="true">
                  <GuestCount AgeQualifyingCode="10" Count="2" />
                </GuestCounts>
                <TimeSpan Start="2021-04-21" End="2021-04-24" />
                <Total AmountIncludingMarkup="21840" AmountAfterTax="21840" AmountBeforeTax="19500" CurrencyCode="INR">
                  <Taxes Amount="2340" CurrencyCode="INR" />
                </Total>
                <BasicPropertyInfo HotelCode="3930" />
                <ResGuestRPHs>
                  <ResGuestRPH RPH="1" />
                </ResGuestRPHs>
              </RoomStay><RoomStay>
                    <RoomTypes>
                        <RoomType NumberOfUnits="1" RoomTypeCode="DLD" />
                    </RoomTypes>
                    <RatePlans>
                    <RatePlan RatePlanCode="1" MealPlanCode="CP" />
                    </RatePlans>
                    <RoomRates>
                        <RoomRate RoomTypeCode="DLD" RatePlanCode="1"><Rates>
                                <Rate EffectiveDate="2021-04-21" ExpireDate="2021-04-22" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="7280" AmountBeforeTax="6500" CurrencyCode="INR">
                                    <Taxes Amount="780" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-04-22" ExpireDate="2021-04-23" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="7280" AmountBeforeTax="6500" CurrencyCode="INR">
                                    <Taxes Amount="780" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-04-23" ExpireDate="2021-04-24" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="7280" AmountBeforeTax="6500" CurrencyCode="INR">
                                    <Taxes Amount="780" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates></RoomRate>
                    </RoomRates>
                <GuestCounts IsPerRoom="true">
                  <GuestCount AgeQualifyingCode="10" Count="2" />
                </GuestCounts>
                <TimeSpan Start="2021-04-21" End="2021-04-24" />
                <Total AmountIncludingMarkup="21840" AmountAfterTax="21840" AmountBeforeTax="19500" CurrencyCode="INR">
                  <Taxes Amount="2340" CurrencyCode="INR" />
                </Total>
                <BasicPropertyInfo HotelCode="3930" />
                <ResGuestRPHs>
                  <ResGuestRPH RPH="1" />
                </ResGuestRPHs>
              </RoomStay><RoomStay>
                    <RoomTypes>
                        <RoomType NumberOfUnits="1" RoomTypeCode="DLD" />
                    </RoomTypes>
                    <RatePlans>
                    <RatePlan RatePlanCode="1" MealPlanCode="CP" />
                    </RatePlans>
                    <RoomRates>
                        <RoomRate RoomTypeCode="DLD" RatePlanCode="1"><Rates>
                                <Rate EffectiveDate="2021-04-21" ExpireDate="2021-04-22" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="7280" AmountBeforeTax="6500" CurrencyCode="INR">
                                    <Taxes Amount="780" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-04-22" ExpireDate="2021-04-23" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="7280" AmountBeforeTax="6500" CurrencyCode="INR">
                                    <Taxes Amount="780" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-04-23" ExpireDate="2021-04-24" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="7280" AmountBeforeTax="6500" CurrencyCode="INR">
                                    <Taxes Amount="780" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates></RoomRate>
                    </RoomRates>
                <GuestCounts IsPerRoom="true">
                  <GuestCount AgeQualifyingCode="10" Count="2" />
                </GuestCounts>
                <TimeSpan Start="2021-04-21" End="2021-04-24" />
                <Total AmountIncludingMarkup="21840" AmountAfterTax="21840" AmountBeforeTax="19500" CurrencyCode="INR">
                  <Taxes Amount="2340" CurrencyCode="INR" />
                </Total>
                <BasicPropertyInfo HotelCode="3930" />
                <ResGuestRPHs>
                  <ResGuestRPH RPH="1" />
                </ResGuestRPHs>
              </RoomStay><RoomStay>
                    <RoomTypes>
                        <RoomType NumberOfUnits="1" RoomTypeCode="DLD" />
                    </RoomTypes>
                    <RatePlans>
                    <RatePlan RatePlanCode="1" MealPlanCode="CP" />
                    </RatePlans>
                    <RoomRates>
                        <RoomRate RoomTypeCode="DLD" RatePlanCode="1"><Rates>
                                <Rate EffectiveDate="2021-04-21" ExpireDate="2021-04-22" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="7280" AmountBeforeTax="6500" CurrencyCode="INR">
                                    <Taxes Amount="780" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-04-22" ExpireDate="2021-04-23" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="7280" AmountBeforeTax="6500" CurrencyCode="INR">
                                    <Taxes Amount="780" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-04-23" ExpireDate="2021-04-24" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="7280" AmountBeforeTax="6500" CurrencyCode="INR">
                                    <Taxes Amount="780" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates></RoomRate>
                    </RoomRates>
                <GuestCounts IsPerRoom="true">
                  <GuestCount AgeQualifyingCode="10" Count="2" />
                </GuestCounts>
                <TimeSpan Start="2021-04-21" End="2021-04-24" />
                <Total AmountIncludingMarkup="21840" AmountAfterTax="21840" AmountBeforeTax="19500" CurrencyCode="INR">
                  <Taxes Amount="2340" CurrencyCode="INR" />
                </Total>
                <BasicPropertyInfo HotelCode="3930" />
                <ResGuestRPHs>
                  <ResGuestRPH RPH="1" />
                </ResGuestRPHs>
              </RoomStay></RoomStays>
            <ResGuests>
              <ResGuest ResGuestRPH="1">
                <Profiles>
                  <ProfileInfo>
                    <Profile ProfileType="1">
                      <Customer>
                        <PersonName>
                          <GivenName>AJAY</GivenName>
                          <Surname>TANDON</Surname>
                        </PersonName>
                        <Telephone PhoneTechType="1" PhoneNumber="+919810333430" FormattedInd="false" DefaultInd="true" />
                        <Email EmailType="1">info@thesolitaire.in</Email>
                        <Address>
                          <AddressLine>NA</AddressLine>
                          <CityName>NA</CityName>
                          <CountryName Code="na">NA</CountryName>
                        </Address>
                      </Customer>
                    </Profile>
                  </ProfileInfo>
                </Profiles>
              </ResGuest>
            </ResGuests>
          </HotelReservation>
        </HotelReservations>
      </OTA_HotelResNotifRQ>';
      $ids_id = 52896;
      $res = $this->pushReservations($xml,$ids_id);
      var_dump($xml,'    ',$res);
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
        $ch 	= curl_init();
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
    public function idsRoomCode($room_type_id)
    {
       $ids_room = IdsRoom::select('ids_room_type_code')
                              ->where('room_type_id','=',$room_type_id)
                              ->first();
       return  $ids_room['ids_room_type_code'];
    }
    public function ratePlan($rate_plan_id)
    {
       $plan = MasterRatePlan::select('plan_type')
                              ->where('rate_plan_id','=',$rate_plan_id)
                              ->first();
       return  $plan['plan_type'];
    }
    //Get IDS status (Exist or not)
    public function getIdsHotel($hotel_id)
    {
        $resp=IdsRoom::where('hotel_id',$hotel_id)->select('ids_hotel_code')->first();
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
        $postdata           = trim($request->getcontent());
        $push_array_data    = json_decode(json_encode(simplexml_load_string($postdata)), true);
        $api_key        = $request->header('key');
        $version        = $push_array_data['@attributes']['Version'];
        $timestamp      = $push_array_data['@attributes']['TimeStamp'];
        $token          = $push_array_data['@attributes']['EchoToken'];
        $hotel_id       = $this->PmsComponents->idsHotel($push_array_data['Inventories']['@attributes']['HotelCode']);
        $update_cm      = 'yes';
        $ip             = $_SERVER['REMOTE_ADDR'];
        $cur_date       = date('Y-m-d');
        $inv_content    ='';
        //print_r($push_array_data);exit;

        //POST METHOD CHECK
        if($api_key!='' && $hotel_id!='')
        {
            $checkApi       = $this->PmsComponents->checkApi($api_key);
            $hotels         = $checkApi->hotels;
            $hotels         = explode(',',$hotels);
            if(in_array($hotel_id, $hotels))
            {
                $flag=0;
                $inven=$push_array_data['Inventories']['Inventory'];
                $inv_array = '<OTA_HotelInvCountNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" EchoToken="'.$api_key.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
                <Inventories HotelCode="'.$hotel_id.'">';

                if(isset($inven[0]))
                {
                    foreach($inven as $Inv)
                    {
                        $date_from = $Inv['StatusApplicationControl']['@attributes']['Start'];
                        $date_to = $Inv['StatusApplicationControl']['@attributes']['End'];
                        $chk_inv=$this->PmsComponents->checkInvTypeCode($Inv['StatusApplicationControl']['@attributes']['InvTypeCode'], $push_array_data['Inventories']['@attributes']['HotelCode']);
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
                                $inv_content.='<Inventory><StatusApplicationControl Start="'.$date_from.'" End="'.$date_to.'" InvTypeCode="'.$room_type_id.'" /><InvCounts><InvCount Count="'.$no_of_rooms.'" /></InvCounts></Inventory>';
                            }
                            else
                            {
                                $return_array = '<OTA_HotelInvCountNotifRS xmlns="http://www.opentravel.org/OTA/2003/05" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
                                <Error>End date should be greater than Start Date  or current date</Error> </OTA_HotelInvCountNotifRS>';
                                echo $return_array;
                            }
                        }
                        else
                        {
                            $return_array = '<OTA_HotelInvCountNotifRS xmlns="http://www.opentravel.org/OTA/2003/05" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
                            <Error>Above InvTypeCode not configured.</Error> </OTA_HotelInvCountNotifRS>';
                            echo $return_array;
                        }
                    }
                }
                else
                {
                    $date_from = $inven['StatusApplicationControl']['@attributes']['Start'];
                    $date_to = $inven['StatusApplicationControl']['@attributes']['End'];
                    $chk_inv=$this->PmsComponents->checkInvTypeCode($inven['StatusApplicationControl']['@attributes']['InvTypeCode'], $push_array_data['Inventories']['@attributes']['HotelCode']);
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
                            $inv_content='<Inventory><StatusApplicationControl Start="'.$date_from.'" End="'.$date_to.'" InvTypeCode="'.$room_type_id.'" /><InvCounts><InvCount Count="'.$no_of_rooms.'" /></InvCounts></Inventory>';
                        }
                        else
                        {
                            $return_array = '<OTA_HotelInvCountNotifRS xmlns="http://www.opentravel.org/OTA/2003/05" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
                            <Error>End Date should be equal or greater than current date or start date</Error> </OTA_HotelInvCountNotifRS>';
                            echo $return_array;
                        }
                    }
                    else
                    {
                        $return_array = '<OTA_HotelInvCountNotifRS xmlns="http://www.opentravel.org/OTA/2003/05" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
                        <Error>Above InvTypeCode not configured.</Error> </OTA_HotelInvCountNotifRS>';
                        echo $return_array;
                    }

                }

                $inv=$inv_array.$inv_content.'</Inventories></OTA_HotelInvCountNotifRQ>';
                $url='https://cm.bookingjini.com/ids/update-inv';
                $da=$this->curlPost($url,$inv);
                if($da)
                {
                    $return_array = '<OTA_HotelInvCountNotifRS xmlns="http://www.opentravel.org/OTA/2003/05" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
                    <Success /> </OTA_HotelInvCountNotifRS>';
                    echo $return_array;
                }
                else{
                  $return_array = '<OTA_HotelInvCountNotifRS xmlns="http://www.opentravel.org/OTA/2003/05" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
                    <Failure /> </OTA_HotelInvCountNotifRS>';
                    echo $return_array;
                }

            }
            else
            {
                $return_array = '<OTA_HotelInvCountNotifRS xmlns="http://www.opentravel.org/OTA/2003/05" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
                <Error>Hotel Not Found</Error> </OTA_HotelInvCountNotifRS>';
                echo $return_array;
            }
        }
        else
        {
            $return_array = '<OTA_HotelInvCountNotifRS xmlns="http://www.opentravel.org/OTA/2003/05" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
            <Error>Please set key in header</Error> </OTA_HotelInvCountNotifRS>';
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