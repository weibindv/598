<?php

// 加载基础文件
require __DIR__ . '/../public/index.php';

use think\Db;

$sqlArr = [
    "CREATE TABLE IF NOT EXISTS `fa_line_cart` (
      `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
      `user_id` int(11) DEFAULT 0 COMMENT '用户ID',
      `goods_id` int(11) DEFAULT 0 COMMENT '商品ID',
      `goods_num` int(11) DEFAULT 0 COMMENT '商品数量',
      `createtime` int(11) DEFAULT NULL COMMENT '创建时间',
      `updatetime` int(11) DEFAULT NULL COMMENT '更新时间',
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='购物车表';",
    
    "CREATE TABLE IF NOT EXISTS `fa_line_goods_comment` (
      `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
      `user_id` int(11) DEFAULT 0 COMMENT '用户ID',
      `goods_id` int(11) DEFAULT 0 COMMENT '商品ID',
      `order_id` int(11) DEFAULT 0 COMMENT '订单ID',
      `score` tinyint(1) DEFAULT 5 COMMENT '评分:1-5',
      `content` text COMMENT '评论内容',
      `images` varchar(2000) DEFAULT '' COMMENT '评论图片',
      `videos` varchar(2000) DEFAULT '' COMMENT '评论视频',
      `createtime` int(11) DEFAULT NULL COMMENT '创建时间',
      `updatetime` int(11) DEFAULT NULL COMMENT '更新时间',
      `status` tinyint(1) DEFAULT 1 COMMENT '状态:0=隐藏,1=显示',
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商品评论表';"
];

foreach ($sqlArr as $sql) {
    try {
        Db::execute($sql);
        echo "Executed SQL successfully.\n";
    } catch (\Exception $e) {
        echo "Error executing SQL: " . $e->getMessage() . "\n";
    }
}
