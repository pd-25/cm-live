<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class InvControlTable extends Model 
{
    protected $table = 'inventory_control_table';
    protected $primaryKey = "id";
     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
	protected $fillable = array('id','hotel_id','pms_status','created_at','updated_at');
	
}