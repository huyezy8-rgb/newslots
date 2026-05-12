<?php

namespace app\common\model;

use think\Model;

class PddInvitation extends Model
{
    protected $autoWriteTimestamp = true;

    public function insert_invitation($userInfo,$contribution_channel, $contribution_amount)
    {
        $pInfo = new PddProgress();
        $pddProgress = $pInfo->latest($userInfo['p_id']);

        $remain = $pddProgress->current_withdrawable - $pddProgress->total_reward;
        if ($contribution_amount > $remain) {
            $contribution_amount = $remain;
        }

        PddInvitation::create([
            "pdd_progress_id" => $pddProgress->id,
            "user_id" => $userInfo['p_id'],
            "invitee_id" => $userInfo['id'],
            "invitee_nickname" => $userInfo['nickname'],
            "contribution_channel" => $contribution_channel,
            "contribution_amount" => $contribution_amount,
        ]);

        if ($contribution_amount > 0) {
            $pInfo->update([
                "id"=> $pddProgress->id,
                "total_reward"=> $pddProgress['total_reward'] + $contribution_amount,
            ]);
        }
    }
}