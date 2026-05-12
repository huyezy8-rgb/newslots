<?php

namespace app\common\service;

use think\facade\Db;

class ActivitySyncService
{
    /**
     * 同步活动配置到 slot_activity 表
     * @param string $type 活动类型（需与业务约定一致，如 'chest'、'270' 等）
     * @param string $name 活动名称
     * @param array $config 配置数组（将以 JSON 存储）
     */
    public static function sync(string $type, string $name, array $config): void
    {
        $now = time();
        $payload = [
            'name' => $name,
            'config' => json_encode($config, JSON_UNESCAPED_UNICODE),
            'status' => 1,
            'update_time' => $now,
        ];

        $existsId = Db::name('activity')->where('type', $type)->value('id');
        if ($existsId) {
            Db::name('activity')->where('id', $existsId)->update($payload);
        } else {
            $payload['type'] = $type;
            $payload['create_time'] = $now;
            Db::name('activity')->insert($payload);
        }
    }
}


