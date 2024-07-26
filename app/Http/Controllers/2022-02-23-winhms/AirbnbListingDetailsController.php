<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Input;
use Validator;
use App\CmOtaDetails;
use App\CompanyDetails;
use DB;
use App\ImageTable;
use App\MasterRoomType;
use App\AirbnbListingDetails;
use App\AirbnbRoomTypeSynchronised;
use App\AirbnbRoomTypeSynchronisedRead;
use App\AirbnbNotifyLogs;
use App\HotelInformation;
/** Listing of airbnb details from kernel
* @author : Ranjit @date : 2020/05/16
* @userstory : This controller consist of listing of airbnb details from hotel section, room type listing, amenities listing, room description listing, Image listing in the airbnb and instance booking of airbnb
*/
class AirbnbListingDetailsController extends Controller
{
    private $code_rules = array(
        'code' => 'required',
        'company_id' => 'required'
    );
    //Custom Error Messages
    private $code_messages = [
        'code.required' => 'Airbnb code is required.',
        'company_id.required' => 'Company id is required.'
            ];
    /** Listing of new hotel in airbnb and update existing hotel in airbnb
    * @author : Ranjit @date : 2020/05/15
    * @userstory : Code separation for airbnb so that with out any error hotel details can be created and modified.
    */
    Public function addHotelAirbnbBrand($data,$hotel_info){
        $airbnbDetails = new CmOtaDetails();
        $company=new CompanyDetails();
        $apiKey='4kk6ii3r86yqk42je6o8bdker';
        $auth_parameter='{"X_Airbnb_API_Key":"'.$apiKey.'"}';
        $comp_details=$company->where('company_id',$data['company_id'])->select('airbnb_refresh_token')->first();
        $refresh_token=$comp_details->airbnb_refresh_token;

        if($refresh_token!="")
        {
            $airbnb_response=$this->addHotelAirbnbListing($apiKey,$refresh_token,$data);
            if($airbnb_response['status']==0){
                $res=array('status'=>0,"message"=>$airbnb_response['response']);
                return response()->json($res);
            }else{
                $airbnb_listing_id=$airbnb_response['response'];
            }
            $airbnbDetails->hotel_id=$hotel_info->hotel_id;
            $airbnbDetails->ota_hotel_code=$airbnb_listing_id;
            $airbnbDetails->auth_parameter=$auth_parameter;
            $airbnbDetails->ota_name="Airbnb";
            $airbnbDetails->url="https://api.airbnb.com/v2/";
            $airbnbDetails->commision=0;
            $airbnbDetails->is_active=1;
            $airbnbDetails->save();
        }
    }
    public function updateHotelAirbnbBrand($data,$hotel_info){
        $airbnbDetails = new CmOtaDetails();
        $company=new CompanyDetails();
        $apiKey='4kk6ii3r86yqk42je6o8bdker';
        $auth_parameter='{"X_Airbnb_API_Key":"'.$apiKey.'"}';
        $comp_details=$company->where('company_id',$data['company_id'])->select('airbnb_refresh_token')->first();
        $refresh_token=$comp_details->airbnb_refresh_token;
        $ota=$airbnbDetails->where('hotel_id',$hotel_info->hotel_id)->where('ota_name','Airbnb')->first();
        if($refresh_token!="")
        {
            if($ota)
            {
                $result=$this->updateHotelAirbnbListing($apiKey,$refresh_token,$data,$ota->ota_hotel_code);
                if($result=='ListitAgain'){
                    $airbnbDetails->where('hotel_id',$hotel_info->hotel_id)->where('ota_name','Airbnb')->delete();
                    $airbnb_response=$this->addHotelAirbnbListing($apiKey,$refresh_token,$data);
                    if($airbnb_response['status']==0){
                        $res=array('status'=>0,"message"=>$airbnb_response['response']);
                        return response()->json($res);
                    }else{
                        $airbnb_listing_id=$airbnb_response['response'];
                    }
                    $airbnbDetails->hotel_id=$hotel_info->hotel_id;
                    $airbnbDetails->ota_hotel_code=$airbnb_listing_id;
                    $airbnbDetails->auth_parameter=$auth_parameter;
                    $airbnbDetails->ota_name="Airbnb";
                    $airbnbDetails->url="https://api.airbnb.com/v2/";
                    $airbnbDetails->commision=0;
                    $airbnbDetails->is_active=1;
                    $airbnbDetails->save();
                    $res=array('status'=>1,"message"=>"Hotel Informations updated successfully");
                    return response()->json($res);
                }
                elseif($result=='invalid_token'){
                    $res=array('status'=>0,"message"=>$result." of airbnb and try to update the airbnb code!");
                    return response()->json($res);
                }else{
                    $res=array('status'=>1,"message"=>"Hotel Information Update to airbnb successfully");
                    return response()->json($res);
                }
            }
            else
            {
                $airbnb_response=$this->addHotelAirbnbListing($apiKey,$refresh_token,$data);
                if($airbnb_response['status']==0){
                    $res=array('status'=>0,"message"=>$airbnb_response['response']);
                    return response()->json($res);
                }else{
                    $airbnb_listing_id=$airbnb_response['response'];
                }
                $airbnbDetails->hotel_id=$hotel_info->hotel_id;
                $airbnbDetails->ota_hotel_code=$airbnb_listing_id;
                $airbnbDetails->auth_parameter=$auth_parameter;
                $airbnbDetails->ota_name="Airbnb";
                $airbnbDetails->url="https://api.airbnb.com/v2/";
                $airbnbDetails->commision=0;
                $airbnbDetails->is_active=1;
                $airbnbDetails->save();
                if($airbnbDetails->save()){
                    $res=array('status'=>1,"message"=>"Hotel Informations updated to airbnb successfully");
                    return response()->json($res);
                }
            }
        }
    }
    //arbnb function for hotel
    public function addHotelAirbnbListing($apiKey,$refresh_token,$data){
        $airbnbModel=new AirbnbListingDetails();
        $auth=$airbnbModel->getAirBnbToken($refresh_token);
        $city=DB::table('city_table')->select('city_name')->where('city_id',$data["city_id"])->first();
        $state=DB::table('state_table')->select('state_name')->where('state_id',$data["state_id"])->first();
        $post_data=array("city" => $city->city_name, "state"=> $state->state_name, "zipcode"=>$data['pin'], "country_code"=> "IN", "name"=> $data['hotel_name'], "lat"=>$data['latitude'], "lng"=> $data['longitude'], "listing_price"=>10,
        "property_type_category"=>"hotel",
        "room_type_category"=> "private_room");
        $post_data=json_encode($post_data);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.airbnb.com/v2/listings");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_POST, 1);

        $headers = array();
        $headers[] = "X-Airbnb-Api-Key:$apiKey";
        $headers[] = "X-Airbnb-Oauth-Token: $auth";
        $headers[] = "Content-Type: application/json";
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }
        curl_close ($ch);
        $result=json_decode($result);
        if(isset($result->error_code) && $result->error_code==400){
            return array('status'=>0,'response'=>$result->error_message);
        }else{
            //return array('status'=>1,'response'=>$result->listing->id);
            return true;
        }
    }
    //arbnb function for hotel
    public function updateHotelAirbnbListing($apiKey,$refresh_token,$data,$airbnb_listing_id){
        $airbnbModel=new AirbnbListingDetails();
        $auth=$airbnbModel->getAirBnbToken($refresh_token);
        $city=DB::table('city_table')->select('city_name')->where('city_id',$data["city_id"])->first();
        $state=DB::table('state_table')->select('state_name')->where('state_id',$data["state_id"])->first();
        $post_data=array("city" => $city->city_name, "state"=> $state->state_name, "zipcode"=>$data['pin'], "country_code"=> "IN", "name"=> $data['hotel_name'], "lat"=>$data['latitude'], "lng"=> $data['longitude'], "listing_price"=>10,
        "property_type_category"=>"hotel",
        "room_type_category"=> "private_room");
        $post_data=json_encode($post_data);
        $headers = array();
        $headers[] = "X-Airbnb-Api-Key: $apiKey";
        $headers[] = "X-Airbnb-Oauth-Token: $auth";
        $headers[] = "Content-Type: application/json";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.airbnb.com/v2/listings/$airbnb_listing_id");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }
        curl_close ($ch);
        $result=json_decode($result);
        if(!isset($result->error_code)){
            return $result->listing->id;
        }elseif(!isset($result->error_code) && $result->error_type=='no_access' && $result->error_message=='This listing has been unlisted.' ){
            return "ListitAgain";
        }
        else if(isset($result->error_code)){
            return $result->error;
        }
    }
    /**
    * End of hotel listing.
    */
    /**Airbnb code
    * @author : Ranjit @date : 2020/05/16
    * @user story: this function is used to save the airbnb code from the airbnb given url.
    */
    public function saveAirbnbcode(Request $request)
    {
        $failure_message='Cm ota details Saving Failed';
        $validator = Validator::make($request->all(),$this->code_rules,$this->code_messages);
        if ($validator->fails())
        {
            return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
        }
        $data=$request->all();
        $airbnbModel=new AirbnbListingDetails();
        $refresh_token=$airbnbModel->getAirBnbRefreshToken($data['code']);
        $up=CompanyDetails::where('company_id', $data['company_id'])
        ->update(['airbnb_code' =>$data['code'],"airbnb_refresh_token"=>$refresh_token]);
        if($up)
        {
            $res=array('status'=>1,"message"=>"Airbnb credentials saved successfully!");
            return response()->json($res);
        }
        else
        {
            $res=array('status'=>0,"message"=>"Airbnb credentials saving failed!");
            return response()->json($res);
        }
    }
    //end of airbnb code saving.
    /**Room type details push to airbnb(image,description,photo,token,cancelation policy,remove photo, update room description,add and update amenities and room type details in add and edit)
    * @author : Ranjit @date 2020/05/16
    * @user story : All the details of room type push to airbnb with amenities of room push to airbnb nd photo uploaded to airbnb
    */
    public function addRoomTypeToAirbnb($data,$hotel_info,$masterRoomType){
        $cm_ota_details=new CmOtaDetails();
        $airbnb_room_type_details = array();
        $cm_ota_data=$cm_ota_details->where('hotel_id',$data['hotel_id'])->where('ota_name','Airbnb')->first();
        $refresh_token=$this->getRefreshToken($hotel_info->company_id);
        if($cm_ota_data){
          if($refresh_token){
            $airbnb_room_id=$this->addAirbnbListing($refresh_token,$hotel_info,$data,$cm_ota_data);
            $airbnb_room_type_details['hotel_id']=$data['hotel_id'];
            $airbnb_room_type_details['room_type_id']=$masterRoomType->room_type_id;
            $airbnb_room_type_details['ota_type_id']=$cm_ota_data->id;
            $airbnb_room_type_details['ota_room_type']=$airbnb_room_id;
            $airbnb_room_type_details['ota_room_type_name']=$masterRoomType->room_type;
            $insert_to_table = AirbnbRoomTypeSynchronised::insert($airbnb_room_type_details);
            $airbnb_data=array(
                "summary" =>  $data['description']
                );
            $update_desc=$this->updateAirbnbDescription($refresh_token,$airbnb_data,$masterRoomType->room_type_id,$hotel_info['hotel_id']);
            $images=explode(',',$data['image']);
            foreach($images as $image){
                $this->uploadPhoto($refresh_token,$image,$masterRoomType->room_type_id,$hotel_info['hotel_id']);
            }
          }
        }
    }
    public function updateRoomTypeToAirbnb($data,$room_type_id,$master_Room_Type,$upload_image){
        $cm_ota_details=new CmOtaDetails();
        $airbnb_room_type_details = array();
        $hotel_info=HotelInformation::where('hotel_id',$data['hotel_id'])->first();
        $refresh_token=$this->getRefreshToken($hotel_info->company_id);
        if($refresh_token){
          $cm_ota_data=$cm_ota_details->where('hotel_id',$data['hotel_id'])->where('ota_name','Airbnb')->first();
          if($cm_ota_data){
              $airbnb_listing_id=$this->getOtaRoomType($room_type_id,$cm_ota_data->ota_id);
              $airbnb_data=array();
              if($airbnb_listing_id){
                  $this->updateAirbnbListing($refresh_token,$airbnb_data,$hotel_info,$data,$cm_ota_data,$room_type_id);
              }
              else{
                  $airbnb_room_id=$this->addAirbnbListing($refresh_token,$hotel_info,$data,$cm_ota_data);
                  $airbnb_room_type_details['hotel_id']=$data['hotel_id'];
                  $airbnb_room_type_details['room_type_id']=$room_type_id;
                  $airbnb_room_type_details['ota_type_id']=$cm_ota_data->id;
                  $airbnb_room_type_details['ota_room_type']=$airbnb_room_id;
                  $airbnb_room_type_details['ota_room_type_name']=$master_Room_Type->room_type;
                  $update_to_table = AirbnbRoomTypeSynchronised::insert($airbnb_room_type_details);
              }
              $airbnb_data=array(
                  "summary" =>  $data['description']
                  );
              $update_desc=$this->updateAirbnbDescription($refresh_token,$airbnb_data,$room_type_id,$hotel_info['hotel_id']);
                if($upload_image!=""){
                    $images=explode(',', $upload_image);
                      foreach($images as $image){
                          $this->uploadPhoto($refresh_token,$image,$room_type_id,$hotel_info['hotel_id']);
                      }
                  }
              }
        }
    }
    public function getOtaRoomType($room_type,$ota_id)
    {
        $ota_room_type_id="";
        $cmOtaRoomTypeSynchDetails= AirbnbRoomTypeSynchronisedRead::select('ota_room_type')
            ->where('room_type_id','=' ,$room_type)
            ->where('ota_type_id','=', $ota_id)
            ->orderBy('id', 'DESC')
            ->first();
        if($cmOtaRoomTypeSynchDetails)
        {
            $ota_room_type_id             =  $cmOtaRoomTypeSynchDetails->ota_room_type;
        }
        return $ota_room_type_id ;
    }
    public function uploadPhoto($refresh_token,$image_id,$room_type_id,$hotel_id)
    {
        $airbnbModel=new AirbnbListingDetails();
        $apiKey='4kk6ii3r86yqk42je6o8bdker';
        $auth=$airbnbModel->getAirBnbToken($refresh_token);
        $cm_ota_detail_model=new CmOtaDetails();
        $cm_ota_details=$cm_ota_detail_model->where('hotel_id',$hotel_id)
                                            ->where('ota_name','Airbnb')
                                            ->where('is_active',1)
                                            ->first();
        $ota_id= $cm_ota_details->ota_id;
        $airbnb_liting_id=$this->getOtaRoomType($room_type_id,$ota_id);
        $imageModel= new ImageTable();
        $image= $imageModel->where('image_id',$image_id)->first();
        $img_data = env('S3_IMAGE_PATH').$image->image_name;
        $type = pathinfo($img_data, PATHINFO_EXTENSION);
        $img_base64_decoded_string = base64_encode($img_data);
        $mime_type="image/".$type;
        $post_data=array(
            "listing_id"=>$airbnb_liting_id,
            "caption"=>"",
            "content_type"=> $mime_type,
            "filename"=> $image->image_name,
            "image"=>$img_base64_decoded_string
                );
        $post_data=json_encode($post_data);
           $ch = curl_init();
           curl_setopt($ch, CURLOPT_URL, "https://api.airbnb.com/v2/listing_photos");
           curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
           curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
           curl_setopt($ch, CURLOPT_POST, 1);

           $headers = array();
           $headers[] = "X-Airbnb-Api-Key: $apiKey";
           $headers[] = "X-Airbnb-Oauth-Token: $auth";
           $headers[] = "Content-Type: application/json";
           curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

           $result = curl_exec($ch);
           if (curl_errno($ch)) {
               echo 'Error:' . curl_error($ch);
           }
           curl_close ($ch);
           $result=json_decode($result);
           if(!isset($result->error))
           {
            $up="";
            $up=$imageModel->where('image_id',$image_id)->update(['airbnb_image_id'=>$result->listing_photo->id]);
            if($up){
                return true;
            }
            else{
                return false;
            }
        }
    }
    //Airbnb listing description Api
    public function airbnbCancellationPolicy($refresh_token,$cancellation_data,$room_type_id,$hotel_id)
    {
        $airbnbModel=new AirbnbListingDetails();
        $apiKey='4kk6ii3r86yqk42je6o8bdker';
        $auth=$airbnbModel->getAirBnbToken($refresh_token);
        $cm_ota_detail_model=new CmOtaDetails();
        $cm_ota_details=$cm_ota_detail_model->where('hotel_id',$hotel_id)
                                            ->where('ota_name','Airbnb')
                                            ->where('is_active',1)
                                            ->first();
        $ota_id= $cm_ota_details->ota_id;
        $airbnb_listing_id=$this->getOtaRoomType($room_type_id,$ota_id);
        $post_data=array(
                "cancellation_policy_category" => $cancellation_data['cancellation_policy_category'],
                "instant_book_welcome_message"=>"Thank you for booking with us!",
                "guest_controls"=>$cancellation_data['guest_controls']
                );
        $post_data=json_encode($post_data);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.airbnb.com/v2/booking_settings/$airbnb_listing_id");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");

        $headers = array();
        $headers[] = "X-Airbnb-Api-Key: $apiKey";
        $headers[] = "X-Airbnb-Oauth-Token: $auth";
        $headers[] = "Content-Type: application/json";
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }
        curl_close ($ch);
        $result=json_decode($result);
        if(!isset($result->error))
        {
            return true;
        }
    }
    //Airbnb listing description Api
    public function airbnbPriceSettings($refresh_token,$air_bnb_data,$room_type_id,$hotel_id)
    {
        $airbnbModel=new AirbnbListingDetails();
        $apiKey='4kk6ii3r86yqk42je6o8bdker';
        $auth=$airbnbModel->getAirBnbToken($refresh_token);
        $cm_ota_detail_model=new CmOtaDetails();
        $cm_ota_details=$cm_ota_detail_model->where('hotel_id',$hotel_id)
                                            ->where('ota_name','Airbnb')
                                            ->where('is_active',1)
                                            ->first();
        $ota_id= $cm_ota_details->ota_id;
        $airbnb_listing_id=$this->getOtaRoomType($room_type_id,$ota_id);
        $post_data=array(
                "cleaning_fee" => $air_bnb_data['cleaning_fee'],
                "security_deposit"=>$air_bnb_data['security_deposit'],
                "guests_included" =>  $air_bnb_data['guests_included']
                );
        $post_data=json_encode($post_data);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.airbnb.com/v2/pricing_settings/$airbnb_listing_id");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");

        $headers = array();
        $headers[] = "X-Airbnb-Api-Key: $apiKey";
        $headers[] = "X-Airbnb-Oauth-Token: $auth";
        $headers[] = "Content-Type: application/json";
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }
        curl_close ($ch);
        $result=json_decode($result);
        if(!isset($result->error))
        {
            return true;
        }

    }
    public function updateReviewStatus(int $hotel_id,$room_type_id,Request $request)
    {
        $hotel_info=HotelInformation::where('hotel_id',$hotel_id)->first();
        $airbnbModel=new AirbnbListingDetails();
        $cm_ota_detail_model=new CmOtaDetails();
        $apiKey='4kk6ii3r86yqk42je6o8bdker';
        $company=new CompanyDetails();
        $comp_details=$company->where('company_id',$hotel_info->company_id)->select('airbnb_refresh_token')->first();
        $refresh_token=$comp_details->airbnb_refresh_token;
        $auth=$airbnbModel->getAirBnbToken($refresh_token);
        $up=DB::table('airbnb_listing_details')
        ->where('room_type_id', $room_type_id)
        ->update(['status' =>"ready for review","notes"=>""]);
        $cm_ota_detail_model=new CmOtaDetails();
        $cm_ota_details=$cm_ota_detail_model->where('hotel_id',$hotel_id)
        ->where('ota_name','Airbnb')
        ->where('is_active',1)
        ->first();
        $ota_id= $cm_ota_details->id;
        $airbnb_listing_id=$this->getOtaRoomType($room_type_id,$ota_id);
        $post_data=array("requested_approval_status_category" => "ready for review");

        $post_data=json_encode($post_data);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.airbnb.com/v2/listings/$airbnb_listing_id");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        $headers = array();
        $headers[] = "X-Airbnb-Api-Key: $apiKey";
        $headers[] = "X-Airbnb-Oauth-Token: $auth";
        $headers[] = "Content-Type: application/json";
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }
        curl_close ($ch);
        dd($result);
        $result=json_decode($result);
        if(!isset($result->error))
        {
            $res=array("status"=>1,"message"=>"Ready for review status updated");
            return response()->json($res);
        }
        else
        {
            $res=array("status"=>0,"message"=>"Status updation failed");
            return response()->json($res);
        }
    }
    public function getGuestControl($selected_guest_control)
    {
        $guest_control_arr=array(
         "allows_children_as_host"=>false,
         "allows_infants_as_host"=>false,
         "allows_pets_as_host"=>false,
         "allows_smoking_as_host"=>false,
         "allows_events_as_host"=>false);
         foreach($selected_guest_control as $control)
         {
            $guest_control_arr[trim($control)]=true;
         }
         return  $guest_control_arr;
    }
    //Airbnb Photos Api
    public function airbnbremovePhoto($image_id,$hotel_id)
    {
       $airbnbModel=new AirbnbListingDetails();
       $apiKey='4kk6ii3r86yqk42je6o8bdker';
       $hotel_info=HotelInformation::where('hotel_id',$hotel_id)->first();
       $company=new CompanyDetails();
       $comp_details=$company->where('company_id',$hotel_info->company_id)->select('airbnb_refresh_token')->first();
       $refresh_token=$comp_details->airbnb_refresh_token;
       $auth=$airbnbModel->getAirBnbToken($refresh_token);
       $ch = curl_init();

       curl_setopt($ch, CURLOPT_URL, "https://api.airbnb.com/v2/listing_photos/$image_id");
       curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
       curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
       $headers = array();
       $headers[] = "X-Airbnb-Api-Key:  $apiKey";
       $headers[] = "X-Airbnb-Oauth-Token: $auth";
       $headers[] = "Content-Type: application/json";
       curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

       $result = curl_exec($ch);
       if (curl_errno($ch)) {
           echo 'Error:' . curl_error($ch);
       }
       curl_close ($ch);
       $result=json_decode($result);
       if(!isset($result->error))
       {
           return true;
       }
    }
    public function getAirbnbToken(Request $request){
        $airbnbModel=new AirbnbListingDetails();
        $apiKey='4kk6ii3r86yqk42je6o8bdker';
        $auth=$airbnbModel->getAirBnbToken('2wlb07in7wml14q87ujvqn8l4');
        return $auth."-".$apiKey;
    }
    public function airbnbInstantBooking(int $airbnb_status,int $room_type_id,int $hotel_id,Request $request)
    {
       $airbnbModel=new AirbnbListingDetails();
       $apiKey='4kk6ii3r86yqk42je6o8bdker';
       $hotel_info=HotelInformation::where('hotel_id',$hotel_id)->first();
       $company=new CompanyDetails();
       $comp_details=$company->where('company_id',$hotel_info->company_id)->select('airbnb_refresh_token')->first();
       $refresh_token=$comp_details->airbnb_refresh_token;
       $auth=$airbnbModel->getAirBnbToken($refresh_token);
       $getota_id=DB::table('cm_ota_details')->select('ota_id')->where('hotel_id',$hotel_id)->where('ota_name','Airbnb')->first();
       $getairbnb_referal_id=DB::table('cm_ota_room_type_synchronize')->select('ota_room_type')->where('hotel_id',$hotel_id)->where('room_type_id',$room_type_id)->where('ota_type_id',$getota_id->ota_id)->first();
       $post_data=array("booking_lead_time" =>array("hours" => 48, "allow_request_to_book" =>1 ));
       $post_data=json_encode($post_data);
       $ch = curl_init();
       curl_setopt($ch, CURLOPT_URL ,"https://api.airbnb.com/v2/availability_rules/$getairbnb_referal_id->ota_room_type");
       curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
       $headers = array();
       $headers[] = "X-Airbnb-Api-Key:  $apiKey";
       $headers[] = "X-Airbnb-Oauth-Token: $auth";
       $headers[] = "Content-Type: application/json";
       curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
       curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
       curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
       $result = curl_exec($ch);
       if (curl_errno($ch)) {
           echo 'Error:' . curl_error($ch);
       }
       curl_close ($ch);
       $result=json_decode($result);

       if(!isset($result->error))
       {
            return response()->json(array('status'=>1,'message'=>'details retrive sucessfully','data'=> $result));
       }
       else{
            return response()->json(array('status'=>0,'message'=>'details retrive fails'));
       }
    }
    public function getAirbnbMaxdaystatus(int $room_type_id,int $hotel_id,Request $request)
    {
        $airbnbModel=new AirbnbListingDetails();
        $apiKey='4kk6ii3r86yqk42je6o8bdker';
        $hotel_info=HotelInformation::where('hotel_id',$hotel_id)->first();
        $company=new CompanyDetails();
        $comp_details=$company->where('company_id',$hotel_info->company_id)->select('airbnb_refresh_token')->first();
        $refresh_token=$comp_details->airbnb_refresh_token;
        $auth=$airbnbModel->getAirBnbToken($refresh_token);
        $getota_id=DB::table('cm_ota_details')->select('ota_id')->where('hotel_id',$hotel_id)->where('ota_name','Airbnb')->first();
        $getairbnb_referal_id=DB::table('cm_ota_room_type_synchronize')->select('ota_room_type')->where('hotel_id',$hotel_id)->where('room_type_id',$room_type_id)->where('ota_type_id',$getota_id->ota_id)->first();
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.airbnb.com/v2/availability_rules/$getairbnb_referal_id->ota_room_type");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $headers = array();
        $headers[] = "X-Airbnb-Api-Key:  $apiKey";
        $headers[] = "X-Airbnb-Oauth-Token: $auth";
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }
        curl_close ($ch);
        $result=json_decode($result);
        if(!isset($result->error))
        {
            return response()->json(array('status'=>1,'message'=>'details retrive sucessfully','data'=> $result));
        }
        else{
            return response()->json(array('status'=>0,'message'=>'details retrive fails'));
       }
    }
    public function updateAirbnbDescription($refresh_token,$air_bnb_data,$room_type_id,$hotel_id)
    {
        $airbnbModel=new AirbnbListingDetails();
        $apiKey='4kk6ii3r86yqk42je6o8bdker';
        $auth=$airbnbModel->getAirBnbToken($refresh_token);
        $cm_ota_detail_model=new CmOtaDetails();
        $cm_ota_details=$cm_ota_detail_model->where('hotel_id',$hotel_id)
                                            ->where('ota_name','Airbnb')
                                            ->where('is_active',1)
                                            ->first();
        $ota_id= $cm_ota_details->ota_id;
        $airbnb_listing_id=$this->getOtaRoomType($room_type_id,$ota_id);
        $post_data=json_encode($air_bnb_data);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.airbnb.com/v2/listing_descriptions/$airbnb_listing_id/en");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");

        $headers = array();
        $headers[] = "X-Airbnb-Api-Key: $apiKey";
        $headers[] = "X-Airbnb-Oauth-Token: $auth";
        $headers[] = "Content-Type: application/json";
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }
        curl_close ($ch);
        $result=json_decode($result);
        if(!isset($result->error))
        {
            return true;
        }
    }
    ////Airbnb LIsting Api
    public function addAirbnbListing($refresh_token,$data,$room_data,$cm_ota_data)
    {
        $airbnbModel=new AirbnbListingDetails();
        $apiKey='4kk6ii3r86yqk42je6o8bdker';
        $auth=$airbnbModel->getAirBnbToken($refresh_token);
        $city=DB::table('city_table')->select('city_name')->where('city_id',$data["city_id"])->first();
        $state=DB::table('state_table')->select('state_name')->where('state_id',$data["state_id"])->first();
        $post_data=array("city" => $city->city_name, "state"=> $state->state_name, "zipcode"=>$data['pin'], "country_code"=> "IN", "name"=> $room_data['room_type'], "lat"=>$data['latitude'], "lng"=> $data['longitude'], "listing_price"=>$room_data['rack_price'],
        "property_type_category"=>"hotel",
        "property_type_group"=>"boutique_hotels_and_more",
        "room_type_category"=> "private_room",
        "listing_currency"=>'INR',
        "person_capacity"=>$room_data['max_people']+$room_data['extra_person'],
        'property_external_id'=>$cm_ota_data->ota_hotel_code,
        'total_inventory_count'=>$room_data['total_rooms']);
        $post_data=json_encode($post_data);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.airbnb.com/v2/listings");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_POST, 1);

        $headers = array();
        $headers[] = "X-Airbnb-Api-Key:$apiKey";
        $headers[] = "X-Airbnb-Oauth-Token: $auth";
        $headers[] = "Content-Type: application/json";
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }
        curl_close ($ch);
        $result=json_decode($result);
        if(!isset($result->error))
        {
             return $result->listing->id;
        }
    }
     ////Airbnb LIsting Api
     public function updateAirbnbListing($refresh_token,$air_bnb_data,$data,$room_data,$cm_ota_data,$room_type_id)
     {
        $airbnbModel=new AirbnbListingDetails();
        $apiKey='4kk6ii3r86yqk42je6o8bdker';
        $auth=$airbnbModel->getAirBnbToken($refresh_token);
        $cm_ota_detail_model=new CmOtaDetails();
        $cm_ota_details=$cm_ota_detail_model->where('hotel_id',$data['hotel_id'])
        ->where('ota_name','Airbnb')
        ->where('is_active',1)
        ->first();
        $ota_id= $cm_ota_details->ota_id;
        $airbnb_listing_id=$this->getOtaRoomType($room_type_id,$ota_id);
        $city=DB::table('city_table')->select('city_name')->where('city_id',$data["city_id"])->first();
        $state=DB::table('state_table')->select('state_name')->where('state_id',$data["state_id"])->first();
        $post_data=array("city" => $city->city_name,
        "state"=> $state->state_name,
        "zipcode"=>$data['pin'],
        "country_code"=> "IN",
        "lat"=>$data['latitude'],
        "lng"=> $data['longitude'],
        "listing_price"=>$room_data['rack_price'],
        "person_capacity"=>$room_data['max_people']+$room_data['extra_person'],
        'property_external_id'=>$cm_ota_data->ota_hotel_code,
        'total_inventory_count'=>$room_data['total_rooms']);
        if(sizeof($air_bnb_data)==7)
        {
            $post_data["property_type_category"]=$air_bnb_data['property_type_category'];
            $post_data["property_type_group"]=$air_bnb_data['property_type_group'];
            $post_data["room_type_category"]= $air_bnb_data['room_type_category'];
            $post_data["bedrooms"] =$air_bnb_data['bedrooms'];
            $post_data["bathrooms"]=$air_bnb_data['bathrooms'];
            $post_data["beds"] = $air_bnb_data['beds'];
            $post_data["name"]=$air_bnb_data['listing_title'];
        }
        if($air_bnb_data['airbnb_status'] == 'new'){
          $post_data["synchronization_category"]="sync_all";
        }
        else{
          $post_data["synchronization_category"]="sync_undecided";
        }
        $post_data=json_encode($post_data);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.airbnb.com/v2/listings/$airbnb_listing_id");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");

        $headers = array();
        $headers[] = "X-Airbnb-API-Key: $apiKey";
        $headers[] = "X-Airbnb-OAuth-Token: $auth";
        $headers[] = "Content-Type: application/json";
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }
        curl_close ($ch);
        // dd($result,$headers,$post_data,$airbnb_listing_id);
        $result=json_decode($result);
        if(!isset($result->error))
        {
            return $result->listing->id;
        }
     }
    ////Airbnb LIsting Api
    public function updateAmenitiesAirbnbListing($refresh_token,$amen,$room_type_id,$hotel_id)
    {
        $airbnbModel=new AirbnbListingDetails();
        $apiKey='4kk6ii3r86yqk42je6o8bdker';
        $auth=$airbnbModel->getAirBnbToken($refresh_token);
        $cm_ota_detail_model=new CmOtaDetails();
        $cm_ota_details=$cm_ota_detail_model->where('hotel_id',$hotel_id)
        ->where('ota_name','Airbnb')
        ->where('is_active',1)
        ->first();
        if($cm_ota_details){
          $ota_id= $cm_ota_details->ota_id;
          $airbnb_liting_id=$this->getOtaRoomType($room_type_id,$ota_id);
          $post_data=array("amenity_categories" => $amen);
          $post_data=json_encode($post_data);
          $ch = curl_init();
          curl_setopt($ch, CURLOPT_URL, "https://api.airbnb.com/v2/listings/$airbnb_liting_id");
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
          curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
          curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");

          $headers = array();
          $headers[] = "X-Airbnb-Api-Key: $apiKey";
          $headers[] = "X-Airbnb-Oauth-Token: $auth";
          $headers[] = "Content-Type: application/json";
          curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

          $result = curl_exec($ch);
          if (curl_errno($ch)) {
              echo 'Error:' . curl_error($ch);
          }
          curl_close ($ch);
          $result=json_decode($result);
          if(!isset($result->error)){
            return true;
          }
        }
    }
    public function getAirbnbData(int $hotel_id,int $room_type_id, Request $request)
    {
        $airbnbListingDetails=new AirbnbListingDetails();
        $conditions=array('hotel_id'=>$hotel_id,'room_type_id'=>$room_type_id);
        $res=$airbnbListingDetails->where($conditions)->first();

        if(!empty($res))
        {   $res->guest_controls=json_decode($res->guest_controls);
            $res1=array("status"=>1,"message"=>"Airbnb listing details retrieved successfullly", "data"=>$res);
            return response()->json($res1);
        }
        else
        {
            $res=array("status"=>0,"message"=>"No Airbnb listing records found");
            return response()->json($res);
        }
    }
    public function getRefreshToken($company_id)
    {
        $company=new CompanyDetails();
        $comp_details=$company->where('company_id',$company_id)->select('airbnb_refresh_token')->first();
        $refresh_token=$comp_details->airbnb_refresh_token;
        if($refresh_token!="")
        {
            return  $refresh_token;
        }
        else
        {
            return 0;
        }
    }
    public function addAirBnbDetails(Request $request)
    {
        $masterRoomType = new MasterRoomType();
        $cm_ota_details=new CmOtaDetails();
        $airbnbListingDetails= new AirbnbListingDetails();
        $failure_message='Airbnb Details Saving Failed';
        $data=$request->all();
        $guest_controls=$this->getGuestControl($data['guest_controls']);
        $data['guest_controls']=json_encode($guest_controls);
        if($airbnbListingDetails->fill($data)->save())
        {
            $data['guest_controls']=json_decode( $data['guest_controls']);
            $airbnb_data=array(
                "property_type_category"=>  $data['property_type_category'],
                "property_type_group"=> $data['property_type_group'],
                "room_type_category"=> $data['room_type_category'],
                "bedrooms" =>$data['bed_room'],
                "bathrooms"=>$data['bath_room'],
                "beds" => $data['beds']
                );
            $hotel_info=HotelInformation::where('hotel_id',$data['hotel_id'])->first();
            $cm_ota_data=$cm_ota_details->where('hotel_id',$data['hotel_id'])->where('ota_name','Airbnb')->first();
            $conditions=array('room_type_id'=>$data['room_type_id'],'is_trash'=>0);
            $room_data=MasterRoomType::select('*')->where($conditions)->first();
            $refresh_token=$this->getRefreshToken($hotel_info->company_id);
            if($refresh_token)
            {
            $update_listing=$this->updateAirbnbListing($refresh_token,$airbnb_data,$hotel_info,$room_data,$cm_ota_data,$data['room_type_id']);
            $airbnb_data=array(
                "space"=>  $data['space'],
                "access"=> $data['access'],
                "interaction"=>$data['interaction'],
                "neighborhood_overview"=>$data['neighbourhood'],
                "transit"=>$data['transit'],
                "notes"=>$data['notes_descr'],
                "house_rules"=>$data['house_rules']
                );
            $airbnb_price_data=array(
                "cleaning_fee" =>  $data['cleaning_fee'],
                "security_deposit"=>  $data['security_deposit']
                );
            $update_desc=$this->updateAirbnbDescription($refresh_token,$airbnb_data,$data['room_type_id'],$data['hotel_id']);
            if($data['cancellation_policy_category']!="")
            {
                $update_cancel_policy=$this->airbnbCancellationPolicy($refresh_token,$data,$data['room_type_id'],$data['hotel_id']);
            }
            if($data['cleaning_fee']!=0 && $data['security_deposit']!=0)
            {
                $update_fees=$this->airbnbPriceSettings($refresh_token,$airbnb_price_data,$data['room_type_id'],$data['hotel_id']);
            }
            }
            $res=array('status'=>1,"message"=>"Airbnb details saved successfully");
            return response()->json($res);
        }
        else
        {
            $res=array('status'=>-1,"message"=>$failure_message);
            $res['errors'][] = "Saving failed";
            return response()->json($res);
        }
    }
    /**
     * AirBnbDetails
     * Create a update record of air bnb details.
     * @auther Godti Vinod
     * @return Hotel air bnb details saving status
     * function addnew for createing a new air bnb details
    **/
    public function updateAirBnbDetails(int $airbnb_details_id,Request $request)
    {
        $masterRoomType = new MasterRoomType();
        $cm_ota_details=new CmOtaDetails();
        $airbnbListingDetails= new AirbnbListingDetails();
        $failure_message='Airbnb Details Saving Failed';
        $data=$request->all();
        $guest_controls=$this->getGuestControl($data['guest_controls']);
        $data['guest_controls']=json_encode($guest_controls);
        $airbnbDetails = AirbnbListingDetails::where('id',$airbnb_details_id)->first();
        if($airbnbDetails->fill($data)->save())
        {
            $data['guest_controls']=json_decode($data['guest_controls']);
            $airbnb_data=array(
                "property_type_category"=>  $data['property_type_category'],
                "property_type_group"=> $data['property_type_group'],
                "room_type_category"=> $data['room_type_category'],
                "bedrooms" =>$data['bed_room'],
                "bathrooms"=>$data['bath_room'],
                "beds" => $data['beds'],
                "listing_title" => $data['listing_title'],
                "airbnb_status" => $data['airbnb_status']
                );
            $hotel_info=HotelInformation::where('hotel_id',$airbnbDetails['hotel_id'])->first();
            $cm_ota_data=$cm_ota_details->where('hotel_id',$airbnbDetails['hotel_id'])->where('ota_name','Airbnb')->first();
            $conditions=array('room_type_id'=>$airbnbDetails['room_type_id'],'is_trash'=>0);
            $room_data=MasterRoomType::select('*')->where($conditions)->first();
            $refresh_token=$this->getRefreshToken($hotel_info->company_id);
            if($refresh_token)
            {
            $update_listing=$this->updateAirbnbListing($refresh_token,$airbnb_data,$hotel_info,$room_data,$cm_ota_data,$airbnbDetails['room_type_id']);
            $airbnb_data=array(
                "space"=>  $data['space'],
                "access"=> $data['access'],
                "interaction"=>$data['interaction'],
                "neighborhood_overview"=>$data['neighbourhood'],
                "transit"=>$data['transit'],
                "notes"=>$data['notes_descr'],
                "house_rules"=>$data['house_rules']
                );
            $airbnb_price_data=array(
                "cleaning_fee" =>  $data['cleaning_fee'],
                "security_deposit"=>  $data['security_deposit'],
                "guests_included" =>  $room_data['max_people']
                );

            $update_desc=$this->updateAirbnbDescription($refresh_token,$airbnb_data,$data['room_type_id'],$data['hotel_id']);
            if($data['cancellation_policy_category']!="")
            {
                $update_cancel_policy=$this->airbnbCancellationPolicy($refresh_token,$data,$data['room_type_id'],$data['hotel_id']);
            }
            if($data['cleaning_fee']!=0 && $data['security_deposit']!=0)
            {
                $update_fees=$this->airbnbPriceSettings($refresh_token,$airbnb_price_data,$data['room_type_id'],$data['hotel_id']);
            }
            }
            $res=array('status'=>1,"message"=>"Airbnb details updated successfully");
            return response()->json($res);
        }
        else
        {
            $res=array('status'=>-1,"message"=>$failure_message);
            $res['errors'][] = "Updation failed";
            return response()->json($res);
        }
    }
    public function checkStatusAirbnb(int $company_id,Request $request)
    {
        $company= new CompanyDetails();
        $comp_details=$company->where('company_id',$company_id)->select('airbnb_refresh_token')->first();
        if($comp_details->airbnb_refresh_token)
        {
            $res=array('status'=>1,"message"=>"Airbnb api exists");
            return response()->json($res);
        }
        else
        {
            $res=array('status'=>0,"message"=>"Airbnb credentials saving failed!");
            return response()->json($res);
        }
    }
}
