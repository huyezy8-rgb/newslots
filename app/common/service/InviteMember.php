<?php

namespace app\common\service;

use app\common\model\Account;
use app\common\model\recharge\Orders;
use think\facade\Db;


class InviteMember
{

    /**
     * 更新推荐人的有效用户记录
     * @param int $userId 被推荐用户ID
     * @param float $amount 指定有效金额
     * @return bool
     */
    public function updateValidInviteUser(int $userId): bool
    {
        // 查询被推荐用户的账户信息
        $user = Account::find($userId);
        if (!$user) {
            return false;
        }

        $inviterId = $user->p_id ?? null;
        if (!$inviterId) {
            return false;
        }

        // 统计该用户充值总额
        $total = Orders::where('user_id', $userId)
            ->where('pay_status', 1) // 只统计已完成充值
            ->sum('amount');

        if ($total < get_sys_config('invite_member_amount')) {
            return false;
        }

        // 检查有效用户是否已存在
        $existingRecord = Db::name('invite_valid_log')->where('pid', $inviterId)->where('uid', $userId)->find();
        
        if ($existingRecord) {
            // 有效用户已存在，更新金额
            Db::name('invite_valid_log')->where('pid', $inviterId)->where('uid', $userId)->update([
                'amount' => $total,
                'edit_time' => time()
            ]);
            \think\facade\Log::info("InviteMember: 用户 {$userId} 有效记录已存在，已更新金额为 {$total}");
            return false;
        }

        // 新增有效用户记录
        try {
            // 更新推荐人的有效邀请数量
            Account::where('id', $inviterId)->inc('valid_invite_count', 1);

            // 插入新的有效用户记录
            $result = Db::name('invite_valid_log')->insert([
                'uid' => $userId,
                'pid' => $inviterId,
                'amount' => $total,
                'add_time' => time(),
                'edit_time' => time()
            ]);

            if ($result) {
                \think\facade\Log::info("InviteMember: 成功插入用户 {$userId} 的有效记录，推荐人 {$inviterId}，金额 {$total}");
                return true;
            } else {
                \think\facade\Log::error("InviteMember: 插入用户 {$userId} 有效记录失败");
                return false;
            }
        } catch (\Exception $e) {
            \think\facade\Log::error("InviteMember: 插入用户 {$userId} 有效记录异常: " . $e->getMessage());
            return false;
        }
    }
}
