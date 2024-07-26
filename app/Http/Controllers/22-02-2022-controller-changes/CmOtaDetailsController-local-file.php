<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\UserCredential;
use App\CmOtaCredentialParameter;//class name from model
use App\CmOtaDetails;//class name from model
use App\CmOtaRoomTypeFetch;//class name from model
use App\CmOtaRateTypeFetch;//class name from model
use App\LogTable;//class name from model
use App\CmOtaRoomTypeFetchSync;//class name from model
use App\CmOtaRatePlanFetchSync;//class name from model
use App\CmOtaDetailsRead;//class name from model
use App\CmOtaRoomTypeFetchRead;//class name from model
use App\CmOtaRateTypeFetchRead;//class name from model
use App\CmOtaRoomTypeFetchSyncRead;//class name from model
use App\CmOtaRatePlanFetchSyncRead;//class name from model
use App\CmOtaCredentialParameterRead;
use DB;
use Ixudra\Curl\Facades\Curl;
use App\Http\Controllers\CommonServiceController;

//create a new class CmOtaDetailsController
class CmOtaDetailsController extends Controller
{
    protected $commonService;
    public function __construct(CommonServiceController $commonService)
    {
       $this->commonService = $commonService;
    }
     //validation rules
    private $rules = array(
        'ota_hotel_code' => 'required',
        'ota_name' => 'required',
        'auth_parameter' => 'required',
        'hotel_id'=>'required | numeric'
    );
    //Custom Error Messages
    private $messages = [
        'ota_hotel_code.required' => 'The ota hotel code is required.',
        'ota_name.required' => 'The ota name is required.',
        'auth_parameter.required' => 'The auth parameterd is required.',
        'hotel_id.required'=>'Hotel id is required'
            ];
    //validation rules
    private $code_rules = array(
        'code' => 'required',
        'company_id' => 'required'
    );
    //Custom Error Messages
    private $code_messages = [
        'code.required' => 'Airbnb code is required.',
        'company_id.required' => 'Company id is required.'
            ];
    /**
     * CM ota  Details
     * Create a new record of CM ota Details.s.
     * @author subhradip
     * @return CM ota Details saving status
     * function addnew for createing a new CM ota Details
    **/
    public function addNewCmHotel(Request $request)
    {
        $cmotadetails = new CmOtaDetails();
        $failure_message='Cm ota details Saving Failed';
        $validator = Validator::make($request->all(),$this->rules,$this->messages);
        if ($validator->fails())
        {
            return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
        }
        $data=$request->all();
        $cmOtaCredentialParameter=new CmOtaCredentialParameterRead();
        $ota_name=$data['ota_name'];
        $cm_data=$cmOtaCredentialParameter->where('ota_name',$ota_name)->first();
        $data['url']=$cm_data->url;
        if($data['ota_name']=='Expedia')
        {
            $data['auth_parameter']='{"username":"EQC_Bookingjini","password":"Bookingjini@March2020"}';
        }
        elseif($data['ota_name']=='Goibibo')
        {
            $data['auth_parameter']='{'.'"bearer_token"'.':'.'"'.$data['auth_parameter'].'"'.','.'"channel_token"'.':'.'"95de1f96be"'.'}';
        }
        elseif($data['ota_name']=='Cleartrip')
        {
            $data['auth_parameter']='{"api_key":"049e6c6fe997b4fb19af64c761f1a845"}';
        }
        elseif($data['ota_name']=='Travelguru')
        {
            $data['auth_parameter']='{"ID":"chm-bookingjini","MessagePassword":"b00kin789jini"}';
        }
        elseif($data['ota_name']=='Booking.com')
        {
            $data['auth_parameter']='{"username":"Bookingjini-channelmanager","password":"wSznWO?2wy\/^-j\/hfUK^MCq?:A*EK)BBXSMK-.*)"}';
        }
        elseif($data['ota_name']=='Via.com')
        {
            $data['auth_parameter']='{"source":"BOOKINGJINI","auth":"QDXW6cv3QAJLT2xu"}';
        }
        elseif($data['ota_name']=='Agoda')
        {
            $data['auth_parameter']='{"apiKey":"d9922c69-dada-48f4-996c-92d691944127"}';
        }
        elseif($data['ota_name']=='Goomo')
        {
            $data['auth_parameter']='{"apiKey":"2727aefc6e6bd77aee2643b3b56268db20b8a48f","channelId":"23","accessToken":"1526983456169"}';
        }
        elseif($data['ota_name']=='EaseMyTrip')
        {
            $data['auth_parameter']='{"Token":"pDm0Q6w6gIaOjX1I9eje0jpqs4G3rskuHMmZ6G8XWnh9UrrpfttStQ=="}';
        }
        elseif($data['ota_name']=='Paytm')
        {
            $data['auth_parameter']='{"api_key":"95DE1F960A148276175A174D642"}';
        }
        elseif($data['ota_name']=='HappyEasyGo')
        {
            $data['auth_parameter']='{"key":"827ccb0eea8a706c4c34a16891f84e7b"}';
        }
        elseif($data['ota_name']=='IRCTC')
        {
            $data['auth_parameter']='{"username":"BookingjiniLiveUsername","password":"#Bookingjini@live07#$"}';
        }elseif($data['ota_name']=='ClearTripHyperGuest')
        {
            $data['auth_parameter']='{"username":"BookingjiniLiveUsername","password":"#Bookingjini@live07#$"}';
        }

         //checkCmOtaDetails function from model for checking duplicasy
        if($cmotadetails->checkCmOtaDetails($data['ota_name'],$data['hotel_id'])=="new")
        {
           if($cmotadetails->fill($data)->save())
           {
               $res=array('status'=>1,"message"=>"Cm ota details saved successfully");
               return response()->json($res);
           }
           else
           {
               $res=array('status'=>-1,"message"=>$failure_message);
               $res['errors'][] = "Internal server error";
               return response()->json($res);
           }
        }
        else
        {
            $res=array('status'=>0,"message"=>"This cm ota details already exist");
            return response()->json($res);
        }
    }
/**
     * CM ota  Details
     * Update record of CM ota  Details
     * @auther subhradip
     * @return CM ota  Details   saving status
     * function updateCmHotel use for update
    **/
    public function updateCmHotel(int $ota_id ,Request $request)
    {
        $failure_message="CM ota  detailse  saving failed.";
        $validator = Validator::make($request->all(),$this->rules,$this->messages);
        if ($validator->fails())
        {
        return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
        }
        $data=$request->all();
        if($data['ota_name']=='Expedia')
        {
            $data['auth_parameter']='{"username":"EQC_Bookingjini","password":"Bookingjini@March2020"}';
        }
        elseif($data['ota_name']=='Goibibo')
        {
            $data['auth_parameter']='{'.'"bearer_token"'.':'.'"'.$data['auth_parameter'].'"'.','.'"channel_token"'.':'.'"95de1f96be"'.'}';
        }
        elseif($data['ota_name']=='Cleartrip')
        {
            $data['auth_parameter']='{"api_key":"049e6c6fe997b4fb19af64c761f1a845"}';
        }
        elseif($data['ota_name']=='Travelguru')
        {
            $data['auth_parameter']='{"ID":"chm-bookingjini","MessagePassword":"b00kin789jini"}';
        }
        elseif($data['ota_name']=='Booking.com')
        {
            $data['auth_parameter']='{"username":"Bookingjini-channelmanager","password":"wSznWO?2wy\/^-j\/hfUK^MCq?:A*EK)BBXSMK-.*)"}';
        }
        elseif($data['ota_name']=='Via.com')
        {
            $data['auth_parameter']='{"source":"BOOKINGJINI","auth":"QDXW6cv3QAJLT2xu"}';
        }
        elseif($data['ota_name']=='Agoda')
        {
            $data['auth_parameter']='{"apiKey":"d9922c69-dada-48f4-996c-92d691944127"}';
        }
        elseif($data['ota_name']=='Goomo')
        {
            $data['auth_parameter']='{"apiKey":"2727aefc6e6bd77aee2643b3b56268db20b8a48f","channelId":"23","accessToken":"1526983456169"}';
        }
        elseif($data['ota_name']=='EaseMyTrip')
        {
            $data['auth_parameter']='{"Token":"pDm0Q6w6gIaOjX1I9eje0jpqs4G3rskuHMmZ6G8XWnh9UrrpfttStQ=="}';
        }
        elseif($data['ota_name']=='Paytm')
        {
            $data['auth_parameter']='{"api_key":"95DE1F960A148276175A174D642"}';
        }
        elseif($data['ota_name']=='HappyEasyGo')
        {
            $data['auth_parameter']='{"key":"827ccb0eea8a706c4c34a16891f84e7b"}';
        }
        elseif($data['ota_name']=='IRCTC')
        {
            $data['auth_parameter']='{"username":"BookingjiniLiveUsername","password":"#Bookingjini@live07#$"}';
        }elseif($data['ota_name']=='ClearTripHyperGuest')
        {
            $data['auth_parameter']='{"username":"BookingjiniLiveUsername","password":"#Bookingjini@live07#$"}';
        }
        $cmOtaCredentialParameter=new CmOtaCredentialParameterRead();
        $ota_name=$data['ota_name'];
        $cm_data=$cmOtaCredentialParameter->where('ota_name',$ota_name)->first();
        $data['url']=$cm_data->url;
        $cmotadetails = CmOtaDetailsRead::where('ota_id',$ota_id)->first();
        //checkmasterroomplanStatus function from model for checking duplicasy
            if($cmotadetails->ota_name==$data['ota_name'] )
            {
                if($cmotadetails->fill($data)->save())
                {
                    $res=array('status'=>1,"message"=>"Cm ota details updated successfully");
                    return response()->json($res);
                }
                else
                {
                    $res=array('status'=>-1,"message"=>$failure_message);
                    $res['errors'][] = "Internal server error";
                    return response()->json($res);
                }
            }
            else
            {
                $res=array('status'=>0,"message"=>"This cm ota details already exist");
                return response()->json($res);
            }

    }
    /**
     * Delete CM ota  Details
     * delete record of CM ota  Details
     * @auther subhradip
     * @return HCM ota  Detailse deleting status
     * function deleteCmHotel used for delete
    **/
    public function deleteCmHotel(int $hotel_id ,int $ota_id ,Request $request)
    {
        $failure_message='Deleted Filure';
        if($ota_id==0)
        {
            if(CmOtaDetails::where('hotel_id',$hotel_id)->update(['is_active' => 0]))
            {

                $res=array('status'=>1,"message"=>'CM ota  Details deleted successfully');
                return response()->json($res);
            }
            else
            {
                $res=array('status'=>-1,"message"=>$failure_message);
                $res['errors'][] = "Internal server error";
                return response()->json($res);
            }
        }
        else
        {
            if(CmOtaDetails::where('ota_id',$ota_id)->update(['is_active' => 0]))
            {

                $res=array('status'=>1,"message"=>'CM ota  Details deleted successfully');
                return response()->json($res);
            }

            else
            {
                $res=array('status'=>-1,"message"=>$failure_message);
                $res['errors'][] = "Internal server error";
                return response()->json($res);
            }
        }
    }

    /**
     * To toggle the ota status
     * @auther Godti Vinod
     * @return Status of toggle success or failure
     * function toggle used for togggle ota status
    **/
    public function toggle(int $hotel_id,int $ota_id ,int $is_active ,Request $request)
    {
        $failure_message='Ota status updation failed';

        if(CmOtaDetails::where('hotel_id',$hotel_id)->where('ota_id',$ota_id)->update(['is_active' =>$is_active]))
        {

            $res=array('status'=>1,"message"=>'Ota status updated successfully!');
            return response()->json($res);
        }
        else
        {
            $res=array('status'=>-1,"message"=>$failure_message);
            return response()->json($res);
        }

    }
    /**
     * Get CM ota  Details
     * get one record of CM ota  Details
     * @auther subhradip
     * function getHoteltoomplantype for delecting data
    **/
    public function getCmHotel(int $ota_id ,Request $request)
    {
        if($ota_id)
        {
            $conditions=array('ota_id'=>$ota_id,'is_active'=>1);
            $res=CmOtaDetailsRead::where($conditions)->get();
            if(sizeof($res)>0)
            {
                return response()->json($res);
            }
            else
            {
                $res=array('status'=>0,"message"=>"No cm ota details records found");
                return response()->json($res);
            }
        }
        else
        {
            $res=array('status'=>-1,"message"=>"Cm ota details fetching failed");
            $res['errors'][] = "UUID is provided";
            return response()->json($res);
        }
    }
    /**
     * Get all CM ota  Details
     * get All record ofCM ota  Details
     * @auther subhradip
     * function getAllHotelroomplantypes for selecting all data
    **/
    public function getAllCmHotel(int $hotel_id,Request $request)
    {
        $conditions=array('is_active'=>1,'hotel_id'=>$hotel_id);
        $res=CmOtaDetailsRead::join('cmlive.cm_ota_credential_parameter','cm_ota_credential_parameter.ota_name','cm_ota_details.ota_name')
        ->where($conditions)
        ->select('cm_ota_details.*','cm_ota_credential_parameter.*')->get();
        $cmOtaRoomTypeFetch=new CmOtaRoomTypeFetchRead();
        $cmOtaRateTypeFetch=new CmOtaRateTypeFetchRead();
        $cmOtaRoomTtypeFetchSync=new CmOtaRoomTypeFetchSyncRead();
        $cmOtaRatePlanFetchSync=new CmOtaRatePlanFetchSyncRead();
        foreach($res as $ota_data)
        {
            $room_fetch=$cmOtaRoomTypeFetch->where('ota_id',$ota_data['ota_id'])->first();
            $rate_fetch=$cmOtaRateTypeFetch->where('ota_id',$ota_data['ota_id'])->first();
            $room_sync=$cmOtaRoomTtypeFetchSync->where('ota_type_id',$ota_data['ota_id'])->first();
            $rate_sync=$cmOtaRatePlanFetchSync->where('ota_type_id',$ota_data['ota_id'])->first();
            if($ota_data['ota_name'] == 'Airbnb' && $room_sync)
            {
                $ota_data['sync_status']=1;
            }
            else
            {
                if($room_fetch && $rate_fetch && $room_sync && $rate_sync)
                {
                    $ota_data['sync_status']=1;
                }
                else{
                    $ota_data['sync_status']=0;
                }
            }
        }
        if(sizeof($res)>0)
        {
            $res=array('status'=>1,"message"=>"Ota details records found","data"=>$res);
            return response()->json($res);
        }
        else
        {
            $res=array('status'=>0,"message"=>"No cm ota details records found","data"=>0);
            return response()->json($res);
        }
    }
    public function removeNamespaceFromXML( $xml )
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
    /**
     * CM ota  for goibibo.
     * Create a new record of CM ota for goibibo.
     * @author subhradip
     * @return CM ota for goibib saving status
     * function goibiboCmHotel for goibibo request .
    **/
    public function multipleFunction(int $ota_id, Request $request)
    {
        $cmotadetails=new CmOtaDetailsRead();
        $ota_data=$cmotadetails->where('ota_id',$ota_id)->first();
        $auth_parameter = json_decode($ota_data->auth_parameter);

        if($ota_data->ota_name == "Goibibo")
        {
            $deleteRoomType    = CmOtaRoomTypeFetch::where('hotel_id',$ota_data->hotel_id)->where('ota_id',$ota_id)->delete();
            $deleteRatePlan    = CmOtaRateTypeFetch::where('hotel_id',$ota_data->hotel_id)->where('ota_id',$ota_id)->delete();
            return $this->goibiboCmHotel($ota_data->ota_hotel_code,$ota_data->url,$auth_parameter,$ota_data->hotel_id,$ota_id,$ota_data->ota_name);

        }
        elseif($ota_data->ota_name == "Travelguru")
        {
            $deleteRoomType    = CmOtaRoomTypeFetch::where('hotel_id',$ota_data->hotel_id)->where('ota_id',$ota_id)->delete();
            $deleteRatePlan    = CmOtaRateTypeFetch::where('hotel_id',$ota_data->hotel_id)->where('ota_id',$ota_id)->delete();
            return $this->travelguruCmHotel($ota_data->ota_hotel_code,$ota_data->url,$auth_parameter,$ota_data->hotel_id,$ota_id,$ota_data->ota_name);
        }
        elseif($ota_data->ota_name == "Cleartrip")
        {
            $deleteRoomType    = CmOtaRoomTypeFetch::where('hotel_id',$ota_data->hotel_id)->where('ota_id',$ota_id)->delete();
            $deleteRatePlan    = CmOtaRateTypeFetch::where('hotel_id',$ota_data->hotel_id)->where('ota_id',$ota_id)->delete();
            return $this->cleartripCmHotel($ota_data->ota_hotel_code,$ota_data->url,$auth_parameter,$ota_data->hotel_id,$ota_id,$ota_data->ota_name);
        }
        elseif($ota_data->ota_name == "Via.com")
        {
            $deleteRoomType    = CmOtaRoomTypeFetch::where('hotel_id',$ota_data->hotel_id)->where('ota_id',$ota_id)->delete();
            $deleteRatePlan    = CmOtaRateTypeFetch::where('hotel_id',$ota_data->hotel_id)->where('ota_id',$ota_id)->delete();
            return $this->viaCmHotel($ota_data->ota_hotel_code,$ota_data->url,$auth_parameter,$ota_data->hotel_id,$ota_id,$ota_data->ota_name);
        }
        elseif($ota_data->ota_name == "Agoda")
        {
            $deleteRoomType    = CmOtaRoomTypeFetch::where('hotel_id',$ota_data->hotel_id)->where('ota_id',$ota_id)->delete();
            $deleteRatePlan    = CmOtaRateTypeFetch::where('hotel_id',$ota_data->hotel_id)->where('ota_id',$ota_id)->delete();
            return $this->agodaCmHotel($ota_data->ota_hotel_code,$ota_data->url,$auth_parameter,$ota_data->hotel_id,$ota_id,$ota_data->ota_name);
        }

        elseif($ota_data->ota_name == "Booking.com")
        {
            $deleteRoomType    = CmOtaRoomTypeFetch::where('hotel_id',$ota_data->hotel_id)->where('ota_id',$ota_id)->delete();
            $deleteRatePlan    = CmOtaRateTypeFetch::where('hotel_id',$ota_data->hotel_id)->where('ota_id',$ota_id)->delete();
            return $this->bokingComCmHotel($ota_data->ota_hotel_code,$ota_data->url,$auth_parameter,$ota_data->hotel_id,$ota_id,$ota_data->ota_name);
        }
        elseif($ota_data->ota_name== "Expedia")
        {
            $deleteRoomType    = CmOtaRoomTypeFetch::where('hotel_id',$ota_data->hotel_id)->where('ota_id',$ota_id)->delete();
            $deleteRatePlan    = CmOtaRateTypeFetch::where('hotel_id',$ota_data->hotel_id)->where('ota_id',$ota_id)->delete();
            return $this->expediaComCmHotel($ota_data->ota_hotel_code,$ota_data->url,$auth_parameter,$ota_data->hotel_id,$ota_id,$ota_data->ota_name);
        }
        /*if($ota_data->ota_name== "Airbnb")
        {
            return $this->airbnbCmHotel($ota_data->ota_hotel_code,$ota_data->url,$auth_parameter,$ota_data->hotel_id,$ota_id,$ota_data->ota_name);
        }*/
        elseif($ota_data->ota_name== "Goomo")
        {
            $deleteRoomType    = CmOtaRoomTypeFetch::where('hotel_id',$ota_data->hotel_id)->where('ota_id',$ota_id)->delete();
            $deleteRatePlan    = CmOtaRateTypeFetch::where('hotel_id',$ota_data->hotel_id)->where('ota_id',$ota_id)->delete();
            return $this->goomoCmHotel($ota_data->ota_hotel_code,$ota_data->url,$auth_parameter,$ota_data->hotel_id,$ota_id,$ota_data->ota_name);
        }
        elseif($ota_data->ota_name == "EaseMyTrip")
        {
            $deleteRoomType    = CmOtaRoomTypeFetch::where('hotel_id',$ota_data->hotel_id)->where('ota_id',$ota_id)->delete();
            $deleteRatePlan    = CmOtaRateTypeFetch::where('hotel_id',$ota_data->hotel_id)->where('ota_id',$ota_id)->delete();
           return $this->easemytripCmHotel($ota_data->ota_hotel_code,$ota_data->url,$auth_parameter,$ota_data->hotel_id,$ota_id,$ota_data->ota_name);
        }
        elseif($ota_data->ota_name == "Paytm")
        {
            $deleteRoomType    = CmOtaRoomTypeFetch::where('hotel_id',$ota_data->hotel_id)->where('ota_id',$ota_id)->delete();
            $deleteRatePlan    = CmOtaRateTypeFetch::where('hotel_id',$ota_data->hotel_id)->where('ota_id',$ota_id)->delete();
           return $this->paytmCmHotel($ota_data->ota_hotel_code,$ota_data->url,$auth_parameter,$ota_data->hotel_id,$ota_id,$ota_data->ota_name);
        }
        elseif($ota_data->ota_name == "HappyEasyGo")
        {
            $deleteRoomType    = CmOtaRoomTypeFetch::where('hotel_id',$ota_data->hotel_id)->where('ota_id',$ota_id)->delete();
            $deleteRatePlan    = CmOtaRateTypeFetch::where('hotel_id',$ota_data->hotel_id)->where('ota_id',$ota_id)->delete();
           return $this->hegCmHotel($ota_data->ota_hotel_code,$ota_data->url,$auth_parameter,$ota_data->hotel_id,$ota_id,$ota_data->ota_name);
        }
        elseif($ota_data->ota_name == "IRCTC")
        {
            $deleteRoomType    = CmOtaRoomTypeFetch::where('hotel_id',$ota_data->hotel_id)->where('ota_id',$ota_id)->delete();
            $deleteRatePlan    = CmOtaRateTypeFetch::where('hotel_id',$ota_data->hotel_id)->where('ota_id',$ota_id)->delete();
           return $this->irctcCmHotel($ota_data->ota_hotel_code,$ota_data->url,$auth_parameter,$ota_data->hotel_id,$ota_id,$ota_data->ota_name);
        }elseif($ota_data->ota_name == "ClearTripHyperGuest")
        {
            $deleteRoomType    = CmOtaRoomTypeFetch::where('hotel_id',$ota_data->hotel_id)->where('ota_id',$ota_id)->delete();
            $deleteRatePlan    = CmOtaRateTypeFetch::where('hotel_id',$ota_data->hotel_id)->where('ota_id',$ota_id)->delete();
           return $this->clearTripHyperGuestCmHotel($ota_data->ota_hotel_code,$ota_data->url,$auth_parameter,$ota_data->hotel_id,$ota_id,$ota_data->ota_name);
        }

    }
    public function goibiboCmHotel($ota_hotel_code,$commonurl,$auth_parameter,$hotel_id,$ota_id,$ota_name)
    {

        $bearer_token      = trim($auth_parameter->bearer_token);
        $channel_token     = trim($auth_parameter->channel_token);
        //$channel_token="";
        $xml='<?xml version="1.0" encoding="UTF-8" ?>
            <Website Name="ingoibibo" HotelCode="'.$ota_hotel_code.'">
            <HotelCode>'.$ota_hotel_code.'</HotelCode>
            </Website>';
        $curlService = new \Ixudra\Curl\CurlService();
        $url=$commonurl.'/gethotellisting/?bearer_token='.$bearer_token.'&channel_token='.$channel_token;
        $response = $curlService->to($url)
        ->withData($xml)
        ->post();
        $response=preg_replace('/&(?!#?[a-z0-9]+;)/', '&amp;', $response);
        if($response=="OAuth Authorization Required")
        {
            $res=array('status'=>0,"message"=>"Goibibo Room type and Room Rate fetch failed","err"=>$response);
            return response()->json($res);
        }
        $array_data=json_decode(json_encode(simplexml_load_string($this->removeNamespaceFromXML($response))), true);
        if(isset($array_data[0])){//Means Error Tag
            $res=array('status'=>0,"message"=>"Goibibo Room type and Room Rate fetch failed","err"=>$array_data[0]);
            return response()->json($res);
        }
        $room_type_deatils =$array_data['RoomList']['Room'];
        $rate_plan_deatils =$array_data['RatePlanList']['RatePlan'];
        $isMultidimensional = $this->commonService->isMultidimensionalArray($room_type_deatils);
        if($isMultidimensional){
          foreach ($room_type_deatils as $key => $value) {
            $isActive = $value['IsActive'];
            $roomID   = $value['RoomTypeCode'];
            $roomName = $value['RoomTypeName'];
            if($isActive == "false" || $isActive == "False"){
                continue;
            }
            if($isActive == "true" || $isActive == "True"){
                $cmOtaRoomTypeFetchModel = new CmOtaRoomTypeFetch();
                $room_active             = 1;
                $room_code               = $roomID;
                $room_name               = $roomName;

                /*----------------cmOtaRoomTypeFetchModel Save -------------------*/

                $cmOtaRoomTypeFetchModel->hotel_id            = $hotel_id;
                $cmOtaRoomTypeFetchModel->ota_id              = $ota_id;
                $cmOtaRoomTypeFetchModel->ota_name            = $ota_name;
                $cmOtaRoomTypeFetchModel->ota_room_type_id    = $room_code;
                $cmOtaRoomTypeFetchModel->ota_room_type_name  = $room_name;
                $cmOtaRoomTypeFetchModel->active              = $room_active;
                $room_type_fetch_status                       = $cmOtaRoomTypeFetchModel->save();

                $room_details[]  = array("room_code"=>"$room_code","room_name"=>"$room_name");
              }
              else{
                $res=array('status'=>0,"message"=>"Room type is not active in Goibibo");
                return response()->json($res);
              }
            }
        }
        else{
          $isActive = $room_type_deatils['IsActive'];
          $roomID   = $room_type_deatils['RoomTypeCode'];
          $roomName = $room_type_deatils['RoomTypeName'];
          if($isActive == "True" || $isActive == "true"){
            $cmOtaRoomTypeFetchModel = new CmOtaRoomTypeFetch();
            $room_active             = 1;
            $room_code               = $roomID;
            $room_name               = $roomName;
            /*----------------cmOtaRoomTypeFetchModel Save -------------------*/

            $cmOtaRoomTypeFetchModel->hotel_id            = $hotel_id;
            $cmOtaRoomTypeFetchModel->ota_id              = $ota_id;
            $cmOtaRoomTypeFetchModel->ota_name            = $ota_name;
            $cmOtaRoomTypeFetchModel->ota_room_type_id    = $room_code;
            $cmOtaRoomTypeFetchModel->ota_room_type_name  = $room_name;
            $cmOtaRoomTypeFetchModel->active              = $room_active;
            $room_type_fetch_status                       = $cmOtaRoomTypeFetchModel->save();

            $room_details[]  = array("room_code"=>"$room_code","room_name"=>$room_name);
          }
          else{
            $res=array('status'=>0,"message"=>"Room type is not active in Goibibo");
            return response()->json($res);
          }
        }
        foreach ($room_details as $room_detail)
        {
            $room_code = $room_detail["room_code"];
            $room_name = $room_detail["room_name"];
            $isMultidimensional = $this->commonService->isAllKeyMultidimensionalArray($rate_plan_deatils);
            if($isMultidimensional){
                foreach ($rate_plan_deatils as  $v) {
                    if($v['RoomTypeCode'] == $room_code ){
                        if($v['IsActive'] == "False" || $v['IsActive'] == "false"){
                            continue;
                        }
                        if($v['IsActive'] == "True" || $v['IsActive'] == "true")
                        {
                            $rate_id       = $v['RatePlanCode'];
                            $rate_name     = $v['RatePlanName'];
                            $validate_from = "";
                            $validate_to   = "";
                            $rate_active   = 1;
                            $cmOtaRateTypeFetchModel = new CmOtaRateTypeFetch();

                            /*----------------cmOtaRateTypeFetchModel Save -------------------*/

                            $cmOtaRateTypeFetchModel->hotel_id            = $hotel_id;
                            $cmOtaRateTypeFetchModel->ota_id              = $ota_id;
                            $cmOtaRateTypeFetchModel->ota_name            = $ota_name;
                            $cmOtaRateTypeFetchModel->ota_room_type_id    = $room_code;
                            $cmOtaRateTypeFetchModel->ota_room_type_name  = $room_name;
                            $cmOtaRateTypeFetchModel->ota_rate_type_id    = $rate_id;
                            $cmOtaRateTypeFetchModel->ota_rate_type_name  = $rate_name;
                            $cmOtaRateTypeFetchModel->validate_from       = $validate_from;
                            $cmOtaRateTypeFetchModel->validate_to         = $validate_to;
                            $cmOtaRateTypeFetchModel->active              = $rate_active;
                            $rate_type_fetch_status                       = $cmOtaRateTypeFetchModel->save();
                        }  // if IsActive closed here
                    }  // if RoomTypeCode closed here
                } //$rate_plan_deatils closed here
            }
            else
            {
                if($rate_plan_deatils['RoomTypeCode'] == $room_code )
                {
                    if($rate_plan_deatils['IsActive'] == "True" || $rate_plan_deatils['IsActive'] == "true")
                    {
                        $rate_id       = $rate_plan_deatils['RatePlanCode'];
                        $rate_name     = $rate_plan_deatils['RatePlanName'];
                        $validate_from = "";
                        $validate_to   = "";
                        $rate_active   = 1;
                        $cmOtaRateTypeFetchModel = new CmOtaRateTypeFetch();

                        /*----------------cmOtaRateTypeFetchModel Save -------------------*/

                        $cmOtaRateTypeFetchModel->hotel_id            = $hotel_id;
                        $cmOtaRateTypeFetchModel->ota_id              = $ota_id;
                        $cmOtaRateTypeFetchModel->ota_name            = $ota_name;
                        $cmOtaRateTypeFetchModel->ota_room_type_id    = $room_code;
                        $cmOtaRateTypeFetchModel->ota_room_type_name  = $room_name;
                        $cmOtaRateTypeFetchModel->ota_rate_type_id    = $rate_id;
                        $cmOtaRateTypeFetchModel->ota_rate_type_name  = $rate_name;
                        $cmOtaRateTypeFetchModel->validate_from       = $validate_from;
                        $cmOtaRateTypeFetchModel->validate_to         = $validate_to;
                        $cmOtaRateTypeFetchModel->active              = $rate_active;
                        $rate_type_fetch_status                       = $cmOtaRateTypeFetchModel->save();

                    }  // if IsActive closed here
                }  // if RoomTypeCode closed here
            }
        }
        $otalog = new LogTable();
        /*----------------otalog Save -------------------*/
        $otalog->hotel_id            = $hotel_id;
        $otalog->ota_id              = $ota_id;
        $otalog->request_msg         = $xml;
        $otalog->response_msg        = $response;
        $otalog->request_url           = $url;
        $ota_log                     = $otalog->save();

        $res=array('status'=>1,"message"=>"Goibibo Room type and Room Rate fetch successfull");
        return response()->json($res);
    }
    public function clearTripHyperGuestCmHotel($ota_hotel_code,$commonurl,$auth_parameter,$hotel_id,$ota_id,$ota_name)
    {

        $bearer_token      = trim($auth_parameter->bearer_token);
        $channel_token     = trim($auth_parameter->channel_token);
        //$channel_token="";
        $xml = '<OTA_HotelAvailRQ xmlns="http://www.opentravel.org/OTA/2003/05" Version="1.0" EchoToken="1234">
                  <AvailRequestSegments>
                    <AvailRequestSegment AvailReqType="Room">
                      <HotelSearchCriteria>
                        <Criterion>
                          <HotelRef HotelCode="'.$ota_hotel_code.'"/>
                        </Criterion>
                      </HotelSearchCriteria>
                    </AvailRequestSegment>
                  </AvailRequestSegments>
                </OTA_HotelAvailRQ>';
        $curlService = new \Ixudra\Curl\CurlService();
        $url=$commonurl.'/gethotellisting/?bearer_token='.$bearer_token.'&channel_token='.$channel_token;
        $response = $curlService->to($url)
        ->withData($xml)
        ->post();
        $response=preg_replace('/&(?!#?[a-z0-9]+;)/', '&amp;', $response);
        if($response=="OAuth Authorization Required")
        {
            $res=array('status'=>0,"message"=>"Goibibo Room type and Room Rate fetch failed","err"=>$response);
            return response()->json($res);
        }
        $array_data=json_decode(json_encode(simplexml_load_string($this->removeNamespaceFromXML($response))), true);
        if(isset($array_data[0])){//Means Error Tag
            $res=array('status'=>0,"message"=>"Goibibo Room type and Room Rate fetch failed","err"=>$array_data[0]);
            return response()->json($res);
        }
        $room_type_deatils_array = $array_data['RoomStays']['RoomStay'];
        $validate_from = "";
        $validate_to   = "";
        $rate_active   = 1;
        if(isset($room_type_deatils_array['RoomTypes']['RoomType']['@attributes'])){//single room stays
            $roomID   = $room_type_deatils_array['RoomTypes']['RoomType']['@attributes']['RoomTypeCode'];
            $roomName = $room_type_deatils_array['RoomTypes']['RoomType']['RoomDescription']['@attributes']['Name'];
            $isActive = "true";
            $this->saveRoomType($hotel_id,$ota_id,$ota_name,$roomID,$roomName,$isActive);
            //echo "<pre>";print_r($room_type_deatils_array['RatePlans']['RatePlan']);exit;
            if(isset($room_type_deatils_array['RatePlans']['RatePlan']['@attributes'])){
                $rate_id = $room_type_deatils_array['RatePlans']['RatePlan']['@attributes']['RatePlanCode'];
                $rate_name = $room_type_deatils_array['RatePlans']['RatePlan']['RatePlanDescription']['@attributes']['Name'];
                $this->saveRatePlan($hotel_id,$ota_id,$ota_name,$roomID,$roomName,$rate_id,$rate_name,$validate_from,$validate_to,$rate_active);
            }else{
                foreach($room_type_deatils_array['RatePlans']['RatePlan'] as $key2=>$val2){
                    $rate_id   = $val2['@attributes']['RatePlanCode'];
                    $rate_name = $val2['RatePlanDescription']['@attributes']['Name'];
                    $this->saveRatePlan($hotel_id,$ota_id,$ota_name,$roomID,$roomName,$rate_id,$rate_name,$validate_from,$validate_to,$rate_active);
                }
            }
        }else{
                if(isset($room_type_deatils_array['RoomTypes']['RoomType'])){//single room types
                    $i = 0;
                    foreach($room_type_deatils_array['RoomTypes']['RoomType'] as $room_type_deatils){
                        //echo "<pre>";print_r($room_type_deatils);
                        $roomID   = $room_type_deatils['@attributes']['RoomTypeCode'];
                        $roomName = $room_type_deatils['RoomDescription']['@attributes']['Name'];
                        $isActive = "true";
                        $save_room = $this->saveRoomType($hotel_id,$ota_id,$ota_name,$roomID,$roomName,$isActive);
                        //echo "<pre>";print_r($room_type_deatils_array[]);exit;
                        if($save_room){
                            if(isset($room_type_deatils_array['RatePlans']['RatePlan']['@attributes'])){
                                $rate_id = $room_type_deatils_array['RatePlans']['RatePlan']['@attributes']['RatePlanCode'];
                                $rate_name = $room_type_deatils_array['RatePlans']['RatePlan']['RatePlanDescription']['@attributes']['Name'];
                                $this->saveRatePlan($hotel_id,$ota_id,$ota_name,$roomID,$roomName,$rate_id,$rate_name,$validate_from,$validate_to,$rate_active);
                            }else{
                                $rate_id   = $room_type_deatils_array['RatePlans']['RatePlan'][$i]['@attributes']['RatePlanCode'];
                                $rate_name = $room_type_deatils_array['RatePlans']['RatePlan'][$i]['RatePlanDescription']['@attributes']['Name'];
                                $this->saveRatePlan($hotel_id,$ota_id,$ota_name,$roomID,$roomName,$rate_id,$rate_name,$validate_from,$validate_to,$rate_active);
                                //echo "<pre>";print_r($room_type_deatils_array['RatePlans']['RatePlan'][$i]);
                                // foreach($room_type_deatils_array['RatePlans']['RatePlan'] as $key2=>$val2){
                                //     $rate_id   = $val2['@attributes']['RatePlanCode'];
                                //     $rate_name = $val2['RatePlanDescription']['@attributes']['Name'];
                                //     $this->saveRatePlan($hotel_id,$ota_id,$ota_name,$roomID,$roomName,$rate_id,$rate_name,$validate_from,$validate_to,$rate_active);
                                // }
                            }
                        }
                        $i++;
                    }
                }else{//if multiple room types
                    //echo "<pre>";print_r($room_type_deatils_array);exit;
                    $j = 0;
                    foreach($room_type_deatils_array as $ke=>$v1){
                        $roomID   = $v1['RoomTypes']['RoomType']['@attributes']['RoomTypeCode'];
                        $roomName = $v1['RoomTypes']['RoomType']['RoomDescription']['@attributes']['Name'];
                        $isActive = "true";
                        $save_room = $this->saveRoomType($hotel_id,$ota_id,$ota_name,$roomID,$roomName,$isActive);
                        //echo "<pre>";print_r($room_type_deatils_array);exit;
                        if($save_room){
                            if(isset($v1['RatePlans']['RatePlan']['@attributes'])){
                                $rate_id = $v1['RatePlans']['RatePlan']['@attributes']['RatePlanCode'];
                                $rate_name = $v1['RatePlans']['RatePlan']['RatePlanDescription']['@attributes']['Name'];
                                $this->saveRatePlan($hotel_id,$ota_id,$ota_name,$roomID,$roomName,$rate_id,$rate_name,$validate_from,$validate_to,$rate_active);
                            }else{
                                $rate_id   = $v1['RatePlans']['RatePlan'][$J]['@attributes']['RatePlanCode'];
                                $rate_name = $v1['RatePlans']['RatePlan'][$j]['RatePlanDescription']['@attributes']['Name'];
                                $this->saveRatePlan($hotel_id,$ota_id,$ota_name,$roomID,$roomName,$rate_id,$rate_name,$validate_from,$validate_to,$rate_active);
                                //echo "<pre>";print_r($room_type_deatils_array['RatePlans']['RatePlan'][$i]);
                                // foreach($room_type_deatils_array['RatePlans']['RatePlan'] as $key2=>$val2){
                                //     $rate_id   = $val2['@attributes']['RatePlanCode'];
                                //     $rate_name = $val2['RatePlanDescription']['@attributes']['Name'];
                                //     $this->saveRatePlan($hotel_id,$ota_id,$ota_name,$roomID,$roomName,$rate_id,$rate_name,$validate_from,$validate_to,$rate_active);
                                // }
                            }
                        }
                        $j++;
                    }
                }
        }
        $otalog = new LogTable();
        /*----------------otalog Save -------------------*/
        $otalog->hotel_id            = $hotel_id;
        $otalog->ota_id              = $ota_id;
        $otalog->request_msg         = $xml;
        $otalog->response_msg        = $response;
        $otalog->request_url           = $url;
        $ota_log                     = $otalog->save();
        
        $res = array('status'=>0,"message"=>"Room type is not active in Goibibo");
        return response()->json($res);
    }
    public function travelguruCmHotel($ota_hotel_code,$commonurl,$auth_parameter,$hotel_id,$ota_id,$ota_name)
    {
        $fromDate          = date("Y-m-d");
        $toDate            = date("Y-m-t",strtotime('+6 month'));
        $messagePassword   = trim($auth_parameter->MessagePassword);
        $id                = trim($auth_parameter->ID);
        $headers           = array (
            //Regulates versioning of the XML interface for the API
            'Content-Type: application/xml',
            );
        $xml ='<?xml version="1.0" encoding="UTF-8" standalone="yes" ?>
            <OTA_HotelAvailRQ xmlns="http://www.opentravel.org/OTA/2003/05">
            <POS>
            <Source>
            <RequestorID MessagePassword="'.$messagePassword.'" ID="'.$id.'" Type="CHM"/>
            </Source>
            </POS>
            <AvailRequestSegments>
            <AvailRequestSegment>
            <HotelSearchCriteria>
            <Criterion>
            <HotelRef HotelCode="'.$ota_hotel_code.'"/>
            <StayDateRange Start="'.$fromDate.'" End="'.$toDate.'">
            </StayDateRange>
            </Criterion>
            </HotelSearchCriteria>
            </AvailRequestSegment>
            </AvailRequestSegments>
            </OTA_HotelAvailRQ>';
       $url = $commonurl.'detail/hotels';
       $ch  = curl_init();
       curl_setopt( $ch, CURLOPT_URL, $url );
       curl_setopt( $ch, CURLOPT_POST, true );
       curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
       curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
       curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml);
       $response = curl_exec($ch);
       curl_close($ch);
       $array_data=json_decode(json_encode(simplexml_load_string($response)), true);
       $parser=simplexml_load_string($response);
       if(isset($array_data["Errors"])){//Means Error Tag
        $res=array('status'=>0,"message"=>$array_data["Errors"]["Error"],"err"=>$array_data["Errors"]["Error"]);
        return response()->json($res);
        }
       if(isset($array_data["@attributes"]['ErrorCode'])){//Means Error Tag
        $res=array('status'=>0,"message"=>"Travelguru Room type and Room Rate fetch failed","err"=>$parser["@attributes"]['ErrorMessage']);
        return response()->json($res);
        }
       $room_type_deatils = $parser->RatePlans->RatePlan;

       foreach ($room_type_deatils as $key => $value) {
       $roomArrays = $value->BookingRules->InventoryInfo->attributes();
       $roomID     = $roomArrays['InvCode'];
       $roomName   = $roomArrays['InvType'];

       $cmOtaRoomTypeFetchModel = new CmOtaRoomTypeFetch();
       $room_active             = 2;
       $room_code               = $roomID;
       $room_name               = $roomName;

       /*----------------cmOtaRoomTypeFetchModel Save -------------------*/

       $cmOtaRoomTypeFetchModel->hotel_id            = $hotel_id;
       $cmOtaRoomTypeFetchModel->ota_id              = $ota_id;
       $cmOtaRoomTypeFetchModel->ota_name            = $ota_name;
       $cmOtaRoomTypeFetchModel->ota_room_type_id    = $room_code;
       $cmOtaRoomTypeFetchModel->ota_room_type_name  = $room_name;
       $cmOtaRoomTypeFetchModel->active              = $room_active;
       $room_type_fetch_status                       = $cmOtaRoomTypeFetchModel->save();

       $room_details[]  = array("room_code"=>"$room_code","room_name"=>"$room_name");
       }
       foreach ($room_details as $room_detail)
       {
       $room_code = $room_detail["room_code"];
       $room_name = $room_detail["room_name"];

       foreach ($room_type_deatils as $key => $value)
       {
       $roomArrays     = $value->BookingRules->InventoryInfo->attributes();
       $roomID         = $roomArrays['InvCode'];
       $roomName       = $roomArrays['InvType'];
       $rateArrays     = $value->attributes();
       
       if($roomID == $room_code ){

       $rateID         = $rateArrays->RatePlanID;
       $rateNameArray  = explode('(',$rateArrays->RatePlanType);
       $add_plan = array();
       if(isset($rateNameArray[1])){
            if(strlen($rateNameArray[1])>3){
                $add_plan = explode(')',$rateNameArray[1]);
            }
       }
       if($add_plan){
        $rateName       = $rateNameArray[0].$add_plan[1];
       }
       else{
        $rateName       = $rateNameArray[0];
       }
       $rate_type      = $rateArrays->RatePlanCategory;

       $rate_id       = $rateID;
       $rate_name     = $rateName;
       $ota_rate_type = $rate_type;
       $validate_from = "";
       $validate_to   = "";
       $rate_active   = 2;
       $cmOtaRateTypeFetchModel = new CmOtaRateTypeFetch();
       /*----------------cmOtaRateTypeFetchModel Save -------------------*/

       $cmOtaRateTypeFetchModel->hotel_id            = $hotel_id;
       $cmOtaRateTypeFetchModel->ota_id              = $ota_id;
       $cmOtaRateTypeFetchModel->ota_name            = $ota_name;
       $cmOtaRateTypeFetchModel->ota_room_type_id    = $room_code;
       $cmOtaRateTypeFetchModel->ota_room_type_name  = $room_name;
       $cmOtaRateTypeFetchModel->ota_rate_type_id    = $rate_id;
       $cmOtaRateTypeFetchModel->ota_rate_type_name  = $rate_name;
       $cmOtaRateTypeFetchModel->ota_rate_type       = $ota_rate_type;
       $cmOtaRateTypeFetchModel->validate_from       = $validate_from;
       $cmOtaRateTypeFetchModel->validate_to         = $validate_to;
       $cmOtaRateTypeFetchModel->active              = $rate_active;
       $rate_type_fetch_status                       = $cmOtaRateTypeFetchModel->save();

       }
       }
       }

       $otalog = new LogTable();
       /*----------------otalog Save -------------------*/
       $otalog->hotel_id            = $hotel_id;
       $otalog->ota_id              = $ota_id;
       $otalog->request_msg         = $xml;
       $otalog->response_msg         = $response;
       $otalog->request_url          = $url;
       $ota_log                     = $otalog->save();
       $res=array('status'=>1,"message"=>"Travelguru Room type and Room Rate fetch successfull");
       return response()->json($res);
    }

    public function cleartripCmHotel($ota_hotel_code,$commonurl,$auth_parameter,$hotel_id,$ota_id,$ota_name)
    {

        if(isset($auth_parameter->api_key))
        {
            $api_key           = trim($auth_parameter->api_key);
        }
        else
        {
            $api_key="";
        }
        $headers = array (
            'Content-Type: application/xml',
            'X-CT-SOURCETYPE: API',
            'X-CT-API-KEY: '.trim($api_key),
            );

    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
    <hotel-room-types xmlns="http://www.cleartrip.com/extranet/hotel-room-types" type="get">
    <hotel-id>'.$ota_hotel_code.'</hotel-id>
    </hotel-room-types>';
    $curlService = new \Ixudra\Curl\CurlService();
    $url = $commonurl.'get-room-types';
    $response = $curlService->to($url)
    ->withData($xml)
    ->withHeaders($headers)
    ->post();
    $array_data         = json_decode(json_encode(simplexml_load_string($response)), true);
    if(!$array_data){//Means Error Tag
        $res=array('status'=>0,"message"=>"Cleartrip Room type and Room Rate fetch failed","err"=>"User Not Authorized");
        return response()->json($res);
        }
    $room_type_deatils      = $array_data['room-types']['room-type'];
    $isMultidimensional = $this->commonService->isMultidimensionalArray($room_type_deatils);

    if($isMultidimensional){

    foreach ($room_type_deatils as $key => $value) {
    if($value['room-status'] == "Active"){
    $cmOtaRoomTypeFetchModel = new CmOtaRoomTypeFetch();
    $room_active             = 1;
    $room_code               = $value['room-id'];
    $room_name               = $value['room-name'];

    /*----------------cmOtaRoomTypeFetchModel Save -------------------*/

    $cmOtaRoomTypeFetchModel->hotel_id            = $hotel_id;
    $cmOtaRoomTypeFetchModel->ota_id              = $ota_id;
    $cmOtaRoomTypeFetchModel->ota_name            = $ota_name;
    $cmOtaRoomTypeFetchModel->ota_room_type_id    = $room_code;
    $cmOtaRoomTypeFetchModel->ota_room_type_name  = $room_name;
    $cmOtaRoomTypeFetchModel->active              = $room_active;
    $room_type_fetch_status                       = $cmOtaRoomTypeFetchModel->save();

    $room_details[]  = array("room_code"=>"$room_code","room_name"=>"$room_name");
    }
    }
    }
    else
    {
    if($room_type_deatils['room-status'] == "Active"){
    $cmOtaRoomTypeFetchModel = new CmOtaRoomTypeFetch();
    $room_active             = 1;
    $room_code               = $room_type_deatils['room-id'];
    $room_name               = $room_type_deatils['room-name'];
    /*----------------cmOtaRoomTypeFetchModel Save -------------------*/

    $cmOtaRoomTypeFetchModel->hotel_id            = $hotel_id;
    $cmOtaRoomTypeFetchModel->ota_id              = $ota_id;
    $cmOtaRoomTypeFetchModel->ota_name            = $ota_name;
    $cmOtaRoomTypeFetchModel->ota_room_type_id    = $room_code;
    $cmOtaRoomTypeFetchModel->ota_room_type_name  = $room_name;
    $cmOtaRoomTypeFetchModel->active              = $room_active;
    $room_type_fetch_status                       = $cmOtaRoomTypeFetchModel->save();

    $room_details[]  = array("room_code"=>"$room_code","room_name"=>"$room_name");
    }
    }
    foreach ($room_details as $key => $value)
     {
    $room_code      = $value['room_code'];
    $room_name      = $value['room_name'];

    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
    <hotel-rate-plan xmlns="http://www.cleartrip.com/extranet/hotel-rate-plan"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" type="get"
    xsi:schemaLocation="http://www.cleartrip.com/extranet/hotel-rate-plan hotel-rate-plan.xsd">
    <hotel-id>'.$ota_hotel_code.'</hotel-id>
    <room-id>'.$room_code.'</room-id>
    </hotel-rate-plan>';

    $url = $commonurl.'get-rate-plan';
    $response = $curlService->to($url)
    ->withData($xml)
    ->withHeaders($headers)
    ->post();
    $array_data=json_decode(json_encode(simplexml_load_string($this->removeNamespaceFromXML($response))), true);

    if(isset($array_data['rate-plans']['rate-plan'])){
     $rate_plan_deatils  = $array_data['rate-plans']['rate-plan'];
     $isMultidimensional = $this->commonService->isMultidimensionalArray($rate_plan_deatils);
    if($isMultidimensional)
    {
    foreach($rate_plan_deatils as $k => $v)
    {
    if($v['rate-status'] == "Active"){
    $cmOtaRateTypeFetchModel = new CmOtaRateTypeFetch();

    $rate_id       = $v['rate-id'];
    $rate_name     = $v['rate-name'];
    $ota_rate_type = $v['rate-type'];
    $validate_from = $v['rate-start-date'];
    $validate_to   = $v['rate-end-date'];
    $rate_active   = 1;

    /*----------------cmOtaRateTypeFetchModel Save -------------------*/

    $cmOtaRateTypeFetchModel->hotel_id            = $hotel_id;
    $cmOtaRateTypeFetchModel->ota_id              = $ota_id;
    $cmOtaRateTypeFetchModel->ota_name            = $ota_name;
    $cmOtaRateTypeFetchModel->ota_room_type_id    = $room_code;
    $cmOtaRateTypeFetchModel->ota_room_type_name  = $room_name;
    $cmOtaRateTypeFetchModel->ota_rate_type_id    = $rate_id;
    $cmOtaRateTypeFetchModel->ota_rate_type_name  = $rate_name;
    $cmOtaRateTypeFetchModel->ota_rate_type       = $ota_rate_type;
    $cmOtaRateTypeFetchModel->validate_from       = $validate_from;
    $cmOtaRateTypeFetchModel->validate_to         = $validate_to    ;
    $cmOtaRateTypeFetchModel->active              = $rate_active;
    $rate_type_fetch_status                       = $cmOtaRateTypeFetchModel->save();

    }
    }
    }else{
    if($rate_plan_deatils['rate-status'] == "Active"){
    $cmOtaRateTypeFetchModel = new CmOtaRateTypeFetch();

    $rate_id       = $rate_plan_deatils['rate-id'];
    $rate_name     = $rate_plan_deatils['rate-name'];
    $ota_rate_type = $rate_plan_deatils['rate-type'];
    $validate_from = $rate_plan_deatils['rate-start-date'];
    $validate_to   = $rate_plan_deatils['rate-end-date'];
    $rate_active   = 1;

    /*----------------cmOtaRateTypeFetchModel Save -------------------*/

    $cmOtaRateTypeFetchModel->hotel_id            = $hotel_id;
    $cmOtaRateTypeFetchModel->ota_id              = $ota_id;
    $cmOtaRateTypeFetchModel->ota_name            = $ota_name;
    $cmOtaRateTypeFetchModel->ota_room_type_id    = $room_code;
    $cmOtaRateTypeFetchModel->ota_room_type_name  = $room_name;
    $cmOtaRateTypeFetchModel->ota_rate_type_id    = $rate_id;
    $cmOtaRateTypeFetchModel->ota_rate_type_name  = $rate_name;
    $cmOtaRateTypeFetchModel->ota_rate_type       = $ota_rate_type;
    $cmOtaRateTypeFetchModel->validate_from       = $validate_from;
    $cmOtaRateTypeFetchModel->validate_to         = $validate_to    ;
    $cmOtaRateTypeFetchModel->active              = $rate_active;
    $rate_type_fetch_status                       = $cmOtaRateTypeFetchModel->save();

    }
    }
    } // rate plan avilabe.
    } // foreach Closed for room_details

    $otalog = new LogTable();
    /*----------------otalog Save -------------------*/
    $otalog->hotel_id            = $hotel_id;
    $otalog->ota_id              = $ota_id;
    $otalog->request_msg         = $xml;
    $otalog->response_msg        = $response;
    $otalog->request_url         = $url;
    $ota_log                     = $otalog->save();

    $res=array('status'=>1,"message"=>"Cleartrip Room type and Room Rate fetch successfull");
    return response()->json($res);
    }

    public function viaCmHotel($ota_hotel_code,$commonurl,$auth_parameter,$hotel_id,$ota_id,$ota_name)
    {
        $source           = trim($auth_parameter->source);
        $auth           = trim($auth_parameter->auth);
        $curlService = new \Ixudra\Curl\CurlService();
        $url = $commonurl.'newWebserviceAPI?actionId=cm_hotelallroomcode&typeId=hotel&source='.$source.'&auth='.$auth.'&requestData={hotelId:'.$ota_hotel_code.'}';

        $response = $curlService->to($url)
        ->get();
        $array_data         = json_decode($response);
        if(isset($array_data->error)){//Means Error Tag
            $res=array('status'=>0,"message"=>"Via.com Room type and Room Rate fetch failed","err"=>$array_data->msg);
            return response()->json($res);

        }
        $room_type_deatils = $array_data->RoomList;
        $rate_type_deatils = $array_data->RatePlanList;

        foreach ($room_type_deatils as $key => $value)
        {
        $roomID                  = $value->roomId;
        $roomName                = $value->roomName;

        $cmOtaRoomTypeFetchModel = new CmOtaRoomTypeFetch();
        $room_active             = 2;
        $room_code               = $roomID;
        $room_name               = $roomName;

        /*----------------cmOtaRoomTypeFetchModel Save -------------------*/

        $cmOtaRoomTypeFetchModel->hotel_id            = $hotel_id;
        $cmOtaRoomTypeFetchModel->ota_id              = $ota_id;
        $cmOtaRoomTypeFetchModel->ota_name            = $ota_name;
        $cmOtaRoomTypeFetchModel->ota_room_type_id    = $room_code;
        $cmOtaRoomTypeFetchModel->ota_room_type_name  = $room_name;
        $cmOtaRoomTypeFetchModel->active              = $room_active;
        $room_type_fetch_status                       = $cmOtaRoomTypeFetchModel->save();

        $room_details[]  = array("room_code"=>"$room_code","room_name"=>"$room_name");
        }

        foreach ($room_details as $room_detail)
        {
        $room_code = $room_detail["room_code"];
        $room_name = $room_detail["room_name"];

        foreach ($rate_type_deatils as $key => $value)
        {
        $roomID    = $value->roomId;
        if($roomID == $room_code ){
        $rateID    = $value->ratePlanCode;
        $rateName  = $value->ratePlanName;
        $rate_type = implode(",", $value->channels);


        $rate_id       = $rateID;
        $rate_name     = $rateName;
        $ota_rate_type = $rate_type;
        $validate_from = "";
        $validate_to   = "";
        $rate_active   = 2;

        $cmOtaRateTypeFetchModel = new CmOtaRateTypeFetch();
        /*----------------cmOtaRateTypeFetchModel Save -------------------*/

        $cmOtaRateTypeFetchModel->hotel_id            = $hotel_id;
        $cmOtaRateTypeFetchModel->ota_id              = $ota_id;
        $cmOtaRateTypeFetchModel->ota_name            = $ota_name;
        $cmOtaRateTypeFetchModel->ota_room_type_id    = $room_code;
        $cmOtaRateTypeFetchModel->ota_room_type_name  = $room_name;
        $cmOtaRateTypeFetchModel->ota_rate_type_id    = $rate_id;
        $cmOtaRateTypeFetchModel->ota_rate_type_name  = $rate_name;
        $cmOtaRateTypeFetchModel->ota_rate_type       = $ota_rate_type;
        $cmOtaRateTypeFetchModel->validate_from       = $validate_from;
        $cmOtaRateTypeFetchModel->validate_to         = $validate_to;
        $cmOtaRateTypeFetchModel->active              = $rate_active;
        $rate_type_fetch_status                       = $cmOtaRateTypeFetchModel->save();
        }
        }
        }

        $otalog = new LogTable();
        /*----------------otalog Save -------------------*/
        $otalog->hotel_id            = $hotel_id;
        $otalog->ota_id              = $ota_id;
        $otalog->request_msg         = $url;
        $otalog->response_msg        = $response;
        $otalog->request_url         = $url;
        $ota_log                     = $otalog->save();
        $res=array('status'=>1,"message"=>"Via.com Room type and Room Rate fetch successfull");
        return response()->json($res);
    }
    public function agodaCmHotel($ota_hotel_code,$commonurl,$auth_parameter,$hotel_id,$ota_id,$ota_name)
    {
        $date              = new \DateTime();
        $dateTimestamp     = $date->getTimestamp();
        $apiKey           = trim($auth_parameter->apiKey);

        $xml ='<?xml version="1.0" encoding="UTF-8"?>
        <request timestamp="'.$dateTimestamp.'" type="5">
        <criteria language="EN">
        <property id="'.$ota_hotel_code.'">
        </property>
        </criteria>
        </request>';
        $curlService = new \Ixudra\Curl\CurlService();
        $url = $commonurl.'api?apiKey='.$apiKey;

        $response = $curlService->to($url)
        ->withData($xml)
        ->withContentType('application/xml')
        ->post();

        $array_data   = json_decode(json_encode(simplexml_load_string($response)), true);
        if(isset($array_data['errors']['error'])){//Means Error Tag
            $res=array('status'=>0,"message"=>"Agoda Room type and Room Rate fetch failed","err"=>$array_data['errors']['error']["@attributes"]["description"]);
            return response()->json($res);
        }
        
        if(isset($array_data['property']['rooms']['room'])){
        $room_type_deatils = $array_data['property']['rooms']['room'];
        }
        $room_count = count($room_type_deatils);
        foreach ($room_type_deatils as $key => $value) {
        /*--------- if room count is greter than one ----------*/
        if($room_count >1){
        $room_code = $value['@attributes']['room_id'];
        $room_name = $value['@attributes']['room_name'];
        }else{
        $room_code = $value['room_id'];
        $room_name = $value['room_name'];
        }
        $cmOtaRoomTypeFetchModel                     = new CmOtaRoomTypeFetch();
        $room_active                                 = 2;

        /*----------------cmOtaRoomTypeFetchModel Save -------------------*/

        $cmOtaRoomTypeFetchModel->hotel_id            = $hotel_id;
        $cmOtaRoomTypeFetchModel->ota_id              = $ota_id;
        $cmOtaRoomTypeFetchModel->ota_name            = $ota_name;
        $cmOtaRoomTypeFetchModel->ota_room_type_id    = $room_code;
        $cmOtaRoomTypeFetchModel->ota_room_type_name  = $room_name;
        $cmOtaRoomTypeFetchModel->active              = $room_active;
        $room_type_fetch_status                       = $cmOtaRoomTypeFetchModel->save();
        $room_details[]  = array("room_code"=>"$room_code","room_name"=>"$room_name");
        }
        $property_type_deatils        = $array_data['property']['products']['product'];
        $rateplan_deatils             = $array_data['property']['rateplans']['rateplan'];
        $property_type_deatils_count  = count($property_type_deatils);
        $t=0;
        foreach ($property_type_deatils as $property) {

                if(isset($property['@attributes']))
                {
                    $rateplan_id =  $property['@attributes']['rateplan_id'];
                    foreach ($rateplan_deatils as $key => $value) {
                        if(isset($value['@attributes'])){
                            if($value['@attributes']['rateplan_id'] == $rateplan_id){
                                    $rate_id              = $value['@attributes']['rateplan_id'];
                                    $rate_name            = $value['@attributes']['rateplan_name'];
                                    $ota_rate_type        = $value['@attributes']['rate_type'];
                                    $validate_from        = $value['@attributes']['stay_start'];
                                    $validate_to          = $value['@attributes']['stay_end'];
                        }
                        }else{
                            if(isset($value['rateplan_id'])){
                                if($value['rateplan_id'] == $rateplan_id){
                                    $rate_id       = $value['rateplan_id'];
                                    $rate_name     = $value['rateplan_name'];
                                    $ota_rate_type = $value['rate_type'];
                                    $validate_from = $value['stay_start'];
                                    $validate_to   = $value['stay_end'];
                                    }
                                }
                            }
                        }

                }
                else
                {
                    $rateplan_id =  $property['rateplan_id'];
                    foreach ($rateplan_deatils as $key => $value) {
                        if(isset($value['@attributes'])){
                            if($value['@attributes']['rateplan_id'] == $rateplan_id){
                                    $rate_id              = $value['@attributes']['rateplan_id'];
                                    $rate_name            = $value['@attributes']['rateplan_name'];
                                    $ota_rate_type        = $value['@attributes']['rate_type'];
                                    $validate_from        = $value['@attributes']['stay_start'];
                                    $validate_to          = $value['@attributes']['stay_end'];
                        }
                        }else{
                            if(isset($value['rateplan_id'])){
                                if($value['rateplan_id'] == $rateplan_id){
                                    $rate_id       = $value['rateplan_id'];
                                    $rate_name     = $value['rateplan_name'];
                                    $ota_rate_type = $value['rate_type'];
                                    $validate_from = $value['stay_start'];
                                    $validate_to   = $value['stay_end'];
                                    }
                                }
                            }
                        }
                }

                $cmOtaRateTypeFetchModel = new CmOtaRateTypeFetch();
                $rate_active   = 2;

               if(isset($property['@attributes']))
               {
                $room_code = $property['@attributes']['room_id'];
               }
               else
               {
                $room_code = $property['room_id'];
               }

                $room_name =  $this->getRoomName($room_details,$room_code);
              /*----------------cmOtaRateTypeFetchModel Save -------------------*/

              $cmOtaRateTypeFetchModel->hotel_id            = $hotel_id;
              $cmOtaRateTypeFetchModel->ota_id              = $ota_id;
              $cmOtaRateTypeFetchModel->ota_name            = $ota_name;
              $cmOtaRateTypeFetchModel->ota_room_type_id    = $room_code;
              $cmOtaRateTypeFetchModel->ota_room_type_name  = $room_name;
              $cmOtaRateTypeFetchModel->ota_rate_type_id    = $rate_id;
              $cmOtaRateTypeFetchModel->ota_rate_type_name  = $rate_name;
              $cmOtaRateTypeFetchModel->ota_rate_type       = $ota_rate_type;
              $cmOtaRateTypeFetchModel->validate_from       = $validate_from;
              $cmOtaRateTypeFetchModel->validate_to         = $validate_to;
              $cmOtaRateTypeFetchModel->active              = $rate_active;
              $rate_type_fetch_status                       = $cmOtaRateTypeFetchModel->save();

        }

        $otalog = new LogTable();
        /*----------------otalog Save -------------------*/
        $otalog->hotel_id            = $hotel_id;
        $otalog->ota_id              = $ota_id;
        $otalog->request_msg         = $xml;
        $otalog->response_msg        = $response;
        $otalog->request_url         = $url;
        $ota_log                     = $otalog->save();
        $res=array('status'=>1,"message"=>"Agoda Room type and Room Rate fetch successfull");
        return response()->json($res);
    }
    //Agoda Room name
    public function getRoomName($room_details,$room_code)
    {
        $room_name="";
        foreach($room_details as $room_detail)
        {
            if($room_detail['room_code']==$room_code)
            {
                $room_name=$room_detail['room_name'];
            }
        }
        return $room_name;
    }
    public function bokingComCmHotel($ota_hotel_code,$commonurl,$auth_parameter,$hotel_id,$ota_id,$ota_name)
    {
        $username          = trim($auth_parameter->username);
        $password          = trim($auth_parameter->password);
        $xml ='<?xml version="1.0" encoding="UTF-8"?>
        <request>
        <username>'.$username.'</username>
        <password>'.$password.'</password>
        <hotel_id>'.$ota_hotel_code.'</hotel_id>
        </request>';
        $curlService = new \Ixudra\Curl\CurlService();

        $url = $commonurl.'roomrates';
        $response = $curlService->to($url)
        ->withData($xml)
        ->post();

        $array_data         = json_decode(json_encode(simplexml_load_string($response)), true);
        if(isset($array_data['fault'])){//Means Error Tag
            $res=array('status'=>0,"message"=>"Booking.com Room type and Room Rate fetch failed","err"=>$array_data['fault']["@attributes"]["string"]);
            return response()->json($res);
        }
        if(isset($array_data['room']['@attributes']))
        {
            $roomID   = $array_data['room']['@attributes']['id'];
            $roomName = $array_data['room']['@attributes']['room_name'];

            $cmOtaRoomTypeFetchModel = new CmOtaRoomTypeFetch();
            $room_active             = 2;
            $room_code               = $roomID;
            $room_name               = $roomName;
            /*----------------cmOtaRoomTypeFetchModel Save -------------------*/

            $cmOtaRoomTypeFetchModel->hotel_id            = $hotel_id;
            $cmOtaRoomTypeFetchModel->ota_id              = $ota_id;
            $cmOtaRoomTypeFetchModel->ota_name            = $ota_name;
            $cmOtaRoomTypeFetchModel->ota_room_type_id    = $room_code;
            $cmOtaRoomTypeFetchModel->ota_room_type_name  = $room_name;
            $cmOtaRoomTypeFetchModel->active              = $room_active;
            $room_type_fetch_status                       = $cmOtaRoomTypeFetchModel->save();

            $ratesDetails = $array_data['room']['rates']['rate'];

            if(isset($ratesDetails['@attributes'])){

            $rateID        = $ratesDetails['@attributes']['id'];
            $rateName      = $ratesDetails['@attributes']['rate_name'];

            $rate_id       = $rateID;
            $rate_name     = $rateName;
            $ota_rate_type = "Net";
            $validate_from = "";
            $validate_to   = "";
            $rate_active   = 1;

            $cmOtaRateTypeFetchModel = new CmOtaRateTypeFetch();
            /*----------------cmOtaRateTypeFetchModel Save -------------------*/

            $cmOtaRateTypeFetchModel->hotel_id            = $hotel_id;
            $cmOtaRateTypeFetchModel->ota_id              = $ota_id;
            $cmOtaRateTypeFetchModel->ota_name            = $ota_name;
            $cmOtaRateTypeFetchModel->ota_room_type_id    = $room_code;
            $cmOtaRateTypeFetchModel->ota_room_type_name  = $room_name;
            $cmOtaRateTypeFetchModel->ota_rate_type_id    = $rate_id;
            $cmOtaRateTypeFetchModel->ota_rate_type_name  = $rate_name;
            $cmOtaRateTypeFetchModel->ota_rate_type       = $ota_rate_type;
            $cmOtaRateTypeFetchModel->validate_from       = $validate_from;
            $cmOtaRateTypeFetchModel->validate_to         = $validate_to;
            $cmOtaRateTypeFetchModel->active              = $rate_active;
            $rate_type_fetch_status                       = $cmOtaRateTypeFetchModel->save();

            }else{

            /*$isMultidimensional = Otacustomhelper::isMultidimensionalArray($ratesDetails);
            if($isMultidimensional){
            */
            foreach ($ratesDetails as $k => $v) {
            $rateID        = $v['@attributes']['id'];
            $rateName      = $v['@attributes']['rate_name'];

            $rate_id       = $rateID;
            $rate_name     = $rateName;
            $ota_rate_type = "Net";
            $validate_from = "";
            $validate_to   = "";
            $rate_active   = 1;

            $cmOtaRateTypeFetchModel = new CmOtaRateTypeFetch();
            /*----------------cmOtaRateTypeFetchModel Save -------------------*/

            $cmOtaRateTypeFetchModel->hotel_id            = $hotel_id;
            $cmOtaRateTypeFetchModel->ota_id              = $ota_id;
            $cmOtaRateTypeFetchModel->ota_name            = $ota_name;
            $cmOtaRateTypeFetchModel->ota_room_type_id    = $room_code;
            $cmOtaRateTypeFetchModel->ota_room_type_name  = $room_name;
            $cmOtaRateTypeFetchModel->ota_rate_type_id    = $rate_id;
            $cmOtaRateTypeFetchModel->ota_rate_type_name  = $rate_name;
            $cmOtaRateTypeFetchModel->ota_rate_type       = $ota_rate_type;
            $cmOtaRateTypeFetchModel->validate_from       = $validate_from;
            $cmOtaRateTypeFetchModel->validate_to         = $validate_to;
            $cmOtaRateTypeFetchModel->active              = $rate_active;
            $rate_type_fetch_status                       = $cmOtaRateTypeFetchModel->save();
            }
            }
        }
        else
        {
            foreach ($array_data['room'] as $key => $value)
            {
            $roomID   = $value['@attributes']['id'];
            $roomName = $value['@attributes']['room_name'];

            $cmOtaRoomTypeFetchModel = new CmOtaRoomTypeFetch();
            $room_active             = 2;
            $room_code               = $roomID;
            $room_name               = $roomName;
            /*----------------cmOtaRoomTypeFetchModel Save -------------------*/

            $cmOtaRoomTypeFetchModel->hotel_id            = $hotel_id;
            $cmOtaRoomTypeFetchModel->ota_id              = $ota_id;
            $cmOtaRoomTypeFetchModel->ota_name            = $ota_name;
            $cmOtaRoomTypeFetchModel->ota_room_type_id    = $room_code;
            $cmOtaRoomTypeFetchModel->ota_room_type_name  = $room_name;
            $cmOtaRoomTypeFetchModel->active              = $room_active;
            $room_type_fetch_status                       = $cmOtaRoomTypeFetchModel->save();

            $ratesDetails = $value['rates']['rate'];
            if(isset($ratesDetails['@attributes']))
            {

            $rateID        = $ratesDetails['@attributes']['id'];
            $rateName      = $ratesDetails['@attributes']['rate_name'];

            $rate_id       = $rateID;
            $rate_name     = $rateName;
            $ota_rate_type = "Net";
            $validate_from = "";
            $validate_to   = "";
            $rate_active   = 1;

            $cmOtaRateTypeFetchModel = new CmOtaRateTypeFetch();
            /*----------------cmOtaRateTypeFetchModel Save -------------------*/

            $cmOtaRateTypeFetchModel->hotel_id            = $hotel_id;
            $cmOtaRateTypeFetchModel->ota_id              = $ota_id;
            $cmOtaRateTypeFetchModel->ota_name            = $ota_name;
            $cmOtaRateTypeFetchModel->ota_room_type_id    = $room_code;
            $cmOtaRateTypeFetchModel->ota_room_type_name  = $room_name;
            $cmOtaRateTypeFetchModel->ota_rate_type_id    = $rate_id;
            $cmOtaRateTypeFetchModel->ota_rate_type_name  = $rate_name;
            $cmOtaRateTypeFetchModel->ota_rate_type       = $ota_rate_type;
            $cmOtaRateTypeFetchModel->validate_from       = $validate_from;
            $cmOtaRateTypeFetchModel->validate_to         = $validate_to;
            $cmOtaRateTypeFetchModel->active              = $rate_active;
            $rate_type_fetch_status                       = $cmOtaRateTypeFetchModel->save();

            }
            else
            {
                foreach ($ratesDetails as $k => $v)
                {
                $rateID        = $v['@attributes']['id'];
                $rateName      = $v['@attributes']['rate_name'];

                $rate_id       = $rateID;
                $rate_name     = $rateName;
                $ota_rate_type = "Net";
                $validate_from = "";
                $validate_to   = "";
                $rate_active   = 1;

                $cmOtaRateTypeFetchModel = new CmOtaRateTypeFetch();
                /*----------------cmOtaRateTypeFetchModel Save -------------------*/

                $cmOtaRateTypeFetchModel->hotel_id            = $hotel_id;
                $cmOtaRateTypeFetchModel->ota_id              = $ota_id;
                $cmOtaRateTypeFetchModel->ota_name            = $ota_name;
                $cmOtaRateTypeFetchModel->ota_room_type_id    = $room_code;
                $cmOtaRateTypeFetchModel->ota_room_type_name  = $room_name;
                $cmOtaRateTypeFetchModel->ota_rate_type_id    = $rate_id;
                $cmOtaRateTypeFetchModel->ota_rate_type_name  = $rate_name;
                $cmOtaRateTypeFetchModel->ota_rate_type       = $ota_rate_type;
                $cmOtaRateTypeFetchModel->validate_from       = $validate_from;
                $cmOtaRateTypeFetchModel->validate_to         = $validate_to;
                $cmOtaRateTypeFetchModel->active              = $rate_active;
                $rate_type_fetch_status                       = $cmOtaRateTypeFetchModel->save();
                }
            }
            } //$array_data['room'] closed here.
        }
          $otalog = new LogTable();
          /*----------------otalog Save -------------------*/
          $otalog->hotel_id            = $hotel_id;
          $otalog->ota_id              = $ota_id;
          $otalog->request_msg         = $xml;
          $otalog->response_msg          = $response;
          $otalog->request_url       = $url;
          $ota_log                     = $otalog->save();

          $res=array('status'=>1,"message"=>"Booking.com Room type and Room Rate fetch successfull");
          return response()->json($res);

    }
    public function expediaComCmHotel($ota_hotel_code,$commonurl,$auth_parameter,$hotel_id,$ota_id,$ota_name)
    {
        $username          = trim($auth_parameter->username);
        $password          = trim($auth_parameter->password);
        $auth              = "$username:$password";
       $curlService = new \Ixudra\Curl\CurlService();
       $url = $commonurl.'products/properties/'.$ota_hotel_code.'/roomTypes';
       $response = $curlService->to($url)
       ->withContentType('application/json')
       ->withOption('HTTPGET',true)
       ->withOption('USERPWD',$auth)
       ->withOption('SSL_VERIFYPEER',false)
       ->get();

       $array_data         = json_decode($response);
       if(isset($array_data->errors)){//Means Error Tag
        $res=array('status'=>0,"message"=>"Expedia Room type and Room Rate fetch failed","err"=>$array_data->errors[0]->message);
        return response()->json($res);
        }
       foreach ($array_data->entity as $key => $value)
        {
        $roomID     = $value->resourceId;
        $roomName   = $value->name->value;
        $isActive   = $value->status;
        if($isActive == "Active")
        {
        $cmOtaRoomTypeFetchModel = new CmOtaRoomTypeFetch();
        $room_active             = 1;
        $room_code               = $roomID;
        $room_name               = $roomName;

        /*----------------cmOtaRoomTypeFetchModel Save -------------------*/

        $cmOtaRoomTypeFetchModel->hotel_id            = $hotel_id;
        $cmOtaRoomTypeFetchModel->ota_id              = $ota_id;
        $cmOtaRoomTypeFetchModel->ota_name            = $ota_name;
        $cmOtaRoomTypeFetchModel->ota_room_type_id    = $room_code;
        $cmOtaRoomTypeFetchModel->ota_room_type_name  = $room_name;
        $cmOtaRoomTypeFetchModel->active              = $room_active;
        $room_type_fetch_status                       = $cmOtaRoomTypeFetchModel->save();

        $room_details[]  = array("room_code"=>"$room_code","room_name"=>"$room_name");
        }
        }

        foreach ($room_details as $room_detail)
        {
        $room_code = $room_detail["room_code"];
        $room_name = $room_detail["room_name"];

        $url       = $commonurl.'products/properties/'.$ota_hotel_code.'/roomTypes/'.$room_code.'/ratePlans';
        $curlService = new \Ixudra\Curl\CurlService();
        $response = $curlService->to($url)
        ->withContentType('application/json')
        ->withOption('HTTPGET',true)
        ->withOption('USERPWD',$auth)
        ->withOption('SSL_VERIFYPEER',false)
        ->get();
        $array_data         = json_decode($response);


        foreach ($array_data->entity as $key => $value)
        {
        if(isset($value->distributionRules[1]))
        {
        $rateID = $value->distributionRules[1]->expediaId;
        $rateName = $value->name;
        $rate_type = $value->rateAcquisitionType;
        }
        else
        {
        $rateID = $value->resourceId;
        $rateName = $value->name;
        $rate_type = $value->rateAcquisitionType;
        }
        $isActive   = $value->status;
        if($isActive == "Active"){
        $rate_id       = $rateID;
        $rate_name     = $rateName;
        $ota_rate_type = $rate_type;
        $validate_from = $value->bookDateStart;
        $validate_to   = $value->bookDateEnd;
        $rate_active   = 1;

        $cmOtaRateTypeFetchModel = new CmOtaRateTypeFetch();
        /*----------------cmOtaRateTypeFetchModel Save -------------------*/

        $cmOtaRateTypeFetchModel->hotel_id            = $hotel_id;
        $cmOtaRateTypeFetchModel->ota_id              = $ota_id;
        $cmOtaRateTypeFetchModel->ota_name            = $ota_name;
        $cmOtaRateTypeFetchModel->ota_room_type_id    = $room_code;
        $cmOtaRateTypeFetchModel->ota_room_type_name  = $room_name;
        $cmOtaRateTypeFetchModel->ota_rate_type_id    = $rate_id;
        $cmOtaRateTypeFetchModel->ota_rate_type_name  = $rate_name;
        $cmOtaRateTypeFetchModel->ota_rate_type       = $ota_rate_type;
        $cmOtaRateTypeFetchModel->validate_from       = $validate_from;
        $cmOtaRateTypeFetchModel->validate_to         = $validate_to;
        $cmOtaRateTypeFetchModel->active              = $rate_active;
        $rate_type_fetch_status                       = $cmOtaRateTypeFetchModel->save();

        }
        }
        } // $room_details foreach is end

        $otalog = new LogTable();
        /*----------------otalog Save -------------------*/
        $otalog->hotel_id            = $hotel_id;
        $otalog->ota_id              = $ota_id;
        $otalog->request_msg         = $url;
        $otalog->response_msg        = $response;
        $otalog->request_url         = $url;
        $ota_log                     = $otalog->save();
        $res=array('status'=>1,"message"=>"Expedia.com Room type and Room Rate fetch successfull");
        return response()->json($res);
    }
    //IDS Next
    public function idsNextComCmHotel($ota_hotel_code,$commonurl,$auth_parameter,$hotel_id,$ota_id,$ota_name)
    {
        $username          = trim($auth_parameter->username);
        $password          = trim($auth_parameter->password);
        $auth              = "$username:$password";
        $curlService = new \Ixudra\Curl\CurlService();
        $xml='<RN_HotelRatePlanRQ xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns="http://www.opentravel.org/OTA/2003/05" Version="1.2"
        EchoToken="879791878">
        <RoomRatePlans>
        <HotelCriteria HotelCode="'.$ota_hotel_code.'" />
        </RoomRatePlans>
        </RN_HotelRatePlanRQ>';
        $response = $curlService->to($commonurl.'/GetHotelRoomTypes')
        ->withData($xml)
        ->withContentType('application/xml')
        ->withOption('USERPWD',$auth)
        ->withOption('SSL_VERIFYPEER',false)
        ->post();
       $array_data = json_decode(json_encode(simplexml_load_string($response)),true);

       foreach($array_data["RoomTypes"]["RoomType"] as $key => $value)
       {
           $ota_room_type_id =$value["@attributes"]["InvTypeCode"];
           $ota_room_type_name   =$value["@attributes"]["Name"];
           $isActive   = $value["@attributes"]["IsRoomActive"];
           if($isActive == 1)
           {
                $cmOtaRoomTypeFetchModel = new CmOtaRoomTypeFetch();
                $room_active             = 1;
                $room_code               = $ota_room_type_id;
                $room_name               = $ota_room_type_name;

                /*----------------cmOtaRoomTypeFetchModel Save -------------------*/

                $cmOtaRoomTypeFetchModel->hotel_id            = $hotel_id;
                $cmOtaRoomTypeFetchModel->ota_id              = $ota_id;
                $cmOtaRoomTypeFetchModel->ota_name            = $ota_name;
                $cmOtaRoomTypeFetchModel->ota_room_type_id    = $room_code;
                $cmOtaRoomTypeFetchModel->ota_room_type_name  = $room_name;
                $cmOtaRoomTypeFetchModel->active              = $room_active;
                $room_type_fetch_status                       = $cmOtaRoomTypeFetchModel->save();
           }

       }
                $otalog = new LogTable();
                /*----------------otalog Save -------------------*/
                $otalog->hotel_id            = $hotel_id;
                $otalog->ota_id              = $ota_id;
                $otalog->request_msg         = $commonurl;
                $otalog->response_msg        = $response;
                $otalog->request_url         = $commonurl;
                $ota_log                     = $otalog->save();
                $res=array('status'=>1,"message"=>"IDSNext Room type fetch successfull");
                return response()->json($res);
    }
    //Goomo room fetch
    public function goomoCmHotel($ota_hotel_code,$commonurl,$auth_parameter,$hotel_id,$ota_id,$ota_name)
    {
        $apiKey          = trim($auth_parameter->apiKey);
        $channelId       = trim($auth_parameter->channelId);
        $accessToken     = trim($auth_parameter->accessToken);
        $request_msg="";
        $response_msg="";
        $room_fetch_url=$commonurl.'/getRoomList?productId='.$ota_hotel_code;
        $request_msg.=$room_fetch_url;
        // Generated by curl-to-PHP: http://incarnate.github.io/curl-to-php/
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $room_fetch_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        $headers = array(); $headers[] = "Content-Type: application/json";
        $headers[] = "apiKey:  $apiKey";
        $headers[] = "channelId: $channelId";
        $headers[] = "accessToken: $accessToken";
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($ch);
        if (curl_errno($ch)) { echo 'Error:' . curl_error($ch); }
        curl_close ($ch);
        $response_msg.=$result;
        $room_data         = json_decode($result,true);
        if(empty($room_data))
        {
            $res=array('status'=>0,"message"=>"Goomo room type fetch failed","err"=>"Apikey validation error");
            return response()->json($res);
        }
        if(isset($room_data['status']))
        {
        if($room_data['status'] == "failure"){//Means Error Tag
            $res=array('status'=>0,"message"=>"Goomo room type fetch failed","err"=>$room_data['message']);
            return response()->json($res);
            }
        }
        foreach ($room_data['roomList'] as $key => $room)
        {
                $cmOtaRoomTypeFetchModel = new CmOtaRoomTypeFetch();
                $cmOtaRoomTypeFetchModel->hotel_id            = $hotel_id;
                $cmOtaRoomTypeFetchModel->ota_id              = $ota_id;
                $cmOtaRoomTypeFetchModel->ota_name            = $ota_name;
                $cmOtaRoomTypeFetchModel->ota_room_type_id    = $room['id'];
                $cmOtaRoomTypeFetchModel->ota_room_type_name  = $room['name'];
                $cmOtaRoomTypeFetchModel->active              = 1;
                $room_type_fetch_status                       = $cmOtaRoomTypeFetchModel->save();
        }
        $rate_fetch_url=$commonurl.'/viewRatePlan?productId='.$ota_hotel_code;
        $request_msg.=$rate_fetch_url;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $rate_fetch_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        $headers = array(); $headers[] = "Content-Type: application/json";
        $headers[] = "apiKey:  $apiKey";
        $headers[] = "channelId: $channelId";
        $headers[] = "accessToken: $accessToken";
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($ch);
        if (curl_errno($ch)) { echo 'Error:' . curl_error($ch); }
        curl_close ($ch);
        $response_msg.=$result;
        $rate_data         = json_decode($result,true);
        if(isset($rate_data['status']))
        {
        if($rate_data['status'] == "failure"){//Means Error Tag
            $res=array('status'=>0,"message"=>"Goomo rate type fetch failed","err"=>$rate_data['message']);
            return response()->json($res);
            }
        }
        foreach ($rate_data as $key => $rate)
        {
            $cmOtaRateTypeFetchModel = new CmOtaRateTypeFetch();
            $cmOtaRateTypeFetchModel->hotel_id            = $hotel_id;
            $cmOtaRateTypeFetchModel->ota_id              = $ota_id;
            $cmOtaRateTypeFetchModel->ota_name            = $ota_name;
            $cmOtaRateTypeFetchModel->ota_room_type_id    = "";
            $cmOtaRateTypeFetchModel->ota_room_type_name  = "";
            $cmOtaRateTypeFetchModel->ota_rate_type_id    = $rate['id'];
            $cmOtaRateTypeFetchModel->ota_rate_type_name  = $rate['ratePlan'];
            $cmOtaRateTypeFetchModel->ota_rate_type       = $rate['rateType'];
            $cmOtaRateTypeFetchModel->validate_from       = $rate['validityStart'];
            $cmOtaRateTypeFetchModel->validate_to         = $rate['validityEnd'];
            $cmOtaRateTypeFetchModel->active              = 1;
            $rate_type_fetch_status                       = $cmOtaRateTypeFetchModel->save();
        }
         $otalog = new LogTable();
                /*----------------otalog Save -------------------*/
                $otalog->hotel_id            = $hotel_id;
                $otalog->ota_id              = $ota_id;
                $otalog->request_msg         = $request_msg;
                $otalog->response_msg        = $response_msg;
                $otalog->request_url         = $commonurl;
                $ota_log                     = $otalog->save();
                $res=array('status'=>1,"message"=>"Goomo Room type and rate fetch successfull");
                return response()->json($res);
    }
    public function easemytripCmHotel($ota_hotel_code,$commonurl,$auth_parameter,$hotel_id,$ota_id,$ota_name)
    {
        $token           = trim($auth_parameter->Token);
        $headers    =   array ('Content-Type: application/json');
        $url = $commonurl.'/getdetails';
        $room_list='{
                        "Auth":
                        {
                            "Token": "'.$token.'",
                            "Type": "RoomList"
                        },
                        "HotelCode": "'.$ota_hotel_code.'"
                    }';
        $room_list=$this->getRoomlistandMealplan($url,$headers,$room_list);
        $room_list_data=json_decode($room_list);
        if($room_list)
        {
            $room_rate_json ='{
                "Auth":
                {
                    "Token": "'.$token.'",
                    "Type": "RoomListWithMealPlan"
                },
                "HotelCode": "'.$ota_hotel_code.'"
            }';
            $room_rate_list=$this->getRoomlistandMealplan($url,$headers,$room_rate_json);
            $room_rate_list_data=json_decode($room_rate_list);
        }
        $response   =   $room_list.'???'.$room_rate_list;
        if(isset($array_data->error)){//Means Error Tag
            $res=array('status'=>0,"message"=>"easemytrip Room type and Room Rate fetch failed","err"=>$array_data->msg);
            return response()->json($res);

        }
        $room_type_deatils = $room_list_data->Data;
        $rate_type_deatils = $room_rate_list_data->Data;
        foreach ($room_type_deatils as $key => $value)
        {

            $roomID                  = $value->RoomCode;
            $roomName                = $value->RoomName;
            $cmOtaRoomTypeFetchModel = new CmOtaRoomTypeFetch();
            $room_active             = 2;
            $room_code               = $roomID;
            $room_name               = $roomName;
            /*----------------cmOtaRoomTypeFetchModel Save -------------------*/

            $cmOtaRoomTypeFetchModel->hotel_id            = $hotel_id;
            $cmOtaRoomTypeFetchModel->ota_id              = $ota_id;
            $cmOtaRoomTypeFetchModel->ota_name            = $ota_name;
            $cmOtaRoomTypeFetchModel->ota_room_type_id    = $room_code;
            $cmOtaRoomTypeFetchModel->ota_room_type_name  = $room_name;
            $cmOtaRoomTypeFetchModel->active              = $room_active;
            $room_type_fetch_status                       = $cmOtaRoomTypeFetchModel->save();
            $room_details[]  = array("room_code"=>"$room_code","room_name"=>"$room_name");
        }

        foreach ($room_details as $room_detail)
        {
            $room_code = $room_detail["room_code"];
            $room_name = $room_detail["room_name"];
            foreach ($rate_type_deatils as $key => $value)
            {
                $roomID    = $value->RoomCode;
                if($roomID == $room_code )
                {
                    $rateID    = $value->PlanId;
                    $rateName  = $value->PlanName;
                    $rate_type = '';
                    $rate_id       = $rateID;
                    $rate_name     = $rateName;
                    $ota_rate_type = $rate_type;
                    $validate_from = "";
                    $validate_to   = "";
                    $rate_active   = 2;
                    $cmOtaRateTypeFetchModel = new CmOtaRateTypeFetch();
                    /*----------------cmOtaRateTypeFetchModel Save -------------------*/

                    $cmOtaRateTypeFetchModel->hotel_id            = $hotel_id;
                    $cmOtaRateTypeFetchModel->ota_id              = $ota_id;
                    $cmOtaRateTypeFetchModel->ota_name            = $ota_name;
                    $cmOtaRateTypeFetchModel->ota_room_type_id    = $room_code;
                    $cmOtaRateTypeFetchModel->ota_room_type_name  = $room_name;
                    $cmOtaRateTypeFetchModel->ota_rate_type_id    = $rate_id;
                    $cmOtaRateTypeFetchModel->ota_rate_type_name  = $rate_name;
                    $cmOtaRateTypeFetchModel->ota_rate_type       = $ota_rate_type;
                    $cmOtaRateTypeFetchModel->validate_from       = $validate_from;
                    $cmOtaRateTypeFetchModel->validate_to         = $validate_to;
                    $cmOtaRateTypeFetchModel->active              = $rate_active;
                    $rate_type_fetch_status                       = $cmOtaRateTypeFetchModel->save();
                }
            }
        }

        $otalog = new LogTable();
        /*----------------otalog Save -------------------*/
        $otalog->hotel_id            = $hotel_id;
        $otalog->ota_id              = $ota_id;
        $otalog->request_msg         = $url;
        $otalog->response_msg        = $response;
        $otalog->request_url         = $url;
        $ota_log                     = $otalog->save();
        $res=array('status'=>1,"message"=>"easemytrip Room type and Room Rate fetch successfull");
        return response()->json($res);
    }
    public function getRoomlistandMealplan($url,$headers,$data)
    {
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $data);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }
    public function paytmCmHotel($ota_hotel_code,$commonurl,$auth_parameter,$hotel_id,$ota_id,$ota_name)
    {
        $api_key           = trim($auth_parameter->api_key);
        $headers    =   array ('Content-Type: application/json');
        $currentdate = date('Y-m-d');
        $todate=date('Y-m-d',strtotime($currentdate.'+1 days'));
        $url = $commonurl.'/getInventory';
        $room_list_json='{
                        "auth": {
                        "key": "'.$api_key.'"
                        },
                        "propertyId": "'.$ota_hotel_code.'",
                        "startDate": "'.$currentdate.'",
                        "endDate": "'.$todate.'"
                    }';
        $room_list=$this->getPaytmRoomlistandMealplan($url,$headers,$room_list);
        $room_list_data=json_decode($room_list);
        if($room_list)
        {
            $url = $commonurl.'/getPrices';
            $room_rate_json ='{
                                "auth": {
                                "key": "'.$api_key.'"
                                },
                                "propertyId": "'.$ota_hotel_code.'",
                                "startDate": "'.$currentdate.'",
                                "endDate": "'.$todate.'"
                             }';
            $room_rate_list=$this->getPaytmRoomlistandMealplan($url,$headers,$room_rate_json);
            $room_rate_list_data=json_decode($room_rate_list);
        }
        $response   =   $room_list.'???'.$room_rate_list;
        if(isset($array_data->error)){//Means Error Tag
            $res=array('status'=>0,"message"=>"paytm Room type and Room Rate fetch failed","err"=>$array_data->msg);
            return response()->json($res);
        }

        $room_type_deatils = $room_list_data->data;
        $rate_type_deatils = $room_rate_list_data->data;

        foreach ($room_type_deatils as $key => $value)
        {

            $roomID                  = $value->roomId;
            $roomName                = $value->roomName;
            $cmOtaRoomTypeFetchModel = new CmOtaRoomTypeFetch();
            $room_active             = 2;
            $room_code               = $roomID;
            $room_name               = $roomName;
            /*----------------cmOtaRoomTypeFetchModel Save -------------------*/

            $cmOtaRoomTypeFetchModel->hotel_id            = $hotel_id;
            $cmOtaRoomTypeFetchModel->ota_id              = $ota_id;
            $cmOtaRoomTypeFetchModel->ota_name            = $ota_name;
            $cmOtaRoomTypeFetchModel->ota_room_type_id    = $room_code;
            $cmOtaRoomTypeFetchModel->ota_room_type_name  = $room_name;
            $cmOtaRoomTypeFetchModel->active              = $room_active;
            $room_type_fetch_status                       = $cmOtaRoomTypeFetchModel->save();
            $room_details[]  = array("room_code"=>"$room_code","room_name"=>"$room_name");
        }

        foreach ($room_details as $room_detail)
        {
            $room_code = $room_detail["room_code"];
            $room_name = $room_detail["room_name"];
            foreach ($rate_type_deatils as $key => $value)
            {
                $roomID    = $value->roomId;
                if($roomID == $room_code )
                {
                    $rateID    = $value->rateId;
                    $rateName  = $value->rateName;
                    $rate_type = '';
                    $rate_id       = $rateID;
                    $rate_name     = $rateName;
                    $ota_rate_type = $rate_type;
                    $validate_from = "";
                    $validate_to   = "";
                    $rate_active   = 2;
                    $cmOtaRateTypeFetchModel = new CmOtaRateTypeFetch();
                    /*----------------cmOtaRateTypeFetchModel Save -------------------*/

                    $cmOtaRateTypeFetchModel->hotel_id            = $hotel_id;
                    $cmOtaRateTypeFetchModel->ota_id              = $ota_id;
                    $cmOtaRateTypeFetchModel->ota_name            = $ota_name;
                    $cmOtaRateTypeFetchModel->ota_room_type_id    = $room_code;
                    $cmOtaRateTypeFetchModel->ota_room_type_name  = $room_name;
                    $cmOtaRateTypeFetchModel->ota_rate_type_id    = $rate_id;
                    $cmOtaRateTypeFetchModel->ota_rate_type_name  = $rate_name;
                    $cmOtaRateTypeFetchModel->ota_rate_type       = $ota_rate_type;
                    $cmOtaRateTypeFetchModel->validate_from       = $validate_from;
                    $cmOtaRateTypeFetchModel->validate_to         = $validate_to;
                    $cmOtaRateTypeFetchModel->active              = $rate_active;
                    $rate_type_fetch_status                       = $cmOtaRateTypeFetchModel->save();
                }
            }
        }

        $otalog = new LogTable();
        /*----------------otalog Save -------------------*/
        $otalog->hotel_id            = $hotel_id;
        $otalog->ota_id              = $ota_id;
        $otalog->request_msg         = $url;
        $otalog->response_msg        = $response;
        $otalog->request_url         = $url;
        $ota_log                     = $otalog->save();
        $res=array('status'=>1,"message"=>"paytm Room type and Room Rate fetch successfull");
        return response()->json($res);
    }
    public function getPaytmRoomlistandMealplan($url,$headers,$data)
    {
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $data);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }
    public function getOtaHotelCodeOfEaseMyTrip(Request $request)
    {
      $token="pDm0Q6w6gIaOjX1I9eje0jpqs4G3rskuHMmZ6G8XWnh9UrrpfttStQ==";
      $url="http://InventoryAPI.easemytrip.com/api/suphotel/getdetails";
        $headers = array('Content-Type:application/json');
        $data ='{
            "Auth": {
            "Token": "'.$token.'",
            "Type": "HotelList"
            }
            }';
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $data);
        $response = curl_exec($ch);
        curl_close($ch);
        $array_data=json_decode($response,true);
        if($array_data["Status"]=="true")
        {
            $res=array('status'=>1,'message'=>$array_data['Message'],'data'=>$array_data['Data']);
            return response()->json($res);
        }
        else{
            $res=array('status'=>0,'message'=>$array_data['Message']);
            return response()->json($res);
        }
    }
    public function getOtaDetail()
    {
        $ota=DB::select(DB::raw("SELECT * from cm_ota_credential_parameter"));
        if($ota){
            $res=array('status'=>1,'message'=>'OTA retrive sucessfully',"ota"=>$ota);
            return response()->json($res);
        }
        $res=array('status'=>0,'message'=>'OTA retrive fails');
        return response()->json($res);
    }
    public function getAllOTACMHotel(int $hotel_id,Request $request)
    {
        $conditions=array('hotel_id'=>$hotel_id);
        $res=CmOtaDetailsRead::join('cm_ota_credential_parameter','cm_ota_credential_parameter.ota_name','cm_ota_details.ota_name')
        ->where($conditions)
        ->select('cm_ota_details.*','cm_ota_credential_parameter.*')->get();
        $cmOtaRoomTypeFetch=new CmOtaRoomTypeFetchRead();
        $cmOtaRateTypeFetch=new CmOtaRateTypeFetchRead();
        $cmOtaRoomTtypeFetchSync=new CmOtaRoomTypeFetchSyncRead();
        $cmOtaRatePlanFetchSync=new CmOtaRatePlanFetchSyncRead();

        foreach($res as $ota_data)
        {
            $room_fetch=$cmOtaRoomTypeFetch->where('ota_id',$ota_data['ota_id'])->first();
            $rate_fetch=$cmOtaRateTypeFetch->where('ota_id',$ota_data['ota_id'])->first();
            $room_sync=$cmOtaRoomTtypeFetchSync->where('ota_type_id',$ota_data['ota_id'])->first();
            $rate_sync=$cmOtaRatePlanFetchSync->where('ota_type_id',$ota_data['ota_id'])->first();
            if($ota_data['ota_name'] == 'Airbnb' && $room_sync)
            {
                $ota_data['sync_status']=1;
            }
            else
            {
                if($room_fetch && $rate_fetch && $room_sync && $rate_sync)
                {
                    $ota_data['sync_status']=1;
                }
                else{
                    $ota_data['sync_status']=0;
                }
            }
        }
        if(sizeof($res)>0)
        {
            $res=array('status'=>1,"message"=>"Ota details records found","data"=>$res);
            return response()->json($res);
        }
        else
        {
            $res=array('status'=>0,"message"=>"No cm ota details records found","data"=>0);
            return response()->json($res);
        }
    }
    public function hegCmHotel($ota_hotel_code,$commonurl,$auth_parameter,$hotel_id,$ota_id,$ota_name)
    {
        $api_key     =  trim($auth_parameter->key);
        $headers     =  array ('Content-Type: application/json');
        $currentdate =  date('Y-m-d');
        $todate      =  date('Y-m-d',strtotime($currentdate.'+1 days'));
        $url = $commonurl.'/heg/fetchRoom';
        $room_list_json='{
                            "auth":{
                                "key":"'.$api_key.'"
                            },
                            "propertyId":"'.$ota_hotel_code.'",
                            "ota":"BookingJini"
                        }';
        $room_list=$this->getRoomlistandMealplan($url,$headers,$room_list_json);
        $room_list_data=json_decode($room_list);
        $room_type_deatils = $room_list_data->data;
        $room_rate_list = '';
        foreach ($room_type_deatils as $key => $value)
        {
            $roomID                  = $value->id;
            $roomName                = $value->name;
            $cmOtaRoomTypeFetchModel = new CmOtaRoomTypeFetch();
            $room_active             = 2;
            $room_code               = $roomID;
            $room_name               = $roomName;
            /*----------------cmOtaRoomTypeFetchModel Save -------------------*/

            $cmOtaRoomTypeFetchModel->hotel_id            = $hotel_id;
            $cmOtaRoomTypeFetchModel->ota_id              = $ota_id;
            $cmOtaRoomTypeFetchModel->ota_name            = $ota_name;
            $cmOtaRoomTypeFetchModel->ota_room_type_id    = $room_code;
            $cmOtaRoomTypeFetchModel->ota_room_type_name  = $room_name;
            $cmOtaRoomTypeFetchModel->active              = $room_active;
            $room_type_fetch_status                       = $cmOtaRoomTypeFetchModel->save();
            $room_details[]  = array("room_code"=>"$room_code","room_name"=>"$room_name");
        }
        if($room_list)
        {
            foreach ($room_details as $key => $room_detail)
            {
                $url = $commonurl.'/heg/fetchRateInfo';
                $room_rate_json = '{
                            "auth":{
                                "key":"'.$api_key.'"
                            },
                            "propertyId":"'.$ota_hotel_code.'",
                            "roomId":"'.$room_detail["room_code"].'",
                            "ota":"BookingJini"
                        }';
                $room_rate_list=$this->getRoomlistandMealplan($url,$headers,$room_rate_json);
                $room_rate_list_data=json_decode($room_rate_list);
                $room_code = $room_detail["room_code"];
                $room_name = $room_detail["room_name"];
                $rate_type_deatils = $room_rate_list_data->data;
                foreach ($rate_type_deatils as $key => $value)
                {
                    $roomID    = $room_detail['room_code'];
                    if($roomID == $room_code )
                    {
                        $rateID    = $value->rateplanId;
                        $rateName  = $value->ratePlanName;
                        $rate_type = '';
                        $rate_id       = $rateID;
                        $rate_name     = $rateName;
                        $ota_rate_type = $rate_type;
                        $validate_from = "";
                        $validate_to   = "";
                        $rate_active   = 2;
                        $cmOtaRateTypeFetchModel = new CmOtaRateTypeFetch();
                        /*----------------cmOtaRateTypeFetchModel Save -------------------*/

                        $cmOtaRateTypeFetchModel->hotel_id            = $hotel_id;
                        $cmOtaRateTypeFetchModel->ota_id              = $ota_id;
                        $cmOtaRateTypeFetchModel->ota_name            = $ota_name;
                        $cmOtaRateTypeFetchModel->ota_room_type_id    = $room_code;
                        $cmOtaRateTypeFetchModel->ota_room_type_name  = $room_name;
                        $cmOtaRateTypeFetchModel->ota_rate_type_id    = $rate_id;
                        $cmOtaRateTypeFetchModel->ota_rate_type_name  = $rate_name;
                        $cmOtaRateTypeFetchModel->ota_rate_type       = $ota_rate_type;
                        $cmOtaRateTypeFetchModel->validate_from       = $validate_from;
                        $cmOtaRateTypeFetchModel->validate_to         = $validate_to;
                        $cmOtaRateTypeFetchModel->active              = $rate_active;
                        $rate_type_fetch_status                       = $cmOtaRateTypeFetchModel->save();
                    }
                }
            }
        }
        $response   =   $room_list.'???'.$room_rate_list;

        if(isset($array_data->error)){//Means Error Tag
            $res=array('status'=>0,"message"=>"Heg Room type and Room Rate fetch failed","err"=>$array_data->msg);
            return response()->json($res);
        }
        $otalog = new LogTable();
        /*----------------otalog Save -------------------*/
        $otalog->hotel_id            = $hotel_id;
        $otalog->ota_id              = $ota_id;
        $otalog->request_msg         = $url;
        $otalog->response_msg        = $response;
        $otalog->request_url         = $url;
        $ota_log                     = $otalog->save();
        $res=array('status'=>1,"message"=>"HappyEasyGo Room type and Room Rate fetch successfull");
        return response()->json($res);
    }
    public function getHegRoomlistandMealplan($url,$headers,$data)
    {
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $data);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }
    public function test_details(){
        $url = "https://ppin.goibibo.com/api/chmv2/gethotellisting";
        $data = '<?xml version="1.0" encoding="UTF-8" ?>
            <Website Name="ingoibibo" HotelCode="1000103321">
            <HotelCode>1000103321</HotelCode>
            </Website>';
            $headers = array(
                      "Content-Type: application/xml",
                      "channel-token:95de1f96be",
                      "bearer-token:fc3b8034b4"
                    );
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $data);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }
    public function updateHotelOtaStatus(Request $request)
    {
        $data=$request->all();
        $failure_message='Ota status updation failed';

        if(CmOtaDetails::where('hotel_id',$data['hotel_id'])->update(['is_status' =>$data['status']]))
        {

            $res=array('status'=>1,"message"=>'Ota status updated successfully!');
            return response()->json($res);
        }
        else
        {
            $res=array('status'=>-1,"message"=>$failure_message);
            return response()->json($res);
        }
    }
    public function irctcCmHotel($ota_hotel_code,$commonurl,$auth_parameter,$hotel_id,$ota_id,$ota_name)
    {
        $password    = trim($auth_parameter->password);
        $username    = trim($auth_parameter->username);
        $idcontext   = $ota_hotel_code;
        $headers     =  array ('Content-Type: application/json');
        $currentdate =  date('Y-m-d');
        $todate      =  date('Y-m-d',strtotime($currentdate.'+1 days'));
        $url = $commonurl.'/property-dtl';
        $room_list_json='{
                            "OTA_HotelDetailsRQ": {
                                "POS": {
                                    "Password": '.'"'.$password.'"'.',
                                    "Username": '.'"'.$username.'"'.',
                                    "ID_Context": '.'"'.$idcontext.'"
                                },
                                "HotelCode": '.'"'.$ota_hotel_code.'"'.'
                            }
                        }';
        $room_list=$this->getRoomlistandMealplan($url,$headers,$room_list_json);
        $room_list_data=json_decode($room_list, true);
        $room_type_deatils = $room_list_data['OTA_HotelDetailsRS'][0]['RoomTypes'];
        $room_rate_list = '';
        foreach ($room_type_deatils as $key => $value)
        {   
            $roomID                  = $value['room_id'];
            $roomName                = $value['room_name'];
            $cmOtaRoomTypeFetchModel = new CmOtaRoomTypeFetch();
            $room_active             = 2;
            $room_code               = $roomID;
            $room_name               = $roomName;
            /*----------------cmOtaRoomTypeFetchModel Save -------------------*/

            $cmOtaRoomTypeFetchModel->hotel_id            = $hotel_id;
            $cmOtaRoomTypeFetchModel->ota_id              = $ota_id;
            $cmOtaRoomTypeFetchModel->ota_name            = $ota_name;
            $cmOtaRoomTypeFetchModel->ota_room_type_id    = $room_code;
            $cmOtaRoomTypeFetchModel->ota_room_type_name  = $room_name;
            $cmOtaRoomTypeFetchModel->active              = $room_active;
            $room_type_fetch_status                       = $cmOtaRoomTypeFetchModel->save();
            $room_details[]  = array("room_code"=>"$room_code","room_name"=>"$room_name");
            $rate_plan_details = $value['RatePlans'];

            foreach($rate_plan_details as $key => $value)
            {
                $rateID    = $value['rate_id'];
                $rateName  = $value['rate_name'];
                $rate_type = '';
                $rate_id       = $rateID;
                $rate_name     = $rateName;
                $ota_rate_type = $rate_type;
                $validate_from = "";
                $validate_to   = "";
                $rate_active   = 2;
                $cmOtaRateTypeFetchModel = new CmOtaRateTypeFetch();
                /*----------------cmOtaRateTypeFetchModel Save -------------------*/

                $cmOtaRateTypeFetchModel->hotel_id            = $hotel_id;
                $cmOtaRateTypeFetchModel->ota_id              = $ota_id;
                $cmOtaRateTypeFetchModel->ota_name            = $ota_name;
                $cmOtaRateTypeFetchModel->ota_room_type_id    = $room_code;
                $cmOtaRateTypeFetchModel->ota_room_type_name  = $room_name;
                $cmOtaRateTypeFetchModel->ota_rate_type_id    = $rate_id;
                $cmOtaRateTypeFetchModel->ota_rate_type_name  = $rate_name;
                $cmOtaRateTypeFetchModel->ota_rate_type       = $ota_rate_type;
                $cmOtaRateTypeFetchModel->validate_from       = $validate_from;
                $cmOtaRateTypeFetchModel->validate_to         = $validate_to;
                $cmOtaRateTypeFetchModel->active              = $rate_active;
                $rate_type_fetch_status                       = $cmOtaRateTypeFetchModel->save();       
            }   
        }
        $response   =  $room_list;
        if(isset($array_data->error)){//Means Error Tag
            $res=array('status'=>0,"message"=>"IRCTC Room type and Rate Plan fetch failed","err"=>$array_data->msg);
            return response()->json($res);
        }
        $otalog = new LogTable();
        
        /*----------------otalog Save -------------------*/
        $otalog->hotel_id            = $hotel_id;
        $otalog->ota_id              = $ota_id;
        $otalog->request_msg         = $url;
        $otalog->response_msg        = $response;
        $otalog->request_url         = $url;
        $ota_log                     = $otalog->save();
        $res=array('status'=>1,"message"=>"IRCTC Room type and Rate Plan fetch successfull");
        return response()->json($res);
    }
}
