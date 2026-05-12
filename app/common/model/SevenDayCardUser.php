<?php

namespace app\common\model;

use think\Model;

/**
 * 七天卡用户开通记录
 */
class SevenDayCardUser extends Model
{
    protected $name = 'seven_day_card_user';

    protected $autoWriteTimestamp = false;

    protected $type = [
        'reward_main' => 'json',
        'reward_rescue' => 'json',
        'reward_daily' => 'json',
    ];
}


