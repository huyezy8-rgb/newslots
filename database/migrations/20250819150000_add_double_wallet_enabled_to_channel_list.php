<?php

use think\migration\Migrator;

class AddDoubleWalletEnabledToChannelList extends Migrator
{
    public function change(): void
    {
        // 表前缀由框架配置处理，这里使用无前缀表名
        if ($this->hasTable('channel_list')) {
            $table = $this->table('channel_list');
            if (!$table->hasColumn('double_wallet_enabled')) {
                $table->addColumn('double_wallet_enabled', 'boolean', [
                    'default' => 1,
                    'null' => false,
                    'comment' => '双钱包开关: 1=开启,0=关闭',
                ])->update();
            }
        }
    }
}


