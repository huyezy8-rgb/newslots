<?php

namespace app\common\model\slot\game;

use think\facade\Cache;
use think\Model;

/**
 * Brand
 */
class Brand extends Model
{
    public static string $cacheTag = 'game_lists';
    // 表名
    protected $name = 'game_brand';

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
    public static function onAfterWrite(): void
    {
        // 清理配置缓存
        Cache::tag(self::$cacheTag)->clear();
    }

}