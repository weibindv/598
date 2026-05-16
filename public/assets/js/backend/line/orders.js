define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'line/orders/index' + location.search,
                    add_url: 'line/orders/add',
                    edit_url: 'line/orders/edit',
                    del_url: 'line/orders/del',
                    multi_url: 'line/orders/multi',
                    import_url: 'line/orders/import',
                    table: 'line_orders',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'order_status',
                sortOrder: 'asc',
                pagination: true,
                sidePagination: 'server',
                pageSize: 10,
                pageList: [10, 25, 50, 100],
                fixedColumns: true,
                fixedRightNumber: 1,
                columns: [
                    [
                        { checkbox: true },
                        { field: 'id', title: '序号' },
                        { field: 'order_no', title: '订单编号', operate: 'LIKE' },
                        {
                            field: 'user.nickname', title: '用户信息', formatter: function (value, row, index) {
                                var avatar = row.user ? row.user.avatar : '/assets/img/avatar.png';
                                var nickname = row.user ? row.user.nickname : '未知';
                                return '<div style="display:flex;align-items:center;"><img src="' + avatar + '" style="width:30px;height:30px;border-radius:50%;margin-right:5px;">' + nickname + '</div>';
                            }
                        },
                        { field: 'goods_detail', title: '商品名称', operate: 'LIKE' },
                        { field: 'paid_amount', title: '实付金额', operate: 'BETWEEN', formatter: function (value) { return '¥' + value; } },
                        {
                            field: 'address', title: '收货地址', formatter: function (value, row, index) {
                                if (!row.address) return '-';
                                return row.address.province + row.address.city + row.address.district + row.address.address;
                            }
                        },
                        { field: 'createtime', title: '订单创建时间', operate: 'RANGE', addclass: 'datetimerange', autocomplete: false, formatter: Table.api.formatter.datetime },
                        {
                            field: 'order_status', title: '订单状态', searchList: { "0": "待付款", "1": "待处理", "2": "待收货", "3": "已完成", "4": "已取消", "5": "退款中", "6": "已退款", "7": "售后中" }, formatter: function (value, row, index) {
                                var color = 'grey';
                                var text = '未知';
                                var statusMap = { "0": "待付款", "1": "待处理", "2": "待收货", "3": "已完成", "4": "已取消", "5": "退款中", "6": "已退款", "7": "售后中" };
                                text = statusMap[value] || '未知';

                                if (value == 0) color = 'info';      // 待付款
                                if (value == 1) color = 'primary';   // 待处理
                                if (value == 2) color = 'warning';   // 待收货
                                if (value == 3) color = 'success';   // 已完成
                                if (value == 4) color = 'danger';    // 已取消
                                if (value == 5) color = 'info';      // 退款中
                                if (value == 6) color = 'muted';     // 已退款
                                if (value == 7) color = 'danger';    // 售后中

                                return '<span class="text-' + color + '">' + text + '</span>';
                            }
                        },
                        {
                            field: 'operate',
                            title: __('Operate'),
                            table: table,
                            events: Table.api.events.operate,
                            buttons: [
                                {
                                    name: 'detail',
                                    text: '详情',
                                    title: '订单详情',
                                    classname: 'btn btn-xs text-info btn-dialog',
                                    icon: 'fa fa-list',
                                    url: 'line/orders/detail'
                                },
                                {
                                    name: 'delivery',
                                    text: '发货',
                                    title: '发货',
                                    classname: 'btn btn-xs text-warning btn-ajax',
                                    icon: 'fa fa-truck',
                                    url: 'line/orders/delivery',
                                    visible: function (row) {
                                        return row.order_status == 1;
                                    },
                                    confirm: '确认发货？',
                                    success: function () { table.bootstrapTable('refresh'); }
                                },
                                {
                                    name: 'cancel',
                                    text: '取消',
                                    title: '取消订单',
                                    classname: 'btn btn-xs text-muted btn-ajax',
                                    icon: 'fa fa-close',
                                    url: 'line/orders/cancel',
                                    visible: function (row) {
                                        return row.order_status == 0;
                                    },
                                    confirm: '确认取消？',
                                    success: function () { table.bootstrapTable('refresh'); }
                                },
                                {
                                    name: 'finish',
                                    text: '标记完成',
                                    title: '标记完成',
                                    classname: 'btn btn-xs text-success btn-ajax',
                                    icon: 'fa fa-check',
                                    url: 'line/orders/finish',
                                    visible: function (row) {
                                        return row.order_status == 2;
                                    },
                                    confirm: '确认标记完成？',
                                    success: function () { table.bootstrapTable('refresh'); }
                                },
                                {
                                    name: 'log',
                                    text: '查看日志',
                                    title: '查看日志',
                                    classname: 'btn btn-xs text-info btn-dialog',
                                    icon: 'fa fa-history',
                                    url: 'line/orders/log',
                                    visible: function (row) {
                                        return row.order_status == 3;
                                    }
                                },
                                {
                                    name: 'apply_refund',
                                    text: '去退款',
                                    title: '退款处理',
                                    classname: 'btn btn-xs text-danger btn-dialog',
                                    icon: 'fa fa-reply',
                                    url: 'line/orders/refund_process',
                                    visible: function(row){
                                        return row.order_status == 5; // 退款中
                                    }
                                },
                                {
                                    name: 'refund_detail',
                                    text: '查看退款',
                                    title: '退款详情',
                                    classname: 'btn btn-xs text-muted btn-dialog',
                                    icon: 'fa fa-money',
                                    url: 'line/orders/refund',
                                    visible: function(row){
                                        return row.order_status == 6; // 已退款
                                    }
                                }
                            ],
                            formatter: Table.api.formatter.operate
                        }
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        recyclebin: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    'dragsort_url': ''
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: 'line/orders/recyclebin' + location.search,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        { checkbox: true },
                        { field: 'id', title: __('Id') },
                        {
                            field: 'deletetime',
                            title: __('Deletetime'),
                            operate: 'RANGE',
                            addclass: 'datetimerange',
                            formatter: Table.api.formatter.datetime
                        },
                        {
                            field: 'operate',
                            width: '140px',
                            title: __('Operate'),
                            table: table,
                            events: Table.api.events.operate,
                            buttons: [
                                {
                                    name: 'Restore',
                                    text: __('Restore'),
                                    classname: 'btn btn-xs btn-info btn-ajax btn-restoreit',
                                    icon: 'fa fa-rotate-left',
                                    url: 'line/orders/restore',
                                    refresh: true
                                },
                                {
                                    name: 'Destroy',
                                    text: __('Destroy'),
                                    classname: 'btn btn-xs btn-danger btn-ajax btn-destroyit',
                                    icon: 'fa fa-times',
                                    url: 'line/orders/destroy',
                                    refresh: true
                                }
                            ],
                            formatter: Table.api.formatter.operate
                        }
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },

        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});
