<?php
use App\Jobs\TestJob;
use App\Jobs\BucketJob;
// use Carbon\Carbon;
/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->group(['prefix' => 'ota_bookings'], function($router) {
    $router->get('/get/{from_date}/{to_date}/{date_type}/{ota}/{booking_status}/{hotel_id}/{booking_id}',['uses'=>'OtaBookingController@getOtaBookingsDateWise']);
});
$router->group(['prefix' => 'manage_inventory','middleware' => 'jwt.auth'], function($router) {
    //Ota and BE inventory update
    $router->get('/get_inventory/{room_type_id}/{date_from}/{date_to}/{mindays}',['uses'=>'ManageInventoryController@getInventery']);
    $router->get('/get_inventory_by_hotel/{hotel_id}/{date_from}/{date_to}/{mindays}',['uses'=>'ManageInventoryController@getInvByHotel']);
    //$router->post('/inventory_update',['uses'=>'InventoryController@inventoryUpdate']);
    $router->post('/inventory_update',['uses'=>'invrateupdatecontrollers\MasterInvRateUpdateController@bulkInvUpdate']);
        //Ota and BE Rates Update
    $router->get('/get_room_rates/{room_type_id}/{rate_plan_id}/{date_from}/{date_to}',['uses'=>'ManageInventoryController@getRates']);
    $router->get('/get_room_rates_by_hotel/{hotel_id}/{date_from}/{date_to}',['uses'=>'ManageInventoryController@getRatesByHotel']);
    //$router->post('/room_rate_update',['uses'=>'RoomRateController@roomRateUpdate']);
    $router->post('/room_rate_update',['uses'=>'invrateupdatecontrollers\MasterInvRateUpdateController@bulkRateUpdate']);
    // $router->post('/sync-inv',['uses'=>'SyncInventorynRatesController@pushInventory']);
    $router->post('/sync-inv',['uses'=>'invrateupdatecontrollers\MasterInvRateUpdateController@syncInvUpdate']);
    // $router->post('/sync-rates',['uses'=>'SyncInventorynRatesController@pushRates']);
    $router->post('/sync-rates',['uses'=>'invrateupdatecontrollers\MasterInvRateUpdateController@syncRateUpdate']);
    //$router->post('/update-inv',['uses'=>'SyncInventorynRatesController@updateInventory']);
    $router->post('/update-inv',['uses'=>'invrateupdatecontrollers\MasterInvRateUpdateController@singleInventoryUpdate']);
    //$router->post('/update-rates',['uses'=>'SyncInventorynRatesController@updateRates']);
    $router->post('/update-rates',['uses'=>'invrateupdatecontrollers\MasterInvRateUpdateController@singleRateUpdate']);

    //Block inventory
    //$router->post('/block_inventory',['uses'=>'OtaBlockInventoryController@addNewCmOtaBlockInventry']);
    $router->post('/block_inventory',['uses'=>'invrateupdatecontrollers\MasterInvRateUpdateController@blockInventoryUpdate']);
    $router->post('/unblock_inventory',['uses'=>'invrateupdatecontrollers\MasterInvRateUpdateTestController@unblockInventoryUpdate']);
    $router->post('/block_rate',['uses'=>'invrateupdatecontrollers\MasterInvRateUpdateController@blockRateUpdate']);
    $router->post('/unblock_rate',['uses'=>'invrateupdatecontrollers\MasterInvRateUpdateTestController@unblockRateUpdate']);

});
//CM ota Details  Routes
$router->group(['prefix' => 'cm_ota_details','middleware' => 'jwt.auth'], function($router) {
    $router->post('/add',['uses'=>'CmOtaDetailsController@addNewCmHotel']);
    $router->post('/add-test',['uses'=>'CmOtaDetailsTestController@addNewCmHotel']);
    $router->post('/update/{ota_id}',['uses'=>'CmOtaDetailsController@updateCmHotel']);
    $router->post('/update-test/{ota_id}',['uses'=>'CmOtaDetailsTestController@updateCmHotel']);
    $router->delete('/{hotel_id}/{ota_id}',['uses'=>'CmOtaDetailsController@deleteCmHotel']);
    //$router->get('/all',['uses'=>'CmOtaDetailsController@getAllCmHotel']);
    $router->get('/{ota_id}',['uses'=>'CmOtaDetailsController@getCmHotel']);
    $router->get('/sync/{ota_id}',['uses'=>'CmOtaDetailsController@multipleFunction']);
    $router->get('/get/{hotel_id}',['uses'=>'CmOtaDetailsController@getAllCmHotel']);
    $router->get('/get-promotion/{hotel_id}',['uses'=>'CmOtaDetailsController@getAllCmHotelPromotion']);
    $router->get('/get-test/{hotel_id}',['uses'=>'CmOtaDetailsTestController@getAllCmHotel']);
    $router->get('/toggle/{hotel_id}/{ota_id}/{is_active}',['uses'=>'CmOtaDetailsController@toggle']);
});
$router->get('/cm_ota_details/sync-test/{ota_id}',['uses'=>'CmOtaDetailsTestController@multipleFunction']);

    //CM ota room type sync  Routes
$router->group(['prefix' => 'cm_ota_roomtype_sync','middleware' => 'jwt.auth'], function($router) {
    $router->post('/add',['uses'=>'CmOtaSyncController@addNewCmOtaSync']);
    $router->post('/update/{id}',['uses'=>'CmOtaSyncController@updateCmOtaSync']);
    $router->delete('/{id}',['uses'=>'CmOtaSyncController@deleteCmOtaSync']);
    $router->get('/ota_room_types/{hotel_id}/{ota_id}',['uses'=>'CmOtaSyncController@otaRoomTypes']);
    $router->get('/ota_sync_room_types/{hotel_id}/{ota_id}',['uses'=>'CmOtaSyncController@fetchOtaSyncRoomTypes']);
    $router->get('/ota_sync_data/{sync_id}',['uses'=>'CmOtaSyncController@fetchOtaSyncById']);
    $router->get('/ota_rate_plan/{hotel_id}/{ota_id}/{ota_room_type_id}',['uses'=>'CmOtaSyncController@fetchOtaRoomRatePlan']);
    $router->get('/ota_sync_rate_plan/{hotel_id}/{ota_id}',['uses'=>'CmOtaSyncController@fetchOtaSyncRoomRatePlan']);
    $router->get('/ota_sync_rate/{sync_id}',['uses'=>'CmOtaSyncController@fetchOtaRatePlanSyncById']);
    $router->get('/ota_room_type/{hotel_id}/{ota_id}/{room_type_id}',['uses'=>'CmOtaSyncController@fetchOtaRoomType']);
    $router->get('/all_ota/{hotel_id}',['uses'=>'CmOtaSyncController@getAllSyncRoomsData']);
    $router->get('/all_ota_rates/{hotel_id}',['uses'=>'CmOtaSyncController@getAllSyncRoomRateData']);
});

//CM ota  rate plan sync  Routes
$router->group(['prefix' => 'cm_ota_rateplan_sync','middleware' => 'jwt.auth'], function($router) {
$router->post('/add',['uses'=>'CmOtaSyncController@addNewCmOtaRatePlanSync']);
$router->post('/update/{id}',['uses'=>'CmOtaSyncController@updateCmOtaRatePlanSync']);
$router->delete('/{id}',['uses'=>'CmOtaSyncController@deleteCmOtaRatePlanSync']);
});
//Ota and BE Inventory update
$router->post('/hotel_inventory_update',['uses'=>'InventoryController@inventoryUpdate']);
//Ota and BE Rates Update
$router->post('/hotel_room_rate_update',['uses'=>'RoomRateController@roomRateUpdate']);
/*================OTA BBookings==========================*/
$router->group(['prefix' => 'ota_bookings'], function($router) {
    $router->get('/get/{from_date}/{to_date}/{date_type}/{ota}/{booking_status}/{hotel_id}/{booking_id}',['uses'=>'OtaBookingController@getOtaBookingsDateWise']);
});

$router->group(['prefix'=>'ota-log-details','middleware' => 'jwt.auth'],function($router){
    $router->get('/inventory/{hotel_id}/{from_date}/{to_date}/{room_type_id}/{selected_be_ota_id}',['uses'=>'OtaLogsController@otaInventoryDetails']);
    $router->get('/rateplan/{hotel_id}/{from_date}/{to_date}/{rate_plan_id}/{selected_be_ota_id}/{room_type_id}',['uses'=>'OtaLogsController@OtaRateplanDetails']);
    $router->get('/booking/{hotel_id}/{from_date}/{to_date}',['uses'=>'OtaLogsController@bookingDetails']);
});
//New Routes for dashboard and reporting
$router->group(['prefix'=>'dashboard','middleware' => 'jwt.auth'],function($router){
    $router->get('/select-ota-invoice/{hotel_id}/{from_date}/{to_date}',['uses'=>'OtaDashboardController@selectInvoice']);
    $router->get('/select-ota-room-nights/{hotel_id}/{checkin}/{checkout}',['uses'=>'OtaDashboardController@getRoomNightsByDateRange']);
    $router->get('/select-ota-revenue/{hotel_id}/{checkin}/{checkout}',['uses'=>'OtaDashboardController@totalRevenueOtaWise']);
    $router->get('/select-ota-avgstay/{hotel_id}/{checkin}/{checkout}',['uses'=>'OtaDashboardController@averageStay']);
    $router->get('/select-ota-rateplan/{hotel_id}/{checkin}/{checkout}',['uses'=>'OtaDashboardController@ratePlanPerformance']);
    $router->get('/ota-booking/{hotel_id}/{from_date}/{to_date}',['uses'=>'OtaDashboardController@dashboardBookingDetails']);
    $router->get('/checkin-ota-booking/{hotel_id}',['uses'=>'OtaDashboardController@getOtaDetails']);
    $router->get('/checkout-ota-booking/{hotel_id}',['uses'=>'OtaDashboardController@getOtaDetailsCheckOut']);
});

//new reports for ota
$router->group(['prefix'=>'otanewreports'],function($router){
    $router->get('/ota_number-of-night/{hotel_id}/{checkin}/{checkout}',['uses'=>'OtaReportingController@getRoomNightsByDateRange']);
    $router->get('/ota_total-amount/{hotel_id}/{checkin}/{checkout}',['uses'=>'OtaReportingController@totalRevenueOtaWise']);
    $router->get('/ota_total-bookings/{hotel_id}/{checkin}/{checkout}',['uses'=>'OtaReportingController@numberOfBookings']);
    $router->get('/ota_average-stay/{hotel_id}/{checkin}/{checkout}',['uses'=>'OtaReportingController@averageStay']);
    $router->get('/ota_rate-plan-performance/{hotel_id}/{checkin}/{checkout}',['uses'=>'OtaReportingController@ratePlanPerformance']);
});

//Inventory section get inventory by ota id
$router->group(['prefix'=>'ota_inv_rate'],function($router){
    $router->get('/get_ota_inventory_by_hotel/{ota_id}/{hotel_id}/{date_from}/{date_to}/{mindays}',['uses'=>'OtaInventoryBookingFetchController@getInventoryDetails']);
    $router->get('/get_ota_rates_by_hotel/{ota_id}/{hotel_id}/{date_from}/{date_to}',['uses'=>'OtaInventoryBookingFetchController@getRatePlan']);
    $router->get('/get_ota_rates_by_room_type/{ota_id}/{hotel_id}/{date_from}/{date_to}/{room_type_id}',['uses'=>'OtaInventoryBookingFetchController@getRatePlanByRoomType']);
});
//OTA BOOKINGS PULL and PUSH ROUTES
//Goibibo bookings push route
$router->post('/v3/api/goibibo-reservations',['uses'=>'otacontrollers\GoibiboController@actionIndex']);
$router->post('/v3/api/goibibo-reservations-test',['uses'=>'otacontrollers\GoibiboControllerTest@actionIndex']);
//Agoda bookings pull route
$router->get('/agoda-reservations',['uses'=>'otacontrollers\AgodaController@actionIndex']);
$router->get('/agoda-reservations-test',['uses'=>'otacontrollers\AgodaTestController@actionIndex']);
//Booking.com respopnse endpoint for bookings
$router->get('/bookingdotcom-pull-reservations',['uses'=>'otacontrollers\BookingdotcomController@actionIndex']);
$router->get('/bookingdotcom-pull-reservations-test',['uses'=>'otacontrollers\BookingDotComControllerTest@actionIndex']);
$router->group(['prefix'=>'bookingDotCom','middleware' => 'jwt.auth'],function($router){
    $router->post('/noshow',['uses'=>'otacontrollers\BookingdotcomController@noShowPush']);
});
$router->post('/gems-noshow',['uses'=>'otacontrollers\BookingdotcomController@noShowPush']);
$router->post('/cleartrip-reservations',['uses'=>'otacontrollers\CleartripController@actionIndex']);
//Expedia booking endpoint
$router->post('/v3/api/expedia-reservations',['uses'=>'otacontrollers\expediaControllers\ExpediaController@actionIndex']);
$router->post('/v3/api/expedia-reservations-test',['uses'=>'otacontrollers\expediaControllers\ExpediaTestController@actionIndex']);
//Viadotcom booking endpoint
$router->post('/v3/api/viadotcom-reservations',['uses'=>'otacontrollers\ViadotcomController@actionIndex']);
//Travelguru booking endpoint
// $router->get('/travelguru-reservations',['uses'=>'otacontrollers\TravelguruController@actionIndex']);
$router->get('/travelguru-reservations/{context}',['uses'=>'otacontrollers\TravelguruController@actionIndex']);
$router->post('/travelguru-reservations-test/{context}',['uses'=>'otacontrollers\TravelguruControllerTest@actionIndex']);

//Airbnb booking endpoint
$router->get('/v3/api/air-bnb-reservations',['uses'=>'otacontrollers\AirBnbController@index']);
$router->post('/v3/api/air-bnb-reservations',['uses'=>'otacontrollers\AirBnbController@index']);
$router->put('/v3/api/air-bnb-reservations/{reservation_id}',['uses'=>'otacontrollers\AirBnbController@reservation']);
$router->get('/v3/api/airbnb-token',['uses'=>'otacontrollers\AirBnbController@index']);
$router->post('/v3/api/air-bnb-notify',['uses'=>'otacontrollers\AirBnbNotificationController@notify']);
//Goomo booking endpoint
$router->post('/v3/api/goomo-reservations',['uses'=>'otacontrollers\GoomoController@index']);
//Inventory auto push update to OTA routes
$router->get('/run-bucket-engine',['uses'=>'CmOtaBookingPushBucketController@actionBookingbucketengine']);
$router->get('/run-bucket-engine-ktdc',['uses'=>'CmOtaBookingPushBucketKtdcController@actionBookingbucketengine']);
$router->get('/run-bucket-engine_test',['uses'=>'CmOtaBookingPushBucketControllersTest@actionBookingbucketengine']);
$router->get('/run-bucket-engine_test2',['uses'=>'CmOtaBookingPushBucketTestControllers2@actionBookingbucketengine']);
$router->get('/run-bucket-engine_ranjit',['uses'=>'CmOtaBookingPushBucketRanjitController@actionBookingbucketengine']);
$router->get('/mail_fire_ranjit',['uses'=>'CmOtaBookingPushBucketRanjitController@mailFire']);
$router->get('/mail-fire-test/{ota_booking_id}/{bucket_booking_status}',['uses'=>'OtaAutoPushUpdateController@mailHandler1']);


$router->post('/v3/api/easemytrip-reservations',['uses'=>'otacontrollers\EaseMyTripController@actionIndex']);
$router->post('/v3/api/paytm-reservations',['uses'=>'otacontrollers\PaytmController@actionIndex']);
$router->post('/happyeasygo-reservations',['uses'=>'otacontrollers\HegController@actionIndex']);//Happy easy go controllers
$router->post('/v3/api/irctc-reservations',['uses'=>'otacontrollers\IrctcController@actionIndex']);//IRCTC controllers
$router->post('/v3/api/akbar-reservations',['uses'=>'otacontrollers\AkbarController@actionIndex']);//IRCTC controllers

$router->get('/manual-ota-booking/{hotel_id}/{booking_id}/{ota_name}/{booking_date}',['uses'=>'otacontrollers\manualOtaBookingFetch\ManualBookingMasterController@manualBookingFetch']);

$router->post('/test-save-current-inv',['uses'=>'CmOtaBookingInvStatusService@testSaveCurrentInvStatus']);

//push inventory to ota
$router->group(['prefix'=>'inv'],function($router){
    $router->post('/push-inv-to-ota',['uses'=>'InventoryPushAfterBEBookingToOtaController@getDetails']);
    $router->post('/push-inv-to-ota-test',['uses'=>'InventoryPushAfterBEBookingToOtaControllerTest@getDetails']);
    $router->post('/push-inv-to-ota-ktdc',['uses'=>'InventoryPushAfterBEBookingToOtaControllerTest@getDetailsForKtdc']);
});
// ota booking download path
$router->get('/booking-data/download/{booking_data}',['uses'=>'BookingDetailsDownloadController@getSearchData']);
$router->get('/get-otahotel-code',['uses'=>'CmOtaDetailsController@getOtaHotelCodeOfEaseMyTrip']);
//test the inventory fetch
$router->get('/test/{ota_booking_id}/{ota_id}/{hotel_id}/{from_date}/{to_date}/{room_types}/{booking_status}/{room_qty}',['uses'=>'CmOtaBookingInvStatusService@saveCurrentInvStatus']);
//ids testing
$router->get('/test-ids',['uses'=>'OtaAutoPushUpdateTestControllers@testhandleIds']);
//dynamic pricing ota push url:
$router->post('/push-to-ota',['uses'=>'DynamicPricingOtaController@pushToOta']);

$router->group(['prefix' => 'manage_inventory','middleware' => 'jwt.auth'], function($router) {
    //Ota and BE inventory update
    // $router->get('/get_inventory/{room_type_id}/{date_from}/{date_to}/{mindays}',['uses'=>'ManageInventoryController@getInventery']);
    // $router->get('/get_inventory_by_hotel/{hotel_id}/{date_from}/{date_to}/{mindays}',['uses'=>'ManageInventoryController@getInvByHotel']);
    //$router->post('/inventory_update',['uses'=>'InventoryController@inventoryUpdate']);
    $router->post('/inventory_update_test',['uses'=>'invrateupdatecontrollers\MasterInvRateUpdateTestController@bulkInvUpdate']);
        //Ota and BE Rates Update
    // $router->get('/get_room_rates/{room_type_id}/{rate_plan_id}/{date_from}/{date_to}',['uses'=>'ManageInventoryController@getRates']);
    // $router->get('/get_room_rates_by_hotel/{hotel_id}/{date_from}/{date_to}',['uses'=>'ManageInventoryController@getRatesByHotel']);
    //$router->post('/room_rate_update',['uses'=>'RoomRateController@roomRateUpdate']);
    $router->post('/room_rate_update_test',['uses'=>'invrateupdatecontrollers\MasterInvRateUpdateTestController@bulkRateUpdate']);
    // $router->post('/sync-inv',['uses'=>'SyncInventorynRatesController@pushInventory']);
        $router->post('/sync-inv_test',['uses'=>'invrateupdatecontrollers\MasterInvRateUpdateTestController@syncInvUpdate']);
    // $router->post('/sync-rates',['uses'=>'SyncInventorynRatesController@pushRates']);
    $router->post('/sync-rates_test',['uses'=>'invrateupdatecontrollers\MasterInvRateUpdateTestController@syncRateUpdate']);
    //$router->post('/update-inv',['uses'=>'SyncInventorynRatesController@updateInventory']);
    $router->post('/update-inv_test',['uses'=>'invrateupdatecontrollers\MasterInvRateUpdateTestController@singleInventoryUpdate']);
    //$router->post('/update-rates',['uses'=>'SyncInventorynRatesController@updateRates']);
    $router->post('/update-rates_test',['uses'=>'invrateupdatecontrollers\MasterInvRateUpdateTestController@singleRateUpdate']);

    //Block inventory
    //$router->post('/block_inventory',['uses'=>'OtaBlockInventoryController@addNewCmOtaBlockInventry']);
    $router->post('/block_inventory_test',['uses'=>'invrateupdatecontrollers\MasterInvRateUpdateTestController@blockInventoryUpdate']);
    $router->post('/unblock_inventory_test',['uses'=>'invrateupdatecontrollers\MasterInvRateUpdateTestController@unblockInventoryUpdate']);
    $router->post('/block_rate_test',['uses'=>'invrateupdatecontrollers\MasterInvRateUpdateTestController@blockRateUpdate']);
    $router->post('/unblock_rate_test',['uses'=>'invrateupdatecontrollers\MasterInvRateUpdateTestController@unblockRateUpdate']);

});
//New de report
$router->get('/getAllOtaBooking/{month}/{year}','DeReportController@getAllOtaBooking');
$router->get('/hotelUseingDE','DeReportController@hotelUseingDE');

$router->group(['prefix'=>'Ota-details'],function($router){
$router->get('/getDetails',['uses'=>'CmOtaDetailsController@getOtaDetail']);
});
$router->get('/cm_ota_all_hotel_details/{hotel_id}',['uses'=>'CmOtaDetailsController@getAllOTACMHotel']);


//testurl
$router->get('/test-gems-booking/{hotel_id}/{bucket_id}',['uses'=>'otacontrollers\CmBookingDataInsertionController@cmBookingDataInsertion']);
$router->get('/test-gems-details/{unique_id}/{gems}',['uses'=>'otacontrollers\CmBookingDataInsertionController@pushBookingToGems']);

// {{routes for DE}}
$router->group(['prefix'=>'superAdmin-report'],function($router){
    $router->get('/getOtaBooking/{from_date}/{to_date}/{hotel_id}/{question_id}','DeReportController@otaBooking');
    $router->get('/getHotelBooking/{from_date}/{to_date}/{hotel_id}/{question_id}','DeReportController@hotelBooking');
    $router->get('/hotelUseingDE','DeReportController@hotelUseingDE');
});

$router->get('/room-type-details','CmOtaDetailsController@test_details');

$router->group(['prefix' => 'ota-report'],function($router){
    $router->get('/last-seven-days/{hotel_id}',['uses'=>'DeReportController@noOfLastSevenDaysOTABookings']);

});
$router->get('/test-fun','TestController@testFunction');
$router->get('/goibibo-test-new',['uses'=>'invrateupdatecontrollers\GoibiboInvRateTestController@testOtaUpdate']);

//to redirect Be to Cm API
$router->post('/cm_ota_booking_inv_status',['uses'=>'CrsCancelBookingInvUpdateRedirectingController@postDetails']);
//over booking issue checking
$router->get('/cmota_update',['uses'=>'TestController@updateCmDetailsHotelIDActiveOrInactive']);
//test api for
$router->get('/test-inventory-flow',['uses'=>'InventoryUpdateAfterBookingController@test']);

//if booking push bucket stop processing it send a sms to the provided number.
$router->get('/sms-push-service',['uses'=>'CmOtaBookingPushBucketTestControllers2@checkBucketRunningStatus']);

//noshow for goibibo.
$router->post('/goibibo-noshow-test',['uses'=>'otacontrollers\GoibiboControllerTest@noShowForGoibibo']);
$router->post('/goibibo-noshow',['uses'=>'otacontrollers\GoibiboController@noShowForGoibibo']);

//booking cancelation push to ids
// $router->post('/crs_cancel_push_to_ids',['uses'=>'IdsXmlCreationAndExecutionControllerTest@pushIdsCrs']); //push to ids while crs cancel
$router->post('/crs_cancel_push_to_ids',['uses'=>'IdsXmlCreationAndExecutionController@pushIdsCrs']);
$router->post('/crs_cancel_push_to_ktdc',['uses'=>'KtdcXmlCreationAndExecutionController@pushKtdcCrs']);
//KTDC booking push
$router->get('/ktdc-test-booking',['uses'=>'KtdcController@testPushBooking']);

//Hotel OTA status update
$router->post('/hotel-ota-status-update',['uses'=>'CmOtaDetailsController@updateHotelOtaStatus']);

//goibibo promitional Api
$router->get('/goibibo-promotional',['uses'=>'invrateupdatecontrollers\GoibiboInvRateTestController@promotionalApiGoibibo']);
$router->post('/goibibo_create_offer',['uses'=>'invrateupdatecontrollers\GoibiboInvRateTestController@createOffer']);

$router->get('/all_ota_details',['uses'=>'DeReportController@getAllOtaType']);
$router->post('/get-otawise-hotel',['uses'=>'DeReportController@getOTAwiseHotel']);

//test property show.
$router->get('/ktdc-test',['uses'=>'TestController@testKtdcSupport']);

//ktdc soft booking push
$router->get('/ktdc-push-booking/{softBookId}/{ktdc_hotel}/{ktdc_id}',['uses'=>'KtdcController@ktdcConfirmBooking']);

//goibibo data bulk upload
$router->get('/goibibo-bookings-ch',['uses'=>'otacontrollers\GoibiboControllerTest@goibiboOccurance']);

//stop bucket processing status
$router->get('/bucket-stop',['uses'=>'CmOtaBookingPushBucketTestControllers2@runBucketAfterStop']);

//get pms push status
$router->get('/get-pms-push-status/{pms_name}/{booking_date}',['uses'=>'PmsLogController@get_pms_push_status']);
$router->get('/get-booking-inventory-logs/{hotel_id}/{room_type_id}/{transaction_date}',['uses'=>'PmsLogController@get_booking_inventory_logs']);
$router->get('/get-pms-names',['uses'=>'PmsLogController@get_pms_names']);
$router->get('/get-activity-logs/{hotel_id}/{user_id}/{log_date}/{log_type}',['uses'=>'PmsLogController@getActivityLog']);


//airbnb maping the existing listing routes
$router->get('/get-all-airbnb-listing/{hotel_id}',['uses'=>'AirbnbListingUpdateController@getAllAirbnbListing']);
$router->post('/add-ota-hotel-details',['uses'=>'AirbnbListingUpdateController@addNewCmHotel']);
$router->post('/update-ota-hotel-details/{ota_id}',['uses'=>'AirbnbListingUpdateController@updateCmHotel']);

//agoda booking processing controller

$router->post('/agoda-booking-notification',['uses'=>'otacontrollers\AgodaNewController@actionIndex']);
$router->post('/agoda-booking-notification-test',['uses'=>'otacontrollers\AgodaTestController@actionIndex']);
//old promotation
// $router->group(['prefix' => 'promotions'], function($router) {
//     $router->post('/create',    ['uses'=>'PromotionsController@createPromotion']);
//     $router->get('/all',        ['uses'=>'PromotionsController@getPromotion']);
//     $router->get('/{promotion_id}',['uses'=>'PromotionsController@getPromotionById']);
//     $router->post('/update/{promotion_id}',['uses'=>'PromotionsController@updatePromotion']);
//     $router->delete('/delete/{promotion_id}', ['uses' => 'PromotionsController@deletePromotionById']);
    
// });
//new promotation
$router->get('/get-all-promotion/{hotel_id}', ['uses' => 'HotelPromotionController@getAllPromotion']);
$router->get('/get-hotel-promotion/{hotel_id}/{id}', ['uses' => 'HotelPromotionController@getHotelPromotion']);
$router->post('/insert-hotel-promotion', ['uses' => 'HotelPromotionController@insertHotelPromotion']);
$router->post('/update-hotel-promotion', ['uses' => 'HotelPromotionController@updateHotelPromotion']);
$router->post('/deactivate-promotion', ['uses' => 'HotelPromotionController@deactivateHotelPromotion']);

$router->get('/get-all-inactive-promotion/{hotel_id}', ['uses' => 'HotelPromotionController@getAllInactivePromotion']);
$router->post('/activate-promotion', ['uses' => 'HotelPromotionController@activateHotelPromotion']);

$router->get('/view-promotion/{promotion_id}', ['uses' => 'HotelPromotionController@viewPromotionStatus']);
//pms remove url 

$router->post('/remove-hotel-pms-info',['uses' => 'PmsController@removeHotelPMSInfor']);

//hostelworld booking(reservation) processing.
$router->get('/hostelworld-booking',['uses'=>'otacontrollers\HostelworldController@actionIndex']);
//make test booking for hostelworld
$router->get('/make-test-booking',['uses'=>'otacontrollers\HostelworldController@makeTestBooking']);
//set cancellation
$router->get('/set-cancellation',['uses'=>'otacontrollers\HostelworldController@setCancellation']);

//dynamic pricing cronjob update.

$router->get('/dynamic-pricing-bucket-processing',['uses'=>'DynamicPricingController@dynamicPricingUpdateToChannel']);

//mail fire test
$router->get('/mail-fire-test',['uses'=>'TestController@mailFireTest']);

//ktdc data display

$router->get('/ktdc-info-dis',['uses'=>'KtdcDataDisplayController@KtdcCrsInv']);

//get booking list from goibibo

$router->get('/get-booking-list-goibibo',['uses'=>'otacontrollers\GoibiboTestController@getMissedBookings']);
$router->get('/get-booking-list-goibibo-id',['uses'=>'otacontrollers\GoibiboTestController@getMissedBookingsDetails']);
$router->get('/puttest',['uses'=>'TestController@puttest']);
$router->get('/get-goibibo-missing-bookings/{hotel_id}',['uses'=>'GoibiboFetchMissBookingController@getMissbooking']);
$router->get('/goibibo-missing-bookings-dlt/{hotel_id}/{booking_id}',['uses'=>'GoibiboFetchMissBookingController@processAllBooking']);
$router->get('/goibibo-missing-bookings-csv/{skip_data}',['uses'=>'GoibiboFetchMissBookingController@getMissbookingToCSV']);
$router->get('/goibibo-missing-bookings-process',['uses'=>'GoibiboFetchMissBookingController@getBookingDetails']);

//hostelworld testcases testing routes
$router->get('/test-cases-testing',['uses'=>'TestController@hostelWorldTesting']);







