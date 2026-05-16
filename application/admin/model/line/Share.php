<?php

namespace app\admin\model\line;

use think\Model;

class Share extends Model
{
    // 表名
    protected $name = 'line_share';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    // 追加属性
    protected $append = [
    ];

    public function goods()
    {
        return $this->belongsTo('app\admin\model\line\Goods', 'goods_id', 'id')->field('id,title,image,mall_price');
    }
}
