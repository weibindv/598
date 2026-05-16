define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'line/staff/index' + location.search,
                    add_url: 'line/staff/add' + location.search,
                    edit_url: 'line/staff/edit' + location.search,
                    del_url: 'line/staff/del',
                    multi_url: 'line/staff/multi',
                    import_url: 'line/staff/import',
                    table: 'line_staff',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                search: false,
                commonSearch: true,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('序号')},
                        {field: 'name', title: __('店员姓名'), operate: 'LIKE'},
                        {field: 'line_nickname', title: __('LINE账号昵称'), operate: 'LIKE', defaultValue: '-'},
                        {field: 'phone', title: __('手机号'), operate: 'LIKE'},
                        {field: 'status', title: __('账号状态'), searchList: {"1": __('Normal'), "0": __('Disabled')}, formatter: function (value, row, index) {
                            return value == '1' ? '<span class="text-success">' + __('Normal') + '</span>' : '<span class="text-danger">' + __('Disabled') + '</span>';
                        }},
                        {field: 'created_at', title: __('绑定时间'), operate: 'RANGE', addclass: 'datetimerange', autocomplete: false},
                        {
                            field: 'operate',
                            title: __('Operate'),
                            table: table,
                            events: Table.api.events.operate,
                            buttons: [
                                {
                                    name: 'reset_pwd',
                                    text: __('Reset Password'),
                                    title: __('Reset Password'),
                                    classname: 'btn btn-xs btn-warning btn-dialog',
                                    icon: 'fa fa-key',
                                    url: 'line/staff/reset_pwd'
                                },
                                {
                                    name: 'enable',
                                    text: __('Enable'),
                                    title: __('Enable'),
                                    classname: 'btn btn-xs btn-success btn-ajax',
                                    icon: 'fa fa-check',
                                    url: 'line/staff/status?status=1',
                                    visible: function (row) {
                                        return row.status == '0';
                                    },
                                    success: function () {
                                        table.bootstrapTable('refresh');
                                    }
                                },
                                {
                                    name: 'disable',
                                    text: __('Disable'),
                                    title: __('Disable'),
                                    classname: 'btn btn-xs btn-danger btn-ajax',
                                    icon: 'fa fa-ban',
                                    url: 'line/staff/status?status=0',
                                    visible: function (row) {
                                        return row.status == '1';
                                    },
                                    success: function () {
                                        table.bootstrapTable('refresh');
                                    }
                                }
                            ],
                            formatter: function (value, row, index) {
                                var that = $.extend({}, this);
                                // 禁用后不显示删除。只有启用的时候显示删除
                                if (row.status == '0') {
                                    that.del = false;
                                } else {
                                    that.del = true;
                                }
                                return Table.api.formatter.operate.call(that, value, row, index);
                            }
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
