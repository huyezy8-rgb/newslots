<?php

namespace app\admin\controller\activity;

use app\common\controller\Backend;
use app\common\model\activity\Chest as ChestModel;
use app\common\model\activity\ChestConfig as ChestConfigModel;
use think\facade\Cache;
use think\facade\Request;

/**
 * 宝箱活动管理
 */
class Chest extends Backend
{
    /**
     * Chest模型对象
     * @var object
     * @phpstan-var \app\common\model\activity\Chest
     */
    protected object $model;

    /**
     * ChestConfig模型对象
     * @var object
     * @phpstan-var \app\common\model\activity\ChestConfig
     */
    protected object $configModel;

    protected string|array $defaultSortField = 'sort,desc';

    protected array|string $preExcludeFields = ['id', 'createtime', 'updatetime'];

    protected string|array $quickSearchField = ['id', 'name'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new ChestModel();
        $this->configModel = new ChestConfigModel();
    }

    /**
     * 宝箱活动配置
     */
    public function config()
    {
        if ($this->request->isPost()) {
            $data = Request::post();
            
            // 验证必填字段
            if (empty($data['name'])) {
                $this->error('活动名称不能为空');
            }
            
            if (!isset($data['bet_multiple']) || $data['bet_multiple'] === '') {
                $this->error('打码倍数不能为空');
            }
            
            if (empty($data['banner_image'])) {
                $this->error('Banner图不能为空');
            }
            
            if (empty($data['default_image'])) {
                $this->error('默认图片不能为空');
            }
            
            if (empty($data['waiting_image'])) {
                $this->error('待领取图片不能为空');
            }
            
            if (empty($data['received_image'])) {
                $this->error('已领取图片不能为空');
            }
            
            // 确保数值字段为数字
            $data['bet_multiple'] = floatval($data['bet_multiple'] ?? 0);
            $data['status'] = intval($data['status'] ?? 1);
            
            // 检查是否已存在配置
            $existingConfig = $this->configModel->find(1);

            if ($existingConfig) {
                // 更新现有配置
                $data['id'] = 1;
                $result = $existingConfig->save($data);
            } else {
                // 创建新配置
                $data['id'] = 1;
                $result = $this->configModel->save($data);
            }

            if ($result === false) {
                $this->error('保存失败');
            }

            // 清除缓存
            $cacheKey = 'chest:config';
            Cache::store('redis')->del($cacheKey);

            $this->success('配置保存成功');
        }

        // 获取现有配置
        $config = $this->configModel->find(1);
        $data = [];

        if ($config) {
            $data = [
                'id' => $config->id,
                'name' => $config->name,
                'status' => $config->status,
                'bet_multiple' => $config->bet_multiple,
                'banner_image' => $config->banner_image,
                'default_image' => $config->default_image,
                'waiting_image' => $config->waiting_image,
                'received_image' => $config->received_image
            ];
        }

        $this->success('获取配置成功', $data);
    }

    /**
     * 若需重写查看、编辑、删除等方法，请复制 @see \app\admin\library\traits\Backend 中对应的方法至此进行重写
     */
}