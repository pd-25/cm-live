<?php
//////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///*======================Air Bnb Reservation URl======================================///////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////////

$router->get('/air-bnb-reservations',['uses'=>'AirbnbController@testReservation']);
$router->post('/air-bnb-reservations',['uses'=>'AirbnbController@testMakeReservation']);
$router->put('/air-bnb-reservations/{confirmation_code}',['uses'=>'AirbnbController@testUpdateReservation']);
$router->put('/air-bnb-reservations',['uses'=>'AirbnbController@testUpdateReservation']);
$router->post('/air-bnb-notify',['uses'=>'AirbnbController@getNotification']);
$router->post('/air-bnb/save-code',['uses'=>'AirbnbController@saveAirbnbcode']);
$router->get('/air-bnb/status/{company_id}',['uses'=>'AirbnbController@checkStatusAirbnb']);




$router->post('/airbnb-hotel-listing',['uses'=>'AirbnbHotelandRoomListingController@hotelListingInAirbnb']);
$router->post('/update-airbnb-hotel-listing',['uses'=>'AirbnbHotelandRoomListingController@updateHotelListingInAirbnb']);
$router->post('/add-airbnb-room-listing',['uses'=>'AirbnbHotelandRoomListingController@addRoomDetails']);
$router->post('/update-airbnb-room-listing',['uses'=>'AirbnbHotelandRoomListingController@updateRoomDetails']);
$router->post('/get-airbnb-room-status',['uses'=>'AirbnbHotelandRoomListingController@getRoomAirbnbStatus']);
$router->post('/update-ammenities-status',['uses'=>'AirbnbHotelandRoomListingController@updateAmenities']);