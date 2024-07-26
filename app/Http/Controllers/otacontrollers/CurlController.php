<?php
namespace App\Http\Controllers\otacontrollers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Input;
use Validator;
use DB;
use App\Http\Controllers\Controller;
/**
 * This controller is used for executing cURL request
 * @auther Ranjit
 * @date-23/01/2019
 */
class CurlController extends Controller
{
    public function curlRequest($url,$headers,$xml)
    {
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml);
        $rlt = curl_exec($ch);
        curl_close($ch);
        $rlt = trim($rlt);
        $rlt=preg_replace('/&(?!#?[a-z0-9]+;)/', '&amp;', $rlt);
        $array_data = json_decode(json_encode(simplexml_load_string($rlt)), true);
        $res=array('array_data'=>$array_data,'rlt'=>$rlt);
        return $res;
    }
}
