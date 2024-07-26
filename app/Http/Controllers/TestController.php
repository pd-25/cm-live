<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Validator;
use DB;

class TestController extends Controller
{
    public function testFunction(){
       $getHotelDetails = DB::table('pms_account')->select('*')->get();
       foreach($getHotelDetails as $hotel_details){
         $hotel_id = explode(',',$hotel_details->hotels);
         foreach($hotel_id as $hotel_info){
           $hotel_info = (int)$hotel_info;
            $get_info = DB::table('inventory_control_table')
                        ->select('*')->where('hotel_id',$hotel_info)
                        ->first();
            if($get_info){
               $update_status = DB::table('inventory_control_table')->where('hotel_id',$hotel_info)->update(['pms_status'=>1]);
            }
            else{
              $info = array('hotel_id'=>$hotel_info,'pms_status'=>1);
              $insert_status = DB::table('inventory_control_table')->insert($info);
            }
         }
       }
    }
    public function memoryLength(){
      $str = str_repeat('a',  255*1024);
      $x = '<OTA_HotelResNotifRQ xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns="http://www.opentravel.org/OTA/2003/05" EchoToken="f65b2d0b-b772-4496-a138-45267d1c3ae9" TimeStamp="2021-01-25T00:00:00.00+05:30" Version="3.002" ResStatus="Commit">
        <POS>
          <Source>
            <RequestorID Type="22" ID="Bookingjini" />
            <BookingChannel Type="CHANNEL">
              <CompanyName Code="BKNG">Bookingjini</CompanyName>
            </BookingChannel>
          </Source>
        </POS>
        <HotelReservations>
          <HotelReservation CreateDateTime="2021-01-25T00:00:00.00+05:30">
            <UniqueID Type="14" ID="#####" ID_Context="Bookingjini" />
            <RoomStays><RoomStay>
                    <RoomTypes>
                        <RoomType NumberOfUnits="1" RoomTypeCode="DLD" />
                    </RoomTypes>
                    <RatePlans>
                    <RatePlan RatePlanCode="1" MealPlanCode="EP" />
                    </RatePlans>
                    <RoomRates>
                        <RoomRate RoomTypeCode="DLD" RatePlanCode="1"><Rates>
                                <Rate EffectiveDate="2021-02-17" ExpireDate="2021-02-18" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-18" ExpireDate="2021-02-19" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-19" ExpireDate="2021-02-20" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-20" ExpireDate="2021-02-21" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-21" ExpireDate="2021-02-22" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-22" ExpireDate="2021-02-23" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-23" ExpireDate="2021-02-24" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-24" ExpireDate="2021-02-25" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-25" ExpireDate="2021-02-26" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-26" ExpireDate="2021-02-27" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-27" ExpireDate="2021-02-28" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates></RoomRate>
                    </RoomRates>
                <GuestCounts IsPerRoom="true">
                  <GuestCount AgeQualifyingCode="10" Count="2" />
                </GuestCounts>
                <TimeSpan Start="2021-02-17" End="2021-02-28" />
                <Total AmountIncludingMarkup="7852900" AmountAfterTax="7852900" AmountBeforeTax="6655000" CurrencyCode="INR">
                  <Taxes Amount="1197900" CurrencyCode="INR" />
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
                    <RatePlan RatePlanCode="1" MealPlanCode="EP" />
                    </RatePlans>
                    <RoomRates>
                        <RoomRate RoomTypeCode="DLD" RatePlanCode="1"><Rates>
                                <Rate EffectiveDate="2021-02-17" ExpireDate="2021-02-18" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-18" ExpireDate="2021-02-19" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-19" ExpireDate="2021-02-20" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-20" ExpireDate="2021-02-21" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-21" ExpireDate="2021-02-22" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-22" ExpireDate="2021-02-23" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-23" ExpireDate="2021-02-24" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-24" ExpireDate="2021-02-25" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-25" ExpireDate="2021-02-26" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-26" ExpireDate="2021-02-27" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-27" ExpireDate="2021-02-28" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates></RoomRate>
                    </RoomRates>
                <GuestCounts IsPerRoom="true">
                  <GuestCount AgeQualifyingCode="10" Count="2" />
                </GuestCounts>
                <TimeSpan Start="2021-02-17" End="2021-02-28" />
                <Total AmountIncludingMarkup="7852900" AmountAfterTax="7852900" AmountBeforeTax="6655000" CurrencyCode="INR">
                  <Taxes Amount="1197900" CurrencyCode="INR" />
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
                    <RatePlan RatePlanCode="1" MealPlanCode="EP" />
                    </RatePlans>
                    <RoomRates>
                        <RoomRate RoomTypeCode="DLD" RatePlanCode="1"><Rates>
                                <Rate EffectiveDate="2021-02-17" ExpireDate="2021-02-18" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-18" ExpireDate="2021-02-19" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-19" ExpireDate="2021-02-20" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-20" ExpireDate="2021-02-21" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-21" ExpireDate="2021-02-22" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-22" ExpireDate="2021-02-23" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-23" ExpireDate="2021-02-24" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-24" ExpireDate="2021-02-25" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-25" ExpireDate="2021-02-26" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-26" ExpireDate="2021-02-27" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-27" ExpireDate="2021-02-28" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates></RoomRate>
                    </RoomRates>
                <GuestCounts IsPerRoom="true">
                  <GuestCount AgeQualifyingCode="10" Count="2" />
                </GuestCounts>
                <TimeSpan Start="2021-02-17" End="2021-02-28" />
                <Total AmountIncludingMarkup="7852900" AmountAfterTax="7852900" AmountBeforeTax="6655000" CurrencyCode="INR">
                  <Taxes Amount="1197900" CurrencyCode="INR" />
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
                    <RatePlan RatePlanCode="1" MealPlanCode="EP" />
                    </RatePlans>
                    <RoomRates>
                        <RoomRate RoomTypeCode="DLD" RatePlanCode="1"><Rates>
                                <Rate EffectiveDate="2021-02-17" ExpireDate="2021-02-18" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-18" ExpireDate="2021-02-19" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-19" ExpireDate="2021-02-20" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-20" ExpireDate="2021-02-21" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-21" ExpireDate="2021-02-22" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-22" ExpireDate="2021-02-23" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-23" ExpireDate="2021-02-24" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-24" ExpireDate="2021-02-25" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-25" ExpireDate="2021-02-26" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-26" ExpireDate="2021-02-27" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-27" ExpireDate="2021-02-28" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates></RoomRate>
                    </RoomRates>
                <GuestCounts IsPerRoom="true">
                  <GuestCount AgeQualifyingCode="10" Count="2" />
                </GuestCounts>
                <TimeSpan Start="2021-02-17" End="2021-02-28" />
                <Total AmountIncludingMarkup="7852900" AmountAfterTax="7852900" AmountBeforeTax="6655000" CurrencyCode="INR">
                  <Taxes Amount="1197900" CurrencyCode="INR" />
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
                    <RatePlan RatePlanCode="1" MealPlanCode="EP" />
                    </RatePlans>
                    <RoomRates>
                        <RoomRate RoomTypeCode="DLD" RatePlanCode="1"><Rates>
                                <Rate EffectiveDate="2021-02-17" ExpireDate="2021-02-18" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-18" ExpireDate="2021-02-19" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-19" ExpireDate="2021-02-20" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-20" ExpireDate="2021-02-21" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-21" ExpireDate="2021-02-22" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-22" ExpireDate="2021-02-23" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-23" ExpireDate="2021-02-24" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-24" ExpireDate="2021-02-25" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-25" ExpireDate="2021-02-26" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-26" ExpireDate="2021-02-27" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-27" ExpireDate="2021-02-28" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates></RoomRate>
                    </RoomRates>
                <GuestCounts IsPerRoom="true">
                  <GuestCount AgeQualifyingCode="10" Count="2" />
                </GuestCounts>
                <TimeSpan Start="2021-02-17" End="2021-02-28" />
                <Total AmountIncludingMarkup="7852900" AmountAfterTax="7852900" AmountBeforeTax="6655000" CurrencyCode="INR">
                  <Taxes Amount="1197900" CurrencyCode="INR" />
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
                    <RatePlan RatePlanCode="1" MealPlanCode="EP" />
                    </RatePlans>
                    <RoomRates>
                        <RoomRate RoomTypeCode="DLD" RatePlanCode="1"><Rates>
                                <Rate EffectiveDate="2021-02-17" ExpireDate="2021-02-18" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-18" ExpireDate="2021-02-19" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-19" ExpireDate="2021-02-20" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-20" ExpireDate="2021-02-21" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-21" ExpireDate="2021-02-22" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-22" ExpireDate="2021-02-23" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-23" ExpireDate="2021-02-24" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-24" ExpireDate="2021-02-25" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-25" ExpireDate="2021-02-26" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-26" ExpireDate="2021-02-27" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-27" ExpireDate="2021-02-28" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates></RoomRate>
                    </RoomRates>
                <GuestCounts IsPerRoom="true">
                  <GuestCount AgeQualifyingCode="10" Count="2" />
                </GuestCounts>
                <TimeSpan Start="2021-02-17" End="2021-02-28" />
                <Total AmountIncludingMarkup="7852900" AmountAfterTax="7852900" AmountBeforeTax="6655000" CurrencyCode="INR">
                  <Taxes Amount="1197900" CurrencyCode="INR" />
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
                    <RatePlan RatePlanCode="1" MealPlanCode="EP" />
                    </RatePlans>
                    <RoomRates>
                        <RoomRate RoomTypeCode="DLD" RatePlanCode="1"><Rates>
                                <Rate EffectiveDate="2021-02-17" ExpireDate="2021-02-18" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-18" ExpireDate="2021-02-19" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-19" ExpireDate="2021-02-20" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-20" ExpireDate="2021-02-21" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-21" ExpireDate="2021-02-22" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-22" ExpireDate="2021-02-23" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-23" ExpireDate="2021-02-24" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-24" ExpireDate="2021-02-25" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-25" ExpireDate="2021-02-26" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-26" ExpireDate="2021-02-27" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-27" ExpireDate="2021-02-28" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates></RoomRate>
                    </RoomRates>
                <GuestCounts IsPerRoom="true">
                  <GuestCount AgeQualifyingCode="10" Count="2" />
                </GuestCounts>
                <TimeSpan Start="2021-02-17" End="2021-02-28" />
                <Total AmountIncludingMarkup="7852900" AmountAfterTax="7852900" AmountBeforeTax="6655000" CurrencyCode="INR">
                  <Taxes Amount="1197900" CurrencyCode="INR" />
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
                    <RatePlan RatePlanCode="1" MealPlanCode="EP" />
                    </RatePlans>
                    <RoomRates>
                        <RoomRate RoomTypeCode="DLD" RatePlanCode="1"><Rates>
                                <Rate EffectiveDate="2021-02-17" ExpireDate="2021-02-18" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-18" ExpireDate="2021-02-19" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-19" ExpireDate="2021-02-20" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-20" ExpireDate="2021-02-21" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-21" ExpireDate="2021-02-22" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-22" ExpireDate="2021-02-23" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-23" ExpireDate="2021-02-24" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-24" ExpireDate="2021-02-25" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-25" ExpireDate="2021-02-26" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-26" ExpireDate="2021-02-27" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-27" ExpireDate="2021-02-28" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates></RoomRate>
                    </RoomRates>
                <GuestCounts IsPerRoom="true">
                  <GuestCount AgeQualifyingCode="10" Count="2" />
                </GuestCounts>
                <TimeSpan Start="2021-02-17" End="2021-02-28" />
                <Total AmountIncludingMarkup="7852900" AmountAfterTax="7852900" AmountBeforeTax="6655000" CurrencyCode="INR">
                  <Taxes Amount="1197900" CurrencyCode="INR" />
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
                    <RatePlan RatePlanCode="1" MealPlanCode="EP" />
                    </RatePlans>
                    <RoomRates>
                        <RoomRate RoomTypeCode="DLD" RatePlanCode="1"><Rates>
                                <Rate EffectiveDate="2021-02-17" ExpireDate="2021-02-18" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-18" ExpireDate="2021-02-19" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-19" ExpireDate="2021-02-20" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-20" ExpireDate="2021-02-21" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-21" ExpireDate="2021-02-22" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-22" ExpireDate="2021-02-23" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-23" ExpireDate="2021-02-24" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-24" ExpireDate="2021-02-25" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-25" ExpireDate="2021-02-26" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-26" ExpireDate="2021-02-27" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates><Rates>
                                <Rate EffectiveDate="2021-02-27" ExpireDate="2021-02-28" RateTimeUnit="Day" UnitMultiplier="1">
                                    <Base AmountAfterTax="713900" AmountBeforeTax="605000.00" CurrencyCode="INR">
                                    <Taxes Amount="108900" CurrencyCode="INR" />
                                    </Base>
                                </Rate>
                            </Rates></RoomRate>
                    </RoomRates>
                <GuestCounts IsPerRoom="true">
                  <GuestCount AgeQualifyingCode="10" Count="2" />
                </GuestCounts>
                <TimeSpan Start="2021-02-17" End="2021-02-28" />
                <Total AmountIncludingMarkup="7852900" AmountAfterTax="7852900" AmountBeforeTax="6655000" CurrencyCode="INR">
           ';
      var_dump($x);
    }
    public function updateCmDetailsHotelIDActiveOrInactive(){
        $getHotel_details = DB::table('kernel.hotels_table')->select('hotel_id','status')
                            ->where('status',0)
                            ->get();

        foreach($getHotel_details as $hotel_info){
            $update = DB::table('cm_ota_details')->where('hotel_id',$hotel_info->hotel_id)->update(['is_status'=>0]);
        }
    }


    public function checkIDSroomSync(){
        
        $url = 'http://idsnextchannelmanagerapi.azurewebsites.net/BookingJini/GetHotelRoomTypes';
        $xml = '<RN_HotelRatePlanRQ xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"    xmlns="http://www.opentravel.org/OTA/2003/05" Version="1.2" EchoToken="879791878">
        <RoomRatePlans>
        <HotelCriteria HotelCode="7567" />
        </RoomRatePlans>
        </RN_HotelRatePlanRQ>';
        $headers = array ('Content-Type: application/json');

        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml);
        $res = curl_exec($ch);
        curl_close($ch);
        return $res;
        
        //curl call for pushing the data
        
    }
    public function getKtdcPropertyDetails(){
        $xml = '<?xml version="1.0" encoding="utf-8"?>
        <PropertyDetailsRequest Key="418367c9-d295-b842-b6a5-60539369811b" UserID=""/>';

        $url = 'http://103.133.180.101:16005';

        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml);
        $res = curl_exec($ch);
        curl_close($ch);
        dd($res);

    }
    public function getKtdcRoomDetails(){
        $xml = '<?xml version="1.0" encoding="utf-8"?>
        <RoomTypesDetailsRequest Key="418367c9-d295-b842-b6a5-60539369811b" UserID="" PropertyID="BP" />';

        $url = 'http://103.133.180.101:16005';

        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml);
        $res = curl_exec($ch);
        curl_close($ch);
        dd($res);

    }
}

