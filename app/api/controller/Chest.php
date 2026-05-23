<?php

namespace app\api\controller;

use app\api\enum\CoinLog;
use app\common\model\Chest as ChestModel;
use app\common\model\activity\ChestConfig as ChestConfigModel;
use app\common\model\ChestReceiveLog;
use app\common\model\recharge\Orders;
use app\common\model\Account;
use app\common\service\AccountService;
use think\facade\Request;
use think\facade\Db;

class Chest extends Base
{
    protected AccountService $accountService;

    protected function getAccountService(): AccountService
    {
        return $this->accountService ??= new AccountService();
    }

    // 获取所有宝箱及用户领取状态
    public function list()
    {
        $userId = $this->userInfo['id'];
        $chests = ChestModel::order('invite_count asc, sort desc')->select();
        $logs = ChestReceiveLog::where('user_id', $userId)->column('chest_id');

// 配置图片
        $chestConfig = ChestConfigModel::where('status', 1)->find();
        $defaultImage = $chestConfig?->default_image ?? '';
        $waitingImage = $chestConfig?->waiting_image ?? '';
        $receivedImage = $chestConfig?->received_image ?? '';

// 1. 我的直属下级总数
        $inviteUserIds = Account::where('p_id', $userId)->column('id');
        $totalInviteCount = count($inviteUserIds);

// 2. 我的下级里有充值的人数（去重）
        $rechargeUserCount = 0;
        if (!empty($inviteUserIds)) {
            $rechargeUserCount = Orders::whereIn('user_id', $inviteUserIds)
                ->where('pay_status', 1)
                ->group('user_id')
                ->count();
        }

// 统计
        $totalReceivedAmount = ChestReceiveLog::where('user_id', $userId)->sum('amount') ?: 0;
        $unclaimedAmount = 0;

// 今日统计（时间戳格式）
        $todayStart = strtotime(date('Y-m-d 00:00:00'));
        $todayEnd = strtotime(date('Y-m-d 23:59:59'));

        $todayInviteCount = Account::where('p_id', $userId)
            ->whereBetween('reg_time', [date('Y-m-d 00:00:00'), date('Y-m-d 23:59:59')])
            ->count();

        $todayInviteUserIds = Account::where('p_id', $userId)
            ->whereBetween('reg_time', [date('Y-m-d 00:00:00'), date('Y-m-d 23:59:59')])
            ->column('id');

        $todayValidUserCount = 0;
        if (!empty($todayInviteUserIds)) {
            $todayValidUserCount = Orders::whereIn('user_id', $todayInviteUserIds)
                ->where('pay_status', 1)
                ->group('user_id')
                ->count();
        }

// ===================== 【正确】时间戳 created_at 统计 =====================
// 今日直属下级充值
        $todayTeamRechargeAmount = 0;
        if (!empty($inviteUserIds)) {
            $todayTeamRechargeAmount = Orders::whereIn('user_id', $inviteUserIds)
                ->where('pay_status', 1)
                ->whereBetween('created_at', [$todayStart, $todayEnd]) // 时间戳查询
                ->sum('amount');
        }

// 总直属下级充值
        $totalTeamRechargeAmount = 0;
        if (!empty($inviteUserIds)) {
            $totalTeamRechargeAmount = Orders::whereIn('user_id', $inviteUserIds)
                ->where('pay_status', 1)
                ->sum('amount');
        }
// ======================================================================

// 返回结构
        $result = [
            'list' => [],
            'statistics' => [
                'unclaimed_amount' => $unclaimedAmount,
                'total_received_amount' => $totalReceivedAmount,
                'banner_image' => $chestConfig ? full_url('', true, $chestConfig->banner_image) : '',
                'today' => [
                    'invite_count' => $todayInviteCount,
                    'valid_user_count' => $todayValidUserCount,
                    'team_recharge_amount' => $todayTeamRechargeAmount,
                ],
                'total' => [
                    'invite_count' => $totalInviteCount,
                    'valid_user_count' => $rechargeUserCount,
                    'team_recharge_amount' => $totalTeamRechargeAmount,
                ]
            ]
        ];


        foreach ($chests as $chest) {
            $need = $chest['invite_count'];

            $canReceive = ($totalInviteCount >= $need) && ($rechargeUserCount >= $need);
            $received = in_array($chest['id'], $logs);

            if ($received) {
                $status = 2;
                $image = $receivedImage ?: $defaultImage;
            } elseif ($canReceive) {
                $status = 1;
                $image = $waitingImage ?: $defaultImage;
            } else {
                $status = 0;
                $image = $defaultImage;
            }

            $result['list'][] = [
                'id' => $chest['id'],
                'name' => $chest['name'],
                'invite_count' => $chest['invite_count'],
                'reward_amount' => $chest['reward_amount'],
                'status' => $status,
                'image' => full_url('', true, $image),
                'default_image' => full_url('', true, $defaultImage),
                'waiting_image' => full_url('', true, $waitingImage),
                'received_image' => full_url('', true, $receivedImage),
            ];

            if ($canReceive && !$received) {
                $unclaimedAmount += $chest['reward_amount'];
            }
        }

        $result['statistics']['unclaimed_amount'] = $unclaimedAmount;

        $this->success(__('Get chest list success'), $result);
    }

    // 用户领取宝箱
    public function receive()
    {
        $userId = $this->userInfo['id'];
        $chestId = Request::param('chest_id');
        $chest = ChestModel::find($chestId);
        if (!$chest) $this->error(__('Chest not exist'));


        // 1. 获取【我的直属邀请总人数】
        $totalInviteCount = Account::where('p_id', $userId)->count() ?? 0;

        // 2. 获取【我的下级有充值的人数】
        $inviteUserIds = Account::where('p_id', $userId)->column('id');
        $rechargeUserCount = 0;
        if (!empty($inviteUserIds)) {
            $rechargeUserCount = Orders::whereIn('user_id', $inviteUserIds)
                ->where('pay_status', 1)
                ->group('user_id')
                ->count();
        }

        // 3. 新判断条件：邀请人数 和 充值人数 都 >= 宝箱要求
        $need = $chest['invite_count'];
        if ($totalInviteCount < $need || $rechargeUserCount < $need) {
            $this->error(__('Not meet receive conditions'));
        }


        // 检查是否已领取
        if (ChestReceiveLog::where(['user_id' => $userId, 'chest_id' => $chestId])->find()) {
            $this->error(__('Already received'));
        }

        // 记录领取和发放奖励金额，事务处理
        Db::startTrans();
        try {
            // 记录领取
            ChestReceiveLog::create([
                'user_id' => $userId,
                'chest_id' => $chestId,
                'amount' => $chest['reward_amount'],
                'createtime' => time()
            ]);

            // 发放奖励金额到账户
            $logTypeId = CoinLog::ChestBox;
            $this->getAccountService()->increaseBalance(
                userId: $this->userInfo['id'],
                amount: $chest['reward_amount'],
                walletType: 1,
                logTypeId: $logTypeId,
                note: CoinLog::getTypeText($logTypeId) . ":" . $chest['reward_amount']
            );

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            $this->error(__('Receive error ') . $e->getMessage());
        }

        $this->success(__('Receive success'), ['reward_amount' => $chest['reward_amount']]);
    }

    // 获取用户领取记录
    public function records()
    {
        $userId = Request::param('user_id');
        $logs = ChestReceiveLog::where('user_id', $userId)->select();
        // 追加reward_amount
        $data = [];
        foreach ($logs as $log) {
            $chest = ChestModel::find($log['chest_id']);
            $data[] = [
                'id' => $log['id'],
                'chest_id' => $log['chest_id'],
                'reward_amount' => $chest ? $chest['reward_amount'] : 0,
                'createtime' => $log['createtime'],
            ];
        }
        $this->success(__('Get chest record success'), $data);
    }

    // 一键领取所有可领取的宝箱
    public function receiveAll()
    {
        $userId = $this->userInfo['id'];


        // 1. 我的直属邀请总人数
        $totalInviteCount = Account::where('p_id', $userId)->count() ?? 0;

        // 2. 我的下级有充值的人数（去重）
        $inviteUserIds = Account::where('p_id', $userId)->column('id');
        $rechargeUserCount = 0;
        if (!empty($inviteUserIds)) {
            $rechargeUserCount = Orders::whereIn('user_id', $inviteUserIds)
                ->where('pay_status', 1)
                ->group('user_id')
                ->count();
        }


        // 获取所有宝箱
        $chests = ChestModel::order('sort desc,id desc')->select();

        // 获取已领取的宝箱ID
        $receivedChestIds = ChestReceiveLog::where('user_id', $userId)->column('chest_id');

        // 筛选出可领取的宝箱
        $canReceiveChests = [];
        foreach ($chests as $chest) {
            $need = $chest['invite_count'];

            // ===================== 新判断逻辑 =====================
            $canReceive = ($totalInviteCount >= $need) && ($rechargeUserCount >= $need);
            $notReceived = !in_array($chest['id'], $receivedChestIds);

            if ($canReceive && $notReceived) {
                $canReceiveChests[] = $chest;
            }
        }

        if (empty($canReceiveChests)) {
            $this->error(__('No chests available for receiving'));
        }

        // 计算总奖励金额
        $totalReward = 0;
        foreach ($canReceiveChests as $chest) {
            $totalReward += $chest['reward_amount'];
        }

        // 事务处理：批量记录领取和发放奖励
        Db::startTrans();
        try {
            $receivedChests = [];

            foreach ($canReceiveChests as $chest) {
                // 记录领取
                $log = ChestReceiveLog::create([
                    'user_id' => $userId,
                    'chest_id' => $chest['id'],
                    'amount' => $chest['reward_amount'],
                    'createtime' => time()
                ]);

                $receivedChests[] = [
                    'chest_id' => $chest['id'],
                    'chest_name' => $chest['name'],
                    'reward_amount' => $chest['reward_amount']
                ];
            }

            // 发放总奖励金额到账户
            $logTypeId = CoinLog::ChestBox;
            $this->getAccountService()->increaseBalance(
                userId: $userId,
                amount: $totalReward,
                walletType: 1,
                logTypeId: $logTypeId,
                note: CoinLog::getTypeText($logTypeId) . ":" . $totalReward . " (一键领取" . count($canReceiveChests) . "个宝箱)"
            );

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            $this->error(__('Receive all chests error ') . $e->getMessage());
        }

        $this->success(__('Receive all chests success'), [
            'total_reward' => $totalReward,
            'received_count' => count($canReceiveChests),
            'received_chests' => $receivedChests
        ]);
    }
}
