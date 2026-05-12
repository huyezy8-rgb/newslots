<?php

namespace app\api\controller;

use app\common\service\UserCollectGameService;
use think\facade\Cache;

class UserCollectGame extends Base
{
    /**
     * 添加收藏
     */
    public function add()
    {
        $gameData = $this->request->only(['game_id']);
        $result = UserCollectGameService::addCollect($this->userInfo->id, $gameData['game_id']);
        //清除游戏列表缓存
        $cacheKey = 'api_game_lists_cache:' . $this->userInfo->id;
        Cache::delete($cacheKey);
        if ($result['code'] === 1) {
            $this->success($result['msg']);
        } else {
            $this->error($result['msg']);
        }
    }

    /**
     * 获取收藏列表
     */
    public function list()
    {
        $data = $this->request->only(['page' => 1, 'size' => 10]);
        $list = UserCollectGameService::getCollectList($this->userInfo->id, $data['page'], $data['size']);
        $this->success(__('Success'), $list);
    }

    /**
     * 删除收藏
     */
    public function remove()
    {
        $gameId = $this->request->post('game_id');
        $result = UserCollectGameService::removeCollect($this->userInfo->id, $gameId);
        //清除游戏列表缓存
        $cacheKey = 'api_game_lists_cache:' . $this->userInfo->id;
        Cache::delete($cacheKey);
        if ($result['code'] === 1) {
            $this->success($result['msg']);
        } else {
            $this->error($result['msg']);
        }
    }
}