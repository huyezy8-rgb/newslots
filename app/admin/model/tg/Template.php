<?php

namespace app\admin\model\tg;

use think\Model;

class Template extends Model
{
    protected $name = 'tg_message_template';

    protected $autoWriteTimestamp = 'int';

    protected $createTime = 'created_at';

    protected $updateTime = 'updated_at';

    protected $type = [];
}
