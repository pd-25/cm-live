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
class SuperAdmin extends Model
{
    protected $connection = 'kernel';
	  protected $table = 'super_admin';
    protected $primaryKey = "super_admin_id";
     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
	protected $fillable = array('username','password');


	/*
	*@auther : Shankar Bag
    *Check the Availibility of Users
	*@param $email for user email

	*@return  count
    */
	public function checkEmailDuplicacy($email)
	{
		$email = strtoupper(trim($email));
		$hotel_user_data=SuperAdmin::where(DB::raw('upper(username)'),$email)->first();

		if($hotel_user_data)
		{

			return "Exist";

		}
		else
		{
			return 'New';
		}
    }
    public function checkEmailDuplicacyWithType($email)
	{
		$email = strtoupper(trim($email));
		$hotel_user_data=SuperAdmin::where(DB::raw('upper(username)'),$email)->first();
		if($hotel_user_data)
		{

		    return "Exist";

		}
		else
		{
			return 'New';
		}
	}
    public function sendMail($email,$template,$subject,$verificationCode)
	{
		$data=array('email'=>$email,'subject'=>$subject);
		Mail::send(['html'=>$template],['verify_code'=>$verificationCode],function($message) use($data)
		{
			$message->to($data['email'])->from( env("MAIL_FROM"), env("MAIL_FROM_NAME"))->subject( $data['subject']);
		});
		if(Mail::failures())
		{
			return false;
		}
		return true;
	}
}
