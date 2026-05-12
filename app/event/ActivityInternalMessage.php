<?php

// app/event/ActivityInternalMessage.php
namespace app\event;

use app\common\model\Account;
use app\common\model\ChannelList;
use app\common\service\MessageService;
use think\facade\Db;
use think\facade\Log;
use think\facade\Lang;
use think\facade\Config;

class ActivityInternalMessage
{
    protected ?MessageService $messageService = null;
    
    protected function getMessageService(): MessageService
    {
        if ($this->messageService === null) {
            $this->messageService = new MessageService();
        }
        return $this->messageService;
    }
    /**
     * 根据渠道语言切换语言包
     * @param string|null $channelLang 渠道语言
     */
    private function setLanguageByChannel(?string $channelLang): void
    {
        // 如果没有渠道语言，使用默认语言
        if (!$channelLang) {
            $channelLang = Config::get('lang.default_lang', 'zh-cn');
        }
        
        $allowLangList = Config::get('lang.allow_lang_list', ['zh-cn', 'en', 'ar']);
        if (in_array($channelLang, $allowLangList)) {
            // 切换语言设置
            Lang::switchLangSet($channelLang);
            // 加载站内信语言包（事件类不在HTTP请求流程中，需要手动加载）
            $langPath = app()->getRootPath() . 'app/api/lang/' . $channelLang . '/message.php';
            if (file_exists($langPath)) {
                app()->lang->load([$langPath]);
            }
        }
    }

    public function handle($userId): void
    {
        try {
            Log::info("ActivityInternalMessage 事件触发，用户ID: $userId");

            $user = Account::find($userId);
            if (!$user) {
                Log::error("ActivityInternalMessage 事件：未找到用户 ID $userId");
                return;
            }
            
            // 获取渠道信息
            $channel_list = ChannelList::where(['id'=>$user->channel_id])->find();
            
            // 根据渠道语言切换语言包
            $channelLang = $channel_list['lang'] ?? null;
            $this->setLanguageByChannel($channelLang);
            
            //查询是否已经参加
            $message = Db::name("messages")
                ->where(["user_id"=>$userId,'type'=>'system','event_name'=>'login_frist'])
                ->find();

            if (!$message) {
                // 使用多语言配置
                $title = 'Benefits of the official Telegram channel';
                $content =' 1. Join the official Telegram channel and check for redeem code messages.
 2. We will distribute a certain amount of redeem codes weekly, first come, first served.
 3. The maximum reward you can get is $777. 
 4. The latest news and exclusive events will also be posted on the channel.
Click the [View More] button below to join immediately.!';
                
                //发信
                $this->getMessageService()->send(
                    [
                        'user_id'     => $userId,
                        'channel_id'  => $user['channel_id'] ?? 0,
                        'type'        => 'system',
                        'title'       => $title,
                        'content'     => $content,
                        'event_name' => 'login_frist',
                        'view_more' => $channel_list['kefu_channel_url'] ?? '',

                    ]
                );
            }
            //查询是否已参加活动
            $message = Db::name("messages")
                ->where(["user_id"=>$userId,'type'=>'gift','event_name'=>'internal_message'])
                ->find();
            if (!$message) {
                $config = Db::name("activity_internal_message")->where(['id'=>1])->find();

                // 根据渠道语言获取标题和内容
                // 如果配置中有多语言字段，优先使用；否则使用默认字段
                $title = $config['title'] ?? '';
                $content = $config['content'] ?? '';
                
                // 如果配置中有多语言字段（如 title_zh, title_en, title_ar），根据渠道语言选择
                $langSuffix = $channelLang ? str_replace('-', '_', $channelLang) : 'en';
                $titleKey = 'title_' . $langSuffix;
                $contentKey = 'content_' . $langSuffix;
                
                if (isset($config[$titleKey]) && !empty($config[$titleKey])) {
                    $title = $config[$titleKey];
                } elseif (empty($title)) {
                    // 如果配置中没有对应语言，使用语言包
                    $title = __('internal_message.activity_title');
                }
                
                if (isset($config[$contentKey]) && !empty($config[$contentKey])) {
                    $content = $config[$contentKey];
                } elseif (empty($content)) {
                    // 如果配置中没有对应语言，使用语言包
                    $content = __('internal_message.activity_content');
                }
                
                $amount = $config['amount'];
                $wallet_type = $config['wallet_type'];
                $valid_hours = $config['valid_hours'];
                $expire_time = ($valid_hours == 0) ? null : time() + $valid_hours * 3600;

                //发信
                $this->getMessageService()->send(
                    [
                        'user_id'     => $userId,
                        'channel_id'  => $user['channel_id'] ?? 0,
                        'type'        => 'gift',
                        'title'       => $title,
                        'content'     => $content,
                        'amount'      => $amount,
                        'wallet_type' => $wallet_type,
                        'expire_time' => $expire_time,
                        'event_name' => 'internal_message',

                    ]
                );

            }else{
                Log::info("已参加站内信活动" );
            }




        } catch (\Throwable $e) {
            Log::error("ActivityInternalMessage 执行异常：" . $e->getMessage());
        }
    }

}
