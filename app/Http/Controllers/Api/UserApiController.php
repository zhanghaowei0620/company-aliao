<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use App\Model\UserModel;
use Illuminate\Support\Str;

class UserApiController extends Controller
{
    //获取accessToken
    public function accessToken(){
        $access = Cache('access');
        if (empty($access)) {
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . env('WX_APP_ID') . "&secret=" . env('WX_KEY') . "";
            $info = file_get_contents($url);
            $arrInfo = json_decode($info, true);
            $key = "access";
            $access = $arrInfo['access_token'];
            $time = $arrInfo['expires_in'];

            cache([$key => $access], $time);
        }
        return $access;
    }
 
    //微信登录
    public function weChat(Request $request){
        $code = $request->input('code');
        $lat1 = $request->input('lat');//纬度
        $lng1 = $request->input('lng');//经度
        $userinfo = $request->input('userinfo');
        //var_dump(json_decode($userinfo));exit;
        $userinfo = json_decode($userinfo);
        $wx_name = $userinfo->nickName;
        $wx_headimg = $userinfo->avatarUrl;
        //$code = 'dada4d6a54d6a';
        $url = "https://api.weixin.qq.com/sns/jscode2session?appid=" . env('WX_APP_ID') . "&secret=" . env('WX_KEY') . "&js_code=$code&grant_type=authorization_code";
        $info = file_get_contents($url);
        $arr = json_decode($info, true);
        //var_dump($arr['unionid']);exit;
        $ac_userInfo = DB::table('ac_user')->where('wx_openid', $arr['openid'])->first();
        if($ac_userInfo){
            $update = [
                'wx_login_time' => time(),
                'lat' => $lat1,
                'lng' => $lng1,
                'update_time' => time()
            ];
            $updateInfo = DB::table('ac_user')->where('wx_openid', $arr['openid'])->update($update);
            if ($updateInfo) {
                $data = [
                    'wx_openid' => $arr['openid'],
                    'session_key' => $arr['session_key']
                ];
                if ($arr['openid'] && $arr['session_key']) {

                    $key = $arr['openid'];
                    Redis::set($key, $arr['openid']);
                    //$openid = Redis::get($key);
                    //var_dump($openid);exit;
                    $data1=[
                        'code' => '0',
                        'msg' => '登录成功',
                        'data' => $data
                    ];
                    $response = [
                        'data' => $data1
                    ];
                    return json_encode($response, JSON_UNESCAPED_UNICODE);
                } else {
                    $response = [
                        'code' => '2',
                        'msg' => '微信授权失败，请检查你的网络'
                    ];
                    die(json_encode($response, JSON_UNESCAPED_UNICODE));
                }
            } else {
                $response = [
                    'code' => '1',
                    'msg' => '登陆失败'
                ];
                die(json_encode($response, JSON_UNESCAPED_UNICODE));
            }
        }else{
            $ac_UserInfo = [
                'wx_name' => $wx_name,
                'wx_headimg' => $wx_headimg,
                'wx_openid' => $arr['openid'],
                'session_key' => $arr['session_key'],
                //'wx_unionid'=>$arr['unionid'],
                'wx_login_time' => time(),
                'wx_create_time' => time()
            ];
            $insertUserInfo = DB::table('ac_user')->insert($ac_UserInfo);
            if ($insertUserInfo) {
                $data = [
                    'wx_openid' => $arr['openid'],
                    'session_key' => $arr['session_key']
                ];
                if ($arr['openid'] && $arr['session_key']) {

                    $key = $arr['openid'];
                    Redis::set($key, $arr['openid']);
                    //$openid = Redis::get($key);
                    //var_dump($openid);exit;
                    $data1=[
                        'code' => '0',
                        'msg' => '注册成功',
                        'data' => $data
                    ];
                    $response = [
                        'data' => $data1
                    ];
                    return json_encode($response, JSON_UNESCAPED_UNICODE);
                } else {
                    $response = [
                        'code' => '2',
                        'msg' => '微信授权失败，请检查你的网络'
                    ];
                    die(json_encode($response, JSON_UNESCAPED_UNICODE));
                }
            } else {
                $response = [
                    'code' => '1',
                    'msg' => '系统出现错误,请稍后重试'
                ];
                die(json_encode($response, JSON_UNESCAPED_UNICODE));
            }
        }
    
    }

    //用户个人信息
    public function userInfo(Request $request){
        $openid1 = $request->input('openid');
        // $openid = "o3Z-K1maBTHyvdZJh0hmciOG-dF0";
        $openid = Redis::get($openid1);
        //var_dump($openid);exit;
        if ($openid) {
            $userInfo = DB::table('ac_user')
                ->where('wx_openid', $openid)->get()->toArray();
            var_dump($userInfo);exit;
            $response = [
                'userInfo' => $userInfo,
                'code' => 0
            ];
            return json_encode($response, JSON_UNESCAPED_UNICODE);
        } else {
            $response = [
                'code' => 1,
                'msg' => '请先登录'
            ];
            die(json_encode($response, JSON_UNESCAPED_UNICODE));
        }

    }

    //喜欢
    public function userLike(Request $request){
        $openid1 = $request->input('openid');
        $openid = Redis::get($openid1);
        $page = $request->input('page');
        $page_num = $request->input('page_num');
        $lat1 = $request->input('lat');//纬度
        $lng1 = $request->input('lng');//经度
        $is_like = $request->input('is_like');  //0 我喜欢的  1 喜欢我的
        
        if($is_like){
            $is_like = $is_like;
        }else{
            $is_like = 0;
        }
        if($openid){
            $userInfo = Db::table('ac_user')->where('wx_openid',$openid)->first();
            if($is_like == 0){
                $user_id = $userInfo->user_id;
                $userLikeInfo =  DB::select("SELECT *, 6378.138*2*ASIN(SQRT(POW(SIN(($lat1*PI()/180-lat*PI()/180)/2),2)+COS($lat1*PI()/180)*COS(lat*PI()/180)*POW(SIN(($lng1*PI()/180-lng*PI()/180)/2),2))) AS juli  from ac_user inner join ac_userlike on ac_user.user_id = ac_userlike.user_like_id where ac_userlike.user_id = $user_id order by juli");   //  order by juli limit $page,$page_num
                foreach($userLikeInfo as $k=>$v){
                    // var_dump($v->user_id);die;
                    $is_likeInfo = Db::table('ac_userlike')->where('user_id',$userInfo->user_id)->where('user_like_id',$v->user_id)->first();
                    if($is_likeInfo){
                        $is_like_mutually = Db::table('ac_userlike')->where('user_like_id',$userInfo->user_id)->where('user_id',$v->user_id)->first();
                        if($is_like_mutually){
                            $is_like = 2;
                        }else{
                            $is_like = 1;
                        }
                    }else{
                        $is_like = 0;     //0不喜欢  1喜欢 2相互喜欢
                    }
                    $v->is_like = $is_like;
                }
                $response = [
                    'code' => 0,
                    'data' => $userLikeInfo,
                    'msg' => '获取数据成功'
                ];
                return json_encode($response, JSON_UNESCAPED_UNICODE);
            }else{
                $user_id = $userInfo->user_id;
                $likeUserInfo =  DB::select("SELECT *, 6378.138*2*ASIN(SQRT(POW(SIN(($lat1*PI()/180-lat*PI()/180)/2),2)+COS($lat1*PI()/180)*COS(lat*PI()/180)*POW(SIN(($lng1*PI()/180-lng*PI()/180)/2),2))) AS juli  from ac_user inner join ac_userlike on ac_user.user_id = ac_userlike.user_id  where ac_userlike.user_like_id = $user_id order by juli");  //limit $page,$page_num
                foreach($userLikeInfo as $k=>$v){
                    // var_dump($v->user_id);die;
                    $is_likeInfo = Db::table('ac_userlike')->where('user_id',$userInfo->user_id)->where('user_like_id',$v->user_id)->first();
                    if($is_likeInfo){
                        $is_like_mutually = Db::table('ac_userlike')->where('user_like_id',$userInfo->user_id)->where('user_id',$v->user_id)->first();
                        if($is_like_mutually){
                            $is_like = 2;
                        }else{
                            $is_like = 1;
                        }
                    }else{
                        $is_like = 0;     //0不喜欢  1喜欢 2相互喜欢
                    }
                    $v->is_like = $is_like;
                }
                $response = [
                    'code' => 0,
                    'data' => $likeUserInfo,
                    'msg' => '获取数据成功'
                ];
                return json_encode($response, JSON_UNESCAPED_UNICODE);
            }
        }else{
            $response = [
                'code' => 1,
                'msg' => '请先登录'
            ];
            die(json_encode($response, JSON_UNESCAPED_UNICODE));
        }
       
        
    }

    //喜欢-添加(删除)
    public function userLikeAdd(Request $request){
        $openid1 = $request->input('openid');
        $openid = Redis::get($openid1);
        $like_user_id = $request->input('user_id'); //喜欢用户的id
        $is_like = $request->input('is_like'); //0喜欢 1取消喜欢
        $userInfo = Db::table('ac_user')->where('wx_openid',$openid)->first();
        if($openid){
            if($is_like == 0){
                $where = [
                    'user_id' => $like_user_id,
                    'like_user_id' => $userInfo->user_id
                ];
                $data = [
                    'user_id' => $userInfo->user_id,
                    'user_like_id' => $like_user_id,
                    'create_time' => time()
                ];
                $isertInfo = Db::table('ac_userlike')->insert($data);
                if($isertInfo){
                    $response = [
                        'code' => 0,
                        'msg' => '已成功添加至您的喜欢'
                    ];
                    return json_encode($response, JSON_UNESCAPED_UNICODE);
                }else{
                    $response = [
                        'code' => 2,
                        'msg' => '系统出现错误,请稍后重试'
                    ];
                    die(json_encode($response, JSON_UNESCAPED_UNICODE));
                }
            }else{
                $deleteInfo = Db::table('ac_userlike')->where('user_id',$userInfo->user_id)->where('user_like_id',$like_user_id)->delete();
                if($deleteInfo){
                    $response = [
                        'code' => 0,
                        'msg' => '已取消喜欢'
                    ];
                    return json_encode($response, JSON_UNESCAPED_UNICODE);
                }else{
                    $response = [
                        'code' => 3,
                        'msg' => '系统出现错误,请稍后重试'
                    ];
                    die(json_encode($response, JSON_UNESCAPED_UNICODE));
                }
            }
        }else{
            $response = [
                'code' => 1,
                'msg' => '请先登录'
            ];
            die(json_encode($response, JSON_UNESCAPED_UNICODE));
        }
    }

    //修改个人信息
    public function updateUserInfo(){
        $openid1 = $request->input('openid');
        $openid = Redis::get($openid1);
        if($openid){
            $head_images = $request->input('head_images'); //头像
            $desc = $request->input('head_images');  //个人简介
            $company = $request->input('head_images');  //公司
            $position = $request->input('position');  //职位
            $driving_images = $request->input('driving_images'); //行驶证照片
            if($driving_images){
                $is_car = 1;
            }else{
                $is_car = 0;
            }
            $user_name = $request->input('user_name'); //用户名称
            $sex = $request->input('sex'); //用户性别
            $user_birthday = $request->input('user_birthday'); //用户生日
            $userInfo = Db::table('ac_user')->where('wx_openid',$openid)->first();
            $updateDate = [
                'user_name' => $user_name,
                'sex' => $sex,
                'user_birthday' => $user_birthday,
                'head_images' => $head_images,
                'desc' => $desc,
                'company' => $company,
                'position' => $position,
                'driving_images' => $driving_images,
                'is_car' => $is_car,
                'update_time' => time()
            ];
            $updateInfo = Db::table('ac_user')->where('user_id',$userInfo->user_id)->update($updateDate);
            if($updateInfo){
                $response = [
                    'code' => 0,
                    'msg' => '修改成功'
                ];
                return json_encode($response, JSON_UNESCAPED_UNICODE);
            }else{
                $response = [
                    'code' => 2,
                    'msg' => '系统出现错误,请稍后重试'
                ];
                die(json_encode($response, JSON_UNESCAPED_UNICODE));
            }
        }else{
            $response = [
                'code' => 1,
                'msg' => '请先登录'
            ];
            die(json_encode($response, JSON_UNESCAPED_UNICODE));
        }
    }

    //上传图片
    public function upload(Request $request){
        if (!empty($_FILES)) {
            //获取扩展名
            $file = json_encode($_FILES);
            $fileName = [];
            for ($i = 0; $i < count($_FILES); $i++) {
                $fileName[$i] = 'images' . $i;
            }
            $exename = $_FILES['file']['type'];
            if ($exename != 'image/png' && $exename != 'image/jpg' && $exename != 'image/gif' && $exename != 'image/jpeg') {
                exit('不允许的扩展名');
            }
            //此处地址根据项目而定，唯一注意的就是图片命名，这里难得去获取后缀，随便写了个png
            $http_type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';
            //$website = $http_type . $_SERVER['HTTP_HOST'];
            if (!is_dir(public_path() . '/images')) mkdir(public_path() . '/images', 0777, true);
            $imageSavePath = '/images' . '/' . uniqid() . rand(1, 100) . '.jpg';
            $uploaded = move_uploaded_file($_FILES['file']['tmp_name'], public_path() . $imageSavePath);
            if ($uploaded) {
                $path1=[
                  'path'=>$imageSavePath
                ];
                $response=[
                    'code'=>0,
                    'data'=>$path1,
                    'msg'=>'上传成功'
                ];
                return (json_encode($response,JSON_UNESCAPED_UNICODE));
            } else {
                $response=[
                    'code'=>1,
                    'msg'=>'上传失败'
                ];
                return (json_encode($response,JSON_UNESCAPED_UNICODE));
            }
        } else {
            echo 2;
        }

    }

    //首页
    public function index(Request $request){
        $openid1 = $request->input('openid');
        $lat1 = $request->input('lat');//纬度
        $lng1 = $request->input('lng');//经度
        $page = $request->input('page');//当前页数
        $page_num = $request->input('page_num');//每页展示条数
        $openid = Redis::get($openid1);
        // $lng1 = '112.606565';
        // $lat1= '37.69946';
        // $openid = "o3Z-K1maBTHyvdZJh0hmciOG-dF0";
        $search_time = $request->input('search_time');  //0时间查询  1距离排序
        $search_time = 0;
        if($search_time == 0){
            $orderBy = 'juli';
        }elseif($search_time == 1){
            $orderBy = 'wx_login_time';
        }else{
            $orderBy = 'wx_login_time';
        }
        $search_age = $request->input('search_age');    //年龄查询
        // $search_age = '20-25';
        $search_age = explode('-',$search_age);
        $big = $search_age[1];
        $little = $search_age[0];
        // var_dump($search_age);
        // var_dump($little);
        // var_dump($big);die;
        $search_is_car = $request->input('search_is_car');  //是否有车 0为否 1为是
        // $search_is_car = 1;
        if($openid){
            $userInfo = Db::table('ac_user')->where('wx_openid',$openid)->first();
            if($userInfo->sex == 1){
                $sex = 2;
            }else{
                $sex = 1;
            }
            // $page = 2;
            // $page_num = 10;
            // var_dump($sex);die;
            
            if($search_time || $search_age || $search_is_car){
                //搜索条件 全选
                $indexuserInfo =  DB::select("SELECT *, 6378.138*2*ASIN(SQRT(POW(SIN(($lat1*PI()/180-lat*PI()/180)/2),2)+COS($lat1*PI()/180)*COS(lat*PI()/180)*POW(SIN(($lng1*PI()/180-lng*PI()/180)/2),2))) AS juli  from ac_user where user_age BETWEEN $little AND $big and sex = $sex and is_car = $search_is_car  order by $orderBy limit $page,$page_num");  //
                foreach($indexuserInfo as $k=>$v){
                    // var_dump($v->user_id);die;
                    $is_likeInfo = Db::table('ac_userlike')->where('user_id',$userInfo->user_id)->where('user_like_id',$v->user_id)->first();
                    if($is_likeInfo){
                        $is_like_mutually = Db::table('ac_userlike')->where('user_like_id',$userInfo->user_id)->where('user_id',$v->user_id)->first();
                        if($is_like_mutually){
                            $is_like = 2;
                        }else{
                            $is_like = 1;
                        }
                    }else{
                        $is_like = 0;     //0不喜欢  1喜欢 2相互喜欢
                    }
                    $v->is_like = $is_like;
                }
                // die;
                // var_dump($indexuserInfo);die;
            }elseif($search_time || $search_age){ 
                //搜索条件 时间 年龄
                $indexuserInfo =  DB::select("SELECT *, 6378.138*2*ASIN(SQRT(POW(SIN(($lat1*PI()/180-lat*PI()/180)/2),2)+COS($lat1*PI()/180)*COS(lat*PI()/180)*POW(SIN(($lng1*PI()/180-lng*PI()/180)/2),2))) AS juli  from ac_userwhere user_age BETWEEN $little AND $big and sex = $sex order by $orderBy limit $page,$page_num");
                foreach($indexuserInfo as $k=>$v){
                    // var_dump($v->user_id);die;
                    $is_likeInfo = Db::table('ac_userlike')->where('user_id',$userInfo->user_id)->where('user_like_id',$v->user_id)->first();
                    if($is_likeInfo){
                        $is_like_mutually = Db::table('ac_userlike')->where('user_like_id',$userInfo->user_id)->where('user_id',$v->user_id)->first();
                        if($is_like_mutually){
                            $is_like = 2;
                        }else{
                            $is_like = 1;
                        }
                    }else{
                        $is_like = 0;     //0不喜欢  1喜欢 2相互喜欢
                    }
                    $v->is_like = $is_like;
                }
            }elseif($search_age || $search_is_car){
                //搜索条件 年龄 是否有车
                $indexuserInfo =  DB::select("SELECT *, 6378.138*2*ASIN(SQRT(POW(SIN(($lat1*PI()/180-lat*PI()/180)/2),2)+COS($lat1*PI()/180)*COS(lat*PI()/180)*POW(SIN(($lng1*PI()/180-lng*PI()/180)/2),2))) AS juli  from ac_user where user_age BETWEEN $little AND $big and is_car = $search_is_car and sex = $sex order by $orderBy limit $page,$page_num");
                foreach($indexuserInfo as $k=>$v){
                    // var_dump($v->user_id);die;
                    $is_likeInfo = Db::table('ac_userlike')->where('user_id',$userInfo->user_id)->where('user_like_id',$v->user_id)->first();
                    if($is_likeInfo){
                        $is_like_mutually = Db::table('ac_userlike')->where('user_like_id',$userInfo->user_id)->where('user_id',$v->user_id)->first();
                        if($is_like_mutually){
                            $is_like = 2;
                        }else{
                            $is_like = 1;
                        }
                    }else{
                        $is_like = 0;     //0不喜欢  1喜欢 2相互喜欢
                    }
                    $v->is_like = $is_like;
                }
            }elseif($search_time || $search_is_car){
                //搜索条件 时间 是否有车
                $indexuserInfo =  DB::select("SELECT *, 6378.138*2*ASIN(SQRT(POW(SIN(($lat1*PI()/180-lat*PI()/180)/2),2)+COS($lat1*PI()/180)*COS(lat*PI()/180)*POW(SIN(($lng1*PI()/180-lng*PI()/180)/2),2))) AS juli  from ac_user where is_car = $search_is_car and sex = $sex order by $orderBy limit $page,$page_num");
                foreach($indexuserInfo as $k=>$v){
                    // var_dump($v->user_id);die;
                    $is_likeInfo = Db::table('ac_userlike')->where('user_id',$userInfo->user_id)->where('user_like_id',$v->user_id)->first();
                    if($is_likeInfo){
                        $is_like_mutually = Db::table('ac_userlike')->where('user_like_id',$userInfo->user_id)->where('user_id',$v->user_id)->first();
                        if($is_like_mutually){
                            $is_like = 2;
                        }else{
                            $is_like = 1;
                        }
                    }else{
                        $is_like = 0;     //0不喜欢  1喜欢 2相互喜欢
                    }
                    $v->is_like = $is_like;
                }
            }elseif($search_time){
                //搜索条件 时间
                $indexuserInfo =  DB::select("SELECT *, 6378.138*2*ASIN(SQRT(POW(SIN(($lat1*PI()/180-lat*PI()/180)/2),2)+COS($lat1*PI()/180)*COS(lat*PI()/180)*POW(SIN(($lng1*PI()/180-lng*PI()/180)/2),2))) AS juli  from ac_user where sex = $sex order by $orderBy limit $page,$page_num");
                foreach($indexuserInfo as $k=>$v){
                    // var_dump($v->user_id);die;
                    $is_likeInfo = Db::table('ac_userlike')->where('user_id',$userInfo->user_id)->where('user_like_id',$v->user_id)->first();
                    if($is_likeInfo){
                        $is_like_mutually = Db::table('ac_userlike')->where('user_like_id',$userInfo->user_id)->where('user_id',$v->user_id)->first();
                        if($is_like_mutually){
                            $is_like = 2;
                        }else{
                            $is_like = 1;
                        }
                    }else{
                        $is_like = 0;     //0不喜欢  1喜欢 2相互喜欢
                    }
                    $v->is_like = $is_like;
                }
            }elseif($search_age){
                //搜索条件 年龄
                $indexuserInfo =  DB::select("SELECT *, 6378.138*2*ASIN(SQRT(POW(SIN(($lat1*PI()/180-lat*PI()/180)/2),2)+COS($lat1*PI()/180)*COS(lat*PI()/180)*POW(SIN(($lng1*PI()/180-lng*PI()/180)/2),2))) AS juli  from ac_user where user_age BETWEEN $little AND and sex = $sex order by $orderBy limit $page,$page_num");
                foreach($indexuserInfo as $k=>$v){
                    // var_dump($v->user_id);die;
                    $is_likeInfo = Db::table('ac_userlike')->where('user_id',$userInfo->user_id)->where('user_like_id',$v->user_id)->first();
                    if($is_likeInfo){
                        $is_like_mutually = Db::table('ac_userlike')->where('user_like_id',$userInfo->user_id)->where('user_id',$v->user_id)->first();
                        if($is_like_mutually){
                            $is_like = 2;
                        }else{
                            $is_like = 1;
                        }
                    }else{
                        $is_like = 0;     //0不喜欢  1喜欢 2相互喜欢
                    }
                    $v->is_like = $is_like;
                }
            }elseif($search_is_car){
                //搜索条件 是否有车
                $indexuserInfo =  DB::select("SELECT *, 6378.138*2*ASIN(SQRT(POW(SIN(($lat1*PI()/180-lat*PI()/180)/2),2)+COS($lat1*PI()/180)*COS(lat*PI()/180)*POW(SIN(($lng1*PI()/180-lng*PI()/180)/2),2))) AS juli  from ac_user where is_car = $search_is_car and sex = $sex order by $orderBy limit $page,$page_num");
                foreach($indexuserInfo as $k=>$v){
                    // var_dump($v->user_id);die;
                    $is_likeInfo = Db::table('ac_userlike')->where('user_id',$userInfo->user_id)->where('user_like_id',$v->user_id)->first();
                    if($is_likeInfo){
                        $is_like_mutually = Db::table('ac_userlike')->where('user_like_id',$userInfo->user_id)->where('user_id',$v->user_id)->first();
                        if($is_like_mutually){
                            $is_like = 2;
                        }else{
                            $is_like = 1;
                        }
                    }else{
                        $is_like = 0;     //0不喜欢  1喜欢 2相互喜欢
                    }
                    $v->is_like = $is_like;
                }
            }else{
                //无搜索条件
                $indexuserInfo =  DB::select("SELECT *, 6378.138*2*ASIN(SQRT(POW(SIN(($lat1*PI()/180-lat*PI()/180)/2),2)+COS($lat1*PI()/180)*COS(lat*PI()/180)*POW(SIN(($lng1*PI()/180-lng*PI()/180)/2),2))) AS juli  from ac_user where sex = $sex order by $orderBy limit $page,$page_num");
                foreach($indexuserInfo as $k=>$v){
                    // var_dump($v->user_id);die;
                    $is_likeInfo = Db::table('ac_userlike')->where('user_id',$userInfo->user_id)->where('user_like_id',$v->user_id)->first();
                    if($is_likeInfo){
                        $is_like_mutually = Db::table('ac_userlike')->where('user_like_id',$userInfo->user_id)->where('user_id',$v->user_id)->first();
                        if($is_like_mutually){
                            $is_like = 2;
                        }else{
                            $is_like = 1;
                        }
                    }else{
                        $is_like = 0;     //0不喜欢  1喜欢 2相互喜欢
                    }
                    $v->is_like = $is_like;
                }
            }
    
            $response = [
                'code' => 0,
                'data' => $indexuserInfo,
                'msg' => '获取数据成功'
            ];
            return json_encode($response, JSON_UNESCAPED_UNICODE);
        }else{
            $response = [
                'code' => 1,
                'msg' => '请先登录'
            ];
            die(json_encode($response, JSON_UNESCAPED_UNICODE));
        }
        
        

    }

    //搜索
    public function search(Request $request){
        $openid1 = $request->input('openid');
        $openid = Redis::get($openid1);
        $search = $request->input('search');
        $lng1 = $request->input('lng1');
        $lat1 = $request->input('lat1');
        $page = $request->input('page');
        $page_num = $request->input('page_num');
        // $search = "阿里巴巴";
        // $lng1 = '112.606565';
        // $lat1= '37.69946';
        // $page = 1;
        // $page_num = 10;
        // $openid = "o3Z-K1maBTHyvdZJh0hmciOG-dF0";
        $userInfo = Db::table('ac_user')->where('wx_openid',$openid)->first();
        $searchInfo =  DB::select("SELECT *, 6378.138*2*ASIN(SQRT(POW(SIN(($lat1*PI()/180-lat*PI()/180)/2),2)+COS($lat1*PI()/180)*COS(lat*PI()/180)*POW(SIN(($lng1*PI()/180-lng*PI()/180)/2),2))) AS juli  from ac_user where user_name like '%$search%' OR company like '%$search%' order by juli limit $page,$page_num");
        foreach($searchInfo as $k=>$v){
            // var_dump($v->user_id);die;
            $is_likeInfo = Db::table('ac_userlike')->where('user_id',$userInfo->user_id)->where('user_like_id',$v->user_id)->first();
            if($is_likeInfo){
                $is_like_mutually = Db::table('ac_userlike')->where('user_like_id',$userInfo->user_id)->where('user_id',$v->user_id)->first();
                if($is_like_mutually){
                    $is_like = 2;
                }else{
                    $is_like = 1;
                }
            }else{
                $is_like = 0;     //0不喜欢  1喜欢 2相互喜欢
            }
            $v->is_like = $is_like;
        }
 
        $response = [
            'code' => 0,
            'data' => $searchInfo,
            'msg' => '获取数据成功'
        ];
        return json_encode($response, JSON_UNESCAPED_UNICODE);
    }


    // public function test(){
    //     $a = Redis::flushAll();
    //     var_dump($a);
    // }


















    /**base64加密*/
    public function base64(){
        $arr = "hello 王瘪犊子";
        $base64_data = base64_encode($arr);
        var_dump($base64_data);
    }
    /**base64解密*/
    public function testBase64(Request $request){
        $base64 = $request->input('b64');
        $base64_str = base64_decode($base64,JSON_UNESCAPED_UNICODE);
        var_dump($base64_str);
    }
}







