<?php

use think\migration\Migrator;
use think\migration\db\Column;

class AddUniqueInviteConstraint extends Migrator
{
    public function change(): void
    {
        $table = $this->table('pdd_invite_log');
        
        if ($table->exists()) {
            // 添加唯一索引，确保邀请人和被邀请人的关系唯一
            if (!$table->hasIndex(['inviter_user_id', 'invite_user_id'])) {
                $table->addIndex(['inviter_user_id', 'invite_user_id'], ['unique' => true, 'name' => 'idx_unique_invite_relation']);
            }
        }
    }
}

