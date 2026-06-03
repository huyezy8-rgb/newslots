<?php

namespace app\admin\model\tg;

use think\Model;

class Log extends Model
{
    protected $name = 'tg_send_record';

    protected $autoWriteTimestamp = 'int';

    protected $createTime = 'created_at';

    protected $updateTime = false;

    protected $type = [
        'buttons_json' => 'json',
    ];

    public function bot(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(Bot::class, 'bot_id', 'id');
    }
}
