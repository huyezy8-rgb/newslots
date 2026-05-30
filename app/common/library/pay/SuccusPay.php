<?php
declare(strict_types=1);

namespace app\common\library\pay;

use app\common\model\payment\Channels;
use ba\PaymentHelper;
use think\facade\Db;
use think\Request;

class SuccusPay extends Driver
{
    protected string $mchNo;
    protected string $key;
    protected string $api;

    public function __construct()
    {
        $this->getConfig();
    }
    public function getConfig(): bool
    {
        $config = Channels::where(['code'=>"Succus", 'status'=>1])->value('config');
        if(empty($config)){
            throw new \Exception('支付渠道未开启');

        }
        $this->mchNo = $config['mchNo'];
        $this->key   = $config['key'];
        $this->api   = $config['api_url'];
        return true;
    }

    public function createOrder(string $orderNo, float $amount, array $extra = []): array
    {
        $base_url    = request()->domain(); // 获取当前站点域名，如：https://www.xxx.com
        $return_url  = $extra['return_url'] ?? '';
        $notify_url  = $base_url . '/index.php/api/notify/cashapp';
        $amount = intval(bcmul((string)$amount, '100', 0)); // 元转分
        $wayCode = $extra['wayCode'] ?? 'cashapp';

        $paramArray = [
            "mchNo"       => $this->mchNo,
            "mchOrderNo"  => $orderNo,
            "wayCode"     => $wayCode,
            "amount"      => $amount,
            "currency"    => 'usd',
            "clientIp"    => $extra['ip'] ?? '127.0.0.1',
            "returnUrl"   => $return_url,
            "notifyUrl"   => $notify_url,
            "expiredTime" => $extra['expiredTime']??'1800',
            "extParam"    => '',
            "timestamp"   => intval(microtime(true) * 1000),
            "signType"    => 'MD5',
            "wayParam"    => [
                "clientId" => $extra['id'] ?? '0',
            ],
        ];

        $paramArray['sign'] = PaymentHelper::sign($paramArray, $this->key);

        $response = PaymentHelper::http_post_json($this->api.'/pay/create', $paramArray,true);
        $result = json_decode($response, true);
        if (!$result || $result['code'] !== 0) {
            throw new \Exception('支付下单失败: ' . ($result['msg'] ?? '未知错误'));
        }
        return $result;
    }

    public function createTransfer(string $orderNo, float $amount, array $extra = []): array
    {
        $base_url    = request()->domain(); // 获取当前站点域名，如：https://www.xxx.com
        $notify_url  = $base_url . '/index.php/api/notify/cashapp';
        $wayCode = $extra['wayCode'] ?? 'ecashapp';
        $wayParam = $extra['wayParam'] ?? [];
        $amount = intval(bcmul((string)$amount, '100', 0)); // 元转分
        $paramArray = [
            "currency"   => "usd",
            "mchNo"      => $this->mchNo,
            "mchOrderNo" => $orderNo,
            "signType"   => "MD5",
            "timestamp"  => round(microtime(true) * 1000),
            "clientIp"   => $_SERVER['SERVER_ADDR'] ?? '127.0.0.1',
            "notifyUrl"  => $notify_url, // 配置里读取回调地址
            "amount"     => $amount,
            "wayCode"    => $wayCode,
            "wayParam"   => $wayParam,
        ];

        $paramArray['sign'] = PaymentHelper::sign($paramArray, $this->key);

        $response = PaymentHelper::http_post_json($this->api.'/transfer/create', $paramArray,true);
        $result = json_decode($response, true);
        if (!$result || $result['code'] !== 0) {
            throw new \Exception('代付下单失败: ' . ($result['msg'] ?? '未知错误'));
        }
        return $result;
    }

    public function close($orderNo): array
    {
        $paramArray = [
            "mchNo"      => $this->mchNo,
            "mchOrderNo" => $orderNo,
            "signType"   => "MD5",
            "timestamp"  => round(microtime(true) * 1000),
        ];
        $paramArray['sign'] = PaymentHelper::sign($paramArray, $this->key);



        $response = PaymentHelper::http_post_json($this->api.'/pay/close', $paramArray);
        $result = json_decode($response, true);
        if (!$result || $result['code'] !== 0) {
            throw new \Exception('关闭订单失败: ' . ($result['msg'] ?? '未知错误'));
        }

        return $result['data'];

    }

    public function notify(Request $request): bool
    {
        $data = $request->post();

        // 验签
        if (!$this->verifySign($data, $this->key)) {
            return false;
        }

        return true;
    }
}
