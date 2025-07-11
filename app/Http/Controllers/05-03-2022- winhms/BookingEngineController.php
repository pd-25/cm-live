<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\UserCredential;
use App\Inventory;//class name from model
use App\MasterRoomType;//class name from model
use App\MasterHotelRatePlan;//class name from model
use App\RatePlanLog;//class name from model
use App\HotelInformation;
use App\HotelAmenities;
use App\PaidServices;
use App\Invoice;
use App\User;
Use App\HotelBooking;
use App\CmOtaDetails;
use DB;
use App\ImageTable;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\InventoryService;
use App\OnlineTransactionDetail;
use App\CompanyDetails;
use App\TaxDetails;
use App\Coupons;
use App\BeSettings;
use App\MasterRatePlan;
use App\BeBookingDetailsTable;

use App\CancellationPolicy;
use App\CancellationPolicyMaster;
use App\Notifications;
use App\BENotificationSlider;

use App\Http\Controllers\PmsControllerTest;
use App\Http\Controllers\BeConfirmBookingInvUpdateRedirectingController;
use App\Http\Controllers\KtdcController;
//create a new class ManageInventoryController

use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Filesystem\Filesystem;

class BookingEngineController extends Controller
{
    protected $invService, $ipService,$idsService,$ktdcService;
    protected $updateInventoryService;
    protected $otaInvRateService;
    protected $curency;
    protected $gst_arr=array();
    protected $beConfBookingInvUpdate;
    public function __construct(InventoryService $invService,UpdateInventoryServiceTest $updateInventoryService,CurrencyController $curency,IpAddressService $ipService,PmsControllerTest $idsService,InvRateBookingDisplayController $otaInvRateService,BeConfirmBookingInvUpdateRedirectingController $beConfBookingInvUpdate,KtdcController $ktdcService)
    {
       $this->invService = $invService;
       $this->updateInventoryService = $updateInventoryService;
       $this->curency=$curency;
       $this->ipService=$ipService;
       $this->idsService = $idsService;
       $this->otaInvRateService= $otaInvRateService;
       $this->beConfBookingInvUpdate=$beConfBookingInvUpdate;
       $this->ktdcService=$ktdcService;
    }
    //validation rules
    private $rules = array(
        'hotel_id' => 'required | numeric',
        'from_date' => 'required ',
        'to_date' => 'required',
        'cart' => 'required'
    );
      //Custom Error Messages
      private $messages = [
        'hotel_id.required' => 'The hotel id field is required.',
        'hotel_id.numeric' => 'The hotel id must be numeric.',
        'from_date.required' => 'Check in date is required.',
        'to_date.required' => 'Check out is required.',
        'cart.required' => 'cart is required.'
        ];
     /* Get api key access and Hotel id
      * @auther Godti Vinod
      * function getAccess for fetching data
         **/
        public function getAccess(string $company_url,Request $request)
        {
            $conditions=array('company_url'=>$company_url);
            $info=CompanyDetails::select('api_key','hotels_table.hotel_id','company_table.company_id','chat_option','currency','is_widget_active')
            ->join('hotels_table','company_table.company_id','=','hotels_table.company_id')
            ->where($conditions)->first();
            $info['comp_hash']= openssl_digest($info['company_id'], 'sha512');
            if($info['api_key'])
            {
                $res=array('status'=>1,'message'=>"Company auth successfull",'data'=>$info);
                return response()->json($res);
            }
            else
            {
                $res=array('status'=>0,'message'=>"Invalid company url");
                return response()->json($res);
            }
        }

/**
 * Get base currency of hotel/Company
 * @param hotel_id(Hotel Id)
 * @auther Godti Vinod
 */
public function getBaseCurrency($hotel_id){
    $company=CompanyDetails::join('hotels_table','company_table.company_id','hotels_table.company_id')
            ->where('hotels_table.hotel_id',$hotel_id)
            ->select('currency','hex_code')
            ->first();
    if($company){
        return $company;
    }
}
//Get Inventory
/**
 * Get Inventory By Hotel id
 * get all record of Inventory by hotel id
 * @auther Godti Vinod
 * function getInvByHotel for fetching data
**/
public function getInvByHotel(string $api_key,int $hotel_id ,string $date_from ,string $date_to,string $currency_name,Request $request)
{
    $status="invalid";
    $status=$this->checkAccess($api_key,$hotel_id);
    if($status=="invalid")
    {
        $res=array('status'=>1,'message'=>"Invalid company or Hotel");
        return response()->json($res);
    }
    $date_from=date('Y-m-d',strtotime($date_from));
    $date_to=date('Y-m-d',strtotime($date_to));
    $roomType=new MasterRoomType();

    $conditions=array('hotel_id'=>$hotel_id,'is_trash'=>0, 'be_room_status'=>0);
    $room_types=MasterRoomType::select('room_type','room_type_id','max_people','max_child','max_room_capacity','image','rack_price','extra_person','extra_child','bed_type','room_amenities','max_infant')->where($conditions)->orderBy('rack_price', 'ASC')->get();


    $date1=date_create($date_from);
    $date2=date_create($date_to);
    $date3=date_create(date('Y-m-d'));
    $diff=date_diff($date1,$date2);
    $diff=$diff->format("%a");//Diffrence betwen checkin and checkout date
    $diff1=date_diff($date3,$date1);///Diffrence betwen booking date that is today and checkin date
    $diff1=$diff1->format("%a");
    $room_details=array();
    $baseCurrency=$this->getBaseCurrency($hotel_id)->currency;//Get base currency

    $room_type_n_rate_plans=array();
    if($room_types)
    {
        foreach($room_types as $key => $room)
        {
            $room['min_inv']=0;
            if($baseCurrency == $currency_name){
                $room_details[$key]["rack_price"]=$room["rack_price"];
            }else{
                $room_details[$key]["rack_price"]=round($this->curency->currencyDetails($room["rack_price"],$currency_name,$baseCurrency),2);
            }
            $room['room_amenities']=explode(',',$room['room_amenities']);
            $room['room_amenities']=$this->getRoomamenity($room['room_amenities']);
            $room_details[$key]["room_amenities"]=$room['room_amenities'];

            $room_details[$key]["min_inv"]=0;
            $room_details[$key]["extra_child"]=$room["extra_child"];
            $room_details[$key]["extra_person"]=$room["extra_person"];
            $room_details[$key]["max_child"]=$room["max_child"];

            $room_details[$key]["max_infant"]=$room["max_infant"];


            $room_details[$key]["max_people"]=$room["max_people"];
            $room_details[$key]["room_type"]=$room["room_type"];
            $room_details[$key]["room_type_id"]=$room["room_type_id"];
            $room_details[$key]["max_room_capacity"]=$room["max_room_capacity"];
            $room['image']=explode(',',$room['image']);//Converting string to array
            $room_types[$key]['allImages'] = $this->getImages($room->image);//--------- get all the images of rooms (by Samya Ranjan)-------------
            $room['image']=$this->getImages(array($room['image'][0]));//Getting actual amenity names
            if(is_object($room['image']) && sizeof($room['image'])>0)
            {
                $room['image']=$room['image'][0]->image_name;
                $room_details[$key]["image"]=$room['image'];
            }
            else
            {
                $room['image']=$this->getImages(array(1));
                $room['image']=$room['image'][0]->image_name;
                $room_details[$key]["image"]=$room['image'];
            }
            foreach($date1 as $d){
              $from_date = date('Y-m-d',strtotime($d));
              break;
            }
            foreach($date2 as $d1){
              $to_date = date('Y-m-d',strtotime($d1));
              break;
            }


            $resp=$this->getAllPublicCupons($hotel_id,$from_date,$to_date,$request);

            // $resp=json_decode($resp->getContent(),true);
            $room['coupons']=array();
            // return response()->json($resp->original['data']);
            if(isset($resp->original['data'])){
                foreach($resp->original['data'] as $coupons){
                    // return $from_date;
                    if(($coupons['room_type_id']===$room["room_type_id"] || $coupons['room_type_id']===0) && (strtotime($from_date) <= strtotime($coupons['valid_to']) && strtotime($from_date) >= strtotime($coupons['valid_from']))){   
                        $room['coupons']=array($coupons);
                    }
                }
               // return $room['coupons'];
            }
            $room_details[$key]["coupons"]=$room['coupons'];
            $data=$this->invService->getInventeryByRoomTYpe($room['room_type_id'],$date_from, $date_to, 0);

            $room['min_inv']  = $data[0]['no_of_rooms'];
            $room_details[$key]["min_inv"]=$data[0]['no_of_rooms'];
            foreach($data as $inv_room)
            {

                if($inv_room['no_of_rooms']<$room['min_inv'])
                {
                    $room['min_inv']  = $inv_room['no_of_rooms'];
                    $room_details[$key]["min_inv"]=$inv_room['no_of_rooms'];
                }
            }
            $room['inv']=$data;
            $room_details[$key]["inv"]=$data;
            $room_type_n_rate_plans=MasterHotelRatePlan::join('rate_plan_table as a', function($join) {
                $join->on('room_rate_plan.rate_plan_id', '=', 'a.rate_plan_id');
                    })
                ->select('a.rate_plan_id','plan_type','plan_name')
                ->where('room_rate_plan.hotel_id',$hotel_id)
                ->where('room_rate_plan.is_trash',0)
                ->where('room_rate_plan.room_type_id',$room['room_type_id'])
                ->orderBy('room_rate_plan.rate_plan_id','ASC')
                ->orderBy('room_rate_plan.created_at','ASC')
                ->distinct()
                ->get();

            $room['min_room_price']=0;
            $room_details[$key]["min_room_price"]=0;
            $rateplan_details=array();
            foreach($room_type_n_rate_plans as $key1=>$all_types)
            {
                $rate_plan_id=(int)$all_types['rate_plan_id'];

                $room_rate_status=$this->checkroomrateplan($room['room_type_id'],$rate_plan_id,$date_from, $date_to);

                if($room_rate_status){
                    $data=$this->invService->getRatesByRoomnRatePlan($room['room_type_id'],$rate_plan_id,$date_from, $date_to);
                }
                else{
                    continue;
                }
                
                //$data=$this->invService->getRatesByRoomnRatePlan($room['room_type_id'],$rate_plan_id,$date_from, $date_to);
                $all_types['bar_price']=0;
                $rateplan_details[$key1]['bar_price']=0;
                $rate_data=array();
                $rateplan_details[$key1]['plan_name']= $all_types["plan_name"];
                $rateplan_details[$key1]['plan_type']= $all_types["plan_type"];
                $rateplan_details[$key1]['rate_plan_id']= $all_types["rate_plan_id"];
                if($data)
                {
                    foreach( $data as $key2=>$rate)
                    {
                        $cur_value=array();
                        $currency_value=array();
                        $multiple_occ=array();
                        $i=0;
                        $j=0;

                        if(isset($rate["multiple_occupancy"]) && sizeof($rate["multiple_occupancy"])==0){
                            $rate["multiple_occupancy"][0]=$all_types['bar_price'];
                        }
                        if(isset($rate["multiple_occupancy"]) && $rate["multiple_occupancy"] !="" && sizeof($rate["multiple_occupancy"])>0){
                        foreach ($rate["multiple_occupancy"] as $value) {
                            $multiple_occ[$i]=$this->getPriceDetails($rate,$value,$all_types['bar_price'],$diff,$diff1);
                            $i++;
                        }
                        foreach($multiple_occ as $mult)
                        {
                            $cur_value[$j]=round($this->curency->currencyDetails($mult,$currency_name,$baseCurrency),2);
                            $j++;
                        }
                        $data[$key2]["multiple_occupancy"]=$multiple_occ;
                        if($baseCurrency == $currency_name){//Same as base currency than not convert
                            $rate_data[$key2]["multiple_occupancy"]=$multiple_occ;
                        }else{
                            $rate_data[$key2]["multiple_occupancy"]=$cur_value;//otherwise converted
                        }
                        }
                        $all_types['bar_price']=$this->getPriceDetails($rate,$rate['bar_price'],$all_types['bar_price'],$diff,$diff1);
                        if($baseCurrency == $currency_name){//Same as base currency than not convert
                            $rateplan_details[$key1]['bar_price']=$all_types['bar_price'];
                        }else{
                            $rateplan_details[$key1]['bar_price']=round($this->curency->currencyDetails($all_types['bar_price'],$currency_name,$baseCurrency),2);
                        }
                        $data[$key2]['bar_price']=$all_types['bar_price'];
                        $rate_data[$key2]['bar_price']=$rateplan_details[$key1]['bar_price'];
                        $rate_data['before_days_offer']= $rate["before_days_offer"];
                        $rate_data['bookingjini_price']= $rate["bookingjini_price"];
                        $rate_data['currency']= $rate["currency"];
                        $rate_data['date']= $rate["date"];
                        $rate_data['day']= $rate["day"];
                        $rate_data['extra_adult_price']= $rate["extra_adult_price"];
                        $rate_data['extra_child_price']= $rate["extra_child_price"];
                        $rate_data['hex_code']= $rate["hex_code"];
                        $rate_data['lastminute_offer']= $rate["lastminute_offer"];
                        $rate_data['rate_plan_id']= $rate["rate_plan_id"];
                        $rate_data['room_type_id']= $rate["room_type_id"];
                        $rate_data['stay_duration_offer']= $rate["stay_duration_offer"];
                    }
                }
                    if($room['min_room_price']==0)
                    {
                     $room['min_room_price']=$all_types['bar_price'];
                     $room_details[$key]['min_room_price']=$rateplan_details[$key1]['bar_price'];
                    }
                    if($all_types['bar_price']<=$room['min_room_price'] && $all_types['bar_price']!=null )
                    {
                        $room['min_room_price']  = $all_types['bar_price'];
                        $room_details[$key]['min_room_price']=$rateplan_details[$key1]['bar_price'];
                    }
                    if($all_types['bar_price']!=0 || $all_types['bar_price']!=null )
                    {
                        $all_types['rates']=$data;
                        $rateplan_details[$key1]['rates']=$rate_data;
                    }
                    if($all_types['bar_price']==0 || $all_types['bar_price']==NULL)
                    {
                        unset($room_type_n_rate_plans[$key1]);
                        //unset($rateplan_details[$key1]);
                    }
                    $rateplan_details=array_values($rateplan_details);
                }
            if($room['min_room_price']!=0 &&  $room['min_room_price']!=null)
            {
                $room['rate_plans']=$room_type_n_rate_plans;
                $room_details['rate_plans']=$rateplan_details;
            }
        }
        $res=array('status'=>1,'message'=>"Hotel inventory retrieved successfully ",'data'=>$room_types,'room_data'=>$room_details);
        return response()->json($res);
    }
    else
    {
        $res=array('status'=>1,'message'=>"Hotel inventory retrieval failed due to invalid information");
        return response()->json($res);
    }
}
/*
 *multiple occupancy and barprice
 */
public function getPriceDetails($rate,$pricevalue,$alltypeprice,$diff,$diff1)
{
    /*if($pricevalue>=$alltypeprice)
    {*/
        $before_days_offr=$rate['before_days_offer'];
        $stayduration_offr=$rate['stay_duration_offer'];
        $lastminut_offr=$rate['lastminute_offer'];

        if($before_days_offr!='no' && $before_days_offr!="")
        {
            $bdf=explode(',', $before_days_offr);
            $before_days=$bdf[0];
            $before_days_offr=$bdf[1];
        }
        else
        {
            $before_days=0;
            $before_days_offr=$pricevalue;
        }
        if($stayduration_offr!='no' && $stayduration_offr!="")
        {
            $sdf=explode(',', $stayduration_offr);
            $stayduration=$sdf[0];
            $stayduration_offr=$sdf[1];
        }
        else
        {
            $stayduration=0;
            $stayduration_offr=$pricevalue;
        }
        if($lastminut_offr!='no' && $lastminut_offr!="")
        {
            $ldf=explode(',', $lastminut_offr);
            $lastminut=$ldf[0];
            $lastminut_offr=$ldf[1];
        }
        else
        {
            $lastminut=-1;
            $lastminut_offr=$pricevalue;
        }
        if($diff1>=$before_days)
        {
            $price1=$before_days_offr;
        }
        else
        {
            $price1=$pricevalue;
        }
        if($diff>=$stayduration)
        {
            $price2=$stayduration_offr;
        }
        else
        {
            $price2=$pricevalue;
        }
        if($diff1<=$lastminut)
        {
            $price3=$lastminut_offr;
        }
        else
        {
            $price3=$pricevalue;
        }
        if( ($price1 < $price2) && ($price1 < $price3) )
        {
            $alltypeprice=$price1;
        }
        else if($price2 < $price3)
        {
            $alltypeprice=$price2;
        }
        else
        {
            $alltypeprice=$price3;
        }
        if($rate['bookingjini_price'])
        {
            $alltypeprice=round($alltypeprice-($alltypeprice * $rate['bookingjini_price'])/100);//Bookingjini Price
        }
        return  $alltypeprice;
    //}
}
 /*
 * Get Room description and picture and other information of the hotel By Hotel id
 * @auther Godti Vinod
 * function getInvByHotel for fetching data
**/
    public function getRoomDetails(string $api_key,int $hotel_id,int $room_type_id,Request $request)
    {
        $status="invalid";
        $status=$this->checkAccess($api_key,$hotel_id);
        if($status=="invalid")
        {
            $res=array('status'=>1,'message'=>"Invalid company or Hotel");
            return response()->json($res);
        }
        $roomType=new MasterRoomType();
        $conditions=array('room_type_id'=>$room_type_id,'is_trash'=>0);
        // $room_types=MasterRoomType::select('room_type_id','room_type','description','image','room_amenities')->where($conditions)->first();
        $room_types=MasterRoomType::select('room_type_id','room_type','description','image','room_amenities','bed_type','room_view_type','room_size_value','room_size_unit')->where($conditions)->first(); //-----get room details by Samya Ranjan------------//

        $room_types->image=explode(',',$room_types->image);//Converting string to array
        $room_types->image=$this->getImages($room_types->image);//Getting actual amenity names
        $room_types->room_amenities=explode(',',$room_types->room_amenities);
        $room_types->room_amenities=$this->getRoomamenity($room_types->room_amenities);
        if($room_types)
        {
            $res=array('status'=>1,'message'=>"Hotel inventory retrieved successfully",'data'=>$room_types);
            return response()->json($res);
        }
        else
        {
            $res=array('status'=>1,'message'=>"Hotel inventory retrieval failed due to invalid information");
            return response()->json($res);
        }
    }
    /*
 * Get Room description and picture and other information of the hotel By Hotel id
 * @auther Godti Vinod
 * function getInvByHotel for fetching data
    **/
    public function getHotelDetails(string $api_key,int $hotel_id,Request $request)
    {
         //$hotel=new HotelInformation();
        $cond=array('api_key'=>$api_key);
        $comp_info=CompanyDetails::select('company_id')->where($cond)->first();
        if(!$comp_info['company_id'])
        {
            $res=array('status'=>1,'message'=>"Invalid hotel or company");
            return response()->json($res);
        }
        $conditions=array('hotel_id'=>$hotel_id,'company_id'=>$comp_info['company_id']);
        $info=HotelInformation::select('hotel_name','hotel_description','hotel_address','child_policy','cancel_policy','terms_and_cond','hotel_policy','facility','airport_name','distance_from_air','rail_station_name','distance_from_rail','land_line','star_of_property','nearest_tourist_places','tour_info','check_in','check_out','exterior_image','latitude','longitude','facebook_link','twitter_link','linked_in_link','instagram_link','whatsapp_no','tripadvisor_link','holiday_iq_link','partial_payment','partial_pay_amt','email_id','mobile','advance_booking_days','is_overseas','tax')->where($conditions)->first();
        $info->facility=explode(',',$info->facility);//Converting string to array
        $info->facility=$this->getHotelAmen($info->facility);//Getting actual amenity names
        $info->exterior_image=explode(',',$info->exterior_image);//Converting string to array
        $info->exterior_image=$this->getImages($info->exterior_image);//Getting actual amenity names
        $info['ota_hotel_code']=$this->getBookingDotComPropertyID($hotel_id);//Get booking.com hotel code
        if($info)
        {
            $res=array('status'=>1,'message'=>"Hotel description successfully ",'data'=>$info);
            return response()->json($res);
        }
        else
        {
            $res=array('status'=>1,'message'=>"Invalid hotel or company");
            return response()->json($res);
        }
    }


    /*
    Get HOTEL AMENITIES of the hotel By Hotel id
    * @auther Godti Vinod
    * function getHotelAmen for fetching data
       **/
    public function getHotelAmen($amen)
    {
        $amenities=HotelAmenities::whereIn('hotel_amenities_id', $amen)
                ->select('hotel_amenities_name')
                ->get();
       if($amenities)
       {
           return $amenities;
       }
       else
       {
           return array();
       }

    }
     /*
    Get HOTEL Images  of the hotel By Hotel id
    * @auther Godti Vinod
    * function getHotelAmen for fetching data
       **/
      public function getImages($imgs)
      {
          $images=ImageTable::whereIn('image_id', $imgs)
                  ->select('image_name')
                  ->get();
         if(sizeof($images)>0)
         {
             return $images;
         }
         else
         {
              $images=ImageTable::where('image_id', 3)
                   ->select('image_name')
                   ->get();
              return $images;
         }

      }
      public function getBannerImages($imgs)
      {
          $banner_ids = explode(",", $imgs);
          $images=ImageTable::whereIn('image_id',  $banner_ids)
                  ->select('image_name')
                  ->get();
        if(sizeof($images)!=0)
        {
            return $images;
        }
         else
         {
            $images=ImageTable::where('image_id',  2)
            ->select('image_name')
            ->get();
            return $images;
         }

      }
      /* Get Room description and picture and other information of the hotel By Hotel id
      * @auther Godti Vinod
      * function getInvByHotel for fetching data
         **/
         public function getHotelLogo(string $api_key,int $company_id,Request $request)
         {
             $conditions=array('company_id'=>$company_id);
             $info=CompanyDetails::select('banner','logo','home_url')
             ->where($conditions)->first();
             if(!$info)
             {
                $res=array('status'=>0,'message'=>"Invalid company credentials");
                return response()->json($res);
             }
             $info->logo=$this->getImages(array($info->logo));
             $info->logo= $info->logo[0]->image_name;

             $info->logo= $info->logo;
             $info->banner=$this->getBannerImages($info->banner);
             $info->banner= $info->banner;
             foreach($info->banner as $key => $value){
                $info->banner[$key]->image_name =  $value->image_name;
             }

             if($info)
             {
                 $res=array('status'=>1,'message'=>"Company log and banners retrieved successfully",'data'=>$info);
                 return response()->json($res);
             }
         }
//Bookings save starts here
public function bookings(string $api_key,Request $request)
{
    $reference = $request->input('reference');
    $date1=date_create($request->input('from_date'));
    $date2=date_create($request->input('to_date'));
    $today = date('Y-m-d');
    $diff=date_diff($date1,$date2);
    $diff=$diff->format("%a");

    $hotel_id=$request->input('hotel_id');
    $status="invalid";
    
    $percentage=0;//THis shoud be set from Mail invoice
    $invoice= new Invoice();
    $booking= new HotelBooking();
    $failure_message='Booking failed due to unsuffcient booking details';
      $validator = Validator::make($request->all(),$this->rules,$this->messages);
      if($validator->fails())
      {
          return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
      }
  
  $cart= $request->input('cart');
  foreach($cart as $cartItem)
  {
    $paid_amount=$cartItem["paid_amount"];
    $percentage=$cartItem["paid_amount_per"];
    if(isset($cartItem["partial_amount_per"])){
        $partial_percentage=$cartItem["partial_amount_per"];
    }
  }
  $from_date=date('Y-m-d',strtotime($request->input('from_date')));
  $to_date=date('Y-m-d',strtotime($request->input('to_date')));
  // return $to_date;
    if($from_date < $today || $to_date < $today){
        return 0;
    }
  $coupon=$request->input('coupon');
  $paid_service=$request->input('paid_service');
  $visitors_ip=$this->ipService->getIPAddress();
  $chkPrice=false;
  $chkPaidService=false;
  //to get partial payment status
  $payment_status=HotelInformation::select('partial_payment','partial_pay_amt')->where('hotel_id',$hotel_id)->first();
  // Check room price && Check Qty && Check Extra adult prce && Check Extra child price && Check GSt && Discounted Price
  $chkPrice=$this->checkRoomPrice($coupon,$cart,$from_date,$to_date,$hotel_id,$diff);
  //Check Paid services
  if($paid_service)
  {
    $chkPaidService=$this->checkPaidService($paid_service);
  }
  else
  {
    $chkPaidService=true;
  }
  // $validCart= ($chkPrice && $chkPaidService
  $validCart = true;
   if($validCart)
    {
        $inv_data=array();
        $hotel= $this->getHotelInfo($hotel_id);
        $booking_data=array();
        $inv_data['check_in_out']="[".$from_date.'-'.$to_date."]";
        $inv_data['room_type']  = json_encode($this->prepareRoomType($cart));
        $inv_data['hotel_id']   = $hotel_id;
        $inv_data['hotel_name'] = $hotel->hotel_name;
        $inv_data['user_id']    = $request->auth->user_id;
        $inv_data['total_amount']  = number_format((float)$this->getTotal($cart,$paid_service,$hotel_id), 2, '.', '');
        if($payment_status->partial_payment == 1)
        {
            if($partial_percentage){
                $partial_amount=$inv_data['total_amount'] * $partial_percentage/100;
            }
            else if($percentage == $payment_status->partial_pay_amt)
            {
                $partial_amount=$inv_data['total_amount'] * $payment_status->partial_pay_amt/100;
            }
            else{
                $partial_amount=number_format((float)$inv_data['total_amount'], 2, '.', '');
            }
            if(round($partial_amount,0) == round($paid_amount,0))
            {
                $inv_data['paid_amount']   = number_format((float)$paid_amount, 2, '.', '');
            }
            else{
              $inv_data['paid_amount'] = number_format((float)$paid_amount, 2, '.', '');
            }
        }
        else
        {
            $inv_data['paid_amount'] = number_format((float)$inv_data['total_amount'], 2, '.', '');
        }

        // code by upendra start
        //$get_billing=DB::table('billing_table')->select('billing_table.*')->join('hotels_table','hotels_table.company_id','=','billing_table.company_id')->where('hotels_table.hotel_id',$hotel_id)->first();
        // $get_billing=DB::table('billing_table')->select('billing_table.*')->where('billing_table.company_id',$company_id)->first();
        // $inv_data['is_cm']=0;
        // if(strpos($get_billing['product_name'],'Channel Mananger')!==false){
        //     $inv_data['is_cm']=1;
        // }
        // code by upendra start
        $inv_data['discount_code'] = isset($coupon['coupon_code']) && $coupon['coupon_code'];
        $inv_data['paid_service_id'] = implode(",",$this->getPaidService($paid_service));
        $inv_data['extra_details'] = json_encode($this->getExtraDetails($cart));
        $inv_data['booking_date'] = date('Y-m-d H:i:s');
        $inv_data['visitors_ip'] = $visitors_ip;
        $inv_data['ref_no']=rand().strtotime("now");
        $inv_data['booking_status']=2;//Initially Booking status set 2 ,For the pending status
        $inv_data['invoice']=$this-> createInvoice($hotel_id,$cart,$coupon,$paid_service,$from_date,$to_date,$inv_data['user_id'],$percentage,$inv_data['ref_no']);
        $inv_data['ids_re_id']=$this->handleIds($cart,$from_date,$to_date,$inv_data['booking_date'],$hotel_id,$inv_data['user_id'],'Commit');
        // $ktdc_re_id=$this->handleKtdc($cart,$from_date,$to_date,$inv_data['booking_date'],$hotel_id,$inv_data['user_id'],'Commit','');
        $getInvInfo = $this->gstDiscountPrice($cart);
        $inv_data['tax_amount'] = $getInvInfo[0];
        $inv_data['discount_amount'] = $getInvInfo[1];
        if(isset($reference)){
            $inv_data['ref_from'] =  $reference;
        }
        $failure_message="Invoice details saving failed";
        if($invoice->fill($inv_data)->save())
        {
            $cur_invoice=Invoice::where('ref_no',$inv_data['ref_no'])->first();
            // date("dmy", strtotime($cur_invoice->booking_date)).str_pad($cur_invoice->invoice_id, 4, '0', STR_PAD_LEFT);//
            $booking_data=$this->prepare_booking_data($cart,$cur_invoice->invoice_id,$from_date,$to_date,$inv_data['user_id'],$inv_data['booking_date'],$hotel_id);
            if(HotelBooking::insert($booking_data))
            {
                if($this->preinvoiceMail($cur_invoice->invoice_id))
                {
                    //Hashing starts
                    $user_data=$this->getUserDetails($inv_data['user_id']);
                    $b_invoice_id=base64_encode($cur_invoice->invoice_id);
                    $invoice_hashData=$cur_invoice->invoice_id.'|'.$cur_invoice->total_amount.'|'.$cur_invoice->paid_amount.'|'.$user_data->email_id.'|'.$user_data->mobile.'|'.$b_invoice_id;
                    $invoice_secureHash= hash('sha512', $invoice_hashData);
                    $res=array("status"=>1,"message"=>"Invoice details saved successfully","invoice_id"=>$cur_invoice->invoice_id,'invoice_secureHash'=>$invoice_secureHash);

                    // bucket code start by upendra
                    // if($inv_data['is_cm']==1){
                        // $con=$beConfBookingInvUpdate->postDetails($cart,$cur_invoice->invoice_id,$hotel_id,$booking_details)                
                    // }
                    // bucket code end by upendra
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
                $res=array('status'=>-1,"message"=>$failure_message);
                $res['errors'][] = "Internal server error";
                return response()->json($res);
            }
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
    $res=array('status'=>-1,"message"=>"Booking failed due to data tempering,Please try again later");
    return response()->json($res);
   }

}
    
//Get Hotel info from the Hotel id
public function getHotelInfo($hotel_id)
{
    $hotel=HotelInformation::select('*')->where('hotel_id',$hotel_id)->first();
    return $hotel;
}
//Prepare the Room Types TO insert into database
public function prepareRoomType($cart)
{
    $room_types=array();
    foreach($cart as $cartItem)
    {
        $room_type_id=$cartItem['room_type_id'];
        $conditions=array('room_type_id'=>$room_type_id,'is_trash'=>0);
        $room_type_array=MasterRoomType::select('room_type')->where($conditions)->first();
        $room_type=$room_type_array['room_type'];
        $plan_type=$cartItem['plan_type'];
        $rooms=$cartItem['rooms'];
        array_push($room_types,sizeof($rooms).' '.$room_type.'('.$plan_type.')');
    }
    return $room_types;
}
//Get total price in the cart
public function getTotal($cart,$paid_services,$hotel_id)
{
    $total_price=0;
    foreach($cart as $cartItem)
    {
        $total_extra_price=0;
        foreach($cartItem['rooms'] as $cart_room)
        {
            $total_extra_price+=$cart_room['extra_child_price']+$cart_room['extra_adult_price'];
        }
       $total_price+=$cartItem['price']+$cartItem['tax'][0]['gst_price']+$total_extra_price-$cartItem['discounted_price'];
       foreach($cartItem['tax'][0]['other_tax'] as $other_tax){
        $total_price+=$other_tax['tax_price'];
       }
    }

    $paid_service_price=0;
    foreach($paid_services as $paid_service)
    {
        $paid_service_price+=$paid_service['price'];
    }
    $total_price= $total_price+$paid_service_price;
    $total_price=round($total_price,2);
    return $total_price;
}
//Get the paid service Ids To insert into INVOICE Table
public function getPaidService($paid_services)
{
    $orig_paid_service=array();
    foreach($paid_services as $paid_service)
    {
            array_push($orig_paid_service,$paid_service['service_no']);
    }
    return $orig_paid_service;
}
//Get the getExtra details such as Room Type id and Selected Child and Adults
public function getExtraDetails($cart)
{
    $extra_details=array();
    foreach($cart as $cartItem)
    {
        foreach($cartItem['rooms'] as $room)
        {
            array_push($extra_details,array( $cartItem['room_type_id']=>array($room['selected_adult'],$room['selected_child'])));
        }
    }
    return $extra_details;
}
public function checkAccess($api_key,$hotel_id)
{
    //$hotel=new HotelInformation();
    $cond=array('api_key'=>$api_key);
    $comp_info=CompanyDetails::select('company_id')
    ->where($cond)->first();
    if(!$comp_info['company_id'])
    {
       return "Invalid";
    }
    $conditions=array('hotel_id'=>$hotel_id,'company_id'=>$comp_info['company_id']);
    $info=HotelInformation::select('hotel_name')->where($conditions)->first();
    if($info)
    {
        return 'valid';
    }
    else
    {
        return "invalid";
    }
}
//Prepare the booking table row to Insert
public function prepare_booking_data($cart,int $invoice_id,$from_date,$to_date,$user_id,$booking_date,$hotel_id)
{
    $booking_data=array();
    $booking_data_arr=array();
    foreach($cart as $cartItem)
    {
        $booking_data['room_type_id']=$cartItem['room_type_id'];
        $booking_data['rooms']=sizeof($cartItem['rooms']);
        $booking_data['check_in']=$from_date;
        $booking_data['check_out']=$to_date;
        $booking_data['booking_status']=2;//Intially Un Paid
        $booking_data['user_id']=$user_id;
        $booking_data['booking_date']=$booking_date;
        $booking_data['invoice_id']=$invoice_id;
        $booking_data['hotel_id']=$hotel_id;
        array_push($booking_data_arr,$booking_data);
    }
    return $booking_data_arr;
}


/********============Pre Check Room Price,Qty,GST,Discounts===========********* */
public function checkRoomPrice($coupon,$cart,$from_date,$to_date,$hotel_id,$diff)
{
    $qty=0;
    $chkQty=array();//Initially chkQty set to false
    $chkRmRate=array();
    foreach($cart as $key => $cartItem)
    {
        // $coupon_info = '';
        // if(isset($coupon)){
        //     foreach($coupon as $coup){
        //         if(sizeof($coupon) == 1){
        //             $coupon_info = $coupon[0];
        //         }else{
        //             $coupon_info = $coupon[$key];
        //         }

        //     }
        // }
        $coupon_info = '';
        if(isset($coupon)){
            foreach($coupon as $key1 => $coup){
                if(sizeof($coupon) == 1){
                    $coupon_info = $coupon[0];
                }else{
                    if($coup['room_data']['room_type_id'] == $cartItem['room_type_id']){
                        $coupon_info = $coupon[$key1];
                    }
                    else if($coup['room_data']['room_type_id'] == 0 ){
                        $coupon_info = $coupon[$key1];
                    }else{

                    }
                }
            }
        }

        $room_type_id=$cartItem['room_type_id'];
        $rooms=$cartItem['rooms'];
        $room_price=$cartItem['price'];
        $gst_price=$cartItem['tax'][0]['gst_price'];
        $other_tax_arr=$cartItem['tax'][0]['other_tax'];
        $discounted_price=$cartItem['discounted_price'];
        $qty=sizeof($rooms);//No of rooms is size of the rooms array
        array_push($chkQty,$this->CheckQty($room_type_id,$qty,$from_date,$to_date,$diff));
        array_push($chkRmRate,$this->CheckRoomRate($room_type_id,$rooms,$from_date,$to_date,$room_price,$coupon_info,$gst_price,$other_tax_arr,$discounted_price,$hotel_id));

    }

    $rmStatus=true;
    $qty_status=true;
    //Check all the room rates status
    foreach($chkQty as $chk_qty)
    {
     if($chk_qty!=1)
     {
        $qty_status=false;
     }
    }
    foreach($chkRmRate as $chkRm)
    {
     if($chkRm!=1)
     {
        $rmStatus=false;
     }
    }
    ///Check all the status
    if($qty_status && $rmStatus)
    {
        return true;
    }
    else{
        return false;
    }
}
///*************Pre check Paid service************************************/
public function checkPaidService($paid_services)
{
    $chk_paid_service=array();
    foreach($paid_services as $paid_service)
    {
        $conditions=array('paid_service_id'=>$paid_service['service_no'],'is_trash'=>0);
        $orig_paidService=PaidServices::where($conditions)->first();
        if($orig_paidService->service_amount ==($paid_service['price']))
        {
            array_push($chk_paid_service,1);
        }

    }
    return $chk_paid_service;
}
//*************Pre Check qunatity of the rooms*************************/
public function CheckQty($room_type_id,$qty,$from_date,$to_date,$diff)
{
    $min_inv=0;
    $data=$this->invService->getInventeryByRoomTYpe($room_type_id,$from_date, $to_date, 0);
    foreach($data as $inv_room)
    {
        if($diff<$inv_room['los'])
        {
            return false;
        }
        else
        {
            if($min_inv==0)
            {
                $min_inv=$inv_room['no_of_rooms'];
            }
            if($inv_room['no_of_rooms']<=$min_inv)
            {
                $min_inv  = $inv_room['no_of_rooms'];
            }
        }

    }
    //Check qty
    if($qty<=$min_inv)
    {
        return true;
    }
    else
    {
        return false;
    }
}
//*************Pre Check ROOM rate of the rooms*************************/
public function CheckRoomRate($room_type_id,$rooms,$from_date,$to_date,$curr_room_price,$coupon,$curr_gst_price,$cur_other_tax,$discounted_price,$hotel_id)
{
    $date1=date_create($from_date);
    $date2=date_create($to_date);
    $date3=date_create(date('Y-m-d'));
    $diff=date_diff($date1,$date2);
    $diff=$diff->format("%a");//Diffrence betwen checkin and checkout date
    $diff1=date_diff($date3,$date1);///Diffrence betwen booking date that is today and checkin date
    $diff1=$diff1->format("%a");
    $room_price=0;

    $extra_adult_ok=false;
    $extra_child_ok=false;
    $chk_disc_ok=false;
    $multiple_occ=array();
    $conditions=array('room_type_id'=>$room_type_id,'is_trash'=>0);
    $room_types=MasterRoomType::select('room_type','room_type_id','max_people','max_child','image','rack_price','extra_person','extra_child')->where($conditions)->first();
    $tot_extra_adult_price=0;
    $tot_extra_child_price=0;

    $max_adult= $room_types->max_people;
    $max_child= $room_types->max_child;
    $calculated_discounted_price=0;
    $curr_discounted_price=0;
    foreach($rooms as $room)
    {

        $curr_extra_adult_price=$room['extra_adult_price'];
        $curr_extra_child_price=$room['extra_child_price'];
        $selected_adult=$room['selected_adult'];
        $selected_child=$room['selected_child'];
        $extra_adult_price=0;
        $extra_child_price=0;
        $room_rates=$this->invService->getRatesByRoomnRatePlan($room_type_id,$room['rate_plan_id'],$from_date,$to_date);
        foreach($room_rates as $room_rate)
        {

            $j=0;
            $before_days_offr=$room_rate['before_days_offer'];
            $stayduration_offr=$room_rate['stay_duration_offer'];
            $lastminut_offr=$room_rate['lastminute_offer'];
            if($before_days_offr!='no' && $before_days_offr!="" )
            {
                $bdf=explode(',', $before_days_offr);
                $before_days=$bdf[0];
                $before_days_offr=$bdf[1];
            }
            else
            {
                $before_days=0;
                $before_days_offr=$room_rate['bar_price'];
            }
            if($stayduration_offr!='no' && $stayduration_offr!="")
            {
                $sdf=explode(',', $stayduration_offr);
                $stayduration=$sdf[0];
                $stayduration_offr=$sdf[1];
            }
            else
            {
                $stayduration=0;
                $stayduration_offr=$room_rate['bar_price'];
            }
            if($lastminut_offr!='no' && $lastminut_offr!="")
            {
                $ldf=explode(',', $lastminut_offr);
                $lastminut=$ldf[0];
                $lastminut_offr=$ldf[1];
            }
            else
            {
                $lastminut=-1;
                $lastminut_offr=$room_rate['bar_price'];
            }
            if($diff1>=$before_days)
            {
                $price1=$before_days_offr;
            }
            else
            {
                $price1=$room_rate['bar_price'];
            }
            if($diff>=$stayduration)
            {
                $price2=$stayduration_offr;
            }
            else
            {
                $price2=$room_rate['bar_price'];
            }
            if($diff1<=$lastminut)
            {
                $price3=$lastminut_offr;
            }
            else
            {
                $price3=$room_rate['bar_price'];
            }
            if( ($price1 < $price2) && ($price1 < $price3) )
            {

                $room_rate['bar_price']=$price1;
            }
            else if($price2 < $price3)
            {

                $room_rate['bar_price']=$price2;
            }
            else
            {

                $room_rate['bar_price']=$price3;
            }
            if($room_rate['bookingjini_price'])
            {
                $room_rate['bar_price']=round($room_rate['bar_price']-($room_rate['bar_price'] * $room_rate['bookingjini_price'])/100);//Bookingjini Price
            }
            $coupon_valid_from ;

            // if($coupon)
            // {
            //     if(isset($coupon[0])){
            //         foreach($coupon as $coup){
            //             $coupon_valid_from  = isset($coup->valid_from)?$coup->valid_from:$coup['valid_from'];
            //             $coupon_valid_to = isset($coup->valid_to)?$coup->valid_to:$coup['valid_to'];
            //          }
            //     }else{
            //         $coupon_valid_from  = isset($coupon['valid_from'])?$coupon['valid_from']:'';
            //         $coupon_valid_to = isset($coupon['valid_to'])?$coupon['valid_to']:'';
            //     }
            //     $coupon_date_array = $this->date_range($coupon_valid_from,$coupon_valid_to,"+1 day", "Y-m-d");
            //     $checkin_from = $date1->format('Y-m-d');
            //     $checkin_todate = $date2->format('Y-m-d');
            //     $checkin_to = date('Y-m-d', strtotime('-1 day', strtotime($checkin_todate)));
            //     $checkin_checkout = $this->date_range($checkin_from,$checkin_to,"+1 day", "Y-m-d");
            //     $coupon_model=new Coupons();
            //     $coupon_id = isset($coupon[0]["coupon_id"])?$coupon[0]["coupon_id"]:$coupon["coupon_id"];
            //     $db_coupon=$coupon_model->where('coupon_id',$coupon_id)->first();
            //     if($selected_adult < $max_adult)
            //         {
            //             $adult=$selected_adult-1;//Array
            //             if($room_rate['multiple_occupancy'] && $room_rate['multiple_occupancy'][$adult]>0)
            //             {
            //                 $room_rate['multiple_occupancy'][$adult]=$room_rate['multiple_occupancy'][$adult]-round(($room_rate['multiple_occupancy'][$adult] * $room_rate['bookingjini_price'])/100);
            //
            //                 // $room_price+=$room_rate['multiple_occupancy'][$adult];
            //                 $total_room_price =$room_rate['multiple_occupancy'][$adult];
            //             }
            //             else
            //             {
            //                 $total_room_price  =$room_rate['bar_price'];
            //                 // $room_price+=$room_rate['bar_price'];
            //             }
            //         }
            //         else
            //         {
            //             $total_room_price  =$room_rate['bar_price'];
            //             // $room_price+=$room_rate['bar_price'];
            //         }
            //         $coupon_room_type_id = isset($coupon[0]['room_type_id'])?$coupon[0]['room_type_id']:$coupon['room_type_id'];
            //         $coupon_discount = isset($coupon[0]['discount'])?$coupon[0]['discount']:$coupon['discount'];
            //     if($db_coupon)
            //     {
            //         if($coupon_room_type_id==0)
            //         {
            //
            //                     if(in_array($room_rate['date'],$coupon_date_array) && $j==0){
            //                         $calculated_discounted_price += $total_room_price* $coupon_discount/100;
            //                         $curr_discounted_price = $calculated_discounted_price*sizeof($rooms);
            //                          $j++;
            //                     }
            //
            //
            //         }
            //         else if($coupon_room_type_id==$room_type_id)
            //         {
            //             if(in_array($room_rate['date'],$coupon_date_array) && $j==0){
            //                 $calculated_discounted_price += $total_room_price* $coupon_discount/100;
            //                 $curr_discounted_price = $calculated_discounted_price*sizeof($rooms);
            //                  $j++;
            //             }
            //         }
            //       }
            //         if($db_coupon->discount== $coupon_discount && $discounted_price==$curr_discounted_price)
            //         {
            //             $chk_disc_ok=true;
            //         }
            // }
            //
            // else{
                $chk_disc_ok=true;
            // }
            $extra_adult_price+=$room_rate['extra_adult_price'];
            $extra_child_price+=$room_rate['extra_child_price'];
            if($selected_adult < $max_adult)
            {
                $adult=$selected_adult-1;//Array
                if($room_rate['multiple_occupancy'] && $room_rate['multiple_occupancy'][$adult]>0)
                {
                    $room_rate['multiple_occupancy'][$adult]=$room_rate['multiple_occupancy'][$adult]-round(($room_rate['multiple_occupancy'][$adult] * $room_rate['bookingjini_price'])/100);

                    $room_price+=$room_rate['multiple_occupancy'][$adult];
                }
                else
                {
                    $room_price+=$room_rate['bar_price'];
                }
            }
            else
            {
                $room_price+=$room_rate['bar_price'];
            }

            //array_push($multiple_occ,$room_rate['multiple_occupancy']);
        }
        //Check extra adult price
            $total_extra_child_price=0;
            $total_extra_adult_price=0;
            if($selected_adult>$max_adult)
            {
                $no_of_extra_adult=$selected_adult-$max_adult;
                $total_extra_adult_price=$no_of_extra_adult * $extra_adult_price;
                if($curr_extra_adult_price == $total_extra_adult_price)
                {
                    $extra_adult_ok=true;
                }
            }
            else if($selected_adult==$max_adult)
            {

                $extra_adult_ok=true;
            }
            else
            {
                $extra_adult_ok=true;//This case covered inside loop
            }
            //Check extra child price
            if($selected_child>$max_child)
            {
                $no_of_extra_child=$selected_child-$max_child;
                $total_extra_child_price=$no_of_extra_child * $extra_child_price;
                if($curr_extra_child_price==$total_extra_child_price)
                {
                    $extra_child_ok=true;
                }

            }
            else if($selected_child==$max_child)
            {
                $extra_child_ok=true;
            }
            else
            {
                $extra_child_ok=true;
            }
         $tot_extra_adult_price+= $total_extra_adult_price;
         $tot_extra_child_price+= $total_extra_child_price;

    }
    ///To check the discounted price
    $chk_gst_ok=false;
    //TO check the GST And Other taxes
    $baseCurrency=$this->getBaseCurrency($hotel_id)->currency;

    $price=$room_price+$tot_extra_child_price+$tot_extra_adult_price-$curr_discounted_price;
    $chk_other_tax_ok="";
    if($baseCurrency=='INR'){

        if($diff == 0){
          $diff = 1;
        }
        $gst_price=$this->getGstPrice($diff,sizeof($rooms),$room_type_id,$price);//TO get the GSt price
        if(round($gst_price, 2)==round($curr_gst_price, 2))
        {
            $chk_gst_ok=true;
            $chk_other_tax_ok=true;
        }
    }
    else if($baseCurrency!='INR'){  //Other tax module check
        $orig_tax_details=$this->getorigTaxDetails($hotel_id);
        $chk_gst_ok=true;
        $chk_other_tax_ok=true;
        if(sizeof($orig_tax_details) == sizeof($cur_other_tax)){
          foreach($orig_tax_details as $key=>$orig_tax){
            if($orig_tax['tax_name']==$cur_other_tax[$key]['tax_name'] &&
            round(($orig_tax['tax_percent'] * $price /100),2)==round($cur_other_tax[$key]['tax_price'],2)){
                $chk_other_tax_ok=$chk_other_tax_ok && true;
            }else{
                $chk_other_tax_ok = $chk_other_tax_ok && false;
            }
          }
        }
    }
    if(round($curr_room_price,2) == round($room_price,2) && $extra_child_ok && $extra_adult_ok &&  $chk_disc_ok && $chk_gst_ok && $chk_other_tax_ok)
    {
        return true;
    }
    else
    {
        return false;
    }
}

//GET dynamic tax moudletot_extra_adult_pricetot_extra_adult_price
public function getorigTaxDetails($hotel_id){
    $conditions=array('hotel_id'=>$hotel_id,'is_trash'=>0);
    $finDetails=TaxDetails::where($conditions)->select('tax_name','tax_percent')->first();
    $tax_name_arr=explode(',', $finDetails->tax_name);
    $tax_percent_arr=explode(',',$finDetails->tax_percent);
    $finace_details=array();
    foreach($tax_name_arr as $key=>$tax){
        array_push($finace_details,array('tax_name' => $tax_name_arr[$key],'tax_percent'=> $tax_percent_arr[$key]));
    }
    return $finace_details;
}
//*************GET the GST of the Price of a Individual room*****************/
public function getGstPrice($no_of_nights,$no_of_rooms,$room_type_id,$price)
{

     $chek_price=($price/$no_of_nights)/$no_of_rooms;
     $gstPercent=$this->checkGSTPercent($room_type_id,$chek_price);

     $gstPrice=(($price)*$gstPercent)/100;
     $gstPrice=round($gstPrice, 2);
     return $gstPrice;
}
//*************Update the GST % of the Individual room*****************/
public function checkGSTPercent($room_type_id,$price)
    {
        if($price<1000)
        {
            return 0;
        }
        else if($price>=1000 && $price<7500)
        {
            return 12;
        }
        else if($price>=7500)
        {
            return 18;
        }

    }
//Get User details
public function getUserDetails($user_id)
{
    $user=User::select('*')->where('user_id',$user_id)->first();
    return $user;
}
//Create Invoice
 /*--------------------------------------- INVOICE (START) ---------------------------------------*/

 public function createInvoice($hotel_id,$cart,$coupon,$paid_services,$check_in,$check_out,$user_id,$percentage,$ref_no)
 {
     $booking_id="#####";
     $booking_date=date('Y-m-d');
     $booking_date=date("jS M, Y", strtotime($booking_date));
     $hotel_details=$this->getHotelInfo($hotel_id);
     $u=$this->getUserDetails($user_id);
     $dsp_check_in=date("jS M, Y", strtotime($check_in));
     $dsp_check_out=date("jS M, Y", strtotime($check_out));
     $date1= date("Y-m-d", strtotime($check_in));
     $date2= date("Y-m-d", strtotime($check_out));
     $diff = abs(strtotime($check_out) - strtotime($check_in));
     if($diff == 0){
       $diff = 1;
     }
     $years = floor($diff / (365*60*60*24));
     $months = floor(($diff - $years * 365*60*60*24) / (30*60*60*24));
     $day= floor(($diff - $years * 365*60*60*24 - $months*30*60*60*24)/ (60*60*24));
     if($day == 0){
       $day =1;
     }
     $total_adult=0;
     $total_child=0;
     $total_cost=0;
     $all_room_type_name="";
     $paid_service_details="";
     $all_rows="";
     $total_discount_price=0;
     $total_price_after_discount=0;
     $total_tax=0;
     $display_discount=0.00;
     $other_tax_arr=array();

     //Get base currency and currency hex code
      $baseCurrency=$this->getBaseCurrency($hotel_id)->currency;
      $currency_code=$this->getBaseCurrency($hotel_id)->hex_code;
     foreach($cart as $cartItem)
     {
        $room_type_id=$cartItem['room_type_id'];
        $conditions=array('room_type_id'=>$room_type_id,'is_trash'=>0);
        $room_type_array=MasterRoomType::select('room_type')->where($conditions)->first();
        $room_type=$room_type_array['room_type'];
        $rate_plan_id=$cartItem['rooms'][0]['rate_plan_id'];
        $conditions=array('rate_plan_id'=>$rate_plan_id,'is_trash'=>0);
        $rate_plan_id_array=MasterRatePlan::select('plan_name')->where($conditions)->first();
        $rate_plan=$rate_plan_id_array['plan_name'];

          $i=1;
         $total_price=0;
         $all_room_type_name.=','.sizeof($cartItem['rooms']).' '.$room_type;
         $every_room_type="";
         $all_rows.= '<tr><td rowspan="'.sizeof($cartItem['rooms']).'" colspan="2">'.$room_type.'('.$rate_plan.')</td>';
         foreach($cartItem['rooms'] as $rooms)
         {
            $ind_total_price=0;
            $total_adult+=$rooms['selected_adult'];
            $total_child+=$rooms['selected_child'];
            $ind_total_price=$rooms['bar_price']+$rooms['extra_adult_price']+$rooms['extra_child_price'];

            if($i==1)
            {
                $all_rows= $all_rows.'<td  align="center">'.$i.'</td>
                <td  align="center"> '.$currency_code.round($rooms['bar_price']/$day).'</td>
                <td  align="center">'.round($rooms['extra_adult_price']/$day).'</td>
                <td  align="center">'.round($rooms['extra_child_price']/$day).'</td>
                <td  align="center">'.$day.'</td>
                <td  align="center">'.$currency_code.$ind_total_price.'</td>
                 </tr>';
            }
            else{
                $all_rows= $all_rows.'<tr><td  align="center">'.$i.'</td>
                <td  align="center">'.$currency_code.round($rooms['bar_price']/$day).'</td>
                <td  align="center">'.round($rooms['extra_adult_price']/$day).'</td>
                <td  align="center">'.round($rooms['extra_child_price']/$day).'</td>
                <td  align="center">'.$day.'</td>
                <td  align="center">'.$currency_code.$ind_total_price.'</td>
                 </tr>';
            }

            $i++;
            $total_price+=$ind_total_price;
            $condition = array('hotel_id'=>$hotel_id,'room_type_id'=>$cartItem['room_type_id'],'rate_plan_id'=>$cartItem['rooms'][0]['rate_plan_id'],'ref_no'=>$ref_no);
            $check_existance = BeBookingDetailsTable::select('id')->where($condition)->first();
            if(!$check_existance){
              $be_bookings = new BeBookingDetailsTable();
              $booking_details['hotel_id'] = $hotel_id;
              $booking_details['ref_no'] = $ref_no;
              $booking_details['room_type'] = $room_type_array['room_type'];
              $booking_details['rooms'] = sizeof($cartItem['rooms']);
              $booking_details['room_rate'] = round($rooms['bar_price']/$day);
              $booking_details['extra_adult'] = round($rooms['extra_adult_price']/$day);
              $booking_details['extra_child'] = round($rooms['extra_child_price']/$day);
              $booking_details['adult'] = $rooms['selected_adult'];
              $booking_details['child'] = $rooms['selected_child'];
              $booking_details['room_type_id'] = $cartItem['room_type_id'];
              $getGstPrice = $this->getGstPricePerRoom($booking_details['room_rate'],$cartItem['discounted_price']);
              $booking_details['tax_amount'] = $getGstPrice;
              $booking_details['rate_plan_name'] =$rate_plan_id_array['plan_name'];
              $booking_details['rate_plan_id'] =$cartItem['rooms'][0]['rate_plan_id'];
              if($be_bookings->fill($booking_details)->save()){
                  $res = array('status'=>1,'message'=>'booking details save successfully');
              }
            }

        }
        $total_cost+=$total_price;
        $total_discount_price += $cartItem['discounted_price'];
        $total_discount_price = number_format((float)$total_discount_price, 2, '.', '');
        $total_price_after_discount += ( $total_price- $cartItem['discounted_price']);
        $total_price_after_discount = number_format((float)$total_price_after_discount, 2, '.', '');
        if($cartItem['tax'][0]['gst_price']!=0){
            $total_tax  += $cartItem['tax'][0]['gst_price'];
        }
        else
        {
            foreach($cartItem['tax'][0]['other_tax'] as $key => $other_tax){
                $total_tax+=$other_tax['tax_price'];
                $other_tax_arr[$key]['tax_name']=$other_tax['tax_name'];
                if(!isset($other_tax_arr[$key]['tax_price'])){
                    $other_tax_arr[$key]['tax_price']=0;
                    $other_tax_arr[$key]['tax_price']+=$other_tax['tax_price'];
                }else{
                    $other_tax_arr[$key]['tax_price']+=$other_tax['tax_price'];
                }
            }
        }
        $total_tax = number_format((float)$total_tax, 2, '.', '');
        $every_room_type=$every_room_type.'
        <tr>
            <td colspan="7" align="right" style="font-weight: bold;">Total &nbsp;</td>
            <td align="center" style="font-weight: bold;">'.$currency_code.$total_price.'</td>
        </tr>';

        $all_rows=$all_rows.$every_room_type;
     }
     if($total_discount_price>0)
     {
         $display_discount=$total_discount_price;
     }
     $service_amount=0;
     if(sizeof($paid_services)>0)
     {
        foreach($paid_services as $paid_service)
        {
           $paid_service_details=$paid_service_details.'<tr>
           <td colspan="7" style="text-align:right;">'.$paid_service['service_name'].'&nbsp;&nbsp;</td>
           <td style="text-align:center;">'.$currency_code.($paid_service['price'] * $paid_service['qty']).'</td>
           <tr>';
           $service_amount+=$paid_service['price']*$paid_service['qty'];

        }
        $paid_service_details='<tr><td colspan="8" bgcolor="#ec8849" style="text-align:center; font-weight:bold;">Paid Service Details</td></tr>'.$paid_service_details;

     }
     $gst_tax_details="";
     if($baseCurrency=='INR'){
        $gst_tax_details='<tr>
        <td colspan="7" align="right"> GST &nbsp;&nbsp;&nbsp;</td>
        <td align="center">'.$currency_code.$total_tax.'</td>
        </tr>';
     }
     $other_tax_details="";
     if(sizeof($other_tax_arr)>0)
     {

        foreach($other_tax_arr as $other_tax)
        {
           $other_tax_details=$other_tax_details.'<tr>
           <td colspan="7" style="text-align:right;">'.$other_tax['tax_name'].'&nbsp;&nbsp;</td>
           <td style="text-align:center;">'.$currency_code.$other_tax['tax_price'].'</td>
           <tr>';
        }
     }
     $total_amt = $total_price_after_discount+$service_amount;
     $total_amt = number_format((float)$total_amt, 2, '.', '');
     $total=$total_amt+$total_tax;
     $total = number_format((float)$total, 2, '.', '');

     if($percentage==0 || $percentage==100)
     {
         $paid_amount=$total;
         $paid_amount = number_format((float)$paid_amount, 2, '.', '');
     }
     else
     {
         $paid_amount=$total*$percentage/100;
         $paid_amount = number_format((float)$paid_amount, 2, '.', '');
     }

     $due_amount=$total-$paid_amount;
     $due_amount = number_format((float)$due_amount, 2, '.', '');
     $body='<html>
     <head>
     <style>
         html{
             font-family: Arial, Helvetica, sans-serif;
         }
         table, td {
             height: 26px;
         }
         table, th {
             height: 35px;
         }
         p{
             color: #000000;
         }
     </style>
     </head>
     <body style="color:#000;">
     <div style="margin: 0px auto;">
     <table width="100%" align="center">
     <tr>
     <td style="border: 1px #0; padding: 4%; border-style: double;">
         <table width="100%" border="0">
             <tr>
                 <th colspan="2" valign="middle" style="font-size: 23px;"><u>BOOKING CONFIRMATION</u></th>
             </tr>
             <tr>
             <td><b style="color: #ffffff;">*</b></td>
             </tr>
             <tr>
                 <td>
                     <div>
                         <div style="font-weight: bold; font-size: 22px; color:#fff; background-color: #1d99b5; padding: 5px;">'.$hotel_details['hotel_name'].'</div>
                     </div>
                 </td>
                 <td style="font-size: 16px;font-weight: bold;" align="right">BOOKING ID : '.$booking_id.'</td>
             </tr>
             <tr>
                 <td colspan="2"><b style="color: #ffffff;">*</b></td>
             </tr>
             <tr>
                 <td colspan="2"><b>Dear '.$u->first_name.' '.$u->last_name.',</b></td>
             </tr>';
             if($hotel_id == 1602){
                 $body.=' <tr>
                     <td colspan="2" style="font-size:17px;"><b>We welcome you to the '.$hotel_details->hotel_name.' as your property of choice for your visit and booking through our hotel\'s website. Your booking confirmation details are asunder:</b></td>
                 </tr>';
             }
             else{
                 $body.=' <tr>
                     <td colspan="2" style="font-size:17px;"><b>We hope this email finds you well. Thank you for choosing '.$hotel_details->hotel_name.' as your property of choice for your visit and booking through our hotel\'s website. Your booking confirmation details have been provided below:</b></td>
                 </tr>';
             }
             $body.='<tr>
                 <td colspan="2"><b style="color: #ffffff;">*</b></td>
             </tr>
     </table>

         <table width="100%" border="1" style="border-collapse: collapse;">
             <th colspan="2" bgcolor="#ec8849">BOOKING DETAILS</th>
             <tr>
                 <td >PROPERTY & PLACE</td>
                 <td>'.$hotel_details->hotel_name.'</td>
             </tr>
             <tr>
                 <td width="45%">NAME OF PRIMARY GUEST</td>
                 <td>'.$u->first_name.' '.$u->last_name.'</td>
             </tr>
             <tr>
                 <td>PHONE NUMBER</td>
                 <td>'.$u->mobile.'</td>
             </tr>
             <tr>
                 <td>EMAIL ID</td>
                 <td>'.$u->email_id.'</td>
             </tr>
             <tr>
                 <td>ADDRESS</td>
                 <td>'.$u->address.','.$u->city.','.$u->state.','.$u->country.'</td>
            </tr>
             <tr>
         <td>BOOKING DATE</td>
         <td>'.$booking_date.'</td>
     </tr>
             <tr>
         <td>CHECK IN DATE</td>
         <td>'.$dsp_check_in.'</td>
     </tr>
             <tr>
         <td>CHECK OUT DATE</td>
         <td>'.$dsp_check_out.'</td>
     </tr>
             <tr>
         <td>CHECK IN TIME</td>
         <td>'.$hotel_details->check_in.'</td>
     </tr>
             <tr>
         <td>CHECK OUT TIME</td>
         <td>'.$hotel_details->check_out.'</td>
     </tr>
                 <tr>
         <td>TOTAL ADULT</td>
         <td>'.$total_adult.'</td>
     </tr>
                 <tr>
         <td>TOTAL CHILDREN</td>
         <td>'.$total_child.'</td>
     </tr>
             <tr>
         <td>NUMBER OF NIGHTS</td>
         <td>'.$day.'</td>
     </tr>
             <tr>
         <td>NO. & TYPES OF ACCOMMODATIONS BOOKED</td>
         <td>'.substr($all_room_type_name,1).'</td>
     </tr>

     </table>

         <table width="100%" border="1" style="border-collapse: collapse;">
             <tr>
     <th colspan="8" valign="middle" height="" style="font-size: 20px;">TARIFF APPLICABLE</th>
     </tr>
             <tr>
         <th colspan="2" bgcolor="#ec8849" align="center">Room Type</th>
         <th bgcolor="#ec8849" align="center">Rooms</th>
         <th bgcolor="#ec8849" align="center">Room Rate</th>
         <th bgcolor="#ec8849" align="center">Extra Adult Price</th>
         <th bgcolor="#ec8849" align="center">Extra Child Price</th>
         <th bgcolor="#ec8849" align="center">Days</th>
         <th bgcolor="#ec8849" align="center">Total Price</th>
     </tr>
             '.$all_rows.'
     <tr>
         <td colspan="8"><p style="color: #ffffff;">*</p></td>
     </tr>
             <tr>
         <td colspan="7" align="right">Total Room Rate&nbsp;&nbsp;</td>
         <td align="center">'.$currency_code.$total_cost.'</td>
     </tr>

     <tr>
         <td colspan="7" align="right">Discount &nbsp;&nbsp;</td>
         <td align="center">'.$currency_code.$display_discount.'</td>
     </tr>
     <tr>
         <td colspan="7" align="right">After Discount&nbsp;&nbsp;</td>
         <td align="center">'.$currency_code.$total_price_after_discount.'</td>
     </tr>
     '.$paid_service_details.'
     <tr>
         <td colspan="7" align="right">Total room rate with paid services &nbsp;&nbsp;</td>
         <td align="center">'.$currency_code.$total_amt.'</td>
     </tr>
         '.$gst_tax_details.'
         '.$other_tax_details.'
     <tr>
         <td colspan="7" align="right"><p>Total Amount&nbsp;&nbsp;</p></td>
         <td align="center">'.$currency_code.$total.'</td>
     </tr>
     <tr>
         <td colspan="7" align="right">Total Paid Amount&nbsp;&nbsp;</td>
         <td align="center">'.$currency_code.'<span id="pd_amt">'.$paid_amount.'</span></td>
     </tr>
     <tr>
         <td colspan="7" align="right">Pay at hotel&nbsp;&nbsp;</td>
         <td align="center">'.$currency_code.'<span id="du_amt">'.$due_amount.'</span></td>
     </tr>
     <tr>
         <td colspan="8"><p style="color: #ffffff;">* </p></td>
     </tr>';
 
    
         if($hotel_id == 1602){
             $body.='<tr>
                     <td colspan="8">
                     <strong>Package includes:</strong>
                     <ul>
                         <li>To & fro transfers from Bhubaneswar.</li>
                         <li>Accommodation.</li>
                         <li>Breakfast for Classic Cottage.</li>
                         <li>Breakfast, Lunch, High Tea and Dinner (buffet)(For all cottages other than Classic).</li>
                         <li>All activities mentioned in the itinerary including entry fees etc.</li>
                     </ul>
                     <strong>Package does not include:</strong>
                     <ul>
                         <li>A la Carte orders and bar services.</li>
                     </ul>
                     <strong>Cancellation & Refund Policy:</strong>
                     <ul>
                         <li>Cancellation 24 hours prior to check-in date/ time: No refund.</li>
                         <li>Cancellation between 24 – 72 hours prior to check-in date/ time: 50% refund.</li>
                         <li>Cancellation more than 72 hours prior to check-in date/ time: 100% refund.</li>
                     </ul>
                     </td>
             </tr>';
         }

     $body.='</table>

         <table width="100%" border="0">
             <tr>
     <th colspan="2" style="font-size: 21px; color: #ffffff;"><u>*</u></th>
     </tr>
             <tr>
         <td colspan="2"><span style="color: #000; font-weight: bold; font-size: 20px;">We are looking forward to hosting you at '.$hotel_details->hotel_name.'.</span></td>
     </tr>
     <tr>
         <td colspan="2"><span style="color: #fff; font-weight: bold; font-size: 20px;">*</span></td>
     </tr>

             <tr>
         <td colspan="2"><span style="color: #000; font-weight: bold;">Regards,<br />
             Reservation Team<br />
             '.$hotel_details->hotel_name.'<br />
             '.$hotel_details->hotel_address.'<br />
             Mob   : '.$hotel_details->mobile.'<br />
             Email : '.$hotel_details->email_id.'</span>
         </td>
     </tr>
     <tr>
         <td colspan="2"><span style="color: #fff; font-weight: bold; font-size: 20px;">*</span></td>
     </tr>
     <tr>
         <td colspan="2"><span style="color: #000; font-weight: bold; font-size: 20px;"><u>Note</u> :</span> Taxes applicable may change subject to govt policy at the time of check in.</td>
     </tr>
     <tr>
         <td colspan="2"><span style="color: #fff; font-weight: bold; font-size: 20px;">*</span></td>
     </tr>
     <!--tr>
         <td colspan="2"><span style="color: #000; font-weight: bold; font-size: 20px;"><u>Terms & Conditions</u> :</span></td>
     </tr>
     <tr>
         <td colspan="2"><span style="color: #fff; font-weight: bold; font-size: 20px;">*</span></td>
     </tr-->
     <!--<tr>
         <td colspan="2"><span style="color:#f00"><li>On Any 100% Cancellation Policy there will be a 3% mandatory deduction from the booking amount due to payment gateway charges.</li></span></td>
     </tr>-->
     <tr>
         <td colspan="2"><span style="color: #000; font-weight: bold;">'.$hotel_details->terms_and_cond.'</span></td>
     </tr>
     <tr>
         <td colspan="2"><span style="color: #fff; font-weight: bold; font-size: 20px;">*</span></td>
     </tr>
     <!--tr>
         <td colspan="2"><span style="color: #000; font-weight: bold; font-size: 20px;"><u>Cancellation Policy</u> :</span></td>
     </tr>
     <tr>
         <td colspan="2"><span style="color: #fff; font-weight: bold; font-size: 20px;">*</span></td>
     </tr-->';
     if($hotel_details->cancel_policy!=""){
     $body.='<tr>
         <td colspan="2"><span style="color: #000; font-weight: bold;">'.$hotel_details->cancel_policy.'</span></td>
     </tr>';
     }else{
         $body.='<tr>
         <td colspan="2"><span style="color: #000; font-weight: bold;">'.$hotel_details->hotel_policy.'</span></td>
     </tr>';
     }
     $body.='</table>
     </td>
     </tr>
     </table>
     </div>
     </body>
     </html>';
     
     return $body;
 }
 /*------------------------------------- INVOICE MAIL (START) ------------------------------------*/
 public  function preinvoiceMail($id)
 {
     $invoice        = $this->successInvoice($id);
     $invoice=$invoice[0];
     $booking_id     = date("dmy", strtotime($invoice->booking_date)).str_pad($invoice->invoice_id, 4, '0', STR_PAD_LEFT);
     $u=$this->getUserDetails($invoice->user_id);

     $subject        = "Booking From ".$invoice->hotel_name;
     $body           = $invoice->invoice;
     $body           = str_replace("#####", $booking_id, $body);
     $body           = str_replace("BOOKING CONFIRMATION", "BOOKING CONFIRMATION(UNPAID)", $body);
     $to_email='reservations@bookingjini.com';//don't change this
    //  if($this->sendMail($to_email,$body, $subject,"",$invoice->hotel_name))
    //  {
    //      return true;
    //  }
    return true;

 }
 /*--------------------------------------- INVOICE MAIL (END) ------------------------------------*/


/*----------------------------------- SUCCESS BOOKING (START) -----------------------------------*/

public function successInvoice($id)
{

    $query      = "Select DISTINCT(a.invoice_id), b.user_id, b.room_type_id, a.booking_date, a.invoice,a.ids_re_id, a.hotel_name, a.hotel_id, a.room_type, a.check_in_out, a.total_amount, a.paid_amount, c.hotel_address, c.mobile, c.email_id, c.terms_and_cond from booking_engine.invoice_table a, booking_engine.hotel_booking b, kernel.hotels_table c where a.invoice_id=b.invoice_id AND a.hotel_id=c.hotel_id AND a.invoice_id=$id";
    $result    = DB::select($query);
    return $result;
}

/*------------------------------------ SUCCESS BOOKING (END) ------------------------------------*/

/*
*Email Invoice
*@param $email for to email
*@param $template is the email template
*@param $subject for email subject
*/
public function sendMail($email,$template, $subject,$hotel_email,$hotel_name)
{
    $data = array('email' =>$email,'subject'=>$subject);
    $data['template']=$template;
    $data['hotel_name']=$hotel_name;
    if($hotel_email=="")
    {
        $data['hotel_email']="";
        $mail_array=['muramamullasiri@gmail.com'];
    }
    else if(sizeof($hotel_email)>1)
    {
        $data['hotel_email']=$hotel_email[0];
        $mail_array=['trilochan.parida@5elements.co.in','reservations@bookingjini.com',$hotel_email[1]];
    }
    else if(sizeof($hotel_email)==1){
        $data['hotel_email']=$hotel_email[0];
        $mail_array=['trilochan.parida@5elements.co.in','reservations@bookingjini.com'];
    }
    else
    {
        $data['hotel_email']="";
        $mail_array=['trilochan.parida@5elements.co.in','reservations@bookingjini.com'];
    }
    $data['mail_array']=$mail_array;
    try{
        Mail::send([],[],function ($message) use ($data)
        {
            if($data['hotel_email']!="")
            {
                $message->to($data['email'])
                ->cc($data['hotel_email'])
                ->bcc($data['mail_array'])
                ->from( env("MAIL_FROM"), $data['hotel_name'])
                ->subject( $data['subject'])
                ->setBody($data['template'], 'text/html');
            }
            else
            {
                $message->to($data['email'])
                ->from( env("MAIL_FROM"), $data['hotel_name'])
                //->cc('gourab.nandy@bookingjini.com')
                ->subject( $data['subject'])
                ->setBody($data['template'], 'text/html');
            }
        });
        if(Mail::failures())
        {
            return false;
        }
        return true;
    }
    catch(Exception $e){
      return true;
    }

}

//Success full booking

/*public function SuccessBooking(string $email_id,string $tr_id,Request $request)
{

}*/
/*----------------------------------- SUCCESS BOOOKING (START) ----------------------------------*/
public function testbooking(int $invoice_id, Request $request){
    $this->successBooking(20359,'200805204949221389380319ccd','VISA-City','3e99e047e08db021b9d38b588daedf2c4ed0312fd224a20dca6f5ab4500c4938','05082020333');
}
public function gemsBooking(Request $request,int $invoice_id,string $gems){
    $get_url = $this->successBooking($invoice_id,$gems,'NA','NA',12345);
    if($get_url){
        return response()->json(array('status'=>1,"mesage"=>"offline booking successful"));
    }
    else{
      return response()->json(array('status'=>0,"mesage"=>"offline booking fails"));
    }
}
public function WinhmsBooking(Request $request,int $invoice_id,string $winhms){
    $get_url = $this->successBooking($invoice_id,$winhms,'NA','NA',12345);
    if($get_url){
        return response()->json(array('status'=>1,"mesage"=>"offline booking successful"));
    }
    else{
      return response()->json(array('status'=>0,"mesage"=>"offline booking fails"));
    }
}
public function crsBooking(int $invoice_id,string $gems){
    $get_url = $this->successBooking($invoice_id,$gems,'NA','NA',12345);
    if($get_url){
        return response()->json(array('status'=>1,"mesage"=>"offline booking successful"));
    }
    else{
      return response()->json(array('status'=>0,"mesage"=>"offline booking fails"));
    }
}
public function successBooking($invoice_id, $mihpayid, $payment_mode, $hash, $txnid)
{
    $invoice_model= new Invoice();
    $transaction_model = new OnlineTransactionDetail();
    if($mihpayid != "true" && $mihpayid != "crs" && $mihpayid != "quickpayment"){
      $dta_avail=$transaction_model->where('transaction_id',$txnid)->where('payment_id',$mihpayid)->first();
      if(!empty($dta_avail))
      {
        return '';
      }
    //  $this->invoiceMail($invoice_id);
    }
    $invoice_details=Invoice::where('invoice_id',$invoice_id)->first();
    $invoice_details->booking_status = 1;
    if($mihpayid == 'crs'){
        $invoice_details->booking_source = 'CRS';
    }
    if($mihpayid == 'true'){
        $invoice_details->booking_source = 'GEMS';
    }
    if($mihpayid == 'quickpayment'){
        $invoice_details->booking_source = 'QUICKPAYMENT';
    }
    if($invoice_details->save())
    {
        $hotel_booking_model=HotelBooking::where('invoice_id',$invoice_id)->get();
        foreach($hotel_booking_model as $key=> $hbm) {
            $hbm->booking_status = 1;
            $hbm->save();
            $resp = $this->updateInventory($hbm,$invoice_details);
        }
        $getPMS_details = DB::connection('cmlive')->table('pms_account')->where('name','GEMS')->first();
          $hotel_info = explode(',',$getPMS_details->hotels);
          if(in_array($invoice_details->hotel_id,$hotel_info)){
            $pushBookignToGems = $this->pushBookingToGems($invoice_id,$mihpayid);
          }
    }
    $transaction_model->payment_mode    = $payment_mode;
    $transaction_model->invoice_id      = $invoice_id;
    $transaction_model->transaction_id  = $txnid;
    $transaction_model->payment_id      = $mihpayid;
    $transaction_model->secure_hash     = $hash;

    if($transaction_model->save())
    {
        //return $invoice_model;
        $hotel_id       = $invoice_details['hotel_id'];
        $company_dtls   = HotelInformation::where('hotel_id',$hotel_id)->first();
        $company_id     = $company_dtls->company_id;

        //Fetch Api Details
        $failure_message="Booking failed";
        $CompanyDetaiils   = CompanyDetails::where('company_id',$company_id)->first();
        if($CompanyDetaiils)
        {
            //$res=array("status"=>1,"message"=>"Payment status updated successfully","comp_url"=>$CompanyDetaiils->company_url);
            return $CompanyDetaiils->company_url;
        }
        else
        {
            return '';
        }
    }
}

    /*------------------------------------ SUCCESS BOOOKING (END) -----------------------------------*/
/*------------------------------------- INVOICE MAIL (START) ------------------------------------*/
public  function invoiceMail($id)
{
    $hotel_info=new HotelInformation();
    $invoice        = $this->successInvoice($id);
    $invoice=$invoice[0];
    $booking_id     = date("dmy", strtotime($invoice->booking_date)).str_pad($invoice->invoice_id, 4, '0', STR_PAD_LEFT);
    $u=$this->getUserDetails($invoice->user_id);
    $user_email_id=$u['email_id'];
    $hotel_contact=$hotel_info->getEmailId($invoice->hotel_id);
    $hotel_email_id=$hotel_contact['email_id'];
    $hotel_mobile=$hotel_contact['mobile'];
    $subject        = $invoice->hotel_name." Booking Confirmation";
    $body           = $invoice->invoice;
    $body           = str_replace("#####", $booking_id, $body);
    if($this->sendMail($user_email_id,$body,$subject,$hotel_email_id,$invoice->hotel_name))
    {
         $to=$u['mobile'];
         $msg="Your transaction has been successful(Booking ID- ".$booking_id."). For more details kindly check your mail ID given at the time of booking.";
         if($this->sendSMS($to, $msg))
         {
           $to=$hotel_mobile;
           $msg="You have got new booking From Bookingjini(Booking ID- ".$booking_id."). For more details kindly check registered email ID.";
           $this->sendSMS($to, $msg);
         }
        if($invoice->hotel_id == 1602){
            $sendMailtoAgent = $this->agentMail($id,$hotel_email_id,$invoice->hotel_name,$booking_id);
        }
        return true;
    }
}
/*--------------------------------------- INVOICE MAIL (END) ------------------------------------*/


public function agentMail($invoice_id,$hotel_email_id,$hotel_name,$booking_id)
{
   return true;
    $subject=$hotel_name." Booking Confirmation";
    $rateLogs=DB::select("CALL getAgentDetails('".$invoice_id."')");
    if(isset($rateLogs[0]->username) && sizeof($rateLogs[0])>0){
        $email = $rateLogs[0]->username;
        $template = $rateLogs[0]->invoice;
        $template  = str_replace("#####", $booking_id, $template);
        $data = array('email' =>$email,'subject'=>$subject, 'template'=>$template,'hotel_name'=>$hotel_name,'hotel_email'=>$hotel_email_id);
        Mail::send([],[],function ($message) use ($data)
        {
            $message->to($data['email'])
            ->cc($data['hotel_email'])
            ->from( env("MAIL_FROM"), 'Agent Bookings')
            ->subject( $data['subject'])
            ->setBody($data['template'], 'text/html');
        });
        if(Mail::failures())
        {
            return false;
        }
        return true;
    }
}

/*------------------------------------- Send SMS after success booking (START) ------------------------------------*/
public function sendSMS($to, $msg)
     {
        $messageToSend = $msg;
        $messageToSend = urlencode($messageToSend);
        $date=Date('d-m-Y\TH:i:s');
        $smsURL = "https://apps.sandeshlive.com/API/WebSMS/Http/v1.0a/index.php?username=531&password=oYSeaxIVK9UPgvG0&sender=BKJINI&to=".$to."&message=".$messageToSend."&reqid=1&format={json|text}&route_id=TRANS-OPT-IN&sendondate=".$date;
        $ch = curl_init();
        // Disable SSL verification
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        // Will return the response, if false it print the response
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // Set the url
        curl_setopt($ch, CURLOPT_URL,$smsURL);
        // Execute
        $result=curl_exec($ch);
        // Closing
        curl_close($ch);

        return true;
     }
/*------------------------------------- Send SMS after success booking (END) ------------------------------------*/

/*------------------------------------- UPDATE INVENTORY (START) ------------------------------------*/
public  function updateInventory($bookingDeatil,$invoice_details)
{
    $mindays=0;
    $updated_inv=array();
    $inventory=new Inventory();
    $inv_update=array();
    $inv_data=$this->invService->getInventeryByRoomTYpe($bookingDeatil->room_type_id,$bookingDeatil->check_in,$bookingDeatil->check_out, $mindays);
    if($inv_data)
    {
        foreach($inv_data as $inv)
        {
            $updated_inv["invoice_id"]=$bookingDeatil->invoice_id;
            $updated_inv["hotel_id"]=$bookingDeatil->hotel_id;
            $updated_inv["check_in"]=$bookingDeatil->check_in;
            $updated_inv["check_out"]=$bookingDeatil->check_out;
            $updated_inv["room_type_id"]=$inv['room_type_id'];
            $updated_inv["user_id"]=0;//User id set CM
            $updated_inv["date_from"]=$inv['date'];
            $updated_inv["date_to"]=date('Y-m-d', strtotime($inv['date']));
            $updated_inv["no_of_rooms"]=$inv['no_of_rooms']-$bookingDeatil->rooms;//Deduct inventory
            $updated_inv["client_ip"]= '1.1.1.1';//\Illuminate\Http\Request::ip();//As server is updating inventory automatically afetr succ booking
            $updated_inv["ota_id"] = 0; //Don't Remove this
            $resp=$this->updateInventoryService->updateInv($updated_inv,$invoice_details);
            if($resp['be_status']="Inventory update successfull")
            {
                array_push($inv_update,1);
            }
        }
    }
    $inv_update_status=true;
    foreach($inv_update as $up)
    {
        if(!$up==1)
        {
            $inv_update_status=false;
        }
    }
    return  $inv_update_status;
}
/*--------------------------------------- UPDATE INVENTORY (END) ------------------------------------*/

/*----------------------------------- RESPONSE FROM PAYU (START) ----------------------------------*/

public function payuResponse(Request $request)
{
$data=$request->all();
$status     = $data['status'];
$email      = $data['email'];
$firstname    = $data['firstname'];
$productinfo  = $data['productinfo'];
$amount     = $data['amount'];
$txnid      = $data['txnid'];
$hash       = $data['hash'];
$payment_mode   = $data['mode'];
$mihpayid     = $data['mihpayid'];
$curdate    = date('Ymd');
$hashData     = 'sHFyCrYD|'.$status.'|||||||||||'.$email.'|'.$firstname.'|'.$productinfo.'|'.$amount.'|'.$txnid.'|HpCvAH';
if (strlen($hashData) > 0)
{
  $secureHash = hash('sha512' , $hashData);
  if(strcmp($secureHash,$hash)== 0 )
  {
    if($status == 'success')
    {
      $id=str_replace($curdate,'',$txnid);
      $url=$this->successBooking($id, $mihpayid, $payment_mode, $hash, $txnid);
        if($url!='')
        {
        ?>
                <html>
            <body onLoad="document.response.submit();">
                <div style="width:300px; margin: 200px auto;">
                <img src="loading.png" />
                </div>
                <form action="http://<?=$url?>/v3/ibe/#/invoice-details/<?=$id?>/<?=$txnid?>" name="response" method="GET">
                </form>
            </body>
            </html>
        <?php
        }else{
            echo '<h1>Error!</h1>';
            echo '<p>Database error</p>';
        }
    }
    else
    {
      ?>
          <script>alert("Sorry! Trasaction not completed");</script>
          <?php
      header('Location: index.php');
    }
  }
  else
  {
    echo '<h1>Error!</h1>';
    echo '<p>Hash validation failed</p>';
    }
}
else
{
  echo '<h1>Error!</h1>';
  echo '<p>Invalid response</p>';
}

}

    /*------------------------------------ RESPONSE FROM PAYU (END) -----------------------------------*/

/*------------------------------------ AFTER SUCCESS VIEW INVOICE -----------------------------------*/
public function invoiceDetails(int $invoice_id,Request $request)
{
$invoice = $this->successInvoice($invoice_id);
$invoice=$invoice[0];
if(!$invoice)
{
  $res=array('status'=>0,"message"=>"Invoice details not found");
  return response()->json($res);
}
$booking_id = date("dmy", strtotime($invoice->booking_date)).str_pad($invoice->invoice_id, 4, '0', STR_PAD_LEFT);
$body = $invoice->invoice;
$body = str_replace("#####", $booking_id, $body);
if($body)
{
$res=array('status'=>1,"message"=>"Invoice feteched sucesssfully!",'data'=>$body);
return response()->json($res);
}
else
{
$res=array('status'=>0,"message"=>"Invoice details not found");
return response()->json($res);
}
}
//BE Calendar Inventory and rates
public function beCalendar(string $api_key,int $hotel_id ,string $startDate,string $currency_name,Request $request)
{
    $startDate=date('Y-m-d', strtotime($startDate));
    $result=array();
    $date_from=$startDate;
    for($i=0; $i<30;$i++)
    {
        $d=$date_from;
        $d1=date('Y-m-d', strtotime($d . ' +1 day'));
        $inv_array=$this->getInvByHotel($api_key, $hotel_id , $d ,$d1,$currency_name,$request);  
        

        $inv_array=$inv_array->getData();
        $inv=$inv_array->data;
        $avail=0;
        // $price='unavailable';
        $price=0;
        $date=$d;
        foreach ($inv as $in)
        {
            if(sizeof($in->inv)>0)
            {

                if($in->inv[0]->no_of_rooms!=0 && $in->inv[0]->block_status==0 && $in->min_room_price!=0)
                {
                    $avail=1;
                    if($price == 0)
                    {
                        $price=$in->min_room_price;
                        $room_type_id = $in->room_type_id;

                    }
                    elseif($price > $in->min_room_price){
                        $price=$in->min_room_price;
                        $room_type_id = $in->room_type_id;

                    }
                    $date=$in->inv[0]->date;
                }

            }
        }

        $result[]=array("date"=>$date, "avail"=>$avail, "price"=>$price,"room_type_id"=>$room_type_id);
        $date_from=date('Y-m-d', strtotime($d . ' +1 day'));
    }
    foreach($result as $key=>$val){

        if($val["price"] === -1){
            $result[$key]["price"]=$result[29]["price"];
        }
    }
    $res=array('status'=>1,"message"=>"Invoice feteched sucessfully!",'data'=>$result);
    return response()->json($res);
}
//Get tax details if Base currency other INR
public function getTaxDetails(string $company_id,string $hotel_id,Request $request){
    $currencyname=CompanyDetails::where('company_id',$company_id)->select('currency')->first();
    if($currencyname){
        if($currencyname->currency !='INR'){
            $conditions=array('hotel_id'=>$hotel_id,'is_trash'=>0);
            $finDetails=TaxDetails::where($conditions)->select('tax_name','tax_percent')->first();
            if($finDetails){
                $tax_name_arr=explode(',', $finDetails->tax_name);
                $tax_percent_arr=explode(',',$finDetails->tax_percent);
                $finace_details=array();
                foreach($tax_name_arr as $key=>$tax){
                    array_push($finace_details,array('tax_name' => $tax_name_arr[$key],'tax_percent'=> $tax_percent_arr[$key]));
                }
                $tax_details['tax_type']='NonGST';
                $tax_details['tax_applicable']=true;
                $tax_details['tax_rules']=$finace_details;
                $res=array('status'=>1,"message"=>"Tax details fetched successfully!",'tax_details'=>$tax_details);
                return response()->json($res);
            }else{
                $tax_details['tax_type']='NonGST';
                $tax_details['tax_applicable']=false;
                $res=array('status'=>1,"message"=>"Tax details fetched successfully!",'tax_details'=>$tax_details);
                return response()->json($res);
            }

        }else{
            $conditions=array('hotel_id'=>$hotel_id,'is_trash'=>0);
            $finDetails=TaxDetails::where($conditions)->select('tax_pay_hotel')->first();

            $tax_details['tax_type']='GST';
            if($finDetails){
                $tax_details['tax_pay_hotel']=$finDetails->tax_pay_hotel;
            }else{
                $tax_details['tax_pay_hotel']=0;
            }

            $res=array('status'=>1,"message"=>"Tax details fetched successfully!",'tax_details'=>$tax_details);
              return response()->json($res);
        }
        }else{
            $res=array('status'=>0,"message"=>"Tax details fetching failed!");
            return response()->json($res);
        }
    }
    /*
    Get room amenity for showing in room details
    * @author Rajashree
    * function getRoomdetails for fetching data
    **/
    public function getRoomamenity($ammenityId)
    {
    $amenities=HotelAmenities::whereIn('hotel_amenities_id', $ammenityId)
    ->select('hotel_amenities_name','font_class')
    ->get();
    return ($amenities)?$amenities:array();
    }
    /*
    Get discount of the room By room type id
    * @author Rajashree
    * function getInvByHotel for fetching data
    **/
    public function getAllPublicCupons(String $hotel_id,$from_date,$to_date,Request $request){

    $begin = strtotime($from_date);
    $end = strtotime($to_date);
    $data_array = array();
    for($currentDate = $begin; $currentDate <= $end; $currentDate += (86400)){
    $data_array_present = array();
    $data_array_notpresent = array();
    $status = array();
    // return $currentDate;
    $Store = date('Y-m-d',$currentDate);
    // $get_data = DB::select(DB::raw('select coupon_id,room_type_id,
    // case when room_type_id != 0 then "present" when room_type_id = 0 then "notpresent" end as status,
    // coupon_name,coupon_code,coupon_for,valid_from,
    // valid_to,discount_type,discount,a.date,a.abc
    // FROM
    // (
    // select t2.coupon_id,
    // case when t2.room_type_id!=0 then t2.room_type_id else 0 end as room_type_id,
    // coupon_name,coupon_code,coupon_for,valid_from,valid_to,discount_type,discount,"'.$Store.'" as date,
    // case when "'.$Store.'" between valid_from and valid_to then "yes" else "no" end as abc
    // from booking_engine.coupons
    // INNER JOIN
    // (
    // SELECT room_type_id,MAX(coupon_id) AS coupon_id
    // FROM booking_engine.coupons where hotel_id = "'.$hotel_id.'" and coupon_for = 1 and is_trash = 0
    // and ("'.$Store.'" between valid_from and valid_to)
    // GROUP BY room_type_id
    // order by coupon_id desc
    // ) t2 ON coupons.room_type_id = t2.room_type_id AND coupons.coupon_id = t2.coupon_id
    // ) AS a where a.abc = "yes"
    // order by room_type_id,coupon_id desc'));

    $get_data = DB::select(DB::raw('select coupon_id,room_type_id,
    case when room_type_id != 0 then "present" when room_type_id = 0 then "notpresent" end as status,
    coupon_name,coupon_code,coupon_for,valid_from,
    valid_to,discount_type,discount,a.date,a.abc
    FROM
    (
    select t2.coupon_id,
    case when t2.room_type_id!=0 then t2.room_type_id else 0 end as room_type_id,
    coupon_name,coupon_code,coupon_for,valid_from,valid_to,discount_type,discount,"'.$Store.'" as date,
    case when "'.$Store.'" between valid_from and valid_to then "yes" else "no" end as abc
    from booking_engine.coupons
    INNER JOIN
    (
    SELECT room_type_id,
    substring_index(group_concat(cast(coupon_id as CHAR) order by discount desc), ",", 1 ) as coupon_id,MAX(discount)
    FROM booking_engine.coupons where hotel_id = "'.$hotel_id.'" and coupon_for = 1 and is_trash = 0
    and ("'.$Store.'" between valid_from and valid_to)
    GROUP BY room_type_id
    order by coupon_id desc
    ) t2 ON coupons.room_type_id = t2.room_type_id AND coupons.coupon_id = t2.coupon_id
    ) AS a where a.abc = "yes"
    order by room_type_id,coupon_id desc'));


    // return $get_data;
    foreach($get_data as $data){
    $status[] = $data->status;
    $data_present['coupon_id']        = $data->coupon_id;
    $data_present['room_type_id']     = $data->room_type_id;
    $data_present['date']             = $data->date;
    $data_present['coupon_name']      = $data->coupon_name;
    $data_present['coupon_code']      = $data->coupon_code;
    $data_present['valid_from']       = $data->valid_from;
    $data_present['valid_to']         = $data->valid_to;
    $data_present['discount_type']    = $data->discount_type;
    $data_present['discount']         = $data->discount;
    $data_array_present[] = $data_present;
    }
    if($data_array_present){
    for($i=0;$i<sizeof($data_array_present);$i++){
    $data_array[] = $data_array_present[$i];
    }
    }
    }
    
    if(sizeof($data_array)>0){
    $res=array('status'=>1,'message'=>"Public coupon retrieved successfully",'data'=>$data_array);
    return response()->json($res);
    }else{
    $res=array('status'=>1,'message'=>"!Sorry Public coupon is not available",'data'=>array());
    return response()->json($res);
    }
    }
        //fetching coupon for public
        public function AllPublicCupon(string $hotel_id,$from_date,$to_date,Request $request){
            $dates = $this->date_range($from_date, $to_date, "+1 day", "Y-m-d");
            $coup;
           foreach ($dates as $date) {
               $cupons=Coupons::select('room_type_id','discount','coupon_code','discount_type','coupon_id','valid_from','valid_to')
               ->where('hotel_id',$hotel_id)
               ->where('is_trash',0)
               ->where('coupon_for',1)
               ->whereDate('valid_from', '<=',$date)
               ->whereDate('valid_to', '>=', $date)
               ->get();
               if(sizeof($cupons)>0){
                   $coup = $cupons;
               }
           }
           if(is_object($coup) && sizeof($coup)>0){
               $res=array('status'=>1,'message'=>"Public coupon retrieved successfully",'data'=>$coup);
               return response()->json($res);
               }else{
               $res=array('status'=>1,'message'=>"!Sorry no public coupon",'data'=>array());
               return response()->json($res);
              }
           }
          function date_range($first, $last, $step = '+1 day', $output_format = 'Y-m-d' ) {
               $dates = array();
               $current = strtotime($first);
               $last = strtotime($last);

               while( $current <= $last ) {

                   $dates[] = date($output_format, $current);
                   $current = strtotime($step, $current);
               }

               return $dates;
          }
        public function testSuccessBooking($invoice_id, $mihpayid, $payment_mode, $hash, $txnid,Request $request)
        {
           $this->successBooking($invoice_id, $mihpayid, $payment_mode, $hash, $txnid);
        }

        /**
         * objective:check the rateplan is exist or not
         * auther @ranjit
         * date-02/04/2019
         */
        public function checkroomrateplan($room_type_id,$rate_plan_id,$date_from,$date_to){
            $getstatus=MasterHotelRatePlan::select('*')
                    ->where(['rate_plan_id' => $rate_plan_id])
                    ->where(['room_type_id' => $room_type_id])
                    ->where('is_trash',0)
                    ->where('from_date' , '<=' , $date_from)
                    ->where('to_date' , '>=' , $date_from)
                    ->where('be_rate_status',0)
                    ->first();
            if($getstatus){
                return 1;
            }
            else{
                return 0;
            }
        }

        /*------------------------------------- Booking.com review api ------------------------------------*/
        public function getReviewFromBookingDotCom($property_id,Request $request)
        {
            $headers = array (
                //Regulates versioning of the XML interface for the API
                'Content-Type: application/json',
                'Authorization:Basic Qm9va2luZ2ppbmktY2hhbm5lbG1hbmFnZXI6d1N6bldPPzJ3eS9eLWovaGZVS15NQ3E/OkEqRUspQkJYU01LLS4qKQ=='
            );
            $url = "https://supply-xml.booking.com/review-api/properties/$property_id/reviews?limit=100";
            $ch = curl_init();
            curl_setopt( $ch, CURLOPT_URL, $url );
            curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
            $result = curl_exec($ch);
            curl_close($ch);
            return $result;
        }
        //Get Booking.com hotel code
        public function getBookingDotComPropertyID($hotel_id){
            $ota_hotel_code_obj=CmOtaDetails::select('ota_hotel_code')
            ->where('hotel_id',$hotel_id)
            ->where('ota_name','Booking.com')
            ->first();
            if($ota_hotel_code_obj){
                return $ota_hotel_code_obj['ota_hotel_code'];
            }else{
                return 0;
            }
        }

        public function getOtaWiseRates(Request $request){
            $data=$request->all();
            $date=$data['date'];
            $date=date('Y-m-d',strtotime($data['date']));
            $data['date']=$date;
            $to_date= date('Y-m-d',strtotime($date.' +1 day'));
            $hotel_id=$data['hotel_id'];
            if($hotel_id == 42){
                return response()->json(array("status"=>0,"message"=>"Ota rates not found"));
              }
            if($hotel_id == 2078){
                return response()->json(array("status"=>0,"message"=>"Ota rates not found"));
            }
            $api_key=$data['api_key'];
            $currency_name=$data['currency'];
            $inv_array=$this->getInvByHotel($api_key, $hotel_id , $date ,$to_date,$currency_name,$request);
            // $inv_array=$inv_array->getData();
            $min_room_price=0;
            $min_rack_price=0;
            $resp=$this->getAllPublicCupons($hotel_id, $date ,$to_date,$request);
            $resp=json_decode($resp->getContent(),true);
            // return $resp;

            // $couponPercent=0;
            // return response()->json($inv_array->original['data']);

            foreach ($inv_array->original['data'] as $inv){
            $couponPercent=0;

                // return $inv;
                if($inv->inv[0]['no_of_rooms']!=0 && $inv->inv[0]['block_status']==0 && $inv['min_room_price']!=0)
                {
                    if($min_room_price==0){
                            $min_room_price=$inv->min_room_price;
                            $min_rack_price=$inv->rack_price;
                    }
                    if($inv->min_room_price!=0 && $inv->min_room_price  < $min_room_price){
                            $min_room_price=$inv->min_room_price;
                    }
                    if($min_rack_price==0){
                            $min_rack_price=$inv->rack_price;
                    }
                    if($inv->rack_price!=0 && $inv->rack_price  < $min_rack_price){
                            $min_rack_price=$inv->rack_price;
                    }


                    if(isset($resp['data'])){
                        foreach($resp['data'] as $coupons){
                                if($coupons['room_type_id']===$inv->room_type_id || $coupons['room_type_id']===0){
                                    // if($coupons['discount_type']!=2){
                                        if($coupons['discount']>$couponPercent){
                                            $couponPercent=$coupons['discount'];
                                        }
                                    // }
                                }
                        }
                    }
                }
            }
        if($couponPercent!=0){
            $min_room_price=$min_room_price-($min_room_price*$couponPercent/100);
            $min_room_price= number_format((float)$min_room_price, 2, '.', '');

        }
        if($min_room_price==0){
            return response()->json(array("status"=>0,"message"=>"Room not available"));
        }
        $found=false;
        $conditions=array('hotel_id'=>$hotel_id,'is_trash'=>0, 'be_room_status'=>0);
        $room_types=MasterRoomType::select('room_type','room_type_id','max_people','max_child','max_room_capacity','image','rack_price','extra_person','extra_child','bed_type','room_amenities')->where($conditions)->orderBy('rack_price', 'ASC')->get();
        foreach($room_types as $room){
            $room_type_n_rate_plans=MasterHotelRatePlan::join('rate_plan_table as a', function($join) {
                $join->on('room_rate_plan.rate_plan_id', '=', 'a.rate_plan_id');
                    })
                ->select('a.rate_plan_id','plan_type','plan_name')
                ->where('room_rate_plan.hotel_id',$hotel_id)
                ->where('room_rate_plan.is_trash',0)
                ->where('room_rate_plan.room_type_id',$room['room_type_id'])
                ->orderBy('room_rate_plan.created_at','ASC')
                ->distinct()
                ->get();
            foreach($room_type_n_rate_plans as $room_type_n_rate_plan){
                $room_type_id=$room['room_type_id'];
                $rate_plan_id=$room_type_n_rate_plan['rate_plan_id'];
                $date=date('Y-m-d',strtotime($data['date']));
                $request->request->add(['room_type_id' => $room['room_type_id']]);
                $request->request->add(['rate_plan_id' => $room_type_n_rate_plan['rate_plan_id']]);
                $request->request->add(['date' => $date]);
                $check_room = Inventory::select('*')
                              ->where('hotel_id',$hotel_id)
                              ->where('room_type_id',$room['room_type_id'])
                              ->orderBy('inventory_id','DESC')
                              ->first();
                if(!$check_room){
                    continue;
                }
                if($check_room->block_status == 0){
                  $resp=$this->otaInvRateService->rateData($request);
                }
                else{
                  continue;
                }
                if($resp){
                $resp=json_decode($resp->getContent(),true);
                    if(isset($resp['data'])){
                        $otaRates=$resp['data'];
                        foreach($otaRates as $key=>$otaRate){
                            if($otaRate[0]!='same as panel' &&  $otaRate[0]!= "-"){
                                $found=true;
                            }

                        }
                        if($found){
                            $data=$this->invService->getRatesByRoomnRatePlan($room_type_id,$rate_plan_id,$date,$to_date);
                            foreach($otaRates as $key=>$otaRate){
                                    if($otaRate[0]=='same as panel' || $otaRate[0]== "-"){
                                            if(isset($data[0]['bar_price'])){
                                              $otaRates[$key][0]=$data[0]['bar_price'];
                                            }
                                    }
                            }
                            $otaRates["BE"]=array('min_rack_price'=>$min_rack_price,'min_room_price'=>$min_room_price);
                            $resp['data']=$otaRates;
                            return response()->json($resp);
                    }
                    }else{
                            return response()->json(array("status"=>0,"message"=>"Ota rates not found"));
                        }
                }else{
                        return response()->json(array("status"=>0,"message"=>"Ota rates not found"));
                    }
                }
            }
            if($found==false){
                $room_types=MasterRoomType::select('room_type','room_type_id','max_people','max_child','max_room_capacity','image','rack_price','extra_person','extra_child','bed_type','room_amenities')->where($conditions)->orderBy('rack_price', 'ASC')->get();
                $room_type_id_be=$room_types[0]['room_type_id'];
                $room_type_n_rate_plans=MasterHotelRatePlan::join('rate_plan_table as a', function($join) {
                    $join->on('room_rate_plan.rate_plan_id', '=', 'a.rate_plan_id');
                        })
                    ->select('a.rate_plan_id','plan_type','plan_name')
                    ->where('room_rate_plan.hotel_id',$hotel_id)
                    ->where('room_rate_plan.is_trash',0)
                    ->where('room_rate_plan.room_type_id',$room_type_id_be)
                    ->orderBy('room_rate_plan.created_at','ASC')
                    ->distinct()
                    ->get();
                 $rate_plan_id=$room_type_n_rate_plans[0]['rate_plan_id'];
                 $request->request->add(['room_type_id' =>  $room_type_id_be]);
                 $request->request->add(['rate_plan_id' => $rate_plan_id]);
                 $resp_inv=$this->otaInvRateService->rateData($request);
                if($resp_inv){
                    $resp_inv=json_decode($resp_inv->getContent(),true);
                    if(isset($resp_inv['data'])){
                        $otaRates=$resp_inv['data'];
                        foreach($otaRates as $key=>$otaRate){
                            if($otaRate[0]==='same as panel' || '-'){
                                $data=$this->invService->getRatesByRoomnRatePlan($room_type_id_be,$rate_plan_id,$date,$to_date);
                                if(isset($data[0]['bar_price'])){
                                    $otaRates[$key][0]=$data[0]['bar_price'];
                                }
                            }
                        }
                        $otaRates["BE"]=array('min_rack_price'=>$min_rack_price,'min_room_price'=>$min_room_price);
                        $resp_inv['data']=$otaRates;
                        return response()->json($resp_inv);
                    }else{
                        return response()->json(array("status"=>0,"message"=>"Ota rates not found"));
                    }
                }else{
                    return response()->json(array("status"=>0,"message"=>"Ota rates not found"));
                }
            }
        }
        /*--------------------------------------- INVOICE MAIL (END) ------------------------------------*/
/*-------------------------------------INVOICE DATA (GOOGLE)------------------------------------*/
public function fetchInvoiceData($invoice_id,Request $request){
    $hotel_id;
    $checkin_date;
    $checkout_date;
    $query      = "Select DISTINCT(a.invoice_id),a.ref_no,a.hotel_id,a.total_amount,a.paid_amount,a.ref_from,b.user_id, b.room_type_id, a.booking_date, a.hotel_name, a.hotel_id, a.room_type, a.check_in_out, a.total_amount, a.paid_amount, c.hotel_address, c.mobile, c.email_id, c.terms_and_cond from invoice_table a, hotel_booking b, kernel.hotels_table c where a.invoice_id=b.invoice_id AND a.hotel_id=c.hotel_id AND a.invoice_id=$invoice_id";
    $result    = DB::select($query);
    foreach($result as $data){
        $hotel_id = $data->hotel_id;
        $checkin_date = substr($data->check_in_out,1,10);
        $checkout_date = str_replace("]","",substr($data->check_in_out,12,21));
        $baseCurrency=$this->getBaseCurrency($hotel_id)->currency;
        $date1=date_create($checkin_date);
        $date2=date_create($checkout_date);
        $diff=date_diff($date1,$date2);
        $diff=$diff->format("%a");
        $data->checkin_date = $checkin_date;
        $data->checkout_date= $checkout_date;
        $data->length_of_stay=$diff;
        $data->currency_code=$baseCurrency;
    }
    if($result)
    {
    $res=array('status'=>1,"message"=>"Invoice data feteched sucesssfully!",'data'=>$result);
    return response()->json($res);
    }
    else
    {
    $res=array('status'=>0,"message"=>"Invoice details not found");
    return response()->json($res);
    }

}
public function testIdsFlow(Request $request){
    $cart = $request->input('cart');
    $resp = $this->handleIds($cart,'2021-01-26','2021-02-05','2021-01-25',2142,2142);
}
public function handleIds($cart,$from_date,$to_date,$booking_date,$hotel_id,$user_id){
    $booking_status='Commit';
    $ids_status=$this->idsService->getIdsStatus($hotel_id);
    if($ids_status){
        $ids_data=$this->prepare_ids_data($cart,$from_date,$to_date,$booking_date,$hotel_id);
        $customer_data=$this->getUserDetails($user_id)->toArray();
        $type="Bookingjini";
        $last_ids_id=$this->idsService->idsBookings($hotel_id,$type,$ids_data,$customer_data,$booking_status);
        if($last_ids_id){
            return $last_ids_id;
        }
        else{
            return 0;
        }
    }
}
public function prepare_ids_data($cart,$from_date,$to_date,$booking_date,$hotel_id){
    $booking_data=array();
    $booking_data['booking_id']='#####';//Intially Booking id not known ,After successful boooking Only booking id Set to this
    $booking_data['room_stay']=array();
    $date1=date_create($from_date);
    $date2=date_create($to_date);
    $diff=date_diff($date1,$date2);
    $diff=$diff->format("%a");
    $no_of_rooms=0;
    foreach($cart as $cartItem){
        $rates_arr=array();
        $gst_price=0;
        $total_adult=0;
        $total_child=0;
        $no_of_rooms=sizeof($cartItem['rooms']);

        foreach($cartItem['rooms'] as $rooms){
            $ind_total_price=0;
            $frm_date=$from_date;
            $total_adult=$rooms['selected_adult'];
            $ind_total_price=$rooms['bar_price']+$rooms['extra_adult_price']+$rooms['extra_child_price'];
            $rates_arr=array();
            for($j=1;$j<=$diff;$j++){
                $amount=0;
                $gst_price=0;
                $d1=$frm_date;
                $d2=date('Y-m-d', strtotime($d1 . ' +1 day'));
                $amount=(($ind_total_price/$diff));
                $gst_price=$this->getGstPrice(1,1,$cartItem['room_type_id'],$amount);//TO get the GSt price
                if(strpos('.', $amount) == false){
                    $amount=$amount.'.00';
                }
                array_push($rates_arr,array("from_date"=>$d1,"to_date"=>$d2,'amount'=>(int)$amount,'tax_amount'=>$gst_price));
                $frm_date=date('Y-m-d', strtotime($d1 . ' +1 day'));
            }
            $arr=array('room_type_id'=>$cartItem['room_type_id'],'rate_plan_id'=>$rooms['rate_plan_id'],'adults'=>$total_adult,'from_date'=>$from_date,'to_date'=>$to_date,'rates'=>$rates_arr);
            array_push($booking_data['room_stay'],$arr);
        }
    }
    return $booking_data;
}
public function sendOtp(Request $request){
    $data=$request->all();
    $uniq = mt_rand(1000, 9999);
    $to = $data['mobile'];
    // $messageToSend = 'Dear customer,'.$uniq.' is your verification code,please enter this to proceed with login otherwise it will expire in 1 minute.Regards, BKJINI';

    $messageToSend=" Dear customer,'.$uniq.' is your verification code,please enter this to proceed with login otherwise it will expire in 1 minute.Regards,BKJINI";

    $date = date('Y-m-d');

    $smsURL="https://apps.sandeshlive.com/API/WebSMS/Http/v1.0a/index.php?userid=1135&password=F4lKwI80ROA51fyq&sender=BKJINI&to=".$to."&message=".$messageToSend."&reqid=1&format={json|text}&route_id=3";


    $ch = curl_init(); // initialize CURL
    curl_setopt($ch, CURLOPT_POST, false); // Set CURL Post Data
    curl_setopt($ch, CURLOPT_URL, $smsURL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $output = curl_exec($ch);
    curl_close($ch);
    if($output){
        $res=array('status'=>1,'data'=>base64_encode(strval($uniq)),'message'=>$uniq,'otp_value'=>$uniq);
        return response()->json($res);
    }
    else{
        $res=array('status'=>0,'message'=>'Otp not send');
        return response()->json($res);
    }
}
public function userInfo($user_id){
  $UserInformation = User::select('first_name', 'last_name', 'mobile', 'email_id','address','zip_code','country','state','city','GSTIN','company_name')
                           ->where('user_id', $user_id)
                           ->first();
  return $UserInformation;
}
public function noOfBookings($invoice_id)
{
    $booked_room_details = HotelBooking::select('room_type_id','rooms','check_in','check_out')
                            ->where('invoice_id', $invoice_id)
                            ->get();
    return $booked_room_details;
}
public function pushBookingToGems($invoice_id,$gems){
  $all_bookings = array();
  // $getBookingDetails =DB::select(DB::raw("SELECT DISTINCT (b.user_id), a.room_type, a.ref_no, a.extra_details, a.booking_date, a.invoice_id, a.total_amount, a.paid_amount, a.hotel_name, a.hotel_id FROM invoice_table a, hotel_booking b where a.invoice_id=$invoice_id AND a.booking_status=1 AND a.ref_no!='offline' ORDER BY a.invoice_id asc"));
  $getBookingDetails = Invoice::join('hotel_booking','invoice_table.invoice_id','=','hotel_booking.invoice_id')
  ->join('kernel.hotels_table','invoice_table.hotel_id','=','hotels_table.hotel_id')->join('kernel.company_table','hotels_table.company_id','=','company_table.company_id')
  ->select('hotel_booking.user_id', 'invoice_table.room_type', 'invoice_table.ref_no', 'invoice_table.extra_details', 'invoice_table.booking_date', 'invoice_table.invoice_id', 'invoice_table.total_amount', 'invoice_table.paid_amount', 'invoice_table.hotel_name', 'invoice_table.hotel_id','company_table.currency','invoice_table.tax_amount','invoice_table.discount_amount')
  ->distinct('hotel_booking.user_id')
  ->where('invoice_table.invoice_id',$invoice_id)
  ->where('invoice_table.ref_no','!=','offline')
  ->orderBy('invoice_table.invoice_id','ASC')
  ->get();

      foreach($getBookingDetails as $key => $bk_details){
        $rooms          =array();
        $user_id        =$bk_details->user_id;
        $ref_no         =$bk_details->ref_no;

        $User_Details   = $this->userInfo($user_id);
        $Booked_Rooms   = $this->noOfBookings($invoice_id);

        $date1=date_create($Booked_Rooms[0]['check_out']);
        $date2=date_create($Booked_Rooms[0]['check_in']);
        $diff=date_diff($date1,$date2);
        $no_of_nights=$diff->format("%a");

        $booking_date   =$bk_details->booking_date;
        $booking_id     =date("dmy", strtotime($booking_date)).str_pad($invoice_id, 4, '0', STR_PAD_LEFT);

        if($ref_no=='offline'){
          $mode_of_payment='Offline';
        }
        else{
          $mode_of_payment='Online';
        }
        $room_type_plan=explode(",", $bk_details->room_type);
        $plan= array();
        for($i=0; $i<sizeof($room_type_plan); $i++){
            $plan[]=substr($room_type_plan[$i], -5, -3);
        }
        $extra=json_decode($bk_details->extra_details);

        $k=0;
        $getRoomDetails = BeBookingDetailsTable:: select('*')->where('ref_no',$bk_details->ref_no)->get();

        foreach ($getRoomDetails as $getRoom) {
            $rooms[] = array(
            "room_type_id"          => $getRoom->room_type_id,
            "room_type_name"        => $getRoom->room_type,
            "no_of_rooms"           => $getRoom->rooms,
            "room_rate"             => $getRoom->room_rate,
            "tax_amount"            => $getRoom->tax_amount,
            "plan"                  => $getRoom->rate_plan_name,
            "adult"                 => $getRoom->adult,
            "child"                 => $getRoom->child,
            "extra_adult_rate"      => $getRoom->extra_adult,
            "extra_child_rate"      => $getRoom->extra_child
            );
        }
        $user_info = array(
            "user_name"             => $User_Details['first_name'].' '.$User_Details['last_name'],
            "mobile"                => $User_Details['mobile'],
            "email"                 => $User_Details['email_id'],
            "address"               => $User_Details['address'],
            "zip_code"              => $User_Details['zip_code'],
            "country"               => $User_Details['country'],
            "state"                 => $User_Details['state'],
            "city"                  => $User_Details['city'],
            "GSTIN"                 => $User_Details['GSTIN'],
            "company_name"          => $User_Details['company_name']
            );
            if($gems == 'true'){
              $Bookings = array(
                  "date_of_booking"       => $booking_date,
                  "hotel_id"              => $bk_details->hotel_id,
                  "hotel_name"            => $bk_details->hotel_name,
                  "check_in"              => $Booked_Rooms[0]['check_in'],
                  "check_out"             => $Booked_Rooms[0]['check_out'],
                  "booking_id"            => $booking_id,
                  "mode_of_payment"       => $mode_of_payment,
                  "grand_total"           => $bk_details->total_amount,
                  "currency"              => $bk_details->currency,
                  "paid_amount"           => 0,
                  "tax_amount"            => $bk_details->tax_amount,
                  "discount_amount"       => $bk_details->discount_amount,
                  "channel"               => "Bookingjini",
                  // "channel_logo"          => "https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/uploads/1016869990agoda.png",
                  "status"                => "confirmed"
                  );
            }
            else{
              $Bookings = array(
                  "date_of_booking"       => $booking_date,
                  "hotel_id"              => $bk_details->hotel_id,
                  "hotel_name"            => $bk_details->hotel_name,
                  "check_in"              => $Booked_Rooms[0]['check_in'],
                  "check_out"             => $Booked_Rooms[0]['check_out'],
                  "booking_id"            => $booking_id,
                  "mode_of_payment"       => $mode_of_payment,
                  "grand_total"           => $bk_details->total_amount,
                  "paid_amount"           => $bk_details->paid_amount,
                  "tax_amount"            => $bk_details->tax_amount,
                  "discount_amount"       => $bk_details->discount_amount,
                  "channel"               => "Bookingjini",
                  // "channel_logo"          => "https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/uploads/1016869990agoda.png",
                  "status"                => "confirmed"
                  );
            }



          $all_bookings[] = array(
          'UserDetails'               => $user_info,
          'BookingsDetails'           => $Bookings,
          'RoomDetails'               => $rooms
          );
          $k++;
      }
      if(sizeof($all_bookings) > 0){
          $all_bookings = http_build_query($all_bookings);
          $url = "https://gems.bookingjini.com/api/insertTravellerBookings";
          $ch = curl_init();
          curl_setopt( $ch, CURLOPT_URL, $url );
          curl_setopt( $ch, CURLOPT_POST, true );
          curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
          curl_setopt( $ch, CURLOPT_POSTFIELDS, $all_bookings);
          $rlt = curl_exec($ch);
          curl_close($ch);
          return $rlt;
      }
  }
  public function gstDiscountPrice($cart){
    $invInfo = array();
    $total_discount_price = 0;
    $total_tax = 0;
      foreach($cart as $cartItem){
        $total_discount_price += $cartItem['discounted_price'];
        $total_discount_price = number_format((float)$total_discount_price, 2, '.', '');
        if($cartItem['tax'][0]['gst_price']!=0){
            $total_tax  += $cartItem['tax'][0]['gst_price'];
        }
        else
        {
            foreach($cartItem['tax'][0]['other_tax'] as $key => $other_tax){
                $total_tax+=$other_tax['tax_price'];
                $other_tax_arr[$key]['tax_name']=$other_tax['tax_name'];
                if(!isset($other_tax_arr[$key]['tax_price'])){
                    $other_tax_arr[$key]['tax_price']=0;
                    $other_tax_arr[$key]['tax_price']+=$other_tax['tax_price'];
                }else{
                    $other_tax_arr[$key]['tax_price']+=$other_tax['tax_price'];
                }
            }
        }
        $total_tax = number_format((float)$total_tax, 2, '.', '');
      }
      $invInfo[0] = $total_tax;
      $invInfo[1] = $total_discount_price;

      return $invInfo;
  }
  public function getGstPricePerRoom($room_rate,$discount){
    $gstPrice = 0;
    $price = $room_rate - $discount;
      if($room_rate >=0 && $room_rate<1000){
          $gstPrice = 0;
      }
      else if($room_rate >=1000 && $room_rate<7500){
          $gstPrice = ($price * 12)/100;
      }
      else if($room_rate >=7500){
          $gstPrice = ($price * 18)/100;
      }
      return $gstPrice;
    }


    //API to fetch cancellation policy master details

    public function fetchCancellationPolicyMasterData(Request $request){

        $cancellation_policy_master_details=CancellationPolicyMaster::select('id','days_before_checkin')->get();
        
        $res = array('status'=>1,'message'=>'Retrieved successfully','cancellation_policy_master_details'=>$cancellation_policy_master_details);
        return response()->json($res);
    }

    //API to fetch Cancellation Policy

    public function fetchCancellationPolicy($hotel_id,Request $request){

        $cancellation_policy=CancellationPolicy::select('id','hotel_id','policy_data')->where('hotel_id',$hotel_id)->first();
        $policy_data_array=array();
        if($cancellation_policy) {
           $cancellation_policy->policy_data=json_decode($cancellation_policy->policy_data);

            foreach($cancellation_policy->policy_data  as $policy_data) {
                $create_array=explode(':', $policy_data);
                $create_object = array('days_before_checkin'=>$create_array[0],'refund_percentage'=>$create_array[1]);
                $policy_data_array[]=$create_object;
            }
            $cancellation_policy->policy_data_array=$policy_data_array;
        }


        $res = array('status'=>1,'message'=>'Retrieved successfully','cancellation_policy'=>$cancellation_policy);
        return response()->json($res);
    }


        //API to fetch Cancellation Policy In FrontView

        public function fetchCancellationPolicyFrontView($hotel_id,Request $request){

            $cancellation_policy=CancellationPolicy::select('id','hotel_id','policy_data')->where('hotel_id',$hotel_id)->first();
            $policy_data_array=array();
            if($cancellation_policy) {
               $cancellation_policy->policy_data=json_decode($cancellation_policy->policy_data);
    
                foreach($cancellation_policy->policy_data  as $policy_data) {
                    $create_array=explode(':', $policy_data);
                    $create_object = array('days'=>$create_array[0],'refund_percentage'=>$create_array[1]);
                    $policy_data_array[]=$create_object;
                }
                $cancellation_policy->policy_data_array=$policy_data_array;
            }
    
            $info=HotelInformation::select('child_policy','cancel_policy','terms_and_cond','hotel_policy','hotel_address','mobile','latitude','longitude')->where('hotel_id',$hotel_id)->first();

            $res = array('status'=>1,'message'=>'Retrieved successfully','cancellation_policy'=>$cancellation_policy,'policy_info'=>$info);
            return response()->json($res);
        }



    //API to insert/update Cancellation Policy
    public function updateCancellationPolicy(Request $request){
        $data=$request->all();
        $data['policy_data']= json_encode($request->input('policy_data'));
        if($data['id']=='undefined'){
        $insertPolicyData=CancellationPolicy::insert(['hotel_id'=>$data['hotel_id'],'policy_data'=>$data['policy_data'],'policy_for'=>$data['policy_for']]);
            if($insertPolicyData){
                $res = array('status'=>1,'message'=>'Inserted Successfully');
                return response()->json($res);
            }
            else{
                $res = array('status'=>0,'message'=>'insertion Failed');
                return response()->json($res);
            }
        }
        else{
        $updatePolicyData=CancellationPolicy::where('id', $data['id'])->where('hotel_id', $data['hotel_id'])->update(['policy_data'=>$data['policy_data'],'policy_for'=>$data['policy_for']]);
            if($updatePolicyData){
                $res = array('status'=>1,'message'=>'Policy updated successfully');
                return response()->json($res);
            }
            else{
                $res = array('status'=>0,'message'=>'Policy updation failure');
                return response()->json($res);
            }
        }
    }



    // BE Cancellation Refund Amount //
    /**
     * @author Hafiz
     * This function is used to get the refund amount by cancel booking depend upon the days before checkin date */    
    public function fetchCancelRefundAmount($invoice_id){
        $get_today = date("Y-m-d"); 
        $ref_per = 0;
        $refund_amount = 0;

        $be_booking_data = Invoice::join('hotel_booking','invoice_table.invoice_id','=','hotel_booking.invoice_id')->select('hotel_booking.check_in','invoice_table.total_amount','invoice_table.hotel_id')->where('hotel_booking.invoice_id',$invoice_id)->first();
                
        if($be_booking_data){
            $check_in = $be_booking_data->check_in;
            $total_amount = $be_booking_data->total_amount;
            $hotel_id = $be_booking_data->hotel_id;
        }
        if($check_in >= $get_today){
            $days = abs(strtotime($check_in) - strtotime($get_today)) / 86400;            
            if($days >= 0){
                $get_cancellation_policy = CancellationPolicy::where('hotel_id',$hotel_id)->first();                
                if($get_cancellation_policy){
                    $closest = null;
                    $refund_days = $get_cancellation_policy->policy_data;
                    $refund_days=json_decode($refund_days);
                    
                    for($i=0;$i<sizeof($refund_days);$i++){
                        $ref_data = explode(':',$refund_days[$i]);
                        $ref_per = $ref_data[1];
                        $daterange = explode('-',$ref_data[0]);
                        if($days  >= $daterange[0] && $days  <= $daterange[1]){
                            $refund_amount = $total_amount * ( $ref_per / 100);
                        }
                    }
                }else{
                    $refund_amount = 0;
                }
            }else{
                $refund_amount = 0;
            }
        }
        return $refund_amount;
    }
public function handleKtdc($cart,$from_date,$to_date,$booking_date,$hotel_id,$user_id,$booking_id){
    $booking_status='Commit';
    $ids_status=$this->ktdcService->getKtdcStatus($hotel_id);
    if($ids_status){
        $ktdc_data=$this->prepare_ktdc_data($cart,$from_date,$to_date,$booking_date,$hotel_id);
        $customer_data=$this->getUserDetails($user_id)->toArray();
        $type="Bookingjini";
        $last_ktdc_id=$this->ktdcService->ktdcBookings($hotel_id,$type,$ktdc_data,$customer_data,$booking_status,$booking_id,$from_date,$to_date);
        if($last_ktdc_id){
            return $last_ktdc_id;
        }
        else{
            return 0;
        }
    }
}
public function prepare_ktdc_data($cart,$from_date,$to_date,$booking_date,$hotel_id){
    $booking_data=array();
    $booking_data['booking_id']='#####';//Intially Booking id not known ,After successful boooking Only booking id Set to this
    $booking_data['room_stay']=array();
    $date1=date_create($from_date);
    $date2=date_create($to_date);
    $diff=date_diff($date1,$date2);
    $diff=$diff->format("%a");
    $no_of_rooms=0;
    foreach($cart as $cartItem){
        $rates_arr=array();
        $gst_price=0;
        $total_adult=0;
        $total_child=0;
        $no_of_rooms=sizeof($cartItem['rooms']);

        foreach($cartItem['rooms'] as $rooms){
            $ind_total_price=0;
            $frm_date=$from_date;
            $total_adult=$rooms['selected_adult'];
            $ind_total_price=$rooms['bar_price']+$rooms['extra_adult_price']+$rooms['extra_child_price'];
            $rates_arr=array();
            for($j=1;$j<=$diff;$j++){
                $amount=0;
                $gst_price=0;
                $d1=$frm_date;
                $d2=date('Y-m-d', strtotime($d1 . ' +1 day'));
                $amount=(($ind_total_price/$diff));
                $gst_price=$this->getGstPrice(1,1,$cartItem['room_type_id'],$amount);//TO get the GSt price
                if(strpos('.', $amount) == false){
                    $amount=$amount.'.00';
                }
                array_push($rates_arr,array("from_date"=>$d1,"to_date"=>$d2,'amount'=>(int)$amount,'tax_amount'=>$gst_price));
                $frm_date=date('Y-m-d', strtotime($d1 . ' +1 day'));
            }
            $arr=array('room_type_id'=>$cartItem['room_type_id'],'rate_plan_id'=>$rooms['rate_plan_id'],'adults'=>$total_adult,'from_date'=>$from_date,'to_date'=>$to_date,'rates'=>$rates_arr,'room'=>$no_of_rooms);
            array_push($booking_data['room_stay'],$arr);
            }
        }
    return $booking_data;
}



    public function uploadNotificationSliderImage(int $hotel_id,Request $request){
        $dz_failure_message="Image uploading failed";
        $dz_data=array();
        $dz_data['hotel_id'] = $hotel_id;


        $dz_image_name=array();
        if ($request->hasFile('uploadFile')) {
        // Make Validation
                $file = array('uploadFile' => $request->file('uploadFile'));
                //Initialize Check Trigger
                $dz_cot = 0;
                //Check number of images and upload it
                foreach($request->file('uploadFile') as $media)
                {
                    //------------Amazon S3------------------//
                    $files = $request->file('uploadFile');
                    $dz_name = time() . $media->getClientOriginalName();
                    $dz_name=str_replace(' ','',$dz_name);

                    $dz_filePath = 'uploads/' . $dz_name;

                    Storage::disk('s3')->put($dz_filePath, file_get_contents($media),'public');



                    $dzfileUpload = new ImageTable;
                    $dzfileUpload->image_name = $dz_filePath;
                    $dzfileUpload->hotel_id = $hotel_id;
                    $dzfileUpload->save();

                    $dz_img_id = ImageTable::select('image_id','image_name')->where('hotel_id',$hotel_id)->where('image_name',$dz_filePath )->first();
                    $dz_im_id = $dz_img_id->image_id;
                    if($dz_im_id){
                        array_push($dz_image_name,$dz_img_id->image_name);
                        $dz_cot = $dz_cot+1;
                    }
                            else
                            {
                                $res=array('status'=>-1,"message"=>$dz_failure_message);
                                $res['errors'][] = "Internal server error";
                                return response()->json($res);
                            }
                }
        // Check Trigger and return Message
        if($dz_cot == count($request->file('uploadFile')))
            {
                $res=array('status'=>1,"message"=>"Images uploaded successfully","image_ids"=>$dz_im_id,"image_name"=>$dz_image_name);
                return response()->json($res);
            }
            else
            {
                $res=array('status'=>-1,"message"=>$dz_failure_message);
                $res['errors'][] = "Internal server error";
                return response()->json($res);
            }

        }
        else{
            return response()->json(array('status'=>0,'message'=>$dz_failure_message,'errors'=>'No File Choosen.'));
        }
    }

    public function updateNotificationSliderImage(Request $request){
        $data = $request -> all();
        $cond = array('hotel_id'=>$data['hotel_id']);

        $check = BENotificationSlider::where($cond)->first();
        $insertData = new BENotificationSlider();                
        $data['images'] = implode(',',$data['images']);
        if(!$check){
            $insData = $insertData->fill($data)->save();
            if($insData){
                return response()->json(array('status'=>1,'message'=>'insert successfully'));
            }
            else{
                return response()->json(array('status'=>0,'message'=>'insertion fails'));
            }
        }
        else{
            $upData = BENotificationSlider::where($cond)->update(['images'=> $data['images']]);                
            if($upData){
                return response()->json(array('status'=>1,'message'=>'update successfully'));
            }
            else{
                return response()->json(array('status'=>0,'message'=>'update fails'));
            }
        }
    }

    public function fetchNotificationSliderImage($hotel_id,Request $request){
        
        $notification_slider_details=BENotificationSlider::select('id','hotel_id','images')->where('hotel_id',$hotel_id)->first();

        if($notification_slider_details) {
            $slider_image_array=array();
            $slider_image_name=explode(',',$notification_slider_details->images);
                if($notification_slider_details->images && sizeof($slider_image_name)>0){
                    $notification_slider_details->image_id=$slider_image_name;
                    foreach($slider_image_name  as $id) {
                        $retrive_image_name = ImageTable::select('image_id','image_name')->where('image_id',$id)->first();
                            $slider_image_array[]=$retrive_image_name->image_name;
                    }
                }
                else{
                    $notification_slider_details->image_id=[];
                }
                $notification_slider_details->image_name =  $slider_image_array;
            }

        $res = array('status'=>1,'message'=>'Retrieved',"notification_slider_details"=>$notification_slider_details);
        return response()->json($res);
        
    }

    public function deleteNotificationSliderImage(Request $request){
        $slider_image_data=$request->all();
  
        $delete_img_id = ImageTable::select('image_id')->where('hotel_id',$hotel_id)->where('image_name',$slider_image_data["image_name"])->first();
        
        $deleteImg= ImageTable::where('image_name',$slider_image_data["image_name"])->where('hotel_id',$slider_image_data['hotel_id'])->delete();
  
        $slider_image_data["image_name"] = str_replace('"',"'",$slider_image_data["image_name"]);
        $sliderimagedata = Storage::disk('s3')->delete($slider_image_data["image_name"]); // delete image from amazon s3
        if($sliderimagedata){
           $res=array('status'=>1 ,'message'=>'Image deleted successfully',"image_id"=>$delete_img_id->image_id);
           return response()->json($res);
        }
        else{
           $res=array('status'=>0,'message'=>'Image deletion failed');
           return response()->json($res);
        }
  
    }

    public function fetchBENotifications($hotel_id,Request $request){

        $be_notifications=Notifications::select('id','hotel_id','content_html')->where('hotel_id',$hotel_id)->first();
        
        $res = array('status'=>1,'message'=>'Retrieved successfully','be_notifications'=>$be_notifications);
        return response()->json($res);
    }

    public function updateNotificationPopup(Request $request){
        $data = $request -> all();
        $cond = array('hotel_id'=>$data['hotel_id']);

        $check = Notifications::where($cond)->first();
        $insertPopupData = new Notifications();                
        if(!$check){
            $insData = $insertPopupData->fill($data)->save();
            if($insData){
                return response()->json(array('status'=>1,'message'=>'insert successfully'));
            }
            else{
                return response()->json(array('status'=>0,'message'=>'insertion fails'));
            }
        }
        else{
            $upPopupData = Notifications::where($cond)->update(['content_html'=> $data['content_html']]);                
            if($upPopupData){
                return response()->json(array('status'=>1,'message'=>'update successfully'));
            }
            else{
                return response()->json(array('status'=>0,'message'=>'update fails'));
            }
        }
    }

    

}
