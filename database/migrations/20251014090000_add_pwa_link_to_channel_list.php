<?php

use think\migration\Migrator;

class AddPwaLinkToChannelList extends Migrator
{
    public function change(): void
    {
        if ($this->hasTable('channel_list')) {
            $table = $this->table('channel_list');

            if (!$table->hasColumn('pwa_link')) {
                $table->addColumn('pwa_link', 'string', [
                    'limit' => 255,
                    'null' => true,
                    'default' => null,
                    'comment' => 'PWA 链接（可为空）',
                ]);
                $table->update();
            }
        }
    }
}


