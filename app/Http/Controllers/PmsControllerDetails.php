<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use DB;
use App\PmsAccount;
use App\RoomTypeTable;
use App\IdsRoom;
use App\PmsRoomFetch;
use App\IdsHotelTracker;
use App\InvControlTable;
use App\KtdcRoom;

class PmsControllerDetails extends Controller
{
    public function getAllPmsType(Request $request)
    {
        $pmsData = PmsAccount::select('id','name')->get();
        if(sizeof($pmsData))
        {
            $res = array('status'=>1,'message'=>'Data Retrieved Successfull','data'=>$pmsData);
            return response()->json($res);
        }else{
            $res = array('status'=>0,'message'=>'Data Retrieved Failed');
            return response()->json($res);
        }
    }
    public function getHotelPmsType($hotel_id,Request $request)
    {
        $pmsData = PmsAccount::select('*')->where('name','IDS NEXT')->get();
        if(sizeof($pmsData))
        {
            $res = array('status'=>1,'message'=>'Data Retrieved Successfull','data'=>$pmsData);
            return response()->json($res);
        }else{
            $res = array('status'=>0,'message'=>'Data Retrieved Failed');
            return response()->json($res);
        }
    }
    public function getAllHotelCode(Request $request)
    {
        $pmsData = PmsAccount::select('id','name')->where('name','IDS NEXT')->first();;
        if(sizeof($pmsData)>0)
        {
            $res = array('status'=>1,'message'=>'Data Retrieved Successfull','data'=>$pmsData);
            return response()->json($res);
        }else{
            $res = array('status'=>0,'message'=>'Data Retrieved Failed');
            return response()->json($res);
        }
    }
    public function addPmsAccountHotelId(Request $request)    {
      $data = $request->all();
      $hotel_code = $data['hotel_code'];
      $hotel_id = $data['hotel_id'];
      $pms_type_name = $data['pms_type_name'];
      $pms_status = $data['pms_status'];
      $hotel = '';
      $pmsData = PmsAccount::select('name','api_key','hotels','push_url','auth_key')->where('name',$pms_type_name)->get();
      if($pmsData){
        foreach($pmsData  as $getdata){
          $hotel = $getdata->hotels;
        }
        $arr2 = explode(',',$hotel);
        $check_array = $arr2;
        if(!in_array($hotel_id,$arr2)){
          array_push($arr2,$hotel_id);
        }
        $hotels = implode(',',$arr2);
        $updateData = PmsAccount::where('name',$pms_type_name)->update(['hotels'=>$hotels]);
        if($updateData){
          if(!in_array($hotel_id,$check_array)){
            $insertData = IdsHotelTracker::insert(['hotel_id'=>$hotel_id,'hotel_code'=>$hotel_code,'pms_room'=>trim($pms_type_name)]);
            if($pms_type_name == 'IDS NEXT'){
              $fetchRoomTypes = $this->addNewPmsSync($hotel_id);
            }
            if($pms_type_name == 'KTDC'){
                $fetchKtdcRoomTypes = $this->ktdcRoomSync($hotel_id);
            }
            if($pms_type_name == 'GEMS'){
              $fecthHotelForGems = $this->fetchHotelForGems($hotel_id);
            }
            if($insertData){
              //Insert Data To inventory_control_table
              $insertData = InvControlTable::insert(['hotel_id'=>$hotel_id,'pms_status'=>$pms_status]);
              if($pms_type_name == 'GEMS'){
                $res = array('status'=>1,'message'=>$fecthHotelForGems);
                return response()->json($res);
              }
              else{
                $res = array('status'=>1,'message'=>'Hotel Code Mapped Successful');
                return response()->json($res);
              }
            }
          }
          else{
            $res = array('status'=>1,'message'=>'Hotel Code Already Exist');
            return response()->json($res);
          }

        }
        else{
          $res = array('status'=>0,'message'=>'Hotel Code Mapped Failed');
          return response()->json($res);
        }
      }
    }

    public function addNewPmsSync($hotel_id)
    {
        // $data = $request->all();
        // $hotel_id = $data['hotel_id'];

        $pmsData = IdsHotelTracker::select('hotel_code')->where('hotel_id',$hotel_id)->first();
        $hotel_code = $pmsData->hotel_code;
        $url = "http://idsnextchannelmanagerapi.azurewebsites.net/BookingJini/GetHotelRoomTypes";
        $headers = array (
        //Regulates versioning of the XML interface for the API
            'Content-Type: application/xml',
            'Accept: application/xml',
            'Authorization:Basic aWRzbmV4dEJvb2tpbmdqaW5pQGlkc25leHQuY29tOmlkc25leHRCb29raW5namluaUAwNzExMjAxNw=='
        );
        $xml_data = '<RN_HotelRatePlanRQ xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns="http://www.opentravel.org/OTA/2003/05" Version="1.2" EchoToken="879791878">
            <RoomRatePlans>
                <HotelCriteria HotelCode="'.$hotel_code.'" />
            </RoomRatePlans>
        </RN_HotelRatePlanRQ>';

        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml_data);
        $response = curl_exec($ch);
        curl_close($ch);

        $array_data=json_decode(json_encode(simplexml_load_string($response)),true);
        for($i=0;$i<sizeof($array_data['RoomTypes']['RoomType']);$i++){
            $pms_room_type_code = $array_data['RoomTypes']['RoomType'][$i]['@attributes']['InvTypeCode'];
            $pms_room_type_name = $array_data['RoomTypes']['RoomType'][$i]['@attributes']['Name'];

            $insertData = PmsRoomFetch::insert(['hotel_id'=>$hotel_id,'hotel_code'=>$hotel_code,
            'pms_room_type'=>trim($pms_room_type_code),'pms_room_type_name'=>$pms_room_type_name]);
        }
        if($insertData){
            $res = array('status'=>1,'message'=>'PMS Hotel Sync Successfully');
            return response()->json($res);
        }
    }

    public function addNewPmsRoomSync(Request $request)
    {
        $data = $request->all();
        $hotel_id = $data['hotel_id'];
        $room_type_id = $data['room_type_id'];
        $ids_room_type_code = $data['pms_room_type'];

        $getHotelCode = PmsRoomFetch::select('*')->where('hotel_id',$hotel_id)->first();

        $hotel_code = $getHotelCode->hotel_code;

        $insertData = IdsRoom::insert(['hotel_id'=>$hotel_id,'ids_hotel_code'=>$hotel_code,'room_type_id'=>$room_type_id,'ids_room_type_code'=>$ids_room_type_code]);
        if($insertData){
            $res = array('status'=>1,'message'=>'PMS Room Sync Successfully');
            return response()->json($res);
        }else{
            $res = array('status'=>0,'message'=>'PMS Room Sync Failed');
            return response()->json($res);
        }
    }

    public function updatePmsRoomSync($sync_id, Request $request)
    {
        $data = $request->all();
        $room_type_id = $data['room_type_id'];
        $ids_room_type_code = $data['pms_room_type'];

        $updateData = IdsRoom::where('id',$sync_id)->update(['room_type_id'=>$room_type_id,'ids_room_type_code'=>$ids_room_type_code]);
        if($updateData){
            $res = array('status'=>1,'message'=>'PMS Room Sync Updated Successfully');
            return response()->json($res);
        }else{
            $res = array('status'=>0,'message'=>'PMS Room Sync Update Failed');
            return response()->json($res);
        }

    }

    public function deletePmsRoomSync($sync_id, Request $request)
    {
        $deleteData = IdsRoom::where('id',$sync_id)->delete();
        if($deleteData){
            $res = array('status'=>1,'message'=>'PMS Room Sync Deleted Successfully');
            return response()->json($res);
        }else{
            $res = array('status'=>0,'message'=>'PMS Room Sync Delete Failed');
            return response()->json($res);
        }

    }

    public function getPmsRoomSync($hotel_id,Request $request)
    {
        $getData=IdsRoom::join('kernel.room_type_table','room_type_table.room_type_id','=','ids_room.room_type_id')
        ->join("pms_room_fetch",function($join){
                    $join->on('pms_room_fetch.pms_room_type','=','ids_room.ids_room_type_code')
                        ->on('pms_room_fetch.hotel_id','=','ids_room.hotel_id');
        })
        ->select('ids_room.id','ids_room.hotel_id','room_type','room_type_table.room_type_id','ids_room_type_code','pms_room_fetch.pms_room_type_name')
        ->where('ids_room.hotel_id',$hotel_id)
        ->distinct('ids_room.id')
        ->get();
        if(sizeof($getData)>0)
        {
            $res = array('status'=>1,'message'=>'PMS room type retrieve successfully','data'=>$getData);
            return response()->json($res);
        }else{
            $res = array('status'=>0,'message'=>'PMS room type retrieve fails');
            return response()->json($res);
        }
    }

    public function getPmsRoomType($hotel_id,Request $request)
    {
        $getData = PmsRoomFetch::select('*')->where('hotel_id',$hotel_id)->get();
        if(sizeof($getData)>0){
            $res = array('status'=>1,'message'=>'PMS Room Types Retrieved Successfully','data'=>$getData);
            return response()->json($res);
        }
        else{
            $res = array('status'=>0,'message'=>'PMS Room Types Retrieved Failed');
            return response()->json($res);
        }
    }

    public function getPmsRoomSyncId($sync_id,Request $request)
    {
        $getData=IdsRoom::join('kernel.room_type_table','room_type_table.room_type_id','=','ids_room.room_type_id')
        ->join('pms_room_fetch','pms_room_fetch.pms_room_type','=','ids_room.ids_room_type_code')
        ->where('ids_room.id',$sync_id)
        ->select('ids_room.id','ids_room.hotel_id','room_type','room_type_table.room_type_id','ids_room_type_code','pms_room_fetch.pms_room_type_name')->first();
        if($getData)
        {
            $res = array('status'=>1,'message'=>'PMS Hotel Sync Successfully','data'=>$getData);
            return response()->json($res);
        }else{
            $res = array('status'=>0,'message'=>'PMS Hotel Sync Fails');
            return response()->json($res);
        }
    }
    public function fetchHotelForGems($hotel_id){
       $url = "https://gems.bookingjini.com/api/createUserFromExtranet";
       $data = array('hotel_id' => $hotel_id);
       $ch = curl_init();
       curl_setopt( $ch, CURLOPT_URL, $url);
       curl_setopt( $ch, CURLOPT_POST, true);
       curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true);
       curl_setopt( $ch, CURLOPT_POSTFIELDS, $data);
       $response = curl_exec($ch);
       curl_close($ch);
       return $response;
     }
     public function ktdcRoomSync($hotel_id){
        $pmsData = IdsHotelTracker::select('hotel_code')->where('hotel_id',$hotel_id)->first();
        $hotel_code = $pmsData->hotel_code;
        $key = 'test123';
        $url = "http://52.172.39.23:16001";
        $xml_data = '<?xml version="1.0" encoding="utf-8"?><RoomTypesDetailsRequest Key="'.$key.'" UserID="" PropertyID="'.$hotel_code.'" />';

        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml_data);
        $response = curl_exec($ch);
        curl_close($ch);
        $array_data=json_decode(json_encode(simplexml_load_string($response)),true);
        for($i=0;$i<sizeof($array_data["RoomType"]);$i++){
            $pms_room_type_code = $array_data["RoomType"][$i]['@attributes']["Id"];
            $pms_room_type_name = $array_data["RoomType"][$i]['@attributes']['Name'];

            $insertData = PmsRoomFetch::insert(['hotel_id'=>$hotel_id,'hotel_code'=>$hotel_code,
            'pms_room_type'=>trim($pms_room_type_code),'pms_room_type_name'=>$pms_room_type_name]);
        }
        if($insertData){
            $res = array('status'=>1,'message'=>'PMS Hotel Sync Successfully');
            return response()->json($res);
        }
     }
     public function addNewKtdcRoomSync(Request $request)
    {
        $data = $request->all();
        $hotel_id = $data['hotel_id'];
        $room_type_id = $data['room_type_id'];
        $ids_room_type_code = $data['pms_room_type'];

        $getHotelCode = PmsRoomFetch::select('*')->where('hotel_id',$hotel_id)->first();

        $hotel_code = $getHotelCode->hotel_code;

        $insertData = KtdcRoom::insert(['hotel_id'=>$hotel_id,'ktdc_hotel_code'=>$hotel_code,'room_type_id'=>$room_type_id,'ktdc_room_type_code'=>$ids_room_type_code]);
        if($insertData){
            $res = array('status'=>1,'message'=>'PMS Room Sync Successfully');
            return response()->json($res);
        }else{
            $res = array('status'=>0,'message'=>'PMS Room Sync Failed');
            return response()->json($res);
        }
    }

    public function updateKtdcRoomSync($sync_id, Request $request)
    {
        $data = $request->all();
        $room_type_id = $data['room_type_id'];
        $ids_room_type_code = $data['pms_room_type'];

        $updateData = KtdcRoom::where('id',$sync_id)->update(['room_type_id'=>$room_type_id,'ktdc_room_type_code'=>$ids_room_type_code]);
        if($updateData){
            $res = array('status'=>1,'message'=>'PMS Room Sync Updated Successfully');
            return response()->json($res);
        }else{
            $res = array('status'=>0,'message'=>'PMS Room Sync Update Failed');
            return response()->json($res);
        }

    }

    public function deleteKtdcRoomSync($sync_id, Request $request)
    {
        $deleteData = KtdcRoom::where('id',$sync_id)->delete();
        if($deleteData){
            $res = array('status'=>1,'message'=>'PMS Room Sync Deleted Successfully');
            return response()->json($res);
        }else{
            $res = array('status'=>0,'message'=>'PMS Room Sync Delete Failed');
            return response()->json($res);
        }

    }

    public function getKtdcRoomSync($hotel_id,Request $request)
    {
        $getData=KtdcRoom::join('kernel.room_type_table','room_type_table.room_type_id','=','ktdc_room.room_type_id')
        ->join('pms_room_fetch','pms_room_fetch.pms_room_type','=','ktdc_room.ktdc_room_type_code')
        ->select('ktdc_room.id','ktdc_room.hotel_id','room_type','room_type_table.room_type_id','ktdc_room_type_code','pms_room_fetch.pms_room_type_name')
        ->where('ktdc_room.hotel_id',$hotel_id)
        ->distinct('ktdc_room.id')
        ->get();
        if(sizeof($getData)>0)
        {
            $res = array('status'=>1,'message'=>'PMS room type retrieve successfully','data'=>$getData);
            return response()->json($res);
        }else{
            $res = array('status'=>0,'message'=>'PMS room type retrieve fails');
            return response()->json($res);
        }
    }

    public function getKtdcRoomType($hotel_id,Request $request)
    {
        $getData = PmsRoomFetch::select('*')->where('hotel_id',$hotel_id)->get();
        if(sizeof($getData)>0){
            $res = array('status'=>1,'message'=>'PMS Room Types Retrieved Successfully','data'=>$getData);
            return response()->json($res);
        }
        else{
            $res = array('status'=>0,'message'=>'PMS Room Types Retrieved Failed');
            return response()->json($res);
        }
    }

    public function getKtdcRoomSyncId($sync_id,Request $request)
    {
        $getData=KtdcRoom::join('kernel.room_type_table','room_type_table.room_type_id','=','ktdc_room.room_type_id')
        ->join('pms_room_fetch','pms_room_fetch.pms_room_type','=','ktdc_room.ktdc_room_type_code')
        ->where('ktdc_room.id',$sync_id)
        ->select('ktdc_room.id','ktdc_room.hotel_id','room_type','room_type_table.room_type_id','ktdc_room_type_code','pms_room_fetch.pms_room_type_name')->first();
        if($getData)
        {
            $res = array('status'=>1,'message'=>'PMS Hotel Sync Successfully','data'=>$getData);
            return response()->json($res);
        }else{
            $res = array('status'=>0,'message'=>'PMS Hotel Sync Fails');
            return response()->json($res);
        }
    }
     //check if the hotel takes IDS
    public function checkIdsHotel(Request $request){
        $hotel_id = $request->getContent();
        $getData = PmsAccount::select('*')->where('hotels','like','%'.$hotel_id.'%')->get();
        if(sizeof($getData)>0){
            foreach($getData as $key => $data){
                $pms[] = $data->name;
            }
            return response()->json($pms);
        }else{
            return 0;
        }
    }
}
