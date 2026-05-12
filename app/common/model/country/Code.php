<?php

namespace app\common\model\country;

use think\Model;

/**
 * Code
 */
class Code extends Model
{
    // 表名
    protected $name = 'country_code';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;


    /**
     * 获取区号时自动添加 + 号前缀
     */
    public function getCodeAttr($value)
    {
        if (!empty($value) && strpos($value, '+') !== 0) {
            return '+' . $value;
        }
        return $value;
    }

    /**
     * 获取图片完整URL
     */
    public function getImageAttr($value)
    {
        return full_url('', $value ? true:false, $value ?? '');
    }

}