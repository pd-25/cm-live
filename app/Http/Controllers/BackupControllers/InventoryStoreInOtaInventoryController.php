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
use App\OtaInventoryTest;
use DB;
use App\Http\Controllers\UpdateInventoryService;
use App\Http\Controllers\CmOtaBookingInvStatusService;
use App\Http\Controllers\IdsController;
use App\Http\Controllers\IpAddressService;
use App\Http\Controllers\otacontrollers\CmBookingDataInsertionController;

class OverBookingUpdateTestController extends Controller{
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
		$this->cmBookingDataInsertion->cmBookingDataInsertion($booking_hotel_id,$bucket_ota_booking_tabel_id);
		$booking_ip='1.1.1.1';
		foreach($room_types as $key => $room_type){
		$inventoryModel                 = new Inventory();
		$room_type 					= $cmOtaRoomTypeSynchronizeModel->getSingleHotelRoomIdFromRoomSynch($room_type,$bucket_hotel_id);

		$logModel =new BookingLog();

		if(!empty($room_type)){
		$inventoryData=$this->cmOtaBookingInvStatusService->getInventoryDetailsByRoomType($bucket_ota_booking_tabel_id,$room_type,$bucket_hotel_id,$bucket_ota_id);//	0 => No of days prior to the booking

		$serach_be_flag					= 1;

		$log_request_msg = $log_request_msg.json_encode($inventoryData);
		$log_response_msg="";
		$logInsertData=array();
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
                    $update_be = DB::connection('be')->table('inventory_table')->insert($data);
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
								$update_be = DB::connection('be')->table('inventory_table')->insert($data);
							}
							catch(Exception $e){
								 return true;
							}
					}
					$log_response_msg=$log_response_msg.json_encode($data);
				}
			} // Cancel Closed here.
		}
		}
		} // foreach $room_types closed here
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

		$room_type = $cmOtaRoomTypeSynchronizeModel->getSingleHotelRoomIdFromRoomSynch($room_type,$bucket_hotel_id);
		if(!empty($room_type)){

		$room_code=$cmOtaRoomTypeSynchronizeModel->getOtaRoomType($room_type,$bucket_ota_id);
		$url="";
		$inventoryData=$this->cmOtaBookingInvStatusService->getInventoryDetailsByRoomType($bucket_ota_booking_tabel_id,$room_type,$bucket_hotel_id,$bucket_ota_id);//	0 => No of days prior to the booking
		if($inventoryData)
		{
		if( $bucket_booking_status == 'Commit'){
			for($i=0; $i<count($inventoryData); $i++)
			{
				$otainventory                   = new OtaInventoryTest();
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
                        $multiple_days='{"Sun":"1","Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1"}';
                        $inv_data=array('hotel_id'=>$bucket_hotel_id,'room_type_id'=>$inventoryData[$i]->room_type_id,'no_of_rooms'=>$room_quanty,'date_from'=>$startDate,'date_to'=>$endDate,'channel'=>$bucket_data['bucket_ota_name'],'client_ip'=>$booking_ip,'user_id'=>0,'multiple_days'=>$multiple_days,'block_status'=>$inventoryData[$i]->block_status,'los'=>$inventoryData[$i]->los,'ota_booking_id'=>$bucket_ota_booking_tabel_id,'booking_status'=>$bucket_booking_status);
                        $otainventory->fill($inv_data)->save();
					} // empty $result.
				}
			} 
		} // If $bucket_booking_status == 'Commit' close here.

		if($bucket_booking_status == 'Cancel')
		{
			for($i=0; $i<count($inventoryData); $i++)
			{
				$otainventory                   = new OtaInventoryTest();
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

					if($room_quanty >= 0){
					
						$multiple_days='{"Sun":"1","Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1"}';
						$inv_data=array('hotel_id'=>$bucket_hotel_id,'room_type_id'=>$inventoryData[$i]->room_type_id,'no_of_rooms'=>$room_quanty,'date_from'=>$startDate,'date_to'=>$endDate,'channel'=>$bucket_data['bucket_ota_name'],'client_ip'=>$booking_ip,'user_id'=>0,'multiple_days'=>$multiple_days,'block_status'=>$inventoryData[$i]->block_status,'los'=>$inventoryData[$i]->los,'ota_booking_id'=>$bucket_ota_booking_tabel_id,'booking_status'=>$bucket_booking_status);
						$otainventory->fill($inv_data)->save();
						
					} // $room_quanty >= 0
				}
			}// for $inventoryData closed here.
			
		} // If $bucket_booking_status == 'Cancel' close here.
		}//  if $inventryData closed here.
		}
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
		$headers = array (
		//Regulates versioning of the XML interface for the API
		'Content-Type: application/xml'
		);

		$room_types 					= explode(",", $booking_room_type);
		$rooms_qty 					    = explode(",", $booking_rooms_qty);
		$booking_ip						=	'1.1.1.1';
		foreach($room_types as $key => $room_type){

		$room_type =  $cmOtaRoomTypeSynchronizeModel->getSingleHotelRoomIdFromRoomSynch($room_type,$bucket_hotel_id);
		if(!empty($room_type)){
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
				$otainventory                   = new OtaInventoryTest();
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

						$Flagchecker = 1;
						$multiple_days='{"Sun":"1","Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1"}';
						$inv_data=array('hotel_id'=>$bucket_hotel_id,'room_type_id'=>$inventoryData[$i]->room_type_id,'no_of_rooms'=>$room_quanty,'date_from'=>$startDate,'date_to'=>$endDate,'channel'=>$bucket_data['bucket_ota_name'],'client_ip'=>$booking_ip,'user_id'=>0,'multiple_days'=>$multiple_days,'block_status'=>$inventoryData[$i]->block_status,'los'=>$inventoryData[$i]->los,'ota_booking_id'=>$bucket_ota_booking_tabel_id,'booking_status'=>$bucket_booking_status);
						$otainventory->fill($inv_data)->save();
					
					} // $room_quanty >= 0
				}
			} // foreach $ota_inventry_details closed here.

		} // If $bucket_booking_status == 'Commit' close here.

		if($bucket_booking_status == 'Cancel'){
			for($i=0; $i<count($inventoryData); $i++)
			{
				$otainventory                   = new OtaInventoryTest();
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

					if($room_quanty >= 0)
					{
							$Flagchecker = 1;
							$multiple_days='{"Sun":"1","Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1"}';
							$inv_data=array('hotel_id'=>$bucket_hotel_id,'room_type_id'=>$inventoryData[$i]->room_type_id,'no_of_rooms'=>$room_quanty,'date_from'=>$startDate,'date_to'=>$endDate,'channel'=>$bucket_data['bucket_ota_name'],'client_ip'=>$booking_ip,'user_id'=>0,'multiple_days'=>$multiple_days,'block_status'=>$inventoryData[$i]->block_status,'los'=>$inventoryData[$i]->los,'ota_booking_id'=>$bucket_ota_booking_tabel_id,'booking_status'=>$bucket_booking_status);
							$otainventory->fill($inv_data)->save();
					} // $room_quanty >= 0
				}
			} // foreach $ota_inventry_details closed here.
		} // If $bucket_booking_status == 'Cancel' close here.s
		}//If $inventoryData close here
		}
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

		$room_type 					= $cmOtaRoomTypeSynchronizeModel->getSingleHotelRoomIdFromRoomSynch($room_type,$bucket_hotel_id);
		if(!empty($room_type)){
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
				$otainventory                   = new OtaInventoryTest();
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
						$Flagchecker = 1;
						$multiple_days='{"Sun":"1","Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1"}';
						$inv_data=array('hotel_id'=>$bucket_hotel_id,'room_type_id'=>$inventoryData[$i]->room_type_id,'no_of_rooms'=>$room_quanty,'date_from'=>$startDate,'date_to'=>$endDate,'channel'=>$bucket_data['bucket_ota_name'],'client_ip'=>$booking_ip,'user_id'=>0,'multiple_days'=>$multiple_days,'block_status'=>$inventoryData[$i]->block_status,'los'=>$inventoryData[$i]->los,'ota_booking_id'=>$bucket_ota_booking_tabel_id,'booking_status'=>$bucket_booking_status);
						$otainventory->fill($inv_data)->save();
				} // $room_quanty >= 0
			}
		} // foreach $ota_inventry_details closed here.
	} // If $bucket_booking_status == 'Commit' close here.
	if($bucket_booking_status == 'Cancel'){
		for($i=0; $i<count($inventoryData); $i++)
		{
			$otainventory                   = new OtaInventoryTest();
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

				if($room_quanty >= 0){
						$Flagchecker = 1;
						$multiple_days='{"Sun":"1","Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1"}';
						$inv_data=array('hotel_id'=>$bucket_hotel_id,'room_type_id'=>$inventoryData[$i]->room_type_id,'no_of_rooms'=>$room_quanty,'date_from'=>$startDate,'date_to'=>$endDate,'channel'=>$bucket_data['bucket_ota_name'],'client_ip'=>$booking_ip,'user_id'=>0,'multiple_days'=>$multiple_days,'block_status'=>$inventoryData[$i]->block_status,'los'=>$inventoryData[$i]->los,'ota_booking_id'=>$bucket_ota_booking_tabel_id,'booking_status'=>$bucket_booking_status);
						$otainventory->fill($inv_data)->save();
				} // $room_quanty >= 0
			}
		} // foreach $inventoryData closed here.
		} // If $bucket_booking_status == 'Cancel' close here.
		}
		}
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

		$room_type 				=  $cmOtaRoomTypeSynchronizeModel->getSingleHotelRoomIdFromRoomSynch($room_type,$bucket_hotel_id);
		if(!empty($room_type)){
		$room_code=$cmOtaRoomTypeSynchronizeModel->getOtaRoomType($room_type,$bucket_ota_id);

		/*******====== Get Inventory ===== ******/

		$inventoryData=$this->cmOtaBookingInvStatusService->getInventoryDetailsByRoomType($bucket_ota_booking_tabel_id,$room_type,$bucket_hotel_id,$bucket_ota_id);//	0 => No of days prior to the booking
		$ratePlanTypeSynchronizeData  = CmOtaRatePlanSynchronize::
		select('*')
		->where('ota_room_type_id', '=' ,$room_code)
		->first();
		$url= '';
		$rateplan_code = $ratePlanTypeSynchronizeData->ota_rate_plan_id;

		if($inventoryData)
		{
		if($bucket_booking_status == 'Commit'){

			for($i=0; $i<count($inventoryData); $i++)
			{
				$otainventory                   = new OtaInventoryTest();
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
						$multiple_days='{"Sun":"1","Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1"}';
						$inv_data=array('hotel_id'=>$bucket_hotel_id,'room_type_id'=>$inventoryData[$i]->room_type_id,'no_of_rooms'=>$room_quanty,'date_from'=>$startDate,'date_to'=>$endDate_inv,'channel'=>$bucket_data['bucket_ota_name'],'client_ip'=>$booking_ip,'user_id'=>0,'multiple_days'=>$multiple_days,'block_status'=>$inventoryData[$i]->block_status,'los'=>$inventoryData[$i]->los,'ota_booking_id'=>$bucket_ota_booking_tabel_id,'booking_status'=>$bucket_booking_status);
						$otainventory->fill($inv_data)->save();
				}
			} // foreach $ota_inventry_details closed here.
			} // If $bucket_booking_status == 'Commit' close here.
			if($bucket_booking_status == 'Cancel'){
				for($i=0; $i<count($inventoryData); $i++)
				{
					$otainventory                   = new OtaInventoryTest();
					if($inventoryData[$i]->block_status==0 && $inventoryData[$i]->no_of_rooms > 0)
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
								$multiple_days='{"Sun":"1","Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1"}';
								$inv_data=array('hotel_id'=>$bucket_hotel_id,'room_type_id'=>$inventoryData[$i]->room_type_id,'no_of_rooms'=>$room_quanty,'date_from'=>$startDate,'date_to'=>$endDate,'channel'=>$bucket_data['bucket_ota_name'],'client_ip'=>$booking_ip,'user_id'=>0,'multiple_days'=>$multiple_days,'block_status'=>$inventoryData[$i]->block_status,'los'=>$inventoryData[$i]->los,'ota_booking_id'=>$bucket_ota_booking_tabel_id,'booking_status'=>$bucket_booking_status);
								$otainventory->fill($inv_data)->save();
						}
					}
				} // foreach $ota_inventry_details closed here.
			} // If $bucket_booking_status == 'Cancel' close here.
			}
			}
			} // foreach $room_types closed here.
			return true;
    }
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

		$room_type	= $cmOtaRoomTypeSynchronizeModel->getSingleHotelRoomIdFromRoomSynch($room_type,$bucket_hotel_id);

		if(!empty($room_type)){
		$room_code=$cmOtaRoomTypeSynchronizeModel->getOtaRoomType($room_type,$bucket_ota_id);

		/*******====== Get Inventory ===== ******/

		$inventoryData=$this->cmOtaBookingInvStatusService->getInventoryDetailsByRoomType($bucket_ota_booking_tabel_id,$room_type,$bucket_hotel_id,$bucket_ota_id);//	0 => No of days prior to the booking

		if($inventoryData)
		{
		if($bucket_booking_status == 'Commit'){

			for($i=0; $i<count($inventoryData); $i++)
			{
				$otainventory                   = new OtaInventoryTest();
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
							$Flagchecker =1;
							$multiple_days='{"Sun":"1","Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1"}';
							$inv_data=array('hotel_id'=>$bucket_hotel_id,'room_type_id'=>$inventoryData[$i]->room_type_id,'no_of_rooms'=>$room_quanty,'date_from'=>$startDate,'date_to'=>$endDate,'channel'=>$bucket_data['bucket_ota_name'],'client_ip'=>$booking_ip,'user_id'=>0,'multiple_days'=>$multiple_days,'block_status'=>$inventoryData[$i]->block_status,'los'=>$inventoryData[$i]->los,'ota_booking_id'=>$bucket_ota_booking_tabel_id,'booking_status'=>$bucket_booking_status);
							$otainventory->fill($inv_data)->save();
					} // $room_quanty >= 0
				}
		} // If $bucket_booking_status == 'Commit' close here.
		if($bucket_booking_status == 'Cancel'){
			for($i=0; $i<count($inventoryData); $i++)
			{
				$otainventory                   = new OtaInventoryTest();
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

					if($room_quanty >= 0)
					{
							$Flagchecker = 1;
							$multiple_days='{"Sun":"1","Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1"}';
							$inv_data=array('hotel_id'=>$bucket_hotel_id,'room_type_id'=>$inventoryData[$i]->room_type_id,'no_of_rooms'=>$room_quanty,'date_from'=>$startDate,'date_to'=>$endDate,'channel'=>$bucket_data['bucket_ota_name'],'client_ip'=>$booking_ip,'user_id'=>0,'multiple_days'=>$multiple_days,'block_status'=>$inventoryData[$i]->block_status,'los'=>$inventoryData[$i]->los,'ota_booking_id'=>$bucket_ota_booking_tabel_id,'booking_status'=>$bucket_booking_status);
							$otainventory->fill($inv_data)->save();
					} // $room_quanty >= 0
				}
			} // foreach $ota_inventry_details closed here.
		} // If $bucket_booking_status == 'Cancel' close here.
		}
		}
		} // foreach $room_types closed here.
		return true;
    }
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

		$room_type = $cmOtaRoomTypeSynchronizeModel->getSingleHotelRoomIdFromRoomSynch($room_type,$bucket_hotel_id);
		if(!empty($room_type)){

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
				$otainventory                   = new OtaInventoryTest();
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
							$multiple_days='{"Sun":"1","Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1"}';
							$inv_data=array('hotel_id'=>$bucket_hotel_id,'room_type_id'=>$inventoryData[$i]->room_type_id,'no_of_rooms'=>$room_quanty,'date_from'=>$startDate,'date_to'=>$endDate,'channel'=>$bucket_data['bucket_ota_name'],'client_ip'=>$booking_ip,'user_id'=>0,'multiple_days'=>$multiple_days,'block_status'=>$inventoryData[$i]->block_status,'los'=>$inventoryData[$i]->los,'ota_booking_id'=>$bucket_ota_booking_tabel_id,'booking_status'=>$bucket_booking_status);
							$otainventory->fill($inv_data)->save();
					} // empty $result.
				}
			}
		} // If $bucket_booking_status == 'Commit' close here.

		if($bucket_booking_status == 'Cancel')
		{
			for($i=0; $i<count($inventoryData); $i++)
			{
				$otainventory                   = new OtaInventoryTest();
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

					if($room_quanty >= 0)
					{
							$multiple_days='{"Sun":"1","Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1"}';
							$inv_data=array('hotel_id'=>$bucket_hotel_id,'room_type_id'=>$inventoryData[$i]->room_type_id,'no_of_rooms'=>$room_quanty,'date_from'=>$startDate,'date_to'=>$endDate,'channel'=>$bucket_data['bucket_ota_name'],'client_ip'=>$booking_ip,'user_id'=>0,'multiple_days'=>$multiple_days,'block_status'=>$inventoryData[$i]->block_status,'los'=>$inventoryData[$i]->los,'ota_booking_id'=>$bucket_ota_booking_tabel_id,'booking_status'=>$bucket_booking_status);
							$otainventory->fill($inv_data)->save();
					} // empty $result.
				}
			}
		} // If $bucket_booking_status == 'Cancel' close here.
		}
		}

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

			 $room_type = $cmOtaRoomTypeSynchronizeModel->getSingleHotelRoomIdFromRoomSynch($room_type,$bucket_hotel_id);

			 if(!empty($room_type)){
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
					$otainventory                   = new OtaInventoryTest();
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
                            $multiple_days='{"Sun":"1","Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1"}';
                            $inv_data=array('hotel_id'=>$bucket_hotel_id,'room_type_id'=>$inventoryData[$i]->room_type_id,'no_of_rooms'=>$room_quanty,'date_from'=>$startDate,'date_to'=>$endDate,'channel'=>$bucket_data['bucket_ota_name'],'client_ip'=>$booking_ip,'user_id'=>0,'multiple_days'=>$multiple_days,'block_status'=>$inventoryData[$i]->block_status,'los'=>$inventoryData[$i]->los,'ota_booking_id'=>$bucket_ota_booking_tabel_id,'booking_status'=>$bucket_booking_status);
                            $otainventory->fill($inv_data)->save();
						} // empty $result.
					}
				}
			} // If $bucket_booking_status == 'Commit' close here.

			if($bucket_booking_status == 'Cancel')
			{
				for($i=0; $i<count($inventoryData); $i++)
				{
					$otainventory                   = new OtaInventoryTest();
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

						if($room_quanty >= 0)
						{
                            $multiple_days='{"Sun":"1","Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1"}';
                            $inv_data=array('hotel_id'=>$bucket_hotel_id,'room_type_id'=>$inventoryData[$i]->room_type_id,'no_of_rooms'=>$room_quanty,'date_from'=>$startDate,'date_to'=>$endDate,'channel'=>$bucket_data['bucket_ota_name'],'client_ip'=>$booking_ip,'user_id'=>0,'multiple_days'=>$multiple_days,'block_status'=>$inventoryData[$i]->block_status,'los'=>$inventoryData[$i]->los,'ota_booking_id'=>$bucket_ota_booking_tabel_id,'booking_status'=>$bucket_booking_status);
                            $otainventory->fill($inv_data)->save();
							// $log_response_msg='';
						} // empty $result.
					}
				}
			} // If $bucket_booking_status == 'Cancel' close here.
			}
			}

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

				$room_type = $cmOtaRoomTypeSynchronizeModel->getSingleHotelRoomIdFromRoomSynch($room_type,$bucket_hotel_id);
				if(!empty($room_type)){
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
						$otainventory                   = new OtaInventoryTest();
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
                                $multiple_days='{"Sun":"1","Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1"}';
                                $inv_data=array('hotel_id'=>$bucket_hotel_id,'room_type_id'=>$inventoryData[$i]->room_type_id,'no_of_rooms'=>$room_quanty,'date_from'=>$startDate,'date_to'=>$endDate,'channel'=>$bucket_data['bucket_ota_name'],'client_ip'=>$booking_ip,'user_id'=>0,'multiple_days'=>$multiple_days,'block_status'=>$inventoryData[$i]->block_status,'los'=>$inventoryData[$i]->los,'ota_booking_id'=>$bucket_ota_booking_tabel_id,'booking_status'=>$bucket_booking_status);
                                $otainventory->fill($inv_data)->save();
							} // empty $result.
						}
					}
				} // If $bucket_booking_status == 'Commit' close here.

				if($bucket_booking_status == 'Cancel')
				{
					for($i=0; $i<count($inventoryData); $i++)
					{
						$otainventory                   = new OtaInventoryTest();
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

							if($room_quanty >= 0)
							{
										$multiple_days='{"Sun":"1","Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1"}';
										$inv_data=array('hotel_id'=>$bucket_hotel_id,'room_type_id'=>$inventoryData[$i]->room_type_id,'no_of_rooms'=>$room_quanty,'date_from'=>$startDate,'date_to'=>$endDate,'channel'=>$bucket_data['bucket_ota_name'],'client_ip'=>$booking_ip,'user_id'=>0,'multiple_days'=>$multiple_days,'block_status'=>$inventoryData[$i]->block_status,'los'=>$inventoryData[$i]->los,'ota_booking_id'=>$bucket_ota_booking_tabel_id,'booking_status'=>$bucket_booking_status);
										$otainventory->fill($inv_data)->save();
							} // empty $result.
						}
					}
				} // If $bucket_booking_status == 'Cancel' close here.
				}
				}
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

					$room_type = $cmOtaRoomTypeSynchronizeModel->getSingleHotelRoomIdFromRoomSynch($room_type,$bucket_hotel_id);
					if(!empty($room_type)){
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
							$otainventory                   = new OtaInventoryTest();
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
									
										$multiple_days='{"Sun":"1","Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1"}';
										$inv_data=array('hotel_id'=>$bucket_hotel_id,'room_type_id'=>$inventoryData[$i]->room_type_id,'no_of_rooms'=>$room_quanty,'date_from'=>$startDate,'date_to'=>$endDate,'channel'=>$bucket_data['bucket_ota_name'],'client_ip'=>$booking_ip,'user_id'=>0,'multiple_days'=>$multiple_days,'block_status'=>$inventoryData[$i]->block_status,'los'=>$inventoryData[$i]->los,'ota_booking_id'=>$bucket_ota_booking_tabel_id,'booking_status'=>$bucket_booking_status);
										$otainventory->fill($inv_data)->save();
									
								} // empty $result.
							}
						}
						
					} // If $bucket_booking_status == 'Commit' close here.

					if($bucket_booking_status == 'Cancel')
					{
						for($i=0; $i<count($inventoryData); $i++)
						{
							$otainventory                   = new OtaInventoryTest();
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

								if($room_quanty >= 0)
								{
										$multiple_days='{"Sun":"1","Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1"}';
										$inv_data=array('hotel_id'=>$bucket_hotel_id,'room_type_id'=>$inventoryData[$i]->room_type_id,'no_of_rooms'=>$room_quanty,'date_from'=>$startDate,'date_to'=>$endDate,'channel'=>$bucket_data['bucket_ota_name'],'client_ip'=>$booking_ip,'user_id'=>0,'multiple_days'=>$multiple_days,'block_status'=>$inventoryData[$i]->block_status,'los'=>$inventoryData[$i]->los,'ota_booking_id'=>$bucket_ota_booking_tabel_id,'booking_status'=>$bucket_booking_status);
										$otainventory->fill($inv_data)->save();
								} // empty $result.
							}
						}
					} // If $bucket_booking_status == 'Cancel' close here.
					}
					}
				} // foreach $room_types closed here.
				return true;
		}
		/*------------------- Goomo Update Function Close------------------------*/
}
