<?php

use think\migration\Migrator;

class AddRouteToApiLog extends Migrator
{
    public function change()
    {
        $table = $this->table('api_log');
        if (!$table->hasColumn('route')) {
            $table->addColumn('route', 'string', [
                'limit' => 255,
                'default' => '',
                'comment' => '接口路由'
            ])->update();
        }
    }
}
