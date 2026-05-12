<?php
declare(strict_types=1);

namespace app\common\library\pay;

use app\common\model\payment\Channels;
use ba\PaymentHelper;
use think\facade\Db;
use think\Request;

/**
 * FiatPay 充值渠道
 * 支持代收/代付功能的REST API
 */
class FiatPay extends Driver
{

    protected string $secretKey;
    protected string $apiUrl;
    protected string $email;
    protected string $password;
    protected ?string $bearerToken = null;

    public function __construct()
    {
        $this->getConfig();
    }

    /**
     * 获取配置信息
     */
    public function getConfig(): bool
    {
        $config = Channels::where(['code' => "Fiat", 'status' => 1])->value('config');
        if (empty($config)) {
            throw new \Exception('FiatPay支付渠道未开启');
        }


        $this->secretKey = $config['secret_key'] ?? '';
        $this->apiUrl = $config['api_url'] ?? '';
        $this->email = $config['email'] ?? '';
        $this->password = $config['password'] ?? '';

        if (empty($this->secretKey) || empty($this->apiUrl)) {
            throw new \Exception('FiatPay配置不完整');
        }

        return true;
    }

    /**
     * 获取Bearer Token
     */
    protected function getBearerToken(): string
    {
        if ($this->bearerToken) {
            return $this->bearerToken;
        }

        $loginData = [
            'email' => $this->email,
            'password' => $this->password
        ];

        // 确保URL格式正确，避免双斜杠
        $url = rtrim($this->apiUrl, '/') . '/api/login';
        $response = PaymentHelper::http_post_json($url, $loginData, true);
        $result = json_decode($response, true);

        if (!$result || !isset($result['access_token'])) {
            throw new \Exception('获取Bearer Token失败:' . ($result['message'] ?? '未知错误'));
        }

        $this->bearerToken = $result['access_token'];
        return $this->bearerToken;
    }

    /**
     * 支持的货币映射
     */
    protected function getSupportedCurrencies(): array
    {
        return [
            'USD' => 'USD',
            'CNY' => 'CNY',
            'EUR' => 'EUR',
            'GBP' => 'GBP',
            'JPY' => 'JPY',
            'AUD' => 'AUD',
            'CAD' => 'CAD',
            'CHF' => 'CHF',
            'HKD' => 'HKD',
            'SGD' => 'SGD',
            'THB' => 'THB',
            'MYR' => 'MYR',
            'IDR' => 'IDR',
            'VND' => 'VND',
            'PHP' => 'PHP',
            'KRW' => 'KRW',
            'INR' => 'INR'
        ];
    }

    /**
     * 获取货币代码
     */
    protected function getCurrencyCode(string $currency): string
    {
        $supportedCurrencies = $this->getSupportedCurrencies();
        $currency = strtoupper($currency);

        // // 如果直接支持，返回
        if (isset($supportedCurrencies[$currency])) {
            return $supportedCurrencies[$currency];
        }

        // // 默认返回USD
        return 'USD';
    }

    /**
     * 生成哈希码
     */
    protected function generateHashCode(string $command): string
    {
        return md5($command . $this->secretKey);
    }

    /**
     * 发送API请求
     */
    protected function sendApiRequest(array $data): array
    {
        $token = $this->getBearerToken();

        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        // 确保URL格式正确，避免双斜杠
        $url = rtrim($this->apiUrl, '/') . '/api/callBack';
        $response = PaymentHelper::http_post_json($url, $data, true, $headers);
        $result = json_decode($response, true);

        if (!$result) {
            throw new \Exception('API响应解析失败');
        }

        return $result;
    }

    /**
     * 创建代收订单
     */
    public function createOrder(string $orderNo, float $amount, array $extra = []): array
    {
        $base_url = request()->domain();
        $callback_url = $base_url . '/index.php/api/notify/fiatpay';
        $redirect_url = $extra['return_url'] ?? $base_url;

        // 确保customer_uid是字符串类型
        $customerUid = $extra['customer_uid'] ?? $extra['id'] ?? '0';

        $data = [
            'command' => 'fiat_payment',
            'hashCode' => $this->generateHashCode('fiat_payment'),
            'callback_url' => $callback_url,
            'redirect_url' => $redirect_url,
            'currency' => $this->getCurrencyCode($extra['currency'] ?? 'MYR'),
            'method' => $extra['wayCode'] ?? 'online_banking',
            'customer_uid' => (string)$customerUid,
            'depositor_name' => $extra['depositor_name'] ?? '',
            'amount' => (string)$amount,
            'txid' => $orderNo
        ];

        // 可选参数
        if (!empty($extra['bank_code'])) {
            $data['bank_code'] = $extra['bank_code'];
        }
        if (!empty($extra['phone_number'])) {
            $data['phone_number'] = $extra['phone_number'];
        }
        if (!empty($extra['bank_account'])) {
            $data['bank_account'] = $extra['bank_account'];
        }

        $result = $this->sendApiRequest($data);

        // 解析API响应，转换为标准格式
        return $this->parseCreateOrderResponse($result, $orderNo);
    }

    /**
     * 创建代付订单
     */
    public function createTransfer(string $orderNo, float $amount, array $extra = []): array
    {
        $base_url = request()->domain();
        $callback_url = $base_url . '/index.php/api/notify/fiatpay';

        // 确保customer_uid是字符串类型
        $customerUid = $extra['customer_uid'] ?? $extra['id'] ?? '0';

        $data = [
            'command' => 'fiat_withdrawal',
            'hashCode' => $this->generateHashCode('fiat_withdrawal'),
            'callback_url' => $callback_url,
            'currency' => $this->getCurrencyCode($extra['currency'] ?? 'MYR'),
            'customer_uid' => (string)$customerUid,
            'amount' => (string)$amount,
            'bank_account_name' => $extra['bank_account_name'] ?? '',
            'bank_account_number' => $extra['bank_account_number'] ?? '',
            'bank_name' => $extra['bank_name'] ?? '',
            'txid' => $orderNo
        ];

        // 可选参数
        if (!empty($extra['bank_code'])) {
            $data['bank_code'] = $extra['bank_code'];
        }
        if (!empty($extra['branch_code'])) {
            $data['branch_code'] = $extra['branch_code'];
        }

        $result = $this->sendApiRequest($data);


        // 解析代付订单响应，转换为标准格式
        return $this->parseCreateTransferResponse($result, $orderNo);
    }

    /**
     * 查询订单状态
     */
    public function queryOrder(string $orderNo): array
    {
        $data = [
            'command' => 'fiat_payment_status',
            'hashCode' => $this->generateHashCode('fiat_payment_status'),
            'txid' => $orderNo
        ];

        return $this->sendApiRequest($data);
    }

    /**
     * 获取银行列表
     */
    public function getBankList(string $currency, string $method, string $type): array
    {
        $data = [
            'command' => 'bank_list',
            'hashCode' => $this->generateHashCode('bank_list'),
            'currency' => $this->getCurrencyCode($currency),
            'method' => $method,
            'type' => $type
        ];

        return $this->sendApiRequest($data);
    }

    /**
     * 查询余额
     */
    public function getBalance(): array
    {
        $data = [
            'command' => 'balances',
            'hashCode' => $this->generateHashCode('balances')
        ];

        return $this->sendApiRequest($data);
    }

    /**
     * 关闭订单 (该API可能不支持，返回成功)
     */
    public function close($orderNo): array
    {
        // 根据API文档，似乎没有关闭订单的接口
        // 可以尝试查询订单状态
        return $this->queryOrder($orderNo);
    }

    /**
     * 处理回调通知
     */
    public function notify(Request $request): bool
    {
        $data = $request->post();

        // 验证签名
        $signature = $request->header('Signature', '');
        if (empty($signature)) {
            return false;
        }

        // 按字母顺序排列参数
        ksort($data);
        $expectedSignature = md5($this->secretKey . json_encode($data));

        if ($signature !== $expectedSignature) {
            return false;
        }

        // 验证必要字段
        $requiredFields = ['txid', 'type', 'customer_uid', 'status'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * 获取回调数据
     */
    public function getNotifyData(Request $request): array
    {
        return $request->post();
    }

    /**
     * 解析创建订单响应，转换为标准格式
     * 将FiatPay响应转换为与AMOPAY相同的格式
     */
    private function parseCreateOrderResponse(array $response, string $orderNo): array
    {
        // 检查响应是否成功
        if (isset($response['status']) && $response['status'] === 'success') {
            // FiatPay成功响应格式转换为标准格式
            $data = [
                'cashierUrl' => $response['pay_url'] ?? '',
                'payOrderNo' => $response['txid'] ?? $orderNo,
            ];
            
            return [
                'code' => 0,
                'data' => $data
            ];
        }
        
        // 处理错误响应
        $errorMessage = '支付订单创建失败';
        
        if (isset($response['message'])) {
            $errorMessage = $response['message'];
        } elseif (isset($response['error'])) {
            $errorMessage = $response['error'];
        } elseif (is_array($response) && !empty($response)) {
            // 如果响应是数组格式的错误信息
            $errorMessage = implode(', ', array_values($response));
        }
        
        throw new \Exception($errorMessage);
    }

    /**
     * 解析代付订单响应，转换为标准格式
     * 根据您提供的响应格式进行处理
     */
    private function parseCreateTransferResponse(array $response, string $orderNo): array
    {
        // 检查响应是否成功
        if (isset($response['status']) && $response['status'] === 'success') {
            // 代付成功响应格式
            $data = [
                'transferOrderNo' => $response['txid'] ?? $orderNo,
                'currency' => $response['currency'] ?? 'MYR',
                'amount' => $response['amount'] ?? '',
                'method' => $response['method'] ?? 'online_banking',
                'status' => $response['status'] ?? 'success'
            ];
            
            return [
                'code' => 0,
                'data' => $data,
                'message' => '代付订单创建成功'
            ];
        }
        
        // 处理错误响应
        $errorMessage = '代付订单创建失败';
        
        if (isset($response['message'])) {
            $errorMessage = $response['message'];
        } elseif (isset($response['error'])) {
            $errorMessage = $response['error'];
        } elseif (is_array($response) && !empty($response)) {
            // 如果响应是数组格式的错误信息
            $errorMessage = implode(', ', array_values($response));
        }
        
        throw new \Exception($errorMessage);
    }
} 