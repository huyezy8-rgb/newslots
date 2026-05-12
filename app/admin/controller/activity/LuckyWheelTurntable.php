<?php

namespace app\admin\controller\activity;

use app\common\controller\Backend;
use app\admin\model\LuckyWheelTurntable as TurntableModel;
use app\common\service\LuckyWheelCacheService;
use think\facade\Db;

class LuckyWheelTurntable extends Backend
{
    /**
     * 模型类实例
     * @var object
     */
    protected object $model;

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new TurntableModel();
    }

    public function index(): void
    {
        if ($this->request->isAjax()) {
            $id = $this->request->param('id', 1);
            $turntable = $this->model->find($id);
            
            if (!$turntable) {
                $this->error('转盘不存在');
            }

            $data = [
                'id' => $turntable->id,
                'wheel_name' => $turntable->wheel_name,
                'unlock_condition' => intval($turntable->unlock_condition),
                'free_times' => $turntable->free_times,
                'max_user_times' => $turntable->max_user_times,
                'status' => $turntable->status,
                'prizes' => $turntable->getPrizesList(),
                'rules' => $turntable->getRulesList()
            ];

            $this->success('', $data);
        }
    }

    public function edit(): void
    {
        if ($this->request->isPost()) {
            $params = $this->request->post();
            
            $data = [
                'wheel_name' => $params['wheel_name'],
                'unlock_condition' => $params['unlock_condition'],
                'free_times' => $params['free_times'],
                'max_user_times' => $params['max_user_times'],
                'status' => $params['status']
            ];

            $turntable = $this->model->find($params['id']);
            if (!$turntable) {
                $this->error('转盘不存在');
            }

            $result = $turntable->save($data);
            if ($result) {
                $this->success('保存成功');
            } else {
                $this->error('保存失败');
            }
        } else {
            // 处理GET请求，返回转盘数据
            $id = $this->request->param('id', 1);
            $turntable = $this->model->find($id);
            
            if (!$turntable) {
                $this->error('转盘不存在');
            }

            // 获取奖项列表并确保是数组格式
            $prizesList = $turntable->getPrizesList();
            if (!is_array($prizesList)) {
                $prizesList = [];
            }
            
            // 获取规则列表并确保是数组格式
            $rulesList = $turntable->getRulesList();
            if (!is_array($rulesList)) {
                $rulesList = [];
            }

            $data = [
                'id' => $turntable->id,
                'wheel_name' => $turntable->wheel_name,
                'unlock_condition' => intval($turntable->unlock_condition),
                'free_times' => $turntable->free_times,
                'max_user_times' => $turntable->max_user_times,
                'status' => $turntable->status,
                'prizes_list' => $prizesList, // 已经是数组格式
                'rules_list' => $rulesList   // 已经是数组格式
            ];

            $this->success('', $data);
        }
    }

    public function updatePrizes(): void
    {
        if ($this->request->isPost()) {
            $params = $this->request->post();
            
            $turntable = $this->model->find($params['id']);
            if (!$turntable) {
                $this->error('转盘不存在');
            }

            // 验证奖项数据
            $prizes = $params['prizes'] ?? [];
            if (empty($prizes)) {
                $this->error('奖项不能为空');
            }

            // 验证奖项数据格式
            foreach ($prizes as $prize) {
                if (!isset($prize['title']) || !isset($prize['amount']) || !isset($prize['probability']) || !isset($prize['sort'])) {
                    $this->error('奖项数据格式不正确');
                }
                
                if (floatval($prize['probability']) < 0) {
                    $this->error('奖项概率不能为负数');
                }
            }

            $turntable->prizes = $prizes;
            $result = $turntable->save();
            
            if ($result) {
                // 清除转盘相关缓存
                $this->clearTurntableCache($params['id']);
                $this->success('奖项配置保存成功');
            } else {
                $this->error('奖项配置保存失败');
            }
        }
    }

    public function updateRules(): void
    {
        if ($this->request->isPost()) {
            $params = $this->request->post();
            
            $turntable = $this->model->find($params['id']);
            if (!$turntable) {
                $this->error('转盘不存在');
            }

            $rules = $params['rules'] ?? [];
            $turntable->setRulesList($rules);
            $result = $turntable->save();
            
            if ($result) {
                // 清除转盘相关缓存
                $this->clearTurntableCache($params['id']);
                $this->success('规则配置保存成功');
            } else {
                $this->error('规则配置保存失败');
            }
        }
    }

    /**
     * 获取用户转盘使用情况
     */
    public function getUserUsage(): void
    {
        if ($this->request->isAjax()) {
            $wheelId = $this->request->param('wheel_id');
            $userId = $this->request->param('user_id');
            
            $turntable = $this->model->find($wheelId);
            if (!$turntable) {
                $this->error('转盘不存在');
            }

            $data = [
                'max_times' => $turntable->max_user_times,
                'used_times' => $turntable->getUserUsedTimes($userId),
                'remaining_times' => $turntable->getUserRemainingTimes($userId),
                'can_use' => $turntable->canUserUse($userId)
            ];

            $this->success('', $data);
        }
    }

    /**
     * 获取转盘统计信息
     */
    public function getStatistics(): void
    {
        if ($this->request->isAjax()) {
            $wheelId = $this->request->param('wheel_id');
            
            $turntable = $this->model->find($wheelId);
            if (!$turntable) {
                $this->error('转盘不存在');
            }

            // 获取总使用次数
            $totalUsage = Db::name('lucky_wheel_logs')
                ->where('wheel_id', $wheelId)
                ->count();

            // 获取今日使用次数
            $todayUsage = Db::name('lucky_wheel_logs')
                ->where('wheel_id', $wheelId)
                ->whereTime('createtime', 'today')
                ->count();

            // 获取总中奖金额
            $totalAmount = Db::name('lucky_wheel_logs')
                ->where('wheel_id', $wheelId)
                ->sum('prize_amount');

            $data = [
                'total_usage' => $totalUsage,
                'today_usage' => $todayUsage,
                'total_amount' => $totalAmount,
                'max_user_times' => $turntable->max_user_times
            ];

            $this->success('', $data);
        }
    }
    
    /**
     * 清除转盘配置缓存
     */
    private function clearTurntableCache($turntableId): void
    {
        try {
            // 只清除转盘配置缓存
            LuckyWheelCacheService::clearWheelsCache();
            
        } catch (\Exception $e) {
            // 记录错误日志，但不影响主流程
            \think\facade\Log::error('清除转盘配置缓存失败: ' . $e->getMessage());
        }
    }
} 