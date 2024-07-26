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
//Hotel User Registration Route
$router->post('/hotel_users/register', ['uses' => 'CompanyRegistrationController@registerHotelAdmin']);
$router->put('/hotel_users/register', ['uses'=>'HotelUserController@activateUser']);
$router->get('/hotel_users/register/resend/{email}', ['uses' => 'HotelUserController@resendEmail']);

//Hotel User Login Route
$router->post('/gems/auth', ['uses' => 'GemsAuthController@gemsUserLogin']);
$router->post('/admin/auth', ['uses' => 'AdminAuthController@adminLogin']);
$router->post('/forgot-password', ['uses' => 'AdminAuthController@forgotPasswordAdmin']);
$router->get('/verify_user', ['uses' => 'AdminAuthController@verifyUser']);
$router->get('/last_login/{company_id}', ['uses' => 'AdminAuthController@lastLogin']);

$router->post('/user/auth', ['uses' => 'PublicUserController@login']);
$router->post('/user/register', ['uses' => 'PublicUserController@register']);


//Hotel user authenticated routes
$router->group(['prefix' => 'admin', 'middleware' => 'jwt.auth'], function ($router) {
    $router->get('/getInfo', ['uses'=>'AdminAuthController@getUsers']);
    $router->post('/change_password', ['uses' => 'AdminAuthController@changePassword']);
    $router->post('/check_password_admin', ['uses' => 'AdminAuthController@checkCurrentPassword']);
});
$router->post('/admin/change_password_admin', ['uses' => 'AdminAuthController@changePasswordAdmin']);
$router->get('/password_change_date/{company_id}', ['uses' => 'AdminAuthController@passwordChangeDate']);

//Hotel user Add/Update/Delete Hotel Property
// 'middleware' => 'jwt.auth'
$router->post('/add_new_property_new', ['uses'=>'AddHotelPropertyNewController@addNewHotelBrand']);
$router->post('/update_new_property/{uuid}', ['uses'=>'AddHotelPropertyController@updateNewHotelBrand']);
$router->post('/uploadhotelimages', ['uses'=>'ImageUploadNewController@imgageToUpload']);
$router->get('/get_Images/{hotel_id}', ['uses'=>'ImageUploadNewController@getImages']);
$router->get('/hotel_admin/get_all_hotels_by_id/{hotel_id}', ['uses'=>'AddHotelPropertyController@getAllRunningHotelDataByid']);
$router->post('/update_checkin_checkout_property/{uuid}', ['uses'=>'AddHotelPropertyController@updateCheckinCheckOut']);
$router->post('/update_property_address/{hotel_id}', ['uses'=>'AddHotelPropertyNewController@updatePropertyAddress']);

$router->group(['prefix' => 'hotel_admin','middleware' => 'jwt.auth' ], function ($router) {
    $router->get('/get_all_hotels_by_company/{comp_hash}/{company_id}', ['uses'=>'AddHotelPropertyController@getAllHotelsDataByCompany']);
    $router->post('/add_new_property', ['uses'=>'AddHotelPropertyController@addNewHotelBrand']);
    $router->post('/update_property/{uuid}', ['uses'=>'AddHotelPropertyController@updateHotelBrand']);
    $router->delete('delete_property/{uuid}', ['uses'=>'AddHotelPropertyController@deleteHotelInfo']);
    $router->delete('disable_property/{uuid}', ['uses'=>'AddHotelPropertyController@disableHotelInfo']);
    $router->get('/get_all_hotels', ['uses'=>'AddHotelPropertyController@getAllHotelData']);
    $router->get('/get_all_running_hotels', ['uses'=>'AddHotelPropertyController@getAllRunningHotelData']);
    $router->get('/get_all_deleted_hotels', ['uses'=>'AddHotelPropertyController@getAllDeletedHotelData']);
    $router->get('/get_all_disabled_hotels', ['uses'=>'AddHotelPropertyController@getAllDisabledHotelData']);
    $router->get('/get_all_hotels_by_country/{country_id}', ['uses'=>'AddHotelPropertyController@getAllRunningHotelDataByCountryId']);
    $router->get('/get_all_hotels_by_country_state/{country_id}/{state_id}', ['uses'=>'AddHotelPropertyController@getAllRunningHotelDataByCountryAndStateId']);
    $router->get('/get_all_hotels_by_country_state_city/{country_id}/{state_id}/{city_id}', ['uses'=>'AddHotelPropertyController@getAllRunningHotelDataByCountryAndStateAndCityId']);
    $router->get('/get_all_hotels_by_name/{name}', ['uses'=>'AddHotelPropertyController@getAllRunningHotelDataByName']);
    $router->get('/get_all_hotels_by_group/{group_uuid}', ['uses'=>'AddHotelPropertyController@getAllRunningHotelDataByGroup']);
    $router->get('/get_all_hotels_by_company_details/{comp_hash}/{commpany_id}/{auth_from}', ['uses'=>'AddHotelPropertyController@getAllHotelsDataByCompanyDetails']);

    $router->post('/exterior', ['uses'=>'AddHotelPropertyController@updateExterior']);
    $router->post('/interior', ['uses'=>'AddHotelPropertyController@updateInterior']);

    $router->get('/get_interior_images/{hotel_id}', ['uses'=>'AddHotelPropertyController@getInteriorImages']);
    $router->get('/get_hotel_list/{company_id}', ['uses'=>'AddHotelPropertyController@getHotelList']);
});

$router->get('/hotel_admin/hotels_by_company/{comp_hash}/{company_id}', ['uses'=>'AddHotelPropertyController@getAllHotelsByCompany']);
$router->get('/hotel_admin/get_all_hotel_by_id/{hotel_id}', ['uses'=>'AddHotelPropertyController@getAllRunningHotelDataByid']);
$router->get('/hotel_admin/get_all_hotel_by_id_be/{hotel_id}', ['uses'=>'AddHotelPropertyController@getAllRunningHotelDataByidBE']);


//Hotel Currencies Routes
    $router->group(['prefix' => 'currencies','middleware' => 'jwt.auth'], function ($router) {
        $router->post('/add', ['uses'=>'CurrenciesController@addNewCurrencies']);
        $router->post('/update/{id}', ['uses'=>'CurrenciesController@updateCurrencies']);
        $router->delete('/{id}', ['uses'=>'CurrenciesController@deleteCurrencies']);
        $router->get('/all', ['uses'=>'CurrenciesController@getAllgetCurrencies']);
        $router->get('/get/{id}', ['uses'=>'CurrenciesController@getCurrencies']);
    });

//Hotel Finance Related Details Routes
$router->group(['prefix' => 'finance_related','middleware' => 'jwt.auth'], function ($router) {
    $router->post('/add', ['uses'=>'FinanceRelatedDetailsController@addNewFinanceDetails']);
    $router->post('/update/{id}', ['uses'=>'FinanceRelatedDetailsController@updateFinanceRelatedDetails']);
    $router->get('/all', ['uses'=>'FinanceRelatedDetailsController@getAllFinanceRelatedDetails']);
    $router->get('/{id}', ['uses'=>'FinanceRelatedDetailsController@getFinanceRelatedSetails']);
    $router->get('/get/{country_id}', ['uses'=>'FinanceRelatedDetailsController@getTitlesByCountry']);
    $router->get('/getCountry/{hotel_id}', ['uses'=>'AddHotelPropertyController@gethotelCountry']);
});

//Hotel Tax Details Routes
    $router->group(['prefix' => 'tax_details','middleware' => 'jwt.auth'], function ($router) {
        $router->post('/add', ['uses'=>'TaxDetailsController@addNewTaxDetails']);
        $router->post('/update', ['uses'=>'TaxDetailsController@updateTaxDetails']);
        $router->get('/{hotel_id}', ['uses'=>'TaxDetailsController@getTaxDetails']);
    });

//GST details update
    $router->get('get-gst-details/{hotel_id}', ['uses'=>'TaxDetailsController@getGSTDetail']);
    $router->post('update-gst-details', ['uses'=>'TaxDetailsController@updateGSTDetail']);

//Hotel paid service Routes
    $router->group(['prefix' => 'paid_services','middleware' => 'jwt.auth'], function ($router) {
        $router->post('/add', ['uses'=>'PaidServicesController@addNewPaidService']);
        $router->post('/update/{id}', ['uses'=>'PaidServicesController@updatePaidService']);
        $router->get('/{paid_service_id}', ['uses'=>'PaidServicesController@getHotelPaidService']);
        $router->get('all/{hotel_id}', ['uses'=>'PaidServicesController@getHotelPaidServices']);
        $router->delete('delete/{paid_service_id}', ['uses'=>'PaidServicesController@DeletePaidServices']);
    });
//For Booking Engine
$router->get('/paidServices/{hotel_id}', ['uses'=>'PaidServicesController@getHotelPaidServices']);

//Routes By godti Vinod
//Hotel amenities
$router->group(['prefix' => 'hotel_amenities','middleware' => 'jwt.auth'], function ($router) {
    $router->post('/update', ['uses'=>'HotelAmenitiesController@updateHotelAmenities']);
    $router->get('/all', ['uses'=>'HotelAmenitiesController@getAmenities']);
    $router->get('/hotelAmenity/{hotel_id}', ['uses'=>'HotelAmenitiesController@getAmenitiesByHotel']);
});

//Hotel cancellation Policy
$router->group(['prefix' => 'cancellation_policy','middleware' => 'jwt.auth'], function ($router) {
    $router->post('/add', ['uses'=>'HotelCancellationController@addNewCancellationPolicies']);
    $router->post('/update/{id}', ['uses'=>'HotelCancellationController@updateCancellationPolicy']);
    $router->get('/{id}', ['uses'=>'HotelCancellationController@getHotelCancellationPolicy']);
    $router->get('/all/{hotel_id}', ['uses'=>'HotelCancellationController@GetAllCancellationPolicy']);
    $router->delete('/delete/{cancel_policy_id}', ['uses'=>'HotelCancellationController@DeleteCancellationPolicy']);
});

//Hotel other Information
$router->group(['prefix' => 'hotel_other_information','middleware' => 'jwt.auth'], function ($router) {
    $router->post('/update', ['uses'=>'HotelOtherInformationController@updateHotelOtherInformation']);
    $router->get('/{hotel_id}', ['uses'=>'HotelOtherInformationController@getHotelOtherInformation']);
});
//Hotel child Policy
$router->group(['prefix' => 'child_policy','middleware' => 'jwt.auth'], function ($router) {
    $router->post('/add', ['uses'=>'HotelChildPlicyController@addNewChildPolicy']);
    $router->post('/update', ['uses'=>'HotelChildPlicyController@updateChildPolicy']);
    $router->get('/{hotel_id}', ['uses'=>'HotelChildPlicyController@getChildPolicy']);
});
//Hotel Rate Plan Details Routes
$router->group(['prefix' => 'master_rate_plan','middleware' => 'jwt.auth'], function ($router) {
    $router->post('/add', ['uses'=>'MasterRatePlancontroller@addNew']);
    $router->post('/update/{rate_plan_id}', ['uses'=>'MasterRatePlancontroller@UpdateMasterRatePlan']);
    $router->delete('/{rate_plan_id}', ['uses'=>'MasterRatePlancontroller@DeleteMasteReatePlan']);
    $router->get('/all/{hotel_id}', ['uses'=>'MasterRatePlancontroller@GetAllHotelRatePlan']);
    $router->get('/{rate_plan_id}', ['uses'=>'MasterRatePlancontroller@GetHotelRatePlan']);
    $router->get('/rate_plans/{hotel_id}', ['uses'=>'MasterRatePlancontroller@GetRatePlans']);
    $router->get('/rate_plan/{rate_plan_id}', ['uses'=>'MasterRatePlancontroller@GetRateplan']);
});

//master Hotel Rate Plan Details Routes
$router->group(['prefix' => 'master_hotel_rate_plan','middleware' => 'jwt.auth'], function ($router) {
    $router->post('/add', ['uses'=>'MasterHotelRatePlanController@addNew']);
    $router->post('/update/{room_rate_plan_id}', ['uses'=>'MasterHotelRatePlanController@UpdateMasterHotelRatePlan']);
    $router->delete('/{room_rate_plan_id}', ['uses'=>'MasterHotelRatePlanController@DeleteMasterHotelRatePlan']);
    $router->get('/all/{hotel_id}', ['uses'=>'MasterHotelRatePlanController@GetAllMasterHotelRateplan']);
    $router->get('/{room_rate_plan_id}', ['uses'=>'MasterHotelRatePlanController@GetMasterHotelRatePlan']);
    $router->get('/rate_plan_by_room_type/{room_type_id}', ['uses'=>'MasterHotelRatePlanController@GetRatePlanByRoomType']);
    $router->get('/room_rate_plan/{hotel_id}', ['uses'=>'MasterHotelRatePlanController@GetRoomRatePlan']);
    $router->get('/room_rate_plan_by_room_type/{hotel_id}/{room_type_id}', ['uses'=>'MasterHotelRatePlanController@GetRoomRatePlanByRoomType']);
    $router->post('/update-status-for-be', ['uses'=>'MasterHotelRatePlanController@modifystatus']);
});
//booking Routes
$router->group(['prefix' => 'booking'], function ($router) {
    $router->post('/all/{hotel_id}', ['uses'=>'ManageBookingController@GetAllBooking']);
});
//cancellation booking Routes
$router->group(['prefix' => 'cancellation_booking'], function ($router) {
    $router->post('/all/{hotel_id}', ['uses'=>'ManageCancellationController@GetAllCancellationBooking']);
});

//packages Routes
$router->group(['prefix' => 'packages','middleware' => 'jwt.auth'], function ($router) {
    $router->post('/add', ['uses'=>'PackagesController@addNewPackages']);
    $router->post('/update/{package_id}', ['uses'=>'PackagesController@UpdatePackages']);
    $router->delete('/{package_id}', ['uses'=>'PackagesController@DeletePackages']);
    $router->get('/all/{hotel_id}', ['uses'=>'PackagesController@GetAllPackages']);
    $router->get('/{package_id}', ['uses'=>'PackagesController@GetPackages']);
    $router->get('/get_packages_images/{package_id}', ['uses'=>'PackagesController@getPckagesImages']);
    $router->post('delete', ['uses'=>'PackagesController@deleteImage']);
});
$router->get('/packages/get_packages_images/{package_id}', ['uses'=>'PackagesController@getPckagesImages']);

// quick payment Routes
    $router->group(['prefix' => 'quick_payment','middleware' => 'jwt.auth'], function ($router) {
        $router->post('/add', ['uses'=>'QuickPaymentLinkController@addQuickPayment']);
        $router->get('/all/{hotel_id}', ['uses'=>'QuickPaymentLinkController@GetAllQuickPayment']);
        $router->get('/check/{payment_link_id}', ['uses'=>'QuickPaymentLinkController@CheckQuickPayment']);
        $router->get('/resend-email/{payment_link_id}/{txn_id}', ['uses'=>'QuickPaymentLinkController@resendEmail']);
        $router->get('/get_quickpayment_bookings/{id}/{hotel_id}', ['uses'=>'QuickPaymentLinkController@getQuickPaymentBookingDetails']);
        $router->post('/get-room-rate-details', ['uses'=>'QuickPaymentLinkController@getRoomRateDetails']);
    });

// Image Upload  Routes
$router->group(['prefix' => 'upload'], function ($router) {
    $router->post('/{hotel_id}', ['uses'=>'ImageUploadController@imgageToUpload']);
});
$router->get('/hotel_admin/get_exterior_images/{hotel_id}', ['uses'=>'AddHotelPropertyController@getExteriorImages']);
$router->post('/deleteImage', ['uses'=>'ImageUploadController@deleteImage']);
$router->get('/getImages/{hotel_id}', ['uses'=>'ImageUploadController@getImages']);
$router->get('/hotel_master_room_type/{room_type_id}', ['uses'=>'MasterRoomTypeController@getHotelroomtype']);

//Room type Routes
$router->group(['prefix' => 'hotel_master_room_type','middleware' => 'jwt.auth'], function ($router) {
    $router->post('add', ['uses'=>'MasterRoomTypeController@addNewRoomType']);
    $router->post('update/{room_type_id}', ['uses'=>'MasterRoomTypeController@updatemasterroomtype']);
    $router->delete('delete/{room_type_id}', ['uses'=>'MasterRoomTypeController@deletemasterroomtype']);
    $router->post('delete', ['uses'=>'MasterRoomTypeController@deleteImage']);
    $router->get('/room_types/{hotel_id}', ['uses'=>'MasterRoomTypeController@GetRoomTypes']);
    $router->get('/room_type/{room_type_id}', ['uses'=>'MasterRoomTypeController@GetRoomType']);
    $router->get('/get_rack_price/{room_type_id}', ['uses'=>'MasterRoomTypeController@getHotelRackPrice']);
    $router->get('/get_max_people/{room_type_id}', ['uses'=>'MasterRoomTypeController@getMaxPeople']);
    $router->post('/airbnb-details-add', ['uses'=>'AirbnbController@addAirBnbDetails']);
    $router->post('/airbnb-details-update/{airbnb_details_id}', ['uses'=>'AirbnbController@updateAirBnbDetails']);
    $router->get('/airbnb-data/{hotel_id}/{room_type_id}', ['uses'=>'AirbnbController@getAirbnbData']);
    $router->get('/airbnb-ready-review/{hotel_id}/{room_type_id}', ['uses'=>'AirbnbController@updateReviewStatus']);
    $router->get('/getairbnb-instant-booking/{room_type_id}/{hotel_id}', ['uses'=>'AirbnbController@getAirbnbMaxdaystatus']);
    $router->get('/getairbnb-instant-booking/{airbnb_status}/{room_type_id}/{hotel_id}', ['uses'=>'AirbnbController@airbnbInstantBooking']);
    $router->post('/listing_notification/{hotel_id}/{room_type_id}', ['uses'=>'MasterRoomTypeController@updateNotification']);
});

$router->get('hotel_master_room_type/get_room_images/{room_type_id}', ['uses'=>'MasterRoomTypeController@getroomtypeImages']);
$router->get('room_type_forbe/room_types/{hotel_id}', ['uses'=>'MasterRoomTypeController@GetRoomTypes']);

//test public coupon//
$router->get('/test/{hotel_id}/{date_from}/{date_to}', ['uses'=>'BookingEngineController@getAllPublicCupons']);
$router->get('/test1/{hotel_id}/{date_from}/{date_to}', ['uses'=>'BookingEngineController@AllPublicCupon']);
//---//
//Manage inveventory routes
$router->group(['prefix' => 'manage_inventory','middleware' => 'jwt.auth'], function ($router) {
    //Ota and BE inventory update
    $router->get('/get_inventory/{room_type_id}/{date_from}/{date_to}/{mindays}', ['uses'=>'ManageInventoryController@getInventery']);
    $router->get('/get_inventory_by_hotel/{hotel_id}/{date_from}/{date_to}/{mindays}', ['uses'=>'ManageInventoryController@getInvByHotel']);
    //$router->post('/inventory_update',['uses'=>'InventoryController@inventoryUpdate']);
    $router->post('/inventory_update', ['uses'=>'invrateupdatecontrollers\MasterInvRateUpdateController@bulkInvUpdate']);
    //Ota and BE Rates Update
    $router->get('/get_room_rates/{room_type_id}/{rate_plan_id}/{date_from}/{date_to}', ['uses'=>'ManageInventoryController@getRates']);
    $router->get('/get_room_rates_by_hotel/{hotel_id}/{date_from}/{date_to}', ['uses'=>'ManageInventoryController@getRatesByHotel']);
    //$router->post('/room_rate_update',['uses'=>'RoomRateController@roomRateUpdate']);
    $router->post('/room_rate_update', ['uses'=>'invrateupdatecontrollers\MasterInvRateUpdateController@bulkRateUpdate']);
    // $router->post('/sync-inv',['uses'=>'SyncInventorynRatesController@pushInventory']);
    $router->post('/sync-inv', ['uses'=>'invrateupdatecontrollers\MasterInvRateUpdateController@syncInvUpdate']);
    // $router->post('/sync-rates',['uses'=>'SyncInventorynRatesController@pushRates']);
    $router->post('/sync-rates', ['uses'=>'invrateupdatecontrollers\MasterInvRateUpdateController@syncRateUpdate']);
    //$router->post('/update-inv',['uses'=>'SyncInventorynRatesController@updateInventory']);
    $router->post('/update-inv', ['uses'=>'invrateupdatecontrollers\MasterInvRateUpdateController@singleInventoryUpdate']);
    //$router->post('/update-rates',['uses'=>'SyncInventorynRatesController@updateRates']);
    $router->post('/update-rates', ['uses'=>'invrateupdatecontrollers\MasterInvRateUpdateController@singleRateUpdate']);

    //Block inventory
    //$router->post('/block_inventory',['uses'=>'OtaBlockInventoryController@addNewCmOtaBlockInventry']);
    $router->post('/block_inventory', ['uses'=>'invrateupdatecontrollers\MasterInvRateUpdateController@blockInventoryUpdate']);
    $router->post('/unblock_inventory', ['uses'=>'invrateupdatecontrollers\MasterInvRateUpdateController@unblockInventoryUpdate']);
    $router->post('/block_rate', ['uses'=>'invrateupdatecontrollers\MasterInvRateUpdateController@blockRateUpdate']);
    $router->post('/unblock_rate', ['uses'=>'invrateupdatecontrollers\MasterInvRateUpdateController@unblockRateUpdate']);
});

//coupons Routes
$router->group(['prefix' => 'coupons','middleware' => 'jwt.auth'], function ($router) {
    $router->post('/add', ['uses'=>'CouponsController@addNewCoupons']);
    $router->post('/update/{coupon_id}', ['uses'=>'CouponsController@Updatecoupons']);
    $router->delete('/{coupon_id}', ['uses'=>'CouponsController@DeleteCoupons']);
    $router->get('/all', ['uses'=>'CouponsController@GetAllCoupons']);
    $router->get('/{coupon_id}', ['uses'=>'CouponsController@GetCoupons']);
    $router->get('/get/{hotel_id}', ['uses'=>'CouponsController@GetCouponsByHotel']);
});
//promotional popup Routes
$router->group(['prefix' => 'promotional_popup','middleware' => 'jwt.auth'], function ($router) {
    $router->post('/add', ['uses'=>'PromotionalPopupController@addNewPromo']);
    $router->post('/update/{coupon_id}', ['uses'=>'PromotionalPopupController@UpdatePromo']);
    $router->delete('/{promo_id}', ['uses'=>'PromotionalPopupController@DeletePromo']);
    $router->get('/all', ['uses'=>'PromotionalPopupController@GetAllPromo']);
    $router->get('/{coupon_id}', ['uses'=>'PromotionalPopupController@GetPromo']);
    $router->get('/get/{hotel_id}', ['uses'=>'PromotionalPopupController@GetPromoByHotel']);
});
// offline booking Routes
$router->group(['prefix' => 'offline_booking','middleware' => 'jwt.auth'], function ($router) {
    $router->post('/add', ['uses'=>'OfflineBookingController@addNewOfflineBooking']);

    $router->get('/{user_id}', ['uses'=>'OfflineBookingController@GetOfflineBooking']);
    $router->get('/all/{hotel_id}/{type}/{from_date}/{to_date}', ['uses'=>'OfflineBookingController@GetAllOfflineBooking']);
});
$router->get('/booking/all/{hotel_id}/{type}/{from_date}/{to_date}', ['uses'=>'ManageBookingController@GetAllBooking']);
//CRM Routes
$router->group(['prefix' => 'crm_leads','middleware' => 'jwt.auth'], function ($router) {
    $router->post('/add', ['uses'=>'CrmLeadsController@addNewcrmleads']);
    $router->post('/update/{contact_details_id}', ['uses'=>'CrmLeadsController@UpdateCrmLeads']);
    $router->get('/{contact_details_id}', ['uses'=>'CrmLeadsController@GetCrmLeads']);
    $router->get('/all/{hotel_id}', ['uses'=>'CrmLeadsController@GetAllCrmLeads']);
});
// follow up Routes
$router->group(['prefix' => 'follow_up','middleware' => 'jwt.auth'], function ($router) {
    $router->post('/add', ['uses'=>'followUpController@addNewFollowUp']);
    $router->get('/all/{client_id}', ['uses'=>'followUpController@GetAllFollowUp']);
});
//manage user routes
$router->group(['prefix' => 'manage_user','middleware' => 'jwt.auth'], function ($router) {
    $router->post('/add', ['uses'=>'ManageUserController@addNewUsers']);
    $router->post('/update/{admin_id}', ['uses'=>'ManageUserController@UpdateUsers']);
    $router->get('/delete-user/{admin_id}', ['uses'=>'ManageUserController@DeleteUsers']);
    $router->get('/{admin_id}', ['uses'=>'ManageUserController@GetUsers']);
    $router->get('/all/{company_id}', ['uses'=>'ManageUserController@GetAllUsers']);
    $router->get('/external_users/{company_id}/{hotel_id}', ['uses'=>'ManageUserController@GetExternalUsers']);
    $router->get('/agent/{company_id}/{hotel_id}', ['uses'=>'ManageUserController@GetAgentUsers']);
});

//CM ota Details  Routes
    $router->group(['prefix' => 'cm_ota_details','middleware' => 'jwt.auth'], function ($router) {
        $router->post('/add', ['uses'=>'CmOtaDetailsController@addNewCmHotel']);
        $router->post('/update/{ota_id}', ['uses'=>'CmOtaDetailsController@updateCmHotel']);
        $router->delete('/{hotel_id}/{ota_id}', ['uses'=>'CmOtaDetailsController@deleteCmHotel']);
        //$router->get('/all',['uses'=>'CmOtaDetailsController@getAllCmHotel']);
        $router->get('/{ota_id}', ['uses'=>'CmOtaDetailsController@getCmHotel']);
        $router->get('/sync/{ota_id}', ['uses'=>'CmOtaDetailsController@multipleFunction']);
        $router->get('/get/{hotel_id}', ['uses'=>'CmOtaDetailsController@getAllCmHotel']);
        $router->get('/toggle/{hotel_id}/{ota_id}/{is_active}', ['uses'=>'CmOtaDetailsController@toggle']);
    });
 //CM ota room type sync  Routes
    $router->group(['prefix' => 'cm_ota_roomtype_sync','middleware' => 'jwt.auth'], function ($router) {
        $router->post('/add', ['uses'=>'CmOtaSyncController@addNewCmOtaSync']);
        $router->post('/update/{id}', ['uses'=>'CmOtaSyncController@updateCmOtaSync']);
        $router->delete('/{id}', ['uses'=>'CmOtaSyncController@deleteCmOtaSync']);
        $router->get('/ota_room_types/{hotel_id}/{ota_id}', ['uses'=>'CmOtaSyncController@otaRoomTypes']);
        $router->get('/ota_sync_room_types/{hotel_id}/{ota_id}', ['uses'=>'CmOtaSyncController@fetchOtaSyncRoomTypes']);
        $router->get('/ota_sync_data/{sync_id}', ['uses'=>'CmOtaSyncController@fetchOtaSyncById']);
        $router->get('/ota_rate_plan/{hotel_id}/{ota_id}/{ota_room_type_id}', ['uses'=>'CmOtaSyncController@fetchOtaRoomRatePlan']);
        $router->get('/ota_sync_rate_plan/{hotel_id}/{ota_id}', ['uses'=>'CmOtaSyncController@fetchOtaSyncRoomRatePlan']);
        $router->get('/ota_sync_rate/{sync_id}', ['uses'=>'CmOtaSyncController@fetchOtaRatePlanSyncById']);
        $router->get('/ota_room_type/{hotel_id}/{ota_id}/{room_type_id}', ['uses'=>'CmOtaSyncController@fetchOtaRoomType']);
        $router->get('/all_ota/{hotel_id}', ['uses'=>'CmOtaSyncController@getAllSyncRoomsData']);
        $router->get('/all_ota_rates/{hotel_id}', ['uses'=>'CmOtaSyncController@getAllSyncRoomRateData']);
    });

 //CM pms details
    $router->group(['prefix' => 'cm_pms_details','middleware' => 'jwt.auth'], function ($router) {
        $router->get('/all', ['uses'=>'PmsControllerDetails@getAllPmsType']);
        $router->get('/all/{hotel_id}', ['uses'=>'PmsControllerDetails@getHotelPmsType']);
        $router->post('/add_pms_hotelid', ['uses'=>'PmsControllerDetails@addPmsAccountHotelId']);
        $router->post('/add_pms_room_fetch', ['uses'=>'PmsControllerDetails@addNewPmsSync']);
        $router->get('/get_pms_rooms/{hotel_id}', ['uses'=>'PmsControllerDetails@getPmsRoomType']);
        $router->post('/add_pms_room_sync', ['uses'=>'PmsControllerDetails@addNewPmsRoomSync']);
        $router->post('/update_pms_room_sync/{sync_id}', ['uses'=>'PmsControllerDetails@updatePmsRoomSync']);
        $router->delete('/{sync_id}', ['uses'=>'PmsControllerDetails@deletePmsRoomSync']);
        $router->get('/get_pms_room_sync/{hotel_id}', ['uses'=>'PmsControllerDetails@getPmsRoomSync']);
        $router->get('/get_pms_room_sync_id/{sync_id}', ['uses'=>'PmsControllerDetails@getPmsRoomSyncId']);
        $router->get('/get_pms_logo/{logo_id}', ['uses'=>'PmsControllerDetails@getPmsLogo']);
    });
    //check if the hotel takes IDS
    $router->post('/check_ids_hotel', ['uses'=>'PmsControllerDetails@checkIdsHotel']);
    $router->get('/check_pms_hotel/{hotel}', ['uses'=>'PmsControllerDetails@checkPmsHotel']);


    $router->get('/getHotelForGems/{hotel_id}', ['uses'=>'PmsControllerDetails@fetchHotelForGems']);
    //CM ota  rate plan sync  Routes
    $router->group(['prefix' => 'cm_ota_rateplan_sync','middleware' => 'jwt.auth'], function ($router) {
        $router->post('/add', ['uses'=>'CmOtaSyncController@addNewCmOtaRatePlanSync']);
        $router->post('/update/{id}', ['uses'=>'CmOtaSyncController@updateCmOtaRatePlanSync']);
        $router->delete('/{id}', ['uses'=>'CmOtaSyncController@deleteCmOtaRatePlanSync']);
    });
    //Ota and BE Inventory update
    $router->post('/hotel_inventory_update', ['uses'=>'InventoryController@inventoryUpdate']);
    //Ota and BE Rates Update
    $router->post('/hotel_room_rate_update', ['uses'=>'RoomRateController@roomRateUpdate']);
    /*================OTA BBookings==========================*/
    $router->group(['prefix' => 'ota_bookings'], function ($router) {
        $router->get('/get/{from_date}/{to_date}/{date_type}/{ota}/{booking_status}/{hotel_id}/{booking_id}', ['uses'=>'OtaBookingController@getOtaBookingsDateWise']);
    });

    //Company Profile updation routes
    $router->group(['prefix' => 'company_profile','middleware' => 'jwt.auth'], function ($router) {
        $router->post('/add', ['uses'=>'CompanyRegistrationController@addNew']);
        $router->post('/update', ['uses'=>'CompanyRegistrationController@updateProfile']);
        $router->get('/{company_id}', ['uses'=>'CompanyRegistrationController@getCompanyProfile']);
        $router->post('/booking_page/{company_id}', ['uses'=>'CompanyRegistrationController@updateBookingPageDetails']);
        $router->get('/get/{company_id}', ['uses'=>'CompanyRegistrationController@getCompanyDetails']);
    });
    $router->get('/company_profile/get-logo/{company_id}', ['uses'=>'CompanyRegistrationController@getCompanyLogo']);
    $router->post('company_profile/delete', ['uses'=>'CompanyRegistrationController@deleteImage']);

    ///Bookingjini PMS Routes
    $router->group(['prefix' => 'pms','middleware' => 'jwt.auth'], function ($router) {
        $router->get('hotel-details/{key}/{hotel_id}', ['uses'=>'PmsController@hotelDetails']);
        $router->post('booking-details', ['uses'=>'PmsController@bookingDetails']);
        $router->post('update-inventory', ['uses'=>'PmsController@updateInventory']);
    });
    //IDS PMS Routes
    $router->group(['prefix' => 'ids'], function ($router) {
        $router->post('update-inventory', ['uses'=>'IdsController@updateInventory']);
        $router->get('get-response', ['uses'=>'IdsController@getResponse']);
        $router->get('bookings', ['uses'=>'IdsController@execute']);
    });
     //rms routes
     $router->group(['prefix' => 'rms'], function ($router) {
         $router->post('/update-inventory', ['uses'=>'RmsController@updateInventory']);
         $router->post('/getroomtype', ['uses'=>'RmsController@getRoomType']);
         $router->post('/getrateplan', ['uses'=>'RmsController@getRatePlan']);
         $router->post('/update-rates', ['uses'=>'RmsController@updateRates']);
         $router->post('/bookings_rules', ['uses'=>'RmsController@rmsBookingRules']);
         $router->get('/ota_bookings', ['uses'=>'CmOtaBookingPushBucketController@actionBookingbucketengine']);
     });
    //pms details
    $router->group(['prefix' => 'pms_details','middleware' => 'jwt.auth'], function ($router) {
        $router->post('/add', ['uses'=>'PmsDetailsController@addNewCmHotel']);
        $router->post('/update/{ota_id}', ['uses'=>'PmsDetailsController@updateCmHotel']);
        $router->get('/sync/{ota_id}', ['uses'=>'PmsDetailsController@multipleFunction']);
    });
    //Dashboard Routes
    $router->get('/invoiceAmount/getById/{hotel_id}', ['uses'=>'DashBoardController@selectInvoice']);
    $router->group(['prefix'=>'dashboard','middleware' => 'jwt.auth'], function ($router) {
        $router->get('/getById/{hotel_id}/{from_date}/{to_date}', ['uses'=>'DashBoardController@selectInvoice']);
        $router->get('/getAll/{hotel_id}', ['uses'=>'DashBoardController@getOtaDetails']);
        $router->get('/gethotelbooking/{hotel_id}', ['uses'=>'DashBoardController@getHotelBookings']);
        $router->get('/getAllcheckout/{hotel_id}', ['uses'=>'DashBoardController@getOtaDetailsCheckOut']);
        $router->get('/gethotelbookingcheckout/{hotel_id}', ['uses'=>'DashBoardController@getHotelBookingsCheckOut']);
        $router->get('/hotelbooking/{invoice_id}', ['uses'=>'DashBoardController@hotelBookingCheckInOutInvoice']);
        $router->get('/otabooking/{id}', ['uses'=>'DashBoardController@otaBookingCheckInOutid']);
        $router->get('/bookingEngeenHelth/{hotel_id}', ['uses'=>'DashBoardController@percentageCount']);
        $router->get('/yearlyhotelbooking/{hotel_id}', ['uses'=>'DashBoardController@yearlyHotelBooking']);
        $router->get('/yearlyotabooking/{hotel_id}', ['uses'=>'DashBoardController@yearlyOtaBooking']);
    });
    //Unique visitor Route
    $router->group(['prefix'=>'dashboard'], function ($router) {
        $router->post('/uniqueVisitors/{hotel_id}', ['uses'=>'DashBoardController@uniqueVisitors']);
        $router->get('/uniqueVisitorsDashboard/{company_id}/{from_date}/{to_date}', ['uses'=>'DashBoardController@uniqueVisitorsDashboard']);
        $router->get('/uniqueVisitorsWB/{company_id}/{from_date}/{to_date}', ['uses'=>'DashBoardController@uniqueVisitorsWB']);
    });
    //Mail-Invoice
    $router->group(['prefix'=>'mailInvoice','middleware' => 'jwt.auth'], function ($router) {
        $router->get('/details/{hotel_id}', ['uses'=>'MailInvoiceController@getInvoiceDetails']);
        $router->post('/mail/{hotel_id}', ['uses'=>'MailInvoiceController@sendInvoiceMail']);
    });
    //Logs Routes
    $router->group(['prefix'=>'log-details','middleware' => 'jwt.auth'], function ($router) {
        $router->get('/inventory/{hotel_id}/{from_date}/{to_date}/{room_type_id}/{selected_be_ota_id}', ['uses'=>'LogsController@inventoryDetails']);
        $router->get('/rateplan/{hotel_id}/{from_date}/{to_date}/{rate_plan_id}/{selected_be_ota_id}/{room_type_id}', ['uses'=>'LogsController@rateplanDetails']);
        $router->get('/session/{hotel_id}/{from_date}/{to_date}', ['uses'=>'LogsController@userSession']);
    });
    $router->group(['prefix'=>'ota-log-details','middleware' => 'jwt.auth'], function ($router) {
        $router->get('/inventory/{hotel_id}/{from_date}/{to_date}/{room_type_id}/{selected_be_ota_id}', ['uses'=>'NewFile\OtaLogsController@otaInventoryDetails']);
        $router->get('/rateplan/{hotel_id}/{from_date}/{to_date}/{rate_plan_id}/{selected_be_ota_id}/{room_type_id}', ['uses'=>'NewFile\OtaLogsController@OtaRateplanDetails']);
        $router->get('/booking/{hotel_id}/{from_date}/{to_date}', ['uses'=>'NewFile\OtaLogsController@bookingDetails']);
    });
    //blocking ip
    $router->post('/BlockedClientIp/insert', ['uses'=>'BlockController@blockClientIp']);
    $router->get('/BlockedClientIp/get', ['uses'=>'BlockController@BlockIpDetails']);
    $router->delete('/BlockedClientIp/delete/{wrong_attempt_id}', ['uses'=>'BlockController@unBlockIp']);

    //reporting
    $router->group(['prefix'=>'reporting','middleware' => 'jwt.auth'], function ($router) {
        $router->get('/details/{hotel_id}/{type}', ['uses'=>'ReportingController@bookingDetails']);
        $router->get('/details_dashboard/{hotel_id}/{from_date}/{to_date}', ['uses'=>'ReportingController@dashboardBookingDetails']);
        $router->get('/total-earning/{hotel_id}/{type}', ['uses'=>'ReportingController@bookingTotalEarning']);
        $router->get('/occupancy/{hotel_id}/{type}', ['uses'=>'ReportingController@occupancy']);
        $router->get('/roomtypeSelect/{hotel_id}', ['uses'=>'ReportingController@roomType']);
        $router->get('/average/{hotel_id}/{room_type_id}', ['uses'=>'ReportingController@average']);
        $router->get('/tvcBooking/{hotel_id}', ['uses'=>'ReportingController@tvcBooking']);
        $router->get('/otaSelect/{hotel_id}', ['uses'=>'ReportingController@getOtaDetails']);
        $router->get('/cvcBooking/{hotel_id}/{ota_id}', ['uses'=>'ReportingController@cvcBooking']);
        $router->get('/comission/{hotel_id}', ['uses'=>'ReportingController@comission']);
        $router->post('/inventory/{hotel_id}', ['uses'=>'ReportingController@inventory']);
        $router->get('/get-otawise-booking/{ota_id}/{hotel_id}', ['uses'=>'ReportingController@getOTAtotalBookings']);
    });

//Device Notfication APi
$router->post('/device_info/device_details', ['uses'=>'DeviceNotificationController@deviceInformation']);

//Test
$router->get('/test-pushIds', ['uses'=>'BookingEngineController@testPushIds']);


//Test airbnb
$router->get('/get_airbnb_token', ['uses'=>'MasterRoomTypeController@getAirbnbToken']);

//no show routes
$router->group(['prefix'=>'bookingDotCom','middleware' => 'jwt.auth'], function ($router) {
    $router->post('/noshow', ['uses'=>'otacontrollers\BookingdotcomController@noShowPush']);
    $router->post('/getdemodata', ['uses'=>'BookingdotcomDemoController@BookingdotcomDemo']);
});
$router->post('/get_bookingdotcom', ['uses'=>'otacontrollers\BookingdotcomController@actionIndex']);
$router->post('/get_goibibo', ['uses'=>'otacontrollers\GoibiboController@actionIndex']);
$router->post('/get_easemytrip', ['uses'=>'otacontrollers\EaseMyTripController@actionIndex']);
$router->post('/get_agoda', ['uses'=>'otacontrollers\AgodaController@actionIndex']);
$router->post('/get_cleartrip', ['uses'=>'otacontrollers\CleartripController@actionIndex']);
$router->post('/get_travelguru', ['uses'=>'otacontrollers\TravelguruController@actionIndex']);
$router->post('/get_viadotcom', ['uses'=>'otacontrollers\ViadotcomController@actionIndex']);
//test the booking voucher
$router->post('/test/test-voucher/', ['uses'=>'otacontrollers\GoibiboController@actionIndex']);
$router->get('/test/voucher_mail/{ota_booking_id}/{bucket_booking_status}', ['uses'=>'OtaAutoPushUpdateController@mailHandler1']);
$router->post('/test/test-booking-voucher/', ['uses'=>'otacontrollers\BookingdotcomController@actionIndex']);
$router->post('/test/test-agoda-voucher/', ['uses'=>'otacontrollers\AgodaController@actionIndex']);
$router->post('/test/test-expedia-voucher/', ['uses'=>'otacontrollers\ExpediaController@actionIndex']);
$router->post('/test/test-via-voucher/', ['uses'=>'otacontrollers\ViadotcomController@actionIndex']);
$router->post('/test/test-cleartrip-voucher/', ['uses'=>'otacontrollers\CleartripController@actionIndex']);
$router->post('/test/test-travelguru-voucher/', ['uses'=>'otacontrollers\TravelguruController@actionIndex']);
$router->post('/test/test-paytm-voucher/', ['uses'=>'otacontrollers\PaytmController@actionIndex']);
$router->get('/test/test-booking-modify', ['uses'=>'otacontrollers\BookingdotcomModifyController@actionIndex']);

//Bookings from HappyEasyGo
$router->post('/happyeasygo-reservations', ['uses'=>'otacontrollers\HegController@actionIndex']);//Happy easy go controllers
//Bookings from IRCTC
$router->post('/irctc-reservations', ['uses'=>'otacontrollers\IrctcController@actionIndex']);// IRCTC controllers
//Bookings from AKBAR fetching bookings
$router->get('/akbar-reservations', ['uses'=>'otacontrollers\AkbarController@actionIndex']);//Akbar controllers
$router->get('/goibibo-reservations-test', ['uses'=>'otacontrollers\GoibiboController@actionIndex']);//Happy easy go controllers

//guest details of checkin date
$router->get('/guest/guest-details/', ['uses'=>'GuestCheckinNotification@guestInformation']);
$router->get('/booking-data/download/{booking_data}', ['uses'=>'BookingDetailsDownloadController@getSearchData']);
//for testing ota bucket controller
$router->get('/get-inv-from-ota', ['uses'=>'CmOtaBookingPushBucketController@actionBookingbucketengine']);
//for testing inv update from otabookingdatainsert
$router->get('/update-inv-in-be', ['uses'=>'otacontrollers\BookingDataInsertationController@updateInvForBe']);

//new reports
$router->group(['prefix'=>'newreports'], function ($router) {
    $router->get('/number-of-night/{hotel_id}/{checkin}/{checkout}', ['uses'=>'NewReportController@getRoomNightsByDateRange']);
    $router->get('/total-amount/{hotel_id}/{checkin}/{checkout}', ['uses'=>'NewReportController@totalRevenueOtaWise']);
    $router->get('/total-bookings/{hotel_id}/{checkin}/{checkout}', ['uses'=>'NewReportController@numberOfBookings']);
    $router->get('/average-stay/{hotel_id}/{checkin}/{checkout}', ['uses'=>'NewReportController@averageStay']);
    $router->get('/rate-plan-performance/{hotel_id}/{checkin}/{checkout}', ['uses'=>'NewReportController@ratePlanPerformance']);
    $router->get('/rate-performance/{hotel_id}/{checkin}/{checkout}', ['uses'=>'NewReportController@ratePerformance']);
});

//display api for inv,rate and bookings

$router->group(['prefix'=>'get-data'], function ($router) {
    $router->post('/number-of-inventory', ['uses'=>'InvRateBookingDisplayController@invData']);
    $router->post('/rate_amount', ['uses'=>'InvRateBookingDisplayController@rateData']);
});
//derive plan api

$router->group(['prefix'=>'derive-plan'], function ($router) {
    $router->get('/check-plan/{hotel_id}', ['uses'=>'DerivePlanController@checkRoomRatePlan']);
    $router->post('/add-plan', ['uses'=>'DerivePlanController@addDetailsOfDerivedPlan']);
    $router->get('/update-derived-plan/{room_rate_plan_id}/{master_status}', ['uses'=>'DerivePlanController@updateMasterPlanStatus']);
    $router->get('/get-plan-details/{hotel_id}/{room_type_id}', ['uses'=>'DerivePlanController@getRoomTypeRatePlanName']);
    $router->get('/make-normal/{hotel_id}/{room_rate_plan}/{room_type}/{rate_plan}', ['uses'=>'DerivePlanController@normalPlan']);
    $router->post('/check_room_occupancy', ['uses'=>'DerivePlanController@getRoomOccupancy']);
    $router->get('/get_derived_rate_plan/{hotel_id}/{room_type_id}/{rate_plan_id}', ['uses'=>'DerivePlanController@getDerivedRatePlan']);
});

//get api data for website-builder
$router->get('/check-website-status/{company_id}', ['uses'=>'AdminAuthController@fetchwebsitestatus']);
$router->get('/get-room-details/{company_id}', ['uses'=>'AdminAuthController@retriveRoomDetails']);
$router->post('/fetch-subdomain-name/{company_id}', ['uses'=>'AdminAuthController@fetchSubdomain']);
$router->get('/fetch-map-details/{company_id}', ['uses'=>'AdminAuthController@getMapDetails']);
$router->get('/get-hotel-menu/{company_id}', ['uses'=>'AdminAuthController@getHotelMenuDetails']);
$router->get('/get-hotel-details/{company_id}', ['uses'=>'AdminAuthController@getHotelDetails']);
$router->get('/get-hotel-banner/{company_id}', ['uses'=>'AdminAuthController@getHotelBanner']);
$router->get('/fetch-hotel-details/{company_id}', ['uses'=>'AdminAuthController@fetchDetails']);
$router->post('/fetch-hotel-mailid/{hotel_id}', ['uses'=>'AdminAuthController@getHotelMailId']);
$router->post('/fetch-hotel-packages/{hotel_id}/{checkin_date}/{checkout_date}', ['uses'=>'AdminAuthController@getHotelPackages']);


$router->get('/test-mail-handler', ['uses'=>'OtaAutoPushUpdateController@testmailhandler']);


//sync inventory and rate
$router->group(['prefix'=>'sync-inv-rate'], function ($router) {
    $router->get('/push-ota', ['uses'=>'invrateupdatecontrollers\MasterInvRateUpdateController@syncInvRateDataPushToOTA']);
    $router->get('/notifications/{hotel_id}', ['uses'=>'NotificationController@getNotificationDetails']);
    $router->post('/read-status', ['uses'=>'NotificationController@updateReadStatus']);
});
//get all hotel specific details

$router->get('/get-specific-details', ['uses'=>'AllHotelDetailsControllers@getHotelSpecificDetails']);

$router->get('/test-hotelogix', ['uses'=>'CmOtaBookingPushBucketController@actionBookingbucketengine']);

// $router->get('/', function () use ($router) {
//     return $router->app->version();
// });
$router->get('sqs', function () use ($router) {
    // \App\Jobs\TestJob::dispatch();
    // return $router->app->version();
    $job=new TestJob();
    dispatch($job);
});

$router->get('run-bucket', function () use ($router) {
    $job2=new BucketJob();
    dispatch($job2);
    // echo "Processing...";
});
// $router->get('syncInvRate', function() use ($router){
//     $job=(new SyncInvRate())->delay(Carbon::now()->addSeconds(25));
//       dispatch($job);
//     return 'success';
// });

//get BDT currency test
$router->get('/get-BTD', ['uses'=>'CurrencyController@getBDT']);

//get hotel name from hotel id for jini-chat-panel
$router->get('/retrive-hotel-name/{hotel_id}', ['uses'=>'AdminAuthController@getHotelName']);
$router->get('/retrive-hotel-list', ['uses'=>'AdminAuthController@getHotelList']);
$router->post('/retrive-hotel-list', ['uses'=>'AdminAuthController@getHotelList']);

//rateshopper api for test

$router->get('/customer-details', ['uses'=>'TestController@getCustomerDetails']);
$router->post('/user-details', ['uses'=>'TestController@getUserDetails']);
$router->post('/hotel-details', ['uses'=>'TestController@getHotelDetails']);

//crm bookings

$router->post('/customer-details', ['uses'=>'CRMBookingControllers@getBooking']);
$router->post('/get-customer-details', ['uses'=>'TestController@getCustomerDetailsByDate']);





//New Routes for dashboard and reporting
$router->group(['prefix'=>'dashboard','middleware' => 'jwt.auth'], function ($router) {
    $router->get('/select-be-invoice/{hotel_id}/{from_date}/{to_date}', ['uses'=>'NewFile\BeDashboardController@selectInvoice']);
    $router->get('/select-ota-invoice/{hotel_id}/{from_date}/{to_date}', ['uses'=>'NewFile\OtaDashboardController@selectInvoice']);
    $router->get('/select-be-visitor/{company_id}/{from_date}/{to_date}', ['uses'=>'NewFile\BeDashboardController@beUniqueVisitors']);

    $router->get('/uniqueVisitorsWB/{company_id}/{from_date}/{to_date}', ['uses'=>'NewFile\WebsitebuilderController@uniqueVisitorsWB']);

    $router->get('/select-ota-room-nights/{hotel_id}/{checkin}/{checkout}', ['uses'=>'NewFile\OtaDashboardController@getRoomNightsByDateRange']);
    $router->get('/select-be-room-nights/{hotel_id}/{checkin}/{checkout}', ['uses'=>'NewFile\BeDashboardController@getRoomNightsByDateRange']);
    $router->get('/select-ota-revenue/{hotel_id}/{checkin}/{checkout}', ['uses'=>'NewFile\OtaDashboardController@totalRevenueOtaWise']);
    $router->get('/select-be-revenue/{hotel_id}/{checkin}/{checkout}', ['uses'=>'NewFile\BeDashboardController@totalRevenueOtaWise']);
    $router->get('/select-crs-revenue/{hotel_id}/{checkin}/{checkout}', ['uses'=>'NewFile\CrsDashboardController@totalRevenueOtaWise']);
    $router->get('/select-ota-avgstay/{hotel_id}/{checkin}/{checkout}', ['uses'=>'NewFile\OtaDashboardController@averageStay']);
    $router->get('/select-be-avgstay/{hotel_id}/{checkin}/{checkout}', ['uses'=>'NewFile\BeDashboardController@averageStay']);
    $router->get('/select-ota-rateplan/{hotel_id}/{checkin}/{checkout}', ['uses'=>'NewFile\OtaDashboardController@ratePlanPerformance']);
    $router->get('/select-be-rateplan/{hotel_id}/{checkin}/{checkout}', ['uses'=>'NewFile\BeDashboardController@ratePlanPerformance']);

    $router->get('/ota-booking/{hotel_id}/{from_date}/{to_date}', ['uses'=>'NewFile\OtaDashboardController@dashboardBookingDetails']);
    $router->get('/be-booking/{hotel_id}/{from_date}/{to_date}', ['uses'=>'NewFile\BeDashboardController@dashboardBookingDetails']);
    $router->get('/crs-booking/{hotel_id}/{from_date}/{to_date}', ['uses'=>'NewFile\CrsDashboardController@dashboardBookingDetails']);

    $router->get('/checkin-ota-booking/{hotel_id}', ['uses'=>'NewFile\OtaDashboardController@getOtaDetails']);
    $router->get('/checkin-be-booking/{hotel_id}', ['uses'=>'NewFile\BeDashboardController@getHotelBookings']);
    $router->get('/checkin-crs-booking/{hotel_id}', ['uses'=>'NewFile\CrsDashboardController@getCrsBookings']);

    $router->get('/checkout-ota-booking/{hotel_id}', ['uses'=>'NewFile\OtaDashboardController@getOtaDetailsCheckOut']);
    $router->get('/checkout-be-booking/{hotel_id}', ['uses'=>'NewFile\BeDashboardController@getHotelBookingsCheckOut']);
    $router->get('/checkout-crs-booking/{hotel_id}', ['uses'=>'NewFile\CrsDashboardController@getCrsBookingsCheckOut']);
    $router->get('/be-checkinout-invoice/{invoice_id}', ['uses'=>'NewFile\BeDashboardController@hotelBookingCheckInOutInvoice']);
});

//Inventory section get inventory by ota id
    $router->get('/ota_inv_rate/get_ota_inventory_by_hotel/{ota_id}/{hotel_id}/{date_from}/{date_to}/{mindays}', ['uses'=>'NewFile\OtaInventoryBookingFetchController@getInventoryDetails']);
    $router->get('/ota_inv_rate/get_ota_rates_by_hotel/{ota_id}/{hotel_id}/{date_from}/{date_to}', ['uses'=>'NewFile\OtaInventoryBookingFetchController@getRatePlan']);

//new reports
$router->group(['prefix'=>'benewreports'], function ($router) {
    $router->get('/be_number-of-night/{hotel_id}/{checkin}/{checkout}', ['uses'=>'NewFile\BeReportingController@getRoomNightsByDateRange']);
    $router->get('/be_total-amount/{hotel_id}/{checkin}/{checkout}', ['uses'=>'NewFile\BeReportingController@totalRevenueOtaWise']);
    $router->get('/be_total-bookings/{hotel_id}/{checkin}/{checkout}', ['uses'=>'NewFile\BeReportingController@numberOfBookings']);
    $router->get('/be_average-stay/{hotel_id}/{checkin}/{checkout}', ['uses'=>'NewFile\BeReportingController@averageStay']);
    $router->get('/be_rate-plan-performance/{hotel_id}/{checkin}/{checkout}', ['uses'=>'NewFile\BeReportingController@ratePlanPerformance']);
});
//new reports for crs
$router->group(['prefix'=>'crsnewreports'], function ($router) {
    $router->get('/crs_number-of-night/{hotel_id}/{checkin}/{checkout}', ['uses'=>'NewFile\CrsReportingController@getRoomNightsByDateRange']);
    $router->get('/crs_total-amount/{hotel_id}/{checkin}/{checkout}', ['uses'=>'NewFile\CrsReportingController@totalRevenueOtaWise']);
    $router->get('/crs_total-bookings/{hotel_id}/{checkin}/{checkout}', ['uses'=>'NewFile\CrsReportingController@numberOfBookings']);
    $router->get('/crs_average-stay/{hotel_id}/{checkin}/{checkout}', ['uses'=>'NewFile\CrsReportingController@averageStay']);
    $router->get('/crs_rate-plan-performance/{hotel_id}/{checkin}/{checkout}', ['uses'=>'NewFile\CrsReportingController@ratePlanPerformance']);
});
//new reports for ota
$router->group(['prefix'=>'otanewreports'], function ($router) {
    $router->get('/ota_number-of-night/{hotel_id}/{checkin}/{checkout}', ['uses'=>'NewFile\OtaReportingController@getRoomNightsByDateRange']);
    $router->get('/ota_total-amount/{hotel_id}/{checkin}/{checkout}', ['uses'=>'NewFile\OtaReportingController@totalRevenueOtaWise']);
    $router->get('/ota_total-bookings/{hotel_id}/{checkin}/{checkout}', ['uses'=>'NewFile\OtaReportingController@numberOfBookings']);
    $router->get('/ota_average-stay/{hotel_id}/{checkin}/{checkout}', ['uses'=>'NewFile\OtaReportingController@averageStay']);
    $router->get('/ota_rate-plan-performance/{hotel_id}/{checkin}/{checkout}', ['uses'=>'NewFile\OtaReportingController@ratePlanPerformance']);
});

//dynamic pricing
$router->group(['prefix'=>'dynamic-pricing'], function ($router) {
    $router->post('/details', ['uses'=>'NewFile\DynamicPricingController@dynamicPricingFormula']);
    $router->get('/rate-push/{hotel_id}/{room_type_id}/{booking_id}', ['uses'=>'NewFile\DynamicPricingController@executeDynamicPricing']);
    $router->post('/get-details', ['uses'=>'NewFile\DynamicPricingController@getDynamicPricing']);
    $router->get('/delete-details/{dlt_id}', ['uses'=>'NewFile\DynamicPricingController@deleteDynamicPricing']);
});

//push dynamic pricing to ota and be
$router->post('/push-to-ota', ['uses'=>'NewFile\DynamicPricingOtaController@pushToOta']);

//push ota booking details and be booking details
$router->get('/ota-booking-push/{booking_id}/{booking_status}', ['uses'=>'NewFile\CallToDynamicPricingController@collectOtaBookingData']);
$router->get('/be-booking-push/{booking_id}/{booking_date}/{booking_status}', ['uses'=>'NewFile\CallToDynamicPricingController@collectBeBookingData']);


//push booking data to be and ota
$router->post('/execute-dynamic-pricing', ['uses'=>'NewFile\ExecutingDynamicPricingController@getBookingData']);


// gems api
$router->post('/gems-details', ['uses'=>'NewFile\GemsDetailsPushController@storeDetails']);

$router->group(['prefix'=>'hotel-details'], function ($router) {
    $router->post('/get-hotel-list', ['uses'=>'CityWiseHotelDisplayController@getHotelDetails']);
    $router->get('/getCityList', ['uses'=>'CityHotelViewController@getAllCityes']);
});
//be anenities testing.
$router->post('/get-amenities', ['uses'=>'BeAmenitiesDisplayController@amenityGroup']);

//super admin role
$router->get('/get-role-access/{role_id}', ['uses'=>'SuperAdminController@getRoleTypeDetail']);
$router->get('/get-role', ['uses'=>'SuperAdminController@getRole']);
$router->get('/get-single-role/{role_type_id}', ['uses'=>'SuperAdminController@getSingleRole']);
$router->post('/save-role', ['uses'=>'SuperAdminController@saveRole']);
$router->post('/save-super-admin', ['uses'=>'SuperAdminController@saveSuperadminUser']);
$router->post('/update-super-admin', ['uses'=>'SuperAdminController@updateSuperadminUser']);
$router->get('/get-superadmin-users', ['uses'=>'SuperAdminController@getSuperadminUser']);
$router->post('/delete-superadmin-users', ['uses'=>'SuperAdminController@deleteSuperadminUser']);

//onboarding

$router->group(['prefix' => 'new-extranet-company'], function ($router) {
    $router->post('/registration', 'ExtranetNewCompanyController@registration');
    $router->get('/list', 'ExtranetNewCompanyController@newCompanyRequestList');
    $router->get('/singleCompany/{id}', 'ExtranetNewCompanyController@singleCompanyDetail');
    $router->get('/fetchagreement/{id}', 'ExtranetNewCompanyController@fetchagreement');
    $router->get('/fetchSingleagreement', 'ExtranetNewCompanyController@fetchSingleagreement');
    $router->post('/updateStatus', 'ExtranetNewCompanyController@updateCompanyStatus');
    $router->post('/multipleupdateStatus', 'ExtranetNewCompanyController@updateMultipleCompany');
    $router->post('/updateAgreement', 'ExtranetNewCompanyController@updateAgreement');
    $router->post('/fetchSingleCompany', 'ExtranetNewCompanyController@fetchSingleCompany');
});
//onboarding




//testing the logic skills
  $router->get('/compute', ['uses'=>'NewFile\TestLogicController@pegionLogic']);
  $router->get('/nake-lace', ['uses'=>'NewFile\TestLogicController@necklace']);
  $router->get('/bfs', ['uses'=>'NewFile\TestLogicController@graphSearchBFS']);
  $router->get('/dfs', ['uses'=>'NewFile\TestLogicController@dfs']);
  $router->get('/weighted-bfs', ['uses'=>'NewFile\TestLogicController@weightedGraphSearchBFS']);
  $router->post('/test-string', ['uses'=>'NewFile\TestLogicController@testString']);
  $router->get('/bookingdata/download/{arrStr}', 'BookingDetailsDownloadControllerCsv@comission');


  $router->get('/', 'BookingDetailsDownloadControllerCsv@comission');

//TestBookingDeletion

    $router->post('/booking-delete', ['uses'=>'BookingDeletionController@bookingDelete']);

//Channel Manager Updation and Processing Status
    $router->get('/cmups-details/{hotel_id}', ['uses'=>'CmProcessingController@CmupsDetails']);

//Test Send Mail
    $router->get('/test_mail/{email}/{template}/{subject}/{hotel_email}/{hotel_name}', ['uses'=>'BookingEngineController@sendMail']);

    $router->get('/test-mail/{id}', 'TestingController@preinvoiceMail');
    $router->get('/test-mail-crs/{id}', 'CrsReservationController@preinvoiceMail');
    $router->get('/test-mail-pack/{id}', 'PackageBookingController@preinvoiceMail');
    $router->get('/test/{ota_booking_id}/{bucket_booking_status}', ['uses'=>'OtaAutoPushUpdateController@mailHandler']);
    $router->get('/test-1/{ota_booking_id}/{bucket_booking_status}', ['uses'=>'OtaAutoPushUpdateController@mailHandler1']);

    $router->group(['prefix' => 'mail-notification'], function ($router) {
        $router->get('/be-mail-details/{hotel_id}/{current_date}', ['uses'=>'MailNotificationController@BeMailDetails']);
        $router->get('/be-mail-resend/{hotel_id}/{id}', ['uses'=>'MailNotificationController@BeMailResend']);
        $router->get('/cm-mail-details/{hotel_id}/{current_date}', ['uses'=>'MailNotificationController@CmMailDetails']);
        $router->get('/cm-mail-resend/{hotel_id}/{id}', ['uses'=>'MailNotificationController@CmMailResend']);
        $router->get('/crs-mail-details/{hotel_id}/{current_date}', ['uses'=>'MailNotificationController@CrsMailDetails']);
        $router->get('/crs-mail-resend/{hotel_id}/{id}', ['uses'=>'MailNotificationController@CrsMailResend']);
    });

// PMS PUSh Data to OTA
    $router->get('/ids-inventory-update', 'PmsPushDataToOtaController@pushIdsInventoryToOta');

// Hotel table and Company table
    $router->get('/update-inactive-company-subdomain', ['uses'=>'MailNotificationController@updateInactiveCompanySubdomain']);

// Digital Marketing
$router->group(['prefix' => 'digital-marketing'], function ($router) {
    $router->get('/dm-details/{hotel_id}/{fin_year}', ['uses'=>'MarketingEngineController@getDmDetails']);
});

//menu master
$router->group(['prefix' => 'menu_master'], function ($router) {
    $router->get('/menu_listing', ['uses'=>'MenuMasterController@getMenuList']);
    $router->post('/add_menu', ['uses'=>'MenuMasterController@addMenu']);
    $router->post('/add_menu', ['uses'=>'MenuMasterController@updateMenu']);

    $router->get('/get-menu', ['uses'=>'MenuMasterController@getMenuMaster']);
});

//New CRS
$router->group(['prefix' => 'crs'], function ($router) {
    $router->get('/hotel_details/{company_id}', ['uses'=>'CrsBookingsController@getHotelDetails']);
    $router->post('/crs_bookings', ['uses'=>'CrsBookingsController@crsBookings']);
    $router->get('/crs_mail/{invoice_id}/{payment_type}', ['uses'=>'CrsBookingsController@crsBookingMail']);
    $router->get('/crs_cronjob', ['uses'=>'CrsBookingsController@crsBookingCronJob']);
    $router->get('/crs_pay/{booking_id}', ['uses'=>'CrsBookingsController@crsPayBooking']);
    $router->post('/crs_modify_bookings', ['uses'=>'CrsBookingsController@crsModifyBooking']);
    $router->post('/crs_register_user_modify', ['uses'=>'CrsBookingsController@crsRegisterUserModify']);
    $router->post('/crs_cancel_booking', ['uses'=>'CrsBookingsController@crsCancelBooking']);
    $router->get('/crs_cancel_refund/{invoice_id}', ['uses'=>'CrsBookingsController@crsCancelRefund']);
    $router->post('/crs_cancel_details', ['uses'=>'CrsBookingsController@crsCacelReportData']);
    $router->post('/crs-reservation-info', ['uses'=>'CrsBookingsController@crsReservation']);
    $router->post('/crs-reservation-info-test', ['uses'=>'CrsBookingsTest2Controller@crsReservation']);
});
    $router->get('/crs-bookings/{invoice_id}/{crs}', ['uses'=>'BookingEngineController@gemsBooking']);
    $router->get('/crs-booking/{invoice_id}/{crs}', ['uses'=>'BookingEngineController@crsBooking']);
    $router->post('/cm_ota_booking_inv_status', ['uses'=>'CrsCancelBookingInvUpdateRedirectingController@postDetails']);
    $router->post('/crs_cancel_details_report', ['uses'=>'CrsController@crsCacelReportData']); //testing of crs cancellation report api for pagination
    $router->post('/crs_cancel_push_to_ids', ['uses'=>'IdsXmlCreationAndExecutionController@pushIdsCrs']); //push to ids while crs cancel
    // cm current invoice
    $router->get('/get-cm-ota-current-inventory/{rm_type}/{from_date}/{to_date}/{ota_id}/{hotel_id}/{inventoryData}', ['uses'=>'CmOtaBookingInvStatusService@getOtaCurrentInventory']);

    //push inventory to ota
    $router->group(['prefix'=>'inv'], function ($router) {
        $router->post('/push-inv-to-ota', ['uses'=>'InventoryPushAfterBEBookingToOtaController@getDetails']);
        //added by subash routes
        $router->post('/push-winhms-inv-to-ota', ['uses'=>'InventoryPushAfterBEBookingToOtaController@getWinhmsDetails']);
    });

    //Notification System

    $router->group(['prefix' => 'notification'], function ($router) {
        $router->get('/billing_cronjob', ['uses'=>'NotificationSystemController@billingdateNotificationCronJob']);
        $router->get('/roomrateplan_cronjob', ['uses'=>'NotificationSystemController@roomRatePlanNotificationCronJob']);
        $router->get('/inventorydata_cronjob', ['uses'=>'NotificationSystemController@inventoryNotificationCronJob']);
        $router->get('/ratedata_cronjob', ['uses'=>'NotificationSystemController@RateNotificationCronJob']);
        $router->get('/ids_missed_booking_cronjob', ['uses'=>'NotificationSystemController@idsMissedBookingNotificationCronjob']);
        $router->get('/ktdc_missed_bookings_cronjob', ['uses'=>'NotificationSystemController@ktdcMissedBookingNotificationCronjob']);
        $router->post('/ids_missed_booking_fetch', ['uses'=>'NotificationSystemController@idsMissedBookingNotificationFetch']);
        $router->post('/ktdc_missed_bookings_fetch', ['uses'=>'NotificationSystemController@ktdcMissedBookingNotificationFetch']);
        $router->get('/accept_notification/{accept_ids}/{hotel_id}', ['uses'=>'NotificationSystemController@acceptNotification']);
        $router->get('/get_notification/{hotel_id}', ['uses'=>'NotificationSystemController@getNotifications']);
    });

    // Booking Engine Cancellation & Modification
    $router->post('/be-cancellation', ['uses'=>'BookingEngineCancellationController@cancelBooking']);
    $router->post('/be_modification', ['uses'=>'BookingEngineModificationController@beModification']);
    $router->post('/be_user_modify', ['uses'=>'BookingEngineModificationController@beUserModify']);

    $router->post('/goibibo_create_offer', ['uses'=>'otacontrollers\GoibiboPromotionalController@createOffer']);
    $router->post('/bookingdotcom_create_offer', ['uses'=>'otacontrollers\BookingdotcomPromotionalController@createOffer']);
    $router->post('/bookingdotcom_retrive_review', ['uses'=>'otacontrollers\BookingdotcomPromotionalController@retrive_reviews']);

    //Extranet Notification
    $router->get('get_extranet_announcement/{hotel_id}', ['uses'=>'ExtranetAnnouncementController@getExtranetAnnouncement']);
    $router->post('no_show_announcement/{hotel_id}', ['uses'=>'ExtranetAnnouncementController@NoShowNotification']);

    $router->get('/get_inventory_by_source_be/{be}/{roomid}/{from_date}/{to_date}/{min_days}', ['uses'=>'invrateupdatecontrollers\FetchBeDataForInvRateSyncController@getInventoryBySourceBe']);
    $router->get('/get_rate_by_source_be/{be}/{roomid}/{rate_id}/{from_date}/{to_date}', ['uses'=>'invrateupdatecontrollers\FetchBeDataForInvRateSyncController@getRateBySourceOta']);

    $router->post('/sync-inv-be', ['uses'=>'invrateupdatecontrollers\BookingEngineInvRateController@syncInventoryUpdate']);
    $router->post('/sync-rate-be', ['uses'=>'invrateupdatecontrollers\BookingEngineInvRateController@rateSyncUpdate']);
    $router->post('/inventory_update_be', ['uses'=>'invrateupdatecontrollers\BookingEngineInvRateController@bulkInvUpdate']);

    $router->get('/quickpayment-test/{id}', ['uses'=>'QuickPaymentLinkController@actionPaymentstatusUpdate']);

    $router->post('/bookings/{api_key}', ['uses'=>'BookingEngineController@bookings']);


     //User access
     $router->post('/user-access-updation-or-creation', ['uses' => 'UserAccessFunctionController@creationOrUpdation']);
     $router->get('/get-user-access/{user_id}', ['uses' => 'UserAccessFunctionController@getUserAccess']);
     $router->delete('/user-access-delete/{user_id}', ['uses' => 'UserAccessFunctionController@deleteUserAccess']);
     $router->get('/get-access-functionality', ['uses' => 'UserAccessFunctionController@accessFunctionlity']);

     //User access


    //  Hotel Promotions
        $router->get('/get-all-promotion/{hotel_id}', ['uses' => 'HotelPromotionController@getAllPromotion']);
        $router->get('/get-hotel-promotion/{hotel_id}/{id}', ['uses' => 'HotelPromotionController@getHotelPromotion']);
        $router->post('/insert-hotel-promotion', ['uses' => 'HotelPromotionController@insertHotelPromotion']);
        $router->post('/update-hotel-promotion', ['uses' => 'HotelPromotionController@updateHotelPromotion']);
        $router->post('/deactivate-promotion', ['uses' => 'HotelPromotionController@deactivateHotelPromotion']);

        $router->get('/get-all-inactive-promotion/{hotel_id}', ['uses' => 'HotelPromotionController@getAllInactivePromotion']);
        $router->post('/activate-promotion', ['uses' => 'HotelPromotionController@activateHotelPromotion']);
    //  Hotel Promotions


    //$router->get('/winhms_inventory_update',['uses'=>'PmsPushDataWinhmsToOtaController@pushInventoryToOta']);
    //$router->get('/winhms_rate-update',['uses'=>'PmsPushDataWinhmsToOtaController@pushRateToOta']);
   // $router->post('/winhms/update-inv',['uses'=>'BookingjiniPMSController@winhmsInventroryUpdate']);
    //$router->post('/v2/backend/web/api/winhms/update-inventory',['uses'=>'WinhmsController@actionUpdateInventory']);
    //$router->get('/winhms-reservation',['uses'=>'WinhmsController@pushIDSDetails']);
//ClearTrip Hyper Guest Routes
        $router->post('/clearTripRateInvUpdates', ['uses'=>'CmOtaDetailsController@clearTripHyperGuestCmHotel']);
        $router->post('/clearTripBooking', ['uses'=>'CleartripHyperGuestController@actionIndex']);
        $router->get('/getAllCountries', 'ExtranetV4\CountryController@getAllCountry');
