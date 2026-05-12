<?php

namespace ba;

use think\facade\Log;

class GameHelper
{



    public static function curlGameApi(string $url, array $data = [], string $method = 'GET',int $switch_wallet = 1)
    {
        $startTime = microtime(true); // 请求开始计时

        $fullUrl = rtrim(get_sys_config('game_api_url'), '/') . $url;
$requestId = round(microtime(true) * 1000) . '_' . mt_rand(100000, 999999);
$body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (strtoupper($method) === 'GET') {
            $fullUrl .= '?' . http_build_query($data);
        }

if ($switch_wallet) {
    $appid = get_sys_config('game_appid_re');
    $secret = get_sys_config('game_app_secret_re');
} else {
    $appid = get_sys_config('game_appid');
    $secret = get_sys_config('game_app_secret');
}

$sign = strtolower(md5($requestId . $body . $secret));

$headers = [
    'X-Appid: ' . $appid,
    'X-Request-Id: ' . $requestId,
    'X-Sign: ' . $sign,
    'Content-Type: application/json;charset=UTF-8',
];


        $retry = 0;
        $maxRetries = 3;

        do {
            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL => $fullUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => strtoupper($method),
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_HTTPHEADER => $headers,
            ]);
if (strtoupper($method) === 'POST') {
    curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
}
      

            $response = curl_exec($curl);
            $errno = curl_errno($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            curl_close($curl);

            $duration = round((microtime(true) - $startTime) * 1000, 2); // 毫秒

            // 记录请求耗时日志
Log::record("[GameApi] {$method} {$url} | 耗时: {$duration}ms | 状态码: {$httpCode} | 错误: {$error} | 返回: {$response}", 'info');
            if ($errno === 0 && $httpCode === 200) {
                $json = json_decode($response, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $json;
                } else {
                    return ['code' => 0, 'msg' => '返回数据解析失败：' . json_last_error_msg()];
                }
            }

            if (in_array($errno, [CURLE_OPERATION_TIMEDOUT, CURLE_COULDNT_CONNECT])) {
                $retry++;
                sleep(1);
            } else {
                break;
            }

        } while ($retry < $maxRetries);

        $duration = round((microtime(true) - $startTime) * 1000, 2); // 失败重试后也记录耗时
Log::record("[GameApi] 请求失败：{$method} {$url} | 耗时: {$duration}ms | 错误: {$error} | 状态码: {$httpCode} | 返回: {$response}", 'error');
        return [
            'code' => 0,
            'msg' => "请求失败：{$error}，HTTP状态码：{$httpCode}，尝试次数：{$retry}"
        ];
    }


    //注册用户
    public static  function get_player_id($user_info)
    {
        if(!$user_info){return false;}
        if(empty($user_info['player_id'])){
            //访问创建游戏玩家接口
$player_info = self::curlGameApi(
    '/api/v1/player/create',
    ["UserID" => (string)$user_info['id']],
    'POST',
    (int)$user_info['switch_wallet']
);
if (empty($player_info['data']['pid'])) {
    Log::record('创建游戏玩家失败：' . json_encode($player_info, JSON_UNESCAPED_UNICODE), 'error');
    return false;
}

return $player_info['data']['pid'];
        }
        return $user_info['player_id'];
    }

}