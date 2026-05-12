<?php

namespace ba;

use think\facade\Log;

class PaymentHelper
{
    public static function generateOrderNo($name): string
    {
        $name = $name??"PAY";
        return $name . date('YmdHis') . mt_rand(1000, 9999);
    }

    public static function sign(array $params, string $key): string
    {
        ksort($params);
        $query = '';

        foreach ($params as $k => $v) {
            if (is_array($v)) {
                $v = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            if ($v !== '' && $v !== null) {
                $query .= $k . '=' . $v . '&';
            }
        }

        $query .= 'key=' . $key;
        return strtoupper(md5($query));
    }


    public static function http_post_json(string $url, array $data, bool $log = false,array $header =[] )
    {
        $jsonData = json_encode($data);
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER , false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST  , 0);
        if (!empty($header)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }else{
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($jsonData)
            ]);
        }

        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);

        curl_close($ch);

        // 记录日志（根据你项目日志系统调整）
        if ($log) {
            // 获取实际使用的headers
            $actualHeaders = !empty($header) ? $header : [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($jsonData)
            ];
            
            $logData = [
                'url' => $url,
                'headers' => $actualHeaders,
                'request' => $data,
                'response' => $response,
                'http_code' => $httpCode,
                'curl_error' => $curlErr,
                'timestamp' => date('Y-m-d H:i:s'),
            ];
            // 这里用简单的文件记录日志，实际项目建议用专门的日志库
            file_put_contents(__DIR__ . '/http_post_json.log', json_encode($logData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);
        }
        if ($response){
            return $response;
        }else{
            return json_encode(['code'=>1]);
        }

    }
}