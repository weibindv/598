<?php

namespace app\admin\model\line;

use think\Model;

class MessageLog extends Model
{
    // 表名
    protected $name = 'line_message_log';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    // 追加属性
    protected $append = [
        'msg_type_text',
        'push_status_text'
    ];

    public function getMsgTypeList()
    {
        return [
            0 => '订单下单通知',
            1 => '订单支付提醒',
            2 => '订单发货通知',
            3 => '订单完成通知',
            4 => '订单取消通知',
            5 => '售后申请通知',
            6 => '售后完成通知',
            7 => '其他通知'
        ];
    }

    public function getPushStatusList()
    {
        return [
            0 => '未推送',
            1 => '已推送',
            2 => '推送失败'
        ];
    }

    public function getMsgTypeTextAttr($value, $data)
    {
        $value = $value ?: ($data['msg_type'] ?? 0);
        $list = $this->getMsgTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getPushStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['push_status'] ?? 0);
        $list = $this->getPushStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    protected static function init()
    {
        self::beforeInsert(function ($row) {
            if (empty($row['message_id'])) {
                $snowflake = new \app\common\library\Snowflake(1, 1);
                $row['message_id'] = $snowflake->nextId();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo('app\common\model\User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
