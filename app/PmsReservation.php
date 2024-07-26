<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class PmsReservation extends Model 
{
    protected $table = 'pms_reservation';
    protected $primaryKey = "id";
     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
	protected $fillable = array('hotel_id','pms_string','pms_confirm','pms_status');	
}