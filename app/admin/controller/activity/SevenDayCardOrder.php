<?php

namespace app\admin\controller\activity;

use Throwable;
use app\common\controller\Backend;
use app\common\model\SevenDayCardUser as SevenDayCardUserModel;
use app\common\model\ChannelList;

/**
 * 七天卡开通订单记录
 */
class SevenDayCardOrder extends Backend
{
    /**
     * @var object
     * @phpstan-var SevenDayCardUserModel
     */
    protected object $model;

    protected string|array $quickSearchField = ['id', 'order_no', 'user_id'];

    /**
     * 列表字段（可按需裁剪）
     * @var string|array
     */
    protected string|array $indexField = '*';

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new SevenDayCardUserModel();
    }

    /**
     * 订单列表
     * @throws Throwable
     */
    public function index(): void
    {
        if ($this->request->param('select')) {
            $this->select();
        }

        list($where, $alias, $limit, $order) = $this->queryBuilder();

        // 渠道权限：按记录上的 channel_id 过滤
        $where = $this->addChannelFilter($where, 'channel_id');

        $res = $this->model
            ->field($this->indexField)
            ->alias($alias)
            ->where($where)
            ->order($order ?: 'id desc')
            ->paginate($limit);

        // 提取列表所有渠道ID
        $list = $res->items();
        $channelIds = array_unique(array_column($list, 'channel_id'));
        $channels = [];
        if ($channelIds) {
            $channels = \app\common\model\ChannelList::where('id', 'in', $channelIds)->column('name', 'id');
        }
        foreach ($list as &$row) {
            $row['channel_name'] = isset($channels[$row['channel_id']]) ? $channels[$row['channel_id']] : '';
        }
        unset($row);

        $this->success('', [
            'list'   => $list,
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

    /**
     * 订单奖励领取记录（弹窗详情）
     * @throws Throwable
     */
    public function detail(): void
    {
        $id = (int)$this->request->param('id');
        if ($id <= 0) {
            $this->error('参数错误');
        }

        $row = $this->model->where('id', $id)->find();
        if (!$row) {
            $this->error('记录不存在');
        }

        // 渠道权限校验
        $this->checkChannelPermission((int)$row['channel_id']);

        // 获取渠道名
        $channel = null;
        $channelName = '';
        if ($row['channel_id']) {
            $channel = ChannelList::where('id', $row['channel_id'])->field('name')->find();
            $channelName = $channel ? $channel['name'] : '';
        }
        $orderArr = $row->toArray();
        $orderArr['channel_name'] = $channelName;

        $rewards = [
            'main'   => $this->formatRewards((array)($row['reward_main'] ?: [])),
            'rescue' => $this->formatRewards((array)($row['reward_rescue'] ?: [])),
            'daily'  => $this->formatRewards((array)($row['reward_daily'] ?: [])),
        ];

        $this->success('', [
            'order'   => $orderArr,
            'rewards' => $rewards,
        ]);
    }

    /**
     * 将[{reward,status}]格式转为带 day 序号的简单数组
     * @param array $list
     * @return array
     */
    protected function formatRewards(array $list): array
    {
        $result = [];
        foreach ($list as $idx => $item) {
            $result[] = [
                'day'    => $idx + 1,
                'reward' => isset($item['reward']) ? (float)$item['reward'] : 0,
                'status' => isset($item['status']) ? (int)$item['status'] : 0,
            ];
        }
        return $result;
    }
}


