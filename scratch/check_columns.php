<?php
// 加载 ThinkPHP 框架
require __DIR__ . '/../thinkphp/base.php';

// 初始化应用
\think\Container::get('app')->path(__DIR__ . '/../application/')->initialize();

use think\Db;

try {
    $columns = Db::getFields('line_orders');
    echo "Columns in line_orders:\n";
    print_r(array_keys($columns));
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
