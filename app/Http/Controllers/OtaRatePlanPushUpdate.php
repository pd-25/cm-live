<?php
namespace App\Http\Controllers;
use Validator;
use App\Inventory;
use App\CmOtaManageInventoryBucket;
use App\CmOtaDetails;
use App\RatePlanLog;
use App\CmOtaRoomTypeSynchronize;
use App\CmOtaRatePlanSynchronize;
use App\LogTable;
use App\AirbnbListingDetails;
use App\HotelInformation;
use App\CompanyDetails;  
use DB;

class OtaRatePlanPushUpdate {
		/*------------------------get room type name---------------------------------*/
		public function getRoomTypeName($room_type)
		{
			$getRoomType=DB::table('room_type_table')->select('room_type')->where('room_type_id',$room_type)->first();
			return $getRoomType->room_type;
		}
		/*-------------------------------end-----------------------------------------*/
/*------------------------get currency---------------------------------*/
		public function getCurrency($hotel_id)
		{
			$hotel_info=HotelInformation::where('hotel_id',$hotel_id)->first();
			$company= new CompanyDetails();
			$comp_details=$company->where('company_id',$hotel_info->company_id)->select('currency')->first();
			$currency=$comp_details->currency;
			return $currency;

		}
/*-------------------------------end-----------------------------------------*/

    public function getDaysUpdate($rateplan_multiple_days,$ota_name)
    {
        $rateplan_multiple_days      = json_decode($rateplan_multiple_days);
		$rateplan_days_data="";
		$prefix="";
        foreach($rateplan_multiple_days as $key=>$value)
		{	/*==============Cleartrip=============*/
			if($ota_name=="Cleartrip")
            {
				if($value==1)
				{
				$rateplan_days_data .=  $prefix .strtoupper($key);
				$prefix=',';
				}
			}
			/*===========Agoda===============*/
			if($ota_name=="Agoda")
			{
				if($value==1)
				{
				$rateplan_days_data.='<dow>'.$this->agodaDays($key).'</dow>';
				}
			}
			/*===========Expedia===============*/
			if($ota_name=='Expedia')
			{
				if($value==1)
				{
				$status="true";
				}
				else if($value==0)
				{
				$status="false";
				}
				$rateplan_days_data.= ' '.strtolower($key).'="'.$status.'"';	
			}
			/*==============Goibibo===============*/
			if($ota_name=="Goibibo")
            {

				if($value==1)
				{
				$status="True";
				}
				else if($value==0)
				{
				$status="False";
				}
				$rateplan_days_data.= ' '.$key.'="'.$status.'"';
			}
			/*==========Via.com===========*/
			if($ota_name=='Via.com')
			{
				
				$rateplan_days_data.= $value;	
			}	
            /*==============TravelGuru===============*/
            if($ota_name=="Travelguru")
            {
                if($key=='Wed')
                {
                    $key='Weds';
                } 
                if($key=='Thu')
                {
                    $key='Thur';
				} 
				$status="true";
				if($value==1)
				{
					$status="true";
				}
				if($value==0)
				{
					$status="false";
				}
				$rateplan_days_data.= ' '.$key.'="'.$status.'"';
			}
			if($ota_name=='EaseMyTrip')
			{
				
				$rateplan_days_data.= $value;	
			}
			
		}
        return $rateplan_days_data;
	}
	public function agodaDays($day)
	{
		$days_data=array("Mon"=>1,"Tue"=>2,"Wed"=>3,"Thu"=>4,"Fri"=>5,"Sat"=>6,"Sun"=>7);
		foreach($days_data as $key=>$value)
		{
			if($key==$day)
			{
				return $value;
			}
		}
	}
	public function goomoDays($rateplan_multiple_days)
	{
        $rateplan_multiple_days      = json_decode($rateplan_multiple_days);
		$rateplan_days_data=array();
		$prefix="";
		foreach($rateplan_multiple_days as $key=>$value)
		{
			if( $value==1)
			{
				$status="true";	
			}
			else
			{
				$status="false";		
			}
			array_push($rateplan_days_data,$status);
			
		}
		return $rateplan_days_data;
	}
	//Method to decide the Occupency prices as per max adult of the room
	public function decideOccupencyPrice($max_adult,$rateplan_bar_price,$rateplan_multiple_price)
	{
		$prices_array=array();
		if($max_adult>=3)
		{
			$prices_array[2]=$rateplan_bar_price;
			if(isset($rateplan_multiple_price[2]) && $rateplan_multiple_price[2] && $rateplan_multiple_price[2]!=0)
			{
				$prices_array[2]=$rateplan_multiple_price[2];
			}
			else
			{
				$prices_array[1]=$rateplan_bar_price;
			}
			if(isset($rateplan_multiple_price[1]) &&$rateplan_multiple_price[1] && $rateplan_multiple_price[1]!=0)
			{
				$prices_array[1]=$rateplan_multiple_price[1];
			}
			else
			{
				$prices_array[1]=$rateplan_bar_price;
			}
			if(isset($rateplan_multiple_price[0]) &&$rateplan_multiple_price[0] && $rateplan_multiple_price[0]!=0)
			{
				$prices_array[0]=$rateplan_multiple_price[0];
			}
			else
			{
				$prices_array[0]=$rateplan_bar_price;
			}
		}
		if($max_adult==2)
		{
			$prices_array[2]=0;
			$prices_array[1]=$rateplan_bar_price;
			if(isset($rateplan_multiple_price[0]) && $rateplan_multiple_price[0] && $rateplan_multiple_price[0]!=0)
			{
				$prices_array[0]=$rateplan_multiple_price[0];
			}
			else
			{
				$prices_array[0]=$rateplan_bar_price;
			}
		}
		if($max_adult==1)
		{
			$prices_array[2]=0;
			$prices_array[1]=0;
			if(isset($rateplan_multiple_price[0]) && $rateplan_multiple_price[0] && $rateplan_multiple_price[0]!=0)
			{
				$prices_array[0]=$rateplan_multiple_price[0];
			}
			else
			{
				$prices_array[0]=$rateplan_bar_price;
			}
		}
		return $prices_array;
	}
	 /*------------------- Cleartrip Update Function Start------------------------*/
	public function cleartripUpdate($bucket_data,$rateplan_data,$user_id)
	{
		 
			$cmOtaDetailsModel  			= new CmOtaDetails();
			$logModel                       = new LogTable();
			$cmOtaRoomTypeSynchronizeModel  = new CmOtaRoomTypeSynchronize();
			$cmOtaRatePlanSynchronizeModel 	= new CmOtaRatePlanSynchronize();
 
			 /*------------------ Get Bucket Data--------------------*/
			$bucket_id                      = $bucket_data['bucket_id'];
			$bucket_hotel_id                = $bucket_data['bucket_hotel_id'];
			$bucket_ota_id                  = $bucket_data['bucket_ota_id'];
			$bucket_ota_name                = $bucket_data['bucket_ota_name'];
			$bucket_rate_plan_log_table_id  = $bucket_data['bucket_rate_plan_log_table_id'];
			$bucket_ota_hotel_code          = $bucket_data['bucket_ota_hotel_code'];
			 
 
 
			 /*------------------ Get Rate Plan Log Data--------------------*/
			$rateplan_rate_plan_log_id   = $rateplan_data['rateplan_rate_plan_log_id'];
			$rateplan_hotel_id           = $rateplan_data['rateplan_hotel_id'];
			$rateplan_room_type_id       = $rateplan_data['rateplan_room_type_id'];
			$rateplan_rate_plan_id       = $rateplan_data['rateplan_rate_plan_id'];
			$rateplan_bar_price          = $rateplan_data['rateplan_bar_price'];
			$rateplan_multiple_occupancy = $rateplan_data['rateplan_multiple_occupancy'];
			$rateplan_date_from          = $rateplan_data['rateplan_date_from'];
			$rateplan_date_to            = $rateplan_data['rateplan_date_to'];
			$rateplan_client_ip          = $rateplan_data['rateplan_client_ip'];			
			$rateplan_multiple_days_data = $this->getDaysUpdate($rateplan_data['rateplan_multiple_days'],"Cleartrip");
			$rateplan_multiple_price     = json_decode($rateplan_multiple_occupancy);
			$max_adult					 = $rateplan_data['max_adult'];
			$extra_adult_price			 = $rateplan_data['extra_adult_price'];
			$extra_child_price			 = $rateplan_data['extra_child_price'];
			$occupency=$this->decideOccupencyPrice($max_adult,$rateplan_bar_price,$rateplan_multiple_price);
			$rateplan_single_price=0;
			$rateplan_double_price=0;
			$rateplan_triple_price=0;
			if($occupency)
			{
				$rateplan_single_price       = $occupency[0];
				$rateplan_double_price       = $occupency[1];
				$rateplan_triple_price       = $occupency[2];
			}
 
 
 
			 /*------------------ Start Date and End Date----------- */
			 $startDate                      = date('d/m/Y', strtotime($rateplan_date_from));
			 $endDate                        = date('d/m/Y', strtotime($rateplan_date_to));
 
			 /*------------------ Get Specific Ota Details-----------*/
 
			 $ota_details_data             	= CmOtaDetails::select('*')
												->where('hotel_id', '=' ,$bucket_hotel_id)
												->where('ota_id', '=' ,$bucket_ota_id)
												->first();	
 
			 $ota_id 					  	= $ota_details_data->ota_id;
			 $ota_hotel_code 			  	= $ota_details_data->ota_hotel_code;
			 $auth_parameter 			  	= json_decode($ota_details_data->auth_parameter);
			 $api_key            			= trim($auth_parameter->api_key);
			 $commonUrl      				= $ota_details_data->url;
 
			 /*------------------ set header ------------------ */
			 $headers = array (
			 //Regulates versioning of the XML interface for the API
			 'Content-Type: application/xml',
			 'X-CT-SOURCETYPE: API',
			 'X-CT-API-KEY: '.$api_key,
			 );
 
			 
			 $room_types 					= explode(",", $rateplan_room_type_id);
			 $rate_plan_id  					= $rateplan_rate_plan_id;
 
			 
 
			 foreach($room_types as $key => $room_type){
				$room_type_name=$this->getRoomTypeName($room_type);
			 $result 					= $cmOtaRatePlanSynchronizeModel->getOtaRoomIdFromRatePlanSynch($room_type,$ota_id,$rate_plan_id);
			 if(!empty($result)){
			 $log_data                 		= [
												 "action_id"          => 2,
												 "hotel_id"           => $bucket_hotel_id,
												 "ota_id"      		  => $bucket_ota_id,
												 "booking_ref_id"     => '',
												 "inventory_ref_id"   => '',
												 "rate_ref_id"        => $bucket_rate_plan_log_table_id,
												 "user_id"            => $user_id,
												 "request_msg"        => '',
												 "response_msg"       => '',
												 "request_url"        => '',
												 "status"         	 => 2,
												 "ip"         		 => $rateplan_client_ip,
												 "comment"			 => 'Processing for update'
												 ];
			 $room_code                    	= $result[0]['ota_room_type_id'];  
			 $rate_code                    	= $result[0]['ota_rate_plan_id'];	
			 $rate_name                    	= $result[0]['ota_rate_plan_name'];	
			 $rate_type                    	= $result[0]['ota_rate_type'];	
			 
			 $xml ='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
			 <hotel-room-rate xmlns="http://www.cleartrip.com/extranet/hotel-room-rate" type="update">
			 <hotel-id>'.$ota_hotel_code.'</hotel-id>
			 <room-type-id>'.$room_code.'</room-type-id>
			 <rate-id>'.$rate_code.'</rate-id>
			 <rate-name>'.$rate_name.'</rate-name>
			 <rate-type>SELL</rate-type>
			 <room-rates>
			 <room-rate>
			 <from-date>'.$startDate.'</from-date>
			 <to-date>'.$endDate.'</to-date>';
			 if($rateplan_single_price!=0){
			   $xml .= '<single-rate>'.$rateplan_single_price.'</single-rate>';
			 }
			 if($rateplan_double_price!=0){
			   $xml .= '<double-rate>'.$rateplan_double_price.'</double-rate>';
			 }
				$xml .= '<applicable-days>'.$rateplan_multiple_days_data.'</applicable-days>
			 </room-rate>
			 </room-rates>
			 </hotel-room-rate>';        
 
 
			 $log_request_msg = $xml;
			 $url = $commonUrl.'push-rate';
			 $logModel->fill($log_data)->save();
 
			 $ch = curl_init();
			 curl_setopt( $ch, CURLOPT_URL, $url );
			 curl_setopt( $ch, CURLOPT_POST, true );
			 curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
			 curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			 curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml);
			 $ota_rlt = curl_exec($ch);
			 curl_close($ch);
 
			 $resultXml=simplexml_load_string($ota_rlt);
			 if($resultXml){
				 $ary_data = json_decode(json_encode($resultXml), true); 
				$status =$ary_data['status']['code'];
				if (substr($status, 0, 1) === 'S') {
				   DB::table('log_table')->where('id', $logModel->id)
				   ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
				   $rlt[$room_type_name]=array('status'=>1,'response_msg'=>"updated successfully");
				}
				else{
				   DB::table('log_table')->where('id', $logModel->id)
				   ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
				   $rlt[$room_type_name]=array('status'=>0,'response_msg'=>$ota_rlt);
				}
			 }
			 else{
				$rlt[$room_type_name]=array('status'=>0,'response_msg'=>$ota_rlt);
				} 
			 }else{
			 // set log for Booking Room Type is not synch with hotel Room Type.
			 $log_data                 	= [
										 "action_id"          => 2,
										 "hotel_id"           => $bucket_hotel_id,
										 "ota_id"      		 => $bucket_ota_id,
										 "booking_ref_id"     => '',
										 "inventory_ref_id"   => '',
										 "rate_ref_id"        => $bucket_rate_plan_log_table_id,
										 "user_id"            => $user_id,
										 "request_msg"        => '',
										 "response_msg"       => '',
										 "request_url"        => '',
										 "status"         	 => 0,
										 "ip"         		 => $rateplan_client_ip,
										 "comment"			 => "This roomrate type is not mapped."
										 ];
			$logModel->fill($log_data)->save();
			$rlt[$room_type_name]=array('status'=>0,'response_msg'=>"Rateplan should be sync");
			} // If else !empty($result) closed here.
			} // foreach $room_types closed here.
			return $rlt;
	}
	 /*------------------- Cleartrip Update Function Close------------------------*/
	/*------------------- Agoda Update Function Start------------------------*/
    public function agodaUpdate($bucket_data,$rateplan_data,$user_id)
    {
            $date 							= new \DateTime();
            $dateTimestamp 					= $date->getTimestamp();

			$cmOtaDetailsModel  			= new CmOtaDetails();
			$logModel                       = new LogTable();
			$cmOtaRoomTypeSynchronizeModel  = new CmOtaRoomTypeSynchronize();
			$cmOtaRatePlanSynchronizeModel 	= new CmOtaRatePlanSynchronize();

			/*------------------ Get Bucket Data--------------------*/
			$bucket_id                      = $bucket_data['bucket_id'];
			$bucket_hotel_id                = $bucket_data['bucket_hotel_id'];
			$bucket_ota_id                  = $bucket_data['bucket_ota_id'];
			$bucket_ota_name                = $bucket_data['bucket_ota_name'];
			$bucket_rate_plan_log_table_id  = $bucket_data['bucket_rate_plan_log_table_id'];
			$bucket_ota_hotel_code          = $bucket_data['bucket_ota_hotel_code'];

			$currency=$this->getCurrency($bucket_hotel_id);


			/*------------------ Get Rate Plan Log Data--------------------*/
			$rateplan_rate_plan_log_id   = $rateplan_data['rateplan_rate_plan_log_id'];
	        $rateplan_hotel_id           = $rateplan_data['rateplan_hotel_id'];
	        $rateplan_room_type_id       = $rateplan_data['rateplan_room_type_id'];
	        $rateplan_rate_plan_id       = $rateplan_data['rateplan_rate_plan_id'];
	        $rateplan_bar_price          = $rateplan_data['rateplan_bar_price'];
	        $rateplan_multiple_occupancy = $rateplan_data['rateplan_multiple_occupancy'];
	        $rateplan_date_from          = $rateplan_data['rateplan_date_from'];
	        $rateplan_date_to            = $rateplan_data['rateplan_date_to'];
	        $rateplan_client_ip          = $rateplan_data['rateplan_client_ip'];
			$extra_adult_price           = $rateplan_data['extra_adult_price'];
			$rateplan_los          		 = $rateplan_data['rateplan_los'];
			$rateplan_multiple_days_data = $this->getDaysUpdate($rateplan_data['rateplan_multiple_days'],"Agoda");
	        $rateplan_multiple_price     = json_decode($rateplan_multiple_occupancy);
			$max_adult					 = $rateplan_data['max_adult'];
			$extra_child_price			 = $rateplan_data['extra_child_price'];
			$occupency=$this->decideOccupencyPrice($max_adult,$rateplan_bar_price,$rateplan_multiple_price);
			$rateplan_single_price=0;
			$rateplan_double_price=0;
			$rateplan_triple_price=0;
			if($occupency)
			{
				$rateplan_single_price       = $occupency[0];
				$rateplan_double_price       = $occupency[1];
				$rateplan_triple_price       = $occupency[2];
			}


			/*------------------ Start Date and End Date----------- */
			$startDate                      = $rateplan_date_from;
        	$endDate                        = $rateplan_date_to; 

			/*------------------ Get Specific Ota Details-----------*/

			$ota_details_data             	= CmOtaDetails::select('*')
												->where('hotel_id', '=' ,$bucket_hotel_id)
												->where('ota_id', '=' ,$bucket_ota_id)
												->first();		

			$ota_id 					  	= $ota_details_data->ota_id;
			$ota_hotel_code 			  	= $ota_details_data->ota_hotel_code;
			$auth_parameter 			  	= json_decode($ota_details_data->auth_parameter);
            $apiKey            			 	= trim($auth_parameter->apiKey);
            $commonUrl      				= $ota_details_data->url;

            /*------------------ set header ------------------ */
			$headers = array (
			//Regulates versioning of the XML interface for the API
			'Content-Type: application/xml'
			);

			
			$room_types 					= explode(",", $rateplan_room_type_id);
			$rate_plan_id  					= $rateplan_rate_plan_id;

			

			foreach($room_types as $key => $room_type){				
				$room_type_name=$this->getRoomTypeName($room_type);
			$result 					=  $cmOtaRatePlanSynchronizeModel->getOtaRoomIdFromRatePlanSynch($room_type,$ota_id,$rate_plan_id);
			if(!empty($result)){

			$log_data               	= [
										"action_id"          => 2,
										"hotel_id"           => $bucket_hotel_id,
										"ota_id"      		 => $bucket_ota_id,
										"booking_ref_id"     => '',
										"inventory_ref_id"   => '',
										"rate_ref_id"        => $bucket_rate_plan_log_table_id,
										"user_id"            => $user_id,
										"request_msg"        => '',
										"response_msg"       => '',
										"request_url"        => '',
										"status"         	 => 2,
										"ip"         		 => $rateplan_client_ip,
										"comment"			 => "Processing for update "
										];
			$room_code                  = $result[0]['ota_room_type_id'];  
			$rate_code                  = $result[0]['ota_rate_plan_id'];	


			$xml = '<?xml version="1.0" encoding="UTF-8"?>
			<request timestamp="'.$dateTimestamp.'" type="1">
			<criteria property_id="'.$ota_hotel_code.'">
			<rate>
			<update room_id="'.$room_code.'" rateplan_id="'.$rate_code.'">
			<date_range from="'.$startDate.'" to="'.$endDate.'">
			'.$rateplan_multiple_days_data.'
			</date_range>
			<prices currency="'.$currency.'">
			<occupancy>';
			if($rateplan_single_price){
			$xml .='<single>'.$rateplan_single_price.'</single>';
			}
			if($rateplan_double_price){
			$xml .='<double>'.$rateplan_double_price.'</double>';
			}
			if($max_adult>2)
			{
				$xml .='<full>'.$rateplan_triple_price.'</full>';
			}
			$xml .='</occupancy>';

			if($extra_adult_price!=0 && $extra_adult_price!='')
             {
             	$xml .='<extra_bed>'.$extra_adult_price.'</extra_bed>';
            
             }             
			$xml .='</prices>
			<restrictions>
			<closed>false</closed>
			<los>
			<min>'.$rateplan_los.'</min>
			</los>
            </restrictions>
			</update>
			</rate>
			</criteria>
			</request>';

			$log_request_msg = $xml;
			$url 	= $commonUrl.'api?apiKey='.$apiKey;
			$logModel->fill($log_data)->save();
			
			$ch 	= curl_init();
			curl_setopt( $ch, CURLOPT_URL, $url );
			curl_setopt( $ch, CURLOPT_POST, true );
			curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml);
			$ota_rlt = curl_exec($ch);
			curl_close($ch);
			$resultXml=simplexml_load_string($ota_rlt);
			if($resultXml)
			{
				$array_data = json_decode(json_encode($resultXml), true);
				if(!isset($array_data['errors'])){
					DB::table('log_table')->where('id', $logModel->id)
					->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
					$rlt[$room_type_name]=array('status'=>1,'response_msg'=>"updated successfully");
				}else{
					DB::table('log_table')->where('id', $logModel->id)
					->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
					if(isset($array_data['errors']['property']))
					{
						$res_msg="";
						if(isset($array_data['errors']['property']['error']) && is_array($array_data['errors']['property']['error']))
						{
							/*foreach($array_data['errors']['property']['error'] as $error){
								$res_msg.=$error['@attributes']['description'];
							}*/
							$res_msg=$array_data['errors']['property']['error']['@attributes']['description'];

						}
						else{
							$res_msg=$array_data['errors']['property']['error']['@attributes']['description'];
						}
						$rlt[$room_type_name]=array('status'=>0,'response_msg'=>$res_msg);
					}
					else{
						$rlt[$room_type_name]=array('status'=>0,'response_msg'=>$array_data['errors']['error']['@attributes']['description']);
					}
				}
			}
			else{
				$rlt[$room_type_name]=array('status'=>0,'response_msg'=>$ota_rlt);
				}
			}else{
			// set log for Booking Room Type is not synch with hotel Room Type.
			$log_data                 	= [
										"action_id"          => 2,
										"hotel_id"           => $bucket_hotel_id,
										"ota_id"      		 => $bucket_ota_id,
										"booking_ref_id"     => '',
										"inventory_ref_id"   => '',
										"rate_ref_id"        => $bucket_rate_plan_log_table_id,
										"user_id"            => $user_id,
										"request_msg"        => '',
										"response_msg"       => '',
										"request_url"        => '',
										"status"         	 => 0,
										"ip"         		 => $rateplan_client_ip,
										"comment"			 => "This roomrate type is not mapped."
										];
			$logModel->fill($log_data)->save();

			$rlt[$room_type_name]=array('status'=>0,'response_msg'=>"Rateplan should be sync");
			} // If else !empty($result) closed here.
		} // foreach $room_types closed here.
			return $rlt;
	}
    /*------------------- Goibibo Update Function Start------------------------*/
    public function goibiboUpdate($bucket_data,$rateplan_data,$user_id)
    {
            $cmOtaDetailsModel  			= new CmOtaDetails();
            $logModel                       = new LogTable();
			$cmOtaRoomTypeSynchronizeModel  = new CmOtaRoomTypeSynchronize();
			$cmOtaRatePlanSynchronizeModel 	= new CmOtaRatePlanSynchronize();

			/*------------------ Get Bucket Data--------------------*/
			$bucket_id                      = $bucket_data['bucket_id'];
			$bucket_hotel_id                = $bucket_data['bucket_hotel_id'];
			$bucket_ota_id                  = $bucket_data['bucket_ota_id'];
			$bucket_ota_name                = $bucket_data['bucket_ota_name'];
			$bucket_rate_plan_log_table_id  = $bucket_data['bucket_rate_plan_log_table_id'];
			$bucket_ota_hotel_code          = $bucket_data['bucket_ota_hotel_code'];
			$currency=$this->getCurrency($bucket_hotel_id);
			

            
			/*------------------ Get Rate Plan Log Data--------------------*/
			$rateplan_rate_plan_log_id   = $rateplan_data['rateplan_rate_plan_log_id'];
	        $rateplan_hotel_id           = $rateplan_data['rateplan_hotel_id'];
	        $rateplan_room_type_id       = $rateplan_data['rateplan_room_type_id'];
	        $rateplan_rate_plan_id       = $rateplan_data['rateplan_rate_plan_id'];
	        $rateplan_bar_price          = $rateplan_data['rateplan_bar_price'];
            $rateplan_multiple_occupancy = $rateplan_data['rateplan_multiple_occupancy'];
	        $rateplan_date_from          = $rateplan_data['rateplan_date_from'];
	        $rateplan_date_to            = $rateplan_data['rateplan_date_to'];
	        $rateplan_client_ip          = $rateplan_data['rateplan_client_ip'];
			$rateplan_multiple_price     = json_decode($rateplan_multiple_occupancy);
			$max_adult					 = $rateplan_data['max_adult'];
			$extra_adult_price			 = $rateplan_data['extra_adult_price'];
			$extra_child_price			 = $rateplan_data['extra_child_price'];
			$occupency=$this->decideOccupencyPrice($max_adult,$rateplan_bar_price,$rateplan_multiple_price);
			$rateplan_single_price=0;
			$rateplan_double_price=0;
			$rateplan_triple_price=0;
			if($occupency)
			{
				$rateplan_single_price       = $occupency[0];
				$rateplan_double_price       = $occupency[1];
				$rateplan_triple_price       = $occupency[2];
			}
            $rateplan_multiple_days_data = $this->getDaysUpdate($rateplan_data['rateplan_multiple_days'],"Goibibo");

			/*------------------ Start Date and End Date----------- */
			$startDate                      = $rateplan_date_from;
        	$endDate                        = $rateplan_date_to; 

			/*------------------ Get Specific Ota Details-----------*/

			$ota_details_data             	=  CmOtaDetails::select('*')
                                                ->where('hotel_id', '=' ,$bucket_hotel_id)
                                                ->where('ota_id', '=' ,$bucket_ota_id)
                                                ->first();  	

			$ota_id 					  	= $ota_details_data->ota_id;
			$ota_hotel_code 			  	= $ota_details_data->ota_hotel_code;
			$auth_parameter 			  	= json_decode($ota_details_data->auth_parameter);
			$bearer_token 					= trim($auth_parameter->bearer_token);
			$channel_token 					= trim($auth_parameter->channel_token);
            $commonUrl      				= $ota_details_data->url;

            /*------------------ set header ------------------ */
			$headers = array (
			//Regulates versioning of the XML interface for the API
			'Content-Type: application/xml'
			);
			
			$room_types 					= explode(",", $rateplan_room_type_id);
			$rate_plan_id  					= $rateplan_rate_plan_id;

			

			foreach($room_types as $key => $room_type){
				$room_type_name=$this->getRoomTypeName($room_type);
			$result 					= $cmOtaRatePlanSynchronizeModel->getOtaRoomIdFromRatePlanSynch($room_type,$ota_id,$rate_plan_id);
            if(!empty($result)){
			$log_data               	= [
										"action_id"          => 2,
										"hotel_id"           => $bucket_hotel_id,
										"ota_id"      		 => $bucket_ota_id,
										"booking_ref_id"     => '',
										"inventory_ref_id"   => '',
										"rate_ref_id"        => $bucket_rate_plan_log_table_id,
										"user_id"            => '',
										"request_msg"        => $user_id,
										"response_msg"       => '',
										"request_url"        => '',
										"status"         	 => 2,
										"ip"         		 => $rateplan_client_ip,
										"comment"			 => "Processing for update "
										];
			$room_code                  = $result[0]['ota_room_type_id'];  
            $rate_code                  = $result[0]['ota_rate_plan_id'];               


            $xml ='<?xml version="1.0" encoding="UTF-8" ?>
					<Website Name="ingoibibo" HotelCode="'.$ota_details_data->ota_hotel_code.'">
						<RatePlan>
							<RatePlanCode>'.$rate_code.'</RatePlanCode>
							<StartDate Format="yyyy-mm-dd">'.$startDate.'</StartDate>
							<EndDate Format="yyyy-mm-dd">'.$endDate.'</EndDate>';
							if($rateplan_single_price!=0)
							{
								$xml .='<SingleOccupancyRates>                      
										<SellRate>'.$rateplan_single_price.'</SellRate>
									</SingleOccupancyRates>';
							}
							if($rateplan_double_price!=0)
							{
								$xml .='<DoubleOccupancyRates>                      
									<SellRate>'.$rateplan_double_price.'</SellRate>
								</DoubleOccupancyRates>';
							}
							if($rateplan_triple_price!=0)
							{
								$xml .='<TripleOccupancyRates>
									<SellRate>'.$rateplan_triple_price.'</SellRate>
								</TripleOccupancyRates>';
							}
							
							if($extra_adult_price && $extra_child_price)
							{
								$xml .='<ExtraChildCharge>'.$extra_child_price.'</ExtraChildCharge>
										<ExtraAdultCharge>'.$extra_adult_price.'</ExtraAdultCharge>';
							}
								$xml .='<DaysOfWeek'.$rateplan_multiple_days_data.'></DaysOfWeek>
									<Currency>'.$currency.'</Currency>
						</RatePlan>
					</Website>';
			$log_request_msg = $xml;
			$url = $commonUrl.'updateroomrates/?bearer_token='.$bearer_token.'&channel_token='.$channel_token;
            $logModel->fill($log_data)->save();

			$ch = curl_init();
			curl_setopt( $ch, CURLOPT_URL, $url );
			curl_setopt( $ch, CURLOPT_POST, true );
			curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml);
			$ota_rlt = curl_exec($ch);
			curl_close($ch);
			if($ota_rlt=='OAuth Authorization Required'){
				DB::table('log_table')->where('id', $logModel->id)
					->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
					$rlt[$room_type_name]=array('status'=>0,'response_msg'=>$ota_rlt);
					return $rlt;
			}
			$resultXml=simplexml_load_string($ota_rlt);
			if($resultXml){
				$array_data = json_decode(json_encode($resultXml), true);
				if(!isset($array_data['Error'])){
					DB::table('log_table')->where('id', $logModel->id)
					->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
					$rlt[$room_type_name]=array('status'=>1,'response_msg'=>$ota_rlt);
				}
				else
				{
					DB::table('log_table')->where('id', $logModel->id)
					->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
					$rlt[$room_type_name]=array('status'=>0,'response_msg'=>$ota_rlt);
				}
			} 
			else{
				$rlt[$room_type_name]=array('status'=>0,'response_msg'=>$ota_rlt);
			}               
			}else{
			// set log for Booking Room Type is not synch with hotel Room Type.
			$log_data                 	= [
										"action_id"          => 2,
										"hotel_id"           => $bucket_hotel_id,
										"ota_id"      		 => $bucket_ota_id,
										"booking_ref_id"     => '',
										"inventory_ref_id"   => '',
										"rate_ref_id"        => $bucket_rate_plan_log_table_id,
										"user_id"            => $user_id,
										"request_msg"        => '',
										"response_msg"       => '',
										"request_url"        => '',
										"status"         	 => 0,
										"ip"         		 => $rateplan_client_ip,
										"comment"			 => 'This roomrate type is not mapped'
										];
             $logModel->fill($log_data)->save();
			 $rlt[$room_type_name]=array('status'=>0,'response_msg'=>"Rateplan should be sync");
			} // If else !empty($result) closed here.
			} // foreach $room_types closed here.
			return $rlt;
        }
	/*------------------- Goibibo Update Function End------------------------*/
	/*------------------- Expedia Update Function Start------------------------*/
    public function expediaUpdate($bucket_data,$rateplan_data,$user_id)
    {
			$cmOtaDetailsModel  			= new CmOtaDetails();
			$logModel                       = new LogTable();
			$cmOtaRoomTypeSynchronizeModel  = new CmOtaRoomTypeSynchronize();
			$cmOtaRatePlanSynchronizeModel 	= new CmOtaRatePlanSynchronize();
			
			/*------------------ Get Bucket Data--------------------*/
			$bucket_id                      = $bucket_data['bucket_id'];
			$bucket_hotel_id                = $bucket_data['bucket_hotel_id'];
			$bucket_ota_id                  = $bucket_data['bucket_ota_id'];
			$bucket_ota_name                = $bucket_data['bucket_ota_name'];
			$bucket_rate_plan_log_table_id  = $bucket_data['bucket_rate_plan_log_table_id'];
			$bucket_ota_hotel_code          = $bucket_data['bucket_ota_hotel_code'];
			$currency=$this->getCurrency($bucket_hotel_id);


			/*------------------ Get Rate Plan Log Data--------------------*/
			$rateplan_rate_plan_log_id   = $rateplan_data['rateplan_rate_plan_log_id'];
	        $rateplan_hotel_id           = $rateplan_data['rateplan_hotel_id'];
	        $rateplan_room_type_id       = $rateplan_data['rateplan_room_type_id'];
	        $rateplan_rate_plan_id       = $rateplan_data['rateplan_rate_plan_id'];
	        $rateplan_bar_price          = $rateplan_data['rateplan_bar_price'];
	        $rateplan_multiple_occupancy = $rateplan_data['rateplan_multiple_occupancy'];
	        $rateplan_date_from          = $rateplan_data['rateplan_date_from'];
	        $rateplan_date_to            = $rateplan_data['rateplan_date_to'];
			$rateplan_client_ip          = $rateplan_data['rateplan_client_ip'];
			$rateplan_los          		 = $rateplan_data['rateplan_los'];
			$rateplan_multiple_price     = json_decode($rateplan_multiple_occupancy);
			$rateplan_multiple_days_data = $this->getDaysUpdate($rateplan_data['rateplan_multiple_days'],"Expedia");
	        $max_adult					 = $rateplan_data['max_adult'];
			$extra_adult_price			 = $rateplan_data['extra_adult_price'];
			$extra_child_price			 = $rateplan_data['extra_child_price'];
			$occupency=$this->decideOccupencyPrice($max_adult,$rateplan_bar_price,$rateplan_multiple_price);
			$rateplan_single_price=0;
			$rateplan_double_price=0;
			$rateplan_triple_price=0;
			if($occupency)
			{
				$rateplan_single_price       = $occupency[0];
				$rateplan_double_price       = $occupency[1];
				$rateplan_triple_price       = $occupency[2];
			}

			/*------------------ Start Date and End Date----------- */
			$startDate                      = $rateplan_date_from;
        	$endDate                        = $rateplan_date_to; 

			/*------------------ Get Specific Ota Details-----------*/
			$ota_details_data             	= CmOtaDetails::select('*')
												->where('hotel_id', '=' ,$bucket_hotel_id)
												->where('ota_id', '=' ,$bucket_ota_id)
												->first();	

			$ota_id 					  	= $ota_details_data->ota_id;
			$ota_hotel_code 			  	= $ota_details_data->ota_hotel_code;
			$auth_parameter 			  	= json_decode($ota_details_data->auth_parameter);
			$username 						= trim($auth_parameter->username);
			$password 						= trim($auth_parameter->password);
            $commonUrl      				= $ota_details_data->url;

            /*------------------ set header ------------------ */
			$headers = array (
			//Regulates versioning of the XML interface for the API
			'Content-Type: application/xml'
			);
			
			$room_types 					= explode(",", $rateplan_room_type_id);
			$rate_plan_id  					= $rateplan_rate_plan_id;

			

			foreach($room_types as $key => $room_type){
				$room_type_name=$this->getRoomTypeName($room_type);
			$result 					=  $cmOtaRatePlanSynchronizeModel->getOtaRoomIdFromRatePlanSynch($room_type,$ota_id,$rate_plan_id);
			if(!empty($result)){
			$log_data               	= [
										"action_id"          => 2,
										"hotel_id"           => $bucket_hotel_id,
										"ota_id"      		 => $bucket_ota_id,
										"booking_ref_id"     => '',
										"inventory_ref_id"   => '',
										"rate_ref_id"        => $bucket_rate_plan_log_table_id,
										"user_id"            => $user_id,
										"request_msg"        => '',
										"response_msg"       => '',
										"request_url"        => '',
										"status"         	 => 2,
										"ip"         		 => $rateplan_client_ip,
										"comment"			 => "Processing for update "
										];
			$room_code                  = $result[0]['ota_room_type_id'];  
			$rate_code                  = $result[0]['ota_rate_plan_id'];   
			
			$xml = '<?xml version="1.0" encoding="UTF-8"?>        
			<AvailRateUpdateRQ xmlns="http://www.expediaconnect.com/EQC/AR/2011/06">
			<Authentication username="'.$username.'" password="'.$password.'"/>
			<Hotel id="'.$ota_hotel_code.'"/>
			<AvailRateUpdate>
			<DateRange from="'.$startDate.'" to="'.$endDate.'" '.$rateplan_multiple_days_data.' />
			<RoomType id="'.$room_code.'">
			<RatePlan id="'.$rate_code.'" closed="false">
			<Rate currency="'.$currency.'">';
			if($rateplan_single_price!=0){
			$xml .='<PerOccupancy rate="'.$rateplan_single_price.'" occupancy="1"/>';

			}
			if($rateplan_double_price!=0){
			$xml .='<PerOccupancy rate="'.$rateplan_double_price.'" occupancy="2"/>';
			}
			if($rateplan_triple_price!=0){
				$xml .='<PerOccupancy rate="'.$rateplan_double_price.'" occupancy="3"/>';
				}
			$xml .='</Rate>
			<Restrictions minLOS="'.$rateplan_los.'" />

			</RatePlan>
			</RoomType>
			</AvailRateUpdate>
			</AvailRateUpdateRQ>';
			$log_request_msg = $xml;
			$url  = $commonUrl.'eqc/ar';
			$logModel->fill($log_data)->save();

			$ch = curl_init();
			curl_setopt( $ch, CURLOPT_URL, $url );
			curl_setopt( $ch, CURLOPT_POST, true );
			curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml);

			if( curl_exec($ch) === false ){
			echo $result = curl_error($ch);
			}else{
			$result = curl_exec($ch);
			}
			curl_close($ch);
			$resultXml=simplexml_load_string($result);
			if($resultXml){
				$array_data = json_decode(json_encode($resultXml), true);
				if(!isset($array_data['Error'])){
				DB::table('log_table')->where('id', $logModel->id)
					->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
					$rlt[$room_type_name]=array('status'=>1,'response_msg'=>'updated successfully');
				}else{
					DB::table('log_table')->where('id', $logModel->id)
					->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
					$rlt[$room_type_name]=array('status'=>0,'response_msg'=>$result);
				}
			}
			else{
				$rlt[$room_type_name]=array('status'=>0,'response_msg'=>$result);
			}

			}else{
			// set log for Booking Room Type is not synch with hotel Room Type.
			$log_data                 	= [
										"action_id"          => 2,
										"hotel_id"           => $bucket_hotel_id,
										"ota_id"      		 => $bucket_ota_id,
										"booking_ref_id"     => '',
										"inventory_ref_id"   => '',
										"rate_ref_id"        => $bucket_rate_plan_log_table_id,
										"user_id"            => $user_id,
										"request_msg"        => '',
										"response_msg"       => '',
										"request_url"        => '',
										"status"         	 => 0,
										"ip"         		 => $rateplan_client_ip,
										"comment"			 => "This roomrate type is not mapped."
										];
			$logModel->fill($log_data)->save();
			$rlt[$room_type_name]=array('status'=>0,'response_msg'=>"Rateplan should be sync");
			} // If else !empty($result) closed here.
			} // foreach $room_types closed here.
			return $rlt; 
      }
    /*------------------- Expedia Update Function End------------------------*/


    /*------------------- Booking.com Update Function Start------------------------*/
    public function bookingdotcomUpdate($bucket_data,$rateplan_data,$user_id)
    {
			$cmOtaDetailsModel  			= new CmOtaDetails();
			$logModel                       = new LogTable();
			$cmOtaRoomTypeSynchronizeModel  = new CmOtaRoomTypeSynchronize();
			$cmOtaRatePlanSynchronizeModel 	= new CmOtaRatePlanSynchronize();

			/*------------------ Get Bucket Data--------------------*/
			$bucket_id                      = $bucket_data['bucket_id'];
			$bucket_hotel_id                = $bucket_data['bucket_hotel_id'];
			$bucket_ota_id                  = $bucket_data['bucket_ota_id'];
			$bucket_ota_name                = $bucket_data['bucket_ota_name'];
			$bucket_rate_plan_log_table_id  = $bucket_data['bucket_rate_plan_log_table_id'];
			$bucket_ota_hotel_code          = $bucket_data['bucket_ota_hotel_code'];
			$currency=$this->getCurrency($bucket_hotel_id);


			/*------------------ Get Rate Plan Log Data--------------------*/
			$rateplan_rate_plan_log_id   = $rateplan_data['rateplan_rate_plan_log_id'];
	        $rateplan_hotel_id           = $rateplan_data['rateplan_hotel_id'];
	        $rateplan_room_type_id       = $rateplan_data['rateplan_room_type_id'];
	        $rateplan_rate_plan_id       = $rateplan_data['rateplan_rate_plan_id'];
	        $rateplan_bar_price          = $rateplan_data['rateplan_bar_price'];
	        $rateplan_multiple_occupancy = $rateplan_data['rateplan_multiple_occupancy'];
	        $rateplan_date_from          = $rateplan_data['rateplan_date_from'];
	        $rateplan_date_to            = $rateplan_data['rateplan_date_to'];
	        $rateplan_client_ip          = $rateplan_data['rateplan_client_ip'];
	        $rateplan_los          		 = $rateplan_data['rateplan_los'];
			$rateplan_multiple_price     = json_decode($rateplan_multiple_occupancy);
			$rateplan_multiple_days_data = $this->getDaysUpdate($rateplan_data['rateplan_multiple_days'],"Booking.com");
	        $max_adult					 = $rateplan_data['max_adult'];
			$extra_adult_price			 = $rateplan_data['extra_adult_price'];
			$extra_child_price			 = $rateplan_data['extra_child_price'];
			$occupency=$this->decideOccupencyPrice($max_adult,$rateplan_bar_price,$rateplan_multiple_price);
			$rateplan_single_price=0;
			$rateplan_double_price=0;
			$rateplan_triple_price=0;
			if($occupency)
			{
				$rateplan_single_price       = $occupency[0];
				$rateplan_double_price       = $occupency[1];
				$rateplan_triple_price       = $occupency[2];
			}
			/*------------------ Start Date and End Date----------- */
			$startDate                      = date('Y-m-d',strtotime($rateplan_date_from));
			$endDate    					= date('Y-m-d', strtotime('+1 days', strtotime($rateplan_date_to)));

	        $date1 							= date_create($startDate);
	        $date2 							= date_create($endDate);
	        $diff  							= date_diff($date1,$date2);
	        $check_date_diff 				= $diff->format("%a"); 

			/*------------------ Get Specific Ota Details-----------*/

			$ota_details_data             	= CmOtaDetails::select('*')
												->where('hotel_id', '=' ,$bucket_hotel_id)
												->where('ota_id', '=' ,$bucket_ota_id)
												->first();	


			$ota_id 					  	= $ota_details_data->ota_id;
			$ota_hotel_code 			  	= $ota_details_data->ota_hotel_code;
			$auth_parameter 			  	= json_decode($ota_details_data->auth_parameter);
			$username 						= trim($auth_parameter->username);
			$password 						= trim($auth_parameter->password);
            $commonUrl      				= $ota_details_data->url;

            /*------------------ set header ------------------ */
			$headers = array (
			//Regulates versioning of the XML interface for the API
			'Content-Type: application/xml'
			);
			
			$room_types 					= explode(",", $rateplan_room_type_id);
			$rate_plan_id  					= $rateplan_rate_plan_id;

			

			foreach($room_types as $key => $room_type){
				$room_type_name=$this->getRoomTypeName($room_type);
			$result 				= $cmOtaRatePlanSynchronizeModel->getOtaRoomIdFromRatePlanSynch($room_type,$ota_id,$rate_plan_id);
			if(!empty($result)){
			$log_data               	= [
										"action_id"          => 2,
										"hotel_id"           => $bucket_hotel_id,
										"ota_id"      		 => $bucket_ota_id,
										"booking_ref_id"     => '',
										"inventory_ref_id"   => '',
										"rate_ref_id"        => $bucket_rate_plan_log_table_id,
										"user_id"            => $user_id,
										"request_msg"        => '',
										"response_msg"       => '',
										"request_url"        => '',
										"status"         	 => 2,
										"ip"         		 => $rateplan_client_ip,
										"comment"			 => "Processing for update "
										];
			$room_code                  = $result[0]['ota_room_type_id'];  
			$rate_code                  = $result[0]['ota_rate_plan_id'];

			
			$xml  = '<request>
						<username>'.$username.'</username>
						<password>'.$password.'</password>
						<hotel_id>'.$ota_hotel_code.'</hotel_id>
						<version>1.0</version>
						<room id="'.$room_code.'">
						<date from="'.$startDate.'" to="'.$endDate.'">
						<currencycode>'.$currency.'</currencycode>
						<rate id="'.$rate_code.'"/>';
						if($rateplan_single_price!=0){
						$xml .='<price1>'.$rateplan_single_price .'</price1>';
						}
						if($rateplan_bar_price!=0){         
						$xml .='<price>'.$rateplan_bar_price.'</price>';
						}
						$xml .='</date>
						</room>
					</request>';
			//dd($xml);
			$log_request_msg = $xml;
			$url         	  = $commonUrl.'availability';
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
			if($resultXml){
				$array_data = json_decode(json_encode(simplexml_load_string($result)), true);
				if(strpos($result, '<error>' ) !== false){
					DB::table('log_table')->where('id', $logModel->id)
					->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
					$rlt[$room_type_name]=array('status'=>0,'response_msg'=>$result);
				}else if(strpos($result, '<warning>' ) !== false){
					DB::table('log_table')->where('id', $logModel->id)
					->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
					$rlt[$room_type_name]=array('status'=>0,'response_msg'=>$result);
					}	
				}
				else{
					if(strpos($result, '<ok>' ) !== false)
					{
						DB::table('log_table')->where('id', $logModel->id)
						->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
						$rlt[$room_type_name]=array('status'=>1,'response_msg'=>"updated sucessfully");
					}
					else{
						$rlt[$room_type_name]=array('status'=>0,'response_msg'=>$result);
					}
				}		    
			}else{
			// set log for Booking Room Type is not synch with hotel Room Type.
			$log_data                 	= [
										"action_id"          => 2,
										"hotel_id"           => $bucket_hotel_id,
										"ota_id"      		 => $bucket_ota_id,
										"booking_ref_id"     => '',
										"inventory_ref_id"   => '',
										"rate_ref_id"        => $bucket_rate_plan_log_table_id,
										"user_id"            => $user_id,
										"request_msg"        => '',
										"response_msg"       => '',
										"request_url"        => '',
										"status"         	 => 0,
										"ip"         		 => $rateplan_client_ip,
										"comment"			 => "This roomrate type is not mapped."
										];
			$logModel->fill($log_data)->save();
			$rlt[$room_type_name]=array('status'=>0,'response_msg'=>"Rateplan should be sync");
			} // If else !empty($result) closed here.
			} // foreach $room_types closed here.
			return $rlt;
	}

    /*------------------- Booking.com Update Function End------------------------*/

    /*------------------- Via.com Update Function Start------------------------*/
    public function viadotcomUpdate($bucket_data,$rateplan_data,$user_id)
    {
					

			$cmOtaDetailsModel  			= new CmOtaDetails();
			$logModel                       = new LogTable();
			$cmOtaRoomTypeSynchronizeModel  = new CmOtaRoomTypeSynchronize();
			$cmOtaRatePlanSynchronizeModel 	= new CmOtaRatePlanSynchronize();

			/*------------------ Get Bucket Data--------------------*/
			$bucket_id                      = $bucket_data['bucket_id'];
			$bucket_hotel_id                = $bucket_data['bucket_hotel_id'];
			$bucket_ota_id                  = $bucket_data['bucket_ota_id'];
			$bucket_ota_name                = $bucket_data['bucket_ota_name'];
			$bucket_rate_plan_log_table_id  = $bucket_data['bucket_rate_plan_log_table_id'];
			$bucket_ota_hotel_code          = $bucket_data['bucket_ota_hotel_code'];
			


			/*------------------ Get Rate Plan Log Data--------------------*/
			$rateplan_rate_plan_log_id   = $rateplan_data['rateplan_rate_plan_log_id'];
	        $rateplan_hotel_id           = $rateplan_data['rateplan_hotel_id'];
	        $rateplan_room_type_id       = $rateplan_data['rateplan_room_type_id'];
	        $rateplan_rate_plan_id       = $rateplan_data['rateplan_rate_plan_id'];
	        $rateplan_bar_price          = $rateplan_data['rateplan_bar_price'];
	        $rateplan_multiple_occupancy = $rateplan_data['rateplan_multiple_occupancy'];
	        $rateplan_date_from          = $rateplan_data['rateplan_date_from'];
	        $rateplan_date_to            = $rateplan_data['rateplan_date_to'];
	        $rateplan_client_ip          = $rateplan_data['rateplan_client_ip'];
	        $extra_adult_price           = $rateplan_data['extra_adult_price'];
			$extra_child_price           = $rateplan_data['extra_child_price'];
			$rateplan_multiple_days_data = $this->getDaysUpdate($rateplan_data['rateplan_multiple_days'],"Via.com");
	        $rateplan_multiple_price     = json_decode($rateplan_multiple_occupancy);
			$max_adult					 = $rateplan_data['max_adult'];
			$occupency=$this->decideOccupencyPrice($max_adult,$rateplan_bar_price,$rateplan_multiple_price);
			$rateplan_single_price=0;
			$rateplan_double_price=0;
			$rateplan_triple_price=0;
			if($occupency)
			{
				$rateplan_single_price       = $occupency[0];
				$rateplan_double_price       = $occupency[1];
				$rateplan_triple_price       = $occupency[2];
			}


			/*------------------ Start Date and End Date----------- */
			$startDate                      = $rateplan_date_from;
        	$endDate                        = $rateplan_date_to; 

			/*------------------ Get Specific Ota Details-----------*/

			$ota_details_data             	= CmOtaDetails::select('*')
													->where('hotel_id', '=' ,$bucket_hotel_id)
													->where('ota_id', '=' ,$bucket_ota_id)
													->first(); 	

			$ota_id 					  	= $ota_details_data->ota_id;
			$ota_hotel_code 			  	= $ota_details_data->ota_hotel_code;
			$auth_parameter 			  	= json_decode($ota_details_data->auth_parameter);
			$source 						= trim($auth_parameter->source);
			$auth 							= trim($auth_parameter->auth);
            $commonUrl      				= $ota_details_data->url;

            /*------------------ set header ------------------ */
			$headers = array('Content-Type:application/json', 'Expect:');
			
			$room_types 					= explode(",", $rateplan_room_type_id);
			$rate_plan_id  					= $rateplan_rate_plan_id;

			

			foreach($room_types as $key => $room_type){
				$room_type_name=$this->getRoomTypeName($room_type);
			$result 				= $cmOtaRatePlanSynchronizeModel->getOtaRoomIdFromRatePlanSynch($room_type,$ota_id,$rate_plan_id);

			if(!empty($result)){
			$log_data               	= [
										"action_id"          => 2,
										"hotel_id"           => $bucket_hotel_id,
										"ota_id"      		 => $bucket_ota_id,
										"booking_ref_id"     => '',
										"inventory_ref_id"   => '',
										"rate_ref_id"        => $bucket_rate_plan_log_table_id,
										"user_id"            => $user_id,
										"request_msg"        => '',
										"response_msg"       => '',
										"request_url"        => '',
										"status"         	 => 2,
										"ip"         		 => $rateplan_client_ip,
										"comment"			 => "Processing for update "
										];
			$room_code                  = $result[0]['ota_room_type_id'];  
			$rate_code                  = $result[0]['ota_rate_plan_id'];
            $rate_type                  = $result[0]['ota_rate_type'];
			if($extra_adult_price!=0 && $extra_child_price!=0)
			{
				$string='{"hotelId":'.$ota_hotel_code.',"ratePlanCode":'.$rate_code.',"startDate":"'.$startDate.'","endDate":"'.$endDate.'","extraAdult":'.$extra_adult_price.',"extraChild":'.$extra_child_price.',"sellRate":'.$rateplan_bar_price.',"currency":"INR","stopSell":"false","days":"'.$rateplan_multiple_days_data.'"}';
			}
			else{
				$string='{"hotelId":'.$ota_hotel_code.',"ratePlanCode":'.$rate_code.',"startDate":"'.$startDate.'","endDate":"'.$endDate.'","sellRate":'.$rateplan_bar_price.',"currency":"INR","stopSell":"false","days":"'.$rateplan_multiple_days_data.'"}';
			}							
			if ($rate_type == "B2C"){
			$url    = $commonUrl.'newWebserviceAPI?actionId=cm_updateroomrates&typeId=hotel&source='.$source.'&auth='.$auth.'&requestData='.$string;
			}else{
			$url    = $commonUrl.'newWebserviceAPI?actionId=cm_updateroomrates&typeId=hotel&source='.$source.'&auth='.$auth.'&requestData='.$string;
			}

			$log_request_msg = $url;
			$logModel->fill($log_data)->save();
			$ch  = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			if( curl_exec($ch) === false ){
			 $result = curl_error($ch);
			}else{
			$result = curl_exec($ch);
			}
			curl_close($ch);
				$array_data = (array) json_decode($result);
				  if(isset($array_data['Success'])){
					DB::table('log_table')->where('id', $logModel->id)
					->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
					$rlt[$room_type_name]=array('status'=>1,'response_msg'=>$result);
				}
				else{
					DB::table('log_table')->where('id', $logModel->id)
					->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
					$rlt[$room_type_name]=array('status'=>0,'response_msg'=>$result);
				}		
			}else{
			// set log for Booking Room Type is not synch with hotel Room Type.
			$log_data                 	= [
										"action_id"          => 2,
										"hotel_id"           => $bucket_hotel_id,
										"ota_id"      		 => $bucket_ota_id,
										"booking_ref_id"     => '',
										"inventory_ref_id"   => '',
										"rate_ref_id"        => $bucket_rate_plan_log_table_id,
										"user_id"            => $user_id,
										"request_msg"        => '',
										"response_msg"       => '',
										"request_url"        => '',
										"status"         	 => 0,
										"ip"         		 => $rateplan_client_ip,
										"comment"			 => "This roomrate type is not mapped."
										];
			$logModel->fill($log_data)->save();
			$rlt[$room_type_name]=array('status'=>0,'response_msg'=>"Rateplan should be sync");
			} // If else !empty($result) closed here.
			} // foreach $room_types closed here.
			return $rlt;
	}
	/*------------------- Via.com Update Function End------------------------*/
   
    /*------------------- Travelguru Update Function Start------------------------*/
    public function travelguruUpdate($bucket_data,$rateplan_data,$user_id)
    {
            $cmOtaDetailsModel  			= new CmOtaDetails();
            $logModel                       = new LogTable();
            $cmOtaRoomTypeSynchronizeModel  = new CmOtaRoomTypeSynchronize();
            $cmOtaRatePlanSynchronizeModel 	= new CmOtaRatePlanSynchronize();

			/*------------------ Get Bucket Data--------------------*/
			$bucket_id                      = $bucket_data['bucket_id'];
			$bucket_hotel_id                = $bucket_data['bucket_hotel_id'];
			$bucket_ota_id                  = $bucket_data['bucket_ota_id'];
			$bucket_ota_name                = $bucket_data['bucket_ota_name'];
			$bucket_rate_plan_log_table_id  = $bucket_data['bucket_rate_plan_log_table_id'];
			$bucket_ota_hotel_code          = $bucket_data['bucket_ota_hotel_code'];
			


			/*------------------ Get Rate Plan Log Data--------------------*/
			$rateplan_rate_plan_log_id   = $rateplan_data['rateplan_rate_plan_log_id'];
	        $rateplan_hotel_id           = $rateplan_data['rateplan_hotel_id'];
	        $rateplan_room_type_id       = $rateplan_data['rateplan_room_type_id'];
	        $rateplan_rate_plan_id       = $rateplan_data['rateplan_rate_plan_id'];
	        $rateplan_bar_price          = $rateplan_data['rateplan_bar_price'];
	        $rateplan_multiple_occupancy = $rateplan_data['rateplan_multiple_occupancy'];
	        $rateplan_date_from          = $rateplan_data['rateplan_date_from'];
	        $rateplan_date_to            = $rateplan_data['rateplan_date_to'];
	        $rateplan_client_ip          = $rateplan_data['rateplan_client_ip'];
			$rateplan_los          		 = $rateplan_data['rateplan_los'];
			$rateplan_multiple_price     = json_decode($rateplan_multiple_occupancy);
            $rateplan_multiple_days_data = $this->getDaysUpdate($rateplan_data['rateplan_multiple_days'],"Travelguru");
	        $max_adult					 = $rateplan_data['max_adult'];
			$extra_adult_price			 = $rateplan_data['extra_adult_price'];
			$extra_child_price			 = $rateplan_data['extra_child_price'];
			$occupency=$this->decideOccupencyPrice($max_adult,$rateplan_bar_price,$rateplan_multiple_price);
			$rateplan_single_price=0;
			$rateplan_double_price=0;
			$rateplan_triple_price=0;
			if($occupency)
			{
				$rateplan_single_price       = $occupency[0];
				$rateplan_double_price       = $occupency[1];
				$rateplan_triple_price       = $occupency[2];
			}

			/*------------------ Start Date and End Date----------- */
			$startDate                      = $rateplan_date_from;
        	$endDate                        = $rateplan_date_to; 

			/*------------------ Get Specific Ota Details-----------*/
			$ota_details_data             	= CmOtaDetails::select('*')
                                                ->where('hotel_id', '=' ,$bucket_hotel_id)
                                                ->where('ota_id', '=' ,$bucket_ota_id)
                                                ->first(); 	

			$ota_id 					  	= $ota_details_data->ota_id;
			$ota_hotel_code 			  	= $ota_details_data->ota_hotel_code;
			$auth_parameter   			 	= json_decode($ota_details_data->auth_parameter);
          	$MessagePassword  				= trim($auth_parameter->MessagePassword);
          	$ID               				= trim($auth_parameter->ID);
            $commonUrl      				= $ota_details_data->url;

            /*------------------ set header ------------------ */
			$headers = array (
			//Regulates versioning of the XML interface for the API
			'Content-Type: application/xml'
			);
			
			$room_types 					= explode(",", $rateplan_room_type_id);
			$rate_plan_id  					= $rateplan_rate_plan_id;

			

			foreach($room_types as $key => $room_type){
				$room_type_name=$this->getRoomTypeName($room_type);
			$result 					= $cmOtaRatePlanSynchronizeModel->getOtaRoomIdFromRatePlanSynch($room_type,$ota_id,$rate_plan_id);
			if(!empty($result)){
			$log_data               	= [
										"action_id"          => 2,
										"hotel_id"           => $bucket_hotel_id,
										"ota_id"      		 => $bucket_ota_id,
										"booking_ref_id"     => '',
										"inventory_ref_id"   => '',
										"rate_ref_id"        => $bucket_rate_plan_log_table_id,
										"user_id"            => $user_id,
										"request_msg"        => '',
										"response_msg"       => '',
										"request_url"        => '',
										"status"         	 => 2,
										"ip"         		 => $rateplan_client_ip,
										"comment"			 => "Processing for update "
										];
            $room_code                  = $result[0]['ota_room_type_id'];  
            $rate_code                  = $result[0]['ota_rate_plan_id'];

			

			$xml = '<OTA_HotelRateAmountNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05">
						<POS>
						<Source>
						<RequestorID MessagePassword="'.$MessagePassword.'" ID="'.$ID.'" Type="CHM"/>
						</Source>
						</POS>
						<RateAmountMessages>
						<RateAmountMessage>
						<StatusApplicationControl RatePlanCode="'.$rate_code.'" RatePlanType="SEL" End="'.$endDate.'" Start="'.$startDate.'" InvCode="'.$room_code.'"
						'.$rateplan_multiple_days_data.'/>
						<Rates>
						<Rate NumberOfUnits="0" MinLos="'.$rateplan_los.'"  End="'.$endDate.'" Start="'.$startDate.'">
							<BaseByGuestAmts>
							<BaseByGuestAmt AmountAfterTax="'.$rateplan_bar_price.'" CurrencyCode="INR"/>
							</BaseByGuestAmts>
						</Rate>
						</Rates>
						</RateAmountMessage>
						</RateAmountMessages>
				 	</OTA_HotelRateAmountNotifRQ>';
			$log_request_msg = $xml;
			$url  = $commonUrl.'rateAmount/update';
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
		if($resultXml)
		{
			$array_data = json_decode(json_encode($resultXml), true);      
		   if(!isset($array_data['Errors'])){
			   DB::table('log_table')->where('id', $logModel->id)
			   ->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
			   $rlt[$room_type_name]=array('status'=>1,'response_msg'=>$result);
		   }
		   else
		   {
			   DB::table('log_table')->where('id', $logModel->id)
			   ->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
			   $rlt[$room_type_name]=array('status'=>0,'response_msg'=>$result);
		   }
		}
		else{
			$rlt[$room_type_name]=array('status'=>0,'response_msg'=>$result);
			}			
			}else{  
			// set log for Booking Room Type is not synch with hotel Room Type.
			$log_data                 	= [
										"action_id"          => 2,
										"hotel_id"           => $bucket_hotel_id,
										"ota_id"      		 => $bucket_ota_id,
										"booking_ref_id"     => '',
										"inventory_ref_id"   => '',
										"rate_ref_id"        => $bucket_rate_plan_log_table_id,
										"user_id"            => '',
										"request_msg"        => $user_id,
										"response_msg"       => '',
										"request_url"        => '',
										"status"         	 => 0,
										"ip"         		 => $rateplan_client_ip,
										"comment"			 => "This roomrate type is not mapped."
										];
            $logModel->fill($log_data)->save();
			$rlt[$room_type_name]=array('status'=>0,'response_msg'=>"Rateplan should be sync");
			} // If else !empty($result) closed here.
			} // foreach $room_types closed here.
			return $rlt;
      }
	/*------------------- Travelguru Update Function End------------------------*/
	
	/*------------------- Airbnb Update Function Start------------------------*/
    public function airbnbUpdate($bucket_data,$rateplan_data,$user_id)
    {
            $cmOtaDetailsModel  			= new CmOtaDetails();
            $logModel                       = new LogTable();
            $cmOtaRoomTypeSynchronizeModel  = new CmOtaRoomTypeSynchronize();
            $cmOtaRatePlanSynchronizeModel 	= new CmOtaRatePlanSynchronize();

			/*------------------ Get Bucket Data--------------------*/
			$bucket_id                      = $bucket_data['bucket_id'];
			$bucket_hotel_id                = $bucket_data['bucket_hotel_id'];
			$bucket_ota_id                  = $bucket_data['bucket_ota_id'];
			$bucket_ota_name                = $bucket_data['bucket_ota_name'];
			$bucket_rate_plan_log_table_id  = $bucket_data['bucket_rate_plan_log_table_id'];
			$bucket_ota_hotel_code          = $bucket_data['bucket_ota_hotel_code'];
			


			/*------------------ Get Rate Plan Log Data--------------------*/
			$rateplan_rate_plan_log_id   = $rateplan_data['rateplan_rate_plan_log_id'];
	        $rateplan_hotel_id           = $rateplan_data['rateplan_hotel_id'];
	        $rateplan_room_type_id       = $rateplan_data['rateplan_room_type_id'];
	        $rateplan_rate_plan_id       = $rateplan_data['rateplan_rate_plan_id'];
	        $rateplan_bar_price          = $rateplan_data['rateplan_bar_price'];
	        $rateplan_multiple_occupancy = $rateplan_data['rateplan_multiple_occupancy'];
	        $rateplan_date_from          = $rateplan_data['rateplan_date_from'];
	        $rateplan_date_to            = $rateplan_data['rateplan_date_to'];
	        $rateplan_client_ip          = $rateplan_data['rateplan_client_ip'];
			$rateplan_los          		 = $rateplan_data['rateplan_los'];
			$rateplan_multiple_price     = json_decode($rateplan_multiple_occupancy);
            $rateplan_multiple_days_data = $this->getDaysUpdate($rateplan_data['rateplan_multiple_days'],"Travelguru");
	        $max_adult					 = $rateplan_data['max_adult'];
			$extra_adult_price			 = $rateplan_data['extra_adult_price'];
			$extra_child_price			 = $rateplan_data['extra_child_price'];
			$occupency=$this->decideOccupencyPrice($max_adult,$rateplan_bar_price,$rateplan_multiple_price);
			$rateplan_single_price=0;
			$rateplan_double_price=0;
			$rateplan_triple_price=0;
			if($occupency)
			{
				$rateplan_single_price       = $occupency[0];
				$rateplan_double_price       = $occupency[1];
				$rateplan_triple_price       = $occupency[2];
			}
			$log_request_msg="";
			/*------------------ Start Date and End Date----------- */
			$startDate                      = $rateplan_date_from;
        	$endDate                        = $rateplan_date_to; 

			/*------------------ Get Specific Ota Details-----------*/
			$ota_details_data             	= CmOtaDetails::select('*')
                                                ->where('hotel_id', '=' ,$bucket_hotel_id)
                                                ->where('ota_id', '=' ,$bucket_ota_id)
                                                ->first(); 	

			$ota_id 					  	= $ota_details_data->ota_id;
			$ota_hotel_code 			  	= $ota_details_data->ota_hotel_code;
			$auth_parameter   			 	= json_decode($ota_details_data->auth_parameter);
          	$api_key               			= trim($auth_parameter->X_Airbnb_API_Key);
			$commonUrl      				= $ota_details_data->url;
			$hotel_info=HotelInformation::where('hotel_id',$bucket_hotel_id)->first();
			$airbnbModel=new AirbnbListingDetails();
			$company= new CompanyDetails();
			$comp_details=$company->where('company_id',$hotel_info->company_id)->select('airbnb_refresh_token')->first();
			$refresh_token=$comp_details->airbnb_refresh_token;

			$oauth_Token      = $airbnbModel->getAirBnbToken($refresh_token);
			
			$room_types 					= explode(",", $rateplan_room_type_id);
			$rate_plan_id  					= $rateplan_rate_plan_id;

			

			foreach($room_types as $key => $room_type){
				$room_type_name=$this->getRoomTypeName($room_type);
			$result 				= $cmOtaRoomTypeSynchronizeModel->getOtaRoomType($room_type,$ota_id);
				if(!empty($result)){
			$log_data               	= [
										"action_id"          => 2,
										"hotel_id"           => $bucket_hotel_id,
										"ota_id"      		 => $bucket_ota_id,
										"booking_ref_id"     => '',
										"inventory_ref_id"   => '',
										"rate_ref_id"        => $bucket_rate_plan_log_table_id,
										"user_id"            => $user_id,
										"request_msg"        => '',
										"response_msg"       => '',
										"request_url"        => '',
										"status"         	 => 2,
										"ip"         		 => $rateplan_client_ip,
										"comment"			 => "Processing for update "
										];
			$room_code                  = $result;
			if($extra_adult_price)
			{
				$air_bnb_data=array("price_per_extra_person"=>$extra_adult_price);
				$this->airbnbPriceSettings($air_bnb_data,$room_code,$oauth_Token,$api_key);
			}
			
			$post_data=array('availability'=>'available',"daily_price"=>$rateplan_double_price);
			$post_data=json_encode($post_data); 
			$ch = curl_init();
			
			curl_setopt($ch, CURLOPT_URL, "https://api.airbnb.com/v2/calendars/$room_code/$startDate/$endDate");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
			$headers = array();
			$headers[] = "X-Airbnb-Api-Key: $api_key";
			$headers[] = "X-Airbnb-Oauth-Token: $oauth_Token";
			$headers[] = "Content-Type: application/json";
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			
			$result = curl_exec($ch);
			if (curl_errno($ch)) {
				echo 'Error:' . curl_error($ch);
			}
			curl_close ($ch);
			$logModel->fill($log_data)->save();
        	$array_data = json_decode($result, true);      
            
          
		if(!isset($array_data['Error'])){
			DB::table('log_table')->where('id', $logModel->id)
			->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
			$rlt[$room_type_name]=array('status'=>1,'response_msg'=>$result);
		}
		else
		{
			DB::table('log_table')->where('id', $logModel->id)
			->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
			$rlt[$room_type_name]=array('status'=>0,'response_msg'=>$result);
		}
						
			}else{
			// set log for Booking Room Type is not synch with hotel Room Type.
			$log_data                 	= [
										"action_id"          => 2,
										"hotel_id"           => $bucket_hotel_id,
										"ota_id"      		 => $bucket_ota_id,
										"booking_ref_id"     => '',
										"inventory_ref_id"   => '',
										"rate_ref_id"        => $bucket_rate_plan_log_table_id,
										"user_id"            => '',
										"request_msg"        => $user_id,
										"response_msg"       => '',
										"request_url"        => '',
										"status"         	 => 0,
										"ip"         		 => $rateplan_client_ip,
										"comment"			 => "This roomrate type is not mapped."
										];
            $logModel->fill($log_data)->save();
			$rlt[$room_type_name]=array('status'=>0,'response_msg'=>"Rateplan should be sync");
			} // If else !empty($result) closed here.
			} // foreach $room_types closed here.
			return $rlt;
      }
	/*------------------- Airbnb Update Function End------------------------*/
	 //Airbnb listing description Api
	 public function airbnbPriceSettings($air_bnb_data,$airbnb_listing_id,$oauth_Token,$api_key)
	 {
		
		 $post_data=array(
				 "price_per_extra_person" => $air_bnb_data['price_per_extra_person']
				 );
		 $post_data=json_encode($post_data);
		 $ch = curl_init();
		 curl_setopt($ch, CURLOPT_URL, "https://api.airbnb.com/v2/pricing_settings/$airbnb_listing_id");
		 curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		 curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
		 curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
 
		 $headers = array();
		 $headers[] = "X-Airbnb-Api-Key: $api_key";
		 $headers[] = "X-Airbnb-Oauth-Token: $oauth_Token";
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

	/*------------------- Goomo Update Function Start------------------------*/
    public function goomoUpdate($bucket_data,$rateplan_data,$user_id)
    {
            $cmOtaDetailsModel  			= new CmOtaDetails();
            $logModel                       = new LogTable();
            $cmOtaRoomTypeSynchronizeModel  = new CmOtaRoomTypeSynchronize();
            $cmOtaRatePlanSynchronizeModel 	= new CmOtaRatePlanSynchronize();

			/*------------------ Get Bucket Data--------------------*/
			$bucket_id                      = $bucket_data['bucket_id'];
			$bucket_hotel_id                = $bucket_data['bucket_hotel_id'];
			$bucket_ota_id                  = $bucket_data['bucket_ota_id'];
			$bucket_ota_name                = $bucket_data['bucket_ota_name'];
			$bucket_rate_plan_log_table_id  = $bucket_data['bucket_rate_plan_log_table_id'];
			$bucket_ota_hotel_code          = $bucket_data['bucket_ota_hotel_code'];
			


			/*------------------ Get Rate Plan Log Data--------------------*/
			$rateplan_rate_plan_log_id   = $rateplan_data['rateplan_rate_plan_log_id'];
	        $rateplan_hotel_id           = $rateplan_data['rateplan_hotel_id'];
	        $rateplan_room_type_id       = $rateplan_data['rateplan_room_type_id'];
	        $rateplan_rate_plan_id       = $rateplan_data['rateplan_rate_plan_id'];
	        $rateplan_bar_price          = $rateplan_data['rateplan_bar_price'];
	        $rateplan_multiple_occupancy = $rateplan_data['rateplan_multiple_occupancy'];
	        $rateplan_date_from          = $rateplan_data['rateplan_date_from'];
	        $rateplan_date_to            = $rateplan_data['rateplan_date_to'];
	        $rateplan_client_ip          = $rateplan_data['rateplan_client_ip'];
			$rateplan_los          		 = $rateplan_data['rateplan_los'];
			$rateplan_multiple_price     = json_decode($rateplan_multiple_occupancy);
            $rateplan_multiple_days_data = $this->goomoDays($rateplan_data['rateplan_multiple_days']);
	        $max_adult					 = $rateplan_data['max_adult'];
			$extra_adult_price			 = $rateplan_data['extra_adult_price'];
			$extra_child_price			 = $rateplan_data['extra_child_price'];
			$occupency=$this->decideOccupencyPrice($max_adult,$rateplan_bar_price,$rateplan_multiple_price);
			$rateplan_single_price=0;
			$rateplan_double_price=0;
			$rateplan_triple_price=0;
			if($occupency)
			{
				$rateplan_single_price       = $occupency[0];
				$rateplan_double_price       = $occupency[1];
				$rateplan_triple_price       = $occupency[2];
			}
			if($extra_adult_price!=0 && $extra_child_price!=0)
			{
				$sellRate= [
					"Double"  => $rateplan_double_price,
					"Extra_adult"  =>  $extra_adult_price,
					"Extra_child" =>  $extra_child_price,
					"Infant"     => '',
					"Single"   => $rateplan_single_price
					];
			}
			else
			{
				$sellRate= [
					"Double"  => $rateplan_double_price,
					"Extra_adult"  =>  "",
					"Extra_child" =>  "",
					"Infant"     => '',
					"Single"   => $rateplan_single_price
					];
			}
		
			$log_request_msg="";
			/*------------------ Start Date and End Date----------- */
			$startDate                      = $rateplan_date_from;
        	$endDate                        = $rateplan_date_to; 

			/*------------------ Get Specific Ota Details-----------*/
			$ota_details_data             	= CmOtaDetails::select('*')
                                                ->where('hotel_id', '=' ,$bucket_hotel_id)
                                                ->where('ota_id', '=' ,$bucket_ota_id)
                                                ->first(); 	

			$ota_id 					  	= $ota_details_data->ota_id;
			$ota_hotel_code 			  	= $ota_details_data->ota_hotel_code;
			$auth_parameter   			 	= json_decode($ota_details_data->auth_parameter);
          	$apiKey               			= trim($auth_parameter->apiKey);
			$channelId               		= trim($auth_parameter->channelId);
			$accessToken               		= trim($auth_parameter->accessToken);
			$commonUrl      				= $ota_details_data->url;
			
			$room_types 					= explode(",", $rateplan_room_type_id);
			$rate_plan_id  					= $rateplan_rate_plan_id;
			

			foreach($room_types as $key => $room_type){
				$room_type_name=$this->getRoomTypeName($room_type);
			$result 				= $cmOtaRatePlanSynchronizeModel->getOtaRoomIdFromRatePlanSynch($room_type,$ota_id,$rate_plan_id);	
			if(!empty($result)){
			$log_data               	= [
										"action_id"          => 2,
										"hotel_id"           => $bucket_hotel_id,
										"ota_id"      		 => $bucket_ota_id,
										"booking_ref_id"     => '',
										"inventory_ref_id"   => '',
										"rate_ref_id"        => $bucket_rate_plan_log_table_id,
										"user_id"            => $user_id,
										"request_msg"        => '',
										"response_msg"       => '',
										"request_url"        => '',
										"status"         	 => 2,
										"ip"         		 => $rateplan_client_ip,
										"comment"			 => "Processing for update "
										];
			$ota_rate_plan_id               = $result[0]['ota_rate_plan_id'];
			$ota_room_id                  	= $result[0]['ota_room_type_id'];
			$log_request_msg="";
			$roomRateId=$ota_room_id.'_'.$ota_rate_plan_id;
			$post_data=array(
			"channelName"=> "Bookingjini",
			"contractType"=>"WEB",
			"days"=>$rateplan_multiple_days_data,
			"endDate" =>$endDate,
			"productId"=>$bucket_ota_hotel_code,
			"roomRateId" => $roomRateId,
			"sellRate"=>$sellRate,
			"startDate" => $startDate);
			$post_data=json_encode($post_data);
			$log_request_msg=$commonUrl.'/updatePrice'.$post_data;							
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, "$commonUrl/updatePrice");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
			curl_setopt($ch, CURLOPT_POST, 1);
			$headers = array();
			$headers[] = "apiKey: $apiKey";
			$headers[] = "channelId: $channelId";
			$headers[] = "accessToken: $accessToken";
			$headers[] = "Content-Type: application/json";
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			$result = curl_exec($ch);
			if (curl_errno($ch)) {
				echo 'Error:' . curl_error($ch);
			}
			curl_close ($ch);
			$logModel->fill($log_data)->save();
			
        	$array_data = json_decode($result, true);
          
			if(!isset($array_data['Error'])){
				DB::table('log_table')->where('id', $logModel->id)
				->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
				$rlt[$room_type_name]=array('status'=>1,'response_msg'=>$result);
			}
			else
			{
				DB::table('log_table')->where('id', $logModel->id)
				->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
				$rlt[$room_type_name]=array('status'=>0,'response_msg'=>$result);
			}
							
			}else{
			// set log for Booking Room Type is not synch with hotel Room Type.
			$log_data                 	= [
										"action_id"          => 2,
										"hotel_id"           => $bucket_hotel_id,
										"ota_id"      		 => $bucket_ota_id,
										"booking_ref_id"     => '',
										"inventory_ref_id"   => '',
										"rate_ref_id"        => $bucket_rate_plan_log_table_id,
										"user_id"            => '',
										"request_msg"        => $user_id,
										"response_msg"       => '',
										"request_url"        => '',
										"status"         	 => 0,
										"ip"         		 => $rateplan_client_ip,
										"comment"			 => "This roomrate type is not mapped."
										];
            $logModel->fill($log_data)->save();
			$rlt[$room_type_name]=array('status'=>0,'response_msg'=>"Rateplan should be sync");
			} // If else !empty($result) closed here.
			} // foreach $room_types closed here.
			return $rlt;
      }
	/*------------------- Goomo Update Function End------------------------*/
	/*-------------------Nodal update rate function start-----------------------*/
	public function nodalUpdate($bucket_data,$rateplan_data,$user_id)
    {
		$cmOtaDetailsModel  			= new CmOtaDetails();
		$logModel                       = new LogTable();
		$cmOtaRoomTypeSynchronizeModel  = new CmOtaRoomTypeSynchronize();
		$cmOtaRatePlanSynchronizeModel 	= new CmOtaRatePlanSynchronize();
		/*------------------ Get Bucket Data--------------------*/
		$bucket_id                      = $bucket_data['bucket_id'];
		$bucket_hotel_id                = $bucket_data['bucket_hotel_id'];
		$bucket_ota_id                  = $bucket_data['bucket_ota_id'];
		$bucket_ota_name                = $bucket_data['bucket_ota_name'];
		$bucket_rate_plan_log_table_id  = $bucket_data['bucket_rate_plan_log_table_id'];
		$bucket_ota_hotel_code          = $bucket_data['bucket_ota_hotel_code'];
		
		/*------------------ Get Rate Plan Log Data--------------------*/
		$rateplan_rate_plan_log_id   = $rateplan_data['rateplan_rate_plan_log_id'];
		$rateplan_hotel_id           = $rateplan_data['rateplan_hotel_id'];
		$rateplan_room_type_id       = $rateplan_data['rateplan_room_type_id'];
		$rateplan_rate_plan_id       = $rateplan_data['rateplan_rate_plan_id'];
		$rateplan_bar_price          = $rateplan_data['rateplan_bar_price'];
		$rateplan_multiple_occupancy = $rateplan_data['rateplan_multiple_occupancy'];
		$rateplan_date_from          = $rateplan_data['rateplan_date_from'];
		$rateplan_date_to            = $rateplan_data['rateplan_date_to'];
		$rateplan_client_ip          = $rateplan_data['rateplan_client_ip'];
		$rateplan_los          		 = $rateplan_data['rateplan_los'];
		$rateplan_multiple_price     = json_decode($rateplan_multiple_occupancy);
		$rateplan_multiple_days_data = $this->getDaysUpdate($rateplan_data['rateplan_multiple_days'],"Travelguru");
		$max_adult					 = $rateplan_data['max_adult'];
		$extra_adult_price			 = $rateplan_data['extra_adult_price'];
		$extra_child_price			 = $rateplan_data['extra_child_price'];
		$occupency=$this->decideOccupencyPrice($max_adult,$rateplan_bar_price,$rateplan_multiple_price);
		$rateplan_single_price=0;
		$rateplan_double_price=0;
		$rateplan_triple_price=0;
		if($occupency)
		{
			$rateplan_single_price       = $occupency[0];
			$rateplan_double_price       = $occupency[1];
			$rateplan_triple_price       = $occupency[2];
		}
		$log_request_msg="";
		/*------------------ Start Date and End Date----------- */
		$startDate                      = $rateplan_date_from;
		$endDate                        = $rateplan_date_to; 
		/*------------------ Get Specific Ota Details-----------*/
		$ota_details_data             	= CmOtaDetails::select('*')
											->where('hotel_id', '=' ,$bucket_hotel_id)
											->where('ota_id', '=' ,$bucket_ota_id)
											->first(); 	
		$ota_id 					  	= $ota_details_data->ota_id;
		$ota_hotel_code 			  	= $ota_details_data->ota_hotel_code;
		$auth_parameter       			= json_decode($ota_details_data->auth_parameter);
		$access_token               	= trim($auth_parameter->access_token);
		$commonUrl      				= $ota_details_data->url;
		
		$room_types 					= explode(",", $rateplan_room_type_id);
		$rate_plan_id  					= $rateplan_rate_plan_id;
		
		foreach($room_types as $key => $room_type){
			$room_type_name=$this->getRoomTypeName($room_type);
		$result 				= $cmOtaRoomTypeSynchronizeModel->getOtaRoomType($room_type,$ota_id);
			if(!empty($result)){
		$log_data               	= [
									"action_id"          => 2,
									"hotel_id"           => $bucket_hotel_id,
									"ota_id"      		 => $bucket_ota_id,
									"booking_ref_id"     => '',
									"inventory_ref_id"   => '',
									"rate_ref_id"        => $bucket_rate_plan_log_table_id,
									"user_id"            => $user_id,
									"request_msg"        => '',
									"response_msg"       => '',
									"request_url"        => '',
									"status"         	 => 2,
									"ip"         		 => $rateplan_client_ip,
									"comment"			 => "Processing for update "
									];
		$room_code                  = $result;
		$url=$commonUrl.'/UpdateRate.php';
		$post_data=array("access_token"=>$access_token,"property_id"=>$bucket_ota_hotel_code,"room_id"=>$room_code,"from_date"=>$startDate,"to_date"=>$endDate,"price"=>$rateplan_bar_price);
		$post_data=json_encode($post_data); 
		$ch = curl_init();
		
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		$headers = array();
		$headers[] = "Content-Type: application/json";
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		
		$result = curl_exec($ch);
		if (curl_errno($ch)) {
			echo 'Error:' . curl_error($ch);
		}
		curl_close ($ch);
		$logModel->fill($log_data)->save();
	$array_data = json_decode($result, true);      
	 	  
	if(!isset($array_data['Error'])){
		DB::table('log_table')->where('id', $logModel->id)
		->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
		$rlt[$room_type_name]=array('status'=>1,'response_msg'=>$result);
	}
	else
	{
		DB::table('log_table')->where('id', $logModel->id)
		->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
		$rlt[$room_type_name]=array('status'=>1,'response_msg'=>$result);
	}
					
		}else{
		// set log for Booking Room Type is not synch with hotel Room Type.
		$log_data                 	= [
									"action_id"          => 2,
									"hotel_id"           => $bucket_hotel_id,
									"ota_id"      		 => $bucket_ota_id,
									"booking_ref_id"     => '',
									"inventory_ref_id"   => '',
									"rate_ref_id"        => $bucket_rate_plan_log_table_id,
									"user_id"            => '',
									"request_msg"        => $user_id,
									"response_msg"       => '',
									"request_url"        => '',
									"status"         	 => 0,
									"ip"         		 => $rateplan_client_ip,
									"comment"			 => "This roomrate type is not mapped."
									];
		$logModel->fill($log_data)->save();
		$rlt[$room_type_name]=array('status'=>0,'response_msg'=>"Rateplan should be sync");
		} // If else !empty($result) closed here.
		} // foreach $room_types closed here.
		return $rlt;
  }
	/*-------------------Nodal update rate function end---------------------------*/
	/*-------------------EaseMyTrip update rate function start--------------------*/
	public function easemytripUpdate($bucket_data,$rateplan_data,$user_id)
    {
			$cmOtaDetailsModel  			= new CmOtaDetails();
			$logModel                       = new LogTable();
			$cmOtaRoomTypeSynchronizeModel  = new CmOtaRoomTypeSynchronize();
			$cmOtaRatePlanSynchronizeModel 	= new CmOtaRatePlanSynchronize();

			/*------------------ Get Bucket Data--------------------*/
			$bucket_id                      = $bucket_data['bucket_id'];
			$bucket_hotel_id                = $bucket_data['bucket_hotel_id'];
			$bucket_ota_id                  = $bucket_data['bucket_ota_id'];
			$bucket_ota_name                = $bucket_data['bucket_ota_name'];
			$bucket_rate_plan_log_table_id  = $bucket_data['bucket_rate_plan_log_table_id'];
			$bucket_ota_hotel_code          = $bucket_data['bucket_ota_hotel_code'];

			/*------------------ Get Rate Plan Log Data--------------------*/
			$rateplan_rate_plan_log_id   = $rateplan_data['rateplan_rate_plan_log_id'];
	        $rateplan_hotel_id           = $rateplan_data['rateplan_hotel_id'];
	        $rateplan_room_type_id       = $rateplan_data['rateplan_room_type_id'];
	        $rateplan_rate_plan_id       = $rateplan_data['rateplan_rate_plan_id'];
	        $rateplan_bar_price          = $rateplan_data['rateplan_bar_price'];
	        $rateplan_multiple_occupancy = $rateplan_data['rateplan_multiple_occupancy'];
	        $rateplan_date_from          = $rateplan_data['rateplan_date_from'];
	        $rateplan_date_to            = $rateplan_data['rateplan_date_to'];
	        $rateplan_client_ip          = $rateplan_data['rateplan_client_ip'];
	        $extra_adult_price           = $rateplan_data['extra_adult_price'];
			$extra_child_price           = $rateplan_data['extra_child_price'];

			
			$rateplan_multiple_days_data = $this->getDaysUpdate($rateplan_data['rateplan_multiple_days'],"Via.com");
	        $rateplan_multiple_price     = json_decode($rateplan_multiple_occupancy);
			$max_adult					 = $rateplan_data['max_adult'];
			$occupency=$this->decideOccupencyPrice($max_adult,$rateplan_bar_price,$rateplan_multiple_price);
			$rateplan_single_price=0;
			$rateplan_double_price=0;
			$rateplan_triple_price=0;
			$rateplan_fourth_price=0;
			if($occupency)
			{
				$rateplan_single_price       = $occupency[0];
				$rateplan_double_price       = $occupency[1];
				if(isset($occupency[2]))
				{
					$rateplan_triple_price       = $occupency[2];	
				}
				if(isset($occupency[3]))
				{
					$rateplan_fourth_price		 = $occupency[3];	
				}
			}
			/*------------------ Start Date and End Date----------- */
			$startDate                      = $rateplan_date_from;
        	$endDate                        = $rateplan_date_to; 

			/*------------------ Get Specific Ota Details-----------*/

			$ota_details_data             	= CmOtaDetails::select('*')
													->where('hotel_id', '=' ,$bucket_hotel_id)
													->where('ota_id', '=' ,$bucket_ota_id)
													->first(); 	

			$ota_id 					  	= $ota_details_data->ota_id;
			$ota_hotel_code 			  	= $ota_details_data->ota_hotel_code;
			$auth_parameter 			  	= json_decode($ota_details_data->auth_parameter);
			$token_key 						= $auth_parameter->Token;
            $commonUrl      				= $ota_details_data->url;
			$commonUrl						= $commonUrl.'/save';
            /*------------------ set header ------------------ */
			$headers = array('Content-Type:application/json', 'Expect:');
			
			$room_types 					= explode(",", $rateplan_room_type_id);
			$rate_plan_id  					= $rateplan_rate_plan_id;

			foreach($room_types as $key => $room_type){
				$room_type_name=$this->getRoomTypeName($room_type);
			$result 				= $cmOtaRatePlanSynchronizeModel->getOtaRoomIdFromRatePlanSynch($room_type,$ota_id,$rate_plan_id);
				
			if(!empty($result)){
			$log_data               	= [
										"action_id"          => 2,
										"hotel_id"           => $bucket_hotel_id,
										"ota_id"      		 => $bucket_ota_id,
										"booking_ref_id"     => '',
										"inventory_ref_id"   => '',
										"rate_ref_id"        => $bucket_rate_plan_log_table_id,
										"user_id"            => $user_id,
										"request_msg"        => '',
										"response_msg"       => '',
										"request_url"        => '',
										"status"         	 => 2,
										"ip"         		 => $rateplan_client_ip,
										"comment"			 => "Processing for update "
										];
			$room_code                  = $result[0]['ota_room_type_id'];  
			$rate_code                  = $result[0]['ota_rate_plan_id'];
			$rate_type                  = $result[0]['ota_rate_type'];		
					dd($extra_adult_price,$extra_child_price);	
			$post_data    ='{
				"RequestType": "SaveSupplierHotel",
				"Token": "'.$token_key.'",
				"HotelCode": "'.$ota_hotel_code.'",
				"Data": [
				{
				"RequestType": "Price_Cancellation",
				"Data": [
				{
				"RoomCode": "'.$room_code.'",
				"From": "'.$startDate.'",
				"To": "'.$endDate.'",
				"RoomAvailablityDetail": {
				"PriceDetail": {
				"PlanId": "'.$rate_code.'",
				"OnePaxOccupancy": '.$rateplan_single_price.',
				"TwoPaxOccupancy": '.$rateplan_double_price.',';
				if(isset($occupency[2]))
				{
					$post_data.='"ThreePaxOccupancy": '.$rateplan_triple_price.',';
				}
				if(isset($occupency[2]))
				{
					$post_data.='"FourPaxOccupancy": '.$rateplan_fourth_price.',';
				}
				$post_data.='"ExtraAdultRate": '.$extra_adult_price.',
				"ExtraBedRate": '.$extra_adult_price.',
				"ChildRate": '.$extra_child_price.',
				"ChildWithBedRate": '.$extra_child_price.',
				"BT": "B2C"
				}
				}
				}
				]
				}
				]
			   }';
			$log_request_msg = $post_data;
			$logModel->fill($log_data)->save();
			$ch  = curl_init();
			curl_setopt($ch, CURLOPT_URL, $commonUrl);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_POSTFIELDS,$post_data);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			if( curl_exec($ch) === false ){
			 $result = curl_error($ch);
			}else{
			$result = curl_exec($ch);
			}
			curl_close($ch);
				$array_data =json_decode($result,true);
				  if(isset($array_data['Success'])){
					DB::table('log_table')->where('id', $logModel->id)
					->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
					$rlt[$room_type_name]=array('status'=>1,'response_msg'=>$result);
				}
				else{
					DB::table('log_table')->where('id', $logModel->id)
					->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
					$rlt[$room_type_name]=array('status'=>0,'response_msg'=>$result);
				}		
			}else{
			// set log for Booking Room Type is not synch with hotel Room Type.
			$log_data                 	= [
										"action_id"          => 2,
										"hotel_id"           => $bucket_hotel_id,
										"ota_id"      		 => $bucket_ota_id,
										"booking_ref_id"     => '',
										"inventory_ref_id"   => '',
										"rate_ref_id"        => $bucket_rate_plan_log_table_id,
										"user_id"            => $user_id,
										"request_msg"        => '',
										"response_msg"       => '',
										"request_url"        => '',
										"status"         	 => 0,
										"ip"         		 => $rateplan_client_ip,
										"comment"			 => "This roomrate type is not mapped."
										];
			$logModel->fill($log_data)->save();
			$rlt[$room_type_name]=array('status'=>0,'response_msg'=>"Rateplan should be sync");
		} // If else !empty($result) closed here.
		} // foreach $room_types closed here.
		return $rlt;
	}
	/*-------------------EaseMyTrip update rate function end--------------------*/
	/*-------------------paytm update rate function start--------------------*/
	public function paytmUpdate($bucket_data,$rateplan_data,$user_id)
    {
			$cmOtaDetailsModel  			= new CmOtaDetails();
			$logModel                       = new LogTable();
			$cmOtaRoomTypeSynchronizeModel  = new CmOtaRoomTypeSynchronize();
			$cmOtaRatePlanSynchronizeModel 	= new CmOtaRatePlanSynchronize();

			/*------------------ Get Bucket Data--------------------*/
			$bucket_id                      = $bucket_data['bucket_id'];
			$bucket_hotel_id                = $bucket_data['bucket_hotel_id'];
			$bucket_ota_id                  = $bucket_data['bucket_ota_id'];
			$bucket_ota_name                = $bucket_data['bucket_ota_name'];
			$bucket_rate_plan_log_table_id  = $bucket_data['bucket_rate_plan_log_table_id'];
			$bucket_ota_hotel_code          = $bucket_data['bucket_ota_hotel_code'];

			/*------------------ Get Rate Plan Log Data--------------------*/
			$rateplan_rate_plan_log_id   = $rateplan_data['rateplan_rate_plan_log_id'];
	        $rateplan_hotel_id           = $rateplan_data['rateplan_hotel_id'];
	        $rateplan_room_type_id       = $rateplan_data['rateplan_room_type_id'];
	        $rateplan_rate_plan_id       = $rateplan_data['rateplan_rate_plan_id'];
	        $rateplan_bar_price          = $rateplan_data['rateplan_bar_price'];
	        $rateplan_multiple_occupancy = $rateplan_data['rateplan_multiple_occupancy'];
	        $rateplan_date_from          = $rateplan_data['rateplan_date_from'];
	        $rateplan_date_to            = $rateplan_data['rateplan_date_to'];
	        $rateplan_client_ip          = $rateplan_data['rateplan_client_ip'];
	        $extra_adult_price           = $rateplan_data['extra_adult_price'];
			$extra_child_price           = $rateplan_data['extra_child_price'];
			
			$rateplan_multiple_days_data = $this->getDaysUpdate($rateplan_data['rateplan_multiple_days'],"Via.com");
	        $rateplan_multiple_price     = json_decode($rateplan_multiple_occupancy);
			$max_adult					 = $rateplan_data['max_adult'];
			$occupency=$this->decideOccupencyPrice($max_adult,$rateplan_bar_price,$rateplan_multiple_price);
			$rateplan_single_price=0;
			$rateplan_double_price=0;
			$rateplan_triple_price=0;
			if($occupency)
			{
				$rateplan_single_price       = $occupency[0];
				$rateplan_double_price       = $occupency[1];
				if(isset($occupency[2]))
				{
					$rateplan_triple_price       = $occupency[2];	
				}
			}
			/*------------------ Start Date and End Date----------- */
			$startDate                      = $rateplan_date_from;
        	$endDate                        = $rateplan_date_to; 

			/*------------------ Get Specific Ota Details-----------*/

			$ota_details_data             	= CmOtaDetails::select('*')
													->where('hotel_id', '=' ,$bucket_hotel_id)
													->where('ota_id', '=' ,$bucket_ota_id)
													->first(); 	

			$ota_id 					  	= $ota_details_data->ota_id;
			$ota_hotel_code 			  	= $ota_details_data->ota_hotel_code;
			$auth_parameter 			  	= json_decode($ota_details_data->auth_parameter);
			$api_key 						= $auth_parameter->api_key;
            $commonUrl      				= $ota_details_data->url;
			$commonUrl						= $commonUrl.'/rateUpdate';
            /*------------------ set header ------------------ */
			$headers = array('Content-Type:application/json', 'Expect:');
			
			$room_types 					= explode(",", $rateplan_room_type_id);
			$rate_plan_id  					= $rateplan_rate_plan_id;

			foreach($room_types as $key => $room_type){
				$room_type_name=$this->getRoomTypeName($room_type);
			$result 				= $cmOtaRatePlanSynchronizeModel->getOtaRoomIdFromRatePlanSynch($room_type,$ota_id,$rate_plan_id);

			if(!empty($result)){
			$log_data               	= [
										"action_id"          => 2,
										"hotel_id"           => $bucket_hotel_id,
										"ota_id"      		 => $bucket_ota_id,
										"booking_ref_id"     => '',
										"inventory_ref_id"   => '',
										"rate_ref_id"        => $bucket_rate_plan_log_table_id,
										"user_id"            => $user_id,
										"request_msg"        => '',
										"response_msg"       => '',
										"request_url"        => '',
										"status"         	 => 2,
										"ip"         		 => $rateplan_client_ip,
										"comment"			 => "Processing for update "
										];
			$room_code                  = $result[0]['ota_room_type_id'];  
			$rate_code                  = $result[0]['ota_rate_plan_id'];
			$rate_type                  = $result[0]['ota_rate_type'];		
						
			$post_data    ='{
			"auth": {
			"key": "'.$api_key.'"
			},
			"data": {
				"propertyId": "'.$ota_hotel_code.'",
				"roomId": "'.$room_code.'",
				"rateplanId": "'.$rate_code.'",
					"rate": [
					{
					"startDate": "'.$startDate.'",
					"endDate": "'.$endDate.'",
					"Single": '.$rateplan_single_price.',
					"Double": '.$rateplan_double_price.',
					"Triple":'.$rateplan_triple_price.',
					"Extra_child": '.$extra_child_price.',
					"Extra_adult": '.$extra_adult_price.'
					}
					]
				}
			}';
		
			$log_request_msg = $post_data;
			$logModel->fill($log_data)->save();
			$ch  = curl_init();
			curl_setopt($ch, CURLOPT_URL, $commonUrl);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_POSTFIELDS,$post_data);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			if( curl_exec($ch) === false ){
			 $result = curl_error($ch);
			}else{
			$result = curl_exec($ch);
			}
			curl_close($ch);
							
				$array_data =json_decode($result,true);
				if(isset($array_data['status']))
				{
					if($array_data['status'] == "success")
					{
						DB::table('log_table')->where('id', $logModel->id)
						->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
						$rlt[$room_type_name]=array('status'=>1,'response_msg'=>$result);
					}
					else{
						DB::table('log_table')->where('id', $logModel->id)
						->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
						$rlt[$room_type_name]=array('status'=>0,'response_msg'=>$result);
					}
				}
				else{
					DB::table('log_table')->where('id', $logModel->id)
					->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
					$rlt[$room_type_name]=array('status'=>0,'response_msg'=>$result);
				}		
			}else{
			// set log for Booking Room Type is not synch with hotel Room Type.
			$log_data                 	= [
										"action_id"          => 2,
										"hotel_id"           => $bucket_hotel_id,
										"ota_id"      		 => $bucket_ota_id,
										"booking_ref_id"     => '',
										"inventory_ref_id"   => '',
										"rate_ref_id"        => $bucket_rate_plan_log_table_id,
										"user_id"            => $user_id,
										"request_msg"        => '',
										"response_msg"       => '',
										"request_url"        => '',
										"status"         	 => 0,
										"ip"         		 => $rateplan_client_ip,
										"comment"			 => "This roomrate type is not mapped."
										];
			$logModel->fill($log_data)->save();
			$rlt[$room_type_name]=array('status'=>0,'response_msg'=>"Rateplan should be sync");
		} // If else !empty($result) closed here.
		} // foreach $room_types closed here.
		return $rlt;
	}
	/*-------------------paytm update rate function end--------------------*/
}
