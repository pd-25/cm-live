<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Input;
use Validator;
use App\CmOtaDetails;
use App\CompanyDetails;
use DB;
use App\User;
use App\State;
use App\City;
use App\ImageTable;
use App\MasterRatePlan;
use App\MasterRoomType;
use App\AgodaListingDetails;
use App\AgodaRoomTypeSynchronised;
use App\AgodaRoomTypeSynchronisedRead;
use App\AgodaNotifyLogs;
use App\HotelInformation;

class BookingDotComListingDetailsController extends Controller
{ 
   public function CreateHotelProperty($data){
    //$data = $request->all();
    // $lat_lon = explode(',', $data['hotel_geo_location']);
    // $data['latitude'] = $lat_lon[0];
    // $data['longitude'] = $lat_lon[1];
    // $data['email_id'] = implode(',', $data['email_id']);
    // $data['land_line'] = implode(',', $data['land_line']);
    // $data['mobile'] = implode(',', $data['mobile']);
    $status = 'new';
    $hotel_id = $data['hotel_id'];
    $user_details = User::select('first_name','last_name')->where(['user_id'=>$data['user_id']])->get()->toArray();
    $state_details = State::select('state_name')->where(['state_id'=>$data['state_id']])->get()->toArray();
    $city_details = City::select('city_name')->where(['city_id'=>$data['city_id']])->get()->toArray();
    $external_imageid = DB::table('hotels_table')->select('exterior_image')->where('hotel_id', $hotel_id)->first();
    $images_array = DB::table('image_table')->select('image_name','image_id')->whereIn('image_id',explode(',',$external_imageid->exterior_image))->get()->toArray();
    $amenities_array = DB::table('room_type_table')->select('room_amenities','total_rooms')->where('hotel_id', $hotel_id)->get()->toArray();

    $xml_amen = '';
    $total_rooms = 0;
    if($amenities_array > 0){
      $xml_amen .= '<FacilityInfo>
                  <GuestRooms>';
      foreach($amenities_array as $amenities=>$amenitie){
        $total_rooms = $total_rooms + $amenitie->total_rooms;
        if($amenitie !== 'NA'){
          $explode_arrays = explode(',', $amenitie->room_amenities);
          // echo "<pre>";print_r($explode_arrays);exit;
          if(count($explode_arrays) > 0 && !empty($explode_arrays[0])){
            $xml_amen .= '<GuestRoom>
                      <Amenities>';
            foreach($explode_arrays as $amenities1=>$amenitie1){
              if($amenitie1 !== 'NA' && !empty($amenitie1)){
                $xml_amen .= '<Amenity RoomAmenityCode="'.$amenitie1.'" />';
              }
            }
            $xml_amen .= '</Amenities>
                  </GuestRoom>';
          }
        }
      }
      $xml_amen .= '</GuestRooms>
              </FacilityInfo>';
    }
    //echo "<pre>";print_r($amenities_array);echo $xml;exit;
    $url = 'https://supply-xml.booking.com/hotels/ota/OTA_HotelDescriptiveContentNotif';
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
            <OTA_HotelDescriptiveContentNotifRQ
              xmlns="http://www.opentravel.org/OTA/2003/05"
              xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
              PrimaryLangID="en-us"
              EchoToken="GUID"
              xsi:schemaLocation="http://www.opentravel.org/2014B/OTA_HotelDescriptiveContentNotifRQ.xsd"
              id="OTA2014B"
              Version="8.0"
              Target="Test">
              <HotelDescriptiveContents>
                <HotelDescriptiveContent
                  HotelName="'.$data['hotel_name'].'"
                  LanguageCode="en"
                  HotelDescriptiveContentNotifType="'.$status.'">
                  <ContactInfos>
                    <ContactInfo ContactProfileType="PhysicalLocation">
                      <Addresses>
                        <Address>
                          <AddressLine>'.$data['state_id'].'</AddressLine>
                          <CityName>'.$data['city_id'].'</CityName>
                          <PostalCode>'.$data['pin'].'</PostalCode>
                          <CountryName>IN</CountryName>
                        </Address>
                      </Addresses>
                    </ContactInfo>
                    <ContactInfo ContactProfileType="general">
                      <Names>
                        <Name Language="en">
                          <GivenName>'.$user_details[0]['first_name'].'</GivenName>
                          <Surname>'.$user_details[0]['last_name'].'</Surname>
                        </Name>
                      </Names>
                      <Emails>
                        <Email>'.$data['email_id'].'</Email>
                      </Emails>
                      <Phones>
                        <Phone PhoneNumber="'.$data['mobile'].'" PhoneTechType="1" Extension="30" />
                      </Phones>
                    </ContactInfo>
                    <ContactInfo ContactProfileType="general">
                      <Names>
                          <Name Language="en">
                              <GivenName>'.$user_details[0]['first_name'].'</GivenName>
                              <Surname>'.$user_details[0]['last_name'].'</Surname>
                          </Name>
                      </Names>
                      <Emails>
                          <Email>'.$data['email_id'].'</Email>
                      </Emails>
                      <Phones>
                          <Phone PhoneNumber="'.$data['mobile'].'" PhoneTechType="5" />
                      </Phones>
                  </ContactInfo>
                    <ContactInfo ContactProfileType="invoices">
                      <Names>
                        <Name Language="en">
                          <GivenName>'.$user_details[0]['first_name'].'</GivenName>
                          <Surname>'.$user_details[0]['last_name'].'</Surname>
                        </Name>
                      </Names>
                      <Addresses>
                        <Address>
                          <AddressLine>'.$state_details[0]['state_name'].'</AddressLine>
                          <CityName>'.$city_details[0]['city_name'].'</CityName>
                          <PostalCode>'.$data['pin'].'</PostalCode>
                          <StateProv StateCode="29" />
                          <CountryName>IN</CountryName>
                        </Address>
                      </Addresses>
                      <Phones>
                        <Phone PhoneNumber="'.$data['mobile'].'" PhoneTechType="1" Extension="30" />
                      </Phones>
                    </ContactInfo>
                  </ContactInfos>
                  <HotelInfo>
                    <CategoryCodes>
                      <GuestRoomInfo Quantity="'.$total_rooms.'" />
                      <HotelCategory ExistsCode="1" Code="Hotel" />
                    </CategoryCodes>
                    <Position Latitude="'.trim($data['latitude']).'" Longitude="'.trim($data['longitude']).'" />
                    <OwnershipManagementInfos>
                      <OwnershipManagementInfo>
                        <CompanyName Code="'.$data['company_id'].'" />
                      </OwnershipManagementInfo>
                    </OwnershipManagementInfos>
                  </HotelInfo>';
            $xml .= $xml_amen;    
            $xml .= '<Policies>
                <Policy>
                    <PolicyInfo CheckInTime="'.$data['check_in'].'" CheckOutTime="'.$data['check_out'].'" UsualStayFreeCutoffAge="12" UsualStayFreeChildPerAdult="1" />';
              //foreach($images_array as $key=>$val){
              $xml .= '<CancelPolicy>
                          <CancelPenalty PolicyCode="43" />
                        <CancelPenalty PolicyCode="1" />
                    </CancelPolicy>';
              //}
              $xml .= '<GuaranteePaymentPolicy>
                        <GuaranteePayment PolicyCode="43" />
                        <GuaranteePayment PolicyCode="1" />
                    </GuaranteePaymentPolicy>
                    <TaxPolicies>
                        <TaxPolicy Code="36" Percent="1800" DecimalPlaces="2" Type="Inclusive" ChargeFrequency="12" />
                        <TaxPolicy Code="3" Percent="350" DecimalPlaces="2" Type="Exclusive" ChargeFrequency="21" />
                    </TaxPolicies>
                </Policy>
            </Policies>
            <MultimediaDescriptions>
                <MultimediaDescription>
                    <ImageItems>';
              $i= 1;
              foreach($images_array as $key=>$val){
                $main = ($i == 1) ? 'Main="1"' : '';
                $xml .= '<ImageItem>
                            <ImageFormat '.$main.' Sort="'.$i.'">
                                <URL>'.$val->image_name.'</URL>
                            </ImageFormat>
                            <TPA_Extensions>
                                <ImageTags>
                                    <ImageTag ID="'.$val->image_id.'"/>
                                </ImageTags>
                            </TPA_Extensions>
                        </ImageItem>';
                $i++;
              }
              $xml .= '</ImageItems>
                </MultimediaDescription>
            </MultimediaDescriptions>
                </HotelDescriptiveContent>
              </HotelDescriptiveContents>
            </OTA_HotelDescriptiveContentNotifRQ>';
            return $xml;
            $result = $this->curlRequest($xml,$url);
            if(isset($array_request['Success'])){
              $type = $array_request['UniqueID']['@attributes']['Type'];
              $id = $array_request['UniqueID']['@attributes']['ID'];
            }else{
              echo "error";
            }
   }
   public function updateHotelProperty($data){
    // $data = $request->all();
    // $lat_lon = explode(',', $data['hotel_geo_location']);
    // $data['latitude'] = $lat_lon[0];
    // $data['longitude'] = $lat_lon[1];
    // $data['email_id'] = implode(',', $data['email_id']);
    // $data['land_line'] = implode(',', $data['land_line']);
    // $data['mobile'] = implode(',', $data['mobile']);
    $status = 'Overlay';
    $hotel_id = $data['hotel_id'];
    $user_details = User::select('first_name','last_name')->where(['user_id'=>$data['user_id']])->get()->toArray();
    $state_details = State::select('state_name')->where(['state_id'=>$data['state_id']])->get()->toArray();
    $city_details = City::select('city_name')->where(['city_id'=>$data['city_id']])->get()->toArray();
    $external_imageid = DB::table('hotels_table')->select('exterior_image')->where('hotel_id', 2035)->first();
    $images_array = DB::table('image_table')->select('image_name','image_id')->whereIn('image_id',explode(',',$external_imageid->exterior_image))->get()->toArray();
    $amenities_array = DB::table('room_type_table')->select('room_amenities','total_rooms')->where('hotel_id', 2035)->get()->toArray();
    $xml_amen = '';
    $total_rooms = 0;
    if($amenities_array > 0){
      $xml_amen .= '<FacilityInfo>
                  <GuestRooms>';
      foreach($amenities_array as $amenities=>$amenitie){
        $total_rooms = $total_rooms + $amenitie->total_rooms;
        if($amenitie !== 'NA'){
          $explode_arrays = explode(',', $amenitie->room_amenities);
          // echo "<pre>";print_r($explode_arrays);exit;
          if(count($explode_arrays) > 0 && !empty($explode_arrays[0])){
            $xml_amen .= '<GuestRoom>
                      <Amenities>';
            foreach($explode_arrays as $amenities1=>$amenitie1){
              if($amenitie1 !== 'NA' && !empty($amenitie1)){
                $xml_amen .= '<Amenity RoomAmenityCode="'.$amenitie1.'" />';
              }
            }
            $xml_amen .= '</Amenities>
                  </GuestRoom>';
          }
        }
      }
      $xml_amen .= '</GuestRooms>
              </FacilityInfo>';
    }
    //echo "<pre>";print_r($amenities_array);echo $xml;exit;
    $timestamp = date('Y-m-d');
    $url = 'https://supply-xml.booking.com/hotels/ota/OTA_HotelDescriptiveContentNotif';
    $xml = '<OTA_HotelDescriptiveContentNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" PrimaryLangID="en-us" EchoToken="GUID" TimeStamp="'.$timestamp.'T09:30:47Z" xsi:schemaLocation="http://www.opentravel.org/2014B/OTA_HotelDescriptiveContentNotifRQ.xsd" id="OTA2014B" Version="8.0" Target="Production">
              <HotelDescriptiveContents>
                <HotelDescriptiveContent
                  HotelName="'.$data['hotel_name'].'"
                  LanguageCode="en"
                  HotelDescriptiveContentNotifType="'.$status.'">
                  <ContactInfos>
                    <ContactInfo ContactProfileType="PhysicalLocation">
                      <Addresses>
                        <Address>
                          <AddressLine>'.$state_details[0]['state_name'].'</AddressLine>
                          <CityName>'.$city_details[0]['city_name'].'</CityName>
                          <PostalCode>'.$data['pin'].'</PostalCode>
                          <CountryName>IN</CountryName>
                        </Address>
                      </Addresses>
                    </ContactInfo>
                    <ContactInfo ContactProfileType="general">
                      <Names>
                        <Name Language="en">
                          <GivenName>'.$user_details[0]['first_name'].'</GivenName>
                          <Surname>'.$user_details[0]['last_name'].'</Surname>
                        </Name>
                      </Names>
                      <Emails>
                        <Email>'.$data['email_id'].'</Email>
                      </Emails>
                      <Phones>
                        <Phone PhoneNumber="'.$data['mobile'].'" PhoneTechType="1" Extension="30" />
                      </Phones>
                    </ContactInfo>
                    <ContactInfo ContactProfileType="general">
                      <Names>
                          <Name Language="en">
                              <GivenName>'.$user_details[0]['first_name'].'</GivenName>
                              <Surname>'.$user_details[0]['last_name'].'</Surname>
                          </Name>
                      </Names>
                      <Emails>
                          <Email>'.$data['email_id'].'</Email>
                      </Emails>
                      <Phones>
                          <Phone PhoneNumber="'.$data['mobile'].'" PhoneTechType="5" />
                      </Phones>
                  </ContactInfo>
                    <ContactInfo ContactProfileType="invoices">
                      <Names>
                        <Name Language="en">
                          <GivenName>'.$user_details[0]['first_name'].'</GivenName>
                          <Surname>'.$user_details[0]['first_name'].'</Surname>
                        </Name>
                      </Names>
                      <Addresses>
                        <Address>
                          <AddressLine>'.$state_details[0]['state_name'].'</AddressLine>
                          <CityName>'.$city_details[0]['city_name'].'</CityName>
                          <PostalCode>'.$data['pin'].'</PostalCode>
                          <StateProv StateCode="29" />
                          <CountryName>IN</CountryName>
                        </Address>
                      </Addresses>
                      <Phones>
                        <Phone PhoneNumber="'.$data['mobile'].'" PhoneTechType="1" Extension="30" />
                      </Phones>
                    </ContactInfo>
                  </ContactInfos>
                  <HotelInfo>
                    <CategoryCodes>
                      <GuestRoomInfo Quantity="'.$total_rooms.'" />
                      <HotelCategory ExistsCode="1" Code="Hotel" />
                    </CategoryCodes>
                    <Position Latitude="'.trim($data['latitude']).'" Longitude="'.trim($data['longitude']).'" />
                    <OwnershipManagementInfos>
                      <OwnershipManagementInfo>
                        <CompanyName Code="'.$data['company_id'].'" />
                      </OwnershipManagementInfo>
                    </OwnershipManagementInfos>
                  </HotelInfo>';
            $xml .= $xml_amen;    
            $xml .= '<Policies>
                <Policy>
                    <PolicyInfo CheckInTime="'.$data['check_in'].'" CheckOutTime="'.$data['check_out'].'" UsualStayFreeCutoffAge="12" UsualStayFreeChildPerAdult="1" />';
              //foreach($images_array as $key=>$val){
              $xml .= '<CancelPolicy>
                          <CancelPenalty PolicyCode="43" />
                        <CancelPenalty PolicyCode="1" />
                    </CancelPolicy>';
              //}
              $xml .= '<GuaranteePaymentPolicy>
                        <GuaranteePayment PolicyCode="43" />
                        <GuaranteePayment PolicyCode="1" />
                    </GuaranteePaymentPolicy>
                    <TaxPolicies>
                        <TaxPolicy Code="36" Percent="1800" DecimalPlaces="2" Type="Inclusive" ChargeFrequency="12" />
                        <TaxPolicy Code="3" Percent="350" DecimalPlaces="2" Type="Exclusive" ChargeFrequency="21" />
                    </TaxPolicies>
                </Policy>
            </Policies>
            <MultimediaDescriptions>
                <MultimediaDescription>
                    <ImageItems>';
              $i= 1;
              foreach($images_array as $key=>$val){
                $main = ($i == 1) ? 'Main="1"' : '';
                $xml .= '<ImageItem>
                            <ImageFormat '.$main.' Sort="'.$i.'">
                                <URL>'.$val->image_name.'</URL>
                            </ImageFormat>
                            <TPA_Extensions>
                                <ImageTags>
                                    <ImageTag ID="'.$val->image_id.'"/>
                                </ImageTags>
                            </TPA_Extensions>
                        </ImageItem>';
                $i++;
              }
              $xml .= '</ImageItems>
                </MultimediaDescription>
            </MultimediaDescriptions>
                </HotelDescriptiveContent>
              </HotelDescriptiveContents>
            </OTA_HotelDescriptiveContentNotifRQ>';
            return $xml;exit;
          if(isset($array_request['Success'])){
            echo "successfully updated";
          }else{
            echo "error";
          }
   }
   public function AddRoomType($data)
   {
    $hotel_id = $data['hotel_id'];
    $room_type_array = DB::table('room_type_table')->select('*')->where('hotel_id', $hotel_id)->get()->toArray();
    //echo "<pre>";print_r($room_type_array);exit;
    $external_imageid = DB::table('hotels_table')->select('exterior_image')->where('hotel_id', $hotel_id)->first();
    $images_array = DB::table('image_table')->select('image_name','image_id')->whereIn('image_id',explode(',',$external_imageid->exterior_image))->get()->toArray();
      $url = 'https://supply-xml.booking.com/hotels/ota/OTA_HotelInvNotif';
      $xml = '<?xml version="1.0" encoding="UTF-8"?>
                <OTA_HotelInvNotifRQ
                  xmlns="http://www.opentravel.org/OTA/2003/05"
                  xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                  xsi:schemaLocation="http://www.opentravel.org/2014B/OTA_HotelInvNotifRQ.xsd"
                  version="6.000"
                  id="OTA2014B"
                  TransactionIdentifier="5"
                  Target="Test">
                  <SellableProducts HotelCode="'.$hotel_id.'">
                    <SellableProduct InvStatusType="Initial">
                      <GuestRoom>
                        <Occupancy MaxOccupancy="'.$room_type_array[0]->max_occupancy.'" MaxAdultOccupancy="'.$room_type_array[0]->max_people.'" MaxChildOccupancy="'.$room_type_array[0]->max_child.'" />
                        <Room NonSmoking="1" RoomType="'.$room_type_array[0]->room_type.'" />';
                        $xml .= '<Amenities>';
                        if($room_type_array > 0){
                          //echo "<pre>";print_r($room_type_array);exit;
                          $room_amen = explode(',', $room_type_array[0]->room_amenities);
                          foreach($room_amen as $amenitie){
                            //echo "<pre>";print_r($amenitie);exit;
                            $xml .= '<Amenity AmenityCode="'.$amenitie.'"/>';
                          }  
                        }
                        $xml .= '</Amenities>
                                  <Description>
                                    <Text>'.strip_tags($room_type_array[0]->description).'</Text>';
                              foreach($images_array as $image){
                                  $xml .= '<Image>'.$image->image_name.'</Image>';
                              }
                        $xml .= '</Description>
                      </GuestRoom>
                    </SellableProduct>
                  </SellableProducts>
                </OTA_HotelInvNotifRQ>';
          return $xml;exit;
          $result = $this->curlRequest($xml,$url);
          $array_request=json_decode(json_encode(simplexml_load_string($xml)), true);
          if(isset($array_request['Success'])){
            $inventory_code = $array_request['InventoryCrossRefs']['InventoryCrossRef']['@attributes']['ResponseInvCode'];
          }else{
            echo "error";
          }
   }
   public function updateRoomType(Request $request)
   {
    $hotel_id = $data['hotel_id'];
    $room_type_array = DB::table('room_type_table')->select('*')->where('hotel_id', $hotel_id)->get()->toArray();
    //echo "<pre>";print_r($room_type_array);exit;
    $external_imageid = DB::table('hotels_table')->select('exterior_image')->where('hotel_id', $hotel_id)->first();
    $images_array = DB::table('image_table')->select('image_name','image_id')->whereIn('image_id',explode(',',$external_imageid->exterior_image))->get()->toArray();
    $url = 'https://supply-xml.booking.com/hotels/ota/OTA_HotelInvNotif';
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
            <OTA_HotelInvNotifRQ version="6.0" Target="Production" xmlns="http://www.opentravel.org/OTA/2003/05">
              <SellableProducts HotelCode="'.$hotel_id.'">
                <SellableProduct InvNotifType="Overlay" InvStatusType="Deactivated" InvCode="'.$room_type_array[0]->room_type_id.'">
                <GuestRoom>
                        <Occupancy MaxOccupancy="'.$room_type_array[0]->max_occupancy.'" MaxAdultOccupancy="'.$room_type_array[0]->max_people.'" MaxChildOccupancy="'.$room_type_array[0]->max_child.'" />
                        <Room NonSmoking="1" RoomType="'.$room_type_array[0]->room_type.'" />';
                        $xml .= '<Amenities>';
                        if($room_type_array > 0){
                          //echo "<pre>";print_r($room_type_array);exit;
                          $room_amen = explode(',', $room_type_array[0]->room_amenities);
                          foreach($room_amen as $amenitie){
                            //echo "<pre>";print_r($amenitie);exit;
                            $xml .= '<Amenity AmenityCode="'.$amenitie.'"/>';
                          }  
                        }
                        $xml .= '</Amenities>
                                  <Description>
                                    <Text>'.strip_tags($room_type_array[0]->description).'</Text>';
                              foreach($images_array as $image){
                                  $xml .= '<Image>'.$image->image_name.'</Image>';
                              }
                        $xml .= '</Description>
                      </GuestRoom>
              </SellableProduct>
            </SellableProducts>
          </OTA_HotelInvNotifRQ>';
          return $xml;exit;
    $result = $this->curlRequest($xml,$url);
    $array_request=json_decode(json_encode(simplexml_load_string($xml)), true);
    if(isset($array_request['Success'])){
      echo "success";
    }else{
      return $result;
    }
   }
   public function manageRoomType(){
    $room_type_id = '123';
    $status = 'Active';
    $status = 'Deactivated';
    $xml = '<OTA_HotelInvNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.opentravel.org/2014B/OTA_HotelInvNotifRQ.xsd" version="6.000" id="OTA2014B" TransactionIdentifier="5" Target="Production">
              <SellableProducts HotelCode="{PropertyID}">
                <SellableProduct InvNotifType="Overlay" InvStatusType="'.$status.'" InvCode="'.$room_type_id.'">
                  <GuestRoom />
                </SellableProduct>
              </SellableProducts>
            </OTA_HotelInvNotifRQ>';
    return $xml;exit;
   }
   public function ManageRatePlan($data)
   {
    $status = 'new';
    //echo "<pre>";print_r($details);exit;
    $url = 'https://supply-xml.booking.com/hotels/ota/OTA_HotelRatePlanNotif';
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
              <OTA_HotelRatePlanNotifRQ
                xmlns="http://www.opentravel.org/OTA/2003/05"
                MessageContentCode="8"
                Version="1.005"
                Target="Test">
                <RatePlans RatePlanNotifType="New" HotelCode="'.$data['hotel_id'].'">';
                $details_of_room = DB::table('kernel.room_rate_plan')->select('rate_plan_id')->where(['hotel_id'=>$data['hotel_id'],'room_type_id'=>$data['room_type_id']])->get()->toArray();
                //echo "<pre>";print_r($details_of_room);exit;
                foreach ($details_of_room as $key => $value) {
                  $details = MasterRatePlan::select('plan_name')->where(['hotel_id'=>$data['hotel_id'],'rate_plan_id'=>$value->rate_plan_id])->get()->toArray();
                  foreach ($details as $key => $value1) {
                    $xml .= '<RatePlan RatePlanNotifType="'.$status.'">
                              <Description Name="'.$value1['plan_name'].'"/>
                            </RatePlan>';
                  }
                }
            $xml .= '</RatePlans>
              </OTA_HotelRatePlanNotifRQ>';
      //$result = $this->curlRequest($xml,$url);
      echo $xml;exit;
      $array_request=json_decode(json_encode(simplexml_load_string($xml)), true);
      if(isset($array_request['Success'])){
        $hotel_name = $array_request['HotelDescriptiveContents']['HotelDescriptiveContent']['@attributes']['HotelName'];
        $hotel_ID = $hotel_name = $array_request['HotelDescriptiveContents']['HotelDescriptiveContent']['@attributes']['ID'];
        $Status = $array_request['HotelDescriptiveContents']['HotelDescriptiveContent']['@attributes']['Status'];
      }else{
        return $result;
      }
   }
   public function updateRatePlan(Request $request)
   {
    $status = 'new';
    $data['hotel_id'] = '2035';
    $data['room_type_id'] = '6443';
    $details_of_room = DB::table('kernel.room_rate_plan')->select('rate_plan_id')->where(['hotel_id'=>$data['hotel_id'],'room_type_id'=>$data['room_type_id']])->get()->toArray();
    $details = MasterRatePlan::select('plan_name')->where(['hotel_id'=>$data['hotel_id'],'rate_plan_id'=>$details_of_room[0]->rate_plan_id])->get()->toArray();;
    //echo "<pre>";print_r($details);exit;
    $url = 'https://supply-xml.booking.com/hotels/ota/OTA_HotelRatePlanNotif';
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
              <OTA_HotelRatePlanNotifRQ
                xmlns="http://www.opentravel.org/OTA/2003/05"
                MessageContentCode="8"
                Version="1.005"
                Target="Test">
                <RatePlans RatePlanNotifType="Overlay" HotelCode="'.$data['hotel_id'].'">';
                $details_of_room = DB::table('kernel.room_rate_plan')->select('rate_plan_id')->where(['hotel_id'=>$data['hotel_id'],'room_type_id'=>$data['room_type_id']])->get()->toArray();
                //echo "<pre>";print_r($details_of_room);exit;
                foreach ($details_of_room as $key => $value) {
                  $details = MasterRatePlan::select('plan_name')->where(['hotel_id'=>$data['hotel_id'],'rate_plan_id'=>$value->rate_plan_id])->get()->toArray();
                  foreach ($details as $key => $value1) {
                    $xml .= '<RatePlan RatePlanNotifType="'.$status.'">
                              <Description Name="'.$value1['plan_name'].'"/>
                            </RatePlan>';
                  }
                }
            $xml .= '</RatePlans>
              </OTA_HotelRatePlanNotifRQ>';
      //$result = $this->curlRequest($xml,$url);
      echo $xml;exit;
      $array_request=json_decode(json_encode(simplexml_load_string($xml)), true);
      if(isset($array_request['Success'])){
        echo "success";
      }else{
        return $result;
      }
   }
   public function retriveProperty($data,$hotel_info,$status)
   {
    $url = 'https://supply-xml.booking.com/hotels/ota/OTA_HotelDescriptiveInfo';
    $xml = '<OTA_HotelDescriptiveInfoRQ>
              <HotelDescriptiveInfos>
                <HotelDescriptiveInfo HotelCode="'.$data['hotel_code'].'"></HotelDescriptiveInfo>
              </HotelDescriptiveInfos>
            </OTA_HotelDescriptiveInfoRQ>';
    $result = $this->curlRequest($xml,$url);
    echo $result;exit;
   }
   public function curlRequest($xml,$url)
   {
        $auth = '';
        $curl = curl_init();
        curl_setopt_array($curl, array(
          CURLOPT_URL => $url,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS =>$xml,
          CURLOPT_HTTPHEADER => array(
            'Authorization: '.$auth,
            'Content-Type: application/xml'
          ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        return $response;

   }
   public function test(){
      $xml = '<promotions><id>VR210380280</id></promotions>';
      $array_request=json_decode(json_encode(simplexml_load_string($xml)), true);
      echo "<pre>";print_r($array_request);exit;
    }
}