<?php

namespace app\admin\model\tg;

use think\Model;

class Rule extends Model
{
    protected $name = 'red_envelope_redemption_rule';

    protected $autoWriteTimestamp = 'int';

    protected $createTime = 'created_at';

    protected $updateTime = 'updated_at';
}
