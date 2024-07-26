<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Validator;
use DB;
use App\User;
use App\ErrorLog;
use App\IdsReservation;
use App\IdsRoom;
use App\MasterRatePlan;
class IdsXmlCreationAndExecutionControllerTest extends Controller{
    protected $idsService;
    public function __construct(IdsController $idsService){
        $this->idsService = $idsService;
    }
    public function pushIds($invoice_id){
        $invoice        = DB::connection('be')->select(DB::raw("Select DISTINCT(a.invoice_id), b.user_id, b.room_type_id, a.booking_date, a.invoice,a.ids_re_id, a.hotel_name, a.hotel_id, a.room_type, a.check_in_out, a.total_amount, a.paid_amount, c.hotel_address, c.mobile, c.email_id, c.terms_and_cond from invoice_table a, hotel_booking b, kernel.hotels_table c where a.invoice_id=b.invoice_id AND a.hotel_id=c.hotel_id AND a.invoice_id=$invoice_id"));
        $invoice=$invoice[0];
        $booking_id     = date("dmy", strtotime($invoice->booking_date)).str_pad($invoice->invoice_id, 4, '0', STR_PAD_LEFT);

        $ids_id=$invoice->ids_re_id;

        $ids_string=$this->getIdsString($ids_id);

        if($ids_string){
            $ids_string = str_replace("#####", $booking_id, $ids_string);
            if($invoice->total_amount!=$invoice->paid_amount && $invoice->paid_amount<$invoice->total_amount){
                $roomstring='<RoomStays AdvancePayment="'.$invoice->paid_amount.'">';
                $ids_string = str_replace("<RoomStays>", $roomstring, $ids_string);
            }
            $this->pushReservations($ids_string,$ids_id,$invoice->hotel_id);
        }
    }
    public function getIdsString($ids_id){
        $resp=IdsReservation::where('id',$ids_id)->select('ids_string')->first();
        if($resp->ids_string){
            return $resp->ids_string;
        }
        else{
            return false;
        }
    }
    public function pushReservations($xml,$ids_id,$hotel_id)
    {
      try{
        $url="http://idsnextchannelmanagerapi.azurewebsites.net/BookingJini/ReservationDelivery";
        $headers = array (
			//Regulates versioning of the XML interface for the API
                'Content-Type: application/xml',
                'Accept: application/xml',
                'Authorization:Basic aWRzbmV4dEJvb2tpbmdqaW5pQGlkc25leHQuY29tOmlkc25leHRCb29raW5namluaUAwNzExMjAxNw=='
            );
        $ch 	= curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml);
        $result = curl_exec($ch);
        curl_close($ch);
        $array_data = json_decode(json_encode(simplexml_load_string($result)), true);
        if(isset($array_data['Success'])){
            if(IdsReservation::where('id',$ids_id)->update(['ids_confirm'=>1])){
                return true;
            }
            else{
                return false;
            }
        }else{
            return false;
        }
      }
        catch(Exception $e){
          $error_log = new ErrorLog();
           $storeError = array(
              'hotel_id'      => $hotel_id,
              'function_name' => 'IdsXmlCreationAndExecutionController.pushReservations',
              'error_string'  => $e
           );
           if($insertError = $error_log->fill($storeError)->save()){
              return true;
           }
        }
    }
    /**
     * This function is used for cancel booking push to ids
     * @author siri date : 12-03-2021
     */
    public function pushIdsCrs(Request $request){
       
        $ids_reservation = new IdsReservation;
        $invoice_id = $request->getContent();
        $invoice_data = DB::connection('be')->select(DB::raw("Select * from invoice_table where invoice_id=$invoice_id"));
        $invoice_data=$invoice_data[0];
        
        $booking_id = date("dmy", strtotime($invoice_data->booking_date)) . $invoice_data->invoice_id;
        
        $ids_data = IdsReservation::where('id',$invoice_data->ids_re_id)->first();
       
        $ids_xml = $ids_data->ids_string;
        //update bookingid  & bookingstatus in xml string
        $update_id_xml = str_replace('#####',$booking_id,$ids_xml);
        if(str_contains($ids_xml, 'Commit')){
            $updated_xml = str_replace('Commit','Cancel',$update_id_xml);
        }else if(str_contains($ids_xml, 'Modify')){
            $updated_xml = str_replace('Modify','Cancel',$update_id_xml);
        }
        //update ids xml string
        $ids_reservation['hotel_id'] = $invoice_data->hotel_id;
        $ids_reservation['ids_string'] = $updated_xml;
        $ids_reservation->save();
        $ids = $ids_reservation->id;
        
        $this->pushReservations($updated_xml,$ids,$invoice_data->hotel_id);
        return $ids;
    }
}
