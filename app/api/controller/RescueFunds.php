<?php

namespace app\api\controller;

use app\admin\model\Config;
use app\api\enum\CoinLog;
use think\facade\Db;

class RescueFunds extends Base
{
    /**
     * 领取救援金
     */
    public function receive()
    {
        //每日最大领取次数
        $rescue_funds_times      = Config::where('name', 'rescue_funds_times')->value('value');
        $today           = date('Y-m-d');
        $userInfo        = $this->userInfo;
        $alreadyReceived_count = \app\common\model\RescueFunds::where('user_id',
            $userInfo->id)
            ->whereDay('rescue_date', $today)
            ->count();

        if ($alreadyReceived_count >= $rescue_funds_times) {
            $this->error(__('Already received today rescue funds')); // 您已领取过今日的救援金
        }

        // 获取配置项

        $maxRescue       = Config::where('name', 'rescue_funds')->value('value');
        $rescueThreshold = Config::where('name', 'rescue_funds_receiving')->value('value');

        if ($userInfo->vip > 0) {
            $balance = $userInfo->recharge_wallet;
            $wallet_type = 1;
        } else {
            $balance = $userInfo->experience_wallet;
            $wallet_type = 0;
        }


        if ($balance > $rescueThreshold) {
            $this->error(__('Balance above receiving threshold, cannot receive rescue funds')); // 余额高于领取门槛，无法领取救援金
        }
        $rescueAmount =  $maxRescue;
//        do {
//            $rescueAmount = round(mt_rand(1, $maxRescue * 100) / 100, 2);
//        } while ($rescueAmount <= 0);

        Db::startTrans();
        try {

            \app\common\model\RescueFunds::create([
                'user_id'     => $userInfo->id,
                'num'         => $rescueAmount,
                'wallet_type' => $wallet_type,
                'rescue_date' => date('Y-m-d H:i:s'),
            ]);

            $coinLog = new \app\common\model\AccountCoinLog();
            $coinLog->UpdateBalance($userInfo->id, CoinLog::RescueFunds, $wallet_type,
                $rescueAmount, CoinLog::getTypeText(CoinLog::RescueFunds));
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error(__('Receive rescue funds failed') . '：'.$e->getMessage()); // 领取救援金失败
        }

        $this->success(); // 成功
    }
}