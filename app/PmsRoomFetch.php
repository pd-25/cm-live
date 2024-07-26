<?php

namespace App;
use Illuminate\Database\Eloquent\Model;
use DB;

class PmsRoomFetch extends Model
{
    protected $table = "pms_room_fetch";
    protected $primaryKey = "id";

    protected $fillable = array('id','hotel_id','hotel_code','pms_room_type');
}
