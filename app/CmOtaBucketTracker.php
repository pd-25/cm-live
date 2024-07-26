<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class CmOtaBucketTracker extends Model 
{
    protected $table = 'cm_ota_bucket_tracker';
    protected $primaryKey = "id";
     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = array('hotel_id','bucket_id','is_processed');
}