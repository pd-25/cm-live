<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Validator;
use DB;
use App\Http\Controllers\IdsXmlCreationAndExecutionController;
class InventoryPushAfterBEBookingToOtaControllerTest extends Controller{
  protected $idsPush;
  public function __construct(IdsXmlCreationAndExecutionController $idsPush)
  {
     $this->idsPush                               = $idsPush;
  }
  public function getDetails(Request $request){
     $data = $request->all();
     if(isset($data['ids_re_id']) && $data['ids_re_id']!=NULL ){
          $this->idsPush->pushIds($data['invoice_id']);
     }
  }
}
