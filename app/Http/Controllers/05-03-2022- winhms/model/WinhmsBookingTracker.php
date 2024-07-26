<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class WinhmsBookingTracker extends Model 
{
    protected $connection = 'cmlive';
    protected $table = 'winhms_booking_tracker';
    protected $primaryKey = "id";
     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
	protected $fillable = array('booking_id','room_reserv_id');
	
}