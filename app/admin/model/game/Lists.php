<?php

namespace app\admin\model\game;

use app\admin\model\jackpot\invest\Log;
use think\facade\Cache;
use think\Model;

/**
 * Lists
 */
class Lists extends Model
{
    public static string $cacheTag = 'game_lists';
    // 表名
    protected $name = 'game_lists';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;



    public static function onAfterWrite(): void
    {
        // 清理配置缓存
        \think\facade\Log::info("清除游戏列表缓存");
        Cache::tag(self::$cacheTag)->clear();
    }


}