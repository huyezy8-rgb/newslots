<?php

namespace app\api\controller;

use app\api\enum\CoinLog;
use app\common\service\ChannelInfoService;

class PopUp extends Base
{
     public function receive()
     {
         try {
             ChannelInfoService::assertChannelActivityEnabled($this->userInfo, 'pop_up');
         } catch (\Throwable $e) {
             $this->error($e->getMessage());
         }
          //判断是否领取过了
          $accountInfo = \app\common\model\AccountCoinLog::where([
              "user_id" => $this->userInfo->id,
              "log_type_id" => CoinLog::PopUp,
          ])->find();


          if ($accountInfo) {
               $this->error(__('Already received')); // 已领取
          }

         $configInfo = \app\admin\model\Config::where("name",
             "pop_up_free")->find();
         $pop_up_free  = 0;
         if ($configInfo) {
             $pop_up_free = $configInfo["value"];
         }

         $coinLog = new \app\common\model\AccountCoinLog();
         $coinLog->UpdateBalance($this->userInfo['id'], CoinLog::PopUp, $this->userInfo['switch_wallet'],
             $pop_up_free, CoinLog::getTypeText(CoinLog::PopUp));

         $this->success(); // 成功
     }
}