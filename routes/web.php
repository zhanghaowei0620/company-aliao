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

Route::get('test','Api\UserApiController@test');


//获取accessToken
Route::get('api/accessToken','Api\UserApiController@accessToken');

//用户登录
Route::get('api/weChat','Api\UserApiController@weChat');

//喜欢
Route::get('api/userLike','Api\UserApiController@userLike');

//用户个人信息
Route::get('api/userInfo','Api\UserApiController@userInfo');

//添加(删除)喜欢
Route::get('api/userLikeAdd','Api\UserApiController@userLikeAdd');

//修改个人信息
Route::get('api/updateUserInfo','Api\UserApiController@updateUserInfo');

//上传图片
Route::get('api/upload','Api\UserApiController@upload');

//首页
Route::get('api/index','Api\UserApiController@index');

//搜索
Route::get('api/search','Api\UserApiController@search');

