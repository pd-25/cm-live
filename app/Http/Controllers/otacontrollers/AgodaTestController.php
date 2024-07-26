<?php
namespace App\Http\Controllers\otacontrollers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Input;
use Validator;
use DB;
use App\CmOtaDetailsRead;
use App\CmOtaAllAutoPush;
use App\CmOtaBookingPushBucket;
use App\Http\Controllers\Controller;
use App\Http\Controllers\otacontrollers\BookingDataInsertationController;
use App\Http\Controllers\otacontrollers\InstantBucketController;
use App\Http\Controllers\otacontrollers\CurlController;
/**
* AgodaController implements booking for AgodaController model.
* Modified by ranjit
* @24/01/19
*/
class AgodaTestController extends Controller
{
    protected $bookingData,$curlcall,$instantbucket;
    public function __construct(BookingDataInsertationController $bookingData,CurlController $curlcall,InstantBucketController $instantbucket)
    {
      $this->bookingData=$bookingData;
      $this->curlcall=$curlcall;
      $this->instantbucket=$instantbucket;
    }
    public function actionIndex(Request $request)
    {
      $OtaAllAutoPushModel          = new CmOtaAllAutoPush();
      $request_ip                   = $_SERVER['REMOTE_ADDR'];

      $ota_details_model            = new CmOtaDetailsRead();
      $ota_details_datas            = $ota_details_model
                                        ->where('ota_name' ,'=', 'Agoda')
                                        ->where('is_active' ,'=', 1)
                                        ->get();

      $total_data=sizeof($ota_details_datas);
      $percentile_data=$total_data % 5;
      $total_data=$total_data-$percentile_data;
      $ota_five_chunk=$total_data/5;
      $arr_five_ota_data=array();
      $ota_data_arr=array();
      $j=0;
      for($i=1;$i<=$total_data;$i++)
      {
        array_push($arr_five_ota_data,$ota_details_datas[$i-1]);
        if($i >=5 && $i%5==0)
        {
          if($j<$ota_five_chunk)
          {
            $ota_data_arr[$j]=$arr_five_ota_data;
            $j++;
            unset( $arr_five_ota_data); // $arr_five_ota_data is gone
            $arr_five_ota_data = array(); // $arr_five_ota_data is here again
          }
        }
      }
      if($percentile_data>0)
      {
        for($x=sizeof($ota_details_datas);$x>sizeof($ota_details_datas)-$percentile_data;$x--)
        {
          array_push($arr_five_ota_data,$ota_details_datas[$x-1]);
        }
        $ota_data_arr[$j]=$arr_five_ota_data;
      }
      foreach($ota_data_arr as $ota_details_data) {

      $auth_parameter               = json_decode($ota_details_data[0]->auth_parameter);
      $apiKey                       = trim($auth_parameter->apiKey);
      $date                         = new \DateTime();
      $dateTimestamp                = $date->getTimestamp();

      $headers = array (
      'Content-Type: application/xml',
      );

      $fromDate  = date("Y-m-d");
      $toDate    = date("Y-m-d", strtotime('+1 days'));

      $bookinglist_xml ='<?xml version="1.0" encoding="UTF-8"?>
      <request timestamp="'.$dateTimestamp.'"  type="3">
      <criteria from="'.$fromDate.'T00:00:00+05:30"  to="'.$toDate.'T00:00:00+05:30" >';
      foreach($ota_details_data as $ota_data) {
        $bookinglist_xml.='<property id="'.$ota_data->ota_hotel_code.'"/>';
      }
      $bookinglist_xml.='</criteria>
      </request>';
      // $bookinglist_url = 'https://supply.agoda.com/api?apiKey='.$apiKey;
      // $ch = curl_init();
      // curl_setopt( $ch, CURLOPT_URL, $bookinglist_url );
      // curl_setopt( $ch, CURLOPT_POST, true );
      // curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
      // curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
      // curl_setopt( $ch, CURLOPT_POSTFIELDS, $bookinglist_xml);
      // $bookinglist_result = curl_exec($ch);
      // curl_close($ch);
      // $bookinglist_array_data = json_decode(json_encode(simplexml_load_string($bookinglist_result)), true);
      // $booking_id=[];
      //For single booking booking
      // if($bookinglist_array_data['properties']['@attributes']['count']==1)
      // {
      //   $bookinglist_array_data['properties']['property']=array($bookinglist_array_data['properties']['property']);
      // }
      /*---------- Checking Booking Avilabe or not ------------- */
      // $bookingDetails_xml="";
      if(1)
      {
          // $propertylist=$bookinglist_array_data['properties']['property'];
          // foreach ($propertylist as $key => $property)
          // {
            // if(isset($property['@attributes']['id']))
            // {
            //   if(isset($property['bookings']['booking']))
            //   {
            //     $bookinglist = $property['bookings']['booking'];
            //     $bookinglist_count=count($bookinglist);
            //     if($bookinglist_count>0)
            //     {
            //       if($bookinglist_count >1)
            //       {
            //         foreach ($bookinglist as $k => $v) {
            //             $booking_id[] = $v['@attributes']['booking_id'];
            //           }
            //       }
            //       else
            //       {
            //           $booking_id[] = $bookinglist['@attributes']['booking_id'];
            //
            //       }


          //     }
          //   }
          // }
          if(1)
          {

            // $bookingDetails_url = 'https://supply.agoda.com/api?apiKey='.$apiKey;
            // $bookingDetails = $this->curlcall->curlRequest($bookingDetails_url,$headers,$bookingDetails_xml);//used for cURL request
            $bookingDetails_array_datas= '<result timestamp="1617688774433">
            <bookings count="1" iataid="96637800">
            <booking property_id="16084521" property_name="Hotel Shangri-La Regency" booking_id="578760184" booking_date="2021-03-14T21:51:00+07:00" last_action="2021-04-05T22:31:58+07:00" arrival="2021-04-10" departure="2021-04-12" status="CancelBooking" acknowledge="0" room_id="203759279" room_type="Deluxe Twin" channel_id="1" channel_name="Retail" rateplan_id="6049948" rateplan_name="Breakfast" promotion_id="166124605" promotion_name="Limited time offer. Price includes 10% discount!" room_count="2" adults="4" children="0" extrabeds="0" cxl_code="2D1N_1D100P_100P">
                  
                  <customer first_name="GOPINATH" last_name="CHENGALPATTU RAJAMANOHARAN" nationality="India"/>
                  <benefits>
                        <benefit benefit_id="1" benefit_name="Breakfast"/>
                      </benefits>
                  <requests>
                        <request request_name="NonSmoke"/><request request_name="TwinBeds"/><request request_name="EarlyCheckIn"/><request request_name="HighFloor"/>
                      </requests>
                  
                  <prices currency="INR" net_inclusive_amt="0.0">
                    <price date="" net_inclusive_amt="0.0" type="Confirmation"/>
                  </prices>
                  
                  
                  
                </booking>
          </bookings>
          </result>';
                // $bookingDetails_array_datas = ($bookingDetails_array_datas);
                $bookingDetails_array_datas = json_decode(json_encode(simplexml_load_string($bookingDetails_array_datas)), true);
            // $rlt= $bookingDetails['rlt'];
            // $OtaAllAutoPushModel->respones_xml = trim($rlt);
            // $OtaAllAutoPushModel->save();
            if($bookingDetails_array_datas['bookings']['@attributes']['count']==1)
            {
              $bookingDetails_array_datas['bookings']['booking']=array($bookingDetails_array_datas['bookings']['booking']);
            }
            if(isset($bookingDetails_array_datas['bookings']['booking']))
            {
              foreach ($bookingDetails_array_datas['bookings']['booking'] as $key => $bookingDetails_data)
              {
                if(isset($bookingDetails_data))
                {
                  $uniqueID =$bookingDetails_data['@attributes']['booking_id'];
                  $hotel_Code = $bookingDetails_data['@attributes']['property_id'];
                  $booking_status = $bookingDetails_data['@attributes']['status'];
                  $rooms_qty = $bookingDetails_data['@attributes']['room_count'];
                  $no_of_adult = $bookingDetails_data['@attributes']['adults'];
                  $no_of_child = $bookingDetails_data['@attributes']['children'];
                  $room_type = $bookingDetails_data['@attributes']['room_id'];
                  $checkin_at = $bookingDetails_data['@attributes']['arrival'];
                  $checkout_at = $bookingDetails_data['@attributes']['departure'];
                  $booking_date =  date('Y-m-d H:i:s',strtotime($bookingDetails_data['@attributes']['booking_date']));
                  $rate_code = $bookingDetails_data['@attributes']['rateplan_id'];
                  if(isset($bookingDetails_data['customer']['@attributes']['email']) && isset($bookingDetails_data['customer']['@attributes']['phone']))
                  {
                    $customerDetail = $bookingDetails_data['customer']['@attributes']['first_name'].' '.$bookingDetails_data['customer']['@attributes']['last_name'].','.$bookingDetails_data['customer']['@attributes']['email'].','.$bookingDetails_data['customer']['@attributes']['phone'];
                  }
                  else if(isset($bookingDetails_data['customer']['@attributes']['email']) && !isset($bookingDetails_data['customer']['@attributes']['phone']))
                  {
                    $bookingDetails_data['customer']['@attributes']['phone']="NA";
                    $customerDetail = $bookingDetails_data['customer']['@attributes']['first_name'].' '.$bookingDetails_data['customer']['@attributes']['last_name'].','.$bookingDetails_data['customer']['@attributes']['email'].','.$bookingDetails_data['customer']['@attributes']['phone'];
                  }
                  else if(!isset($bookingDetails_data['customer']['@attributes']['email']) && isset($bookingDetails_data['customer']['@attributes']['phone']))
                  {
                  $bookingDetails_data['customer']['@attributes']['email']="NA";
                  $customerDetail = $bookingDetails_data['customer']['@attributes']['first_name'].' '.$bookingDetails_data['customer']['@attributes']['last_name'].','.$bookingDetails_data['customer']['@attributes']['email'].','.$bookingDetails_data['customer']['@attributes']['phone'];
                  }
                  else
                  {
                    $customerDetail = $bookingDetails_data['customer']['@attributes']['first_name'].' '.$bookingDetails_data['customer']['@attributes']['last_name'].','.'NA,'.','.'NA';
                  }
                  $amount =  $bookingDetails_data['prices']['@attributes']['net_inclusive_amt'];
                  $tax_amount =isset($bookingDetails_data['prices']['@attributes']['tax_amt']) ?
                                $bookingDetails_data['prices']['@attributes']['tax_amt'] : 0 ;
                  $currency =  $bookingDetails_data['prices']['@attributes']['currency'];
                  $channel_name='Agoda';
                  $payment_status = 'Paid';
                  $ota_hotel_details = $ota_details_model
                    ->where('ota_hotel_code' ,'=', $hotel_Code)
                    ->first();
                  if($ota_hotel_details->hotel_id)
                  {
                    //agoda booking save in cmotabooking
                    if($booking_status == 'ConfirmBooking' || $booking_status == 'AmendBooking'){
                      $booking_status = 'Commit';
                      }
                      if($booking_status == 'CancelBooking'){
                        $booking_status = 'Cancel';
                      }
                    $bookingDetails = array('UniqueID'=>$uniqueID,'customerDetail'=>$customerDetail,'booking_status'=>$booking_status,'rooms_qty'=>$rooms_qty,'room_type'=>$room_type,'checkin_at'=>$checkin_at,'checkout_at'=>$checkout_at,'booking_date'=>$booking_date,'amount'=>$amount,'payment_status'=>$payment_status,'rate_code'=>$rate_code,'rlt'=>"",'currency'=>$currency,'channel_name'=>$channel_name,'tax_amount'=>$tax_amount,'no_of_adult'=>$no_of_adult,'no_of_child'=>$no_of_child,'inclusion'=>'NA');
                    $bookinginfo  = $this->bookingData->cmOtaBooking($bookingDetails,$ota_hotel_details);//this function call used for insert/update booking in database
                    $db_status  = $bookinginfo['db_status'];
                    $ota_booking_tabel_id =$bookinginfo['ota_booking_tabel_id'];
                    $push_by = "Agoda";
                    //after saving booking data call to bucket engine.
                    if($db_status)
                    {
                      $this->instantbucket->bucketEngineUpdate($booking_status,$push_by,$ota_hotel_details,$ota_booking_tabel_id);//this function is used for booking bucket data updation
                    }
                    dd('stop');
                  }
                  /*-----------------------is Inserting Booking data---------------------- */
                  }
                }
              }
          }//end og agoda booking
          if( $bookingDetails_xml="")
          {
            echo "No Agoda reservations";
          }
        // }//end of for loop
      }//Property list ends
    }//end of loop
  } // index Action Closed.
}
