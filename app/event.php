<?php
// 事件定义文件
// config/event.php
return [
    // 自动绑定事件名称与类
    'bind' => [],

    'listen' => [
        'GameRegister' => [\app\event\GameRegister::class],
        'InternalMessage' => [\app\event\ActivityInternalMessage::class],
        'DayGold' => [\app\event\DayGold::class],
        'LevelUp' => [\app\event\LevelUp::class],
        'InviteMember' => [\app\event\InviteMember::class],
        'DepositVip' => [\app\event\DepositVip::class],
        'FacebookConversion' => [\app\event\FacebookConversion::class],
        'FirstDeposit270' => [\app\event\FirstDeposit270::class],
        'GameVip' => [\app\event\GameVip::class],
        'LeaderboardStats' => [\app\event\LeaderboardStats::class],
        'AppInit'  => [],
        'HttpRun'  => [],
        'HttpEnd'  => [],
        'LogLevel' => [],
        'LogWrite' => [],
    ],

    'subscribe' => [],
];
