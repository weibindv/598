<?php

namespace app\admin\model\line;

use think\Model;


class Merchant extends Model
{

    

    

    // 表名
    protected $name = 'line_merchant';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'datetime';
    
    // 定义时间戳格式
    protected $dateFormat = 'Y-m-d H:i:s';

    // 定义时间戳字段名
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];
    

    







}