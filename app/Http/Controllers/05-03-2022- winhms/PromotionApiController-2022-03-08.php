<?php
namespace App\Http\Controllers;
use Validator;
use DB;
use App\CmOtaDetails;
use App\OtaPromotions;

/**
 * @Ranjit
 * date : 06-10-2021
 * promotion Api controller handls all the functionality related to ota update.
 * creation,updation and deletion of promotion.
 */
class PromotionApiController extends Controller
{
    public function createPromotion($get_ota_code,$promotional_data,$promotion_id){
        foreach($get_ota_code as $ota_id){
            $get_ota_hotel_code = CmOtaDetails::select('*')
            ->where('ota_id',$ota_id)
            ->first();
            if($get_ota_hotel_code->ota_name == 'Goibibo'){
                $goibibo_promotion = $this->goibiboPromotion($get_ota_hotel_code,$promotional_data,$promotion_id,'creation');
                return $goibibo_promotion;
            }elseif($get_ota_hotel_code->ota_name == 'Agoda'){
                 $agoda_promotion = $this->agodaNewPromotion($get_ota_hotel_code,$promotional_data,'creation');
                 return $agoda_promotion;
            }
            // else if($get_ota_hotel_code->ota_name == 'Booking.com'){
            // $booking_dot_com_promotion = $this->bookingDotComPromotion($get_ota_hotel_code,$promotional_data,'creation');
            // return $booking_dot_com_promotion;
            // }
            // else if($get_ota_hotel_code->ota_name == 'Agoda'){
            // $agoda_promotion = $this->agodaPromotion($get_ota_hotel_code,$promotional_data,'creation');
            // return $agoda_promotion;
            // }  
        }
    }
    public function updatePromotion($get_ota_code,$promotional_data,$promotion_id){
        foreach($get_ota_code as $ota_id){
            $get_ota_hotel_code = CmOtaDetails::select('*')
            ->where('ota_id',$ota_id)
            ->first();
            if($get_ota_hotel_code->ota_name == 'Goibibo'){
                $goibibo_promotion = $this->goibiboPromotion($get_ota_hotel_code,$promotional_data,$promotion_id,'updation');
                return $goibibo_promotion;
            }elseif($get_ota_hotel_code->ota_name == 'Agoda'){
                 $agoda_promotion = $this->agodaNewPromotion($get_ota_hotel_code,$promotional_data,'updation');
                 return $agoda_promotion;
            }
            // else if($get_ota_hotel_code->ota_name == 'Booking.com'){
            //     $booking_dot_com_promotion = $this->bookingDotComPromotion($get_ota_hotel_code,$promotional_data,'updation');
            //     return $booking_dot_com_promotion;
            // }
            // else if($get_ota_hotel_code->ota_name == 'Agoda'){
            //     $agoda_promotion = $this->agodaPromotion($get_ota_hotel_code,$promotional_data,'updation');
            //     return $agoda_promotion;
            // }
        }
    }
    public function deletePromotion($get_ota_code,$promotional_data,$promotion_id){
        foreach($get_ota_code as $ota_id){
            $get_ota_hotel_code = CmOtaDetails::select('*')
            ->where('ota_id',$ota_id)
            ->first();
            if($get_ota_hotel_code->ota_name == 'Goibibo'){
                $goibibo_promotion = $this->goibiboPromotion($get_ota_hotel_code,$promotional_data,$promotion_id,'deletion');
                return $goibibo_promotion;
            }elseif($get_ota_hotel_code->ota_name == 'Agoda'){
                 $agoda_promotion = $this->agodaNewPromotion($get_ota_hotel_code,$promotional_data,'updation');
                 return $agoda_promotion;
            }
            // else if($get_ota_hotel_code->ota_name == 'Booking.com'){
            //     $booking_dot_com_promotion = $this->bookingDotComPromotion($get_ota_hotel_code,$promotional_data,'deletion');
            //     return $booking_dot_com_promotion;
            // }
            // else if($get_ota_hotel_code->ota_name == 'Agoda'){
            //     $agoda_promotion = $this->agodaPromotion($get_ota_hotel_code,$promotional_data,'deletion');
            //     return $agoda_promotion;
            // }
        }
    }
    public function goibiboPromotion($get_ota_hotel_code,$promotional_data,$promotion_id,$status){
        if($status == 'creation'){
            $auth_parameter         = json_decode($get_ota_hotel_code->auth_parameter);
            $bearer_token           = trim($auth_parameter->bearer_token);
            $channel_token          = trim($auth_parameter->channel_token);
            $headers = array(
                      "Content-Type: application/xml",
                      "channel-token:".$channel_token,
                      "bearer-token:".$bearer_token
                    );
            $hotel_code         = $get_ota_hotel_code->ota_hotel_code;
            $promotion_name     = $promotional_data['promotion_name'];
            $non_refoundable    = 'false';
            if(isset($promotional_data['booking_start_date']) && $promotional_data['booking_start_date'] != ""){
                $booking_start_date =  date("Y-m-d",Strtotime($promotional_data['booking_start_date']));
            }
            if(isset($promotional_data['booking_end_date']) && $promotional_data['booking_end_date'] != ""){
                $booking_end_date = date("Y-m-d",Strtotime($promotional_data['booking_end_date']));
            }
            if(isset($promotional_data['stay_start_date'])){
                $stay_start_date = date("Y-m-d",Strtotime($promotional_data['stay_start_date']));
            }
            if(isset($promotional_data['stay_end_date'])){
                $stay_end_date = date("Y-m-d",Strtotime($promotional_data['stay_end_date']));
            }
            $blackout = $promotional_data['blackout_option'];
            $black_out_dates = isset($promotional_data['blackout_dates'])?$promotional_data['blackout_dates']:'';
            $pay_at_hotel    = 'false';
            $applicable_for_room_rateplan = $promotional_data['applicable_for_room_rateplan'];
            if($applicable_for_room_rateplan == 1){
                $applicable_for_room_rateplan = 'all';
            }
            $offer_for_all_user=isset($promotional_data['offer_for_all_user'])?$promotional_data['offer_for_all_user']:0;
            $offer_for_loggedin_user=isset($promotional_data['offer_for_loggedin_user'])?$promotional_data['offer_for_loggedin_user']:0;
            $offer_type = isset($promotional_data['offer_type'])?$promotional_data['offer_type']:0;
            $applicable_for = $promotional_data['offer_type'] == 1 ?'fixed':'percentage';
            $offer_for_all_user = $promotional_data['discount'];
            $url = 'https://partners-connect.goibibo.com/api/chm/v1/offer';
            $promotion_xml = '<?xml version="1.0" encoding="UTF-8"?>
                    <Website Name="ingoibibo" HotelCode="'.$hotel_code.'">
                    <OfferName>'.$promotion_name.'</OfferName>
                    <OfferCategory>basic</OfferCategory>
                    <NonRefundable>'.$non_refoundable.'</NonRefundable>
                    <Restrictions>';
                    if(isset($promotional_data['booking_start_date']) && $promotional_data['booking_start_date'] != ""){
                        $promotion_xml.='<BookingDate>
                                            <Start>'.$booking_start_date.'</Start>';
                        if(isset($promotional_data['booking_end_date']) && $promotional_data['booking_end_date'] != ""){
                            $promotion_xml.='<End>'.$booking_end_date.'</End>';
                        }
                        $promotion_xml.='</BookingDate>';                      
                    }
                    if(isset($promotional_data['stay_start_date'])){
                        $promotion_xml.='<StayDate>
                                            <Start>'.$stay_start_date.'</Start>';
                        if(isset($promotional_data['stay_end_date'])){
                            $promotion_xml.='<End>'.$stay_end_date.'</End>';
                        }
                        $promotion_xml.='</StayDate>';
                    }
                    if($blackout){
                        $promotion_xml.='<StayBlackoutRanges>';
                        $black_out_dates = explode(',',$black_out_dates);
                        $i=0;
                        while($i<sizeof($black_out_dates)){
                            $black_out_date1 = date("Y-m-d",Strtotime($black_out_dates[$i]));
                            $black_out_date2 = date("Y-m-d",Strtotime($black_out_dates[$i]));
                            $promotion_xml.='<Range>
                                                <Start>'.$black_out_date1.'</Start>
                                                <End>'.$black_out_date2.'</End>
                                            </Range>';
                            $i++;
                        }
                        $promotion_xml.='</StayBlackoutRanges>';
                    }
                    $promotion_xml.='
                        <PayAtHotel>'.$pay_at_hotel.'</PayAtHotel>
                    </Restrictions>
                    <ApplicableToList>
                        <ApplicableTo>
                            <Type>Hotel</Type>
                            <Code>'.$hotel_code.'</Code>
                        </ApplicableTo>
                    </ApplicableToList>
                    <OfferCondition>'.$applicable_for_room_rateplan.'</OfferCondition>
                    <OfferValueList>
                        <OfferValueObject>
                            <OfferBasis>discount</OfferBasis>
                            <OfferValue>'.$offer_for_all_user.'</OfferValue>
                            <OfferType>'.$applicable_for.'</OfferType>
                            <Segment>all</Segment>
                        </OfferValueObject>
                        <OfferValueObject>
                            <OfferBasis>discount</OfferBasis>
                            <OfferValue>'.$offer_for_all_user.'</OfferValue>
                            <OfferType>'.$applicable_for.'</OfferType>
                            <Segment>loggedin</Segment>
                        </OfferValueObject>
                    </OfferValueList>
                    </Website>';
                $curl_call = $this->curlCall($url,$headers,$promotion_xml);
                $curl_call_array = json_decode(json_encode(simplexml_load_string($curl_call)), true);
                if(isset($curl_call_array['Success']) &&  $curl_call_array['Success'] == true){
                    $ota_promotional_data['ota_id'] = $get_ota_hotel_code->ota_id;
                    $ota_promotional_data['hotel_id'] = $get_ota_hotel_code->hotel_id;
                    $ota_promotional_data['promotion_id'] = $promotion_id;
                    $ota_promotional_data['ota_name'] = $get_ota_hotel_code->ota_name;
                    $ota_promotional_data['ota_promotion_code'] = $curl_call_array['OfferCode'];
                    $ota_promotional_data['request'] = $promotion_xml;
                    $ota_promotional_data['response'] = $curl_call;
                    $ota_promotional_data['status'] = 'Success';
                    $insert_details = OtaPromotions::insert($ota_promotional_data);
                    if($insert_details){
                        return response()->json(array('status'=>1,'ota_name'=>'Goibibo','message'=>$curl_call_array['Message']));
                    }
                }
                else if(isset($curl_call_array['Success']) &&  $curl_call_array['Success'] == false){
                    $ota_promotional_data['ota_id'] = $get_ota_hotel_code->ota_id;
                    $ota_promotional_data['hotel_id'] = $get_ota_hotel_code->hotel_id;
                    $ota_promotional_data['promotion_id'] = $promotion_id;
                    $ota_promotional_data['ota_name'] = $get_ota_hotel_code->ota_name;
                    $ota_promotional_data['ota_promotion_code'] = $curl_call_array['OfferCode'];
                    $ota_promotional_data['request'] = $promotion_xml;
                    $ota_promotional_data['response'] = $curl_call;
                    $ota_promotional_data['status'] = 'Error';
                    $insert_details = OtaPromotions::insert($ota_promotional_data);
                    if($insert_details){
                        return response()->json(array('status'=>0,'ota_name'=>'Goibibo','message'=>$curl_call_array['Message']));
                    }
                }
        }
        else if($status == 'updation' || $status == 'deletion'){
            $auth_parameter         = json_decode($get_ota_hotel_code->auth_parameter);
            $bearer_token           = trim($auth_parameter->bearer_token);
            $channel_token          = trim($auth_parameter->channel_token);
            $headers = array(
                      "Content-Type: application/xml",
                      "channel-token:".$channel_token,
                      "bearer-token:".$bearer_token
                    );
            $hotel_code         = $get_ota_hotel_code->ota_hotel_code;
            $promotion_name     = $promotional_data['promotion_name'];
            if(isset($promotional_data['booking_start_date']) && $promotional_data['booking_start_date'] != ""){
                $booking_start_date =  date("Y-m-d",Strtotime($promotional_data['booking_start_date']));;
            }
            if(isset($promotional_data['booking_end_date']) && $promotional_data['booking_end_date'] != ""){
                $booking_end_date = date("Y-m-d",Strtotime($promotional_data['booking_end_date']));
            }
            if(isset($promotional_data['stay_start_date'])){
                $stay_start_date = date("Y-m-d",Strtotime($promotional_data['stay_start_date']));
            }
            if(isset($promotional_data['stay_end_date'])){
                $stay_end_date = date("Y-m-d",Strtotime($promotional_data['stay_end_date']));
            }
            $blackout = $promotional_data['blackout_option'];
            $black_out_dates = isset($promotional_data['blackout_dates'])?$promotional_data['blackout_dates']:'';
            $pay_at_hotel    = 'false';
            $applicable_for_room_rateplan = $promotional_data['applicable_for_room_rateplan'];
            if($applicable_for_room_rateplan == 1){
                $applicable_for_room_rateplan = 'all';
            }
            $offer_for_all_user=isset($promotional_data['offer_for_all_user'])?$promotional_data['offer_for_all_user']:0;
            $offer_for_loggedin_user=isset($promotional_data['offer_for_loggedin_user'])?$promotional_data['offer_for_loggedin_user']:0;
            $offer_type = isset($promotional_data['offer_type'])?$promotional_data['offer_type']:0;
            if($status == 'updation'){
                $is_active = $promotional_data['is_trash'] == 0 ?'True':'False';
            }
            if($status == 'deletion'){
                $is_active = 'False';
            }
            $get_promotion = OtaPromotions::select('*')
                            ->where('promotion_id',$promotion_id)
                            ->where('ota_id',$get_ota_hotel_code->ota_id)
                            ->where('hotel_id',$get_ota_hotel_code->hotel_id)
                            ->first();

            if($get_promotion){
                $ota_promotion_code = $get_promotion->ota_promotion_code;
            }
            else{
                return response()->json(array('status'=>0,'ota_name'=>'Goibibo','message'=>'Promotion not available'));
            }
            $applicable_for = $promotional_data['offer_type'] == 1 ?'fixed ':'percentage';
            $offer_for_all_user = $promotional_data['discount'];
            $url = 'https://partners-connect.goibibo.com/api/chm/v1/offer';
            $promotion_xml = '<?xml version="1.0" encoding="UTF-8"?>
                    <Website Name="ingoibibo" HotelCode="'.$hotel_code.'">
                    <OfferCode>'.$ota_promotion_code.'</OfferCode>
                    <IsActive>'.$is_active.'</IsActive>
                    <Restrictions>';
                    if(isset($promotional_data['booking_start_date']) && $promotional_data['booking_start_date'] != ""){
                        $promotion_xml.='<BookingDate>
                                            <Start>'.$booking_start_date.'</Start>';
                        if(isset($promotional_data['booking_end_date']) && $promotional_data['booking_end_date'] != ""){
                            $promotion_xml.='<End>'.$booking_end_date.'</End>';
                        }
                        $promotion_xml.='</BookingDate>';                      
                    }
                    if(isset($promotional_data['stay_start_date'])){
                        $promotion_xml.='<StayDate>
                                            <Start>'.$stay_start_date.'</Start>';
                        if(isset($promotional_data['stay_end_date'])){
                            $promotion_xml.='<End>'.$stay_end_date.'</End>';
                        }
                        $promotion_xml.='</StayDate>';
                    }
                    if($blackout){
                    }
                    $promotion_xml.='
                        <PayAtHotel>'.$pay_at_hotel.'</PayAtHotel>
                    </Restrictions>
                    <OfferValueList>
                        <OfferValueObject>
                            <OfferBasis>discount</OfferBasis>
                            <OfferValue>'.$offer_for_all_user.'</OfferValue>
                            <OfferType>'.$applicable_for.'</OfferType>
                            <Segment>all</Segment>
                        </OfferValueObject>
                    </OfferValueList>
                    </Website>';
                $curl_call = $this->curlCallEdit($url,$headers,$promotion_xml);
                $curl_call_array = json_decode(json_encode(simplexml_load_string($curl_call)), true);
                if(isset($curl_call_array['Success']) &&  $curl_call_array['Success'] == true){
                    $ota_promotional_data['ota_id'] = $get_ota_hotel_code->ota_id;
                    $ota_promotional_data['hotel_id'] = $get_ota_hotel_code->hotel_id;
                    $ota_promotional_data['promotion_id'] = $promotion_id;
                    $ota_promotional_data['ota_name'] = $get_ota_hotel_code->ota_name;
                    $ota_promotional_data['ota_promotion_code'] = $curl_call_array['OfferCode'];
                    $ota_promotional_data['request'] = $promotion_xml;
                    $ota_promotional_data['response'] = $curl_call;
                    $ota_promotional_data['status'] = 'Success';
                    if($status == 'updation'){
                        $update_details = OtaPromotions::where('promotion_id',$promotion_id)
                                        ->where('ota_id',$get_ota_hotel_code->ota_id)
                                        ->where('hotel_id',$get_ota_hotel_code->hotel_id)
                                        ->update($ota_promotional_data);
                        if($update_details){
                            return response()->json(array('status'=>0,'ota_name'=>'Goibibo','message'=>$curl_call_array['Message']));
                        }
                    }
                    if($status == 'deletion'){
                        return response()->json(array('status'=>1,'ota_name'=>'Goibibo','message'=>$curl_call_array['Message']));
                    }
                }
                else if(isset($curl_call_array['Success']) &&  $curl_call_array['Success'] == false){
                    $ota_promotional_data['ota_id'] = $get_ota_hotel_code->ota_id;
                    $ota_promotional_data['hotel_id'] = $get_ota_hotel_code->hotel_id;
                    $ota_promotional_data['promotion_id'] = $promotion_id;
                    $ota_promotional_data['ota_name'] = $get_ota_hotel_code->ota_name;
                    $ota_promotional_data['ota_promotion_code'] = $curl_call_array['OfferCode'];
                    $ota_promotional_data['request'] = $promotion_xml;
                    $ota_promotional_data['response'] = $curl_call;
                    $ota_promotional_data['status'] = 'Error';
                    if($status == 'updation'){
                        $promotion_id = $promotional_data['promotion_id'];
                        $update_details = OtaPromotions::where('promotion_id',$promotion_id)
                                        ->where('ota_id',$get_ota_hotel_code->ota_id)
                                        ->where('hotel_id',$get_ota_hotel_code->hotel_id)
                                        ->update($ota_promotional_data);
                        if($update_details){
                            return response()->json(array('status'=>0,'ota_name'=>'Goibibo','message'=>$curl_call_array['Message']));
                        }
                    }
                    if($status == 'deletion'){
                        return response()->json(array('status'=>0,'ota_name'=>'Goibibo','message'=>$curl_call_array['Message']));
                    }
                }
        }
    }
    public function agodaPromotion($get_ota_hotel_code,$promotional_data,$status){
        if($status == 'creation'){
            $auth_parameter         = json_decode($get_ota_hotel_code->auth_parameter);
            $bearer_token           = trim($auth_parameter->bearer_token);
            $channel_token          = trim($auth_parameter->channel_token);
            $headers = array(
                      "Content-Type: application/xml",
                      "channel-token:".$channel_token,
                      "bearer-token:".$bearer_token
                    );
            $hotel_code         = $get_ota_hotel_code->ota_hotel_code;
            $promotion_name     = $promotional_data['promotion_name'];
            $non_refoundable    = $promotional_data['non_refundable'];
            if(isset($non_refoundable) && $non_refoundable == 1){
                $non_refoundable = true;
            }
            else{
                $non_refoundable = false;
            }
            if(isset($promotional_data['booking_start_date'])){
                $booking_start_date =  $promotional_data['booking_start_date'];
            }
            if(isset($promotional_data['booking_end_date'])){
                $booking_end_date = $promotional_data['booking_end_date'];
            }
            if(isset($promotional_data['stay_start_date'])){
                $stay_start_date = $promotional_data['stay_start_date'];
            }
            if(isset($promotional_data['stay_end_date'])){
                $stay_end_date = $promotional_data['stay_end_date'];
            }
            $blackout = $promotional_data['blackout'];
            $black_out_dates = isset($promotional_data['blackout'])?$promotional_data['blackout']:'';
            $pay_at_hotel    = $promotional_data['pay_at_hotel'];
            if(isset($pay_at_hotel) && $pay_at_hotel == 1){
                $pay_at_hotel = true;
            }
            else{
                $pay_at_hotel = false;
            }
            $applicable_for_room_rateplan = $promotional_data['applicable_for_room_rateplan'];
            if($applicable_for_room_rateplan == 1){
                $applicable_for_room_rateplan = 'all';
            }
            $offer_for_all_user=isset($promotional_data['offer_for_all_user'])?$promotional_data['offer_for_all_user']:0;
            $offer_for_loggedin_user=isset($promotional_data['offer_for_loggedin_user'])?$promotional_data['offer_for_loggedin_user']:0;
            $offer_type = isset($promotional_data['offer_type'])?$promotional_data['offer_type']:0;
    
            $url = 'https://ppin.goibibo.com/api/chm/v1/offer';
            $promotion_xml = '<?xml version="1.0" encoding="UTF-8"?>
                    <Website Name="ingoibibo" HotelCode="'.$hotel_code.'">
                    <OfferName>'.$promotion_name.'</OfferName>
                    <OfferCategory>basic</OfferCategory>
                    <NonRefundable>'.$non_refoundable.'</NonRefundable>
                    <Restrictions>';
                    if(isset($promotional_data['booking_start_date'])){
                        $promotion_xml.='<BookingDate>
                                            <Start>'.$booking_start_date.'</Start>';
                        if(isset($promotional_data['booking_end_date'])){
                            $promotion_xml.='<End>'.$booking_end_date.'</End>';
                        }
                        $promotion_xml.='</BookingDate>';                      
                    }
                    if(isset($promotional_data['stay_start_date'])){
                        $promotion_xml.='<StayDate>
                                            <Start>'.$stay_start_date.'</Start>';
                        if(isset($promotional_data['stay_end_date'])){
                            $promotion_xml.='<End>'.$stay_end_date.'</End>';
                        }
                        $promotion_xml.='</StayDate>';
                    }
                    if($blackout){
                        $promotion_xml.='<StayBlackoutRanges>';
                        for($i=0;$i<sizeof($black_out_dates);$i+2){
                            $promotion_xml.='<Range>
                                                <Start>'.$black_out_dates[$i].'</Start>
                                                <End>'.$black_out_dates[$i+1].'</End>
                                            </Range>';
                        }
                        $promotion_xml.='</StayBlackoutRanges>';
                    }
                    $promotion_xml.='
                        <PayAtHotel>'.$pay_at_hotel.'</PayAtHotel>
                    </Restrictions>
                    <ApplicableToList>
                        <ApplicableTo>
                            <Type>Hotel</Type>
                            <Code>'.$hotel_code.'</Code>
                        </ApplicableTo>
                    </ApplicableToList>
                    <OfferCondition>'.$applicable_for_room_rateplan.'</OfferCondition>
                    <OfferValueList>
                        <OfferValueObject>
                            <OfferBasis>discount</OfferBasis>
                            <OfferValue>'.$offer_for_all_user.'</OfferValue>
                            <OfferType>percentage</OfferType>
                            <Segment>'.$offer_type.'</Segment>
                        </OfferValueObject>
                        <OfferValueObject>
                            <OfferBasis>discount</OfferBasis>
                            <OfferValue>'.$offer_for_loggedin_user.'</OfferValue>
                            <OfferType>percentage</OfferType>
                            <Segment>'.$offer_type.'</Segment>
                        </OfferValueObject>
                    </OfferValueList>
                    </Website>';
                $curl_call = $this->curlCall($url,$headers,$promotion_xml);
                $curl_call_array = json_decode($curl_call);
                if(isset($curl_call_array['Success']) &&  $curl_call_array['Success'] == true){
                    $promotional_data['ota_promotion_code'] = $curl_call_array['OfferCode'];
                    $promotional_data['promotion_request'] = $promotion_xml;
                    $promotional_data['promotion_response'] = $curl_call;
                    $insert_details = Promotions::insert($promotional_data);
                    if($insert_details){
                        return response()->json(array('status'=>1,'ota_name'=>'Goibibo','message'=>$curl_call_array['Message']));
                    }
                }
                else if(isset($curl_call_array['Success']) &&  $curl_call_array['Success'] == false){
                    $promotional_data['ota_promotion_code'] = $curl_call_array['OfferCode'];
                    $promotional_data['promotion_request'] = $promotion_xml;
                    $promotional_data['promotion_response'] = $curl_call;
                    $insert_details = Promotions::insert($promotional_data);
                    if($insert_details){
                        return response()->json(array('status'=>0,'ota_name'=>'Goibibo','message'=>$curl_call_array['Message']));
                    }
                }
        }
        else if($status == 'updation' || $status == 'deletion'){
            $auth_parameter         = json_decode($get_ota_hotel_code->auth_parameter);
            $bearer_token           = trim($auth_parameter->bearer_token);
            $channel_token          = trim($auth_parameter->channel_token);
            $headers = array(
                      "Content-Type: application/xml",
                      "channel-token:".$channel_token,
                      "bearer-token:".$bearer_token
                    );
            $hotel_code         = $get_ota_hotel_code->ota_hotel_code;
            $promotion_name     = $promotional_data['promotion_name'];
            $non_refoundable    = $promotional_data['non_refundable'];
            if(isset($non_refoundable) && $non_refoundable == 1){
                $non_refoundable = true;
            }
            else{
                $non_refoundable = false;
            }
            if(isset($promotional_data['booking_start_date'])){
                $booking_start_date =  $promotional_data['booking_start_date'];
            }
            if(isset($promotional_data['booking_end_date'])){
                $booking_end_date = $promotional_data['booking_end_date'];
            }
            if(isset($promotional_data['stay_start_date'])){
                $stay_start_date = $promotional_data['stay_start_date'];
            }
            if(isset($promotional_data['stay_end_date'])){
                $stay_end_date = $promotional_data['stay_end_date'];
            }
            $blackout = $promotional_data['blackout'];
            $black_out_dates = isset($promotional_data['blackout'])?$promotional_data['blackout']:'';
            $pay_at_hotel    = $promotional_data['pay_at_hotel'];
            if(isset($pay_at_hotel) && $pay_at_hotel == 1){
                $pay_at_hotel = true;
            }
            else{
                $pay_at_hotel = false;
            }
            $applicable_for_room_rateplan = $promotional_data['applicable_for_room_rateplan'];
            if($applicable_for_room_rateplan == 1){
                $applicable_for_room_rateplan = 'all';
            }
            $offer_for_all_user=isset($promotional_data['offer_for_all_user'])?$promotional_data['offer_for_all_user']:0;
            $offer_for_loggedin_user=isset($promotional_data['offer_for_loggedin_user'])?$promotional_data['offer_for_loggedin_user']:0;
            $offer_type = isset($promotional_data['offer_type'])?$promotional_data['offer_type']:0;
            if($status == 'updation'){
                $is_active = $promotional_data['is_active'] == 1 ?True:False;
            }
            if($status == 'deletion'){
                $is_active = False;
            }
           
            if(isset($promotional_data['ota_promotion_code'])){
                $ota_promotion_code = $promotional_data['ota_promotion_code'];
            }
            else{
                return response()->json(array('status'=>0,'ota_name'=>'Goibibo','message'=>'Promotion not available'));
            }

            $url = 'https://ppin.goibibo.com/api/chm/v1/offer';
            $promotion_xml = '<?xml version="1.0" encoding="UTF-8"?>
                    <Website Name="ingoibibo" HotelCode="'.$hotel_code.'">
                    <OfferCode>'.$ota_promotion_code.'</OfferCode>
                    <IsActive>'.$is_active.'</IsActive>
                    <NonRefundable>'.$non_refoundable.'</NonRefundable>
                    <Restrictions>';
                    if(isset($promotional_data['booking_start_date'])){
                        $promotion_xml.='<BookingDate>
                                            <Start>'.$booking_start_date.'</Start>';
                        if(isset($promotional_data['booking_end_date'])){
                            $promotion_xml.='<End>'.$booking_end_date.'</End>';
                        }
                        $promotion_xml.='</BookingDate>';                      
                    }
                    if(isset($promotional_data['stay_start_date'])){
                        $promotion_xml.='<StayDate>
                                            <Start>'.$stay_start_date.'</Start>';
                        if(isset($promotional_data['stay_end_date'])){
                            $promotion_xml.='<End>'.$stay_end_date.'</End>';
                        }
                        $promotion_xml.='</StayDate>';
                    }
                    if($blackout){
                        $promotion_xml.='<StayBlackoutRanges>';
                        for($i=0;$i<sizeof($black_out_dates);$i+2){
                            $promotion_xml.='<Range>
                                                <Start>'.$black_out_dates[$i].'</Start>
                                                <End>'.$black_out_dates[$i+1].'</End>
                                            </Range>';
                        }
                        $promotion_xml.='</StayBlackoutRanges>';
                    }
                    $promotion_xml.='
                        <PayAtHotel>'.$pay_at_hotel.'</PayAtHotel>
                    </Restrictions>
                    <OfferValueList>
                        <OfferValueObject>
                            <OfferBasis>discount</OfferBasis>
                            <OfferValue>'.$offer_for_all_user.'</OfferValue>
                            <OfferType>'.$offer_type.'</OfferType>
                            <Segment>all</Segment>
                        </OfferValueObject>
                        <OfferValueObject>
                            <OfferBasis>discount</OfferBasis>
                            <OfferValue>'.$offer_for_loggedin_user.'</OfferValue>
                            <OfferType>'.$offer_type.'</OfferType>
                            <Segment>loggedin</Segment>
                        </OfferValueObject>
                    </OfferValueList>
                    </Website>';
        
                $curl_call = $this->curlCall($url,$headers,$promotion_xml);
                $curl_call_array = json_decode($curl_call);
                if(isset($curl_call_array['Success']) &&  $curl_call_array['Success'] == true){
                    $promotional_data['ota_promotion_code'] = $curl_call_array['OfferCode'];
                    $promotional_data['promotion_request'] = $promotion_xml;
                    $promotional_data['promotion_response'] = $curl_call;
                    if($status == 'updation'){
                        $promotion_id = $promotional_data['promotion_id'];
                        $update_details = Promotions::where('promotion_id',$promotion_id)->update($promotional_data);
                        if($update_details){
                            return response()->json(array('status'=>0,'ota_name'=>'Goibibo','message'=>$curl_call_array['Message']));
                        }
                    }
                    if($status == 'deletion'){
                        return response()->json(array('status'=>1,'ota_name'=>'Goibibo','message'=>$curl_call_array['Message']));
                    }
                }
                else if(isset($curl_call_array['Success']) &&  $curl_call_array['Success'] == false){
                    $promotional_data['ota_promotion_code'] = $curl_call_array['OfferCode'];
                    $promotional_data['promotion_request'] = $promotion_xml;
                    $promotional_data['promotion_response'] = $curl_call;
                    if($status == 'updation'){
                        $promotion_id = $promotional_data['promotion_id'];
                        $update_details = Promotions::where('promotion_id',$promotion_id)->update($promotional_data);
                        if($update_details){
                            return response()->json(array('status'=>0,'ota_name'=>'Goibibo','message'=>$curl_call_array['Message']));
                        }
                    }
                    if($status == 'deletion'){
                        return response()->json(array('status'=>0,'ota_name'=>'Goibibo','message'=>$curl_call_array['Message']));
                    }
                }
        }
    }
    public function test(){
        $get_ota_hotel_code = new OtaPromotions();
        $get_ota_hotel_code->ota_hotel_code = '9732580';
        $auth_parameter = new OtaPromotions();
        $auth_parameter->bearer_token = 'd9922c69-dada-48f4-996c-92d691944127';
        $auth_parameter->channel_token = 'd9922c69-dada-48f4-996c-92d691944127';
        $promotional_data['promotion_name'] = 'MyPromotion';
        $promotional_data['non_refundable'] = 1;
        $promotional_data['booking_start_date'] = '2022-02-26';
        $promotional_data['booking_end_date'] = '2022-02-28';
        $promotional_data['stay_start_date'] = '2022-02-26';
        $promotional_data['stay_end_date'] = '2022-03-28';
        $promotional_data['blackout'] = ['2022-03-20','2022-03-21'];
        $promotional_data['pay_at_hotel'] = 1;
        $promotional_data['applicable_for_room_rateplan'] = 1;
        $promotional_data['is_active'] = 1;
        $status = 'deletion';
        $this->agodaNewPromotion($get_ota_hotel_code,$promotional_data,$status);
    }
    public function agodaNewPromotion($get_ota_hotel_code,$promotional_data,$status){
        if($status == 'creation'){
            $auth_parameter         = json_decode($get_ota_hotel_code->auth_parameter);
            $bearer_token           = 'd9922c69-dada-48f4-996c-92d691944127';//trim($auth_parameter->bearer_token);
            $channel_token          = 'd9922c69-dada-48f4-996c-92d691944127';//trim($auth_parameter->channel_token);
            $headers = array(
                      "Content-Type: application/xml",
                      "apiKey:".$bearer_token
                    );
            $hotel_code         = $get_ota_hotel_code->ota_hotel_code;
            $promotion_name     = $promotional_data['promotion_name'];
            $non_refoundable    = $promotional_data['non_refundable'];
            if(isset($non_refoundable) && $non_refoundable == 1){
                $non_refoundable = true;
            }
            else{
                $non_refoundable = false;
            }
            if(isset($promotional_data['booking_start_date'])){
                $booking_start_date =  $promotional_data['booking_start_date'];
            }
            if(isset($promotional_data['booking_end_date'])){
                $booking_end_date = $promotional_data['booking_end_date'];
            }
            if(isset($promotional_data['stay_start_date'])){
                $stay_start_date = $promotional_data['stay_start_date'];
            }
            if(isset($promotional_data['stay_end_date'])){
                $stay_end_date = $promotional_data['stay_end_date'];
            }
            $blackout = $promotional_data['blackout'];
            $black_out_dates = isset($promotional_data['blackout'])?$promotional_data['blackout']:'';
            $pay_at_hotel    = $promotional_data['pay_at_hotel'];
            if(isset($pay_at_hotel) && $pay_at_hotel == 1){
                $pay_at_hotel = true;
            }
            else{
                $pay_at_hotel = false;
            }
            $applicable_for_room_rateplan = $promotional_data['applicable_for_room_rateplan'];
            if($applicable_for_room_rateplan == 1){
                $applicable_for_room_rateplan = 'all';
            }
            $offer_for_all_user = isset($promotional_data['offer_for_all_user'])?$promotional_data['offer_for_all_user']:0;
            $offer_for_loggedin_user = isset($promotional_data['offer_for_loggedin_user'])?$promotional_data['offer_for_loggedin_user']:0;
            $offer_type = isset($promotional_data['offer_type'])?$promotional_data['offer_type']:0;
            $current_time_stamp = strtotime(date("Y-m-d H:i:s"));

            $url = 'https://content-push.agoda.com/cm/promotion';
            $promotion_xml = '<?xml version="1.0" encoding="UTF-8"?>
                              <HotelPromotion_RQ Timestamp="'.$current_time_stamp.'">
                              <Promotion PromotionType="Customized" PromotionName="'.$promotion_name.'" PromotionExternalId="CTM001" HotelId="'.$hotel_code.'"/>';
                    if(isset($promotional_data['booking_start_date'])){
                        $promotion_xml.='<SaleDateRange Start="'.$booking_start_date.'" End="'.$booking_end_date.'"/>';                    
                    }
                    if(isset($promotional_data['stay_start_date'])){
                        $promotion_xml .= '<StayDateRange Start="'.$stay_start_date.'" End="'.$stay_end_date.'"/>';
                    }
                    if($blackout){
                        $promotion_xml.='<BlackoutDateRange>';
                        for($i=0;$i<sizeof($black_out_dates);$i++){
                            $promotion_xml .= '<DateRange Start="'.$black_out_dates[$i].'" End="'.$black_out_dates[$i].'"/>';
                        }
                        $promotion_xml .= '</BlackoutDateRange>';
                    }
                    $promotion_xml.='<Discount>
                                        <PercentPerNight NightType="SpecificDOW">
                                          <Value>'.$offer_type.'</Value>
                                        </PercentPerNight>
                                        </Discount>
                                    <CancellationPolicy Id="963"/>
                                    </HotelPromotion_RQ>';//963
                    // <CustomerSegments>
                    //     <CustomerSegment Id="'.$offer_for_loggedin_user.'"/>
                    // </CustomerSegments>
                $curl_call = $this->curlCall($url,$headers,$promotion_xml);
                $curl_call_array = json_decode(json_encode(simplexml_load_string($this->removeNamespaceFromXML($curl_call))), true);
                //echo $promotion_xml;echo "<pre>";print_r($curl_call_array);exit;
                if(isset($curl_call_array["Success"])){
                    $tuid = $curl_call_array['@attributes']['Tuid'];
                    $ota_promotion_code = $curl_call_array['@attributes']['PromotionId'];
                    $promotional_data['ota_promotion_code'] = $ota_promotion_code;
                    $promotional_data['promotion_request'] = $promotion_xml;
                    $promotional_data['promotion_response'] = $curl_call;
                    $insert_details = Promotions::insert($promotional_data);
                    if($insert_details){
                        return response()->json(array('status'=>1,'ota_name'=>'Agoda','message'=>'Success'));
                    }else{
                        echo "demo";
                    }
                }
                if(isset($curl_call_array["Errors"])){
                    if(isset($curl_call_array["Errors"]["Error"]['@attributes'])){
                        $short_text =  $curl_call_array['Errors']['Error']['@attributes']['ShortText'];
                        $status_from_res = $curl_call_array['Errors']['Error']['@attributes']['Status'];
                    }else{
                        foreach($curl_call_array["Errors"]["Error"] as $key=>$val){
                            $short_text_array[] = $val['@attributes']['ShortText'];
                        }
                        $short_text = implode(',', $short_text_array);
                    }
                    return response()->json(array('status'=>0,'ota_name'=>'Agoda','message'=>$short_text));
                }
        }elseif ($status == 'updation' || $status == 'deletion'){
            $auth_parameter         = json_decode($get_ota_hotel_code->auth_parameter);
            $bearer_token           = 'd9922c69-dada-48f4-996c-92d691944127';//trim($auth_parameter->bearer_token);
            $channel_token          = 'd9922c69-dada-48f4-996c-92d691944127';//trim($auth_parameter->channel_token);
            $headers = array(
                      "Content-Type: application/xml",
                      "apiKey:".$bearer_token
                    );
            $hotel_code         = $get_ota_hotel_code->ota_hotel_code;
            $promotion_name     = $promotional_data['promotion_name'];
            $non_refoundable    = $promotional_data['non_refundable'];
            if(isset($non_refoundable) && $non_refoundable == 1){
                $non_refoundable = true;
            }
            else{
                $non_refoundable = false;
            }
            if(isset($promotional_data['booking_start_date'])){
                $booking_start_date =  $promotional_data['booking_start_date'];
            }
            if(isset($promotional_data['booking_end_date'])){
                $booking_end_date = $promotional_data['booking_end_date'];
            }
            if(isset($promotional_data['stay_start_date'])){
                $stay_start_date = $promotional_data['stay_start_date'];
            }
            if(isset($promotional_data['stay_end_date'])){
                $stay_end_date = $promotional_data['stay_end_date'];
            }
            $blackout = $promotional_data['blackout'];
            $black_out_dates = isset($promotional_data['blackout'])?$promotional_data['blackout']:'';
            $pay_at_hotel    = $promotional_data['pay_at_hotel'];
            if(isset($pay_at_hotel) && $pay_at_hotel == 1){
                $pay_at_hotel = true;
            }
            else{
                $pay_at_hotel = false;
            }
            $applicable_for_room_rateplan = $promotional_data['applicable_for_room_rateplan'];
            if($applicable_for_room_rateplan == 1){
                $applicable_for_room_rateplan = 'all';
            }
            $offer_for_all_user=isset($promotional_data['offer_for_all_user'])?$promotional_data['offer_for_all_user']:0;
            $offer_for_loggedin_user=isset($promotional_data['offer_for_loggedin_user'])?$promotional_data['offer_for_loggedin_user']:0;
            $offer_type = isset($promotional_data['offer_type'])?$promotional_data['offer_type']:0;
            if($status == 'updation'){
                $is_active = $promotional_data['is_active'] == 1 ?True:False;
            }
            if($status == 'deletion'){
                $is_active = False;
            }
            $promotional_data['ota_promotion_code'] = 167299947;
            if(isset($promotional_data['ota_promotion_code'])){
                $ota_promotion_code = $promotional_data['ota_promotion_code'];
            }
            else{
                return response()->json(array('status'=>0,'ota_name'=>'Agoda','message'=>'Promotion not available'));
            }

            $current_time_stamp = strtotime(date("Y-m-d H:i:s"));
            if($status == 'updation'){
                $url = 'https://content-push.agoda.com/cm/promotion';
                $promotion_xml = '<?xml version="1.0" encoding="UTF-8"?>
                              <HotelPromotion_RQ Timestamp="'.$current_time_stamp.'">
                              <Promotion PromotionType="Customized" PromotionName="'.$promotion_name.'" PromotionExternalId="'.$ota_promotion_code.'" HotelId="'.$hotel_code.'"/>';
                    if(isset($promotional_data['booking_start_date'])){
                        $promotion_xml.='<SaleDateRange Start="'.$booking_start_date.'" End="'.$booking_end_date.'"/>';                    
                    }
                    if(isset($promotional_data['stay_start_date'])){
                        $promotion_xml .= '<StayDateRange Start="'.$stay_start_date.'" End="'.$stay_end_date.'"/>';
                    }
                    if($blackout){
                        $promotion_xml.='<BlackoutDateRange>';
                        for($i=0;$i<sizeof($black_out_dates);$i++){
                            $promotion_xml .= '<DateRange Start="'.$black_out_dates[$i].'" End="'.$black_out_dates[$i].'"/>';
                        }
                        $promotion_xml .= '</BlackoutDateRange>';
                    }
                    $promotion_xml.='<Discount>
                                        <PercentPerNight NightType="SpecificDOW">
                                          <Value>'.$offer_type.'</Value>
                                        </PercentPerNight>
                                    </Discount>
                                    <CancellationPolicy Id="3434"/>
                                    </HotelPromotion_RQ>';//963
                    // <CustomerSegments>
                    //     <CustomerSegment Id="'.$offer_for_loggedin_user.'"/>
                    // </CustomerSegments>
            }else{
                $url = 'https://content-push.agoda.com/cm/promotion/toggle';
                $promotion_xml = '<?xml version="1.0" encoding="UTF-8"?><HotelPromotionToggle_RQ HotelId="'.$hotel_code.'" IsActive="true" PromotionId="'.$ota_promotion_code.'" Timestamp="'.$current_time_stamp.'"/>';
            }
                $curl_call = $this->curlCall($url,$headers,$promotion_xml);

                $curl_call_array = json_decode(json_encode(simplexml_load_string($this->removeNamespaceFromXML($curl_call))), true);
                //echo $promotion_xml;echo "<pre>";print_r($curl_call_array);exit;
                if($status == 'deletion'){
                    if(isset($curl_call_array["Success"])){
                            return response()->json(array('status'=>1,'ota_name'=>'Agoda','message'=>'Success'));
                    }else{
                        if(isset($curl_call_array["Errors"]["Error"]['@attributes'])){
                            $short_text =  $curl_call_array['Errors']['Error']['@attributes']['ShortText'];
                            $status_from_res = $curl_call_array['Errors']['Error']['@attributes']['Status'];
                        }else{
                            foreach($curl_call_array["Errors"]["Error"] as $key=>$val){
                                $short_text_array[] = $val['@attributes']['ShortText'];
                            }
                            $short_text = implode(',', $short_text_array);
                        }
                        return response()->json(array('status'=>0,'ota_name'=>'Agoda','message'=>$short_text));
                    }
                }else{
                    if(isset($curl_call_array["Success"])){
                        $tuid = $curl_call_array['@attributes']['Tuid'];
                        $ota_promotion_code = $curl_call_array['@attributes']['PromotionId'];
                        $promotional_data['ota_promotion_code'] = $ota_promotion_code;
                        $promotional_data['promotion_request'] = $promotion_xml;
                        $promotional_data['promotion_response'] = $curl_call;
                        if($status == 'updation'){
                            $promotion_id = $promotional_data['promotion_id'];
                            $update_details = Promotions::where('promotion_id',$promotion_id)->update($promotional_data);
                            if($update_details){
                                return response()->json(array('status'=>0,'ota_name'=>'Agoda','message'=>'Success'));
                            }
                        }
                    }
                    if(isset($array_data["Errors"])){
                        if(isset($curl_call_array["Errors"]["Error"]['@attributes'])){
                            $short_text =  $curl_call_array['Errors']['Error']['@attributes']['ShortText'];
                            $status_from_res = $curl_call_array['Errors']['Error']['@attributes']['Status'];
                        }else{
                            foreach($curl_call_array["Errors"]["Error"] as $key=>$val){
                                $short_text_array[] = $val['@attributes']['ShortText'];
                            }
                            $short_text = implode(',', $short_text_array);
                        }
                        return response()->json(array('status'=>0,'ota_name'=>'Agoda','message'=>$short_text));
                    }
                }
        }
    }
    public function curlCall($url,$headers,$promotion_xml){
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $promotion_xml);
        $ota_rlt = curl_exec($ch);
        curl_close($ch);
        return $ota_rlt;
    }
    public function curlCallEdit($url,$headers,$promotion_xml){
        // dd($url,$headers,$promotion_xml);
        $ch = curl_init();
        curl_setopt_array($ch, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_POSTFIELDS =>$promotion_xml,
        CURLOPT_HTTPHEADER =>$headers 
        ));
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }
    public function removeNamespaceFromXML($xml)
    {
        // Because I know all of the the namespaces that will possibly appear in
        // in the XML string I can just hard code them and check for
        // them to remove them
        $toRemove = ['rap', 'turss', 'crim', 'cred', 'j', 'rap-code', 'evic'];
        // This is part of a regex I will use to remove the namespace declaration from string
        $nameSpaceDefRegEx = '(\S+)=["\']?((?:.(?!["\']?\s+(?:\S+)=|[>"\']))+.)["\']?';

        // Cycle through each namespace and remove it from the XML string
       foreach( $toRemove as $remove ) {
            // First remove the namespace from the opening of the tag
            $xml = str_replace('<' . $remove . ':', '<', $xml);
            // Now remove the namespace from the closing of the tag
            $xml = str_replace('</' . $remove . ':', '</', $xml);
            // This XML uses the name space with CommentText, so remove that too
            $xml = str_replace($remove . ':commentText', 'commentText', $xml);
            // Complete the pattern for RegEx to remove this namespace declaration
            $pattern = "/xmlns:{$remove}{$nameSpaceDefRegEx}/";
            // Remove the actual namespace declaration using the Pattern
            $xml = preg_replace($pattern, '', $xml, 1);
        }

        // Return sanitized and cleaned up XML with no namespaces
        return $xml;
    }
}