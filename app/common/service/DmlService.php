<?php
namespace app\common\service;

use app\api\enum\CoinLog;
use app\common\model\activity\Daygold;
use app\common\model\activity\DepositVip;
use app\common\model\activity\FirstDeposit25;
use app\common\model\activity\FirstDeposit270;
use app\common\model\activity\FirstDepositDaily;
use app\common\model\activity\FirstVip49;
use app\common\model\activity\FirstVip6;
use app\common\model\RankingActivity;
use think\facade\Db;
use think\facade\Log;

class DmlService
{

    /**
     * 根据 log_type_id 查询打码倍数 并插入记录
     * @param int $userId 用户ID
     * @param float $amount 金额
     * @param int $coinLogId 流水ID
     * @param int $logTypeId 活动编码
     * @param int $newBalance 变动后余额
     * @return void
     */
    public function addDmlLogByLogTypeId($userId, float $amount, int $coinLogId, int $logTypeId,$newBalance)
    {
        // 排除不需要打码记录的类型
        if (in_array($logTypeId, [
            // 提现退款（驳回返还）
            CoinLog::WithdrawRefund, 
            CoinLog::ExWithdrawRefund, 
            CoinLog::PDDWithdrawRefund,
            CoinLog::GameWin,
            
            // 游戏退款（非收入）
            CoinLog::GameRefund,
        ])) {
            return;
        }
        
        // 根据 log_type_id 查询打码倍数
        switch ($logTypeId) {
            // 充值本金 - 使用默认打码倍率
            case CoinLog::Recharge: 
                $multiple = (float)get_sys_config('bet_multiplier') ?? 1; 
                break;
            
            // 充值到账后的赠送活动 - 使用渠道配置倍率
            case CoinLog::FirstDeposit25: $multiple = $this->getBetMultiplierFromChannel($userId, 'first_deposit_25') ?: FirstDeposit25::where(['id'=>1])->value('bet_multiplier'); break;
            case CoinLog::FirstDeposit270: $multiple = $this->getBetMultiplierFromChannel($userId, 'first_deposit_270') ?: FirstDeposit270::where(['id'=>1])->value('bet_multiplier'); break;
            case CoinLog::FirstDepositDaily: $multiple = $this->getBetMultiplierFromChannel($userId, 'first_deposit_daily') ?: FirstDepositDaily::where(['id'=>1])->value('bet_multiplier'); break;
            case CoinLog::DepositVip: $multiple = $this->getBetMultiplierFromChannel($userId, 'deposit_vip') ?: DepositVip::where(['id'=>1])->value('bet_multiplier'); break;
            case CoinLog::FirstVip6: $multiple = $this->getBetMultiplierFromChannel($userId, 'first_vip_6') ?: FirstVip6::where(['id'=>1])->value('bet_multiplier'); break;
            case CoinLog::FirstVip49: $multiple = $this->getBetMultiplierFromChannel($userId, 'first_vip_49') ?: FirstVip49::where(['id'=>1])->value('bet_multiplier'); break;
            
            // 纯赠送类活动 - 使用渠道配置倍率
            case CoinLog::DayGold: $multiple = $this->getBetMultiplierFromChannel($userId, 'daygold') ?: Daygold::where(['id'=>1])->value('bet_multiplier'); break;
            case CoinLog::ChestBox: $multiple = $this->getBetMultiplierFromChannel($userId, 'chest') ?: 1; break;
            case CoinLog::LeaderboardDaily: 
            case CoinLog::LeaderboardWeekly: 
            case CoinLog::LeaderboardMonthly: $multiple = $this->getBetMultiplierFromChannel($userId, 'leaderboard') ?: RankingActivity::where(['id'=>1])->value('bet_multiple'); break;
            
            case CoinLog::SevenDayCard: $multiple = $this->getBetMultiplierFromChannel($userId, 'seven_day_card') ?: Db::name('seven_day_card_config')->where(['id'=>1])->value('bet_multiplier'); break;

            default:  $multiple = (float)get_sys_config('bet_multiplier') ?? 1; break; // 默认1倍
        }
        $multiple = $multiple??1;
        $this->addDmlLogWithLogTypeId($userId, $amount, $coinLogId, $multiple, $logTypeId,$newBalance);
    }

    /**
     * 根据用户渠道配置获取打码倍率
     * @param int $userId 用户ID
     * @param string $activityKey 活动key
     * @return float|null 打码倍率，null表示未找到
     */
    private function getBetMultiplierFromChannel(int $userId, string $activityKey): ?float
    {
        try {
            // 获取用户信息
            $user = \app\common\model\Account::with('channel')->find($userId);
            if (!$user || !$user->channel) {
                return null;
            }

            // 解析渠道活动配置
            $activities = [];
            if (isset($user->channel['activity'])) {
                $activities = is_array($user->channel['activity']) 
                    ? $user->channel['activity'] 
                    : (json_decode((string)$user->channel['activity'], true) ?: []);
            }

            // 查找渠道配置中对应活动的打码倍率
            foreach ($activities as $activity) {
                if (($activity['key'] ?? null) === $activityKey) {
                    $betMultiplier = $activity['option']['bet_multiplier'] ?? null;
                    if ($betMultiplier && is_numeric($betMultiplier) && $betMultiplier > 0) {
                        return (float)$betMultiplier;
                    }
                    break;
                }
            }

            return null;

        } catch (\Exception $e) {
            \think\facade\Log::error("获取渠道打码倍率失败: " . json_encode([
                'user_id' => $userId,
                'activity_key' => $activityKey
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return null;
        }
    }

    /**
     * 插入打码记录，支持 log_type_id
     */
    public function addDmlLogWithLogTypeId($userId, float $amount, int $coinLogId, int $multiple, int $logTypeId,$newBalance)
    {
        Db::name('account_dml_log')->insert([
            'user_id'      => $userId,
            'coin_log_id'  => $coinLogId,
            'amount'       => $amount,
            'required_dml' => bcmul($amount, $multiple, 6),
            'completed_dml'=> 0,
            'is_finished'  => 0,
            'log_type_id'  => $logTypeId,
            'create_time'  => time()
        ]);
        $this->recalculateWithdrawAvailable($userId,$newBalance);
    }



    /**
     * 下注时更新打码进度
     */
    public function updateDml($userId, float $betAmount, float $newBalance)
    {
        \think\facade\Log::info("打码计算:用户{$userId},流水{$betAmount},变动后余额{$newBalance}");
        // 打印SQL语句
        $sql = Db::name('account_dml_log')
            ->where(['user_id'=>$userId,'is_finished'=>0])
            ->order('id')
            ->fetchSql(true)
            ->select();
        \think\facade\Log::info("SQL语句: " . $sql);
        
        $list = Db::name('account_dml_log')
            ->where(['user_id'=>$userId,'is_finished'=>0])
            ->order('id')
            ->select();


        \think\facade\Log::info("打码记录:总数" . count($list));
        
        $remainingBetAmount = $betAmount; // 记录剩余投注金额
        
        foreach ($list as $item) {
            $remain = bcsub($item['required_dml'], $item['completed_dml'], 6);
            if ($remain <= 0) {
                Db::name('account_dml_log')->where('id', $item['id'])->update(['is_finished' => 1]);
                \think\facade\Log::info("打码记录{$item['id']}已完成");
                continue;
            }
            
            $use = min($remainingBetAmount, $remain);
            $oldCompleted = $item['completed_dml'];
            $newCompleted = bcadd($oldCompleted, $use, 6);
            
            Db::name('account_dml_log')->where('id', $item['id'])->update(['completed_dml' => $newCompleted]);
            
            \think\facade\Log::info("打码记录{$item['id']}: 使用{$use}, 完成度: {$oldCompleted} -> {$newCompleted}/{$item['required_dml']}");
            
            if (bccomp($newCompleted, $item['required_dml'], 6) >= 0) {
                Db::name('account_dml_log')->where('id', $item['id'])->update(['is_finished' => 1]);
                \think\facade\Log::info("打码记录{$item['id']}标记为完成");
            }
            
            $remainingBetAmount = bcsub($remainingBetAmount, $use, 6);
            if ($remainingBetAmount <= 0) {
                \think\facade\Log::info("投注金额已用完，剩余: {$remainingBetAmount}");
                break;
            }
        }
        
        $this->recalculateWithdrawAvailable($userId, $newBalance);
    }

    /**
     * 重算可提现余额（按比例释放，累计提现金额以 withdraw_orders 表为准）
     */
    public function recalculateWithdrawAvailable($userId,$newBalance)
    {
        $logs = Db::name('account_dml_log')->where('user_id', $userId)->select();
        $totalReleased = 0;
        foreach ($logs as $log) {
            $ratio = $log['required_dml'] > 0 ? min(1, bcdiv($log['completed_dml'], $log['required_dml'], 6)) : 1;
            $totalReleased = bcadd($totalReleased, bcmul($log['amount'], $ratio, 6), 6);
        }
        $totalWithdrawn = Db::name('withdraw_orders')
            ->where('user_id', $userId)
            ->whereIn('status', [0, 1, 2, 4]) // 0=待审核, 1=审核通过, 2=已打款, 4=打款失败
            ->sum('amount');
        $withdrawAvailable = max(0, bcsub($totalReleased, $totalWithdrawn, 2));
        if ($withdrawAvailable > $newBalance) {
            $withdrawAvailable = $newBalance;
        }
        Log::info("可提现余额:".$withdrawAvailable);
        Db::name('account')->where('id', $userId)->update(['withdraw_available' => $withdrawAvailable]);
    }
} 