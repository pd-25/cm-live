<?php
namespace App\Http\Controllers;
use App\ReportsQuestion;
use App\HotelInformation;
use Eloquent;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
class DeReportController extends Controller
{
    public function getAllOtaBooking($month = null,$year = null)
    {
       if($year != 0 && $month == 0)
       {
           $query = "WHERE YEAR(booking_date) = ".$year."";
       }
       else if($year != 0 && $month != 0)
       {
           $query = "WHERE YEAR(booking_date) = ".$year." AND MONTH(booking_date) = ".$month."";
       }
       else
       {
           $query = "";
       }

        $allotaBookings = DB::select(DB::raw("SELECT hotels_table.hotel_id, hotels_table.hotel_name, cm_ota_booking.amount FROM cm_ota_booking LEFT JOIN kernel.hotels_table ON cm_ota_booking.hotel_id = hotels_table.hotel_id $query"));

        $format = [["name" => "Hotel Id", "field" => "hotel_id", "options" => ["filter" => true, "sort" => true]], ["name" => "Hotel Name", "field" => "hotel_name", "options" => ["filter" => true, "sort" => true]], ["name" => "Booking Amount", "field" => "amount", "options" => ["filter" => true, "sort" => true]]];
        if($allActiveHotels != [])
        {
           $msg=array('status' => 1,'message'=>'All hotel Detail Found','hotelDeatails' => $allActiveHotels, 'format' => $format);
           return response()->json($msg);
        }
        else
        {
           $msg=array('status' => 0,'message'=>'Hotels Detail not Found');
           return response()->json($msg);
        }
    }
    public function hotelUseingDE()
    {
        $allActiveHotels = DB::connection('kernel')->select(DB::raw("SELECT hotels_table.hotel_id, hotels_table.hotel_name FROM hotels_table LEFT JOIN billing_table ON hotels_table.company_id = billing_table.company_id WHERE product_name LIKE '%Channel Manager%'"));


        $format = [["name" => "Hotel Id", "field" => "hotel_id", "options" => ["filter" => true, "sort" => true]], ["name" => "Hotel Name", "field" => "hotel_name", "options" => ["filter" => true, "sort" => true]]];
        if($allActiveHotels != [])
        {
           $msg=array('status' => 1,'message'=>'All hotel Detail Found','hotelDeatails' => $allActiveHotels, 'format' => $format);
           return response()->json($msg);
        }
        else
        {
           $msg=array('status' => 0,'message'=>'Hotels Detail not Found');
           return response()->json($msg);
        }

    }
    public function hotelNotUseingDE()
    {
        $allActiveHotels = DB::connection('kernel')->select(DB::raw("SELECT hotels_table.hotel_id, hotels_table.hotel_name FROM hotels_table LEFT JOIN billing_table ON hotels_table.company_id = billing_table.company_id WHERE product_name NOT LIKE '%Channel Manager%'"));


        $format = [["name" => "Hotel Id", "field" => "hotel_id", "options" => ["filter" => true, "sort" => true]], ["name" => "Hotel Name", "field" => "hotel_name", "options" => ["filter" => true, "sort" => true]]];
        if($allActiveHotels != [])
        {
           $msg=array('status' => 1,'message'=>'All hotel Detail Found','hotelDeatails' => $allActiveHotels, 'format' => $format);
           return response()->json($msg);
        }
        else
        {
           $msg=array('status' => 0,'message'=>'Hotels Detail not Found');
           return response()->json($msg);
        }

    }
    public function getCityWiseOtaBooking($city_id, $start_date, $end_date)
    {
        $myStartDate = date('Y-m-d', strtotime($start_date));
        $myEndDate = date('Y-m-d', strtotime($end_date));
        // dd($myStartDate);
        $allBookings = DB::select(DB::raw("SELECT COUNT(cm_ota_booking.unique_id) AS noOfBooking, hotels_table.hotel_name FROM cm_ota_booking LEFT JOIN kernel.hotels_table ON cm_ota_booking.hotel_id = hotels_table.hotel_id WHERE hotels_table.city_id = $city_id AND cm_ota_booking.confirm_status = 1 AND cm_ota_booking.cancel_status = 0 AND CAST(booking_date as DATE) BETWEEN DATE('$myStartDate') AND DATE('$myEndDate') GROUP BY hotels_table.hotel_name"));

        $format = [["name" => "Hotel Name", "field" => "hotel_name", "options" => ["filter" => true, "sort" => true]], ["name" => "Bookings", "field" => "noOfBooking", "options" => ["filter" => true, "sort" => true]]];
        if($allBookings != [])
        {
           $msg=array('status' => 1,'message'=>'All Booking Found','hotelDeatails' => $allBookings, 'format' => $format);
           return response()->json($msg);
        }
        else
        {
           $msg=array('status' => 0,'message'=>'Hotels Booking not Found');
           return response()->json($msg);
        }
    }
    public function otaBooking($from_date, $to_date, $hotel_id = NULL, $question_id)
    {
      if($question_id == 11)
      {
         $otaBookings = DB::select(DB::raw("SELECT cm_ota_booking.channel_name, COUNT(cm_ota_booking.channel_name) AS noOfBooking, SUM(CAST(`amount` AS DECIMAL(10,2))) as totalAmount FROM cm_ota_booking WHERE cm_ota_booking.confirm_status = 1 AND cm_ota_booking.cancel_status = 0 AND date(`booking_date`) between '$from_date' AND '$to_date' GROUP BY cm_ota_booking.channel_name"));

         $totalBooking = [];
         $channel_name = [];
         $bookingAmount = [];

         foreach($otaBookings as $total)
         {
            if($total->channel_name != null)
            {
               array_push($totalBooking, $total->noOfBooking);
               array_push($channel_name, $total->channel_name);
               array_push($bookingAmount, $total->totalAmount);
            }
         }
         if($otaBookings != 0)
         {
            $msg=array('status' => 1,'message'=>'OTA Bookings','numberOfBooking' => $totalBooking, 'otaName' => $channel_name, 'totalBookingAmount'=> $bookingAmount);
            return response()->json($msg);
         }
         else
         {
            $msg=array('status' => 0,'message'=>'No Data Found');
            return response()->json($msg);
         }
      }
      else if ($question_id == 63)
      {
         $otaBookings = DB::select(DB::raw("SELECT cm_ota_booking.channel_name, COUNT(cm_ota_booking.channel_name) AS noOfBooking,SUM(CAST(`amount` AS DECIMAL(10,2))) as totalAmount FROM cm_ota_booking WHERE cm_ota_booking.confirm_status = 1 AND cm_ota_booking.cancel_status = 0 AND date(`booking_date`) between '$from_date' AND '$to_date' AND hotel_id = '$hotel_id' GROUP BY cm_ota_booking.channel_name"));

         $totalBooking = [];
         $channel_name = [];
         $bookingAmount = [];

         foreach($otaBookings as $total)
         {
            if($total->channel_name != null)
            {
               array_push($totalBooking, $total->noOfBooking);
               array_push($channel_name, $total->channel_name);
               array_push($bookingAmount, $total->totalAmount);
            }
         }
         if($otaBookings != 0)
         {
            $msg=array('status' => 1,'message'=>'OTA Bookings','numberOfBooking' => $totalBooking, 'otaName' => $channel_name, 'totalBookingAmount'=> $bookingAmount);
            return response()->json($msg);
         }
         else
         {
            $msg=array('status' => 0,'message'=>'No Data Found');
            return response()->json($msg);
         }
      }
    }
    public function noOfLastSevenDaysOTABookings($hotel_id)
	{
	    $from_date=date('Y-m-d',strtotime(' - 7 days'));
	    $to_date=date('Y-m-d');
	    // $hotel_id=759;
	    $otaBookings = DB::select(DB::raw("SELECT sum(CAST(amount AS DECIMAL(10,2))) as booking_amount,count(id) as booking_count,channel_name  from cm_ota_booking where hotel_id='$hotel_id' and booking_date >=DATE('$from_date') and booking_date <= DATE('$to_date') group by channel_name"));
	    if($otaBookings){
	      $msg=array('status' => 1, 'bookings' => $otaBookings);
	      return response()->json($msg);
	    }
	    else{
	      $msg=array('status' => 0);
	      return response()->json($msg);
	    }

	}
   public function getAllOtaType(Request $request)
   {
      $otaData = DB::table('cm_ota_credential_parameter')->select('ota_name')->get();
      if(sizeof($otaData))
      {
          $res = array('status'=>1,'message'=>'Data Retrieved Successfully','data'=>$otaData);
          return response()->json($res);
      }else{
          $res = array('status'=>0,'message'=>'Data Retrieved Failed');
          return response()->json($res);
      }
   }
   // -----------------get ota wise hotels ----------------------//
   public function getOTAwiseHotel(Request $request)
   {
      $data=$request->all();

      $getHotels = DB::table('cm_ota_details')->select('cm_ota_details.hotel_id')->where('cm_ota_details.ota_name',$data['ota_name'])->get();

      if($getHotels){
         $res=array('status'=>1,'message'=>'Hotels data retrived successfully','data'=>$getHotels);
         return response()->json($res); 
      }
      else{
         $res=array('status'=>0,'message'=>'Hotels data not found');
         return response()->json($res);   
      }
   }
}
