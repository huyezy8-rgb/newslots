<?php

namespace app\common\model;

use think\Model;

class PddProgress extends Model
{
    protected $autoWriteTimestamp = true;

    /**
     * 获取用户唯一进度（没有则创建）
     */
    public function latest(int $userId): PddProgress
    {
        $info = $this->where(['user_id' => $userId])->find();
        if ($info) {
            return $info;
        }
        return $this->create([
            'user_id' => $userId,
            'valid_invite_count' => 0,
            'draw_times' => 1, //赠送首次抽奖
            'first_draw_done' => 0,
            'direct_cash_state' => 0,
        ]);
    }
}
