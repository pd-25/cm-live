<?php
namespace App\Http\Controllers;
use Exception;
use Validator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PromotionApiController;
use DB;

use App\HotelPromotion;
use App\OtaPromotions;
/**
 * This controller used to add,update tables of promotions
 * @author Mohommed Hafiz
 */
class HotelPromotionController extends Controller{
    protected $promotion_ota_api_call;
    public function __construct(PromotionApiController $promotion_ota_api_call)
    {
       $this->promotion_ota_api_call        = $promotion_ota_api_call;
    }
    private $promotion_rules=array(
        'hotel_id'=>'required|numeric'
    );
    private $promotion_messages=[
     'hotel_id=>required'=>'hotel_id is required'
    ];

    public function getAllPromotion(int $hotel_id,Request $request){
        $getAllPromotion= HotelPromotion::select('id','stay_start_date','stay_end_date','booking_start_date','booking_end_date','selected_room_rateplan','promotion_name','discount')->where('hotel_id',$hotel_id)
        ->where('is_trash',0)
        ->orderBy('id','DESC')
        ->get();
        foreach($getAllPromotion  as $promo) {
            $promo->selected_room_rateplan=json_decode($promo->selected_room_rateplan);
        }
        $res = array('status'=>1,'message'=>'Retrieved successfully',"all_promotion"=>$getAllPromotion);
        return response()->json($res);
    }

    public function getHotelPromotion(int $hotel_id,int $id,Request $request){
        $getPromotion= HotelPromotion::select('offer_type','discount','applicable_for','stay_start_date','stay_end_date','booking_start_date','booking_end_date','blackout_option','blackout_dates','applicable_for_room_rateplan','selected_room_rateplan','promotion_name')
        ->where('hotel_id',$hotel_id)
        ->where('id',$id)
        ->where('is_trash',0)
        ->orderBy('id','DESC')
        ->first();
        if($getPromotion) {
            $get_ota_details = OtaPromotions::select('*')->where('promotion_id',$id)->where('is_trash',0)->get();
            $ota_ids = array();
            foreach($get_ota_details as $key => $ota_info){
                $ota_ids[$key]['ota_id']=$ota_info->ota_id;
                $ota_ids[$key]['ota_name']=$ota_info->ota_name;
            }
            $getPromotion->ota = $ota_ids;
            if($getPromotion->blackout_dates){
                $getPromotion->blackout_dates=explode(',',$getPromotion->blackout_dates);
            }
            else{
                $getPromotion->blackout_dates=[];
            }
            $getPromotion->selected_room_rateplan=json_decode($getPromotion->selected_room_rateplan);
         }

           $res = array('status'=>1,'message'=>'Retrieved successfully',"promotion"=>$getPromotion);
           return response()->json($res);
    }
    
    public function insertHotelPromotion(Request $request){
        $failure_message='Field is required';
        $validator = Validator::make($request->all(),$this->promotion_rules,$this->promotion_messages);
        if ($validator->fails())
        {
            return response()->json(array(
                'status'=>0,
                'message'=>$failure_message,
                'errors'=>$validator->errors()
            ));
        }
        $hotel_promotion = new HotelPromotion();
        $data=$request->all();
        $get_ota_code = $data['ota'];
        $data['ota'] = implode(",",$data['ota']);
        if(sizeof($data['blackout_dates']) > 0){
            $data['blackout_dates'] = implode(",",$data['blackout_dates']);
        }else{
            $data['blackout_dates']='';
        }
        $data['selected_room_rateplan']= json_encode($request->input('selected_room_rateplan'));        
        if($hotel_promotion->fill($data)->save()){
            $promotional_data = $data;
            $promotion_id = $hotel_promotion->id;
            $call_to_ota_update = $this->promotion_ota_api_call->createPromotion($get_ota_code,$promotional_data,$promotion_id);
            $res = array('status'=>1,'message'=>'Inserted Successfully');
            return response()->json($res);
        }
        else{
            $res = array('status'=>0,'message'=>'insertion Failed');
            return response()->json($res);
        }
    }

    public function updateHotelPromotion(Request $request){
        $failure_message='Field is required';
        $validator = Validator::make($request->all(),$this->promotion_rules,$this->promotion_messages);
        if ($validator->fails())
        {
            return response()->json(array(
                'status'=>0,
                'message'=>$failure_message,
                'errors'=>$validator->errors()
            ));
        }
        $data=$request->all();
        $get_ota_code = $data['ota'];
        if(sizeof($data['blackout_dates']) > 0){
            $data['blackout_dates'] = implode(",",$data['blackout_dates']);
        }else{
            $data['blackout_dates']='';
        }
        $data['selected_room_rateplan']= json_encode($request->input('selected_room_rateplan'));

        $updatePromotion=HotelPromotion::where('hotel_id', $data['hotel_id'])->where('id', $data['id'])->update(['offer_type'=>$data['offer_type'],'discount'=>$data['discount'],'applicable_for'=>$data['applicable_for'],'stay_start_date'=>$data['stay_start_date'],'stay_end_date'=>$data['stay_end_date'],'booking_start_date'=>$data['booking_start_date'],'booking_end_date'=>$data['booking_end_date'],'blackout_option'=>$data['blackout_option'],'blackout_dates'=>$data['blackout_dates'],'applicable_for_room_rateplan'=>$data['applicable_for_room_rateplan'],'selected_room_rateplan'=>$data['selected_room_rateplan'],'promotion_name'=>$data['promotion_name']]);
        
        if($updatePromotion){
            $promotional_data = $data;
            $promotion_id = $data['id'];
            $promotional_data['is_trash'] = 0;
            $call_to_ota_update = $this->promotion_ota_api_call->updatePromotion($get_ota_code,$promotional_data,$promotion_id);
            dd($call_to_ota_update);
            $res = array('status'=>1,'message'=>'Updated Successfully');
            return response()->json($res);
        }
        else{
            $res = array('status'=>0,'message'=>'Updated Failed');
            return response()->json($res);
        }
    }

    public function deactivateHotelPromotion(Request $request){
        $failure_message='Field is required';
        $validator = Validator::make($request->all(),$this->promotion_rules,$this->promotion_messages);
        if ($validator->fails())
        {
            return response()->json(array(
                'status'=>0,
                'message'=>$failure_message,
                'errors'=>$validator->errors()
            ));
        }

        $data=$request->all();
        $deactivatePromotion=HotelPromotion::where('hotel_id', $data['hotel_id'])->where('id', $data['id'])->update(['is_trash'=>1]);
        $p_data = HotelPromotion::select('*')->where('id', $data['id'])->first();
        $get_all_ota = OtaPromotions::select('ota_id')->where('promotion_id', $data['id'])->get();
        foreach($get_all_ota as $ota_info){
            $get_ota_code[] = $ota_info->ota_id;
        }
        if($deactivatePromotion){
            $promotion_id = $data['id'];
            $promotional_data['hotel_id']                       = $p_data->hotel_id;
            $promotional_data['offer_type']                     = $p_data->offer_type;
            $promotional_data['discount']                       = $p_data->discount;
            $promotional_data['applicable_for']                 = $p_data->applicable_for;
            $promotional_data['stay_start_date']                = $p_data->stay_start_date;
            $promotional_data['stay_end_date']                  = $p_data->stay_end_date;
            $promotional_data['booking_start_date']             = $p_data->booking_start_date;
            $promotional_data['booking_end_date']               = $p_data->booking_end_date;
            $promotional_data['blackout_option']                = $p_data->blackout_option;
            $promotional_data['blackout_dates']                 = $p_data->blackout_dates;
            $promotional_data['applicable_for_room_rateplan']   = $p_data->applicable_for_room_rateplan;
            $promotional_data['selected_room_rateplan']         = $p_data->selected_room_rateplan;
            $promotional_data['promotion_name']                 = $p_data->promotion_name;
            $promotional_data['is_trash']                       = 1;
            $call_to_ota_update = $this->promotion_ota_api_call->deletePromotion($get_ota_code,$promotional_data,$promotion_id);
            dd($call_to_ota_update);
            $res = array('status'=>1,'message'=>'Deactivated Successfully');
            return response()->json($res);
        }
        else{
            $res = array('status'=>0,'message'=>'Deactivation Failed');
            return response()->json($res);
        }
    }

    public function getAllInactivePromotion(int $hotel_id,Request $request){
        $getAllInactivePromotion= HotelPromotion::select('id','stay_start_date','stay_end_date','booking_start_date','booking_end_date','selected_room_rateplan','promotion_name','discount')->where('hotel_id',$hotel_id)
        ->where('is_trash',1)
        ->orderBy('id','DESC')
        ->get();

        foreach($getAllInactivePromotion  as $promo) {
            $promo->selected_room_rateplan=json_decode($promo->selected_room_rateplan);
         }

        $res = array('status'=>1,'message'=>'Retrieved successfully',"all_inactive_promotion"=>$getAllInactivePromotion);
        return response()->json($res);
    }

    public function activateHotelPromotion(Request $request){
        $failure_message='Field is required';
        $validator = Validator::make($request->all(),$this->promotion_rules,$this->promotion_messages);
        if ($validator->fails())
        {
            return response()->json(array(
                'status'=>0,
                'message'=>$failure_message,
                'errors'=>$validator->errors()
            ));
        }

        $data=$request->all();
        $activatePromotion=HotelPromotion::where('hotel_id', $data['hotel_id'])->where('id', $data['id'])->update(['is_trash'=>0]);
        $p_data = HotelPromotion::select('*')->where('id', $data['id'])->first();
        $get_all_ota = OtaPromotions::select('ota_id')->where('promotion_id', $data['id'])->get();
        foreach($get_all_ota as $ota_info){
            $get_ota_code[] = $ota_info->ota_id;
        }
        if($activatePromotion){
            $promotion_id = $data['id'];
            $promotional_data['hotel_id']                       = $p_data->hotel_id;
            $promotional_data['offer_type']                     = $p_data->offer_type;
            $promotional_data['discount']                       = $p_data->discount;
            $promotional_data['applicable_for']                 = $p_data->applicable_for;
            $promotional_data['stay_start_date']                = $p_data->stay_start_date;
            $promotional_data['stay_end_date']                  = $p_data->stay_end_date;
            $promotional_data['booking_start_date']             = $p_data->booking_start_date;
            $promotional_data['booking_end_date']               = $p_data->booking_end_date;
            $promotional_data['blackout_option']                = $p_data->blackout_option;
            $promotional_data['blackout_dates']                 = $p_data->blackout_dates;
            $promotional_data['applicable_for_room_rateplan']   = $p_data->applicable_for_room_rateplan;
            $promotional_data['selected_room_rateplan']         = $p_data->selected_room_rateplan;
            $promotional_data['promotion_name']                 = $p_data->promotion_name;
            $promotional_data['is_trash']                       = 0;
            $call_to_ota_update = $this->promotion_ota_api_call->updatePromotion($get_ota_code,$promotional_data,$promotion_id);
            dd($call_to_ota_update);
            $res = array('status'=>1,'message'=>'Activated Successfully');
            return response()->json($res);
        }
        else{
            $res = array('status'=>0,'message'=>'Activation Failed');
            return response()->json($res);
        }
    }
    public function viewPromotionStatus($promotion_id){
        $get_details = OtaPromotions::select('*')->where('promotion_id',$promotion_id)->where('is_trash',0)->get();

        if($get_details){
            $resp = array('status'=>1,"message"=>"Ota promotion details fetch successfully","data"=>$get_details);
            return response()->json($resp);
        }
        else{
            $resp = array('status'=>0,"message"=>"Ota promotion details fetch fails");
            return response()->json($resp);
        }
    }
}

