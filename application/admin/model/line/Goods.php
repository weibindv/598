<?php

namespace app\admin\model\line;

use think\Model;
class Goods extends Model
{





    // 表名
    protected $name = 'line_goods';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    // 追加属性
    protected $append = [
        'status_text',
        'examine_time_text'
    ];


    protected static function init()
    {
        self::afterInsert(function ($row) {
            if (!$row['weigh']) {
                $pk = $row->getPk();
                $row->getQuery()->where($pk, $row[$pk])->update(['weigh' => $row[$pk]]);
            }
        });
    }


    public function getStatusList()
    {
        return ['50' => __('Status 50')];
    }

    public function getStockStatusList()
    {
        return ['normal' => '充足', 'hidden' => '预警'];
    }

    public function getShareStatusList()
    {
        return ['1' => '已分享', '0' => '未分享'];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? '';
    }


    public function getExamineTimeTextAttr($value, $data)
    {
        $value = $value ?: ($data['examine_time'] ?? '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setExamineTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


}
