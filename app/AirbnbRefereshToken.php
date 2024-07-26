<?php
namespace App;
use Illuminate\Auth\Authenticatable;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Support\Facades\Mail;
use Exception;
use DB;
class AirbnbRefereshToken extends Model
{
    protected $connection = 'kernel';
	  protected $table = 'airbnb_referesh_token';
    protected $primaryKey = "id";
     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
	protected $fillable = array('company_id','airbnb_code','airbnb_refresh_token');
}
