<?php

namespace app\api\controller;

use app\api\enum\CoinLog;
use app\common\controller\Api;
use app\common\service\ChannelResolver;
use think\App;

class Channel extends Base
{
    protected array $noNeedLogin = ["getPixelIdByName"];

    public function getPixelIdByName(){
        $data = $this->request->only([
            'channel_name',
        ]);

        $channelInfo = ChannelResolver::resolve($data["channel_name"] ?? null, $this->request);
        if (!$channelInfo) {
            $this->error(__('Channel not found'));
        }

       $this->success("ok", [
           "facebook_pixel_id" => $channelInfo->facebook_pixel_id,
       ]);

    }
    /**
     *  获取渠道信息
     */
    public function info()
    {
        if ($this->request->isPost()) {
            $channelInfo
                = \app\common\model\ChannelList::withoutField("create_time,update_time")
                ->where("id", $this->userInfo->channel_id)
                ->find();

            if (!$channelInfo) {
                $channelInfo = \app\common\model\ChannelList::withoutField("create_time,update_time")->order('id', 'asc')->find();
            }

            $channelInfoService = new \app\common\service\ChannelInfoService();
            $this->success(__('Get success'), $channelInfoService->getChannelDetailInfo($this->userInfo, $channelInfo));
        }
    }
}
