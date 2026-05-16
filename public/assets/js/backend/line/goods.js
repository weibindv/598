define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'line/goods/index' + location.search,
                    add_url: 'line/goods/add',
                    edit_url: 'line/goods/edit',
                    del_url: 'line/goods/del',
                    multi_url: 'line/goods/multi',
                    import_url: 'line/goods/import',
                    table: 'line_goods',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'weigh',
                pagination: true,
                sidePagination: 'server',
                pageSize: 10,
                pageList: [10, 25, 50, 100],
                fixedColumns: true,
                fixedRightNumber: 1,
                columns: [
                    [
                        { checkbox: true },
                        {
                            field: 'index',
                            title: '序号',
                            operate: false,
                            formatter: function (value, row, index) {
                                return index + 1;
                            }
                        },
                        { field: 'id', title: __('Id') },
                        { field: 'image', title: __('Image'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image },
                        { field: 'title', title: __('Title'), operate: 'LIKE' },
                        { field: 'mall_price', title: __('Mall_price'), operate: 'BETWEEN', formatter: function (value) { return '¥ ' + value; } },
                        { field: 'merchant_price', title: __('Merchant_price'), operate: 'BETWEEN', formatter: function (value) { return '¥ ' + value; } },
                        { field: 'stock_status', title: '库存', searchList: { "normal": '充足', "hidden": '预警' }, formatter: Table.api.formatter.status },
                        { field: 'share_status', title: '状态', searchList: { "1": '已分享', "0": '未分享' }, formatter: Table.api.formatter.status },
                        {
                            field: 'operate',
                            title: __('Operate'),
                            table: table,
                            events: Table.api.events.operate,
                            buttons: [
                                {
                                    name: 'detail',
                                    text: '详情',
                                    title: '详情',
                                    classname: 'btn btn-link btn-xs btn-dialog',
                                    icon: '',
                                    url: 'line/goods/detail'
                                },
                                {
                                    name: 'edit',
                                    text: '改价',
                                    title: '改价',
                                    classname: 'btn btn-link btn-xs btn-editone',
                                    icon: ''
                                },
                                {
                                    name: 'del',
                                    text: '删除',
                                    title: '删除',
                                    classname: 'btn btn-link btn-xs btn-delone',
                                    icon: ''
                                },
                                {
                                    name: 'share',
                                    text: '分享',
                                    title: '分享',
                                    classname: 'btn btn-link btn-xs btn-ajax text-success',
                                    icon: '',
                                    url: 'line/goods/share?status=1',
                                    visible: function (row) {
                                        return row.share_status == '0';
                                    },
                                    success: function (data, ret) {
                                        table.bootstrapTable('refresh');
                                    }
                                },
                                {
                                    name: 'unshare',
                                    text: '取消分享',
                                    title: '取消分享',
                                    classname: 'btn btn-link btn-xs btn-ajax text-danger',
                                    icon: '',
                                    url: 'line/goods/share?status=0',
                                    visible: function (row) {
                                        return row.share_status == '1';
                                    },
                                    success: function (data, ret) {
                                        table.bootstrapTable('refresh');
                                    }
                                },
                                {
                                    name: 'copy',
                                    text: '复制链接',
                                    title: '复制链接',
                                    classname: 'btn btn-link btn-xs btn-copy',
                                    icon: '',
                                    click: function (data, row) {
                                        var url = "pages/goods/detail?id=" + row.id;
                                        var input = document.createElement('input');
                                        input.value = url;
                                        document.body.appendChild(input);
                                        input.select();
                                        document.execCommand('copy');
                                        document.body.removeChild(input);
                                        Toastr.success("复制成功");
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

            // 同步数据按钮点击事件
            $(document).on("click", ".btn-sync", function () {
                Layer.confirm("确认要同步远程商品数据吗？", { icon: 3, title: '提示' }, function (index) {
                    Layer.close(index);

                    var offset = 0;
                    var limit = 300; // 与后端一致
                    var total = 0;
                    var syncedCount = 0;
                    var isStop = false;

                    // 创建一个持久的同步进度窗口
                    var progressId = "sync-progress-" + Math.floor(Math.random() * 1000);
                    var html = '<div id="' + progressId + '" style="padding: 20px;">' +
                        '    <div class="progress progress-striped active" style="margin-bottom:10px;">' +
                        '        <div class="progress-bar progress-bar-success" role="progressbar" style="width: 0%">0%</div>' +
                        '    </div>' +
                        '    <div style="margin-bottom:10px;">进度: <span class="sync-count">0</span> / <span class="sync-total">0</span></div>' +
                        '    <div style="background:#f4f4f4; padding:10px; height:200px; overflow-y:auto; font-size:12px; border:1px solid #ddd;" class="sync-log">' +
                        '        <div class="text-muted">准备同步...</div>' +
                        '    </div>' +
                        '</div>';

                    var layerIndex = Layer.open({
                        type: 1,
                        title: '同步远程商品数据进度',
                        area: ['500px', '400px'],
                        content: html,
                        btn: ['停止并关闭'],
                        closeBtn: 1,
                        yes: function (index) {
                            isStop = true;
                            Layer.close(index);
                        },
                        end: function () {
                            isStop = true;
                        }
                    });

                    var $container = $("#" + progressId);
                    var $progressBar = $container.find(".progress-bar");
                    var $countLabel = $container.find(".sync-count");
                    var $totalLabel = $container.find(".sync-total");
                    var $log = $container.find(".sync-log");

                    var syncProcess = function () {
                        if (isStop) return;
                        Fast.api.ajax({
                            url: "line/goods/fetch_and_sync",
                            loading: false,
                            data: { offset: offset, limit: limit }
                        }, function (data, ret) {
                            if (isStop) return false;
                            total = data.total;
                            syncedCount += data.count;
                            offset += data.count; // 使用实际返回的数量作为偏移量，防止因 limit 限制导致跳过数据

                            // 更新UI
                            $totalLabel.text(total);
                            $countLabel.text(syncedCount);
                            var percent = Math.round((syncedCount / total) * 100) + "%";
                            $progressBar.css("width", percent).text(percent);

                            // 追加日志
                            if (data.titles && data.titles.length > 0) {
                                $.each(data.titles, function (i, title) {
                                    $log.append('<div><i class="fa fa-check text-success"></i> 已同步: ' + title + '</div>');
                                });
                                $log.scrollTop($log[0].scrollHeight);
                            }

                            if (syncedCount < total && data.count > 0) {
                                syncProcess();
                            } else {
                                $progressBar.removeClass("active progress-striped");
                                $log.append('<div class="text-success" style="font-weight:bold;margin-top:10px;">同步任务已完成！</div>');
                                $log.scrollTop($log[0].scrollHeight);

                                // 完成后允许手动关闭
                                setTimeout(function () {
                                    if (!isStop) {
                                        Layer.close(layerIndex);
                                        Toastr.success("同步完成");
                                        table.bootstrapTable('refresh');
                                    }
                                }, 1500);
                            }
                            return false;
                        }, function (data, ret) {
                            if (!isStop) Layer.close(layerIndex);
                            return true;
                        });
                    };

                    syncProcess();
                });
            });
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
                url: 'line/goods/recyclebin' + location.search,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        { checkbox: true },
                        { field: 'id', title: __('Id') },
                        { field: 'title', title: __('Title'), align: 'left' },
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
                                    url: 'line/goods/restore',
                                    refresh: true
                                },
                                {
                                    name: 'Destroy',
                                    text: __('Destroy'),
                                    classname: 'btn btn-xs btn-danger btn-ajax btn-destroyit',
                                    icon: 'fa fa-times',
                                    url: 'line/goods/destroy',
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
