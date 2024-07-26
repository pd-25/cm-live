<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\OfflineBooking;
use App\User;
use App\Invoice;//class name from model
use App\HotelInformation;
use App\CmOtaBooking;//class name from model
use App\CmOtaRoomTypeSynchronize;
use App\CmOtaRatePlanSynchronize;
use DB;

//create a new class OfflineBookingController
class BookingDetailsDownloadController extends Controller
{
    public function getSearchData($booking_data,Request $request)
    {
        $data=json_decode(urldecode ($booking_data));
        $from_date = date('Y-m-d',strtotime($data[0]->from_date));
        $to_date =  date('Y-m-d',strtotime($data[0]->to_date));
        $row=array();
        if($data[0]->status == 'be')
        {
            if($data[0]->date_status==2)
            {
                $bet_date='check_in';
            }
            else if($data[0]->date_status==1)
            {
                $bet_date='booking_date';
            }
            else
            {
                $bet_date='check_in';
            }
            $con='hotel_booking.'.$bet_date;
             $result =  Invoice::join('hotel_booking','invoice_table.invoice_id','=','hotel_booking.invoice_id')->select('invoice_table.invoice_id','hotel_booking.rooms','hotel_booking.check_in','hotel_booking.check_out','invoice_table.hotel_name','invoice_table.room_type','invoice_table.total_amount','invoice_table.hotel_id','invoice_table.paid_amount','invoice_table.booking_date','invoice_table.booking_status','invoice_table.user_id')->where('invoice_table.hotel_id',$data[0]->hotel_id)->where('invoice_table.booking_status',1)->whereBetween($con, array($from_date, $to_date))->get();
             if(sizeof($result)>0)
            {
                 foreach($result as $rslt)
                {
                    $userdetails=User::select('first_name','last_name','email_id','mobile')->where('user_id',$rslt->user_id)->first();
                    $getCommision= HotelInformation::join('company_table','hotels_table.company_id','=','company_table.company_id')->select('company_table.commission')->where('hotel_id',$rslt->hotel_id)->first();
                    $commision_amount=$rslt->total_amount*$getCommision->commission/100;
                    $hotelier_amount=$rslt->total_amount-$commision_amount;
                    $unique_id=date('dmy',strtotime($rslt->booking_date)).$rslt->invoice_id;
                    $username=$userdetails->first_name.' '.$userdetails->last_name;
                    $date1 = date_create($rslt->check_in);
                    $date2 = date_create($rslt->check_out);
                    $number_of_night=date_diff($date1,$date2);
                    $number_of_night=$number_of_night->d;
                    $row[]=array('Reference No'=>$unique_id, 'Guest Name'=>$username, 'Email'=>$userdetails->email_id, 'Mobile'=>$userdetails->mobile,'Hotel Name'=>$rslt->hotel_name,'Booking Status'=>$rslt->booking_status,'Booking Date'=>$rslt->booking_date,'Checkin Date'=>$rslt->check_in,'Checkout Date'=>$rslt->check_out,'Room Type'=>$rslt->room_type,'Rooms'=>$rslt->rooms,'Total Nights'=>$number_of_night,'Total Amount'=>$rslt->total_amount,'Paid Amount'=>$rslt->paid_amount,'Comission Amount'=>$commision_amount,'Hotelier Amount'=>$hotelier_amount);
                 }
            }
             else{
                 echo "Sorry!No booking available.";
            }
        }
        else if($data[0]->status == 'ota')
        {
            if($data[0]->booking_id == 'NA')
            {
                if($data[0]->book_status == 'confirmed')
                {
                    $condition = $data[0]->ota_id == 0 ? array('confirm_status'=>1,'cancel_status'=>0,'hotel_id'=>$data[0]->hotel_id) : array('ota_id'=>$data[0]->ota_id,'confirm_status'=>1,'cancel_status'=>0,'hotel_id'=>$data[0]->hotel_id);
                }
                else{
                    $condition = $data[0]->ota_id == 0 ? array('confirm_status'=>1,'cancel_status'=>1,'hotel_id'=>$data[0]->hotel_id) : array('ota_id'=>$data[0]->ota_id,'confirm_status'=>1,'cancel_status'=>1,'hotel_id'=>$data[0]->hotel_id);
                }
                if($data[0]->date_status==2)
                {
                    $bet_date='checkin_at';
                }
                else if($data[0]->date_status==1)
                {
                    $bet_date='booking_date';
                }
                else
                {
                    $bet_date='checkin_at';
                }

                $result =  CmOtaBooking::select('*')->where($condition)->whereDate($bet_date,'>=',$from_date)->whereDate($bet_date,'<=',$to_date)->get();
                if(sizeof($result)>0)
                {
                    foreach($result as $rslt)
                    {
                        $customer_data=explode(',',$rslt->customer_details);
                        $username=$customer_data[0];
                        $email=$customer_data[1];
                        $contact=$customer_data[2];
                        $adult_data=explode(',',$rslt->no_of_adult);
                        $child_data=explode(',',$rslt->no_of_child);
                        $child_sum=0;
                        $adult_sum=0;
                        foreach($adult_data as $adult)
                        {
                          $adult_sum += $adult;
                        }
                        foreach($child_data as $child)
                        {
                          $child_sum += (int)$child;
                        }
                        $total_pax=$adult_sum+ $child_sum;
                        $date1 = date_create($rslt->checkin_at);
                        $date2 = date_create($rslt->checkout_at);
                        $number_of_night=date_diff($date1,$date2);
                        $number_of_night=$number_of_night->d;
                        $rate_code=$this->getRate_plan($rslt->room_type,$rslt->ota_id,$rslt->rate_code);
                        $room_type=$this->getRoom_types($rslt->room_type,$rslt->ota_id);
                        $row[]=array('Reference No'=>$rslt->unique_id, 'Guest Name'=>$username, 'Email'=>$email, 'Mobile'=>$contact,'Channel Name'=>$rslt->channel_name,'Booking Status'=>$rslt->booking_status,'Booking Date'=>$rslt->booking_date,'Checkin Date'=>$rslt->checkin_at,'Checkout Date'=>$rslt->checkout_at,'Room Type'=>$room_type,'Plan Type'=>$rate_code,'Total Pax(adult(s)+child(s)'=>$total_pax,'Rooms'=>$rslt->rooms_qty,'Total Nights'=>$number_of_night,'Total Amount'=>$rslt->amount,'Tax Amount'=>$rslt->tax_amount,'Payment Status'=>$rslt->payment_status,'Inclusion'=>$rslt->inclusion);
                    }
                }
                else{
                    echo "Sorry!No booking available.";
                }
            }
            else
            {
                $condition=array('unique_id'=>$data[0]->booking_id);
                $result =  CmOtaBooking::select('*')->where($condition)->first();
                if($result)
                {
                    $customer_data=explode(',',$rslt->customer_details);
                    $username=$customer_data[0];
                    $email=$customer_data[1];
                    $contact=$customer_data[2];
                    $adult_data=explode(',',$rslt->no_of_adult);
                    $child_data=explode(',',$rslt->no_of_child);
                    $child_sum=0;
                    $adult_sum=0;
                    foreach($adult_data as $adult)
                    {
                        $adult_sum += $adult;
                    }
                    foreach($child_data as $child)
                    {
                        $child_sum += $child;
                    }
                    $total_pax=$adult_sum+ $child_sum;
                    $date1 = date_create($rslt->checkin_at);
                    $date2 = date_create($rslt->checkout_at);
                    $number_of_night=date_diff($date1,$date2);
                    $number_of_night=$number_of_night->d;
                    $rate_code=$this->getRate_plan($rslt->room_type,$rslt->ota_id,$rslt->rate_code);
                    $room_type=$this->getRoom_types($rslt->room_type,$rslt->ota_id);
                    $row[]=array('Reference No'=>$rslt->unique_id, 'Guest Name'=>$username, 'Email'=>$email, 'Mobile'=>$contact,'Channel Name'=>$rslt->channel_name,'Booking Status'=>$rslt->booking_status,'Booking Date'=>$rslt->booking_date,'Checkin Date'=>$rslt->checkin_at,'Checkout Date'=>$rslt->checkout_at,'Room Type'=>$room_type,'Plan Type'=>$rate_code,'Total Pax(adult(s)+child(s)'=>$total_pax,'Rooms'=>$rslt->rooms_qty,'Total Nights'=>$number_of_night,'Total Amount'=>$rslt->amount,'Tax Amount'=>$rslt->tax_amount,'Payment Status'=>$rslt->payment_status,'Inclusion'=>$rslt->inclusion);
                }
                else{
                    echo "Sorry!No booking available.";
                }
            }
        }
        else
        {
            if($data[0]->date_status==2)
            {
                $bet_date='check_in';
            }
            else if($data[0]->date_status==1)
            {
                $bet_date='booking_date';
            }
            else
            {
                $bet_date='check_in';
            }
            $con='offline_booking.'.$bet_date;
             $result =  OfflineBooking::join('room_type_table','offline_booking.room_type_id','=','room_type_table.room_type_id')->join('user_table','offline_booking.user_id','=','user_table.user_id')->join('hotels_table','offline_booking.hotel_id','=','hotels_table.hotel_id')->select('hotels_table.hotel_name','room_type_table.room_type','offline_booking.hotel_booking_id','offline_booking.total_amount','offline_booking.paid_amount','offline_booking.booking_date','offline_booking.booking_status','user_table.first_name','user_table.last_name','user_table.email_id','user_table.mobile','offline_booking.rooms','offline_booking.check_in','offline_booking.check_out')->where('offline_booking.hotel_id',$data[0]->hotel_id)->where('offline_booking.booking_status',1)->whereBetween($con, array($from_date, $to_date))->get();
             if(sizeof($result)>0)
            {
                 foreach($result as $rslt)
                {
                    $unique_id=date('dmy',strtotime($rslt->booking_date)).$rslt->hotel_booking_id;
                    $username=$rslt->first_name.' '.$rslt->last_name;
                    $date1 = date_create($rslt->check_in);
                    $date2 = date_create($rslt->check_out);
                    $number_of_night=date_diff($date1,$date2);
                    $number_of_night=$number_of_night->d;
                    $row[]=array('Reference No'=>$unique_id, 'Guest Name'=>$username, 'Email'=>$rslt->email_id, 'Mobile'=>$rslt->mobile,'Hotel Name'=>$rslt->hotel_name,'Booking Status'=>$rslt->booking_status,'Booking Date'=>$rslt->booking_date,'Checkin Date'=>$rslt->check_in,'Checkout Date'=>$rslt->check_out,'Room Type'=>$rslt->room_type,'Rooms'=>$rslt->rooms,'Total Nights'=>$number_of_night,'Total Amount'=>$rslt->total_amount,'Paid Amount'=>$rslt->paid_amount);
                 }
            }
             else{
                 echo "Sorry!No booking available.";
            }
        }

        if($data[0]->status == 'be')
        {
            if(sizeof($row)>0)
            {
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename=beBooking.csv');
                $output = fopen("php://output", "w");
                fputcsv($output, array('Reference No','Guest Name','Email','Mobile','Hotel Name','Booking Status','Booking Date','Checkin Date','Checkout Date','Room Type','Rooms','Total Nights','Total Amount','Paid Amount','Comission Amount','Hotelier Amount'));

                foreach($row as $data)
                {
                    fputcsv($output, $data);
                }
                fclose($output);
            }
        }
        else if($data[0]->status == 'ota')
        {
            if(sizeof($row)>0)
            {
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename=otaBooking.csv');
                $output = fopen("php://output", "w");
                fputcsv($output, array('Reference No','Guest Name','Email','Mobile','Channel Name','Booking Status','Booking Date','Checkin Date','Checkout Date','Room Type','Plan Type','Total Pax(adult(s)+child(s)','Rooms','Total Nights','Total Amount','Tax Amount','Payment Status','Inclusion'));
                foreach($row as $data)
                {
                    fputcsv($output, $data);
                }
                fclose($output);
            }
        }
        else{
            if(sizeof($row)>0)
            {
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename=offlineBooking.csv');
                $output = fopen("php://output", "w");
                fputcsv($output, array('Reference No','Guest Name','Email','Mobile','Hotel Name','Booking Status','Booking Date','Checkin Date','Checkout Date','Room Type','Rooms','Total Nights','Total Amount','Paid Amount'));
                foreach($row as $data)
                {
                    fputcsv($output, $data);
                }
                fclose($output);
            }
        }
    }
    public function getRoom_types($room_type,$ota_id)
    {
      $cmOtaRoomTypeSynchronize= new CmOtaRoomTypeSynchronize();
      $room_types=explode(',',$room_type);
      $hotel_room_type=array();
      foreach($room_types as $ota_room_type)
      {
        $room=$cmOtaRoomTypeSynchronize->getRoomType($ota_room_type,$ota_id);
        if($room === 0)
        {
          array_push($hotel_room_type,"Room type is not synced with OTA");
        }
        else
        {
          array_push($hotel_room_type,$room);
        }
      }
      return implode(',',$hotel_room_type);
    }
    public function getRate_plan($ota_room_type,$ota_id,$rate_plan_id)
    {
      $cmOtaRatePlanSynchronize= new CmOtaRatePlanSynchronize();
      $rate_plan_ids=explode(',',$rate_plan_id);
      $hotel_rate_plan=array();
      foreach($rate_plan_ids as $ota_rate_plan_id)
      {
       array_push($hotel_rate_plan,$cmOtaRatePlanSynchronize->getRoomRatePlan($ota_id,$ota_rate_plan_id));
      }

      return implode(',',$hotel_rate_plan);
    }
}
