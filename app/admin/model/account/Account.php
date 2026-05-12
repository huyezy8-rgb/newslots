<?php

namespace app\admin\model\account;

use think\Model;
use think\facade\Db;

/**
 * Account
 */
class Account extends Model
{
    // 表名
    protected $name = 'account';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;


    public function getExperienceWalletAttr($value): ?float
    {
        return is_null($value) ? null : (float)$value;
    }

    public function getRechargeWalletAttr($value): ?float
    {
        return is_null($value) ? null : (float)$value;
    }

    // 新增前：根据 p_id 生成 team_path 与 team_level（与 common/Account 同步）
    protected static function onBeforeInsert(Account $model)
    {
        [$teamPath, $teamLevel] = self::buildTeamPathByParentId((int)($model->p_id ?? 0));
        $model->team_path = $teamPath;
        $model->team_level = $teamLevel;
    }

    // 移除更新前校验，交由校验器处理

    // 更新后：当 p_id 发生变化时，重建自身及所有下级的路径（与 common/Account 同步）
    protected static function onAfterUpdate(Account $model)
    {
        $origin = $model->getOrigin();
        $newParentId = (int)($model->p_id ?? 0);
        $oldParentId = (int)($origin['p_id'] ?? 0);
        if ($newParentId !== $oldParentId) {
            \app\common\model\Account::rebuildTeamPathRecursively((int)$model->id);
        }
    }

    // 复制一份生成路径的逻辑（common/Account 中为私有方法）
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
}