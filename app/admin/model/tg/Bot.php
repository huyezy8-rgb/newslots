<?php

namespace app\admin\model\tg;

use think\Model;

class Bot extends Model
{
    protected $name = 'tg_bot_config';

    protected $autoWriteTimestamp = 'int';

    protected $createTime = 'created_at';

    protected $updateTime = 'updated_at';

    protected $type = [];
}
