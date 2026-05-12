<?php

namespace app\admin\validate\account;

use think\Validate;
use think\facade\Db;

class Account extends Validate
{
    protected $failException = true;

    /**
     * 验证规则
     */
    protected $rule = [
        'p_id' => ['checkParentValid'],
        'rebate_rate' => ['checkRebateRate'],
    ];

    /**
     * 提示消息
     */
    protected $message = [
        'p_id.checkParentValid' => '上级设置不合法',
        'rebate_rate.checkRebateRate' => '返佣比例不合法',
    ];

    /**
     * 验证场景
     */
    protected $scene = [
        'add'  => ['p_id', 'rebate_rate'],
        'edit' => ['p_id', 'rebate_rate'],
    ];

    /**
     * 自定义验证：p_id 不能为自身，且不能为自己的下级
     */
    protected function checkParentValid($value, $rule, $data = [])
    {
        $newPid = intval($value ?? 0);
        $selfId = intval($data['id'] ?? 0);
        if ($newPid <= 0 || $selfId <= 0) {
            return true; // 0 或未设置，直接通过
        }
        if ($newPid === $selfId) {
            return 'p_id 不能为本人';
        }
        // 读取自身 team_path，构造子树前缀，判断 newPid 是否在子树中
        $teamPath = (string)Db::name('account')->where('id', $selfId)->value('team_path');
        $prefix = rtrim($teamPath, '/') . '/' . $selfId . '/';
        $isDescendant = Db::name('account')
            ->where('id', $newPid)
            ->whereLike('team_path', $prefix . '%')
            ->find();
        if ($isDescendant) {
            return 'p_id 不能为自己的下级';
        }
        return true;
    }

    /**
     * 自定义验证：rebate_rate 允许为 0，允许空；若提供则必须为数字，范围不限
     */
    protected function checkRebateRate($value)
    {
        if ($value === '' || $value === null) {
            return true;
        }
        if (!is_numeric($value)) {
            return '返佣比例必须是数字';
        }
        return true;
    }

}
