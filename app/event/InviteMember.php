<?php

namespace app\event;

use app\common\model\Account;
use app\common\service\InviteMember as ServiceInviteMember;
use think\facade\Db;
use think\facade\Log;

class InviteMember
{

    protected ?ServiceInviteMember $inviteService = null;

    protected function getInviteService(): ServiceInviteMember
    {
        if ($this->inviteService === null) {
            $this->inviteService = new ServiceInviteMember();
        }
        return $this->inviteService;
    }

    public function handle($userId): void
    {
        try {
            Log::info("更新推荐人有效用户: $userId");

            $result = $this->getInviteService()->updateValidInviteUser($userId);

            if ($result) {
                Log::info("InviteMember 有效用户事件：用户 {$userId} 执行升级成功，新增有效记录");
            } else {
                Log::info("InviteMember 有效用户事件：用户 {$userId} 执行完成，但未新增记录（可能已存在或未达到条件）");
            }
        } catch (\Throwable $e) {
            Log::error("InviteMember 执行异常：" . $e->getMessage(), [
                'user_id' => $userId,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}