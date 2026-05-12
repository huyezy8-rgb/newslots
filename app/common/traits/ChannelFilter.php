<?php

namespace app\common\traits;

use app\common\service\MemberLevelService;
use Throwable;
use think\facade\Db;

/**
 * 渠道筛选 Trait
 * 用于处理基于管理员绑定渠道的数据筛选逻辑
 */
trait ChannelFilter
{
    /**
     * 获取当前管理员的渠道ID
     * @return int|null 渠道ID，null表示超级管理员或未绑定渠道
     * @throws Throwable
     */
    protected function getCurrentAdminChannelId(): ?int
    {
        // 获取当前管理员的渠道ID
        $currentAdmin = \app\admin\model\Admin::find($this->auth->id);
        if (!$currentAdmin || is_null($currentAdmin->channel_id)) {
            // 如果没有绑定渠道，返回null表示不限制
            return null;
        }

        return $currentAdmin->channel_id;
    }

    /**
     * 根据渠道ID获取用户ID列表
     * @param int $channelId 渠道ID
     * @return array 用户ID数组
     * @throws Throwable
     */
    protected function getUserIdsByChannelId(int $channelId): array
    {
        return Db::name('account')
            ->where('channel_id', $channelId)
            ->column('id');
    }

    /**
     * 添加渠道权限过滤条件
     * @param array $where 现有的where条件数组
     * @param string $userIdField 用户ID字段名，默认为'user_id'
     * @return array 添加了渠道过滤的where条件数组
     * @throws Throwable
     */
    protected function addChannelFilter(array $where, string $userIdField = 'user_id'): array
    {
        $channelId = $this->getCurrentAdminChannelId();

        if ($channelId !== null) {
            if($userIdField == 'channel_id'){
                $where[] = [$userIdField, '=', $channelId];
            }elseif($userIdField == 'channelid'){
                $where[] = ['id', '=', $channelId];
            }else{
                $userIds = $this->getUserIdsByChannelId($channelId);
                if (!empty($userIds)) {
                    $where[] = [$userIdField, 'in', $userIds];
                } else {
                    // 如果该渠道没有用户，返回空结果
                    $where[] = [$userIdField, '=', 0];
                }
            }

        }
        return $where;
    }

    /**
     * 检查渠道权限（用于编辑、删除等操作）
     * @param int $channelId 要操作的渠道ID
     * @param string $errorMessage 错误提示信息
     * @throws Throwable
     */
    protected function checkChannelPermission(int $channelId, string $errorMessage = '您没有权限操作此渠道的数据'): void
    {
        $currentChannelId = $this->getCurrentAdminChannelId();
        if ($currentChannelId !== null && $currentChannelId !== $channelId) {
            $this->error($errorMessage);
        }
    }

    /**
     * 检查用户是否属于当前管理员可操作的渠道
     * @param int $userId 用户ID
     * @param string $errorMessage 错误提示信息
     * @throws Throwable
     */
    protected function checkUserChannelPermission(int $userId, string $errorMessage = '您没有权限操作此用户的数据'): void
    {
        $channelId = $this->getCurrentAdminChannelId();
        if ($channelId !== null) {
            $userChannelId = Db::name('account')
                ->where('id', $userId)
                ->value('channel_id');
            
            if ($userChannelId !== $channelId) {
                $this->error($errorMessage);
            }
        }
    }

    /**
     * 获取渠道过滤的用户ID列表
     * @return array|null 用户ID数组，null表示不限制
     * @throws Throwable
     */
    protected function getChannelFilterUserIds(): ?array
    {
        $channelId = $this->getCurrentAdminChannelId();
        if ($channelId === null) {
            return null;
        }
        return $this->getUserIdsByChannelId($channelId);
    }

    /**
     * 构建渠道过滤的查询条件
     * @param string $userIdField 用户ID字段名
     * @return array 查询条件数组
     * @throws Throwable
     */
    protected function buildChannelWhere(string $userIdField = 'user_id'): array
    {
        $where = [];
        $userIds = $this->getChannelFilterUserIds();
        
        if ($userIds !== null) {
            if (!empty($userIds)) {
                $where[] = [$userIdField, 'in', $userIds];
            } else {
                // 如果该渠道没有用户，返回空结果
                $where[] = [$userIdField, '=', 0];
            }
        }
        
        return $where;
    }

    /**
     * 应用渠道过滤到查询构建器
     * @param \think\db\Query $query 查询构建器
     * @param string $userIdField 用户ID字段名
     * @return \think\db\Query
     * @throws Throwable
     */
    protected function applyChannelFilter($query, string $userIdField = 'user_id')
    {
        $userIds = $this->getChannelFilterUserIds();
        
        if ($userIds !== null) {
            if (!empty($userIds)) {
                $query->whereIn($userIdField, $userIds);
            } else {
                // 如果该渠道没有用户，返回空结果
                $query->where($userIdField, 0);
            }
        }
        
        return $query;
    }

    /**
     * 获取渠道统计信息
     * @param string $tableName 表名
     * @param string $userIdField 用户ID字段名
     * @param array $fields 统计字段
     * @return array 统计结果
     * @throws Throwable
     */
    protected function getChannelStats(string $tableName, string $userIdField = 'user_id', array $fields = []): array
    {
        $channelId = $this->getCurrentAdminChannelId();
        
        if ($channelId === null) {
            // 超级管理员，返回所有渠道的统计
            $query = Db::name($tableName);
            if (!empty($fields)) {
                $query->field($fields);
            }
            return $query->find() ?: [];
        } else {
            // 渠道管理员，只返回自己渠道的统计
            $userIds = $this->getUserIdsByChannelId($channelId);
            $query = Db::name($tableName)->whereIn($userIdField, $userIds);
            if (!empty($fields)) {
                $query->field($fields);
            }
            return $query->find() ?: [];
        }
    }

    /**
     * 获取渠道分组统计
     * @param string $tableName 表名
     * @param string $userIdField 用户ID字段名
     * @param string $groupField 分组字段名
     * @param array $fields 统计字段
     * @return array 分组统计结果
     * @throws Throwable
     */
    protected function getChannelGroupStats(string $tableName, string $userIdField = 'user_id', string $groupField = 'channel_id', array $fields = []): array
    {
        $channelId = $this->getCurrentAdminChannelId();
        
        if ($channelId === null) {
            // 超级管理员，返回所有渠道的分组统计
            $query = Db::name($tableName);
            if (!empty($fields)) {
                $query->field($fields);
            }
            return $query->group($groupField)->select()->toArray();
        } else {
            // 渠道管理员，只返回自己渠道的分组统计
            $userIds = $this->getUserIdsByChannelId($channelId);
            $query = Db::name($tableName)->whereIn($userIdField, $userIds);
            if (!empty($fields)) {
                $query->field($fields);
            }
            return $query->group($groupField)->select()->toArray();
        }
    }

    /**
     * 检查当前管理员是否有权限访问指定渠道
     * @param int $channelId 渠道ID
     * @return bool
     * @throws Throwable
     */
    protected function hasChannelAccess(int $channelId): bool
    {
        $currentChannelId = $this->getCurrentAdminChannelId();
        return $currentChannelId === null || $currentChannelId === $channelId;
    }

    /**
     * 获取当前管理员可访问的渠道列表
     * @return array 渠道ID数组
     * @throws Throwable
     */
    protected function getAccessibleChannelIds(): array
    {
        $channelId = $this->getCurrentAdminChannelId();
        if ($channelId === null) {
            // 超级管理员可以访问所有渠道
            return Db::name('channel_list')->column('id');
        } else {
            // 渠道管理员只能访问自己的渠道
            return [$channelId];
        }
    }
} 