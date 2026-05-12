<?php

namespace app\api\controller;

use ba\Random;
use Throwable;
use ba\Captcha;
use think\Response;
use ba\ClickCaptcha;
use think\facade\Config;
use app\common\facade\Token;
use app\common\controller\Api;
use app\admin\library\Auth as AdminAuth;
use app\common\library\Auth as UserAuth;

class Common extends Api
{
    /**
     * 图形验证码
     * @throws Throwable
     */
    public function captcha(): Response
    {
        $captchaId = $this->request->request('id');
        $config    = array(
            'codeSet'  => '123456789',            // 验证码字符集合
            'fontSize' => 22,                     // 验证码字体大小(px)
            'useCurve' => false,                  // 是否画混淆曲线
            'useNoise' => true,                   // 是否添加杂点
            'length'   => 4,                      // 验证码位数
            'bg'       => array(255, 255, 255),   // 背景颜色
        );

        $captcha = new Captcha($config);
        return $captcha->entry($captchaId);
    }

    /**
     * 点选验证码
     */
    public function clickCaptcha(): void
    {
        $id      = $this->request->request('id/s');
        $captcha = new ClickCaptcha();
        $this->success('', $captcha->creat($id));
    }

    /**
     * 点选验证码检查
     * @throws Throwable
     */
    public function checkClickCaptcha(): void
    {
        $id      = $this->request->post('id/s');
        $info    = $this->request->post('info/s');
        $unset   = $this->request->post('unset/b', false);
        $captcha = new ClickCaptcha();
        if ($captcha->check($id, $info, $unset)) $this->success();
        $this->error();
    }

    /**
     * 刷新 token
     * 无需主动删除原 token，由 token 驱动自行实现过期 token 清理，可避免并发场景下无法获取到过期 token 数据
     */
    public function refreshToken(): void
    {
        $refreshToken = $this->request->post('refreshToken');
        $refreshToken = Token::get($refreshToken);

        if (!$refreshToken || $refreshToken['expire_time'] < time()) {
            $this->error(__('Login expired, please login again'));
        }

        $newToken = Random::uuid();

        // 管理员token刷新
        if ($refreshToken['type'] == AdminAuth::TOKEN_TYPE . '-refresh') {
            Token::set($newToken, AdminAuth::TOKEN_TYPE, $refreshToken['user_id'], (int)Config::get('buildadmin.admin_token_keep_time'));
        }

        // 会员token刷新
        if ($refreshToken['type'] == UserAuth::TOKEN_TYPE . '-refresh') {
            Token::set($newToken, UserAuth::TOKEN_TYPE, $refreshToken['user_id'], (int)Config::get('buildadmin.user_token_keep_time'));
        }

        $this->success('', [
            'type'  => $refreshToken['type'],
            'token' => $newToken
        ]);
    }

    //发送短信接口
    function sendSmsPost($numbers, $content, $senderId = '', $orderId = '')
    {
        header('content-type:text/html;charset=utf8');

        $apiKey = "your api key";
        $apiSecret = "your api secret";
        $appId = "{{appId}}";
        $url = "https://api.laaffic.com/v3/sendSms";
        $timeStamp = time();
        $sign = md5($apiKey . $apiSecret . $timeStamp);

        $dataArr['appId'] = $appId;
        $dataArr['numbers'] = $numbers;
        $dataArr['content'] = "Your verification code is: $content. Please use this code to complete your verification process. Do not share this code with anyone.";
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
