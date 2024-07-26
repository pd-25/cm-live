<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Validator;
use DB;
use App\User;
use App\ErrorLog;
use App\WinhmsReservation;
use App\WinhmsRoom;
use App\MasterRatePlan;
class WinhmsXmlCreationAndExecutionController extends Controller{
    
    public function pushWinhms($invoice_id){
        $invoice        = DB::connection('be')->select(DB::raw("Select DISTINCT(a.invoice_id), b.user_id, b.room_type_id, a.booking_date, a.invoice,a.ids_re_id, a.hotel_name, a.hotel_id, a.room_type, a.check_in_out, a.total_amount, a.paid_amount, c.hotel_address, c.mobile, c.email_id, c.terms_and_cond from invoice_table a, hotel_booking b, kernel.hotels_table c where a.invoice_id=b.invoice_id AND a.hotel_id=c.hotel_id AND a.invoice_id=$invoice_id"));
        $invoice=$invoice[0];
        $booking_id     = date("dmy", strtotime($invoice->booking_date)).str_pad($invoice->invoice_id, 4, '0', STR_PAD_LEFT);

        $ids_id=$invoice->ids_re_id;

        $ids_string=$this->getWinhmsString($ids_id);

        if($ids_string){
            $ids_string = str_replace("#####", $booking_id, $ids_string);
            if($invoice->total_amount!=$invoice->paid_amount && $invoice->paid_amount<$invoice->total_amount){
                $roomstring='<RoomStays AdvancePayment="'.$invoice->paid_amount.'">';
                $ids_string = str_replace("<RoomStays>", $roomstring, $ids_string);
            }
            $this->pushReservations($ids_string,$ids_id,$invoice->hotel_id);
        }
    }
    
    public function pushWinhmsCrs(Request $request){
       
        $winhms_reservation = new WinhmsReservation;
        $invoice_id = $request->getContent();
        $invoice_data = DB::connection('be')->select(DB::raw("Select * from invoice_table where invoice_id=$invoice_id"));
        $invoice_data=$invoice_data[0];
        
        $booking_id = date("dmy", strtotime($invoice_data->booking_date)) . $invoice_data->invoice_id;
        
        $winhms_data = WinhmsReservation::where('id',$invoice_data->winhms_re_id)->first();
        
        $winhms_xml = $winhms_data->winhms_string;
        //update bookingid  & bookingstatus in xml string
        $update_winhms_xml = str_replace('#####',$booking_id,$winhms_xml);
        if(str_contains($ids_xml, 'Commit')){
            $updated_xml = str_replace('Commit','Cancel',$update_winhms_xml);
        }else if(str_contains($ids_xml, 'Modify')){
            $updated_xml = str_replace('Modify','Cancel',$update_winhms_xml);
        }
        //update ids xml string
        $winhms_reservation['hotel_id'] = $invoice_data->hotel_id;
        $winhms_reservation['ids_string'] = $updated_xml;
        $winhms_reservation->save();
        $ids = $winhms_reservation->id;
        
        // $this->pushReservations($updated_xml,$ids,$invoice_data->hotel_id);
        return $ids;
    }
    
    public function getWinhmsString($winhms_id){
        $resp=WinhmsReservation::where('id',$winhms_id)->select('winhms_string')->first();
        if($resp->ids_string){
            return $resp->ids_string;
        }
        else{
            return false;
        }
    }
    
    public function pushWinhmsReservations($xml,$ids_id,$hotel_id)
    {
      try{
        $url="http://idsnextchannelmanagerapi.azurewebsites.net/BookingJini/ReservationDelivery";
        $headers = array (
            //Regulates versioning of the XML interface for the API
                'Content-Type: application/xml',
                'Accept: application/xml',
                'Authorization:Basic aWRzbmV4dEJvb2tpbmdqaW5pQGlkc25leHQuY29tOmlkc25leHRCb29raW5namluaUAwNzExMjAxNw=='
            );
        $ch     = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml);
        $result = curl_exec($ch);
        curl_close($ch);
        $array_data = json_decode(json_encode(simplexml_load_string($result)), true);
        if(isset($array_data['Success'])){
            if(WinhmsReservation::where('id',$ids_id)->update(['winhms_confirm'=>1])){
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
              'function_name' => 'IdsXmlCreationAndExecutionController.pushWinhmsReservations',
              'error_string'  => $e
           );
           if($insertError = $error_log->fill($storeError)->save()){
              return true;
           }
        }
    }
}
