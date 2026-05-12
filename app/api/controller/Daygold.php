<?php

namespace app\api\controller;

use app\admin\controller\crud\Log;
use app\api\enum\CoinLog;
use app\common\controller\Api;
use app\common\model\Account;
use app\common\service\AccountService;
use app\common\service\ChannelInfoService;
use think\facade\Db;

class Daygold extends Base
{
    // 活动ID
    private const ACTIVITY_ID = 1;

    // 签到状态常量
    private const STATUS_CANNOT_CLAIM = 0; // 不可领取
    private const STATUS_CAN_CLAIM = 1;    // 可领取
    private const STATUS_CLAIMED = 2;      // 已领取

    public function initialize(): void
    {
        parent::initialize();
    }
    protected function getAccountService(): AccountService
    {
        return $this->accountService ??= new AccountService();
    }

    
    /**
     * 获取签到状态
     */
    public function index()
    {
        try {
            // 检查渠道活动是否启用
            ChannelInfoService::assertChannelActivityEnabled($this->userInfo, 'day_gold');
            
            $uid = $this->userInfo['id'];
            $activity = $this->getDayGoldActivity();
            $info = $this->getUserActivityInfo($uid);

            $signInfo = $this->getCurrentSignInfo($info, time(), $activity['deadline_hour']);

            $deadline_time = strtotime("today {$activity['deadline_hour']}:00:00");
            if (time() > $deadline_time) {
                $deadline_time += 24 * 60 * 60;
            }
            $data = [
                'rewards' => json_decode($activity['rewards'], true),
                'receive_status' => is_string($info['receive_status']) ? json_decode($info['receive_status'], true) : $info['receive_status'],
                'day_status' => $signInfo['day_status'],
                'current_day' => $signInfo['current_index'] + 1,
                'deadline_time' => $deadline_time,
            ];


        } catch (\Throwable $e) {
            $this->error(__('Get sign info failed') . ': ' . $e->getMessage()); // 获取签到信息失败
        }
        $this->success(__('Get sign info success'), $data); // 获取签到信息成功
    }

    /**
     * 执行签到
     */
    public function Clockin()
    {
        Db::startTrans();
        try {
            // 检查渠道活动是否启用
            ChannelInfoService::assertChannelActivityEnabled($this->userInfo, 'day_gold');
            
            $uid = $this->userInfo['id'];
            $activity = $this->getDayGoldActivity();
            $info = $this->getUserActivityInfo($uid);
            $now = time();

            // 检查是否可签到
            if ($this->isTodayReceived($info['last_receive_time'] ?? 0, $now, $activity['deadline_hour'])) {
                throw new \Exception(__('Today already received, please come back after') . $activity['deadline_hour'] . __('o\'clock')); // 今日已领取，请晚上X点后再来
            }

            // 获取当前签到信息
            $signInfo = $this->getCurrentSignInfo($info, $now, $activity['deadline_hour']);
            if ($signInfo['day_status'] != self::STATUS_CAN_CLAIM) {
                throw new \Exception(__('Current sign cannot receive')); // 当前签到不可领取
            }

            // 更新签到状态
            $this->updateSignStatus($uid, $info, $signInfo['current_index'], $now);

            // 发放奖励
            $rewardAmount = $this->giveReward($this->userInfo, $activity, $signInfo['current_index']);

            Db::commit();

        } catch (\Throwable $e) {
            Db::rollback();
            $this->error(__('Sign failed') . ': ' . $e->getMessage()); // 签到失败
        }
        $this->success(__('Sign success'), ['reward' => $rewardAmount]); // 签到成功
    }

    /**
     * 获取用户活动信息
     */
    private function getUserActivityInfo(int $uid): array
    {
        $info = Db::name('activity_daygold_user')
            ->where(['uid' => $uid])
            ->find();

        if (!$info) {
            throw new \Exception(__('User sign info not exist')); // 用户签到信息不存在
        }

        // 自动修复异常状态
        $info = $this->autoFixAbnormalStatus($uid, $info);

        return $info;
    }

    /**
     * 获取当前签到信息
     */
    private function getCurrentSignInfo(array $info, int $now, int $deadlineHour): array
    {
        $receiveStatus = is_string($info['receive_status']) ? json_decode($info['receive_status'], true) : $info['receive_status'];

        $isTodayReceived = $this->isTodayReceived(
            $info['last_receive_time'] ?? 0,
            $now,
            $deadlineHour
        );

        if (!$isTodayReceived) {
            // 新周期：当前可领取的索引应该是times的值
            $currentDayIndex = $info['times'] ?? 0;
            // 确保索引不越界
            $currentDayIndex = min($currentDayIndex, count($receiveStatus) - 1);

            // 自动将当前索引状态设为可领取(如果还未领取)
            if(($receiveStatus[$currentDayIndex] ?? self::STATUS_CANNOT_CLAIM) == self::STATUS_CANNOT_CLAIM) {
                $receiveStatus[$currentDayIndex] = self::STATUS_CAN_CLAIM;
            }
        } else {
            // 同一周期：使用times-1作为当前索引
            $currentDayIndex = max(0, ($info['times'] ?? 1) - 1);
        }

        return [
            'current_index' => $currentDayIndex,
            'day_status' => $receiveStatus[$currentDayIndex] ?? self::STATUS_CANNOT_CLAIM,
        ];
    }

    /**
     * 是否今天已领取（以deadline_hour为分界点）
     */
    private function isTodayReceived(int $lastReceiveTime, int $now, int $deadlineHour): bool
    {
        if ($lastReceiveTime == 0) {
            return false; // 从未签到过
        }

        $todayDeadline = strtotime("today {$deadlineHour}:00:00");
        $yesterdayDeadline = $todayDeadline - 86400;
        \think\facade\Log::info("签到时间记录,当前时间：". date('Y-m-d H:i:s', $now)."|上次签到时间：". date('Y-m-d H:i:s', $lastReceiveTime)."|今日截止时间：".date('Y-m-d H:i:s', $todayDeadline)."|昨日截止时间".date('Y-m-d H:i:s', $yesterdayDeadline));
        // 当前时间在今天deadline之前
        if ($now < $todayDeadline) {
            // 上次签到必须在 [昨天deadline, 今天deadline) 之间
            return $lastReceiveTime >= $yesterdayDeadline
                && $lastReceiveTime < $todayDeadline;
        }
        // 当前时间在今天deadline之后
        else {
            // 上次签到必须在 [今天deadline, 现在] 之间
            return $lastReceiveTime >= $todayDeadline;
        }
    }

    /**
     * 自动修复异常状态
     */
    private function autoFixAbnormalStatus(int $uid, array $info): array
    {
        $receiveStatus = json_decode($info['receive_status'], true);
        $times = $info['times'];
        $totalDays = count($receiveStatus);
        $lastReceiveTime = $info['last_receive_time'] ?? 0;
        
        // 检测异常状态：times >= totalDays 且所有状态都是已领取
        $allClaimed = true;
        foreach ($receiveStatus as $status) {
            if ($status != self::STATUS_CLAIMED) {
                $allClaimed = false;
                break;
            }
        }
        
        // 检测是否完成整个签到周期
        if ($times >= $totalDays && $allClaimed) {
            // 获取活动配置以检查时间
            try {
                $activity = $this->getDayGoldActivity();
                $deadlineHour = $activity['deadline_hour'] ?? 0;
                $now = time();
                
                // 使用现有的 isTodayReceived 方法判断是否在周期内
                if ($this->isTodayReceived($lastReceiveTime, $now, $deadlineHour)) {
                    // 用户今天已经签到，说明还在周期内，不进行修复
                    return $info;
                }
                
            } catch (\Exception $e) {
                // 如果获取活动配置失败，为了安全起见，不进行修复
                \think\facade\Log::warning("获取活动配置失败，跳过自动修复: " . $e->getMessage());
                return $info;
            }
            
            // 重置状态，开始新的签到周期
            $receiveStatus = array_fill(0, $totalDays, self::STATUS_CANNOT_CLAIM);
            $receiveStatus[0] = self::STATUS_CAN_CLAIM; // 第一天设为可领取
            
            // 更新数据库
            $update = Db::name('activity_daygold_user')
                ->where(['uid' => $uid])
                ->update([
                    'receive_status' => json_encode($receiveStatus),
                    'times' => 0,
                    'update_time' => $now
                ]);
                
            if ($update) {
                \think\facade\Log::info("用户 {$uid} 完成签到周期，自动重置状态: times={$times} -> 0, receive_status 已重置");
                return [
                    'receive_status' => $receiveStatus,
                    'times' => 0
                ];
            }
        }
        
        return $info;
    }

    /**
     * 更新签到状态
     */
    private function updateSignStatus(int $uid, array $info, int $currentDayIndex, int $now): void
    {
        $receiveStatus = is_string($info['receive_status']) ? json_decode($info['receive_status'], true) : $info['receive_status'];
        $receiveStatus[$currentDayIndex] = self::STATUS_CLAIMED;

        // 解锁下一天的签到（如果不是最后一天）
        if (isset($receiveStatus[$currentDayIndex + 1])) {
            $receiveStatus[$currentDayIndex + 1] = self::STATUS_CAN_CLAIM;
        }

        $update = Db::name('activity_daygold_user')
            ->where(['uid' => $uid])
            ->update([
                'receive_status' => json_encode($receiveStatus),
                'times' => $info['times'] + 1,
                'last_receive_time' => $now,
                'update_time' => $now
            ]);

        if (!$update) {
            throw new \Exception(__('Update sign status failed')); // 更新签到状态失败
        }
    }

    /**
     * 发放奖励
     */
    private function giveReward(Account $userinfo, array $activity, int $dayIndex): float
    {
        $uid= $userinfo['id'];
        $rewards = json_decode($activity['rewards'], true);
        $rewardAmount = $rewards[$dayIndex] ?? 0;

        if ($rewardAmount > 0) {
            $log_type_id = CoinLog::getIdByEvent('day_gold');
            $note = CoinLog::getTypeText($log_type_id) ."赠送";

            $walletType = $userinfo['switch_wallet'] ?? 0;
            $this->getAccountService()->increaseBalance(
                userId: $uid,
                amount: $rewardAmount,
                walletType:$walletType,
                logTypeId: $log_type_id,
                note:$note
            );
        }

        return $rewardAmount;
    }

    /**
     * 获取签到活动配置
     */
    private function getDayGoldActivity(): array
    {
        $activity = Db::name('activity_daygold')
            ->where('id', self::ACTIVITY_ID)
            ->find();

        if (!$activity) {
            throw new \Exception('签到活动配置不存在');
        }

        return $activity;
    }
}