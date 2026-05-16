<?php

namespace app\admin\model\line;

use think\Model;

class UserAddress extends Model
{
    // 表名
    protected $table = 'fa_user_address';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

}
