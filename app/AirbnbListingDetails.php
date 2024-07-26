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
class AirbnbListingDetails extends Model
{
    protected $table = 'airbnb_listing_details';
    protected $primaryKey = "id";
     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
	protected $fillable = array('hotel_id','room_type_id','property_type_group','property_type_category','room_type_category','cancellation_policy_category','security_deposit','cleaning_fee','space','access','house_rules','bed_room','beds','bath_room','listing_title','neighbourhood','guest_controls','interaction','transit','notes_descr');
    public function getAirBnbToken($refresh_token)
    {
       $apiKey=env('AIRBNB-APIKEY');
       $secret=env('AIRBNB-SECRET');
       $auth_token=$apiKey.':'.$secret;
       $refresh_token_data=array();
       $refresh_token_data['refresh_token']=$refresh_token;
       $refresh_token_data=json_encode($refresh_token_data);
       $ch = curl_init();
       curl_setopt($ch, CURLOPT_URL, "https://api.airbnb.com/v2/oauth2/authorizations?_unwrapped=true");
       curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
       curl_setopt($ch, CURLOPT_POSTFIELDS, $refresh_token_data);
       curl_setopt($ch, CURLOPT_POST, 1);
       curl_setopt($ch, CURLOPT_USERPWD, $auth_token);

       $headers = array();
       $headers[] = "Content-Type: application/json";
       curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

       $result = curl_exec($ch);
       if (curl_errno($ch)) {
           echo 'Error:' . curl_error($ch);
       }
       curl_close ($ch);
       $result=json_decode($result);
       if(isset($result->access_token)){
            return $result->access_token;
       }
       else{
            return 'No access token';
       }
    }
    public function getAirBnbRefreshToken($code)
    {
        $apiKey=env('AIRBNB-APIKEY');
        $secret=env('AIRBNB-SECRET');
        $auth_token=$apiKey.':'.$secret;
        $ch = curl_init();
        $code_data=array();
        $code_data['code']=$code;
        $code_data=json_encode( $code_data);

        curl_setopt($ch, CURLOPT_URL, "https://api.airbnb.com/v2/oauth2/authorizations?_unwrapped=true");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,$code_data);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_USERPWD, $auth_token);

        $headers = array();
        $headers[] = "Content-Type: application/json";
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }
        curl_close ($ch);
       $result=json_decode($result);
       return $result->refresh_token;
    }

}
