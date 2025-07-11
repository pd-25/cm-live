<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class LogTable extends Model 
{
    protected $table = 'log_table';
    protected $primaryKey = "id";
     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
	protected $fillable = array('action_id','hotel_id','ota_id','booking_ref_id','inventory_ref_id','rate_ref_id', 'user_id','request_msg','response_msg','request_url','status','comment','ip');
}