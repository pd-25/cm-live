<?php

namespace App;
use Illuminate\Database\Eloquent\Model;
use DB;

class CmBookingDetailsTable extends Model
{
    protected $table = "cm_booking_details_table";
    protected $primaryKey = "id";

    protected $fillable = array('id','hotel_id','ref_no','room_type','rooms','room_rate','extra_adult','extra_child','room_type_id','rate_plan_id','rate_plan_name','adult','child','created_at','updated_at');
}
