<?php
namespace App\Http\Controllers\invrateupdatecontrollers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Validator;
use DB;
use App\Inventory;
use App\OtaInventory;
use App\OtaRatePlan;
use App\CmOtaDetails;
use App\MasterHotelRatePlan;
use App\RatePlanLog;
use App\RateUpdateLog;
use App\LogTable;
use App\DerivedPlan;
use App\HotelInformation;
use App\BillingDetails;
use App\Http\Controllers\Controller;
use App\Http\Controllers\IpAddressService;
use App\Http\Controllers\invrateupdatecontrollers\GoibiboInvRateController;
use App\Http\Controllers\invrateupdatecontrollers\BookingdotcomInvRateController;
use App\Http\Controllers\invrateupdatecontrollers\EaseMyTripInvRateController;
use App\Http\Controllers\invrateupdatecontrollers\PaytmInvRateController;
use App\Http\Controllers\invrateupdatecontrollers\AgodaInvRateController;
use App\Http\Controllers\invrateupdatecontrollers\ExpediaInvRateController;
use App\Http\Controllers\invrateupdatecontrollers\ViaInvRateController;
use App\Http\Controllers\invrateupdatecontrollers\GoomoInvRateController;
use App\Http\Controllers\invrateupdatecontrollers\TravelguruInvRateController;
use App\Http\Controllers\invrateupdatecontrollers\AirbnbInvRateController;
use App\Http\Controllers\invrateupdatecontrollers\GetDataForRateController;
use App\Http\Controllers\invrateupdatecontrollers\FetchOtaDataForInvRateSyncController;
use App\Http\Controllers\invrateupdatecontrollers\HegInvRateController;
use App\Http\Controllers\invrateupdatecontrollers\IrctcInvRateController;
/**
 * This masterinvrateupdatecontroller manages all data ota and be inv,rate,sync,block all function
 * @auther ranjit
 * created date 21/02/19
 */
class MasterInvRateUpdateController extends Controller
{
    protected $goibiboInventoryRatePushUpdate,$ipService,$bookingdotcomInventoryRatePushUpdate;
    protected $easemytripInventoryRatePushUpdate,$paytmInventoryRatePushUpdate,$agodaInventoryRatePushUpdate;
    protected $expediaInventoryRatePushUpdate,$goomoInventoryRatePushUpdate,$travelguruInventoryRatePushUpdate;
    protected $getdata_curlreq,$inventoryService,$hegInventoryRatePushUpdate,$irctcInventoryRatePushUpdate;

    public function __construct(GoibiboInvRateController $goibiboInventoryRatePushUpdate,
    IpAddressService $ipService,
    BookingdotcomInvRateController $bookingdotcomInventoryRatePushUpdate,
    EaseMyTripInvRateController $easemytripInventoryRatePushUpdate,
    PaytmInvRateController $paytmInventoryRatePushUpdate,
    AgodaInvRateController $agodaInventoryRatePushUpdate,
    ExpediaInvRateController $expediaInventoryRatePushUpdate,
    ViaInvRateController $viaInventoryRatePushUpdate,
    GoomoInvRateController $goomoInventoryRatePushUpdate,
    TravelguruInvRateController  $travelguruInventoryRatePushUpdate,
    AirbnbInvRateController $airbnbInventoryRatePushUpdate,
    HegInvRateController $hegInventoryRatePushUpdate,
    GetDataForRateController $getdata_curlreq,FetchOtaDataForInvRateSyncController $inventoryService,
    IrctcInvRateController $irctcInventoryRatePushUpdate)
    {
       $this->goibiboInventoryRatePushUpdate        = $goibiboInventoryRatePushUpdate;
       $this->ipService                             = $ipService;
       $this->bookingdotcomInventoryRatePushUpdate  = $bookingdotcomInventoryRatePushUpdate;
       $this->easemytripInventoryRatePushUpdate     = $easemytripInventoryRatePushUpdate;
       $this->paytmInventoryRatePushUpdate          = $paytmInventoryRatePushUpdate;
       $this->agodaInventoryRatePushUpdate          = $agodaInventoryRatePushUpdate;
       $this->expediaInventoryRatePushUpdate        = $expediaInventoryRatePushUpdate;
       $this->viaInventoryRatePushUpdate            = $viaInventoryRatePushUpdate;
       $this->goomoInventoryRatePushUpdate          = $goomoInventoryRatePushUpdate;
       $this->travelguruInventoryRatePushUpdate     = $travelguruInventoryRatePushUpdate;
       $this->hegInventoryRatePushUpdate            = $hegInventoryRatePushUpdate;
       $this->airbnbInventoryRatePushUpdate         = $airbnbInventoryRatePushUpdate;
       $this->getdata_curlreq                       = $getdata_curlreq;
       $this->inventoryService                      = $inventoryService;
       $this->irctcInventoryRatePushUpdate         = $irctcInventoryRatePushUpdate;
    }
    //inventory update rules for single update and sync inventory
    private $inv_rules = array(
        'hotel_id' => 'required | numeric',
    );
    private $inv_messages = [
        'hotel_id.required' => 'Hotel id is required.',
        'hotel_id.numeric' => 'Hotel id should be numeric.',
            ];
    //inventory update rules for block inventory update
    private $rules = array(
        'hotel_id' => 'required',
        'room_type_id' => 'required',
        'date_from' => 'required',
        'date_to' => 'required'
    );
    private $messages = [
        'hotel_id.required' => 'The hotel id is required.',
        'room_type_id.required' => 'The room type id is required.',
        'date_from.required' => 'The date from is required.',
        'date_to.required' => 'The date to is required.'
            ];
    private $rules_unblock = array(
        'hotel_id' => 'required',
        'room_type_id' => 'required',
        'from_date' => 'required',
        'to_date' => 'required'
    );
    private $messages_unblock  = [
        'hotel_id.required' => 'The hotel id is required.',
        'room_type_id.required' => 'The room type id is required.',
        'from_date.required' => 'The from date is required.',
        'to_date.required' => 'The to date is required.'
            ];
    private $rules_unblock_rate = array(
        'hotel_id' => 'required',
        'room_type_id' => 'required',
        'rate_plan_id' => 'required',
        'from_date' => 'required',
        'to_date' => 'required'
    );
    private $messages_unblock_rate  = [
        'hotel_id.required' => 'The hotel id is required.',
        'room_type_id.required' => 'The room type id is required.',
        'rate_plan_id.required' => 'The rate plan id is required.',
        'from_date.required' => 'The from date is required.',
        'to_date.required' => 'The to date is required.'
            ];
    //inventory update rules for bulk inventory update
    private $blk_rules = array(
        'no_of_rooms' => 'required | numeric',
        'date_from' => 'required ',
        'date_to' => 'required ',
        'hotel_id' => 'required | numeric',
        'room_type_id' => 'required | numeric'
    );
    private $blk_messages = [
        'no_of_rooms.required' => 'No of rooms required.',
        'date_from.required' => 'From date is required.',
        'date_to.required' => 'To date is required.',
        'hotel_id.required' => 'Hotel id is required.',
        'room_type_id.required' => 'Room type id is required.',
        'hotel_id.numeric' => 'Hotel id should be numeric.',
        'no_of_rooms.numeric' => 'No of rooms should be numeric.',
        'date_from.date_format' => 'Date format should be Y-m-d',
        'date_to.date_format' => 'Date format should be Y-m-d'
            ];
    //rate update rules
    private $rates_rules = array(
        'hotel_id' => 'required | numeric',
    );
    private $rates_messages = [
        'hotel_id.required' => 'Hotel id is required.',
        'hotel_id.numeric' => 'Hotel id should be numeric.',
            ];
    //single inventory update for be and ota(multi,single).
    public function singleInventoryUpdate(Request $request)
    {
        $failure_message='Inventory sync failed';
        $validator = Validator::make($request->all(),$this->inv_rules,$this->inv_messages);
        if ($validator->fails())
        {
            return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
        }
        $data=$request->all();
        $inventory=$data['inv'];
        $imp_data=$this->impDate($data,$request);//used to get user id and client ip.
        $resp=array();
        foreach($imp_data['ota_id'] as $otaid){
                $ota_details = CmOtaDetails::select('*')
                ->where('hotel_id', '=' ,$imp_data['hotel_id'])
                ->where('ota_id','=',$otaid)
                ->where('is_active', '=' ,1)
                ->first();
                if($ota_details)
                {
                    $bucketdata = $this->creatingBucketdata($ota_details,$imp_data['hotel_id'],$imp_data['client_ip'],$imp_data['user_id']);//calling the function for creating bucket data
                }
                switch($bucketdata['ota_name'])
                {
                    case "Goibibo":
                                        try{
                                        $resp[]   =   $this->goibiboInventoryRatePushUpdate->singleInvUpdate($bucketdata['bucket_data'],$inventory,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                        }
                                        catch(Execption $resp)
                                        {

                                        }
                                        break;
                    case "Booking.com":
                                        try{
                                        $resp[]   =   $this->bookingdotcomInventoryRatePushUpdate->singleInvUpdate($bucketdata['bucket_data'],$inventory,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                        }
                                        catch(Execption $resp)
                                        {

                                        }
                                        break;
                    case "EaseMyTrip":
                                        try{
                                        $resp[]   =   $this->easemytripInventoryRatePushUpdate->singleInvUpdate($bucketdata['bucket_data'],$inventory,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                        }
                                        catch(Execption $resp)
                                        {

                                        }
                                        break;
                    case "Paytm":
                                        try{
                                        $resp[]   =   $this->paytmInventoryRatePushUpdate->singleInvUpdate($bucketdata['bucket_data'],$inventory,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                        }
                                        catch(Execption $resp)
                                        {

                                        }
                                        break;
                    case "Agoda":
                                        try{
                                        $resp[]   =   $this->agodaInventoryRatePushUpdate->singleInvUpdate($bucketdata['bucket_data'],$inventory,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                        }
                                        catch(Execption $resp)
                                        {

                                        }
                                        break;
                    case "Expedia":
                                        try{
                                        $resp[]   =   $this->expediaInventoryRatePushUpdate->singleInvUpdate($bucketdata['bucket_data'],$inventory,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                        }
                                        catch(Execption $resp)
                                        {

                                        }
                                        break;
                    case "Via.com":
                                        try{
                                        $resp[]   =   $this->viaInventoryRatePushUpdate->singleInvUpdate($bucketdata['bucket_data'],$inventory,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                        }
                                        catch(Execption $resp)
                                        {

                                        }
                                        break;
                    case "Goomo":
                                        try{
                                        $resp[]   =   $this->goomoInventoryRatePushUpdate->singleInvUpdate($bucketdata['bucket_data'],$inventory,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                        }
                                        catch(Execption $resp)
                                        {

                                        }
                                        break;
                    case "Travelguru":
                                        try{
                                        $resp[]   =   $this->travelguruInventoryRatePushUpdate->singleInvUpdate($bucketdata['bucket_data'],$inventory,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                        }
                                        catch(Execption $resp)
                                        {

                                        }
                                        break;
                    case "Airbnb":
                                        try{
                                        $resp[]   =   $this->airbnbInventoryRatePushUpdate->singleInvUpdate($bucketdata['bucket_data'],$inventory,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                        }
                                        catch(Execption $resp)
                                        {

                                        }
                                        break;
                    case "HappyEasyGo":
                                        try{
                                        $resp[]   =   $this->hegInventoryRatePushUpdate->singleInvUpdate($bucketdata['bucket_data'],$inventory,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                        }
                                        catch(Execption $resp)
                                        {

                                        }
                                        break;
                    case "IRCTC":
                                        try{
                                        $resp[]   =   $this->irctcInventoryRatePushUpdate->singleInvUpdate($bucketdata['bucket_data'],$inventory,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                        }
                                        catch(Execption $resp)
                                        {

                                        }
                                        break;

                    default       :    $resp[]=response()->json(array("status"=>0,"message"=>'No ota choosen for sync please choose!')) ;
                }
        }
        return $resp;
    }
     //inventory sync for be and ota(multi,single).
    public function syncInvUpdate(Request $request)
    {
        $failure_message='Inventory sync failed';
        $validator = Validator::make($request->all(),$this->inv_rules,$this->inv_messages);
        if ($validator->fails())
        {
            return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
        }
        $data=$request->all();
        $data['room_type_id']=(int)($data['room_type_id']);
        $from_date=date('Y-m-d',strtotime($data['from_date']));
        $to_date=date('Y-m-d',strtotime($from_date."+".$data['duration']."days"));
        $data['inv']=$this->inventoryService->getInventoryBySourceOta($data['source_ota_name'],$data['room_type_id'],$from_date,$to_date,0);
        $inventory=$data;
        $imp_data=$this->impDate($data,$request);//used to get user id and client ip.
        $resp=array();
        foreach($imp_data['ota_id'] as $otaid){

            $ota_details = CmOtaDetails::select('*')
            ->where('hotel_id', '=' ,$imp_data['hotel_id'])
            ->where('ota_id','=',$otaid)
            ->where('is_active', '=' ,1)
            ->first();
            if($ota_details)
            {
                $bucketdata = $this->creatingBucketdata($ota_details,$imp_data['hotel_id'],$imp_data['client_ip'],$imp_data['user_id']);
            }
            switch($bucketdata['ota_name'])
                {
                    case "Goibibo":
                                        try{

                                            $resp[]   =   $this->goibiboInventoryRatePushUpdate->inventorySycUpdate($bucketdata['bucket_data'],$inventory,$bucketdata['auth_parameter'],$bucketdata['commonUrl'],$from_date,$to_date);
                                            }
                                        catch(Exception $resp)
                                        {

                                        }
                                        break;
                     case "Booking.com":
                                        try{
                                            $resp[]   =   $this->bookingdotcomInventoryRatePushUpdate->inventorySycUpdate($bucketdata['bucket_data'],$inventory,$bucketdata['auth_parameter'],$bucketdata['commonUrl'],$from_date,$to_date);
                                            }
                                        catch(Exception $resp)
                                        {

                                        }
                                        break;
                    case "EaseMyTrip":
                                        try{
                                            $resp[]   =   $this->easemytripInventoryRatePushUpdate->inventorySycUpdate($bucketdata['bucket_data'],$inventory,$bucketdata['auth_parameter'],$bucketdata['commonUrl'],$from_date,$to_date);
                                            }
                                        catch(Exception $resp)
                                        {

                                        }
                                        break;
                    case "Paytm":
                                        try{
                                            $resp[]   =   $this->paytmInventoryRatePushUpdate->inventorySycUpdate($bucketdata['bucket_data'],$inventory,$bucketdata['auth_parameter'],$bucketdata['commonUrl'],$from_date,$to_date);
                                            }
                                        catch(Exception $resp)
                                        {

                                        }
                                        break;
                    case "Agoda":
                                        try{
                                            $resp[]   =   $this->agodaInventoryRatePushUpdate->inventorySycUpdate($bucketdata['bucket_data'],$inventory,$bucketdata['auth_parameter'],$bucketdata['commonUrl'],$from_date,$to_date,$from_date,$to_date);
                                            }
                                        catch(Exception $resp)
                                        {

                                        }
                                        break;
                    case "Expedia":
                                        try{
                                            $resp[]   =   $this->expediaInventoryRatePushUpdate->inventorySycUpdate($bucketdata['bucket_data'],$inventory,$bucketdata['auth_parameter'],$bucketdata['commonUrl'],$from_date,$to_date,$from_date,$to_date);
                                            }
                                        catch(Exception $resp)
                                        {

                                        }
                                        break;
                    case "Via.com":
                                        try{
                                            $resp[]   =   $this->viaInventoryRatePushUpdate->inventorySycUpdate($bucketdata['bucket_data'],$inventory,$bucketdata['auth_parameter'],$bucketdata['commonUrl'],$from_date,$to_date);
                                            }
                                        catch(Exception $resp)
                                        {

                                        }
                                        break;
                    case "Goomo":
                                        try{
                                            $resp[]   =   $this->goomoInventoryRatePushUpdate->inventorySycUpdate($bucketdata['bucket_data'],$inventory,$bucketdata['auth_parameter'],$bucketdata['commonUrl'],$from_date,$to_date);
                                            }
                                        catch(Exception $resp)
                                        {

                                        }
                                        break;
                    case "Travelguru":
                                        try{
                                            $resp[]   =   $this->travelguruInventoryRatePushUpdate->inventorySycUpdate($bucketdata['bucket_data'],$inventory,$bucketdata['auth_parameter'],$bucketdata['commonUrl'],$from_date,$to_date);
                                            }
                                        catch(Exception $resp)
                                        {

                                        }
                                        break;
                    case "Airbnb":
                                        try{
                                            $resp[]   =   $this->airbnbInventoryRatePushUpdate->inventorySycUpdate($bucketdata['bucket_data'],$inventory,$bucketdata['auth_parameter'],$bucketdata['commonUrl'],$from_date,$to_date);
                                            }
                                        catch(Exception $resp)
                                        {

                                        }
                                        break;
                    case "HappyEasyGo":
                                        try{
                                            $resp[]   =
                                            $this->hegInventoryRatePushUpdate->inventorySycUpdate($bucketdata['bucket_data'],$inventory,$bucketdata['auth_parameter'],$bucketdata['commonUrl'],$from_date,$to_date);
                                            }
                                        catch(Exception $resp)
                                        {

                                        }
                                        break;
                    case "IRCTC":
                                        try{
                                            $resp[]   =
                                            $this->irctcInventoryRatePushUpdate->inventorySycUpdate($bucketdata['bucket_data'],$inventory,$bucketdata['auth_parameter'],$bucketdata['commonUrl'],$from_date,$to_date);
                                            }
                                        catch(Exception $resp)
                                        {

                                        }
                                        break;

                    default       :     $resp[] = array("status"=>0,"message"=>'No ota choosen for sync please choose!');
                }
        }
        return $resp;
    }
    //block inventory update for be and ota(multi,single).
    public function blockInventoryUpdate(Request $request)
    {
        $failure_message='Block inventry operation failed';
        $validator = Validator::make($request->all(),$this->rules,$this->messages);
        if ($validator->fails())
        {
            return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
        }
        $data=$request->all();
        $room_types=$request->input('room_type_id');
        $fmonth=explode('-',$data['date_from']);//for removing extra o from month and remove this code after mobile app update
        if(strlen($fmonth[1]) == 3)
        {
            $fmonth[1]=ltrim($fmonth[1],0);
        }

        $data['date_from']=implode('-',$fmonth);
        $tmonth=explode('-',$data['date_to']);
        if(strlen($tmonth[1]) == 3)
        {
            $tmonth[1]=ltrim($tmonth[1],0);
        }
        $data['date_to']=implode('-',$tmonth);

        $data['date_from']=date('Y-m-d',strtotime($data['date_from']));
        $data['date_to']=date('Y-m-d',strtotime($data['date_to']));
        $imp_data=$this->impDate($data,$request);//used to get user id and client ip.
        $data["client_ip"]=$imp_data['client_ip'];
        $data["user_id"]=$imp_data['user_id'];
        $resp=array();
        foreach($imp_data['ota_id'] as $otaid){
            $ota_details = CmOtaDetails::select('*')
            ->where('hotel_id', '=' ,$imp_data['hotel_id'])
            ->where('ota_id','=',$otaid)
            ->where('is_active', '=' ,1)
            ->first();
            $bucketdata = $this->creatingBucketdata($ota_details,$imp_data['hotel_id'],$imp_data['client_ip'],$imp_data['user_id']);
            switch($bucketdata['ota_name'])
            {
                case "Goibibo":
                                        try{
                                        $resp[]  =   $this->goibiboInventoryRatePushUpdate->blockInventoryUpdate($bucketdata['bucket_data'],$room_types,$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                        }
                                        catch(Exception $resp)
                                        {

                                        }
                                        break;
                case "Booking.com":
                                        try{
                                        $resp[]  =   $this->bookingdotcomInventoryRatePushUpdate->blockInventoryUpdate($bucketdata['bucket_data'],$room_types,$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                        }
                                        catch(Exception $resp)
                                        {

                                        }
                                        break;
                case "EaseMyTrip":
                                        try{
                                        $resp[]  =   $this->easemytripInventoryRatePushUpdate->blockInventoryUpdate($bucketdata['bucket_data'],$room_types,$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                        }
                                        catch(Exception $resp)
                                        {

                                        }
                                        break;
                case "Paytm":
                                        try{
                                        $resp[]  =   $this->paytmInventoryRatePushUpdate->blockInventoryUpdate($bucketdata['bucket_data'],$room_types,$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                        }
                                        catch(Exception $resp)
                                        {

                                        }
                                        break;
                case "Agoda":
                                        try{
                                            $resp[]  =   $this->agodaInventoryRatePushUpdate->blockInventoryUpdate($bucketdata['bucket_data'],$room_types,$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                        }
                                        catch(Exception $resp)
                                        {

                                        }
                                        break;
                case "Expedia":
                                        try{
                                        $resp[]  =   $this->expediaInventoryRatePushUpdate->blockInventoryUpdate($bucketdata['bucket_data'],$room_types,$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                        }
                                        catch(Exception $resp)
                                        {

                                        }
                                        break;
                case "Via.com":
                                        try{
                                        $resp[]  =   $this->viaInventoryRatePushUpdate->blockInventoryUpdate($bucketdata['bucket_data'],$room_types,$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                        }
                                        catch(Exception $resp)
                                        {

                                        }
                                        break;
                case "Goomo":
                                        try{
                                        $resp[]  =   $this->goomoInventoryRatePushUpdate->blockInventoryUpdate($bucketdata['bucket_data'],$room_types,$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                        }
                                        catch(Exception $resp)
                                        {

                                        }
                                        break;
                case "Travelguru":
                                        try{
                                        $resp[]  =   $this->travelguruInventoryRatePushUpdate->blockInventoryUpdate($bucketdata['bucket_data'],$room_types,$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                        }
                                        catch(Exception $resp)
                                        {

                                        }
                                        break;
                case "Airbnb":
                                        try{
                                        $resp[]  =   $this->airbnbInventoryRatePushUpdate->blockInventoryUpdate($bucketdata['bucket_data'],$room_types,$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                        }
                                        catch(Exception $resp)
                                        {

                                        }
                                        break;
                case "HappyEasyGo":
                                        try{
                                        $resp[]  =   $this->hegInventoryRatePushUpdate->blockInventoryUpdate($bucketdata['bucket_data'],$room_types,$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                        }
                                        catch(Exception $resp)
                                        {

                                        }
                                        break;
                                        
                case "IRCTC":
                                        try{
                                        $resp[]  =   $this->irctcInventoryRatePushUpdate->blockInventoryUpdate($bucketdata['bucket_data'],$room_types,$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                        }
                                        catch(Exception $resp)
                                        {

                                        }
                                        break;
                default       :     $resp[] = array("status"=>0,"message"=>'No ota choosen for sync please choose!');
            }
        }
        return $resp;
    }
     //bulk inventory update for be and ota(multi,single).
    public function bulkInvUpdate(Request $request)
    {
        $logModel = new LogTable();
        $failure_message='Inventory updation failed';
        $validator = Validator::make($request->all(),$this->blk_rules,$this->blk_messages);
        if ($validator->fails())
        {
            return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
        }
        $data=$request->all();
        $fmonth=explode('-',$data['date_from']);//for removing extra o from month and remove this code after mobile app update
        if(strlen($fmonth[1]) == 3)
        {
            $fmonth[1]=ltrim($fmonth[1],0);
            $fmonth[2]= $fmonth[2] < 10 ? "0".$fmonth[2] : $fmonth[2];
        }
        $data['date_from']=implode('-',$fmonth);
        $tmonth=explode('-',$data['date_to']);
        if(strlen($tmonth[1]) == 3)
        {
            $tmonth[1]=ltrim($tmonth[1],0);
            $tmonth[2]= $tmonth[2] < 10 ? "0".$tmonth[2] : $tmonth[2];
        }
        $data['date_to']=implode('-',$tmonth);
        $data['date_from']=date('Y-m-d',strtotime($data['date_from']));
        $data['date_to']=date('Y-m-d',strtotime($data['date_to']));
        $imp_data=$this->impDate($data,$request);//used to get user id and client ip.
        $data["client_ip"]=$imp_data['client_ip'];
        $data["user_id"]=$imp_data['user_id'];
        $resp=array();
        foreach($imp_data['ota_id'] as $otaid){
            $ota_details = CmOtaDetails::select('*')
            ->where('hotel_id', '=' ,$imp_data['hotel_id'])
            ->where('ota_id','=',$otaid)
            ->where('is_active', '=' ,1)
            ->first();
            $bucketdata = $this->creatingBucketdata($ota_details,$imp_data['hotel_id'],$imp_data['client_ip'],$imp_data['user_id']);
            switch($bucketdata['ota_name'])
            {
                case "Goibibo":
                                    try{
                                        $resp[]  =   $this->goibiboInventoryRatePushUpdate->bulkInvUpdate($bucketdata['bucket_data'],$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                case "Booking.com":
                                    try{
                                        $resp[] =   $this->bookingdotcomInventoryRatePushUpdate->bulkInvUpdate($bucketdata['bucket_data'],$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                case "EaseMyTrip":
                                    try{
                                        $resp[] =   $this->easemytripInventoryRatePushUpdate->bulkInvUpdate($bucketdata['bucket_data'],$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                case "Paytm":
                                    try{
                                        $resp[] =   $this->paytmInventoryRatePushUpdate->bulkInvUpdate($bucketdata['bucket_data'],$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                case "Agoda":
                                    try{
                                        $resp[] =   $this->agodaInventoryRatePushUpdate->bulkInvUpdate($bucketdata['bucket_data'],$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                case "Expedia":
                                    try{
                                        $resp[] =   $this->expediaInventoryRatePushUpdate->bulkInvUpdate($bucketdata['bucket_data'],$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                case "Via.com":
                                    try{
                                        $resp[] =   $this->viaInventoryRatePushUpdate->bulkInvUpdate($bucketdata['bucket_data'],$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                case "Goomo":
                                    try{
                                        $resp[] =   $this->goomoInventoryRatePushUpdate->bulkInvUpdate($bucketdata['bucket_data'],$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                case "Travelguru":
                                    try{
                                        $resp[] =   $this->travelguruInventoryRatePushUpdate->bulkInvUpdate($bucketdata['bucket_data'],$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                case "Airbnb":
                                    try{
                                        $resp[] =   $this->airbnbInventoryRatePushUpdate->bulkInvUpdate($bucketdata['bucket_data'],$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                case "HappyEasyGo":
                                    try{
                                        $resp[] =   $this->hegInventoryRatePushUpdate->bulkInvUpdate($bucketdata['bucket_data'],$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                case "IRCTC":
                                    try{
                                        $resp[] =   $this->irctcInventoryRatePushUpdate->bulkInvUpdate($bucketdata['bucket_data'],$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                default       :     $resp[] = array("status"=>0,"message"=>'No ota choosen for sync please choose!');
            }
        }
        return $resp;
    }
     //sync rate for be and ota(multi,single).
    public function syncRateUpdate(Request $request)
    {
        $failure_message='Rates sync failed';
        $validator = Validator::make($request->all(),$this->rates_rules,$this->rates_messages);
        if ($validator->fails())
        {
            return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
        }
        $data=$request->all();
        $data['room_type_id']=(int)($data['room_type_id']);
        $from_date=date('Y-m-d',strtotime($data['from_date']));
        $to_date=date('Y-m-d',strtotime($from_date."+".$data['duration']."days"));
        $data['rates']=$this->inventoryService->getRateBySourceOta($data['source_ota_name'],$data['room_type_id'],$data['rate_plan_id'],$from_date,$to_date);
        $rates_data=$data;

        $imp_data=$this->impDate($data,$request);//used to get user id and client ip.
        $resp=array();

        foreach($imp_data['ota_id'] as $otaid){
            $ota_details = CmOtaDetails::select('*')
            ->where('hotel_id', '=' ,$imp_data['hotel_id'])
            ->where('ota_id','=',$otaid)
            ->where('is_active', '=' ,1)
            ->first();
            if($ota_details)
            {
                $bucketdata = $this->creatingBucketdata($ota_details,$imp_data['hotel_id'],$imp_data['client_ip'],$imp_data['user_id']);
            }
            switch($bucketdata['ota_name'])
                {
                    case "Goibibo":
                                        try{
                                            $resp[]   =   $this->goibiboInventoryRatePushUpdate->rateSyncUpdate($bucketdata['bucket_data'],$rates_data,$bucketdata['auth_parameter'],$bucketdata['commonUrl'],$from_date,$to_date);
                                        }
                                        catch(Exception $resp)
                                        {

                                        }
                                        break;
                    case "Booking.com":
                                        try{
                                            $resp[]   =   $this->bookingdotcomInventoryRatePushUpdate->rateSyncUpdate($bucketdata['bucket_data'],$rates_data,$bucketdata['auth_parameter'],$bucketdata['commonUrl'],$from_date,$to_date);
                                        }
                                        catch(Exception $resp)
                                        {

                                        }
                                        break;
                    case "EaseMyTrip":
                                        try{
                                            $resp[]   =   $this->easemytripInventoryRatePushUpdate->rateSyncUpdate($bucketdata['bucket_data'],$rates_data,$bucketdata['auth_parameter'],$bucketdata['commonUrl'],$from_date,$to_date);
                                        }
                                        catch(Exception $resp)
                                        {

                                        }
                                        break;
                    case "Paytm":
                                        try{
                                            $resp[]   =   $this->paytmInventoryRatePushUpdate->rateSyncUpdate($bucketdata['bucket_data'],$rates_data,$bucketdata['auth_parameter'],$bucketdata['commonUrl'],$from_date,$to_date);
                                        }
                                        catch(Exception $resp)
                                        {

                                        }
                                        break;
                    case "Agoda":
                                        try{
                                            $resp[]   =   $this->agodaInventoryRatePushUpdate->rateSyncUpdate($bucketdata['bucket_data'],$rates_data,$bucketdata['auth_parameter'],$bucketdata['commonUrl'],$from_date,$to_date);
                                        }
                                        catch(Exception $resp)
                                        {

                                        }
                                        break;
                    case "Expedia":
                                        try{
                                            $resp[]   =   $this->expediaInventoryRatePushUpdate->rateSyncUpdate($bucketdata['bucket_data'],$rates_data,$bucketdata['auth_parameter'],$bucketdata['commonUrl'],$from_date,$to_date,$from_date,$to_date);
                                        }
                                        catch(Exception $resp)
                                        {

                                        }
                                        break;
                    case "Via.com":
                                        try{
                                            $resp[]   =   $this->viaInventoryRatePushUpdate->rateSyncUpdate($bucketdata['bucket_data'],$rates_data,$bucketdata['auth_parameter'],$bucketdata['commonUrl'],$from_date,$to_date,$from_date,$to_date);
                                        }
                                        catch(Exception $resp)
                                        {

                                        }
                                        break;
                    case "Goomo":
                                        try{
                                            $resp[]   =   $this->goomoInventoryRatePushUpdate->rateSyncUpdate($bucketdata['bucket_data'],$rates_data,$bucketdata['auth_parameter'],$bucketdata['commonUrl'],$from_date,$to_date,$from_date,$to_date);
                                        }
                                        catch(Exception $resp)
                                        {

                                        }
                                        break;
                    case "Travelguru":
                                        try{
                                            $resp[]   =   $this->travelguruInventoryRatePushUpdate->rateSyncUpdate($bucketdata['bucket_data'],$rates_data,$bucketdata['auth_parameter'],$bucketdata['commonUrl'],$from_date,$to_date);
                                        }
                                        catch(Exception $resp)
                                        {

                                        }
                                        break;
                    case "Airbnb":
                                        try{
                                            $resp[]   =   $this->airbnbInventoryRatePushUpdate->rateSyncUpdate($bucketdata['bucket_data'],$rates_data,$bucketdata['auth_parameter'],$bucketdata['commonUrl'],$from_date,$to_date);
                                        }
                                        catch(Exception $resp)
                                        {

                                        }
                                        break;
                    case "HappyEasyGo":
                                        try{
                                            $resp[]   =   $this->hegInventoryRatePushUpdate->rateSyncUpdate($bucketdata['bucket_data'],$rates_data,$bucketdata['auth_parameter'],$bucketdata['commonUrl'],$from_date,$to_date);
                                        }
                                        catch(Exception $resp)
                                        {

                                        }
                                        break;
                    case "IRCTC":
                                        try{
                                            $resp[]   =   $this->irctcInventoryRatePushUpdate->rateSyncUpdate($bucketdata['bucket_data'],$rates_data,$bucketdata['auth_parameter'],$bucketdata['commonUrl'],$from_date,$to_date);
                                        }
                                        catch(Exception $resp)
                                        {

                                        }
                                        break;

                    default       :     $resp[] = array("status"=>0,"message"=>'No ota choosen for sync please choose!');
                }
        }
        return $resp;
    }
     //single rate update for be and ota(multi,single).
    public function singleRateUpdate(Request $request)
    {
        $data=$request->all();
        $rates_data=$data['rates'];
        $imp_data=$this->impDate($data,$request);//used to get user id and client ip.
        $resp=array();
        foreach($imp_data['ota_id'] as $otaid){
            $ota_details = CmOtaDetails::select('*')
            ->where('hotel_id', '=' ,$imp_data['hotel_id'])
            ->where('ota_id','=',$otaid)
            ->where('is_active', '=' ,1)
            ->first();
            if($ota_details){
                $bucketdata = $this->creatingBucketdata($ota_details,$imp_data['hotel_id'],$imp_data['client_ip'],$imp_data['user_id']);
            }
            switch($bucketdata['ota_name']){
                    case "Goibibo":
                                            try{
                                                $resp[]   =   $this->goibiboInventoryRatePushUpdate->singleRateUpdate($bucketdata['bucket_data'],$rates_data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                            }
                                            catch(Exception $resp)
                                            {

                                            }
                                            break;
                    case "Booking.com":
                                            try{
                                                $resp[]   =   $this->bookingdotcomInventoryRatePushUpdate->singleRateUpdate($bucketdata['bucket_data'],$rates_data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                            }
                                            catch(Exception $resp)
                                            {

                                            }
                                            break;
                    case "EaseMyTrip":
                                            try{
                                                $resp[]   =   $this->easemytripInventoryRatePushUpdate->singleRateUpdate($bucketdata['bucket_data'],$rates_data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                            }
                                            catch(Exception $resp)
                                            {

                                            }
                                            break;
                    case "Paytm":
                                            try{
                                                $resp[]   =   $this->paytmInventoryRatePushUpdate->singleRateUpdate($bucketdata['bucket_data'],$rates_data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                            }
                                            catch(Exception $resp)
                                            {

                                            }
                                            break;
                    case "Agoda":
                                            try{
                                                $resp[]   =   $this->agodaInventoryRatePushUpdate->singleRateUpdate($bucketdata['bucket_data'],$rates_data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                            }
                                            catch(Exception $resp)
                                            {

                                            }
                                            break;
                    case "Expedia":
                                            try{
                                                $resp[]   =   $this->expediaInventoryRatePushUpdate->singleRateUpdate($bucketdata['bucket_data'],$rates_data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                            }
                                            catch(Exception $resp)
                                            {

                                            }
                                            break;
                    case "Via.com":
                                            try{
                                                $resp[]   =   $this->viaInventoryRatePushUpdate->singleRateUpdate($bucketdata['bucket_data'],$rates_data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                            }
                                            catch(Exception $resp)
                                            {

                                            }
                                            break;
                    case "Goomo":
                                            try{
                                                $resp[]   =   $this->goomoInventoryRatePushUpdate->singleRateUpdate($bucketdata['bucket_data'],$rates_data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                            }
                                            catch(Exception $resp)
                                            {

                                            }
                                            break;
                    case "Travelguru":
                                            try{
                                                $resp[]   =   $this->travelguruInventoryRatePushUpdate->singleRateUpdate($bucketdata['bucket_data'],$rates_data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                            }
                                            catch(Exception $resp)
                                            {

                                            }
                                            break;
                    case "Airbnb":
                                            try{
                                                $resp[]   =   $this->airbnbInventoryRatePushUpdate->singleRateUpdate($bucketdata['bucket_data'],$rates_data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                            }
                                            catch(Exception $resp)
                                            {

                                            }
                                            break;
                    case "HappyEasyGo":
                                            try{
                                                $resp[]   =   $this->hegInventoryRatePushUpdate->singleRateUpdate($bucketdata['bucket_data'],$rates_data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                            }
                                            catch(Exception $resp)
                                            {

                                            }
                                            break;
                    case "IRCTC":
                                            try{
                                                $resp[]   =   $this->irctcInventoryRatePushUpdate->singleRateUpdate($bucketdata['bucket_data'],$rates_data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                            }
                                            catch(Exception $resp)
                                            {

                                            }
                                            break;
                    default       :     $resp[] = array("status"=>0,"message"=>'No ota choosen for sync please choose!');
             }
        }
        return $resp;
    }
     //block rate update for be and ota(multi,single).
     public function blockRateUpdate(Request $request)
     {
         $failure_message='Block inventry operation failed';
         $validator = Validator::make($request->all(),$this->rules,$this->messages);
         if ($validator->fails())
         {
             return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
         }
         $data=$request->all();
         $room_types=$request->input('room_type_id');
         $fmonth=explode('-',$data['date_from']);//for removing extra o from month and remove this code after mobile app update
         if(strlen($fmonth[1]) == 3)
         {
             $fmonth[1]=ltrim($fmonth[1],0);
         }

         $data['date_from']=implode('-',$fmonth);
         $tmonth=explode('-',$data['date_to']);
         if(strlen($tmonth[1]) == 3)
         {
             $tmonth[1]=ltrim($tmonth[1],0);
         }
         $data['date_to']=implode('-',$tmonth);

         $data['date_from']=date('Y-m-d',strtotime($data['date_from']));
         $data['date_to']=date('Y-m-d',strtotime($data['date_to']));
         $imp_data=$this->impDate($data,$request);//used to get user id and client ip.
         $resp=array();
         foreach($imp_data['ota_id'] as $otaid){
             $ota_details = CmOtaDetails::select('*')
             ->where('hotel_id', '=' ,$imp_data['hotel_id'])
             ->where('ota_id','=',$otaid)
             ->where('is_active', '=' ,1)
             ->first();

             $bucketdata = $this->creatingBucketdata($ota_details,$imp_data['hotel_id'],$imp_data['client_ip'],$imp_data['user_id']);
             switch($bucketdata['ota_name'])
             {
                 case "Goibibo":
                                         try{
                                          $resp[]  =   $this->goibiboInventoryRatePushUpdate->blockRateUpdate($bucketdata['bucket_data'],$room_types,$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                         }
                                         catch(Exception $resp)
                                         {

                                         }
                                         break;
                 case "Booking.com":
                                         try{
                                          $resp[]  =   $this->bookingdotcomInventoryRatePushUpdate->blockRateUpdate($bucketdata['bucket_data'],$room_types,$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                         }
                                         catch(Exception $resp)
                                         {

                                         }
                                         break;
                 case "EaseMyTrip":
                                         try{

                                         $resp[]  =   $this->easemytripInventoryRatePushUpdate->blockRateUpdate($bucketdata['bucket_data'],$room_types,$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                         }
                                         catch(Exception $resp)
                                         {

                                         }
                                         break;
                 case "Paytm":
                                         try{
                                           $resp[]  =   $this->paytmInventoryRatePushUpdate->blockRateUpdate($bucketdata['bucket_data'],$room_types,$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                         }
                                         catch(Exception $resp)
                                         {

                                         }
                                         break;
                 case "Agoda":
                                         try{
                                              $resp[]  =   $this->agodaInventoryRatePushUpdate->blockRateUpdate($bucketdata['bucket_data'],$room_types,$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                         }
                                         catch(Exception $resp)
                                         {

                                         }
                                         break;
                 case "Expedia":
                                         try{
                                          $resp[]  =   $this->expediaInventoryRatePushUpdate->blockRateUpdate($bucketdata['bucket_data'],$room_types,$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                         }
                                         catch(Exception $resp)
                                         {

                                         }
                                         break;
                 case "Via.com":
                                         try{
                                          $resp[]  =   $this->viaInventoryRatePushUpdate->blockRateUpdate($bucketdata['bucket_data'],$room_types,$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                         }
                                         catch(Exception $resp)
                                         {

                                         }
                                         break;
                 case "Goomo":
                                         try{
                                           $resp[]  =   $this->goomoInventoryRatePushUpdate->blockRateUpdate($bucketdata['bucket_data'],$room_types,$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                         }
                                         catch(Exception $resp)
                                         {

                                         }
                                         break;
                 case "Travelguru":
                                         try{
                                         $resp[]  =   $this->travelguruInventoryRatePushUpdate->blockRateUpdate($bucketdata['bucket_data'],$room_types,$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                         }
                                         catch(Exception $resp)
                                         {

                                         }
                                         break;
                 case "HappyEasyGo":
                                         try{
                                         $resp[]  =   $this->hegInventoryRatePushUpdate->blockRateUpdate($bucketdata['bucket_data'],$room_types,$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                         }
                                         catch(Exception $resp)
                                         {

                                         }
                                         break;
                case "IRCTC":
                                        try{
                                        $resp[]  =   $this->irctcInventoryRatePushUpdate->blockRateUpdate($bucketdata['bucket_data'],$room_types,$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                        }
                                        catch(Exception $resp)
                                        {

                                        }
                                        break;

                 default       :     $resp[] = array("status"=>0,"message"=>'No ota choosen for sync please choose!');
             }
         }
         return $resp;
     }
    //rate unblock option
    public function bulkRateUpdate(Request $request)
    {
        $data=$request->all();
        $prv_data=array();
        $imp_data=$this->impDate($data,$request);//used to get user id and client ip.
        $price=MasterHotelRatePlan::select('min_price','max_price')->where('hotel_id',$imp_data['hotel_id'])->where('room_type_id',$data["room_type_id"])->where('rate_plan_id',$data["rate_plan_id"])->first();
        if($imp_data['hotel_id'] == 1993){
            var_dump($imp_data['hotel_id'],$data["room_type_id"],$data["rate_plan_id"]);
        }
        $bp=0;
        $mp=0;
        if($data['bar_price'] >= $price->min_price && $data['bar_price'] < $price->max_price)
        {
            $bp=1;
        }
        if($bp==0)
        {
            $res=array('status'=>0,'message'=>"price should be equal or greater than: ".$price->min_price." and should be lessthan: ".$price->max_price);
            return response()->json($res);
        }
        if(sizeof($data['multiple_occupancy']) == 0){
            $data['multiple_occupancy'][0] = $data['bar_price'];
        }
        $multi_price=$data['multiple_occupancy'];
        if(sizeof($multi_price)>0){
            foreach($multi_price as $key => $multiprice)
            {
                if($multiprice == 0 || $multiprice == ''){
                    $rate['multiple_occupancy'][$key] = $rate['bar_price'];
                }
                if($multiprice >= $price->min_price && $multiprice < $price->max_price)
                {
                    $mp=$mp+1;
                }
            }
        }
        if($mp == 0)
        {
            $res=array('status'=>0,'message'=>"multiple occupancy should be equal or greater than: ".$price->min_price." and should be lessthan: ".$price->max_price);
            return response()->json($res);
        }
        $data['from_date']=date('Y-m-d',strtotime($data['from_date']));
        $data['to_date']=date('Y-m-d',strtotime($data['to_date']));
        $conds = array('hotel_id'=>$data['hotel_id'],'derived_room_type_id'=>$data['room_type_id'],'derived_rate_plan_id'=>$data['rate_plan_id']);
        $chek_parents = DerivedPlan::select('*')->where($conds)->get();
        if(sizeof($chek_parents)>0){
            if($data['extra_adult_price'] == 0 || $data['extra_adult_price'] == ""){
                $data['extra_adult_price'] = $this->getExtraAdultChildPrice($data,1);
            }
            if($data['extra_child_price'] == 0 || $data['extra_child_price'] == ""){
                $data['extra_child_price'] = $this->getExtraAdultChildPrice($data,2);
            }
            $response = $this->bulkBeOtaPush($imp_data,$data);
            $bar_price = $data['bar_price'];
            $extra_adult_price = $data['extra_adult_price'];
            $extra_child_price = $data['extra_child_price'];
            $multiple_occupancy_array = $data['multiple_occupancy'];
            foreach($chek_parents as $details){
                $multiple_occupancy=array();
                $getPrice = explode(",",$details->amount_type);
                $indexSize =  sizeof($getPrice)-1;
                if($details->select_type == 'percentage'){
                    $percentage_price = ($bar_price * $getPrice[$indexSize])/100;
                    $data['bar_price'] = round($bar_price + $percentage_price);
                    foreach($multiple_occupancy_array as $key => $multi){
                        $multi_per_price = ($multi * $getPrice[$key])/100;
                        $multiple_occupancy[]= round($multi + $multi_per_price);
                    }
                    $data['multiple_occupancy'] = $multiple_occupancy;
                }
                else{
                    $data['bar_price'] = round($bar_price + $getPrice[$indexSize]);
                    foreach($multiple_occupancy_array as $key => $multi){
                        $multiple_occupancy[]= round($multi + $getPrice[$key]);
                    }
                    $data['multiple_occupancy'] = $multiple_occupancy;
                }
                if($details->extra_adult_select_type == 'percentage'){
                    $percentage_price = ($extra_adult_price * $details->extra_adult_amount)/100;
                    $data['extra_adult_price'] = round($extra_adult_price + $percentage_price);
                }
                else{
                    $data['extra_adult_price'] = round($extra_adult_price + $details->extra_adult_amount);
                }
                if($details->extra_child_select_type == 'percentage'){
                    $percentage_price = ($extra_child_price * $details->extra_child_amount)/100;
                    $data['extra_child_price'] = round($extra_child_price + $percentage_price);
                }
                else{
                    $data['extra_child_price'] = round($extra_child_price + $details->extra_child_amount);
                }
                $data['room_type_id'] = $details->room_type_id;
                $data['rate_plan_id'] = $details->rate_plan_id;
                $response[] = $this->bulkBeOtaPush($imp_data,$data);
            }
        }
        else{
            $response = $this->bulkBeOtaPush($imp_data,$data);
        }
       return $response;
    }

    public function bulkBeOtaPush($imp_data,$data){
        $resp=array();
        foreach($imp_data['ota_id'] as $otaid){
            $ota_details = CmOtaDetails::select('*')
            ->where('hotel_id', '=' ,$imp_data['hotel_id'])
            ->where('ota_id','=',$otaid)
            ->where('is_active', '=' ,1)
            ->first();
            if($ota_details)
            {
                $bucketdata = $this->creatingBucketdata($ota_details,$imp_data['hotel_id'],$imp_data['client_ip'],$imp_data['user_id']);
            }
            switch($bucketdata['ota_name'])
            {
                case "Goibibo":
                                    try{
                                        $resp[]   =   $this->goibiboInventoryRatePushUpdate->bulkRateUpdate($bucketdata['bucket_data'],$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                case "Booking.com":
                                    try{
                                        $resp[]   =   $this->bookingdotcomInventoryRatePushUpdate->bulkRateUpdate($bucketdata['bucket_data'],$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                case "EaseMyTrip":
                                    try{
                                        $resp[]   =   $this->easemytripInventoryRatePushUpdate->bulkRateUpdate($bucketdata['bucket_data'],$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                case "Paytm":
                                    try{
                                        $resp[]   =   $this->paytmInventoryRatePushUpdate->bulkRateUpdate($bucketdata['bucket_data'],$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                case "Agoda":
                                    try{
                                        $resp[]   =   $this->agodaInventoryRatePushUpdate->bulkRateUpdate($bucketdata['bucket_data'],$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                case "Expedia":
                                    try{
                                        $resp[]   =   $this->expediaInventoryRatePushUpdate->bulkRateUpdate($bucketdata['bucket_data'],$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                case "Via.com":
                                    try{
                                        $resp[]   =   $this->viaInventoryRatePushUpdate->bulkRateUpdate($bucketdata['bucket_data'],$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                case "Goomo":
                                    try{
                                        $resp[]   =   $this->goomoInventoryRatePushUpdate->bulkRateUpdate($bucketdata['bucket_data'],$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                case "Travelguru":
                                    try{
                                        $resp[]   =   $this->travelguruInventoryRatePushUpdate->bulkRateUpdate($bucketdata['bucket_data'],$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                case "Airbnb":
                                    try{
                                        $resp[]   =   $this->airbnbInventoryRatePushUpdate->bulkRateUpdate($bucketdata['bucket_data'],$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                case "HappyEasyGo":
                                    try{
                                        $resp[]   =   $this->hegInventoryRatePushUpdate->bulkRateUpdate($bucketdata['bucket_data'],$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
                case "IRCTC":
                                    try{
                                        $resp[]   =   $this->irctcInventoryRatePushUpdate->bulkRateUpdate($bucketdata['bucket_data'],$data,$bucketdata['auth_parameter'],$bucketdata['commonUrl']);
                                    }
                                    catch(Exception $resp)
                                    {

                                    }
                                    break;
    
                default       :     $resp[] = array("status"=>0,"message"=>'No ota choosen for sync please choose!');
            }
        }
        return $resp;
    }
     //creatingBucketdata.
    public function creatingBucketdata($ota_details,$hotel_id,$client_ip,$user_id)
    {
        if($ota_details)
        {
            $bucket_data                   = [
                "bucket_id"                      => 0,
                "bucket_hotel_id"                => $hotel_id,
                "bucket_inventory_table_id"      => 0,
                "bucket_ota_id"                  => $ota_details['ota_id'],
                "bucket_ota_name"                => $ota_details['ota_name'],
                "bucket_rate_plan_log_table_id"  => 0,
                "bucket_ota_hotel_code"          => $ota_details['ota_hotel_code'],
                "bucket_client_ip"               => $client_ip,
                "bucket_user_id"                 => $user_id
                ];

            $ota_name            = $ota_details['ota_name'];
            $auth_parameter      = json_decode($ota_details['auth_parameter']);
            $commonUrl           = $ota_details['url'];
            return array('bucket_data'=>$bucket_data,'ota_name'=>$ota_name,'auth_parameter'=>$auth_parameter,'commonUrl'=>$commonUrl);
        }
    }
    public function impDate($data,$request)
    {
        $hotel_id = $data['hotel_id'];
        $ota_id = $data['ota_id'];
        $client_ip=$this->ipService->getIPAddress();//get client ip
        $user_id=0;
        if(isset($request->auth->admin_id)){
            $user_id=$request->auth->admin_id;
        }else if(isset($request->auth->super_admin_id)){
            $user_id=$request->auth->super_admin_id;
        }
        else if(isset($request->auth->id)){
            $user_id=$request->auth->id;
        }
        return array('hotel_id'=>$hotel_id,'ota_id'=>$ota_id,'client_ip'=>$client_ip,'user_id'=>$user_id);
    }
    //Get Product Modules [Channel Manager]
    public function getProductDetails($hotel_id){
        $company_id="";
        $hotel_details=HotelInformation::where("hotel_id",$hotel_id)->first();
        if($hotel_details){
                $company_id=$hotel_details->company_id;
        }
        $billing_details=BillingDetails::where('company_id',$company_id)->first();
        $product_names=json_decode($billing_details->product_name);
        $cm_exist=false;
        $be_exist="";
        foreach($product_names as $product_name)
        {
                if($product_name=="Channel Manager"){
                        $cm_exist=true;
                }
                if($product_name=="Booking Engine"){
                        $be_exist=true;
                }
        }
        if($be_exist==false &&  $cm_exist===true){
                return true;
        }else{
                return false;
        }
    }
    public function getExtraAdultChildPrice($data,$source){
        $conds = array('hotel_id'=>$data['hotel_id'],'room_type_id'=>$data['room_type_id'],'rate_plan_id'=>$data['rate_plan_id']);
        $getPriceDetails = MasterHotelRatePlan::select('extra_adult_price','extra_child_price')
                            ->where($conds)
                            ->first();
        if($getPriceDetails){
            if($source = 1){
                return $getPriceDetails->extra_adult_price;
            }
            else{
                return $getPriceDetails->extra_child_price;
            }
        }
        else{
            return 0;
        }
    }
}
