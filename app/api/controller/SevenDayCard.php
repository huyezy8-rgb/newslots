<?php

namespace app\api\controller;

use app\api\enum\CoinLog;
use app\common\model\SevenDayCardUser;
use app\common\service\AccountService;
use app\Request;
use think\facade\Db;

class SevenDayCard extends Base
{
    protected array $noNeedLogin = [];

    /**
     * 获取活动数据 + 购买状态 + 7天进度
     */
    public function info()
    {
        $config = Db::name('seven_day_card_config')->where('id',1)->find();
        if ($config) {
            $config['seven_day_rewards'] = json_decode($config['seven_day_rewards'], true) ?: [];
            $config['rescue_rewards'] = json_decode($config['rescue_rewards'], true) ?: [];
            $config['daily_rewards'] = json_decode($config['daily_rewards'], true) ?: [];
        }

        $userRecord = Db::name('seven_day_card_user')->where('user_id', $this->userInfo['id'])->where('end_time', '>', time())->order('id','desc')->find();
        $bought = $userRecord ? 1 : 0;

        $progress = null;
        if ($userRecord) {
            $progress = [
                'reward_main' => json_decode($userRecord['reward_main'], true),
                'reward_rescue' => json_decode($userRecord['reward_rescue'], true),
                'reward_daily' => json_decode($userRecord['reward_daily'], true),
                'start_time' => $userRecord['start_time'],
                'end_time' => $userRecord['end_time'],
            ];
        }

        $this->success('', [
            'config' => $config,
            'bought' => $bought,
            'progress' => $progress,
        ]);
    }

    /**
     * 领取某一天的奖励
     * type: main|rescue (只能领取七天奖励和救援金)
     * day: 1-7
     */
    public function claim(Request $request)
    {
        $type = $request->param('type','main');
        $day = (int)$request->param('day', 1);
        if ($day < 1 || $day > 7) $this->error('Invalid day');

        // 只允许领取七天奖励和救援金
        if (!in_array($type, ['main', 'rescue'])) {
            $this->error('Invalid type');
        }

        $field = match ($type) {
            'main' => 'reward_main',
            'rescue' => 'reward_rescue',
            default => null,
        };

        $record = Db::name('seven_day_card_user')->where('user_id', $this->userInfo['id'])->order('id','desc')->find();
        if (!$record) $this->error('Not purchased');
        if (time() > (int)$record['end_time']) $this->error('Expired');

        $arr = json_decode($record[$field], true) ?: [];
        $idx = $day - 1;
        if (!isset($arr[$idx])) $this->error('Config error');
        if ((int)($arr[$idx]['status'] ?? 0) === 1) $this->error('Already claimed');

        $amount = (float)($arr[$idx]['reward'] ?? 0);
        if ($amount <= 0) $this->error('No reward');

        // 救援金领取次数限制检查
        if ($type === 'rescue') {
            $rescue_funds_times = \app\admin\model\Config::where('name', 'rescue_funds_times')->value('value');
            $today = date('Y-m-d');
            $alreadyReceived_count = \app\common\model\RescueFunds::where('user_id', $this->userInfo['id'])
                ->whereDay('rescue_date', $today)
                ->count();
            
            if ($alreadyReceived_count >= $rescue_funds_times) {
                $this->error(__('Already received today rescue funds'));
            }
        }

        Db::startTrans();
        try {
            $arr[$idx]['status'] = 1;
            Db::name('seven_day_card_user')->where('id', $record['id'])->update([
                $field => json_encode($arr),
                'updated_at' => time(),
            ]);

            // 发放到充值钱包
            (new AccountService())->increaseBalance(
                userId: $this->userInfo['id'],
                amount: $amount,
                walletType: 1,
                logTypeId: CoinLog::SevenDayCard,
                note: __('Seven day card reward claim')
            );

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }

        $this->success(__('Claim success'), ['amount' => $amount]);
    }
}


