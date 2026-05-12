<?php

namespace app\common\model;

use think\Model;

class ChannelList extends Model
{
    protected $table = 'slot_channel_list';

    protected $autoWriteTimestamp = true;


    public function getLogoAttr($value)
    {
        return full_url('', $value ? true:false, $value ?? '');
    }
    public function getPwaLogoAttr($value)
    {
        return full_url('', $value ? true:false, $value ?? '');
    }
    public function getFaviconAttr($value)
    {
        return full_url('', $value ? true:false, $value ?? '');
    }
}