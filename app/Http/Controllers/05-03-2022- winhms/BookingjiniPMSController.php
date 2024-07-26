<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Validator;
use DB;
use App\PmsDetails;
use App\MasterHotelRatePlan;
use App\PmsRoom;
use App\WinhmsRoom;
use App\PmsInvPush;
use App\IdsInvPush;
use App\PmsRatePush;
use App\PmsReservation;
use App\PmsAccount;
use App\CmOtaDetails;
use App\OtaInventory;
use App\Inventory;
use App\WinHms;
use App\Http\Controllers\IpAddressService;
use App\Http\Controllers\PmsComponentController;


class BookingjiniPMSController extends Controller
{
    /**
     * This controller is used for bookingjini Pms for others
     * @author @ranjit date:2019-06-03
     */
     protected $rules = array(
        'hotel_id' => 'required | numeric',
        'room_type_id' => 'required | numeric',
        'date_from' => 'required',
        'date_to' => 'required',
        'no_of_rooms' => 'required | numeric'
     );
     protected $messages = [
       'hotel_id.required' => 'hotel id should be required',
       'hotel_id.numeric' => 'hotel id should be numeric',
       'room_type_id.required' => 'room type id should be required',
       'room_type_id.numeric' => 'room type id should be numeric',
       'date_from.required' => 'date from should be required',
       'date_to.required' => 'date to should be required',
       'no_of_rooms.required' => 'number of room should be required',
       'no_of_rooms.numeric' => 'number of room should be numeric'
     ];
     protected $soldout_rules = array(
        'hotel_id' => 'required | numeric',
        'room_type_id' => 'required | numeric',
        'date_from' => 'required',
        'date_to' => 'required',
     );
     protected $soldout_messages = [
       'hotel_id.required' => 'hotel id should be required',
       'hotel_id.numeric' => 'hotel id should be numeric',
       'room_type_id.required' => 'room type id should be required',
       'room_type_id.numeric' => 'room type id should be numeric',
       'date_from.required' => 'date from should be required',
       'date_to.required' => 'date to should be required',
     ];
     protected $rate_rules = array(
        'hotel_id' => 'required | numeric',
        'room_type_id' => 'required | numeric',
        'rate_plan_id' => 'required | numeric',
        'date_from' => 'required',
        'date_to' => 'required',
        'bar_price' => 'required',
        'multiple_occupancy' => 'required'
     );
     protected $rate_messages = [
       'hotel_id.required' => 'hotel id should be required',
       'hotel_id.numeric' => 'hotel id should be numeric',
       'room_type_id.required' => 'room type id should be required',
       'room_type_id.numeric' => 'room type id should be numeric',
       'rate_plan_id.required' => 'rate plan id should be required',
       'rate_plan_id.numeric' => 'rate plan id should be numeric',
       'date_from.required' => 'date from should be required',
       'date_to.required' => 'date to should be required',
       'bar_price.required' => 'price should be required',
       'multiple_occupancy.required' => 'multiple occupancy should be required'
     ];
    protected $pmsService,$ipAddress,$PmsComponents;
    public function __construct(PmsService $pmsService,IpAddressService $ipAddress,PmsComponentController $PmsComponents)
    {
       $this->pmsService = $pmsService;
       $this->ipAddress = $ipAddress;
       $this->PmsComponents = $PmsComponents;
    }
    /**
    * Below two function used for managing pms inventory update and rate updated
    * @author Ranjit kumar dash @date : 2020-07-08
    */
    public function pmsInventoryUpdate(Request $request){
        $failure_message = "Please! provide necessary details";
        $validator=Validator::make($request->all(),$this->rules,$this->messages);
        if($validator->fails()){
           return response()->json(array('status'=>0,'message'=>$failure_message,'error'=>$validator->errors()));
        }
        $data = $request->all();
        $key = $request->header('key');
        if($key != null || $key != NULL || $key !=Null){
          $key = $request->header('key');
        }
        else{
          $checkHotel = PmsAccount::get();
          foreach($checkHotel as $hotels_data){
            $hotels = explode(',',$hotels_data->hotels);
            foreach($hotels as $hotel_id){
              if($hotel_id == $data["hotel_id"]){
                $key = $hotels_data->api_key;
              }
            }
          }
        }

        $xml_data = '<OTA_HotelInvCountNotifRQ xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance"  xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema" xmlns="http://www.bookingjini.com/OTA/2017/01"  EchoToken="'.$key.'" TimeStamp="2019-07-04T00:00:00.00+05:30" Version="0">
                      <Inventories HotelCode="'.$data["hotel_id"].'">
                          <Inventory>
                                  <StatusApplicationControl Start="'.$data["date_from"].'" End="'.$data["date_to"].'" InvTypeCode="'.$data["room_type_id"].'" />
                                  <InvCounts>
                                    <InvCount Count="'.$data["no_of_rooms"].'"/>
                                  </InvCounts>
                          </Inventory>
                      </Inventories>
                      </OTA_HotelInvCountNotifRQ>';
        $blk_status = 0;
        $callToUpdate = $this->inventoryUpdateToBookingjini($xml_data,$blk_status,$pms);
        if($callToUpdate){
            $callToUpdate = array("status"=>'success',"code"=>200,"message"=>'Inventory update successfully');
            return response()->json($callToUpdate);
        }
    }
    public function pmsSoldoutInventoryUpdate(Request $request){
        $failure_message = "Please! provide necessary details";
        $validator=Validator::make($request->all(),$this->soldout_rules,$this->soldout_messages);
        if($validator->fails()){
           return response()->json(array('status'=>0,'message'=>$failure_message,'error'=>$validator->errors()));
        }
        $data = $request->all();
        $key = $request->header('key');
        if($key != null || $key != NULL || $key !=Null){
          $key = $request->header('key');
        }
        else{
          $checkHotel = PmsAccount::get();
          foreach($checkHotel as $hotels_data){
            $hotels = explode(',',$hotels_data->hotels);
            foreach($hotels as $hotel_id){
              if($hotel_id == $data["hotel_id"]){
                $key = $hotels_data->api_key;
              }
            }
          }
        }

        $xml_data = '<OTA_HotelInvCountNotifRQ xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance"  xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema" xmlns="http://www.bookingjini.com/OTA/2017/01"  EchoToken="'.$key.'" TimeStamp="2019-07-04T00:00:00.00+05:30" Version="0">
                      <Inventories HotelCode="'.$data["hotel_id"].'">
                          <Inventory>
                                  <StatusApplicationControl Start="'.$data["date_from"].'" End="'.$data["date_to"].'" InvTypeCode="'.$data["room_type_id"].'" />
                                  <InvCounts>
                                    <InvCount Count="0"/>
                                  </InvCounts>
                          </Inventory>
                      </Inventories>
                      </OTA_HotelInvCountNotifRQ>';
        $blk_status = 1;
        $callToUpdate = $this->inventoryUpdateToBookingjini($xml_data,$blk_status,$pms);
        if($callToUpdate){
          $callToUpdate = array("status"=>'success',"code"=>200,"message"=>'Inventory update successfully');
            return response()->json($callToUpdate);
        }
    }
    public function idsInventroryUpdate(Request $request){
        $xml_data = $request->getcontent();
        $blk_status = 0;
        $response = $this->inventoryUpdateToBookingjini($xml_data,$blk_status,$pms);
        return $response;
    }
    // public function winhmsInventroryUpdate(Request $request){
    //     $xml_data = $request->getcontent();
    //     $blk_status = 0;
    //     $pms = 'winhms';
    //     $response = $this->inventoryUpdateToBookingjini($xml_data,$blk_status,$pms);
    //     return $response;
    // }
    public function pmsRateUpdate(Request $request){
      $failure_message = "Please! provide necessary details";
      $validator=Validator::make($request->all(),$this->rate_rules,$this->rate_messages);
      if($validator->fails()){
         return response()->json(array('status'=>0,'message'=>$failure_message,'error'=>$validator->errors()));
      }
      $data = $request->all();
      $xml_data = '<OTA_HotelRateAmountNotifRQ xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance"  xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema" xmlns="http://www.bookingjini.com/OTA/2017/01"  EchoToken="90e75a2f-6e9d-4a5c-a8f9-d44dd1a802cd" TimeStamp="2019-07-04T00:00:00.00+05:30" Version="0">
                      <RateAmountMessages HotelCode="'.$data["hotel_id"].'" HotelName="hotel moon flower">
                          <RateAmountMessage>
                              <StatusApplicationControl RatePlanCode="'.$data["rate_plan_id"].'" InvTypeCode="'.$data["room_type_id"].'" />
                              <Rates>
                                  <Rate Start="'.$data["date_from"].'" End="'.$data["date_to"].'">
                                      <BaseByGuestAmts>
                                         <BaseByGuestAmt BarPrice="'.$data["bar_price"].'" NumberOfGuests="2" />
                                          <BaseByGuestAmt MultipleOccupancy="'.$data["multiple_occupancy"].'" NumberOfGuests="3"/>
                                      </BaseByGuestAmts>
                                  </Rate>
                              </Rates>
                          </RateAmountMessage>
                      </RateAmountMessages>
                  </OTA_HotelRateAmountNotifRQ>';
      $callToUpdate = $this->rateUpdateToBookingjini($xml_data);
      if($callToUpdate){
          return $callToUpdate;
      }
    }

    public function bookingjiniHotelDetails(Request $request){
       $data=$request->getContent();
       $array_data=json_decode(json_encode(simplexml_load_string($data)),true);
       $apikey=$array_data['@attributes']['EchoToken'];
       $hotel_code=$array_data['HotelDetails']['HotelDetailsInfo']["@attributes"]['HotelCode'];
       $getHotelId=PmsDetails::select('hotels','api_key')->get();
       $count=0;
       $details=0;
       foreach($getHotelId as $hoteldetails)
       {
         if($hoteldetails->api_key == $apikey)
         {
            $count=$count+1;
            $details=strpos($hoteldetails->hotels,$hotel_code);
         }
       }
       if($count<=0)
        {
          $res='<OTA_HotelDetailsInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance"
          xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01">
                  <Error>Invalid apikey</Error>
              </OTA_HotelDetailsInfoRS>';
          return $res;
        }
        if($details<=0)
        {
          $res='<OTA_HotelDetailsInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance"
          xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01">
                  <Error>Enter valid hotel code</Error>
              </OTA_HotelDetailsInfoRS>';
          return $res;
        }

        $getRoomType=$this->pmsService->getRoomTypes($hotel_code);
        $i=0;
        foreach($getRoomType as $roomtype)
        {
          $getRatePlanId[$i]=MasterHotelRatePlan::select('room_rate_plan_id','bar_price','extra_adult_price','extra_child_price','before_days_offer','stay_duration_offer','lastminute_offer','rate_plan_table.rate_plan_id','room_type_id','from_date','to_date','multiple_occupancy','rate_plan_table.plan_type','rate_plan_table.plan_name')
          ->join('rate_plan_table','room_rate_plan.rate_plan_id','rate_plan_table.rate_plan_id')->where('room_rate_plan.hotel_id',$hotel_code)
          ->where('room_rate_plan.room_type_id',$roomtype->room_type_id)->get();
          $i++;
        }
        $hotel_details='<OTA_HotelDetailsInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance"
        xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01">
                <Success>True</Success>
                <HotelDetails>
                  <HotelDetailsInfo>
                      <Rooms>';
                        foreach($getRoomType as $roomtype){
                          $hotel_details.='<Room Code="'.$roomtype['room_type_id'].'" RooomTypeName="'.$roomtype['room_type'].'"/>';
                        }
        $hotel_details.='</Rooms>
                        <RatePlans>';
                      foreach($getRatePlanId as $rateplan){
                        foreach($rateplan as $rate){
                          $hotel_details.='<RatePlan ResponseRatePlanCode="'.$rate["rate_plan_id"].'"/>';
                        }
                      }
        $hotel_details.='</RatePlans>
                  </HotelDetailsInfo>
                </HotelDetails>
            </OTA_HotelDetailsInfoRS>';
            return $hotel_details;
    }
    public function inventoryUpdateToBookingjini($xml_data,$blk_status,$pms){
      //echo $xml_data;exit;
      $array_data=json_decode(json_encode(simplexml_load_string($xml_data)),true);
      $apikey=$array_data['@attributes']['EchoToken'];
      $hotel_code=$array_data["Inventories"]["@attributes"]['HotelCode'];
      $getHotelId=PmsDetails::select('hotels','api_key','name','id')->get();
      $update_cm      = 'no';//Chnahe this if hotel have CM
      $ip             = $this->ipAddress->getIPAddress();
      $cur_date       = date('Y-m-d');
      $count=0;
      $details=0;
      $pms_name='';
      $pms_id=0;
      foreach($getHotelId as $hoteldetails){
        if($hoteldetails->api_key == $apikey) {
           $count=$count+1;
           $details=strpos($hoteldetails->hotels,$hotel_code);
           // $details=in_array($hotel_code,$hoteldetails->hotels);
           $pms_name=$hoteldetails->name;
           $pms_id=$hoteldetails->id;
        }
      }
      if($count<=0) {
         $res='<OTA_HotelInventoryDetailsInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance"
         xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01">
                 <Error>Invalid apikey</Error>
             </OTA_HotelInventoryDetailsInfoRS>';
         return $res;
       }
       if($details<0 || $hotel_code == "0"){
         $res='<OTA_HotelInventoryDetailsInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance"
        xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01">
                 <Error>Enter valid hotel code</Error>
             </OTA_HotelInventoryDetailsInfoRS>';
         return $res;
       }
       else if($hotel_code!='')
       {
         if(!isset($array_data['Inventories']["Inventory"]["StatusApplicationControl"])){
           foreach($array_data['Inventories']["Inventory"] as $inv_data){
                $date_from         = $inv_data["StatusApplicationControl"]['@attributes']["Start"];
                $date_to           = $inv_data["StatusApplicationControl"]['@attributes']["End"];
                $ota_room_type     = $inv_data["StatusApplicationControl"]['@attributes']["InvTypeCode"];
                $date_from         = date('Y-m-d',strtotime($date_from));
                $date_to           = date('Y-m-d',strtotime($date_to));
                $getlos = DB::connection('be')->table('inventory_table')->select('los','block_status')
                            ->where('hotel_id',$hotel_code)->where('room_type_id',$ota_room_type)
                            ->where('date_from',$date_from)
                            ->where('date_to',$date_to)
                            ->orderBy('inventory_id','DESC')
                            ->first();
                if(isset($getlos->los)){
                    $los = $getlos->los;
                    $block_status=$getlos->block_status;
                }
                else{
                    $los=0;
                    $block_status=0;
                }
                if($ota_room_type == "0" || strlen($ota_room_type)<=0)
                {
                $res='<OTA_HotelInventoryDetailsInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance"
                xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01">
                            <Error>Enter valid inventory type code</Error>
                        </OTA_HotelInventoryDetailsInfoRS>';
                    return $res;
                }
                $no_of_rooms=$inv_data["InvCounts"]["InvCount"]['@attributes']['Count'];
                if(strlen($no_of_rooms)<=0)
                {
                $res='<OTA_HotelInventoryDetailsInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance"
                xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01">
                            <Error>Enter valid number of rooms</Error>
                        </OTA_HotelInventoryDetailsInfoRS>';
                    return $res;
                }
                //$roomDetails=PmsRoom::where([['hotel_id',$hotel_code],['ids_hotel_code',$hotel_code],['room_type_id',$ota_room_type]])->first();
                if($pms=='winhms'){
                    $roomDetails=WinhmsRoom::where([['hotel_id',$hotel_code],['winhms_hotel_code',$hotel_code],['room_type_id',$ota_room_type]])->first();
                }
                $this->pmsService->serviceRequest(0,$hotel_code, $ip, 'Update Inventory');
                if($blk_status == 1){
                $block_status = 1;
                }
                $inventory      = array(
                            "hotel_id"          => $hotel_code,
                            "room_type_id"      => $ota_room_type,
                            "date_from"         => $date_from,
                            "date_to"           => $date_to,
                            "no_of_rooms"       => $no_of_rooms,
                            "pms_id"            => $pms_id,
                            "pms_name"          => $pms_name,
                            "update_cm"         => $update_cm,
                            "api_key"           => $apikey,
                            "los"               => $los,
                            "block_status"      => $block_status,
                            "client_ip"         => $ip,
                            "user_id"           => 0
                            );
                if(strtotime($date_to)>=strtotime($date_from) && strtotime($date_from)>=strtotime($cur_date) && strtotime($date_to)>=strtotime($cur_date))
                {
                    if($pms == 'IDS'){
                            //Update Inventory
                            $idsInvPush= new IdsInvPush();
                            $updateInventory =$idsInvPush->fill($inventory)->save();
                            if($updateInventory){
                            $return_array = '<OTA_HotelInventoryDetailsInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance"
                            xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01">
                                    <Success>Inventory update sucessfully</Success> </OTA_HotelInventoryDetailsInfoRS>';
                            }
                    }
                    else if($pms == 'winhms'){
                            $winhmsInvPush= new WinHms();
                            $updateInventory =$winhmsInvPush->fill($inventory)->save();
                            if($updateInventory){
                                $return_array = '<OTA_HotelInventoryDetailsInfoRS
                                xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance"
                                xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"
                                xmlns="http://www.bookingjini.com/OTA/2017/01">
                                <Success>Inventory update sucessfully</Success>
                                </OTA_HotelInventoryDetailsInfoRS>';
                            }
                    }
                    else{

                        //Update Inventory
                        $invPush= new PmsInvPush();
                        $updateInventory =$invPush->fill($inventory)->save();
                        if($updateInventory){
                        $return_array = '<OTA_HotelInventoryDetailsInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance"
                        xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01">
                                    <Success>Inventory update sucessfully</Success> </OTA_HotelInventoryDetailsInfoRS>';
                        } 
                    }
                }
                else
                {
                $return_array = '<OTA_HotelInventoryDetailsInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance"
                xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01">
                            <Error>From Date should be less than or equal to to date and To date should be more that today date </Error> </OTA_HotelInventoryDetailsInfoRS>';
                //return $return_array;
                }
            }
            return $return_array;
        }
        else{
            $date_from      = $array_data['Inventories']["Inventory"]["StatusApplicationControl"]['@attributes']["Start"];
            $date_to        = $array_data['Inventories']["Inventory"]["StatusApplicationControl"]['@attributes']["End"];
            $ota_room_type  = $array_data['Inventories']["Inventory"]["StatusApplicationControl"]['@attributes']["InvTypeCode"];
            $date_from      = date('Y-m-d',strtotime($date_from));
            $date_to        = date('Y-m-d',strtotime($date_to));
            $getlos = DB::connection('be')->table('inventory_table')->select('*')
                        ->where('hotel_id',$hotel_code)->where('room_type_id',$ota_room_type)
                        ->where('date_from','<=',$date_from)
                        ->where('date_to','>=',$date_to)
                        ->orderBy('inventory_id','DESC')
                        ->first();
              if(isset($getlos->los)){
                  $los = $getlos->los;
                  $block_status=$getlos->block_status;
              }
              else{
                  $los=0;
                  $block_status=0;
              }
            if($ota_room_type == "0" || strlen($ota_room_type)<=0)
            {
              $res='<OTA_HotelInventoryDetailsInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance"
              xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01">
                       <Error>Enter valid inventory type code</Error>
                    </OTA_HotelInventoryDetailsInfoRS>';
                return $res;
            }
            $no_of_rooms=$array_data['Inventories']["Inventory"]["InvCounts"]["InvCount"]['@attributes']['Count'];
            if(strlen($no_of_rooms)<=0)
            {
              $res='<OTA_HotelInventoryDetailsInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance"
              xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01">
                         <Error>Enter valid number of rooms</Error>
                    </OTA_HotelInventoryDetailsInfoRS>';
                return $res;
            }
            //$roomDetails=PmsRoom::where([['hotel_id',$hotel_code],['ids_hotel_code',$hotel_code],['room_type_id',$ota_room_type]])->first();
            if($pms=='winhms'){
                $roomDetails=WinhmsRoom::where([['hotel_id',$hotel_code],['winhms_hotel_code',$hotel_code],['room_type_id',$ota_room_type]])->first();
                $pms_name = $pms;
            }
            $this->pmsService->serviceRequest(0,$hotel_code, $ip, 'Update Inventory');
            if($blk_status == 1){
              $block_status = 1;
            }
            $inventory      = array(
                        "hotel_id"          => $hotel_code,
                        "room_type_id"      => $ota_room_type,
                        "date_from"         => $date_from,
                        "date_to"           => $date_to,
                        "no_of_rooms"       => $no_of_rooms,
                        "pms_id"            => $pms_id,
                        "pms_name"          => $pms_name,
                        "update_cm"         => $update_cm,
                        "api_key"           => $apikey,
                        "los"               => $los,
                        "block_status"      => $block_status,
                        "client_ip"         => $ip,
                        );

            if(strtotime($date_to)>=strtotime($date_from) && strtotime($date_from)>=strtotime($cur_date) && strtotime($date_to)>=strtotime($cur_date))
            {
                if($pms == 'IDS'){
                    //Update Inventory
                    $idsInvPush= new IdsInvPush();
                    $updateInventory =$idsInvPush->fill($inventory)->save();
                    if($updateInventory){
                    $return_array = '<OTA_HotelInventoryDetailsInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance"
                    xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01">
                                <Success>Inventory update sucessfully</Success> </OTA_HotelInventoryDetailsInfoRS>';
                    } 
                }elseif ($pms == 'winhms') {
                  $winhmsInvPush= new WinHms();
                      $updateInventory =$winhmsInvPush->fill($inventory)->save();
                      if($updateInventory){
                          $return_array = '<OTA_HotelInventoryDetailsInfoRS
                          xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance"
                          xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"
                          xmlns="http://www.bookingjini.com/OTA/2017/01">
                          <Success>Inventory update sucessfully</Success>
                          </OTA_HotelInventoryDetailsInfoRS>';
                          return $return_array;
                      }
                }else{
                    //Update Inventory
                    $invPush= new PmsInvPush();
                    $updateInventory =$invPush->fill($inventory)->save();
                    if($updateInventory)
                    {
                    $return_array = '<OTA_HotelInventoryDetailsInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance"
                    xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01">
                            <Success>Inventory update sucessfully</Success> </OTA_HotelInventoryDetailsInfoRS>';
                    return $return_array;
                    }
                }
                
            }
            else
            {
              $return_array = '<OTA_HotelInventoryDetailsInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance"
              xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01">
                           <Error>From Date should be less than or equal to to date and To date should be more that today date </Error> </OTA_HotelInventoryDetailsInfoRS>';
              return $return_array;
            }
          }
       }
    }
    public function rateUpdateToBookingjini($xml_data){
      $array_data=json_decode(json_encode(simplexml_load_string($xml_data)),true);
      $updateRate='';
      $apikey=$array_data['@attributes']['EchoToken'];
      $hotel_code=$array_data["RateAmountMessages"]["@attributes"]['HotelCode'];
      $hotel_name=isset($array_data["RateAmountMessages"]["@attributes"]['HotelName'])?isset($array_data["RateAmountMessages"]["@attributes"]['HotelName']):'';
      $getHotelId=PmsDetails::select('hotels','api_key','name','id')->get();
      $ip             = $this->ipAddress->getIPAddress();
      $cur_date       = date('Y-m-d');
      $count=0;
      $details=0;
      $pms_name='';
      $pms_id=0;
      foreach($getHotelId as $hoteldetails){
        if($hoteldetails->api_key == $apikey) {
           $count=$count+1;
           $details=strpos($hoteldetails->hotels,$hotel_code);
           $pms_name=$hoteldetails->name;
           $pms_id=$hoteldetails->id;
        }
      }
      if($count<=0) {
         $res='<OTA_HotelRateDetailsInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance"
         xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01">
                 <Error>Invalid apikey</Error>
             </OTA_HotelRateDetailsInfoRS>';
         return $res;
       }
       if($details<=0 || $hotel_code == "0"){
         $res='<OTA_HotelRateDetailsInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance"
        xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01">
                 <Error>Enter valid hotel code</Error>
             </OTA_HotelRateDetailsInfoRS>';
         return $res;
       }
       else if($hotel_code!='')
       {
         $rates_arr=array();
         $length_of_rates=0;
         $results_arr=array();
         if(!isset($array_data['RateAmountMessages']['RateAmountMessage']['StatusApplicationControl']) && sizeof($array_data['RateAmountMessages']['RateAmountMessage'] > 0)){

           $rates_arr=$array_data['RateAmountMessages']['RateAmountMessage'];

           $length_of_rates=sizeof($rates_arr);
           foreach($rates_arr as $rate){
             $data=array();
             $room_type_id=$rate['StatusApplicationControl']['@attributes']['InvTypeCode'];
             $rate_plan_id=$rate['StatusApplicationControl']['@attributes']['RatePlanCode'];
             $from_date=$rate['Rates']['Rate']['@attributes']['Start'];
             $end_date=$rate['Rates']['Rate']['@attributes']['End'];

             $data['hotel_id']=$hotel_code;
             $data['from_date']=date('Y-m-d',strtotime($from_date));
             $data['to_date']=date('Y-m-d',strtotime($end_date));
             $data['room_type_id']=$room_type_id;
             $data['rate_plan_id']=$rate_plan_id;
             $data['multiple_occupancy']=array();
             if($hotel_name == 'PMS'){
                foreach($rate['Rates']['Rate']['BaseByGuestAmts']['BaseByGuestAmt'] as $key => $occupancy){
                    $data['bar_price']=$occupancy['BarPrice'];
                    $data['multiple_occupancy'][0]=$occupancy['MultipleOccupancy'];
                }
             }
             else{
                foreach($rate['Rates']['Rate']['BaseByGuestAmts']['BaseByGuestAmt'] as $key => $occupancy){

                    if(sizeof($rate['Rates']['Rate']['BaseByGuestAmts']['BaseByGuestAmt'])>1)
                    {
                        if(sizeof($rate['Rates']['Rate']['BaseByGuestAmts']['BaseByGuestAmt'])== $key+1)
                        {
                        $data['bar_price']=$occupancy['@attributes']['AmountBeforeTax'];
                        }else{
                        array_push($data['multiple_occupancy'],$occupancy['@attributes']['AmountBeforeTax']);
                        }
                    }else{
                        $data['bar_price']=$occupancy['AmountBeforeTax'];
                        $data['multiple_occupancy'][0]=$data['bar_price'];
                    }
                }
             }
             $data['multiple_occupancy']=json_encode($data['multiple_occupancy']);
             $data['multiple_days']='{"Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1","Sun":"1"}';
             $data['user_id']=0;
             $data['block_status']=0;
             $data['client_ip']=$ip;
             $data['extra_adult_price']=0;
             $data['extra_child_price']=0;
             $data['pms_name']=$pms_name;
             $data['pms_id']=$pms_id;
             $ratePush= new PmsRatePush();
             $updateRate =$ratePush->fill($data)->save();
           }
         }else{
           $rate=$array_data['RateAmountMessages']['RateAmountMessage'];
           $length_of_rates=1;
           $data=array();
           $room_type_id=$rate['StatusApplicationControl']['@attributes']['InvTypeCode'];
           $rate_plan_id=$rate['StatusApplicationControl']['@attributes']['RatePlanCode'];
           $from_date=$rate['Rates']['Rate']['@attributes']['Start'];
           $end_date=$rate['Rates']['Rate']['@attributes']['End'];
           $data['hotel_id']=$hotel_code;
           $data['from_date']=date('Y-m-d',strtotime($from_date));
           $data['to_date']=date('Y-m-d',strtotime($end_date));
           $data['room_type_id']=$room_type_id;
           $data['rate_plan_id']=$rate_plan_id;
           $data['multiple_occupancy']=array();
           if($hotel_name == 'PMS'){
                foreach($rate['Rates']['Rate']['BaseByGuestAmts']['BaseByGuestAmt'] as $key => $occupancy){
                    if($key == 0)
                    {
                        $data['bar_price']=$occupancy["@attributes"]['BarPrice'];
                    }
                    else{
                        $data['multiple_occupancy']=$occupancy["@attributes"]['MultipleOccupancy'];
                    }
                }
            }
            else{
               foreach($rate['Rates']['Rate']['BaseByGuestAmts']['BaseByGuestAmt'] as $key => $occupancy){
                 if(sizeof($rate['Rates']['Rate']['BaseByGuestAmts']['BaseByGuestAmt'])== $key+1)
                 {
                   $data['bar_price']=$occupancy['@attributes']['AmountBeforeTax'];
                 }else{
                  array_push( $data['multiple_occupancy'],$occupancy['@attributes']['AmountBeforeTax']);
                 }
               }
            }
           $data['multiple_occupancy']=json_encode($data['multiple_occupancy']);
           $data['multiple_days']='{"Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1","Sun":"1"}';
           $data['user_id']=0;
           $data['block_status']=0;
           $data['client_ip']=$ip;
           $data['extra_adult_price']=0;
           $data['extra_child_price']=0;
           $data['pms_name']=$pms_name;
           $data['pms_id']=$pms_id;
           $ratePush= new PmsRatePush();
           $updateRate =$ratePush->fill($data)->save();
         }
       }
       if($updateRate){
         $return_array = '<OTA_HotelRateDetailsInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance"
         xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01">
                   <Success>Rates updated sucessfully</Success> </OTA_HotelRateDetailsInfoRS>';
         return $return_array;
       }else{
         $return_array = '<OTA_HotelRateDetailsInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance"
         xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01">
                    <Error>Room type or rate plan is not matching</Error>
               </OTA_HotelRateDetailsInfoRS>';
         return $return_array;
       }
    }
    public function bookingPushByBookingjini($hotel_id,$type,$booking_data,$customer_data){
      $ota_hotel_code=PmsRoom::select('pms_hotel_code')->where([['hotel_id',$hotel_id],['pms_name','Rms']])->first();
      $bookingjini_status="****";
      $bookingjini_state="$$$$";
      $bookingjini_resp="%%%%";
      $resp_bookingjini="@@@@";
      $push_bookings_xml='
      <'.$bookingjini_status.' xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance"
      xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01" EchoToken="22f10819-9fa1-4e27-8cf4-bookingjini
      " TimeStamp="'.date('Y-m-d').'T00:00:00.00+05:30" Version="4" '.$bookingjini_state.'>
              <'.$bookingjini_resp.'>
                  <'.$resp_bookingjini.' CreateDateTime="'.date('Y-m-d').'T00:00:00.00+05:30">
                  <UniqueID Type="14" ID="'.$booking_data['booking_id'].'" ID_Context="CRSConfirmNumber" />
                      <RoomStays>';
                      foreach($booking_data['room_stay'] as $room_data)
                      {
                          $bookingjini_room_type= $room_data['room_type_id'];
                          $bookingjini_plan_type= $room_data['rate_plan_id'];
                          $tot_amt=0;
                          $tax_amt=0;
                          foreach($room_data['rates'] as $book_data)
                          {
                              $tot_amt+=$book_data['amount'];
                              $tax_amt+=$book_data['tax_amount'];
                          }
                          $push_bookings_xml.='
                          <RoomStay>
                                 <Total AmountAfterTax="'.($tot_amt+$tax_amt).'" AmountBeforeTax="'.$tot_amt.'" CurrencyCode="INR"/>
                                  <RoomTypes>
                                      <RoomType IsRoom="true" NumberOfUnits="'.$room_data['no_of_rooms'].'" RoomTypeCode="'.$bookingjini_room_type.'" />
                                 </RoomTypes>
                                  <RatePlans>
                                      <RatePlan RatePlanCode="'.$bookingjini_plan_type.'"  />
                                      <RatePlanInclusions TaxInclusive="false"/>
                                  </RatePlans>
                              <RoomRates>
                                  <RoomRate EffectiveDate="'.$room_data['from_date'].'" ExpireDate="'.$room_data['to_date'].'" RoomTypeCode="'.$bookingjini_room_type.'" RatePlanCode="'.$bookingjini_plan_type.'">
                                      <Rates>';
                                      $tot_amt=0;
                                      $tax_amt=0;
                                      $room_statys="";
                                      foreach($room_data['rates'] as $book_data)
                                      {
                                          $tot_amt=$book_data['amount'];
                                          $tax_amt=$book_data['tax_amount'];

                                          $room_statys.='<Rate RateTimeUnit="Day" UnitMultiplier="1" EffectiveDate="'.$book_data['from_date'].'" ExpireDate="'.$book_data['to_date'].'">
                                              <Base AmountBeforeTax="'.$book_data['amount'].'" AmountAfterTax="'.($book_data['amount']+$book_data['tax_amount']).'" CurrencyCode="INR" />
                                              <Total AmountIncludingMarkup="'.($tot_amt+$tax_amt).'" AmountAfterTax="'.($tot_amt+$tax_amt).'" AmountBeforeTax="'.$tot_amt.'" CurrencyCode="INR"/>
                                          </Rate>';
                                      }
                          $push_bookings_xml.=$room_statys;
                          $push_bookings_xml.='</Rates>
                                  </RoomRate>
                              </RoomRates>
                              <GuestCounts IsPerRoom="true">
                                  <GuestCount AgeQualifyingCode="10" Count="'.$room_data['adults'].'" />
                              </GuestCounts>
                              <TimeSpan Start="'.$room_data['from_date'].'" End="'.$room_data['to_date'].'" />
                              <BasicPropertyInfo HotelCode="'.$hotel_id.'" HotelName="Bookingjini"/>
                          </RoomStay>';
                      }
                       $push_bookings_xml.='</RoomStays>
                      <ResGuests>
                          <ResGuest PrimaryIndicator="true">
                              <Profiles>
                                  <ProfileInfo>
                                      <Profile ProfileType="1">
                                      <Customer>
                                      <PersonName>
                                        <NamePrefix>Mr/Ms/Mis</NamePrefix>
                                        <GivenName>'.$customer_data['first_name'].'</GivenName>
                                        <MiddleName/>
                                        <Surname>'.$customer_data['last_name'].'</Surname>
                                        <NameSuffix/>
                                      </PersonName>
                                      <Telephone PhoneTechType="1" PhoneNumber="NA" FormattedInd="false" DefaultInd="true" />
                                      <Email EmailType="1">'.$customer_data['email_id'].'</Email>
                                      <Address Type="1" Remark="Personal"
                                        CompanyName="NA" FormattedInd="false" DefaultInd="false">
                                        <AddressLine>NA</AddressLine>
                                        <CityName>NA</CityName>
                                        <PostalCode>NA</PostalCode>
                                        <StateProv>NA</StateProv>
                                        <CountryName>NA</CountryName>
                                      </Address>
                                  </Customer>
                                      </Profile>
                                  </ProfileInfo>
                              </Profiles>
                          </ResGuest>
                      </ResGuests>
                      <ResGlobalInfo>
                          <HotelReservationIDs>
                              <HotelReservationID ResID_Type="14" ResID_Value="'.$booking_data['booking_id'].'" ResID_Source="CRS" ResID_SourceContext="CrsConfirmNumber"/>
                          </HotelReservationIDs>
                      </ResGlobalInfo>
                  </'.$resp_bookingjini.'>
              </'.$bookingjini_resp.'>
              </'.$bookingjini_status.'>';

        $bookingjini=new PmsReservation();
        $data['hotel_id']=$hotel_id;
        $data['pms_string']=$push_bookings_xml;
        $data['pms_status']='Bookingjini';
        if($bookingjini->fill($data)->save())
        {
          return $bookingjini->id;
        }
        else
        {
            return false;
        }
    }
    public function pushReservations($xml)
    {
        $url="pms link goes here";
        $headers = array (
              'Content-Type: application/xml'
        );
        $ch 	= curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml);
        $result = curl_exec($ch);
        curl_close($ch);
        if($result)
        {
            return true;
        }
    }
    public function bookingjiniCancelBooking($invoice){
      $customer_details=DB::table('user_table')->select('*')->where('user_id',$invoice->user_id)->first();
      $curdate=date('dmy',strtotime($invoice->booking_date));
      $booking_id=$curdate.$invoice->invoice_id;
      $push_bookings_xml='
      <OTA_CancelRQ xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance"
      xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01" EchoToken="22f10819-9fa1-4e27-8cf4-bookingjini" TimeStamp="'.date('Y-m-d').'T00:00:00.00+05:30" Version="4" CancelType="Cancel">
      <TPA_Extensions>
        <BasicPropertyInfo HotelCode="'.$invoice->hotel_id.'" HotelName="'.$invoice->hotel_name.'"/>
      </TPA_Extensions>
      <UniqueID Type="14" ID="'.$booking_id.'" ID_Context="CRSConfirmNumber"/>
      <Verification>
        <PersonName>
          <NamePrefix>Mr/Ms/Mis</NamePrefix>
          <GivenName>'.$customer_details->first_name.'</GivenName>
          <MiddleName/>
          <Surname>'.$customer_details->last_name.'</Surname>
          <NameSuffix/>
          </PersonName>
          <Telephone PhoneTechType="1" PhoneNumber="'.$customer_details->mobile.'" FormattedInd="false" DefaultInd="true" />
          <AddressInfo Type="1" Remark="Personal"
            CompanyName="NA" FormattedInd="false" DefaultInd="false">
            <AddressLine>NA</AddressLine>
            <CityName>NA</CityName>
            <PostalCode>NA</PostalCode>
            <StateProv>NA</StateProv>
            <CountryName>NA</CountryName>
          </AddressInfo>
          <Email EmailType="1">'.$customer_details->email_id.'</Email>
      </Verification>
      <CancellationContactPerson>
          <PersonName>
              <Surname></Surname>
          </PersonName>
      </CancellationContactPerson>
      <Reasons>
          <Reason Type="CancelReason"></Reason>
          <Reason Type="CancelCommit"></Reason>
          <Reason Type="CancelReasonCode"></Reason>
      </Reasons>
      </OTA_CancelRQ>';
      return $push_bookings_xml;
    }
    public function getBookingjiniStatus($hotel_id)
    {
        $resp=PmsRoom::where('hotel_id',$hotel_id)->where('pms_name','Bookingjini')->select('id')->first();
        if($resp)
        {
            return true;
        }
        else
        {
            return false;
        }
    }
    public function getBookingjiniString($bookingjini_id)
    {
        $resp=PmsReservation::where('id',$bookingjini_id)->select('pms_string')->first();
        if($resp->pms_string)
        {
            return $resp->pms_string;
        }
        else
        {
            return false;
        }
    }
    public function bookingjiniRestriction(Request $request)
    {
        $postdata           = $request->getContent();
        $push_array_data    = json_decode(json_encode(simplexml_load_string($postdata)), true);
        $apikey             = $push_array_data['@attributes']['EchoToken'];
        $getHotelId         = PmsDetails::select('hotels','api_key','name','id')->get();
        $version            = $push_array_data['@attributes']['Version'];
        $timestamp          = $push_array_data['@attributes']['TimeStamp'];
        $token              = $push_array_data['@attributes']['EchoToken'];
        $ip                 = $this->ipAddress->getIPAddress();
        $hotelcode          = $push_array_data["AvailStatusMessages"]["@attributes"]["HotelCode"];
        $hotel_id           = $this->pmsService->pmsHotel($hotelcode);
        $getOtaNames        = array();
        $api_check              = 0;
        foreach($getHotelId as $details){
            if($details->api_key == $apikey){
                $api_check=$api_check+1;
                $pms_name=$details->name;
                $pms_id=$details->id;
            }
        }
        if(!$postdata)
        {
            $return_array = '<?xml version="1.0" encoding="utf-8"?><OTA_HotelRestrictionInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance" xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01" EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
                            <Errors xmlns="">
                                <Error Type="118" Code="118">Please set POST Method</Error>
                            </Errors>
                            </OTA_HotelRestrictionInfoRS>';
            return response($return_array)->header('Content-Type', 'application/xml');
        }
        else if($hotel_id!='')
        {
            if($api_check > 0){
                if(isset($push_array_data["AvailStatusMessages"]["AvailStatusMessage"][0])){
                    $available_status  =  $push_array_data["AvailStatusMessages"]["AvailStatusMessage"];
                    $count=0;
                    foreach($available_status as $avl_data){

                        if(isset($avl_data["RestrictionStatus"])){
                            $rest_status     =   $avl_data["RestrictionStatus"]['@attributes']["Status"];
                            $on_restrict    =   $avl_data["RestrictionStatus"]['@attributes']["Restriction"];

                            if(($rest_status == 'Close' && $on_restrict == 'Arrival') || ($rest_status == 'Close' && $on_restrict == 'Departure') || ($rest_status == 'Close' && $on_restrict == 'Master')){
                                $getOtaNames = array();
                                if($on_restrict == 'Arrival'){
                                    $restriction_status =  'CTA';
                                }
                                else if($on_restrict == 'Departure'){
                                    $restriction_status =  'CTD';
                                }
                                else{
                                    $restriction_status =  'CTM';
                                }
                                $inv_type_code  =  $avl_data["StatusApplicationControl"]['@attributes']['InvTypeCode'];
                                $rateplan_code  =  $avl_data["StatusApplicationControl"]['@attributes']['RatePlanCode'];
                                $meal_plan_code =  $avl_data["StatusApplicationControl"]['@attributes']['MealPlanCode'];
                                $start_date     =  $avl_data["StatusApplicationControl"]['@attributes']['Start'];
                                $end_date       =  $avl_data["StatusApplicationControl"]['@attributes']['End'];
                                $multiple_days['Sun']   = $avl_data["StatusApplicationControl"]['@attributes']["Sun"];
                                $multiple_days['Mon']   = $avl_data["StatusApplicationControl"]['@attributes']["Mon"];
                                $multiple_days['Tue']   = $avl_data["StatusApplicationControl"]['@attributes']["Tue"];
                                $multiple_days['Wed']   = $avl_data["StatusApplicationControl"]['@attributes']["Weds"];
                                $multiple_days['Thu']   = $avl_data["StatusApplicationControl"]['@attributes']["Thur"];
                                $multiple_days['Fri']   = $avl_data["StatusApplicationControl"]['@attributes']["Fri"];
                                $multiple_days['Sat']   = $avl_data["StatusApplicationControl"]['@attributes']["Sat"];

                                if(isset($avl_data["StatusApplicationControl"]['DestinationSystemCodes'])){
                                    $isExist     = array();
                                    foreach ($avl_data["StatusApplicationControl"]['DestinationSystemCodes'] as $value) {
                                        if(is_array($value)){
                                            foreach($value as $val){
                                                $getOtaName=DB::table('cm_ota_credential_parameter')->select('ota_name')->where('id',$val)->first();
                                                $isExist=DB::table('cm_ota_details')->select('*')->where('hotel_id',$hotel_id)->where('ota_name',$getOtaName->ota_name)->where('is_active',1)->first();
                                                if(sizeof($isExist)>0){
                                                    $getOtaNames[]=$getOtaName->ota_name;
                                                }
                                            }
                                        }
                                        else{
                                            $getOtaName=DB::table('cm_ota_credential_parameter')->select('ota_name')->where('id',$value)->first();
                                            $isExist=DB::table('cm_ota_details')->select('*')->where('hotel_id',$hotel_id)->where('ota_name',$getOtaName->ota_name)->where('is_active',1)->first();
                                            if(sizeof($isExist)>0){
                                                $getOtaNames[]=$getOtaName->ota_name;
                                            }
                                        }
                                    }
                                }

                                if($inv_type_code != '' && $rateplan_code != '')
                                {
                                    $room_type_id = $this->pmsService->pmsRoom($hotel_id,$inv_type_code);
                                    $rate_plan_id = $this->pmsService->pmsRate($room_type_id,$meal_plan_code);
                                    $j=0;
                                    if(sizeof($getOtaNames)>0){
                                        foreach($getOtaNames as $ota_name){
                                            $ota_name=strtolower($ota_name);
                                            $condition=array('block_status'=>0,'room_type_id'=>$room_type_id,'hotel_id'=>$hotel_id,'channel'=>$ota_name);
                                            $get_number_of_room = OtaInventory::select('no_of_rooms')->where($condition)->where('date_from','<=',$start_date)->where('date_to','>=',$end_date)->first();
                                            if(sizeof($get_number_of_room)>0){
                                                $data=['date_from'=>$start_date,'date_to'=>$end_date,'room_type_id'=>$room_type_id,'user_id'=>0,'ota_id'=>0,'hotel_id'=>$hotel_id,'no_of_rooms'=>$get_number_of_room->no_of_rooms,'pms_id'=>$pms_id,'pms_name'=>$pms_name,'client_ip'=>$ip,'los'=>1,'ota_details'=>$ota_name,'restriction_status'=>$restriction_status];
                                                $user_id=$data['user_id'];
                                                $ota_id=$data['ota_id'];
                                                $client_ip=$this->ipAddress->getIPAddress();
                                                $data['multiple_days']=json_encode($multiple_days);
                                                $inventory = new PmsInvPush();
                                                $update_Inv=$inventory->fill($data)->save();
                                                if($update_Inv)
                                                {
                                                    $j++;
                                                }
                                            }
                                        }
                                        if(sizeof($getOtaNames) == $j){
                                            $count++;
                                        }
                                    }
                                    else{
                                        $getOtaNames = CmOtaDetails::select('ota_id','ota_name')->where('hotel_id',$hotel_id)->get();
                                        if(sizeof($getOtaNames)>0){
                                            foreach($getOtaNames as $ota){
                                                $ota_name=strtolower($ota->ota_name);
                                                $condition=array('block_status'=>0,'room_type_id'=>$room_type_id,'hotel_id'=>$hotel_id,'channel'=>$ota_name);
                                                $get_number_of_room = OtaInventory::select('no_of_rooms')->where($condition)->where('date_from','<=',$start_date)->where('date_to','>=',$end_date)->first();
                                                if(sizeof($get_number_of_room)>0){
                                                    $data=['date_from'=>$start_date,'date_to'=>$end_date,'room_type_id'=>$room_type_id,'user_id'=>0,'ota_id'=>0,'hotel_id'=>$hotel_id,'no_of_rooms'=>$get_number_of_room->no_of_rooms,'pms_id'=>$pms_id,'pms_name'=>$pms_name,'client_ip'=>$ip,'los'=>1,'ota_details'=>$ota_name,'restriction_status'=>$restriction_status];
                                                    $user_id=$data['user_id'];
                                                    $ota_id=$data['ota_id'];
                                                    $client_ip=$this->ipAddress->getIPAddress();
                                                    $data['multiple_days']=json_encode($multiple_days);
                                                    $inventory = new PmsInvPush();
                                                    $update_Inv=$inventory->fill($data)->save();
                                                    if($update_Inv)
                                                    {
                                                        $j++;
                                                    }
                                                }
                                            }
                                        }
                                        $condition=array('block_status'=>0,'room_type_id'=>$room_type_id,'hotel_id'=>$hotel_id);
                                        $get_number_of_room = Inventory::select('no_of_rooms')->where($condition)->where('date_from','<=',$start_date)->where('date_to','>=',$end_date)->first();
                                        if(sizeof($get_number_of_room)>0){
                                            $data=['date_from'=>$start_date,'date_to'=>$end_date,'room_type_id'=>$room_type_id,'user_id'=>0,'ota_id'=>0,'hotel_id'=>$hotel_id,'no_of_rooms'=>$get_number_of_room->no_of_rooms,'pms_id'=>$pms_id,'pms_name'=>$pms_name,'client_ip'=>$ip,'los'=>1,'ota_details'=>'be','restriction_status'=>$restriction_status];
                                            $user_id=$data['user_id'];
                                            $ota_id=$data['ota_id'];
                                            $client_ip=$this->ipAddress->getIPAddress();
                                            $data['multiple_days']=json_encode($multiple_days);
                                            $inventory = new PmsInvPush();
                                            $update_Inv=$inventory->fill($data)->save();
                                            if($update_Inv){
                                                $count++;
                                            }
                                        }
                                    }

                                }
                                else{
                                    $return_array='<?xml version="1.0" encoding="utf-8"?>
                                    <OTA_HotelRestrictionInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance" xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01" EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
                                    <Errors xmlns="">
                                    <Error Type="118" Code="118">InvalidRatePlan</Error>
                                    </Errors>
                                    </OTA_HotelRestrictionInfoRS>';
                                    return response($return_array)->header('Content-Type', 'application/xml');
                                }
                            }

                            if($rest_status == 'Open' && $on_restrict == 'Master'){
                                $getOtaNames = array();
                                $inv_type_code  =  $avl_data["StatusApplicationControl"]['@attributes']['InvTypeCode'];
                                $rateplan_code  =  $avl_data["StatusApplicationControl"]['@attributes']['RatePlanCode'];
                                $meal_plan_code =  $avl_data["StatusApplicationControl"]['@attributes']['MealPlanCode'];
                                $start_date     =  $avl_data["StatusApplicationControl"]['@attributes']['Start'];
                                $end_date       =  $avl_data["StatusApplicationControl"]['@attributes']['End'];
                                $multiple_days['Sun']   = $avl_data["StatusApplicationControl"]['@attributes']["Sun"];
                                $multiple_days['Mon']   = $avl_data["StatusApplicationControl"]['@attributes']["Mon"];
                                $multiple_days['Tue']   = $avl_data["StatusApplicationControl"]['@attributes']["Tue"];
                                $multiple_days['Wed']   = $avl_data["StatusApplicationControl"]['@attributes']["Weds"];
                                $multiple_days['Thu']   = $avl_data["StatusApplicationControl"]['@attributes']["Thur"];
                                $multiple_days['Fri']   = $avl_data["StatusApplicationControl"]['@attributes']["Fri"];
                                $multiple_days['Sat']   = $avl_data["StatusApplicationControl"]['@attributes']["Sat"];
                                if(isset($avl_data["StatusApplicationControl"]['DestinationSystemCodes'])){
                                    $isExist     = array();

                                    foreach ($avl_data["StatusApplicationControl"]['DestinationSystemCodes'] as $value) {
                                        if(is_array($value)){
                                            foreach($value as $val){
                                                $getOtaName=DB::table('cm_ota_credential_parameter')->select('ota_name')->where('id',$val)->first();
                                                $isExist=DB::table('cm_ota_details')->select('*')->where('hotel_id',$hotel_id)->where('ota_name',$getOtaName->ota_name)->where('is_active',1)->first();
                                                if(sizeof($isExist)>0){
                                                    $getOtaNames[]=$getOtaName->ota_name;
                                                }
                                            }
                                        }
                                        else{
                                            $getOtaName=DB::table('cm_ota_credential_parameter')->select('ota_name')->where('id',$value)->first();
                                            $isExist=DB::table('cm_ota_details')->select('*')->where('hotel_id',$hotel_id)->where('ota_name',$getOtaName->ota_name)->where('is_active',1)->first();
                                            if(sizeof($isExist)>0){
                                                $getOtaNames[]=$getOtaName->ota_name;
                                            }
                                        }
                                    }
                                }

                                if($inv_type_code != '' && $rateplan_code != '')
                                {
                                    $room_type_id = $this->pmsService->pmsRoom($hotel_id,$inv_type_code);
                                    $rate_plan_id = $this->pmsService->pmsRate($room_type_id,$meal_plan_code);
                                    if(sizeof($getOtaNames) > 0){
                                        $i=0;
                                        foreach($getOtaNames as $ota_name){
                                            $ota_name=strtolower($ota_name);
                                            $condition=array('block_status'=>0,'room_type_id'=>$room_type_id,'hotel_id'=>$hotel_id,'channel'=>$ota_name);
                                            $get_number_of_room = OtaInventory::select('no_of_rooms')->where($condition)->where('date_from','<=',$start_date)->where('date_to','>=',$end_date)->first();
                                            if(sizeof($get_number_of_room)>0){
                                                $data=['date_from'=>$start_date,'date_to'=>$end_date,'room_type_id'=>$room_type_id,'user_id'=>0,'ota_id'=>0,'hotel_id'=>$hotel_id,'no_of_rooms'=>$get_number_of_room->no_of_rooms,'pms_id'=>$pms_id,'pms_name'=>$pms_name,'client_ip'=>$ip,'los'=>1,'ota_details'=>$ota_name];
                                                $user_id=$data['user_id'];
                                                $ota_id=$data['ota_id'];
                                                $client_ip=$this->ipAddress->getIPAddress();
                                                $data['multiple_days']=json_encode($multiple_days);
                                                $inventory = new PmsInvPush();
                                                $update_Inv=$inventory->fill($data)->save();
                                                if($update_Inv){
                                                    $i++;
                                                }
                                            }
                                        }
                                        if(sizeof($getOtaNames) == $i){
                                            $count++;
                                        }
                                    }
                                    else{
                                        $client_ip=$this->ipAddress->getIPAddress();
                                        $getOtaDetails = CmOtaDetails::select('ota_id','ota_name')->where('hotel_id',$hotel_id)->get();
                                        if(sizeof($getOtaDetails) > 0){
                                          $i=0;
                                          foreach($getOtaDetails as $otas){

                                              $ota_name=strtolower($otas->ota_name);
                                              $condition=array('block_status'=>0,'room_type_id'=>$room_type_id,'hotel_id'=>$hotel_id,'channel'=>$ota_name);
                                              $get_number_of_room = OtaInventory::select('no_of_rooms')->where($condition)->where('date_from','<=',$start_date)->where('date_to','>=',$end_date)->first();
                                              if(sizeof($get_number_of_room)>0){
                                                $data=['date_from'=>$start_date,'date_to'=>$end_date,'room_type_id'=>$room_type_id,'user_id'=>0,'ota_id'=>0,'hotel_id'=>$hotel_id,'no_of_rooms'=>$get_number_of_room->no_of_rooms,'pms_id'=>$pms_id,'pms_name'=>$pms_name,'client_ip'=>$client_ip,'los'=>1,'ota_details'=>$ota_name];
                                                $user_id=$data['user_id'];
                                                $ota_id=$data['ota_id'];
                                                $data['multiple_days']=json_encode($multiple_days);
                                                $inventory = new PmsInvPush();
                                                $update_Inv=$inventory->fill($data)->save();
                                                if($update_Inv){
                                                    $i++;
                                                }
                                              }
                                          }
                                        }
                                        $condition=array('block_status'=>0,'room_type_id'=>$room_type_id,'hotel_id'=>$hotel_id);

                                        $get_number_of_room = Inventory::select('no_of_rooms')->where($condition)->where('date_from','<=',$start_date)->where('date_to','>=',$end_date)->first();
                                        if(sizeof($get_number_of_room)>0){
                                            $data=['date_from'=>$start_date,'date_to'=>$end_date,'room_type_id'=>$room_type_id,'user_id'=>0,'ota_id'=>0,'hotel_id'=>$hotel_id,'no_of_rooms'=>$get_number_of_room->no_of_rooms,'pms_id'=>$pms_id,'pms_name'=>$pms_name,'client_ip'=>$ip,'los'=>1,'ota_details'=>'be'];
                                            $user_id=$data['user_id'];
                                            $ota_id=$data['ota_id'];
                                            $client_ip=$this->ipAddress->getIPAddress();
                                            $data['multiple_days']=json_encode($multiple_days);
                                            $inventory = new PmsInvPush();
                                            $update_Inv=$inventory->fill($data)->save();
                                            if($update_Inv){
                                                $count++;
                                            }
                                        }
                                    }
                                }
                                else{
                                    $return_array='<?xml version="1.0" encoding="utf-8"?>
                                    <OTA_HotelRestrictionInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance" xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01" EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
                                    <Errors xmlns="">
                                    <Error Type="118" Code="118">InvalidRatePlan</Error>
                                    </Errors>
                                    </OTA_HotelRestrictionInfoRS>';
                                    return response($return_array)->header('Content-Type', 'application/xml');
                                }
                            }
                            $rest_status_array=['Open','Close'];
                            if(!in_array($rest_status,$rest_status_array)){
                                $return_array='<?xml version="1.0" encoding="utf-8"?>
                                <OTA_HotelRestrictionInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance" xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01" EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
                                <Errors xmlns="">
                                <Error Type="118" Code="118">Invalid XML</Error>
                                </Errors>
                                </OTA_HotelRestrictionInfoRS>';
                                return response($return_array)->header('Content-Type', 'application/xml');
                            }
                        }
                        else
                        {
                            $los = $avl_data["LengthsOfStay"]["LengthOfStay"]["@attributes"]["Time"];
                            $getOtaNames = array();
                            $inv_type_code  =  $avl_data["StatusApplicationControl"]['@attributes']['InvTypeCode'];
                            $rateplan_code  =  $avl_data["StatusApplicationControl"]['@attributes']['RatePlanCode'];
                            $meal_plan_code =  $avl_data["StatusApplicationControl"]['@attributes']['MealPlanCode'];
                            $start_date     =  $avl_data["StatusApplicationControl"]['@attributes']['Start'];
                            $end_date       =  $avl_data["StatusApplicationControl"]['@attributes']['End'];
                            $multiple_days['Sun']   = $avl_data["StatusApplicationControl"]['@attributes']["Sun"];
                            $multiple_days['Mon']   = $avl_data["StatusApplicationControl"]['@attributes']["Mon"];
                            $multiple_days['Tue']   = $avl_data["StatusApplicationControl"]['@attributes']["Tue"];
                            $multiple_days['Wed']   = $avl_data["StatusApplicationControl"]['@attributes']["Weds"];
                            $multiple_days['Thu']   = $avl_data["StatusApplicationControl"]['@attributes']["Thur"];
                            $multiple_days['Fri']   = $avl_data["StatusApplicationControl"]['@attributes']["Fri"];
                            $multiple_days['Sat']   = $avl_data["StatusApplicationControl"]['@attributes']["Sat"];
                            if(isset($avl_data["StatusApplicationControl"]['DestinationSystemCodes'])){
                                $isExist     = array();

                                foreach ($avl_data["StatusApplicationControl"]['DestinationSystemCodes'] as $value) {
                                    if(is_array($value)){
                                        foreach($value as $val){
                                            $getOtaName=DB::table('cm_ota_credential_parameter')->select('ota_name')->where('id',$val)->first();
                                            $isExist=DB::table('cm_ota_details')->select('*')->where('hotel_id',$hotel_id)->where('ota_name',$getOtaName->ota_name)->where('is_active',1)->first();
                                            if(sizeof($isExist)>0){
                                                $getOtaNames[]=$getOtaName->ota_name;
                                            }
                                        }
                                    }
                                    else{
                                        $getOtaName=DB::table('cm_ota_credential_parameter')->select('ota_name')->where('id',$value)->first();
                                        $isExist=DB::table('cm_ota_details')->select('*')->where('hotel_id',$hotel_id)->where('ota_name',$getOtaName->ota_name)->where('is_active',1)->first();
                                        if(sizeof($isExist)>0){
                                            $getOtaNames[]=$getOtaName->ota_name;
                                        }
                                    }
                                }
                            }

                            if($inv_type_code != '' && $rateplan_code != '')
                            {
                                $room_type_id = $this->pmsService->pmsRoom($hotel_id,$inv_type_code);
                                $rate_plan_id = $this->pmsService->pmsRate($room_type_id,$meal_plan_code);
                                if(sizeof($getOtaNames) > 0){
                                    $i=0;
                                    foreach($getOtaNames as $ota_name){
                                        $ota_name=strtolower($ota_name);
                                        $condition=array('block_status'=>0,'room_type_id'=>$room_type_id,'hotel_id'=>$hotel_id,'channel'=>$ota_name);
                                        $get_number_of_room = OtaInventory::select('no_of_rooms')->where($condition)->where('date_from','<=',$start_date)->where('date_to','>=',$end_date)->first();
                                        if(sizeof($get_number_of_room)>0){
                                            $data=['date_from'=>$start_date,'date_to'=>$end_date,'room_type_id'=>$room_type_id,'user_id'=>0,'ota_id'=>0,'hotel_id'=>$hotel_id,'no_of_rooms'=>$get_number_of_room->no_of_rooms,'pms_id'=>$pms_id,'pms_name'=>$pms_name,'client_ip'=>$ip,'los'=>$los,'ota_details'=>$ota_name];
                                            $user_id=$data['user_id'];
                                            $ota_id=$data['ota_id'];
                                            $client_ip=$this->ipAddress->getIPAddress();
                                            $data['multiple_days']=json_encode($multiple_days);
                                            $inventory = new PmsInvPush();
                                            $update_Inv=$inventory->fill($data)->save();
                                            if($update_Inv){
                                                $i++;
                                            }
                                        }
                                    }
                                    if(sizeof($getOtaNames) == $i){
                                        $count++;
                                    }
                                }
                                else{
                                    $client_ip=$this->ipAddress->getIPAddress();
                                        $getOtaDetails = CmOtaDetails::select('ota_id','ota_name')->where('hotel_id',$hotel_id)->get();
                                        if(sizeof($getOtaDetails) > 0){
                                          $i=0;
                                          foreach($getOtaDetails as $otas){

                                              $ota_name=strtolower($otas->ota_name);
                                              $condition=array('block_status'=>0,'room_type_id'=>$room_type_id,'hotel_id'=>$hotel_id,'channel'=>$ota_name);
                                              $get_number_of_room = OtaInventory::select('no_of_rooms')->where($condition)->where('date_from','<=',$start_date)->where('date_to','>=',$end_date)->first();
                                              if(sizeof($get_number_of_room)>0){
                                                $data=['date_from'=>$start_date,'date_to'=>$end_date,'room_type_id'=>$room_type_id,'user_id'=>0,'ota_id'=>0,'hotel_id'=>$hotel_id,'no_of_rooms'=>$get_number_of_room->no_of_rooms,'pms_id'=>$pms_id,'pms_name'=>$pms_name,'client_ip'=>$client_ip,'los'=>$los,'ota_details'=>$ota_name];
                                                $user_id=$data['user_id'];
                                                $ota_id=$data['ota_id'];
                                                $data['multiple_days']=json_encode($multiple_days);
                                                $inventory = new PmsInvPush();
                                                $update_Inv=$inventory->fill($data)->save();
                                                if($update_Inv){
                                                    $i++;
                                                }
                                            }
                                          }
                                        }
                                    $condition=array('block_status'=>0,'room_type_id'=>$room_type_id,'hotel_id'=>$hotel_id);

                                    $get_number_of_room = Inventory::select('no_of_rooms')->where($condition)->where('date_from','<=',$start_date)->where('date_to','>=',$end_date)->first();
                                    if(sizeof($get_number_of_room)>0){
                                        $data=['date_from'=>$start_date,'date_to'=>$end_date,'room_type_id'=>$room_type_id,'user_id'=>0,'ota_id'=>0,'hotel_id'=>$hotel_id,'no_of_rooms'=>$get_number_of_room->no_of_rooms,'pms_id'=>$pms_id,'pms_name'=>$pms_name,'client_ip'=>$ip,'los'=>$los,'ota_details'=>'be'];
                                        $user_id=$data['user_id'];
                                        $ota_id=$data['ota_id'];
                                        $data['multiple_days']=json_encode($multiple_days);
                                        $inventory = new PmsInvPush();
                                        $update_Inv=$inventory->fill($data)->save();
                                        if($update_Inv){
                                            $count++;
                                        }
                                    }
                                }
                            }
                            else{
                                $return_array='<?xml version="1.0" encoding="utf-8"?>
                                <OTA_HotelRestrictionInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance" xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01" EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
                                <Errors xmlns="">
                                <Error Type="118" Code="118">InvalidRatePlan</Error>
                                </Errors>
                                </OTA_HotelRestrictionInfoRS>';
                                return response($return_array)->header('Content-Type', 'application/xml');
                            }
                        }
                    }
                    if($count == sizeof($available_status))
                    {
                        $return_array =  '<?xml version="1.0" encoding="utf-8"?>
                        <OTA_HotelRestrictionInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance" xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01" EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
                        <Success />
                        </OTA_HotelRestrictionInfoRS>';
                        return response($return_array)->header('Content-Type', 'application/xml');
                    }
                    else{
                        $return_array='<?xml version="1.0" encoding="utf-8"?>
                        <OTA_HotelRestrictionInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance" xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01" EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
                        <Errors xmlns="">
                        <Error Type="118" Code="118">Opps! unable to update</Error>
                        </Errors>
                        </OTA_HotelRestrictionInfoRS>';
                        return response($return_array)->header('Content-Type', 'application/xml');
                    }
                }
                else{
                    $available_status  =  $push_array_data["AvailStatusMessages"]["AvailStatusMessage"];
                    if(isset($available_status["RestrictionStatus"])){
                        $rest_status     =   $available_status["RestrictionStatus"]['@attributes']["Status"];
                        $on_restrict    =   $available_status["RestrictionStatus"]['@attributes']["Restriction"];

                        if(($rest_status == 'Close' && $on_restrict == 'Arrival') || ($rest_status == 'Close' && $on_restrict == 'Departure') || ($rest_status == 'Close' && $on_restrict == 'Master')){
                            $getOtaNames = array();
                            if($on_restrict == 'Arrival'){
                                $restriction_status =  'CTA';
                            }
                            else if($on_restrict == 'Departure'){
                                $restriction_status =  'CTD';
                            }
                            else{
                                $restriction_status =  'CTM';
                            }
                            $inv_type_code  =  $available_status["StatusApplicationControl"]['@attributes']['InvTypeCode'];
                            $rateplan_code  =  $available_status["StatusApplicationControl"]['@attributes']['RatePlanCode'];
                            $meal_plan_code =  $available_status["StatusApplicationControl"]['@attributes']['MealPlanCode'];
                            $start_date     =  $available_status["StatusApplicationControl"]['@attributes']['Start'];
                            $end_date       =  $available_status["StatusApplicationControl"]['@attributes']['End'];
                            $multiple_days['Sun']   = $available_status["StatusApplicationControl"]['@attributes']["Sun"];
                            $multiple_days['Mon']   = $available_status["StatusApplicationControl"]['@attributes']["Mon"];
                            $multiple_days['Tue']   = $available_status["StatusApplicationControl"]['@attributes']["Tue"];
                            $multiple_days['Wed']   = $available_status["StatusApplicationControl"]['@attributes']["Weds"];
                            $multiple_days['Thu']   = $available_status["StatusApplicationControl"]['@attributes']["Thur"];
                            $multiple_days['Fri']   = $available_status["StatusApplicationControl"]['@attributes']["Fri"];
                            $multiple_days['Sat']   = $available_status["StatusApplicationControl"]['@attributes']["Sat"];

                            if(isset($available_status["StatusApplicationControl"]['DestinationSystemCodes'])){
                                $isExist     = array();
                                foreach ($available_status["StatusApplicationControl"]['DestinationSystemCodes'] as $value) {
                                    if(is_array($value)){
                                        foreach($value as $val){
                                            $getOtaName=DB::table('cm_ota_credential_parameter')->select('ota_name')->where('id',$val)->first();
                                            $isExist=DB::table('cm_ota_details')->select('*')->where('hotel_id',$hotel_id)->where('ota_name',$getOtaName->ota_name)->where('is_active',1)->first();
                                            if(sizeof($isExist)>0){
                                                $getOtaNames[]=$getOtaName->ota_name;
                                            }
                                        }
                                    }
                                    else{
                                        $getOtaName=DB::table('cm_ota_credential_parameter')->select('ota_name')->where('id',$value)->first();
                                        $isExist=DB::table('cm_ota_details')->select('*')->where('hotel_id',$hotel_id)->where('ota_name',$getOtaName->ota_name)->where('is_active',1)->first();
                                        if(sizeof($isExist)>0){
                                            $getOtaNames[]=$getOtaName->ota_name;
                                        }
                                    }
                                }
                            }

                            if($inv_type_code != '' && $rateplan_code != '')
                            {
                                $room_type_id = $this->pmsService->pmsRoom($hotel_id,$inv_type_code);
                                $rate_plan_id = $this->pmsService->pmsRate($room_type_id,$meal_plan_code);
                                $j=0;
                                foreach($getOtaNames as $ota_name){
                                    $ota_name=strtolower($ota_name);
                                    $condition=array('block_status'=>0,'room_type_id'=>$room_type_id,'hotel_id'=>$hotel_id,'channel'=>$ota_name);
                                    $get_number_of_room = OtaInventory::select('no_of_rooms')->where($condition)->where('date_from','<=',$start_date)->where('date_to','>=',$end_date)->first();
                                    if(sizeof($get_number_of_room)>0){
                                        $data=['date_from'=>$start_date,'date_to'=>$end_date,'room_type_id'=>$room_type_id,'no_of_rooms'=>$get_number_of_room->no_of_rooms,'user_id'=>0,'ota_id'=>0,'hotel_id'=>$hotel_id,'pms_id'=>$pms_id,'pms_name'=>$pms_name,'client_ip'=>$ip,'restriction_status'=>$restriction_status,'ota_details'=>$ota_name];
                                        $user_id=$data['user_id'];
                                        $ota_id=$data['ota_id'];
                                        $data['multiple_days']=json_encode($multiple_days);
                                        $inventory = new PmsInvPush();
                                        $update_Inv=$inventory->fill($data)->save();
                                        if($update_Inv)
                                        {
                                            $j++;
                                        }
                                    }
                                }
                                if($j)
                                {
                                    $return_array =  '<?xml version="1.0" encoding="utf-8"?>
                                    <OTA_HotelRestrictionInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance" xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01" EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
                                    <Success />
                                    </OTA_HotelRestrictionInfoRS>';
                                    return response($return_array)->header('Content-Type', 'application/xml');
                                }
                                else{
                                    $return_array='<?xml version="1.0" encoding="utf-8"?>
                                    <OTA_HotelRestrictionInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance" xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01" EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
                                    <Errors xmlns="">
                                    <Error Type="118" Code="118">Opps! unable to update</Error>
                                    </Errors>
                                    </OTA_HotelRestrictionInfoRS>';
                                    return response($return_array)->header('Content-Type', 'application/xml');
                                }
                            }
                            else{
                                $return_array='<?xml version="1.0" encoding="utf-8"?>
                                <OTA_HotelRestrictionInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance" xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01" EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
                                <Errors xmlns="">
                                <Error Type="118" Code="118">InvalidRatePlan</Error>
                                </Errors>
                                </OTA_HotelRestrictionInfoRS>';
                                return response($return_array)->header('Content-Type', 'application/xml');
                            }
                        }
                         if($rest_status == 'Open' && $on_restrict == 'Master'){

                            $getOtaNames = array();
                            $inv_type_code  =  $available_status["StatusApplicationControl"]['@attributes']['InvTypeCode'];
                            $rateplan_code  =  $available_status["StatusApplicationControl"]['@attributes']['RatePlanCode'];
                            $meal_plan_code =  $available_status["StatusApplicationControl"]['@attributes']['MealPlanCode'];
                            $start_date     =  $available_status["StatusApplicationControl"]['@attributes']['Start'];
                            $end_date       =  $available_status["StatusApplicationControl"]['@attributes']['End'];
                            $multiple_days['Sun']   = $available_status["StatusApplicationControl"]['@attributes']["Sun"];
                            $multiple_days['Mon']   = $available_status["StatusApplicationControl"]['@attributes']["Mon"];
                            $multiple_days['Tue']   = $available_status["StatusApplicationControl"]['@attributes']["Tue"];
                            $multiple_days['Wed']   = $available_status["StatusApplicationControl"]['@attributes']["Weds"];
                            $multiple_days['Thu']   = $available_status["StatusApplicationControl"]['@attributes']["Thur"];
                            $multiple_days['Fri']   = $available_status["StatusApplicationControl"]['@attributes']["Fri"];
                            $multiple_days['Sat']   = $available_status["StatusApplicationControl"]['@attributes']["Sat"];

                            if(isset($available_status["StatusApplicationControl"]['DestinationSystemCodes'])){
                                $isExist     = array();

                                foreach ($available_status["StatusApplicationControl"]['DestinationSystemCodes'] as $value) {
                                    if(is_array($value)){
                                        foreach($value as $val){
                                            $getOtaName=DB::table('cm_ota_credential_parameter')->select('ota_name')->where('id',$val)->first();
                                            $isExist=DB::table('cm_ota_details')->select('*')->where('hotel_id',$hotel_id)->where('ota_name',$getOtaName->ota_name)->where('is_active',1)->first();
                                            if(sizeof($isExist)>0){
                                                $getOtaNames[]=$getOtaName->ota_name;
                                            }
                                        }
                                    }
                                    else{
                                        $getOtaName=DB::table('cm_ota_credential_parameter')->select('ota_name')->where('id',$value)->first();
                                        $isExist=DB::table('cm_ota_details')->select('*')->where('hotel_id',$hotel_id)->where('ota_name',$getOtaName->ota_name)->where('is_active',1)->first();
                                        if(sizeof($isExist)>0){
                                            $getOtaNames[]=$getOtaName->ota_name;
                                        }
                                    }
                                }
                            }

                            if($inv_type_code != '' && $rateplan_code != '')
                            {
                                $room_type_id = $this->pmsService->pmsRoom($hotel_id,$inv_type_code);
                                $rate_plan_id = $this->pmsService->pmsRate($room_type_id,$meal_plan_code);
                                if(sizeof($getOtaNames) > 0){
                                    $i=0;
                                    foreach($getOtaNames as $ota_name){
                                        $ota_name=strtolower($ota_name);
                                        $condition=array('block_status'=>0,'room_type_id'=>$room_type_id,'hotel_id'=>$hotel_id,'channel'=>$ota_name);
                                        $get_number_of_room = OtaInventory::select('no_of_rooms')->where($condition)->where('date_from','<=',$start_date)->where('date_to','>=',$end_date)->first();
                                        if(sizeof($get_number_of_room)>0){
                                            $data=['date_from'=>$start_date,'date_to'=>$end_date,'room_type_id'=>$room_type_id,'user_id'=>0,'ota_id'=>0,'hotel_id'=>$hotel_id,'no_of_rooms'=>$get_number_of_room->no_of_rooms,'pms_id'=>$pms_id,'pms_name'=>$pms_name,'client_ip'=>$ip,'los'=>1,'ota_details'=>$ota_name];
                                            $user_id=$data['user_id'];
                                            $ota_id=$data['ota_id'];
                                            $client_ip=$this->ipAddress->getIPAddress();
                                            $data['multiple_days']=json_encode($multiple_days);
                                            $inventory = new PmsInvPush();
                                            $update_Inv=$inventory->fill($data)->save();
                                            if($update_Inv){
                                                $i++;
                                            }
                                        }
                                    }
                                    if(sizeof($getOtaNames) == $i){
                                        $return_array =  '<?xml version="1.0" encoding="utf-8"?>
                                        <OTA_HotelRestrictionInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance" xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01" EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
                                        <Success />
                                        </OTA_HotelRestrictionInfoRS>';
                                        return response($return_array)->header('Content-Type', 'application/xml');
                                    }
                                    else{
                                        $return_array='<?xml version="1.0" encoding="utf-8"?>
                                        <OTA_HotelRestrictionInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance" xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01 "EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
                                        <Errors xmlns="">
                                        <Error Type="118" Code="118">Opps! unable to update</Error>
                                        </Errors>
                                        </OTA_HotelRestrictionInfoRS>';
                                        return response($return_array)->header('Content-Type', 'application/xml');
                                    }
                                }
                                else{
                                    $client_ip=$this->ipAddress->getIPAddress();
                                        $getOtaDetails = CmOtaDetails::select('ota_id','ota_name')->where('hotel_id',$hotel_id)->get();
                                        if(sizeof($getOtaDetails) > 0){
                                          $i=0;
                                          foreach($getOtaDetails as $otas){

                                              $ota_name=strtolower($otas->ota_name);
                                              $condition=array('block_status'=>0,'room_type_id'=>$room_type_id,'hotel_id'=>$hotel_id,'channel'=>$ota_name);
                                              $get_number_of_room = OtaInventory::select('no_of_rooms')->where($condition)->where('date_from','<=',$start_date)->where('date_to','>=',$end_date)->first();
                                              if(sizeof($get_number_of_room)>0){
                                                $data=['date_from'=>$start_date,'date_to'=>$end_date,'room_type_id'=>$room_type_id,'user_id'=>0,'ota_id'=>0,'hotel_id'=>$hotel_id,'no_of_rooms'=>$get_number_of_room->no_of_rooms,'pms_id'=>$pms_id,'pms_name'=>$pms_name,'client_ip'=>$client_ip,'los'=>1,'ota_details'=>$ota_name];
                                                $user_id=$data['user_id'];
                                                $ota_id=$data['ota_id'];
                                                $data['multiple_days']=json_encode($multiple_days);
                                                $inventory = new PmsInvPush();
                                                $update_Inv=$inventory->fill($data)->save();
                                                if($update_Inv){
                                                    $i++;
                                                }
                                              }
                                          }
                                        }
                                    $condition=array('block_status'=>0,'room_type_id'=>$room_type_id,'hotel_id'=>$hotel_id);

                                    $get_number_of_room = Inventory::select('no_of_rooms')->where($condition)->where('date_from','<=',$start_date)->where('date_to','>=',$end_date)->first();
                                    if(sizeof($get_number_of_room)>0){
                                        $data=['date_from'=>$start_date,'date_to'=>$end_date,'room_type_id'=>$room_type_id,'user_id'=>0,'ota_id'=>0,'hotel_id'=>$hotel_id,'no_of_rooms'=>$get_number_of_room->no_of_rooms,'pms_id'=>$pms_id,'pms_name'=>$pms_name,'client_ip'=>$ip,'los'=>1,'ota_details'=>'be'];
                                        $user_id=$data['user_id'];
                                        $ota_id=$data['ota_id'];
                                        $data['multiple_days']=json_encode($multiple_days);
                                        $inventory = new PmsInvPush();
                                        $update_Inv=$inventory->fill($data)->save();
                                        if($update_Inv){
                                            $return_array =  '<?xml version="1.0" encoding="utf-8"?>
                                            <OTA_HotelRestrictionInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance" xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01" EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
                                            <Success />
                                            </OTA_HotelRestrictionInfoRS>';
                                            return response($return_array)->header('Content-Type', 'application/xml');
                                        }
                                        else
                                        {
                                            $return_array='<?xml version="1.0" encoding="utf-8"?>
                                            <OTA_HotelRestrictionInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance" xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01" EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
                                            <Errors xmlns="">
                                            <Error Type="118" Code="118">Opps! unable to update</Error>
                                            </Errors>
                                            </OTA_HotelRestrictionInfoRS>';
                                            return response($return_array)->header('Content-Type', 'application/xml');
                                        }
                                    }
                                }
                            }
                            else{
                                $return_array='<?xml version="1.0" encoding="utf-8"?>
                                <OTA_HotelRestrictionInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance" xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01" EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
                                <Errors xmlns="">
                                <Error Type="118" Code="118">InvalidRatePlan</Error>
                                </Errors>
                                </OTA_HotelRestrictionInfoRS>';
                                return response($return_array)->header('Content-Type', 'application/xml');
                            }
                        }
                        if($rest_status != 'Open' || $rest_status != 'Close'){
                            $return_array='<?xml version="1.0" encoding="utf-8"?>
                            <OTA_HotelRestrictionInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance" xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01" EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
                            <Errors xmlns="">
                            <Error Type="118" Code="118">Invalid XML</Error>
                            </Errors>
                            </OTA_HotelRestrictionInfoRS>';
                            return response($return_array)->header('Content-Type', 'application/xml');
                        }
                    }
                    else{
                            $los =$available_status ["LengthsOfStay"]["LengthOfStay"]["@attributes"]["Time"];

                            $getOtaNames = array();
                            $inv_type_code  =  $available_status["StatusApplicationControl"]['@attributes']['InvTypeCode'];
                            $rateplan_code  =  $available_status["StatusApplicationControl"]['@attributes']['RatePlanCode'];
                            $meal_plan_code =  $available_status["StatusApplicationControl"]['@attributes']['MealPlanCode'];
                            $start_date     =  $available_status["StatusApplicationControl"]['@attributes']['Start'];
                            $end_date       =  $available_status["StatusApplicationControl"]['@attributes']['End'];
                            $multiple_days['Sun']   = $available_status["StatusApplicationControl"]['@attributes']["Sun"];
                            $multiple_days['Mon']   = $available_status["StatusApplicationControl"]['@attributes']["Mon"];
                            $multiple_days['Tue']   = $available_status["StatusApplicationControl"]['@attributes']["Tue"];
                            $multiple_days['Wed']   = $available_status["StatusApplicationControl"]['@attributes']["Weds"];
                            $multiple_days['Thu']   = $available_status["StatusApplicationControl"]['@attributes']["Thur"];
                            $multiple_days['Fri']   = $available_status["StatusApplicationControl"]['@attributes']["Fri"];
                            $multiple_days['Sat']   = $available_status["StatusApplicationControl"]['@attributes']["Sat"];

                            if(isset($available_status["StatusApplicationControl"]['DestinationSystemCodes'])){
                                $isExist     = array();

                                foreach ($available_status["StatusApplicationControl"]['DestinationSystemCodes'] as $value) {
                                    if(is_array($value)){
                                        foreach($value as $val){
                                            $getOtaName=DB::table('cm_ota_credential_parameter')->select('ota_name')->where('id',$val)->first();
                                            $isExist=DB::table('cm_ota_details')->select('*')->where('hotel_id',$hotel_id)->where('ota_name',$getOtaName->ota_name)->where('is_active',1)->first();
                                            if(sizeof($isExist)>0){
                                                $getOtaNames[]=$getOtaName->ota_name;
                                            }
                                        }
                                    }
                                    else{
                                        $getOtaName=DB::table('cm_ota_credential_parameter')->select('ota_name')->where('id',$value)->first();
                                        $isExist=DB::table('cm_ota_details')->select('*')->where('hotel_id',$hotel_id)->where('ota_name',$getOtaName->ota_name)->where('is_active',1)->first();
                                        if(sizeof($isExist)>0){
                                            $getOtaNames[]=$getOtaName->ota_name;
                                        }
                                    }
                                }
                            }

                            if($inv_type_code != '' && $rateplan_code != '')
                            {
                                $room_type_id = $this->pmsService->pmsRoom($hotel_id,$inv_type_code);
                                $rate_plan_id = $this->pmsService->pmsRate($room_type_id,$meal_plan_code);
                                if(sizeof($getOtaNames) > 0){
                                    $i=0;
                                    foreach($getOtaNames as $ota_name){
                                        $ota_name=strtolower($ota_name);
                                        $condition=array('block_status'=>0,'room_type_id'=>$room_type_id,'hotel_id'=>$hotel_id,'channel'=>$ota_name);
                                        $get_number_of_room = OtaInventory::select('no_of_rooms')->where($condition)->where('date_from','>=',$start_date)->where('date_to','<=',$end_date)->first();
                                        if(sizeof($get_number_of_room)>0){
                                            $data=['date_from'=>$start_date,'date_to'=>$end_date,'room_type_id'=>$room_type_id,'user_id'=>0,'ota_id'=>0,'hotel_id'=>$hotel_id,'no_of_rooms'=>$get_number_of_room->no_of_rooms,'pms_id'=>$pms_id,'pms_name'=>$pms_name,'client_ip'=>$ip,'los'=>$los,'ota_details'=>$ota_name];
                                            $user_id=$data['user_id'];
                                            $ota_id=$data['ota_id'];
                                            $client_ip=$this->ipAddress->getIPAddress();
                                            $data['multiple_days']=json_encode($multiple_days);
                                            $inventory = new PmsInvPush();
                                            $update_Inv=$inventory->fill($data)->save();
                                            if($update_Inv){
                                                $i++;
                                            }
                                        }
                                    }
                                    if(sizeof($getOtaNames) == $i){
                                        $return_array =  '<?xml version="1.0" encoding="utf-8"?>
                                        <OTA_HotelRestrictionInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance" xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01" EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
                                        <Success />
                                        </OTA_HotelRestrictionInfoRS>';
                                        return response($return_array)->header('Content-Type', 'application/xml');
                                    }
                                    else{
                                        $return_array='<?xml version="1.0" encoding="utf-8"?>
                                        <OTA_HotelRestrictionInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance" xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01" EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
                                        <Errors xmlns="">
                                        <Error Type="118" Code="118">Opps! unable to update</Error>
                                        </Errors>
                                        </OTA_HotelRestrictionInfoRS>';
                                        return response($return_array)->header('Content-Type', 'application/xml');
                                    }
                                }
                                else{
                                    $client_ip=$this->ipAddress->getIPAddress();
                                        $getOtaDetails = CmOtaDetails::select('ota_id','ota_name')->where('hotel_id',$hotel_id)->get();
                                        if(sizeof($getOtaDetails) > 0){
                                          $i=0;
                                          foreach($getOtaDetails as $otas){

                                              $ota_name=strtolower($otas->ota_name);
                                              $condition=array('block_status'=>0,'room_type_id'=>$room_type_id,'hotel_id'=>$hotel_id,'channel'=>$ota_name);
                                              $get_number_of_room = OtaInventory::select('no_of_rooms')->where($condition)->where('date_from','<=',$start_date)->where('date_to','>=',$end_date)->first();
                                              if(sizeof($get_number_of_room)>0){
                                                $data=['date_from'=>$start_date,'date_to'=>$end_date,'room_type_id'=>$room_type_id,'user_id'=>0,'ota_id'=>0,'hotel_id'=>$hotel_id,'no_of_rooms'=>$get_number_of_room->no_of_rooms,'pms_id'=>$pms_id,'pms_name'=>$pms_name,'client_ip'=>$client_ip,'los'=>$los,'ota_details'=>$ota_name];
                                                $user_id=$data['user_id'];
                                                $ota_id=$data['ota_id'];
                                                $data['multiple_days']=json_encode($multiple_days);
                                                $inventory = new PmsInvPush();
                                                $update_Inv=$inventory->fill($data)->save();
                                                if($update_Inv){
                                                    $i++;
                                                }
                                              }
                                          }
                                        }
                                    $condition=array('block_status'=>0,'room_type_id'=>$room_type_id,'hotel_id'=>$hotel_id);

                                    $get_number_of_room = Inventory::select('no_of_rooms')->where($condition)->where('date_from','>=',$start_date)->where('date_to','<=',$end_date)->first();
                                    if(sizeof($get_number_of_room)>0){
                                        $data=['date_from'=>$start_date,'date_to'=>$end_date,'room_type_id'=>$room_type_id,'user_id'=>0,'ota_id'=>0,'hotel_id'=>$hotel_id,'no_of_rooms'=>$get_number_of_room->no_of_rooms,'pms_id'=>$pms_id,'pms_name'=>$pms_name,'client_ip'=>$ip,'los'=>$los,'ota_details'=>'be'];
                                        $user_id=$data['user_id'];
                                        $ota_id=$data['ota_id'];
                                        $data['multiple_days']=json_encode($multiple_days);
                                        $inventory = new PmsInvPush();
                                        $update_Inv=$inventory->fill($data)->save();
                                        if($update_Inv){
                                            $return_array =  '<?xml version="1.0" encoding="utf-8"?>
                                            <OTA_HotelRestrictionInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance" xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01" EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
                                            <Success />
                                            </OTA_HotelRestrictionInfoRS>';
                                            return response($return_array)->header('Content-Type', 'application/xml');
                                        }
                                        else
                                        {
                                            $return_array='<?xml version="1.0" encoding="utf-8"?>
                                            <OTA_HotelRestrictionInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance" xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01" EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
                                            <Errors xmlns="">
                                            <Error Type="118" Code="118">Opps! unable to update</Error>
                                            </Errors>
                                            </OTA_HotelRestrictionInfoRS>';
                                            return response($return_array)->header('Content-Type', 'application/xml');
                                        }
                                    }
                                }
                            }
                            else{
                                $return_array='<?xml version="1.0" encoding="utf-8"?>
                                <OTA_HotelRestrictionInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance" xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01" EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
                                <Errors xmlns="">
                                <Error Type="118" Code="118">InvalidRatePlan</Error>
                                </Errors>
                                </OTA_HotelRestrictionInfoRS>';
                                return response($return_array)->header('Content-Type', 'application/xml');
                            }
                    }
                }
            }
            else{

                $return_array = '<?xml version="1.0" encoding="utf-8"?> <OTA_HotelRestrictionInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance" xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01 "EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
                                <Errors xmlns="">
                                    <Error Type="118" Code="118">Please set key in header</Error>
                                </Errors>
                                </OTA_HotelRestrictionInfoRS>';
                return response($return_array)->header('Content-Type', 'application/xml');
            }
        }
        else{
            $return_array = '<?xml version="1.0" encoding="utf-8"?> <OTA_HotelRestrictionInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance" xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01" EchoToken="'.$token.'" TimeStamp="'.$timestamp.'" Version="'.$version.'">
                            <Errors xmlns="">
                                 <Error Type="118" Code="118">Please set Hotelcode<</Error>
                            </Errors> </OTA_HotelRestrictionInfoRS >';
            return response($return_array)->header('Content-Type', 'application/xml');
        }
    }
    public function pmsHotelDetails(Request $request){
        $data = $request->all();
        $header = $request->header('key');
        if($header != '' && $data['hotel_id'] != ''){
            $api_key    = $header;
            $hotel_id   = $data['hotel_id'];
            $ip         = $this->ipAddress->getIPAddress();
            $checkApi   = $this->PmsComponents->checkApi($api_key);
            $hotels     = explode(',',$checkApi->hotels);
            if(in_array($hotel_id, $hotels)){
                $this->PmsComponents->serviceRequest($api_key, $hotel_id, $ip, 'Fetch Hotels');

                $HotelDetails   = $this->PmsComponents->searchHotelIdwise($hotel_id);
                $Room_Types     = $this->PmsComponents->searchRoomtypeHotelwise($hotel_id);
                $RoomTypes = array();
                $count = 0;
                foreach ($Room_Types as $rt) {
                    $room_type=$rt->room_type_id;
                    $ratePlans = $this->PmsComponents->SearchRoomratePlanHotelWise($room_type);
                    $RoomTypes[] = array(
                        "room_type_id"   => $rt->room_type_id,
                        "room_type_name" => $rt->room_type,
                        "no_of_rooms"    => $rt->total_rooms,
                        "PricePlan"      => $ratePlans,
                    );
                    $result = array();
                    $result[] = array(
                        'HotelDetails' => $HotelDetails,
                        'RoomTypes'     => $RoomTypes
                    );

                    if(!empty($result)){
                        $return_array = array('data'=>$result, 'status'=>'yes'); //if yes then only dashboard or no only service
                    }
                    else{
                        $return_array = array("status"=>"Error","code"=>"401","message"=>"Hotel not found");
                    }
                }
                  return $return_array;
            }
            else{
                $return_array = array("status"=>"Error","code"=>"401","message"=>"Hotel not found");
                return $return_array;
            }
        }
        else{
              $return_array = array("status"=>"Error","code"=>"401","message"=>"Fields are blank");
              return $return_array;
        }
    }
    public function pmsBookings(Request $request){

      $data = $request->all();
      $header = $request->header('key');
      if($header!='' && $data['hotel_id']!=''){
          $api_key    = $header;
          $hotel_id   = $data['hotel_id'];
          $last_booking_id = isset($data['last_id'])?$data['last_id']:'';
          $booking_date_i = isset($data['booking_date'])?$data['booking_date']:'';
          $ip         = $this->ipAddress->getIPAddress();
          $checkApi   = $this->PmsComponents->checkApi($api_key);
          $hotels     = explode(',',$checkApi->hotels);
          // $hotels     = json_decode($hotels);
          if(in_array($hotel_id, $hotels)){
              //Save Service Request Log
              $this->PmsComponents->serviceRequest($api_key, $hotel_id, $ip, 'Fetch Bookings');

              //Fetch Booking Details
              $BookingDetails = $this->PmsComponents->searchAllBookings($hotel_id, $last_booking_id, $booking_date_i);
              $all_bookings = array();
              foreach ($BookingDetails as $bd){
                  $rooms          =array();
                  $user_id        =isset($bd->user_id)?$bd->user_id:$bd['user_id'];
                  $invoice_id     =isset($bd->invoice_id)?$bd->invoice_id:$bd['invoice_id'];
                  $ref_no         =isset($bd->ref_no)?$bd->ref_no:$bd['ref_no'];

                  $User_Details   = $this->PmsComponents->UserInfo($user_id);
                  $Booked_Rooms   = $this->PmsComponents->NoOfBookings($invoice_id);
                  $check_out = isset($Booked_Rooms[0]->check_out)?$Booked_Rooms[0]->check_out:$Booked_Rooms[0]['check_out'];
                  $check_in = isset($Booked_Rooms[0]->check_in)?$Booked_Rooms[0]->check_in:$Booked_Rooms[0]['check_in'];
                  $date1=date_create($check_out);
                  $date2=date_create($check_in);
                  $diff=date_diff($date1,$date2);
                  $no_of_nights=$diff->format("%a");

                  $booking_date   =isset($bd->booking_date)?$bd->booking_date:$bd['booking_date'];
                  $booking_id     =date("dmy", strtotime($booking_date)).str_pad($invoice_id, 4, '0', STR_PAD_LEFT);

                  if($ref_no=='offline'){
                      $mode_of_payment='Offline';
                  }
                  else{
                      $mode_of_payment='Online';
                  }
                  $room_details = isset($bd->room_type)?$bd->room_type:$bd['room_type'];
                 $room_type_plan=explode(",", $room_details);
                 $plan= array();
                 for($i=0; $i<sizeof($room_type_plan); $i++){
                    $plan[]=substr($room_type_plan[$i], -5, -2);
                 }
                 $extra_details = isset($bd->extra_details)?$bd->extra_details:$bd['extra_details'];
                 $extra=json_decode($extra_details);
                 $k=0;
                 foreach ($Booked_Rooms  as $br) {
                   $adult=0;
                   $child=0;
                  foreach($extra as $key=>$value){
                      $room_type_id = isset($br->room_type_id)?$br->room_type_id:$br['room_type_id'];
                      $rooms_no = isset($br->rooms)?$br->rooms:$br['rooms'];
                      if(trim($room_type_id)==trim($key)){
                          for($j=0;$j<$rooms;$j++){
                           $adult=$adult+$value[$j][0];
                           $child=$child+$value[$j][1];
                          }
                      }
                  }
                  if($child=='NA'){
                      $child=0;
                  }
                  $total_amount = isset($bd->total_amount)?$bd->total_amount:$bd['total_amount'];
                  $room_type = $br->room_type;
                  $rooms[] = array(
                  "room_type_id"          => $room_type_id,
                  "room_type_name"        => $room_type,
                  "no_of_rooms"           => $rooms_no,
                  "room_rate"             => ($total_amount/$rooms_no)/$no_of_nights,
                  "plan"                  => trim($plan[$k]),
                  "adult"                 => $adult,
                  "child"                 => $child
                  );
                  $k++;
                }
                  $user_fname  = isset($User_Details->first_name)?$User_Details->first_name:$User_Details['first_name'];
                  $user_lname  = isset($User_Details->last_name)?$User_Details->last_name:$User_Details['last_name'];
                $user_info = array(
                    "user_name"             => $user_fname.' '.$user_lname,
                    "mobile"                => isset($User_Details->mobile)?$User_Details->mobile:$User_Details['mobile'],
                    "email"                 => isset($User_Details->email_id)?$User_Details->email_id:$User_Details['email_id'],
                    );

                $Bookings = array(
                    "date_of_booking"       => $booking_date,
                    "hotel_id"              => isset($bd->hotel_id)?$bd->hotel_id:$bd['hotel_id'],
                    "hotel_name"            => isset($bd->hotel_name)?$bd->hotel_name:$bd['hotel_name'],
                    "check_in"              => isset($Booked_Rooms[0]->check_in)?$Booked_Rooms[0]->check_in:$Booked_Rooms[0]['check_in'],
                    "check_out"             => isset($Booked_Rooms[0]->check_out)?$Booked_Rooms[0]->check_out:$Booked_Rooms[0]['check_out'],
                    "booking_id"            => $booking_id,
                    "mode_of_payment"       => $mode_of_payment,
                    "grand_total"           => isset($bd->total_amount)?$bd->total_amount:$bd['total_amount'],
                    "paid_amount"           => isset($bd->paid_amount)?$bd->paid_amount:$bd['paid_amount'],
                    "channel"               => "Bookingjini",
                    "status"                => "confirmed"
                    );

                $all_bookings[] = array(
                  'UserDetails'               => $user_info,
                  'BookingsDetails'           => $Bookings,
                  'RoomDetails'               => $rooms
                  );
              }

              $OTABookingDetails = $this->PmsComponents->searchAllOTABookings($hotel_id, $booking_date_i);
              $ota_all_bookings = array();
              foreach ($OTABookingDetails as $otabd){
                  $ota_rooms          =array();
                  $ota_invoice_id     =$otabd->id;
                  $unique_id          =$otabd->unique_id;
                  $room_qty           =explode(",",$otabd->rooms_qty);
                  $total_amount       =$otabd->amount;
                  $date1=date_create($otabd->checkout_at);
                  $date2=date_create($otabd->checkin_at);
                  $diff=date_diff($date1,$date2);
                  $no_of_nights=$diff->format("%a");
                  if($otabd->cancel_status==1){
                      $booking_status="cancelled";
                  }
                  else{
                      $booking_status="confirmed";
                  }
                  $amount=$total_amount;
                  $payment_status=$otabd->payment_status;
                  if($payment_status=="Paid"){
                      $payment_status="Online";
                  }
                  $ota_booking_date   =$otabd->booking_date;
                  $ota_booking_id     =date("dmy", strtotime($ota_booking_date)).str_pad($ota_invoice_id, 4, '0', STR_PAD_LEFT);


                  $plans=$this->PmsComponents->getHotelRatePlanIdFromRatePlanSynch($otabd->room_type, $otabd->rate_code, $hotel_id);
                  $rooms1=$this->PmsComponents->getHotelRoomIdFromRoomSynch($otabd->room_type, $hotel_id);
                  // if(sizeof($plans)<=0)
                  // {
                  // 	$plans = 'NA';
                  // }
                  $adults=explode(",",$otabd->no_of_adult);
                  $childs=explode(",",$otabd->no_of_child);
                   $k=0;

                  foreach ($rooms1[$k]  as $key=>$value) {
                       $adult=$adults[$k];
                       $child=$childs[$k];
                       if($child=='NA'){
                          $child=0;
                      }
                      $plans_name = isset($plans[0][$k])?$plans[0][$k]:'NA';
                      $plan_id = isset($plans[0][$k+1])?$plans[0][$k+1]:'NA';
                      $ota_rooms[] = array(
                      "room_type_id"          => $key,
                      "room_type_name"        => $value,
                      "no_of_rooms"           => $room_qty[$k],
                      "room_rate"             => ($amount/$room_qty[$k])/$no_of_nights,
                      "plan"                  => trim($plans_name),
                      "room_rate_plan_id"     => trim($plan_id),
                      "adult"                 => $adult,
                      "child"                 => $child
                      );
                      $k++;

                  }
                    $d=$otabd->customer_details;
                    $da=explode(',',$d);
                    $ota_user_info = array(
                      "user_name"             => $da[0],
                      "mobile"                => $da[2],
                      "email"                 => $da[1],
                      );

                  $ota_bookings = array(
                      "ota_unique_id"         => $unique_id,
                      "date_of_booking"       => $ota_booking_date,
                      "hotel_id"              => $hotel_id,
                      "hotel_name"            => 'NA',
                      "check_in"              => $otabd->checkin_at,
                      "check_out"             => $otabd->checkout_at,
                      "booking_id"            => $ota_booking_id,
                      "mode_of_payment"       => $payment_status,
                      "grand_total"           => $amount,
                      "paid_amount"           => $amount,
                      "channel"               => $otabd->channel_name,
                      "status"                => $booking_status
                      );

                  $ota_all_bookings[] = array(
                  'UserDetails'               => $ota_user_info,
                  'BookingsDetails'           => $ota_bookings,
                  'RoomDetails'               => $ota_rooms
                  );
              }
              $bookings=array_merge($all_bookings,$ota_all_bookings);
              if($bookings){
                  $return_array =array('data'=>$bookings, 'b_status'=>'yes'); //$all_bookings; //if yes then only dashboard or no only service
              }
              else{
                if($last_booking_id==''){
                     $return_array = array("status"=>"Empty","code"=>"200","message"=>"Last booking id is not valid or no more booking is there.");
                }
                else{
                  $return_array = array("status"=>"Empty","code"=>"200","message"=>"There is no new bookings");
                }
              }
              return $return_array;
          }
          else  {
              $return_array = array("status"=>"Error","code"=>"401","message"=>"Hotel not found");
              return $return_array;
          }
      }
      else{
          $return_array = array("status"=>"Error","code"=>"401","message"=>"Fields are blank");
          return $return_array;
      }
    }
}
