<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\UserCredential;
use App\CmOtaDetails;
use App\CmOtaDetailsRead;
use App\CmOtaRoomTypeFetchSync;//class name from model
use App\CmOtaRatePlanFetchSync;//class name from model
use App\CmOtaRoomTypeFetch;
Use App\CmOtaRateTypeFetch;
use App\CmOtaRoomTypeFetchSyncRead;//class name from model
use App\CmOtaRatePlanFetchSyncRead;//class name from model
use App\CmOtaRoomTypeFetchRead;
Use App\CmOtaRateTypeFetchRead;

use DB;
use Webpatser\Uuid\Uuid;
use Ixudra\Curl\Facades\Curl;
use App\Http\Controllers\AirbnbListingUpdateController;
//create a new class CmOtaSyncController
class CmOtaSyncController extends Controller
{
    protected $airbnblistingupdate;
    public function __construct(AirbnbListingUpdateController $airbnblistingupdate )
    {
       $this->airbnblistingupdate=$airbnblistingupdate;
    }
    //validation rules
    private $rules = array(
        'room_type_id' => 'required',
        'ota_type_id' => 'required',
        'ota_room_type' => 'required',
        //'ota_room_type_name' => 'required'
    );
    //Custom Error Messages
    private $messages = [
        'room_type_id.required' => 'Room type id  required.',
        'ota_type_id.required' => 'The ota  is required.',
        'ota_room_type.required' => 'The ota room type id is required.',
        //'ota_room_type_name.required' => 'The ota room type name is required.',
            ];
    /**
     * CM ota  room type sync.
     * Create a new record of CM otaroom type syncs.
     * @author subhradip
     * @return CM ota room type sync saving status
     * function addnew for createing a new CM ota room type sync
    **/
    public function addNewCmOtaSync(Request $request)
    {
        $cmotaroomtypefetchsync = new CmOtaRoomTypeFetchSync();
        $failure_message='Cm ota room type sync Saving Failed';
        $validator = Validator::make($request->all(),$this->rules,$this->messages);
        if ($validator->fails()){
            return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
        }
        
        $data=$request->all();
        $get_details = CmOtaRoomTypeFetch::select('beds','basictype')->where('hotel_id',$data['hotel_id'])->where('ota_room_type_id',$data['ota_room_type'])->where('ota_id',$data['ota_type_id'])->first();
       
        $deleteSyncData = CmOtaRoomTypeFetchSync::where('hotel_id',$data['hotel_id'])->where('room_type_id',$data['room_type_id'])->where('ota_type_id',$data['ota_type_id'])->delete();
        $data['beds'] = $get_details->beds;
        $data['basictype'] = $get_details->basictype;

        try {
            if($cmotaroomtypefetchsync->fill($data)->save()){
                    try{
                        $airbnbDetails = new CmOtaDetails();
                        $ota=$airbnbDetails->where('ota_id',$data['ota_type_id'])->where('ota_name','Airbnb')
                        ->where('is_active',1)->first();
                        if($ota){
                            $update_airbnb = $this->airbnblistingupdate->updateSyncStatus($data['hotel_id'],$data['ota_room_type']);
                        }
                    }
                    catch(Exception $e){
    
                    }
                $res=array('status'=>1,"message"=>"Room type sync successfull");
                return response()->json($res);
            }
            else{
                $res=array('status'=>-1,"message"=>$failure_message);
                $res['errors'][] = "Internal server error";
                return response()->json($res);
            }
          }
          catch(\Illuminate\Database\QueryException $ex){
            $res=array('status'=>-1,"message"=>"Room type already in sync with OTA room type");
            return response()->json($res);
            // Note any method of class PDOException can be called on $ex.
          }
    }
/**
     * CM ota room type sync.
     * Update record of CM ota  room type sync.
     * @auther subhradip
     * @return CM ota  room type sync saving status
     * function updateCmOtaSync use for update
    **/
    public function updateCmOtaSync(int $id ,Request $request)
    {
        $cm_otaroomtypefetchsync = new CmOtaRoomTypeFetchSync();
        $cm_otaroomtypefetch=new CmOtaRoomTypeFetch();
        $failure_message="CM ota  room type sync  saving failed.";
        $validator = Validator::make($request->all(),$this->rules,$this->messages);
        if ($validator->fails())
        {
        return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
        }
        $data=$request->all();
        $ota_room_type_name=$cm_otaroomtypefetch->OtaRoomType($data['ota_room_type']);
        $cmotaroomtypefetchsync = CmOtaRoomTypeFetchSync::where('id',$id)->first();
        $data['ota_room_type_name']=$ota_room_type_name;
        if($cmotaroomtypefetchsync->id == $id )
        {

            try {
                $get_details = CmOtaRoomTypeFetch::select('beds','basictype')->where('hotel_id',$data['hotel_id'])->where('ota_room_type_id',$data['ota_room_type'])->where('ota_id',$data['ota_type_id'])->first();
                $data['beds'] = $get_details->beds;
                $data['basictype'] = $get_details->basictype;
                if($cmotaroomtypefetchsync->fill($data)->save())
                {
                    try{
                        $airbnbDetails = new CmOtaDetails();
                        $ota=$airbnbDetails->where('ota_id',$cmotaroomtypefetchsync->ota_type_id)->where('ota_name','Airbnb')
                        ->where('is_active',1)->first();
                        if($ota){
                            $update_airbnb = $this->airbnblistingupdate->updateSyncStatus($cmotaroomtypefetchsync->hotel_id,$cmotaroomtypefetchsync->ota_room_type);
                        }
                    }
                    catch(Exception $e){
    
                    }
                    $res=array('status'=>1,"message"=>"Room type sync successfull");
                    return response()->json($res);
                }
                else
                {
                    $res=array('status'=>-1,"message"=>$failure_message);
                    $res['errors'][] = "Internal server error";
                    return response()->json($res);
                }
              }
              catch(\Illuminate\Database\QueryException $ex)
              {
                //dd($ex->getMessage());
                $res=array('status'=>-1,"message"=>"Room type already in sync with OTA room type");
                return response()->json($res);
                // Note any method of class PDOException can be called on $ex.
              }
        }
    }
    /**
     * Delete CM ota room type sync.
     * delete record of CM ota room type sync.
     * @auther subhradip
     * @return CM ota room type sync deleting status
     * function deletemasterroomtype used for delete
    **/
    public function deleteCmOtaSync(int $id ,Request $request)
    {
        $failure_message='Ota room type sync record deletion failed';
        $cmotaroomtypefetchsync = CmOtaRoomTypeFetchSync::where('id',$id)->first();
        if($cmotaroomtypefetchsync){
            if(CmOtaRoomTypeFetchSync::where('id',$id)->delete())
            {
                try{
                    $airbnbDetails = new CmOtaDetails();
                    $ota=$airbnbDetails->where('ota_id',$cmotaroomtypefetchsync->ota_type_id)->where('ota_name','Airbnb')
                    ->where('is_active',1)->first();
                    if($ota){
                        $update_airbnb = $this->airbnblistingupdate->removeSyncStatus($cmotaroomtypefetchsync->hotel_id,$cmotaroomtypefetchsync->ota_room_type);
                    }
                }
                catch(Exception $e){
    
                }
                $res=array('status'=>1,"message"=>'Cm ota room type sync deleted successfully');
                return response()->json($res);
            }
            else
            {
                $res=array('status'=>-1,"message"=>$failure_message);
                $res['errors'][] = "Internal server error";
                return response()->json($res);
            }
        }
        else{
            $res=array('status'=>-1,"message"=>"Already Deleted! Please refresh the page");
                $res['errors'][] = "Internal server error";
                return response()->json($res);
        }
    }
    //validation rules
    private $rules1 = array(
        'hotel_room_type_id' => 'required',
        'ota_type_id' => 'required',
        'ota_room_type_id' => 'required',
        'hotel_rate_plan_id' => 'required',
    );
    //Custom Error Messages
    private $messages1 = [
        'hotel_room_type_id.required' => 'The hotel room type id is required.',
        'ota_type_id.required' => 'The ota type id is required.',
        'ota_room_type_id.required' => 'The ota room type id is required.',
        'hotel_rate_plan_id.required' => 'The hotel rate plan id is required.',
            ];
    /**
     * CM ota  rate plan sync.
     * Create a new record of CM ota rate plan sync.
     * @author subhradip
     * @return CM ota rate plan sync saving status
     * function addNewCmOtaRatePlanSync for createing a new CM ota rate plane sync
    **/
    public function addNewCmOtaRatePlanSync(Request $request)
    {
        $cmotarateplanfetchsync = new CmOtaRatePlanFetchSync();
        $failure_message='Cm ota rate planpe sync Saving Failed';
        $validator = Validator::make($request->all(),$this->rules1,$this->messages1);
        if ($validator->fails())
        {
            return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
        }
        $data=$request->all();
        $deleteSyncData = CmOtaRatePlanFetchSync::where('hotel_id',$data['hotel_id'])->where('hotel_room_type_id',$data['hotel_room_type_id'])->where('ota_type_id',$data['ota_type_id'])->where('hotel_rate_plan_id',$data['hotel_rate_plan_id'])->delete();
        try {
            if($cmotarateplanfetchsync->fill($data)->save())
            {
                $res=array('status'=>1,"message"=>"Cm ota rate plan sync saved successfully");
                return response()->json($res);
            }
            else
            {
                $res=array('status'=>-1,"message"=>$failure_message);
                $res['errors'][] = "Internal server error";
                return response()->json($res);
            }
          }
          catch(\Illuminate\Database\QueryException $ex)
          {
            $res=array('status'=>-1,"message"=>"Room Rate plan already in sync with OTA");
            return response()->json($res);
          }
    }
/**
     * CM ota rate plan sync.
     * Update record of CM ota  rate plan sync..
     * @auther subhradip
     * @return CM ota  rate plan sync saving status
     * function updateCmOtaRatePlanSync use for update
    **/
    public function updateCmOtaRatePlanSync(int $id ,Request $request)
    {
        $cm_otarateplanfetchsync = new CmOtaRatePlanFetchSync();
        $cmOtaRateTypeFetch =new CmOtaRateTypeFetch();
        $failure_message="CM ota  rate plan sync  saving failed.";
        $validator = Validator::make($request->all(),$this->rules1,$this->messages1);
        if ($validator->fails())
        {
        return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
        }
        $data=$request->all();
        $data['ota_rate_plan_name']=$cmOtaRateTypeFetch->OtaRatePlan($data['ota_rate_plan_id']);
        $cm_otarateplanfetchsync = CmOtaRatePlanFetchSync::where('id',$id)->first();
        if($cm_otarateplanfetchsync->id == $id )
        {
            try {
                if($cm_otarateplanfetchsync->fill($data)->save())
                {
                    $res=array('status'=>1,"message"=>"Cm ota rate plan sync saved successfully");
                    return response()->json($res);
                }
                else
                {
                    $res=array('status'=>-1,"message"=>$failure_message);
                    $res['errors'][] = "Internal server error";
                    return response()->json($res);
                }
              }
              catch(\Illuminate\Database\QueryException $ex)
              {
                //dd($ex->getMessage());
                $res=array('status'=>-1,"message"=>"Room Rate plan already in sync with OTA");
                return response()->json($res);
              }
        }
    }
    /**
     * Delete CM ota rate plan sync.
     * delete record of CM ota rate plan sync.
     * @auther subhradip
     * @return CM ota rate plan sync deleting status
     * function deleteCmOtaRatePlanSync used for delete
    **/
    public function deleteCmOtaRatePlanSync(int $id ,Request $request)
    {
        $failure_message='Deleted Filure';
        if(CmOtaRatePlanFetchSync::where('id',$id)->delete())
        {

            $res=array('status'=>1,"message"=>'Cm ota room type sync deleted successfully');
            return response()->json($res);
        }
        else
        {
            $res=array('status'=>-1,"message"=>$failure_message);
            $res['errors'][] = "Internal server error";
            return response()->json($res);
        }
    }
///////////////////////// Fetch OTA Room Types By OTA ID////////////////////////////
    public function getAllSyncRoomsData(int $hotel_id,Request $request)
    {
        $sync_data=array();
        $conditions=array('cm_ota_details.hotel_id'=>$hotel_id,'is_active'=>1);
        $res=CmOtaDetailsRead::
        join('cm_ota_credential_parameter','cm_ota_credential_parameter.ota_name','cm_ota_details.ota_name')
        ->join('kernel.image_table','cm_ota_credential_parameter.ota_logo','image_table.image_id')
        ->where($conditions)
        ->select('ota_id','is_active','cm_ota_details.hotel_id as hotel_id','ota_hotel_code','cm_ota_details.ota_name as  ota_name','image_table.image_name')->get();
        
        if(sizeof($res)>0)
        {
            //$res['sync_data']=array();
            foreach($res as $ota_data)
            {
                if($ota_data->is_active==1)
                {
                    $ota_data['sync_data']=$this->fetchOtaSyncRoomTypes($hotel_id,$ota_data['ota_id']);
                    $ota_data['ota_room_types']=$this->fetchOtaRoomTypes($hotel_id,$ota_data['ota_id']);
                }
                else
                {
                    $ota_data['sync_data']=array();
                    $ota_data['ota_room_types']=array();
                }
            }

            $resp=array('status'=>1,"message"=>"Ota details records found","data"=>$res);
            return response()->json($resp);
        }
        else
        {
            $res=array('status'=>0,"message"=>"No cm ota details records found");
            return response()->json($res);
        }
    }
    /**
     * Fetch OTA  Room Types
     * @auther Godti Vinod
     * @return CM Fetch OTA room types
     * function fetchOtaRoomTypes used to fetch
    **/
    public function fetchOtaRoomTypes(int $hotel_id,int $ota_id )
    {
        $failure_message='Ota Room Type fetching failed';

        $data=CmOtaRoomTypeFetchRead::where('hotel_id',$hotel_id)->where('ota_id',$ota_id)->where('active','!=',0)->select('ota_room_type_name','ota_room_type_id')->where('created_at',DB::raw('(SELECT MAX(created_at) FROM cm_ota_room_type_fetch where ota_id='.$ota_id.' AND hotel_id='.$hotel_id.' AND active!=0)'))->get();

        if($data)
        {
            return $data;
        }
        else
        {
            return array();
        }
    }

    public function otaRoomTypes(int $hotel_id,int $ota_id,Request $request)
    {
        $failure_message='Ota Room Type fetching failed';

        $data=$this->fetchOtaRoomTypes($hotel_id,$ota_id);
        if($data)
        {
            $res=array('status'=>1,"message"=>'Cm ota room types retrieved successfully','data'=>$data);
            return response()->json($res);
        }
        else
        {
            $res=array('status'=>-1,"message"=>$failure_message);
            $res['errors'][] = "Internal server error";
            return response()->json($res);
        }
    }
     /**
     * Fetch OTA  Room Types
     * @auther Godti Vinod
     * @return CM Fetch OTA room types
     * function fetchOtaRoomTypes used to fetch
    **/
    public function fetchOtaSyncRoomTypes(int $hotel_id,int $ota_id)
    { 
        $failure_message='Ota Room Type fetching failed';
        $data=CmOtaRoomTypeFetchSync::join('kernel.room_type_table','room_type_table.room_type_id','=','cm_ota_room_type_synchronize.room_type_id')
        ->where('cm_ota_room_type_synchronize.hotel_id',$hotel_id)
        ->where('ota_type_id',$ota_id)
        ->select('id','ota_room_type_name','ota_room_type','room_type','room_type_table.room_type_id')
        ->get();
        if($data)
        {
            return $data;  
        }
        else{
            return array();
        }
    }
    public function fetchOtaSyncById(int $sync_id ,Request $request)
    {
        $failure_message='Ota Room Type fetching failed';
        $data=CmOtaRoomTypeFetchSyncRead::join('kernel.room_type_table','room_type_table.room_type_id','=','cm_ota_room_type_synchronize.room_type_id')->where('cm_ota_room_type_synchronize.id',$sync_id)->select('id','cm_ota_room_type_synchronize.room_type_id as room_type_id','ota_room_type','ota_room_type_name','room_type')->first();

        if($data)
        {
            $res=array('status'=>1,"message"=>'Cm ota sync room types retrieved successfully','data'=>$data);
            return response()->json($res);
        }
        else
        {
            $res=array('status'=>-1,"message"=>$failure_message);
            $res['errors'][] = "Internal server error";
            return response()->json($res);
        }
    }
    public function fetchOtaRoomType(int $hotel_id,int $ota_id,int $room_type_id,Request $request)
    {
        $failure_message='Ota Room Type fetching failed';

         $data=CmOtaRoomTypeFetchSyncRead::where('hotel_id',$hotel_id)->where('ota_type_id',$ota_id)->where('room_type_id',$room_type_id)->select('ota_room_type_name','ota_room_type')->first();
         if($data)
         {
             $res=array('status'=>1,"message"=>'Cm ota room types retrieved successfully','data'=>$data);
             return response()->json($res);
         }
         else
         {
             $res=array('status'=>-1,"message"=>$failure_message);
             $res['errors'][] = "Internal server error";
             return response()->json($res);
         }
    }
///////////////////////// Fetch OTA Room Rate plan By OTA ID////////////////////////////
public function getAllSyncRoomRateData(int $hotel_id,Request $request)
{
    $sync_data=array();
    $conditions=array('cm_ota_details.hotel_id'=>$hotel_id);
    $res=CmOtaDetailsRead::
    join('cm_ota_credential_parameter','cm_ota_credential_parameter.ota_name','cm_ota_details.ota_name')
    ->join('kernel.image_table','cm_ota_credential_parameter.ota_logo','image_table.image_id')
    ->where($conditions)
    ->select('ota_id','is_active','cm_ota_details.hotel_id','ota_hotel_code','cm_ota_details.ota_name as  ota_name','image_table.image_name')->get();

    if(sizeof($res)>0)
    {
        //$res['sync_data']=array();
        foreach($res as $ota_data)
        {
            if($ota_data->is_active==1)
            {
            $ota_data['sync_data']=$this->fetchOtaSyncRoomRatePlan($hotel_id,$ota_data['ota_id']);
            }
            else
            {
                $ota_data['sync_data']=array();
            }
        }
        $res=array('status'=>1,"message"=>"Ota details records found","data"=>$res);
        return response()->json($res);
    }
    else
    {
        $res=array('status'=>0,"message"=>"No cm ota details records found");
        return response()->json($res);
    }
}
    /**
     * Fetch OTA  Room Types
     * @auther Godti Vinod
     * @return CM Fetch OTA room types
     * function fetchOtaRoomTypes used to fetch
    **/
    public function fetchOtaRoomRatePlan(int $hotel_id,int $ota_id,string $ota_room_type_id,Request $request)
    {
      $failure_message='Ota room rate plan fetching failed';
      if($this->getOtaName($ota_id)=='Goomo'){
          $data=CmOtaRateTypeFetch::where('hotel_id',$hotel_id)->where('ota_id',$ota_id)->where('active','!=',0)->select('ota_rate_type_name','ota_rate_type_id')->where('created_at',DB::raw('(SELECT MAX(created_at) FROM cm_ota_rate_type_fetch where ota_id='.$ota_id.' AND hotel_id='.$hotel_id.' AND active!=0)'))->get();

      }else{
          $created_at=DB::select("SELECT MAX(created_at) as created_at FROM cm_ota_rate_type_fetch where ota_id=$ota_id AND hotel_id=$hotel_id AND active!=0 AND ota_room_type_id='$ota_room_type_id'");
          $data=CmOtaRateTypeFetch::where('ota_room_type_id',($ota_room_type_id))
          ->where('hotel_id',$hotel_id)->where('ota_id',$ota_id)->where('active','!=',0)
          ->select('ota_rate_type_name','ota_rate_type_id')
          ->where('created_at',$created_at[0]->created_at)->get();
      }
      if($data)
      {

          $res=array('status'=>1,"message"=>'Cm ota room rate plan fetched successfully','data'=>$data);
          return response()->json($res);
      }
      else
      {
          $res=array('status'=>-1,"message"=>$failure_message);
          $res['errors'][] = "Internal server error";
          return response()->json($res);
      }
    }
    //Get Ota Name from Ota id
    public function getOtaName($ota_id){
        $cm_ota_details=CmOtaDetailsRead::where('ota_id',$ota_id)->first();
        if($cm_ota_details){
            return $cm_ota_details->ota_name;
        }
    }
    public function fetchOtaSyncRoomRatePlan(int $hotel_id,int $ota_id)
    {
        $failure_message='Ota room rate plan fetching failed';
        $data=CmOtaRatePlanFetchSync::
        join('cm_ota_room_type_synchronize','cm_ota_rate_plan_synchronize.ota_room_type_id','=','cm_ota_room_type_synchronize.ota_room_type')
        ->join('kernel.room_type_table','cm_ota_rate_plan_synchronize.hotel_room_type_id','=','room_type_table.room_type_id')
        ->join('kernel.rate_plan_table','cm_ota_rate_plan_synchronize.hotel_rate_plan_id','=','rate_plan_table.rate_plan_id')
        ->where('cm_ota_rate_plan_synchronize.hotel_id',$hotel_id)
        ->where('cm_ota_room_type_synchronize.ota_type_id',$ota_id)
        ->where('room_type_table.is_trash',0)
        ->where('rate_plan_table.is_trash',0)
        ->where('cm_ota_rate_plan_synchronize.ota_type_id',$ota_id)
        ->select('cm_ota_rate_plan_synchronize.id as id','ota_room_type_name','room_type','plan_name','ota_rate_plan_name','cm_ota_rate_plan_synchronize.ota_room_type_id','cm_ota_rate_plan_synchronize.hotel_room_type_id','cm_ota_rate_plan_synchronize.created_at')
        ->distinct('cm_ota_rate_plan_synchronize.id')
        ->orderBy('cm_ota_rate_plan_synchronize.created_at','DESC')
        ->get();
        if($data)
        {
            return $data;  
        }
        else
        {
            return array();
        } 
    }

    public function fetchOtaRatePlanSyncById(int $sync_id ,Request $request)
    {
        $failure_message='Ota Room Type fetching failed';
        $data=CmOtaRatePlanFetchSyncRead::join('kernel.room_type_table','room_type_table.room_type_id','=','cm_ota_rate_plan_synchronize.hotel_room_type_id')
        ->join('kernel.rate_plan_table','rate_plan_table.rate_plan_id','=','cm_ota_rate_plan_synchronize.hotel_rate_plan_id')
        ->join('cm_ota_room_type_synchronize','cm_ota_room_type_synchronize.ota_room_type','=','cm_ota_rate_plan_synchronize.ota_room_type_id')
        ->where('cm_ota_rate_plan_synchronize.id',$sync_id)
        ->select('cm_ota_rate_plan_synchronize.id as id','ota_room_type_name','ota_room_type','cm_ota_rate_plan_synchronize.hotel_room_type_id as room_type_id','room_type',
        'cm_ota_rate_plan_synchronize.hotel_rate_plan_id as hotel_rate_plan_id','plan_name','ota_rate_plan_id','ota_rate_plan_name')->first();
       if($data)
        {

            $res=array('status'=>1,"message"=>'Cm ota sync room types retrieved successfully','data'=>$data);
            return response()->json($res);
        }
        else
        {
            $res=array('status'=>-1,"message"=>$failure_message);
            $res['errors'][] = "Data not found";
            return response()->json($res);
        }
    }


}
