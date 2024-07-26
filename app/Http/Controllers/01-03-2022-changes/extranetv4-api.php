<?php

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->get('/getAllPropertyTypes','ExtranetV4\PropertyTypesController@getAllPropertyTypes');
$router->get('/getAllPropertySubTypes/{property_type_id}','ExtranetV4\PropertySubTypesController@getAllPropertySubTypes');
$router->get('/getAllPropertyAmenities','ExtranetV4\PropertyAmenitiesController@getAllPropertyAmenities');
$router->get('/getAllRoomtypeamenities','ExtranetV4\RoomTypesAmenitiesController@getAllRoomtypeamenities');

$router->post('/hotel_master_new_room_type/add','ExtranetV4\MasterRoomTypeNewController@addNewRoomType');


//Room type Routes
$router->group(['prefix' => 'hotel_master_new_room_type','middleware' => 'jwt.auth'], function($router) {
    // $router->get('/all/{hotel_id}',['uses'=>'MasterRoomTypeController@getAllRoomTypes']);
    // $router->get('/{room_type_id}',['uses'=>'MasterRoomTypeController@getHotelroomtype']);
    // $router->post('update/{room_type_id}',['uses'=>'MasterRoomTypeController@updatemasterroomtype']);
    // $router->delete('delete/{room_type_id}',['uses'=>'MasterRoomTypeController@deletemasterroomtype']);
    // $router->post('delete',['uses'=>'MasterRoomTypeController@deleteImage']);
    // $router->get('/room_types/{hotel_id}',['uses'=>'MasterRoomTypeController@GetRoomTypes']);
    // $router->get('/room_type/{room_type_id}',['uses'=>'MasterRoomTypeController@GetRoomType']);
    // $router->get('/get_rack_price/{room_type_id}',['uses'=>'MasterRoomTypeController@getHotelRackPrice']);
    // $router->get('/get_max_people/{room_type_id}',['uses'=>'MasterRoomTypeController@getMaxPeople']);
    // $router->post('update_amen/{room_type_id}',['uses'=>'MasterRoomTypeController@updateAmenities']);
    // $router->post('/airbnb-details-add',['uses'=>'AirbnbController@addAirBnbDetails']);
    // $router->post('/airbnb-details-update/{airbnb_details_id}',['uses'=>'AirbnbController@updateAirBnbDetails']);
    // $router->get('/airbnb-data/{hotel_id}/{room_type_id}',['uses'=>'AirbnbController@getAirbnbData']);
    // $router->get('/airbnb-ready-review/{hotel_id}/{room_type_id}',['uses'=>'AirbnbController@updateReviewStatus']);
    // $router->get('/getairbnb-instant-booking/{room_type_id}/{hotel_id}',['uses'=>'AirbnbController@getAirbnbMaxdaystatus']);
    // $router->get('/getairbnb-instant-booking/{airbnb_status}/{room_type_id}/{hotel_id}',['uses'=>'AirbnbController@airbnbInstantBooking']);
    // $router->post('/listing_notification/{hotel_id}/{room_type_id}',['uses'=>'MasterRoomTypeController@updateNotification']);
});



?>
