<?php

namespace app\common\model;

use think\Model;

class TgBotConfig extends Model
{
    protected $name = 'tg_bot_config';

    protected $autoWriteTimestamp = 'int';

    protected $createTime = 'created_at';

    protected $updateTime = 'updated_at';

    protected $type = [];
}
