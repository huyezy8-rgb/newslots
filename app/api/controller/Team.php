<?php

namespace app\api\controller;

use app\common\service\TeamPathService;
use app\common\service\AccountService;
use think\facade\Db;

/**
 * 团队管理控制器
 */
class Team extends Base
{
    protected array $noNeedLogin = [];

    /**
     * 获取团队信息
     */
    public function index()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Request method must be POST'));
        }

        $userId = $this->userInfo['id'];
        $teamPathService = new TeamPathService();

        try {
            // 获取用户信息
            $user = Db::name('account')->where('id', $userId)->find();
            if (!$user) {
                $this->error(__('User not found'));
            }

            // 获取佣金统计信息
            $commissionStats = $this->getCommissionStatistics($userId);

            // 获取团队统计
            $teamStats = $this->getTeamStatistics($userId);

            $data = [
                'user_info' => [
                    'id' => $user['id'],
                    'name' => $user['name'] ?? '',
                    'nickname' => $user['nickname'] ?? '',
                    'rebate_rate' => floatval($user['rebate_rate'] ?? 0),
                    'commission_balance' => floatval($user['commission_balance'] ?? 0),
                    'team_path' => $user['team_path'] ?? '',
                    'team_level' => intval($user['team_level'] ?? 0),
                    'p_id' => intval($user['p_id'] ?? 0)
                ],
                'commission_stats' => $commissionStats,
                'team_stats' => $teamStats
            ];

        } catch (\Exception $e) {
            $this->error(__('Failed to get team information') . ': ' . $e->getMessage());
        }
        $this->success(__('Team information retrieved successfully'), $data);
    }


    /**
     * 获取代理列表（合并搜索功能）
     */
    public function agents()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Request method must be POST'));
        }

        $userId = $this->userInfo['id'];
        $keyword = $this->request->post('keyword', '');
        $page = max(1, intval($this->request->post('page', 1)));
        $pageSize = max(1, intval($this->request->post('page_size', 10)));

        try {
            // 构建查询条件
            $query = Db::name('account')->where('p_id', $userId);
            
            // 如果有搜索关键词，添加搜索条件
            if (!empty($keyword)) {
                $query->where(function($subQuery) use ($keyword) {
                    $subQuery->where('id', $keyword)
                        ->whereOr('name', 'like', '%' . $keyword . '%')
                        ->whereOr('nickname', 'like', '%' . $keyword . '%');
                });
            }

            // 获取总数
            $total = $query->count();

            // 分页查询
            $offset = ($page - 1) * $pageSize;
            $agents = $query->field('id, name, nickname, rebate_rate, commission_balance, create_time')
                ->limit($offset, $pageSize)
                ->order('create_time', 'desc')
                ->select()
                ->toArray();

            // 为每个代理添加佣金数据
            foreach ($agents as &$agent) {
                $agentData = $this->getAgentCommissionData($agent['id'],$userId);
                $agent = array_merge($agent, $agentData);
            }

            $data = [
                'list' => $agents,
                'total' => $total,
                'page' => $page,
                'page_size' => $pageSize,
                'pages' => $pageSize > 0 ? ceil($total / $pageSize) : 0,
                'keyword' => $keyword,
                'has_search' => !empty($keyword)
            ];
            
        } catch (\Exception $e) {
            $this->error(__('Failed to get agent list') . ': ' . $e->getMessage());
        }
        $this->success(__('Agent list retrieved successfully'), $data);
    }

    /**
     * 获取佣金统计信息
     */
    private function getCommissionStatistics($userId)
    {
        // 获取总佣金收入
        $totalCommission = Db::name('team_commission_log')
            ->where('user_id', $userId)
            ->sum('commission');

        // 获取总投注额（通过下级投注产生的佣金）
        $totalBetAmount = Db::name('team_commission_log')
            ->where('user_id', $userId)
            ->sum('bet_amount');

        // 获取代理数量（直属下级）
        $agentCount = Db::name('account')
            ->where('p_id', $userId)
            ->count();

        return [
            'total_commission' => floatval($totalCommission),
            'total_bet_amount' => floatval($totalBetAmount), 
            'agent_count' => intval($agentCount)
        ];
    }

    /**
     * 获取团队统计信息
     */
    private function getTeamStatistics($userId)
    {
        $teamPathService = new TeamPathService();
        
        // 获取团队总人数
        $teamUserCount = $teamPathService->getTeamUserCount($userId);
        
        // 获取团队总充值
        $teamRecharge = $teamPathService->getTeamRechargeAmount($userId);
        
        return [
            'team_user_count' => intval($teamUserCount),
            'team_recharge' => floatval($teamRecharge)
        ];
    }



    /**
     * 获取单个代理的佣金数据
     */
    private function getAgentCommissionData($agentId,$userId)
    {
        // 获取该代理的投注额
        $betAmount = Db::name('team_commission_log')
            ->where('source_user_id', $agentId)
            ->where('user_id', $userId)
            ->sum('bet_amount');

        // 获取从该代理获得的佣金
        $commission = Db::name('team_commission_log')
            ->where('source_user_id', $agentId)
            ->where('user_id', $userId)
            ->sum('commission');

        // 获取下级佣金（该代理作为user_id获得的佣金）
        $lowerCommission = Db::name('team_commission_log')
            ->where('source_user_id', $agentId)
            ->where('user_id', $userId)
            ->sum('commission');

        return [
            'bet_amount' => floatval($betAmount),
            'commission_from_agent' => floatval($commission),
            'lower_level_commission' => floatval($lowerCommission)
        ];
    }



    /**
     * 调整代理返佣比例
     */
    public function adjustRebateRate()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Request method must be POST'));
        }
      
        // 获取参数（支持 multipart/form-data 和 JSON）
        $agentId = intval($this->request->post('agent_id', 0));
        $newRate = floatval($this->request->post('rebate_rate', 0));
        $userId = $this->userInfo['id'];

        // 参数验证
        if ($agentId <= 0) {
            $this->error(__('Invalid agent ID'));
        }
        
        if ($newRate < 0 || $newRate > 100) {
            $this->error(__('Rebate rate must be between 0 and 100'));
        }

        // 初始化变量
        $updatedAgent = null;

        try {
            // 验证代理是否为当前用户的直属下级
            $agent = Db::name('account')
                ->where('id', $agentId)
                ->where('p_id', $userId)
                ->find();

            if (!$agent) {
                throw new \Exception(__('Agent not found or not your direct subordinate'));
            }

            // 获取代理当前返佣比例
            $currentAgentRate = floatval($agent['rebate_rate'] ?? 0);
            
            // 检查是否只能调高不能降低
            if ($newRate < $currentAgentRate) {
                throw new \Exception(__('Rebate rate can only be increased, not decreased') . ' (' . __('Current') . ': ' . $currentAgentRate . '%, ' . __('New value') . ': ' . $newRate . '%)');
            }

            // 获取当前用户的返佣比例
            $currentUser = Db::name('account')
                ->where('id', $userId)
                ->field('rebate_rate')
                ->find();

            // 只有当前用户有返佣比例且大于0时，才进行比例限制检查
            if ($currentUser && floatval($currentUser['rebate_rate']) > 0 && $newRate > floatval($currentUser['rebate_rate'])) {
                throw new \Exception(__('Agent rebate rate cannot exceed your own rate') . ' (' . $currentUser['rebate_rate'] . '%)');
            }

            // 更新返佣比例
            $updateResult = Db::name('account')
                ->where('id', $agentId)
                ->update([
                    'rebate_rate' => $newRate,
                    'update_time' => time()
                ]);

            if (!$updateResult) {
                throw new \Exception(__('Failed to update rebate rate'));
            }

            // 返回更新后的代理信息
            $updatedAgent = Db::name('account')
                ->where('id', $agentId)
                ->field('id, name, nickname, rebate_rate')
                ->find();

        } catch (\Exception $e) {
            $this->error(__('Failed to adjust rebate rate') . ': ' . $e->getMessage());
        }

        // 在 try-catch 外面返回成功
        $this->success(__('Rebate rate adjusted successfully'), $updatedAgent);
    }

    /**
     * 佣金提取到余额（全部提取）
     */
    public function withdrawCommission()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Request method must be POST'));
        }

        $userId = $this->userInfo['id'];

        // 启动数据库事务
        Db::startTrans();
        try {
            // 获取用户当前余额信息
            $user = Db::name('account')
                ->where('id', $userId)
                ->field('commission_balance, recharge_wallet')
                ->find();

            if (!$user) {
                throw new \Exception(__('User not found'));
            }

            $commissionBalance = floatval($user['commission_balance']);
            $currentBalance = floatval($user['recharge_wallet']);

            // 检查佣金余额是否大于0
            if ($commissionBalance <= 0) {
                throw new \Exception(__('Insufficient commission balance'));
            }

            // 使用AccountService处理余额变动
            $accountService = new AccountService();

            // 1. 从佣金余额扣除（全部）
            $accountService->decreaseBalance(
                $userId, 
                $commissionBalance, 
                2, // commission_balance
                \app\api\enum\CoinLog::CommissionWithdraw, 
                '佣金提取到余额（扣除）'
            );

            // 2. 增加到充值钱包
            $accountService->increaseBalance(
                $userId, 
                $commissionBalance, 
                1, // recharge_wallet
                \app\api\enum\CoinLog::CommissionWithdraw, 
                '佣金提取到余额（增加）'
            );

            // 提交事务
            Db::commit();

            $data = [
                'withdraw_amount' => $commissionBalance,
                'new_commission_balance' => 0.00,
                'new_balance' => $currentBalance + $commissionBalance
            ];

        } catch (\Exception $e) {
            Db::rollback();
            $this->error(__('Commission withdraw failed') . ': ' . $e->getMessage());
        }

        $this->success(__('Commission withdraw successful'), $data);
    }

    /**
     * 获取佣金提取记录
     */
    public function withdrawLog()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Request method must be POST'));
        }

        $userId = $this->userInfo['id'];
        $page = max(1, intval($this->request->post('page', 1)));
        $pageSize = max(1, min(100, intval($this->request->post('page_size', 10))));

        try {
            // 查询佣金提取记录（从commission_balance扣除的记录）
            $query = Db::name('account_coin_log')
                ->where('user_id', $userId)
                ->where('log_type_id', \app\api\enum\CoinLog::CommissionWithdraw)
                ->where('wallet_type', 2) // commission_balance
                ->where('num', '<', 0); // 扣除记录为负数

            // 获取总数
            $total = $query->count();

            // 分页查询
            $offset = ($page - 1) * $pageSize;
            $logs = $query->field('id, old_num, num, new_num, note, create_time')
                ->limit($offset, $pageSize)
                ->order('create_time', 'desc')
                ->select()
                ->toArray();

            // 格式化数据
            foreach ($logs as &$log) {
                $log['amount'] = abs(floatval($log['num'])); // 提取金额（取绝对值）
                $log['status'] = 1; // 记录存在即为成功
            
                
                // 移除不需要的字段
                unset($log['old_num'], $log['num'], $log['new_num'], $log['note']);
            }

            $data = [
                'list' => $logs,
                'total' => $total,
                'page' => $page,
                'page_size' => $pageSize,
                'pages' => $pageSize > 0 ? ceil($total / $pageSize) : 0
            ];

        } catch (\Exception $e) {
            $this->error(__('Failed to get withdraw log') . ': ' . $e->getMessage());
        }

        $this->success(__('Withdraw log retrieved successfully'), $data);
    }
} 