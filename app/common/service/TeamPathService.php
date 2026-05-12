<?php

namespace app\common\service;

use think\facade\Db;
use think\facade\Log;

/**
 * 团队路径管理服务
 * 实现无限级团队设计，使用路径分隔符 /
 */
class TeamPathService
{
    /**
     * 更新用户团队路径
     * @param int $userId 用户ID
     * @return bool
     */
    public function updateTeamPath(int $userId): bool
    {
        try {
            $user = Db::name('account')->where('id', $userId)->find();
            if (!$user) {
                Log::error("用户不存在: {$userId}");
                return false;
            }

            if ($user['p_id'] == 0) {
                // 根节点用户，路径采用以斜杠包裹的格式 '/'
                $teamPath = '/';
                $level = 0;
            } else {
                // 普通用户
                $parent = Db::name('account')->where('id', $user['p_id'])->find();
                if (!$parent) {
                    // 上级不存在，设为根节点
                    $teamPath = '/';
                    $level = 0;
                    Log::warning("用户 {$userId} 的上级 {$user['p_id']} 不存在，设为根节点");
                } else {
                    $parentPath = rtrim((string)$parent['team_path'], '/');
                    $teamPath = $parentPath . '/' . $parent['id'] . '/';
                    $level = $parent['team_level'] + 1;
                }
            }

            // 更新用户团队路径
            Db::name('account')->where('id', $userId)->update([
                'team_path' => $teamPath,
                'team_level' => $level,
            ]);

            // 递归更新子节点
            $this->updateChildrenTeamPath($userId);

            Log::info("用户 {$userId} 团队路径更新成功: {$teamPath}, 层级: {$level}");
            return true;

        } catch (\Exception $e) {
            Log::error("更新用户 {$userId} 团队路径失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 递归更新子节点团队路径
     * @param int $parentId 父节点ID
     */
    private function updateChildrenTeamPath(int $parentId): void
    {
        $children = Db::name('account')->where('p_id', $parentId)->column('id');
        foreach ($children as $childId) {
            $this->updateTeamPath($childId);
        }
    }

    /**
     * 获取用户的所有下级（包括间接下级）
     * @param int $userId 用户ID
     * @param array $fields 查询字段
     * @return array
     */
    public function getAllChildren(int $userId, array $fields = ['*']): array
    {
        $user = Db::name('account')->where('id', $userId)->find();
        if (!$user) {
            return [];
        }

        $fieldStr = implode(',', $fields);
        
        $prefix = rtrim((string)$user['team_path'], '/') . '/' . $user['id'] . '/';
        return Db::name('account')
            ->field($fieldStr)
            ->where('team_path', 'like', $prefix . '%')
            ->select()
            ->toArray();
    }

    /**
     * 获取用户的直属下级
     * @param int $userId 用户ID
     * @param array $fields 查询字段
     * @return array
     */
    public function getDirectChildren(int $userId, array $fields = ['*']): array
    {
        $fieldStr = implode(',', $fields);
        
        return Db::name('account')
            ->field($fieldStr)
            ->where('p_id', $userId)
            ->select()
            ->toArray();
    }

    /**
     * 获取用户的所有上级
     * @param int $userId 用户ID
     * @param array $fields 查询字段
     * @return array
     */
    public function getAllParents(int $userId, array $fields = ['*']): array
    {
        $user = Db::name('account')->where('id', $userId)->find();
        if (!$user || $user['p_id'] == 0) {
            return [];
        }

        $path = trim((string)$user['team_path'], '/');
        $parentIds = $path === '' ? [] : explode('/', $path);

        if (empty($parentIds)) {
            return [];
        }

        $fieldStr = implode(',', $fields);
        
        return Db::name('account')
            ->field($fieldStr)
            ->whereIn('id', $parentIds)
            ->order('team_level', 'asc')
            ->select()
            ->toArray();
    }

    /**
     * 获取用户的直接上级
     * @param int $userId 用户ID
     * @param array $fields 查询字段
     * @return array|null
     */
    public function getDirectParent(int $userId, array $fields = ['*']): ?array
    {
        $user = Db::name('account')->where('id', $userId)->find();
        if (!$user || $user['p_id'] == 0) {
            return null;
        }

        $fieldStr = implode(',', $fields);
        
        return Db::name('account')
            ->field($fieldStr)
            ->where('id', $user['p_id'])
            ->find();
    }

    /**
     * 计算团队奖金分发
     * @param int $userId 用户ID
     * @param float $amount 奖金金额
     * @param array $ratios 各级分成比例 [层级 => 比例]
     * @return array 奖金分发结果
     */
    public function calculateTeamBonus(int $userId, float $amount, array $ratios = []): array
    {
        $user = Db::name('account')->where('id', $userId)->find();
        if (!$user) {
            return [];
        }

        // 默认分成比例
        if (empty($ratios)) {
            $ratios = [
                0 => 0.10, // 一级上级 10%
                1 => 0.05, // 二级上级 5%
                2 => 0.03, // 三级上级 3%
            ];
        }

        $path = trim((string)$user['team_path'], '/');
        $parentIds = $path === '' ? [] : explode('/', $path);
        // 移除自己ID
        $parentIds = array_filter($parentIds, function($id) use ($userId) {
            return $id != $userId;
        });

        // 反转，最近上级先算
        $parentIds = array_reverse($parentIds);
        
        $bonusResults = [];
        
        foreach ($ratios as $level => $ratio) {
            if (!isset($parentIds[$level])) {
                break;
            }
            
            $leaderId = $parentIds[$level];
            $bonus = $amount * $ratio;
            
            $bonusResults[] = [
                'leader_id' => $leaderId,
                'level' => $level + 1,
                'ratio' => $ratio,
                'bonus' => $bonus,
                'user_id' => $userId
            ];
        }

        return $bonusResults;
    }

    /**
     * 统计团队充值金额
     * @param int $userId 用户ID
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @return float
     */
    public function getTeamRechargeAmount(int $userId, string $startTime = '', string $endTime = ''): float
    {
        $user = Db::name('account')->where('id', $userId)->find();
        if (!$user) {
            return 0.0;
        }

        $query = Db::name('recharge_orders')
            ->alias('r')
            ->join('account u', 'u.id = r.user_id')
            ->where('r.pay_status', 1);

        // 添加时间条件
        if ($startTime) {
            $query->where('r.created_at', '>=', strtotime($startTime));
        }
        if ($endTime) {
            $query->where('r.created_at', '<=', strtotime($endTime));
        }

        // 查询团队用户
        $prefix = rtrim((string)$user['team_path'], '/') . '/' . $user['id'] . '/';
        $query->where('u.team_path', 'like', $prefix . '%');

        return (float)$query->sum('r.amount');
    }

    /**
     * 统计团队用户数量
     * @param int $userId 用户ID
     * @return int
     */
    public function getTeamUserCount(int $userId): int
    {
        $user = Db::name('account')->where('id', $userId)->find();
        if (!$user) {
            return 0;
        }

        $prefix = rtrim((string)$user['team_path'], '/') . '/' . $user['id'] . '/';
        return Db::name('account')
            ->where('team_path', 'like', $prefix . '%')
            ->count();
    }

    /**
     * 批量更新所有用户的团队路径
     * @return bool
     */
    public function updateAllTeamPaths(): bool
    {
        try {
            Log::info("开始批量更新所有用户团队路径");
            
            // 获取所有根节点用户
            $rootUsers = Db::name('account')
                ->where('p_id', 0)
                ->order('id', 'asc')
                ->column('id');

            $successCount = 0;
            $failCount = 0;

            foreach ($rootUsers as $rootId) {
                if ($this->updateTeamPath($rootId)) {
                    $successCount++;
                } else {
                    $failCount++;
                }
            }

            Log::info("批量更新完成: 成功 {$successCount} 个，失败 {$failCount} 个");
            return $failCount == 0;

        } catch (\Exception $e) {
            Log::error("批量更新团队路径失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 验证团队路径的完整性
     * @param int $userId 用户ID
     * @return bool
     */
    public function validateTeamPath(int $userId): bool
    {
        $user = Db::name('account')->where('id', $userId)->find();
        if (!$user) {
            return false;
        }

        $path = (string)$user['team_path'];
        if ($path !== '/' && !preg_match('/^\/(\d+\/)*$/', $path)) {
            return false;
        }

        // 检查层级是否正确：根'/'层级为0，否则为ID段数
        $trimmed = trim($path, '/');
        $expectedLevel = ($trimmed === '') ? 0 : count(explode('/', $trimmed));
        if ((int)$user['team_level'] !== (int)$expectedLevel) {
            return false;
        }

        // 检查上级是否存在
        if ($user['p_id'] > 0) {
            $parent = Db::name('account')->where('id', $user['p_id'])->find();
            if (!$parent) {
                return false;
            }
        }

        return true;
    }
} 