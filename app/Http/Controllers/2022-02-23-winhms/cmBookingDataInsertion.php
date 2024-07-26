<?php

namespace App\Http\Controllers\otacontrollers;
use Illuminate\Http\Request;
use Validator;
use App\CmBookingDetailsTable;
use App\CmOtaBooking;
use App\CmOtaRoomTypeSynchronize;
use App\CmOtaRatePlanSynchronize;
use App\RoomTypeTable;
use App\CmOtaRoomTypeFetch;
use App\CmOtaRateTypeFetch;
use App\PmsAccount;
use DB;
use App\Http\Controllers\Controller;
class CmBookingDataInsertionController extends Controller
{
    public function cmBookingDataInsertion($booking_hotel_id,$bucket_ota_booking_tabel_id){

        $no_of_adult = '';
        $no_of_child = '';
        $price = array();

        $checkHotel = PmsAccount::where('name','GEMS')->first();
        $hotel_ids = explode(',',$checkHotel->hotels);

        if(!in_array($booking_hotel_id,$hotel_ids)){
            return true;
        }
        else{
            $getCmBookingData = CmOtaBooking::select('*')->where('hotel_id',$booking_hotel_id)->where('id',$bucket_ota_booking_tabel_id)->first();
            if($getCmBookingData){
                $dlt_status = CmBookingDetailsTable::where('ref_no',$getCmBookingData->unique_id)->delete();
                $room_type_id = explode(",",$getCmBookingData->room_type);
                $rate_plan_id = explode(",",$getCmBookingData->rate_code);
                $room_price = explode(",",$getCmBookingData->room_price);
                $amount = $getCmBookingData->amount;
                $rooms = explode(",",$getCmBookingData->rooms_qty);

                if($getCmBookingData->no_of_adult != 0){
                    $no_of_adult_arr = explode(",",$getCmBookingData->no_of_adult);
                }
                else{
                    $no_of_adult = '0';
                }
                if($getCmBookingData->no_of_child != 0){
                    $no_of_child_arr = explode(",",$getCmBookingData->no_of_child);
                }
                else{
                    $no_of_child = '0';
                }
                $check_room_id = 0;
                $k = 0;
                for($i=0;$i<sizeof($room_type_id);$i++)
                {
                    if($check_room_id == 0){
                        $check_room_id = $room_type_id[$i];
                    }
                    else{
                        if($check_room_id == $room_type_id[$i]){
                            $check_room_id = $room_type_id[$i];
                            continue;
                        }
                        else{
                            $check_room_id = $room_type_id[$i];
                        }
                    }
                    
                    //get room_type_id //
                    $get_room_type = CmOtaRoomTypeSynchronize::join('kernel.room_type_table','room_type_table.room_type_id','=','cm_ota_room_type_synchronize.room_type_id')->select('room_type_table.room_type_id','ota_type_id','ota_room_type',
                    'cm_ota_room_type_synchronize.ota_room_type_name','room_type')->where('cm_ota_room_type_synchronize.ota_room_type',$room_type_id[$i])->where('ota_type_id',$getCmBookingData->ota_id)->first();
                    //get rate_plan_id
                    $get_rate_plan = CmOtaRatePlanSynchronize::join('kernel.rate_plan_table','cm_ota_rate_plan_synchronize.hotel_rate_plan_id','=','rate_plan_table.rate_plan_id')->join('kernel.room_type_table','cm_ota_rate_plan_synchronize.hotel_room_type_id','=','room_type_table.room_type_id')->where('cm_ota_rate_plan_synchronize.ota_rate_plan_id',$rate_plan_id[$i])->where('cm_ota_rate_plan_synchronize.ota_type_id',$getCmBookingData->ota_id)->where('cm_ota_rate_plan_synchronize.ota_room_type_id',$room_type_id[$i])->first();
                    if(!isset($rooms[$k])){
                        continue;
                    }
                    for($j=0;$j<$rooms[$k];$j++)
                    {
                        $room_amount = isset($room_price[$i])?trim($room_price[$i]):0;
                        $hotel_rate_plan_id = isset($get_rate_plan->hotel_rate_plan_id)?$get_rate_plan->hotel_rate_plan_id:0;
                        $plan_name = isset($get_rate_plan->plan_name)?$get_rate_plan->plan_name:'NA';
                        $CmBookingDetails  = new CmBookingDetailsTable;
                        $gems_room_type = isset($get_room_type->room_type)?$get_room_type->room_type:'NA';
                        $gems_room_type_id = isset($get_room_type->room_type_id)?$get_room_type->room_type_id:0;
                        $CmBookingDetails->hotel_id              = trim($booking_hotel_id);
                        $CmBookingDetails->ref_no                = trim($getCmBookingData->unique_id);
                        $CmBookingDetails->room_type             = trim($gems_room_type);
                        $CmBookingDetails->rooms                 = 1;
                        $CmBookingDetails->room_rate             = $room_amount;  //--room price--//
                        $CmBookingDetails->extra_adult           = 0; //--no data--//
                        $CmBookingDetails->extra_child           = 0; //--no data--//
                        $CmBookingDetails->room_type_id          = trim($gems_room_type_id);
                        $CmBookingDetails->rate_plan_id          = trim($hotel_rate_plan_id);
                        $CmBookingDetails->rate_plan_name        = trim($plan_name);
                        if($no_of_adult == '0'){
                            $CmBookingDetails->adult             = 0;
                        }
                        else{
                            if(sizeof($no_of_adult_arr)>1){
                                $CmBookingDetails->adult             = $no_of_adult_arr[$i];
                            }
                            else{
                                $CmBookingDetails->adult             = $no_of_adult_arr[$i]/$rooms[$i];
                            }
                            
                        }
                        if($no_of_child == '0'){
                            $CmBookingDetails->child             = $no_of_child;
                        }
                        else{
                            if(sizeof($no_of_child_arr)>1){
                                $CmBookingDetails->child             = isset($no_of_child_arr[$i])?$no_of_child_arr[$i]:0;
                            }
                            else{
                                $CmBookingDetails->child             = isset($no_of_child_arr[$i])?$no_of_child_arr[$i]/$rooms[$i]:0;
                            }
                            
                        }
                        $db_status = $CmBookingDetails->save();
                    }
                    $k++;
                }
                if($db_status)
                {
                    if($this->pushBookingToGems($getCmBookingData->unique_id,true)){
                        $res=array('status'=>1,'message'=>"Data Inserted Successfully");
                        return response()->json($res);
                    }
                }
            }
        }
    }

    public function updateBooking(Request $request){

        $bookingDetails = $request->all();
        $CmBookingDetails  = new CmBookingDetailsTable;
        $data = [
        'hotel_id'        => trim($bookingDetails['hotel_id']),
        'ref_no'          => trim($bookingDetails['UniqueID']),
        'room_type'       => trim($bookingDetails['room_type']),
        'rooms'           => trim($bookingDetails['rooms']),
        'room_rate'       => trim($bookingDetails['room_rate']),
        'extra_adult'     => trim($bookingDetails['extra_adult']),
        'extra_child'     => trim($bookingDetails['extra_child']),
        'room_type_id'    => trim($bookingDetails['room_type_id']),
        'rate_plan_id'    => $bookingDetails['rate_plan_id'],
        'rate_plan_name'  => trim($bookingDetails['rate_plan_name']),
        'adult'           => trim($bookingDetails['adult']),
        'child'           => trim($bookingDetails['child']),
        ];

        if($db_status = $CmBookingDetails->where('ref_no',$bookingDetails['UniqueID'])->update($data))
        {
            $res=array('status'=>1,'message'=>"Booking Updated Successfully");
            return response()->json($res);
        }
    }

    public function cancelBooking(Request $request){

        $bookingDetails = $request->all();
        $CmBookingDetails  = new CmBookingDetails;
        $uniqueID = $bookingDetails['UniqueID'];
        $booking_status= 'Cancel';
        $cancel_status = 1; //Updating the cancel status
        $otaBookingData=CmOtaBooking::where('unique_id',$uniqueID)->where('confirm_status',1)->where('cancel_status',$cancel_status)->first();
        if($otaBookingData){
            $res=array('status'=>1,'message'=>"Booking Cancelled Successfully");
            return response()->json($res);
        }
    }


    public function pushBookingToGems($unique_id,$gems){
        $all_bookings = array();
        $getBookingDetails = CmOtaBooking::join('kernel.hotels_table','cm_ota_booking.hotel_id','=','hotels_table.hotel_id')->join('kernel.company_table','hotels_table.company_id','=','company_table.company_id')
        ->join('cm_ota_details','cm_ota_booking.ota_id','=','cm_ota_details.ota_id')
        ->select('cm_ota_booking.room_type', 'cm_ota_booking.unique_id','cm_ota_booking.amount',
        'cm_ota_booking.hotel_id','cm_ota_booking.currency','cm_ota_booking.booking_date','cm_ota_booking.customer_details','cm_ota_booking.checkin_at','cm_ota_booking.checkout_at','hotels_table.hotel_name',
        'cm_ota_booking.payment_status','cm_ota_booking.booking_status','cm_ota_details.ota_name','cm_ota_booking.tax_amount','cm_ota_booking.channel_name','collection_amount')
        ->where('cm_ota_booking.unique_id',$unique_id)
        ->orderBy('cm_ota_booking.id','ASC')
        ->get();

        $ota_logo = array();
        $ota_logo['MakeMyTrip'] = 'uploads/1422311435mmt.png';
        $ota_logo['Goibibo'] = '1119412432goibibo.png';
        $ota_logo['Expedia'] = '1137382374expedia.png';
        $ota_logo['Cleartrip'] = '111669874cleartip.png';
        $ota_logo['Agoda'] = '1016869990agoda.png';
        $ota_logo['Travelguru'] = '1071839720travelguru_logo.gif';
        $ota_logo['Booking.com'] = '1519602952booking.png';
        $ota_logo['Via.com'] = '529817383via.png';
        $ota_logo['Goomo'] = 'Goomo.png';
        $ota_logo['Airbnb'] = 'airbnb-logo.jpg';
        $ota_logo['EaseMyTrip'] = 'easemytrip.png';
        $ota_logo['Paytm'] = 'paytm.png';
        $ota_logo['HappyEasyGo'] = 'happyeasygo.png';

        foreach($getBookingDetails as $key => $bk_details){
            $rooms          =array();
            $ref_no         =$bk_details->unique_id;
            $booking_date   =$bk_details->booking_date;
            $booking_id     =$unique_id;
            $mode_of_payment='Online';
            $User_Details=explode(",", $bk_details->customer_details);
            if(sizeof($User_Details)<3){
                $user_name = 'NA';
                $email = $User_Details[0];
                $mobile = $User_Details[1];
            }
            else{
                $user_name = $User_Details[0];
                $email = $User_Details[1];
                $mobile = $User_Details[2];    
            }
            if($bk_details->payment_status == 'Pay at hotel'){
                $paid_amount = 0;
                $collection_amount = $bk_details->collection_amount;
            }else{
                if($bk_details->channel_name == "Booking.com" || $bk_details->channel_name == "Expedia"){
                    $paid_amount = $bk_details->amount + $bk_details->tax_amount;
                }
                else{
                    $paid_amount = $bk_details->amount;
                }
                $collection_amount = $bk_details->collection_amount;
            }

            if($bk_details->tax_amount == 0){
                $tax = 'Tax Included';
            }else{
                $tax = $bk_details->tax_amount;
            }

            $k=0;
            $x=0;
            // $getRoomDetails = CmBookingDetailsTable::select('*')->where('ref_no',$bk_details->unique_id)->get();
            $getRoomDetails = DB::table('cm_booking_details_table')
                            ->select(DB::raw("(GROUP_CONCAT(room_type SEPARATOR ',')) as room_type"),
                            DB::raw("(GROUP_CONCAT(ref_no SEPARATOR ',')) as ref_no"),
                            DB::raw("(GROUP_CONCAT(room_type_id SEPARATOR ',')) as room_type_id"),
                            DB::raw("(GROUP_CONCAT(rate_plan_id SEPARATOR ',')) as rate_plan_id"),
                            DB::raw("(GROUP_CONCAT(rate_plan_name SEPARATOR ',')) as rate_plan_name"),
                            DB::raw("(GROUP_CONCAT(room_rate SEPARATOR ',')) as room_price"),
                            DB::raw('SUM(rooms) AS rooms'),DB::raw('SUM(room_rate) AS room_rate'),
                            DB::raw('SUM(extra_adult) AS extra_adult'),DB::raw('SUM(extra_child) AS extra_child'),
                            DB::raw('SUM(adult) AS adult'),DB::raw('SUM(child) AS child'))
                            ->where('ref_no',$unique_id)->groupBy('ref_no','room_type_id','rate_plan_id')->get();
         
            foreach ($getRoomDetails as $getRoom) {

                $room_type_id = implode(',',array_unique(explode(",",$getRoom->room_type_id)));
                $room_type = implode(',',array_unique(explode(",",$getRoom->room_type)));
                $room_plan = implode(',',array_unique(explode(",",$getRoom->rate_plan_id)));
                $rate_plan_name = implode(',',array_unique(explode(",",$getRoom->rate_plan_name)));
                $room_price = implode(',',array_unique(explode(",",$getRoom->room_price)));

                $rooms[] = array(
                "room_type_id"          => $room_type_id,
                "room_type_name"        => $room_type,
                "no_of_rooms"           => $getRoom->rooms,
                "room_rate"             => $room_price,
                "plan"                  => $rate_plan_name,
                "adult"                 => $getRoom->adult,
                "child"                 => $getRoom->child,
                "extra_adult_rate"      => $getRoom->extra_adult,
                "extra_child_rate"      => $getRoom->extra_child
                );
            }
            $user_info = array(
                "user_name"             => $user_name,
                "mobile"                => $mobile,
                "email"                 => $email,
                );
                if($gems == 'true'){
                  if($bk_details->channel_name == "Booking.com" || $bk_details->channel_name == "Expedia"){
                    $grand_amount = $bk_details->amount + $bk_details->tax_amount;
                  }
                  else{
                    $grand_amount = $bk_details->amount;
                  }
                  if($bk_details->booking_status == 'Commit'){
                    $booking_status = 'confirmed';
                  }
                  else{
                    $booking_status = $bk_details->booking_status;
                  }

                $Bookings = array(
                    "date_of_booking"       => $booking_date,
                    "hotel_id"              => $bk_details->hotel_id,
                    "hotel_name"            => $bk_details->hotel_name,
                    "check_in"              => $bk_details['checkin_at'],
                    "check_out"             => $bk_details['checkout_at'],
                    "booking_id"            => $booking_id,
                    "mode_of_payment"       => $mode_of_payment,
                    "grand_total"           => $grand_amount,
                    "collection_amount"     => $collection_amount,
                    "currency"              => $bk_details->currency,
                    "paid_amount"           => $paid_amount,
                    "tax_amount"            => $bk_details->tax_amount,
                    "discount_amount"       => 0,
                    "channel"               => $bk_details->channel_name,
                    "channel_logo"          => "https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/uploads/".$ota_logo[$bk_details->ota_name],
                    "status"                => $booking_status
                    );
                }
                else{
                $Bookings = array(
                    "date_of_booking"       => $booking_date,
                    "hotel_id"              => $bk_details->hotel_id,
                    "hotel_name"            => $bk_details->hotel_name,
                    "check_in"              => $bk_details['checkin_at'],
                    "check_out"             => $bk_details['checkout_at'],
                    "booking_id"            => $booking_id,
                    "mode_of_payment"       => $mode_of_payment,
                    "grand_total"           => $bk_details->total_amount,
                    "collection_amount"     => $collection_amount,
                    "paid_amount"           => $paid_amount,
                    "tax_amount"            => $tax,
                    "discount_amount"       => 0,
                    "channel"               => $bk_details->ota_name,
                    "channel_logo"          => "https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/uploads/".$ota_logo[$bk_details->ota_name],
                    "status"                => $booking_status
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
}
