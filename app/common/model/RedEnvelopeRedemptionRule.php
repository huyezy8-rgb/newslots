<?php

namespace app\common\model;

use think\Model;

class RedEnvelopeRedemptionRule extends Model
{
    protected $name = 'red_envelope_redemption_rule';

    protected $autoWriteTimestamp = 'int';

    protected $createTime = 'created_at';

    protected $updateTime = 'updated_at';
}
