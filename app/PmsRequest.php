<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
//a class Coupons created
class PmsREquest extends Model 
{
    protected $table = 'pms_request';
    protected $primaryKey = "id";
     /**
     * The attributes that are mass assignable.
     * @author subhradip
     * @var array
     */
    protected $fillable = array('user_id','api_key','hotel_id','ip','request_for','requested_date');
}