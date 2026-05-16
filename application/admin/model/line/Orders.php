<?php

namespace app\admin\model\line;

use think\Model;
use traits\model\SoftDelete;

class Orders extends Model
{

    use SoftDelete;

    protected static function init()
    {
        self::beforeInsert(function ($row) {
            if (empty($row['order_no'])) {
                $snowflake = new \app\common\library\Snowflake(1, 1);
                $row['order_no'] = $snowflake->nextId();
            }
        });
    }

    

    // 表名
    protected $name = 'line_orders';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        'paytime_text',
        'delivery_time_text',
        'completetime_text',
        'canceltime_text',
        'order_status_text'
    ];
    

    



    public function getPaytimeTextAttr($value, $data)
    {
        $value = $value ?: ($data['paytime'] ?? '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getDeliveryTimeTextAttr($value, $data)
    {
        $value = $value ?: ($data['delivery_time'] ?? '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getCompletetimeTextAttr($value, $data)
    {
        $value = $value ?: ($data['completetime'] ?? '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getCanceltimeTextAttr($value, $data)
    {
        $value = $value ?: ($data['canceltime'] ?? '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    public function getOrderStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['order_status'] ?? '');
        $statusMap = [
            0 => '待付款',
            1 => '待处理',
            2 => '待收货',
            3 => '已完成',
            4 => '已取消',
            5 => '退款中',
            6 => '已退款',
            7 => '售后中'
        ];
        return $statusMap[$value] ?? '未知';
    }

    protected function setPaytimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setDeliveryTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setCompletetimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setCanceltimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


    public function user()
    {
        return $this->belongsTo('app\common\model\User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function address()
    {
        return $this->belongsTo('app\admin\model\user\Address', 'address_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

}
