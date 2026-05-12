<?php

namespace app\admin\controller\activity;

use app\common\controller\Backend;
use app\common\model\SevenDayCardConfig;

/**
 * 七天卡活动管理
 */
class SevenDayCard extends Backend
{
    /**
     * 模型类实例
     * @var object
     */
    protected object $model;

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new SevenDayCardConfig();
    }

    /**
     * 查看配置
     */
    public function index(): void
    {
        if ($this->request->isAjax()) {
            $config = SevenDayCardConfig::getOrCreateConfig();
            $this->success('', $config);
        }
    }

    /**
     * 编辑配置
     */
    public function edit(): void
    {
        if ($this->request->isPost()) {
            $params = $this->request->post();
            
            // 验证数据
            $this->validate($params, [
                'title' => 'require|max:100',
                'bet_multiple' => 'require|float|>=:0',
                'original_price' => 'require|float|>=:0',
                'current_price' => 'require|float|>=:0',
                'seven_day_rewards' => 'require|array|length:7',
                'rescue_rewards' => 'require|array|length:7',
                'daily_rewards' => 'require|array|length:7',
                'is_pwa' => 'in:0,1',
            ]);
            
            $config = SevenDayCardConfig::find(1);
            if (!$config) {
                $config = SevenDayCardConfig::create($params);
            } else {
                $config->save($params);
            }
            
            $this->success('保存成功');
        }
        
        $config = SevenDayCardConfig::getOrCreateConfig();
        $this->success('', $config);
    }
}
