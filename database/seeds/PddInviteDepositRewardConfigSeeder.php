<?php

use think\facade\Cache;
use think\facade\Db;
use think\migration\Seeder;

class PddInviteDepositRewardConfigSeeder extends Seeder
{
    private const MIN_CONFIG = 'pdd_invite_deposit_reward';
    private const MAX_CONFIG = 'pdd_invite_deposit_reward_max';
    private const TURNTABLE_WEIGHTS = [
        'pdd_withdrawal' => 120,
        'pdd_unlock_required_invites' => 110,
        'pdd_valid_invite_recharge_min' => 100,
        self::MIN_CONFIG => 90,
        self::MAX_CONFIG => 89,
        'pdd_invite_register_reward' => 80,
        'pdd_invite_register_reward_max' => 79,
        'pdd_init_min' => 70,
        'pdd_init_max' => 60,
    ];

    public function run(): void
    {
        if (!$this->hasTable('config')) {
            return;
        }

        $this->upsertConfig(
            self::MIN_CONFIG,
            '每个新邀请充值的最小基础奖励',
            '0.2',
            self::TURNTABLE_WEIGHTS[self::MIN_CONFIG]
        );
        $this->upsertConfig(
            self::MAX_CONFIG,
            '每个新邀请充值的最大基础奖励',
            '0.6',
            self::TURNTABLE_WEIGHTS[self::MAX_CONFIG]
        );

        foreach (self::TURNTABLE_WEIGHTS as $name => $weigh) {
            Db::name('config')->where('name', $name)->where('group', 'turntable')->update([
                'weigh' => $weigh,
            ]);
        }

        Cache::tag('sys_config')->clear();
    }

    private function upsertConfig(string $name, string $title, string $value, int $weigh): void
    {
        $config = Db::name('config')->where('name', $name)->find();
        if ($config) {
            Db::name('config')->where('id', $config['id'])->update([
                'title' => $title,
                'type' => 'number',
                'weigh' => $weigh,
            ]);
            return;
        }

        Db::name('config')->insert([
            'name' => $name,
            'group' => 'turntable',
            'title' => $title,
            'type' => 'number',
            'value' => $value,
            'content' => null,
            'rule' => '',
            'extend' => '',
            'allow_del' => 0,
            'weigh' => $weigh,
        ]);
    }
}
