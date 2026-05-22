<?php

namespace app\api\controller;

use app\common\service\PddService;
use app\common\service\ChannelInfoService;
use think\facade\Db;

class Pdd extends Base
{
    public function index()
    {
        try {
            ChannelInfoService::assertChannelActivityEnabled($this->userInfo, 'pdd');
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
        }

        // 当前邀请进度（优先展示 status=1 可领取的进度）
        $progress = PddService::getDisplayProgress($this->userInfo['id']);

        $data = [
            'progress' => $progress,
            // 提现金额应与当前进度绑定，使用 progress.target_amount
            'pdd_withdrawal' => (float)($progress['target_amount'] ?? (get_sys_config('pdd_withdrawal') ?? 30.0)),
            //弹窗状态 根据进度是否是首次判断
            'pop_up_status' => $progress['progress_frist']?0:1,
        ];

        $this->success(__('Success'), $data);
    }

    /**
     * 获取用户的进度组列表
     */
    public function groups()
    {
        try {
            ChannelInfoService::assertChannelActivityEnabled($this->userInfo, 'pdd');
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
        }

        $groups = \app\common\model\PddProgressGroup::getUserGroups($this->userInfo['id']);
        $this->success(__('Success'), ['groups' => $groups]);
    }

    // 不再返回邀请明细

    public function withdrawal()
    {
        try {
            ChannelInfoService::assertChannelActivityEnabled($this->userInfo, 'pdd');
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
        }

        $progressId = (int)$this->request->param('progress_id');
        if (!$progressId) {
            $this->error(__('Progress ID is required'));
        }

        // PDD提现直接转入充值账户，只需要progress_id参数
        try {
            $result = PddService::withdrawByInviteProgress($this->userInfo->id, $progressId);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
        $this->success(__('Withdrawal successful'), $result);
    }

    /**
     * 获取邀请奖励记录
     */
    public function inviteLog()
    {
        try {
            ChannelInfoService::assertChannelActivityEnabled($this->userInfo, 'pdd');
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
        }

        $page = (int)($this->request->param('page', 1));
        $limit = (int)($this->request->param('limit', 20));

        try {
            $offset = ($page - 1) * $limit;
            
            // 查询邀请奖励记录
            $logs = Db::name('pdd_invite_log')
                ->alias('l')
                ->join('account a', 'l.invite_user_id = a.id')
                ->field('a.nickname, l.amount, l.create_time')
                ->where('l.inviter_user_id', $this->userInfo['id'])
                ->order('l.create_time DESC')
                ->limit($offset, $limit)
                ->select()
                ->toArray();
            
            // 查询总数
            $total = Db::name('pdd_invite_log')
                ->where('inviter_user_id', $this->userInfo['id'])
                ->count();
            
            // 格式化数据
            $formattedLogs = [];
            foreach ($logs as $log) {
                $formattedLogs[] = [
                    'nickname' => $log['nickname'],
                    'amount' => (float)$log['amount'],
                    'create_time' => date('Y-m-d H:i:s', $log['create_time'])
                ];
            }
            
            $data = [
                'list' => $formattedLogs,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($total / $limit)
            ];
            
        } catch (\Exception $e) {
            $this->error(__('Failed to get invite records'));
        }

        $this->success(__('Success'), $data);
    }

    /**
     * 获取邀请数据页面信息
     */
    public function data()
    {
        try {
            ChannelInfoService::assertChannelActivityEnabled($this->userInfo, 'pdd');
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
        }

        $period = $this->request->param('period', 'today'); // today, yesterday, this_week, last_week, this_month, last_month
        
        try {
            // 获取时间范围
            $timeRange = $this->getTimeRange($period);
            
            // 获取邀请数据
            $inviteData = $this->getInviteData($timeRange);
            
            // 获取业绩数据
            $performanceData = $this->getPerformanceData($timeRange);
            
            // 获取佣金数据
            $commissionData = $this->getCommissionData($timeRange);
            
            // 获取历史数据
            $historyData = $this->getHistoryData();
            
            $data = [
                'invite_data' => $inviteData,
                'performance_data' => $performanceData,
                'commission_data' => $commissionData,
                'history_data' => $historyData,
                'period' => $period,
                'time_range' => $timeRange
            ];
            
        } catch (\Exception $e) {
            $this->error(__('Failed to get data'));
        }
        
        $this->success(__('Success'), $data);
    }



    /**
     * 获取时间范围
     */
    private function getTimeRange($period)
    {
        $now = time();
        $today = strtotime('today');
        $yesterday = strtotime('yesterday');
        $thisWeekStart = strtotime('this week');
        $lastWeekStart = strtotime('last week');
        $lastWeekEnd = strtotime('last week +6 days');
        $thisMonthStart = strtotime('first day of this month');
        $lastMonthStart = strtotime('first day of last month');
        $lastMonthEnd = strtotime('last day of last month');

        switch ($period) {
            case 'today':
                return ['start' => $today, 'end' => $now];
            case 'yesterday':
                return ['start' => $yesterday, 'end' => $today - 1];
            case 'this_week':
                return ['start' => $thisWeekStart, 'end' => $now];
            case 'last_week':
                return ['start' => $lastWeekStart, 'end' => $lastWeekEnd];
            case 'this_month':
                return ['start' => $thisMonthStart, 'end' => $now];
            case 'last_month':
                return ['start' => $lastMonthStart, 'end' => $lastMonthEnd];
            default:
                return ['start' => $today, 'end' => $now];
        }
    }

    /**
     * 获取邀请数据
     */
    private function getInviteData($timeRange)
    {
        $userId = $this->userInfo->id;
        
        // 获取用户信息，包括上级ID和团队层级
        $userInfo = Db::name('account')
            ->field('p_id, team_level')
            ->where('id', $userId)
            ->find();
        
        // 获取上级信息
        $parentId = $userInfo['p_id'] ?? 0;
        $parentInfo = null;
        if ($parentId > 0) {
            $parentInfo = Db::name('account')
                ->field('id, nickname')
                ->where('id', $parentId)
                ->find();
        }
        
        // 获取直接邀请用户数（在指定时间范围内注册的）
        $directInvites = Db::name('account')
            ->where('p_id', $userId)
            ->where('create_time', '>=', $timeRange['start'])
            ->where('create_time', '<=', $timeRange['end'])
            ->count();
        
        // 获取有效邀请用户数（有充值的）
        $validInvites = Db::name('account')
            ->alias('a')
            ->join('recharge_orders ro', 'a.id = ro.user_id')
            ->where('a.p_id', $userId)
            ->where('a.create_time', '>=', $timeRange['start'])
            ->where('a.create_time', '<=', $timeRange['end'])
            ->where('ro.pay_status', 1) // 充值成功
            ->where('ro.amount', '>', 0)
            ->group('a.id')
            ->count();
        
        // 获取邀请用户的首充金额
        $firstDepositAmount = Db::name('account')
            ->alias('a')
            ->join('recharge_orders ro', 'a.id = ro.user_id')
            ->where('a.p_id', $userId)
            ->where('a.create_time', '>=', $timeRange['start'])
            ->where('a.create_time', '<=', $timeRange['end'])
            ->where('ro.pay_status', 1) // 充值成功
            ->where('ro.amount', '>', 0)
            ->sum('ro.amount');
        
        return [
            'direct_invites' => (int)$directInvites,
            'valid_invites' => (int)$validInvites,
            'first_deposit_amount' => round((float)$firstDepositAmount, 2),
            'parent_id' => $parentId > 0 ? (int)$parentId : null,
            'parent_nickname' => $parentInfo ? $parentInfo['nickname'] : null,
            'mode' => 'Unlimited Level'
        ];
    }

    /**
     * 获取业绩数据
     */
    private function getPerformanceData($timeRange)
    {
        $userId = $this->userInfo->id;
        
        // 获取团队路径
        $user = Db::name('account')->where('id', $userId)->field('team_path')->find();
        $teamPath = $user['team_path'] ?? '';
        
        // 新游戏玩家数量（直接邀请且有游戏记录）
        $newGamePlayers = Db::name('account')
            ->alias('a')
            ->join('game_transactions gt', 'a.id = gt.user_id')
            ->where('a.p_id', $userId)
            ->where('a.create_time', '>=', $timeRange['start'])
            ->where('a.create_time', '<=', $timeRange['end'])
            ->where('gt.create_time', '>=', $timeRange['start'])
            ->where('gt.create_time', '<=', $timeRange['end'])
            ->group('a.id')
            ->count();
        
        // 首充用户数量
        $firstChargeUsers = Db::name('account')
            ->alias('a')
            ->join('recharge_orders ro', 'a.id = ro.user_id')
            ->where('a.p_id', $userId)
            ->where('a.create_time', '>=', $timeRange['start'])
            ->where('a.create_time', '<=', $timeRange['end'])
            ->where('ro.pay_status', 1) // 充值成功
            ->where('ro.amount', '>', 0)
            ->group('a.id')
            ->count();
        
        // 初始充值金额（首充金额）
        $initialDepositAmount = 0;
        $invitedUsers = Db::name('account')
            ->where('p_id', $userId)
            ->where('create_time', '>=', $timeRange['start'])
            ->where('create_time', '<=', $timeRange['end'])
            ->column('id');
        
        foreach ($invitedUsers as $invitedUserId) {
            $firstRecharge = Db::name('recharge_orders')
                ->where('user_id', $invitedUserId)
                ->where('pay_status', 1)
                ->where('amount', '>', 0)
                ->order('created_at', 'asc')
                ->find();
            
            if ($firstRecharge) {
                $initialDepositAmount += $firstRecharge['amount'];
            }
        }
        
        // 充值金额（所有充值）
        $depositAmount = Db::name('account')
            ->alias('a')
            ->join('recharge_orders ro', 'a.id = ro.user_id')
            ->where('a.p_id', $userId)
            ->where('ro.created_at', '>=', $timeRange['start'])
            ->where('ro.created_at', '<=', $timeRange['end'])
            ->where('ro.pay_status', 1) // 充值成功
            ->where('ro.amount', '>', 0)
            ->sum('ro.amount');
        
        // 宝箱奖励（基于宝箱领取记录）
        $treasureBoxReward = Db::name('account')
            ->alias('a')
            ->join('chest_receive_log crl', 'a.id = crl.user_id')
            ->where('a.p_id', $userId)
            ->where('crl.createtime', '>=', $timeRange['start'])
            ->where('crl.createtime', '<=', $timeRange['end'])
            ->where('crl.amount', '>', 0)
            ->sum('crl.amount');
        
        // 佣金
        $commission = Db::name('account_coin_log')
            ->where('user_id', $userId)
            ->where('create_time', '>=', $timeRange['start'])
            ->where('create_time', '<=', $timeRange['end'])
            ->where('log_type_id', \app\api\enum\CoinLog::CommissionBet)
            ->where('num', '>', 0)
            ->sum('num');
        
        return [
            'new_game_players' => (float)$newGamePlayers,
            'first_charge_users' => (int)$firstChargeUsers,
            'initial_deposit_amount' => round((float)$initialDepositAmount, 2),
            'deposit_amount' => round((float)$depositAmount, 2),
            'treasure_box_reward' => round((float)$treasureBoxReward, 2),
            'commission' => round((float)$commission, 2)
        ];
    }

    /**
     * 获取佣金数据
     */
    private function getCommissionData($timeRange)
    {
        $userId = $this->userInfo->id;
        
        // 当前可提取佣金
        $extractableCommission = (float)Db::name('account')->where('id', $userId)->value('commission_balance');
        
        // 指定时间范围内的佣金收入
        $periodCommission = Db::name('account_coin_log')
            ->where('user_id', $userId)
            ->where('create_time', '>=', $timeRange['start'])
            ->where('create_time', '<=', $timeRange['end'])
            ->where('log_type_id', \app\api\enum\CoinLog::CommissionBet)
            ->where('num', '>', 0)
            ->sum('num');
        
        // 累计佣金收入
//        $totalCommission = Db::name('account_coin_log')
//            ->where('user_id', $userId)
//            ->where('log_type_id', \app\api\enum\CoinLog::CommissionBet)
//            ->where('num', '>', 0)
//            ->sum('num');
        $totalCommission = (float)Db::name('account')->where('id', $userId)->value('commission_balance');
        
        return [
            'extractable_commission' => round($extractableCommission, 2),
            'period_commission' => round((float)$periodCommission, 2),
            'total_commission' => round((float)$totalCommission, 2)
        ];
    }

    /**
     * 获取历史数据
     */
    private function getHistoryData()
    {
        $userId = $this->userInfo->id;
        $userLevel = $this->userInfo->team_level;
        $userTeamPath = $this->userInfo->team_path;
        
        // 总业绩（所有下级用户的总下注额）
        $totalPerformance = Db::name('account')
            ->alias('a')
            ->join('game_transactions gt', 'a.id = gt.user_id')
            ->where('a.team_path', 'like', $userTeamPath . $userId . '/%')
            ->where('gt.reason', 'bet') // 下注记录
            ->where('gt.amount', '>', 0)
            ->sum('gt.amount');
        
        // 一级业绩（直接下级的总下注额）
        $firstLevelPerformance = Db::name('account')
            ->alias('a')
            ->join('game_transactions gt', 'a.id = gt.user_id')
            ->where('a.p_id', $userId)
            ->where('gt.reason', 'bet') // 下注记录
            ->where('gt.amount', '>', 0)
            ->sum('gt.amount');
        
        // 二级业绩（二级下级的总下注额）
        $secondLevelPerformance = Db::name('account')
            ->alias('a')
            ->join('game_transactions gt', 'a.id = gt.user_id')
            ->where('a.team_path', 'like', $userTeamPath . $userId . '/%')
            ->where('a.team_level', $userLevel + 2)
            ->where('gt.reason', 'bet') // 下注记录
            ->where('gt.amount', '>', 0)
            ->sum('gt.amount');
        
        // 三级业绩（三级下级的总下注额）
        $thirdLevelPerformance = Db::name('account')
            ->alias('a')
            ->join('game_transactions gt', 'a.id = gt.user_id')
            ->where('a.team_path', 'like', $userTeamPath . $userId . '/%')
            ->where('a.team_level', $userLevel + 3)
            ->where('gt.reason', 'bet') // 下注记录
            ->where('gt.amount', '>', 0)
            ->sum('gt.amount');
        
        // 总佣金（所有下级用户的佣金总额）
        $totalCommission = Db::name('account_coin_log')
            ->alias('acl')
            ->join('account a', 'acl.user_id = a.id')
            ->where('a.team_path', 'like', $userTeamPath . $userId . '/%')
            ->where('acl.log_type_id', \app\api\enum\CoinLog::CommissionBet)
            ->where('acl.num', '>', 0)
            ->sum('acl.num');
        
        // 一级佣金（直接下级的佣金总额）
        $firstLevelCommission = Db::name('account_coin_log')
            ->alias('acl')
            ->join('account a', 'acl.user_id = a.id')
            ->where('a.p_id', $userId)
            ->where('acl.log_type_id', \app\api\enum\CoinLog::CommissionBet)
            ->where('acl.num', '>', 0)
            ->sum('acl.num');
        
        // 二级佣金（二级下级的佣金总额）
        $secondLevelCommission = Db::name('account_coin_log')
            ->alias('acl')
            ->join('account a', 'acl.user_id = a.id')
            ->where('a.team_path', 'like', $userTeamPath . $userId . '/%')
            ->where('a.team_level', $userLevel + 2)
            ->where('acl.log_type_id', \app\api\enum\CoinLog::CommissionBet)
            ->where('acl.num', '>', 0)
            ->sum('acl.num');
        
        // 三级佣金（三级下级的佣金总额）
        $thirdLevelCommission = Db::name('account_coin_log')
            ->alias('acl')
            ->join('account a', 'acl.user_id = a.id')
            ->where('a.team_path', 'like', $userTeamPath . $userId . '/%')
            ->where('a.team_level', $userLevel + 3)
            ->where('acl.log_type_id', \app\api\enum\CoinLog::CommissionBet)
            ->where('acl.num', '>', 0)
            ->sum('acl.num');
        
        // 未领取佣金（当前用户的佣金余额）
        $unclaimedCommission = (float)Db::name('account')->where('id', $userId)->value('commission_balance');
        
        // 一级存款金额（直接下级的充值总额）
        $firstLevelDepositAmount = Db::name('account')
            ->alias('a')
            ->join('recharge_orders ro', 'a.id = ro.user_id')
            ->where('a.p_id', $userId)
            ->where('ro.pay_status', 1)
            ->where('ro.amount', '>', 0)
            ->sum('ro.amount');
        
        // 二级存款金额（二级下级的充值总额）
        $secondLevelDepositAmount = Db::name('account')
            ->alias('a')
            ->join('recharge_orders ro', 'a.id = ro.user_id')
            ->where('a.team_path', 'like', $userTeamPath . $userId . '/%')
            ->where('a.team_level', $userLevel + 2)
            ->where('ro.pay_status', 1)
            ->where('ro.amount', '>', 0)
            ->sum('ro.amount');
        
        // 三级存款金额（三级下级的充值总额）
        $thirdLevelDepositAmount = Db::name('account')
            ->alias('a')
            ->join('recharge_orders ro', 'a.id = ro.user_id')
            ->where('a.team_path', 'like', $userTeamPath . $userId . '/%')
            ->where('a.team_level', $userLevel + 3)
            ->where('ro.pay_status', 1)
            ->where('ro.amount', '>', 0)
            ->sum('ro.amount');
        
        return [
            'total_performance' => round((float)$totalPerformance, 2),
            'first_level_performance' => round((float)$firstLevelPerformance, 2),
            'second_level_performance' => round((float)$secondLevelPerformance, 2),
            'third_level_performance' => round((float)$thirdLevelPerformance, 2),
            'total_commission' => round((float)$totalCommission, 2),
            'first_level_commission' => round((float)$firstLevelCommission, 2),
            'second_level_commission' => round((float)$secondLevelCommission, 2),
            'third_level_commission' => round((float)$thirdLevelCommission, 2),
            'unclaimed_commission' => round($unclaimedCommission, 2),
            'first_level_deposit_amount' => round($firstLevelDepositAmount, 2),
            'second_level_deposit_amount' => round($secondLevelDepositAmount, 2),
            'third_level_deposit_amount' => round($thirdLevelDepositAmount, 2)
        ];
    }
}