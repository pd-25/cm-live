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

class AirbnbListingUpdateController extends Controller
{
    /**
     * This controller is used for mapping the existing listing of Airbnb to bookingjini system
     * @author @ranjit date:2021-07-07
     */
    private $rules = array(
        'ota_name' => 'required',
        'hotel_id'=>'required | numeric'
    );
    //Custom Error Messages
    private $messages = [
        'ota_name.required' => 'The ota name is required.',
        'hotel_id.required'=>'Hotel id is required'
    ];
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
    public function addNewCmHotel(Request $request)
    {
        $cmotadetails = new CmOtaDetails();
        $failure_message='Cm ota details Saving Failed';
        $validator = Validator::make($request->all(),$this->rules,$this->messages);
        if ($validator->fails())
        {
            return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
        }
        $data=$request->all();
        $cmOtaCredentialParameter=new CmOtaCredentialParameterRead();
        $ota_name=$data['ota_name'];
        $cm_data=$cmOtaCredentialParameter->where('ota_name',$ota_name)->first();
        $data['url']=$cm_data->url;
        if($data['ota_name']=='Airbnb')
        {
            $get_company_id     = HotelInformation::select('company_id')->where('hotel_id',$data['hotel_id'])->first();
            $company_id         = $get_company_id->company_id;
            $get_airbnb_option  = CompanyDetails::select('airbnb_option')->where('company_id',$company_id)->first();
            $airbnb_option      = $get_airbnb_option->airbnb_option;
            if($airbnb_option == 1){
                $getUser_id = AirbnbAccessToken::select('*')->where('company_id',$company_id)->first();
            }
            else{
                $getUser_id = AirbnbAccessToken::select('*')->where('hotel_id',$data['hotel_id'])->first();
            }
            $user_id = $getUser_id->user_id;
            $data['ota_hotel_code']=$user_id;
            $data['auth_parameter']='{"X_Airbnb_API_Key":"4kk6ii3r86yqk42je6o8bdker"}';
        }
         //checkCmOtaDetails function from model for checking duplicasy
        if($cmotadetails->checkCmOtaDetails($data['ota_name'],$data['hotel_id'])=="new")
        {
           if($cmotadetails->fill($data)->save())
           {
               $res=array('status'=>1,"message"=>"Cm ota details saved successfully");
               return response()->json($res);
           }
           else
           {
               $res=array('status'=>-1,"message"=>$failure_message);
               $res['errors'][] = "Internal server error";
               return response()->json($res);
           }
        }
        else
        {
            $res=array('status'=>0,"message"=>"This cm ota details already exist");
            return response()->json($res);
        }
    }
    public function updateCmHotel(int $ota_id ,Request $request)
    {
        $failure_message="CM ota  detailse  saving failed.";
        $validator = Validator::make($request->all(),$this->rules,$this->messages);
        if ($validator->fails())
        {
        return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
        }
        $data=$request->all();
        if($data['ota_name']=='Airbnb')
        {
            $get_company_id     = HotelInformation::select('company_id')->where('hotel_id',$data['hotel_id'])->first();
            $company_id         = $get_company_id->company_id;
            $get_airbnb_option  = CompanyDetails::select('airbnb_option')->where('company_id',$company_id)->first();
            $airbnb_option      = $get_airbnb_option->airbnb_option;
            if($airbnb_option == 1){
                $getUser_id = AirbnbAccessToken::select('*')->where('company_id',$company_id)->first();
            }
            else{
                $getUser_id = AirbnbAccessToken::select('*')->where('hotel_id',$data['hotel_id'])->first();
            }
            $user_id = $getUser_id->user_id;
            $data['ota_hotel_code']=$user_id;
            $data['auth_parameter']='{"X_Airbnb_API_Key":"4kk6ii3r86yqk42je6o8bdker"}';
        }
        $cmOtaCredentialParameter=new CmOtaCredentialParameterRead();
        $ota_name=$data['ota_name'];
        $cm_data=$cmOtaCredentialParameter->where('ota_name',$ota_name)->first();
        $data['url']=$cm_data->url;
        $cmotadetails = CmOtaDetails::where('ota_id',$ota_id)->first();
        //checkmasterroomplanStatus function from model for checking duplicasy
            if($cmotadetails->ota_name==$data['ota_name'] )
            {
                if($cmotadetails->fill($data)->save())
                {
                    $res=array('status'=>1,"message"=>"Cm ota details updated successfully");
                    return response()->json($res);
                }
                else
                {
                    $res=array('status'=>-1,"message"=>$failure_message);
                    $res['errors'][] = "Internal server error";
                    return response()->json($res);
                }
            }
            else
            {
                $res=array('status'=>0,"message"=>"This cm ota details already exist");
                return response()->json($res);
            }
    }
    public function updateSyncStatus($hotel_id,$ota_room_type_id){
        $x_airbnb_oauth_token = $this->getAirbnbToken($hotel_id);

        if($hotel_id == 1953){
                $x_airbnb_api_key = '28nb6aej5cji9vsnqbh22di8y';
        }
        else{
                $x_airbnb_api_key = '4kk6ii3r86yqk42je6o8bdker';
        }
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.airbnb.com/v2/listings/".$ota_room_type_id,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "PUT",
            CURLOPT_POSTFIELDS => "{\"synchronization_category\":\"sync_all\"}",
            CURLOPT_HTTPHEADER => [
                "Accept: application/json",
                "Content-Type: application/json",
                "X-Airbnb-API-Key: ".$x_airbnb_api_key,
                "X-Airbnb-OAuth-Token: ".$x_airbnb_oauth_token
            ],
        ]);
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
    }
    public function removeSyncStatus($hotel_id,$ota_room_type_id){
        $x_airbnb_oauth_token = $this->getAirbnbToken($hotel_id);

        if($hotel_id == 1953){
                $x_airbnb_api_key = '28nb6aej5cji9vsnqbh22di8y';
        }
        else{
                $x_airbnb_api_key = '4kk6ii3r86yqk42je6o8bdker';
        }
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.airbnb.com/v2/listings/".$ota_room_type_id,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "PUT",
            CURLOPT_POSTFIELDS => "{\"synchronization_category\":null}",
            CURLOPT_HTTPHEADER => [
                "Accept: application/json",
                "Content-Type: application/json",
                "X-Airbnb-API-Key: ".$x_airbnb_api_key,
                "X-Airbnb-OAuth-Token: ".$x_airbnb_oauth_token
            ],
        ]);
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
    }
    public function getAirbnbToken($hotel_id){
        $airbnbModel=new AirbnbListingDetails();
        $get_company_id     = HotelInformation::select('company_id')->where('hotel_id',$hotel_id)->first();
        $company_id         = $get_company_id->company_id;
        $get_airbnb_option  = CompanyDetails::select('airbnb_option')->where('company_id',$company_id)->first();
        $airbnb_option      = $get_airbnb_option->airbnb_option;
        if($airbnb_option == 1){
            $getAccessToken = AirbnbAccessToken::select('*')->where('company_id',$company_id)->first();
        }
        else{
            $getAccessToken = AirbnbAccessToken::select('*')->where('hotel_id',$hotel_id)->first();
        }
        $today = date('Y-m-d H:i:s');
        $current_time= strtotime($today);
        if($current_time > $getAccessToken->expaire_time){
            $refresh_token = $getAccessToken->refresh_token;
            $accessTokenInfo = $airbnbModel->getAirBnbToken($refresh_token); 
            $get_company_id     = HotelInformation::select('company_id')->where('hotel_id',$hotel_id)->first();
            $company_id         = $get_company_id->company_id;
            $get_airbnb_option  = CompanyDetails::select('airbnb_option')->where('company_id',$company_id)->first();
            $airbnb_option      = $get_airbnb_option->airbnb_option;
            if($airbnb_option == 1){
                $update_access_token = AirbnbAccessToken::where('company_id',$company_id)->update(['access_token'=>$accessTokenInfo->access_token,'expaire_time'=>$accessTokenInfo->expires_at]);
            }
            else{
                $update_access_token = AirbnbAccessToken::where('hotel_id',$hotel_id)->update(['access_token'=>$accessTokenInfo->access_token,'expaire_time'=>$accessTokenInfo->expires_at]);
            }
            $auth = $accessTokenInfo->access_token;
            return $auth;
        }
        else{
            $auth = $getAccessToken->access_token;
            return $auth;
        }
    }
}