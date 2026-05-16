<?php

namespace app\admin\controller\line;

use app\common\controller\Backend;
use think\Db;
use app\admin\model\line\Orders;
use app\admin\model\line\LineUser;
use app\admin\model\line\Share;
use app\admin\model\line\Browse;

/**
 * 统计报表接口
 */
class Statistics extends Backend
{
    protected $noNeedRight = ['*'];

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 统计数据汇总
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            $type = $this->request->get('type', 'today');

            return json([
                'code' => 1,
                'msg'  => '',
                'data' => [
                    'overview'   => $this->getOverview($type),
                    'revenue'    => $this->getRevenue(),
                    'orderTrend' => $this->getOrderTrend(),
                    'userStats'  => $this->getUserStats(),
                    'hotGoods'   => $this->getHotGoods(),
                    'shareStats' => $this->getShareStats(),
                ]
            ]);
        }
        return $this->view->fetch();
    }

    /**
     * 模块一：概览统计
     */
    private function getOverview($type)
    {
        $startTime = 0;
        $endTime = time();
        $prevStartTime = 0;
        $prevEndTime = 0;

        switch ($type) {
            case 'today':
                $startTime = strtotime(date('Y-m-d 00:00:00'));
                $prevStartTime = $startTime - 86400;
                $prevEndTime = $startTime - 1;
                break;
            case 'week':
                $startTime = strtotime('this week 00:00:00');
                $prevStartTime = $startTime - 86400 * 7;
                $prevEndTime = $startTime - 1;
                break;
            case 'month':
                $startTime = strtotime(date('Y-m-01 00:00:00'));
                $prevStartTime = strtotime('-1 month', $startTime);
                $prevEndTime = $startTime - 1;
                break;
        }

        $getData = function ($start, $end) {
            // 成交金额
            $amount = Orders::where('paytime', 'between', [$start, $end])
                ->where('order_status', 'in', [1, 2, 3])
                ->sum('paid_amount');
            
            // 订单总数
            $orderCount = Orders::where('createtime', 'between', [$start, $end])->count();

            // 下单人数
            $userCount = Orders::where('createtime', 'between', [$start, $end])
                ->group('user_id')
                ->count();

            // 退款/取消订单
            $cancelCount = Orders::where('updatetime', 'between', [$start, $end])
                ->where('order_status', 'in', [4, 6])
                ->count();

            // LINE引流进店 (基于浏览记录)
            $trafficCount = Browse::where('create_time', 'between', [$start, $end])->count();

            return [
                'amount'       => number_format($amount, 2, '.', ''),
                'order_count'  => $orderCount,
                'user_count'   => $userCount,
                'cancel_count' => $cancelCount,
                'traffic_count'=> $trafficCount,
            ];
        };

        $current = $getData($startTime, $endTime);
        $previous = $getData($prevStartTime, $prevEndTime);

        // 计算对比差值
        $diff = [
            'amount'       => number_format($current['amount'] - $previous['amount'], 2, '.', ''),
            'order_count'  => $current['order_count'] - $previous['order_count'],
            'user_count'   => $current['user_count'] - $previous['user_count'],
            'cancel_count' => $current['cancel_count'] - $previous['cancel_count'],
            'traffic_count'=> $current['traffic_count'] - $previous['traffic_count'],
        ];

        return [
            'current'  => $current,
            'previous' => $previous,
            'diff'     => $diff
        ];
    }

    /**
     * 模块二：总营收
     */
    private function getRevenue()
    {
        $total = Orders::where('order_status', 'in', [1, 2, 3])->sum('paid_amount');
        
        $todayStart = strtotime(date('Y-m-d 00:00:00'));
        $todayAmount = Orders::where('paytime', '>=', $todayStart)
            ->where('order_status', 'in', [1, 2, 3])
            ->sum('paid_amount');
            
        $yesterdayStart = $todayStart - 86400;
        $yesterdayAmount = Orders::where('paytime', 'between', [$yesterdayStart, $todayStart - 1])
            ->where('order_status', 'in', [1, 2, 3])
            ->sum('paid_amount');

        return [
            'total'        => number_format($total, 2, '.', ''),
            'today'        => number_format($todayAmount, 2, '.', ''),
            'today_diff'   => number_format($todayAmount - $yesterdayAmount, 2, '.', ''),
        ];
    }

    /**
     * 模块三：订单趋势 (近7天)
     */
    private function getOrderTrend()
    {
        $list = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} day"));
            $start = strtotime($date . ' 00:00:00');
            $end = strtotime($date . ' 23:53:59');

            $orderCount = Orders::where('createtime', 'between', [$start, $end])->count();
            $amount = Orders::where('paytime', 'between', [$start, $end])
                ->where('order_status', 'in', [1, 2, 3])
                ->sum('paid_amount');
            $userCount = Orders::where('createtime', 'between', [$start, $end])
                ->group('user_id')
                ->count();

            $list[] = [
                'date'        => $date,
                'order_count' => $orderCount,
                'amount'      => number_format($amount, 2, '.', ''),
                'user_count'  => $userCount
            ];
        }
        return $list;
    }

    /**
     * 模块四：用户统计
     */
    private function getUserStats()
    {
        $total = \app\common\model\User::count();
        
        $todayStart = strtotime(date('Y-m-d 00:00:00'));
        $todayNew = \app\common\model\User::where('createtime', '>=', $todayStart)->count();
        
        $yesterdayStart = $todayStart - 86400;
        $yesterdayNew = \app\common\model\User::where('createtime', 'between', [$yesterdayStart, $todayStart - 1])->count();

        // 近30天活跃用户 (有登录记录的用户)
        $activeThreshold = time() - 86400 * 30;
        $activeUsers = \app\common\model\User::where('logintime', '>=', $activeThreshold)->count();

        return [
            'total'      => $total,
            'today_new'  => $todayNew,
            'today_diff' => $todayNew - $yesterdayNew,
            'active_30'  => $activeUsers
        ];
    }

    /**
     * 模块五：热门商品排行 (销量前10)
     */
    private function getHotGoods()
    {
        // 统计 paid_amount > 0 的订单中的商品
        // 因为商品详情存储在 JSON 中，我们可以从订单关联表中查询或者根据 goods_id 统计
        // 考虑到性能，我们从 fa_line_orders 汇总 goods_id (假设每个订单有一个主商品)
        // 或者从 fa_line_goods 关联查询。
        
        $list = Orders::where('order_status', 'in', [1, 2, 3])
            ->group('goods_id')
            ->field('goods_id, count(*) as sales, sum(paid_amount) as amount')
            ->order('sales', 'desc')
            ->limit(10)
            ->select();

        $totalAmount = Orders::where('order_status', 'in', [1, 2, 3])->sum('paid_amount');

        foreach ($list as &$item) {
            $goods = \app\admin\model\line\Goods::get($item['goods_id']);
            $item['goods_name'] = $goods ? $goods->title : '未知商品';
            $item['percent'] = $totalAmount > 0 ? round(($item['amount'] / $totalAmount) * 100, 2) : 0;
            $item['amount'] = number_format($item['amount'], 2, '.', '');
        }

        return $list;
    }

    /**
     * 模块六：LINE分享数据
     */
    private function getShareStats()
    {
        $totalShareNum = Share::sum('share_num');
        $totalOrderNum = Share::sum('order_num');
        
        $rate = $totalShareNum > 0 ? round(($totalOrderNum / $totalShareNum) * 100, 2) : 0;

        return [
            'share_num' => $totalShareNum,
            'order_num' => $totalOrderNum,
            'rate'      => $rate . '%'
        ];
    }
}
