<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

//获取用户手机号
Route::post('api/getPhoneNumber','Api\UserApiController@test');

//用户手机号入库
Route::post('api/userPhoneNumberAdd','Api\userPhoneNumberAdd@test');

//获取accessToken
Route::get('api/accessToken','Api\UserApiController@accessToken');

//用户登录
Route::post('api/weChat','Api\UserApiController@weChat');

//喜欢
Route::post('api/userLike','Api\UserApiController@userLike');

//用户个人信息
Route::post('api/userInfo','Api\UserApiController@userInfo');

//添加(删除)喜欢
Route::post('api/userLikeAdd','Api\UserApiController@userLikeAdd');

//修改个人信息
Route::post('api/updateUserInfo','Api\UserApiController@updateUserInfo');

//上传图片
Route::post('api/upload','Api\UserApiController@upload');

//首页
Route::post('api/index','Api\UserApiController@index');

//搜索
Route::post('api/search','Api\UserApiController@search');

