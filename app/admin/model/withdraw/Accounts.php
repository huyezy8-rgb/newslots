<?php

namespace app\admin\model\withdraw;

use think\Model;
use think\model\relation\HasOne;

/**
 * Accounts
 */
class Accounts extends Model
{
    // 表名
    protected $name = 'withdraw_accounts';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

    /**
     * 关联用户信息
     */
    public function user(): HasOne
    {
        return $this->hasOne('app\common\model\Account', 'id', 'user_id');
    }
}