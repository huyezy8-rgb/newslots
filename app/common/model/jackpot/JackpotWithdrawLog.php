<?php

namespace app\common\model\jackpot;

use think\Model;

class JackpotWithdrawLog extends Model
{
    protected $table = 'slot_jackpot_withdraw_log';

    protected $autoWriteTimestamp = true;
}