<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class PmsDetails extends Model 
{
    protected $table = 'pms_account';
    protected $primaryKey = "id";
     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
	protected $fillable = array('hotel_id','pms_code','name','auth_parameter','url','commision');
    
    public function checkCmOtaDetails($ota_name,$hotel_id)
    {
        $conditions=array('name'=> $name,'hotel_id'=>$hotel_id);
        $cmotadetails = CmOtaDetails::where($conditions)->first(['id']);
        if($cmotadetails)
        {
            return "exist";
        }
        else
        {
            return "new";
        }
    }
}