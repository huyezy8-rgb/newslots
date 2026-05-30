<?php
declare(strict_types=1);

namespace app\common\library\pay;

use app\common\model\payment\Channels;
use ba\PaymentHelper;
use think\facade\Log;
use think\Request;
use think\facade\Config;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

class AmoPay extends Driver
{
    protected string $clientId;
    protected string $secretKey;
    protected string $api;
    protected const JWT_ALGORITHM = 'HS256';
    protected const DEFAULT_CRYPTO = 'USDT';
    protected const DEFAULT_NETWORK = 'BSC';
    protected const DEFAULT_PAYMENT_METHOD = '10001';

    public function __construct()
    {
        $this->getConfig();

        if (empty($this->clientId) || empty($this->secretKey)) {
            throw new Exception('AmoPay 配置不完整，请在后台配置 AmoPay 的 Client ID 和 Secret Key');
        }


    }
    public function getConfig()
    {
        $config = Channels::where(['code'=>"Amo", 'status'=>1])->value('config');
        if(empty($config)){
            throw new \Exception('支付渠道未开启');

        }
        $this->clientId = $config['client_id'];
        $this->secretKey   = $config['secret_key'];
        $this->api   = $config['api_url'];
    }
    public function createOrder(string $orderNo, float $amount, array $extra = []): array
    {
        $this->validateAmount($amount);

        $payload = $this->createPayload($orderNo, $amount, $extra);
        $jwtToken = $this->generateJwtToken($payload);

        // 添加调试日志
        Log::info('AmoPay创建订单'.json_encode( [
            'order_no' => $orderNo,
            'amount' => $amount,
            'payload' => $payload,
            'jwt_token_length' => strlen($jwtToken),
            'client_id' => $this->clientId
        ]));

        $response = $this->sendRequest(
            'POST',
            '/payment/intent',
            $payload['data'],
            $jwtToken
        );

        return $this->parseOrderResponse($response);
    }

    public function close(string $orderNo): array
    {
        throw new Exception('AmoPay不支持关闭订单操作');
    }

    public function createTransfer(string $orderNo, float $amount, array $extra = []): array
    {
        throw new Exception('AmoPay不支持转账功能');
    }

    public function queryOrder(string $orderNo): array
    {
        $payload = $this->createQueryPayload();
        $jwtToken = $this->generateJwtToken($payload);

        $response = $this->sendRequest(
            'GET',
            '/payment/intent/' . urlencode($orderNo),
            [],
            $jwtToken
        );

        return $this->parseQueryResponse($response);
    }

    public function notify(Request $request): array
    {
        $jwtToken = $this->extractBearerToken($request);
        $decoded = $this->verifyJwtToken($jwtToken);

        return [
            'status' => true,
            'data' => [
                'header' => $this->extractJwtHeader($decoded),
                'payload' => (array)$decoded->data
            ]
        ];
    }

    protected function createPayload(string $orderNo, float $amount, array $extra): array
    {
        return [
            'jti' => $orderNo,
            'iss' => $this->clientId,
            'aud' => 'AMOPAY',
            'iat' => time(),
            'data' => [
                'side' => strtoupper($extra['side'] ?? 'BUY'),
                'cryptoCurrency' => strtoupper($extra['cryptocurrency'] ?? self::DEFAULT_CRYPTO),
                'network' => strtoupper($extra['network'] ?? self::DEFAULT_NETWORK),
                'cryptoQuantity' => $extra['crypto_quantity'] ?? 0,
                'fiatCurrency' => strtoupper($extra['fiat_currency'] ?? 'USD'),
                'amount' => round($amount, 2),
                'merchantReference' => $orderNo,
                'callbackUrl' => $this->getCallbackUrl($extra),
                'redirectUrl' => $extra['return_url'] ?? '',
                'paymentMethod' => $extra['wayCode'] ?? self::DEFAULT_PAYMENT_METHOD,
                'provider' => $extra['provider'] ?? 'PROVIDER'
            ]
        ];
    }

    protected function createQueryPayload(): array
    {
        return [
            'jti' => $this->generateUuid(),
            'iss' => $this->clientId,
            'aud' => 'AMOPAY',
            'iat' => time(),
            'data' => new \stdClass()
        ];
    }

    protected function sendRequest(string $method, string $endpoint, array $data, string $jwtToken): array
    {
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $jwtToken
        ];

        $url = $this->api . $endpoint;

        // 添加请求调试日志
        Log::info('AmoPay API请求'.json_encode( [
            'method' => $method,
            'url' => $url,
            'data' => $data,
            'headers' => $headers
        ]));

        $response = PaymentHelper::http_post_json($url, $data, true, $headers);

        $result = json_decode($response, true);
        if ($result === null) {
            throw new Exception('API响应解析失败');
        }

        // 添加响应调试日志
        Log::info('AmoPay API响应'.json_encode([
            'response' => $result,
            'raw_response' => $response
        ]));

        return $result;
    }

    protected function parseOrderResponse(array $response): array
    {
        if (!isset($response['ret']) || $response['ret'] !== 0) {
            throw new Exception($response['message'] ?? '支付下单失败');
        }
        $data = [
            'cashierUrl'=>$response['data']['redirectUrl'] ?? '',
            'payOrderNo'=>$response['data']['orderNo'] ?? '',
        ];
        return [
            'code'=>0,
            'data' => $data
        ];
    }

    protected function parseQueryResponse(array $response): array
    {
        if (!isset($response['ret']) || $response['ret'] !== 0) {
            throw new Exception($response['message'] ?? '查询订单失败');
        }

        return $response['data'] ?? [];
    }

    protected function verifyJwtToken(string $token): object
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secretKey, self::JWT_ALGORITHM));

            if ($decoded->iss !== $this->clientId || $decoded->aud !== 'AMOPAY') {
                throw new Exception('JWT验证失败: 无效的iss或aud');
            }

            return $decoded;
        } catch (Exception $e) {
            Log::error('JWT验证失败: ' . $e->getMessage());
            throw new Exception('回调验证失败');
        }
    }

    protected function extractBearerToken(Request $request): string
    {
        $jwtToken = $request->header('Authorization');
        if (!$jwtToken || !preg_match('/^Bearer\s+(.+)$/', $jwtToken, $matches)) {
            throw new Exception('缺少有效的Authorization头');
        }
        return $matches[1];
    }

    protected function extractJwtHeader(object $decoded): array
    {
        return [
            'iss' => $decoded->iss,
            'aud' => $decoded->aud,
            'iat' => $decoded->iat,
            'jti' => $decoded->jti ?? null
        ];
    }

    protected function getCallbackUrl(array $extra): string
    {
        return $extra['callback_url'] ?? request()->domain() . '/index.php/api/notify/amopay';
    }

    protected function validateAmount(float $amount): void
    {
        if ($amount <= 0) {
            throw new Exception('金额必须大于0');
        }
    }

    protected function generateJwtToken(array $payload): string
    {
        return JWT::encode($payload, $this->secretKey, self::JWT_ALGORITHM);
    }

    protected function generateUuid(): string
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}