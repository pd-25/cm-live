<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
//a class CmOtaRoomTypeFetch created
class CmOtaRoomTypeFetch extends Model
{
    protected $connection = 'mysql';
    protected $table = 'cm_ota_room_type_fetch';
    protected $primaryKey = "ota_id";
     /**
     * The attributes that are mass assignable.
     * @author subhradip
     * @var array
     */
    protected $fillable = array('hotel_id','ota_id','ota_name',
                                'ota_room_type_id','ota_room_type_name',
                                'active');


        public function OtaRoomType($ota_room_type_id)
        {
            $cmotaroomtypefetch = CmOtaRoomTypeFetch::where('ota_room_type_id',$ota_room_type_id)->first();
            if($cmotaroomtypefetch)
            {
                return $cmotaroomtypefetch->ota_room_type_name;
            }
            else{
                return false;
            }
        }

}
