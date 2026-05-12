<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\controller\Frontend;
use ba\GameHelper;
use think\facade\Db;
use think\facade\Log;

class Game extends Base
{
    protected array $noNeedLogin = [];


    /**
     *
     * 获取游戏列表
     * @throws \Throwable
     */
    /**
     * 获取游戏列表
     * @throws \Throwable
     */
    public function lists()
    {
        // 1. 参数处理
        $refresh = input('get.refresh/d', 0);
        $userId = $this->userInfo->id;
        $cacheKey = 'api_game_lists_cache:' . $userId;

        // 2. 尝试从缓存中读取，除非强制刷新
        $listCache = $refresh ? null : cache($cacheKey);

        if (!$listCache) {
            try {
                // 3. 获取启用状态的品牌列表并按sort排序
                $enabledBrands = Db::name('game_brand')
                    ->where('status', 1)
                    ->order('sort', 'DESC')
                    ->column('name', 'name');

                // 3.1. 获取品牌详细信息用于返回
                $brandLists = Db::name('game_brand')
                    ->field('name, icon')
                    ->where('status', 1)
                    ->order('sort', 'DESC')
                    ->select()
                    ->toArray();

                // 4. 构建查询条件
                $where = [
                    ['status', '=', 1]
                ];
                if (!empty($enabledBrands)) {
                    $where[] = ['brand', 'IN', array_keys($enabledBrands)];
                }

                // 5. 数据库查询
                $games = Db::name('game_lists')
                    ->field([
                        'id', 'game_id', 'game_name', 'game_name_en',
                        'icon', 'brand', 'sort', 'hot', 'fs'
                    ])
                    ->order([
                        'hot' => 'DESC',   // 热门游戏优先
                        'sort' => 'DESC',  // 数值越大越靠前
                        'id' => 'ASC'
                    ])
                    ->where($where)
                    ->select()
                    ->toArray();

                if (empty($games)) {
                    throw new Exception('No games found');
                }

                // 6. 获取用户收藏
                $collectGameIds = Db::name('user_collect_game')
                    ->where('user_id', $userId)
                    ->column('game_id');
                $collectGameIdMap = array_flip($collectGameIds);
                $collectCount = count($collectGameIds);

                // 7. 数据处理
                $grouped = [];
                $brands = ['hot']; // 热门品牌默认排在最前

                foreach ($games as $game) {
                    // 标记收藏状态
                    $game['is_collect'] = isset($collectGameIdMap[$game['game_id']]) ? 1 : 0;

                    // 按品牌分组
                    $brand = $game['brand'];
                    $grouped[$brand][] = $game;

                    // 如果是热门游戏，也添加到hot分组
                    if ($game['hot'] == 1) {
                        $grouped['hot'][] = $game;
                    }

                    // 收集品牌列表(除hot外)
                    if ($brand !== 'hot' && !in_array($brand, $brands)) {
                        $brands[] = $brand;
                    }
                }

                // 8. 构建缓存数据
                $listCache = [
                    'brands' => $brands,
                    'grouped' => $grouped,
                    'brand_lists' => $brandLists,
                    'timestamp' => time(),
                    'collect_count' => $collectCount
                ];

                // 9. 写入缓存
                cache($cacheKey, $listCache, 3600,tag:'game_lists');

            } catch (Exception $e) {
                // 记录错误日志
                Log::error('获取游戏列表失败: ' . $e->getMessage());
                $this->error(__('获取游戏列表失败'));
            }
        }

        // 10. 返回结果
        $this->success(__('OK'), [
            'icon_url' => rtrim(get_sys_config('icon_url', ''), '/') . '/',
            'list' => $listCache['grouped'] ?? [],
            'brands' => $listCache['brands'] ?? ['hot'],
            'brand_lists' => $listCache['brand_lists'] ?? [],
            'collect_count' => $listCache['collect_count'] ?? 0,
            'cache_time' => $listCache['timestamp'] ?? 0
        ]);
    }



    public function get_url()
    {
        $game_id = input('game_id', 0);
        $lang = request()->header('accept-language', 'en');
        $refresh = input('get.refresh/d', 0); // 是否刷新缓存
        $user_id = $this->userInfo->id; // 示例：实际中应为 $this->auth->getUser()->id();

        if (!$game_id) {
            $this->error(__('Game ID cannot be empty'));
        }

        //验证用户游戏状态
        if($this->userInfo->game_status == 0){
            $this->error(__('Your account is not allowed to play games'));
        }

        if(!$this->userInfo->player_id){
            event('GameRegister',$user_id);
        }
        // 查询游戏信息
        $game_info = Db::name('game_lists')->where('game_id|id', $game_id)->find();

        if (!$game_info) {
            $this->error(__('Game not found.'));
        }
        $game_id = $game_info['game_id'];

        // 缓存 key 唯一组合
        $cacheKey = "game_url_{$user_id}_{$game_id}_{$lang}";

        // 如果未命中缓存或用户请求刷新
        if (!$refresh && ($url = cache($cacheKey))) {
            $this->success(__('OK'), $url);
        }

        // 查询游戏信息
        $game_info = Db::name('game_lists')->where(['game_id' => $game_id])->find();

        if (!$game_info) {
            $this->error(__('Game not found'));
        }

  $data = [
    "UserID" => (string)$user_id,
    "GameID" => $game_info['game_id'],
    "Language" => $lang
];

$res = GAMEHelper::curlGameApi(
    '/api/v1/game/launch',
    $data,
    'POST',
    (int)$this->userInfo['switch_wallet']
);
  if (empty($res['data']['url'])) {
    Log::record('游戏启动失败：' . json_encode($res, JSON_UNESCAPED_UNICODE), 'error');
    $this->error('游戏启动失败，请稍后再试');
}

$url = $res['data']['url'];
        // 写入缓存，有效期 600 秒
        cache($cacheKey, $url, 600);

        //记录登录游戏次数
        $currentDate = date('Y-m-d');

        // 查询是否已有记录
        $existingLog = Db::name('user_login_game_log')
            ->where([
                'user_id' => $user_id,
                'game_id' => $game_info['id'],
                'login_date' => $currentDate
            ])
            ->find();

        if ($existingLog) {
            // 更新登录次数
            Db::name('user_login_game_log')
                ->where('id', $existingLog['id'])
                ->inc('login_count')
                ->update(['update_time' => time()]);
        } else {
            // 插入新记录
            Db::name('user_login_game_log')->insert([
                'user_id' => $user_id,
                'channel_id' => $this->userInfo['channel_id'] ?? 0,
                'game_id' => $game_info['id'],
                'login_date' => $currentDate,
                'login_count' => 1,
                'create_time' => time()
            ]);
        }

        $this->success(__('OK'), $url);
    }
}



