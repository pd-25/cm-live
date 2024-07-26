<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class CmOtaBookingInvStatus extends Model 
{
    protected $table = 'cm_ota_booking_inv_status';
    protected $primaryKey = "id";
     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = array('ota_booking_id','inventory','hotel_id','ota_id');
}