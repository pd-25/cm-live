<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class WinhmsReservation extends Model 
{
    protected $table = 'winhms_reservation';
    protected $primaryKey = "id";
     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
	protected $fillable = array('hotel_id','ids_string','ids_confirm');
	
}