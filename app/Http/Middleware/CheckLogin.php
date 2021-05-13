<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Redis;

class CheckLogin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $token = $request->input('token');
        $uid = $request->input('uid');


        if(empty($token) || empty($uid)){
            $response = [
                'error'=>40003,
                'msg'=>'未授权'
            ];
            die(json_encode($response,JSON_UNESCAPED_UNICODE));
        }
        $ip = $_SERVER['SERVER_ADDR'];
        $key = 'user_request'.$ip;
        Redis::incr($key);
        Redis::expire($key,60);
        $num = Redis::get($key);
        //var_dump($num);exit;

        if($num>10){
            $response = [
                'error'=>40006,
                'msg'=>'请求超过限制'
            ];
            die(json_encode($response,JSON_UNESCAPED_UNICODE));
        }else{
            $user_key = "H:user_login";
            $res = Redis::lrange($user_key,0,-1);
            $key_id = $res[0];
            $userInfo = Redis::hgetall($key_id);
            $user_token = $userInfo['token'];
            if(empty($user_token)){
                $response = [
                    'error'=>40004,
                    'msg'=>'请先登陆获取授权'
                ];
                die(json_encode($response,JSON_UNESCAPED_UNICODE));
            }
            if($token != $user_token){
                $response = [
                    'error'=>40005,
                    'msg'=>'无效的taoken值'
                ];
                die(json_encode($response,JSON_UNESCAPED_UNICODE));
            }else{
                $response = [
                    'error'=>0,
                    'msg'=>'ok'
                ];
                die(json_encode($response,JSON_UNESCAPED_UNICODE));
            }
        }
        //var_dump();die;
        return $next($request);
    }
}
