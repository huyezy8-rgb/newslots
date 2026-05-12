<?php

use ba\Filesystem;
use app\admin\library\module\Server;

if (!function_exists('get_account_verification_type')) {

    /**
     * 获取可用的账户验证方式
     * 用于：用户找回密码|用户注册
     * @return string[] email=电子邮件,mobile=手机短信验证
     * @throws Throwable
     */
    function get_account_verification_type(): array
    {
        $types = [];

        // 电子邮件，检查后台系统邮件配置是否全部填写
        $sysMailConfig = get_sys_config('', 'mail');
        $configured    = true;
        foreach ($sysMailConfig as $item) {
            if (!$item) {
                $configured = false;
            }
        }
        if ($configured) {
            $types[] = 'email';
        }

        // 手机号，检查是否安装短信模块
        $sms = Server::getIni(Filesystem::fsFit(root_path() . 'modules/sms/'));
        if ($sms && $sms['state'] == 1) {
            $types[] = 'mobile';
        }

        return $types;
    }

    function generateInviteCode($length = 6) {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $code;
    }


      //发送短信接口
      function sendSmsPost($numbers, $content, $senderId = '', $orderId = '')
      {
          header('content-type:text/html;charset=utf8');
  
          $apiKey = get_sys_config('sms_apikey');
          $apiSecret = get_sys_config('sms_apisecret');
          $appId = get_sys_config('sms_appid');
          $url = "https://api.laaffic.com/v3/sendSms";
          $timeStamp = time();
          $sign = md5($apiKey . $apiSecret . $timeStamp);
  
          $dataArr['appId'] = $appId;
          $dataArr['numbers'] = $numbers;
          $dataArr['content'] = str_replace('xxxx', $content, get_sys_config('sms_content'));
          $dataArr['senderId'] = $senderId;
          $dataArr['orderId'] = $orderId;
  
          $data = json_encode($dataArr);
          $headers = array('Content-Type:application/json;charset=UTF-8', "Sign:$sign", "Timestamp:$timeStamp", "Api-Key:$apiKey");
  
          $ch = curl_init();
          curl_setopt($ch, CURLOPT_URL, $url);
          curl_setopt($ch, CURLOPT_POST, 1);
          curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 600);
          curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
          curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
          curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
          curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
  
          $response = curl_exec($ch);
          curl_close($ch);
  
          return json_decode($response, true);
      }

}