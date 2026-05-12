<?php
namespace app\common\model;
use think\Model;
class Chest extends Model
{
    protected $table = 'slot_chest';
    protected $autoWriteTimestamp = true;


    public function getDefaultImageAttr($value)
    {
        return full_url('', $value ? true:false, $value ?? '');
    }

    public function getWaitingImageAttr($value)
    {
        return full_url('', $value ? true:false, $value ?? '');
    }

    public function getReceivedImageAttr($value)
    {
        return full_url('', $value ? true:false, $value ?? '');
    }
} 