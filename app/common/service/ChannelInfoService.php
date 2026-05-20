<?php

namespace app\common\service;

use app\api\enum\CoinLog;
use app\common\model\Account;
use app\common\model\AccountCoinLog;
use app\common\model\activity\FirstDeposit270;
use app\common\model\activity\daygold\User as DaygoldUser;
use app\common\model\Banner;
use app\common\model\ChannelList;
use think\facade\Db;


class ChannelInfoService
{
    public static function getPositiveNumericSysConfig(string $name, int|float $default): int|float
    {
        $value = get_sys_config($name);
        if ($value === null || $value === '' || !is_numeric($value) || (float)$value <= 0) {
            return $default;
        }

        return $value + 0;
    }

    public static function getExperienceWithdrawBetBase(): int|float
    {
        return self::getExperienceWithdrawBetBaseList()[0];
    }

    public static function getExperienceWithdrawBetBaseList(): array
    {
        $value = get_sys_config('ex_withdraw_bet_base');
        $values = [];

        if (is_array($value)) {
            $values = $value;
        } elseif (is_string($value)) {
            $value = trim($value);
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $values = $decoded;
            } else {
                $values = explode(',', $value);
            }
        } elseif (is_numeric($value)) {
            $values = [$value];
        }

        $values = array_values(array_filter(array_map(function ($item) {
            if (!is_numeric($item) || (float)$item <= 0) {
                return null;
            }

            return $item + 0;
        }, $values), fn($item) => $item !== null));

        return $values ?: [9000];
    }

    public static function getExperienceWithdrawSuccessCount(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        return Db::name('account_coin_log')
            ->where('user_id', $userId)
            ->where('log_type_id', CoinLog::ExWithdraw)
            ->where('wallet_type', 1)
            ->where('num', '>', 0)
            ->count();
    }

    public static function getExperienceWithdrawBetBaseForUser(int $userId): int|float
    {
        $info = self::getExperienceWithdrawStageInfo($userId);

        return $info['ex_withdraw_bet_base'];
    }

    public static function getExperienceWithdrawStageInfo(int $userId): array
    {
        $list = self::getExperienceWithdrawBetBaseList();
        $successCount = self::getExperienceWithdrawSuccessCount($userId);
        $index = min($successCount, count($list) - 1);

        return [
            'ex_withdraw_bet_base' => $list[$index],
            'ex_withdraw_bet_base_list' => $list,
            'ex_withdraw_success_count' => $successCount,
            'ex_withdraw_stage' => $index + 1,
        ];
    }

    public static function getExperienceWithdrawAmount(): int|float
    {
        return self::getPositiveNumericSysConfig('ex_withdraw_amount', 30);
    }

    /**
     * 断言指定渠道活动是否开启
     * @throws \Exception
     */
    public static function assertChannelActivityEnabled(Account $account, string $activityKey): void
    {
        $channel = $account->channel ?? null;
        if (!$channel) {
            // 兜底按 channel_id 查询
            if (!empty($account->channel_id)) {
                $channel = ChannelList::withoutField('create_time,update_time')->where('id', $account->channel_id)->find();
            }
        }
        if (!$channel) {
            throw new \Exception(__('Please select channel'));
        }

        $activities = [];
        if (isset($channel['activity'])) {
            $activities = is_array($channel['activity']) ? $channel['activity'] : (json_decode((string)$channel['activity'], true) ?: []);
        }

        $enabled = 1; // 默认开启
        foreach ($activities as $activity) {
            if (($activity['key'] ?? null) === $activityKey) {
                // 只有当明确设置为0时才关闭，其他情况（包括未设置、null、1等）都视为开启
                if (isset($activity['enabled']) && (int)$activity['enabled'] === 0) {
                    $enabled = 0;
                }
                break;
            }
        }

        if ($enabled !== 1) {
            throw new \Exception(__('Activity disabled for this channel'));
        }
    }
    /**
     * 获取渠道详细信息
     * @param Account $account
     * @param ChannelList|null $channelInfo
     * @param bool $isNewUser 是否为新用户（新用户跳过一些查询）
     * @return array
     */
    public function getChannelDetailInfo(Account $account, ?ChannelList $channelInfo = null, bool $isNewUser = false): array
    {
        
        if (!$channelInfo) {
            $channelInfo = ChannelList::withoutField("create_time,update_time")->order('id', 'asc')->find();
        }

        $activity = json_decode($channelInfo["activity"], true) ?? [];

        // 新用户跳过一些查询，因为这些数据在注册时都是空的
        if (!$isNewUser) {
            // 用户活动状态查询
            $result = [];
            
            //弹窗验证
            $result['popup'] = AccountCoinLog::where([
                "user_id" => $account->id,
                "log_type_id" => CoinLog::PopUp,
            ])->find();

            //绑定手机
            $result['bind_mobile'] = AccountCoinLog::where([
                "user_id" => $account->id,
                "log_type_id" => CoinLog::BindMobile,
            ])->find();

            //救援金(今天)
            $result['rescue'] = AccountCoinLog::where([
                "user_id" => $account->id,
                "log_type_id" => CoinLog::RescueFunds,
            ])->whereBetween("create_time", [
                strtotime(date("Y-m-d")),
                strtotime(date("Y-m-d", strtotime("+1 day"))),
            ])->find();

            //每日奖励(今天)
            $result['daygold'] = DaygoldUser::where([
                "uid" => $account->id,
                "channel_id" => $account->channel_id,
            ])->whereBetween("last_receive_time", [
                strtotime(date("Y-m-d")),
                strtotime(date("Y-m-d", strtotime("+1 day"))),
            ])->find();
            
            $accountInfo = $result['popup'];
            $bindMobile = $result['bind_mobile'];
            $rescue = $result['rescue'];
            $daygold = $result['daygold'];

            foreach ($activity as $key => $value) {
                //关闭多个首页弹窗
                if ($accountInfo && isset($value["key"]) && in_array($value["key"], ["pop_up", "turntable", "pwa","pop_up_success","turntable_success"])) {
                    $activity[$key]["popup_enabled_home"] = 0;
                }
                // 根据用户状态关闭对应活动，而不是移除
                if ($accountInfo && isset($value["key"]) && in_array($value["key"], ["pop_up", "pop_up_daily", "pop_up_vip"])) {
                    $activity[$key]["enabled"] = 0;
                }

                if ($bindMobile && isset($value["key"]) && $value["key"] === "bind_mobile") {
                    $activity[$key]["enabled"] = 0;
                }

                if ($rescue && isset($value["key"]) && $value["key"] === "rescue_funds") {
                    $activity[$key]["enabled"] = 0;
                }

                //关闭每日奖励首页弹窗
                if ($daygold && isset($value["key"]) && $value["key"] === "daygold") {
                    $activity[$key]["popup_enabled_home"] = 0;
                }
            }
        }

       

        // 根据 group 字段和 is_sidebar 字段将活动分为 Events、Rewards 和 Sidebar 三个分组
        $eventsActivities = [];
        $rewardsActivities = [];
        $sidebarActivities = [];
        
        foreach ($activity as $item) {
            $group = $item['group'] ?? 'null';
            $isSidebar = (int)($item['is_sidebar'] ?? 0);
            
            // 侧边栏分组：所有 is_sidebar = 1 的活动（独立于 group）
            if ($isSidebar === 1) {
                $sidebarActivities[] = $item;
            }
            
            // group 分组：互斥的，一个活动只能属于一个 group
            if ($group === 'Events') {
                $eventsActivities[] = $item;
            } elseif ($group === 'Rewards') {
                $rewardsActivities[] = $item;
            }
            // group 为 'null' 的活动不包含在 group 分组中
        }
        
        $channelInfo["activity"] = array_values($activity);
        $channelInfo["activity_groups"] = [
            "Events" => $eventsActivities,
            "Rewards" => $rewardsActivities,
            "Sidebar" => $sidebarActivities
        ];
        $channelInfo = array_merge($channelInfo->toArray(), self::getExperienceWithdrawStageInfo((int)($account->id ?? 0)));
        $channelInfo["ex_withdraw_amount"] = self::getExperienceWithdrawAmount();

        
        return $channelInfo;
    }
} 
