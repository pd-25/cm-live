<?php
namespace App\Http\Controllers;

//create a new class CmOtaDetailsController
class CommonServiceController extends Controller
{ 
    public function isMultidimensionalArray($array)
    {
        foreach($array as $v)
        {

            if(is_array($v))
            {
               return true;
            }
            else
            {
                return false;                
            }
        }
    }
    public function isAllKeyMultidimensionalArray($array)
    {
        $is_arr=1;
        foreach($array as $v)
        {
            if(is_array($v))
            {
                $is_arr = $is_arr && 1 ;
            }
            else
            {
                $is_arr = $is_arr && 0 ;
            }
        }
        if($is_arr==1)
        {
            return true;
        }
        else
        {
            return false;  
        }
    }
     /*To check the uniqueMultidimArray array or not*/
     public function uniqueMultidimArray($array, $key) { 
        $temp_array = array(); 
        $i = 0; 
        $key_array = array(); 
        
        foreach($array as $val) { 
            if (!in_array($val[$key], $key_array)) { 
                $key_array[$i] = $val[$key]; 
                $temp_array[$i] = $val; 
            } 
            $i++; 
        } 
        return $temp_array; 
    } 
}