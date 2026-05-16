<?php

namespace app\admin\model\line;

use think\Model;

class Browse extends Model
{
    // 表名
    protected $name = 'line_browse';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    // 追加属性
    protected $append = [
    ];
}
