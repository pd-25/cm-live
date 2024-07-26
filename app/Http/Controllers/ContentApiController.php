<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Validator;
use DB;
use App\AirbnbAccessToken;
use App\CmOtaDetails;
use App\CmOtaRoomTypeFetch;
use App\CmOtaRateTypeFetch;
use App\AirbnbListingDetails;
use App\HotelInformation;
use App\CompanyDetails;
use App\CmOtaCredentialParameterRead;

class ContentApiController extends Controller
{
    public function getAllAirbnbListing($hotel_id){

        $get_company_id     = HotelInformation::select('company_id')->where('hotel_id',$hotel_id)->first();
        $company_id         = $get_company_id->company_id;
        $get_airbnb_option  = CompanyDetails::select('airbnb_option')->where('company_id',$company_id)->first();
        $airbnb_option      = $get_airbnb_option->airbnb_option;
        if($airbnb_option == 1){
            $getuserid = AirbnbAccessToken::select('*')->where('company_id',$company_id)->first();
        }
        else{
            $getuserid = AirbnbAccessToken::select('*')->where('hotel_id',$hotel_id)->first();
        }
       $airbnb_user_id = (int)$getuserid->user_id;
       $x_airbnb_oauth_token = $this->getAirbnbToken($hotel_id);

       if($hotel_id == 1953){
            $x_airbnb_api_key = '28nb6aej5cji9vsnqbh22di8y';
       }
       else{
            $x_airbnb_api_key = '4kk6ii3r86yqk42je6o8bdker';
       }

        $curl = curl_init();
    
        curl_setopt_array($curl, [
        CURLOPT_URL => "https://api.airbnb.com/v2/listings?user_id=".$airbnb_user_id."&has_availability=true",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => [
            "Accept: application/json",
            "X-Airbnb-API-Key: ".$x_airbnb_api_key,
            "X-Airbnb-OAuth-Token: ".$x_airbnb_oauth_token
        ],
        ]);
    
        $response = curl_exec($curl);
        $err = curl_error($curl);
    
        curl_close($curl);
       
        $listing_data = json_decode($response);
        $listing_array = array();
        $getOtaDetails = CmOtaDetails::select('*')->where('hotel_id',$hotel_id)->where('ota_name','Airbnb')->where('is_active',1)->first();
        $dltAirbnbListing = CmOtaRoomTypeFetch::where('hotel_id',$hotel_id)->delete();
        foreach($listing_data as $key => $value){
            if(sizeof($value)>0){
                foreach($value as $listing_info){
                    $listing_array = array(
                        "hotel_id"          => $hotel_id,
                        "ota_id"            => $getOtaDetails->ota_id,
                        "ota_name"          => 'Airbnb',
                        "ota_room_type_id"  => $listing_info->id,
                        "ota_room_type_name"=> $listing_info->name,
                        "active"            =>1
                    );
                    $insertRoomtypeAirbnb = CmOtaRoomTypeFetch::insert($listing_array);
                    $validate_from = 
                    $validate_to = 
                    $rate_listing_array = array(
                        "hotel_id"            => $hotel_id,
                        "ota_id"              => $getOtaDetails->ota_id,
                        "ota_name"            => 'Airbnb',
                        "ota_room_type_id"    => $listing_info->id,
                        "ota_room_type_name"  => $listing_info->name,
                        "ota_rate_type_id"    => $listing_info->id,
                        "ota_rate_type_name"  => $listing_info->name,
                        "validate_from"       => "",
                        "validate_to"         => "",
                        "active"              => 1
                    );
                    $insertRatePlanAirbnb = CmOtaRateTypeFetch::insert($rate_listing_array);
                }
                return response()->json(array('status'=>1,'message'=>"Listing fetch successfully!"));
            }
            else{
                return response()->json(array('status'=>0,'message'=>"Listing not available for this property. Please create the lsiting from the Bookingjini Extranet"));
            }
        } 
    }
}