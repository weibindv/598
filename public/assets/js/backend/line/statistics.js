define(['jquery', 'bootstrap', 'backend', 'addtabs', 'table', 'form'], function ($, undefined, Backend, Datatable, Table, Form) {

    var Controller = {
        index: function () {
            var refreshData = function (type) {
                Fast.api.ajax({
                    url: 'line/statistics/index',
                    data: {type: type || 'today'},
                    loading: false,
                }, function (data, ret) {
                    renderOverview(data.overview);
                    renderRevenue(data.revenue);
                    renderTrend(data.orderTrend);
                    renderUserStats(data.userStats);
                    renderHotGoods(data.hotGoods);
                    renderShareStats(data.shareStats);
                    return false;
                });
            };

            var renderOverview = function (data) {
                var items = [
                    {label: '成交金额', value: '¥' + data.current.amount, diff: data.diff.amount, icon: 'fa fa-money', color: '#1890ff'},
                    {label: '订单总数', value: data.current.order_count, diff: data.diff.order_count, icon: 'fa fa-file-text-o', color: '#faad14'},
                    {label: '下单人数', value: data.current.user_count, diff: data.diff.user_count, icon: 'fa fa-users', color: '#52c41a'},
                    {label: '退款/取消订单', value: data.current.cancel_count, diff: data.diff.cancel_count, icon: 'fa fa-commenting-o', color: '#ff4d4f'},
                    {label: 'LINE引流进店', value: data.current.traffic_count, diff: data.diff.traffic_count, icon: 'fa fa-shopping-bag', color: '#ff7a45'}
                ];
                var html = '';
                items.forEach(function (item) {
                    var diffClass = item.diff >= 0 ? 'up' : 'down';
                    var diffText = (item.diff >= 0 ? '+' : '') + item.diff + ' 对比昨日';
                    html += '<div class="overview-item">' +
                        '<div class="overview-icon" style="background: ' + item.color + '15; color: ' + item.color + '"><i class="' + item.icon + '"></i></div>' +
                        '<div class="overview-info">' +
                        '<span class="value">' + item.value + '</span>' +
                        '<span class="label">' + item.label + '</span>' +
                        '<div class="diff ' + diffClass + '">' + diffText + '</div>' +
                        '</div>' +
                        '</div>';
                });
                $('#overview-content').html(html);
            };

            var renderRevenue = function (data) {
                $('#revenue-total').text('¥ ' + data.total);
                $('#revenue-today').text('¥ ' + data.today);
                var diffClass = data.today_diff >= 0 ? 'up' : 'down';
                $('#revenue-diff').attr('class', 'diff ' + diffClass).text((data.today_diff >= 0 ? '+' : '') + data.today_diff + ' 对比昨日');
            };

            var renderTrend = function (list) {
                var html = '';
                list.forEach(function (item) {
                    html += '<tr>' +
                        '<td>' + item.date + '</td>' +
                        '<td>' + item.order_count + '</td>' +
                        '<td>¥' + item.amount + '</td>' +
                        '<td>' + item.user_count + '人</td>' +
                        '</tr>';
                });
                $('#trend-content').html(html);
            };

            var renderUserStats = function (data) {
                $('#user-total').text(data.total);
                $('#user-today').text(data.today_new);
                var diffClass = data.today_diff >= 0 ? 'up' : 'down';
                $('#user-diff').attr('class', 'diff ' + diffClass).text((data.today_diff >= 0 ? '+' : '') + data.today_diff + ' 对比昨日');
                $('#user-active').text(data.active_30);
            };

            var renderHotGoods = function (list) {
                var html = '';
                list.forEach(function (item, index) {
                    html += '<tr>' +
                        '<td>' + (index + 1) + '</td>' +
                        '<td style="text-align: left;">' + item.goods_name + '</td>' +
                        '<td>' + item.sales + '件</td>' +
                        '<td>¥' + item.amount + '</td>' +
                        '<td>' + item.percent + '% <div class="progress-bar-custom"><div class="progress-inner" style="width: ' + item.percent + '%"></div></div></td>' +
                        '</tr>';
                });
                $('#hot-content').html(html);
            };

            var renderShareStats = function (data) {
                $('#share-num').text(data.share_num + '次');
                $('#share-order').text(data.order_num + '单');
                $('#share-rate').text(data.rate);
            };

            // Tab click event
            $('.overview-tab').on('click', function () {
                $(this).addClass('active').siblings().removeClass('active');
                refreshData($(this).data('type'));
            });

            // Initial load
            refreshData('today');
        }
    };
    return Controller;
});
