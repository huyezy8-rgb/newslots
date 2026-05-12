<?php

namespace app\api\controller;

use app\common\service\LuckyWheelService;

class LuckyWheel extends Base
{

    public function initialize(): void
    {
        parent::initialize();
    }

    /**
     * 获取幸运转盘信息
     * 接口地址：/api/lucky_wheel/info
     * 请求方式：GET
     * 需要登录：是
     */
    public function info()
    {

        $userId = $this->userInfo['id'];
        $result = LuckyWheelService::getUserWheels($userId);

        if ($result['code'] == 1) {
            $this->success(__('Get success'), $result['data']);
        } else {
            $this->error($result['msg']);
        }
    }

    /**
     * 执行转盘抽奖
     * 接口地址：/api/lucky_wheel/draw
     * 请求方式：POST
     * 需要登录：是
     * 请求参数：
     * - wheel_id: 转盘ID（必填）
     */
    public function draw()
    {

        $wheelId = $this->request->post('wheel_id');

        if (!$wheelId) {
            $this->error(__('Wheel ID cannot be empty'));
        }

        $userId = $this->userInfo['id'];
        $result = LuckyWheelService::draw($userId, $wheelId);

        if ($result['code'] == 1) {
            $this->success(__('Draw success'), $result['data']);
        } else {
            $this->error($result['msg']);
        }
    }

    /**
     * 获取用户转盘记录
     * 接口地址：/api/lucky_wheel/logs
     * 请求方式：GET
     * 需要登录：是
     * 请求参数：
     * - wheel_id: 转盘ID（可选，不传则获取所有转盘记录）
     * - page: 页码（可选，默认1）
     * - limit: 每页数量（可选，默认10）
     */
    public function logs()
    {

        $wheelId = $this->request->get('wheel_id');
        $page = $this->request->get('page', 1);
        $limit = $this->request->get('limit', 10);

        $userId = $this->userInfo['id'];
        $result = LuckyWheelService::getUserLogs($userId, $wheelId, $page, $limit);

        if ($result['code'] == 1) {
            $this->success(__('Get success'), $result['data']);
        } else {
            $this->error($result['msg']);
        }
    }
}
