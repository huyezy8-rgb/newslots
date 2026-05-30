<?php

namespace app\common\service;

use app\api\enum\CoinLog;
use think\db\Query;
use think\facade\Db;

class UserActiveService
{
    private const ACTIVITY_LOG_TYPES = [
        CoinLog::InternalMessage,
        CoinLog::DayGold,
        CoinLog::BindMobile,
        CoinLog::PopUp,
        CoinLog::Pwa,
        CoinLog::FirstDeposit270,
        CoinLog::FirstDepositDaily,
        CoinLog::RescueFunds,
        CoinLog::DepositVip,
        CoinLog::RedEnvelope,
        CoinLog::FirstDeposit25,
        CoinLog::GameVip375,
        CoinLog::FirstVip49,
        CoinLog::FirstVip6,
        CoinLog::MemberUpgrade,
        CoinLog::ChestBox,
        CoinLog::LeaderboardDaily,
        CoinLog::LeaderboardWeekly,
        CoinLog::LeaderboardMonthly,
        CoinLog::LuckyWheel,
        CoinLog::MemberWeeklyReward,
        CoinLog::MemberMonthlyReward,
        CoinLog::PDDInitReward,
        CoinLog::PDDInviteReward,
        CoinLog::PDDQualifiedFill,
        CoinLog::PDDYqCz,
        CoinLog::SevenDayCard,
    ];

    public static function isUserActive(int $userId, string $date): bool
    {
        [$startTime, $endTime] = self::dayRange($date);
        return !empty(self::getActiveUserIds($startTime, $endTime, [$userId]));
    }

    public static function getActiveUserIdsByDate(string $date, ?array $userIds = null, ?int $channelId = null): array
    {
        [$startTime, $endTime] = self::dayRange($date);
        return self::getActiveUserIds($startTime, $endTime, $userIds, $channelId);
    }

    public static function getActiveUserIds(int $startTime, int $endTime, ?array $userIds = null, ?int $channelId = null): array
    {
        $userIds = self::normalizeUserIds($userIds);
        if ($channelId !== null) {
            $channelUserIds = Db::name('account')->where('channel_id', $channelId)->column('id');
            $channelUserIds = self::normalizeUserIds($channelUserIds);
            $userIds = $userIds === null ? $channelUserIds : array_values(array_intersect($userIds, $channelUserIds));
        }

        if ($userIds !== null && empty($userIds)) {
            return [];
        }

        $activeUserIds = [];
        $dateStart = date('Y-m-d H:i:s', $startTime);
        $dateEnd = date('Y-m-d H:i:s', $endTime);

        self::mergeUserIds($activeUserIds, self::columnUserIds(
            Db::name('game_transactions')
                ->where('req_time', '>=', $dateStart)
                ->where('req_time', '<=', $dateEnd),
            'user_id',
            $userIds
        ));

        self::mergeUserIds($activeUserIds, self::columnUserIds(
            Db::name('recharge_orders')
                ->where('created_at', '>=', $startTime)
                ->where('created_at', '<=', $endTime),
            'user_id',
            $userIds
        ));

        self::mergeUserIds($activeUserIds, self::columnUserIds(
            Db::name('withdraw_orders')
                ->where('create_time', '>=', $startTime)
                ->where('create_time', '<=', $endTime),
            'user_id',
            $userIds
        ));

        self::mergeUserIds($activeUserIds, self::columnUserIds(
            Db::name('activity_daygold_user')
                ->where('last_receive_time', '>=', $startTime)
                ->where('last_receive_time', '<=', $endTime),
            'uid',
            $userIds,
            'uid'
        ));

        self::mergeUserIds($activeUserIds, self::columnUserIds(
            Db::name('messages')
                ->where('type', 'gift')
                ->where('receive_status', 1)
                ->where('updated_at', '>=', $startTime)
                ->where('updated_at', '<=', $endTime),
            'user_id',
            $userIds
        ));

        self::mergeUserIds($activeUserIds, self::columnUserIds(
            Db::name('lucky_wheel_logs')
                ->where('createtime', '>=', $startTime)
                ->where('createtime', '<=', $endTime),
            'user_id',
            $userIds
        ));

        self::mergeUserIds($activeUserIds, self::columnUserIds(
            Db::name('chest_receive_log')
                ->where('createtime', '>=', $startTime)
                ->where('createtime', '<=', $endTime),
            'user_id',
            $userIds
        ));

        self::mergeUserIds($activeUserIds, self::getTaskActivityUserIds($startTime, $endTime, $userIds));
        self::mergeUserIds($activeUserIds, self::getActivityRewardLogUserIds($startTime, $endTime, $userIds));

        sort($activeUserIds);
        return $activeUserIds;
    }

    private static function getTaskActivityUserIds(int $startTime, int $endTime, ?array $userIds): array
    {
        $activeUserIds = [];

        foreach (['activity_first_deposit_daily_user', 'activity_first_deposit_25_user'] as $table) {
            self::mergeUserIds($activeUserIds, self::columnUserIds(
                Db::name($table)
                    ->where('receive_status', 2)
                    ->where('update_time', '>=', $startTime)
                    ->where('update_time', '<=', $endTime),
                'user_id',
                $userIds
            ));
        }

        foreach (['activity_first_deposit_270_user', 'activity_deposit_vip_user'] as $table) {
            self::mergeUserIds($activeUserIds, self::columnUserIds(
                Db::name($table)
                    ->where('update_time', '>=', $startTime)
                    ->where('update_time', '<=', $endTime)
                    ->where(function (Query $query) {
                        $query->where('bet_num_status', 1)->whereOr('bet_test_status', 1);
                    }),
                'user_id',
                $userIds
            ));
        }

        self::mergeUserIds($activeUserIds, self::columnUserIds(
            Db::name('activity_first_deposit_270_user')
                ->where('day_reward_time', '>=', $startTime)
                ->where('day_reward_time', '<=', $endTime),
            'user_id',
            $userIds
        ));

        return $activeUserIds;
    }

    private static function getActivityRewardLogUserIds(int $startTime, int $endTime, ?array $userIds): array
    {
        return self::columnUserIds(
            Db::name('account_coin_log')
                ->whereIn('log_type_id', self::ACTIVITY_LOG_TYPES)
                ->where('create_time', '>=', $startTime)
                ->where('create_time', '<=', $endTime),
            'user_id',
            $userIds
        );
    }

    private static function columnUserIds(Query $query, string $column, ?array $userIds, ?string $whereColumn = null): array
    {
        if ($userIds !== null) {
            $query->whereIn($whereColumn ?: $column, $userIds);
        }

        return self::normalizeUserIds($query->distinct(true)->column($column)) ?: [];
    }

    private static function mergeUserIds(array &$target, array $userIds): void
    {
        foreach ($userIds as $userId) {
            $target[(int)$userId] = (int)$userId;
        }
    }

    private static function normalizeUserIds(?array $userIds): ?array
    {
        if ($userIds === null) {
            return null;
        }

        return array_values(array_unique(array_filter(array_map('intval', $userIds))));
    }

    private static function dayRange(string $date): array
    {
        return [
            strtotime($date . ' 00:00:00'),
            strtotime($date . ' 23:59:59'),
        ];
    }
}
