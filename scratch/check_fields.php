<?php
define('APP_PATH', __DIR__ . '/application/');
require __DIR__ . '/../thinkphp/base.php';
\think\Container::get('app')->path(APP_PATH)->initialize();

try {
    $fields = \think\Db::getTableFields('fa_line_goods');
    echo "Fields in fa_line_goods:\n";
    print_r($fields);
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
