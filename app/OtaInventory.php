<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
//a class OtaBlockInventory created
class OtaInventory extends Model 
{
    protected $table = 'ota_inventory';
    protected $primaryKey = "inventory_id";
     /**
     * The attributes that are mass assignable.
     * @author subhradip
     * @var array
     */
    protected $fillable = array('hotel_id','room_type_id','no_of_rooms',
                                'date_from','date_to','client_ip',
                                'user_id','block_status','los','channel','multiple_days','ota_details','restriction_status','action_status','ota_booking_id','booking_status');

    

}	