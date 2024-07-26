<?php
namespace App\Http\Controllers\otacontrollers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Input;
use Validator;
use App\CmOtaDetails;
use App\CmOtaAllAutoPush;
use DB;
use App\Http\Controllers\otacontrollers\BookingDataInsertationController;
use App\Http\Controllers\otacontrollers\InstantBucketController;
use App\Http\Controllers\Controller;
/**
 * ViadotcomController implements via booking.
 * @modify by ranjit
 * @29/01/19
 */

class ViadotcomController extends Controller
{
    protected $bookingData,$curlcall,$instantbucket;
    public function __construct(BookingDataInsertationController $bookingData,InstantBucketController $instantbucket)
    {
      $this->bookingData=$bookingData;
      $this->instantbucket=$instantbucket;
    }
    public function actionIndex(Request $request)
    {
        /*--------- variabl edeclartion ------------*/
        $OtaAllAutoPushModel           = new CmOtaAllAutoPush();
        $postdata 						=$request->getContent();
        $request_ip = $_SERVER['REMOTE_ADDR'];

        if($postdata!='')
        {
          //saving the xml data pushed by via.
            $OtaAllAutoPushModel->respones_xml = trim($postdata);
            $OtaAllAutoPushModel->save();
            $array_data           = (array) json_decode($postdata);
          //Gathering the  required booking data.
            $UniqueID             = $array_data['reference'];
            $otaHotelCode         = $array_data['productId'];
            //return $otaHotelCode;
            $ota_hotel_details    = CmOtaDetails::select('*')
                                    ->where('ota_hotel_code','=', $otaHotelCode)
                                    ->where('is_status', 1)
                                    ->first();

            if(!$ota_hotel_details)
            {
              return $rtn_xml='{"reference":"'.$UniqueID.'", "status":"Not Confirmed", "confirmationId":"", "description":"Invalid ProductId"}';
            }
            else
            {
                $booking_status       = $array_data['status'];
                $rooms_qty            = $array_data['totalRooms'];
                $roomDetails          = $array_data['roomBookingInfos'];
                $inclusion='';
                $adults=array();
                $child=array();
  
                foreach ($roomDetails as $key => $value)
                {
                  $room_type_ids[]      = $value->roomId;
                  $rate_plan_ids[]      = $value->ratePlanCode;
                  $inclusion            = implode(',',$value->inclusions);
                  $adults[]             = sizeof($value->adults);
                  $child[]              = sizeof($value->children);
                  foreach ($value->adults as $k => $v)
                  {
                    if(isset($v->email) && isset($v->phone))
                    {
                      $custommerDetails[]   = $v->title.' '.$v->firstName.' '.$v->lastName.','.$v->email.','.$v->phone;
                    }
                    else if(isset($v->email) && !isset($v->phone))
                    {
                      $custommerDetails[]   = $v->title.' '.$v->firstName.' '.$v->lastName.','.$v->email.','.'NA';
                    }
                    else if(!isset($v->email) && isset($v->phone))
                    {
                      $custommerDetails[]   = $v->title.' '.$v->firstName.' '.$v->lastName.','.'NA'.','.$v->phone;
                    }
                    else{
                      $custommerDetails[]   = $v->title.' '.$v->firstName.' '.$v->lastName.','.'NA'.','.'NA';
                    }
                  }
                }
                $no_of_adult          = implode(',',$adults);
                $no_of_child          = implode(',',$child);
                $room_type            = implode(',', $room_type_ids);
                $rate_code            = implode(',', $rate_plan_ids);
                $customerDetail       = implode('.', $custommerDetails);
    
                $pricingData          =  $array_data['pricing']->totalPrice;
                $amount               = $pricingData;
                $tax_amount          =  $array_data['pricing']->totalTaxes;
                $currency             = $array_data['currency'];
                $payment_status       = $array_data['status'] == 'Manual'?'Pay at hotel':'Paid';
                $checkin_at           = $array_data['checkInDate'];
                $checkout_at          = $array_data['checkOutDate'];
                $booking_date         = $array_data['generationTime'];
                $price = array();
                for($i=0;$i<$rooms_qty;$i++){
                  $price[]            =  ($amount/$rooms_qty);
                }
                $room_price           = implode(',',$price);

                $rlt=$postdata;
                if($booking_status == 'Manual' || $booking_status == 'Confirmed'){
                  $booking_status  = 'Commit';
                  }
                if($booking_status == 'Cancelled'){
                $booking_status  = 'Cancel';
                }
                $bookingDetails = array('UniqueID'=>$UniqueID,'customerDetail'=>$customerDetail,'booking_status'=>$booking_status,'rooms_qty'=>$rooms_qty,'room_type'=>$room_type,'checkin_at'=>$checkin_at,'checkout_at'=>$checkout_at,'booking_date'=>$booking_date,'amount'=>$amount,'payment_status'=>$payment_status,'rate_code'=>$rate_code,'rlt'=>$rlt,'currency'=>$currency,'channel_name'=>'Via.com','room_price'=>$room_price,'tax_amount'=>$tax_amount,'no_of_adult'=>$no_of_adult,'no_of_child'=>$no_of_child,'inclusion'=>$inclusion);
                $bookinginfo  = $this->bookingData->cmOtaBooking($bookingDetails,$ota_hotel_details);//this function call used for insert/update booking in database
                $db_status  = $bookinginfo['db_status'];
                $cm_confirmation_id =$bookinginfo['ota_booking_tabel_id'];
                $push_by = "Via";
                if($db_status)
                {
                  $this->instantbucket->bucketEngineUpdate($booking_status,$push_by,$ota_hotel_details,$cm_confirmation_id);//this function is used for booking bucket data updation
                  $rtn_xml = '{"reference":"'.$UniqueID.'", "status":"Confirmed", "confirmationId":"'.$cm_confirmation_id.'", "description":"Success"}';
                }
                else
                {
                  $rtn_xml = '{"reference":"'.$UniqueID.'", "status":"Not Confirmed", "confirmationId":"", "description":"Invalid ProductId Or Booking already with us"}';
                }
                return $rtn_xml;
            }
        }
      else{
      return "Booking data missing";
      }
    } //function close
}
