<?php

namespace app\common\model;

use think\Model;

/**
 * Banner
 */
class Banner extends Model
{
    // 表名
    protected $name = 'banner';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

    // 追加属性
    protected $append = [
        'channel',
    ];

    // 字段类型转换
    protected $type = [
        'start_time' => 'timestamp:Y-m-d H:i:s',
        'end_time'   => 'timestamp:Y-m-d H:i:s',
    ];


    public function getChannelAttr($value, $row): array
    {
        return [
            'name' => \app\admin\model\channel\Listsss::whereIn('id', $row['channel_ids'])->column('name'),
        ];
    }

    public function getChannelIdsAttr($value): array
    {
        if ($value === '' || $value === null) return [];
        if (!is_array($value)) {
            return explode(',', $value);
        }
        return $value;
    }

    public function setChannelIdsAttr($value): string
    {
        return is_array($value) ? implode(',', $value) : $value;
    }
}