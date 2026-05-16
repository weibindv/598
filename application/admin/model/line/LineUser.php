<?php

namespace app\admin\model\line;

use think\Model;

class LineUser extends Model
{
    // 表名
    protected $name = 'line_user';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
    ];
    
    /**
     * 获取用户ID (兼容性方法)
     */
    public function getUserId()
    {
        if (isset($this->data['user_id'])) {
            return $this->data['user_id'];
        }
        return 0;
    }

    /**
     * 属性获取器：当访问 $model->user_id 时触发
     */
    public function getUserIdAttr($value)
    {
        return $value ?: 0;
    }
}
