<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class AgodaListingDetails extends Model 
{
    protected $table = 'agoda_listing_details';
    protected $primaryKey = "id";
     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
	protected $fillable = array('hotel_id','room_type_id','uniq_id');
	
   
}