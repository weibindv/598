<?php

namespace app\admin\model\line;

use think\Model;

class GoodsComment extends Model
{
    // 表名
    protected $name = 'line_goods_comment';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo('app\common\model\User', 'user_id', 'id');
    }

    /**
     * 关联商品
     */
    public function goods()
    {
        return $this->belongsTo('app\admin\model\line\Goods', 'goods_id', 'id');
    }
}
