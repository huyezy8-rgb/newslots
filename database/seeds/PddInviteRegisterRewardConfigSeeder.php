<?php

use think\facade\Cache;
use think\facade\Db;
use think\migration\Seeder;

class PddInviteRegisterRewardConfigSeeder extends Seeder
{
    private const MIN_CONFIG = 'pdd_invite_register_reward';
    private const MAX_CONFIG = 'pdd_invite_register_reward_max';
    private const TURNTABLE_WEIGHTS = [
        'pdd_withdrawal' => 120,
        'pdd_unlock_required_invites' => 110,
        'pdd_valid_invite_recharge_min' => 100,
        'pdd_invite_deposit_reward' => 90,
        'pdd_invite_deposit_reward_max' => 89,
        self::MIN_CONFIG => 80,
        self::MAX_CONFIG => 79,
        'pdd_init_min' => 70,
        'pdd_init_max' => 60,
    ];

    public function run(): void
    {
        if (!$this->hasTable('config')) {
            return;
        }

        $minConfig = Db::name('config')->where('name', self::MIN_CONFIG)->find();
        $defaultValue = (string)($minConfig['value'] ?? '0.1');

        if ($minConfig) {
            Db::name('config')->where('id', $minConfig['id'])->update([
                'title' => '每个新邀请注册的最小基础奖励',
                'type' => 'number',
                'weigh' => self::TURNTABLE_WEIGHTS[self::MIN_CONFIG],
            ]);
        } else {
            Db::name('config')->insert([
                'name' => self::MIN_CONFIG,
                'group' => 'turntable',
                'title' => '每个新邀请注册的最小基础奖励',
                'type' => 'number',
                'value' => $defaultValue,
                'content' => null,
                'rule' => '',
                'extend' => '',
                'allow_del' => 0,
                'weigh' => self::TURNTABLE_WEIGHTS[self::MIN_CONFIG],
            ]);
        }

        $maxConfig = Db::name('config')->where('name', self::MAX_CONFIG)->find();
        if ($maxConfig) {
            Db::name('config')->where('id', $maxConfig['id'])->update([
                'title' => '每个新邀请注册的最大基础奖励',
                'type' => 'number',
                'weigh' => self::TURNTABLE_WEIGHTS[self::MAX_CONFIG],
            ]);
        } else {
            Db::name('config')->insert([
                'name' => self::MAX_CONFIG,
                'group' => 'turntable',
                'title' => '每个新邀请注册的最大基础奖励',
                'type' => 'number',
                'value' => $defaultValue,
                'content' => null,
                'rule' => '',
                'extend' => '',
                'allow_del' => 0,
                'weigh' => self::TURNTABLE_WEIGHTS[self::MAX_CONFIG],
            ]);
        }

        foreach (self::TURNTABLE_WEIGHTS as $name => $weigh) {
            Db::name('config')->where('name', $name)->where('group', 'turntable')->update([
                'weigh' => $weigh,
            ]);
        }

        Cache::tag('sys_config')->clear();
    }
}
