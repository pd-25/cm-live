<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
//a class CmOtaRoomTypeFetch created
class CmOtaRatePlanFetchSync extends Model
{
    protected $connection = 'mysql';
    protected $table = 'cm_ota_rate_plan_synchronize';
    protected $primaryKey = "id";
     /**
     * The attributes that are mass assignable.
     * @author subhradip
     * @var array
     */
    protected $fillable = array('hotel_id','hotel_room_type_id','ota_type_id',
                                'ota_room_type_id','hotel_rate_plan_id',
                                'ota_rate_plan_id','ota_rate_plan_name','ota_rate_type');
    public function checkExist($ota_id,$hotel_id,$room_type_id,$rate_plan_id)
    {
        $cmOtaRatePlanFetchSync = CmOtaRatePlanFetchSync::where('ota_type_id',$ota_id)->where('hotel_id',$hotel_id)->where('hotel_room_type_id',$room_type_id)->where('hotel_rate_plan_id',$rate_plan_id)->where('is_trash',0)->first();
        if($cmOtaRatePlanFetchSync)
        {
            return 'exist';
        }
        else{
            return 'new';
        }
    }

}
