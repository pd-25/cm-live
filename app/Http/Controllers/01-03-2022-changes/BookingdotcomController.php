<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Input;
use Validator;
use App\CmOtaDetails;
use App\CmOtaBooking;
use App\CmOtaAllAutoPush;
use App\CmOtaBookingPushBucket;
use App\LogTable;
use App\Http\Controllers\CmOtaBookingInvStatusService;
use App\Http\Controllers\CommonServiceController;
use DB;

/**
* Booking.com Controller implements the CRUD actions for Booking.com model.
*/
class BookingdotcomController extends Controller
{
    protected $commonService,$ipService;
    protected $cmOtaBookingInvStatusService;
    public function __construct(CommonServiceController $commonService,CmOtaBookingInvStatusService $cmOtaBookingInvStatusService,IpAddressService $ipService)
    {
       $this->commonService = $commonService;
       $this->cmOtaBookingInvStatusService=$cmOtaBookingInvStatusService;
       $this->ipService=$ipService;
    }


    public function actionIndex(Request $request)
    {
              $OtaAllAutoPushModel          = new CmOtaAllAutoPush();
              $ota_details_model            = new CmOtaDetails();
              $otalog                        = new LogTable();
             $headers = array (
              'Content-Type: application/xml',
              );

              $xml ='<?xml version="1.0" encoding="UTF-8"?>
              <request>
              <username>Bookingjini-channelmanager</username>
              <password>wSznWO?2wy/^-j/hfUK^MCq?:A*EK)BBXSMK-.*)</password>
              </request>';
              $url = 'https://secure-supply-xml.booking.com/hotels/xml/reservations';
              
              $ch  = curl_init();
              curl_setopt( $ch, CURLOPT_URL, $url );
              curl_setopt( $ch, CURLOPT_POST, true );
              curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
              curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
              curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml);
              $result = curl_exec($ch);
              curl_close($ch);             
              $OtaAllAutoPushModel->respones_xml = trim($result);
              $array_data = json_decode(json_encode(simplexml_load_string($result)), true);
              
              if(isset($array_data['reservation'])){
                $OtaAllAutoPushModel->save();
              $reservation_datas  = $array_data['reservation'];
              $isMultidimensional_reservation_datas = $this->commonService->isMultidimensionalArray($reservation_datas);
            
            /*---if candition for checking is multidimentional array or not here---*/
            if($isMultidimensional_reservation_datas)
            {
              
            foreach ($reservation_datas as $reservation_data) {
              $otaBookingModel    = new CmOtaBooking();
              $otaHotelCode       = $reservation_data['hotel_id'];
              $ota_hotel_details  = $ota_details_model
              ->where('ota_hotel_code', '=' ,$otaHotelCode)
              ->first();
              $channel_name   = 'Booking.com';
            if($ota_hotel_details->hotel_id)
            {
              $booking_status = $reservation_data['status'];
              
              if($booking_status == "new"){

              $UniqueID       = $reservation_data['id'];
              $booking_date   = $reservation_data['date'];
              $customerDetail = $reservation_data['customer']['first_name']." ".$reservation_data['customer']['last_name'].",".$reservation_data['customer']['email'].",".$reservation_data['customer']['telephone'];    
              $amount         = $reservation_data['totalprice'];
              $currency       = $reservation_data['currencycode'];
             
            
             
              
              $payment_status = 'NA';

              $otalog->ota_id = $ota_hotel_details->ota_id;
              $otalog->hotel_id = $ota_hotel_details->hotel_id;
              $otalog->request_msg = $xml;
              $otalog->booking_ref_id = $UniqueID;
              $otalog->response_msg = trim($result);
              $otalog->save();


               /*-----------Fetch Rate Plan------------------*/
              $xml            = simplexml_load_string($result);
              
              foreach($xml as $room_rate_xml)
              {
               if($room_rate_xml->status=='new')
               {
                   $roomRateNode   = $room_rate_xml->children()->room;
               }
              }
              $p=0;
              $extra_amount=0;
              foreach ($roomRateNode as $key => $value) {
                $rt_code[] = $value->children()->price->attributes()->rate_id;
                $p++;
              }
               $rt_code   =  array_unique($rt_code, SORT_REGULAR);;

              $isMultidimensional_rooms = $this->commonService->isMultidimensionalArray($reservation_data['room']);
                
              if($isMultidimensional_rooms){

              $UniqueRooms = $this->commonService->uniqueMultidimArray($reservation_data['room'],'id');
              $gestdetails =  $reservation_data['room'];
              $rta         = array_column($reservation_data['room'], 'id');
              
              $roomarrayidoccurrences = array_count_values($rta);

              for($i=0;$i<$p;$i++)
              {     if(isset($reservation_data['room'][$i]['price_details']['hotel']['extracomponent']))
                  {
                    $ex_size=sizeof($reservation_data['room'][$i]['price_details']['hotel']['extracomponent']);
                    if($ex_size>1)
                    {
                      for($e=0;$e<$ex_size;$e++)
                      {
                        if(isset($reservation_data['room'][$i]['price_details']['hotel']['extracomponent'][$e]))
                        {
                            $extra_amount   =  $extra_amount+$reservation_data['room'][$i]['price_details']['hotel']['extracomponent'][$e]['@attributes']['amount'];
                        }
                      }

                    }
                    else
                    {
                      if(isset($reservation_data['room'][$i]['price_details']['hotel']['extracomponent']))
                      {
                          $extra_amount   =  $extra_amount+$reservation_data['room'][$i]['price_details']['hotel']['extracomponent']['@attributes']['amount'];
                      }
                    }
                  }
              }
              $adults=array();
              $children=array();
              $i++;
              foreach ($UniqueRooms as $UniqueRoom){
                $rm_type[]    = $UniqueRoom['id'];
                $rm_qty[]     = $roomarrayidoccurrences[$UniqueRoom['id']];
                $checkin_at   = $UniqueRoom['arrival_date'];
                $checkout_at  = $UniqueRoom['departure_date'];
              }
              foreach ($gestdetails as $UniqueRoom){
                $adults[$i]       = $UniqueRoom["numberofguests"];
                $children[$i]     = $UniqueRoom["max_children"];
                $i++;
              }
              $rooms_qty = implode(',', $rm_qty);
              $room_type = implode(',', $rm_type);
              $rate_code = implode(',', $rt_code);
              $no_of_adult = implode(',', $adults);
              $no_of_child = implode(',', $children);
              
              }else{
                $rooms_qty   = 1;
                $room_type   = $reservation_data['room']['id'];
                $checkin_at  = $reservation_data['room']['arrival_date'];
                $checkout_at = $reservation_data['room']['departure_date'];
                $adults       = $reservation_data['room']["numberofguests"];
                $children     = $reservation_data['room']["max_children"];
                $rate_code = implode(',', $rt_code);
                $ex_size=isset($reservation_data['room']['price_details']['hotel']['extracomponent']) && sizeof($reservation_data['room']['price_details']['hotel']['extracomponent']);
                if($ex_size>1)
                {
                  for($e=0;$e<$ex_size;$e++)
                  {
                    if(isset($reservation_data['room']['price_details']['hotel']['extracomponent'][$e]))
                    {
                        $extra_amount   =  $extra_amount+$reservation_data['room']['price_details']['hotel']['extracomponent'][$e]['@attributes']['amount'];
                    }
                  }

                }
                else
                {
                  if(isset($reservation_data['room']['price_details']['hotel']['extracomponent']))
                  {
                      $extra_amount   =  $extra_amount+$reservation_data['room']['price_details']['hotel']['extracomponent']['@attributes']['amount'];
                  }
                }
                }

              $amount         = $amount+$extra_amount;

              $otaBookingUpdateModel = $otaBookingModel->where('unique_id' ,'=', trim($UniqueID))
                                              ->first();

            if($otaBookingUpdateModel){
               return "This New Booking is avaliable in Bookingjini channel manager system";
               
              }else{
               $otaBookingModel->ota_id           = trim($ota_hotel_details->ota_id);
               $otaBookingModel->hotel_id         = trim($ota_hotel_details->hotel_id);
               $otaBookingModel->unique_id        = trim($UniqueID);
               $otaBookingModel->booking_status   = trim($booking_status);
               $otaBookingModel->customer_details = trim($customerDetail);
               $otaBookingModel->rooms_qty        = trim($rooms_qty);
               $otaBookingModel->room_type        = trim($room_type);
               $otaBookingModel->checkin_at       = trim($checkin_at);
               $otaBookingModel->checkout_at      = trim($checkout_at);
               $otaBookingModel->booking_date     = trim($booking_date);
               $otaBookingModel->rate_code        = trim($rate_code);
               $otaBookingModel->amount           = $amount;
               $otaBookingModel->payment_status   = $payment_status;
               $otaBookingModel->response_xml     = trim($result);
               $otaBookingModel->currency         = trim($currency);
               $otaBookingModel->tax_amount       = $extra_amount;
               $otaBookingModel->channel_name     = trim($channel_name);
               $otaBookingModel->no_of_adult      = trim($no_of_adult);
               $otaBookingModel->no_of_child      = trim($no_of_child);
               $otaBookingModel->inclusion        = trim('NA');

               
               if($bookingStatus['newBooking']       = $otaBookingModel->save()){
                $ota_booking_tabel_id = $otaBookingModel->id;
                $this->cmOtaBookingInvStatusService->saveCurrentInvStatus($ota_booking_tabel_id,$ota_hotel_details->ota_id,$ota_hotel_details->hotel_id,$checkin_at,$checkout_at,$room_type);
              }
           }


            if(isset($bookingStatus['newBooking'])){

            $push_by = "booking.com";
            $checking_status = ' ';
            if($booking_status == 'new'){
            $booking_status = 'Commit';
            }
            
            if($booking_status == 'Commit'){
            $checking_status  = $otaBookingModel
              ->where('unique_id', '=', trim($UniqueID))
              ->Where('confirm_status','=', 0)
              ->first();
            }
            
             /*------- Sending Instances to bucket -----------------*/
            if($checking_status){
             $current_ota_details        = $ota_details_model
                                           ->where('hotel_id', '=', $ota_hotel_details->hotel_id)
                                           ->where('ota_id' ,'=', $ota_hotel_details->ota_id)
                                           ->where('is_active' ,'=', 1)
                                           ->first();
             /*-------------------Split for Bookingjini-----------------*/
              $cmOtaBookingPushBucketModel                       = new CmOtaBookingPushBucket();
              $cmOtaBookingPushBucketModel->hotel_id             = $ota_hotel_details->hotel_id;
              $cmOtaBookingPushBucketModel->ota_booking_tabel_id = $ota_booking_tabel_id;
              $cmOtaBookingPushBucketModel->ota_id               = 0;
              $cmOtaBookingPushBucketModel->ota_hotel_code       = 0;
              $cmOtaBookingPushBucketModel->ota_name             = "Bookingjini";
              $cmOtaBookingPushBucketModel->is_update            = 0;
              $cmOtaBookingPushBucketModel->booking_status       = $booking_status;
              $cmOtaBookingPushBucketModel->push_by              = "Booking.com";              
              $cmOtaBookingPushBucketModel->save();

             if($current_ota_details){          
            
             $for_bucket_hotel_details   = $ota_details_model
                                           ->where('hotel_id' ,'=',$ota_hotel_details->hotel_id)
                                           ->where('is_active','=' ,1)
                                           ->get();
             foreach ($for_bucket_hotel_details as $key => $value) {
                
                if($value->ota_id != $ota_hotel_details->ota_id){
              /*--------push request in cm_ota_booking_push_bucket Start--------------*/
              $cmOtaBookingPushBucketModel                       = new CmOtaBookingPushBucket();
              $cmOtaBookingPushBucketModel->hotel_id             = $ota_hotel_details->hotel_id;
              $cmOtaBookingPushBucketModel->ota_booking_tabel_id = $ota_booking_tabel_id;
              $cmOtaBookingPushBucketModel->ota_id               = $value->ota_id;
              $cmOtaBookingPushBucketModel->ota_hotel_code       = $value->ota_hotel_code;
              $cmOtaBookingPushBucketModel->ota_name             = $value->ota_name;
              $cmOtaBookingPushBucketModel->is_update            = 0;
              $cmOtaBookingPushBucketModel->booking_status       = $booking_status;
              $cmOtaBookingPushBucketModel->push_by              = "Booking.com";              
              $cmOtaBookingPushBucketModel->save();
             /*--------push request in cm_ota_booking_push_bucket End-----------------*/
                }
              }
            }            
         }

         /*---------- sending Booking details to Booking Engine -----------*/
           if($booking_status == "Commit"){
              $be_status = 1;
              $actionLog = "newBookingPush";

            }
            if($booking_status == "Modify"){
              $be_status        = 6;
              $actionLog        = "modifyBookingPush";
              $checking_status  = true;


            }
            if($booking_status == "Cancel"){
              $be_status = 3;
              $actionLog = "cancelBookingPush";

            }
          if($booking_status == 'Commit'){
            $checking_status  = $otaBookingModel
              ->where('unique_id' ,'=', trim($UniqueID))
              ->where('confirm_status','=', 0)
              ->first();
              if($checking_status){
                $checking_status->confirm_status = 1;
                $checking_status->save();
              }
            }
           //  Yii::$app->LogComponents->LogPush($actionLog, $checkin_at, $checkout_at, $request_ip, $ota_hotel_details->hotel_id, $room_type, $rate_code, $result, "", 1,'','');
                
      } // is inserting booking data
    } // new booking status closed 

          if($booking_status == "modified"){
           
           //modify bookings
           $UniqueID       = $reservation_data['id'];
           $booking_date   = $reservation_data['date'];

           $customerDetail  = $reservation_data['customer']['first_name']." ".$reservation_data['customer']['last_name'].",".$reservation_data['customer']['email'].",".$reservation_data['customer']['telephone'];
           $amount         = $reservation_data['totalprice'];
           $currency       = $reservation_data['currencycode'];
           
           $payment_status = 'NA';

           $otalog->ota_id = $ota_hotel_details->ota_id;
           $otalog->hotel_id = $ota_hotel_details->hotel_id;
           $otalog->request_msg = $xml;
           $otalog->booking_ref_id = $UniqueID;
           $otalog->response_msg = trim($result);
           $otalog->save();


            /*-----------Fetch Rate Plan------------------*/
           $xml            = simplexml_load_string($result);
           foreach($xml as $room_rate_xml)
           {
            if($room_rate_xml->status=='new')
            {
                $roomRateNode   = $room_rate_xml->children()->room;
            }
           }
           $p=0;
           $extra_amount=0;
           foreach ($roomRateNode as $key => $value) {
             $rt_code[] = $value->children()->price->attributes()->rate_id;
             $p++;
           }
            $rt_code   =  array_unique($rt_code, SORT_REGULAR);;

           $isMultidimensional_rooms = $this->commonService->isMultidimensionalArray($reservation_data['room']);
             
           if($isMultidimensional_rooms){

            $UniqueRooms = $this->commonService->uniqueMultidimArray($reservation_data['room'],'id');
            $gestdetails =  $reservation_data['room'];
           $rta         = array_column($reservation_data['room'], 'id');
           
           $roomarrayidoccurrences = array_count_values($rta);

            for($i=0;$i<$p;$i++)
            {
              if(isset($reservation_data['room'][$i]['price_details']['hotel']['extracomponent']))
              {
                  $ex_size=sizeof($reservation_data['room'][$i]['price_details']['hotel']['extracomponent']);
                  if($ex_size>1)
                  {
                    for($e=0;$e<$ex_size;$e++)
                    {
                      if(isset($reservation_data['room'][$i]['price_details']['hotel']['extracomponent'][$e]))
                      {
                          $extra_amount   =  $extra_amount+$reservation_data['room'][$i]['price_details']['hotel']['extracomponent'][$e]['@attributes']['amount'];
                      }
                    }

                  }
                  else
                  {
                    if(isset($reservation_data['room'][$i]['price_details']['hotel']['extracomponent']))
                    {
                        $extra_amount   =  $extra_amount+$reservation_data['room'][$i]['price_details']['hotel']['extracomponent']['@attributes']['amount'];
                    }
                  }
              }
            }
            $adults=array();
            $children=array();
            $i=0;
           foreach ($UniqueRooms as $UniqueRoom){
             $rm_type[]    = $UniqueRoom['id'];
             $rm_qty[]     = $roomarrayidoccurrences[$UniqueRoom['id']];
             $checkin_at   = $UniqueRoom['arrival_date'];
             $checkout_at  = $UniqueRoom['departure_date'];
            }
            foreach ($gestdetails as $UniqueRoom){
              $adults[$i]       = $UniqueRoom["numberofguests"];
              $children[$i]     = $UniqueRoom["max_children"];
              $i++;
           }
           $rooms_qty = implode(',', $rm_qty);
           $room_type = implode(',', $rm_type);
           $rate_code = implode(',', $rt_code);
           $no_of_adult = implode(',', $adults);
           $no_of_child = implode(',', $children);
           
           }else{
             $rooms_qty   = 1;
             $room_type   = $reservation_data['room']['id'];
             $checkin_at  = $reservation_data['room']['arrival_date'];
             $checkout_at = $reservation_data['room']['departure_date'];
             $adults       = $reservation_data['room']["numberofguests"];
             $children     = $reservation_data['room']["max_children"];
             $rate_code = implode(',', $rt_code);
             $ex_size=isset($reservation_data['room']['price_details']['hotel']['extracomponent']) && sizeof($reservation_data['room']['price_details']['hotel']['extracomponent']);
                if($ex_size>1)
                {
                  for($e=0;$e<$ex_size;$e++)
                  {
                    if(isset($reservation_data['room']['price_details']['hotel']['extracomponent'][$e]))
                    {
                        $extra_amount   =  $extra_amount+$reservation_data['room']['price_details']['hotel']['extracomponent'][$e]['@attributes']['amount'];
                    }
                  }

                }
                else
                {
                  if(isset($reservation_data['room']['price_details']['hotel']['extracomponent']))
                  {
                      $extra_amount   =  $extra_amount+$reservation_data['room']['price_details']['hotel']['extracomponent']['@attributes']['amount'];
                  }
                }
           }

           $amount         = $amount+$extra_amount;

           $otaBookingUpdateModel = $otaBookingModel->where('unique_id' ,'=', trim($UniqueID))
                                            ->where('booking_status' ,'=', 'modified')
                                            ->first();

         if($otaBookingUpdateModel){
            return "This New Booking is avaliable in Bookingjini channel manager system";
            
           }else{
            $otaBookingModel->ota_id           = trim($ota_hotel_details->ota_id);
            $otaBookingModel->hotel_id         = trim($ota_hotel_details->hotel_id);
            $otaBookingModel->unique_id        = trim($UniqueID);
            $otaBookingModel->booking_status   = trim($booking_status);
            $otaBookingModel->customer_details = trim($customerDetail);
            $otaBookingModel->rooms_qty        = trim($rooms_qty);
            $otaBookingModel->room_type        = trim($room_type);
            $otaBookingModel->checkin_at       = trim($checkin_at);
            $otaBookingModel->checkout_at      = trim($checkout_at);
            $otaBookingModel->booking_date     = trim($booking_date);
            $otaBookingModel->rate_code        = trim($rate_code);
            $otaBookingModel->amount           = $amount;
            $otaBookingModel->payment_status   = $payment_status;
            $otaBookingModel->response_xml     = trim($result);
            $otaBookingModel->currency         = trim($currency);
            $otaBookingModel->tax_amount       = $extra_amount;
            $otaBookingModel->channel_name     = trim($channel_name);
            $otaBookingModel->no_of_adult      = trim($no_of_adult);
            $otaBookingModel->no_of_child      = trim($no_of_child);
            $otaBookingModel->inclusion        = trim('NA');
            
            if($bookingStatus['modifyBooking']       = $otaBookingModel->save()){
             $ota_booking_tabel_id = $otaBookingModel->id;
             $this->cmOtaBookingInvStatusService->saveCurrentInvStatus($ota_booking_tabel_id,$ota_hotel_details->ota_id,$ota_hotel_details->hotel_id,$checkin_at,$checkout_at,$room_type);
           }
        }


         if(isset($bookingStatus['modifyBooking'])){

         $push_by = "booking.com";
         $checking_status = ' ';
         if($booking_status == 'modified'){
         $booking_status = 'Modify';
         }
         
         if($booking_status == 'Modify'){
         $checking_status  = $otaBookingModel
           ->where('unique_id', '=', trim($UniqueID))
           ->where('booking_status', '=', 'modified')
           ->Where('co$gestdetailsnfirm_status','=', 0)
           ->first();
         }
         
          /*------- Sending Instances to bucket -----------------*/
         if($checking_status){

          $ota_hotel_code             = $ota_hotel_code;  
          $current_ota_details        = $ota_details_model
                                        ->where('hotel_id', '=', $ota_hotel_details->hotel_id)
                                        ->where('ota_id' ,'=', $ota_hotel_details->ota_id)
                                        ->where('is_active' ,'=', 1)
                                        ->first();
          /*-------------------Split for Bookingjini-----------------*/
           $cmOtaBookingPushBucketModel                       = new CmOtaBookingPushBucket();
           $cmOtaBookingPushBucketModel->hotel_id             = $ota_hotel_details->hotel_id;
           $cmOtaBookingPushBucketModel->ota_booking_tabel_id = $ota_booking_tabel_id;
           $cmOtaBookingPushBucketModel->ota_id               = 0;
           $cmOtaBookingPushBucketModel->ota_hotel_code       = 0;
           $cmOtaBookingPushBucketModel->ota_name             = "Bookingjini";
           $cmOtaBookingPushBucketModel->is_update            = 0;
           $cmOtaBookingPushBucketModel->booking_status       = $booking_status;
           $cmOtaBookingPushBucketModel->push_by              = "Booking.com";              
           $cmOtaBookingPushBucketModel->save();

          if($current_ota_details){          
         
          $for_bucket_hotel_details   = $ota_details_model
                                        ->where('hotel_id' ,'=',$ota_hotel_details->hotel_id)
                                        ->where('is_active','=' ,1)
                                        ->get();
          foreach ($for_bucket_hotel_details as $key => $value) {
             
             if($value->ota_id != $ota_hotel_details->ota_id){
           /*--------push request in cm_ota_booking_push_bucket Start--------------*/
           $cmOtaBookingPushBucketModel                       = new CmOtaBookingPushBucket();
           $cmOtaBookingPushBucketModel->hotel_id             = $ota_hotel_details->hotel_id;
           $cmOtaBookingPushBucketModel->ota_booking_tabel_id = $ota_booking_tabel_id;
           $cmOtaBookingPushBucketModel->ota_id               = $value->ota_id;
           $cmOtaBookingPushBucketModel->ota_hotel_code       = $value->ota_hotel_code;
           $cmOtaBookingPushBucketModel->ota_name             = $value->ota_name;
           $cmOtaBookingPushBucketModel->is_update            = 0;
           $cmOtaBookingPushBucketModel->booking_status       = $booking_status;
           $cmOtaBookingPushBucketModel->push_by              = "Booking.com";              
           $cmOtaBookingPushBucketModel->save();
          /*--------push request in cm_ota_booking_push_bucket End-----------------*/
             }
           }
         }            
      }

      /*---------- sending Booking details to Booking Engine -----------*/
        if($booking_status == "Commit"){
           $be_status = 1;
           $actionLog = "newBookingPush";

         }
         if($booking_status == "Modify"){
           $be_status        = 6;
           $actionLog        = "modifyBookingPush";
           $checking_status  = true;


         }
         if($booking_status == "Cancel"){
           $be_status = 3;
           $actionLog = "cancelBookingPush";

         }
        if($booking_status == 'Modify'){
            $checking_status  = $otaBookingModel
              ->where('unique_id' ,'=', trim($UniqueID))
              ->where('booking_status','=','modified')
              ->where('confirm_status','=', 0)
              ->first();
              if($checking_status){
                $checking_status->confirm_status = 1;
                $checking_status->save();
              }
          }
            
      } // is inserting booking data
        } // modified booking status closed

              if($booking_status == "cancelled"){

              $UniqueID       = $reservation_data['id'];
              $booking_date   = $reservation_data['date'];

              $customerDetail  = $reservation_data['customer']['first_name']." ".$reservation_data['customer']['last_name'].",".$reservation_data['customer']['email'].",".$reservation_data['customer']['telephone'];
              $amount         = $reservation_data['totalprice'];
              $currency       = $reservation_data['currencycode'];
              $payment_status = 'NA';

              $otalog->ota_id = $ota_hotel_details->ota_id;
              $otalog->hotel_id = $ota_hotel_details->hotel_id;
              $otalog->request_msg = $xml;
              $otalog->booking_ref_id = $UniqueID;
              $otalog->response_msg = trim($result);
              $otalog->save();

                
              $otaBookingUpdateModel = $otaBookingModel
                ->where('unique_id' ,'=', trim($UniqueID) )
                ->first();
              /*----------------- Fetch booking values -------------------*/
              $room_type      = $otaBookingUpdateModel->room_type;
              $rooms_qty      = $otaBookingUpdateModel->rooms_qty;
              $rate_code      = $otaBookingUpdateModel->rate_code;
              $amount         = $otaBookingUpdateModel->amount;
              $checkin_at     = $otaBookingUpdateModel->checkin_at ;
              $checkout_at    = $otaBookingUpdateModel->checkout_at;
              $booking_date   = $otaBookingUpdateModel->booking_date;
              $customerDetail = $otaBookingUpdateModel->customerDetail;
             /*----------------- Fetch booking values -------------------*/ 

              if($otaBookingUpdateModel){
               $otaBookingUpdateModel->booking_status   = trim($booking_status);
               $otaBookingUpdateModel->booking_date     = trim($booking_date);
               $otaBookingUpdateModel->amount           = $amount;
               $otaBookingUpdateModel->currency         = $currency;
               
               if($bookingStatus['cancelBooking']       = $otaBookingUpdateModel->save())
               {
                $ota_booking_tabel_id = $otaBookingUpdateModel->id;
                $this->cmOtaBookingInvStatusService->saveCurrentInvStatus($ota_booking_tabel_id,$ota_hotel_details->ota_id,$ota_hotel_details->hotel_id,$checkin_at,$checkout_at,$room_type);
              }
              }

            if(isset($bookingStatus['cancelBooking'])){

            $push_by = "booking.com";
            $checking_status = ' ';
            
            if($booking_status == 'cancelled'){
            $booking_status = 'Cancel';
            } 

            
            if($booking_status == 'Cancel'){
            $checking_status  = $otaBookingModel
              ->where('unique_id' ,'=', trim($UniqueID) )
              ->where('cancel_status','=', 0)
              ->first();
            }
            /*------- Sending Instances to bucket -----------------*/
            if($checking_status){

             $ota_hotel_code             = $otaHotelCode; 

             $current_ota_details        = $ota_details_model
                                           ->where('hotel_id', '=' ,$ota_hotel_details->hotel_id)
                                           ->where('ota_id' ,'=', $ota_hotel_details->ota_id)
                                           ->where('is_active' ,'=' ,1)
                                           ->first();
             /*-------------------Split for Bookingjini-----------------*/
              $cmOtaBookingPushBucketModel                       = new CmOtaBookingPushBucket();
              $cmOtaBookingPushBucketModel->hotel_id             = $ota_hotel_details->hotel_id;
              $cmOtaBookingPushBucketModel->ota_booking_tabel_id = $ota_booking_tabel_id;
              $cmOtaBookingPushBucketModel->ota_id               = 0;
              $cmOtaBookingPushBucketModel->ota_hotel_code       = 0;
              $cmOtaBookingPushBucketModel->ota_name             = "Bookingjini";
              $cmOtaBookingPushBucketModel->is_update            = 0;
              $cmOtaBookingPushBucketModel->booking_status       = $booking_status;
              $cmOtaBookingPushBucketModel->push_by              = "Booking.com";              
              $cmOtaBookingPushBucketModel->save();

             if($current_ota_details){           
            
             $for_bucket_hotel_details   = $ota_details_model
                                           ->where('hotel_id','=' ,$ota_hotel_details->hotel_id)
                                           ->where('is_active','=' ,1)
                                           ->get();
             foreach ($for_bucket_hotel_details as $key => $value) {
                
                if($value->ota_id != $ota_hotel_details->ota_id){
              /*--------push request in cm_ota_booking_push_bucket Start--------------*/
              $cmOtaBookingPushBucketModel                       = new CmOtaBookingPushBucket();
              $cmOtaBookingPushBucketModel->hotel_id             = $ota_hotel_details->hotel_id;
              $cmOtaBookingPushBucketModel->ota_booking_tabel_id = $ota_booking_tabel_id;
              $cmOtaBookingPushBucketModel->ota_id               = $value->ota_id;
              $cmOtaBookingPushBucketModel->ota_hotel_code       = $value->ota_hotel_code;
              $cmOtaBookingPushBucketModel->ota_name             = $value->ota_name;
              $cmOtaBookingPushBucketModel->is_update            = 0;
              $cmOtaBookingPushBucketModel->booking_status       = $booking_status;
              $cmOtaBookingPushBucketModel->push_by              = "Booking.com";              
              $cmOtaBookingPushBucketModel->save();
             /*--------push request in cm_ota_booking_push_bucket End-----------------*/
                }
              }           
            }
          }

         /*---------- sending Booking details to Booking Engine -----------*/
           if($booking_status == "Commit"){
              $be_status = 1;
              $actionLog = "newBookingPush";

            }
            if($booking_status == "Modify"){
              $be_status        = 6;
              $actionLog        = "modifyBookingPush";
              $checking_status  = true;


            }
            if($booking_status == "Cancel"){
              $be_status = 3;
              $actionLog = "cancelBookingPush";

            }

           if($checking_status){
          }

            if($booking_status == 'Cancel'){
            $checking_status  = $otaBookingModel
              ->where('unique_id' ,'=', trim($UniqueID) )
              ->where('cancel_status','=', 0)
              ->first();
              if($checking_status){
                $checking_status->cancel_status = 1;
                $checking_status->save();
                  }
              }        
          } // is inserting booking data
                
              } // cancelled booking status closed




            }else{
              return "This hotel is not exits in Bookingjini Database! Thank you for contat with us.";
        } // else for $ota_hotel_details->hotel_id not exit

          } // for each loop $reservation_datas closed
              
              


            }else{  // if close for multidimentional array check
                $otaBookingModel    = new CmOtaBooking();
              $reservation_datas  = $array_data['reservation'];
              $reservation_data   = $reservation_datas;
              $otaHotelCode       = $reservation_data['hotel_id'];
             
           
              $ota_hotel_details  = $ota_details_model
              ->where('ota_hotel_code' ,'=' ,$otaHotelCode)
              ->first();
              $channel_name   = 'Booking.com';
            if($ota_hotel_details->hotel_id)
            {
              $booking_status = $reservation_data['status'];
              
              if($booking_status == "new"){
               
              $UniqueID       = $reservation_data['id'];
              $booking_date   = $reservation_data['date'];

              $customerDetail  = $reservation_data['customer']['first_name']." ".$reservation_data['customer']['last_name'].",".$reservation_data['customer']['email'].",".$reservation_data['customer']['telephone'];
              $amount         = $reservation_data['totalprice'];
              $currency         = $reservation_data['currencycode'];
              $payment_status = 'NA';

              $otalog->ota_id = $ota_hotel_details->ota_id;
              $otalog->hotel_id = $ota_hotel_details->hotel_id;
              $otalog->request_msg = $xml;
              $otalog->booking_ref_id = $UniqueID;
              $otalog->response_msg = trim($result);
              $otalog->save();


               /*-----------Fetch Rate Plan------------------*/
              $xml            = simplexml_load_string($result);
              $roomRateNode   = $xml->children()->reservation->room;
               $p=0;
               $extra_amount=0;
              foreach ($roomRateNode as $key => $value) {
                $rt_code[] = $value->children()->price->attributes()->rate_id;
                $p++;
              }
               $rt_code   =  array_unique($rt_code, SORT_REGULAR);;

              $isMultidimensional_rooms = $this->commonService->isMultidimensionalArray($reservation_data['room']);
             
              if($isMultidimensional_rooms){
               
              $gestdetails =  $reservation_data['room'];
              $UniqueRooms = $this->commonService->uniqueMultidimArray($reservation_data['room'],'id');
              
            
              $rta         = array_column($reservation_data['room'], 'id');
              
              $roomarrayidoccurrences = array_count_values($rta);
                
             
                for($i=0;$i<$p;$i++)
                {
                  if(isset($reservation_data['room'][$i]['price_details']['hotel']['extracomponent']))
                  {
                  $ex_size=sizeof($reservation_data['room'][$i]['price_details']['hotel']['extracomponent']);
                  if($ex_size>1)
                  {
                    for($e=0;$e<$ex_size;$e++)
                    {
                      if(isset($reservation_data['room'][$i]['price_details']['hotel']['extracomponent'][$e]))
                      {
                          $extra_amount   =  $extra_amount+$reservation_data['room'][$i]['price_details']['hotel']['extracomponent'][$e]['@attributes']['amount'];
                      }
                    }

                  }
                  else
                  {
                    if(isset($reservation_data['room'][$i]['price_details']['hotel']['extracomponent']))
                    {
                        $extra_amount   =  $extra_amount+$reservation_data['room'][$i]['price_details']['hotel']['extracomponent']['@attributes']['amount'];
                    }
                  }
                }
                }
                $adults=array();
                $children=array();
                $i=0;
               
              foreach ($UniqueRooms as $UniqueRoom){
                $rm_type[]    = $UniqueRoom['id'];
                $rm_qty[]     = $roomarrayidoccurrences[$UniqueRoom['id']];
                $checkin_at   = $UniqueRoom['arrival_date'];
                $checkout_at  = $UniqueRoom['departure_date'];
              }
              foreach ($gestdetails as $details){
                $adults[$i]       = $details["numberofguests"];
                $children[$i]     = $details["max_children"];
                $i++;
              }
              $rooms_qty = implode(',', $rm_qty);
              $room_type = implode(',', $rm_type);
              $rate_code = implode(',', $rt_code);
              $no_of_adult = implode(',', $adults);
              $no_of_child = implode(',', $children);
             
             
              
              
              }else{
                
                $rooms_qty   = 1;
                $room_type   = $reservation_data['room']['id'];
                $checkin_at  = $reservation_data['room']['arrival_date'];
                $checkout_at = $reservation_data['room']['departure_date'];
                $adults       = $reservation_data['room']["numberofguests"];
                $children     = $reservation_data['room']["max_children"];
                $rate_code = implode(',', $rt_code);
                if(isset($reservation_data['room']['price_details']['hotel']['extracomponent']))
                {
                $ex_size=sizeof($reservation_data['room']['price_details']['hotel']['extracomponent']);
                if($ex_size>1)
                {
                  for($e=0;$e<$ex_size;$e++)
                  {
                    if(isset($reservation_data['room']['price_details']['hotel']['extracomponent'][$e]))
                    {
                        $extra_amount   =  $extra_amount+$reservation_data['room']['price_details']['hotel']['extracomponent'][$e]['@attributes']['amount'];
                    }
                  }

                }
                else
                {
                  if(isset($reservation_data['room']['price_details']['hotel']['extracomponent']))
                  {
                      $extra_amount   =  $extra_amount+$reservation_data['room']['price_details']['hotel']['extracomponent']['@attributes']['amount'];
                  }
                }
              }
            }
              $amount         = $amount+$extra_amount;

              $otaBookingUpdateModel = $otaBookingModel
              ->where('unique_id','=', trim($UniqueID) )
              ->first();

            if($otaBookingUpdateModel){
               return "This New Booking is avaliable in Bookingjini channel manager system";
               
              }else{
               $otaBookingModel->ota_id           = trim($ota_hotel_details->ota_id);
               $otaBookingModel->hotel_id         = trim($ota_hotel_details->hotel_id);
               $otaBookingModel->unique_id        = trim($UniqueID);
               $otaBookingModel->booking_status   = trim($booking_status);
               $otaBookingModel->customer_details = trim($customerDetail);
               $otaBookingModel->rooms_qty        = trim($rooms_qty);
               $otaBookingModel->room_type        = trim($room_type);
               $otaBookingModel->checkin_at       = trim($checkin_at);
               $otaBookingModel->checkout_at      = trim($checkout_at);
               $otaBookingModel->booking_date     = trim($booking_date);
               $otaBookingModel->rate_code        = trim($rate_code);
               $otaBookingModel->amount           = $amount;
               $otaBookingModel->payment_status   = $payment_status;
               $otaBookingModel->response_xml     = trim($result);
               $otaBookingModel->currency         = trim($currency);
               $otaBookingModel->tax_amount       = $extra_amount;
               $otaBookingModel->channel_name     = trim($channel_name);
               $otaBookingModel->no_of_adult      = trim($no_of_adult);
               $otaBookingModel->no_of_child      = trim($no_of_child);
               $otaBookingModel->inclusion        = trim('NA');
               
               if($bookingStatus['newBooking']       = $otaBookingModel->save()){
              $ota_booking_tabel_id = $otaBookingModel->id;
              
              $this->cmOtaBookingInvStatusService->saveCurrentInvStatus($ota_booking_tabel_id,$ota_hotel_details->ota_id,$ota_hotel_details->hotel_id,$checkin_at,$checkout_at,$room_type);
            }
           }


            if(isset($bookingStatus['newBooking'])){

            $push_by = "booking.com";
            $checking_status = ' ';
            if($booking_status == 'new'){
            $booking_status = 'Commit';
            }
            
            if($booking_status == 'Commit'){
              $checking_status  = $otaBookingModel
                ->where('unique_id' ,'=' ,trim($UniqueID))
                ->where('confirm_status','=',0)
                ->first();
            }
            
             /*------- Sending Instances to bucket -----------------*/
            if($checking_status){

             $ota_hotel_code             = $otaHotelCode;
             
             $current_ota_details        = $ota_details_model
                                           ->where('hotel_id' ,'=', $ota_hotel_details->hotel_id)
                                           ->where('ota_id' ,'=', $ota_hotel_details->ota_id)
                                           ->where('is_active' ,'=', 1)
                                           ->first();
             /*-------------------Split for Bookingjini-----------------*/
              $cmOtaBookingPushBucketModel                       = new CmOtaBookingPushBucket();
              $cmOtaBookingPushBucketModel->hotel_id             = $ota_hotel_details->hotel_id;
              $cmOtaBookingPushBucketModel->ota_booking_tabel_id = $ota_booking_tabel_id;
              $cmOtaBookingPushBucketModel->ota_id               = 0;
              $cmOtaBookingPushBucketModel->ota_hotel_code       = 0;
              $cmOtaBookingPushBucketModel->ota_name             = "Bookingjini";
              $cmOtaBookingPushBucketModel->is_update            = 0;
              $cmOtaBookingPushBucketModel->booking_status       = $booking_status;
              $cmOtaBookingPushBucketModel->push_by              = "Booking.com";        
              $cmOtaBookingPushBucketModel->save();
             if($current_ota_details){          
            
             $for_bucket_hotel_details   = $ota_details_model
                                           ->where('hotel_id' ,'=', $ota_hotel_details->hotel_id)
                                           ->where('is_active' ,'=', 1)
                                           ->get();
             foreach ($for_bucket_hotel_details as $key => $value) {
               
                if($value->ota_id != $ota_hotel_details->ota_id){
              /*--------push request in cm_ota_booking_push_bucket Start--------------*/
              $cmOtaBookingPushBucketModel                       = new CmOtaBookingPushBucket();
              $cmOtaBookingPushBucketModel->hotel_id             = $ota_hotel_details->hotel_id;
              $cmOtaBookingPushBucketModel->ota_booking_tabel_id = $ota_booking_tabel_id;
              $cmOtaBookingPushBucketModel->ota_id               = $value->ota_id;
              $cmOtaBookingPushBucketModel->ota_hotel_code       = $value->ota_hotel_code;
              $cmOtaBookingPushBucketModel->ota_name             = $value->ota_name;
              $cmOtaBookingPushBucketModel->is_update            = 0;
              $cmOtaBookingPushBucketModel->booking_status       = $booking_status;
              $cmOtaBookingPushBucketModel->push_by              = "Booking.com";              
              $cmOtaBookingPushBucketModel->save();
             /*--------push request in cm_ota_booking_push_bucket End-----------------*/
                }
              }
            }            
         }

         /*---------- sending Booking details to Booking Engine -----------*/
           if($booking_status == "Commit"){
              $be_status = 1;
              $actionLog = "newBookingPush";

            }
            if($booking_status == "Modify"){
              $be_status        = 6;
              $actionLog        = "modifyBookingPush";
              $checking_status  = true;


            }
            if($booking_status == "Cancel"){
              $be_status = 3;
              $actionLog = "cancelBookingPush";

            }

           if($checking_status){

           // Yii::$app->OtaBookingPushComponents->bookingPush($ota_hotel_details->hotel_id,$room_type,$rooms_qty,$rate_code,$amount,$amount, $checkin_at, $checkout_at,$booking_date,$be_status,'',$request_ip,$customerDetail,$booking_date);
          }
          if($booking_status == 'Commit'){
            $checking_status  = $otaBookingModel
              ->where('unique_id' ,'=' ,trim($UniqueID) )
              ->where('confirm_status','=' , 0)
              ->first();
              if($checking_status){
                $checking_status->confirm_status = 1;
                $checking_status->save();
              }
            }
           //  Yii::$app->LogComponents->LogPush($actionLog, $checkin_at, $checkout_at, $request_ip, $ota_hotel_details->hotel_id, $room_type, $rate_code, $result, "", 1,'','');
                
      } // is inserting booking data
    } // new booking status closed 

          if($booking_status == "modified"){
           //Modify booking
           $UniqueID       = $reservation_data['id'];
           $booking_date   = $reservation_data['date'];

           $customerDetail  = $reservation_data['customer']['first_name']." ".$reservation_data['customer']['last_name'].",".$reservation_data['customer']['email'].",".$reservation_data['customer']['telephone'];
           $amount         = $reservation_data['totalprice'];
           $currency         = $reservation_data['currencycode'];
           $payment_status = 'NA';

           $otalog->ota_id = $ota_hotel_details->ota_id;
           $otalog->hotel_id = $ota_hotel_details->hotel_id;
           $otalog->request_msg = $xml;
           $otalog->booking_ref_id = $UniqueID;
           $otalog->response_msg = trim($result);
           $otalog->save();


            /*-----------Fetch Rate Plan------------------*/
           $xml            = simplexml_load_string($result);
           $roomRateNode   = $xml->children()->reservation->room;
            $p=0;
            $extra_amount=0;
           foreach ($roomRateNode as $key => $value) {
             $rt_code[] = $value->children()->price->attributes()->rate_id;
             $p++;
           }
            $rt_code   =  array_unique($rt_code, SORT_REGULAR);;

           $isMultidimensional_rooms = $this->commonService->isMultidimensionalArray($reservation_data['room']);
          
           if($isMultidimensional_rooms){

            $UniqueRooms = $this->commonService->uniqueMultidimArray($reservation_data['room'],'id');
            $gestdetails =  $reservation_data['room'];
           $rta         = array_column($reservation_data['room'], 'id');
           
           $roomarrayidoccurrences = array_count_values($rta);

            for($i=0;$i<$p;$i++)
            {
              $ex_size=sizeof($reservation_data['room'][$i]['price_details']['hotel']['extracomponent']);
              if($ex_size>1)
              {
                for($e=0;$e<$ex_size;$e++)
                {
                  if(isset($reservation_data['room'][$i]['price_details']['hotel']['extracomponent'][$e]))
                  {
                      $extra_amount   =  $extra_amount+$reservation_data['room'][$i]['price_details']['hotel']['extracomponent'][$e]['@attributes']['amount'];
                  }
                }

              }
              else
              {
                if(isset($reservation_data['room'][$i]['price_details']['hotel']['extracomponent']))
                {
                    $extra_amount   =  $extra_amount+$reservation_data['room'][$i]['price_details']['hotel']['extracomponent']['@attributes']['amount'];
                }
              }
            }
            $adults=array();
            $children=array();
            $i=0;
           foreach ($UniqueRooms as $UniqueRoom){
             $rm_type[]    = $UniqueRoom['id'];
             $rm_qty[]     = $roomarrayidoccurrences[$UniqueRoom['id']];
             $checkin_at   = $UniqueRoom['arrival_date'];
             $checkout_at  = $UniqueRoom['departure_date'];
            }
            foreach ($gestdetails as $UniqueRoom){
              $adults[$i]       = $UniqueRoom["numberofguests"];
              $children[$i]     = $UniqueRoom["max_children"];
              $i++;
           }
           $rooms_qty = implode(',', $rm_qty);
           $room_type = implode(',', $rm_type);
           $rate_code = implode(',', $rt_code);
           $no_of_adult = implode(',', $adults);
           $no_of_child = implode(',', $children);
           
           
           }else{
             $rooms_qty   = 1;
             $room_type   = $reservation_data['room']['id'];
             $checkin_at  = $reservation_data['room']['arrival_date'];
             $checkout_at = $reservation_data['room']['departure_date'];
             $adults       = $reservation_data['room']["numberofguests"];
             $children     = $reservation_data['room']["max_children"];
             $rate_code = implode(',', $rt_code);
             $ex_size=isset($reservation_data['room']['price_details']['hotel']['extracomponent']) && sizeof($reservation_data['room']['price_details']['hotel']['extracomponent']);
                if($ex_size>1)
                {
                  for($e=0;$e<$ex_size;$e++)
                  {
                    if(isset($reservation_data['room']['price_details']['hotel']['extracomponent'][$e]))
                    {
                        $extra_amount   =  $extra_amount+$reservation_data['room']['price_details']['hotel']['extracomponent'][$e]['@attributes']['amount'];
                    }
                  }

                }
                else
                {
                  if(isset($reservation_data['room']['price_details']['hotel']['extracomponent']))
                  {
                      $extra_amount   =  $extra_amount+$reservation_data['room']['price_details']['hotel']['extracomponent']['@attributes']['amount'];
                  }
                }
           }

           $amount         = $amount+$extra_amount;

           $otaBookingUpdateModel = $otaBookingModel
           ->where('unique_id','=', trim($UniqueID) )
           ->where('booking_status','=', 'modified' )
           ->first();

         if($otaBookingUpdateModel){
            return "This New Booking is avaliable in Bookingjini channel manager system";
            
           }else{
            $otaBookingModel->ota_id           = trim($ota_hotel_details->ota_id);
            $otaBookingModel->hotel_id         = trim($ota_hotel_details->hotel_id);
            $otaBookingModel->unique_id        = trim($UniqueID);
            $otaBookingModel->booking_status   = trim($booking_status);
            $otaBookingModel->customer_details = trim($customerDetail);
            $otaBookingModel->rooms_qty        = trim($rooms_qty);
            $otaBookingModel->room_type        = trim($room_type);
            $otaBookingModel->checkin_at       = trim($checkin_at);
            $otaBookingModel->checkout_at      = trim($checkout_at);
            $otaBookingModel->booking_date     = trim($booking_date);
            $otaBookingModel->rate_code        = trim($rate_code);
            $otaBookingModel->amount           = $amount;
            $otaBookingModel->tax_amount       = $extra_amount;
            $otaBookingModel->payment_status   = $payment_status;
            $otaBookingModel->response_xml     = trim($result);
            $otaBookingModel->currency         = trim($currency);
            $otaBookingModel->channel_name     = trim($channel_name);
            $otaBookingModel->no_of_adult      = trim($no_of_adult);
            $otaBookingModel->no_of_child      = trim($no_of_child);
            $otaBookingModel->inclusion        = trim('NA');
            
            if($bookingStatus['modifyBooking']       = $otaBookingModel->save()){
           $ota_booking_tabel_id = $otaBookingModel->id;
           $this->cmOtaBookingInvStatusService->saveCurrentInvStatus($ota_booking_tabel_id,$ota_hotel_details->ota_id,$ota_hotel_details->hotel_id,$checkin_at,$checkout_at,$room_type);
         }
        }


         if(isset($bookingStatus['modifyBooking']))
         {

                  $push_by = "booking.com";
                  $checking_status = ' ';
                  if($booking_status == 'modified'){
                  $booking_status = 'Modify';
                  }
                  
                  if($booking_status == 'Modify'){
                    $checking_status  = $otaBookingModel
                      ->where('unique_id' ,'=' ,trim($UniqueID))
                      ->where('booking_status','=','modified')
                      ->where('confirm_status','=',0)
                      ->first();
                  }
                  
                    /*------- Sending Instances to bucket -----------------*/
                  if($checking_status){

                    $ota_hotel_code             = $otaHotelCode;
                    
                    $current_ota_details        = $ota_details_model
                                                  ->where('hotel_id' ,'=', $ota_hotel_details->hotel_id)
                                                  ->where('ota_id' ,'=', $ota_hotel_details->ota_id)
                                                  ->where('is_active' ,'=', 1)
                                                  ->first();
                    /*-------------------Split for Bookingjini-----------------*/
                    $cmOtaBookingPushBucketModel                       = new CmOtaBookingPushBucket();
                    $cmOtaBookingPushBucketModel->hotel_id             = $ota_hotel_details->hotel_id;
                    $cmOtaBookingPushBucketModel->ota_booking_tabel_id = $ota_booking_tabel_id;
                    $cmOtaBookingPushBucketModel->ota_id               = 0;
                    $cmOtaBookingPushBucketModel->ota_hotel_code       = 0;
                    $cmOtaBookingPushBucketModel->ota_name             = "Bookingjini";
                    $cmOtaBookingPushBucketModel->is_update            = 0;
                    $cmOtaBookingPushBucketModel->booking_status       = $booking_status;
                    $cmOtaBookingPushBucketModel->push_by              = "Booking.com";        
                    $cmOtaBookingPushBucketModel->save();
                    if($current_ota_details){          
                  
                    $for_bucket_hotel_details   = $ota_details_model
                                                  ->where('hotel_id' ,'=', $ota_hotel_details->hotel_id)
                                                  ->where('is_active' ,'=', 1)
                                                  ->get();
                    foreach ($for_bucket_hotel_details as $key => $value) {
                      
                      if($value->ota_id != $ota_hotel_details->ota_id){
                    /*--------push request in cm_ota_booking_push_bucket Start--------------*/
                    $cmOtaBookingPushBucketModel                       = new CmOtaBookingPushBucket();
                    $cmOtaBookingPushBucketModel->hotel_id             = $ota_hotel_details->hotel_id;
                    $cmOtaBookingPushBucketModel->ota_booking_tabel_id = $ota_booking_tabel_id;
                    $cmOtaBookingPushBucketModel->ota_id               = $value->ota_id;
                    $cmOtaBookingPushBucketModel->ota_hotel_code       = $value->ota_hotel_code;
                    $cmOtaBookingPushBucketModel->ota_name             = $value->ota_name;
                    $cmOtaBookingPushBucketModel->is_update            = 0;
                    $cmOtaBookingPushBucketModel->booking_status       = $booking_status;
                    $cmOtaBookingPushBucketModel->push_by              = "Booking.com";              
                    $cmOtaBookingPushBucketModel->save();
                    /*--------push request in cm_ota_booking_push_bucket End-----------------*/
                      }
                    }
                  }            
              }

              /*---------- sending Booking details to Booking Engine -----------*/
                if($booking_status == "Commit"){
                  $be_status = 1;
                  $actionLog = "newBookingPush";

                }
                if($booking_status == "Modify"){
                  $be_status        = 6;
                  $actionLog        = "modifyBookingPush";
                  $checking_status  = true;


                }
                if($booking_status == "Cancel"){
                  $be_status = 3;
                  $actionLog = "cancelBookingPush";

                }
              if($booking_status == 'Modfify'){
                $checking_status  = $otaBookingModel
                  ->where('unique_id' ,'=' ,trim($UniqueID) )
                  ->where('booking_status' ,'=' ,'modified')
                  ->where('confirm_status','=' , 0)
                  ->first();
                  if($checking_status){
                    $checking_status->confirm_status = 1;
                    $checking_status->save();
                  }
                }
                    
          } // is inserting modified booking data          
        } // modified booking status closed

              if($booking_status == "cancelled"){

              $UniqueID       = $reservation_data['id'];
              $booking_date   = $reservation_data['date'];

              $customerDetail  = $reservation_data['customer']['first_name']." ".$reservation_data['customer']['last_name'].",".$reservation_data['customer']['email'].",".$reservation_data['customer']['telephone'];
              $amount         = $reservation_data['totalprice'];
              $currency         = $reservation_data['currencycode'];
              $payment_status = 'NA';

              $otalog->ota_id = $ota_hotel_details->ota_id;
              $otalog->hotel_id = $ota_hotel_details->hotel_id;
              $otalog->request_msg = $xml;
              $otalog->booking_ref_id = $UniqueID;
              $otalog->response_msg = trim($result);
              $otalog->save();

                
              $otaBookingUpdateModel = $otaBookingModel
              ->where('unique_id' ,'=', trim($UniqueID) )
              ->first();
              /*----------------- Fetch booking values -------------------*/
              $room_type      = $otaBookingUpdateModel->room_type;
              $rooms_qty      = $otaBookingUpdateModel->rooms_qty;
              $rate_code      = $otaBookingUpdateModel->rate_code;
              $amount         = $otaBookingUpdateModel->amount;
              $checkin_at     = $otaBookingUpdateModel->checkin_at ;
              $checkout_at    = $otaBookingUpdateModel->checkout_at;
              $booking_date   = $otaBookingUpdateModel->booking_date;
              $customerDetail = $otaBookingUpdateModel->customerDetail;
             /*----------------- Fetch booking values -------------------*/ 

              if($otaBookingUpdateModel){
               $otaBookingUpdateModel->booking_status   = trim($booking_status);
               $otaBookingUpdateModel->booking_date     = trim($booking_date);
               $otaBookingUpdateModel->amount           = $amount;
               $otaBookingUpdateModel->currency           = $currency;
               
               if($bookingStatus['cancelBooking']       = $otaBookingUpdateModel->save()){
              $ota_booking_tabel_id = $otaBookingUpdateModel->id;
              $this->cmOtaBookingInvStatusService->saveCurrentInvStatus($ota_booking_tabel_id,$ota_hotel_details->ota_id,$ota_hotel_details->hotel_id,$checkin_at,$checkout_at,$room_type);
            }
              }

            if(isset($bookingStatus['cancelBooking'])){

            $push_by = "booking.com";
            $checking_status = ' ';
            
            if($booking_status == 'cancelled'){
            $booking_status = 'Cancel';
            } 

            
            if($booking_status == 'Cancel'){
            $checking_status  = $otaBookingModel
              ->where('unique_id' ,'=', trim($UniqueID))
              ->where('cancel_status','=', 0)
              ->first();
            }
            /*------- Sending Instances to bucket -----------------*/
            if($checking_status){

             $ota_hotel_code             = $otaHotelCode; 

             $current_ota_details        = $ota_details_model
                                           ->where('hotel_id' ,'=', $ota_hotel_details->hotel_id)
                                           ->where('ota_id','=', $ota_hotel_details->ota_id)
                                           ->where('is_active' ,'=', 1)
                                           ->first();
             /*-------------------Split for Bookingjini-----------------*/
              $cmOtaBookingPushBucketModel                       = new CmOtaBookingPushBucket();
              $cmOtaBookingPushBucketModel->hotel_id             = $ota_hotel_details->hotel_id;
              $cmOtaBookingPushBucketModel->ota_booking_tabel_id = $ota_booking_tabel_id;
              $cmOtaBookingPushBucketModel->ota_id               = 0;
              $cmOtaBookingPushBucketModel->ota_hotel_code       = 0;
              $cmOtaBookingPushBucketModel->ota_name             = "Bookingjini";
              $cmOtaBookingPushBucketModel->is_update            = 0;
              $cmOtaBookingPushBucketModel->booking_status       = $booking_status;
              $cmOtaBookingPushBucketModel->push_by              = "Booking.com";              
              $cmOtaBookingPushBucketModel->save();

             if($current_ota_details){           
            
             $for_bucket_hotel_details   = $ota_details_model
                                           ->where('hotel_id' ,'=',$ota_hotel_details->hotel_id)
                                           ->where('is_active' ,'=', 1)
                                           ->get();
             foreach ($for_bucket_hotel_details as $key => $value) {
                
                if($value->ota_id != $ota_hotel_details->ota_id){
              /*--------push request in cm_ota_booking_push_bucket Start--------------*/
              $cmOtaBookingPushBucketModel                       = new CmOtaBookingPushBucket();
              $cmOtaBookingPushBucketModel->hotel_id             = $ota_hotel_details->hotel_id;
              $cmOtaBookingPushBucketModel->ota_booking_tabel_id = $ota_booking_tabel_id;
              $cmOtaBookingPushBucketModel->ota_id               = $value->ota_id;
              $cmOtaBookingPushBucketModel->ota_hotel_code       = $value->ota_hotel_code;
              $cmOtaBookingPushBucketModel->ota_name             = $value->ota_name;
              $cmOtaBookingPushBucketModel->is_update            = 0;
              $cmOtaBookingPushBucketModel->booking_status       = $booking_status;
              $cmOtaBookingPushBucketModel->push_by              = "Booking.com";              
              $cmOtaBookingPushBucketModel->save();
             /*--------push request in cm_ota_booking_push_bucket End-----------------*/
                }
              }           
            }
          }

         /*---------- sending Booking details to Booking Engine -----------*/
           if($booking_status == "Commit"){
              $be_status = 1;
              $actionLog = "newBookingPush";

            }
            if($booking_status == "Modify"){
              $be_status        = 6;
              $actionLog        = "modifyBookingPush";
              $checking_status  = true;


            }
            if($booking_status == "Cancel"){
              $be_status = 3;
              $actionLog = "cancelBookingPush";

            }

           if($checking_status){

           // Yii::$app->OtaBookingPushComponents->bookingPush($ota_hotel_details->hotel_id,$room_type,$rooms_qty,$rate_code,$amount,$amount, $checkin_at, $checkout_at,$booking_date,$be_status,'',$request_ip,$customerDetail,$booking_date);
          }

            if($booking_status == 'Cancel'){
            $checking_status  = $otaBookingModel
              ->where('unique_id' ,'=',trim($UniqueID))
              ->where('cancel_status','=', 0)
              ->first();
              if($checking_status){
                $checking_status->cancel_status = 1;
                $checking_status->save();
                  }
              }        
        } // is inserting booking data
                
              } // cancelled booking status closed




            }else{
              return "This hotel is not exits in Bookingjini Database! Thank you for contat with us.";
        } // else for $ota_hotel_details->hotel_id not exit


            } // else close for multidimentional array check 
            

              

        } // if close isset $array_data['reservation'] avaliable or not
        else{
          echo "No Reservation <br>";

        } // else close isset $array_data['reservation'] avaliable or not

    } // index action closed.


    public function noShowPush(Request $request)
    {
      $logModel = new LogTable();
      $data=$request->all();
      $inventory_client_ip=$this->ipService->getIPAddress();
      $user_id  = $request->auth->admin_id;
      $ota_details_data             	= CmOtaDetails::select('*')
      ->where('hotel_id', '=' ,$data['hotel_id'])
      ->where('ota_id', '=' ,$data['ota_id'])
      ->first(); 	
      $commonUrl      				= $ota_details_data->url;

      $log_data               	= [
        "action_id"          => 4,
        "hotel_id"           => $data['hotel_id'],
        "ota_id"      		 => $data['ota_id'],
        "booking_ref_id"     => '',
        "inventory_ref_id"   => $data['inventory_ref_id'],
        "rate_ref_id"        => '',
        "user_id"            => $user_id,
        "request_msg"        => '',
        "response_msg"       => '',
        "request_url"        => '',
        "status"         	 => 2,
        "ip"         		 => $inventory_client_ip,
        "comment"			 => "Processing for update "
        ];

        $headers = array (
          'Content-Type: application/xml'
          );
      
      $xml          = '<request>
      <username>Bookingjini-channelmanager</username>
      <password>wSznWO?2wy/^-j/hfUK^MCq?:A*EK)BBXSMK-.*)</password>
      <reservation_id>'.$data['unique_id'].'</reservation_id>
      <report waived_fees="yes">is_no_show</report>
      </request>';
			$log_request_msg = $xml;
			$url         	 = $commonUrl.'reporting';
			$logModel->fill($log_data)->save();
			$ch = curl_init();
			curl_setopt( $ch, CURLOPT_URL, $url );
			curl_setopt( $ch, CURLOPT_POST, true );
			curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml);
			$result = curl_exec($ch);
      curl_close($ch);
      
      $resultXml=simplexml_load_string($result);
      $array=json_decode(json_encode((array)$resultXml), TRUE);
			if($resultXml){
				if(strpos($result, '<status>' ) !== false){
					DB::table('log_table')->where('id', $logModel->id)
					->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
				  return response()->json(array('status'=>1,'message'=>"No show successful!"));	
				}
				else if(strpos($result, '<reporting>' ) !== false){
					DB::table('log_table')->where('id', $logModel->id)
          ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
          $zero=0;
          return response()->json(array('status'=>0,'message'=>$array['fault']['string']));	
				}
    }
  }

} // class closed.