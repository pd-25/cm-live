<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class PmsRate extends Model 
{
    protected $table = 'pms_rates';
    protected $primaryKey = "id";
     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
	protected $fillable = array('hotel_id','hotel_room_type_id','pms_type_id','pms_room_type_id','hotel_rate_plan_id','pms_rate_plan_name','pms_rate_type');
	
}