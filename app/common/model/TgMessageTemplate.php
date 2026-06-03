<?php

namespace app\common\model;

use think\Model;

class TgMessageTemplate extends Model
{
    protected $name = 'tg_message_template';

    protected $autoWriteTimestamp = 'int';

    protected $createTime = 'created_at';

    protected $updateTime = 'updated_at';

    protected $type = [];
}
