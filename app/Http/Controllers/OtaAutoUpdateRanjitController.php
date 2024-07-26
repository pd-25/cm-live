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
use App\CompanyDetails;
use App\CmBookingConfirmationResponse;
use App\BookingLog;
use App\LogTable;
use App\Inventory;
use App\CmOtaBooking;
use App\HotelInformation;
use App\City;
use App\State;
use App\OtaInventory;
use App\AirbnbAccessToken;
use App\PmsAccount;
use App\DynamicPricingBucket;
use DB;
use App\AirbnbListingDetails;
use App\Http\Controllers\UpdateInventoryService;
use App\Http\Controllers\CmOtaBookingInvStatusService;
use App\Http\Controllers\IdsController;
use App\Http\Controllers\IpAddressService;
use App\Http\Controllers\otacontrollers\CmBookingDataInsertionController;
use App\Http\Controllers\otacontrollers\BookOnePmsDataInsertionController;
use App\Http\Controllers\TTDCMMW\TTDCReservationController;
use App\Http\Controllers\WinHms\WinhmsController;

class OtaAutoUpdateRanjitController extends Controller{
		protected $inventoryService,$ttdcService,$winhmscontroller;
		private $updateInvService,$bookOnePmsDataInsertion;
		protected $idsService,$ipService,$cmBookingDataInsertion,$ktdcService;
    public function __construct(CmOtaBookingInvStatusService $cmOtaBookingInvStatusService,IdsController $idsService,IpAddressService $ipService,CmBookingDataInsertionController $cmBookingDataInsertion,KtdcController $ktdcService,BookOnePmsDataInsertionController $bookOnePmsDataInsertion,TTDCReservationController $ttdcService,WinhmsController $winhmscontroller)
    {
			   $this->cmOtaBookingInvStatusService=$cmOtaBookingInvStatusService;
			   $this->idsService=$idsService;
			   $this->ktdcService = $ktdcService;
			   $this->ipService=$ipService;
			   $this->cmBookingDataInsertion=$cmBookingDataInsertion;
			   $this->bookOnePmsDataInsertion=$bookOnePmsDataInsertion;
			   $this->ttdcService = $ttdcService;
			   $this->winhmscontroller=$winhmscontroller;
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
		$modify_status					= $bucket_data['bucket_booking_modify_status'];

		/*------------------ Get Booking Data--------------------*/
		$booking_ota_id                 = $booking_data['booking_ota_id'];
		$booking_hotel_id             	= $booking_data['booking_hotel_id'];
		$booking_room_type            	= $booking_data['booking_room_type'];
		$booking_rooms_qty            	= $booking_data['booking_rooms_qty'];
		$booking_checkin_at           	= $booking_data['booking_checkin_at'];
		$booking_checkout_at 			= $booking_data['booking_checkout_at'];

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
		$get_dp_status 			= HotelInformation::select('is_dp')->where('hotel_id',$bucket_hotel_id)->first();
		if($get_dp_status->is_dp){
			$update_booking = $this->dynamicPricingUpdate($booking_hotel_id,$booking_room_type,$booking_rooms_qty,$booking_checkin_at,$booking_checkout_at);
		}
		if($booking_data['booking_source'] == 'ota' && $modify_status == 0){
			$is_ids = PmsAccount::where('name','IDS NEXT')->whereRaw('FIND_IN_SET('.$bucket_hotel_id.',hotels)')->first();
			if($is_ids){
				$this->handleIds($bucket_data,$booking_data);
			}
			$is_ktdc = PmsAccount::where('name','KTDC')->whereRaw('FIND_IN_SET('.$bucket_hotel_id.',hotels)')->first();
			if($is_ktdc){
				$this->handleKtdc($bucket_data,$booking_data,$booking_data['booking_channel']);
			}
			$is_ttdc = PmsAccount::where('name','TTDC')->whereRaw('FIND_IN_SET('.$bucket_hotel_id.',hotels)')->first();
			if($is_ttdc){
				$this->handleTtdc($bucket_data,$booking_data,$booking_data['booking_channel']);
			}
			$is_winhms = PmsAccount::where('name','WINHMS')->whereRaw('FIND_IN_SET('.$bucket_hotel_id.',hotels)')->first();
			if($is_winhms){
				$this->handleWinhms($bucket_data,$booking_data);
			}
			$is_gems = PmsAccount::where('name','GEMS')->whereRaw('FIND_IN_SET('.$bucket_hotel_id.',hotels)')->first();
			if($is_gems){
				$this->cmBookingDataInsertion->cmBookingDataInsertion($booking_hotel_id,$bucket_ota_booking_tabel_id);
			}
			$is_bookone = PmsAccount::where('name','BookOne')->whereRaw('FIND_IN_SET('.$bucket_hotel_id.',hotels)')->first();
			if($is_bookone){
				$this->bookOnePmsDataInsertion->cmBookingDataInsertionToBookingOnePms($booking_hotel_id,$bucket_ota_booking_tabel_id);
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
					try{
						// $update_be = DB::connection('be')->table('inventory_table')->insert($data);
					}
					catch(Exception $e){
						return true;
					}
				}
				$log_response_msg=$log_response_msg.json_encode($data);
			}
		} // Commit Closed here.
			if( $bucket_booking_status == 'Cancel'){
				for($i=0; $i<count($inventoryData); $i++)
				{	if($inventoryData[$i]->block_status==0 && $inventoryData[$i]->no_of_rooms > 0)
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
							try{
								// $update_be = DB::connection('be')->table('inventory_table')->insert($data);
							}
							catch(Exception $e){
								 return true;
							}
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
		if($booking_data['booking_source'] == 'ota' && $modify_status == 0){
		// $this->mailHandler1($bucket_ota_booking_tabel_id,$bucket_booking_status);
		}
		DB::table('booking_logs')->where('id', $logModel->id)
		->update(['status' =>1,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg]);

		return true;
}

/*------------------- Cleartrip Update Function Start------------------------*/
public function cleartripUpdate($bucket_data,$booking_data)
{
    return true;
	$log_request_msg="";
	$log_response_msg="";
	$cmOtaDetailsModel  			= new CmOtaDetails();
	$logModel                       = new BookingLog();
	$cmOtaRoomTypeSynchronizeModel  = new CmOtaRoomTypeSynchronize();
	$cmOtaRatePlanSynchronizeModel 	= new CmOtaRatePlanSynchronize();


	/*------------------ Get Bucket Data--------------------*/
	$bucket_id                      = $bucket_data['bucket_id'];
	$bucket_hotel_id                = $bucket_data['bucket_hotel_id'];
	$bucket_ota_id                  = $bucket_data['bucket_ota_id'];
	$bucket_ota_name                = $bucket_data['bucket_ota_name'];
	$bucket_ota_booking_tabel_id    = $bucket_data['bucket_ota_booking_tabel_id'];
	$bucket_ota_hotel_code          = $bucket_data['bucket_ota_hotel_code'];
	$bucket_booking_status          =
	$bucket_data['bucket_booking_status'];


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
	$api_key            			= trim($auth_parameter->api_key);
	$commonUrl      				= $ota_details_data->url;

	/*------------------ set header ------------------ */
	$headers = array (
	//Regulates versioning of tbooking_rooms_qtyhe XML interface for the API
	'Content-Type: application/xml',
	'X-CT-SOURCETYPE: API',
	'X-CT-API-KEY: '.$api_key,
	);
	$room_types 					= explode(",", $booking_room_type);
	$booking_ip						=	'1.1.1.1';

	foreach($room_types as $key => $room_type)
	{

		$room_type 					= $cmOtaRoomTypeSynchronizeModel->getSingleHotelRoomIdFromRoomSynch($room_type,$bucket_hotel_id);
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

		$inventoryData=$this->cmOtaBookingInvStatusService->getInventoryDetailsByRoomType($bucket_ota_booking_tabel_id,$room_type,$bucket_hotel_id,$bucket_ota_id);//	0 => No of days prior to the booking
		$Flagchecker=0;
		$url="";
		if($inventoryData)
		{
		if( $bucket_booking_status == 'Commit'|| $bucket_booking_status == 'Modify')
		{
			for($i=0; $i<count($inventoryData); $i++)
			{
				$otainventory                   = new OtaInventory();
				if($inventoryData[$i]->block_status==0)
				{
					if(is_object($inventoryData[$i]->date)){
						foreach($inventoryData[$i]->date as $info){
							$stDate      	= date('d/m/Y', strtotime($info));
							$edDate      	= date('d/m/Y', strtotime($info));
							$stDate1      	= date('Y-m-d', strtotime($info));
							$edDate1      	= date('Y-m-d', strtotime($info));
						}
					}
					else{
						$stDate      	= date('d/m/Y', strtotime($inventoryData[$i]->date));
						$edDate      	= date('d/m/Y', strtotime($inventoryData[$i]->date));
						$stDate1      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
						$edDate1      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
					}
					$room_quanty = $inventoryData[$i]->update_to;

					if($room_quanty >= 0){
						$xml ='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
					<hotel-inventory xmlns="http://www.cleartrip.com/extranet/hotel-inventory" type="update">
					<hotel-id>'.$ota_hotel_code.'</hotel-id>
					<room-type-id>'.$room_code.'</room-type-id>
					<room-inventories>
					<room-inventory>
					<from-date>'.$stDate.'</from-date>
					<to-date>'.$edDate.'</to-date>
					<applicable-days>ALL</applicable-days>
					<inventory>'.$room_quanty.'</inventory>
					<release-hours>24</release-hours>
					</room-inventory>
					</room-inventories>
					</hotel-inventory>';
					$log_request_msg .= $xml;
					$url = $commonUrl.'push-inventory';

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
					$status =$ary_data['status']['code'];
						if (substr($status, 0, 1) === 'S') {
							$Flagchecker = 1;
						}
						else{
							$Flagchecker = 0;
						}
					} // $room_quanty >= 0
				}
			} // for $inventoryData closed here.

			if($Flagchecker == 1){
				DB::table('booking_logs')->where('id', $logModel->id)
				->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);

			}else{
				DB::table('booking_logs')->where('id', $logModel->id)
				->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);

			}

		} // If $bucket_booking_status == 'Commit' close here.

		if( $bucket_booking_status == 'Cancel')
		{
			for($i=0; $i<count($inventoryData); $i++)
			{
				$otainventory                   = new OtaInventory();
				if($inventoryData[$i]->block_status==0 && $inventoryData[$i]->no_of_rooms > 0)
				{
					if(is_object($inventoryData[$i]->date)){
						foreach($inventoryData[$i]->date as $info){
							$stDate      	= date('d/m/Y', strtotime($info));
							$edDate      	= date('d/m/Y', strtotime($info));
							$stDate1      	= date('Y-m-d', strtotime($info));
							$edDate1      	= date('Y-m-d', strtotime($info));
						}
					}
					else{
						$stDate      	= date('d/m/Y', strtotime($inventoryData[$i]->date));
						$edDate      	= date('d/m/Y', strtotime($inventoryData[$i]->date));
						$stDate1      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
						$edDate1      	= date('Y-m-d', strtotime($inventoryData[$i]->date));
					}
					$room_quanty = $inventoryData[$i]->update_to;

					if($room_quanty >= 0)
					{
					$xml ='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
					<hotel-inventory xmlns="http://www.cleartrip.com/extranet/hotel-inventory" type="update">
					<hotel-id>'.$ota_hotel_code.'</hotel-id>
					<room-type-id>'.$room_code.'</room-type-id>
					<room-inventories>
					<room-inventory>
					<from-date>'.$stDate.'</from-date>
					<to-date>'.$edDate.'</to-date>
					<applicable-days>ALL</applicable-days>
					<inventory>'.$room_quanty.'</inventory>
					<release-hours>24</release-hours>
					</room-inventory>
					</room-inventories>
					</hotel-inventory>';
					$log_request_msg = $log_request_msg.$xml;
					$url = $commonUrl.'push-inventory';

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
					$status =$ary_data['status']['code'];
					if (substr($status, 0, 1) === 'S') {
						$Flagchecker = 1;
					}
					else
					{
						$Flagchecker = 0;
					}
					} // $room_quanty >= 0
					else
					{
						$log_response_msg = $xml;
					}
				}
			} //  for $inventoryData closed here.] closed here.
			if($Flagchecker == 1){
				DB::table('booking_logs')->where('id', $logModel->id)
				->update(['status' => 1,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);

			}else{
				DB::table('booking_logs')->where('id', $logModel->id)
				->update(['status' => 0,'request_msg'=>$log_request_msg,'response_msg'=>$log_response_msg,'request_url'=>$url]);

			}
		} // If $bucket_booking_status == 'Cancel' close here.
		}//If $inventoryData closed here
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
	return true;
}
/*------------------- Cleartrip Update Function Close------------------------*/
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
		if( $bucket_booking_status == 'Commit' || $bucket_booking_status == 'Modify'){
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
					$today_date = date('Y-m-d');
					if($startDate < $today_date){
						continue;
					}
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
				if($inventoryData[$i]->block_status==0 && $inventoryData[$i]->no_of_rooms > 0)
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
					$today_date = date('Y-m-d');
					if($startDate < $today_date){
						continue;
					}
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
		if($bucket_booking_status == 'Commit' || $bucket_booking_status == 'Modify'){
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
					$today_date = date('Y-m-d');
					if($startDate < $today_date){
						continue;
					}
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
						$word = "<TITLE>Error</TITLE>";
						if(strpos($ota_rlt, $word)){
							$Flagchecker = 0;
						}
						else{
							$ary_data = json_decode(json_encode(simplexml_load_string($ota_rlt)), true);
							if(!isset($ary_data['Error'])){
								$Flagchecker = 1;
							}else{
								$Flagchecker = 0;
							}
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
					$today_date = date('Y-m-d');
					if($startDate < $today_date){
						continue;
					}
					if($room_quanty >= 0)
					{

						$xml ='<?xml version="1.0" encoding="UTF-8" ?>
					<AvailRateUpdateRQ hotelCode="'.$ota_hotel_code.'" timeStamp="">
					<AvailRateUpdate locatorID="1">
						<DateRange from="'.$startDate.'" to="'.$endDate.'"/>
						<Availability code="'.$room_code.'" count="'.$room_quanty.'" closed="false" />
					</AvailRateUpdate>
					</AvailRateUpdateRQ>';
						$log_request_msg = $log_request_msg.$xml;

						$url = "https://partners-connect.goibibo.com/api/chm/v3/ari";

						$ch  = curl_init();
						curl_setopt( $ch, CURLOPT_URL, $url );
						curl_setopt( $ch, CURLOPT_POST, true );
						curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
						curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
						curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml);
						$ota_rlt = curl_exec($ch);
						curl_close($ch);
						$log_response_msg = $log_response_msg.$ota_rlt;
						$word = "<TITLE>Error</TITLE>";
						if(strpos($ota_rlt, $word)){
							$Flagchecker = 0;
						}
						else{
							$ary_data = json_decode(json_encode(simplexml_load_string($ota_rlt)), true);
							if(!isset($ary_data['Error'])){
								$Flagchecker = 1;
							}else{
								$Flagchecker = 0;
							}
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
		if($room_code){
			$inventoryData=$this->cmOtaBookingInvStatusService->getInventoryDetailsByRoomType($bucket_ota_booking_tabel_id,$room_type,$bucket_hotel_id,$bucket_ota_id);//	0 => No of days prior to the booking
			$Flagchecker = 0;
			$url="";
			if($inventoryData)
			{
				if($bucket_booking_status == 'Commit' || $bucket_booking_status == 'Modify'){
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
							$today_date = date('Y-m-d');
							if($startDate < $today_date){
								continue;
							}
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
					if($inventoryData[$i]->block_status==0 && $inventoryData[$i]->no_of_rooms > 0)
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
						$today_date = date('Y-m-d');
						if($startDate < $today_date){
							continue;
						}
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
							$result = curl_error($ch);
							curl_close($ch);

							$log_response_msg = $log_response_msg.$result;
							$array_data = json_decode(json_encode(simplexml_load_string($result)), true);

							if(!isset($array_data['Error'])){
								$Flagchecker = 1;
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
		->where('ota_type_id', '=' ,$bucket_ota_id)
    	->where('hotel_id',$bucket_hotel_id)
		->orderBy('id','DESC')
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
		if($bucket_booking_status == 'Commit' || $bucket_booking_status == 'Modify'){

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
					$today_date = date('Y-m-d');
					if($startDate < $today_date){
						continue;
					}
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
					if(simplexml_load_string($result)){
						$array_data = json_decode(json_encode(simplexml_load_string($result)), true);
					}
					else{
						$word = "<ok>";
						if(strpos($result, $word) === false){
							$array_data['error'] = 'error';
						}
						else{
							$array_data = "<ok>";
						}
					}
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
						$today_date = date('Y-m-d');
						if($startDate < $today_date){
							continue;
						}
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
							if(simplexml_load_string($result)){
								$array_data = json_decode(json_encode(simplexml_load_string($result)), true);
							}
							else{
								$word = "<ok>";
								if(strpos($result, $word) === false){
									$array_data['error'] = 'error';
								}
								else{
									$array_data = "<ok>";
								}
							}
							 
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
		if($bucket_booking_status == 'Commit' || $bucket_booking_status == 'Modify'){

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
					$today_date = date('Y-m-d');
					if($startDate < $today_date){
						continue;
					}
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
					$today_date = date('Y-m-d');
					if($startDate < $today_date){
						continue;
					}
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
		if($bucket_booking_status == 'Commit' || $bucket_booking_status == 'Modify')
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
					$today_date = date('Y-m-d');
					if($startDate < $today_date){
						continue;
					}
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
				if($inventoryData[$i]->block_status==0 && $inventoryData[$i]->no_of_rooms > 0)
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
					$today_date = date('Y-m-d');
					if($startDate < $today_date){
						continue;
					}
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
			if($bucket_booking_status == 'Commit' || $bucket_booking_status == 'Modify')
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
						$today_date = date('Y-m-d');
						if($startDate < $today_date){
							continue;
						}

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
					if($inventoryData[$i]->block_status==0 && $inventoryData[$i]->no_of_rooms > 0)
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
						$today_date = date('Y-m-d');
						if($startDate < $today_date){
							continue;
						}
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
				if($bucket_booking_status == 'Commit' || $bucket_booking_status == 'Modify' )
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
							$today_date = date('Y-m-d');
							if($startDate < $today_date){
								continue;
							}
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
						if($inventoryData[$i]->block_status==0 && $inventoryData[$i]->no_of_rooms > 0)
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
							$today_date = date('Y-m-d');
							if($startDate < $today_date){
								continue;
							}
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
					$array_data = array();
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
					if($bucket_booking_status == 'Commit' || $bucket_booking_status == 'Modify')
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
								$today_date = date('Y-m-d');
								if($startDate < $today_date){
									continue;
								}
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
						if(isset($array_data["Status"]) && $array_data["Status"] =="Success"){
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
							if($inventoryData[$i]->block_status==0 && $inventoryData[$i]->no_of_rooms > 0)
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
								$today_date = date('Y-m-d');
								if($startDate < $today_date){
									continue;
								}
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
			if($bucket_booking_status == 'Commit' || $bucket_booking_status == 'Modify')
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
						$today_date = date('Y-m-d');
						if($startDate < $today_date){
							continue;
						}
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
					if($inventoryData[$i]->block_status==0 && $inventoryData[$i]->no_of_rooms > 0)
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
						$today_date = date('Y-m-d');
						if($startDate < $today_date){
							continue;
						}
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

/*------------------- Airbnb Update Function Start------------------------*/
public function airbnbUpdate($bucket_data,$booking_data)
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
		$commonUrl      				= $ota_details_data->url;
		$getCompany_id					= HotelInformation::select('company_id')->where('hotel_id',$bucket_data['bucket_hotel_id'])->first();
		$company                        = new CompanyDetails();
        $comp_details                   = $company->where('company_id',$getCompany_id->company_id)->select('airbnb_refresh_token')->first();
        $refresh_token                  = $comp_details->airbnb_refresh_token;
		$airbnbModel                    = new AirbnbListingDetails();
		$oauth_Token                    = $this->getAirbnbToken($bucket_hotel_id);
		if($oauth_Token == 0){
			return true;
		}
        if($bucket_hotel_id == 1953){
            $api_key = '28nb6aej5cji9vsnqbh22di8y';
        }
        else{
            $api_key = trim($auth_parameter->X_Airbnb_API_Key);
        }
		/*------------------ set header ------------------ */
		$headers = array('Content-Type:application/json', 'Expect:');

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
		if($bucket_booking_status == 'Commit' || $bucket_booking_status == 'Modify'){

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
					$today_date = date('Y-m-d');
					if($startDate < $today_date){
						continue;
					}
					if($room_quanty >= 0){

					$post_data=array();
					$post_data['listing_id']=$room_code;
					if($room_quanty <= 0){
						$operations=array();
						$operations['dates']=array($startDate .":".$endDate );
						$operations['availability']="unavailable";
						$post_data['operations']=array($operations);
					}
					else{
						$operations=array();
						$operations['dates']=array($startDate .":".$endDate );
						$operations['availability']="available";
						$operations['available_count']=$room_quanty;
						$post_data['operations']=array($operations);
					}
                    $post_data=json_encode($post_data);
                    $log_request_msg=$post_data;

                    $url=$commonUrl."/calendar_operations?_allow_dates_overlap=true";

                    $headers = array();
                    $headers[] = "X-Airbnb-Api-Key: $api_key";
                    $headers[] = "X-Airbnb-Oauth-Token: $oauth_Token";
                    $headers[] = "Content-Type: application/json";
					$ch = curl_init();
					curl_setopt( $ch, CURLOPT_URL, $url );
					curl_setopt( $ch, CURLOPT_POST, true );
					curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
					curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
					curl_setopt( $ch, CURLOPT_POSTFIELDS, $post_data);
					$ota_rlt = curl_exec($ch);
					curl_close($ch);
					$log_response_msg = $log_response_msg.$ota_rlt;
				    $array_data = json_decode($ota_rlt, true);
					if(!isset($array_data['Error'])){
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
				if($inventoryData[$i]->block_status==0 && $inventoryData[$i]->no_of_rooms > 0)
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
					$today_date = date('Y-m-d');
					if($startDate < $today_date){
						continue;
					}
					if($room_quanty >= 0)
					{

						$post_data=array();
						$post_data['listing_id']=$room_code;
						$operations=array();
						$operations['dates']=array($startDate .":".$endDate );
						$operations['availability']="available";
						$operations['available_count']=$room_quanty;
						$post_data['operations']=array($operations);

						$post_data=json_encode($post_data);
						$log_request_msg=$post_data;

						$url=$commonUrl."/calendar_operations?_allow_dates_overlap=true";
						
						$headers = array();
						$headers[] = "X-Airbnb-Api-Key: $api_key";
						$headers[] = "X-Airbnb-Oauth-Token: $oauth_Token";
						$headers[] = "Content-Type: application/json";

						$ch  = curl_init();
						curl_setopt( $ch, CURLOPT_URL, $url );
						curl_setopt( $ch, CURLOPT_POST, true );
						curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
						curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
						curl_setopt( $ch, CURLOPT_POSTFIELDS, $post_data);
						$ota_rlt = curl_exec($ch);
						curl_close($ch);
						$log_response_msg = $log_response_msg.$ota_rlt;
						$array_data = json_decode($ota_rlt, true);

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
/*------------------- Airbnb Update Function End------------------------*/


/*--------------------IRCTC Function Start----------------------------------------------*/
public function irctcUpdate($bucket_data,$booking_data)
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
			$password    					= trim($auth_parameter->password);
            $username    					= trim($auth_parameter->username);
			$commonUrl      				= $ota_details_data->url;
			
			/*------------------ set header ------------------ */
			$headers = array (
			//Regulates versioning of the XML interface for the API
			'Content-Type: application/json'
			);


			$room_types 				= explode(",", $booking_room_type);
			$booking_ip					=	'1.1.1.1';
			$time_stamp = date("Y-m-d h:i:s");

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
			if($bucket_booking_status == 'Commit' || $bucket_booking_status == 'Modify')
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
						$today_date = date('Y-m-d');
						if($startDate < $today_date){
							continue;
						}

						if($room_quanty >= 0)
						{
							$xml = '{
								"OTA_HotelInvCountNotifRQ": 
								{
									"EchoToken": "abc13dd23",
									"TimeStamp": '.'"'.$time_stamp.'"'.',
									"Target": "Production",
									"Version": "",
									"HotelCode": '.'"'.$ota_hotel_code.'"'.',
									"POS": {
										"RequestorID": {
											"Password": '.'"'.$password.'"'.',
											"User": '.'"'.$username.'"'.',
											"ID_Context": "CKLive"
										}
									},
									"Inventories": [{
										"StatusApplicationControl": {
											"Start": "'.$startDate.'",
											"End": "'.$endDate.'",
											"InvTypeCode": "'.$room_code.'"
										},
										"InvCounts": {
											"Days": [{
												"Mon": "True",
												"Tue": "True",
												"Weds": "True",
												"Thur": "True",
												"Fri": "True",
												"Sat": "True",
												"Sun": "True"
											}],
											"InvCount": '.$room_quanty.',
											"CutOff": "3",
											"StopSell": "False"
										}
									}]
								}
							}';

							$log_request_msg =$log_request_msg.$xml;

							$url                    = $commonUrl.'/update-inv';

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
						$today_date = date('Y-m-d');
						if($startDate < $today_date){
							continue;
						}
						if($room_quanty >= 0)
						{
							$xml = '{
								"OTA_HotelInvCountNotifRQ": 
								{
									"EchoToken": "abc13dd23",
									"TimeStamp": '.'"'.$time_stamp.'"'.',
									"Target": "Production",
									"Version": "",
									"HotelCode": '.'"'.$ota_hotel_code.'"'.',
									"POS": {
										"RequestorID": {
											"Password": '.'"'.$password.'"'.',
											"User": '.'"'.$username.'"'.',
											"ID_Context": "CKLive"
										}
									},
									"Inventories": [{
										"StatusApplicationControl": {
											"Start": "'.$startDate.'",
											"End": "'.$endDate.'",
											"InvTypeCode": "'.$room_code.'"
										},
										"InvCounts": {
											"Days": [{
												"Mon": "True",
												"Tue": "True",
												"Weds": "True",
												"Thur": "True",
												"Fri": "True",
												"Sat": "True",
												"Sun": "True"
											}],
											"InvCount": '.$room_quanty.',
											"CutOff": "3",
											"StopSell": "False"
										}
									}]
								}
							}';

							$log_request_msg =$log_request_msg.$xml;

							$url  = $commonUrl.'/update-inv';

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
/*------------------------IRCTC Function Close-----------------------------------------*/

/*--------------------Akbar Function Start----------------------------------------------*/
public function akbarUpdate($bucket_data,$booking_data)
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
			$client_token    = trim($auth_parameter->client_token);
            $access_token    = trim($auth_parameter->access_token);
            $product_token   = trim($auth_parameter->product_token);
			$commonUrl      				= $ota_details_data->url;
			
			/*------------------ set header ------------------ */
			$headers = array (
			//Regulates versioning of the XML interface for the API
			'Content-Type: application/json'
			);


			$room_types 				= explode(",", $booking_room_type);
			$booking_ip					=	'1.1.1.1';
			$time_stamp = date("Y-m-d h:i:s");

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
			if($bucket_booking_status == 'Commit' || $bucket_booking_status == 'Modify')
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
						$today_date = date('Y-m-d');
						if($startDate < $today_date){
							continue;
						}

						if($room_quanty >= 0)
						{
							$xml = '{
								"hobse": {
									"version": "1.0",
									"datetime": '.'"'.$time_stamp.'"'.',
									"clientToken": '.'"'.$client_token.'"'.',
									"accessToken": '.'"'.$access_token.'"'.',
									"productToken": '.'"'.$product_token.'"'.',
									"request": {
										"method": "/htl/UpdateInventory",
										"data": {
											"hotelId": '.'"'.$ota_hotel_code.'"'.',
											"resultType": "json",
											"invData": [
												{
													"roomCode": '.'"'.$room_code.'"'.',
													"noOfRooms": '.'"'.$room_quanty.'"'.',
													"fromDate": '.'"'.$startDate.'"'.',
													"toDate": '.'"'.$endDate.'"'.'
												}
											]
										} 
								}}}';
							$log_request_msg =$log_request_msg.$xml;
							$url                    = $commonUrl.'/UpdateInventory';
							$akbar_xml = array('params'=>$xml);
							$curl = curl_init();
							curl_setopt_array($curl, array(
								CURLOPT_URL => 'https://api.hobse.com/v1/htl/UpdateInventory',
								CURLOPT_RETURNTRANSFER => true,
								CURLOPT_POSTFIELDS => $akbar_xml,
							));
							$result = curl_exec($curl);
							curl_close($curl);
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
						$today_date = date('Y-m-d');
						if($startDate < $today_date){
							continue;
						}
						if($room_quanty >= 0)
						{
							$xml = '{
								"hobse": {
									"version": "1.0",
									"datetime": '.'"'.$time_stamp.'"'.',
									"clientToken": '.'"'.$client_token.'"'.',
									"accessToken": '.'"'.$access_token.'"'.',
									"productToken": '.'"'.$product_token.'"'.',
									"request": {
										"method": "/htl/UpdateInventory",
										"data": {
											"hotelId": '.'"'.$ota_hotel_code.'"'.',
											"resultType": "json",
											"invData": [
												{
													"roomCode": '.'"'.$room_code.'"'.',
													"noOfRooms": '.'"'.$room_quanty.'"'.',
													"fromDate": '.'"'.$startDate.'"'.',
													"toDate": '.'"'.$endDate.'"'.'
												}
											]
										} 
								}}}';
							$log_request_msg =$log_request_msg.$xml;
							$url                    = $commonUrl.'/UpdateInventory';
							$akbar_xml = array('params'=>$xml);
							$curl = curl_init();
							curl_setopt_array($curl, array(
								CURLOPT_URL => 'https://api.hobse.com/v1/htl/UpdateInventory',
								CURLOPT_RETURNTRANSFER => true,
								CURLOPT_POSTFIELDS => $akbar_xml,
							));
							$result = curl_exec($curl);
							curl_close($curl);
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
/*------------------------Akbar Function Close-----------------------------------------*/

	//-------------------IDS Integration-----------------------------//
	//HANDLE IDS UPDATE
public function testhandleIds(Request $request){

	$this->handleIds(array(),array('booking_hotel_id'=>1151));
}
public function handleIds($bucket_data,$booking_data)
{
	$booking_status=$bucket_data['bucket_booking_status'];
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
			$customer_data['last_name']=isset($name[1])?$name[1]:'NA';
			$customer_data['email_id']=$customer_details[1];
			$customer_data['mobile']=$customer_details[2];
		}
		$type=$booking_data['booking_channel'];
		$ids_string="";
		$last_ids_id=$this->idsService->idsBookings($hotel_id,$type,$ids_data,$customer_data,$booking_status);
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
public function handleKtdc($bucket_data,$booking_data,$booking_channel)
{
	$booking_status=$bucket_data['bucket_booking_status'];
	$hotel_id = $booking_data['booking_hotel_id'];
	$ktdc_status=$this->ktdcService->getKtdcStatus($hotel_id);
	$customer_data=array();
    if($ktdc_status)
    {
		$ktdc_data=$this->prepare_ktdc_data($hotel_id,$booking_data);
		$customer_details=$booking_data['booking_customer_details'];
		$customer_details=explode(',',$customer_details);
		$name=explode(' ',$customer_details[0]);
		$customer_data['first_name']=isset($name[0])?$name[0]:'';
		$customer_data['last_name']=isset($name[1])?$name[1]:'';
		$customer_data['email_id']=isset($customer_details[1])?$customer_details[1]:'';
		$customer_data['mobile']=isset($customer_details[2])?$customer_details[2]:'';
		$type=$booking_data['booking_channel'];
		$ktdc_string="";
		if($booking_status == "Cancel"){
			$lst_booking_id = $this->ktdcService->ktdcCancelBooking($ktdc_data['booking_id']);
			if($lst_booking_id){
				DB::table('ktdc_reservation')->where('id', $lst_booking_id)
								->update(['ktdc_confirm' => 1]);
			}
		}
		else{
			$last_ktdc_id=$this->ktdcService->ktdcBookings($hotel_id,$type,$ktdc_data,$customer_data,$booking_status,$booking_data["booking_checkin_at"],$booking_data["booking_checkout_at"],$booking_channel);
			if($last_ktdc_id)
			{
				DB::table('cm_ota_booking')
				->where('id',$bucket_data['bucket_ota_booking_tabel_id'])
				->update(['ktdc_re_id' => $last_ktdc_id]);
				$ktdc_string=$this->ktdcService->getKtdcString($last_ktdc_id);
				if($this->ktdcService->pushReservations($ktdc_string,$last_ktdc_id)){
					DB::table('ktdc_reservation')->where('id', $last_ktdc_id)
									->update(['ktdc_confirm' => 1]);
				}else{
					DB::table('ktdc_reservation')->where('id', $last_ktdc_id)
									->update(['ktdc_confirm' => 2]);
				}
			}
		}
        return true;
    }
    else
    {
    	return true;
    }

}
public function handleTtdc($bucket_data,$booking_data,$booking_channel)
{
	$booking_status=$bucket_data['bucket_booking_status'];
	$hotel_id = $booking_data['booking_hotel_id'];
	$ttdc_status=$this->ttdcService->getTtdcStatus($hotel_id);
	$customer_data=array();
    if($ttdc_status)
    {
		$ttdc_data=$this->prepare_ttdc_data($hotel_id,$booking_data);
		$customer_details=$booking_data['booking_customer_details'];
		$customer_details=explode(',',$customer_details);
		$name=explode(' ',$customer_details[0]);
		$customer_data['first_name']=isset($name[0])?$name[0]:'';
		$customer_data['last_name']=isset($name[1])?$name[1]:'';
		$customer_data['email_id']=isset($customer_details[1])?$customer_details[1]:'';
		$customer_data['mobile']=isset($customer_details[2])?$customer_details[2]:'';
		$type=$booking_data['booking_channel'];
		$ttdc_string="";
		if($booking_status == "Cancel"){
			$cancel_booking = $this->ttdcService->ttdcCancelBooking($ttdc_data['booking_id']);
		}else if($booking_status == "Modify"){
			$last_ttdc_details = DB::table('cm_ota_booking')->select('ttdc_re_id')->where('id',$bucket_data['bucket_ota_booking_tabel_id'])->first();
			$ttdc_re_id = $last_ttdc_details->ttdc_re_id;
			$modify_booking = $this->ttdcService->ttdcModifyBooking($hotel_id,$type,$ttdc_data,$customer_data,$booking_status,$booking_data["booking_checkin_at"],$booking_data["booking_checkout_at"],$booking_channel,$ttdc_re_id);
			$ttdc_string=$this->ttdcService->getTtdcModifiedString($ttdc_re_id);
			$modification = $this->ttdcService->pushReservations($ttdc_string,$ttdc_re_id);
		}else{
			$last_ttdc_id=$this->ttdcService->ttdcBookings($hotel_id,$type,$ttdc_data,$customer_data,$booking_status,$booking_data["booking_checkin_at"],$booking_data["booking_checkout_at"],$booking_channel);
			if($last_ttdc_id)
			{
				DB::table('cm_ota_booking')
				->where('id',$bucket_data['bucket_ota_booking_tabel_id'])
				->update(['ttdc_re_id' => $last_ttdc_id]);
				$ttdc_string=$this->ttdcService->getTtdcString($last_ttdc_id);
				if($this->ttdcService->pushReservations($ttdc_string,$last_ttdc_id)){
					DB::table('ttdc_reservation')->where('id', $last_ttdc_id)
									->update(['ttdc_confirm' => 1]);
				}else{
					DB::table('ttdc_reservation')->where('id', $last_ttdc_id)
									->update(['ttdc_confirm' => 2]);
				}
			}
		}
        return true;
    }
    else
    {
    	return true;
    }

}

public function prepare_ttdc_data($hotel_id,$ota_booking_data)
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
	$display_total_adult = 0;
	$total_children = 0;
	$booking_data['total_booking_amount']=$ota_booking_data['booking_amount'];
	$booking_data['booking_tax_amount']=$ota_booking_data['booking_tax_amount'];
	$booking_data['rate_plan_id_info'] = $cmOtaRatePlanSynchronizeModel->get_room_rate_plan($rate_code[0]);
	$no_of_nights = (int)$diff;
	$total_adult=explode(',',$ota_booking_data['total_adult']);//By default adults 2
	$total_child=explode(',',$ota_booking_data['total_child']);
	$check_room_code = 0;
	$check_rate_code = 0;
    $total_adult_info = array();
    $total_child_info = array();
    $total_adult_dlt = 0;
    $total_child_dlt = 0;
    $k=0;
    $l=0;
    foreach($room_types as $key=>$room_type)
        {
			$room_type= $cmOtaRoomTypeSynchronizeModel->getSingleHotelRoomIdFromRoomSynch($room_type,$hotel_id);
			$hotel_rate_plan_id= $cmOtaRatePlanSynchronizeModel->get_room_rate_plan($rate_code[$key]);
			if(sizeof($rate_code)>1){
				$rates_arr=array();
				if($no_of_room_types>1){
                    if($check_room_code == 0){
                        foreach($total_adult as $adult){
                            $display_total_adult+= $adult;
                        }
                        foreach($total_child as $child){
                            if($child == ""){
                                $child = 0;
                            }
							$display_total_adult+=$child;
                            $total_children+= $child;
                        }
                    }	
				}
				else{
					foreach($total_adult as $adult){
						$display_total_adult+= $adult;
					}
					foreach($total_child as $child){
						if($child == ""){
							$child = 0;
						}
						$display_total_adult+=$child;
						$total_children+= $child;
					}
				}
				if(!isset($rooms_qty[$key])){
					continue;
				}
				for($i=0;$i<$rooms_qty[$key];$i++)
				{
					if($check_room_code == 0 && $check_rate_code == 0){
						$check_rate_code = $rate_code[$key];
						$check_room_code = $room_type;
					}
					else{
						if($check_rate_code == $rate_code[$key] && $check_room_code == $room_type){
                            $check_rate_code = $rate_code[$key];
						    $check_room_code = $room_type;
							continue;
						}
                        else{
                            $check_rate_code = $rate_code[$key];
						    $check_room_code = $room_type;
                        }
					}
					$frm_date=$from_date;
					$rates_arr=array();
					for($j=1;$j<=$diff;$j++)
					{
						$amount=0;
						$d1=$frm_date;
						$d2=date('Y-m-d', strtotime($d1 . ' +1 day'));
						$amount=(($ota_booking_data['booking_amount']/$no_of_room_types/$rooms_qty[$key]/$diff));
						$gst = (($ota_booking_data['booking_tax_amount']/$no_of_room_types/$rooms_qty[$key]/$diff));
						if(strpos('.', $amount) == false)
						{
							$amount=$amount;
						}
						array_push($rates_arr,array("from_date"=>$d1,"to_date"=>$d2,'amount'=>$amount,'tax_amount'=>$gst));
						$frm_date=date('Y-m-d', strtotime($d1 . ' +1 day'));
					}
                $adult_key = $rooms_qty[$key];
                $child_key = $rooms_qty[$key];
                while($k<$adult_key){
                    $total_adult_info[] = isset($total_adult[$k])?$total_adult[$k]:0;
                    $total_adult_dlt+=isset($total_adult[$k])?(int)$total_adult[$k]:0;
                    $k++;
                }
                while($l<$child_key){
                    $total_child_info[] = isset($total_child[$l])?$total_child[$l]:0;
                    $total_child_dlt+= isset($total_child[$l])?(int)$total_child[$l]:0;
                    $l++;
                }
				$arr=array('room_type_id'=>$room_type,'rate_plan_id'=>$hotel_rate_plan_id,'adults'=>$total_adult_info,'children'=>$total_child_info,'total_adult'=>$total_adult_dlt,'total_child'=>$total_child_dlt,'from_date'=>$from_date,'to_date'=>$to_date,'rates'=>$rates_arr,'no_of_nights'=>$no_of_nights,'room_qty'=>$rooms_qty[$key]);
				array_push($booking_data['room_stay'],$arr);
				}
			}
			else{
				$rates_arr=array();
				foreach($total_adult as $adult){
					$display_total_adult+= $adult;
                    $total_adult_dlt+=(int)$adult;
				}
				foreach($total_child as $child){
					if($child == "" || $child == 'NA' ){
						$child = 0;
					}
					$display_total_adult+=$child;
					$total_children+= $child;
                    $total_child_dlt+=(int)$child;
				}
				$frm_date=$from_date;
				$rates_arr=array();
				for($j=1;$j<=$diff;$j++)
				{
					$amount=0;
					$d1=$frm_date;
					$d2=date('Y-m-d', strtotime($d1 . ' +1 day'));
					$amount=(($ota_booking_data['booking_amount']/$no_of_room_types/$rooms_qty[$key]/$diff));
					$gst = (($ota_booking_data['booking_tax_amount']/$no_of_room_types/$rooms_qty[$key]/$diff));
					if(strpos('.', $amount) == false)
					{
						$amount=$amount;
					}
					array_push($rates_arr,array("from_date"=>$d1,"to_date"=>$d2,'amount'=>$amount,'tax_amount'=>$gst));
					$frm_date=date('Y-m-d', strtotime($d1 . ' +1 day'));
				}
			$arr=array('room_type_id'=>$room_type,'rate_plan_id'=>$hotel_rate_plan_id,'adults'=>$total_adult,'children'=>$total_child,'total_adult'=>$total_adult_dlt,'total_child'=>$total_child_dlt,'from_date'=>$from_date,'to_date'=>$to_date,'rates'=>$rates_arr,'no_of_nights'=>$no_of_nights,'room_qty'=>$rooms_qty[$key]);
			array_push($booking_data['room_stay'],$arr);
			}
		}
	$booking_data['display_pax'] = $display_total_adult;
	$booking_data['display_child'] = $total_children;
    return $booking_data;
}
public function handleWinhms($bucket_data,$booking_data)
{
	$bucket_booking_status=$bucket_data['bucket_booking_status'];
	$hotel_id = $booking_data['booking_hotel_id'];
	$winhms_status=$this->winhmscontroller->getWinhmsStatus($hotel_id);
	$customer_data=array();
    if($winhms_status)
    {
		$winhms_data = $this->prepare_winhms_data($hotel_id,$booking_data);
		//echo "<pre>";print_r($winhms_data);exit;
		$customer_details = $booking_data['booking_customer_details'];
		$customer_details = explode(',',$customer_details);
		if(sizeof($customer_details) != 3){
			$name=explode(' ',$customer_details[0]);
			if(sizeof($name)>0){
				$customer_data['first_name']=(empty($name[0])) ? 'NA' : $name[0];
				$customer_data['last_name']=(empty($name[1])) ? 'NA' : $name[1];
				$customer_data['email_id']=(empty($customer_details[0])) ? 'NA' : $customer_details[0];
				$customer_data['mobile']=(empty($customer_details[1])) ? 'NA' : $customer_details[1];
			}else{
				$customer_data['first_name']='NA';
				$customer_data['last_name']='NA';
				$customer_data['email_id']=(empty($customer_details[0])) ? 'NA' : $customer_details[0];
				$customer_data['mobile']=(empty($customer_details[1])) ? 'NA' : $customer_details[1];
			}
		}else{
			$name=explode(' ',$customer_details[0]);
			$customer_data['first_name']=(empty($name[0])) ? 'NA' : $name[0];
			$customer_data['last_name']=isset($name[1])?$name[1]:'NA';
			$customer_data['email_id']=(empty($customer_details[1])) ? 'NA' : $customer_details[1];
			$customer_data['mobile']=(empty($customer_details[2])) ? 'NA' : $customer_details[2];
		}
		$type = $booking_data['booking_channel'];
		$winhms_string = "";

		if($bucket_booking_status == 'Cancel'){
			$last_winhms_id = $this->winhmscontroller->winhmsCancelBooking($hotel_id,$type,$winhms_data,$customer_data,$bucket_booking_status);
		}else{
			$last_winhms_id = $this->winhmscontroller->winhmsBookings($hotel_id,$type,$winhms_data,$customer_data,$bucket_booking_status);
		}
		//echo $last_winhms_id;exit;
		if($last_winhms_id)
        {
			DB::table('cmlive.cm_ota_booking')->where('id',$bucket_data['bucket_ota_booking_tabel_id'])->update(['winhms_re_id' => $last_winhms_id]);
			$winhms_string = $this->winhmscontroller->getWinhmsString($last_winhms_id);
			if($this->winhmscontroller->pushReservations($winhms_string,$last_winhms_id)){
				DB::table('winhms_reservation')->where('id', $last_winhms_id)->update(['winhms_confirm' => 1]);
			}else{
				DB::table('winhms_reservation')->where('id', $last_winhms_id)->update(['winhms_confirm' => 2]);
			}
        }

        return true;
    }else{
    	return true;
    }

}
public function prepare_winhms_data($hotel_id,$ota_booking_data)
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
	$display_total_adult = 0;
	$total_adult=explode(',',$ota_booking_data['total_adult']);//By default adults 2
	$k = 0;
    foreach($room_types as $key=>$room_type)
        {
			$room_type= $cmOtaRoomTypeSynchronizeModel->getSingleHotelRoomIdFromRoomSynch($room_type,$hotel_id);
			$hotel_rate_plan_id= $cmOtaRatePlanSynchronizeModel->get_room_rate_plan($rate_code[$key]);
            $rates_arr=array();
			if(!isset($rooms_qty[$key])){
				continue;
			}
            for($i=0;$i<$rooms_qty[$key];$i++,$k++)
            {
                $frm_date=$from_date;
                $rates_arr=array();
                for($j=1;$j<=$diff;$j++)
                {
                    $amount=0;
                    $d1=$frm_date;
                    $d2=date('Y-m-d', strtotime($d1 . ' +1 day'));
                    $amount=(($ota_booking_data['booking_amount']/$no_of_room_types/$rooms_qty[$key]/$diff));
					$tax_amount = (($ota_booking_data['booking_tax_amount']/$no_of_room_types)/$rooms_qty[$key])/$diff;
                    if(strpos('.', $amount) == false)
                    {
                        $amount=$amount;
                    }
                    array_push($rates_arr,array("from_date"=>$d1,"to_date"=>$d2,'amount'=>$amount,'tax_amount'=>$tax_amount));
                    $frm_date=date('Y-m-d', strtotime($d1 . ' +1 day'));
                }
            $arr=array('room_type_id'=>$room_type,'rate_plan_id'=>$hotel_rate_plan_id,'adults'=>$total_adult[$k],'from_date'=>$from_date,'to_date'=>$to_date,'rates'=>$rates_arr,'no_of_rooms'=>$rooms_qty[$key]);
			array_push($booking_data['room_stay'],$arr);
			}
		}
    return $booking_data;
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
	$display_total_adult = 0;
	$total_adult=explode(',',$ota_booking_data['total_adult']);//By default adults 2
	$k = 0;
    foreach($room_types as $key=>$room_type)
        {
			$room_type= $cmOtaRoomTypeSynchronizeModel->getSingleHotelRoomIdFromRoomSynch($room_type,$hotel_id);
			$hotel_rate_plan_id= $cmOtaRatePlanSynchronizeModel->get_room_rate_plan($rate_code[$key]);
            $rates_arr=array();
			// $total_adult=explode(',',$ota_booking_data['total_adult']);//By default adults 2
			// foreach($total_adult as $adult){
			// 	$display_total_adult= (int)$adult;
			// }
			if(!isset($rooms_qty[$key])){
				continue;
			}
            for($i=0;$i<$rooms_qty[$key];$i++,$k++)
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
            $arr=array('room_type_id'=>$room_type,'rate_plan_id'=>$hotel_rate_plan_id,'adults'=>$total_adult[$k],'from_date'=>$from_date,'to_date'=>$to_date,'rates'=>$rates_arr);
			array_push($booking_data['room_stay'],$arr);
			}
		}
    return $booking_data;
}
public function prepare_ktdc_data($hotel_id,$ota_booking_data)
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
	$display_total_adult = 0;
	$total_children = 0;
	$booking_data['total_booking_amount']=$ota_booking_data['booking_amount'];
	$booking_data['booking_tax_amount']=$ota_booking_data['booking_tax_amount'];
	$booking_data['rate_plan_id_info'] = $cmOtaRatePlanSynchronizeModel->get_room_rate_plan($rate_code[0]);
	$no_of_nights = (int)$diff;
	$total_adult=explode(',',$ota_booking_data['total_adult']);//By default adults 2
	$total_child=explode(',',$ota_booking_data['total_child']);
	$check_room_code = 0;
	$check_rate_code = 0;
    $total_adult_info = array();
    $total_child_info = array();
    $total_adult_dlt = 0;
    $total_child_dlt = 0;
    $k=0;
    $l=0;
    foreach($room_types as $key=>$room_type)
        {
			$room_type= $cmOtaRoomTypeSynchronizeModel->getSingleHotelRoomIdFromRoomSynch($room_type,$hotel_id);
			$hotel_rate_plan_id= $cmOtaRatePlanSynchronizeModel->get_room_rate_plan($rate_code[$key]);
			if(sizeof($rate_code)>1){
				$rates_arr=array();
				if($no_of_room_types>1){
                    if($check_room_code == 0){
                        foreach($total_adult as $adult){
                            $display_total_adult+= $adult;
                        }
                        foreach($total_child as $child){
                            if($child == ""){
                                $child = 0;
                            }
                            $total_children+= $child;
                        }
                    }	
				}
				else{
					foreach($total_adult as $adult){
						$display_total_adult+= $adult;
					}
					foreach($total_child as $child){
						if($child == ""){
							$child = 0;
						}
						$total_children+= $child;
					}
				}
				if(!isset($rooms_qty[$key])){
					continue;
				}
				for($i=0;$i<$rooms_qty[$key];$i++)
				{
					if($check_room_code == 0 && $check_rate_code == 0){
						$check_rate_code = $rate_code[$key];
						$check_room_code = $room_type;
					}
					else{
						if($check_rate_code == $rate_code[$key] && $check_room_code == $room_type){
                            $check_rate_code = $rate_code[$key];
						    $check_room_code = $room_type;
							continue;
						}
                        else{
                            $check_rate_code = $rate_code[$key];
						    $check_room_code = $room_type;
                        }
					}
					$frm_date=$from_date;
					$rates_arr=array();
					for($j=1;$j<=$diff;$j++)
					{
						$amount=0;
						$d1=$frm_date;
						$d2=date('Y-m-d', strtotime($d1 . ' +1 day'));
						$amount=(($ota_booking_data['booking_amount']/$no_of_room_types/$rooms_qty[$key]/$diff));
						$gst = (($ota_booking_data['booking_tax_amount']/$no_of_room_types/$rooms_qty[$key]/$diff));
						if(strpos('.', $amount) == false)
						{
							$amount=$amount;
						}
						array_push($rates_arr,array("from_date"=>$d1,"to_date"=>$d2,'amount'=>$amount,'tax_amount'=>$gst));
						$frm_date=date('Y-m-d', strtotime($d1 . ' +1 day'));
					}
                $adult_key = $rooms_qty[$key];
                $child_key = $rooms_qty[$key];
                while($k<$adult_key){
                    $total_adult_info[] = isset($total_adult[$k])?$total_adult[$k]:0;
                    $total_adult_dlt+=isset($total_adult[$k])?(int)$total_adult[$k]:0;
                    $k++;
                }
                while($l<$child_key){
                    $total_child_info[] = isset($total_child[$l])?$total_child[$l]:0;
                    $total_child_dlt+= isset($total_child[$l])?(int)$total_child[$l]:0;
                    $l++;
                }
				$arr=array('room_type_id'=>$room_type,'rate_plan_id'=>$hotel_rate_plan_id,'adults'=>$total_adult_info,'children'=>$total_child_info,'total_adult'=>$total_adult_dlt,'total_child'=>$total_child_dlt,'from_date'=>$from_date,'to_date'=>$to_date,'rates'=>$rates_arr,'no_of_nights'=>$no_of_nights,'room_qty'=>$rooms_qty[$key]);
				array_push($booking_data['room_stay'],$arr);
				}
			}
			else{
				$rates_arr=array();
				foreach($total_adult as $adult){
					$display_total_adult+= $adult;
                    $total_adult_dlt+=(int)$adult;
				}
				foreach($total_child as $child){
					if($child == "" || $child == 'NA' ){
						$child = 0;
					}
					$total_children+= $child;
                    $total_child_dlt+=(int)$child;
				}
				$frm_date=$from_date;
				$rates_arr=array();
				for($j=1;$j<=$diff;$j++)
				{
					$amount=0;
					$d1=$frm_date;
					$d2=date('Y-m-d', strtotime($d1 . ' +1 day'));
					$amount=(($ota_booking_data['booking_amount']/$no_of_room_types/$rooms_qty[$key]/$diff));
					$gst = (($ota_booking_data['booking_tax_amount']/$no_of_room_types/$rooms_qty[$key]/$diff));
					if(strpos('.', $amount) == false)
					{
						$amount=$amount;
					}
					array_push($rates_arr,array("from_date"=>$d1,"to_date"=>$d2,'amount'=>$amount,'tax_amount'=>$gst));
					$frm_date=date('Y-m-d', strtotime($d1 . ' +1 day'));
				}
			$arr=array('room_type_id'=>$room_type,'rate_plan_id'=>$hotel_rate_plan_id,'adults'=>$total_adult,'children'=>$total_child,'total_adult'=>$total_adult_dlt,'total_child'=>$total_child_dlt,'from_date'=>$from_date,'to_date'=>$to_date,'rates'=>$rates_arr,'no_of_nights'=>$no_of_nights,'room_qty'=>$rooms_qty[$key]);
			array_push($booking_data['room_stay'],$arr);
			}
		}
	$booking_data['display_pax'] = $display_total_adult;
	$booking_data['display_child'] = $total_children;
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
	if(isset($hotel->city_id)){
		$city_name=DB::table('city_table')->where('city_id',$hotel->city_id)->first();
	}
	else{
		$city_name='NA';
	}
	if(isset($hotel->city_id)){
		$state_name=DB::table('state_table')->where('state_id',$hotel->state_id)->first();
	}
	else{
		$state_name='NA';
	}
	
	$hotel_name=isset($hotel->hotel_name)?$hotel->hotel_name:'NA';
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
		$booking_status="Confirmed";
	}
	else if($bucket_booking_status=="Cancel")
	{
		$subject="Booking Cancellation Mail from ".$ota_name." BookingId: ".$ota_booking_data->unique_id;
		$booking_status="Cancelled";
	}
	else if($bucket_booking_status=="Modfiy")
	{
		$subject="Booking Modification Mail from ".$ota_name." BookingId: ".$ota_booking_data->unique_id;
		$booking_status="Modified";
	}
	$template=array("ota_name"=>$ota_name,"hotel_name"=>$hotel->hotel_name,"hotel_address"=>$hotel->hotel_address,
	"city_name"=>$city_name->city_name,
	"state_name"=>$state_name->state_name,
	"booking_id"=>$ota_booking_data->unique_id,"check_in"=>$ota_booking_data->checkin_at,
	"check_out"=>$ota_booking_data->checkout_at,"room_type"=>$hotel_room_type,"rate_plan"=>$hotel_rate_plan,"rooms"=>$rooms,
	"total_amount"=>$ota_booking_data->amount,"customer_details"=>$ota_booking_data->customer_details,"booking_status"=>$booking_status,
	"payment_status"=>$ota_booking_data->payment_status,"commision_amount"=>$commision_amount,"supplier_amount"=>$supplier_amount,"number_of_nights"=>$number_of_nights);
	if($email_id)
	{
		$this->sendMail($email_id,$template,$subject,$hotel->hotel_name, $email_id1,$hotel_id);
	}
}
/*
*Email Invoice
*@param $email for to email
*@param $template is the email template
*@param $subject for email subject
*/
public function sendMail($hotel_email,$template, $subject,$hotel_name, $email_id1,$hotel_id)
{
	if($email_id1!="")
	{
		$mail_array=['channelmanager@5elements.co.in', $email_id1];
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
	$customer_number=	isset($customer_details[2])?$customer_details[2]:'';
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
	$hotel_room_type_id="";
	$hotel_rate_plan="";
	$guest=array();
	$guest1=array();
	$rate_plan_details=array();
	foreach($rate_plans as $rates){
		if(sizeof($rate_plan_details) > 0){
			if(!in_array($rates,$rate_plan_details)){
				$rate_plan_details[]=$rates;
			}
		}
		else{
			$rate_plan_details[]=$rates;
		}
	}
	foreach($rooms as $key=>$room){
		$k=0;
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
				$sum +=(int)$no_of_adult[$k];
				$child_info = isset($no_of_child[$k])?(int)$no_of_child[$k]:0;
				$sum1 += (int)$child_info;
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
			$guest1[]=(int)$no_of_child[$k];
			$k++;
		}
	}
	foreach($room_types as $key=>$room_type)
	{
		if(sizeof($room_types)==1)
		{
			$hotel_room_type= $cmOtaRoomTypeSynchronizeModel->getRoomType($room_type,$ota_id);
			$hotel_room_type_id= $cmOtaRoomTypeSynchronizeModel->getRoomTypeID($room_type,$ota_id);
		}
		
		else
		{
			if($key==0){
				$hotel_room_type.=$cmOtaRoomTypeSynchronizeModel->getRoomType($room_type,$ota_id);
				$hotel_room_type_id.=$cmOtaRoomTypeSynchronizeModel->getRoomTypeID($room_type,$ota_id);

			}else{
				$hotel_room_type.='`'.$cmOtaRoomTypeSynchronizeModel->getRoomType($room_type,$ota_id);
				$hotel_room_type_id.='`'.$cmOtaRoomTypeSynchronizeModel->getRoomTypeID($room_type,$ota_id);
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
				$hotel_rate_plan.='`'.$cmOtaRatePlanSynchronizeModel->getRoomRatePlan($ota_id,$rate_plan);
			}
		}
	}
	$hotel=HotelInformation::where('hotel_id',$hotel_id)->select('hotel_name','email_id','hotel_address','city_id','state_id','is_dp')->first();
	if(isset($hotel->city_id)){
		$city_name=City::where('city_id',$hotel->city_id)->first();
	}
	else{
		$city_name='NA';
	}
	if(isset($hotel->city_id)){
		$state_name=State::where('state_id',$hotel->state_id)->first();
	}
	else{
		$state_name='NA';
	}
	$hotel_name=isset($hotel->hotel_name)?$hotel->hotel_name:'NA';
	if(isset($hotel->email_id)){
		$hotel->email_id = $hotel->email_id;
	}
	else{
		return true;
	}
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
		$booking_status="Confirmed";
	}
	else if($bucket_booking_status=="Cancel")
	{
		$subject="Booking Cancellation Mail from ".$channel_name." BookingId: ".$ota_booking_data->unique_id;
		$booking_status="Cancelled";
	}
	else if($bucket_booking_status=="Modify")
	{
		$subject="Booking Modification Mail from ".$channel_name." BookingId: ".$ota_booking_data->unique_id;
		$booking_status="Modified";
	}
	if($hotel->is_dp == 1){
		$rooms_info = implode(',',$rooms);
		$db_bucket = array(
			"hotel_id"=>$hotel_id,
			"room_type_id"=>$hotel_room_type_id,
			"check_in"=>$ota_booking_data->checkin_at,
			"check_out"=>$ota_booking_data->checkout_at,
			"no_of_rooms"=>$rooms_info
		);
		$insert_data_to_dp_bucket = DB::table('db_bucket')->insert($db_bucket);
	}
	$city_name_info = isset($city_name->city_name)?$city_name->city_nam:'NA';
	$state_name_info = isset($state_name->state_name)?$state_name->state_name:'NA';
	$template=array("ota_name"=>$channel_name,"hotel_name"=>$hotel->hotel_name,"hotel_address"=>$hotel->hotel_address,"city_name"=>$city_name_info,"booking_id"=>$ota_booking_data->unique_id,"check_in"=>$ota_booking_data->checkin_at,"check_out"=>$ota_booking_data->checkout_at,'booking_date'=>$ota_booking_data->booking_date,"room_type"=>$hotel_room_type,"rate_plan"=>$hotel_rate_plan,"rooms"=>$rooms,"total_amount"=>$ota_booking_data->amount,"customer_details"=>$customer_name,"booking_status"=>$booking_status,"payment_status"=>$ota_booking_data->payment_status,"number_of_nights"=>$number_of_nights,"state_name"=>$state_name_info,"customer_number"=>$customer_number,"tax_amount"=>$tax_amount,"inclusion"=>$inclusion,
	"special_info"=>$special_info,"cancel_policy"=>$cancel_policy,
	"no_of_adult"=>$guest,"no_of_child"=>$guest1,"customer_email"=>$customer_email,
	"currency"=>$currency);
	if($email_id)
	{
		$this->sendMail1($email_id,$template,$subject,$hotel->hotel_name, $email_id1,$hotel_id);
	}
}
public function sendMail1($hotel_email,$template,$subject,$hotel_name,$email_id1,$hotel_id)
{
	if($email_id1!="")
	{
		$mail_array=['channelmanager@5elements.co.in', $email_id1];
		$data = array('hotel_email' =>$hotel_email,'subject'=>$subject,'mail_array'=>$mail_array,"sec_mail"=>$email_id1);
	}
	else
	{
		$mail_array=['channelmanager@5elements.co.in'];
		$data = array('hotel_email' =>$hotel_email,'subject'=>$subject,'mail_array'=>$mail_array);
	}

	$data['template']=$template;
	$data['hotel_name']=$hotel_name;
	if($email_id1!=""){
		Mail::send(['html' => 'emails.otaBookingTemplate1'],$template,function ($message) use ($data)
		{
			$message->to($data['hotel_email'])
			->cc($data['sec_mail'])
			->bcc($data['mail_array'])
			->from( env("MAIL_FROM"))
			->subject( $data['subject']);
		});
	}
	else{
		Mail::send(['html' => 'emails.otaBookingTemplate1'],$template,function ($message) use ($data)
		{
		$message->to($data['hotel_email'])
		->bcc($data['mail_array'])
		->from( env("MAIL_FROM"))
		->subject( $data['subject']);
		});
	}
	if(Mail::failures())
	{
			return false;
	}
	return true;

}
public function getAirbnbToken($hotel_id){
	$airbnbModel=new AirbnbListingDetails();
	$getAccessToken = AirbnbAccessToken::select('*')->where('hotel_id',$hotel_id)->first();
	$today = date('Y-m-d H:i:s');
	$current_time= strtotime($today);
	if($current_time > $getAccessToken->expaire_time){
		$refresh_token = $getAccessToken->refresh_token;
		$accessTokenInfo = $airbnbModel->getAirBnbToken($refresh_token); 
		if(!isset($accessTokenInfo->access_token)){
			return 0;
		}
		$get_company_id     = HotelInformation::select('company_id')->where('hotel_id',$hotel_id)->first();
		$company_id         = $get_company_id->company_id;
		$get_airbnb_option  = CompanyDetails::select('airbnb_option')->where('company_id',$company_id)->first();
		$airbnb_option      = $get_airbnb_option->airbnb_option;
		if($airbnb_option == 1){
			$update_access_token = AirbnbAccessToken::where('company_id',$company_id)->update(['access_token'=>$accessTokenInfo->access_token,'expaire_time'=>$accessTokenInfo->expires_at]);
		}
		else{
			$update_access_token = AirbnbAccessToken::where('hotel_id',$hotel_id)->update(['access_token'=>$accessTokenInfo->access_token,'expaire_time'=>$accessTokenInfo->expires_at]);
		}
		$auth = $accessTokenInfo->access_token;
		return $auth;
	}
	else{
		$auth = $getAccessToken->access_token;
		return $auth;
	}
}
	public function dynamicPricingUpdate($booking_hotel_id,$booking_room_type,$booking_rooms_qty,$booking_checkin_at,$booking_checkout_at){
		$booking_checkin_at = date('Y-m-d',strtotime($booking_checkin_at));
		$booking_checkout_at = date('Y-m-d',strtotime($booking_checkout_at));
		$dp_booking_details = array(
			"hotel_id" => $booking_hotel_id,
			"room_type_id" => $booking_room_type,
			"check_in" => $booking_checkin_at,
			"check_out" => $booking_checkout_at,
			"no_of_rooms" => $booking_rooms_qty,
		);
		$dp_data_insertion = DynamicPricingBucket::insert($dp_booking_details);
		if($dp_data_insertion){
			return response()->json(array('status'=>1,'message'=>'booking for dynamic pricing updataed successfully'));
		}
		else{
			return response()->json(array('status'=>0,'message'=>'booking for dynamic pricing updataed fails'));
		}
	}
	public function getTtdcModifiedString($ttdc_re_id){
		$ttdc_booking_update = DB::table('ttdc_reservation')->where('ttdc_re_id', $ttdc_re_id)->select('ttdc_modify_booking_string')->first();
		$ttdc_modify_booking_string = $ttdc_booking_update['ttdc_modify_booking_string'];
		return $ttdc_modify_booking_string;
	}
	public function getTtdcRoomTypeProperty($booking_id){
		
	}
}
