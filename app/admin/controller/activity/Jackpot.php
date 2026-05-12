<?php

namespace app\admin\controller\activity;

use app\common\controller\Backend;
use app\common\model\jackpot\JackpotConfig;

class Jackpot extends Backend
{
        public function edit(): void
      {
          if ($this->request->isPost()) {
              $data = $this->request->post();
              $config = JackpotConfig::find(1);
              $config->bonus_amount = $data["bonus_amount"];
              $config->daily_invest_threshold = $data["daily_invest_threshold"];
              $config->shake_ratio_config = json_encode($data["shake_ratio_config"]);
              $config->save();
              $this->success(__('Success'));
          }

          $config = JackpotConfig::find(1);

          $config["shake_ratio_config"] = json_decode($config["shake_ratio_config"],true);

          $this->success(__('Success'),$config);
      }
}