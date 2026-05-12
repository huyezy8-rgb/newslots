<?php

namespace app\event;

use app\common\model\Account;
use app\common\service\MemberLevelService;
use think\facade\Db;
use think\facade\Log;

class LevelUp
{
    protected function getMemberLevelService(): MemberLevelService
    {
        return $this->memberlevelservice ??= new MemberLevelService();
    }

    public function handle($userId): void
    {
        try {
            Log::channel('LevelUp')->info("LevelUp 事件触发，用户ID: $userId");

            $this->getMemberLevelService()->upgradeByUserId($userId);

            Log::channel('LevelUp')->info("LevelUp 事件：用户 {$userId} 执行升级成功");

        } catch (\Throwable $e) {
            // 如果是"无需变更等级"的情况，降级为 warning，这是正常的业务逻辑
            if (strpos($e->getMessage(), 'no_level_change_needed') !== false) {
                Log::channel('LevelUp')->warning("LevelUp 事件：用户 {$userId} 无需变更等级");
            } else {
                Log::channel('LevelUp')->error("LevelUp 执行异常：" . $e->getMessage());
            }
        }
    }
}