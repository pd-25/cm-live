<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Validator;
use DB;
// use App\Http\Controllers\IpAddressService;
use App\Http\Controllers\WinhmsController;
use App\Http\Controllers\BookingjiniPMSController;
use App\Http\Controllers\PmsService;
use App\PmsDetails;
use App\HotelInformation;
use App\WinHms;
use App\CmOtaDetails;
use App\WinhmsRoom;
use App\PmsAccount;

class WinhmsInvPushController extends Controller
{
   protected $pmsService,$WinhmsController,$BookingjiniPMSController;
    public function __construct(PmsService $pmsService,WinhmsController $WinhmsController,BookingjiniPMSController $BookingjiniPMSController)
    {
       $this->pmsService = $pmsService;
    //    $this->ipAddress = $ipAddress;
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
        $xml = $request->getContent();
        $timestamp_current = date('Y-m-d\TH:i:sP');
        $array_data = json_decode(json_encode(simplexml_load_string($xml)), true);

        $apikey =$array_data['@attributes']['EchoToken'];
        $username = $array_data['POS']['Source']['RequestorID']['@attributes']['ID'];
        $password = $array_data['POS']['Source']['RequestorID']['@attributes']['MessagePassword'];
        $source_of_request = $array_data['POS']['Source']['RequestorID']['@attributes']['ID_Context'];

        if(empty($username)){
            $res_user='<?xml version="1.0" encoding="UTF-8"?>
                        <OTA_HotelInvCountNotifRS EchoToken="'.@$apikey.'" TimeStamp="'.$timestamp_current.'" Version="2.1">
                        <Errors>
                        <Error Code="500" Type="error" Status="error" ShortText="User Name can not be empty." />
                        </Errors></OTA_HotelInvCountNotifRS>';
            return $res_user;
        }
        if(empty($password)){
            $res_pass='<?xml version="1.0" encoding="UTF-8"?>
                        <OTA_HotelInvCountNotifRS EchoToken="'.@$apikey.'" TimeStamp="'.$timestamp_current.'" Version="2.1">
                        <Errors>
                        <Error Code="500" Type="error" Status="error" ShortText="Password can not be empty." />
                        </Errors></OTA_HotelInvCountNotifRS>';
            return $res_pass;
        }

        //$timestamp =$array_data['@attributes']['TimeStamp'];
        $hotel_code = $array_data["Inventories"]["@attributes"]['HotelCode'];
        $conditions = ['user_name'=>$username,'password'=>md5($password),'name'=>'Winhms'];
        $getHotelId = PmsDetails::select('hotels', 'api_key', 'name', 'id','user_name','password')->where($conditions)->first();
        $update_cm = 'yes';//providing the data to all channels.
        $ip = $_SERVER['REMOTE_ADDR'];
        $cur_date = date('Y-m-d');
        //$count = 0;
        $details = 0;
        $pms_name = '';
        $pms_id = 0;
        if(empty($getHotelId)){
            $res_auth='<?xml version="1.0" encoding="UTF-8"?>
                        <OTA_HotelInvCountNotifRS EchoToken="'.@$apikey.'" TimeStamp="'.$timestamp_current.'" Version="2.1">
                        <Errors>
                        <Error Code="500" Type="error" Status="error" ShortText="Authentication Failed!!" />
                        </Errors></OTA_HotelInvCountNotifRS>';
            return $res_auth;
        }
        $hotel_code_from_db = $this->WinhmsController->WinhmsHotel($hotel_code);
        if(empty($hotel_code_from_db)){
            $hotel_info = 0;
        }else{
            $hotel_info = 1;
        }
        if(isset($getHotelId->hotels)){
            $array_hotels = explode(',', $getHotelId->hotels);
            if(in_array($hotel_code_from_db,$array_hotels)){
                $details = 1;
            }
        }
        if ($details < 0 || $hotel_info == 0) {
            $res='<?xml version="1.0" encoding="UTF-8"?>
                            <OTA_HotelInvCountNotifRS EchoToken="'.$apikey.'" TimeStamp="'.$timestamp_current.'" Version="2.1">
                            <Errors>
                            <Error Code="500" Type="error" Status="error" ShortText="Enter valid hotel code" />
                            </Errors></OTA_HotelInvCountNotifRS>';
            return $res;
        }
        $pms_name = $getHotelId->name;
        $pms_id = $getHotelId->id;
        if($hotel_code != ''){
             if(!isset($array_data['Inventories']["Inventory"]["StatusApplicationControl"])){
                $data["hotel_name"] = HotelInformation::select('hotel_name','hotel_id')->where('hotel_id', $hotel_code_from_db)->first();
                $hotel_name = (empty($data["hotel_name"]->hotel_name)) ? '' : $data["hotel_name"]->hotel_name;
                $xml_data_header = '<?xml version="1.0" encoding="UTF-8"?>
                                <OTA_HotelInvCountNotifRQ xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                                    xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns="http://www.opentravel.org/OTA/2003/05" EchoToken="'.$apikey.'"
                                    TimeStamp="'.$timestamp_current.'" Target="Production" Version="0">
                                    <Inventories HotelCode="'.$hotel_code_from_db.'" HotelName="'.$hotel_name.'">';
                $xml_data_body = '';
               foreach($array_data['Inventories']["Inventory"] as $inv_data){
                 $date_from         = $inv_data["StatusApplicationControl"]['@attributes']["Start"];
                 $date_to           = $inv_data["StatusApplicationControl"]['@attributes']["End"];
                 $ota_room_type     = $inv_data["StatusApplicationControl"]['@attributes']["InvTypeCode"];
                 if($ota_room_type == "0" || strlen($ota_room_type) <= 0){
                    $res='<?xml version="1.0" encoding="UTF-8"?>
                         <OTA_HotelInvCountNotifRS EchoToken="'.$apikey.'" TimeStamp="'.$timestamp.'" Version="2.1">
                         <Errors>
                         <Error Code="500" Type="error" Status="error" ShortText="Enter valid inventory type code" />
                         </Errors></OTA_HotelInvCountNotifRS>' ; 
                     return $res; 
                 }
                 $date_from         = date('Y-m-d',strtotime($date_from));
                 $date_to           = date('Y-m-d',strtotime($date_to));
                 $ota_room_type_from_db = $this->WinhmsController->checkInvTypeCode($ota_room_type,$hotel_code);

                 $getlos = DB::connection('be')->table('inventory_table')->select('los','block_status')
                             ->where('hotel_id',$hotel_code_from_db)->where('room_type_id',$ota_room_type_from_db)
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
                     $no_of_rooms=$inv_data["InvCounts"]["InvCount"]['@attributes']['Count'];
                     if(strlen($no_of_rooms) <= 0)
                     {
                             $res='<?xml version="1.0" encoding="UTF-8"?>
                                <OTA_HotelInvCountNotifRS EchoToken="'.$apikey.'" TimeStamp="'.$timestamp.'" Version="2.1">
                                <Errors>
                                <Error Code="500" Type="error" Status="error" ShortText="Enter valid number of rooms" />
                                </Errors></OTA_HotelInvCountNotifRS>' ; 
                            return $res;
                     }
                     //re assign variable by calling controller method
                    
                     $this->pmsService->serviceRequest($apikey,$hotel_code_from_db, $ip, 'Update Inventory');

                     if(strtotime($date_to) >= strtotime($date_from))
                     {
                            $xml_data_body.= '<Inventory>
                                                <StatusApplicationControl InvTypeCode="'.$ota_room_type_from_db.'" Start="'.$date_from.'" End="'.$date_to.'" />
                                                    <InvCounts>
                                                        <InvCount Count="'.$no_of_rooms.'" />
                                                    </InvCounts>
                                                </Inventory>';
                         
                    }else{
                        $return_array = '<?xml version="1.0" encoding="UTF-8"?>
                                        <OTA_HotelInvCountNotifRS EchoToken="'.$apikey.'" TimeStamp="'.$timestamp_current.'" Version="2.1">
                                        <Errors>
                                        <Error Code="500" Type="error" Status="error" ShortText="From Date should be less than or equal to to date and To date should be more that today date" />
                                        </Errors></OTA_HotelInvCountNotifRS>';
                        return $return_array;
                    }

        }
            $xml_data_footer = '</Inventories></OTA_HotelInvCountNotifRQ>';
            $xml_data = $xml_data_header.$xml_data_body.$xml_data_footer;
            $response = $this->BookingjiniPMSController->inventoryUpdateToBookingjini($xml_data,0,'winhms');
                if($response){
                    $return_array = '<?xml version="1.0" encoding="UTF-8"?>
                                    <OTA_HotelInvCountNotifRS xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                                    xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns="http://www.opentravel.org/OTA/2003/05"
                                    EchoToken="'.$apikey.'" TimeStamp="'.$timestamp_current.'" Version="2.1">
                                    <Success/>
                                    </OTA_HotelInvCountNotifRS>';
                }
                return $return_array;
        }else{
            $date_from = $array_data['Inventories']["Inventory"]["StatusApplicationControl"]['@attributes']["Start"];
            $date_to = $array_data['Inventories']["Inventory"]["StatusApplicationControl"]['@attributes']["End"];
            $ota_room_type = $array_data['Inventories']["Inventory"]["StatusApplicationControl"]['@attributes']["InvTypeCode"];
            if($ota_room_type == "0" || strlen($ota_room_type) <= 0) { 
                $res='<?xml version="1.0" encoding="UTF-8"?>
                                <OTA_HotelInvCountNotifRS EchoToken="'.$apikey.'" TimeStamp="'.$timestamp_current.'" Version="2.1">
                                <Errors>
                                <Error Code="500" Type="error" Status="error" ShortText="Enter valid inventory type code" />
                                </Errors></OTA_HotelInvCountNotifRS>' ; 
                return $res; 
            }
            $date_from = date('Y-m-d',strtotime($date_from));
            $date_to = date('Y-m-d',strtotime($date_to));
            $ota_room_type_from_db = $this->WinhmsController->checkInvTypeCode($ota_room_type,$hotel_code);
            $getlos = DB::connection('be')->table('inventory_table')->select('*')
            ->where('hotel_id',$hotel_code_from_db)->where('room_type_id',$ota_room_type_from_db)
            ->where('date_from','<=',$date_from) ->where('date_to','>=',$date_to)
                ->orderBy('inventory_id','DESC')
                ->first();
            if(isset($getlos->los)){
                $los = $getlos->los;
                $block_status = $getlos->block_status;
            }
            else{
                $los = 0;
                $block_status = 0;
            }
                $no_of_rooms=$array_data['Inventories']["Inventory"]["InvCounts"]["InvCount"]['@attributes']['Count'];
                if(strlen($no_of_rooms) <=0) { 
                    $res='<?xml version="1.0" encoding="UTF-8"?>
                                <OTA_HotelInvCountNotifRS EchoToken="'.$apikey.'" TimeStamp="'.$timestamp_current.'" Version="2.1">
                                <Errors>
                                <Error Code="500" Type="error" Status="error" ShortText="Enter valid number of rooms" />
                                </Errors></OTA_HotelInvCountNotifRS>' ; 
                    return $res; 
                } 
                //re assign variable by calling controller method
                $this->pmsService->serviceRequest($apikey,$hotel_code, $ip, 'Update Inventory');

                if(strtotime($date_to) >= strtotime($date_from))
                {

                    $data["hotel_name"] = HotelInformation::select('hotel_name','hotel_id')->where('hotel_id', $hotel_code_from_db)->first();
                    $hotel_name = (empty($data["hotel_name"]->hotel_name)) ? '' : $data["hotel_name"]->hotel_name;                   

                    $xml_data = '<?xml version="1.0" encoding="UTF-8"?>
                                    <OTA_HotelInvCountNotifRQ xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                                        xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns="http://www.opentravel.org/OTA/2003/05" EchoToken="'.$apikey.'" TimeStamp="'.$timestamp_current.'" Target="Production" Version="0">
                                        <Inventories HotelCode="'.$hotel_code_from_db.'" HotelName="'.$hotel_name.'">
                                            <Inventory>
                                            <StatusApplicationControl InvTypeCode="'.$ota_room_type_from_db.'" Start="'.$date_from.'" End="'.$date_to.'" />
                                                <InvCounts>
                                                    <InvCount Count="'.$no_of_rooms.'" />
                                                </InvCounts>
                                            </Inventory>
                                        </Inventories>
                                    </OTA_HotelInvCountNotifRQ>';

                    $response = $this->BookingjiniPMSController->inventoryUpdateToBookingjini($xml_data,0,'winhms');
                    if($response){
                            $return_array = '<?xml version="1.0" encoding="UTF-8"?>
                                            <OTA_HotelInvCountNotifRS xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                                            xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns="http://www.opentravel.org/OTA/2003/05"
                                            EchoToken="'.$apikey.'" TimeStamp="'.$timestamp_current.'" Version="2.1">
                                            <Success/>
                                            </OTA_HotelInvCountNotifRS>';
                            return $return_array;
                    }
                }else{
                    $return_array = '<?xml version="1.0" encoding="UTF-8"?>
                                <OTA_HotelInvCountNotifRS EchoToken="'.$apikey.'" TimeStamp="'.$timestamp_current.'" Version="2.1">
                                <Errors>
                                <Error Code="500" Type="error" Status="error" ShortText="From Date should be less than or equal to to date and To date should be more that today date" />
                                </Errors></OTA_HotelInvCountNotifRS>';
                    return $return_array;
                }
            }
        }
    }      
}
?>