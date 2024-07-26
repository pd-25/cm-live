<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
//a class CmOtaRateTypeFetch created
class CmOtaRateTypeFetchRead extends Model
{
    protected $connection = 'cm_read';
    protected $table = 'cm_ota_rate_type_fetch';
    protected $primaryKey = "ota_id";
     /**
     * The attributes that are mass assignable.
     * @author subhradip
     * @var array
     */
    protected $fillable = array('hotel_id','ota_id','ota_name',
                                'ota_room_type_id','ota_room_type_name',
                                'ota_rate_type_id','ota_rate_type_name',
                                'ota_rate_type','validate_from',
                                'validate_to','active');
        public function OtaRatePlan($ota_rate_plan_id)
        {
            $cmotaratetypefetch = CmOtaRateTypeFetchRead::where('ota_rate_type_id',$ota_rate_plan_id)->first();
            if($cmotaratetypefetch)
            {
                return $cmotaratetypefetch->ota_rate_type_name;
            }
            else{
                return false;
            }
        }


}
