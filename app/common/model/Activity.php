<?php

namespace app\common\model;

use think\Model;

/**
 * Activity
 */
class Activity extends Model
{
    // 表名
    protected $name = 'activity';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

    protected static function onAfterInsert($model): void
    {
        if (is_null($model->sort)) {
            $pk = $model->getPk();
            if (strlen($model[$pk]) >= 19) {
                $model->where($pk, $model[$pk])->update(['sort' => $model->count()]);
            } else {
                $model->where($pk, $model[$pk])->update(['sort' => $model[$pk]]);
            }
        }
    }
}