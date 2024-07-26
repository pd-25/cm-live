<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Validator;
use DB;
use App\Http\Controllers\IpAddressService;
use App\Http\Controllers\WinhmsController;
use App\Http\Controllers\BookingjiniPMSController;
use App\Http\Controllers\PmsService;
use App\PmsDetails;
use App\HotelInformation;
use App\WinHms;
use App\CmOtaDetails;
use App\WinhmsRoom;

class WinhmsInvPushController extends Controller
{
   protected $pmsService,$ipAddress,$WinhmsController,$BookingjiniPMSController;
    public function __construct(PmsService $pmsService,IpAddressService $ipAddress,WinhmsController $WinhmsController,BookingjiniPMSController $BookingjiniPMSController)
    {
       $this->pmsService = $pmsService;
       $this->ipAddress = $ipAddress;
       $this->WinhmsController = $WinhmsController;
       $this->BookingjiniPMSController = $BookingjiniPMSController;
    }
    
    // public static function serviceRequest($api_key, $hotel_id, $ip, $request_for){
    //         $serviceRequestModel= new PmsRequest();

    //         $serviceRequestModel->api_key     = $api_key;
    //         $serviceRequestModel->hotel_id    = $hotel_id;
    //         $serviceRequestModel->ip          = $ip;
    //         $serviceRequestModel->request_for = $request_for;
    //         $serviceRequestModel->save();
    // }

    public function winhmsInventoryUpdate(Request $request){
        dd('hello');
        $json = file_get_contents('php://input');
        if(empty($json)){
                $res='<OTA_HotelInventoryDetailsInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance"
                 xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01">
                         <Error>No request Found..</Error>
                     </OTA_HotelInventoryDetailsInfoRS>';
                return $res;
        }
        $array_data = json_decode(json_encode(simplexml_load_string($json)), true);
        //print_r($array_data);exit;
        $apikey =$array_data['@attributes']['EchoToken'];
        $timestamp =$array_data['@attributes']['TimeStamp'];
        $hotel_code = $array_data["Inventories"]["@attributes"]['HotelCode'];
        $getHotelId = PmsDetails::select('hotels', 'api_key', 'name', 'id')->get();
        $update_cm = 'no';//Chnahe this if hotel have CM
        $ip = $_SERVER['REMOTE_ADDR'];
        $cur_date = date('Y-m-d');
        $count = 0;
        $details = 0;
        $pms_name = '';
        $pms_id = 0;
        //echo "<pre>";echo $hotel_code;print_r($getHotelId);exit;
            foreach ($getHotelId as $hoteldetails) {
                if ($hoteldetails->api_key == $apikey) {
                    $count = $count+1;
                    $details = strpos($hoteldetails->hotels, $hotel_code);
                    $array_hotels = explode(',', $hoteldetails->hotels);
                    $details=in_array($hotel_code,$array_hotels);
                    $pms_name = $hoteldetails->name;
                    $pms_id = $hoteldetails->id;
                }
            }
            if ($count <= 0) {
                $res='<OTA_HotelInventoryDetailsInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance"
                xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01">
                     <Error>Invalid apikey</Error>
                 </OTA_HotelInventoryDetailsInfoRS>';
                return $res;
            }
            if ($details < 0 || $hotel_code == "0") {
                $res='<OTA_HotelInventoryDetailsInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance"
                xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01">
                     <Error>Enter valid hotel code</Error>
                 </OTA_HotelInventoryDetailsInfoRS>';
                return $res;
            }else if($hotel_code != ''){
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
                           $block_status = $getlos->block_status;
                       }else{
                           $los = 0;
                           $block_status = 0;
                       }
                     if($ota_room_type == "0" || strlen($ota_room_type) <= 0){
                           $res='<OTA_HotelInventoryDetailsInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance"
                           xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01">
                                     <Error>Enter valid inventory type code</Error>
                                 </OTA_HotelInventoryDetailsInfoRS>';
                             return $res;
                      }
                     $no_of_rooms=$inv_data["InvCounts"]["InvCount"]['@attributes']['Count'];
                     if(strlen($no_of_rooms) <= 0)
                     {
                           $res='<OTA_HotelInventoryDetailsInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance"
                           xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01">
                                      <Error>Enter valid number of rooms</Error>
                                 </OTA_HotelInventoryDetailsInfoRS>';
                             return $res;
                     }
                     $this->pmsService->serviceRequest(0,$hotel_code, $ip, 'Update Inventory');
                     if($blk_status == 1){
                       $block_status = 1;
                     }
                     $inventory = array(
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

                     if(strtotime($date_to) >= strtotime($date_from))
                     {
                         $winhmsInvPush = new WinHms();
                         $updateInventory = $winhmsInvPush->fill($inventory)->save();
                           if($updateInventory){

                            $data["hotel_name"] = HotelInformation::select('hotel_name','hotel_id')->where('hotel_id', $hotel_id)->first();
                            $hotel_name = (empty($data["hotel_name"]->hotel_name)) ? '' : $data["hotel_name"]->hotel_name;
                            $ota_details = CmOtaDetails::select('ota_name','ota_id')->where('hotel_id',$hotel_id)->first();

                            foreach($ota_details as $values){
                                $ota_name = $values->ota_name;
                                $ota_id = $values->ota_id;
                            }

                            //re assign variable by calling controller method
                            $ota_room_type = $this->WinhmsController->checkInvTypeCode($ota_room_type,$hotel_code);
                            $hotel_code = $this->WinhmsController->WinhmsHotel($hotel_code);

                             $xml_data = '<?xml version="1.0" encoding="UTF-8"?>
                                            <OTA_HotelInvCountNotifRQ xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                                                xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns="http://www.opentravel.org/OTA/2003/05" EchoToken="'.$apikey.'"
                                                TimeStamp="'.$timestamp.'" Target="Production" Version="0">
                                                <POS>
                                                    <Source>
                                                    <RequestorID ID="'.$ota_id.'" ID_Context="'.$ota_name.'" MessagePassword="'.$apikey.'" />
                                                    </Source>
                                                </POS>
                                                <Inventories HotelCode="'.$hotel_code.'" HotelName="'.$hotel_name.'">
                                                    <Inventory>
                                                        <StatusApplicationControl InvTypeCode="'.$ota_room_type.'" Start="'.$date_from.'" End="'.$date_to.'" />
                                                        <InvCounts>
                                                            <InvCount Count="'.$no_of_rooms.'" />
                                                        </InvCounts>
                                                    </Inventory>
                                                </Inventories>
                                            </OTA_HotelInvCountNotifRQ>';
                    $response = $this->BookingjiniPMSController->inventoryUpdateToBookingjini($xml_data,$blk_status,'winhms');
                    if($response){
                        $return_array = '<OTA_HotelInventoryDetailsInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance"
                        xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema" xmlns="http://www.bookingjini.com/OTA/2017/01">
                        <Success>Inventory update sucessfully</Success>
                    </OTA_HotelInventoryDetailsInfoRS>';
                    }
            }
        }else{
            $return_array = '<OTA_HotelInventoryDetailsInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance"
                xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema" xmlns="http://www.bookingjini.com/OTA/2017/01">
                <Error>From Date should be less than or equal to to date and To date should be more that today date </Error>
            </OTA_HotelInventoryDetailsInfoRS>';
            return $return_array;
        }

    }
        return $return_array;
    }else{
        $date_from = $array_data['Inventories']["Inventory"]["StatusApplicationControl"]['@attributes']["Start"];
        $date_to = $array_data['Inventories']["Inventory"]["StatusApplicationControl"]['@attributes']["End"];
        $ota_room_type = $array_data['Inventories']["Inventory"]["StatusApplicationControl"]['@attributes']["InvTypeCode"];
        $date_from = date('Y-m-d',strtotime($date_from));
        $date_to = date('Y-m-d',strtotime($date_to));
        $getlos = DB::connection('be')->table('inventory_table')->select('*')
        ->where('hotel_id',$hotel_code)->where('room_type_id',$ota_room_type)
        ->where('date_from','<=',$date_from) ->where('date_to','>=',$date_to)
            ->orderBy('inventory_id','DESC')
            ->first();
        if(isset($getlos->los)){
        $los = $getlos->los;
        $block_status = $getlos->block_status;
        }else{
        $los = 0;
        $block_status = 0;
        }
        if($ota_room_type == "0" || strlen($ota_room_type) <= 0) { 
            $res='<OTA_HotelInventoryDetailsInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance" xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01"><Error>Enter valid inventory type code</Error></OTA_HotelInventoryDetailsInfoRS>' ; return $res; 
        }
            $no_of_rooms=$array_data['Inventories']["Inventory"]["InvCounts"]["InvCount"]['@attributes']['Count'];
            if(strlen($no_of_rooms) <=0) { 
                $res='<OTA_HotelInventoryDetailsInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance"
                      xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema"  xmlns="http://www.bookingjini.com/OTA/2017/01">
                                 <Error>Enter valid number of rooms</Error>
                            </OTA_HotelInventoryDetailsInfoRS>' ; 
                return $res; 
            } 
            $this->pmsService->serviceRequest(0,$hotel_code, $ip, 'Update Inventory');
            if(!empty($blk_status)){
                if($blk_status == 1){
                    $block_status = 1;
                }
            }
            $inventory = array(
            "hotel_id" => $hotel_code,
            "room_type_id" => $ota_room_type,
            "date_from" => $date_from,
            "date_to" => $date_to,
            "no_of_rooms" => $no_of_rooms,
            "pms_id" => $pms_id,
            "pms_name" => $pms_name,
            "update_cm" => $update_cm,
            "api_key" => $apikey,
            "los" => $los,
            "block_status" => $block_status,
            "client_ip" => $ip,
            );

            if(strtotime($date_to) >= strtotime($date_from))
            {
                //Update Inventory
                $winhmsInvPush = new WinHms();
                $updateInventory = $winhmsInvPush->fill($inventory)->save();

                $data["hotel_name"] = HotelInformation::select('hotel_name','hotel_id')->where('hotel_id', $hotel_code)->first();
                $hotel_name = (empty($data["hotel_name"]->hotel_name)) ? '' : $data["hotel_name"]->hotel_name;
                $ota_details = CmOtaDetails::select('ota_name','ota_id')->where('hotel_id',$hotel_code)->first();
                //echo "<pre>";dd($ota_details);exit;
                foreach($ota_details as $values){
                    $ota_name = $values->ota_name;
                    $ota_id = $values->ota_id;
                }
                //re assign variable by calling controller method
                $ota_room_type = $this->WinhmsController->checkInvTypeCode($ota_room_type,$hotel_code);
                $hotel_code = $this->WinhmsController->WinhmsHotel($hotel_code);

                $xml_data = '
                <?xml version="1.0" encoding="UTF-8"?>
                <OTA_HotelInvCountNotifRQ xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                    xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns="http://www.opentravel.org/OTA/2003/05"
                    EchoToken="'.$apikey.'" TimeStamp="'.$timestamp.'" Target="Production" Version="0">
                    <POS>
                        <Source>
                        <RequestorID ID="'.$ota_id.'" ID_Context="'.$ota_name.'" MessagePassword="'.$apikey.'" />
                        </Source>
                    </POS>
                    <Inventories HotelCode="'.$hotel_code.'" HotelName="'.$hotel_name.'">
                        <Inventory>
                            <StatusApplicationControl InvTypeCode="'.$ota_room_type.'" Start="'.$date_from.'"
                                End="'.$date_to.'" />
                            <InvCounts>
                                <InvCount Count="'.$no_of_rooms.'" />
                            </InvCounts>
                        </Inventory>
                    </Inventories>
                </OTA_HotelInvCountNotifRQ>';

                $response = $this->$BookingjiniPMSController->inventoryUpdateToBookingjini($xml_data,$blk_status,'winhms');
                if($response){
                    $callToUpdate = array("status"=>'success',"code"=>200,"message"=>'Inventory update successfully');
                    //return response()->json($callToUpdate);
                }

                if($updateInventory)
                {
                    $return_array = '<OTA_HotelInventoryDetailsInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance"
                    xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema" xmlns="http://www.bookingjini.com/OTA/2017/01">
                    <Success>Inventory update sucessfully</Success>
                    </OTA_HotelInventoryDetailsInfoRS>';
                    return $return_array;
                }
            }else{
                $return_array = '<OTA_HotelInventoryDetailsInfoRS xmlns:xsi="http://www.bookingjini.com/2017/XMLSchema-instance"
                    xmlns:xsd="http://www.bookingjini.com/2017/XMLSchema" xmlns="http://www.bookingjini.com/OTA/2017/01">
                    <Error>From Date should be less than or equal to to date and To date should be more that today date </Error>
                    </OTA_HotelInventoryDetailsInfoRS>';
                return $return_array;
            }
        }
            }
        }
        
    }
?>