<?php

namespace app\admin\controller\game;

use app\common\controller\Backend;
use ba\GameHelper;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Log;

/**
 * 游戏列表管理
 */
class Lists extends Backend
{
    /**
     * Lists模型对象
     * @var object
     * @phpstan-var \app\admin\model\game\Lists
     */
    protected object $model;

    /**
     * 排除字段
     * @var array
     */
    protected array|string $preExcludeFields = ['id', 'update_time', 'create_time'];

    /**
     * 快速搜索字段
     * @var array
     */
    protected string|array $quickSearchField = ['id', 'game_name', 'game_name_en','game_id','brand'];

    /**
     * 可编辑字段
     * @var array
     */
    protected array $editableFields = ['sort', 'hot', 'fs', 'status'];

    /**
     * 每批处理的数量
     * @var int
     */
    protected $batchSize = 100;

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\game\Lists();
    }

    /**
     * 查看
     * @throws Throwable
     */
    public function index(): void
    {
        if ($this->request->param('select')) {
            $this->select();
        }

        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $iconUrl = addslashes(rtrim(get_sys_config('icon_url') ?? '', '/') . '/');
        $field = is_array($this->indexField)
            ? array_merge($this->indexField, ["CONCAT('{$iconUrl}', icon) as icon"])
            : array_merge(explode(',', $this->indexField), ["CONCAT('{$iconUrl}', icon) as icon"]);

        // 获取启用状态的品牌列表
        $enabledBrands = Db::name('game_brand')
            ->where('status', 1)
            ->order('sort desc')
            ->column('name');

        // 如果没有启用状态的品牌，则显示所有游戏
        if (!empty($enabledBrands)) {
            $where[] = ['brand', 'in', $enabledBrands];
        }

        // 添加渠道数据限制
        $this->addChannelDataLimit($where);


        $res = $this->model
            ->field($field)
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate($limit);

        // 调试：打印SQL语句
        if ($this->request->param('debug')) {
            echo "SQL语句: " . $this->model->getLastSql() . "\n";
        }

        $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

    /**
     * 获取品牌列表
     * @return \think\response\Json
     */
    public function getBrands(): void
    {
        try {
            // 从slot_game_brand表获取品牌数据
            $brands = Db::name('game_brand')
                ->field('id, name, icon, sort, status')
                ->where('status', 1) // 只获取启用的品牌
                ->order('sort DESC, id ASC')
                ->select()
                ->toArray();


        } catch (\Throwable $e) {
            $this->error('获取品牌列表失败：' . $e->getMessage());
        }
        $this->success('', $brands);
    }

    /**
     * 更新游戏列表
     * @return \think\response\Json
     */
    public function updateGameList()
    {
        try {
            // 设置脚本最大执行时间
            set_time_limit(0);
            // 设置内存限制
            ini_set('memory_limit', '512M');

            // 开启事务
            Db::startTrans();

            // 获取中文游戏列表
           $res_zh = GameHelper::curlGameApi('/api/v1/game/list', ["Language" => 'zh'], 'POST', 1);

// 获取英文游戏列表
$res_en = GameHelper::curlGameApi('/api/v1/game/list', ["Language" => 'en'], 'POST', 1);



if ($res_zh['code'] !== 0) {
    throw new \Exception('获取中文游戏列表失败：' . (isset($res_zh['msg']) ? $res_zh['msg'] : '未知错误'));
}

if ($res_en['code'] !== 0) {
    throw new \Exception('获取英文游戏列表失败：' . (isset($res_en['msg']) ? $res_en['msg'] : '未知错误'));
}
         if (empty($res_zh['data']['list']) || empty($res_en['data']['list'])) {
    throw new \Exception('游戏列表为空');
}
            // 将英文列表转换为以ID为键的关联数组
            $enGames = [];
    foreach ($res_en['data']['list'] as $game) {
    $enGames[$game['id']] = $game;
}
            // 获取所有现有游戏ID
            $existingGames = $this->model->column('game_id');
            $existingGames = array_flip($existingGames);

            // 准备批量数据
            $updateData = [];
            $insertData = [];
            $errorCount = 0;
            $errorMsgs = [];
            $currentTime = time();

            // 处理游戏数据
            foreach ($res_zh['data']['list'] as $game) {
                try {
                    // 验证必要字段
                    if (empty($game['id']) || empty($game['name'])) {
    throw new \Exception('游戏数据缺少必要字段');
}

                    // 解析游戏ID获取厂商信息
                  $arr = explode('_', $game['id']);
                    $provider = $arr[0] ?? '';

                    // 获取对应的英文数据
                    $enGame = $enGames[$game['id']] ?? null;
                    // 准备数据
                    $data = [
    'game_id' => $game['id'],
    'game_name' => $game['name'],
    'game_name_en' => $enGame['name'] ?? '',
    'type' => intval($game['type'] ?? 0),
    'icon' => $game['id'] . '.webp',
    'source'=> 'popapi',
    'brand' => $game['manufacturer'] ?? $provider,
    'update_time' => $currentTime
];

                    // 检查游戏是否已存在
                   if (isset($existingGames[$game['id']])) {

                        $updateData[] = $data;
                    } else {
                        $data['create_time'] = $currentTime;
                        $data['status'] =1;
                        $data['fs'] = 0;
                        $insertData[] = $data;
                    }

                    // 当数据达到批处理大小时执行批量操作
                    if (count($updateData) >= $this->batchSize) {
                        $this->batchUpdate($updateData);
                        $updateData = [];
                    }
                    if (count($insertData) >= $this->batchSize) {
                        $this->batchInsert($insertData);
                        $insertData = [];
                    }
                } catch (\Exception $e) {
                    $errorCount++;
                  $errorMsg = "游戏 {$game['id']} 处理失败：" . $e->getMessage();
                    $errorMsgs[] = $errorMsg;
                    // 记录错误日志
                    $this->logError($errorMsg, $game, $data ?? []);
                }
            }

            // 处理剩余的数据
            if (!empty($updateData)) {
                $this->batchUpdate($updateData);
            }
            if (!empty($insertData)) {
                $this->batchInsert($insertData);
            }

            // 提交事务
            Db::commit();

            $successCount = ($this->updateCount ?? 0) + ($this->insertCount ?? 0);

            // 清除API游戏列表缓存
            $this->clearApiGameListCache();

            $result = [
                'success_count' => $successCount,
                'error_count' => $errorCount,
                'error_messages' => $errorMsgs
            ];

        } catch (\Throwable $e) {
            // 回滚事务
            Db::rollback();
            $this->error('游戏列表更新失败：' . $e->getMessage());
        }
        $this->success('游戏列表更新成功', $result);
    }

    /**
     * 批量更新
     * @param array $data
     */
    protected function batchUpdate(array $data): void
    {
        try {
            foreach ($data as $item) {
                $this->model->where('game_id', $item['game_id'])->update($item);
            }
            $this->updateCount = ($this->updateCount ?? 0) + count($data);
        } catch (\Exception $e) {
            Log::error('批量更新失败：' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 批量插入
     * @param array $data
     */
    protected function batchInsert(array $data): void
    {
        try {
            $this->model->insertAll($data);
            $this->insertCount = ($this->insertCount ?? 0) + count($data);
        } catch (\Exception $e) {
            Log::error('批量插入失败：' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 记录错误日志
     * @param string $message
     * @param array $game
     * @param array $data
     */
    protected function logError(string $message, array $game, array $data): void
    {
        Log::error($message.json_encode([
                'game' => $game,
                'data' => $data
            ]));
    }

    /**
     * 清除API游戏列表缓存
     */
    protected function clearApiGameListCache(): void
    {
        Cache::tag('game_lists')->clear();
        return ;
    }

    /**
     * 添加渠道数据限制
     * @param array $where
     */
    protected function addChannelDataLimit(array &$where): void
    {
        // 如果是超级管理员，不限制数据
        if ($this->auth->isSuperAdmin()) {
            return;
        }

        // 获取当前管理员的渠道ID
        $currentAdmin = \app\admin\model\Admin::find($this->auth->id);
        if (!$currentAdmin || is_null($currentAdmin->channel_id)) {
            // 如果没有绑定渠道，不限制数据
            return;
        }

        // 获取渠道信息
        $channel = \app\admin\model\channel\Listsss::find($currentAdmin->channel_id);
        if (!$channel) {
            return;
        }

        // 根据渠道的品牌限制游戏数据
        if (!empty($channel->brand)) {
            $brands = explode(',', $channel->brand);
            $where[] = ['brand', 'in', $brands];
        }
    }

    /**
     * 若需重写查看、编辑、删除等方法，请复制 @see \app\admin\library\traits\Backend 中对应的方法至此进行重写
     */
}