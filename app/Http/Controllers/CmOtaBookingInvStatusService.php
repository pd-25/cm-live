<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\CmOtaBookingInvStatus;
use App\CmOtaRoomTypeSynchronize;
use App\CmOtaBooking;
use App\CmOtaDetails;
use App\CmOtaDetailsRead;
use App\OtaInventory;
use DB;
//create a new class CmOtaBookingInvStatus

class CmOtaBookingInvStatusService extends Controller
{
    public function saveCurrentInvStatus(string $ota_booking_id,int $ota_id,int $hotel_id,$from_date,$to_date,$room_types,$booking_status,$room_qty)
    {
        $inventoryData=array();
        $otaInventoryData=array();
        $room_types  = explode(",", $room_types);
        $room_qty=explode(',',$room_qty);
        $cmOtaRoomTypeSynchronizeModel  = new CmOtaRoomTypeSynchronize();
        $newInventory=array();
        $otaNewInventory=array();
        $flag=0;
        $newInvStatus=array();
        foreach($room_types as $room_key=>$room_type)
        {
            $rm_type  = $cmOtaRoomTypeSynchronizeModel->getSingleHotelRoomIdFromRoomSynch($room_type,$hotel_id);
            if($rm_type!=0)
            {
                try{
                  $inv_data = array("room_type_id"=>$rm_type,"date_form"=>$from_date,"date_to"=>$to_date,"mindays"=>0);
                  $url = 'https://be.bookingjini.com/get-be-current-inventory';
                  $ch = curl_init();
                  curl_setopt( $ch, CURLOPT_URL, $url );
                  curl_setopt( $ch, CURLOPT_POST, true );
                  curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
                  curl_setopt( $ch, CURLOPT_POSTFIELDS, $inv_data);
                  $inventoryData = curl_exec($ch);
                  curl_close($ch);
                  $inventoryData = json_decode($inventoryData);
                  if(empty($inventoryData)){
                      continue;
                  }
                }
                catch(Exception $e){

                }
                $otaInventoryData=$this->getOtaCurrentInventory($rm_type,$from_date,$to_date,$ota_id,$hotel_id,$inventoryData);
                if(sizeof($newInvStatus)>0){
                    $flag=0;
                    $outer_index=-1;
                    foreach($newInvStatus as $ota_key=>$allTypesInv){
                        foreach($allTypesInv as $key=>$newInv){
                            foreach($newInv as $i=>$inv){

                              $room_type_id = is_object($inv)?$newInv[$i]->room_type_id:$inv['room_type_id'];
                                if($rm_type==$room_type_id){
                                    $flag=1;
                                    $outer_index=$key;
                                }
                            }
                        }
                    }
                    if($flag==1 && $outer_index > -1){
                        foreach($inventoryData as $key=>$inv){
                            if($inv['no_of_rooms'] <= 0){
                                foreach($newInvStatus as $new_inv_key => $newInvs){
                                    $newInvs[$new_inv_key][$outer_index][$key]['no_of_rooms']=0;
                                    $newInvs[$new_inv_key][$outer_index][$key]['update_to']=0;
                                }
                            }
                            if($booking_status == 'Commit' || $booking_status == 'Modify'){
                                foreach($newInvStatus as $newInvs){
                                    if(isset($newInvs[$new_inv_key][$outer_index][$key]['update_to'])){
                                        if($newInvs[$new_inv_key][$outer_index][$key]['update_to'] >= $room_qty[$room_key]){
                                            $newInvs[$new_inv_key][$outer_index][$key]['update_to']=$newInvs[$new_inv_key][$outer_index][$key]['update_to']-$room_qty[$room_key];
                                        }else{
                                            $newInvs[$new_inv_key][$outer_index][$key]['update_to']=0;
                                            $newInvs[$new_inv_key][$outer_index][$key]['no_of_rooms']=0;
                                        }

                                    }
                                }
                            }
                            if($booking_status == 'Cancel'){
                                foreach($newInvStatus as $new_inv_key=>$newInvs){
                                    if(isset($newInvs[$new_inv_key][$outer_index][$key]['update_to'])){
                                    $newInvs[$new_inv_key][$outer_index][$key]['update_to']=$newInvs[$new_inv_key][$outer_index][$key]['update_to']+$room_qty[$room_key];
                                    }
                                }
                            }
                        }
                        foreach($otaInventoryData as $ota_key => $ota_inv){
                            foreach($ota_inv as $key=>$inv){
                                if($inv['no_of_rooms'] <= 0){
                                    foreach($newInvStatus as $new_inv_key =>$newInvs){
                                    $newInvs[$new_inv_key][$outer_index][$key]['no_of_rooms']=0;
                                    $newInvs[$new_inv_key][$ota_key][$key]['update_to']=0;
                                    }
                                }
                                if($booking_status == 'Commit' || $booking_status == 'Modify'){
                                    if($inv['no_of_rooms'] >= $room_qty[$room_key]){
                                        $newInvs[$new_inv_key][$ota_key][$key]['update_to']= $newInvs[$new_inv_key][$ota_key][$key]['no_of_rooms']-$room_qty[$room_key];
                                    }else{
                                        $newInvs[$new_inv_key][$ota_key][$key]['update_to']=0;
                                        $newInvs[$new_inv_key][$ota_key][$key]['no_of_rooms']=0;
                                    }
                                }
                                if($booking_status == 'Cancel'){
                                    $newInvs[$new_inv_key][$ota_key][$key]['update_to']= $newInvs[$new_inv_key][$ota_key][$key]['no_of_rooms']+$room_qty[$room_key];
                                }
                            }
                        }
                    }else{

                        foreach($inventoryData as $key=>$inv){
                            if($inv->no_of_rooms <= 0){
                                $inventoryData[$key]->no_of_rooms=0;
                                $inventoryData[$key]->update_to=0;
                            }
                            if($booking_status == 'Commit' || $booking_status == 'Modify'){
                                if($inventoryData[$key]->no_of_rooms >=$room_qty[$room_key]){
                                    $inventoryData[$key]->update_to=$inventoryData[$key]->no_of_rooms-$room_qty[$room_key];
                                }else{
                                    $inventoryData[$key]->update_to=0;
                                    $inventoryData[$key]->no_of_rooms=0;
                                }

                            }
                            if($booking_status == 'Cancel'){
                              $inventoryData[$key]->update_to=$inventoryData[$key]->no_of_rooms-$room_qty[$room_key];
                            }

                        }
                        foreach($otaInventoryData as $ota_key => $ota_inv){
                            foreach($ota_inv as $key=>$inv){
                                if($inv['no_of_rooms'] <= 0){
                                    $otaInventoryData[$ota_key][$key]['no_of_rooms']=0;
                                    $otaInventoryData[$ota_key][$key]['update_to']=0;
                                }
                                if($booking_status == 'Commit' || $booking_status == 'Modify'){
                                    if($inv['no_of_rooms'] >= $room_qty[$room_key]){
                                        $otaInventoryData[$ota_key][$key]['update_to']= $otaInventoryData[$ota_key][$key]['no_of_rooms']-$room_qty[$room_key];
                                    }else{
                                        $otaInventoryData[$ota_key][$key]['update_to']=0;
                                        $otaInventoryData[$ota_key][$key]['no_of_rooms']=0;
                                    }
                                }
                                if($booking_status == 'Cancel'){
                                    $otaInventoryData[$ota_key][$key]['update_to']= $otaInventoryData[$ota_key][$key]['no_of_rooms']+$room_qty[$room_key];
                                }
                            }
                        }
                        $otaInventoryData['BE'] = $inventoryData;
                        array_push($newInvStatus,$otaInventoryData);

                    }
                }else{
                    foreach($inventoryData as $key=>$inv){
                        if($inv->no_of_rooms <= 0){
                            $inventoryData[$key]->no_of_rooms=0;
                            $inventoryData[$key]->update_to=0;
                        }
                        if($booking_status == 'Commit' || $booking_status == 'Modify'){
                            if($inventoryData[$key]->no_of_rooms >= $room_qty[$room_key]){
                                $inventoryData[$key]->update_to=$inventoryData[$key]->no_of_rooms-$room_qty[$room_key];
                            }else{
                                $inventoryData[$key]->update_to=0;
                                $inventoryData[$key]->no_of_rooms=0;
                            }
                        }
                        if($booking_status == 'Cancel'){
                            $inventoryData[$key]->update_to=$inventoryData[$key]->no_of_rooms+$room_qty[$room_key];
                        }
                    }
                    foreach($otaInventoryData as $ota_key => $ota_inv){
                        foreach($ota_inv as $key=>$inv){
                            if($inv['no_of_rooms'] <= 0){
                                $otaInventoryData[$ota_key][$key]['no_of_rooms']=0;
                                $otaInventoryData[$ota_key][$key]['update_to']=0;
                            }
                            if($booking_status == 'Commit' || $booking_status == 'Modify'){
                                if($inv['no_of_rooms'] >= $room_qty[$room_key]){
                                    $otaInventoryData[$ota_key][$key]['update_to']= $otaInventoryData[$ota_key][$key]['no_of_rooms']-$room_qty[$room_key];
                                }else{
                                    $otaInventoryData[$ota_key][$key]['update_to']=0;
                                    $otaInventoryData[$ota_key][$key]['no_of_rooms']=0;
                                }
                            }
                            if($booking_status == 'Cancel'){
                                $otaInventoryData[$ota_key][$key]['update_to']= $otaInventoryData[$ota_key][$key]['no_of_rooms']+$room_qty[$room_key];
                            }
                        }
                    }
                    $otaInventoryData['BE']=$inventoryData;
                    array_push($newInvStatus,$otaInventoryData);
                }
            }
        }
        $invStatus = new CmOtaBookingInvStatus();
        $invStatus->ota_booking_id= $ota_booking_id;
        $invStatus->hotel_id= $hotel_id;
        $invStatus->ota_id= $ota_id;
        $invStatus->inventory= json_encode($newInvStatus);
        if($invStatus->save()){
            return 1;
        }
    }
     public function checkBookingId($booking_id)
    {
        $booking_details    =   CmOtaBooking::select('*')->where('unique_id',$booking_id)->first();
        if($booking_details)
        {
            return true;
        }
        else{
            return false;
        }
    }
    public function getOtaCurrentInventory($rm_type,$from_date,$to_date,$ota_id,$hotel_id,$inventoryData){
        $date1=date_create($from_date);
        $date2=date_create($to_date);
        $diff=date_diff($date1,$date2);
        $diff=$diff->format("%a");
        $otaDetails=array();
        for($i=0;$i<$diff;$i++){
             $timestamp = strtotime($from_date);
             $day = date('D', $timestamp);
             $getOtaInfo=CmOtaDetailsRead::select('ota_name','ota_id')
            ->where('hotel_id',$hotel_id)
            ->where('is_active',1)
            ->get();
            foreach($getOtaInfo as $otas){
                $key = $otas->ota_name;
                $getOtaDetails=OtaInventory::select('no_of_rooms','block_status','room_type_id','los','multiple_days')
                                ->where('hotel_id',$hotel_id)
                                ->where('channel',$key)
                                ->where('room_type_id',$rm_type)
                                ->where('date_from','<=',$from_date)
                                ->where('date_to','>=',$from_date)
                                ->orderBy('inventory_id','desc')
                                ->first();
                if($getOtaDetails){
                    $otaDetails[$key][$i]['no_of_rooms']=$getOtaDetails->no_of_rooms;
                    $otaDetails[$key][$i]['block_status']=$getOtaDetails->block_status;
                    $otaDetails[$key][$i]['room_type_id']=$getOtaDetails->room_type_id;
                    $otaDetails[$key][$i]['date']=$from_date;
                    $otaDetails[$key][$i]['day']=$day;
                    $otaDetails[$key][$i]['los']=$getOtaDetails->los;
                }
                else{
                    $otaDetails[$key][$i]['no_of_rooms']=0;
                    $otaDetails[$key][$i]['block_status']=0;
                    $otaDetails[$key][$i]['room_type_id']=$rm_type;
                    $otaDetails[$key][$i]['date']=$from_date;
                    $otaDetails[$key][$i]['day']=$day;
                    $otaDetails[$key][$i]['los']=1;
                }
            }
            $from_date=date('Y-m-d',strtotime($from_date.'+1 days'));
        }
        return $otaDetails;
    }
    public function getInventoryDetailsByRoomType(string $cm_ota_booking_id,int $room_type_id,int $hotel_id,int $ota_id)
    {
        $cmOtaBookingInvStatus=new CmOtaBookingInvStatus();
        $inv_data=$cmOtaBookingInvStatus
                    ->where('ota_booking_id',$cm_ota_booking_id)
                    ->where('hotel_id',$hotel_id)
                    ->orderBy('created_at','DESC')
                    ->first();
        if($ota_id == 0){
            $ota_name = 'BE';
        }
        else{
            $getOtaName = CmOtaDetails::select('ota_name')
                        ->where('hotel_id',$hotel_id)
                        ->where('ota_id',$ota_id)
                        ->first();
            $ota_name = $getOtaName->ota_name;
        }
        $inv_data=json_decode($inv_data->inventory);
        $return_array=array();
        foreach($inv_data as $room_inv_data){
            foreach($room_inv_data as $key=>$inner_inv)
            {
                if($key == $ota_name){
                    foreach($inner_inv as $inv){
                        if($room_type_id == $inv->room_type_id)
                        {
                          // if($inv->no_of_rooms != 0){
                          //   if($inv->block_status != 1){
                          //     $return_array[] = $inv;
                          //   }
                          // }
                          $return_array[] = $inv;

                        }
                    }
                }
            }
        }
        return $return_array;
    }
}
