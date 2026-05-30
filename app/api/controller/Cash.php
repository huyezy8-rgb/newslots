<?php
declare(strict_types=1);

namespace app\api\controller;

use app\api\enum\CoinLog;
use app\common\service\CommissionService;
use app\common\service\DmlService;
use think\Request;
use think\Response;
use think\facade\Db;
use think\facade\Log;

class Cash
{
    private array $config;

    public function __construct()
    {

        set_timezone(); //设置时区

        $this->config = [
            'AppID' => get_sys_config('game_appid'),
            'AppSecret' => get_sys_config('game_app_secret'),
            'AppID_RE' => get_sys_config('game_appid_re'),
            'AppSecret_RE' => get_sys_config('game_app_secret_re'),
            'CurrencyRate' => 1,
        ];
    }

    // 获取玩家余额
    public function get(Request $request): Response
    {
        $params = $request->post();

        Log::info('Cash@get 请求参数: ' . json_encode($params, JSON_UNESCAPED_UNICODE));

        // if (!$this->auth($params)) {
        //     Log::warning('Cash@get 鉴权失败: ' . json_encode($params, JSON_UNESCAPED_UNICODE));
        //     return json(['code' => 1, 'error' => '鉴权失败']);
        // }

       $userId = $params['UserID'] ?? $params['userid'] ?? '';
        if (!$userId) {
            Log::warning('Cash@get 缺少 UserID: ' . json_encode($params, JSON_UNESCAPED_UNICODE));
            return json(['code' => 1, 'error' => 'UserID 缺失']);
        }

        $balance = $this->getUserBalance($userId);

        Log::info('Cash@get 返回结果: ' . json_encode([
                'UserID' => $userId,
                'Balance' => $balance
            ], JSON_UNESCAPED_UNICODE));

        return json([
            'code' => 0,
            'error' => '',
            'data' => [
                'Balance' => $balance
            ]
        ]);
    }

    // 修改玩家余额（下注、派奖、退款）
    public function transferInOut(Request $request): Response
    {
        $params = $request->post();

        Log::info('Cash@transferInOut 请求参数: ' . json_encode($params, JSON_UNESCAPED_UNICODE));
        $params['UserID'] = $params['UserID'] ?? $params['userid'] ?? '';
$params['TransactionID'] = $params['TransactionID'] ?? $params['tid'] ?? '';
$params['Amount'] = $params['Amount'] ?? $params['amount'] ?? 0;
$params['RealAmount'] = $params['RealAmount'] ?? $params['real_amount'] ?? $params['amount'] ?? 0;
$params['Reason'] = $params['Reason'] ?? $params['reason'] ?? '';
$params['GameID'] = $params['GameID'] ?? $params['gameid'] ?? '';
$params['RoundID'] = $params['RoundID'] ?? $params['roundid'] ?? '';
$params['ReqTime'] = $params['ReqTime'] ?? $params['req_time'] ?? date('Y-m-d H:i:s');
if (is_numeric($params['ReqTime']) && strlen((string)$params['ReqTime']) >= 13) {
    $params['ReqTime'] = date('Y-m-d H:i:s', intval($params['ReqTime'] / 1000));
}

//        if (!$this->auth($params)) {
//            Log::warning('Cash@transferInOut 鉴权失败: ' . json_encode($params, JSON_UNESCAPED_UNICODE));
//            return json(['code' => 1, 'error' => '鉴权失败']);
//        }

        $required = ['UserID', 'TransactionID', 'Amount', 'RealAmount', 'Reason', 'GameID', 'RoundID', 'ReqTime'];
        foreach ($required as $field) {
            if (!isset($params[$field])) {
                Log::warning("Cash@transferInOut 缺少字段: $field ,参数: " . json_encode($params, JSON_UNESCAPED_UNICODE));
                return json(['code' => 1, 'error' => "缺少字段: $field"]);
            }
        }

        $userId = $params['UserID'];
        $transactionId = $params['TransactionID'];

        // 幂等性判断：是否已处理过该交易
        $exists = Db::name('game_transactions')->where('transaction_id', $transactionId)->find();
        if ($exists) {
            $balance = $this->getUserBalance($userId);
            Log::info('Cash@transferInOut 幂等返回: ' . json_encode([
                    'transaction_id' => $transactionId,
                    'Balance' => $balance
                ], JSON_UNESCAPED_UNICODE));
            return json([
                'code' => 0,
                'error' => '',
                'data' => [
                    'Balance' => $balance,
                    'RealAmount' => $params['RealAmount']
                ]
            ]);
        }

        $amount = floatval($params['Amount']);
        $realAmount = floatval($params['RealAmount']);
        $expected = $amount * $this->config['CurrencyRate'];

        // 浮点容差比较
        if (abs($realAmount - $expected) > 0.00001) {
            Log::warning('Cash@transferInOut RealAmount 不符合比例: ' . json_encode([
                    'Amount' => $amount,
                    'RealAmount' => $realAmount,
                    'Expected' => $expected
                ], JSON_UNESCAPED_UNICODE));
            return json(['code' => 1, 'error' => 'RealAmount 不符合比例关系']);
        }

        $balance = $this->getUserBalance($userId);
        if ($amount < 0 && $balance + $amount < 0) {
            Log::warning('Cash@transferInOut 余额不足: ' . json_encode([
                    'UserID' => $userId,
                    'Balance' => $balance,
                    'Amount' => $amount
                ], JSON_UNESCAPED_UNICODE));
            return json(['code' => 1, 'error' => '余额不足']);
        }

        // 修改余额
        $newBalance = round($balance + $amount, 2);

        Db::transaction(function () use ($userId, $newBalance, $transactionId, $params, $balance) {
            $user_info = Db::name('account')->where('id', $userId)->find();
            if ($user_info['switch_wallet'] == 0) {
                $balance_update = ['experience_wallet' => $newBalance];
                $wallet_type = 0;
            } else {
                $balance_update = ['recharge_wallet' => $newBalance];
                $wallet_type = 1;
            }

            if ($params['Reason'] == "bet") {
                $log_type_id = CoinLog::GameBet;
                $Typetext = CoinLog::getTypeText(CoinLog::GameBet);
            } elseif ($params['Reason'] == "win") {
                $log_type_id = CoinLog::GameWin;
                $Typetext = CoinLog::getTypeText(CoinLog::GameWin);
            } else {
                $log_type_id = CoinLog::GameRefund;
                $Typetext = CoinLog::getTypeText(CoinLog::GameRefund);
            }

            $logId = Db::name('account_coin_log')->insertGetId([
                'user_id'     => $userId,
                'channel_id'  => $user_info['channel_id'],
                'wallet_type' => $wallet_type,
                'old_num'     => $balance,
                'num'         => $params['Amount'],
                'new_num'     => $newBalance,
                'log_type_id' => $log_type_id,
                'note'        => $Typetext . '，' . __('Amount: %s', [$params['Amount']]),
                'create_time' => time(),
                'update_time' => time(),
            ]);

            if (!$logId) {
                throw new \Exception('记录日志失败');
            }

            // 更新余额
$account = \app\common\model\Account::find($userId);
$account->save($balance_update);

if ($user_info['switch_wallet'] == 1) {

    $releaseAmount = 0;

    if (in_array($params['Reason'], ['bet', 'win'], true)) {
        $releaseAmount = abs((float)$params['Amount']);
    }

    if ($releaseAmount > 0) {
        // 根据打码量计算：下注和派奖都释放可提现额度
        Log::info('Cash@transferInOut 打码计算: ', [
            'user_id' => $userId,
            'reason' => $params['Reason'],
            'release_amount' => $releaseAmount,
            'new_balance' => $newBalance,
        ]);

        (new DmlService())->updateDml($userId, $releaseAmount, (float)$newBalance);
    }

    if ($params['Reason'] == "bet") {
        // 更新累计下注
        $account->setInc('sum_bet', abs(floatval($params['Amount'])));
        // 更新虚拟提现
        $account->setInc('ex_withdraw_bet', abs(floatval($params['Amount'])));
        Log::info('Cash@transferInOut 更新活动数据: ');
        event('FirstDeposit270', ['amount' => abs(floatval($params['Amount'])), 'user_id' => $userId]);
        event('DepositVip', ['amount' => abs(floatval($params['Amount'])), 'user_id' => $userId]);
        event('GameVip', ['amount' => abs(floatval($params['Amount'])), 'game_id' => $params['GameID'], 'user_id' => $userId]);

        // 增加用户每日下注
        $account->setInc('today_sum_bet', abs(floatval($params['Amount'])));
        //下注返佣
        $svc = new CommissionService();
        $ok = $svc->dispatchSettleJob(intval($userId), abs(floatval($params['Amount'])), floatval(get_sys_config('bet_commission_base_bl') ?? 0.5));
        if ($ok) {
            Log::info('Cash@transferInOut 下注返佣添加到队列成功: ');
        } else {
            Log::warning('Cash@transferInOut 下注返佣添加到队列失败');
        }
        // 触发排行榜统计事件
        event('LeaderboardStats', ['amount' => abs(floatval($params['Amount'])), 'user_id' => $userId]);
    }
}
            // 插入交易记录，增加 wallet_type 字段
            Db::name('game_transactions')->insert([
                'transaction_id' => $transactionId,
                'user_id' => $userId,
                'amount' => $params['Amount'],
                'real_amount' => $params['RealAmount'],
                'reason' => $params['Reason'],
                'game_id' => $params['GameID'],
                'round_id' => $params['RoundID'],
                'req_time' => (new \DateTime($params['ReqTime']))->format('Y-m-d H:i:s'),
                'channel_id' => $user_info['channel_id'],
                'create_time' => time(),
                'wallet_type' => $wallet_type  // 新增钱包类型字段
            ]);
        });

        Log::info('Cash@transferInOut 更新余额成功: ' . json_encode([
                'UserID' => $userId,
                'NewBalance' => $newBalance
            ], JSON_UNESCAPED_UNICODE));

        return json([
            'code' => 0,
            'error' => '',
            'data' => [
                'Balance' => $newBalance,
                'RealAmount' => $realAmount
            ]
        ]);
    }

    // AppID 与 AppSecret 验证
  private function auth(array $params): bool
{
    $userId = $params['UserID'] ?? $params['userid'] ?? '';
    if (!$userId) {
        return false;
    }

    $user_info = Db::name('account')->where('id', $userId)->find();
    if (!$user_info) {
        return false;
    }
        if ($user_info['switch_wallet'] == 0) {
            return isset($params['AppID'], $params['AppSecret']) &&
                $params['AppID'] === $this->config['AppID'] &&
                $params['AppSecret'] === $this->config['AppSecret'];
        } else {
            return isset($params['AppID'], $params['AppSecret']) &&
                $params['AppID'] === $this->config['AppID_RE'] &&
                $params['AppSecret'] === $this->config['AppSecret_RE'];
        }
    }

    // 获取玩家余额（单位换算）
    private function getUserBalance(string $userId): float
    {
        $user_info = Db::name('account')->where('id', $userId)->find();
        if ($user_info['switch_wallet'] == 0) {
            $rawBalance = $user_info['experience_wallet'];
        } else {
            $rawBalance = $user_info['recharge_wallet'];
        }

        return round(floatval($rawBalance) ?? 0, 2);
    }
}