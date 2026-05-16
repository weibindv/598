-- LINE Pay 支付集成 - 数据库字段迁移
-- 执行前请备份数据库

-- 1. 给订单表添加 LINE Pay 交易号字段
ALTER TABLE `fa_line_orders` 
ADD COLUMN `transaction_id` VARCHAR(64) DEFAULT NULL COMMENT 'LINE Pay 交易ID' 
AFTER `order_status`;

-- 2. 给订单表添加支付方式字段（可选，方便后续扩展其他支付）
ALTER TABLE `fa_line_orders` 
ADD COLUMN `payment_method` VARCHAR(32) DEFAULT 'linepay' COMMENT '支付方式: linepay, alipay, wechat' 
AFTER `transaction_id`;

-- 3. 给订单表添加 LINE Pay 相关信息字段（可选，记录支付详情）
ALTER TABLE `fa_line_orders` 
ADD COLUMN `pay_info` TEXT DEFAULT NULL COMMENT 'LINE Pay 返回的完整支付信息(JSON)' 
AFTER `payment_method`;
