<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
//a class CmOtaRoomTypeFetch created
class CmOtaRoomTypeFetchSync extends Model
{
    protected $connection = 'mysql';
    protected $table = 'cm_ota_room_type_synchronize';
    protected $primaryKey = "id";
     /**
     * The attributes that are mass assignable.
     * @author subhradip
     * @var array
     */
    protected $fillable = array('hotel_id','room_type_id','ota_type_id',
                                'ota_room_type','ota_room_type_name');


    public function checkExist($ota_id,$hotel_id,$room_type_id,$ota_room_type)
    {
        $roomTypeEx = CmOtaRoomTypeFetchSync::where('ota_type_id',$ota_id)->where('hotel_id',$hotel_id)->where('room_type_id',$room_type_id)->where('ota_room_type',$ota_room_type)->where('is_trash',0)->first();
        //$otaroomTypeEx = CmOtaRoomTtypeFetchSync::where('ota_type_id',$ota_id)->where('hotel_id',$hotel_id)->where('ota_room_type',$ota_room_type)->where('is_trash',0)->first();
        if($roomTypeEx)
        {
            return 'exist';
        }
        else{
            return 'new';
        }
    }

}
