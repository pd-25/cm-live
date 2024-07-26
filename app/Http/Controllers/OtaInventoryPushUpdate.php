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
class OtaInventoryPushUpdate extends Controller
{

	/*------------------------get room type name---------------------------------*/
	public function getRoomTypeName($room_type)
	{
		$getRoomType=DB::table('room_type_table')->select('room_type')->where('room_type_id',$room_type)->first();
		return $getRoomType->room_type;
	}
	/*-------------------------------end-----------------------------------------*/ 
	/*------------------- Cleartrip Update Function Start------------------------*/
	public function cleartripUpdate($bucket_data,$inventory_data,$user_id)
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
			$bucket_inventory_table_id      = $bucket_data['bucket_inventory_table_id'];
			$bucket_ota_hotel_code          = $bucket_data['bucket_ota_hotel_code'];
			


			/*------------------ Get Booking Data--------------------*/
			$inventory_inventory_id         = $inventory_data['inventory_inventory_id'];
			$inventory_hotel_id            	= $inventory_data['inventory_hotel_id'];     
			$inventory_room_type_id       	= $inventory_data['inventory_room_type_id'];
			$inventory_no_of_rooms          = $inventory_data['inventory_no_of_rooms'];
			$inventory_date_from           	= $inventory_data['inventory_date_from'];
			$inventory_date_to          	= $inventory_data['inventory_date_to'];
			$inventory_client_ip            = $inventory_data['inventory_client_ip'];
			$inventory_los                  = $inventory_data['inventory_los'];

			/*------------------ Start Date and End Date----------- */
        	$startDate                      = date('d/m/Y', strtotime($inventory_date_from));
        	$endDate                        = date('d/m/Y', strtotime($inventory_date_to));

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

			
			$room_types 					= explode(",", $inventory_room_type_id);
			$room_qtys 					    = explode(",", $inventory_no_of_rooms);
			$rlt							= array();
			foreach($room_types as $key => $room_type){
			
			$room_type_name=$this->getRoomTypeName($room_type);
			
			$result 					= $cmOtaRoomTypeSynchronizeModel->getOtaRoomType($room_type,$ota_id);
			if(!empty($result)){

			$log_data               	= [
										"action_id"          => 4,
										"hotel_id"           => $bucket_hotel_id,
										"ota_id"      		 => $bucket_ota_id,
										"booking_ref_id"     => '',
										"inventory_ref_id"   => $bucket_inventory_table_id,
										"rate_ref_id"        => '',
										"user_id"            => $user_id,
										"request_msg"        => '',
										"response_msg"       => '',
										"request_url"        => '',
										"status"         	 => 2,
										"ip"         		 => $inventory_client_ip,
										"comment"			 => "Processing for update "
										];
			$room_code     				= $result;	
			$room_quanty = $room_qtys[$key];
				
				if($room_quanty >= 0){

					$xml ='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
				<hotel-inventory xmlns="http://www.cleartrip.com/extranet/hotel-inventory" type="update">
				<hotel-id>'.$ota_hotel_code.'</hotel-id>
				<room-type-id>'.$room_code.'</room-type-id>
				<room-inventories>
				<room-inventory>
				<from-date>'.$startDate.'</from-date>
				<to-date>'.$endDate.'</to-date>
				<applicable-days>ALL</applicable-days>
				<inventory>'.$room_quanty.'</inventory>
				<release-hours>0</release-hours>
				</room-inventory>
				</room-inventories>
				</hotel-inventory>';

				$log_request_msg = $xml;
				$url = $commonUrl.'push-inventory';
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
					if (isset($ary_data['status']['code']) && $ary_data['status']['code']=='S001') {
						DB::table('log_table')->where('id', $logModel->id)
						->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
						$rlt[$room_type_name]=array('status'=>1,'response_msg'=>"Updated successfully");
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
				}
			}else{
			// set log for Booking Room Type is not synch with hotel Room Type.
			$log_data                 	= [
										"action_id"          => 4,
										"hotel_id"           => $bucket_hotel_id,
										"ota_id"      		 => $bucket_ota_id,
										"booking_ref_id"     => '',
										"inventory_ref_id"   => $bucket_inventory_table_id,
										"rate_ref_id"        => '',
										"user_id"            => $user_id,
										"request_msg"        => '',
										"response_msg"       => '',
										"request_url"        => '',
										"status"         	 => 0,
										"ip"         		 => $inventory_client_ip,
										"comment"			 => "Booking room type is not mapped."
										];
			$logModel->fill($log_data)->save();
			$rlt[$room_type_name]=array('status'=>0,'response_msg'=>"Roomtype should be sync");
			
			} // If else !empty($result) closed here
			} // foreach $room_types closed here 	
			return $rlt;

	}
	/*------------------- Cleartrip Update Function Close------------------------*/
	/*------------------- Agoda Update Function Start------------------------*/
    public function agodaUpdate($bucket_data,$inventory_data,$user_id)
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
			$bucket_inventory_table_id      = $bucket_data['bucket_inventory_table_id'];
			$bucket_ota_hotel_code          = $bucket_data['bucket_ota_hotel_code'];
			


			/*------------------ Get Booking Data--------------------*/
			$inventory_inventory_id         = $inventory_data['inventory_inventory_id'];
			$inventory_hotel_id            	= $inventory_data['inventory_hotel_id'];     
			$inventory_room_type_id       	= $inventory_data['inventory_room_type_id'];
			$inventory_no_of_rooms          = $inventory_data['inventory_no_of_rooms'];
			$inventory_date_from           	= $inventory_data['inventory_date_from'];
			$inventory_date_to          	= $inventory_data['inventory_date_to'];
			$inventory_client_ip            = $inventory_data['inventory_client_ip'];
			$inventory_los                  = $inventory_data['inventory_los'];

			/*------------------ Start Date and End Date----------- */
			$startDate                      = $inventory_date_from;
        	$endDate                        = $inventory_date_to; 

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

			
			$room_types 					= explode(",", $inventory_room_type_id);
			$room_qtys 					    = explode(",", $inventory_no_of_rooms);
			$rlt=array();
			foreach($room_types as $key => $room_type){				
				$room_type_name=$this->getRoomTypeName($room_type);
			$result 					=  $cmOtaRoomTypeSynchronizeModel->getOtaRoomType($room_type,$ota_id);
			if(!empty($result)){

			$log_data               	= [
										"action_id"          => 4,
										"hotel_id"           => $bucket_hotel_id,
										"ota_id"      		 => $bucket_ota_id,
										"booking_ref_id"     => '',
										"inventory_ref_id"   => $bucket_inventory_table_id,
										"rate_ref_id"        => '',
										"user_id"            => $user_id,
										"request_msg"        => '',
										"response_msg"       => '',
										"request_url"        => '',
										"status"         	 => 2,
										"ip"         		 => $inventory_client_ip,
										"comment"			 => "Processing for update "
										];
			$room_code     				= $result;	

			
			$room_quanty = $room_qtys[$key];
                

               if($room_quanty >= 0){

               	 $xml ='<?xml version="1.0" encoding="UTF-8"?>
				<request timestamp="1436931804" type="1">
				<criteria property_id="'.$ota_hotel_code.'">
				<inventory>
				<update room_id="'.$room_code.'">
				<date_range from="'.$startDate.'" to="'.$endDate.'">
				<dow>1</dow>
				<dow>2</dow>
				<dow>3</dow>
				<dow>4</dow>
				<dow>5</dow>
				<dow>6</dow>
				<dow>7</dow>
				</date_range>
				<allotment>'.$room_quanty.'</allotment>
				 <restrictions>
                <closed>false</closed>
                <ctd>false</ctd>
				<cta>false</cta>
                </restrictions>
				</update>
				</inventory>
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
                $result = curl_exec($ch);
				curl_close($ch);
				$resultXml=simplexml_load_string($result);
                if($resultXml)
				{
					$array_data = json_decode(json_encode($resultXml), true);
					if(!isset($array_data['errors'])){
						DB::table('log_table')->where('id', $logModel->id)
						->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
						$rlt[$room_type_name]=array('status'=>1,'response_msg'=>"updated successfully");
					}else{
						DB::table('log_table')->where('id', $logModel->id)
						->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
						$rlt[$room_type_name]=array('status'=>0,'response_msg'=>$array_data['errors']['error']['@attribute']['description']);
					}
				}
				else{
					$rlt[$room_type_name]=array('status'=>0,'response_msg'=>$result);
					}
				}
                
			}else{
			// set log for Booking Room Type is not synch with hotel Room Type.
			$log_data                 	= [
										"action_id"          => 4,
										"hotel_id"           => $bucket_hotel_id,
										"ota_id"      		 => $bucket_ota_id,
										"booking_ref_id"     => '',
										"inventory_ref_id"   => $bucket_inventory_table_id,
										"rate_ref_id"        => '',
										"user_id"            => $user_id,
										"request_msg"        => '',
										"response_msg"       => '',
										"request_url"        => '',
										"status"         	 => 0,
										"ip"         		 => $inventory_client_ip,
										"comment"			 => "Booking room type is not mapped."
										];
			$logModel->fill($log_data)->save();
			$rlt[$room_type_name]=array('status'=>0,'response_msg'=>"Roomtype should be sync");
			} // If else !empty($result) closed here
		} // foreach $room_types closed here. 
			return $rlt;
	}
    /*------------------- Goibibo Update Function Start------------------------*/
    public function goibiboUpdate($bucket_data,$inventory_data,$user_id)
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
			$bucket_inventory_table_id      = $bucket_data['bucket_inventory_table_id'];
			$bucket_ota_hotel_code          = $bucket_data['bucket_ota_hotel_code'];
			


			/*------------------ Get Booking Data--------------------*/
			$inventory_inventory_id         = $inventory_data['inventory_inventory_id'];
			$inventory_hotel_id            	= $inventory_data['inventory_hotel_id'];     
			$inventory_room_type_id       	= $inventory_data['inventory_room_type_id'];
			$inventory_no_of_rooms          = $inventory_data['inventory_no_of_rooms'];
			$inventory_date_from           	= $inventory_data['inventory_date_from'];
			$inventory_date_to          	= $inventory_data['inventory_date_to'];
            $inventory_client_ip            = $inventory_data['inventory_client_ip'];
            $inventory_los                  = $inventory_data['inventory_los'];


			/*------------------ Start Date and End Date----------- */
			$startDate                      = $inventory_date_from;
        	$endDate                        = $inventory_date_to; 

			/*------------------ Get Specific Ota Details-----------*/

			$ota_details_data             	= CmOtaDetails::select('*')
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
			
			$room_types 					= explode(",", $inventory_room_type_id);
			$room_qtys 					    = explode(",", $inventory_no_of_rooms);
			$rlt=array();
			foreach($room_types as $key => $room_type){
				$room_type_name=$this->getRoomTypeName($room_type);
			$result 					= $cmOtaRoomTypeSynchronizeModel->getOtaRoomType($room_type,$ota_id);
			if(!empty($result)){
			$log_data               	= [
										"action_id"          => 4,
										"hotel_id"           => $bucket_hotel_id,
										"ota_id"      		 => $bucket_ota_id,
										"booking_ref_id"     => '',
										"inventory_ref_id"   => $bucket_inventory_table_id,
										"rate_ref_id"        => '',
										"user_id"            => $user_id,
										"request_msg"        => '',
										"response_msg"       => '',
										"request_url"        => '',
										"status"         	 => 2,
										"ip"         		 => $inventory_client_ip,
										"comment"			 => "Processing for update "
										];
			    $room_code     		 	= $result;

			
				$room_quanty = $room_qtys[$key];
                if($room_quanty >= 0){

				$xml ='<?xml version="1.0" encoding="UTF-8" ?>
				<Website Name="ingoibibo" HotelCode="'.$ota_hotel_code.'">
				<Room>
				<RoomTypeCode>'.$room_code.'</RoomTypeCode>
				<StartDate Format="yyyy-mm-dd">'.$startDate.'</StartDate>
				<EndDate Format="yyyy-mm-dd">'.$endDate.'</EndDate>
				<DaysOfWeek Mon="True" Tue="True" Wed="True" Thu="True" Fri="True" Sat="True" Sun="True"></DaysOfWeek>
				<MinLOS>'.$inventory_los.'</MinLOS>
				<Available>'.$room_quanty.'</Available>
				<StopSell>False</StopSell>
				</Room>
				</Website>';

				$log_request_msg = $xml;
				
				$url = $commonUrl.'updateroominventory/?bearer_token='.$bearer_token.'&channel_token='.$channel_token;
				$logModel->fill($log_data)->save();
                dd($url,$xml,$headers);
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
					$array_data = json_decode(json_encode($resultXml), true);
					if(!isset($array_data['Error'])){
						DB::table('log_table')->where('id', $logModel->id)
						->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$ota_rlt]);
						$rlt[$room_type_name]=array('status'=>1,'response_msg'=>$ota_rlt);
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
                } // $room_quanty >= 0
			}else{
			// set log for Booking Room Type is not synch with hotel Room Type.
			$log_data                 	= [
										"action_id"          => 4,
										"hotel_id"           => $bucket_hotel_id,
										"ota_id"      		 => $bucket_ota_id,
										"booking_ref_id"     => '',
										"inventory_ref_id"   => $bucket_inventory_table_id,
										"rate_ref_id"        => '',
										"user_id"            => $user_id,
										"request_msg"        => '',
										"response_msg"       => '',
										"request_url"        => '',
										"status"         	 => 0,
										"ip"         		 => $inventory_client_ip,
										"comment"			 => "Booking room type is not mapped."
										];
			$logModel->fill($log_data)->save();
			$rlt[$room_type_name]=array('status'=>0,'response_msg'=>"Roomtype should be sync");
			} // If else !empty($result) closed here.
			}// foreach $room_types	 closed here.
			return $rlt;
        }
	/*------------------- Goibibo Update Function End------------------------*/
	/*------------------- Expedia Update Function Start------------------------*/
    public function expediaUpdate($bucket_data,$inventory_data,$user_id)
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
			$bucket_inventory_table_id      = $bucket_data['bucket_inventory_table_id'];
			$bucket_ota_hotel_code          = $bucket_data['bucket_ota_hotel_code'];
			
			/*------------------ Get Booking Data--------------------*/
			$inventory_inventory_id         = $inventory_data['inventory_inventory_id'];
			$inventory_hotel_id            	= $inventory_data['inventory_hotel_id'];     
			$inventory_room_type_id       	= $inventory_data['inventory_room_type_id'];
			$inventory_no_of_rooms          = $inventory_data['inventory_no_of_rooms'];
			$inventory_date_from           	= $inventory_data['inventory_date_from'];
			$inventory_date_to          	= $inventory_data['inventory_date_to'];
			$inventory_client_ip            = $inventory_data['inventory_client_ip'];
			$inventory_los                  = $inventory_data['inventory_los'];

			/*------------------ Start Date and End Date----------- */
			$startDate                      = $inventory_date_from;
        	$endDate                        = $inventory_date_to; 

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
			
			$room_types 					= explode(",", $inventory_room_type_id);
			$room_qtys 					    = explode(",", $inventory_no_of_rooms);
			$rlt=array();
			foreach($room_types as $key => $room_type){
				$room_type_name=$this->getRoomTypeName($room_type);
			$result 					= $cmOtaRoomTypeSynchronizeModel->getOtaRoomType($room_type,$ota_id);
			
			if(!empty($result)){
			$log_data               	= [
										"action_id"          => 4,
										"hotel_id"           => $bucket_hotel_id,
										"ota_id"      		 => $bucket_ota_id,
										"booking_ref_id"     => '',
										"inventory_ref_id"   => $bucket_inventory_table_id,
										"rate_ref_id"        => '',
										"user_id"            => $user_id,
										"request_msg"        => '',
										"response_msg"       => '',
										"request_url"        => '',
										"status"         	 => 2,
										"ip"         		 => $inventory_client_ip,
										"comment"			 => "Processing for update "
										];
			$room_code     				= $result;

			
			
			
			$room_quanty = $room_qtys[$key];
			if($room_quanty >= 0){
			$xml          = '<?xml version="1.0" encoding="UTF-8"?>
			<!--Sample AR request message: updating total allocation of a room type-->
			<AvailRateUpdateRQ xmlns="http://www.expediaconnect.com/EQC/AR/2011/06">
			<Authentication username="'.$username.'" password="'.$password.'"/>
			<Hotel id="'.$ota_hotel_code.'"/>
			<AvailRateUpdate>
			<DateRange from="'.$startDate.'" to="'.$endDate.'"/>
			<RoomType id="'.$room_code.'" closed="false">
			<Inventory totalInventoryAvailable="'.$room_quanty.'"/>
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
					}
					else{
						DB::table('log_table')->where('id', $logModel->id)
						->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
						$rlt[$room_type_name]=array('status'=>0,'response_msg'=>$result);
					}
				}
				else{
					$rlt[$room_type_name]=array('status'=>0,'response_msg'=>$result);
				}
				} // $room_quanty >= 0
			}else{
			// set log for Booking Room Type is not synch with hotel Room Type.
			$log_data                 	= [
										"action_id"          => 4,
										"hotel_id"           => $bucket_hotel_id,
										"ota_id"      		 => $bucket_ota_id,
										"booking_ref_id"     => '',
										"inventory_ref_id"   => $bucket_inventory_table_id,
										"rate_ref_id"        => '',
										"user_id"            => $user_id,
										"request_msg"        => '',
										"response_msg"       => '',
										"request_url"        => '',
										"status"         	 => 0,
										"ip"         		 => $inventory_client_ip,
										"comment"			 => "Booking room type is not mapped."
										];
			$logModel->fill($log_data)->save();
			$rlt[$room_type_name]=array('status'=>0,'response_msg'=>"Roomtype should be sync");
			
			} // If else !empty($result) closed here.
			} // foreach $room_types closed here.
			return $rlt;
      }
    /*------------------- Expedia Update Function End------------------------*/


    /*------------------- Booking.com Update Function Start------------------------*/
    public function bookingdotcomUpdate($bucket_data,$inventory_data,$user_id)
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
			$bucket_inventory_table_id      = $bucket_data['bucket_inventory_table_id'];
			$bucket_ota_hotel_code          = $bucket_data['bucket_ota_hotel_code'];
			


			/*------------------ Get Booking Data--------------------*/
			$inventory_inventory_id         = $inventory_data['inventory_inventory_id'];
			$inventory_hotel_id            	= $inventory_data['inventory_hotel_id'];     
			$inventory_room_type_id       	= $inventory_data['inventory_room_type_id'];
			$inventory_no_of_rooms          = $inventory_data['inventory_no_of_rooms'];
			$inventory_date_from           	= $inventory_data['inventory_date_from'];
			$inventory_date_to          	= $inventory_data['inventory_date_to'];
			$inventory_client_ip            = $inventory_data['inventory_client_ip'];
			$inventory_los                  = $inventory_data['inventory_los'];

			/*------------------ Start Date and End Date----------- */
			$startDate                      = $inventory_date_from;
			$endDate    					= date('Y-m-d', strtotime('+1 days', strtotime($inventory_date_to)));

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
			
			$room_types 					= explode(",", $inventory_room_type_id);
			$room_qtys 					    = explode(",", $inventory_no_of_rooms);
			$rlt=array();
			foreach($room_types as $key => $room_type){
				$room_type_name=$this->getRoomTypeName($room_type);
			$result 				= $cmOtaRoomTypeSynchronizeModel->getOtaRoomType($room_type,$ota_id);
			if(!empty($result)){
			$log_data               	= [
										"action_id"          => 4,
										"hotel_id"           => $bucket_hotel_id,
										"ota_id"      		 => $bucket_ota_id,
										"booking_ref_id"     => '',
										"inventory_ref_id"   => $bucket_inventory_table_id,
										"rate_ref_id"        => '',
										"user_id"            => $user_id,
										"request_msg"        => '',
										"response_msg"       => '',
										"request_url"        => '',
										"status"         	 => 2,
										"ip"         		 => $inventory_client_ip,
										"comment"			 => "Processing for update "
										];
			
			$room_code     				= $result;
	        $ratePlanTypeSynchronizeData  = CmOtaRatePlanSynchronize::
											select('*')
											->where('ota_room_type_id', '=' ,$room_code)
											->first(); 	
			$rateplan_code = $ratePlanTypeSynchronizeData->ota_rate_plan_id; 
	
			$room_quanty = $room_qtys[$key];
			if($room_quanty >= 0){

			$xml          = '<request>
			<username>'.$username.'</username>
			<password>'.$password.'</password>
			<hotel_id>'.$ota_hotel_code.'</hotel_id>
			<version>1.0</version>
			<room id="'.$room_code.'">
			<date from="'.$startDate.'" to="'.$endDate.'">
			<roomstosell>'.$room_quanty.'</roomstosell>
			<rate id="'.$rateplan_code.'"/>
			<minimumstay>'.$inventory_los.'</minimumstay>
			<closed>0</closed>
			</date>
			</room>
			</request>';
			$log_request_msg = $xml;
			$url         	 = $commonUrl.'availability';
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
				if(strpos($result, '<error>' ) !== false){
					DB::table('log_table')->where('id', $logModel->id)
					->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
					$rlt[$room_type_name]=array('status'=>0,'response_msg'=>$result);
				}
				else if(strpos($result, '<warning>' ) !== false){
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
			}
			}else{
			// set log for Booking Room Type is not synch with hotel Room Type.
			$log_data                 	= [
										"action_id"          => 4,
										"hotel_id"           => $bucket_hotel_id,
										"ota_id"      		 => $bucket_ota_id,
										"booking_ref_id"     => '',
										"inventory_ref_id"   => $bucket_inventory_table_id,
										"rate_ref_id"        => '',
										"user_id"            => $user_id,
										"request_msg"        => '',
										"response_msg"       => '',
										"request_url"        => '',
										"status"         	 => 0,
										"ip"         		 => $inventory_client_ip,
										"comment"			 => "Booking room type is not mapped."
										];
			$logModel->fill($log_data)->save();
			$rlt[$room_type_name]=array('status'=>0,'response_msg'=>"Roomtype should be sync");
			
			} // If else !empty($result) closed here.
			} // foreach $room_types closed here.
			return $rlt;
	}

    /*------------------- Booking.com Update Function End------------------------*/

    /*------------------- Via.com Update Function Start------------------------*/
    public function viadotcomUpdate($bucket_data,$inventory_data,$user_id)
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
			$bucket_inventory_table_id      = $bucket_data['bucket_inventory_table_id'];
			$bucket_ota_hotel_code          = $bucket_data['bucket_ota_hotel_code'];
			


			/*------------------ Get Booking Data--------------------*/
			$inventory_inventory_id         = $inventory_data['inventory_inventory_id'];
			$inventory_hotel_id            	= $inventory_data['inventory_hotel_id'];     
			$inventory_room_type_id       	= $inventory_data['inventory_room_type_id'];
			$inventory_no_of_rooms          = $inventory_data['inventory_no_of_rooms'];
			$inventory_date_from           	= $inventory_data['inventory_date_from'];
			$inventory_date_to          	= $inventory_data['inventory_date_to'];
			$inventory_client_ip            = $inventory_data['inventory_client_ip'];
			$inventory_los                  = $inventory_data['inventory_los'];

			/*------------------ Start Date and End Date----------- */
			$startDate                      = $inventory_date_from;
        	$endDate                        = $inventory_date_to; 

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
			/*$headers = array (
			//Regulates versioning of the XML interface for the API
			'Content-Type: application/json'
			);*/
			$headers = array('Content-Type:application/json', 'Expect:');
			
			$room_types 					= explode(",", $inventory_room_type_id);
			$room_qtys 					    = explode(",", $inventory_no_of_rooms);
			$rlt=array();
			foreach($room_types as $key => $room_type){
				$room_type_name=$this->getRoomTypeName($room_type);
			$result 				=  $cmOtaRoomTypeSynchronizeModel->getOtaRoomType($room_type,$ota_id);

			if(!empty($result)){
			$log_data               	= [
										"action_id"          => 4,
										"hotel_id"           => $bucket_hotel_id,
										"ota_id"      		 => $bucket_ota_id,
										"booking_ref_id"     => '',
										"inventory_ref_id"   => $bucket_inventory_table_id,
										"rate_ref_id"        => '',
										"user_id"            => $user_id,
										"request_msg"        => '',
										"response_msg"       => '',
										"request_url"        => '',
										"status"         	 => 2,
										"ip"         		 => $inventory_client_ip,
										"comment"			 => "Processing for update "
										];
			$room_code     				= $result;


			
			$room_quanty = $room_qtys[$key];
			if($room_quanty >= 0){
			$url= $commonUrl.'newWebserviceAPI?actionId=cm_updateroominventory&typeId=hotel&source='.$source.'&auth='.$auth.'&requestData={"hotelId":'.$ota_hotel_code.',"roomId":'.$room_code.',"startDate":"'.$startDate.'","endDate":"'.$endDate.'","available":'.$room_quanty.',"stopSell":"false"}';
			

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
			//$curl_log = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
			curl_close($ch);
				$array_data = (array) json_decode($result);
				if(isset($array_data['Success'])){
					DB::table('log_table')->where('id', $logModel->id)
					->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
					$rlt[$room_type_name]=array('status'=>1,'response_msg'=>$result);
					
					}else{
						DB::table('log_table')->where('id', $logModel->id)
						->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
						$rlt[$room_type_name]=array('status'=>0,'response_msg'=>$result);
						
					}
					} // $room_quanty >= 0
			
			}else{
			// set log for Booking Room Type is not synch with hotel Room Type.
			$log_data                 	= [
										"action_id"          => 4,
										"hotel_id"           => $bucket_hotel_id,
										"ota_id"      		 => $bucket_ota_id,
										"booking_ref_id"     => '',
										"inventory_ref_id"   => $bucket_inventory_table_id,
										"rate_ref_id"        => '',
										"user_id"            =>  $user_id,
										"request_msg"        => '',
										"response_msg"       => '',
										"request_url"        => '',
										"status"         	 => 0,
										"ip"         		 => $inventory_client_ip,
										"comment"			 => "Booking room type is not mapped."
										];
			$logModel->fill($log_data)->save();
			$rlt[$room_type_name]=array('status'=>0,'response_msg'=>"Roomtype should be sync");
			
			} // If else !empty($result) closed here.
			} // foreach $room_types closed here.
			return $rlt;
	}
	/*------------------- Via.com Update Function End------------------------*/
	/*------------------- Travelguru Update Function Start------------------------*/
    public function travelguruUpdate($bucket_data,$inventory_data,$user_id)
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
			$bucket_inventory_table_id      = $bucket_data['bucket_inventory_table_id'];
			$bucket_ota_hotel_code          = $bucket_data['bucket_ota_hotel_code'];
			


			/*------------------ Get Booking Data--------------------*/
			$inventory_inventory_id         = $inventory_data['inventory_inventory_id'];
			$inventory_hotel_id            	= $inventory_data['inventory_hotel_id'];     
			$inventory_room_type_id       	= $inventory_data['inventory_room_type_id'];
			$inventory_no_of_rooms          = $inventory_data['inventory_no_of_rooms'];
			$inventory_date_from           	= $inventory_data['inventory_date_from'];
			$inventory_date_to          	= $inventory_data['inventory_date_to'];
			$inventory_client_ip            = $inventory_data['inventory_client_ip'];
			$inventory_los                  = $inventory_data['inventory_los'];

			/*------------------ Start Date and End Date----------- */
			$startDate                      = $inventory_date_from;
        	$endDate                        = $inventory_date_to; 

			/*------------------ Get Specific Ota Details-----------*/

			$ota_details_data             	= CmOtaDetails::select('*')
												->where('hotel_id', '=' ,$bucket_hotel_id)
												->where('ota_id', '=' ,$bucket_ota_id)
												->first(); 	 	

			$ota_id 					  	= $ota_details_data->ota_id;
			$ota_hotel_code 			  	= $ota_details_data->ota_hotel_code;
			$auth_parameter       			= json_decode($ota_details_data->auth_parameter);
            $MessagePassword      			= trim($auth_parameter->MessagePassword);
            $ID                   			= trim($auth_parameter->ID);
            $commonUrl      				= $ota_details_data->url;

            /*------------------ set header ------------------ */
			$headers = array (
			//Regulates versioning of the XML interface for the API
			'Content-Type: application/xml'
			);
			
			$room_types 					= explode(",", $inventory_room_type_id);
			$room_qtys 					    = explode(",", $inventory_no_of_rooms);
			$rlt=array();

			foreach($room_types as $key => $room_type){
				$room_type_name=$this->getRoomTypeName($room_type);
			$result 				= $cmOtaRoomTypeSynchronizeModel->getOtaRoomType($room_type,$ota_id);
			
			if(!empty($result)){
			$log_data               	= [
										"action_id"          => 4,
										"hotel_id"           => $bucket_hotel_id,
										"ota_id"      		 => $bucket_ota_id,
										"booking_ref_id"     => '',
										"inventory_ref_id"   => $bucket_inventory_table_id,
										"rate_ref_id"        => '',
										"user_id"            => $user_id,
										"request_msg"        => '',
										"response_msg"       => '',
										"request_url"        => '',
										"status"         	 => 2,
										"ip"         		 => $inventory_client_ip,
										"comment"			 => "Processing for update "
										];
			$room_code     		 	= $result;


			
			$room_quanty = $room_qtys[$key];
			if($room_quanty >= 0){
			

			$xml = '<OTA_HotelAvailNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05">
			<POS>
			<Source>
			<RequestorID MessagePassword="'.$MessagePassword.'" ID="'.$ID.'" Type="CHM"/>
			</Source>
			</POS>
			<AvailStatusMessages HotelCode="'.$ota_details_data->ota_hotel_code.'">
			<AvailStatusMessage BookingLimit="'.$room_quanty.'">
			<StatusApplicationControl Mon="true" Tue="true" Weds="true" Thur="true" Sun="true" Sat="true"  Fri="true" Start="'.$startDate.'" End="'.$endDate.'" InvCode="'.$room_code.'"/>
			<RestrictionStatus SellThroughOpenIndicator="false"/>
			</AvailStatusMessage>
			</AvailStatusMessages>
			</OTA_HotelAvailNotifRQ>';



			$log_request_msg = $xml;
			$url         	 = $commonUrl.'availability/update';
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
						$rlt[$room_type_name]=array('status'=>1,'response_msg'=>"updated successfully");
					}else{
						DB::table('log_table')->where('id', $logModel->id)
						->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
						$rlt[$room_type_name]=array('status'=>0,'response_msg'=>$array_data['errors']);
					}
				}
				else{
					$rlt[$room_type_name]=array('status'=>0,'response_msg'=>$result);
					}
				}
			}else{
			// set log for Booking Room Type is not synch with hotel Room Type.
			$log_data                 	= [
										"action_id"          => 4,
										"hotel_id"           => $bucket_hotel_id,
										"ota_id"      		 => $bucket_ota_id,
										"booking_ref_id"     => '',
										"inventory_ref_id"   => $bucket_inventory_table_id,
										"rate_ref_id"        => '',
										"user_id"            => $user_id,
										"request_msg"        => '',
										"response_msg"       => '',
										"request_url"        => '',
										"status"         	 => 0,
										"ip"         		 => $inventory_client_ip,
										"comment"			 => "Booking room type is not mapped."
										];
			$logModel->fill($log_data)->save();
			$rlt[$room_type_name]=array('status'=>0,'response_msg'=>"Roomtype should be sync");
			
			} // If else !empty($result) closed here.
			} // foreach $room_types closed here.
			return $rlt;
	}
	/*------------------- Travelguru Update Function End------------------------*/
	/*------------------- Airbnb Update Function Start------------------------*/
    public function airbnbUpdate($bucket_data,$inventory_data,$user_id)
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
			$bucket_inventory_table_id      = $bucket_data['bucket_inventory_table_id'];
			$bucket_ota_hotel_code          = $bucket_data['bucket_ota_hotel_code'];
			


			/*------------------ Get Booking Data--------------------*/
			$inventory_inventory_id         = $inventory_data['inventory_inventory_id'];
			$inventory_hotel_id            	= $inventory_data['inventory_hotel_id'];     
			$inventory_room_type_id       	= $inventory_data['inventory_room_type_id'];
			$inventory_no_of_rooms          = $inventory_data['inventory_no_of_rooms'];
			$inventory_date_from           	= $inventory_data['inventory_date_from'];
			$inventory_date_to          	= $inventory_data['inventory_date_to'];
			$inventory_client_ip            = $inventory_data['inventory_client_ip'];
			$inventory_los                  = $inventory_data['inventory_los'];

			/*------------------ Start Date and End Date----------- */
			$startDate                      = $inventory_date_from;
        	$endDate                        = $inventory_date_to; 

			/*------------------ Get Specific Ota Details-----------*/

			$ota_details_data             	= CmOtaDetails::select('*')
												->where('hotel_id', '=' ,$bucket_hotel_id)
												->where('ota_id', '=' ,$bucket_ota_id)
												->first(); 	 	

			$ota_id 					  	= $ota_details_data->ota_id;
			$ota_hotel_code 			  	= $ota_details_data->ota_hotel_code;
			$auth_parameter       			= json_decode($ota_details_data->auth_parameter);
            $api_key               			= trim($auth_parameter->X_Airbnb_API_Key);
			$commonUrl      				= $ota_details_data->url;
			$hotel_info=HotelInformation::where('hotel_id',$bucket_hotel_id)->first();
			$airbnbModel=new AirbnbListingDetails();
			$company= new CompanyDetails();
			$comp_details=$company->where('company_id',$hotel_info->company_id)->select('airbnb_refresh_token')->first();
			$refresh_token=$comp_details->airbnb_refresh_token;

			$oauth_Token      = $airbnbModel->getAirBnbToken($refresh_token);
            /*------------------ set header ------------------ */
			
			$room_types 					= explode(",", $inventory_room_type_id);
			$room_qtys 					    = explode(",", $inventory_no_of_rooms);
			$rlt=array();
			foreach($room_types as $key => $room_type){
				$room_type_name=$this->getRoomTypeName($room_type);
			$result 				= $cmOtaRoomTypeSynchronizeModel->getOtaRoomType($room_type,$ota_id);

			if(!empty($result)){
			$log_data               	= [
										"action_id"          => 4,
										"hotel_id"           => $bucket_hotel_id,
										"ota_id"      		 => $bucket_ota_id,
										"booking_ref_id"     => '',
										"inventory_ref_id"   => $bucket_inventory_table_id,
										"rate_ref_id"        => '',
										"user_id"            => $user_id,
										"request_msg"        => '',
										"response_msg"       => '',
										"request_url"        => '',
										"status"         	 => 2,
										"ip"         		 => $inventory_client_ip,
										"comment"			 => "Processing for update "
										];
			$room_code     		 	= $result;
			
			$room_quanty = $room_qtys[$key];
			
			if($room_quanty >= 0){
				$post_data=array();
				$post_data['listing_id']=$room_code;
				$operations=array();
				$operations['dates']=array($startDate .":".$endDate );
				$operations['availability']="available";
				$operations['available_count']=$room_quanty;
				$post_data['operations']=array($operations);
				$post_data=json_encode($post_data);
				$log_request_msg="";
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, "$commonUrl/calendar_operations?_allow_dates_overlap=true");
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
				curl_setopt($ch, CURLOPT_POST, 1);
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
			} // $room_quanty >= 0
			
			}else{
			// set log for Booking Room Type is not synch with hotel Room Type.
			$log_data                 	= [
										"action_id"          => 4,
										"hotel_id"           => $bucket_hotel_id,
										"ota_id"      		 => $bucket_ota_id,
										"booking_ref_id"     => '',
										"inventory_ref_id"   => $bucket_inventory_table_id,
										"rate_ref_id"        => '',
										"user_id"            => $user_id,
										"request_msg"        => '',
										"response_msg"       => '',
										"request_url"        => '',
										"status"         	 => 0,
										"ip"         		 => $inventory_client_ip,
										"comment"			 => "Booking room type is not mapped."
										];
			$logModel->fill($log_data)->save();
			$rlt[$room_type_name]=array('status'=>0,'response_msg'=>"Roomtype should be sync");
			
			} // If else !empty($result) closed here.
			} // foreach $room_types closed here.
			return $rlt;
	}
	/*------------------- Airbnb Update Function End------------------------*/

	/*------------------- Goomo Update Function Start------------------------*/
    public function goomoUpdate($bucket_data,$inventory_data,$user_id)
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
			$bucket_inventory_table_id      = $bucket_data['bucket_inventory_table_id'];
			$bucket_ota_hotel_code          = $bucket_data['bucket_ota_hotel_code'];
			


			/*------------------ Get Booking Data--------------------*/
			$inventory_inventory_id         = $inventory_data['inventory_inventory_id'];
			$inventory_hotel_id            	= $inventory_data['inventory_hotel_id'];     
			$inventory_room_type_id       	= $inventory_data['inventory_room_type_id'];
			$inventory_no_of_rooms          = $inventory_data['inventory_no_of_rooms'];
			$inventory_date_from           	= $inventory_data['inventory_date_from'];
			$inventory_date_to          	= $inventory_data['inventory_date_to'];
			$inventory_client_ip            = $inventory_data['inventory_client_ip'];
			$inventory_los                  = $inventory_data['inventory_los'];

			/*------------------ Start Date and End Date----------- */
			$startDate                      = $inventory_date_from;
        	$endDate                        = $inventory_date_to; 

			/*------------------ Get Specific Ota Details-----------*/

			$ota_details_data             	= CmOtaDetails::select('*')
												->where('hotel_id', '=' ,$bucket_hotel_id)
												->where('ota_id', '=' ,$bucket_ota_id)
												->first(); 	 	

			$ota_id 					  	= $ota_details_data->ota_id;
			$ota_hotel_code 			  	= $ota_details_data->ota_hotel_code;
			$auth_parameter       			= json_decode($ota_details_data->auth_parameter);
			$apiKey               			= trim($auth_parameter->apiKey);
			$channelId               		= trim($auth_parameter->channelId);
			$accessToken               		= trim($auth_parameter->accessToken);
			$commonUrl      				= $ota_details_data->url;
            /*------------------ set header ------------------ */
			
			$room_types 					= explode(",", $inventory_room_type_id);
			$room_qtys 					    = explode(",", $inventory_no_of_rooms);
			$rlt=array();
			foreach($room_types as $key => $room_type){
				$room_type_name=$this->getRoomTypeName($room_type);
			$result 				= $cmOtaRoomTypeSynchronizeModel->getOtaRoomType($room_type,$ota_id);

			if(!empty($result)){
			$log_data               	= [
										"action_id"          => 4,
										"hotel_id"           => $bucket_hotel_id,
										"ota_id"      		 => $bucket_ota_id,
										"booking_ref_id"     => '',
										"inventory_ref_id"   => $bucket_inventory_table_id,
										"rate_ref_id"        => '',
										"user_id"            => $user_id,
										"request_msg"        => '',
										"response_msg"       => '',
										"request_url"        => '',
										"status"         	 => 2,
										"ip"         		 => $inventory_client_ip,
										"comment"			 => "Processing for update "
										];
			$room_code     		 	= $result;


			
			$room_quanty = $room_qtys[$key];
			$days=array('true', 'true', 'true','true','true','true','true');
			$post_data=array("available" => $room_quanty, "block"=>false, "days"=>$days, "channelName"=> "Bookingjini", 
			"startDate" => $startDate,
			"endDate" =>$endDate,
			"roomId" => $room_code,
			"productId"=>$bucket_ota_hotel_code);
			$post_data=json_encode($post_data);
			if($room_quanty >= 0){
			
				$log_request_msg="";
				$log_request_msg=$commonUrl.'/updateInventory'.$post_data;	
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, "$commonUrl/updateInventory");
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
			} // $room_quanty >= 0
			
			}else{
			// set log for Booking Room Type is not synch with hotel Room Type.
			$log_data                 	= [
										"action_id"          => 4,
										"hotel_id"           => $bucket_hotel_id,
										"ota_id"      		 => $bucket_ota_id,
										"booking_ref_id"     => '',
										"inventory_ref_id"   => $bucket_inventory_table_id,
										"rate_ref_id"        => '',
										"user_id"            => $user_id,
										"request_msg"        => '',
										"response_msg"       => '',
										"request_url"        => '',
										"status"         	 => 0,
										"ip"         		 => $inventory_client_ip,
										"comment"			 => "Booking room type is not mapped."
										];
			$logModel->fill($log_data)->save();
			$rlt[$room_type_name]=array('status'=>0,'response_msg'=>"Roomtype should be sync");
			
			} // If else !empty($result) closed here.
			} // foreach $room_types closed here.
			return $rlt;
	}
		/*---------------------Nodal updateInventory Function Start-----------------*/
		public function nodalUpdate($bucket_data,$inventory_data,$user_id)
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
				$bucket_inventory_table_id      = $bucket_data['bucket_inventory_table_id'];
				$bucket_ota_hotel_code          = $bucket_data['bucket_ota_hotel_code'];
				
				/*------------------ Get Booking Data--------------------*/
				$inventory_inventory_id         = $inventory_data['inventory_inventory_id'];
				$inventory_hotel_id            	= $inventory_data['inventory_hotel_id'];     
				$inventory_room_type_id       	= $inventory_data['inventory_room_type_id'];
				$inventory_no_of_rooms          = $inventory_data['inventory_no_of_rooms'];
				$inventory_date_from           	= $inventory_data['inventory_date_from'];
				$inventory_date_to          	= $inventory_data['inventory_date_to'];
				$inventory_client_ip            = $inventory_data['inventory_client_ip'];
				$inventory_los                  = $inventory_data['inventory_los'];
				/*------------------ Start Date and End Date----------- */
				$startDate                      = $inventory_date_from;
				$endDate                        = $inventory_date_to; 
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
				/*------------------ set header ------------------ */
				$room_types 					= explode(",", $inventory_room_type_id);
				$room_qtys 					    = explode(",", $inventory_no_of_rooms);
				$rlt=array();
				foreach($room_types as $key => $room_type){
					$room_type_name=$this->getRoomTypeName($room_type);
				$result 				= $cmOtaRoomTypeSynchronizeModel->getOtaRoomType($room_types,$ota_id);
				if(!empty($result)){
				$log_data               	= [
											"action_id"          => 4,
											"hotel_id"           => $bucket_hotel_id,
											"ota_id"      		 => $bucket_ota_id,
											"booking_ref_id"     => '',
											"inventory_ref_id"   => $bucket_inventory_table_id,
											"rate_ref_id"        => '',
											"user_id"            => $user_id,
											"request_msg"        => '',
											"response_msg"       => '',
											"request_url"        => '',
											"status"         	 => 2,
											"ip"         		 => $inventory_client_ip,
											"comment"			 => "Processing for update "
											];
				$room_code     		 	= $result;
				$room_quanty = $room_qtys[$key];
				$url=$commonUrl.'/UpdateInventory.php';
				$post_data=array("access_token"=>$access_token,"property_id"=>$bucket_ota_hotel_code,"room_id"=>$room_code,"from_date"=>$startDate,"to_date"=>$endDate,"availability"=>$room_quanty);
				$post_data=json_encode($post_data); 
				$log_request_msg=$url.$post_data;
				if($room_quanty >= 0){
					$log_request_msg="";
					$ch = curl_init();
					curl_setopt($ch, CURLOPT_URL, $url);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
					curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
					curl_setopt($ch, CURLOPT_POST, 1);
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
						$rlt[$room_type_name]=array('status'=>0,'response_msg'=>$result);
						
					}
				} // $room_quanty >= 0
				
				}else{
				// set log for Booking Room Type is not synch with hotel Room Type.
				$log_data                 	= [
											"action_id"          => 4,
											"hotel_id"           => $bucket_hotel_id,
											"ota_id"      		 => $bucket_ota_id,
											"booking_ref_id"     => '',
											"inventory_ref_id"   => $bucket_inventory_table_id,
											"rate_ref_id"        => '',
											"user_id"            => $user_id,
											"request_msg"        => '',
											"response_msg"       => '',
											"request_url"        => '',
											"status"         	 => 0,
											"ip"         		 => $inventory_client_ip,
											"comment"			 => "Booking room type is not mapped."
											];
				$logModel->fill($log_data)->save();
				$rlt[$room_type_name]=array('status'=>0,'response_msg'=>"Roomtype should be sync");
				
				} // If else !empty($result) closed here.
				} // foreach $room_types closed here.
				return $rlt;
		}
		/*---------------------Nodal updateInventory Function End-----------------*/
		/*------------------- easemytrip Update Function Start------------------------*/
		public function easemytripUpdate($bucket_data,$inventory_data,$user_id)
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
				$bucket_inventory_table_id      = $bucket_data['bucket_inventory_table_id'];
				$bucket_ota_hotel_code          = $bucket_data['bucket_ota_hotel_code'];
				
	
	
				/*------------------ Get Booking Data--------------------*/
				$inventory_inventory_id         = $inventory_data['inventory_inventory_id'];
				$inventory_hotel_id            	= $inventory_data['inventory_hotel_id'];     
				$inventory_room_type_id       	= $inventory_data['inventory_room_type_id'];
				$inventory_no_of_rooms          = $inventory_data['inventory_no_of_rooms'];
				$inventory_date_from           	= $inventory_data['inventory_date_from'];
				$inventory_date_to          	= $inventory_data['inventory_date_to'];
				$inventory_client_ip            = $inventory_data['inventory_client_ip'];
				$inventory_los                  = $inventory_data['inventory_los'];
	
				/*------------------ Start Date and End Date----------- */
				$startDate                      = $inventory_date_from;
				$endDate                        = $inventory_date_to; 
	
				/*------------------ Get Specific Ota Details-----------*/
	
				$ota_details_data             	= CmOtaDetails::select('*')
													->where('hotel_id', '=' ,$bucket_hotel_id)
													->where('ota_id', '=' ,$bucket_ota_id)
													->first(); 	
	
				$ota_id 					  	= $ota_details_data->ota_id;
				$ota_hotel_code 			  	= $ota_details_data->ota_hotel_code;
				$auth_parameter 			  	= json_decode($ota_details_data->auth_parameter);
				$token_key						= trim($auth_parameter->Token);
				$commonUrl      				= $ota_details_data->url;
	
				/*------------------ set header ------------------ */
				/*$headers = array (
				//Regulates versioning of the XML interface for the API
				'Content-Type: application/json'
				);*/
				$headers = array('Content-Type:application/json');
				
				$room_types 					= explode(",", $inventory_room_type_id);
				$room_qtys 					    = explode(",", $inventory_no_of_rooms);
				$rlt=array();
				foreach($room_types as $key => $room_type){
					$room_type_name=$this->getRoomTypeName($room_type);
				$result 				=  $cmOtaRoomTypeSynchronizeModel->getOtaRoomType($room_type,$ota_id);
	
				if(!empty($result)){
				$log_data               	= [
											"action_id"          => 4,
											"hotel_id"           => $bucket_hotel_id,
											"ota_id"      		 => $bucket_ota_id,
											"booking_ref_id"     => '',
											"inventory_ref_id"   => $bucket_inventory_table_id,
											"rate_ref_id"        => '',
											"user_id"            => $user_id,
											"request_msg"        => '',
											"response_msg"       => '',
											"request_url"        => '',
											"status"         	 => 2,
											"ip"         		 => $inventory_client_ip,
											"comment"			 => "Processing for update "
											];
				$room_code     				= $result;
				
				$room_quanty = $room_qtys[$key];
				if($room_quanty >= 0){
					$commonUrl = $commonUrl.'/save';
				$logModel->fill($log_data)->save();
				$post_data=' {
					"RequestType": "SaveSupplierHotel",
					"Token": "'.$token_key.'",
					"HotelCode": "'.$ota_hotel_code.'",
					"Data": [
					{
					"RequestType": "UpdateAllocation",
					"Data": [
					{
					"RoomCode": "'.$room_code.'",
					"From": "'.$startDate.'",
					"To": "'.$endDate.'",
					"Allocation":'.$room_quanty.'
					}
					]
					}
					]
				   }';
				   $log_request_msg = $post_data;
				   $ch  = curl_init();
				   curl_setopt($ch, CURLOPT_URL, $commonUrl);
				   curl_setopt($ch, CURLOPT_POST, true);
				   curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
				   curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
				   curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				   curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				   if( curl_exec($ch) === false ){
				   $result = curl_error($ch);
				   }else{
				   $result = curl_exec($ch);
				   }
				   curl_close($ch);
				   $array_data = json_decode($result,true);
					if(isset($array_data['Success'])){
						DB::table('log_table')->where('id', $logModel->id)
						->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
						$rlt[$room_type_name]=array('status'=>1,'response_msg'=>$result);
						}else{
							DB::table('log_table')->where('id', $logModel->id)
							->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$result]);
							$rlt[$room_type_name]=array('status'=>0,'response_msg'=>$result);	
						}
						} // $room_quanty >= 0
				
				}else{
				// set log for Booking Room Type is not synch with hotel Room Type.
				$log_data                 	= [
											"action_id"          => 4,
											"hotel_id"           => $bucket_hotel_id,
											"ota_id"      		 => $bucket_ota_id,
											"booking_ref_id"     => '',
											"inventory_ref_id"   => $bucket_inventory_table_id,
											"rate_ref_id"        => '',
											"user_id"            =>  $user_id,
											"request_msg"        => '',
											"response_msg"       => '',
											"request_url"        => '',
											"status"         	 => 0,
											"ip"         		 => $inventory_client_ip,
											"comment"			 => "Booking room type is not mapped."
											];
				$logModel->fill($log_data)->save();
				$rlt[$room_type_name]=array('status'=>0,'response_msg'=>"Roomtype should be sync");
				
				} // If else !empty($result) closed here.
				} // foreach $room_types closed here.
				return $rlt;
		}
		/*------------------- easemytrip Update Function End------------------------*/
		/*------------------- paytm Update Function Start------------------------*/
		public function paytmUpdate($bucket_data,$inventory_data,$user_id)
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
				$bucket_inventory_table_id      = $bucket_data['bucket_inventory_table_id'];
				$bucket_ota_hotel_code          = $bucket_data['bucket_ota_hotel_code'];
				
	
	
				/*------------------ Get Booking Data--------------------*/
				$inventory_inventory_id         = $inventory_data['inventory_inventory_id'];
				$inventory_hotel_id            	= $inventory_data['inventory_hotel_id'];     
				$inventory_room_type_id       	= $inventory_data['inventory_room_type_id'];
				$inventory_no_of_rooms          = $inventory_data['inventory_no_of_rooms'];
				$inventory_date_from           	= $inventory_data['inventory_date_from'];
				$inventory_date_to          	= $inventory_data['inventory_date_to'];
				$inventory_client_ip            = $inventory_data['inventory_client_ip'];
				$inventory_los                  = $inventory_data['inventory_los'];
	
				/*------------------ Start Date and End Date----------- */
				$startDate                      = $inventory_date_from;
				$endDate                        = $inventory_date_to; 
	
				/*------------------ Get Specific Ota Details-----------*/
	
				$ota_details_data             	= CmOtaDetails::select('*')
													->where('hotel_id', '=' ,$bucket_hotel_id)
													->where('ota_id', '=' ,$bucket_ota_id)
													->first(); 	
	
				$ota_id 					  	= $ota_details_data->ota_id;
				$ota_hotel_code 			  	= $ota_details_data->ota_hotel_code;
				$auth_parameter 			  	= json_decode($ota_details_data->auth_parameter);
				$api_key 						= trim($auth_parameter->api_key);
				$commonUrl      				= $ota_details_data->url;
	
				/*------------------ set header ------------------ */
				/*$headers = array (
				//Regulates versioning of the XML interface for the API
				'Content-Type: application/json'
				);*/
				$headers = array('Content-Type:application/json');
				
				$room_types 					= explode(",", $inventory_room_type_id);
				$room_qtys 					    = explode(",", $inventory_no_of_rooms);
				$rlt=array();
				foreach($room_types as $key => $room_type){
					$room_type_name=$this->getRoomTypeName($room_type);
				$result 				=  $cmOtaRoomTypeSynchronizeModel->getOtaRoomType($room_type,$ota_id);
	
				if(!empty($result)){
				$log_data               	= [
											"action_id"          => 4,
											"hotel_id"           => $bucket_hotel_id,
											"ota_id"      		 => $bucket_ota_id,
											"booking_ref_id"     => '',
											"inventory_ref_id"   => $bucket_inventory_table_id,
											"rate_ref_id"        => '',
											"user_id"            => $user_id,
											"request_msg"        => '',
											"response_msg"       => '',
											"request_url"        => '',
											"status"         	 => 2,
											"ip"         		 => $inventory_client_ip,
											"comment"			 => "Processing for update "
											];
				$room_code     				= $result;
	
	
				
				$room_quanty = $room_qtys[$key];
				if($room_quanty >= 0){
					$commonUrl = $commonUrl.'/inventoryUpdate';
				$logModel->fill($log_data)->save();
				$post_data='{
						"auth": {
						"key": "'.$api_key.'"
						},
						"data": {
						"propertyId": "'.$ota_hotel_code.'",
						"roomId": "'.$room_code.'",
						"inventory": [
						{
						"startDate": "'.$startDate.'",
						"endDate": "'.$endDate.'",
						"free": '.$room_quanty.'
						}
						]
						}
						}';
				$log_request_msg = $post_data;
				$ch  = curl_init();
				curl_setopt($ch, CURLOPT_URL, $commonUrl);
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				if( curl_exec($ch) === false ){
				$result = curl_error($ch);
				}else{
				$result = curl_exec($ch);
				}
				curl_close($ch);
				$array_data = json_decode($result,true);
				//dd($commonUrl,$headers,$post_data,$result);
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
											"action_id"          => 4,
											"hotel_id"           => $bucket_hotel_id,
											"ota_id"      		 => $bucket_ota_id,
											"booking_ref_id"     => '',
											"inventory_ref_id"   => $bucket_inventory_table_id,
											"rate_ref_id"        => '',
											"user_id"            =>  $user_id,
											"request_msg"        => '',
											"response_msg"       => '',
											"request_url"        => '',
											"status"         	 => 0,
											"ip"         		 => $inventory_client_ip,
											"comment"			 => "Booking room type is not mapped."
											];
				$logModel->fill($log_data)->save();
				$rlt[$room_type_name]=array('status'=>0,'response_msg'=>"Roomtype should be sync");
				}
				} // If else !empty($result) closed here.
				} // foreach $room_types closed here.
				return $rlt;
		}
		/*------------------- paytm Update Function End------------------------*/
	}