<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class CmOtaBookingPushBucket extends Model 
{
    protected $table = 'cm_ota_booking_push_bucket';
    protected $primaryKey = "id";
     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = array('hotel_id','ota_booking_tabel_id','ota_id',
                                'ota_hotel_code','ota_name','is_update','is_processed','booking_status',
                                'push_by');
}