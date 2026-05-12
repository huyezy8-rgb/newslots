<?php

use think\migration\Migrator;
use Phinx\Db\Adapter\MysqlAdapter;

class AddLangCurrencyToChannelList extends Migrator
{
    public function change(): void
    {
        // 使用无前缀表名，框架会自动加上表前缀（如 slot_）
        if ($this->hasTable('channel_list')) {
            $table = $this->table('channel_list');

            if (!$table->hasColumn('lang')) {
                $table->addColumn('lang', 'string', [
                    'limit' => 20,
                    'default' => 'en',
                    'null' => false,
                    'comment' => '渠道语言（如 en / zh-cn）',
                ]);
            }

            if (!$table->hasColumn('currency_code')) {
                $table->addColumn('currency_code', 'string', [
                    'limit' => 10,
                    'default' => 'USD',
                    'null' => false,
                    'comment' => '货币代码（ISO，如 USD/CNY/INR）',
                ]);
            }

            if (!$table->hasColumn('currency_symbol')) {
                $table->addColumn('currency_symbol', 'string', [
                    'limit' => 8,
                    'default' => '$',
                    'null' => false,
                    'comment' => '货币符号（如 $/¥/₹）',
                ]);
            }

            if (!$table->hasColumn('time_zone')) {
                $table->addColumn('time_zone', 'string', [
                    'limit' => 64,
                    'default' => 'America/New_York',
                    'null' => false,
                    'comment' => '时区（IANA 标准），如 America/New_York',
                ]);
            }

            $table->update();
        }
    }
}


