<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class WinhmsRatePush extends Model
{
    protected $table = 'pms_rate_push';
    protected $primaryKey = "rate_plan_log_id";
     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = array('hotel_id','room_type_id','pms_id','rate_plan_id','bar_price','multiple_occupancy','multiple_days','from_date','to_date','block_status','los','client_ip','user_id','extra_adult_price','extra_child_price','push_status','pms_id','pms_name','ota_details');
    
}