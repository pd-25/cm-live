<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class AirbnbNotifyLogs extends Model 
{
    protected $table = 'airbnb_notify_logs';
    protected $primaryKey = "airbnb_notify_log_id";
     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
	protected $fillable = array('notification');   
}