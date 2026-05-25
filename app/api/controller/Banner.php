<?php

namespace app\api\controller;

use think\facade\Db;
use think\facade\Cache;
use app\common\service\ChannelResolver;

/**
 * Banner图接口
 */
class Banner extends Base
{
    protected array $noNeedLogin = ['index'];

    /**
     * 获取Banner列表（按渠道过滤，返回完整字段）
     */
    public function index()
    {
        // 渠道识别：优先 channel_name 参数，其次 Referer 域名，最后默认第一条渠道
        $data = $this->request->only([
            'channel_name',
        ]);

        $channelInfo = ChannelResolver::resolve($data['channel_name'] ?? null, $this->request);
        if (!$channelInfo) {
            $this->error(__('Channel not found'));
        }

        // 渠道ID
        $channelId = $channelInfo ? intval($channelInfo['id']) : 0;

        try {
            $cacheKey = 'api_banner_list_' . ($channelInfo['id'] ?? 'all');
            $banners = Cache::get($cacheKey);

            if ($banners === null) {
                $currentTime = time();
                $query = Db::name('banner')
                    ->where('status', 1)
                    ->where(function ($query) use ($currentTime) {
                        $query->whereNull('start_time')
                              ->whereOr('start_time', '<=', $currentTime);
                    })
                    ->where(function ($query) use ($currentTime) {
                        $query->whereNull('end_time')
                              ->whereOr('end_time', '>=', $currentTime);
                    });

                // 按渠道绑定过滤：channel_ids 为空=全渠道；或包含当前渠道ID
                if ($channelId > 0) {
                    $query->whereRaw("(channel_ids = '' OR FIND_IN_SET(" . $channelId . ", channel_ids))");
                }

                $records = $query->order('sort', 'desc')
                    ->order('id', 'desc')
                    ->select()
                    ->toArray();

                // 处理返回数据：返回完整字段，图片转完整URL
                $banners = [];
                foreach ($records as $banner) {
                    $banners[] = [
                        'id' => intval($banner['id']),
                        'title' => $banner['title'] ?? '',
                        'content' => $banner['content'] ?? '',
                        'image' => $this->getFullImageUrl($banner['image'] ?? ''),
                        'link' => $banner['link'] ?? '',
                        'jump_type' => intval($banner['jump_type'] ?? 0),
                        'activity' => $banner['activity'] ?? '',
                        'sort' => intval($banner['sort'] ?? 0),
                        'status' => intval($banner['status'] ?? 0),
                        'start_time' => intval($banner['start_time'] ?? 0),
                        'end_time' => intval($banner['end_time'] ?? 0),
                    ];
                }

                // 缓存5分钟
                Cache::set($cacheKey, $banners, 300);
            }



        } catch (\Exception $e) {
            $this->error(__('Failed to get banner list') . ': ' . $e->getMessage());
        }
        $this->success(__('Banner list retrieved successfully'), [
            'list' => $banners,
            'total' => count($banners),
            'channel' => $channelInfo ? [
                'id' => intval($channelInfo['id']),
                'name' => (string)$channelInfo['name'],
                'domain' => (string)$channelInfo['domain'],
            ] : null,
        ]);
    }

    

    /**
     * Banner点击统计（可选功能）
     */
    public function click()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Request method must be POST'));
        }

        $id = intval($this->request->post('id', 0));
        
        if ($id <= 0) {
            $this->error(__('Invalid banner ID'));
        }

        try {
            $banner = Db::name('banner')->where('id', $id)->find();
            
            if (!$banner || !$this->isBannerActive($banner)) {
                $this->error(__('Banner not found or not available'));
            }

            // 这里可以添加点击统计逻辑
            // 例如：记录到统计表、增加点击计数等
            // $this->recordBannerClick($id);

            $this->success(__('Click recorded successfully'));

        } catch (\Exception $e) {
            $this->error(__('Failed to record click') . ': ' . $e->getMessage());
        }
    }

    /**
     * 检查Banner是否有效
     */
    private function isBannerActive(array $banner): bool
    {
        // 检查状态
        if (intval($banner['status']) !== 1) {
            return false;
        }

        $currentTime = time();

        // 检查开始时间
        if (!empty($banner['start_time']) && intval($banner['start_time']) > $currentTime) {
            return false;
        }

        // 检查结束时间
        if (!empty($banner['end_time']) && intval($banner['end_time']) < $currentTime) {
            return false;
        }

        return true;
    }

    /**
     * 获取完整图片URL
     */
    private function getFullImageUrl(string $image): string
    {
        if (empty($image)) {
            return '';
        }
        
        // 如果已经是完整URL，直接返回
        if (str_contains($image, 'http')) {
            return $image;
        }
        
        // 拼接完整URL
        $domain = $this->request->domain();
        return $domain . '/' . ltrim($image, '/');
    }
}
