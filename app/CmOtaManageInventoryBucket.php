<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class CmOtaManageInventoryBucket extends Model 
{
    protected $table = 'cm_ota_manage_inventory_bucket';
    protected $primaryKey = "id";
     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
	protected $fillable = array('hotel_id','inventory_table_id','rate_plan_log_table_id','ota_id','ota_hotel_code','ota_name', 'push_at');
}