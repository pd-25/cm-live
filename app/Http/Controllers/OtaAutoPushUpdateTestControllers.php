<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Mail;
use Validator;
use App\CmOtaDetails;
use App\CmOtaRatePlanSynchronize;
use App\CmOtaRoomTypeSynchronize;
use App\CmOtaAllAutoPush;
use App\CmBookingConfirmationResponse;
use App\BookingLog;
use App\LogTable;
use App\Inventory;
use App\CmOtaBooking;
use App\HotelInformation;
use App\City;
use App\State;
use App\OtaInventory;
use App\PmsAccount;
use DB;
use App\Http\Controllers\UpdateInventoryService;
use App\Http\Controllers\CmOtaBookingInvStatusService;
use App\Http\Controllers\IdsController;
use App\Http\Controllers\IpAddressService;
use App\Http\Controllers\otacontrollers\CmBookingDataInsertionController;

class OtaAutoPushUpdateTestControllers extends Controller{
		protected $inventoryService;
		private $updateInvService;
		protected $idsService,$ipService,$cmBookingDataInsertion;
    public function __construct(CmOtaBookingInvStatusService $cmOtaBookingInvStatusService,IdsController $idsService,IpAddressService $ipService,CmBookingDataInsertionController $cmBookingDataInsertion)
    {
			   $this->cmOtaBookingInvStatusService=$cmOtaBookingInvStatusService;
			   $this->idsService=$idsService;
				 $this->ipService=$ipService;
				 $this->cmBookingDataInsertion=$cmBookingDataInsertion;
    }
	public function bookingjiniUpdate($bucket_data,$booking_data){
		$log_request_msg="";
		$log_response_msg="";
		date_default_timezone_set("Asia/Calcutta");
		$cmOtaDetailsModel  			= new CmOtaDetails();
		$cmOtaRoomTypeSynchronizeModel  = new CmOtaRoomTypeSynchronize();
		$cmOtaRatePlanSynchronizeModel 	= new CmOtaRatePlanSynchronize();

		$date                     		= new \DateTime();
		$dateTimestamp            		= $date->format('Y-m-d H:i:s');

		/*------------------ Get Bucket Data--------------------*/
		$bucket_id                      = $bucket_data['bucket_id'];
		$bucket_hotel_id                = $bucket_data['bucket_hotel_id'];
		$bucket_ota_id                  = $bucket_data['bucket_ota_id'];
		$bucket_ota_name                = $bucket_data['bucket_ota_name'];
		$bucket_ota_booking_tabel_id    = $bucket_data['bucket_ota_booking_tabel_id'];
		$bucket_ota_hotel_code          = $bucket_data['bucket_ota_hotel_code'];
		$bucket_booking_status          = $bucket_data['bucket_booking_status'];

		/*------------------ Get Booking Data--------------------*/
		$booking_ota_id                 = $booking_data['booking_ota_id'];
		$booking_hotel_id             	= $booking_data['booking_hotel_id'];
		$booking_room_type            	= $booking_data['booking_room_type'];
		$booking_rooms_qty            	= $booking_data['booking_rooms_qty'];
		$booking_checkin_at           	= $booking_data['booking_checkin_at'];

		/*------------------ Start Date and End Date----------- */
		$startDate                      = $booking_checkin_at;
		$endDate 		  				= $booking_checkin_at;
		/*-------------------room type String convert into array-----------------*/
		$room_types 					= explode(",", $booking_room_type);
		$rooms_qty 					    = explode(",", $booking_rooms_qty);
		$serach_be_flag					= 0;
		$current_room_inventory 		= 0;
		$room_code_pre                  = 0;
		$room_code_cur                  = 0;
		if($booking_data['booking_source'] == 'ota'){
			$this->handleBookOne($bucket_ota_booking_tabel_id, $bucket_hotel_id);
			$this->handleIds($bucket_data,$booking_data);
			$is_gems = PmsAccount::where('name','GEMS')->whereRaw('FIND_IN_SET('.$bucket_hotel_id.',hotels)')->first();
			if($is_gems){
				$this->cmBookingDataInsertion->cmBookingDataInsertion($booking_hotel_id,$bucket_ota_booking_tabel_id);
			}
		}
		$booking_ip='1.1.1.1';
		foreach($room_types as $key => $room_type){
		$inventoryModel                 = new Inventory();
		if($booking_data['booking_source'] == 'ota'){
			$room_type 					= $cmOtaRoomTypeSynchronizeModel->getSingleHotelRoomIdFromRoomSynch($room_type,$bucket_hotel_id);
		}
		else{
			$room_type = $room_types[$key];
		}

		$logModel =new BookingLog();

		if(!empty($room_type)){
		$log_data                 		= [
									"action_id"          => 4,
									"hotel_id"           => $bucket_hotel_id,
									"ota_id"      		 => $bucket_ota_id,
									"booking_ref_id"     => $bucket_ota_booking_tabel_id,
									"user_id"            => 0,
									"request_msg"        => '',
									"response_msg"       => '',
									"request_url"        => '',
									"status"         	 => 2,
									"ip"         		 => $booking_ip,
									"comment"			 => "Processing for update "
									];
		$inventoryData=array();
		$inventoryData=$this->cmOtaBookingInvStatusService->getInventoryDetailsByRoomType($bucket_ota_booking_tabel_id,$room_type,$bucket_hotel_id,$bucket_ota_id);//	0 => No of days prior to the booking

		$serach_be_flag					= 1;

		$log_request_msg = $log_request_msg.json_encode($inventoryData);
		$log_response_msg="";
		$logInsertData=array();
		$current_log_id=$logModel->fill($log_data)->save();
		$data=array();
		$flagchecker=array();

		if($inventoryData)
		{
		if( $bucket_booking_status == 'Commit' || $bucket_booking_status == 'Modify'){
			for($i=0; $i<count($inventoryData); $i++)
			{	if($inventoryData[$i]->block_status==0)
				{
					if(is_object($inventoryData[$i]->date)){
						foreach($inventoryData[$i]->date as $info){
							$startDate      	= date('Y-m-d', strtotime($info));
							$endDate      	= date('Y-m-d', strtotime($info));
						}
					}
					else{
						$startDate      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
						$endDate      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
					}
				$room_quanty = $inventoryData[$i]->update_to;
				$user_id=0;//0 means inventory auto push By  Ota
				$data['date_from']=$startDate;
				$data['date_to']=$endDate;
				$data['user_id']=$user_id;
				$data['no_of_rooms']=$room_quanty;
				$data['hotel_id']=$bucket_hotel_id;
				$data['room_type_id']=$room_type;
				$data['client_ip'] = $this->ipService->getIPAddress();
				$data['multiple_days']      = '{"Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1","Sun":"1"}';
				}
				$log_response_msg=$log_response_msg.json_encode($data);
			}
		} // Commit Closed here.
			if( $bucket_booking_status == 'Cancel'){
				for($i=0; $i<count($inventoryData); $i++)
				{	if($inventoryData[$i]->block_status==0)
					{
						if(is_object($inventoryData[$i]->date)){
							foreach($inventoryData[$i]->date as $info){
								$startDate      	= date('Y-m-d', strtotime($info));
								$endDate      	= date('Y-m-d', strtotime($info));
							}
						}
						else{
							$startDate      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
							$endDate      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
						}
						$room_quantity = $inventoryData[$i]->update_to;
						$user_id=0;//0 means inventory auto push By  Ota
						$data['date_from']=$startDate;
						$data['date_to']=$startDate;
						$data['user_id']=$user_id;
						$data['no_of_rooms']=$room_quantity;
						$data['hotel_id']=$bucket_hotel_id;
						$data['room_type_id']=$room_type;
						$data['client_ip'] = $this->ipService->getIPAddress();
						$data['multiple_days']      = '{"Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1","Sun":"1"}';
					}
					$log_response_msg=$log_response_msg.json_encode($data);
				}
			} // Cancel Closed here.
		}
		}else{
		// set log for Booking Room Type is not synch with hotel Room Type.
			$log_data                 	= [
									"action_id"          => 4,
									"hotel_id"           => $bucket_hotel_id,
									"ota_id"      		 => $bucket_ota_id,
									"booking_ref_id"     => $bucket_ota_booking_tabel_id,
									"user_id"            => 0,
									"request_msg"        => '',
									"response_msg"       => '',
									"request_url"        => '',
									"status"         	 => 0,
									"ip"         		 => $booking_ip,
									"comment"			 => "Booking room type is not mapped."
									];
			$logModel->fill($log_data)->save();
			} // If else !empty($result) closed here

		} // foreach $room_types closed here
		//Later change this to mailhandler
		if($booking_data['booking_source'] == 'ota'){
			$this->mailHandler1($bucket_ota_booking_tabel_id,$bucket_booking_status);
		}
		DB::table('booking_logs')->where('id', $logModel->id)
		->update(['status' =>1,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg]);

		return true;
}

/*------------------- Agoda Update Function Start------------------------*/
public function agodaUpdate($bucket_data,$booking_data)
{
		$date 							= new \DateTime();
		$dateTimestamp 					= $date->getTimestamp();
		$logModel                       = new BookingLog();
		$cmOtaDetailsModel  			= new CmOtaDetails();
		$cmOtaRoomTypeSynchronizeModel  = new CmOtaRoomTypeSynchronize();
		$cmOtaRatePlanSynchronizeModel 	= new CmOtaRatePlanSynchronize();

		$log_request_msg="";
		$log_response_msg="";
		/*------------------ Get Bucket Data--------------------*/
		$bucket_id                      = $bucket_data['bucket_id'];
		$bucket_hotel_id                = $bucket_data['bucket_hotel_id'];
		$bucket_ota_id                  = $bucket_data['bucket_ota_id'];
		$bucket_ota_name                = $bucket_data['bucket_ota_name'];
		$bucket_ota_booking_tabel_id    = $bucket_data['bucket_ota_booking_tabel_id'];
		$bucket_ota_hotel_code          = $bucket_data['bucket_ota_hotel_code'];
		$bucket_booking_status          = $bucket_data['bucket_booking_status'];


		/*------------------ Get Booking Data--------------------*/
		$booking_ota_id                 = $booking_data['booking_ota_id'];
		$booking_hotel_id             	= $booking_data['booking_hotel_id'];
		$booking_room_type            	= $booking_data['booking_room_type'];
		$booking_rooms_qty            	= $booking_data['booking_rooms_qty'];
		$booking_checkin_at           	= $booking_data['booking_checkin_at'];

		/*------------------ Get Specific Ota Details-----------*/

		$ota_details_data             	= $cmOtaDetailsModel
											->where('hotel_id', '=' , $bucket_hotel_id)
											->where('ota_id', '=', $bucket_ota_id )
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


		$room_types 					= explode(",", $booking_room_type);
		$booking_ip						=	'1.1.1.1';

		foreach($room_types as $key => $room_type){

			if($booking_data['booking_source'] == 'ota'){
				$room_type =  $cmOtaRoomTypeSynchronizeModel->getSingleHotelRoomIdFromRoomSynch($room_type,$bucket_hotel_id);
				}
				else{
					$room_type = $room_types[$key];
				}
		if(!empty($room_type)){
		$log_data               	= [
									"action_id"          => 4,
									"hotel_id"           => $bucket_hotel_id,
									"ota_id"      		 => $bucket_ota_id,
									"booking_ref_id"     => $bucket_ota_booking_tabel_id,
									"user_id"            => 0,
									"request_msg"        => '',
									"response_msg"       => '',
									"request_url"        => '',
									"status"         	 => 2,
									"ip"         		 => $booking_ip,
									"comment"			 => "Processing for update "
									];
		$logModel->fill($log_data)->save();

		$room_code=$cmOtaRoomTypeSynchronizeModel->getOtaRoomType($room_type,$bucket_ota_id);
		$url="";
		$inventoryData=$this->cmOtaBookingInvStatusService->getInventoryDetailsByRoomType($bucket_ota_booking_tabel_id,$room_type,$bucket_hotel_id,$bucket_ota_id);//	0 => No of days prior to the booking
		if($inventoryData)
		{
		if( $bucket_booking_status == 'Commit'){
			for($i=0; $i<count($inventoryData); $i++)
			{
				$otainventory                   = new OtaInventory();
				if($inventoryData[$i]->block_status==0)
				{
					if(is_object($inventoryData[$i]->date)){
						foreach($inventoryData[$i]->date as $info){
							$startDate      	= date('Y-m-d', strtotime($info));
							$endDate      	= date('Y-m-d', strtotime($info));
						}
					}
					else{
						$startDate      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
						$endDate      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
					}
					$room_quanty = $inventoryData[$i]->update_to;

					if($room_quanty >= 0)
					{
						$xml ='<?xml version="1.0" encoding="UTF-8"?>
						<request timestamp="'.$dateTimestamp.'" type="1">
						<criteria property_id="'.$ota_hotel_code.'">
						<inventory>
						<update room_id="'.$room_code.'">
						<date_range from="'.$startDate.'" to="'.$endDate.'">
						</date_range>
						<allotment>'.$room_quanty.'</allotment>
						</update>
						</inventory>
						</criteria>
						</request>';
						$log_request_msg = $log_request_msg.$xml;


						$url 	= $commonUrl.'api?apiKey='.$apiKey;
						$ch 	= curl_init();
						curl_setopt( $ch, CURLOPT_URL, $url );
						curl_setopt( $ch, CURLOPT_POST, true );
						curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
						curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
						curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml);
						$result = curl_exec($ch);
						curl_close($ch);
						$log_response_msg = $log_response_msg.$result;
						if($result != strip_tags($result)){
							$array_data = json_decode(json_encode(simplexml_load_string($result)), true);
						}
						else{
							DB::table('booking_logs')->where('id', $logModel->id)
							->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);
						}
					} // empty $result.
				}
			} // for $inventoryData closed here.
				if(!isset($array_data['errors'])){
					DB::table('booking_logs')->where('id', $logModel->id)
					->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);
				}else{
					DB::table('booking_logs')->where('id', $logModel->id)
					->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);
				}
		} // If $bucket_booking_status == 'Commit' close here.

		if($bucket_booking_status == 'Cancel')
		{
			for($i=0; $i<count($inventoryData); $i++)
			{
				$otainventory                   = new OtaInventory();
				if($inventoryData[$i]->block_status==0)
				{
					if(is_object($inventoryData[$i]->date)){
						foreach($inventoryData[$i]->date as $info){
							$startDate      	= date('Y-m-d', strtotime($info));
							$endDate      	= date('Y-m-d', strtotime($info));
						}
					}
					else{
						$startDate      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
						$endDate      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
					}
					$room_quanty = $inventoryData[$i]->update_to;

					if($room_quanty >= 0){
					$xml ='<?xml version="1.0" encoding="UTF-8"?>
					<request timestamp="'.$dateTimestamp.'" type="1">
					<criteria property_id="'.$ota_hotel_code.'">
					<inventory>
					<update room_id="'.$room_code.'">
					<date_range from="'.$startDate.'" to="'.$endDate.'">

					</date_range>
					<allotment>'.$room_quanty.'</allotment>
					</update>
					</inventory>
					</criteria>
					</request>';

					$log_request_msg = $log_request_msg.$xml;

					$url 	= $commonUrl.'api?apiKey='.$apiKey;
					$ch 	= curl_init();
					curl_setopt( $ch, CURLOPT_URL, $url );
					curl_setopt( $ch, CURLOPT_POST, true );
					curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
					curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
					curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml);
					$result = curl_exec($ch);
					curl_close($ch);

					$log_response_msg = $log_response_msg.$result;

					if($result != strip_tags($result)){
						$array_data = json_decode(json_encode(simplexml_load_string($result)), true);
					}
					else{
						DB::table('booking_logs')->where('id', $logModel->id)
						->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);
					}
					} // $room_quanty >= 0
				}
			}// for $inventoryData closed here.
			if(!isset($array_data['errors'])){
				DB::table('booking_logs')->where('id', $logModel->id)
				->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);
			}else{
				DB::table('booking_logs')->where('id', $logModel->id)
				->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);
			}
		} // If $bucket_booking_status == 'Cancel' close here.
		}//  if $inventryData closed here.
		}else{
		// set log for Booking Room Type is not synch with hotel Room Type.
		$log_data                 	= [
									"action_id"          => 4,
									"hotel_id"           => $bucket_hotel_id,
									"ota_id"      		 => $bucket_ota_id,
									"booking_ref_id"     => $bucket_ota_booking_tabel_id,
									"user_id"            => 0,
									"request_msg"        => '',
									"response_msg"       => '',
									"request_url"        => '',
									"status"         	 => 0,
									"ip"         		 => $booking_ip,
									"comment"			 => "Booking room type is not mapped."
									];
		$logModel->fill($log_data)->save();

		} // If else !empty($result) closed here
	} // foreach $room_types closed here.
	return true;
}
/*------------------- Agoda Update Function Close------------------------*/
/*------------------- Goibibo Update Function Start------------------------*/
public function goibiboUpdate($bucket_data,$booking_data)
{
		$log_request_msg="";
		$log_response_msg="";
		$cmOtaDetailsModel  			= new CmOtaDetails();
		$cmOtaRoomTypeSynchronizeModel  = new CmOtaRoomTypeSynchronize();
		$cmOtaRatePlanSynchronizeModel 	= new CmOtaRatePlanSynchronize();
		$logModel                       = new BookingLog();


		/*------------------ Get Bucket Data--------------------*/
		$bucket_id                      = $bucket_data['bucket_id'];
		$bucket_hotel_id                = $bucket_data['bucket_hotel_id'];
		$bucket_ota_id                  = $bucket_data['bucket_ota_id'];
		$bucket_ota_name                = $bucket_data['bucket_ota_name'];
		$bucket_ota_booking_tabel_id    = $bucket_data['bucket_ota_booking_tabel_id'];
		$bucket_ota_hotel_code          = $bucket_data['bucket_ota_hotel_code'];
		$bucket_booking_status          = $bucket_data['bucket_booking_status'];


		/*------------------ Get Booking Data--------------------*/
		$booking_ota_id                 = $booking_data['booking_ota_id'];
		$booking_hotel_id             	= $booking_data['booking_hotel_id'];
		$booking_room_type            	= $booking_data['booking_room_type'];
		$booking_rooms_qty            	= $booking_data['booking_rooms_qty'];
		$booking_checkin_at           	= $booking_data['booking_checkin_at'];

		/*------------------ Get Specific Ota Details-----------*/

		$ota_details_data             	=  $cmOtaDetailsModel
										->where('hotel_id', '=' , $bucket_hotel_id)
										->where('ota_id', '=', $bucket_ota_id )
										->first();
		$ota_id 					  	= $ota_details_data->ota_id;
		$ota_hotel_code 			  	= $ota_details_data->ota_hotel_code;
		$auth_parameter 			  	= json_decode($ota_details_data->auth_parameter);
		$bearer_token 					= trim($auth_parameter->bearer_token);
		$channel_token 					= trim($auth_parameter->channel_token);
		$commonUrl      				= $ota_details_data->url;

		/*------------------ set header ------------------ */
		$headers = array(
			"Content-Type: application/xml",
			"channel-token:".$channel_token,
			"bearer-token:".$bearer_token
		  );

		$room_types 					= explode(",", $booking_room_type);
		$rooms_qty 					    = explode(",", $booking_rooms_qty);
		$booking_ip						=	'1.1.1.1';
		foreach($room_types as $key => $room_type){

			if($booking_data['booking_source'] == 'ota'){
				$room_type =  $cmOtaRoomTypeSynchronizeModel->getSingleHotelRoomIdFromRoomSynch($room_type,$bucket_hotel_id);
				}
				else{
					$room_type = $room_types[$key];
				}
		if(!empty($room_type)){
		$log_data               	= [
									"action_id"          => 4,
									"hotel_id"           => $bucket_hotel_id,
									"ota_id"      		 => $bucket_ota_id,
									"booking_ref_id"     => $bucket_ota_booking_tabel_id,
									"user_id"            => 0,
									"request_msg"        => '',
									"response_msg"       => '',
									"request_url"        => '',
									"status"         	 => 2,
									"ip"         		 => $booking_ip,
									"comment"			 => "Processing for update "
									];
		$logModel->fill($log_data)->save();
		$room_code=$cmOtaRoomTypeSynchronizeModel->getOtaRoomType($room_type,$bucket_ota_id);


		/*******====== Get Inventory===== ******/

		$inventoryData=$this->cmOtaBookingInvStatusService->getInventoryDetailsByRoomType($bucket_ota_booking_tabel_id,$room_type,$bucket_hotel_id,$bucket_ota_id);//	0 => No of days prior to the booking
		$url = " ";
		$Flagchecker=0;
		if($inventoryData)
		{
		if($bucket_booking_status == 'Commit'){

			for($i=0; $i<count($inventoryData); $i++)
			{
				$otainventory                   = new OtaInventory();
				if($inventoryData[$i]->block_status==0)
				{
					if(is_object($inventoryData[$i]->date)){
						foreach($inventoryData[$i]->date as $info){
							$startDate      	= date('Y-m-d', strtotime($info));
							$endDate      	= date('Y-m-d', strtotime($info));
						}
					}
					else{
						$startDate      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
						$endDate      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
					}
					$room_quanty = $inventoryData[$i]->update_to;

					if($room_quanty >= 0){

					$xml ='<?xml version="1.0" encoding="UTF-8" ?>
					<AvailRateUpdateRQ hotelCode="'.$ota_hotel_code.'" timeStamp="">
					<AvailRateUpdate locatorID="1">
						<DateRange from="'.$startDate.'" to="'.$endDate.'"/>
						<Availability code="'.$room_code.'" count="'.$room_quanty.'" closed="false" />
					</AvailRateUpdate>
					</AvailRateUpdateRQ>';

					$log_request_msg .= $xml;

					$url = "https://partners-connect.goibibo.com/api/chm/v3/ari";

					$ch = curl_init();
					curl_setopt( $ch, CURLOPT_URL, $url );
					curl_setopt( $ch, CURLOPT_POST, true );
					curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
					curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
					curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml);
					$ota_rlt = curl_exec($ch);
					curl_close($ch);
					$log_response_msg = $log_response_msg.$ota_rlt;

				    $ary_data = json_decode(json_encode(simplexml_load_string($ota_rlt)), true);

					if(!isset($ary_data['Error'])){
						$Flagchecker = 1;
					}else{
						$Flagchecker = 0;
					}
					} // $room_quanty >= 0
				}
			} // foreach $ota_inventry_details closed here.

			if($Flagchecker == 1)
				{
					DB::table('booking_logs')->where('id', $logModel->id)
					->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);

				}
				else
				{
					DB::table('booking_logs')->where('id', $logModel->id)
					->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);

				}
		} // If $bucket_booking_status == 'Commit' close here.

		if($bucket_booking_status == 'Cancel'){
			for($i=0; $i<count($inventoryData); $i++)
			{
				$otainventory                   = new OtaInventory();
				if($inventoryData[$i]->block_status==0)
				{
					if(is_object($inventoryData[$i]->date)){
						foreach($inventoryData[$i]->date as $info){
							$startDate      	= date('Y-m-d', strtotime($info));
							$endDate      	= date('Y-m-d', strtotime($info));
						}
					}
					else{
						$startDate      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
						$endDate      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
					}
					$room_quanty = $inventoryData[$i]->update_to;

					if($room_quanty >= 0)
					{

						$xml ='<?xml version="1.0" encoding="UTF-8" ?>
						<Website Name="ingoibibo" HotelCode="'.$ota_hotel_code.'">
						<Room>
						<RoomTypeCode>'.$room_code.'</RoomTypeCode>
						<StartDate Format="yyyy-mm-dd">'.$startDate.'</StartDate>
						<EndDate Format="yyyy-mm-dd">'.$endDate.'</EndDate>
						<DaysOfWeek Mon="True" Tue="True" Wed="True" Thu="True" Fri="True" Sat="True" Sun="True"></DaysOfWeek>
						<MinLOS>1</MinLOS>
						<Available>'.$room_quanty.'</Available>
						</Room>
						</Website>';
						$log_request_msg = $log_request_msg.$xml;

						$url = $commonUrl.'updateroominventory/?bearer_token='.$bearer_token.'&channel_token='.$channel_token;

						$ch  = curl_init();
						curl_setopt( $ch, CURLOPT_URL, $url );
						curl_setopt( $ch, CURLOPT_POST, true );
						curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
						curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
						curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml);
						$ota_rlt = curl_exec($ch);
						curl_close($ch);
						$log_response_msg = $log_response_msg.$ota_rlt;
						$ary_data = json_decode(json_encode(simplexml_load_string($ota_rlt)), true);

						if(!isset($ary_data['Error'])){
							$Flagchecker = 1;
						}else{
							$Flagchecker = 0;
						}

					} // $room_quanty >= 0
				}
			} // foreach $ota_inventry_details closed here.
			if($Flagchecker == 1){
				DB::table('booking_logs')->where('id', $logModel->id)
				->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);
			}else{
				DB::table('booking_logs')->where('id', $logModel->id)
				->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);
			}
		} // If $bucket_booking_status == 'Cancel' close here.s
		}//If $inventoryData close here
		}
		else{
		// set log for Booking Room Type is not synch with hotel Room Type.
		$log_data                 	= [
									"action_id"          => 4,
									"hotel_id"           => $bucket_hotel_id,
									"ota_id"      		 => $bucket_ota_id,
									"booking_ref_id"     => $bucket_ota_booking_tabel_id,
									"inventory_ref_id"   => '',
									"rate_ref_id"        => '',
									"user_id"            => 0,
									"request_msg"        => '',
									"response_msg"       => '',
									"request_url"        => '',
									"status"         	 => 0,
									"ip"         		 => $booking_ip,
									"comment"			 => "Booking room type is not mapped."
									];
		$logModel->fill($log_data)->save();
		} // If else !empty($result) closed here.
		}// foreach $room_types	 closed here.*/
		return true;
	}
/*------------------- Goibibo Update Function End------------------------*/

/*------------------- Expedia Update Function Start------------------------*/
public function expediaUpdate($bucket_data,$booking_data)
{
		$cmOtaDetailsModel  			= new CmOtaDetails();
		$cmOtaRoomTypeSynchronizeModel  = new CmOtaRoomTypeSynchronize();
		$cmOtaRatePlanSynchronizeModel 	= new CmOtaRatePlanSynchronize();
		$logModel                       = new BookingLog();

		$log_request_msg="";
		$log_response_msg="";
		/*------------------ Get Bucket Data--------------------*/
		$bucket_id                      = $bucket_data['bucket_id'];
		$bucket_hotel_id                = $bucket_data['bucket_hotel_id'];
		$bucket_ota_id                  = $bucket_data['bucket_ota_id'];
		$bucket_ota_name                = $bucket_data['bucket_ota_name'];
		$bucket_ota_booking_tabel_id    = $bucket_data['bucket_ota_booking_tabel_id'];
		$bucket_ota_hotel_code          = $bucket_data['bucket_ota_hotel_code'];
		$bucket_booking_status          = $bucket_data['bucket_booking_status'];


		/*------------------ Get Booking Data--------------------*/
		$booking_ota_id                 = $booking_data['booking_ota_id'];
		$booking_hotel_id             	= $booking_data['booking_hotel_id'];
		$booking_room_type            	= $booking_data['booking_room_type'];
		$booking_rooms_qty            	= $booking_data['booking_rooms_qty'];
		$booking_checkin_at           	= $booking_data['booking_checkin_at'];
		/*------------------ Get Specific Ota Details-----------*/

		$ota_details_data             	=  $cmOtaDetailsModel
										->where('hotel_id', '=' ,$bucket_hotel_id)
										->where('ota_id', '=' ,$bucket_ota_id )
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

		$room_types 					= explode(",", $booking_room_type);
		$booking_ip						=	'1.1.1.1';

		foreach($room_types as $key => $room_type){

			if($booking_data['booking_source'] == 'ota'){
				$room_type =  $cmOtaRoomTypeSynchronizeModel->getSingleHotelRoomIdFromRoomSynch($room_type,$bucket_hotel_id);
				}
				else{
					$room_type = $room_types[$key];
				}
		if(!empty($room_type)){
		$log_data               	= [
									"action_id"          => 4,
									"hotel_id"           => $bucket_hotel_id,
									"ota_id"      		 => $bucket_ota_id,
									"booking_ref_id"     => $bucket_ota_booking_tabel_id,
									"user_id"            => 0,
									"request_msg"        => '',
									"response_msg"       => '',
									"request_url"        => '',
									"status"         	 => 2,
									"ip"         		 => $booking_ip,
									"comment"			 => "Processing for update "
									];
		$logModel->fill($log_data)->save();
		$room_code=$cmOtaRoomTypeSynchronizeModel->getOtaRoomType($room_type,$bucket_ota_id);

			/*******====== Get Inventory===== ******/

		$inventoryData=$this->cmOtaBookingInvStatusService->getInventoryDetailsByRoomType($bucket_ota_booking_tabel_id,$room_type,$bucket_hotel_id,$bucket_ota_id);//	0 => No of days prior to the booking
		$Flagchecker = 0;
		$url="";
		if($inventoryData)
		{
		if($bucket_booking_status == 'Commit'){

			for($i=0; $i<count($inventoryData); $i++)
			{
				$otainventory                   = new OtaInventory();
				if($inventoryData[$i]->block_status==0)
				{
					if(is_object($inventoryData[$i]->date)){
						foreach($inventoryData[$i]->date as $info){
							$startDate      	= date('Y-m-d', strtotime($info));
							$endDate      	= date('Y-m-d', strtotime($info));
						}
					}
					else{
						$startDate      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
						$endDate      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
					}
					$room_quanty = $inventoryData[$i]->update_to;

					if($room_quanty >= 0){
					$xml          = '<?xml version="1.0" encoding="UTF-8"?>
					<!--Sample AR request message: updating total allocation of a room type-->
					<AvailRateUpdateRQ xmlns="http://www.expediaconnect.com/EQC/AR/2011/06">
					<Authentication username="'.$username.'" password="'.$password.'"/>
					<Hotel id="'.$ota_hotel_code.'"/>
					<AvailRateUpdate>
					<DateRange from="'.$startDate.'" to="'.$endDate.'"/>
					<RoomType id="'.$room_code.'">
					<Inventory totalInventoryAvailable="'.$room_quanty.'"/>
					</RoomType>
					</AvailRateUpdate>
					</AvailRateUpdateRQ>';
					$log_request_msg = $log_request_msg.$xml;
					$url  = $commonUrl.'eqc/ar';
					$ch = curl_init();
					curl_setopt( $ch, CURLOPT_URL, $url );
					curl_setopt( $ch, CURLOPT_POST, true );
					curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
					curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false);
					curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
					curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml);
					$result = curl_exec($ch);
					curl_close($ch);
					$log_response_msg = $log_response_msg.$result;
					$array_data = json_decode(json_encode(simplexml_load_string($result)), true);
					if(!isset($array_data['Error'])){
						$Flagchecker = 1;
					}else{
						$Flagchecker = 0;
					}
				} // $room_quanty >= 0
			}
		} // foreach $ota_inventry_details closed here.
		if($Flagchecker == 1){
			DB::table('booking_logs')->where('id', $logModel->id)
			->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);

		}else{
			DB::table('booking_logs')->where('id', $logModel->id)
			->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);

		}
	} // If $bucket_booking_status == 'Commit' close here.
	if($bucket_booking_status == 'Cancel'){
		for($i=0; $i<count($inventoryData); $i++)
		{
			$otainventory                   = new OtaInventory();
			if($inventoryData[$i]->block_status==0)
			{
				if(is_object($inventoryData[$i]->date)){
					foreach($inventoryData[$i]->date as $info){
						$startDate      	= date('Y-m-d', strtotime($info));
						$endDate      	= date('Y-m-d', strtotime($info));
					}
				}
				else{
					$startDate      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
					$endDate      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
				}
				$room_quanty = $inventoryData[$i]->update_to;

				if($room_quanty >= 0){
					$xml          = '<?xml version="1.0" encoding="UTF-8"?>
					<AvailRateUpdateRQ xmlns="http://www.expediaconnect.com/EQC/AR/2011/06">
					<Authentication username="'.$username.'" password="'.$password.'"/>
					<Hotel id="'.$ota_hotel_code.'"/>
					<AvailRateUpdate>
					<DateRange from="'.$startDate.'" to="'.$endDate.'"/>
					<RoomType id="'.$room_code.'">
					<Inventory totalInventoryAvailable="'.$room_quanty.'"/>
					</RoomType>
					</AvailRateUpdate>
					</AvailRateUpdateRQ>';
					$log_request_msg = $log_request_msg.$xml;
					$xml = trim($xml);
					$url  = $commonUrl.'eqc/ar';
					$ch = curl_init();
					curl_setopt( $ch, CURLOPT_URL, $url );
					curl_setopt( $ch, CURLOPT_POST, true );
					curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
					curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false);
					curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
					curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml);
					$result = curl_exec($ch);
					curl_close($ch);
					$log_response_msg = $log_response_msg.$result;
					$array_data = json_decode(json_encode(simplexml_load_string($result)), true);

					if(!isset($array_data['Error'])){
						$Flagchecker = 1;
					//return true;
					}else{
						$Flagchecker = 0;

					//return false;
					}

				} // $room_quanty >= 0
			}
		} // foreach $inventoryData closed here.

		if($Flagchecker == 1){
			DB::table('booking_logs')->where('id', $logModel->id)
			->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);

		}else{
			DB::table('booking_logs')->where('id', $logModel->id)
			->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);

		}

		} // If $bucket_booking_status == 'Cancel' close here.
		}
		}else{
		// set log for Booking Room Type is not synch with hotel Room Type.
		$log_data                 	= [
									"action_id"          => 4,
									"hotel_id"           => $bucket_hotel_id,
									"ota_id"      		 => $bucket_ota_id,
									"booking_ref_id"     => $bucket_ota_booking_tabel_id,
									"user_id"            => 0,
									"request_msg"        => '',
									"response_msg"       => '',
									"request_url"        => '',
									"status"         	 => 0,
									"ip"         		 => $booking_ip,
									"comment"			 => "Booking room type is not mapped."
									];
		$logModel->fill($log_data)->save();
		} // If else !empty($result) closed here.
		} // foreach $room_types closed here.
		return true;
  }
/*------------------- Expedia Update Function End------------------------*/


/*------------------- Booking.com Update Function Start------------------------*/
public function bookingdotcomUpdate($bucket_data,$booking_data)
{
		$log_request_msg="";
		$log_response_msg="";
		$cmOtaDetailsModel  			= new CmOtaDetails();
		$cmOtaRoomTypeSynchronizeModel  = new CmOtaRoomTypeSynchronize();
		$cmOtaRatePlanSynchronizeModel 	= new CmOtaRatePlanSynchronize();
		$logModel                       = new BookingLog();


		/*------------------ Get Bucket Data--------------------*/
		$bucket_id                      = $bucket_data['bucket_id'];
		$bucket_hotel_id                = $bucket_data['bucket_hotel_id'];
		$bucket_ota_id                  = $bucket_data['bucket_ota_id'];
		$bucket_ota_name                = $bucket_data['bucket_ota_name'];
		$bucket_ota_booking_tabel_id    = $bucket_data['bucket_ota_booking_tabel_id'];
		$bucket_ota_hotel_code          = $bucket_data['bucket_ota_hotel_code'];
		$bucket_booking_status          = $bucket_data['bucket_booking_status'];


		/*------------------ Get Booking Data--------------------*/
		$booking_ota_id                 = $booking_data['booking_ota_id'];
		$booking_hotel_id             	= $booking_data['booking_hotel_id'];
		$booking_room_type            	= $booking_data['booking_room_type'];
		$booking_rooms_qty            	= $booking_data['booking_rooms_qty'];
		$booking_checkin_at           	= $booking_data['booking_checkin_at'];
		/*------------------ Get Specific Ota Details-----------*/

		$ota_details_data             	=  $cmOtaDetailsModel
											->where('hotel_id', '=' ,$bucket_hotel_id)
											->where('ota_id', '=' ,$bucket_ota_id )
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

		$room_types 					= explode(",", $booking_room_type);
		$booking_ip						=	'1.1.1.1';

		foreach($room_types as $key => $room_type){

			if($booking_data['booking_source'] == 'ota'){
				$room_type =  $cmOtaRoomTypeSynchronizeModel->getSingleHotelRoomIdFromRoomSynch($room_type,$bucket_hotel_id);
				}
				else{
					$room_type = $room_types[$key];
				}
		if(!empty($room_type)){
		$log_data               	= [
									"action_id"          => 4,
									"hotel_id"           => $bucket_hotel_id,
									"ota_id"      		 => $bucket_ota_id,
									"booking_ref_id"     => $bucket_ota_booking_tabel_id,
									"user_id"            => 0,
									"request_msg"        => '',
									"response_msg"       => '',
									"request_url"        => '',
									"status"         	 => 2,
									"ip"         		 => $booking_ip,
									"comment"			 => "Processing for update "
									];
		$logModel->fill($log_data)->save();
		$room_code=$cmOtaRoomTypeSynchronizeModel->getOtaRoomType($room_type,$bucket_ota_id);

		/*******====== Get Inventory ===== ******/

		$inventoryData=$this->cmOtaBookingInvStatusService->getInventoryDetailsByRoomType($bucket_ota_booking_tabel_id,$room_type,$bucket_hotel_id,$bucket_ota_id);//	0 => No of days prior to the booking
		$ratePlanTypeSynchronizeData  = CmOtaRatePlanSynchronize::
		select('*')
		->where('ota_room_type_id', '=' ,$room_code)
		->first();
		$url= '';
		if(!isset($ratePlanTypeSynchronizeData->ota_rate_plan_id)){
			$log_data                 	= [
				"action_id"          => 4,
				"hotel_id"           => $bucket_hotel_id,
				"ota_id"      		 => $bucket_ota_id,
				"booking_ref_id"     => $bucket_ota_booking_tabel_id,
				"user_id"            => 0,
				"request_msg"        => '',
				"response_msg"       => '',
				"request_url"        => '',
				"status"         	 => 0,
				"ip"         		 => $booking_ip,
				"comment"			 => "Booking rate plan is not mapped."
				];
			$logModel->fill($log_data)->save();
			$rateplan_code = 0;
		}
		else{
			$rateplan_code = $ratePlanTypeSynchronizeData->ota_rate_plan_id;
		}

		if($inventoryData)
		{
		if($bucket_booking_status == 'Commit'){

			for($i=0; $i<count($inventoryData); $i++)
			{
				$otainventory                   = new OtaInventory();
				if($inventoryData[$i]->block_status==0)
				{
					if(is_object($inventoryData[$i]->date)){
						foreach($inventoryData[$i]->date as $info){
							$startDate = date('Y-m-d', strtotime($info));
							$endDate_inv=date('Y-m-d',strtotime($info));//Dont Change this
							$endDate=date('Y-m-d',strtotime('+1 day', strtotime($info)));
						}
					}
					else{
						$startDate      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
						$endDate_inv     = date('Y-m-d', strtotime($inventoryData[$i]->date));
						$endDate		= date('Y-m-d',strtotime('+1 day', strtotime($inventoryData[$i]->date)));
					}
					$room_quanty = $inventoryData[$i]->update_to;

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
					<minimumstay>1</minimumstay>
					<closed>0</closed>
					</date>
					</room>
					</request>';
					$log_request_msg  = $log_request_msg.$xml;
					$url         	  = $commonUrl.'availability';
					$ch = curl_init();
					curl_setopt( $ch, CURLOPT_URL, $url );
					curl_setopt( $ch, CURLOPT_POST, true );
					curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
					curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
					curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml);
					$result = curl_exec($ch);
					curl_close($ch);
					$log_response_msg .= $result;

					// $log_response_msg ='';
					 $array_data = json_decode(json_encode(simplexml_load_string($result)), true);
					} // $room_quanty >= 0
				}
			} // foreach $ota_inventry_details closed here.
			if(!isset($array_data['error'])){
				DB::table('booking_logs')->where('id', $logModel->id)
				->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);

			}else{
				DB::table('booking_logs')->where('id', $logModel->id)
				->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);

			}
			} // If $bucket_booking_status == 'Commit' close here.
			if($bucket_booking_status == 'Cancel'){
				for($i=0; $i<count($inventoryData); $i++)
				{
					$otainventory                   = new OtaInventory();
					if($inventoryData[$i]->block_status==0)
					{
						if(is_object($inventoryData[$i]->date)){
							foreach($inventoryData[$i]->date as $info){
								$startDate = date('Y-m-d', strtotime($info));
								$endDate_inv=date('Y-m-d',strtotime($info));//Dont Change this
								$endDate=date('Y-m-d',strtotime('+1 day', strtotime($info)));
							}
						}
						else{
							$startDate      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
							$endDate_inv     = date('Y-m-d', strtotime($inventoryData[$i]->date));
							$endDate		= date('Y-m-d',strtotime('+1 day', strtotime($inventoryData[$i]->date)));
						}
						$room_quanty = $inventoryData[$i]->update_to;

						if($room_quanty >= 0)
						{
							$xml          = '<request>
							<username>'.$username.'</username>
							<password>'.$password.'</password>
							<hotel_id>'.$ota_hotel_code.'</hotel_id>
							<version>1.0</version>
							<room id="'.$room_code.'">
							<date from="'.$startDate.'" to="'.$endDate.'">
							<roomstosell>'.$room_quanty.'</roomstosell>
							<rate id="'.$rateplan_code.'"/>
							<minimumstay>1</minimumstay>
							<closed>0</closed>
							</date>
							</room>
							</request>';
							$log_request_msg  = $log_request_msg.$xml;
							$url         	  = $commonUrl.'availability';
							$ch = curl_init();
							curl_setopt( $ch, CURLOPT_URL, $url );
							curl_setopt( $ch, CURLOPT_POST, true );
							curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
							curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
							curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml);
							$result = curl_exec($ch);
							$log_response_msg =$log_response_msg.$result;
							// $log_response_msg ='';
							curl_close($ch);
							 $array_data = json_decode(json_encode(simplexml_load_string($result)), true);
						}
					}
				} // foreach $ota_inventry_details closed here.
				if(!isset($array_data['error'])){
					DB::table('booking_logs')->where('id', $logModel->id)
					->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);

				}else{
					DB::table('booking_logs')->where('id', $logModel->id)
					->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);

				}
			} // If $bucket_booking_status == 'Cancel' close here.
			}
			}else{
			// set log for Booking Room Type is not synch with hotel Room Type.
			$log_data                 	= [
										"action_id"          => 4,
										"hotel_id"           => $bucket_hotel_id,
										"ota_id"      		 => $bucket_ota_id,
										"booking_ref_id"     => $bucket_ota_booking_tabel_id,
										"user_id"            => 0,
										"request_msg"        => '',
										"response_msg"       => '',
										"request_url"        => '',
										"status"         	 => 0,
										"ip"         		 => $booking_ip,
										"comment"			 => "Booking room type is not mapped."
										];
			$logModel->fill($log_data)->save();
			} // If else !empty($result) closed here.
			} // foreach $room_types closed here.
			return true;
}

/*------------------- Booking.com Update Function End------------------------*/

/*------------------- Via.com Update Function Start------------------------*/
public function viadotcomUpdate($bucket_data,$booking_data)
{

		$log_request_msg="";
		$log_response_msg="";
		$url = '';
		$cmOtaDetailsModel  			= new CmOtaDetails();
		$cmOtaRoomTypeSynchronizeModel  = new CmOtaRoomTypeSynchronize();
		$cmOtaRatePlanSynchronizeModel 	= new CmOtaRatePlanSynchronize();
		$logModel                       = new BookingLog();


		/*------------------ Get Bucket Data--------------------*/
		$bucket_id                      = $bucket_data['bucket_id'];
		$bucket_hotel_id                = $bucket_data['bucket_hotel_id'];
		$bucket_ota_id                  = $bucket_data['bucket_ota_id'];
		$bucket_ota_name                = $bucket_data['bucket_ota_name'];
		$bucket_ota_booking_tabel_id    = $bucket_data['bucket_ota_booking_tabel_id'];
		$bucket_ota_hotel_code          = $bucket_data['bucket_ota_hotel_code'];
		$bucket_booking_status          = $bucket_data['bucket_booking_status'];


		/*------------------ Get Booking Data--------------------*/
		$booking_ota_id                 = $booking_data['booking_ota_id'];
		$booking_hotel_id             	= $booking_data['booking_hotel_id'];
		$booking_room_type            	= $booking_data['booking_room_type'];
		$booking_rooms_qty            	= $booking_data['booking_rooms_qty'];
		$booking_checkin_at           	= $booking_data['booking_checkin_at'];
		$booking_checkout_at			= $booking_data['booking_checkout_at'];
		/*------------------ Start Date and End Date----------- */
		$startDate                      = $booking_checkin_at;
		$endDate                        = $booking_checkout_at;

		/*------------------ Get Specific Ota Details-----------*/

		$ota_details_data             	=  $cmOtaDetailsModel
											->where('hotel_id', '=' ,$bucket_hotel_id)
											->where('ota_id', '=' ,$bucket_ota_id )
											->first();

		$ota_id 					  	= $ota_details_data->ota_id;
		$ota_hotel_code 			  	= $ota_details_data->ota_hotel_code;
		$auth_parameter 			  	= json_decode($ota_details_data->auth_parameter);
		$source 						= trim($auth_parameter->source);
		$auth 							= trim($auth_parameter->auth);
		$commonUrl      				= $ota_details_data->url;

		/*------------------ set header ------------------ */
		$headers = array (
		//Regulates versioning of the XML interface for the API
		'Content-Type: application/xml'
		);
		$Flagchecker=0;
		$room_types 					= explode(",", $booking_room_type);
		$booking_ip						=	'1.1.1.1';

		foreach($room_types as $key => $room_type){

			if($booking_data['booking_source'] == 'ota'){
				$room_type =  $cmOtaRoomTypeSynchronizeModel->getSingleHotelRoomIdFromRoomSynch($room_type,$bucket_hotel_id);
				}
				else{
					$room_type = $room_types[$key];
				}

		if(!empty($room_type)){
		$log_data               	= [
									"action_id"          => 4,
									"hotel_id"           => $bucket_hotel_id,
									"ota_id"      		 => $bucket_ota_id,
									"booking_ref_id"     => $bucket_ota_booking_tabel_id,
									"user_id"            => 0,
									"request_msg"        => '',
									"response_msg"       => '',
									"request_url"        => '',
									"status"         	 => 2,
									"ip"         		 => $booking_ip,
									"comment"			 => "Processing for update "
									];
		$logModel->fill($log_data)->save();
		$room_code=$cmOtaRoomTypeSynchronizeModel->getOtaRoomType($room_type,$bucket_ota_id);

		/*******====== Get Inventory ===== ******/

		$inventoryData=$this->cmOtaBookingInvStatusService->getInventoryDetailsByRoomType($bucket_ota_booking_tabel_id,$room_type,$bucket_hotel_id,$bucket_ota_id);//	0 => No of days prior to the booking

		if($inventoryData)
		{
		if($bucket_booking_status == 'Commit'){

			for($i=0; $i<count($inventoryData); $i++)
			{
				$otainventory                   = new OtaInventory();
				if($inventoryData[$i]->block_status==0)
				{
					if(is_object($inventoryData[$i]->date)){
						foreach($inventoryData[$i]->date as $info){
							$startDate      	= date('Y-m-d', strtotime($info));
							$endDate      	= date('Y-m-d', strtotime($info));
						}
					}
					else{
						$startDate      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
						$endDate      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
					}
					$room_quanty = $inventoryData[$i]->update_to;

					if($room_quanty >= 0)
					{
						$url= $commonUrl.'newWebserviceAPI?actionId=cm_updateroominventory&typeId=hotel&source='.$source.'&auth='.$auth.'&requestData={"hotelId":'.$ota_hotel_code.',"roomId":'.$room_code.',"startDate":"'.$startDate.'","endDate":"'.$endDate.'","available":'.$room_quanty.'}';
						$log_request_msg = $log_request_msg.$url;

						$ch  = curl_init();
						curl_setopt($ch, CURLOPT_URL, $url);
						curl_setopt($ch, CURLOPT_HTTPGET, true);
						curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
						if( curl_exec($ch) === false ){
						echo $result = curl_error($ch);
						}else{
						$result = curl_exec($ch);
						}
						curl_close($ch);
						$log_response_msg = $log_response_msg.$result;
						$array_data = (array) json_decode($result);
						if(isset($array_data['Success'])){
							$Flagchecker =1;
						}else{
							$Flagchecker =0;
						}
					} // $room_quanty >= 0
				}
			} // foreach $ota_inventry_details closed here.
		if($Flagchecker == 1){
			DB::table('booking_logs')->where('id', $logModel->id)
			->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$commonUrl]);

		}else{
			DB::table('booking_logs')->where('id', $logModel->id)
			->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$commonUrl]);

		}
		} // If $bucket_booking_status == 'Commit' close here.
		if($bucket_booking_status == 'Cancel'){
			for($i=0; $i<count($inventoryData); $i++)
			{
				$otainventory                   = new OtaInventory();
				if($inventoryData[$i]->block_status==0)
				{
					if(is_object($inventoryData[$i]->date)){
						foreach($inventoryData[$i]->date as $info){
							$startDate      	= date('Y-m-d', strtotime($info));
							$endDate      	= date('Y-m-d', strtotime($info));
						}
					}
					else{
						$startDate      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
						$endDate      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
					}
					$room_quanty = $inventoryData[$i]->update_to;

					if($room_quanty >= 0)
					{
						$url= $commonUrl.'newWebserviceAPI?actionId=cm_updateroominventory&typeId=hotel&source='.$source.'&auth='.$auth.'&requestData={"hotelId":'.$ota_hotel_code.',"roomId":'.$room_code.',"startDate":"'.$startDate.'","endDate":"'.$endDate.'","available":'.$room_quanty.'}';
						$log_request_msg = $log_request_msg.$url;

						$ch  = curl_init();
						curl_setopt($ch, CURLOPT_URL, $url);
						curl_setopt($ch, CURLOPT_HTTPGET, true);
						curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
						if( curl_exec($ch) === false ){
						echo $result = curl_error($ch);
						}else{
						$result = curl_exec($ch);
						}
						curl_close($ch);
						$log_response_msg = $result;
						$array_data = (array) json_decode($result);

						if(isset($array_data['Success'])){
							$Flagchecker = 1;
						}else{
							$Flagchecker = 0;
						}
					} // $room_quanty >= 0
				}
			} // foreach $ota_inventry_details closed here.
			if($Flagchecker == 1) {
				DB::table('booking_logs')->where('id', $logModel->id)
				->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);

			}else{
				DB::table('booking_logs')->where('id', $logModel->id)
				->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);

			}
		} // If $bucket_booking_status == 'Cancel' close here.
		}
		}else{
		// set log for Booking Room Type is not synch with hotel Room Type.
		$log_data                 	= [
									"action_id"          => 4,
									"hotel_id"           => $bucket_hotel_id,
									"ota_id"      		 => $bucket_ota_id,
									"booking_ref_id"     => $bucket_ota_booking_tabel_id,
									"user_id"            => 0,
									"request_msg"        => '',
									"response_msg"       => '',
									"request_url"        => '',
									"status"         	 => 0,
									"ip"         		 => $booking_ip,
									"comment"			 => "Booking room type is not mapped."
									];
		$logModel->fill($log_data)->save();
		} // If else !empty($result) closed here.
		} // foreach $room_types closed here.
		return true;
}
/*------------------- Via.com Update Function End------------------------*/


/*------------------- Travelguru Update Function Start------------------------*/
public function travelguruUpdate($bucket_data,$booking_data)
{
		$log_request_msg="";
		$log_response_msg="";
		$date 							= new \DateTime();
		$dateTimestamp 					= $date->getTimestamp();

		$cmOtaDetailsModel  			= new CmOtaDetails();
		$cmOtaRoomTypeSynchronizeModel  = new CmOtaRoomTypeSynchronize();
		$cmOtaRatePlanSynchronizeModel 	= new CmOtaRatePlanSynchronize();
		$logModel                       = new BookingLog();


		/*------------------ Get Bucket Data--------------------*/
		$bucket_id                      = $bucket_data['bucket_id'];
		$bucket_hotel_id                = $bucket_data['bucket_hotel_id'];
		$bucket_ota_id                  = $bucket_data['bucket_ota_id'];
		$bucket_ota_name                = $bucket_data['bucket_ota_name'];
		$bucket_ota_booking_tabel_id    = $bucket_data['bucket_ota_booking_tabel_id'];
		$bucket_ota_hotel_code          = $bucket_data['bucket_ota_hotel_code'];
		$bucket_booking_status          = $bucket_data['bucket_booking_status'];


		/*------------------ Get Booking Data--------------------*/
		$booking_ota_id                 = $booking_data['booking_ota_id'];
		$booking_hotel_id             	= $booking_data['booking_hotel_id'];
		$booking_room_type            	= $booking_data['booking_room_type'];
		$booking_rooms_qty            	= $booking_data['booking_rooms_qty'];
		$booking_checkin_at           	= $booking_data['booking_checkin_at'];

		/*------------------ Get Specific Ota Details-----------*/

		$ota_details_data             	=  $cmOtaDetailsModel
											->where('hotel_id', '=' ,$bucket_hotel_id)
											->where('ota_id', '=' ,$bucket_ota_id )
											->first();


		$ota_id 					  	= $ota_details_data->ota_id;
		$ota_hotel_code 			  	= $ota_details_data->ota_hotel_code;
		$auth_parameter 			  	= json_decode($ota_details_data->auth_parameter);
		$MessagePassword      			= trim($auth_parameter->MessagePassword);
		$ID                   			= trim($auth_parameter->ID);
		$commonUrl      				= $ota_details_data->url;

		/*------------------ set header ------------------ */
		$headers = array (
		//Regulates versioning of the XML interface for the API
		'Content-Type: application/xml'
		);


		$room_types 				= explode(",", $booking_room_type);
		$booking_ip					=	'1.1.1.1';

		foreach($room_types as $key => $room_type){

			if($booking_data['booking_source'] == 'ota'){
				$room_type =  $cmOtaRoomTypeSynchronizeModel->getSingleHotelRoomIdFromRoomSynch($room_type,$bucket_hotel_id);
				}
				else{
					$room_type = $room_types[$key];
				}
		if(!empty($room_type)){

		$log_data               	= [
									"action_id"          => 4,
									"hotel_id"           => $bucket_hotel_id,
									"ota_id"      		 => $bucket_ota_id,
									"booking_ref_id"     => $bucket_ota_booking_tabel_id,
									"user_id"            => 0,
									"request_msg"        => '',
									"response_msg"       => '',
									"request_url"        => '',
									"status"         	 => 2,
									"ip"         		 => $booking_ip,
									"comment"			 => "Processing for update "
									];
		$logModel->fill($log_data)->save();
		$room_code=$cmOtaRoomTypeSynchronizeModel->getOtaRoomType($room_type,$bucket_ota_id);

		/*******====== Get Inventory ===== ******/

		$inventoryData=$this->cmOtaBookingInvStatusService->getInventoryDetailsByRoomType($bucket_ota_booking_tabel_id,$room_type,$bucket_hotel_id,$bucket_ota_id);//	0 => No of days prior to the booking
		$url="";
		if($inventoryData)
		{
		if($bucket_booking_status == 'Commit')
		{
			for($i=0; $i<count($inventoryData); $i++)
			{
				$otainventory                   = new OtaInventory();
				if($inventoryData[$i]->block_status==0)
				{
					if(is_object($inventoryData[$i]->date)){
						foreach($inventoryData[$i]->date as $info){
							$startDate      	= date('Y-m-d', strtotime($info));
							$endDate      	= date('Y-m-d', strtotime($info));
						}
					}
					else{
						$startDate      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
						$endDate      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
					}
					$room_quanty = $inventoryData[$i]->update_to;

					if($room_quanty >= 0)
					{
						$xml = '<OTA_HotelAvailNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05">
						<POS>
						<Source>
						<RequestorID MessagePassword="'.$MessagePassword.'" ID="'.$ID.'" Type="CHM"/>
						</Source>
						</POS>
						<AvailStatusMessages HotelCode="'.$ota_hotel_code.'">
						<AvailStatusMessage BookingLimit="'.$room_quanty.'">
						<StatusApplicationControl  Mon="true" Tue="true" Weds="true" Thur="true" Sun="true" Sat="true"  Fri="true" Start="'.$startDate.'" End="'.$endDate.'" InvCode="'.$room_code.'"/>
						<RestrictionStatus SellThroughOpenIndicator="false"/>
						</AvailStatusMessage>
						</AvailStatusMessages>
						</OTA_HotelAvailNotifRQ>';

						$log_request_msg =$log_request_msg.$xml;

						$url 	= $commonUrl.'availability/update';

						$ch = curl_init();
						curl_setopt( $ch, CURLOPT_URL, $url );
						curl_setopt( $ch, CURLOPT_POST, true );
						curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
						curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
						curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml);
						$result = curl_exec($ch);
						curl_close($ch);
						$array_data = json_decode(json_encode(simplexml_load_string($result)), true);

						$log_response_msg=$log_response_msg.$result;
						//$log_response_msg='';
					} // empty $result.
				}
			}
			if(isset($array_data['Success'])){
				DB::table('booking_logs')->where('id', $logModel->id)
				->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);

			}else{
				DB::table('booking_logs')->where('id', $logModel->id)
				->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);

			}
		} // If $bucket_booking_status == 'Commit' close here.

		if($bucket_booking_status == 'Cancel')
		{
			for($i=0; $i<count($inventoryData); $i++)
			{
				$otainventory                   = new OtaInventory();
				if($inventoryData[$i]->block_status==0)
				{
					if(is_object($inventoryData[$i]->date)){
						foreach($inventoryData[$i]->date as $info){
							$startDate      	= date('Y-m-d', strtotime($info));
							$endDate      	= date('Y-m-d', strtotime($info));
						}
					}
					else{
						$startDate      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
						$endDate      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
					}
					$room_quanty = $inventoryData[$i]->update_to;

					if($room_quanty >= 0)
					{
						$xml = '<OTA_HotelAvailNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05">
						<POS>
						<Source>
						<RequestorID MessagePassword="'.$MessagePassword.'" ID="'.$ID.'" Type="CHM"/>
						</Source>
						</POS>
						<AvailStatusMessages HotelCode="'.$ota_hotel_code.'">
						<AvailStatusMessage BookingLimit="'.$room_quanty.'">
						<StatusApplicationControl Start="'.$startDate.'" End="'.$endDate.'" InvCode="'.$room_code.'"/>
						<RestrictionStatus SellThroughOpenIndicator="false"/>
						</AvailStatusMessage>
						</AvailStatusMessages>
						</OTA_HotelAvailNotifRQ>';

						$log_request_msg =$log_request_msg.$xml;

						$url 	= $commonUrl.'availability/update';

						$ch = curl_init();
						curl_setopt( $ch, CURLOPT_URL, $url );
						curl_setopt( $ch, CURLOPT_POST, true );
						curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
						curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
						curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml);
						$result = curl_exec($ch);
						curl_close($ch);

						$array_data = json_decode(json_encode(simplexml_load_string($result)), true);
						$log_response_msg=$log_response_msg.$result;
						// $log_response_msg='';
					} // empty $result.
				}
			}
			if(isset($array_data['Success'])){
				DB::table('booking_logs')->where('id', $logModel->id)
				->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);

			}else{
				DB::table('booking_logs')->where('id', $logModel->id)
				->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);

			}
		} // If $bucket_booking_status == 'Cancel' close here.
		}
			else{
					DB::table('booking_logs')->where('id', $logModel->id)
					->update(['status' => 0,"comment"=>"Unable to fetch inventory"]);
			}

		}
		else{
		// set log for Booking Room Type is not synch with hotel Room Type.
		$log_data                 	= [
									"action_id"          => 4,
									"hotel_id"           => $bucket_hotel_id,
									"ota_id"      		 => $bucket_ota_id,
									"booking_ref_id"     => $bucket_ota_booking_tabel_id,
									"user_id"            => 0,
									"request_msg"        => '',
									"response_msg"       => '',
									"request_url"        => '',
									"status"         	 => 0,
									"ip"         		 => $booking_ip,
									"comment"			 => "Booking room type is not mapped."
									];
		$logModel->fill($log_data)->save();

		} // If else !empty($result) closed here

	} // foreach $room_types closed here.
	return true;
}
/*------------------- Travelguru Update Function Close------------------------*/
	/*------------------- Easemytrip Update Function Start------------------------*/
	public function easeMyTripUpdate($bucket_data,$booking_data)
	{
			$log_request_msg="";
			$log_response_msg="";
			$date 							= new \DateTime();
			$dateTimestamp 					= $date->getTimestamp();

			$cmOtaDetailsModel  			= new CmOtaDetails();
			$cmOtaRoomTypeSynchronizeModel  = new CmOtaRoomTypeSynchronize();
			$cmOtaRatePlanSynchronizeModel 	= new CmOtaRatePlanSynchronize();
			$logModel                       = new BookingLog();


			/*------------------ Get Bucket Data--------------------*/
			$bucket_id                      = $bucket_data['bucket_id'];
			$bucket_hotel_id                = $bucket_data['bucket_hotel_id'];
			$bucket_ota_id                  = $bucket_data['bucket_ota_id'];
			$bucket_ota_name                = $bucket_data['bucket_ota_name'];
			$bucket_ota_booking_tabel_id    = $bucket_data['bucket_ota_booking_tabel_id'];
			$bucket_ota_hotel_code          = $bucket_data['bucket_ota_hotel_code'];
			$bucket_booking_status          = $bucket_data['bucket_booking_status'];


			/*------------------ Get Booking Data--------------------*/
			$booking_ota_id                 = $booking_data['booking_ota_id'];
			$booking_hotel_id             	= $booking_data['booking_hotel_id'];
			$booking_room_type            	= $booking_data['booking_room_type'];
			$booking_rooms_qty            	= $booking_data['booking_rooms_qty'];
			$booking_checkin_at           	= $booking_data['booking_checkin_at'];
			/*------------------ Get Specific Ota Details-----------*/

			$ota_details_data             	=  $cmOtaDetailsModel
												->where('hotel_id', '=' ,$bucket_hotel_id)
												->where('ota_id', '=' ,$bucket_ota_id )
												->first();


			$ota_id 					  	= $ota_details_data->ota_id;
			$ota_hotel_code 			  	= $ota_details_data->ota_hotel_code;
			$auth_parameter 			  	= json_decode($ota_details_data->auth_parameter);
			$token 			    			= trim($auth_parameter->Token);
			$commonUrl      				= $ota_details_data->url;

			/*------------------ set header ------------------ */
			$headers = array (
			//Regulates versioning of the XML interface for the API
			'Content-Type: application/json'
			);


			$room_types 				= explode(",", $booking_room_type);
			$booking_ip					=	'1.1.1.1';

			foreach($room_types as $key => $room_type){

				if($booking_data['booking_source'] == 'ota'){
					$room_type =  $cmOtaRoomTypeSynchronizeModel->getSingleHotelRoomIdFromRoomSynch($room_type,$bucket_hotel_id);
					}
					else{
						$room_type = $room_types[$key];
					}

			 if(!empty($room_type)){

			$log_data               	= [
										"action_id"          => 4,
										"hotel_id"           => $bucket_hotel_id,
										"ota_id"      		 => $bucket_ota_id,
										"booking_ref_id"     => $bucket_ota_booking_tabel_id,
										"user_id"            => 0,
										"request_msg"        => '',
										"response_msg"       => '',
										"request_url"        => '',
										"status"         	 => 2,
										"ip"         		 => $booking_ip,
										"comment"			 => "Processing for update "
										];
			$logModel->fill($log_data)->save();
			$room_code=$cmOtaRoomTypeSynchronizeModel->getOtaRoomType($room_type,$bucket_ota_id);

			/*******====== Get Inventory ===== ******/
			$url="";
			$inventoryData=$this->cmOtaBookingInvStatusService->getInventoryDetailsByRoomType($bucket_ota_booking_tabel_id,$room_type,$bucket_hotel_id,$bucket_ota_id);//	0 => No of days prior to the booking
			if($inventoryData)
			{
			if($bucket_booking_status == 'Commit')
			{
				for($i=0; $i<count($inventoryData); $i++)
				{
					$otainventory                   = new OtaInventory();
					if($inventoryData[$i]->block_status==0)
					{
						if(is_object($inventoryData[$i]->date)){
							foreach($inventoryData[$i]->date as $info){
								$startDate      	= date('Y-m-d', strtotime($info));
								$endDate      	= date('Y-m-d', strtotime($info));
							}
						}
						else{
							$startDate      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
							$endDate      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
						}
						$room_quanty = $inventoryData[$i]->update_to;


						if($room_quanty >= 0)
						{
							$xml='{
								"RequestType": "SaveSupplierHotel",
								"Token": "'.$token.'",
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
									] }';

							$log_request_msg =$log_request_msg.$xml;

							$url = trim($commonUrl.'/save');
							$ch = curl_init();
							curl_setopt( $ch, CURLOPT_URL, $url );
							curl_setopt( $ch, CURLOPT_POST, true );
							curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
							curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
							curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml);
							$result = curl_exec($ch);
							curl_close($ch);

							$array_data = json_decode(($result), true);

							$log_response_msg=$log_response_msg.$result;

						} // empty $result.
					}
				}
				if(isset($array_data["Status"])){
					if($array_data["Status"] == true){
						DB::table('booking_logs')->where('id', $logModel->id)
					->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);

						}else{
							DB::table('booking_logs')->where('id', $logModel->id)
							->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);

						}
				}
				else{
					DB::table('booking_logs')->where('id', $logModel->id)
					->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);

				}
			} // If $bucket_booking_status == 'Commit' close here.

			if($bucket_booking_status == 'Cancel')
			{
				for($i=0; $i<count($inventoryData); $i++)
				{
					$otainventory                   = new OtaInventory();
					if($inventoryData[$i]->block_status==0)
					{
						if(is_object($inventoryData[$i]->date)){
							foreach($inventoryData[$i]->date as $info){
								$startDate      	= date('Y-m-d', strtotime($info));
								$endDate      	= date('Y-m-d', strtotime($info));
							}
						}
						else{
							$startDate      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
							$endDate      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
						}
						$room_quanty = $inventoryData[$i]->update_to;

						if($room_quanty >= 0)
						{
							$xml='{
								"RequestType": "SaveSupplierHotel",
								"Token": "'.$token.'",
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
									] }';

							$log_request_msg =$log_request_msg.$xml;

							$url 	= $commonUrl.'save';

							$ch = curl_init();
							curl_setopt( $ch, CURLOPT_URL, $url );
							curl_setopt( $ch, CURLOPT_POST, true );
							curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
							curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
							curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml);
							$result = curl_exec($ch);
							curl_close($ch);
							$array_data = json_decode(($result), true);
							$log_response_msg=$log_response_msg.$result;
						} // empty $result.
					}
				}

				if(isset($array_data["Status"])){
					if($array_data["Status"] == true){
						DB::table('booking_logs')->where('id', $logModel->id)
					->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);

						}else{
							DB::table('booking_logs')->where('id', $logModel->id)
							->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);

						}
				}
				else{
					DB::table('booking_logs')->where('id', $logModel->id)
					->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);

				}

			} // If $bucket_booking_status == 'Cancel' close here.
			}
			}else{
			// set log for Booking Room Type is not synch with hotel Room Type.
			$log_data                 	= [
										"action_id"          => 4,
										"hotel_id"           => $bucket_hotel_id,
										"ota_id"      		 => $bucket_ota_id,
										"booking_ref_id"     => $bucket_ota_booking_tabel_id,
										"inventory_ref_id"   => '',
										"rate_ref_id"        => '',
										"user_id"            => 0,
										"request_msg"        => '',
										"response_msg"       => '',
										"request_url"        => '',
										"status"         	 => 0,
										"ip"         		 => $booking_ip,
										"comment"			 => "Booking room type is not mapped."
										];
			$logModel->fill($log_data)->save();

			} // If else !empty($result) closed here

		} // foreach $room_types closed here.
		return true;
}
	/*------------------- Easemytrip Update Function Close------------------------*/
	/*------------------- Paytm Update Function Start------------------------*/
	public function paytmUpdate($bucket_data,$booking_data)
	{
				$log_request_msg="";
				$log_response_msg="";
				$date 							= new \DateTime();
				$dateTimestamp 					= $date->getTimestamp();

				$cmOtaDetailsModel  			= new CmOtaDetails();
				$cmOtaRoomTypeSynchronizeModel  = new CmOtaRoomTypeSynchronize();
				$cmOtaRatePlanSynchronizeModel 	= new CmOtaRatePlanSynchronize();
				$logModel                       = new bookingLog();


				/*------------------ Get Bucket Data--------------------*/
				$bucket_id                      = $bucket_data['bucket_id'];
				$bucket_hotel_id                = $bucket_data['bucket_hotel_id'];
				$bucket_ota_id                  = $bucket_data['bucket_ota_id'];
				$bucket_ota_name                = $bucket_data['bucket_ota_name'];
				$bucket_ota_booking_tabel_id    = $bucket_data['bucket_ota_booking_tabel_id'];
				$bucket_ota_hotel_code          = $bucket_data['bucket_ota_hotel_code'];
				$bucket_booking_status          = $bucket_data['bucket_booking_status'];


				/*------------------ Get Booking Data--------------------*/
				$booking_ota_id                 = $booking_data['booking_ota_id'];
				$booking_hotel_id             	= $booking_data['booking_hotel_id'];
				$booking_room_type            	= $booking_data['booking_room_type'];
				$booking_rooms_qty            	= $booking_data['booking_rooms_qty'];
				$booking_checkin_at           	= $booking_data['booking_checkin_at'];

				/*------------------ Get Specific Ota Details-----------*/

				$ota_details_data             	=  $cmOtaDetailsModel
													->where('hotel_id', '=' ,$bucket_hotel_id)
													->where('ota_id', '=' ,$bucket_ota_id )
													->first();


				$ota_id 					  	= $ota_details_data->ota_id;
				$ota_hotel_code 			  	= $ota_details_data->ota_hotel_code;
				$auth_parameter 			  	= json_decode($ota_details_data->auth_parameter);
				$api_key 						= trim($auth_parameter->api_key);
				$commonUrl      				= $ota_details_data->url;

				/*------------------ set header ------------------ */
				$headers = array (
				//Regulates versioning of the XML interface for the API
				'Content-Type: application/json'
				);


				$room_types 				= explode(",", $booking_room_type);
				$booking_ip					=	'1.1.1.1';

				foreach($room_types as $key => $room_type){

					if($booking_data['booking_source'] == 'ota'){
						$room_type =  $cmOtaRoomTypeSynchronizeModel->getSingleHotelRoomIdFromRoomSynch($room_type,$bucket_hotel_id);
						}
						else{
							$room_type = $room_types[$key];
						}
				if(!empty($room_type)){

				$log_data               	= [
											"action_id"          => 4,
											"hotel_id"           => $bucket_hotel_id,
											"ota_id"      		 => $bucket_ota_id,
											"booking_ref_id"     => $bucket_ota_booking_tabel_id,
											"user_id"            => 0,
											"request_msg"        => '',
											"response_msg"       => '',
											"request_url"        => '',
											"status"         	 => 2,
											"ip"         		 => $booking_ip,
											"comment"			 => "Processing for update "
											];
				$logModel->fill($log_data)->save();
				$room_code=$cmOtaRoomTypeSynchronizeModel->getOtaRoomType($room_type,$bucket_ota_id);

				/*******====== Get Inventory ===== ******/

				 $inventoryData=$this->cmOtaBookingInvStatusService->getInventoryDetailsByRoomType($bucket_ota_booking_tabel_id,$room_type,$bucket_hotel_id,$bucket_ota_id);//	0 => No of days prior to the booking
				 $url="";
				if($inventoryData)
				{
				if($bucket_booking_status == 'Commit')
				{
					for($i=0; $i<count($inventoryData); $i++)
					{
						$otainventory                   = new OtaInventory();
						if($inventoryData[$i]->block_status==0)
						{
							if(is_object($inventoryData[$i]->date)){
								foreach($inventoryData[$i]->date as $info){
									$startDate      	= date('Y-m-d', strtotime($info));
									$endDate      	= date('Y-m-d', strtotime($info));
								}
							}
							else{
								$startDate      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
								$endDate      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
							}
							$room_quanty = $inventoryData[$i]->update_to;

							if($room_quanty >= 0)
							{
								$xml='{
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
									}}';

								$log_request_msg =$log_request_msg.$xml;

								$url                    = $commonUrl.'/inventoryUpdate';

								$ch = curl_init();
								curl_setopt( $ch, CURLOPT_URL, $url );
								curl_setopt( $ch, CURLOPT_POST, true );
								curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
								curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
								curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml);
								$result = curl_exec($ch);
								curl_close($ch);
								$array_data = json_decode(($result), true);

								$log_response_msg=$log_response_msg.$result;
							} // empty $result.
						}
					}
					if(isset($array_data["status"])){
						if($array_data["status"] == "success"){
							DB::table('booking_logs')->where('id', $logModel->id)
							->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);

							}else{
								DB::table('booking_logs')->where('id', $logModel->id)
								->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);

							}
					}
					else{
						DB::table('booking_logs')->where('id', $logModel->id)
						->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg]);
					}
				} // If $bucket_booking_status == 'Commit' close here.

				if($bucket_booking_status == 'Cancel')
				{
					for($i=0; $i<count($inventoryData); $i++)
					{
						$otainventory                   = new OtaInventory();
						if($inventoryData[$i]->block_status==0)
						{
							if(is_object($inventoryData[$i]->date)){
								foreach($inventoryData[$i]->date as $info){
									$startDate      	= date('Y-m-d', strtotime($info));
									$endDate      	= date('Y-m-d', strtotime($info));
								}
							}
							else{
								$startDate      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
								$endDate      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
							}
							$room_quanty = $inventoryData[$i]->update_to;

							if($room_quanty >= 0)
							{
								$xml='{
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
									}}';

								$log_request_msg =$log_request_msg.$xml;

								$url 	= $commonUrl.'availability/update';

								$ch = curl_init();
								curl_setopt( $ch, CURLOPT_URL, $url );
								curl_setopt( $ch, CURLOPT_POST, true );
								curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
								curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
								curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml);
								$result = curl_exec($ch);
								curl_close($ch);
								$array_data = json_decode(($result), true);
								$log_response_msg=$log_response_msg.$result;
								$log_response_msg='';
							} // empty $result.
						}
					}
					if(isset($array_data["status"])){
						if($array_data["status"] == "success"){
							DB::table('booking_logs')->where('id', $logModel->id)
						->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);

							}else{
								DB::table('booking_logs')->where('id', $logModel->id)
								->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);

							}
					}
					else{
						DB::table('booking_logs')->where('id', $logModel->id)
						->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg]);
					}
				} // If $bucket_booking_status == 'Cancel' close here.
				}
				}else{
				// set log for Booking Room Type is not synch with hotel Room Type.
				$log_data                 	= [
											"action_id"          => 4,
											"hotel_id"           => $bucket_hotel_id,
											"ota_id"      		 => $bucket_ota_id,
											"booking_ref_id"     => $bucket_ota_booking_tabel_id,
											"user_id"            => 0,
											"request_msg"        => '',
											"response_msg"       => '',
											"request_url"        => '',
											"status"         	 => 0,
											"ip"         		 => $booking_ip,
											"comment"			 => "Booking room type is not mapped."
											];
				$logModel->fill($log_data)->save();

				} // If else !empty($result) closed here

			} // foreach $room_types closed here.
			return true;
	}
		/*------------------- Paytm Update Function Close------------------------*/
			/*------------------- Goomo Update Function Start------------------------*/
			public function goomoUpdate($bucket_data,$booking_data)
			{
					$log_request_msg="";
					$log_response_msg="";
					$date 							= new \DateTime();
					$dateTimestamp 					= $date->getTimestamp();

					$cmOtaDetailsModel  			= new CmOtaDetails();
					$cmOtaRoomTypeSynchronizeModel  = new CmOtaRoomTypeSynchronize();
					$cmOtaRatePlanSynchronizeModel 	= new CmOtaRatePlanSynchronize();
					$logModel                       = new BookingLog();


					/*------------------ Get Bucket Data--------------------*/
					$bucket_id                      = $bucket_data['bucket_id'];
					$bucket_hotel_id                = $bucket_data['bucket_hotel_id'];
					$bucket_ota_id                  = $bucket_data['bucket_ota_id'];
					$bucket_ota_name                = $bucket_data['bucket_ota_name'];
					$bucket_ota_booking_tabel_id    = $bucket_data['bucket_ota_booking_tabel_id'];
					$bucket_ota_hotel_code          = $bucket_data['bucket_ota_hotel_code'];
					$bucket_booking_status          = $bucket_data['bucket_booking_status'];


					/*------------------ Get Booking Data--------------------*/
					$booking_ota_id                 = $booking_data['booking_ota_id'];
					$booking_hotel_id             	= $booking_data['booking_hotel_id'];
					$booking_room_type            	= $booking_data['booking_room_type'];
					$booking_rooms_qty            	= $booking_data['booking_rooms_qty'];
					$booking_checkin_at           	= $booking_data['booking_checkin_at'];
					/*------------------ Get Specific Ota Details-----------*/

					$ota_details_data             	=  $cmOtaDetailsModel
														->where('hotel_id', '=' ,$bucket_hotel_id)
														->where('ota_id', '=' ,$bucket_ota_id )
														->first();


					$ota_id 					  	= $ota_details_data->ota_id;
					$ota_hotel_code 			  	= $ota_details_data->ota_hotel_code;
					$auth_parameter 			  	= json_decode($ota_details_data->auth_parameter);
					$apiKey               			= trim($auth_parameter->apiKey);
					$channelId               		= trim($auth_parameter->channelId);
					$accessToken               		= trim($auth_parameter->accessToken);
					$commonUrl      				= $ota_details_data->url;

					/*------------------ set header ------------------ */
					$headers = array();
					$headers[] = "apiKey: $apiKey";
					$headers[] = "channelId: $channelId";
					$headers[] = "accessToken: $accessToken";
					$headers[] = "Content-Type: application/json";


					$room_types 				= explode(",", $booking_room_type);
					$booking_ip					=	'1.1.1.1';

					foreach($room_types as $key => $room_type){

						if($booking_data['booking_source'] == 'ota'){
							$room_type =  $cmOtaRoomTypeSynchronizeModel->getSingleHotelRoomIdFromRoomSynch($room_type,$bucket_hotel_id);
							}
							else{
								$room_type = $room_types[$key];
							}
					if(!empty($room_type)){

					$log_data               	= [
												"action_id"          => 4,
												"hotel_id"           => $bucket_hotel_id,
												"ota_id"      		 => $bucket_ota_id,
												"booking_ref_id"     => $bucket_ota_booking_tabel_id,
												"user_id"            => 0,
												"request_msg"        => '',
												"response_msg"       => '',
												"request_url"        => '',
												"status"         	 => 2,
												"ip"         		 => $booking_ip,
												"comment"			 => "Processing for update "
												];
					$logModel->fill($log_data)->save();
					$room_code=$cmOtaRoomTypeSynchronizeModel->getOtaRoomType($room_type,$bucket_ota_id);

					/*******====== Get Inventory ===== ******/

					$inventoryData=$this->cmOtaBookingInvStatusService->getInventoryDetailsByRoomType($bucket_ota_booking_tabel_id,$room_type,$bucket_hotel_id,$bucket_ota_id);//	0 => No of days prior to the booking
					$url="";
					if($inventoryData)
					{
					if($bucket_booking_status == 'Commit')
					{
						for($i=0; $i<count($inventoryData); $i++)
						{
							$otainventory                   = new OtaInventory();
							if($inventoryData[$i]->block_status==0)
							{
								if(is_object($inventoryData[$i]->date)){
									foreach($inventoryData[$i]->date as $info){
										$startDate      	= date('Y-m-d', strtotime($info));
										$endDate      	= date('Y-m-d', strtotime($info));
									}
								}
								else{
									$startDate      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
									$endDate      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
								}
								$room_quanty = $inventoryData[$i]->update_to;

								if($room_quanty >= 0)
								{
									$days=array('true', 'true', 'true','true','true','true','true');
									$post_data=array("available" => $room_quanty, "block"=>false, "days"=>$days, "channelName"=> "Bookingjini",
									"startDate" => $startDate,
									"endDate" =>$endDate,
									"roomId" => $room_code,
									"productId"=>$ota_hotel_code);
									$post_data=json_encode($post_data);
									$log_request_msg=$commonUrl.'/updateInventory'.$post_data;
									$url=$commonUrl."/updateInventory";
									$ch = curl_init();
									curl_setopt( $ch, CURLOPT_URL, $url );
									curl_setopt( $ch, CURLOPT_POST, true );
									curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
									curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
									curl_setopt( $ch, CURLOPT_POSTFIELDS, $post_data);
									$result = curl_exec($ch);
									curl_close($ch);
									$array_data = json_decode(($result), true);

									$log_response_msg=$log_response_msg.$result;
								} // empty $result.
							}
						}
						if($array_data["Status"]=="Success"){
							DB::table('booking_logs')->where('id', $logModel->id)
							->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);
						}else{
							DB::table('booking_logs')->where('id', $logModel->id)
							->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);
						}
					} // If $bucket_booking_status == 'Commit' close here.

					if($bucket_booking_status == 'Cancel')
					{
						for($i=0; $i<count($inventoryData); $i++)
						{
							$otainventory                   = new OtaInventory();
							if($inventoryData[$i]->block_status==0)
							{
								if(is_object($inventoryData[$i]->date)){
									foreach($inventoryData[$i]->date as $info){
										$startDate      	= date('Y-m-d', strtotime($info));
										$endDate      	= date('Y-m-d', strtotime($info));
									}
								}
								else{
									$startDate      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
									$endDate      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
								}
								$room_quanty = $inventoryData[$i]->update_to;

								if($room_quanty >= 0)
								{
									$days=array('true', 'true', 'true','true','true','true','true');
									$post_data=array("available" => $room_quanty, "block"=>false, "days"=>$days, "channelName"=> "Bookingjini",
									"startDate" => $startDate,
									"endDate" =>$endDate,
									"roomId" => $room_code,
									"productId"=>$ota_hotel_code);
									$post_data=json_encode($post_data);
									$log_request_msg=$commonUrl.'/updateInventory'.$post_data;
									$url=$commonUrl."/updateInventory";

									$log_request_msg =$log_request_msg;

									$url 	= $commonUrl.'availability/update';

									$ch = curl_init();
									curl_setopt( $ch, CURLOPT_URL, $url );
									curl_setopt( $ch, CURLOPT_POST, true );
									curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
									curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
									curl_setopt( $ch, CURLOPT_POSTFIELDS, $post_data);
									$result = curl_exec($ch);
									curl_close($ch);
									$array_data = json_decode(($result), true);
									$log_response_msg=$log_response_msg.$result;
								} // empty $result.
							}
						}
						if($array_data["Status"]=="Success"){
							DB::table('booking_logs')->where('id', $logModel->id)
							->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);
						}else{
							DB::table('booking_logs')->where('id', $logModel->id)
							->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);
						}
					} // If $bucket_booking_status == 'Cancel' close here.
					}
					}else{
					// set log for Booking Room Type is not synch with hotel Room Type.
					$log_data                 	= [
												"action_id"          => 4,
												"hotel_id"           => $bucket_hotel_id,
												"ota_id"      		 => $bucket_ota_id,
												"booking_ref_id"     => $bucket_ota_booking_tabel_id,
												"user_id"            => 0,
												"request_msg"        => '',
												"response_msg"       => '',
												"request_url"        => '',
												"status"         	 => 0,
												"ip"         		 => $booking_ip,
												"comment"			 => "Booking room type is not mapped."
												];
					$logModel->fill($log_data)->save();

					} // If else !empty($result) closed here

				} // foreach $room_types closed here.
				return true;
		}
		/*------------------- Goomo Update Function Close------------------------*/
		/*--------------------HappyEasyGo Function Start----------------------------------------*/
public function happyEasyGoUpdate($bucket_data,$booking_data)
{
			$log_request_msg="";
			$log_response_msg="";
			$date 							= new \DateTime();
			$dateTimestamp 					= $date->getTimestamp();

			$cmOtaDetailsModel  			= new CmOtaDetails();
			$cmOtaRoomTypeSynchronizeModel  = new CmOtaRoomTypeSynchronize();
			$cmOtaRatePlanSynchronizeModel 	= new CmOtaRatePlanSynchronize();
			$logModel                       = new bookingLog();


			/*------------------ Get Bucket Data--------------------*/
			$bucket_id                      = $bucket_data['bucket_id'];
			$bucket_hotel_id                = $bucket_data['bucket_hotel_id'];
			$bucket_ota_id                  = $bucket_data['bucket_ota_id'];
			$bucket_ota_name                = $bucket_data['bucket_ota_name'];
			$bucket_ota_booking_tabel_id    = $bucket_data['bucket_ota_booking_tabel_id'];
			$bucket_ota_hotel_code          = $bucket_data['bucket_ota_hotel_code'];
			$bucket_booking_status          = $bucket_data['bucket_booking_status'];


			/*------------------ Get Booking Data--------------------*/
			$booking_ota_id                 = $booking_data['booking_ota_id'];
			$booking_hotel_id             	= $booking_data['booking_hotel_id'];
			$booking_room_type            	= $booking_data['booking_room_type'];
			$booking_rooms_qty            	= $booking_data['booking_rooms_qty'];
			$booking_checkin_at           	= $booking_data['booking_checkin_at'];

			/*------------------ Get Specific Ota Details-----------*/

			$ota_details_data             	=  $cmOtaDetailsModel
												->where('hotel_id', '=' ,$bucket_hotel_id)
												->where('ota_id', '=' ,$bucket_ota_id )
												->first();


			$ota_id 					  	= $ota_details_data->ota_id;
			$ota_hotel_code 			  	= $ota_details_data->ota_hotel_code;
			$auth_parameter 			  	= json_decode($ota_details_data->auth_parameter);
			$api_key 						= trim($auth_parameter->key);
			$commonUrl      				= $ota_details_data->url;

			/*------------------ set header ------------------ */
			$headers = array (
			//Regulates versioning of the XML interface for the API
			'Content-Type: application/json'
			);


			$room_types 				= explode(",", $booking_room_type);
			$booking_ip					=	'1.1.1.1';

			foreach($room_types as $key => $room_type){
					// $room_type =  $cmOtaRoomTypeSynchronizeModel->getSingleHotelRoomIdFromRoomSynch($room_type,$bucket_hotel_id);

				if($booking_data['booking_source'] == 'ota'){
					$room_type =  $cmOtaRoomTypeSynchronizeModel->getSingleHotelRoomIdFromRoomSynch($room_type,$bucket_hotel_id);
					}
					else{
						$room_type = $room_types[$key];
					}
			if(!empty($room_type)){

			$log_data               	= [
										"action_id"          => 4,
										"hotel_id"           => $bucket_hotel_id,
										"ota_id"      		 => $bucket_ota_id,
										"booking_ref_id"     => $bucket_ota_booking_tabel_id,
										"user_id"            => 0,
										"request_msg"        => '',
										"response_msg"       => '',
										"request_url"        => '',
										"status"         	 => 2,
										"ip"         		 => $booking_ip,
										"comment"			 => "Processing for update "
										];
			$logModel->fill($log_data)->save();
			$room_code=$cmOtaRoomTypeSynchronizeModel->getOtaRoomType($room_type,$bucket_ota_id);

			/*******====== Get Inventory ===== ******/

			 $inventoryData=$this->cmOtaBookingInvStatusService->getInventoryDetailsByRoomType($bucket_ota_booking_tabel_id,$room_type,$bucket_hotel_id,$bucket_ota_id);//	0 => No of days prior to the booking
			 $url="";
			if($inventoryData)
			{
			if($bucket_booking_status == 'Commit')
			{
				for($i=0; $i<count($inventoryData); $i++)
				{
					$otainventory                   = new OtaInventory();
					if($inventoryData[$i]->block_status==0)
					{
						if(is_object($inventoryData[$i]->date)){
							foreach($inventoryData[$i]->date as $info){
								$startDate      	= date('Y-m-d', strtotime($info));
								$endDate      	= date('Y-m-d', strtotime($info));
							}
						}
						else{
							$startDate      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
							$endDate      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
						}
						$room_quanty = $inventoryData[$i]->update_to;

						if($room_quanty >= 0)
						{
							$xml='{
								"auth": {
								"key": "'.$api_key.'"
								},
								"data": {
									"propertyId": "'.$ota_hotel_code.'",
									"roomId": "'.$room_code.'",
									"ota": "BookingJini",
									"inventory": [
									{
									"startDate": "'.$startDate.'",
									"endDate": "'.$endDate.'",
									"free": '.$room_quanty.'
									}
									]
									}}';

							$log_request_msg =$log_request_msg.$xml;

							$url                    = $commonUrl.'/heg/updateInventory';

							$ch = curl_init();
							curl_setopt( $ch, CURLOPT_URL, $url );
							curl_setopt( $ch, CURLOPT_POST, true );
							curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
							curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
							curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml);
							$result = curl_exec($ch);
							curl_close($ch);
							$array_data = json_decode(($result), true);

							$log_response_msg=$log_response_msg.$result;
						} // empty $result.
					}
				}
				if(isset($array_data["status"])){
					if($array_data["status"] == "success"){
						DB::table('booking_logs')->where('id', $logModel->id)
						->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);

						}else{
							DB::table('booking_logs')->where('id', $logModel->id)
							->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);

						}
				}
				else{
					DB::table('booking_logs')->where('id', $logModel->id)
					->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg]);
				}
			} // If $bucket_booking_status == 'Commit' close here.

			if($bucket_booking_status == 'Cancel')
			{
				for($i=0; $i<count($inventoryData); $i++)
				{
					$otainventory                   = new OtaInventory();
					if($inventoryData[$i]->block_status==0)
					{
						if(is_object($inventoryData[$i]->date)){
							foreach($inventoryData[$i]->date as $info){
								$startDate      	= date('Y-m-d', strtotime($info));
								$endDate      	= date('Y-m-d', strtotime($info));
							}
						}
						else{
							$startDate      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
							$endDate      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
						}
						$room_quanty = $inventoryData[$i]->update_to;

						if($room_quanty >= 0)
						{
							$xml='{
								"auth": {
								"key": "'.$api_key.'"
								},
								"data": {
									"propertyId": "'.$ota_hotel_code.'",
									"roomId": "'.$room_code.'",
									"ota": "BookingJini",
									"inventory": [
									{
									"startDate": "'.$startDate.'",
									"endDate": "'.$endDate.'",
									"free": '.$room_quanty.'
									}
									]
									}}';

							$log_request_msg =$log_request_msg.$xml;

							$url 	= $commonUrl.'/heg/updateInventory';

							$ch = curl_init();
							curl_setopt( $ch, CURLOPT_URL, $url );
							curl_setopt( $ch, CURLOPT_POST, true );
							curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
							curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
							curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml);
							$result = curl_exec($ch);
							curl_close($ch);
							$array_data = json_decode(($result), true);
							$log_response_msg=$log_response_msg.$result;
							$log_response_msg='';
						} // empty $result.
					}
				}
				if(isset($array_data["status"])){
					if($array_data["status"] == "success"){
						DB::table('booking_logs')->where('id', $logModel->id)
					->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);

						}else{
							DB::table('booking_logs')->where('id', $logModel->id)
							->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);

						}
				}
				else{
					DB::table('booking_logs')->where('id', $logModel->id)
					->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg]);
				}
			} // If $bucket_booking_status == 'Cancel' close here.
			}
			}else{
			// set log for Booking Room Type is not synch with hotel Room Type.
			$log_data                 	= [
										"action_id"          => 4,
										"hotel_id"           => $bucket_hotel_id,
										"ota_id"      		 => $bucket_ota_id,
										"booking_ref_id"     => $bucket_ota_booking_tabel_id,
										"user_id"            => 0,
										"request_msg"        => '',
										"response_msg"       => '',
										"request_url"        => '',
										"status"         	 => 0,
										"ip"         		 => $booking_ip,
										"comment"			 => "Booking room type is not mapped."
										];
			$logModel->fill($log_data)->save();

			} // If else !empty($result) closed here

		} // foreach $room_types closed here.
		return true;
}
/*--------------------HappyEasyGo Function Close----------------------------------------*/

	//-------------------IDS Integration-----------------------------//
	//HANDLE IDS UPDATE
public function testhandleIds(Request $request){

	$this->handleIds(array(),array('booking_hotel_id'=>1151));
}
public function handleIds($bucket_data,$booking_data)
{
	$bucket_booking_status=$bucket_data['bucket_booking_status'];
	$hotel_id = $booking_data['booking_hotel_id'];
	$ids_status=$this->idsService->getIdsStatus($hotel_id);
	$customer_data=array();
    if($ids_status)
    {
		$ids_data=$this->prepare_ids_data($hotel_id,$booking_data);

		$customer_details=$booking_data['booking_customer_details'];
		$customer_details=explode(',',$customer_details);
		if(sizeof($customer_details) != 3){
			$customer_data['first_name']='NA';
			$customer_data['last_name']='NA';
			$customer_data['email_id']=$customer_details[0];
			$customer_data['mobile']=$customer_details[1];
		}
		else{
			$name=explode(' ',$customer_details[0]);
			$customer_data['first_name']=$name[0];
			$customer_data['last_name']=$name[1];
			$customer_data['email_id']=$customer_details[1];
			$customer_data['mobile']=$customer_details[2];
		}
		$type=$booking_data['booking_channel'];
		$ids_string="";
		$last_ids_id=$this->idsService->idsBookings($hotel_id,$type,$ids_data,$customer_data,$bucket_booking_status);
		if($last_ids_id)
        {
			DB::table('cm_ota_booking')
            ->where('id',$bucket_data['bucket_ota_booking_tabel_id'])
			->update(['ids_re_id' => $last_ids_id]);
			$ids_string=$this->idsService->getIdsString($last_ids_id);
			if($this->idsService->pushReservations($ids_string,$last_ids_id)){
				DB::table('ids_reservation')->where('id', $last_ids_id)
								->update(['ids_confirm' => 1]);
			}else{
				DB::table('ids_reservation')->where('id', $last_ids_id)
								->update(['ids_confirm' => 2]);
			}
        }

        return true;
    }
    else
    {
    	return true;
    }

}

public function handleBookOne($ota_booking_tabel_id, $hotel_id)
{
	if($hotel_id==1491)
	{
		$booking_date  =date('Y-m-d');//date('Y-m-d', strtotime('2019-10-08'));
		$response_format = 'JSON';
		$last_id='';
		$url        ='https://admin.bookingjini.com/v2/backend/web/api/pms/booking-details';
		$post       =array("hotel_id"=>$hotel_id, "response_format"=>$response_format, "last_id"=>$last_id, "booking_date"=>$booking_date);
		//  Initiate curl
		$ch = curl_init();
		// Disable SSL verification
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		// Will return the response, if false it print the response
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		// Set the url
		curl_setopt($ch, CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('key: b5a45e2b-e1a5-4707-93d0-cbd5cfe902f0'));
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		// Execute
		$result=curl_exec($ch);
		// Closing
		curl_close($ch);
		$all_bookings=json_decode($result);
		if(isset($all_bookings->data))
		{
			foreach ($all_bookings->data as $booking){
			$id=substr($booking->BookingsDetails->booking_id, 6);
			if($ota_booking_tabel_id==$id){
				$tr_id=$booking->BookingsDetails->booking_id;
				$return_array =array('data'=>array($booking), 'b_status'=>'yes');
				$res=json_encode($return_array);

		  $curl = curl_init();
		  curl_setopt_array($curl, array(
		  CURLOPT_URL => "https://bookingjini-integration.appspot.com/api/bookingJini/reservation",
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 30,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "POST",
		  CURLOPT_POSTFIELDS =>$res,
		  CURLOPT_HTTPHEADER => array(
		    "API_KEY: 34234234m423525345sdffs53rfsdfsdf23423rewfew",
		    "CHANNEL_ID: 2",
		    "Content-Type: application/json",
		    "MESSAGE_TYPE: application/json",
		    "TRANSACTION_ID: ".$tr_id,
		    "cache-control: no-cache"
		  ),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		if ($err) {
		  echo "cURL Error #:" . $err;
		} else {
		  echo $response;
		}
				}
			}
		}

	}
	else
    {
    	return true;
    }

}

public function prepare_ids_data($hotel_id,$ota_booking_data)
{
	$cmOtaRoomTypeSynchronizeModel  = new CmOtaRoomTypeSynchronize();
	$cmOtaRatePlanSynchronizeModel 	= new CmOtaRatePlanSynchronize();
    $booking_data=array();
    $booking_data['booking_id']=$ota_booking_data['booking_unique_id'];//Unique booking from OTA
	$booking_data['room_stay']=array();
	$from_date=$ota_booking_data['booking_checkin_at'];
	$to_date=$ota_booking_data['booking_checkout_at'];
    $date1=date_create($ota_booking_data['booking_checkin_at']);
    $date2=date_create($ota_booking_data['booking_checkout_at']);
    $diff=date_diff($date1,$date2);
    $diff=$diff->format("%a");
	$no_of_rooms=0;
	$room_types 					= explode(",", $ota_booking_data['booking_room_type']);
	$rooms_qty 					    = explode(",", $ota_booking_data['booking_rooms_qty']);
	$rate_code 					    = explode(",",$ota_booking_data['booking_rate_code']);
	$no_of_room_types=sizeof($room_types);
    foreach($room_types as $key=>$room_type)
        {
			$room_type= $cmOtaRoomTypeSynchronizeModel->getSingleHotelRoomIdFromRoomSynch($room_type,$hotel_id);
			$hotel_rate_plan_id= $cmOtaRatePlanSynchronizeModel->get_room_rate_plan($rate_code[$key]);
            $rates_arr=array();
			$total_adult=2;//By default adults 2

            for($i=0;$i<$rooms_qty[$key];$i++)
            {
                $frm_date=$from_date;
                $rates_arr=array();
                for($j=1;$j<=$diff;$j++)
                {
                    $amount=0;
                    $d1=$frm_date;
                    $d2=date('Y-m-d', strtotime($d1 . ' +1 day'));
                    $amount=(($ota_booking_data['booking_amount']/$no_of_room_types/$rooms_qty[$key]/$diff));
                    if(strpos('.', $amount) == false)
                    {
                        $amount=$amount;
                    }
                    array_push($rates_arr,array("from_date"=>$d1,"to_date"=>$d2,'amount'=>$amount,'tax_amount'=>0));
                    $frm_date=date('Y-m-d', strtotime($d1 . ' +1 day'));
                }
            $arr=array('room_type_id'=>$room_type,'rate_plan_id'=>$hotel_rate_plan_id,'adults'=>$total_adult,'from_date'=>$from_date,'to_date'=>$to_date,'rates'=>$rates_arr);
			array_push($booking_data['room_stay'],$arr);
			}
		}
    return $booking_data;
}
public function mailHandler($ota_booking_id,$bucket_booking_status)
{
	$cmOtaRoomTypeSynchronizeModel  = new CmOtaRoomTypeSynchronize();
	$cmOtaRatePlanSynchronizeModel 	= new CmOtaRatePlanSynchronize();

	$cm_ota_booking_model= new CmOtaBooking();
	$ota_booking_data=$cm_ota_booking_model->where('id',$ota_booking_id)->first();
	$ota_id=$ota_booking_data->ota_id;
	$room_types=$ota_booking_data->room_type;
	$rate_plans=$ota_booking_data->rate_code;
	$hotel_id=$ota_booking_data->hotel_id;

	$room_types 					= explode(",", $room_types);
	$rate_plans 					= explode(",", $rate_plans);
	$hotel_room_type="";
	$hotel_rate_plan="";
	$from_date=strtotime($ota_booking_data->checkout_at);
	$to_date=strtotime($ota_booking_data->checkin_at);
	$difference=$from_date-$to_date;
	$number_of_nights=round($difference/(60 * 60 * 24));
	$rooms=explode(',',$ota_booking_data->rooms_qty);
	$room_data=0;
	foreach($rooms as $room)
	{
		$room_data=$room_data+$room;
	}
	$rooms=$room_data;

	foreach($room_types as $room_type)
	{
		if(sizeof($room_types)==1)
		{
			$hotel_room_type= $cmOtaRoomTypeSynchronizeModel->getRoomType($room_type,$ota_id);
		}
		else
		{
			$hotel_room_type=','.$cmOtaRoomTypeSynchronizeModel->getRoomType($room_type,$ota_id);
		}

	}
	foreach($rate_plans as $rate_plan)
	{
		if(sizeof($room_types)==1)
		{
			$hotel_rate_plan= $cmOtaRatePlanSynchronizeModel->getRoomRatePlan($ota_id,$rate_plan);
		}
		else
		{
			$hotel_rate_plan=','.$cmOtaRatePlanSynchronizeModel->getRoomRatePlan($ota_id,$rate_plan);
		}

	}
	$hotel=HotelInformation::where('hotel_id',$hotel_id)->select('hotel_name','email_id','hotel_address','city_id','state_id')->first();
	$city_name=DB::table('city_table')->where('city_id',$hotel->city_id)->first();
	$state_name=DB::table('state_table')->where('state_id',$hotel->state_id)->first();
	$hotel_name=$hotel->hotel_name;
	$hotel_address=$hotel->hotel_address;
	$email_id=explode(',',$hotel->email_id);
	if($email_id)
	{
		$s=sizeof($email_id);
		if($s>1)
		{
			$email_id1=$email_id[1];
			$email_id=$email_id[0];
		}
		else if($s==1)
		{
			$email_id=$email_id[0];
			$email_id1="";
		}
		else
		{
			$email_id='channelmanager@5elements.co.in';
			$email_id1="";
		}
	}
	$ota_details=CmOtaDetails::where('ota_id',$ota_id)->select('ota_name','commision')->first();
	$ota_name=$ota_details->ota_name;
	$commision=$ota_details->commision;
	$commision_amount=number_format($ota_booking_data->amount*($commision/100),2);
	$supplier_amount=number_format($ota_booking_data->amount-$commision_amount,2);
	if($bucket_booking_status=="Commit")
	{
		$subject="Booking Confirmation Mail from ".$ota_name." BookingId: ".$ota_booking_data->unique_id;
		$bucket_booking_status="Confirmed";
	}
	else if($bucket_booking_status=="Cancel")
	{
		$subject="Booking Cancellation Mail from ".$ota_name." BookingId: ".$ota_booking_data->unique_id;
		$bucket_booking_status="Cancelled";
	}
	else if($bucket_booking_status=="Modfiy")
	{
		$subject="Booking Modification Mail from ".$ota_name." BookingId: ".$ota_booking_data->unique_id;
		$bucket_booking_status="Modified";
	}
	$template=array("ota_name"=>$ota_name,"hotel_name"=>$hotel->hotel_name,"hotel_address"=>$hotel->hotel_address,
	"city_name"=>$city_name->city_name,
	"state_name"=>$state_name->state_name,
	"booking_id"=>$ota_booking_data->unique_id,"check_in"=>$ota_booking_data->checkin_at,
	"check_out"=>$ota_booking_data->checkout_at,"room_type"=>$hotel_room_type,"rate_plan"=>$hotel_rate_plan,"rooms"=>$rooms,
	"total_amount"=>$ota_booking_data->amount,"customer_details"=>$ota_booking_data->customer_details,"booking_status"=>$bucket_booking_status,
	"payment_status"=>$ota_booking_data->payment_status,"commision_amount"=>$commision_amount,"supplier_amount"=>$supplier_amount,"number_of_nights"=>$number_of_nights);
	if($email_id)
	{
		$this->sendMail($email_id,$template,$subject,$hotel->hotel_name, $email_id1);
	}
}
/*
*Email Invoice
*@param $email for to email
*@param $template is the email template
*@param $subject for email subject
*/
public function sendMail($hotel_email,$template, $subject,$hotel_name, $email_id1)
{
	if($email_id1!="")
	{
		$mail_array=['channelmanager@5elements.co.in', $email_id1];
		// $mail_array=$email_id1;
	}
	else
	{
		$mail_array=['channelmanager@5elements.co.in'];
	}

	//$mail_array=['satya.narayan@5elements.co.in'];
    $data = array('hotel_email' =>$hotel_email,'subject'=>$subject,'mail_array'=>$mail_array);

	  $data['template']=$template;
    $data['hotel_name']=$hotel_name;
    Mail::send(['html' => 'emails.otaBookingTemplate'],$template,function ($message) use ($data)
	{
		$message->to($data['hotel_email'])
		->bcc($data['mail_array'])
		->from( env("MAIL_FROM"), $data['hotel_name'])
		->subject( $data['subject']);
    });
    if(Mail::failures())
    {
        return false;
    }
    return true;
}


public function mailHandler1($ota_booking_id,$bucket_booking_status)
{
	$cmOtaRoomTypeSynchronizeModel  = new CmOtaRoomTypeSynchronize();
	$cmOtaRatePlanSynchronizeModel 	= new CmOtaRatePlanSynchronize();
	$cm_ota_booking_model= new CmOtaBooking();
	$ota_booking_data=	$cm_ota_booking_model->where('id',$ota_booking_id)->first();
	$ota_id=	$ota_booking_data->ota_id;
	$room_types=	$ota_booking_data->room_type;
	$rate_plans=	$ota_booking_data->rate_code;
	$hotel_id=	$ota_booking_data->hotel_id;
	$from_date=	strtotime($ota_booking_data->checkout_at);
	$to_date=	strtotime($ota_booking_data->checkin_at);
	$booking_date=	$ota_booking_data->booking_date;
	$channel_name=	$ota_booking_data->channel_name;
	$tax_amount=	$ota_booking_data->tax_amount;
	$no_of_adult=	explode(',',$ota_booking_data->no_of_adult);
	$no_of_child=	explode(',',$ota_booking_data->no_of_child);
	$inclusion =	explode(',',$ota_booking_data->inclusion);
	$inclusion	=	$inclusion[0];
	$special_info	=	$ota_booking_data->special_information;
	$cancel_policy	=	$ota_booking_data->cancel_policy;
	$customer_details=	explode(',',$ota_booking_data->customer_details);
	$customer_name=	$customer_details[0];
	$customer_email=	$customer_details[1];
	$customer_number=	$customer_details[2];
	$currency	=	$ota_booking_data->currency;
	if($customer_email == null)
	{
		$customer_email='NA';
	}
	$difference=$from_date-$to_date;
	$number_of_nights=round($difference/(60 * 60 * 24));
	$rooms=explode(',',$ota_booking_data->rooms_qty);
	$room_types 					= explode(",", $room_types);
	$rate_plans 					= explode(",", $rate_plans);
	$hotel_room_type="";
	$hotel_rate_plan="";
	$guest=array();
	$guest1=array();
	$rate_plan_details=array();
	foreach($rate_plans as $rates){
		if(sizeof($rate_plan_details) > 0){
			if(in_array($rates,$rate_plan_details)){

			}
			else{
				$rate_plan_details[]=$rates;
			}
		}
		else{
			$rate_plan_details[]=$rates;
		}
	}
	$k=0;
	foreach($rooms as $key=>$room){
		if($room > 1){
			$sum = 0;
			$sum1 = 0;
			for($i=0;$i<$room;$i++){
				if(!isset($no_of_child[$i])){
					$no_of_child[$i] = 0;
				}
                if(!isset($no_of_adult[$i])){
                    $no_of_adult[$i] = 0;
                }
				$sum = $sum + $no_of_adult[$k];
				$sum1 = $sum1 + isset($no_of_child[$k])?$no_of_child[$k]:0;
				$k++;
			}
			$guest[]=(string)$sum;
			$guest1[]=(string)$sum1;
		}
		else{
			$guest[]=$no_of_adult[$k];
			if(!isset($no_of_child[$k])){
				$no_of_child[$k] = 0;
			}
			$guest1[]=$no_of_child[$k];
			$k++;
		}
	}
	foreach($room_types as $key=>$room_type)
	{
		if(sizeof($room_types)==1)
		{
			$hotel_room_type= $cmOtaRoomTypeSynchronizeModel->getRoomType($room_type,$ota_id);
		}
		else
		{
			if($key==0){
				$hotel_room_type.=$cmOtaRoomTypeSynchronizeModel->getRoomType($room_type,$ota_id);

			}else{
				$hotel_room_type.=','.$cmOtaRoomTypeSynchronizeModel->getRoomType($room_type,$ota_id);
			}
		}
	}

	foreach($rate_plan_details as $key=>$rate_plan)
	{
		if(sizeof($rate_plans)==1)
		{
			$hotel_rate_plan= $cmOtaRatePlanSynchronizeModel->getRoomRatePlan($ota_id,$rate_plan);
		}
		else
		{
			if($key==0){
				$hotel_rate_plan.= $cmOtaRatePlanSynchronizeModel->getRoomRatePlan($ota_id,$rate_plan);
			}
			else{
				$hotel_rate_plan.=','.$cmOtaRatePlanSynchronizeModel->getRoomRatePlan($ota_id,$rate_plan);
			}
		}
	}
	$hotel=HotelInformation::where('hotel_id',$hotel_id)->select('hotel_name','email_id','hotel_address','city_id','state_id')->first();
	$city_name=City::where('city_id',$hotel->city_id)->first();
	$state_name=State::where('state_id',$hotel->state_id)->first();
	$hotel_name=$hotel->hotel_name;
	$email_id=explode(',',$hotel->email_id);
	if($email_id)
	{
		$s=sizeof($email_id);
		if($s>1)
		{
			$email_id1=$email_id[1];
			$email_id=$email_id[0];
		}
		else if($s==1)
		{
			$email_id=$email_id[0];
			$email_id1="";
		}
		else
		{
			$email_id='channelmanager@5elements.co.in';
			$email_id1="";
		}
	}
	if($bucket_booking_status=="Commit")
	{
		$subject="Booking Confirmation Mail from ".$channel_name." BookingId: ".$ota_booking_data->unique_id;
		$bucket_booking_status="Confirmed";
	}
	else if($bucket_booking_status=="Cancel")
	{
		$subject="Booking Cancellation Mail from ".$channel_name." BookingId: ".$ota_booking_data->unique_id;
		$bucket_booking_status="Cancelled";
	}
	else if($bucket_booking_status=="Modify")
	{
		$subject="Booking Modification Mail from ".$channel_name." BookingId: ".$ota_booking_data->unique_id;
		$bucket_booking_status="Modified";
	}
	$template=array("ota_name"=>$channel_name,"hotel_name"=>$hotel->hotel_name,"hotel_address"=>$hotel->hotel_address,"city_name"=>$city_name->city_name,"booking_id"=>$ota_booking_data->unique_id,"check_in"=>$ota_booking_data->checkin_at,"check_out"=>$ota_booking_data->checkout_at,'booking_date'=>$ota_booking_data->booking_date,"room_type"=>$hotel_room_type,"rate_plan"=>$hotel_rate_plan,"rooms"=>$rooms,"total_amount"=>$ota_booking_data->amount,"customer_details"=>$customer_name,"booking_status"=>$bucket_booking_status,"payment_status"=>$ota_booking_data->payment_status,"number_of_nights"=>$number_of_nights,"state_name"=>$state_name->state_name,"customer_number"=>$customer_number,"tax_amount"=>$tax_amount,"inclusion"=>$inclusion,
	"special_info"=>$special_info,"cancel_policy"=>$cancel_policy,
	"no_of_adult"=>$guest,"no_of_child"=>$guest1,"customer_email"=>$customer_email,
	"currency"=>$currency);
	if($email_id)
	{
		$this->sendMail1($email_id,$template,$subject,$hotel->hotel_name, $email_id1);
	}
}
public function sendMail1($hotel_email,$template, $subject,$hotel_name, $email_id1)
{
	if($email_id1!="")
	{
		$mail_array=['channelmanager@5elements.co.in', $email_id1];
	}
	else
	{
	  $mail_array=['channelmanager@5elements.co.in'];
	}
	$data = array('hotel_email' =>$hotel_email,'subject'=>$subject,'mail_array'=>$mail_array);

	$data['template']=$template;
	$data['hotel_name']=$hotel_name;

	Mail::send(['html' => 'emails.otaBookingTemplate1'],$template,function ($message) use ($data)
{
	$message->to($data['hotel_email'])
	->bcc($data['mail_array'])
	->from( env("MAIL_FROM"))
	->subject( $data['subject']);
	});
	if(Mail::failures())
	{
			return false;
	}
	return true;

}
}
