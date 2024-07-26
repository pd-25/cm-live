<?php
namespace App\Http\Controllers;
use Exception;
use Validator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;


use App\Http\Controllers\Controller;
use DB;

use App\HotelPromotion;
/**
 * This controller used to add,update tables of promotions
 * @author Mohommed Hafiz
 */
class HotelPromotionController extends Controller{

    private $promotion_rules=array(
        'hotel_id'=>'required|numeric'
    );
    private $promotion_messages=[
     'hotel_id=>required'=>'hotel_id is required'
    ];



    public function getAllPromotion(int $hotel_id,Request $request){

        // $failure_message='Field is required';
        // $validator = Validator::make($request->all(),$this->promotion_rules,$this->promotion_messages);
        // if ($validator->fails())
        // {
        //     return response()->json(array(
        //         'status'=>0,
        //         'message'=>$failure_message,
        //         'errors'=>$validator->errors()
        //     ));
        // }

        $getAllPromotion= HotelPromotion::select('id','stay_start_date','stay_end_date','booking_start_date','booking_end_date','selected_room_rateplan','promotion_name','discount')->where('hotel_id',$hotel_id)->where('is_trash',1)->get();

        foreach($getAllPromotion  as $promo) {
            $promo->selected_room_rateplan=json_decode($promo->selected_room_rateplan);
         }


           $res = array('status'=>1,'message'=>'Retrieved successfully',"all_promotion"=>$getAllPromotion);
           return response()->json($res);
    }


    public function getHotelPromotion(int $hotel_id,int $id,Request $request){

        // $failure_message='Field is required';
        // $validator = Validator::make($request->all(),$this->promotion_rules,$this->promotion_messages);
        // if ($validator->fails())
        // {
        //     return response()->json(array(
        //         'status'=>0,
        //         'message'=>$failure_message,
        //         'errors'=>$validator->errors()
        //     ));
        // }

        $getPromotion= HotelPromotion::select('ota','offer_type','discount','applicable_for','stay_start_date','stay_end_date','booking_start_date','booking_end_date','blackout_option','blackout_dates','applicable_for_room_rateplan','selected_room_rateplan','promotion_name')->where('hotel_id',$hotel_id)->where('id',$id)->first();

        if($getPromotion) {
            // $getPromotion->ota=explode(',',$getPromotion->ota);
            $getPromotion->ota = array_map('intval',array_filter(explode(',',$getPromotion->ota),'is_numeric'));
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

        $data=$request->all();

        $data['ota'] = implode(",",$data['ota']);
        if(sizeof($data['blackout_dates']) > 0){
            $data['blackout_dates'] = implode(",",$data['blackout_dates']);
        }else{
            $data['blackout_dates']='';
        }
        $data['selected_room_rateplan']= json_encode($request->input('selected_room_rateplan'));

        $insertPromotion=HotelPromotion::insert(['hotel_id'=>$data['hotel_id'],'ota'=>$data['ota'],'offer_type'=>$data['offer_type'],'discount'=>$data['discount'],'applicable_for'=>$data['applicable_for'],'stay_start_date'=>$data['stay_start_date'],'stay_end_date'=>$data['stay_end_date'],'booking_start_date'=>$data['booking_start_date'],'booking_end_date'=>$data['booking_end_date'],'blackout_option'=>$data['blackout_option'],'blackout_dates'=>$data['blackout_dates'],'applicable_for_room_rateplan'=>$data['applicable_for_room_rateplan'],'selected_room_rateplan'=>$data['selected_room_rateplan'],'promotion_name'=>$data['promotion_name']]);
        
        
        if($insertPromotion){
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

        $data['ota'] = implode(",",$data['ota']);
        if(sizeof($data['blackout_dates']) > 0){
            $data['blackout_dates'] = implode(",",$data['blackout_dates']);
        }else{
            $data['blackout_dates']='';
        }
        $data['selected_room_rateplan']= json_encode($request->input('selected_room_rateplan'));

        $updatePromotion=HotelPromotion::where('hotel_id', $data['hotel_id'])->where('id', $data['id'])->update(['ota'=>$data['ota'],'offer_type'=>$data['offer_type'],'discount'=>$data['discount'],'applicable_for'=>$data['applicable_for'],'stay_start_date'=>$data['stay_start_date'],'stay_end_date'=>$data['stay_end_date'],'booking_start_date'=>$data['booking_start_date'],'booking_end_date'=>$data['booking_end_date'],'blackout_option'=>$data['blackout_option'],'blackout_dates'=>$data['blackout_dates'],'applicable_for_room_rateplan'=>$data['applicable_for_room_rateplan'],'selected_room_rateplan'=>$data['selected_room_rateplan'],'promotion_name'=>$data['promotion_name']]);
        
        
        if($updatePromotion){
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


        $deactivatePromotion=HotelPromotion::where('hotel_id', $data['hotel_id'])->where('id', $data['id'])->update(['is_trash'=>0]);
        
        if($deactivatePromotion){
            $res = array('status'=>1,'message'=>'Deactivated Successfully');
            return response()->json($res);
        }
        else{
            $res = array('status'=>0,'message'=>'Deactivation Failed');
            return response()->json($res);
        }

    }


    public function getAllInactivePromotion(int $hotel_id,Request $request){

        // $failure_message='Field is required';
        // $validator = Validator::make($request->all(),$this->promotion_rules,$this->promotion_messages);
        // if ($validator->fails())
        // {
        //     return response()->json(array(
        //         'status'=>0,
        //         'message'=>$failure_message,
        //         'errors'=>$validator->errors()
        //     ));
        // }

        $getAllInactivePromotion= HotelPromotion::select('id','stay_start_date','stay_end_date','booking_start_date','booking_end_date','selected_room_rateplan','promotion_name','discount')->where('hotel_id',$hotel_id)->where('is_trash',0)->get();

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

        $activatePromotion=HotelPromotion::where('hotel_id', $data['hotel_id'])->where('id', $data['id'])->update(['is_trash'=>1]);
        
        if($activatePromotion){
            $res = array('status'=>1,'message'=>'Activated Successfully');
            return response()->json($res);
        }
        else{
            $res = array('status'=>0,'message'=>'Activation Failed');
            return response()->json($res);
        }

    }
}

