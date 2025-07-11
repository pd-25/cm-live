<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class HotelInformation extends Model 
{
    protected $table = 'hotels_table';
    protected $primaryKey = "hotel_id";
     /**
     * The attributes that are mass assignable.
     * @AUTHOR : Shankar Bag
     * @var array
     */
    // Data Filling
    protected $fillable = array('company_id','user_id','hotel_name','hotel_description','country_id','state_id','city_id','hotel_address','email_id','mobile','reservation_manager_no','gm_contact_no','land_line','latitude','longitude','pin','sac_number','advance_booking_days','partial_payment','partial_pay_amt','star_of_property','check_in','check_out','round_clock_check_in_out','whatsapp_no','facebook_link','twitter_link','linked_in_link','instagram_link','tripadvisor_link','holiday_iq_link','logo','exterior_image',
                                 'distance_from_air','airport_name','distance_from_rail','rail_station_name','bus_station_name','distance_from_bus','nearest_tourist_places','tour_info','terms_and_cond','child_policy','cancel_policy','hotel_policy');
    
    public function getEmailId($hotel_id)
    {

        $hotel_info=HotelInformation::where("hotel_id",$hotel_id)->first();
        if($hotel_info)
        {
            $email_id=explode(',',$hotel_info['email_id']);
            $mobile=explode(',',$hotel_info['mobile']);
        }
        $result=array("email_id"=>$email_id, "mobile"=>$mobile[0]);
        return $result;
    }
    
}
