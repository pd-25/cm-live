<?php
namespace App\Http\Controllers;
namespace App\Http\Controllers\ExtranetV4;
use Exception;
use Validator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Controller;
use DB;
/**
 * This controller used to add,update tables of promotions
 * @author Jigyans Singh
 */
class CountryController extends Controller
{
    public function getAllCountry()
    { 
        $QsData = DB::table('kernel.country_table')->get()->toArray();
        print_r($QsData);exit;
        if(!empty($QsData)) 
		{

            $res = array('status'=>1,'message'=>'Property Type Retrieved successfully',"all_amenities_category"=>$QsData);
             return response()->json($res);
        }
        else
        {
            $res = array('status'=>0,'message'=>'No Such Property Type Found');
             return response()->json($res);
        }
    }
}