<?php

namespace app\api\controller;

use app\common\model\GameLists;

class GameVip extends Base
{
    public function index()
    {
        $game_vip_config = get_sys_config(group: 'game_vip_375');
        $reward_list = $game_vip_config['activity_375_reward_list'];
        $game_list=GameLists::field(['id', 'game_id','game_name', 'game_name_en', 'icon', 'brand', 'fs'])
            ->where('status',1)
            ->where('fs',1)
            ->select()
            ->toArray();

        $icon_url = addslashes(rtrim(get_sys_config('icon_url') ?? '', '/') . '/');
        $this->success(__('Success'), compact('reward_list','game_list','icon_url'));
    }
}