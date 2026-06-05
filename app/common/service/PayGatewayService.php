<?php

namespace app\common\service;

use app\common\library\pay\Driver;
use app\common\model\payment\Channels;
use app\common\model\payment\Methods;
use app\Request;
use think\facade\Db;


class PayGatewayService
{

    /**
     * 支付类型到wayCode映射
     */


    /**
     * 获取wayCode
     */
    public function getWayCode($payType)
    {
        $code = Methods::where(['unique_tag'=>$payType, 'status'=>1])->value('code');
        if(!$code){
            throw new \Exception('支付方式未开启');

        }
        return $code;
    }

    /**
     * 获取驱动类名
     */
    public function getDriverName($payType)
    {
        $channel_code = Methods::where(['unique_tag'=>$payType, 'status'=>1])->value('channel_code');
        if(!$channel_code){
            throw new \Exception('支付方式未开启');

        }
        return $channel_code;
    }

    /**
     * 创建支付订单
     * @param array $data ['pay_type' => string, 'order_no' => string, 'amount' => float]
     * @return array
     * @throws \Exception
     */
    public function createOrder(array $data): array
    {
        if (empty($data['pay_type']) || empty($data['order_no']) || empty($data['amount'] || empty($data['extra'] ))) {
            throw new \Exception(__('service.incomplete_parameters'));
        }
        $payType = strtolower($data['pay_type']);
        $data['extra']['wayCode'] = $this->getWayCode($payType);
        $driverName = $this->getDriverName($payType);
        $pay = Driver::instance($driverName);
        return $pay->createOrder($data['order_no'], (float)$data['amount'],$data['extra']);
    }

    /**
     * 关闭订单
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function closeOrder(array $data): array
    {
        if ( empty($data['pay_type']) ||empty($data['order_no']) ) {
            throw new \Exception(__('service.incomplete_parameters'));
        }
        $payType = strtolower($data['pay_type']);
        $driverName = $this->getDriverName($payType);
        $pay = Driver::instance($driverName);
        return $pay->close($data['order_no']);
    }

    /**
     * 提现
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function createTransfer(array $data): array
    {
        if (empty($data['pay_type']) || empty($data['order_no']) || empty($data['amount'] || empty($data['extra'] ))) {
            throw new \Exception(__('service.incomplete_parameters'));
        }
        $payType = strtolower($data['pay_type']);
        $data['extra']['wayCode'] = $this->getWayCode($payType);
        $driverName = $this->getDriverName($payType);
        $pay = Driver::instance($driverName);
        return $pay->createTransfer($data['order_no'], (float)$data['amount'],$data['extra']);
    }

    /**
     * 处理异步通知
     * @param Request $request
     * @param string $driverName
     * @return bool
     * @throws \Exception
     */
    public function handleNotify(Request $request, string $driverName): bool
    {
        $pay = Driver::instance($driverName);
        return $pay->notify($request);
    }

    /**
     * 获取用户可用支付渠道
     * @param int $userId
     * @param array $payChannels
     * @return array
     */
    public function getAvailablePayChannels($userId, $payChannels)
    {
        // 参数验证
        if (empty($userId)) {
            return [];
        }

        // 获取用户充值统计（可考虑缓存）
        $userStats = $this->getUserRechargeStats($userId);
        
        // 获取所有启用的支付方式
        $methods = $this->getEnabledPaymentMethods();
        $methodMap = $this->buildMethodMap($methods);

        // 如果payChannels为空，返回所有支付方式
        if (empty($payChannels)) {
            return $this->buildPaymentChannels($this->filterDisplayableMethods($methods, $userStats), 0);
        }

        // 处理配置的支付渠道
        return $this->processConfiguredChannels($payChannels, $methodMap, $userStats);
    }

    /**
     * 获取可用的提现渠道
     * 提现不需要限制条件，只根据pay_method筛选（0=所有方式，2=提现）
     * @return array
     */
    public function getAvailableWithdrawChannels(): array
    {
        // 获取所有启用的提现方式（pay_method = 0 或 2）
        $methods = $this->getEnabledWithdrawMethods();
        
        // 直接构建提现渠道列表，不需要条件限制
        return $this->buildWithdrawChannels($methods);
    }

    /**
     * 获取用户充值统计
     * @param int $userId
     * @return array
     */
    private function getUserRechargeStats($userId): array
    {
        $totalAmount = Db::name('recharge_orders')
            ->where(['user_id' => $userId, 'pay_status' => 1])
            ->sum('amount');
        $totalTimes = Db::name('recharge_orders')
            ->where(['user_id' => $userId, 'pay_status' => 1])
            ->count();

        return [
            'total_amount' => $totalAmount ?: 0,
            'total_times' => $totalTimes ?: 0,
        ];
    }



    /**
     * 获取启用的支付方式
     * @param string $payMethod 支付方式类型：'0'=所有方式,'1'=充值,'2'=提现
     * @return array
     */
    private function getEnabledPaymentMethods(string $payMethod = '1'): array
    {
        $where = ['status' => 1];
        
        // 根据支付方式类型过滤
        if ($payMethod !== '0') {
            $where[] = ['pay_method', 'in', ['0', $payMethod]]; // 包含"所有方式"和指定方式
        }
        
        return \app\common\model\payment\Methods::where($where)
            ->field('unique_tag, name, icon, show, is_clause, condition_recharge_amount, condition_recharge_times, channel_code, code, pay_method, min_recharge_amount, max_recharge_amount, min_withdraw_amount, max_withdraw_amount')
            ->select()
            ->toArray();
    }

    /**
     * 获取启用的提现方式
     * @return array
     */
    public function getPaymentMethodAmountLimits(string $payType): array
    {
        $method = \app\common\model\payment\Methods::where([
            'unique_tag' => strtolower($payType),
            'status' => 1,
        ])->field('min_recharge_amount, max_recharge_amount, min_withdraw_amount, max_withdraw_amount')->find();

        if (!$method) {
            throw new \Exception(__('Payment method param error'));
        }

        return [
            'min_recharge_amount' => $this->formatLimitValue($method['min_recharge_amount'] ?? null),
            'max_recharge_amount' => $this->formatLimitValue($method['max_recharge_amount'] ?? null),
            'min_withdraw_amount' => $this->formatLimitValue($method['min_withdraw_amount'] ?? null),
            'max_withdraw_amount' => $this->formatLimitValue($method['max_withdraw_amount'] ?? null),
        ];
    }

    public function validatePaymentAmount(string $payType, float $amount, string $type): void
    {
        $limits = $this->getPaymentMethodAmountLimits($payType);

        if ($type === 'recharge') {
            $min = $limits['min_recharge_amount'];
            $max = $limits['max_recharge_amount'];
            if ($min !== null && $amount < $min) {
                throw new \Exception(__('Recharge amount cannot be less than %s', [$this->formatLimitAmount($min)]));
            }
            if ($max !== null && $amount > $max) {
                throw new \Exception(__('Recharge amount cannot exceed %s', [$this->formatLimitAmount($max)]));
            }
            return;
        }

        if ($type === 'withdraw') {
            $min = $limits['min_withdraw_amount'];
            $max = $limits['max_withdraw_amount'];
            if ($min !== null && $amount < $min) {
                throw new \Exception(__('Withdraw amount cannot be less than %s', [$this->formatLimitAmount($min)]));
            }
            if ($max !== null && $amount > $max) {
                throw new \Exception(__('Withdraw amount cannot exceed %s', [$this->formatLimitAmount($max)]));
            }
        }
    }

    private function formatLimitValue($amount): ?float
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        return (float)$amount;
    }

    private function formatLimitAmount(float $amount): string
    {
        return rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');
    }

    private function getEnabledWithdrawMethods(): array
    {
        return $this->getEnabledPaymentMethods('2');
    }

    /**
     * 构建支付方式映射
     * @param array $methods
     * @return array
     */
    private function buildMethodMap(array $methods): array
    {
        $methodMap = [];
        foreach ($methods as $method) {
            $methodMap[strtolower($method['unique_tag'])] = $method;
        }
        return $methodMap;
    }

    /**
     * 构建支付渠道数组
     * @param array $methods
     * @param float $rewardPercent
     * @return array
     */
    private function buildPaymentChannels(array $methods, float $rewardPercent = 0): array
    {
        $result = [];
        foreach ($methods as $method) {
            $channelData = [
                'channel'        => $method['unique_tag'],
                'reward_percent' => $rewardPercent,
                'icon'           => $method['icon'] ?? '',
                'name'           => $method['name'] ?? '',
                'show'           => $method['show'] ?? 'all',
                'min_recharge_amount' => $this->formatLimitValue($method['min_recharge_amount'] ?? null),
                'max_recharge_amount' => $this->formatLimitValue($method['max_recharge_amount'] ?? null),
                'min_withdraw_amount' => $this->formatLimitValue($method['min_withdraw_amount'] ?? null),
                'max_withdraw_amount' => $this->formatLimitValue($method['max_withdraw_amount'] ?? null),
                // 默认为所有渠道添加银行列表相关字段
                'bank_list'      => [],
                'bank_count'     => 0,
                'has_banks'      => false,
            ];

            // 如果是 FiatPay 渠道，尝试填充银行列表数据
            if (isset($method['channel_code']) && $method['channel_code'] === 'Fiat') {
                try {
                    $methodCode = $method['code'] ?? 'online_banking';
                    $needsBankList = in_array($methodCode, ['online_banking', 'card']);
                    
                    if ($needsBankList) {
                        $bankList = $this->getFiatPayBankList($methodCode, 'deposit', true);
                        $channelData['bank_list'] = $bankList['data'] ?? [];
                        $channelData['bank_count'] = $bankList['total'] ?? 0;
                        $channelData['has_banks'] = !empty($bankList['data']);
                    }
                } catch (\Exception $e) {
                    // 银行列表获取失败时，保持默认空值
                    $this->logPaymentError("构建FiatPay渠道银行列表失败: " . $e->getMessage(), [
                        'method' => $method['unique_tag'] ?? '',
                        'method_code' => $method['code'] ?? ''
                    ]);
                }
            }

            $result[] = $channelData;
        }
        return $result;
    }

    private function filterDisplayableMethods(array $methods, array $userStats): array
    {
        return array_values(array_filter($methods, function (array $method) use ($userStats): bool {
            return $this->shouldDisplayChannel($method, $userStats);
        }));
    }

    /**
     * 构建提现渠道列表
     * @param array $methods
     * @return array
     */
    private function buildWithdrawChannels(array $methods): array
    {

        $result = [];
        foreach ($methods as $method) {
            $channelData= [
                'channel' => $method['unique_tag'],
                'name'    => $method['name'] ?? '',
                'show'    => $method['show'] ?? 'all',
                'min_recharge_amount' => $this->formatLimitValue($method['min_recharge_amount'] ?? null),
                'max_recharge_amount' => $this->formatLimitValue($method['max_recharge_amount'] ?? null),
                'min_withdraw_amount' => $this->formatLimitValue($method['min_withdraw_amount'] ?? null),
                'max_withdraw_amount' => $this->formatLimitValue($method['max_withdraw_amount'] ?? null),
                // 默认为所有渠道添加银行列表相关字段
                'bank_list'      => [],
                'bank_count'     => 0,
                'has_banks'      => false,
            ];
            // 如果是 FiatPay 渠道，尝试填充银行列表数据
            if (isset($method['channel_code']) && $method['channel_code'] === 'Fiat') {
                try {
                    $methodCode = $method['code'] ?? 'fiat_withdrawal';
                    $needsBankList = in_array($methodCode, ['fiat_withdrawal']);

                    if ($needsBankList) {
                        $bankList = $this->getFiatPayBankList('online_banking', 'withdrawal', true);
                        $channelData['bank_list'] = $bankList['data'] ?? [];
                        $channelData['bank_count'] = $bankList['total'] ?? 0;
                        $channelData['has_banks'] = !empty($bankList['data']);
                    }
                } catch (\Exception $e) {
                    // 银行列表获取失败时，保持默认空值
                    $this->logPaymentError("构建FiatPay渠道银行列表失败: " . $e->getMessage(), [
                        'method' => $method['unique_tag'] ?? '',
                        'method_code' => $method['code'] ?? ''
                    ]);
                }
            }
            $result[] = $channelData;
        }


        return $result;
    }

    /**
     * 处理配置的支付渠道
     * @param array $payChannels
     * @param array $methodMap
     * @param array $userStats
     * @return array
     */
    private function processConfiguredChannels(array $payChannels, array $methodMap, array $userStats): array
    {
        $result = [];
        $matchedCount = 0;

        foreach ($payChannels as $item) {
            $channel = strtolower($item['channel']);
            $method = $methodMap[$channel] ?? [];
            
            if (!empty($method)) {
                $matchedCount++;
            }
            
            // 检查是否满足显示条件
            if ($this->shouldDisplayChannel($method, $userStats)) {
                $result[] = $this->mergeChannelData($item, $method);
            }
        }

        return $result;
    }

    /**
     * 判断是否应该显示渠道
     * @param array $method
     * @param array $userStats
     * @return bool
     */
    private function shouldDisplayChannel(array $method, array $userStats): bool
    {
        // 如果没有匹配到方法，不显示
        if (empty($method)) {
            return false;
        }

        // 如果不需要按条件显示，直接显示
        if (empty($method['is_clause']) || $method['is_clause'] != 1) {
            return true;
        }

        $requiredAmount = max(0, (float)($method['condition_recharge_amount'] ?? 30));
        $requiredTimes = max(0, (int)($method['condition_recharge_times'] ?? 3));

        return (float)$userStats['total_amount'] >= $requiredAmount && (int)$userStats['total_times'] >= $requiredTimes;
    }

    /**
     * 合并渠道数据
     * @param array $item
     * @param array $method
     * @return array
     */
    private function mergeChannelData(array $item, array $method): array
    {
        $result = array_merge(
            $item,
            [
                'icon' => $method['icon'] ?? '',
                'name' => $method['name'] ?? '',
                'show' => $method['show'] ?? 'all',
                'min_recharge_amount' => $this->formatLimitValue($method['min_recharge_amount'] ?? null),
                'max_recharge_amount' => $this->formatLimitValue($method['max_recharge_amount'] ?? null),
                'min_withdraw_amount' => $this->formatLimitValue($method['min_withdraw_amount'] ?? null),
                'max_withdraw_amount' => $this->formatLimitValue($method['max_withdraw_amount'] ?? null),
                // 默认为所有渠道添加银行列表相关字段
                'bank_list' => [],
                'bank_count' => 0,
                'has_banks' => false,
            ]
        );

        // 如果是 FiatPay 渠道，填充实际银行列表数据
        if (isset($method['channel_code']) && $method['channel_code'] === 'Fiat') {
            try {
                // 获取支付方式代码，判断是否需要银行列表
                $methodCode = $method['code'] ?? 'online_banking';
                $needsBankList = in_array($methodCode, ['online_banking', 'card']); // 网银和银行卡需要选择银行
                
                if ($needsBankList) {
                    // 获取银行列表，使用缓存
                    $bankList = $this->getFiatPayBankList($methodCode, 'deposit', true);
                    $result['bank_list'] = $bankList['data'] ?? [];
                    $result['bank_count'] = $bankList['total'] ?? 0;
                    $result['has_banks'] = !empty($bankList['data']);
                }
                // 如果不需要银行列表（如二维码），保持默认的空值
                
                // 记录银行列表处理结果
                $this->logPaymentInfo("FiatPay渠道银行列表处理", [
                    'channel' => $item['channel'] ?? '',
                    'method_code' => $methodCode,
                    'needs_bank_list' => $needsBankList,
                    'bank_count' => $result['bank_count']
                ]);
                
            } catch (\Exception $e) {
                // 银行列表获取失败时，不影响主流程，只记录错误
                $result['bank_list'] = [];
                $result['bank_count'] = 0;
                $result['has_banks'] = false;
                
                $this->logPaymentError("FiatPay渠道银行列表获取失败: " . $e->getMessage(), [
                    'channel' => $item['channel'] ?? '',
                    'method_code' => $method['code'] ?? ''
                ]);
            }
        }

        return $result;
    }

    /**
     * 获取FiatPay银行列表
     * 优化版：支持缓存、参数验证、性能优化、智能去重
     * 
     * @param string $paycode 支付方式代码
     * @param string $type 类型 (deposit/withdrawal)
     * @param bool $useCache 是否使用缓存
     * @return array
     * @throws \Exception
     */
    public function getFiatPayBankList(string $paycode = 'online_banking', string $type = 'deposit', bool $useCache = true): array
    {
        $currency = "MYR"; // 固定使用MYR货币
        
        try {
            // 生成缓存键
            $cacheKey = "fiatpay_banks_{$currency}_{$paycode}_{$type}";
            
            // 尝试从缓存获取
            if ($useCache) {
                $cachedResult = $this->getBankListFromCache($cacheKey);
                if ($cachedResult !== null) {
                    $this->logPaymentInfo("FiatPay银行列表缓存命中", [
                        'currency' => $currency,
                        'paycode' => $paycode,
                        'type' => $type,
                        'cache_key' => $cacheKey
                    ]);
                    
                    return $cachedResult;
                }
            }

            // 创建FiatPay实例
            $fiatPayDriver = new \app\common\library\pay\FiatPay();

            // 调用银行列表API
            $apiResult = $fiatPayDriver->getBankList($currency, $paycode, $type);
            
            // 优化的数据处理
            $processedResult = $this->processBankListData($apiResult);
            
            // 缓存结果（5分钟）
            if ($useCache && !empty($processedResult['data'])) {
                $this->cacheBankList($cacheKey, $processedResult, 300);
            }
            
            // 记录成功日志
            $this->logPaymentInfo("FiatPay银行列表获取成功", [
                'currency' => $currency,
                'paycode' => $paycode,
                'type' => $type,
                'total_banks' => $processedResult['total'] ?? 0,
                'original_count' => $processedResult['original_count'] ?? 0,
                'duplicates_removed' => ($processedResult['original_count'] ?? 0) - ($processedResult['total'] ?? 0),
                'cached' => $useCache
            ]);
            
            return $processedResult;

        } catch (\Exception $e) {
            // 详细错误处理
            $errorDetails = [
                'currency' => $currency,
                'paycode' => $paycode,
                'type' => $type,
                'error_type' => get_class($e),
                'error_line' => $e->getLine(),
                'error_file' => basename($e->getFile())
            ];
            
            $this->logPaymentError("FiatPay银行列表获取失败: " . $e->getMessage(), $errorDetails);

            // 尝试返回缓存的数据作为降级方案
            $cacheKey = "fiatpay_banks_{$currency}_{$paycode}_{$type}";
            $fallbackResult = $this->getBankListFromCache($cacheKey, true); // 允许过期缓存
            
            if ($fallbackResult !== null) {
                $this->logPaymentInfo("使用过期缓存作为降级方案", $errorDetails);
                return $fallbackResult;
            }

            throw new \Exception('获取银行列表失败: ' . $e->getMessage());
        }
    }

    /**
     * 处理银行列表数据（去重、排序）
     * 
     * @param array $apiResult
     * @return array
     */
    private function processBankListData(array $apiResult): array
    {
        $originalCount = 0;
        $processedBanks = [];
        
        if (isset($apiResult['data']) && is_array($apiResult['data'])) {
            $originalCount = count($apiResult['data']);
            $bankMap = []; // 使用关联数组进行更高效的去重
            
            foreach ($apiResult['data'] as $bank) {
                $bankCode = trim($bank['bank_code'] ?? '');
                $bankName = trim($bank['bank_name'] ?? '');
                
                // 跳过无效数据
                if (empty($bankCode) || empty($bankName)) {
                    continue;
                }
                
                // 去重：如果已存在相同bank_code，保留名称更完整的
                if (!isset($bankMap[$bankCode]) || strlen($bankName) > strlen($bankMap[$bankCode]['bank_name'])) {
                    $bankMap[$bankCode] = [
                        'bank_code' => $bankCode,
                        'bank_name' => $bankName,
                    ];
                }
            }
            
            // 转换为索引数组并按名称排序
            $processedBanks = array_values($bankMap);
            usort($processedBanks, function($a, $b) {
                return strcmp($a['bank_name'], $b['bank_name']);
            });
        }
        
        return [
            'data' => $processedBanks,
            'total' => count($processedBanks),
            'original_count' => $originalCount,
            'duplicates_removed' => $originalCount - count($processedBanks),
            'last_updated' => date('Y-m-d H:i:s'),
            'cache_ttl' => 300, // 缓存时间(秒)
            'note' => '银行名称保持原始格式，未进行格式化处理'
        ];
    }

    /**
     * 从缓存获取银行列表
     * 
     * @param string $key
     * @param bool $allowExpired
     * @return array|null
     */
    private function getBankListFromCache(string $key, bool $allowExpired = false): ?array
    {
        try {
            if (function_exists('cache')) {
                $result = cache($key);
                if ($result !== null) {
                    // 检查是否过期（如果不允许过期缓存）
                    if (!$allowExpired && isset($result['cached_at'])) {
                        $cacheAge = time() - $result['cached_at'];
                        if ($cacheAge > ($result['cache_ttl'] ?? 300)) {
                            return null; // 缓存已过期
                        }
                    }
                    return $result;
                }
            }
        } catch (\Exception $e) {
            $this->logPaymentError("缓存读取失败: " . $e->getMessage(), ['cache_key' => $key]);
        }
        
        return null;
    }

    /**
     * 缓存银行列表
     * 
     * @param string $key
     * @param array $data
     * @param int $ttl
     * @return void
     */
    private function cacheBankList(string $key, array $data, int $ttl = 300): void
    {
        try {
            if (function_exists('cache')) {
                $data['cached_at'] = time();
                $data['cache_ttl'] = $ttl;
                cache($key, $data, $ttl);
            }
        } catch (\Exception $e) {
            $this->logPaymentError("缓存写入失败: " . $e->getMessage(), ['cache_key' => $key]);
        }
    }

    /**
     * 记录支付相关信息日志
     * 
     * @param string $message
     * @param array $data
     * @return void
     */
    private function logPaymentInfo(string $message, array $data = []): void
    {
        try {
            if (class_exists('\think\facade\Log')) {
                \think\facade\Log::channel('payment')->info($message, $data);
            }
        } catch (\Exception $e) {
            // 静默处理日志错误
        }
    }

    /**
     * 记录支付相关错误日志
     * 
     * @param string $message
     * @param array $data
     * @return void
     */
    private function logPaymentError(string $message, array $data = []): void
    {
        try {
            if (class_exists('\think\facade\Log')) {
                \think\facade\Log::channel('payment')->error($message, $data);
            }
        } catch (\Exception $e) {
            // 静默处理日志错误
        }
    }
}
