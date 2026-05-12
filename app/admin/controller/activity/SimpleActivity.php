<?php

namespace app\admin\controller\activity;

use app\admin\model\Config;
use app\common\controller\Backend;
use app\common\model\activity\Activity;
use app\common\model\ChannelList;

/**
 * 在Config表的活动配置
 */
class SimpleActivity extends Backend
{
    public function edit(): void
    {
        $group  = $this->request->param("group");
        if ($this->request->isPost()) {
            $data = $this->request->post();
            foreach ($data as $k => $v) {
                $model = Config::where("name", $k)->find();
                if ($model) {
                    $model->value = $v;
                    $model->save();
                }
                $group = $model->group;
            }

            if ($group) {
                //更新活动总配置
                Activity::update(["config"=>json_encode($data)], ["type"=> $group]);
                if ($group == 'pop_up'){
                    $new_data=[];
                    $new_data['pid_key'] = $group;
                    $new_data['pop_up_free'] = get_sys_config('pop_up_free');
                    Activity::update(["config"=>json_encode($new_data)], ["type"=> 'pop_up_success']);
                }
                if ($group == 'turntable'){
                    $new_data=[];
                    $new_data['pid_key'] = $group;
                    Activity::update(["config"=>json_encode($new_data)], ["type"=> 'turntable_success']);
                }
                //更新渠道活动
                $channelList = ChannelList::select();
                foreach ($channelList as $k => $v) {
                      $activiy = json_decode($v->activity, true);
                      foreach ($activiy as $kk => $vv) {
                           if ($vv["key"] == $group) {
                               $activiy[$kk]["option"] = $data;
                           }
                      }

                    $v->activity = json_encode($activiy, JSON_UNESCAPED_UNICODE);
                    $v->save();
                }
            }

            $this->success();
        }

        $list = Config::field("name,title,type,value,extend")->where("group", $group)->order("id","asc")->select();
        $this->success('', $list);
    }
}