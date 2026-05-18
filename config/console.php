<?php
// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
return [
    // 指令定义
    'commands' => [
        'rest'=>'app\command\Reset',
        'UpdateExpiredOrdersp'=>'app\command\Recharge\UpdateExpiredOrders',
        'generate:operation-data'=>'app\command\GenerateOperationData',
        'generate:yesterday-operation-data'=>'app\command\GenerateYesterdayOperationData',
        'ClearUserData'=>'app\command\ClearUserData',
        'update:current-operation-data'=>'app\command\UpdateCurrentOperationData',
        'leaderboard:reward'=>'app\command\LeaderboardReward',
        'leaderboard:schedule'=>'app\command\LeaderboardRewardSchedule',
        'team:path'=>'app\command\TeamPathMigration',
        'commission:dispatch'=>'app\command\Commission',
        'member:weekly-reward'=>'app\command\MemberWeeklyReward',
        'member:monthly-reward'=>'app\command\MemberMonthlyReward',
        'seven-day-card:daily'=>'app\command\SevenDayCardDailyReward',
        'payment:test-callback'=>'app\command\Payment\TestCallback',
    ],
];
