<?php

namespace app\common\service;

use app\common\model\UserCollectGame;
use think\facade\Db;

class UserCollectGameService
{
    /**
     * 添加收藏
     */
    public static function addCollect($userId, $gameId)
    {
        // 检查是否已收藏
        $exists = UserCollectGame::where([
            'user_id' => $userId,
            'game_id' => $gameId,
        ])->find();
    
        if ($exists) {
            //取消收藏
            return self::removeCollect($userId,$gameId);
        }
    
        // 查询游戏数据
        $gameData = Db::name('game_lists')->where('id|game_id', $gameId)->find();
        if (!$gameData) {
            return ['code' => 0, 'msg' => __('service.game_does_not_exist')];
        }
    
        // 添加收藏
        $collect = new UserCollectGame();
        $collect->save([
            'user_id' => $userId,
            'game_id' => $gameId,
            'game_name' => $gameData['game_name'],
            'game_name_en' => $gameData['game_name_en'],
            'game_icon' => $gameData['icon'],
        ]);
    
        return ['code' => 1, 'msg' => __('service.collection_successful')];
    }

    /**
     * 获取收藏列表
     */
    public static function getCollectList($userId, $page = 1, $size = 10)
    {
        return UserCollectGame::where('user_id', $userId)
            ->order('create_time', 'desc')
            ->paginate(['page' => $page, 'list_rows' => $size]);
    }

    /**
     * 删除收藏
     */
    public static function removeCollect($userId, $gameId)
    {
        $result = UserCollectGame::where([
            'user_id' => $userId,
            'game_id' => $gameId,
        ])->delete();

        if ($result) {
            return ['code' => 1, 'msg' => __('service.uncollection_successful')];
        }

        return ['code' => 0, 'msg' => __('service.uncollection_failed')];
    }
}