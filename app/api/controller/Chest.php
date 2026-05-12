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
        $chests = ChestModel::order('invite_count asc,recharge_amount asc,sort desc')->select();
        $logs = ChestReceiveLog::where('user_id', $userId)->column('chest_id');

        // 获取宝箱活动配置中的图片数据
        $chestConfig = ChestConfigModel::where('status', 1)->find();
        $defaultImage = $chestConfig ? $chestConfig->default_image : '';
        $waitingImage = $chestConfig ? $chestConfig->waiting_image : '';
        $receivedImage = $chestConfig ? $chestConfig->received_image : '';
        // 查询用户充值总额
        $recharge = Orders::where('user_id', $userId)->where('pay_status', 1)->sum('amount');
        // 查询用户邀请有效用户数
        $user = Account::find($userId);
        $inviteCount = $user->valid_invite_count ?? 0;

        // 计算未领取金额（一键领取时能领取的金额）
        $unclaimedAmount = 0;
        // $canReceiveChests = [];
        foreach ($chests as $chest) {
            $canReceive = $recharge >= $chest['recharge_amount'] && $inviteCount >= $chest['invite_count'];
            $notReceived = !in_array($chest['id'], $logs);
            if ($canReceive && $notReceived) {
                $unclaimedAmount += $chest['reward_amount'];
                // $canReceiveChests[] = $chest;
            }
        }

        // 计算总领取金额
        $totalReceivedAmount = ChestReceiveLog::where('user_id', $userId)->sum('amount');

        // 获取今日时间范围
        $todayStart = strtotime(date('Y-m-d 00:00:00'));
        $todayEnd = strtotime(date('Y-m-d 23:59:59'));

        // 今日邀请人数（今日注册的直属下级）
        $todayInviteCount = Account::where('p_id', $userId)
            ->where('reg_time', '>=', date('Y-m-d 00:00:00'))
            ->where('reg_time', '<=', date('Y-m-d 23:59:59'))
            ->count();

        // 今日有效用户人数（今日达到充值门槛的直属下级）
        $todayValidUserCount = Db::name('invite_valid_log')
            ->where('pid', $userId)
            ->where('add_time', '>=', $todayStart)
            ->where('add_time', '<=', $todayEnd)
            ->count();

        // 今日下级用户充值总金额
        $teamPathService = new \app\common\service\TeamPathService();
        $todayTeamRechargeAmount = $teamPathService->getTeamRechargeAmount($userId, date('Y-m-d 00:00:00'), date('Y-m-d 23:59:59'));

        // 总的邀请人数（所有直属下级）
        $totalInviteCount = Account::where('p_id', $userId)->count();

        // 总的有效用户人数（所有达到充值门槛的直属下级）
        $totalValidUserCount = Db::name('invite_valid_log')
            ->where('pid', $userId)
            ->count();

        // 总的下级用户充值总金额
        $totalTeamRechargeAmount = $teamPathService->getTeamRechargeAmount($userId);

        $result = [
            'list' => [],
            'statistics' => [
                'unclaimed_amount' => $unclaimedAmount, // 未领取金额
                'total_received_amount' => $totalReceivedAmount, // 总领取金额
                'banner_image' => $chestConfig ? full_url('', true, $chestConfig->banner_image) : '', // 宝箱活动配置中的图片数据
                'today' => [
                    'invite_count' => $todayInviteCount, // 今日邀请人数
                    'valid_user_count' => $todayValidUserCount, // 今日有效用户人数
                    'team_recharge_amount' => $todayTeamRechargeAmount, // 今日下级用户充值总金额
                ],
                'total' => [
                    'invite_count' => $totalInviteCount, // 总邀请人数
                    'valid_user_count' => $totalValidUserCount, // 总有效用户人数
                    'team_recharge_amount' => $totalTeamRechargeAmount, // 总下级用户充值总金额
                ]
            ]
        ];

        foreach ($chests as $chest) {
            $canReceive = $recharge >= $chest['recharge_amount'] && $totalValidUserCount >= $chest['invite_count'];
            $received = in_array($chest['id'], $logs);
            // 状态图片逻辑 - 使用配置表中的图片
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
                'recharge_amount' => $chest['recharge_amount'],
                'invite_count' => $chest['invite_count'],
                'reward_amount' => $chest['reward_amount'],
                'sort' => $chest['sort'],
                'status' => $status,
                'image' => $image,
                'default_image' => full_url('', true, $defaultImage),
                'waiting_image' => full_url('', true, $waitingImage),
                'received_image' => full_url('', true, $receivedImage),
            ];
        }

        $this->success(__('Get chest list success'), $result);
    }

    // 用户领取宝箱
    public function receive()
    {
        $userId = $this->userInfo['id'];
        $chestId = Request::param('chest_id');
        $chest = ChestModel::find($chestId);
        if (!$chest) $this->error(__('Chest not exist'));
        // 查询用户充值总额
        $recharge = Orders::where('user_id', $userId)->where('pay_status', 1)->sum('amount');
        // $user = Account::find($userId);
        $inviteCount = Db::name('invite_valid_log')
            ->where('pid', $userId)
            ->count() ?? 0;
        if ($recharge < $chest['recharge_amount'] || $inviteCount < $chest['invite_count']) {
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
                logTypeId: $logTypeId,  // ✅ 使用定义的常量
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

        // 查询用户充值总额
        $recharge = Orders::where('user_id', $userId)->where('pay_status', 1)->sum('amount');
        $user = Account::find($userId);
        $inviteCount = $user->valid_invite_count ?? 0;

        // 获取所有宝箱
        $chests = ChestModel::order('sort desc,id desc')->select();

        // 获取已领取的宝箱ID
        $receivedChestIds = ChestReceiveLog::where('user_id', $userId)->column('chest_id');

        // 筛选出可领取的宝箱
        $canReceiveChests = [];
        foreach ($chests as $chest) {
            $canReceive = $recharge >= $chest['recharge_amount'] && $inviteCount >= $chest['invite_count'];
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
