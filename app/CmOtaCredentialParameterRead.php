<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
//a class MasterFloorType created
class CmOtaCredentialParameterRead extends Model
{
    protected $connection = 'cm_read';
    protected $table = 'cm_ota_credential_parameter';
    protected $primaryKey = "id";
     /**
     * The attributes that are mass assignable.
     * @author subhradip
     * @var array
     */
    protected $fillable = array('ota_type_code','ota_name',
                                'auth_parameter','url','ota_logo');



}
