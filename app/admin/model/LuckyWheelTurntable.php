<?php

namespace app\admin\model;

use think\Model;

class LuckyWheelTurntable extends Model
{
    protected $name = 'lucky_wheel_turntable';
    
    // 设置字段信息
    protected $schema = [
        'id'                => 'int',
        'wheel_name'        => 'string',
        'unlock_condition'  => 'decimal',
        'free_times'        => 'int',
        'max_user_times'    => 'int',
        'prizes'            => 'text',
        'rules'             => 'text',
        'status'            => 'int',
        'createtime'        => 'int',
        'updatetime'        => 'int',
    ];

    // 自动时间戳
    protected $autoWriteTimestamp = true;
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    // JSON字段
    protected $json = ['prizes', 'rules'];

    // 状态获取器
    public function getStatusTextAttr($value, $data)
    {
        $status = [0 => '禁用', 1 => '启用'];
        return $status[$data['status']] ?? '未知';
    }

    // 获取奖项列表
    public function getPrizesList()
    {
        $prizes = $this->prizes ?: [];
        
        // 如果是对象，转换为数组
        if (is_object($prizes)) {
            $prizes = (array) $prizes;
        }
        
        if (empty($prizes)) {
            // 默认8个奖项
            $prizes = [
                ['title' => '谢谢参与', 'amount' => 0, 'probability' => 0.3, 'sort' => 1],
                ['title' => '1元', 'amount' => 1, 'probability' => 0.2, 'sort' => 2],
                ['title' => '2元', 'amount' => 2, 'probability' => 0.15, 'sort' => 3],
                ['title' => '5元', 'amount' => 5, 'probability' => 0.1, 'sort' => 4],
                ['title' => '10元', 'amount' => 10, 'probability' => 0.08, 'sort' => 5],
                ['title' => '20元', 'amount' => 20, 'probability' => 0.05, 'sort' => 6],
                ['title' => '50元', 'amount' => 50, 'probability' => 0.02, 'sort' => 7],
                ['title' => '100元', 'amount' => 100, 'probability' => 0.01, 'sort' => 8],
            ];
        }
        // 确保返回的是索引数组
        return array_values($prizes);
    }

    // 获取规则列表
    public function getRulesList()
    {
        $rules = $this->rules ?: [];
        
        // 如果是对象，转换为数组
        if (is_object($rules)) {
            $rules = (array) $rules;
        }
        
        // 确保返回的是索引数组
        return array_values($rules);
    }

    // 设置奖项列表
    public function setPrizesList($prizes)
    {
        $this->prizes = $prizes;
        return $this;
    }

    // 设置规则列表
    public function setRulesList($rules)
    {
        $this->rules = $rules;
        return $this;
    }

    // 添加奖项
    public function addPrize($prize)
    {
        $prizes = $this->getPrizesList();
        $prizes[] = $prize;
        $this->setPrizesList($prizes);
        return $this;
    }

    // 添加规则
    public function addRule($rule)
    {
        $rules = $this->getRulesList();
        $rules[] = $rule;
        $this->setRulesList($rules);
        return $this;
    }

    // 更新奖项
    public function updatePrize($index, $prize)
    {
        $prizes = $this->getPrizesList();
        if (isset($prizes[$index])) {
            $prizes[$index] = array_merge($prizes[$index], $prize);
            $this->setPrizesList($prizes);
        }
        return $this;
    }

    // 更新规则
    public function updateRule($index, $rule)
    {
        $rules = $this->getRulesList();
        if (isset($rules[$index])) {
            $rules[$index] = array_merge($rules[$index], $rule);
            $this->setRulesList($rules);
        }
        return $this;
    }

    // 删除奖项
    public function deletePrize($index)
    {
        $prizes = $this->getPrizesList();
        if (isset($prizes[$index])) {
            unset($prizes[$index]);
            $prizes = array_values($prizes); // 重新索引
            $this->setPrizesList($prizes);
        }
        return $this;
    }

    // 删除规则
    public function deleteRule($index)
    {
        $rules = $this->getRulesList();
        if (isset($rules[$index])) {
            unset($rules[$index]);
            $rules = array_values($rules); // 重新索引
            $this->setRulesList($rules);
        }
        return $this;
    }

    /**
     * 获取用户已使用次数
     * @param int $userId 用户ID
     * @return int
     */
    public function getUserUsedTimes($userId)
    {
        return $this->hasMany(LuckyWheelLogs::class, 'wheel_id', 'id')
            ->where('user_id', $userId)
            ->count();
    }

    /**
     * 检查用户是否可以继续使用转盘
     * @param int $userId 用户ID
     * @return bool
     */
    public function canUserUse($userId)
    {
        // 如果最大次数为0，表示无限制
        if ($this->max_user_times == 0) {
            return true;
        }
        
        $usedTimes = $this->getUserUsedTimes($userId);
        return $usedTimes < $this->max_user_times;
    }

    /**
     * 获取用户剩余次数
     * @param int $userId 用户ID
     * @return int
     */
    public function getUserRemainingTimes($userId)
    {
        // 如果最大次数为0，返回-1表示无限制
        if ($this->max_user_times == 0) {
            return -1;
        }
        
        $usedTimes = $this->getUserUsedTimes($userId);
        $remaining = $this->max_user_times - $usedTimes;
        return max(0, $remaining);
    }
} 