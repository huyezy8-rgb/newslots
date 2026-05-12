<?php

namespace app\common\model;

use think\Model;
use think\facade\Db;

class PddProgressGroup extends Model
{
    protected $name = 'pdd_progress_groups';
    protected $autoWriteTimestamp = true;
    protected $json = ['invited_users', 'qualified_users'];

    /**
     * 为指定进度创建或获取进度组
     */
    public static function getOrCreateGroup(int $userId, int $progressId): array
    {
        $group = self::where('user_id', $userId)
            ->where('progress_id', $progressId)
            ->find();
            
        if (!$group) {
            $group = self::create([
                'user_id' => $userId,
                'progress_id' => $progressId,
                'invited_users' => [],
                'qualified_users' => [],
                'has_qualified_user' => 0, // 达标人数，初始为0
            ]);
        }
        
        return $group->toArray();
    }

    /**
     * 添加邀请用户到进度组
     */
    public static function addInvitedUser(int $userId, int $progressId, int $invitedUserId): bool
    {
        $group = self::where('user_id', $userId)
            ->where('progress_id', $progressId)
            ->find();
            
        if (!$group) {
            return false;
        }
        
        $invitedUsers = $group->invited_users ?: [];
        if (is_string($invitedUsers)) {
            $decoded = json_decode($invitedUsers, true);
            $invitedUsers = is_array($decoded) ? $decoded : [];
        } elseif (is_object($invitedUsers)) {
            $invitedUsers = (array)$invitedUsers;
        }
        // 确保是索引数组
        if (!is_array($invitedUsers)) {
            $invitedUsers = [];
        }
        // 强制转换为索引数组
        $invitedUsers = array_values($invitedUsers);
        
        if (!in_array($invitedUserId, $invitedUsers)) {
            $invitedUsers[] = $invitedUserId;
            // 直接使用数据库操作确保格式正确
            Db::name('pdd_progress_groups')->where('id', $group->id)->update([
                'invited_users' => json_encode(array_values($invitedUsers))
            ]);
        }
        
        return true;
    }

    /**
     * 标记用户为达标（充值50元）
     */
    public static function markUserQualified(int $userId, int $progressId, int $qualifiedUserId): bool
    {
        $group = self::where('user_id', $userId)
            ->where('progress_id', $progressId)
            ->find();
            
        if (!$group) {
            return false;
        }
        
        $qualifiedUsers = $group->qualified_users ?: [];
        if (is_string($qualifiedUsers)) {
            $decoded = json_decode($qualifiedUsers, true);
            $qualifiedUsers = is_array($decoded) ? $decoded : [];
        } elseif (is_object($qualifiedUsers)) {
            $qualifiedUsers = (array)$qualifiedUsers;
        }
        // 确保是索引数组
        if (!is_array($qualifiedUsers)) {
            $qualifiedUsers = [];
        }
        // 强制转换为索引数组
        $qualifiedUsers = array_values($qualifiedUsers);
        
        if (!in_array($qualifiedUserId, $qualifiedUsers)) {
            $qualifiedUsers[] = $qualifiedUserId;
            // 直接使用数据库操作确保格式正确
            Db::name('pdd_progress_groups')->where('id', $group->id)->update([
                'qualified_users' => json_encode(array_values($qualifiedUsers)),
                'has_qualified_user' => count($qualifiedUsers)
            ]);
        }
        
        return true;
    }

    /**
     * 获取用户的进度组列表
     */
    public static function getUserGroups(int $userId): array
    {
        return self::where('user_id', $userId)
            ->order('id', 'desc')
            ->select()
            ->toArray();
    }

    /**
     * 获取指定进度的进度组
     */
    public static function getGroupByProgress(int $userId, int $progressId): ?array
    {
        $group = self::where('user_id', $userId)
            ->where('progress_id', $progressId)
            ->find();
            
        return $group ? $group->toArray() : null;
    }
}
