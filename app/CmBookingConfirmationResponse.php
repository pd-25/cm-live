<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class CmBookingConfirmationResponse extends Model 
{
    protected $table = 'cm_booking_confirmation_response';
    protected $primaryKey = "id";
     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
	protected $fillable = array('booking_id','cm_confirmation_id','xml');
}