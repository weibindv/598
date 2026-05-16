define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'line/merchant/index' + location.search,
                    add_url: 'line/merchant/add',
                    edit_url: 'line/merchant/edit',
                    del_url: 'line/merchant/del',
                    multi_url: 'line/merchant/multi',
                    import_url: 'line/merchant/import',
                    table: 'line_merchant',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                fixedColumns: true,
                fixedRightNumber: 1,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'merchant_id', title: __('Merchant_id'), operate: 'LIKE'},
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'address', title: __('Address'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'contact_person', title: __('Contact_person'), operate: 'LIKE'},
                        {field: 'contact_phone', title: __('Contact_phone'), operate: 'LIKE'},
                        {field: 'status', title: __('Status'), searchList: {"1":__('Normal'),"0":__('Hidden')}, formatter: Table.api.formatter.toggle},
                        {field: 'staff_count', title: __('Staff_count')},
                        {field: 'created_at', title: __('Created_at'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'updated_at', title: __('Updated_at'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {
                            field: 'operate',
                            title: __('Operate'),
                            table: table,
                            events: Table.api.events.operate,
                            buttons: [
                                {
                                    name: 'view_staff',
                                    text: __('View Staff'),
                                    title: function (row) {
                                        return __('View Staff') + ' - ' + row.name;
                                    },
                                    classname: 'btn btn-xs btn-primary btn-dialog',
                                    icon: 'fa fa-users',
                                    url: 'line/staff/index?merchant_id={ids}',
                                    extend: 'data-area=\'["95%", "95%"]\''
                                }
                            ],
                            formatter: Table.api.formatter.operate
                        }
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);

            // 绑定同步按钮事件
            $(document).on("click", ".btn-sync", function () {
                var that = this;
                Layer.confirm("确认要同步厂商吗？", function (index) {
                    Backend.api.ajax({
                        url: "line/merchant/sync",
                        data: {}
                    }, function (data, ret) {
                        table.bootstrapTable('refresh');
                        Layer.close(index);
                    });
                });
            });
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
