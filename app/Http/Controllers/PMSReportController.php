<?php

namespace App\Http\Controllers;
use Eloquent;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

use App\PmsInvPush;
class PMSReportController extends Controller
{
	public function getPMSInventoryList($hotel_id=NULL)
	{
		$inventory_data=PmsInvPush::leftjoin('kernel.hotels_table','pms_inventory_push.hotel_id','=','hotels_table.hotel_id')->leftjoin('kernel.room_type_table','pms_inventory_push.room_type_id','=','room_type_table.room_type_id')->select('hotels_table.hotel_name','room_type_table.room_type','pms_inventory_push.*');
        if($hotel_id){
            $inventory_data=$inventory_data->where('pms_inventory_push.hotel_id',$hotel_id);
        }
        $inventory_data=$inventory_data->paginate(25);
		if($inventory_data){
            $res=array('status'=>1,'message'=>'PMS Inventory retrived successfully','data'=>$inventory_data);
            return response()->json($res);
        }else{
            $res=array('status'=>0,'message'=>'PMS Inventory can not be retrived');
            return response()->json($res);
        }

	}
    public function getHotelPMSWise(Request $request)
    {
        $data=$request->all();
        $getHotelIds=DB::table('pms_account')->select('hotels')->where('pms_account.id',$data['pms_id'])->first();
        if($getHotelIds){
            $hotels= explode(',',$getHotelIds->hotels);
            $getHotels=DB::connection('kernel')->table('hotels_table')->select('hotels_table.hotel_name','hotels_table.hotel_id')->whereIN('hotels_table.hotel_id',$hotels)->get();
            if($getHotels){
                $res=array('status'=>1,'message'=>'Hotels retrived successfully','data'=>$getHotels);
                return response()->json($res);
            }
            else{
                $res=array('status'=>0,'message'=>'Hotels not found');
                return response()->json($res);
            }
        }
        else{
            $res=array('status'=>0,'message'=>'Hotels not found');
            return response()->json($res);
        }


    }
    public function getPMSHotelWise(Request $request)
    {
       $data=$request->all();
       $getData=DB::select(DB::raw("select pms_account.name from pms_account where find_in_set('".$data['hotel_id']."',pms_account.hotels) <> 0"));
       $res=array('status'=>1,'message'=>'PMS List retrived successfully','data'=>$getData);
        return response()->json($res);

    }
}
