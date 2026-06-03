<?php

namespace app\command;

use app\common\service\TgBotService;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class TgSend extends Command
{
    protected function configure()
    {
        $this->setName('tg:send')
            ->setDescription('Send scheduled Telegram bot messages');
    }

    protected function execute(Input $input, Output $output)
    {
        $result = (new TgBotService())->processDueBots();
        $output->writeln(sprintf(
            'TG send checked. processed=%d sent=%d failed=%d',
            $result['processed'],
            $result['sent'],
            $result['failed']
        ));
    }
}
