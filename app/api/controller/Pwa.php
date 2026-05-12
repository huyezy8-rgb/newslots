<?php

namespace app\api\controller;

use app\api\enum\CoinLog;
use app\common\service\ChannelInfoService;

class Pwa extends Base
{
     public function install()
     {
         try {
             ChannelInfoService::assertChannelActivityEnabled($this->userInfo, 'pwa');
         } catch (\Throwable $e) {
             $this->error($e->getMessage());
         }
         //判断是否领取过了
         $accountInfo = \app\common\model\AccountCoinLog::where([
             "user_id" => $this->userInfo->id,
             "log_type_id" => CoinLog::Pwa,
         ])->find();

         if ($accountInfo) {
             $this->error(__('Already received')); // 已领取
         }

         $configInfo = \app\admin\model\Config::where("name",
             "pwa_free")->find();
         $pwa_free  = 0;
         if ($configInfo) {
             $pwa_free = $configInfo["value"];
         }

        // 根据渠道双钱包开关决定使用哪个钱包
        $walletType = $this->getWalletTypeForReward($this->userInfo);
        
        $coinLog = new \app\common\model\AccountCoinLog();
        $coinLog->UpdateBalance($this->userInfo['id'], CoinLog::Pwa, $walletType,
            $pwa_free, CoinLog::getTypeText(CoinLog::Pwa));

         $this->success('ok'); // 成功
     }


}