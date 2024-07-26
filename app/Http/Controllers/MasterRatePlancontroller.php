<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\UserCredential;
use App\MasterRatePlan;//class name from model
use DB;
use App\MasterHotelRatePlan;
use Webpatser\Uuid\Uuid;
//create a new class MasterRatePlancontroller
class MasterRatePlancontroller extends Controller
{ 
  //validation rules
    private $rules = array(
        'plan_name' => 'required',
        'hotel_id'=>'required'
    );
    //Custom Error Messages
    private $messages = [
        'plan_name.required' => 'The plan name field is required.',
        'hotel_id.required' => 'Hotel id is required',
            ];
    /**
     * Hotel Rate Plan Names
     * Create a new record of Rate Plan Names.
     * @author subhradip
     * @return Hotel Rate Plan Names saving status
     * function addnew for createing a new Rate Plan Names
    **/

    public function addNew(Request $request)
    {   
        $master_rateplan = new MasterRatePlan();
        $failure_message='Rate Plan Names Details Saving Failed';
        $validator = Validator::make($request->all(),$this->rules,$this->messages);
        if ($validator->fails())
        {
            return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
        }
        $data=$request->all();
        //TO get user id from AUTH token
        if(isset($request->auth->admin_id)){
            $data['user_id']=$request->auth->admin_id;
        }else if(isset($request->auth->super_admin_id)){
            $data['user_id']=$request->auth->super_admin_id;
        }
        else if(isset($request->auth->id)){
            $data['user_id']=$request->auth->id;
        }
        $data['plan_type']= $data['plan_type'];
         //CheckMasterRatePlanStatus function from model for checking duplicasy
        if($master_rateplan->CheckMasterRatePlanStatus($data['plan_type'],$data['hotel_id'])=="new")
        { 
           if($master_rateplan->fill($data)->save())
           {
            $res=array('status'=>1,'message'=>"Hotel Rate Plan Names saved successfully",'res'=>$data);
            return response()->json($res);
           }
           else
           {
               $res=array('status'=>-1,"message"=>$failure_message);
               $res['errors'][] = "Internal server error";
               return response()->json($res);
           }
        }
        else
        {
            $res=array('status'=>0,"message"=>"This Hotel Rate Plan Already registered");
               return response()->json($res);
        }
    }
    /**
     * Delete Hotel Rate Plan
     * delete record of Rate Plan
     * @author subhradip
     * @return Hotel Rate Plan deleting status
     * function DeleteMasteReatePlan used for delete
    **/
    public function DeleteMasteReatePlan(int $rate_plan_id ,Request $request)
    {  
        $failure_message='Deleted Failure';
        MasterHotelRatePlan::where('rate_plan_id',$rate_plan_id)->delete();
        if(MasterRatePlan::where('rate_plan_id',$rate_plan_id)->delete())
        {
            $res=array('status'=>1,"message"=>'Hotel Rate Plan deleted successfully');         
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
     * Hotel Rate Plan
     * Update record of Hotel Rate Plan
     * @author subhradip
     * @return Hotel Rate Plan  saving status
     * function UpdateMasterRatePlan use for update
    **/
    public function UpdateMasterRatePlan(int $rate_plan_id ,Request $request)
    {
        $failure_message="Hotel's Rate Plan  saving failed.";
        
            $validator = Validator::make($request->all(),$this->rules,$this->messages);
            if ($validator->fails())
            {
            return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
            }
        $data=$request->all();
        $master_rate_plan = MasterRatePlan::where('rate_plan_id',$rate_plan_id)->first();
        if($master_rate_plan->rate_plan_id == $rate_plan_id )
        {
             
                if($master_rate_plan->fill($data)->save())
                {
                    $res=array('status'=>1,'message'=>"Hotel Rate Plan updated saved successfully",'res'=>$data);
                    return response()->json($res);
                }
                else
                {
                    $res=array('status'=>-1,"message"=>$failure_message);
                    $res['errors'][] = "Internal server error";
                    return response()->json($res);
                }
        }
    }
    /**
     * Get Hotel Rate Plan 
     * get one record of Hotel Rate Plan
     * @author subhradip
     * function GetHotelRatePlan for delecting data
    **/
    public function GetHotelRatePlan(int $rate_plan_id ,Request $request)
    { 
        if($rate_plan_id)
        { 
            $conditions=array('rate_plan_id'=>$rate_plan_id,'is_trash'=>0);
            $res=MasterRatePlan::where($conditions)->first();       
            return (sizeof($res)>0)
                    ?
                        response()->json(array('status'=>1,'message'=>"Rate paln details found",'data'=>$res))
                    :   
                        response()->json(array('status'=>0,"message"=>"No hotel Rate Plan records found")); 
        }
        else
        {
            $res=array('status'=>-1,"message"=>"hotel Rate Plan  fetching failed");
            $res['errors'][] = "UUID is provided";
            return response()->json($res); 
        }       
    }
    /**
     * Get all Hotel Rate Plan 
     * get All record of Hotel Rate Plan
     * @author subhradip
     * function GetAllHotelRatePlan for selecting all data
    **/
    public function GetAllHotelRatePlan(int $hotel_id ,Request $request)
    {
        $conditions=array('hotel_id'=>$hotel_id,'is_trash'=>0);
        $res=MasterRatePlan::where($conditions)->get();
        return (sizeof($res)>0)
                ?
                    response()->json(array('status'=>1,'message'=>"records found",'data'=>$res))
                :
                    response()->json(array('status'=>0,"message"=>"No hotel Rate Plan records found"));
    }
     /**
     * Get  room type
     * get all records of  rate plans 
     * @author subhradip
     * function GetRatePlans for fetching all rate plan data
    **/

    public function GetRatePlans(int $hotel_id ,Request $request)
    { 
        $conditions=array('hotel_id'=>$hotel_id,'is_trash'=>0);
        $res=MasterRatePlan::select('plan_type','plan_name','rate_plan_id')->where($conditions)->get();
        return (sizeof($res)>0)
            ?
                response()->json(array('status'=>1,'message'=>"records found",'data'=>$res))
            :
                response()->json(array('status'=>0,"message"=>"No hotel Rate Plan records found"));       
    } 
    
    /**
     * Get  room type name
     * get one record of  rate plan type
     * @author subhradip
     * function GetRateplan for fetching rate plan data
    **/
    public function GetRateplan(int $rate_plan_id ,Request $request)
    { 
        $conditions=array('rate_plan_id'=>$rate_plan_id);
        $res=MasterRatePlan::select('plan_type','plan_name','rate_plan_id')
                             ->where($conditions)->first();
        return (sizeof($res)>0)
            ?
                response()->json(array('status'=>1,'message'=>"records found",'data'=>$res))
            :
                response()->json(array('status'=>0,"message"=>"No rate plans records found"));
    }
}