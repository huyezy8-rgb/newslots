<?php

namespace app\admin\model\channel;

use think\Model;

/**
 * Listsss
 */
class Listsss extends Model
{
    // 表名
    protected $name = 'channel_list';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;
    protected $updateTime = false;


    public function getExperienceGoldLimitAttr($value): ?float
    {
        return is_null($value) ? null : (float)$value;
    }
}