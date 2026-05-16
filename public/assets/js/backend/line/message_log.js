define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'line/message_log/index' + location.search,
                    add_url: 'line/message_log/add',
                    // edit_url: 'line/message_log/edit',
                    // del_url: 'line/message_log/del',
                    multi_url: 'line/message_log/multi',
                    table: 'line_message_log',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                pagination: true,
                sidePagination: 'server',
                pageSize: 10,
                pageList: [10, 25, 50, 100],
                columns: [
                    [
                        { checkbox: true },
                        { field: 'id', title: __('Id'), operate: false },
                        { field: 'message_id', title: __('Message_id'), operate: 'LIKE' },
                        {
                            field: 'nickname',
                            title: __('Recipient'),
                            formatter: function (value, row, index) {
                                return value + ' (LINE)';
                            }
                        },
                        {
                            field: 'msg_type',
                            title: __('Msg_type'),
                            searchList: Config.msgTypeList,
                            formatter: function (value, row, index) {
                                var colors = { 0: 'info', 1: 'warning', 2: 'success', 3: 'primary', 4: 'danger', 5: 'warning', 6: 'success', 7: 'info' };
                                var color = colors[value] || 'info';
                                return '<span class="label label-' + color + '">' + row.msg_type_text + '</span>';
                            }
                        },
                        {
                            field: 'push_status',
                            title: __('Push_status'),
                            searchList: Config.pushStatusList,
                            formatter: function (value, row, index) {
                                var colors = { 1: 'success', 0: 'grey', 2: 'danger' };
                                var color = colors[value] || 'grey';
                                return '<span class="text-' + color + '"><i class="fa fa-circle"></i> ' + row.push_status_text + '</span>';
                            }
                        },
                        { field: 'createtime', title: __('Createtime'), operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime },
                        { field: 'order_no', title: __('Related Order'), operate: 'LIKE' },
                        {
                            field: 'operate',
                            title: __('Operate'),
                            table: table,
                            events: Table.api.events.operate,
                            formatter: Table.api.formatter.operate,
                            buttons: [
                                {
                                    name: 'detail',
                                    text: __('查看详情'),
                                    title: __('查看详情'),
                                    classname: 'btn btn-xs btn-primary btn-dialog',
                                    icon: 'fa fa-list',
                                    url: 'line/message_log/detail',
                                    callback: function (data) {
                                        Layer.alert("接收 ID: " + data.user_id);
                                    }
                                }
                            ]
                        }
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        // add: function () {
        //     Controller.api.bindevent();
        // },
        // edit: function () {
        //     Controller.api.bindevent();
        // },
        // api: {
        //     bindevent: function () {
        //         Form.api.bindevent($("form[role=form]"));
        //     }
        // }
    };
    return Controller;
});
