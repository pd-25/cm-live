<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class KtdcInvPush extends Model 
{
    protected $table = 'ktdc_inventory_push';
    protected $primaryKey = "inventory_id";
     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
	protected $fillable = array('hotel_id','room_type_id','ktdc_id','no_of_rooms','date_from','date_to','block_status','los','client_ip','user_id','push_status','ktdc_name','multiple_days','ota_details','restriction_status','action_status');
	
}