<?php

namespace app\common\model;

use think\Model;
use think\facade\Db;
use think\facade\Cache;

class Account extends Model
{
    protected $table = 'slot_account';

    protected $autoWriteTimestamp = true;
    
    // 隐藏敏感字段
    protected $hidden = ['password'];
    
    // 缓存标签
    public static string $cacheTag = 'account_userinfo';




    /**
     * 获取邀请码
     */
    public static function getInviteCode()
    {
        while (true) {
            $code = generateInviteCode(6);
            if (!self::where('invite_code', $code)->find()) {
                return $code;
            }
        }
    }

    // 新增前：根据 p_id 生成 team_path 与 team_level
    protected static function onBeforeInsert(Account $model)
    {
        [$teamPath, $teamLevel] = self::buildTeamPathByParentId((int)($model->p_id ?? 0));
        $model->team_path = $teamPath;
        $model->team_level = $teamLevel;
    }

    // 更新后：当 p_id 发生变化时，重建自身及所有下级的路径
    protected static function onAfterUpdate(Account $model)
    {
        $origin = $model->getOrigin();
        $newParentId = (int)($model->p_id ?? 0);
        $oldParentId = (int)($origin['p_id'] ?? 0);
        if ($newParentId !== $oldParentId) {
            self::rebuildTeamPathRecursively($model->id);
        }
    }

    /**
     * 写入后清理缓存
     */
    public static function onAfterWrite(): void
    {
        // 清理用户信息相关缓存
        Cache::tag(self::$cacheTag)->clear();
        
        // 清理特定用户的SSE缓存
        $userId = request()->userInfo['id'] ?? null;
        if ($userId) {
            Cache::delete("sse:userinfo:{$userId}");
        }
    }

    /**
     * 删除后清理缓存
     */
    public static function onAfterDelete(): void
    {
        // 清理用户信息相关缓存
        Cache::tag(self::$cacheTag)->clear();
    }

    /**
     * 关联渠道信息
     */
    public function channel()
    {
        return $this->belongsTo(\app\common\model\ChannelList::class, 'channel_id', 'id');
    }

    /**
     * 根据上级ID生成路径与层级
     * 规则：root = '/'; 子 = rtrim(parent.team_path,'/') + '/' + parent.id + '/'
     */
    private static function buildTeamPathByParentId(int $parentId): array
    {
        if ($parentId <= 0) {
            return ['/', 0];
        }
        $parent = Db::name('account')->where('id', $parentId)->field('id,team_path,team_level')->find();
        if (!$parent) {
            return ['/', 0];
        }
        $parentPath = rtrim((string)$parent['team_path'], '/');
        $teamPath = $parentPath . '/' . (int)$parent['id'] . '/';
        $teamLevel = (int)($parent['team_level'] ?? 0) + 1;
        return [$teamPath, $teamLevel];
    }

    /**
     * 递归重建指定用户及其所有下级的 team_path 与 team_level
     */
    public static function rebuildTeamPathRecursively(int $userId): void
    {
        $user = Db::name('account')->where('id', $userId)->field('id,p_id')->find();
        if (!$user) {
            return;
        }

        // 先更新自己
        [$teamPath, $teamLevel] = self::buildTeamPathByParentId((int)($user['p_id'] ?? 0));
        Db::name('account')->where('id', $userId)->update([
            'team_path'  => $teamPath,
            'team_level' => $teamLevel,
        ]);

        // 再更新所有直接下级，递归
        $childrenIds = Db::name('account')->where('p_id', $userId)->column('id');
        foreach ($childrenIds as $childId) {
            self::rebuildTeamPathRecursively((int)$childId);
        }
    }
}