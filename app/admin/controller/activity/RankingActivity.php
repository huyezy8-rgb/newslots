<?php

namespace app\admin\controller\activity;

use app\common\controller\Backend;
use app\common\model\RankingActivity as RankingActivityModel;
use think\facade\Cache;
use think\facade\Request;
use think\facade\Db;

class RankingActivity extends Backend
{
    /**
     * RankingActivity模型对象
     * @var object
     * @phpstan-var \app\common\model\RankingActivity
     */
    protected object $model;

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new RankingActivityModel();
    }

    // 配置表单页面
    public function config()
    {
        if ($this->request->isPost()) {
            $data = Request::post();
            
            // 验证必填字段
            if (empty($data['name'])) {
                $this->error('活动名称不能为空');
            }
            
            // 确保数值字段为数字
            $data['daily_pool_ratio'] = floatval($data['daily_pool_ratio'] ?? 1.00);
            $data['weekly_pool_ratio'] = floatval($data['weekly_pool_ratio'] ?? 1.00);
            $data['monthly_pool_ratio'] = floatval($data['monthly_pool_ratio'] ?? 1.00);
            $data['bet_multiple'] = floatval($data['bet_multiple'] ?? 1.00);
            $data['day_limit'] = intval($data['day_limit'] ?? 100);
            $data['week_limit'] = intval($data['week_limit'] ?? 100);
            $data['month_limit'] = intval($data['month_limit'] ?? 100);
            $data['status'] = intval($data['status'] ?? 1);
            
            // 确保奖励配置为数组
            $data['day_rewards'] = is_array($data['day_rewards'] ?? []) ? $data['day_rewards'] : [];
            $data['week_rewards'] = is_array($data['week_rewards'] ?? []) ? $data['week_rewards'] : [];
            $data['month_rewards'] = is_array($data['month_rewards'] ?? []) ? $data['month_rewards'] : [];

                // 检查是否已存在配置
                $existingActivity = $this->model->find(7);

                if ($existingActivity) {
                    // 更新现有配置 - 使用现有记录的ID
                    $data['id'] = $existingActivity->id;
                    $result = $existingActivity->save($data);
                } else {
                    // 创建新配置
                    $result = $this->model->save($data);
                }

                if ($result === false) {
                    throw new \Exception('保存失败：数据库操作返回false');
                }

                // 使用Redis缓存，删除排行榜配置缓存
                $cacheKey = 'leaderboard:config';
                Cache::store('redis')->del($cacheKey);

                $this->success('配置保存成功');

          
        }

        // 获取现有配置
        $activity = $this->model->find(7);
        $config = [];

        if ($activity) {
            $config = [
                'id' => $activity->id,
                'name' => $activity->name,
                'status' => $activity->status,
                'daily_pool_ratio' => $activity->daily_pool_ratio ?? 1.00,
                'weekly_pool_ratio' => $activity->weekly_pool_ratio ?? 1.00,
                'monthly_pool_ratio' => $activity->monthly_pool_ratio ?? 1.00,
                'day_limit' => $activity->day_limit,
                'week_limit' => $activity->week_limit,
                'month_limit' => $activity->month_limit,
                'day_rewards' => $activity->day_rewards ?? [],
                'week_rewards' => $activity->week_rewards ?? [],
                'month_rewards' => $activity->month_rewards ?? []
            ];
        }

        $this->success('获取配置成功', $config);
    }

    // 获取奖励配置模板
    public function getRewardTemplate()
    {
        $template = [
            'day' => [
                ['rank_start' => 1, 'rank_end' => 1, 'reward_percent' => 10.00],
                ['rank_start' => 2, 'rank_end' => 3, 'reward_percent' => 5.00],
                ['rank_start' => 4, 'rank_end' => 10, 'reward_percent' => 2.00],
            ],
            'week' => [
                ['rank_start' => 1, 'rank_end' => 1, 'reward_percent' => 15.00],
                ['rank_start' => 2, 'rank_end' => 3, 'reward_percent' => 8.00],
                ['rank_start' => 4, 'rank_end' => 10, 'reward_percent' => 3.00],
            ],
            'month' => [
                ['rank_start' => 1, 'rank_end' => 1, 'reward_percent' => 20.00],
                ['rank_start' => 2, 'rank_end' => 3, 'reward_percent' => 10.00],
                ['rank_start' => 4, 'rank_end' => 10, 'reward_percent' => 5.00],
            ]
        ];

        $this->success(__(''), $template);
    }
}
