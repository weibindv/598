<?php

// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

return [
    //别名配置,别名只能是映射到控制器且访问时必须加上请求的方法
    '__alias__' => [
    ],
    //变量规则
    '__pattern__' => [
    ],

    // LINE 登录路由
    'api/line/user/login' => 'api/line.user/login',
    'api/line/user/loginCallback' => 'api/line.user/loginCallback',
    'api/line/user/mockLogin' => 'api/line.user/mockLogin',

    // LINE Pay 支付路由
    'api/line/order/linePay' => 'api/line.order/linePay',
    'api/line/order/linePayConfirm' => 'api/line.order/linePayConfirm',
    'api/line/order/linePayCancel' => 'api/line.order/linePayCancel',
    'api/line/order/linePayStatus' => 'api/line.order/linePayStatus',

    //        域名绑定到模块
//        '__domain__'  => [
//            'admin' => 'admin',
//            'api'   => 'api',
//        ],
];
