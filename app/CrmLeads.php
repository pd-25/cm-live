<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class CrmLeads extends Model 
{
    protected $table = 'contact_details';
    protected $primaryKey = "contact_details_id";
     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
	protected $fillable = array('company_id','hotel_id','name',
                                 'email_id','mobile','city_id','state_id',
                                 'country_id','status','ip','user_id');
	
   
}