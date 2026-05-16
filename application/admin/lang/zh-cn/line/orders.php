<?php

return [
    'Id'            => '自增主键',
    'Order_no'      => '订单编号（如18929764706）',
    'User_id'       => '用户ID',
    'Address_id'    => '收货地址ID（关联user_address.id）',
    'Goods_detail'  => '商品明细（果冻x2；牛奶x4…）',
    'Total_amount'  => '总金额',
    'Paid_amount'   => '实付金额',
    'Order_status'  => '订单状态（待处理/待付款/待收货/已完成/已取消）',
    'Remark'        => '备注',
    'Createtime'    => '订单创建时间戳',
    'Paytime'       => '支付时间戳',
    'Delivery_time' => '发货时间戳',
    'Completetime'  => '完成时间戳',
    'Canceltime'    => '取消时间戳',
    'Updatetime'    => '更新时间戳',
    'Deletetime'    => '删除时间戳（软删除）'
];
