<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Validator;
use DB;
use App\IdsReservation;
use App\Invoice;

class idsStatusCheckController extends Controller
{
    public function idsStatusCheck(){
        $current_date = '%'.date('Y-m-d').'%';

        $get_data = IdsReservation::
        join('booking_engine.invoice_table','invoice_table.ids_re_id','=','ids_reservation.id')
        ->join('cm_ota_booking on','cm_ota_booking.ids_re_id','=','ids_reservation.id')
        ->where('invoice_table.booking_status',1)->where('cm_ota_booking.booking_status','!=','pending')
        ->where('invoice_table.created_at','LIKE',$current_date)->where('cm_ota_booking.created_at','LIKE',$current_date)->get();
        $res = response()->json($get_data);
        return $res;
    }
}
?>
