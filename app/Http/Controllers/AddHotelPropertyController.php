<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\UserCredential;
use App\HotelUserProfile;
use App\HotelUserCredential;
use App\City;
use DB;
use Carbon\Carbon;
use App\HotelInformation;
use Illuminate\Support\Facades\Storage;
use App\ImageTable;
use App\AdminUser;
use App\Role;
use App\Reseller;
use App\CmOtaDetails;
// use App\AirbnbListingDetails;
use App\CompanyDetails;
use App\TemplateMaster;
use App\Http\Controllers\Controller;

class AddHotelPropertyController extends Controller
{
    protected $ipService;
    public function __construct(IpAddressService $ipService)
    {
        $this->ipService = $ipService;
        //    $this->airbnbService=$airbnbService;
    }
    //validation rules
    //@auther : Shankar Bag
    //@Story : This array will used at the time of back-end validation

    private $rules = array(
        'company_id' => 'required | numeric',
        'hotel_name' => 'required | max:50',
        "email_id" => 'required | emails',
        'mobile' => 'required | mobiles',
        'hotel_geo_location' => array(
            'required',
            'regex:/^(\-?\d+(\.\d+)?),\s*(\-?\d+(\.\d+)?)$/'
        ),
        "city_id" => 'required | numeric',
        "state_id" => 'required | numeric',
        "country_id" => 'required | numeric',
        'pin' => 'digits:6',
        "advance_booking_days" => 'numeric',
        "partial_payment" => 'numeric',
        "partial_pay_amt" => 'numeric',
        "round_clock_checkin_out" => 'numeric',
        //"whatsapp_no" => 'digits:10'
    );

    //Custom Error Messages
    private $messages = [
        'company_id.required' => 'Company id name field is required',
        'user_id.required' => 'User id field is required',
        'legal_head.required' => 'Legal head field is required',
        'email_id.required' => 'Minimum one email id is required',
        'email_id.emails' => 'All email ids should be valid',
        'mobile.mobiles' => 'All mobile number should be 10 digit numbers',
        'zip.required' => 'Zip code is required'
    ];
    //validation rules
    //@auther : Godti Vinod
    //@Story : This array will used at the time of back-end validation
    private $rulesExt = array(
        'hotel_id' => 'required | numeric',
        'image_ids' => 'required'
    );
    //Custom Error Messages
    private $messagesExt = [
        'hotel_id.required' => 'Hotel id field is required',
        'hotel_id.numeric' => 'Hotel id  field should be numeric',
        'image_ids.required' => 'Images id required'
    ];
    /**
     * Add a new hotel
     * @auther : Shankar Bag
     * @Story : by this function we can add a new hotel brand row to 'hotel_informations' table.
     *          Before inserting new row , this function will be check , whether this brand is present or not.
     *          If present then it will return the failuer Message. If not present then it will add it and return the success message.
     *
     * @return hotel-info user addition status
     **/

    public function addNewHotelBrand(Request $request)
    {
        $data = $request->all();
        $failure_message = "Hotel information saving failed";
        $hotel_info = new HotelInformation();
        $validator = Validator::make($request->all(), $this->rules, $this->messages);
        if ($validator->fails()) {
            return response()->json(array('status' => 0, 'message' => $failure_message, 'errors' => $validator->errors()));
        }
        foreach ($data['email_id'] as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $res = array('status' => 0, "message" => "Invalid email format");
                return response()->json($res);
            }
        }
        foreach ($data['mobile'] as $mobile) {
            if (!preg_match('/^[0-9]{10}+$/', $mobile)) {
                $res = array('status' => 0, "message" => "Invalid Mobile number format");
                return response()->json($res);
            }
        }
        // Separting the latitude and longitude
        $lat_lon = explode(',', $data['hotel_geo_location']);
        $data['latitude'] = $lat_lon[0];
        $data['longitude'] = $lat_lon[1];
        $data['email_id'] = implode(',', $data['email_id']);
        $data['land_line'] = implode(',', $data['land_line']);
        $data['mobile'] = implode(',', $data['mobile']);
        $data['exterior_image'] = 3; //By default image of the hotel from image table
        $data['client_ip'] = $this->ipService->getIPAddress();
        $data['anytime_checkinout'] = $data['anytimecheckinout'];
        if (isset($request->auth->admin_id)) {
            $data['user_id'] = $request->auth->admin_id;
        } elseif (isset($request->auth->super_admin_id)) {
            $data['user_id'] = $request->auth->super_admin_id;
        } elseif (isset($request->auth->id)) {
            $data['user_id'] = $request->auth->id;
        } else {
            $data['user_id'] = $data['admin_id'];
        }
        if ($hotel_info->fill($data)->save()) {
            // try{
            //   $airbnb_call = $this->airbnbController->addHotelAirbnbBrand($data,$hotel_info);
            // }
            // catch(Execption $e){
            //     return true;
            // }
            $get_company_id = HotelInformation::select('company_id')
                ->where('hotel_id', $hotel_info->hotel_id)
                ->first();
            $check_gems_status = CompanyDetails::select('creation_mode')
                ->where('company_id', $get_company_id->company_id)
                ->first();
            if ($check_gems_status) {
                $update_gems = PmsAccount::select('hotels')
                    ->where('name', 'GEMS')
                    ->first();
                $array_creation = explode(',', $update_gems->hotels);
                array_push($array_creation, $hotel_info->hotel_id);
                $array_creation = implode(',', $array_creation);
                $update_gems = PmsAccount::where('name', 'GEMS')
                    ->update($array_creation);
            }
            $res = array('status' => 1, "message" => "Hotel Informations saved successfully", 'hotel_id' => $hotel_info->hotel_id);
            return response()->json($res);
        } else {
            $res = array('status' => -1, "message" => $failure_message);
            $res['errors'][] = "Internal server error";
            return response()->json($res);
        }
    }


    public function updateNewHotelBrand(Request $request, string $id)
    {
        $failure_message = "Hotel information saving failed";
        $validator = Validator::make(
            $request->all(),
            [
                "company_id" => "required",
                "hotel_name" => "required",
                "admin_id" => "required",
                "mobile" => "required",
                "email_id" => "required",
                "hotel_description" => "required"
            ]
        );
        if ($validator->fails()) {
            return response()->json(["status" => "0", 'message' => $failure_message, "errors" => $validator->errors()]);
        }

        $hotel_info = HotelInformation::where('hotel_id', $id)->first();
        $data = $request->all();
        $data['client_ip'] = $this->ipService->getIPAddress();
        $company = new CompanyDetails();
        if ($hotel_info->fill($data)->save()) {
            // try{
            //   $airbnb_call = $this->airbnbController->updateHotelAirbnbBrand($data,$hotel_info);
            // }
            // catch(Exception $e){
            //   return true;
            // }
            $res = array('status' => 1, "message" => "Hotel Informations updated successfully");
            return response()->json($res);
        } else {
            $res = array('status' => -1, "message" => $failure_message);
            $res['errors'][] = "Internal server error";
            return response()->json($res);
        }
    }


    public function updateCheckinCheckOut(Request $request, string $id)
    {
        $failure_message = "Check-in / Check-out Time Saving Failed";
        $validator = Validator::make(
            $request->all(),
            [
                "check_in" => "required",
                "check_out" => "required"
            ]
        );

        if ($validator->fails()) {
            return response()->json(["status" => "0", 'message' => $failure_message, "errors" => $validator->errors()]);
        }

        $hotel_info = HotelInformation::where('hotel_id', $id)->first();
        $data = $request->all();
        $data['client_ip'] = $this->ipService->getIPAddress();
        if ($hotel_info->fill($data)->save()) {
            $res = array('status' => 1, "message" => "Check-in / Check-out Time Updated Successfully");
            return response()->json($res);
        } else {
            $res = array('status' => -1, "message" => $failure_message);
            $res['errors'][] = "Internal server error";
            return response()->json($res);
        }
    }



    public function updateHotelBrand(Request $request, string $id)
    {
        $data = $request->all();
        // print_r($data);exit;
        // $cmotadetails = new CmOtaDetails();
        $failure_message = "Hotel information saving failed";
        // Validate UUID
        $validator = Validator::make($request->all(), $this->rules, $this->messages);
        if ($validator->fails()) {
            return response()->json(array('status' => 0, 'message' => $failure_message, 'errors' => $validator->errors()));
        }
        $data = $request->all();
        foreach ($data['email_id'] as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $res = array('status' => 0, "message" => "Invalid email format");
                return response()->json($res);
            }
        }
        foreach ($data['mobile'] as $mobile) {
            if (!preg_match('/^[0-9]{10}+$/', $mobile)) {
                $res = array('status' => 0, "message" => "Invalid Mobile number format");
                return response()->json($res);
            }
        }
        $hotel_info = HotelInformation::where('hotel_id', $id)->first();
        $data = $request->all();
        // Convert all php array to PG Array
        $lat_lon = explode(',', $data['hotel_geo_location']);
        $data['latitude'] = $lat_lon[0];
        $data['longitude'] = $lat_lon[1];
        $data['email_id'] = implode(',', $data['email_id']);
        $data['land_line'] = implode(',', $data['land_line']);
        $data['mobile'] = implode(',', $data['mobile']);
        $data['client_ip'] = $this->ipService->getIPAddress();
        $company = new CompanyDetails();
        if ($hotel_info->fill($data)->save()) {
            // try{
            //   $airbnb_call = $this->airbnbController->updateHotelAirbnbBrand($data,$hotel_info);
            // }
            // catch(Exception $e){
            //   return true;
            // }
            $res = array('status' => 1, "message" => "Hotel Informations updated successfully");
            return response()->json($res);
        } else {
            $res = array('status' => -1, "message" => $failure_message);
            $res['errors'][] = "Internal server error";
            return response()->json($res);
        }
    }



    // DELETE RECORD FROM HOTELS DETAILS
    /**
     * Delete Hotel Info Details
     * @auther : Shankar Bag
     * @Story : by this function we can delete a hotel brand having the UUID coming from URL.
     *          It will update the status of 'is_trash' column to=> 1, of 'hotel_informations' table
     *          'is_trash' Default status : 0; Updated status :1
     *
     * @return hotel-info deleting status
     **/
    public function deleteHotelInfo(Request $request, string $uuid)
    {
        // Validate UUID
        $failure_message = 'Hotel Info deletion failed';
        $uuid_validator = Validator::make(['uuid' => $uuid], ['uuid' => 'uuid'], $this->messages);
        if ($uuid_validator->fails()) {
            return response()->json(array('status' => 0, 'message' => $failure_message, 'errors' => $uuid_validator->errors()));
        }
        $cur_time = Carbon::now()->toDateTimeString();
        $uid = $request->auth->id;
        // Enable status
        if (HotelInformation::where('hotel_property_list_uuid', $uuid)->update(['is_trash' => 1], ['updated_at' => $cur_time], ['updated_by' => $uid])) {
            $res = array('status' => 1, "message" => 'Hotel Info deleted successfully');
            return response()->json($res);
        } else {
            $res = array('status' => -1, "message" => $failure_message);
            $res['errors'][] = "Internal server error";
            return response()->json($res);
        }
    }

    // DISABLE RECORD FROM HOTELS DETAILS
    /**
     * Disable Hotel Info Details
     * @auther : Shankar Bag
     * @Story : by this function we can disable a hotel brand having the UUID coming from URL.
     *          It will update the status of 'is_enable' column to=> 0, of 'hotel_informations' table
     *          'is_trash' Default status : 1; Updated status :0
     *
     * @return hotel-info disabling status
     **/
    public function disableHotelInfo(string $uuid, Request $request)
    {
        // Validate UUID
        $failure_message = 'Hotel Info disable failed';
        $uuid_validator = Validator::make(['uuid' => $uuid], ['uuid' => 'uuid'], $this->messages);
        if ($uuid_validator->fails()) {
            return response()->json(array('status' => 0, 'message' => $failure_message, 'errors' => $uuid_validator->errors()));
        }
        $cur_time = Carbon::now()->toDateTimeString();
        $uid = $request->auth->id;
        // Enable status
        if (HotelInformation::where('hotel_property_list_uuid', $uuid)->update(['is_enable' => 0], ['updated_at' => $cur_time], ['updated_by' => $uid])) {
            $res = array('status' => 1, "message" => 'Hotel Info disabled successfully');
            return response()->json($res);
        } else {
            $res = array('status' => -1, "message" => $failure_message);
            $res['errors'][] = "Internal server error";
            return response()->json($res);
        }
    }
    /* @auther : Shankar Bag
     * @Story : GET All hotel details Including Deleted,Disabled
     * @return All Select Data
    **/
    public function getAllHotelData(Request $request)
    {
        $hotel_user_data = HotelInformation::orderBy('hotel_id')->get();
        return response()->json($hotel_user_data);
    }
    /* @auther : Shankar Bag
     * @Story : GET All hotel details Excluding Deleted,Disabled
     * @return All Select Data
    **/
    public function getAllRunningHotelData(Request $request)
    {
        $hotel_user_data = HotelInformation::where('is_trash', 0)->where('is_enable', 1)->orderBy('id')->get();
        return response()->json($hotel_user_data);
    }
    /* @auther : Shankar Bag
     * @Story : GET All Deleted hotel details
     * @return All Select Data
    **/
    public function getAllDeletedHotelData(Request $request)
    {
        $hotel_user_data = HotelInformation::where('is_trash', 1)->orderBy('id')->get();
        return response()->json($hotel_user_data);
    }
    /* @auther : Shankar Bag
     * @Story : GET All Disabled hotel details
     * @return All Select Data
    **/
    public function getAllDisabledHotelData(Request $request)
    {
        $hotel_user_data = HotelInformation::where('is_enable', 0)->orderBy('id')->get();
        return response()->json($hotel_user_data);
    }
    /* @auther : Shankar Bag
     * @Story : GET All hotel details having Same country_id
     * @Param : It will take the country id as the parameter
     * @return All Select Data
    **/
    public function getAllRunningHotelDataByCountryId(Request $request, string $country_id)
    {
        $hotel_user_data = HotelInformation::where('is_trash', 0)->where('is_enable', 1)->where('hotel_country_id', $country_id)->orderBy('id')->get();
        return response()->json($hotel_user_data);
    }
    /* @auther : Shankar Bag
     * @Story : GET All hotel details having Same country_id and state_id
     * @Param : It will take the country id, State_id as the parameter
     * @return All Select Data
    **/
    public function getAllRunningHotelDataByCountryAndStateId(Request $request, string $country_id, string $state_id)
    {
        $hotel_user_data = HotelInformation::where('is_trash', 0)->where('is_enable', 1)->where('hotel_country_id', $country_id)->where('hotel_state_id', $state_id)->orderBy('id')->get();
        return response()->json($hotel_user_data);
    }
    /* @auther : Shankar Bag
     * @Story : GET All hotel details having Same country_id and state_id and city_id
     * @Param : It will take the country id, State_id, city_id as the parameter
     * @return All Select Data
    **/
    public function getAllRunningHotelDataByCountryAndStateAndCityId(Request $request, string $country_id, string $state_id, string $city_id)
    {
        $hotel_user_data = HotelInformation::where('is_trash', 0)->where('is_enable', 1)->where('hotel_country_id', $country_id)->where('hotel_state_id', $state_id)->where('hotel_city_id', $city_id)->orderBy('id')->get();
        return response()->json($hotel_user_data);
    }
    /* @auther : Shankar Bag
     * @Story : GET only hotel details having the uuid
     * @Param : It will take the uuid as the parameter
     * @return All Select Data
    **/
    public function getAllRunningHotelDataByid(Request $request, string $hotel_id)
    {
        $hotel_data = DB::table('hotels_table')->select('*')->where('hotel_id', $hotel_id)->first();
        $image = explode(',', $hotel_data->exterior_image);
        if ($image[0] == null || $image[0] == '' || $image[0] == 0) {
            $image[0] = 3;
        }
        $img_name = DB::table('image_table')->select('image_name')->where('image_id', $image[0])->first();
        if (!isset($img_name->image_name)) {
            $img_name = DB::table('image_table')->select('image_name')->where('image_id', 3)->first();
            $hotel_data->exterior_image = $img_name->image_name;
        } else {
            $hotel_data->exterior_image = $img_name->image_name;
        }
        return response()->json($hotel_data);
    }
    public function getAllRunningHotelDataByidBE(Request $request, string $hotel_id)
    {
        $hotel_data = DB::table('hotels_table')->select('*')->where('hotel_id', $hotel_id)->first();
        $image = explode(',', $hotel_data->exterior_image);
        if ($image[0] == null || $image[0] == '' || $image[0] == 0) {
            $image[0] = 3;
        }
        $img_name = DB::table('image_table')->select('image_name')->where('image_id', $image[0])->first();
        if (!isset($img_name->image_name)) {
            $img_name = DB::table('image_table')->select('image_name')->where('image_id', 3)->first();
            $hotel_data->exterior_image = $img_name->image_name;
        } else {
            $hotel_data->exterior_image = $img_name->image_name;
        }
        $city = City::where('city_id', $hotel_data->city_id)->select('city_name')->first();
        $hotel_data->city_id = $city->city_name;
        return response()->json(array($hotel_data));
    }
    /* @auther : Shankar Bag
     * @Story : GET only hotel details with legal name matching
     * @Param : It will take the input text as the parameter
     * @return All Select Data
    **/
    public function getAllRunningHotelDataByName(Request $request, string $name)
    {
        $hotel_user_data = HotelInformation::where('is_trash', 0)->where('is_enable', 1)->Where('brand_name', 'like', '%' . $name . '%')->orderBy('id')->get();
        return response()->json($hotel_user_data);
    }
    /* @auther : Shankar Bag
     * @Story : GET  hotel details having same group
     * @Param : It will take the user_Login UUID as the parameter
     * @return All Select Data
    **/
    public function getAllRunningHotelDataByGroup(Request $request, string $group_uuid)
    {
        $uuid_validator = Validator::make(['uuid' => $group_uuid], ['uuid' => 'uuid'], $this->messages);
        if ($uuid_validator->fails()) {
            return response()->json(array('status' => 0, 'message' => "Invalid UUID", 'errors' => $uuid_validator->errors()));
        }
        $hotel_admin_info = HotelUserCredential::where('is_trash', 0)->where('is_enable', 1)->Where('user_credentials_uuid', $group_uuid)->first();
        $pg_arr = $hotel_admin_info->child_hotels;
        $php_arr =  $hotel_admin_info->postgresArrayToArray($pg_arr);
        $hotels = HotelInformation::whereIn('id', $php_arr)->get();
        return response()->json($hotels);
    }
    // Get hotals of company
    /* @auther : Shankar Bag
     * @Story : GET  hotel details having same group
     * @Param : It will take the user_Login UUID as the parameter
     * @return All Select Data
    **/
    public function getAllHotelsDataByCompanyDetails(Request $request, string $comp_hash, string $auth_from, string $company_id)
    {
        $role_id = "";
        $url = "";
        $billing_details = DB::table('billing_table')->select('product_name')->where('company_id', $company_id)->first();
        if (isset($request->auth->id)) {
            $verify_reseller = CompanyDetails::where('company_id', $company_id)->where('reseller_id', $request->auth->id)->first();
            $url = Reseller::select('return_url')->where('id', $request->auth->id)->first();
            $url = $url->return_url;
        } else {
            $verify_reseller = 1;
        }
        if ($auth_from != "inside") {
            $ip = $this->ipService->getIPAddress();
            $reseller_id = $request->auth->id;
            $data = Reseller::select("whitelist_ip")->where('id', $reseller_id)->first();
            $whitelist_ip = explode(",", $data->whitelist_ip[0]);
            if (!in_array($ip, $whitelist_ip)) {
                $res = array('status' => -1, "message" => "accesss using your Valid IP", 'url' => $url);
                return response()->json($res);
            }
        }
        if ($verify_reseller) {
            if (isset($request->auth->admin_id)) {
                $role_id = $request->auth->role_id;
                $user_id = $request->auth->admin_id;
            } elseif (isset($request->auth->super_admin_id)) {
                $role_id = 1;
                $user_id = $request->auth->super_admin_id;
            } elseif (isset($request->auth->id)) {
                $role_id = 1;
                $user_id = $request->auth->id;
            }
            $count = 0;
            $company_hash_code = openssl_digest($company_id, 'sha512');
            if ($comp_hash != $company_hash_code) {
                $res = array('status' => 0, "message" => "Hotel list retrival failed");
                $res['errors'][] = "Please provide valid company";
                return response()->json($res);
            }
            $roles = Role::select('role_name')->where("role_id", '=', 1)->first();
            if ($roles->role_name == env('HOTEL_ADMIN')) {
                $hotels = HotelInformation::where('company_id', $company_id)->select('*')->get();
                $admindetails = array();
                $i = 0;
                if (sizeof($hotels) > 0) {
                    foreach ($hotels as $hotel) {
                        $admindetails[$i] = AdminUser::select('admin_id')->where('company_id', $company_id)->where('role_id', 1)->first();
                        $ext_images = explode(',', $hotel['exterior_image']);
                        if (is_array($ext_images) && !$ext_images) {
                            $images = DB::table('image_table')
                                ->select('image_id', 'image_name')
                                ->where('image_id', $ext_images[0])
                                ->first();
                            ($images) ?
                                $hotel['exterior_image'] = $images->image_name
                                :
                                '';
                        }
                        $i++;
                    }
                }
                if (sizeof($hotels) > 0 && sizeof($admindetails) > 0) {
                    return (isset($request->auth->id)) ?
                        response()->json(array('status' => 1, "message" => "Properties retrieved successfully!", "data" => $hotels, "adminData" => $admindetails, 'url' => $url, 'product_details' => $billing_details->product_name))
                        :
                        response()->json(array('status' => 1, "message" => "Properties retrieved successfully!", "data" => $hotels, "adminData" => $admindetails, 'product_details' => $billing_details->product_name));
                } else {
                    $res = array('status' => 0, "message" => "No property created yet!");
                    return response()->json($res);
                }
            } elseif ($roles->role_name == env('HOTEL_USER')) {
                $hotels = HotelInformation::where('user_id', $user_id)->get();

                if (sizeof($hotels) > 0) {
                    foreach ($hotels as $hotel) {
                        $ext_images = explode(',', $hotel['exterior_image']);
                        if (is_array($ext_images)) {
                            $images = DB::table('image_table')
                                ->select('image_id', 'image_name')
                                ->where('image_id', $ext_images[0])
                                ->first();
                            ($images) ?
                                $hotel['exterior_image'] = $images->image_name
                                : '';
                        }
                    }
                }
                if (sizeof($hotels) > 0) {
                    return (isset($request->auth->id)) ?
                        response()->json(array('status' => 1, "message" => "Properties retrieved successfully!", "data" => $hotels, 'url' => $url))
                        :
                        response()->json(array('status' => 1, "message" => "Properties retrieved successfully!", "data" => $hotels));
                } else {
                    $res = array('status' => 0, "message" => "No property created yet!");
                    return response()->json($res);
                }
            }
        } else {
            $res = array('status' => -1, "message" => "Sorry! you are not authorized", 'url' => $url);
            return response()->json($res);
        }
    }



    public function getAllHotelsDataByCompany(Request $request, string $comp_hash, string $company_id)
    {
        if (isset($request->auth->admin_id)) {
            $role_id = $request->auth->role_id;
            $user_id = $request->auth->admin_id;
        } elseif (isset($request->auth->super_admin_id)) {
            $role_id = 1;
            $user_id = $request->auth->super_admin_id;
        } elseif (isset($request->auth->id)) {
            $role_id = 1;
            $user_id = $request->auth->id;
        }

        $count = 0;
        $company_hash_code = openssl_digest($company_id, 'sha512');
        if ($comp_hash != $company_hash_code) {
            $res = array('status' => 0, "message" => "Hotel list retrival failed");
            $res['errors'][] = "Please provide valid company";
            return response()->json($res);
        }
        $roles = Role::select('role_name')->where("role_id", '=', $role_id)->first();
        $hotels = "";
        if ($roles->role_name == env('HOTEL_ADMIN')) {
            $hotels = HotelInformation::where('company_id', $company_id)->select('*')->get()->toArray();
        } elseif ($roles->role_name == env('HOTEL_USER')) {
            // $hotels=HotelInformation::where('user_id',$user_id)->get();

            $user_data = DB::table('admin_table')->select('hotel_id')->where('admin_id', $user_id)->first();
            $user_data->hotel_id = array_map('intval', array_filter(explode(',', $user_data->hotel_id), 'is_numeric'));

            if ($user_data && $user_data->hotel_id) {
                $hotels = HotelInformation::whereIn('hotel_id', $user_data->hotel_id)->get();
            }
        }

        $admindetails = array();
        $i = 0;
        if (sizeof($hotels) > 0) {
            foreach ($hotels as $hotel) {
                $resultsingle['hotel_id'] = $hotel['hotel_id'];
                $resultsingle['company_id'] = $hotel['company_id'];
                $resultsingle['user_id'] = $hotel['user_id'];
                $resultsingle['hotel_name'] = $hotel['hotel_name'];
                $resultsingle['hotel_description'] = $hotel['hotel_description'];
                $resultsingle['country_id'] = $hotel['country_id'];
                $resultsingle['state_id'] = $hotel['state_id'];
                $statename = DB::table('kernel.state_table')->select('state_name')->where('state_id', $hotel['state_id'])->first();
                $resultsingle['state_name'] = $statename->state_name;
                $resultsingle['city_id'] = $hotel['city_id'];
                $cityname = DB::table('kernel.city_table')->select('city_name')->where('city_id', $hotel['city_id'])->first();
                $resultsingle['city_name'] = $cityname->city_name;
                $resultsingle['hotel_address'] = $hotel['hotel_address'];
                $resultsingle['exterior_image'] = $hotel['exterior_image'];
                $resultsingle['interior_image'] = $hotel['interior_image'];
                $resultsingle['facility'] = $hotel['facility'];
                $resultsingle['latitude'] = $hotel['latitude'];
                $resultsingle['longitude'] = $hotel['longitude'];
                $resultsingle['check_in'] = $hotel['check_in'];
                $resultsingle['check_out'] = $hotel['check_out'];
                $resultsingle['round_clock_check_in_out'] = $hotel['round_clock_check_in_out'];
                $resultsingle['hotel_policy'] = $hotel['hotel_policy'];
                $resultsingle['hotel_grade'] = $hotel['hotel_grade'];
                $resultsingle['pin'] = $hotel['pin'];
                $resultsingle['mobile'] = $hotel['mobile'];
                $resultsingle['land_line'] = $hotel['land_line'];
                $resultsingle['email_id'] = $hotel['email_id'];
                $resultsingle['be_opt'] = $hotel['be_opt'];
                $resultsingle['child_policy'] = $hotel['child_policy'];
                $resultsingle['cancel_policy'] = $hotel['cancel_policy'];
                $resultsingle['distance_from_air'] = $hotel['distance_from_air'];
                $resultsingle['distance_from_rail'] = $hotel['distance_from_rail'];
                $resultsingle['airport_name'] = $hotel['airport_name'];
                $resultsingle['rail_station_name'] = $hotel['rail_station_name'];
                $resultsingle['nearest_tourist_places'] = $hotel['nearest_tourist_places'];
                $resultsingle['bus_station_name'] = $hotel['bus_station_name'];
                $resultsingle['distance_from_bus'] = $hotel['distance_from_bus'];
                $resultsingle['terms_and_cond'] = $hotel['terms_and_cond'];
                $resultsingle['facebook_link'] = $hotel['facebook_link'];
                $resultsingle['twitter_link'] = $hotel['twitter_link'];
                $resultsingle['tripadvisor_link'] = $hotel['tripadvisor_link'];
                $resultsingle['client_ip'] = $hotel['client_ip'];
                $resultsingle['created_date_time'] = $hotel['created_date_time'];
                $resultsingle['updated_date_time'] = $hotel['updated_date_time'];
                $resultsingle['partial_payment'] = $hotel['partial_payment'];
                $resultsingle['partial_pay_amt'] = $hotel['partial_pay_amt'];
                $resultsingle['tour_info'] = $hotel['tour_info'];
                $resultsingle['current_day_booking'] = $hotel['current_day_booking'];
                $resultsingle['sac_number'] = $hotel['sac_number'];
                $resultsingle['advance_booking_days'] = $hotel['advance_booking_days'];
                $resultsingle['star_of_property'] = $hotel['star_of_property'];
                $resultsingle['whatsapp_no'] = $hotel['whatsapp_no'];
                $resultsingle['linked_in_link'] = $hotel['linked_in_link'];
                $resultsingle['instagram_link'] = $hotel['instagram_link'];
                $resultsingle['holiday_iq_link'] = $hotel['holiday_iq_link'];
                $resultsingle['lisa'] = $hotel['lisa'];
                $resultsingle['status'] = $hotel['status'];
                $resultsingle['is_overseas'] = $hotel['is_overseas'];
                $resultsingle['created_at'] = $hotel['created_at'];
                $resultsingle['created_at'] = $hotel['updated_at'];
                $resultsingle['subscription_customer_id'] = $hotel['subscription_customer_id'];
                $resultsingle['tax_in'] = $hotel['tax_in'];
                $resultsingle['sendgrid_sender_id'] = $hotel['sendgrid_sender_id'];
                $resultsingle['hotels_tablecol'] = $hotel['hotels_tablecol'];
                $resultsingle['property_sub_type_id'] = $hotel['property_sub_type_id'];
                $resultsingle['property_type_id'] = $hotel['property_type_id'];
                $result[] = $resultsingle;
                if ($roles->role_name == env('HOTEL_ADMIN')) {
                    $admindetails[$i] = AdminUser::select('admin_id')->where('company_id', $company_id)->where('role_id', 1)->first();
                }
                $ext_images = explode(',', $hotel['exterior_image']);
                if (is_array($ext_images) && !$ext_images) {
                    $images = DB::table('image_table')
                        ->select('image_id', 'image_name')
                        ->where('image_id', $ext_images[0])
                        ->first();
                    ($images) ?
                        $hotel['exterior_image'] = $images->image_name
                        : '';
                }
                $i++;
            }
        }
        if (sizeof($hotels) > 0 && sizeof($admindetails) > 0) {
            return (isset($request->auth->id)) ?
                response()->json(array('status' => 1, "message" => "Properties retrieved successfully!", "data" => $result, "adminData" => $admindetails))
                :
                response()->json(array('status' => 1, "message" => "Properties retrieved successfully!", "data" => $result, "adminData" => $admindetails));
        } elseif (sizeof($hotels) > 0 && sizeof($admindetails) == 0) {
            return (isset($request->auth->id)) ?
                response()->json(array('status' => 1, "message" => "Properties retrieved successfully!", "data" => $result))
                :
                response()->json(array('status' => 1, "message" => "Properties retrieved successfully!", "data" => $result));
        } else {
            $res = array('status' => 0, "message" => "No property created yet!");
            return response()->json($res);
        }
    }




    public function getAllHotelsDataByCompany1111111111111(Request $request, string $comp_hash, string $company_id)
    {
        if (isset($request->auth->admin_id)) {
            $role_id = $request->auth->role_id;
            $user_id = $request->auth->admin_id;
        } elseif (isset($request->auth->super_admin_id)) {
            $role_id = 1;
            $user_id = $request->auth->super_admin_id;
        } elseif (isset($request->auth->id)) {
            $role_id = 1;
            $user_id = $request->auth->id;
        }

        $count = 0;
        $company_hash_code = openssl_digest($company_id, 'sha512');
        if ($comp_hash != $company_hash_code) {
            $res = array('status' => 0, "message" => "Hotel list retrival failed");
            $res['errors'][] = "Please provide valid company";
            return response()->json($res);
        }
        $roles = Role::select('role_name')->where("role_id", '=', $role_id)->first();
        if ($roles->role_name == env('HOTEL_ADMIN')) {
            $hotels = HotelInformation::where('company_id', $company_id)->select('*')->get();
            $admindetails = array();
            $i = 0;
            if (sizeof($hotels) > 0) {
                foreach ($hotels as $hotel) {
                    $admindetails[$i] = AdminUser::select('admin_id')->where('company_id', $company_id)->where('role_id', 1)->first();
                    $ext_images = explode(',', $hotel['exterior_image']);
                    if (is_array($ext_images) && !$ext_images) {
                        $images = DB::table('image_table')
                            ->select('image_id', 'image_name')
                            ->where('image_id', $ext_images[0])
                            ->first();
                        ($images) ?
                            $hotel['exterior_image'] = $images->image_name
                            : '';
                    }
                    $i++;
                }
            }
            if (sizeof($hotels) > 0 && sizeof($admindetails) > 0) {
                $resultarray = array('status' => 1, "message" => "Properties retrieved successfully!", "data" => $hotels, "adminData" => $admindetails);
                return (isset($request->auth->id)) ?
                    response()->json($resultarray)
                    :
                    response()->json(array('status' => 1, "message" => "Properties retrieved successfully!", "data" => $hotels, "adminData" => $admindetails));
            } else {
                $res = array('status' => 0, "message" => "No property created yet!");
                return response()->json($res);
            }
        } elseif ($roles->role_name == env('HOTEL_USER')) {
            // $hotels=HotelInformation::where('user_id',$user_id)->get();

            $user_data = DB::table('admin_table')->select('hotel_id')->where('admin_id', $user_id)->first();
            $user_data->hotel_id = array_map('intval', array_filter(explode(',', $user_data->hotel_id), 'is_numeric'));


            if ($user_data && $user_data->hotel_id) {
                $hotels = HotelInformation::whereIn('hotel_id', $user_data->hotel_id)->get();
            }

            if (sizeof($hotels) > 0) {
                foreach ($hotels as $hotel) {
                    $ext_images = explode(',', $hotel['exterior_image']);
                    if (is_array($ext_images)) {
                        $images = DB::table('image_table')
                            ->select('image_id', 'image_name')
                            ->where('image_id', $ext_images[0])
                            ->first();
                        ($images) ?
                            $hotel['exterior_image'] = $images->image_name
                            :
                            '';
                    }
                }
            }
            if (sizeof($hotels) > 0) {
                return (isset($request->auth->id)) ?
                    response()->json(array('status' => 1, "message" => "Properties retrieved successfully!", "data" => $hotels))
                    :
                    response()->json(array('status' => 1, "message" => "Properties retrieved successfully!", "data" => $hotels));
            } else {
                $res = array('status' => 0, "message" => "No property created yet!");
                return response()->json($res);
            }
        }
    }
    // Get hotals of company
    /* @auther : Shankar Bag
     * @Story : GET  hotel details having same group
     * @Param : It will take the user_Login UUID as the parameter
     * @return All Select Data
    **/
    public function getAllHotelsByCompany(Request $request, string $comp_hash, string $company_id)
    {
        $company_hash_code = openssl_digest($company_id, 'sha512');
        if ($comp_hash != $company_hash_code) {
            $res = array('status' => 0, "message" => "Hotel list retrival failed");
            $res['errors'][] = "Please provide valid company";
            return response()->json($res);
        }
        $hotels = HotelInformation::where('company_id', $company_id)->select('*')->get();
        if ($hotels) {
            foreach ($hotels as $hotel) {
                (($hotel['be_opt'] == 1)) ? $hotel['be_opt'] = 'enquiry' : $hotel['be_opt'] = 'instant';
                $ext_images = explode(',', $hotel['exterior_image']);
                $images = DB::table('image_table')
                    ->select('image_id', 'image_name')
                    ->where('image_id', $ext_images)
                    ->first();
                ($images) ?
                    $hotel['exterior_image'] = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/' . $images->image_name
                    :
                    '';

                $city_details = DB::table('city_table')->select('city_name')->where('city_id', $hotel->city_id)->first();
                $hotel->city_name = $city_details->city_name;
            }
        }

        return ($hotels) ?
            response()->json(array('status' => 1, "message" => "Hotels retrieved successfully!", "data" => $hotels))
            :
            response()->json(array('status' => 0, "message" => "Hotels not found!"));
    }
    // Update Exterior Images
    /* @auther : Godti Vinod
     * @Story : Update the image id'
     *
     * @return Success or error status
    **/
    public function updateExterior(Request $request)
    {
        $data = $request->all();
        $imagesExt = array();
        $failure_message = "Exterior image uploading failed";
        $validator = Validator::make($request->all(), $this->rulesExt, $this->messagesExt);
        if ($validator->fails()) {
            return response()->json(array('status' => 0, 'message' => $failure_message, 'errors' => $validator->errors()));
        }
        $hotel_id = $data['hotel_id'];
        $ext_img = $data['image_ids'];
        $images = HotelInformation::select('exterior_image')->where('hotel_id', $hotel_id)->first();
        if (sizeof($images->exterior_image) > 0) {
            $images = explode(',', $images->exterior_image);
            foreach ($images as $img) {
                array_push($ext_img, (int)$img); //PUsing the images get from user request
            }
        }
        $ext_img = implode(',', $ext_img);
        if (HotelInformation::where('hotel_id', $hotel_id)->update(['exterior_image' => $ext_img])) {
            $res = array('status' => 1, "message" => 'Hotel exterior images uploaded successfully');
            return response()->json($res);
        } else {
            $res = array('status' => -1, "message" => $failure_message);
            $res['errors'][] = "Internal server error";
            return response()->json($res);
        }
    }
    // Update Interior Images
    /* @auther : Godti Vinod
     * @Story : Update the image id'
     * @return Success or error status
    **/
    public function updateInterior(Request $request)
    {
        $data = $request->all();
        $failure_message = "Interor image uploading failed";
        $validator = Validator::make($request->all(), $this->rulesExt, $this->messagesExt);
        if ($validator->fails()) {
            return response()->json(array('status' => 0, 'message' => $failure_message, 'errors' => $validator->errors()));
        }
        $hotel_id = $data['hotel_id'];
        $int_img = $data['image_ids'];
        $images = HotelInformation::select('interior_image')->where('hotel_id', $hotel_id)->first();
        if (sizeof($images->interior_image) > 0) {
            $images = explode(',', $images->interior_image);
            foreach ($images as $img) {
                array_push($int_img, (int)$img); //PUsing the images get from user request
            }
        }
        $int_img = implode(',', $int_img);
        if (HotelInformation::where('hotel_id', $hotel_id)->update(['interior_image' => $int_img])) {
            $res = array('status' => 1, "message" => 'Hotel interior images uploaded successfully');
            return response()->json($res);
        } else {
            $res = array('status' => -1, "message" => $failure_message);
            $res['errors'][] = "Internal server error";
            return response()->json($res);
        }
    }
    // Get Exterior images of hotel
    /* @auther : Godti Vinod
     * @Story : GET  Exterior images of hotel
     * @return All Select Data
    **/
    public function getExteriorImages(Request $request, int $hotel_id)
    {
        $images = HotelInformation::select('exterior_image')->where('hotel_id', $hotel_id)->first();
        $images = explode(',', $images->exterior_image);
        $extImages = DB::table('image_table')
            ->select('image_id', 'image_name')
            ->whereIn('image_id', $images)
            ->get();
        return ($extImages) ?
            response()->json(array('status' => 1, "message" => 'Hotel exterior images retrieved successfully', 'extImages' => $extImages))
            :
            response()->json(array('status' => 1, "message" => 'Hotel exterior images retrieved successfully', 'extImages' => $extImages));
    }
    // Get Interior images of hotel
    /* @auther : Godti Vinod
     * @Story : GET  Interior images of hotel
     * @return All Select Data
    **/
    public function getInteriorImages(Request $request, int $hotel_id)
    {
        $images = HotelInformation::select('interior_image')->where('hotel_id', $hotel_id)->first();
        $images = explode(',', $images->interior_image);
        $intImages = DB::table('image_table')
            ->select('image_id', 'image_name')
            ->whereIn('image_id', $images)
            ->get();
        return ($intImages) ?
            response()->json(array('status' => 1, "message" => 'Hotel interior     images retrieved successfully', 'intImages' => $intImages))
            :
            response()->json(array('status' => 0, "message" => 'Interior images not uploaded yet'));
    }
    // GET ALL HOTELS DETAILS
    /**
     * GET All country id
     * @auther : Shankar Bag
     * @Story : by this function we will get all row hotel .
     * @return hotel-info disabling status
     **/
    public function gethotelCountry(int $hotel_id, Request $request)
    {
        $country_id = HotelInformation::select('country_id')->where('hotel_id', '=', $hotel_id)->first();
        $res = array('status' => 1, "message" => 'Hotel country retrieved successfully', 'data' => $country_id);
        return response()->json($res);
    }
    public function getHotelList(int $company_id, Request $request)
    {
        $hotel_list = HotelInformation::select('hotel_id', 'hotel_name')->where('company_id', '=', $company_id)->get();
        $res = array('status' => 1, "message" => 'Hotel list retrieved successfully', 'data' => $hotel_list);
        return response()->json($res);
    }
}
