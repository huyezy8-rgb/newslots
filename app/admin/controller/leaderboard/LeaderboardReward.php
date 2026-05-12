<?php

namespace app\admin\controller\leaderboard;

use app\common\controller\Backend;
use think\facade\Db;

/**
 * 排行榜奖励发放记录
 */
class LeaderboardReward extends Backend
{
    protected object $model;

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\common\model\LeaderboardRewardLog();
    }

    /**
     * 排行榜奖励发放记录列表
     */
    public function index(): void
    {
        $page = $this->request->get('page', 1);
        $limit = $this->request->get('limit', 10);
        $type = $this->request->get('type', '');
        $channel_id = $this->request->get('channel_id', null);
        
        // 获取当前登录管理员绑定的渠道id
        if ($this->getCurrentAdminChannelId() !== null) {
            $channel_id = $this->getCurrentAdminChannelId();
        }

        $where = [];
        if ($type) {
            $where[] = ['type', '=', $type];
        }
        if ($channel_id !== null && $channel_id !== '') {
            $where[] = ['channel_id', '=', $channel_id];
        }

        $list = $this->model
            ->where($where)
            ->order('create_time', 'desc')
            ->paginate([
                'list_rows' => $limit,
                'page' => $page
            ]);

        // 处理数据，添加周期信息
        $items = $list->items();
        foreach ($items as &$item) {
            $item['period'] = $this->getPeriodText($item['type'], $item['create_time']);
            
            // 手动添加渠道名称
            if (empty($item['channel_id'])) {
                $item['channel_name'] = '全部渠道';
            } else {
                $channel = \think\facade\Db::name('channel_list')->where('id', $item['channel_id'])->find();
                $item['channel_name'] = $channel ? $channel['name'] : '未知渠道';
            }
            
            // 手动计算成功率
            $total = $item['success_count'] + $item['fail_count'];
            if ($total == 0) {
                $item['success_rate'] = '0%';
            } else {
                $item['success_rate'] = round($item['success_count'] / $total * 100, 2) . '%';
            }
        }

        $this->success('success', [
            'list' => $items,
            'total' => $list->total(),
            'page' => $page,
            'limit' => $limit
        ]);
    }

    /**
     * 查看榜单详情
     */
    public function detail(): void
    {
        $id = $this->request->param('id');
        
        // 调试信息
        \think\facade\Log::info('Detail request params: ' . json_encode($this->request->param()));
        \think\facade\Log::info('Detail request post data: ' . json_encode($this->request->post()));
        
        if (!$id) {
            $this->error('参数错误');
        }

        $rewardLog = $this->model->find($id);
        if (!$rewardLog) {
            $this->error('记录不存在');
        }

        // 检查渠道权限
        if ($this->getCurrentAdminChannelId() !== null && $rewardLog->channel_id != $this->getCurrentAdminChannelId()) {
            $this->error('无权限查看');
        }

        // 根据奖励发放时间获取对应周期的排行榜数据
        $leaderboardData = $this->getLeaderboardData($rewardLog);
        
        // 处理奖励记录的渠道名称和成功率
        $rewardLogData = $rewardLog->toArray();
        
        // 处理渠道名称
        if (empty($rewardLogData['channel_id'])) {
            $rewardLogData['channel_name'] = '全部渠道';
        } else {
            $channel = Db::name('channel_list')->where('id', $rewardLogData['channel_id'])->find();
            $rewardLogData['channel_name'] = $channel ? $channel['name'] : '未知渠道';
        }
        
        // 计算成功率
        $total = $rewardLogData['success_count'] + $rewardLogData['fail_count'];
        if ($total == 0) {
            $rewardLogData['success_rate'] = '0%';
        } else {
            $rewardLogData['success_rate'] = round($rewardLogData['success_count'] / $total * 100, 2) . '%';
        }
        
        $this->success('success', [
            'reward_log' => $rewardLogData,
            'leaderboard_data' => $leaderboardData
        ]);
    }

    /**
     * 根据奖励发放记录获取对应周期的排行榜数据
     */
    private function getLeaderboardData($rewardLog)
    {
        $rewardLogId = $rewardLog->id;
        $type = $rewardLog->type;
        $createTime = $rewardLog->create_time;
        $channelId = $rewardLog->channel_id;

        // 优先通过 reward_log_id 关联查询，确保获取本次发放的实际数据
        $leaderboardData = Db::name('leaderboard_stats')
            ->where('reward_log_id', $rewardLogId)
            ->order('rank', 'asc')
            ->order('total_bet', 'desc')
            ->select();

        // 如果没有通过 reward_log_id 找到数据（可能是旧数据），使用周期查询作为备选
        if (empty($leaderboardData)) {
            // 根据类型和时间确定周期（奖励发放时间的前一天/周/月）
            $period = $this->getRewardPeriod($type, $createTime);
            
            $leaderboardData = Db::name('leaderboard_stats')
                ->where('type', $type)
                ->where('period', $period)
                ->where('channel_id', $channelId)
                ->where('reward_amount', '>', 0) // 只显示有奖励的记录
                ->order('rank', 'asc')
                ->order('total_bet', 'desc')
                ->select();
        }

        // 格式化数据
        $result = [];
        foreach ($leaderboardData as $item) {
            // 处理渠道名称
            $channelName = '全部渠道';
            if (!empty($item['channel_id'])) {
                $channel = Db::name('channel_list')->where('id', $item['channel_id'])->find();
                $channelName = $channel ? $channel['name'] : '未知渠道';
            }
            
            // 计算成功率（基于奖励金额是否大于0）
            $successRate = '0%';
            if ($item['reward_amount'] > 0) {
                $successRate = '100%';
            }
            
            // 处理排名：如果rank为0或null，根据奖励金额判断
            $rank = isset($item['rank']) ? (int)$item['rank'] : null;
            
            // 如果rank为0但有奖励金额，说明数据有问题，返回null让前端显示-
            // 如果rank为0且没有奖励金额，可能是未发放奖励的记录，也返回null
            if ($rank === 0) {
                $rank = null;
            }
            
            $result[] = [
                'rank' => $rank,
                'user_id' => $item['user_id'],
                'nickname' => $item['nickname'] ?: '用户' . $item['user_id'],
                'total_bet' => $item['total_bet'],
                'reward_ratio' => $item['reward_ratio'],
                'reward_amount' => $item['reward_amount'],
                'reward_remark' => $item['reward_remark'],
                'channel_name' => $channelName,
                'success_rate' => $successRate
            ];
        }

        return $result;
    }

    /**
     * 获取奖励发放对应的周期（基于发放时间的前一天/周/月）
     * @param string $type 排行榜类型
     * @param int $timestamp 奖励发放时间戳
     * @return string
     */
    private function getRewardPeriod(string $type, int $timestamp): string
    {
        switch ($type) {
            case 'daily':
                // 日榜：获取昨天的数据
                return date('Y-m-d', strtotime('-1 day', $timestamp));
            case 'weekly':
                // 周榜：获取上周的数据（上周一的日期，格式：Y-m-d）
                $lastWeekStart = strtotime('last monday', $timestamp);
                return date('Y-m-d', $lastWeekStart);
            case 'monthly':
                // 月榜：获取上月的数据
                return date('Y-m', strtotime('-1 month', $timestamp));
            default:
                return date('Y-m-d', $timestamp);
        }
    }


    /**
     * 获取奖励配置
     */
    private function getRewardConfig($type)
    {
        // 这里可以从配置表或配置文件中获取
        $configs = [
            'daily' => [
                1 => 10, 2 => 8, 3 => 6, 4 => 4, 5 => 2,
                6 => 1, 7 => 1, 8 => 1, 9 => 1, 10 => 1
            ],
            'weekly' => [
                1 => 15, 2 => 12, 3 => 10, 4 => 8, 5 => 6,
                6 => 4, 7 => 3, 8 => 2, 9 => 2, 10 => 2
            ],
            'monthly' => [
                1 => 20, 2 => 18, 3 => 15, 4 => 12, 5 => 10,
                6 => 8, 7 => 6, 8 => 4, 9 => 3, 10 => 2
            ]
        ];

        return $configs[$type] ?? [];
    }

    /**
     * 获取奖励比例
     */
    private function getRewardRatio($rank, $config)
    {
        return $config[$rank] ?? 0;
    }

    /**
     * 获取周期文本
     */
    private function getPeriodText($type, $timestamp)
    {
        // 发放奖励的时间是过了0点发的，所以周期应该是前一天/前一周/前一月
        switch ($type) {
            case 'daily':
                // 日榜：发放时间的前一天
                return date('Y-m-d', strtotime('-1 day', $timestamp));
            case 'weekly':
                // 周榜：发放时间的前一周的周一（格式：Y-m-d）
                $lastWeekStart = strtotime('last monday', $timestamp);
                return date('Y-m-d', $lastWeekStart);
            case 'monthly':
                // 月榜：发放时间的前一个月
                return date('Y-m', strtotime('-1 month', $timestamp));
            default:
                return date('Y-m-d', $timestamp);
        }
    }
}
