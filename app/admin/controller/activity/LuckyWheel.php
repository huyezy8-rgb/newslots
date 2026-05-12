<?php

namespace app\admin\controller\activity;

use app\admin\model\LuckyWheelConfig;
use app\common\controller\Backend;
use app\common\service\LuckyWheelCacheService;
use think\facade\Db;

/**
 * 幸运转盘主配置
 */
class LuckyWheel extends Backend
{
    /**
     * 模型类实例
     * @var object
     */
    protected object $model;

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new LuckyWheelConfig();
    }

    /**
     * 查看
     */
    public function index(): void
    {
        if ($this->request->isAjax()) {
            $config = $this->model->find(1);
            if (!$config) {
                // 创建默认配置
                $config = $this->model->create([
                    'title' => '幸运转盘',
                    'banner_image' => '',
                    'bet_multiple' => 1.0,
                    'status' => 1
                ]);
            }
            $this->success('', $config);
        }
    }

    /**
     * 编辑
     */
    public function edit(): void
    {
        if ($this->request->isPost()) {
            $params = $this->request->post();
            
            $config = $this->model->find(1);
            if (!$config) {
                $config = $this->model->create($params);
            } else {
                $config->save($params);
            }
            
            // 清除幸运转盘相关缓存
            $this->clearLuckyWheelCache();
            
            $this->success('保存成功');
        }
        
        $config = $this->model->find(1);
        $this->success('', $config);
    }
    
    /**
     * 清除幸运转盘配置缓存
     */
    private function clearLuckyWheelCache(): void
    {
        try {
            // 只清除配置缓存
            LuckyWheelCacheService::clearConfigCache();
            
        } catch (\Exception $e) {
            // 记录错误日志，但不影响主流程
            \think\facade\Log::error('清除幸运转盘配置缓存失败: ' . $e->getMessage());
        }
    }
} 