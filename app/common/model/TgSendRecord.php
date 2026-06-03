<?php

namespace app\common\model;

use think\Model;

class TgSendRecord extends Model
{
    protected $name = 'tg_send_record';

    protected $autoWriteTimestamp = 'int';

    protected $createTime = 'created_at';

    protected $updateTime = false;

    protected $type = [
        'buttons_json' => 'json',
    ];
}
