<?php
namespace App;
use Illuminate\Database\Eloquent\Model; 

//a class Coupons created
class OtaPromotions extends Model 
{
    protected $table        = 'ota_promotion';
    protected $primaryKey   = "id";
    
     /**
     * @author rajendra
     */
    protected $fillable = array('promotion_id','hotel_id','ota_id','ota_name','ota_promotion_code','response','request',);
 
}	


   