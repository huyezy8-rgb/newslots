<?php

namespace app\common\model;

use think\Model;

/**
 * 运营数据模型
 */
class OperationData extends Model
{
    protected $table = 'slot_operation_data';
    
    protected $autoWriteTimestamp = true;
    
    // 设置字段信息
    protected $schema = [
        'id'          => 'int',
        'date'        => 'date',
        'channel_id'  => 'int',
        'data'        => 'json',
        'create_time' => 'int',
        'update_time' => 'int',
    ];
    
    /**
     * 获取或设置JSON数据
     * @param mixed $value
     * @return array
     */
    public function getDataAttr($value)
    {
        return is_string($value) ? json_decode($value, true) : $value;
    }
    
    /**
     * 设置JSON数据
     * @param mixed $value
     * @return string
     */
    public function setDataAttr($value)
    {
        return is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
    }
    
    /**
     * 根据日期和渠道获取数据
     * @param string $date 日期
     * @param int|null $channelId 渠道ID
     * @return array|null
     */
    public static function getByDateAndChannel(string $date, ?int $channelId = null): ?array
    {
        $where = ['date' => $date];
        if ($channelId !== null) {
            $where['channel_id'] = $channelId;
        }
        
        $data = self::where($where)->find();
        return $data ? $data->data : null;
    }
    
    /**
     * 根据日期列表和渠道批量获取数据
     * @param array $dateList 日期列表
     * @param int|null $channelId 渠道ID
     * @return array
     */
    public static function getByDateListAndChannel(array $dateList, ?int $channelId = null): array
    {
        $query = self::where('date', 'in', $dateList);
        
        if ($channelId !== null) {
            // 筛选特定渠道时，查询该渠道的数据
            $query = $query->where('channel_id', $channelId);
        } else {
            // 不筛选渠道时，查询全部渠道的数据（channel_id为null的记录）
            $query = $query->whereNull('channel_id');
        }
        
        $data = $query->select()->toArray();
        
        // 将数据按日期索引，并带上 update_time
        $result = [];
        foreach ($data as $item) {
            $result[$item['date']] = [
                'data' => $item['data'],
                'update_time' => $item['update_time'] ?? null,
            ];
        }
        
        return $result;
    }
    
    /**
     * 保存运营数据
     * @param string $date 日期
     * @param array $data 数据
     * @param int|null $channelId 渠道ID
     * @return bool
     */
    public static function saveData(string $date, array $data, ?int $channelId = null): bool
    {
        $where = ['date' => $date];
        if ($channelId !== null) {
            $where['channel_id'] = $channelId;
        } else {
            $where['channel_id'] = null;
        }

        $model = self::where($where)->find();
        if ($model) {
            // 更新现有数据
            \think\facade\Log::info('[OperationData] 更新: date=' . $date . ' channel_id类型:' . gettype($channelId) . ' 值:' . var_export($channelId, true));
            $model->data = $data;
            $model->setAttr('channel_id', $channelId); // 强制写入 null
            return $model->save();
        } else {
            // 创建新数据
            \think\facade\Log::info('[OperationData] 新增: date=' . $date . ' channel_id类型:' . gettype($channelId) . ' 值:' . var_export($channelId, true));
            $model = new self();
            $model->date = $date;
            $model->setAttr('channel_id', $channelId); // 强制写入 null
            $model->data = $data;
            return $model->save();
        }
    }
} 