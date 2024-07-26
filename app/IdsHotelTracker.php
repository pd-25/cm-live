<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class IdsHotelTracker extends Model
{
    protected $table = "ids_hotel_code_tracker";
    protected $primaryKey = "id";

    protected $fillable = array('id','hotel_id','hotel_code','pms_room');
}
