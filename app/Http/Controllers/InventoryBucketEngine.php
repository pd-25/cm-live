<?php
namespace App\Http\Controllers;
use Validator;
use App\Inventory;
use App\CmOtaManageInventoryBucket;
use App\CmOtaDetails;
use App\RatePlanLog;
use App\MasterRoomType;
use App\Http\Controllers\OtaInventoryPushUpdate;
use App\Http\Controllers\OtaRatePlanPushUpdate;

use DB;
class InventoryBucketEngine extends Controller
{  
    protected $otaInventoryPushUpdate;

    public function __construct(OtaInventoryPushUpdate $otaInventoryPushUpdate)
    {
       $this->otaInventoryPushUpdate = $otaInventoryPushUpdate;
    }  
     
    public function RunBucketEngine($hotel_id,$id,$upadateType,$user_id,$single_ota_id)
    {
        $cmOtaDetailsModel             	  = new CmOtaDetails();
        $cmOtaManageInventoryBucketModel  = new CmOtaManageInventoryBucket();
    
        if($upadateType == "inventory")
        {
            $inventory_table_id = $id;             
            if($single_ota_id > 0)
            {
                $cmOtaManageInventoryBucketDetailsByOtaId   =   CmOtaManageInventoryBucket::select('*')
                ->where('is_update','=' ,0)
                ->where('hotel_id','=', $hotel_id)
                ->where("inventory_table_id","=",$inventory_table_id)
                ->where("ota_id","=",$single_ota_id)
                ->groupBy('hotel_id')
                ->orderBy('push_at','asc')
                ->first();
                return $this->inventoryUpdate($cmOtaManageInventoryBucketDetailsByOtaId,$user_id,$inventory_table_id);
            }
            else{
                $cmOtaManageInventoryBucketDetails = CmOtaManageInventoryBucket::select('*')
                ->where('is_update','=' ,0)
                ->where('hotel_id','=', $hotel_id)
                ->where("inventory_table_id","=",$inventory_table_id)
                ->groupBy('hotel_id')
                ->orderBy('push_at','asc')
                ->first();		
                return $this->inventoryUpdate($cmOtaManageInventoryBucketDetails,$user_id,$inventory_table_id);							                           
            } 
        }   
        if($upadateType == "roomrate")
        {
            $rate_plan_log_id = $id;
            $extra_adult_price    = 0;
            $extra_child_price    = 0;
           
            if($single_ota_id > 0)  
            {
                $cmOtaManageInventoryBucketDetailsByOtaId   =   CmOtaManageInventoryBucket::select('*')
                ->where('is_update','=' ,0)
                ->where('hotel_id','=', $hotel_id)
                ->where("rate_plan_log_table_id","=",$rate_plan_log_id)
                ->where("ota_id","=",$single_ota_id)
                ->groupBy('hotel_id')
                ->orderBy('push_at','asc')
                ->first();
              return $this->rateUpdate($cmOtaManageInventoryBucketDetailsByOtaId,$user_id,$rate_plan_log_id);
            } 
            else{
                $cmOtaManageInventoryBucketDetails = CmOtaManageInventoryBucket::select('*')
                ->where('is_update','=' ,0)
                ->where('hotel_id','=', $hotel_id)
                ->where("rate_plan_log_table_id","=",$rate_plan_log_id)
                ->groupBy('hotel_id')
                ->orderBy('push_at','asc')
                ->first();    
               return $this->rateUpdate($cmOtaManageInventoryBucketDetails,$user_id,$rate_plan_log_id);
            }    
        } 
    }
    public function inventoryUpdate($cmOtaManageInventoryBucketDetailsByOtaId,$user_id,$id)
    {
        if($cmOtaManageInventoryBucketDetailsByOtaId)
        {
            $ota_name = $cmOtaManageInventoryBucketDetailsByOtaId->ota_name;
            $bucket_id                     = $cmOtaManageInventoryBucketDetailsByOtaId->id;
            $hotel_id               = $cmOtaManageInventoryBucketDetailsByOtaId->hotel_id;
            $inventory_table_id     = $cmOtaManageInventoryBucketDetailsByOtaId->inventory_table_id;
            $ota_id                 = $cmOtaManageInventoryBucketDetailsByOtaId->ota_id;
            $ota_hotel_code         = $cmOtaManageInventoryBucketDetailsByOtaId->ota_hotel_code;
    
            $bucketdata                   = [
                                            "bucket_id"                   => $bucket_id,
                                            "bucket_hotel_id"             => $hotel_id,
                                            "bucket_inventory_table_id"   => $inventory_table_id,
                                            "bucket_ota_id"               => $ota_id,
                                            "bucket_ota_name"             => $ota_name,
                                            "bucket_ota_hotel_code"       => $ota_hotel_code,
                                            ];
            try{
                DB::table('cm_ota_manage_inventory_bucket')->where('id', $bucket_id)
                ->update(['is_update' => 2]);
            }catch(Exception $e){
                echo 'Message: ' .$e->getMessage();
            }

            $inventoryDetails          = Inventory::select('*')
            ->where('inventory_id','=',$id)
            ->first();
            
            $inventory_inventory_id      = $inventoryDetails->inventory_id;
            $inventory_hotel_id          = $inventoryDetails->hotel_id;
            $inventory_room_type_id      = $inventoryDetails->room_type_id;
            $inventory_no_of_rooms       = $inventoryDetails->no_of_rooms;
            $inventory_date_from         = $inventoryDetails->date_from;
            $inventory_date_to           = $inventoryDetails->date_to;
            $inventory_client_ip         = $inventoryDetails->client_ip;
            $inventory_los               = $inventoryDetails->los;  


            $inventory_data  = [
                                "inventory_inventory_id"       => $inventory_inventory_id,
                                "inventory_hotel_id"           => $inventory_hotel_id,
                                "inventory_room_type_id"       => $inventory_room_type_id,
                                "inventory_no_of_rooms"        => $inventory_no_of_rooms,
                                "inventory_date_from"          => $inventory_date_from,
                                "inventory_date_to"            => $inventory_date_to,
                                "inventory_client_ip"          => $inventory_client_ip,
                                "inventory_los"                => $inventory_los,
                                
                                ];
            $cmOtaDetails           = CmOtaDetails::select('*')
            ->where('hotel_id', '=' ,$hotel_id)
            ->where('ota_id', '=' ,$ota_id )
            ->where('is_active', '=' ,1)
            ->first(); 
            if($cmOtaDetails)
            {
                if($ota_name == "Cleartrip")
                {
                    $return_status   = $this->otaInventoryPushUpdate->cleartripUpdate($bucketdata,$inventory_data,$user_id);
                    return $this->returnStatus($return_status,$bucket_id);
                }
                else if($ota_name == "Agoda")
                {
                    $return_status   =  $this->otaInventoryPushUpdate->agodaUpdate($bucketdata,$inventory_data,$user_id);
                    return $this->returnStatus($return_status,$bucket_id);
                }
                else if($ota_name == "Goibibo")
                {
                    $return_status   =  $this->otaInventoryPushUpdate->goibiboUpdate($bucketdata,$inventory_data,$user_id);
                    return $this->returnStatus($return_status,$bucket_id);
                }
                else if($ota_name == "Expedia")
                {
                    $return_status   =  $this->otaInventoryPushUpdate->expediaUpdate($bucketdata,$inventory_data,$user_id);
                    return $this->returnStatus($return_status,$bucket_id);
                }
                else  if($ota_name == "Booking.com")
                {
                    $return_status   =  $this->otaInventoryPushUpdate->bookingdotcomUpdate($bucketdata,$inventory_data,$user_id);
                    return $this->returnStatus($return_status,$bucket_id);
                }
                else if($ota_name == "Via.com")
                {
                    $return_status =   $this->otaInventoryPushUpdate->viadotcomUpdate($bucketdata,$inventory_data,$user_id);
                    return $this->returnStatus($return_status,$bucket_id);
                }
                else if($ota_name == "Travelguru")
                {
                    $return_status =  $this->otaInventoryPushUpdate->travelguruUpdate($bucketdata,$inventory_data,$user_id); 
                    return $this->returnStatus($return_status,$bucket_id);
                } 
                else if($ota_name == "Airbnb")
                {
                    $return_status =  $this->otaInventoryPushUpdate->airbnbUpdate($bucketdata,$inventory_data,$user_id);
                    return $this->returnStatus($return_status,$bucket_id);
                }
                else if($ota_name == "Goomo")
                {
                    $return_status =  $this->otaInventoryPushUpdate->goomoUpdate($bucketdata,$inventory_data,$user_id);
                    return $this->returnStatus($return_status,$bucket_id);
                } 
                else if($ota_name == "Nodal")
                {
                    $return_status =  $this->otaInventoryPushUpdate->nodalUpdate($bucketdata,$inventory_data,$user_id);
                    return $this->returnStatus($return_status,$bucket_id);
                }
                else if($ota_name == "EaseMyTrip")
                {
                    $return_status =  $this->otaInventoryPushUpdate->easemytripUpdate($bucketdata,$inventory_data,$user_id);
                    return $this->returnStatus($return_status,$bucket_id);
                }
                else if($ota_name == "Paytm")
                {
                    $return_status =  $this->otaInventoryPushUpdate->paytmUpdate($bucketdata,$inventory_data,$user_id);
                    return $this->returnStatus($return_status,$bucket_id);
                }
            }
        }
        else{
            echo "No Bucket Record Found!";
        }  
    }
    public function rateUpdate($cmOtaManageInventoryBucketDetailsByOtaId,$user_id,$id)
    {
        $roomTypeModel= new MasterRoomType();
        $otaRatePlanPushUpdate            = new OtaRatePlanPushUpdate();  
        if($cmOtaManageInventoryBucketDetailsByOtaId)
        {
            $ota_name                   = $cmOtaManageInventoryBucketDetailsByOtaId->ota_name;
            $bucket_id                  = $cmOtaManageInventoryBucketDetailsByOtaId->id;
            $hotel_id                   = $cmOtaManageInventoryBucketDetailsByOtaId->hotel_id;
            $rate_plan_log_table_id     = $cmOtaManageInventoryBucketDetailsByOtaId->rate_plan_log_table_id;
            $ota_id                     = $cmOtaManageInventoryBucketDetailsByOtaId->ota_id;
            $ota_hotel_code             = $cmOtaManageInventoryBucketDetailsByOtaId->ota_hotel_code;
            $bucketdata                   = [
                                            "bucket_id"                         => $bucket_id,
                                            "bucket_hotel_id"                   => $hotel_id,
                                            "bucket_rate_plan_log_table_id"     => $rate_plan_log_table_id,
                                            "bucket_ota_id"                     => $ota_id,
                                            "bucket_ota_name"                   => $ota_name,
                                            "bucket_ota_hotel_code"             => $ota_hotel_code,
                                            ];
            try{
                DB::table('cm_ota_manage_inventory_bucket')->where('id', $bucket_id )
                ->update(['is_update' =>2]);//Processing
            }
            catch(Exception $e)
            {
                echo 'Message: ' .$e->getMessage();
            }

            /*--------------------Fetching Inventory Model Details--------------------------*/
    
            $rateRatePlanDetails        = RatePlanLog::select('*')
            ->where('rate_plan_log_id','=',$id)
            ->first();
            
            $rateplan_rate_plan_log_id   = $rateRatePlanDetails->rate_plan_log_id;
            $rateplan_hotel_id           = $rateRatePlanDetails->hotel_id;
            $rateplan_room_type_id       = $rateRatePlanDetails->room_type_id;
            $rateplan_rate_plan_id       = $rateRatePlanDetails->rate_plan_id;
            $rateplan_bar_price          = $rateRatePlanDetails->bar_price;
            $rateplan_multiple_occupancy = $rateRatePlanDetails->multiple_occupancy;
            $rateplan_multiple_days      = $rateRatePlanDetails->multiple_days;
            $rateplan_date_from          = $rateRatePlanDetails->from_date;
            $rateplan_date_to            = $rateRatePlanDetails->to_date;
            $rateplan_client_ip          = $rateRatePlanDetails->client_ip;
            $rateplan_los                = $rateRatePlanDetails->los;
            $extra_adult_price           = $rateRatePlanDetails->extra_adult_price;
            $extra_child_price           = $rateRatePlanDetails->extra_child_price;
            $max_adult=$roomTypeModel->select('max_people')->where('room_type_id',$rateplan_room_type_id)->first()->max_people;
            $rateplan_data               = [
                        "rateplan_rate_plan_log_id"   => $rateplan_rate_plan_log_id,
                        "rateplan_hotel_id"           => $rateplan_hotel_id,
                        "rateplan_room_type_id"       => $rateplan_room_type_id,
                        "rateplan_rate_plan_id"       => $rateplan_rate_plan_id,
                        "rateplan_bar_price"          => $rateplan_bar_price,
                        "rateplan_multiple_occupancy" => $rateplan_multiple_occupancy,
                        "rateplan_date_from"          => $rateplan_date_from,
                        "rateplan_date_to"            => $rateplan_date_to,
                        "rateplan_client_ip"          => $rateplan_client_ip,
                        "rateplan_multiple_days"      => $rateplan_multiple_days,
                        "rateplan_los"                => $rateplan_los,    
                        "extra_adult_price"           => $extra_adult_price,
                        "extra_child_price"           => $extra_child_price,
                        "max_adult"                   => $max_adult
                        ];
            $cmOtaDetails           = CmOtaDetails::select('*')
            ->where('hotel_id', '=' ,$hotel_id)
            ->where('ota_id', '=' ,$ota_id )
            ->where('is_active', '=' ,1)
            ->first(); 
            if($cmOtaDetails)
            { 
                if($ota_name == "Cleartrip")
                {
                    $return_status   = $otaRatePlanPushUpdate->cleartripUpdate($bucketdata,$rateplan_data,$user_id);
                    return $this->returnStatus($return_status,$bucket_id);
                }
                else if($ota_name == "Agoda")
                {
                    $return_status   = $otaRatePlanPushUpdate->agodaUpdate($bucketdata,$rateplan_data,$user_id);
                    return $this->returnStatus($return_status,$bucket_id);
                }
                else if($ota_name == "Goibibo")
                {
                        
                    $return_status   = $otaRatePlanPushUpdate->goibiboUpdate($bucketdata,$rateplan_data,$user_id);  
                    return $this->returnStatus($return_status,$bucket_id);
                }
                else if($ota_name == "Expedia")
                {
                    $return_status   = $otaRatePlanPushUpdate->expediaUpdate($bucketdata,$rateplan_data,$user_id);   
                    return $this->returnStatus($return_status,$bucket_id);
                }
                else if($ota_name == "Booking.com")
                {
                    $return_status   = $otaRatePlanPushUpdate->bookingdotcomUpdate($bucketdata,$rateplan_data,$user_id);
                    return $this->returnStatus($return_status,$bucket_id);
                }
                else if($ota_name == "Via.com")
                {
                    $return_status =  $otaRatePlanPushUpdate->viadotcomUpdate($bucketdata,$rateplan_data,$user_id);   
                    return $this->returnStatus($return_status,$bucket_id);
                }  
                else if($ota_name == "Travelguru")
                {
                    $return_status =  $otaRatePlanPushUpdate->travelguruUpdate($bucketdata,$rateplan_data,$user_id);   
                    return $this->returnStatus($return_status,$bucket_id);
                }
                else if($ota_name == "Airbnb")
                {
                    $return_status =  $otaRatePlanPushUpdate->airbnbUpdate($bucketdata,$rateplan_data,$user_id);
                    return $this->returnStatus($return_status,$bucket_id);
                }
                else if($ota_name == "Goomo")
                {
                    $return_status =  $otaRatePlanPushUpdate->goomoUpdate($bucketdata,$rateplan_data,$user_id);
                    return $this->returnStatus($return_status,$bucket_id);
                } 
                else if($ota_name == "Nodal")
                {
                    $return_status =  $otaRatePlanPushUpdate->nodalUpdate($bucketdata,$rateplan_data,$user_id);
                    return $this->returnStatus($return_status,$bucket_id);
                }
                else if($ota_name == "EaseMyTrip")
                {
                    $return_status =  $otaRatePlanPushUpdate->easemytripUpdate($bucketdata,$rateplan_data,$user_id);
                    return $this->returnStatus($return_status,$bucket_id);
                }
                else if($ota_name == "Paytm")
                {
                    $return_status =  $otaRatePlanPushUpdate->paytmUpdate($bucketdata,$rateplan_data,$user_id);
                   return $this->returnStatus($return_status,$bucket_id);
                }
            } 
        }
        else{
            echo "No Bucket Record Found!";
        }  
    }
    public function returnStatus($return_status,$bucket_id)
    {
        $check=true;
        foreach($return_status as $result)
        {
            $check=$check && $result["status"];   
        }
        if($check){
            DB::table('cm_ota_manage_inventory_bucket')->where('id', $bucket_id )
            ->update(['is_update' =>1]);
            return $return_status;
        }else{
            DB::table('cm_ota_manage_inventory_bucket')->where('id', $bucket_id )
            ->update(['is_update' =>3]);
            return $return_status;   
        }
    }
}