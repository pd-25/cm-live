<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\UserCredential;
use App\Inventory;//class name from model
use App\CmOtaDetails;//class name from model
use App\LogTable;//class name from model
use DB;
use Ixudra\Curl\Facades\Curl;
use App\Http\Controllers\CommonServiceController;
use App\CmOtaRatePlanSynchronize;
use App\Http\Controllers\IpAddressService;
use App\AirbnbListingDetails;
use App\HotelInformation;
use App\CompanyDetails;  
//create a new class OtaBlockInventoryController
class OtaBlockInventoryController extends Controller
{ 
        protected $commonService;
        protected $ipService;
        public function __construct(CommonServiceController $commonService,IpAddressService $ipService)
        {
        $this->commonService = $commonService;
        $this->ipService=$ipService;
        }
    //validation rules
    private $rules = array(
        'hotel_id' => 'required',
        'room_type_id' => 'required',
        'date_from' => 'required',
        'date_to' => 'required',
        //'client_ip' => 'required'
    );
    //Custom Error Messages
    private $messages = [
        'hotel_id.required' => 'The hotel id is required.',
        'room_type_id.required' => 'The room type id is required.',
        'date_from.required' => 'The date from is required.',
        'date_to.required' => 'The date to is required.',
        //'client_ip.required' => 'The client ip is required.',
            ];
    /**
     * CM ota  block inventry.
     * Create a new record of CM ota block inventry.
     * @author subhradip
     * @return CM ota block inventry saving status
     * function addnew for createing a new CM ota rblock inventry.
    **/
    public function addNewCmOtaBlockInventry(Request $request)
    {   
        //$inventory = new Inventory();
        $failure_message='Block inventry operation failed';
        $validator = Validator::make($request->all(),$this->rules,$this->messages);
        if ($validator->fails())
        {
            return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
        }
        $room_types=$request->input('room_type_id');
        //dd($room_types);
        $data=$request->all();
        $hotel_id=$data['hotel_id'];
        $fmonth=explode('-',$data['date_from']);//for removing extra o from month and remove this code after mobile app update
        if(strlen($fmonth[1]) == 3)
        {
            $fmonth[1]=ltrim($fmonth[1],0);
        }
        $data['date_from']=implode('-',$fmonth);
        $tmonth=explode('-',$data['date_to']);
        if(strlen($tmonth[1]) == 3)
        {
            $tmonth[1]=ltrim($tmonth[1],0);
        }
        $data['date_to']=implode('-',$tmonth);
        
        $data['date_from']=date('Y-m-d',strtotime($data['date_from']));
        $data['date_to']=date('Y-m-d',strtotime($data['date_to']));
        //TO get user id from AUTH token
        if(isset($request->auth->admin_id)){
            $data['user_id']=$request->auth->admin_id;
        }else if(isset($request->auth->super_admin_id)){
            $data['user_id']=$request->auth->super_admin_id;
        }
        else if(isset($request->auth->id)){
            $data['user_id']=$request->auth->id;
        }
        $user_id=$data['user_id'];
        if(!isset($data['ota_id'])){
            $data['ota_id']=0;
        }
        $ota_id=$data['ota_id'];//Later get it from frontend
        if(!isset($data['client_ip']))
        {
            $client_ip=$this->ipService->getIPAddress();
        }
        else{
            $client_ip=$data['client_ip'];
        }
        
        $count=0;
        $return_resp=array();
        $i=0;
        foreach($room_types as $room_type_id)
        {
            $inventory = new Inventory();
            $data['room_type_id']=$room_type_id;
            if($ota_id == -1)
            {
                if($inventory->fill($data)->save())
                    {
                        $return_resp=array('status' => 1,'response'=> 'blocked successfully on Booking Engine','be'=>'be');
                        $count++;
                    }
                    else{
                        $return_resp=array('status' => 0,'response'=> 'blocked unsuccessfully on Booking Engine','be'=>'be');
                        $count++;
                    }
            }
            if($ota_id == 0)
            {
                if($inventory->fill($data)->save())
                {   $inventorId = $inventory->inventory_id;
                    $hotel_id = $inventory->hotel_id;
                    $return_resp[$i]=$this->multipleFunction($inventorId,$hotel_id,$user_id,$client_ip,$ota_id);
                    $count++;
                }
            }
            else{
                $inventorId=array('room_type_id'=>$room_type_id,'no_of_rooms'=>0,'date_from'=>$data['date_from'],'date_to'=>$data['date_to'],'client_ip'=>$client_ip,'user_id'=>$user_id);
                $return_resp[$i]=$this->multipleFunction($inventorId,$hotel_id,$user_id,$client_ip,$ota_id);
                $count++;
            }
            $i++;
            
        }
        if($count==sizeof($room_types))
        {
            $res=array('status'=>1,"message"=>"Inventry blocked successfully",'data_status'=>$return_resp);
            return response()->json($res);
        }
        else
        {
            $res=array('status'=>-1,"message"=>$failure_message);
            $res['errors'][] = "Internal server error";
            return response()->json($res);
        }
        
    }
    public function multipleFunction($inventorId,$hotel_id,$user_id,$client_ip,$ota_id)
    {
         /*-------------------call otas api for block inventory-------------------------*/
        $cmOtaDetailsModel                = new CmOtaDetails();
        $resp=array();
        $ota_id_status=$ota_id;
        if($ota_id_status > 0)
        {
            $room_type_id = $inventorId['room_type_id'];
            $no_of_rooms = $inventorId['no_of_rooms'];
            $date_from = $inventorId['date_from'];
            $date_to = $inventorId['date_to'];
            $client_ip = $inventorId['client_ip'];
            $user_id = $inventorId['user_id'];
            $inventorId = $room_type_id.",".$date_from.",".$date_to;
            $cmOtaDetailsName                 = $cmOtaDetailsModel
            ->where('hotel_id', '=', $hotel_id)
            ->where('ota_id',$ota_id)
            ->where('is_active', '=', 1)
            ->first();
            if($cmOtaDetailsName)
            {
                $ota=$cmOtaDetailsName->ota_name;
                $ota_details_data_byId=$cmOtaDetailsName;
                $id=$cmOtaDetailsName->ota_id;
                $otahotel_code=$cmOtaDetailsName->ota_hotel_code;
                $authparameter=json_decode($cmOtaDetailsName->auth_parameter);
                $cUrl=$cmOtaDetailsName->url; 
                $ota_roomtype = DB::table('cm_ota_room_type_synchronize')
                ->where('hotel_id', '=', $hotel_id)
                ->where('room_type_id', '=', $room_type_id)
                ->where('ota_type_id', '=', $id)
                ->value('ota_room_type');
            }
            if($ota_roomtype != null)
            {
                if($ota== "Goibibo")
                { 
                    $resp['Goibibo']=$this->goibiboBlockIventry($id,$hotel_id,$otahotel_code,$date_from,$date_to,$ota_roomtype,$authparameter,$cUrl,$user_id,$client_ip,$inventorId);
                }
                else if($ota== "Travelguru")
                { 
                    $resp['Travelguru']= $this->travelguruBlockIventry($id,$hotel_id,$otahotel_code,$date_from,$date_to,$ota_roomtype,$authparameter,$cUrl,$user_id,$client_ip,$inventorId);
                }
                else if($ota== "Cleartrip")
                { 
                    $resp['Cleartrip']= $this->cleartripBlockIventry($id,$hotel_id,$otahotel_code,$date_from,$date_to,$ota_roomtype,$authparameter,$cUrl,$user_id,$client_ip,$inventorId);
                }
                else if($ota== "Via.com")
                { 
                    $resp['Via.com']= $this->viaBlockIventry($id,$hotel_id,$otahotel_code,$date_from,$date_to,$ota_roomtype,$authparameter,$cUrl,$user_id,$client_ip,$inventorId);
                } 
                else if($ota== "Agoda")
                { 
                    $resp['Agoda']=$this->agodaBlockIventry($id,$hotel_id,$otahotel_code,$date_from,$date_to,$ota_roomtype,$authparameter,$cUrl,$user_id,$client_ip,$inventorId);
                }
                else if($ota== "Booking.com")
                { 
                    $resp['Booking.com']= $this->bookingDotComBlockIventry($id,$hotel_id,$otahotel_code,$date_from,$date_to,$ota_roomtype,$authparameter,$cUrl,$user_id,$client_ip,$inventorId);
                }
                else if($ota== "Expedia")
                { 
                    $resp['Expedia']=$this->expediaBlockIventry($id,$hotel_id,$otahotel_code,$date_from,$date_to,$ota_roomtype,$authparameter,$cUrl,$user_id,$client_ip,$inventorId);
                }
                else if($ota== "Goomo")
                { 
                    $resp['Goomo']=$this->goomoBlockIventry($id,$hotel_id,$otahotel_code,$date_from,$date_to,$ota_roomtype,$authparameter,$cUrl,$user_id,$client_ip,$inventorId);
                }
                else if($ota== "Airbnb")
                { 
                    $resp['Airbnb']=$this->airbnbBlockIventory($id,$hotel_id,$otahotel_code,$date_from,$date_to,$ota_roomtype,$authparameter,$cUrl,$user_id,$client_ip,$inventorId);
                }
                else if($ota  == "EaseMyTrip")
                { 
                    $resp['EaseMyTrip']=$this->easemytripBlockIventory($id,$hotel_id,$otahotel_code,$date_from,$date_to,$ota_roomtype,$authparameter,$cUrl,$user_id,$client_ip,$inventorId);
                   
                }
                else if($ota  == "Paytm")
                { 
                    $resp['Paytm']=$this->paytmBlockIventory($id,$hotel_id,$otahotel_code,$date_from,$date_to,$ota_roomtype,$authparameter,$cUrl,$user_id,$client_ip,$inventorId);  
                }
            }
        }
        else{
            $cmOtaDetailsDetails              = $cmOtaDetailsModel
            ->where('hotel_id', '=', $hotel_id)
            ->where('is_active', '=', 1)
            ->get();
            $room_type_id = DB::table('inventory_table')->where('inventory_id', $inventorId)->value('room_type_id');
            $no_of_rooms = DB::table('inventory_table')->where('inventory_id', $inventorId)->value('no_of_rooms');
            $date_from = DB::table('inventory_table')->where('inventory_id', $inventorId)->value('date_from');
            $date_to = DB::table('inventory_table')->where('inventory_id', $inventorId)->value('date_to');
            $client_ip = DB::table('inventory_table')->where('inventory_id', $inventorId)->value('client_ip');
            $user_id = DB::table('inventory_table')->where('inventory_id', $inventorId)->value('user_id');

            foreach ($cmOtaDetailsDetails as $cmOtaDetailsDetail)
            {
                $ota_details_data    = $cmOtaDetailsDetail;
                $ota_id              = $ota_details_data->ota_id;
                $ota_name            = $ota_details_data->ota_name;
                $ota_hotel_code      = $ota_details_data->ota_hotel_code;
                $auth_parameter      = json_decode($ota_details_data->auth_parameter);
                $commonUrl           = $ota_details_data->url;
                $ota_room_type = DB::table('cm_ota_room_type_synchronize')
                                    ->where('hotel_id', '=', $hotel_id)
                                    ->where('room_type_id', '=', $room_type_id)
                                    ->where('ota_type_id', '=', $ota_id)
                                    ->value('ota_room_type');
                                   
                if($ota_room_type != null)
                {
                    if($ota_name == "Goibibo")
                    { 
                        $resp['Goibibo']=$this->goibiboBlockIventry($ota_id,$hotel_id,$ota_hotel_code,$date_from,$date_to,$ota_room_type,$auth_parameter,$commonUrl,$user_id,$client_ip,$inventorId);
                    }
                    if($ota_name == "Travelguru")
                    { 
                        $resp['Travelguru']= $this->travelguruBlockIventry($ota_id,$hotel_id,$ota_hotel_code,$date_from,$date_to,$ota_room_type,$auth_parameter,$commonUrl,$user_id,$client_ip,$inventorId);
                    }
                    if($ota_name == "Cleartrip")
                    { 
                        $resp['Cleartrip']= $this->cleartripBlockIventry($ota_id,$hotel_id,$ota_hotel_code,$date_from,$date_to,$ota_room_type,$auth_parameter,$commonUrl,$user_id,$client_ip,$inventorId);
                    }
                    if($ota_name == "Via.com")
                    { 
                        $resp['Via.com']= $this->viaBlockIventry($ota_id,$hotel_id,$ota_hotel_code,$date_from,$date_to,$ota_room_type,$auth_parameter,$commonUrl,$user_id,$client_ip,$inventorId);
                    } 
                    if($ota_name == "Agoda")
                    { 
                        $resp['Agoda']=$this->agodaBlockIventry($ota_id,$hotel_id,$ota_hotel_code,$date_from,$date_to,$ota_room_type,$auth_parameter,$commonUrl,$user_id,$client_ip,$inventorId);
                    }
                    if($ota_name == "Booking.com")
                    { 
                        $resp['Booking.com']= $this->bookingDotComBlockIventry($ota_id,$hotel_id,$ota_hotel_code,$date_from,$date_to,$ota_room_type,$auth_parameter,$commonUrl,$user_id,$client_ip,$inventorId);
                    }
                    if($ota_name == "Expedia")
                    { 
                        $resp['Expedia']=$this->expediaBlockIventry($ota_id,$hotel_id,$ota_hotel_code,$date_from,$date_to,$ota_room_type,$auth_parameter,$commonUrl,$user_id,$client_ip,$inventorId);
                    }
                    if($ota_name == "Goomo")
                    { 
                        $resp['Goomo']=$this->goomoBlockIventry($ota_id,$hotel_id,$ota_hotel_code,$date_from,$date_to,$ota_room_type,$auth_parameter,$commonUrl,$user_id,$client_ip,$inventorId);
                    }
                    else if($ota_name== "Airbnb")
                    { 
                        $resp['Airbnb']=$this->airbnbBlockIventory($ota_id,$hotel_id,$ota_hotel_code,$date_from,$date_to,$ota_room_type,$auth_parameter,$commonUrl,$user_id,$client_ip,$inventorId);
                    }
                    else if($ota_name== "EaseMyTrip")
                    { 
                        $resp['EaseMyTrip']=$this->easemytripBlockIventory($ota_id,$hotel_id,$ota_hotel_code,$date_from,$date_to,$ota_room_type,$auth_parameter,$commonUrl,$user_id,$client_ip,$inventorId);
                    }
                    else if($ota_name== "Paytm")
                    { 
                        $resp['Paytm']=$this->paytmBlockIventory($ota_id,$hotel_id,$ota_hotel_code,$date_from,$date_to,$ota_room_type,$auth_parameter,$commonUrl,$user_id,$client_ip,$inventorId);
                    }
                }
            }
        }     
    return $resp;
    }
    public function goibiboBlockIventry($ota_id,$hotel_id,$ota_hotel_code,$date_from,$date_to,$ota_room_type,$auth_parameter,$commonUrl,$user_id,$client_ip,$inventoryId)
    {
        $bearer_token      = trim($auth_parameter->bearer_token);
        $channel_token     = trim($auth_parameter->channel_token);
       
        $blocklog = new LogTable();     
        $log_data                 		= [
            "action_id"          => 3,
            "hotel_id"           => $hotel_id,
            "ota_id"      		 => $ota_id,
            "booking_ref_id"     => '',
            "inventory_ref_id"   => $inventoryId,
            "rate_ref_id"        => '',
            "user_id"            => $user_id,
            "request_msg"        =>  '',
            "response_msg"       =>  '',
            "request_url"        =>  '',
            "status"         	 => 2,
            "ip"         		 => $client_ip,
            "comment"			 => "Processing for update"
            ];
       
        $blocklog->fill($log_data)->save();
        $xml ='<?xml version="1.0" encoding="UTF-8" ?>
            <Website Name="ingoibibo" HotelCode="'.$ota_hotel_code.'">
            <Room>
            <RoomTypeCode>'.$ota_room_type.'</RoomTypeCode>
            <StartDate Format="yyyy-mm-dd">'.$date_from.'</StartDate>
            <EndDate Format="yyyy-mm-dd">'.$date_to.'</EndDate>
            <DaysOfWeek Mon="True" Tue="True" Wed="True" Thu="True" Fri="True" Sat="True" Sun="True"></DaysOfWeek>
            <StopSell>True</StopSell>
            </Room>
            </Website>';   
        $curlService = new \Ixudra\Curl\CurlService();
        $url = $commonUrl.'updateroominventory/?bearer_token='.$bearer_token.'&channel_token='.$channel_token;
        $response = $curlService->to($url)
        ->withData($xml)
        ->post();
        $resultXml=simplexml_load_string($response);
        if($resultXml){
            $array_data = json_decode(json_encode($resultXml), true);
            if(!isset($array_data['Error'])){
                    DB::table('log_table')->where('id',  $blocklog->id)
                    ->update(['status' => 1,'request_msg'=>$xml,'request_url'=>$url,'response_msg'=> $response]);
                $return_resp=array('status' => 1,'response'=> ' blocked successfully');
                return $return_resp;
            }
            else
            {
                    DB::table('log_table')->where('id', $blocklog->id)
                    ->update(['status' => 0,'request_msg'=>$xml,'request_url'=>$url,'response_msg'=>$response]);
                $return_resp=array('status' => 0,'response'=> $response);
                return $return_resp;
            }
        }
        else{
            $return_resp=array('status' => 0,'response'=> $response);
            return $return_resp;
        }
    }
    public function travelguruBlockIventry($ota_id,$hotel_id,$ota_hotel_code,$date_from,$date_to,$ota_room_type,$auth_parameter,$commonUrl,$user_id,$client_ip,$inventoryId)
    {
        $MessagePassword   = trim($auth_parameter->MessagePassword);
        $ID                = trim($auth_parameter->ID);
        $blocklog = new LogTable();     
        $log_data                 		= [
            "action_id"          => 3,
            "hotel_id"           => $hotel_id,
            "ota_id"      		 => $ota_id,
            "booking_ref_id"     => '',
            "inventory_ref_id"   => $inventoryId,
            "rate_ref_id"        => '',
            "user_id"            => $user_id,
            "request_msg"        =>  '',
            "response_msg"       =>  '',
            "request_url"        =>  '',
            "status"         	 => 2,
            "ip"         		 => $client_ip,
            "comment"			 => "Processing for update"
            ];
            $blocklog->fill($log_data)->save();
        $headers           = array (
            //Regulates versioning of the XML interface for the API
            'Content-Type: application/xml',
            );
        $xml = '<OTA_HotelAvailNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05">
            <POS>
            <Source>
            <RequestorID MessagePassword="'.$MessagePassword.'" ID="'.$ID.'" Type="CHM"/>
            </Source>
            </POS>
            <AvailStatusMessages HotelCode="'.$ota_hotel_code.'">
            <AvailStatusMessage BookingLimit="0">
            <StatusApplicationControl Mon="true" Tue="true" Weds="true" Thur="true" Sun="true" Sat="true"  Fri="true" Start="'.$date_from.'" End="'.$date_to.'" InvCode="'.$ota_room_type.'"/>
            <RestrictionStatus SellThroughOpenIndicator="true"/>
            </AvailStatusMessage>
            </AvailStatusMessages>
            </OTA_HotelAvailNotifRQ>';
        $curlService = new \Ixudra\Curl\CurlService();
        $url  = $commonUrl.'availability/update';
        $ch  = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml);
        $response = curl_exec($ch);
        curl_close($ch);
        $resultXml=simplexml_load_string($response);
        if($resultXml)
        {
            $array_data = json_decode(json_encode($resultXml), true);
            if(!isset($array_data['Errors'])){
               
                    DB::table('log_table')->where('id',  $blocklog->id)
                    ->update(['status' => 1,'request_msg'=>$xml,'request_url'=>$url,'response_msg'=> $response]);
               
                $return_resp=array('status' => 1,'response'=> ' blocked successfully');
                return $return_resp;
            }
            else
            {
                DB::table('log_table')->where('id', $blocklog->id)
                    ->update(['status' => 0,'request_msg'=>$xml,'request_url'=>$url,'response_msg'=>$response]);
                $return_resp=array('status' => 0,'response'=> $response);
                return $return_resp;
            }
        }
        else{
            $return_resp=array('status' => 0,'response'=> $response);
            return $return_resp;
            }
        
    }
    public function cleartripBlockIventry($ota_id,$hotel_id,$ota_hotel_code,$date_from,$date_to,$ota_room_type,$auth_parameter,$commonUrl,$user_id,$client_ip,$inventoryId)
    {
        $api_key           = trim($auth_parameter->api_key);
        $blocklog = new LogTable();     
        $log_data                 		= [
            "action_id"          => 3,
            "hotel_id"           => $hotel_id,
            "ota_id"      		 => $ota_id,
            "booking_ref_id"     => '',
            "inventory_ref_id"   => $inventoryId,
            "rate_ref_id"        => '',
            "user_id"            => $user_id,
            "request_msg"        =>  '',
            "response_msg"       =>  '',
            "request_url"        =>  '',
            "status"         	 => 2,
            "ip"         		 => $client_ip,
            "comment"			 => "Processing for update"
            ];
        $date_from=date('d/m/Y',strtotime($date_from));
        $date_to=date('d/m/Y',strtotime($date_to));
        $blocklog->fill($log_data)->save();
        $headers = array (
            'Content-Type: application/xml',
            'X-CT-SOURCETYPE: API',
            'X-CT-API-KEY: '.$api_key,
            );

            $xml ='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
            <hotel-inventory xmlns="http://www.cleartrip.com/extranet/hotel-inventory" type="update">
            <hotel-id>'.$ota_hotel_code.'</hotel-id>
            <room-type-id>'.$ota_room_type.'</room-type-id>
            <room-inventories>
            <room-inventory>
            <from-date>'.$date_from.'</from-date>
            <to-date>'.$date_to.'</to-date>
            <applicable-days>ALL</applicable-days>
            <inventory>0</inventory>
            <release-hours>24</release-hours>
            </room-inventory>
            </room-inventories>
            </hotel-inventory>';
        $curlService = new \Ixudra\Curl\CurlService();
        $url = $commonUrl.'push-inventory';
        $response = $curlService->to($url)
        ->withData($xml)
        ->withHeaders($headers)
        ->post();
       
        $resultXml=simplexml_load_string($response);
        if($resultXml){
            $array_data = json_decode(json_encode($resultXml), true);
            $status =$array_data['status']['code'];
            if (substr($status, 0, 1) === 'S') {
                DB::table('log_table')->where('id',  $blocklog->id)
                ->update(['status' => 1,'request_msg'=>$xml,'request_url'=>$url,'response_msg'=> $response]);
                $return_resp=array('status' => 1,'response'=> ' blocked successfully');
                return $return_resp;
            }
            else
            {
                DB::table('log_table')->where('id', $blocklog->id)
                ->update(['status' => 0,'request_msg'=>$xml,'request_url'=>$url,'response_msg'=>$response]);
                $return_resp=array('status' => 0,'response'=> $response);
                return $return_resp;
            }
        }
        else{
            $return_resp=array('status' => 0,'response'=> $response);
            return $return_resp;
            } 
        
    }
    public function viaBlockIventry($ota_id,$hotel_id,$ota_hotel_code,$date_from,$date_to,$ota_room_type,$auth_parameter,$commonUrl,$user_id,$client_ip,$inventoryId)
    {
        $source           = trim($auth_parameter->source);
        $auth           = trim($auth_parameter->auth);
        $blocklog = new LogTable();     
        $log_data                 		= [
            "action_id"          => 3,
            "hotel_id"           => $hotel_id,
            "ota_id"      		 => $ota_id,
            "booking_ref_id"     => '',
            "inventory_ref_id"   => $inventoryId,
            "rate_ref_id"        => '',
            "user_id"            => $user_id,
            "request_msg"        =>  '',
            "response_msg"       =>  '',
            "request_url"        =>  '',
            "status"         	 => 2,
            "ip"         		 => $client_ip,
            "comment"			 => "Processing for update"
            ];
       
        $blocklog->fill($log_data)->save();
        $curlService = new \Ixudra\Curl\CurlService();
        $url = $commonUrl.'newWebserviceAPI?actionId=cm_stopsellbyroomid&typeId=hotel&source='.$source.'&auth='.$auth.'&requestData={"hotelId":'.$ota_hotel_code.',"roomId":'.$ota_room_type.',"startDate":"'.$date_from.'","endDate":"'.$date_to.'","stopSell":"true"}';
        $response = $curlService->to($url)
                                 ->post();
        
            $array_data = (array) json_decode($response);
            
                if(isset($array_data['Success'])){
                        DB::table('log_table')->where('id', $blocklog->id)
                        ->update(['status' => 1,'request_msg'=>$url,'response_msg'=>$response]);
                    $return_resp=array('status' => 1,'response'=> ' blocked successfully');
                    return $return_resp;
                    }
                else{
                        DB::table('log_table')->where('id', $blocklog->id)
                        ->update(['status' => 0,'request_msg'=>$url,'response_msg'=>$response]);
                    $return_resp=array('status' => 0,'response'=> $response);
                    return $return_resp;
                }
        
        
    }
    public function agodaBlockIventry($ota_id,$hotel_id,$ota_hotel_code,$date_from,$date_to,$ota_room_type,$auth_parameter,$commonUrl,$user_id,$client_ip,$inventoryId)
    {
        $date              = new \DateTime();
        $dateTimestamp     = $date->getTimestamp();
        $blocklog = new LogTable();     
        $log_data                 		= [
            "action_id"          => 3,
            "hotel_id"           => $hotel_id,
            "ota_id"      		 => $ota_id,
            "booking_ref_id"     => '',
            "inventory_ref_id"   => $inventoryId,
            "rate_ref_id"        => '',
            "user_id"            => $user_id,
            "request_msg"        =>  '',
            "response_msg"       =>  '',
            "request_url"        =>  '',
            "status"         	 => 2,
            "ip"         		 => $client_ip,
            "comment"			 => "Processing for update"
            ];
            $blocklog->fill($log_data)->save();
        $apiKey            = trim($auth_parameter->apiKey);
        $xml ='<?xml version="1.0" encoding="UTF-8"?>
                <request timestamp="1436931804" type="1">
                <criteria property_id="'.$ota_hotel_code.'">
                <inventory>
                <update room_id="'.$ota_room_type.'">
                <date_range from="'.$date_from.'" to="'.$date_to.'">
                </date_range>
                <restrictions>
                <closed>true</closed>
                <ctd>false</ctd>
                <cta>false</cta>
                </restrictions>
                </update>
                </inventory>
                </criteria>
                </request>';
        $curlService = new \Ixudra\Curl\CurlService();
        $url  = $commonUrl.'api?apiKey='.$apiKey;
        $response = $curlService->to($url)
        ->withData($xml)
        ->withContentType('application/xml')
        ->post();
        $resultXml=simplexml_load_string($response);
        if($resultXml)
        {
            $array_data = json_decode(json_encode($resultXml), true);
            if(!isset($array_data['errors'])){
                    DB::table('log_table')->where('id', $blocklog->id)
                    ->update(['status' => 1,'request_msg'=>$xml,'request_url'=>$url,'response_msg'=>$response]);
                $return_resp=array('status' => 1,'response'=> ' blocked successfully');
                return $return_resp;
            }else{
                    DB::table('log_table')->where('id', $blocklog->id)
                    ->update(['status' => 0,'request_msg'=>$xml,'request_url'=>$url,'response_msg'=>$response]);
                $return_resp=array('status' => 0,'response'=> $response);
                return $return_resp;
            }
        }
        else{
            $return_resp=array('status' => 0,'response'=> $response);
            return $return_resp;
            }
    }
    public function bookingDotComBlockIventry($ota_id,$hotel_id,$ota_hotel_code,$date_from,$date_to,$ota_room_type,$auth_parameter,$commonUrl,$user_id,$client_ip,$inventoryId)
    {
        
        $username          = trim($auth_parameter->username);
        $password          = trim($auth_parameter->password);
        $cmOtaRatePlanSynchronize=new CmOtaRatePlanSynchronize();
        $rate_types=$cmOtaRatePlanSynchronize->get_ota_rate_plans($ota_room_type,$ota_id);
        $date1=date_create($date_from);
        $date2=date_create($date_to);
        $diff=date_diff($date1,$date2);
        $diff=$diff->format("%a");
        $date_to=date('Y-m-d',strtotime('+1 day', strtotime($date_to)));
        
        $blocklog = new LogTable();     
        $log_data                 		= [
            "action_id"          => 3,
            "hotel_id"           => $hotel_id,
            "ota_id"      		 => $ota_id,
            "booking_ref_id"     => '',
            "inventory_ref_id"   => $inventoryId,
            "rate_ref_id"        => '',
            "user_id"            => $user_id,
            "request_msg"        =>  '',
            "response_msg"       =>  '',
            "request_url"        =>  '',
            "status"         	 => 2,
            "ip"         		 => $client_ip,
            "comment"			 => "Processing for update"
            ];
            $blocklog->fill($log_data)->save();
        $response_string="";
        $responseXml=array();//To get the response fron OTA
        $requestXml="";//To save request to OTA
        $url           = $commonUrl.'availability';
        foreach($rate_types as $ota_rates)
        {
            $xml = '<request>
                    <username>'.$username.'</username>
                    <password>'.$password.'</password>
                    <hotel_id>'.$ota_hotel_code.'</hotel_id>
                    <version>1.0</version>
                   
                    </request>';
            $curlService = new \Ixudra\Curl\CurlService();
            $response = $curlService->to($url)
            ->withData($xml)
            ->post();
            array_push($responseXml,$response);
            $requestXml+=$xml;
        }  
        $success_status=true;
        foreach($responseXml as $responseStr){
            if(strpos($responseStr, '<error>' ) !== false){
                $success_status =$success_status && false;
                $response_string+=$responseStr;
             }
            else if(strpos($responseStr, '<warning>' ) !== false){
                $success_status =$success_status && false;
                $response_string+=$responseStr;
            }
            else{
            if(strpos($responseStr, '<ok>' ) !== false)
            {
                $success_status =$success_status && true;
                $response_string+=$responseStr;
            }
        } 
        }
        if($success_status){
                DB::table('log_table')->where('id', $blocklog->id)
                ->update(['status' => 1,'request_msg'=>$requestXml,'request_url'=>$url,'response_msg'=>$response_string]);
            $return_resp=array('status' => 1,'response'=> ' blocked successfully');
            return $return_resp;
        }else{
                DB::table('log_table')->where('id', $blocklog->id)
                ->update(['status' => 0,'request_msg'=>$requestXml,'request_url'=>$url,'response_msg'=>$response_string]);
            $return_resp=array('status' => 1,'response'=> $response_string);
            return $return_resp;
        }
    }
      
    public function expediaBlockIventry($ota_id,$hotel_id,$ota_hotel_code,$date_from,$date_to,$ota_room_type,$auth_parameter,$commonUrl,$user_id,$client_ip,$inventoryId)
    {
        $username          = trim($auth_parameter->username);
        $password          = trim($auth_parameter->password);
        $blocklog = new LogTable();     
        $log_data                 		= [
            "action_id"          => 3,
            "hotel_id"           => $hotel_id,
            "ota_id"      		 => $ota_id,
            "booking_ref_id"     => '',
            "inventory_ref_id"   => $inventoryId,
            "rate_ref_id"        => '',
            "user_id"            => $user_id,
            "request_msg"        =>  '',
            "response_msg"       =>  '',
            "request_url"        =>  '',
            "status"         	 => 2,
            "ip"         		 => $client_ip,
            "comment"			 => "Processing for update"
            ];
        
            $blocklog->fill($log_data)->save();
       
        $auth              = "$username:$password";
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
                <AvailRateUpdateRQ xmlns="http://www.expediaconnect.com/EQC/AR/2011/06">
                <Authentication username="'.$username.'" password="'.$password.'"/>
                <Hotel id="'.$ota_hotel_code.'"/>
                <AvailRateUpdate>
                <DateRange from="'.$date_from.'" to="'.$date_to.'"/>
                <RoomType id="'.$ota_room_type.'" closed="true">
                </RoomType>
                </AvailRateUpdate>
                </AvailRateUpdateRQ>';
        $curlService = new \Ixudra\Curl\CurlService();
        $url  = $commonUrl.'eqc/ar';
        $response = $curlService->to($url)
        ->withData($xml)
        ->post();
        $resultXml=simplexml_load_string($response);
        if($resultXml){
            $array_data = json_decode(json_encode($resultXml), true);
            if(!isset($array_data['Error'])){
                   
                     DB::table('log_table')->where('id', $blocklog->id)
                    ->update(['status' => 1,'request_msg'=>$xml,'request_url'=>$url,'response_msg'=>$response]);
                $return_resp=array('status' => 1,'response'=> 'blocked successfully');
                return $return_resp;
            }else{
                    DB::table('log_table')->where('id', $blocklog->id)
                    ->update(['status' => 0,'request_msg'=>$xml,'request_url'=>$url,'response_msg'=>$response]);
                $return_resp=array('status' => 0,'response'=> $response);
                return $return_resp;
            }
        }
        else{
            $return_resp=array('status' => 0,'response'=> $response);
            return $return_resp;
        }
        
   }
   public function goomoBlockIventry($ota_id,$hotel_id,$ota_hotel_code,$date_from,$date_to,$ota_room_type,$auth_parameter,$commonUrl,$user_id,$client_ip,$inventoryId)
   {
        $apiKey               			= trim($auth_parameter->apiKey);
        $channelId               		= trim($auth_parameter->channelId);
        $accessToken               		= trim($auth_parameter->accessToken);
        $blocklog = new LogTable();     
        $log_data                 		= [
           "action_id"          => 3,
           "hotel_id"           => $hotel_id,
           "ota_id"      		 => $ota_id,
           "booking_ref_id"     => '',
           "inventory_ref_id"   => $inventoryId,
           "rate_ref_id"        => '',
           "user_id"            => $user_id,
           "request_msg"        =>  '',
           "response_msg"       =>  '',
           "request_url"        =>  '',
           "status"         	 => 2,
           "ip"         		 => $client_ip,
           "comment"			 => "Processing for update"
           ];
             $blocklog->fill($log_data)->save();
        $days=array('true', 'true', 'true','true','true','true','true');
        $post_data=array("available" =>0, "block"=>true, "days"=>$days, "channelName"=> "Bookingjini", 
        "startDate" => $date_from,
        "endDate" =>$date_to,
        "roomId" => $ota_room_type,
        "productId"=>$ota_hotel_code);
        $post_data=json_encode($post_data);
        $log_request_msg="";
        $log_request_msg=$commonUrl.'/updateInventory'.$post_data;	
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "$commonUrl/updateInventory");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_POST, 1);

        $headers = array();
        $headers[] = "apiKey: $apiKey";
        $headers[] = "channelId: $channelId";
        $headers[] = "accessToken: $accessToken";
        $headers[] = "Content-Type: application/json";
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }
        curl_close ($ch);
        $array_data = json_decode($response, true);
        if(!isset($array_data['Error'])){
                DB::table('log_table')->where('id', $blocklog->id)
                ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$response]);
            $return_resp=array('status' => 1,'response'=> $response);
            return $return_resp;
        }
        else
        {
                DB::table('log_table')->where('id', $blocklog->id)
                ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$response]);
            $return_resp=array('status' => 0,'response'=> $response);
            return $return_resp;
        }
    }
    //Airbnb block inventory
    public function airbnbBlockIventory($ota_id,$hotel_id,$ota_hotel_code,$date_from,$date_to,$ota_room_type,$auth_parameter,$commonUrl,$user_id,$client_ip,$inventoryId)
    {
        $blocklog = new LogTable();     
        $log_data                 		= [
            "action_id"          => 3,
            "hotel_id"           => $hotel_id,
            "ota_id"      		 => $ota_id,
            "booking_ref_id"     => '',
            "inventory_ref_id"   => $inventoryId,
            "rate_ref_id"        => '',
            "user_id"            => $user_id,
            "request_msg"        =>  '',
            "response_msg"       =>  '',
            "request_url"        =>  '',
            "status"         	 => 2,
            "ip"         		 => $client_ip,
            "comment"			 => "Processing for update"
            ];
            $blocklog->fill($log_data)->save();
        $ota_details_data             	= CmOtaDetails::select('*')
												->where('hotel_id', '=' ,$hotel_id)
												->where('ota_id', '=' ,$ota_id)
												->first(); 	 	

			$ota_id 					  	= $ota_details_data->ota_id;
			$ota_hotel_code 			  	= $ota_details_data->ota_hotel_code;
			$auth_parameter       			= json_decode($ota_details_data->auth_parameter);
            $api_key               			= trim($auth_parameter->X_Airbnb_API_Key);
			$commonUrl      				= $ota_details_data->url;
			$hotel_info=HotelInformation::where('hotel_id',$hotel_id)->first();
			$airbnbModel=new AirbnbListingDetails();
			$company= new CompanyDetails();
			$comp_details=$company->where('company_id',$hotel_info->company_id)->select('airbnb_refresh_token')->first();
			$refresh_token=$comp_details->airbnb_refresh_token;

            $oauth_Token      = $airbnbModel->getAirBnbToken($refresh_token);
            $post_data=array();
				$post_data['listing_id']=$ota_room_type;
				$operations=array();
				$operations['dates']=array($date_from .":".$date_to );
				$operations['availability']="unavailable";
				$post_data['operations']=array($operations);
				$post_data=json_encode($post_data);
				$log_request_msg="";
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, "$commonUrl/calendar_operations?_allow_dates_overlap=true");
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
				curl_setopt($ch, CURLOPT_POST, 1);
				$headers = array();
				$headers[] = "X-Airbnb-Api-Key: $api_key";
				$headers[] = "X-Airbnb-Oauth-Token: $oauth_Token";
				$headers[] = "Content-Type: application/json";
				curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

				$result = curl_exec($ch);
				if (curl_errno($ch)) {
					echo 'Error:' . curl_error($ch);
				}
				curl_close ($ch);
                $array_data = json_decode($result, true);
                
                if(!isset($array_data['Error'])){
                        DB::table('log_table')->where('id', $blocklog->id)
                        ->update(['status' => 1,'request_msg'=>$post_data,'response_msg'=>$result]);
                    $return_resp=array('status' => 1,'response'=> ' blocked successfully');
                    return $return_resp;
                    }
                else{
                        DB::table('log_table')->where('id', $blocklog->id)
                        ->update(['status' => 0,'request_msg'=>$post_data,'response_msg'=>$result]);
                    $return_resp=array('status' => 0,'response'=> $result);
                    return $return_resp;
                }
    }
    public function easemytripBlockIventory($ota_id,$hotel_id,$ota_hotel_code,$date_from,$date_to,$ota_room_type,$auth_parameter,$commonUrl,$user_id,$client_ip,$inventoryId)
    {		
        
        $token_key						= $auth_parameter->Token;
        $blocklog = new LogTable();     
        $headers = array('Content-Type:application/json', 'Expect:');
        $log_data                 		= [
            "action_id"          => 3,
            "hotel_id"           => $hotel_id,
            "ota_id"      		 => $ota_id,
            "booking_ref_id"     => '',
            "inventory_ref_id"   => $inventoryId,
            "rate_ref_id"        => '',
            "user_id"            => $user_id,
            "request_msg"        =>  '',
            "response_msg"       =>  '',
            "request_url"        =>  '',
            "status"         	 => 2,
            "ip"         		 => $client_ip,
            "comment"			 => "Processing for update"
            ];
        $commonUrl = $commonUrl.'/save';
        $room_quanty    =  0;
        $blocklog->fill($log_data)->save();
        $post_data=' {
            "RequestType": "SaveSupplierHotel",
            "Token": "'.$token_key.'",
            "HotelCode": "'.$ota_hotel_code.'",
            "Data": [
            {
            "RequestType": "UpdateAllocation",
            "Data": [
            {
            "RoomCode": "'.$ota_room_type.'",
            "From": "'.$date_from.'",
            "To": "'.$date_to.'",
            "Allocation":'.$room_quanty.'
            }
            ]
            }
            ]
           }';
        
        $log_request_msg = $post_data;
        $ch  = curl_init();
        curl_setopt($ch, CURLOPT_URL, $commonUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if( curl_exec($ch) === false ){
        $result = curl_error($ch);
        }else{
        $result = curl_exec($ch);
        }
        curl_close($ch);
        $array_data = json_decode($result,true);
        
        if(isset($array_data["Status"])){
        if($array_data["Status"] == true){
            DB::table('log_table')->where('id', $blocklog->id)
            ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
            $return_resp=array('status' => 1,'response'=> 'blocked successfully');
            return $return_resp;
            }
            else{
                DB::table('log_table')->where('id', $blocklog->id)
                ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                $return_resp=array('status' => 0,'response'=> $result);  
                return $return_resp; 
            }
        }
        else{
            DB::table('log_table')->where('id', $blocklog->id)
            ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
            $return_resp=array('status' => 0,'response'=> $result);
            return $return_resp;
        }
    }
    public function paytmBlockIventory($ota_id,$hotel_id,$ota_hotel_code,$date_from,$date_to,$ota_room_type,$auth_parameter,$commonUrl,$user_id,$client_ip,$inventoryId)
    {		   
        $api_key						= $auth_parameter->api_key;
        $blocklog = new LogTable();     
        $headers = array('Content-Type:application/json', 'Expect:');
        $log_data                 		= [
            "action_id"          => 3,
            "hotel_id"           => $hotel_id,
            "ota_id"      		 => $ota_id,
            "booking_ref_id"     => '',
            "inventory_ref_id"   => $inventoryId,
            "rate_ref_id"        => '',
            "user_id"            => $user_id,
            "request_msg"        =>  '',
            "response_msg"       =>  '',
            "request_url"        =>  '',
            "status"         	 => 2,
            "ip"         		 => $client_ip,
            "comment"			 => "Processing for update"
            ];
        $commonUrl = $commonUrl.'/inventoryUpdate';
        $room_quanty    =  0;
        $blocklog->fill($log_data)->save();
        $post_data='{
			"auth": {
			"key": "'.$api_key.'"
			},
			"data": {
			"propertyId": "'.$ota_hotel_code.'",
			"roomId": "'.$ota_room_type.'",
			"inventory": [
			{
			"startDate": "'.$date_from.'",
            "endDate": "'.$date_to.'",
            "block": "true",
			"free": "'.$room_quanty.'"
			}
			]
			}
			}';
    
        $log_request_msg = $post_data;
        $blocklog->fill($log_data)->save();
        $ch  = curl_init();
        curl_setopt($ch, CURLOPT_URL, $commonUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if( curl_exec($ch) === false ){
        $result = curl_error($ch);
        }else{
        $result = curl_exec($ch);
        }
        curl_close($ch);
        $array_data = json_decode($result,true);
        
        if(isset($array_data["Status"])){
        if($array_data["Status"] == true){
            DB::table('log_table')->where('id', $blocklog->id)
            ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
            $return_resp=array('status' => 1,'response'=> 'blocked successfully');
            return $return_resp;
            }
            else{
                DB::table('log_table')->where('id', $blocklog->id)
                ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
                $return_resp=array('status' => 0,'response'=> $result);  
                return $return_resp; 
            }
        }
        else{
            DB::table('log_table')->where('id', $blocklog->id)
            ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
            $return_resp=array('status' => 0,'response'=> $result);
            return $return_resp;
        }
    }
}