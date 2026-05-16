<?php

namespace app\api\controller\line;

use app\common\controller\Api;
use app\common\library\LinePay;
use app\admin\model\line\Orders;
use app\admin\model\line\MessageLog;
use app\admin\model\line\GoodsComment;
use app\admin\model\line\Share;
use think\Db;

/**
 * 订单接口
 */
class Order extends Api
{
    protected $noNeedLogin = [];
    protected $noNeedRight = '*';

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 下订单（支持多商品下单）
     * 
     * @ApiMethod (POST)
     * @ApiParams (name="goods", type="json", required=true, description="商品列表，格式：[{\"goods_id\":1,\"goods_num\":2,\"specs\":\"规格\"},...]")
     * @ApiParams (name="goods_id", type="integer", required=false, description="商品ID（兼容单商品模式）")
     * @ApiParams (name="goods_num", type="integer", required=false, description="数量（兼容单商品模式）")
     * @ApiParams (name="specs", type="string", required=false, description="规格（兼容单商品模式）")
     * @ApiParams (name="address_id", type="integer", required=true, description="地址ID")
     * @ApiParams (name="cart_ids", type="string", required=false, description="购物车ID列表，下单后自动清空，多个用逗号隔开")
     * @ApiParams (name="share_code", type="string", required=false, description="分享代码")
     */
    public function create()
    {
        $goods = $this->request->post('goods', '');
        $address_id = $this->request->post('address_id');
        $cart_ids = $this->request->post('cart_ids', '');
        $share_code = $this->request->post('share_code', '');
        $user = $this->auth->getUser();

        if (!$address_id) {
            $this->error(__('Invalid parameters'));
        }

        $goodsList = [];
        $total_amount = '0.00';
        $merchantIds = [];

        // 解析商品列表
        if (!empty($goods)) {
            if (is_array($goods)) {
                $goodsList = $goods;
            } else {
                // 尝试解析 JSON 字符串。
                $goods = html_entity_decode($goods); // 修复 HTML 实体问题 (如 &quot;)
                $goodsList = json_decode($goods, true);
                // 兼容处理：如果是带转义的字符串，尝试去掉转义再试一次
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $goodsList = json_decode(stripslashes($goods), true);
                }

                if (json_last_error() !== JSON_ERROR_NONE || !is_array($goodsList)) {
                    $this->error('商品列表格式错误：' . json_last_error_msg() . '，数据内容：' . $goods);
                }
            }
        } else {
            // 兼容单商品模式
            $goods_id = $this->request->post('goods_id');
            $goods_num = $this->request->post('goods_num', 1);
            $specs = $this->request->post('specs', '');

            if (!$goods_id) {
                $this->error('商品ID不能为空');
            }
            $goodsList[] = [
                'goods_id' => $goods_id,
                'goods_num' => $goods_num,
                'specs' => $specs
            ];
        }

        if (empty($goodsList)) {
            $this->error('商品列表不能为空');
        }

        $goodsDetails = [];
        $firstMerchantId = null;

        // 查询并计算每个商品
        foreach ($goodsList as $item) {
            $goodsId = $item['goods_id'];
            $goodsNum = intval($item['goods_num']);
            $specs = isset($item['specs']) ? $item['specs'] : '';

            if (!$goodsId || $goodsNum <= 0) {
                $this->error('商品ID或数量无效');
            }

            $goods = \app\admin\model\line\Goods::get($goodsId);
            if (!$goods) {
                $this->error('商品不存在，ID: ' . $goodsId);
            }

            // 临时调试：查看商品模型中的所有字段
            // halt($goods->toArray()); 

            // 计算单个商品总价
            $itemAmount = bcmul($goods->price, $goodsNum, 2);
            $total_amount = bcadd($total_amount, $itemAmount, 2);

            // 记录商户ID（用于消息推送）
            // 优先尝试 merchant_id，如果不存在则尝试 shop_id，再没有则默认为空
            $currentMerchantId = $goods->shop_id ?? null;

            \think\Log::info('商品ID: ' . $goodsId . ', 商户ID: ' . $currentMerchantId);
            \think\Log::info('商品数据: ' . json_encode($goods, JSON_UNESCAPED_UNICODE));
            \think\Log::info('商品详情: ' . json_encode($goodsDetails, JSON_UNESCAPED_UNICODE));
            if ($firstMerchantId === null) {
                $firstMerchantId = $currentMerchantId;
            }
            if ($currentMerchantId) {
                $merchantIds[$currentMerchantId] = $currentMerchantId;
            }

            // 构建商品详情
            $goodsDetails[] = [
                'goods_id' => $goodsId,
                'goods_title' => $goods->title,
                'goods_num' => $goodsNum,
                'goods_price' => $goods->price,
                'specs' => $specs,
                'subtotal' => $itemAmount
            ];
        }

        Db::startTrans();
        try {
            $orderData = [
                'user_id' => $user->id,
                'address_id' => $address_id,
                'total_amount' => $total_amount,
                'paid_amount' => 0,
                'order_status' => 0, // 待付款
                'merchant_id' => $firstMerchantId,
                'goods_detail' => json_encode($goodsDetails, JSON_UNESCAPED_UNICODE), // 存储多商品详情
                'share_code' => $share_code,
                'createtime' => time(),
                'updatetime' => time(),
            ];

            // 生成订单号 (手动调用模型中的生成逻辑或使用雪花算法)
            $snowflake = new \app\common\library\Snowflake(1, 1);
            $orderData['order_no'] = $snowflake->nextId();

            $orderId = Db::name('line_orders')->insertGetId($orderData);

            // 下单成功，为每个商户记录一条消息
            foreach ($merchantIds as $merchantId) {
                $msg = new MessageLog();
                $msg->save([
                    'user_id' => $user->id,
                    'nickname' => $user->nickname,
                    'msg_type' => 0, // 订单下单通知
                    'push_status' => 1, // 已推送
                    'order_id' => $orderId,
                    'order_no' => $orderData['order_no'],
                    'merchant_id' => $merchantId
                ]);
            }

            // 清空指定购物车记录
            if (!empty($cart_ids)) {
                $cartIdArr = explode(',', $cart_ids);
                \app\admin\model\line\Cart::where('user_id', $user->id)
                    ->where('id', 'in', $cartIdArr)
                    ->delete();
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error('下单失败：' . $e->getMessage());
        }

        $this->success('下单成功', [
            'order_id' => $orderId,
            'order_no' => $orderData['order_no'],
            'total_amount' => $total_amount,
            'goods_count' => count($goodsDetails)
        ]);
    }

    /**
     * 取消订单
     * 
     * @ApiMethod (POST)
     * @ApiParams (name="order_id", type="integer", required=true, description="订单ID")
     */
    public function cancel()
    {
        $order_id = $this->request->post('order_id');
        $user = $this->auth->getUser();
        if (!$order_id) {
            $this->error(__('Invalid parameters'));
        }
        $order = Orders::get($order_id);
        if (!$order) {
            $this->error('订单不存在');
        }
        if ($order->user_id != $user->id) {
            $this->error('您没有权限取消该订单');
        }
        if ($order->order_status != 0) {
            $this->error('订单状态不为待付款，不能取消');
        }

        Db::startTrans();
        try {
            $order->order_status = 4; // 已取消
            $order->canceltime = time();
            $order->save();

            // 记录取消消息
            $msg = new MessageLog();
            $msg->save([
                'user_id' => $user->id,
                'nickname' => $user->nickname,
                'msg_type' => 4, // 订单取消通知
                'push_status' => 1,
                'order_id' => $order->id,
                'order_no' => $order->order_no,
                'merchant_id' => $order->merchant_id
            ]);

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error('取消订单失败：' . $e->getMessage());
        }

        $this->success('取消订单成功');
    }

    /**
     * 订单列表
     * 
     * @ApiMethod (GET)
     * @ApiParams (name="status", type="integer", required=false, description="订单状态")
     * @ApiParams (name="page", type="integer", required=false, description="页码")
     * @ApiParams (name="limit", type="integer", required=false, description="每页数量")
     */
    public function index()
    {
        $status = $this->request->get('status', '');
        $page = $this->request->get('page', 1);
        $limit = $this->request->get('limit', 10);
        $user = $this->auth->getUser();
        $where = ['user_id' => $user->id];
        if ($status !== '') {
            $where['order_status'] = $status;
        }
        $list = Orders::where($where)->order('id', 'desc')->page($page, $limit)->select();
        $total = Orders::where($where)->count();
        $this->success('', ['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /**
     * 订单详情
     * 
     * @ApiMethod (GET)
     * @ApiParams (name="order_id", type="integer", required=true, description="订单ID")
     */
    public function detail()
    {
        $order_id = $this->request->get('order_id');
        $user = $this->auth->getUser();
        if (!$order_id) {
            $this->error(__('Invalid parameters'));
        }
        $order = Orders::with(['address'])->find($order_id);
        if (!$order) {
            $this->error('订单不存在');
        }
        if ($order->user_id != $user->id) {
            $this->error('您没有权限查看该订单');
        }
        // 解析多商品详情并合并当前商品图片
        $goodsDetail = json_decode($order->goods_detail, true) ?: [];
        $goodsIds = array_column($goodsDetail, 'goods_id');

        // 兼容处理：如果是旧版单商品订单且 goods_detail 为空
        if (empty($goodsIds) && $order->goods_id) {
            $goodsIds = [$order->goods_id];
            $goods = \app\admin\model\line\Goods::get($order->goods_id);
            if ($goods) {
                $goodsDetail = [[
                    'goods_id' => (string)$order->goods_id,
                    'goods_title' => $goods->title,
                    'goods_num' => 1,
                    'goods_price' => $goods->price,
                    'specs' => '',
                    'subtotal' => $goods->price
                ]];
            }
        }

        $goodsMap = [];
        if (!empty($goodsIds)) {
            $goodsList = \app\admin\model\line\Goods::where('id', 'in', $goodsIds)->select();
            foreach ($goodsList as $g) {
                $goodsMap[$g->id] = $g;
            }
        }

        // 重新封装数据，将当前图片加入快照详情中
        foreach ($goodsDetail as &$item) {
            $gid = $item['goods_id'];
            $item['image'] = isset($goodsMap[$gid]) ? $goodsMap[$gid]->image : '';
        }
        unset($item);

        $order['goods_list'] = $goodsDetail;

        $this->success('', [
            'order' => $order
        ]);
    }

    /**
     * 发起 LINE Pay 支付
     * 返回支付链接，前端需要跳转到该链接
     *
     * @ApiMethod (POST)
     * @ApiParams (name="order_no", type="string", required=true, description="订单号")
     */
    public function linePay()
    {
        $order_no = $this->request->post('order_no');
        $user = $this->auth->getUser();
        if (!$order_no) {
            $this->error(__('Invalid parameters'));
        }
        $order = Orders::where('order_no', $order_no)->find();
        if (!$order) {
            $this->error('订单不存在');
        }
        if ($order->user_id != $user->id) {
            $this->error('您没有权限支付该订单');
        }
        if ($order->order_status != 0) {
            $this->error('订单状态不为待付款，不能支付');
        }

        $linePay = new LinePay();
        $currency = 'TWD'; // 根据业务需求调整币种

        // 构建商品名称
        $goodsDetails = json_decode($order->goods_detail, true);
        $productName = "订单: " . $order->order_no;
        if (!empty($goodsDetails) && is_array($goodsDetails)) {
            $firstItem = $goodsDetails[0];
            $productName = $firstItem['goods_title'] ?? '商品';
            if (count($goodsDetails) > 1) {
                $productName .= ' 等' . count($goodsDetails) . '件商品';
            }
        }

        $products = [
            [
                'name' => mb_substr($productName, 0, 100, 'UTF-8'),
                'quantity' => 1,
                'price' => (float) $order->total_amount
            ]
        ];

        // 回调地址配置 (LINE Pay 会在成功后跳转回 confirmUrl 并带上 transactionId)
        // 我们需要手动带上 orderId 以便回调时识别订单
        $domain = $this->request->domain();
        $confirmUrl = $domain . '/api/line.order/linePayConfirm?orderId=' . $order->order_no;
        $cancelUrl = $domain . '/api/line.order/linePayCancel?orderId=' . $order->order_no;

        // 构建 LINE Pay 支付参数
        $params = $linePay->buildPaymentParams(
            $order->order_no,
            (float) $order->total_amount,
            $currency,
            $products,
            $confirmUrl,
            $cancelUrl
        );

        // 发起支付请求
        // \think\Log::info("[Order] Initiating LINE Pay for Order: " . $order->order_no);
        $result = $linePay->requestPayment($params);

        if (isset($result['returnCode']) && $result['returnCode'] == '0000') {
            // 保存 transactionId 到订单
            $transactionId = $result['info']['transactionId'] ?? '';
            $order->transaction_id = $transactionId;
            $order->save();

            // 获取支付链接并返回给前端
            $paymentUrl = $linePay->getPaymentUrl($result, 'web');

            $this->success('支付发起成功', [
                'payment_url' => $paymentUrl,
                'transaction_id' => $transactionId,
                'order_no' => $order->order_no
            ]);
        } else {
            $this->error('LINE Pay 支付发起失败：' . ($result['returnMessage'] ?? '未知错误'));
        }
    }

    /**
     * LINE Pay 支付回调确认
     * 用户在 LINE 完成支付后，LINE 会跳转回此地址
     */
    public function linePayConfirm()
    {
        $transactionId = $this->request->get('transactionId');
        $orderNo = $this->request->get('orderId');

        if (!$transactionId || !$orderNo) {
            $this->redirect('/#/pages/payment/fail?reason=missing_params');
            return;
        }

        // 根据订单号查询订单
        $order = Orders::where('order_no', $orderNo)->find();
        if (!$order) {
            $this->redirect('/#/pages/payment/fail?reason=order_not_found');
            return;
        }

        // 验证订单状态
        if ($order->order_status != 0) {
            // 已支付或已取消，直接跳转
            if ($order->order_status == 1) {
                $this->redirect('/#/pages/payment/success?order_id=' . $order->id);
            } else {
                $this->redirect('/#/pages/payment/fail?reason=order_status_invalid');
            }
            return;
        }

        $linePay = new LinePay();
        $currency = 'TWD';

        // 确认支付（扣款）
        $params = [
            'amount' => (float) $order->total_amount,
            'currency' => $currency
        ];

        $result = $linePay->confirmPayment($transactionId, $params);

        if (isset($result['returnCode']) && $result['returnCode'] == '0000') {
            // 支付成功，更新订单状态
            Db::startTrans();
            try {
                $order->order_status = 1; // 待处理
                $order->paid_amount = $order->total_amount;
                $order->paytime = time();
                $order->save();

                // 记录支付成功消息
                $msg = new MessageLog();
                $msg->save([
                    'user_id' => $order->user_id,
                    'nickname' => '',
                    'msg_type' => 1, // 新订单提醒 (支付成功)
                    'push_status' => 1,
                    'order_id' => $order->id,
                    'order_no' => $order->order_no,
                    'merchant_id' => $order->merchant_id
                ]);

                // 同步更新分享数据
                if (!empty($order->share_code)) {
                    $this->updateShareData($order->share_code, $order->paid_amount);
                }

                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                // 记录日志，但仍然跳转到成功页（以 LINE 返回的状态为准）
                // \think\Log::error('LINE Pay 确认支付后更新订单失败：' . $e->getMessage());
            }

            // 跳转到前端支付成功页面
            $this->redirect('/#/pages/payment/success?order_id=' . $order->id);
        } else {
            // 支付失败，记录日志
            // \think\Log::error('LINE Pay 确认支付失败：' . json_encode($result));
            $reason = isset($result['returnMessage']) ? urlencode($result['returnMessage']) : 'unknown';
            $this->redirect('/#/pages/payment/fail?reason=' . $reason);
        }
    }

    /**
     * LINE Pay 取消支付回调
     */
    public function linePayCancel()
    {
        $orderNo = $this->request->get('orderId', '');

        if ($orderNo) {
            // 可选：更新订单状态或记录日志
            // \think\Log::info('用户取消 LINE Pay 支付，订单号：' . $orderNo);
        }

        // 跳转到前端支付取消页面
        $this->redirect('/#/pages/payment/cancel');
    }

    /**
     * 模拟支付（测试用）
     * 直接将订单状态更新为已支付
     *
     * @ApiMethod (POST)
     * @ApiParams (name="order_no", type="string", required=true, description="订单号")
     */
    public function mockPay()
    {
        $order_no = $this->request->post('order_no');
        $user = $this->auth->getUser();

        if (!$order_no) {
            $this->error(__('Invalid parameters'));
        }

        $order = Orders::where('order_no', $order_no)->find();
        if (!$order) {
            $this->error('订单不存在');
        }
        if ($order->user_id != $user->id) {
            $this->error('您没有权限支付该订单');
        }
        if ($order->order_status != 0) {
            $this->error('订单状态不为待付款，不能支付');
        }

        Db::startTrans();
        try {
            $order->order_status = 1; // 待处理
            $order->paid_amount = $order->total_amount;
            $order->paytime = time();
            $order->save();

            // 记录支付成功消息
            $msg = new MessageLog();
            $msg->save([
                'user_id' => $order->user_id,
                'nickname' => '',
                'msg_type' => 1, // 新订单提醒 (支付成功)
                'push_status' => 1,
                'order_id' => $order->id,
                'order_no' => $order->order_no,
                'merchant_id' => $order->merchant_id
            ]);
            // 同步更新分享数据
            if (!empty($order->share_code)) {
                $this->updateShareData($order->share_code, $order->paid_amount);
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error('模拟支付失败：' . $e->getMessage());
        }

        $this->success('模拟支付成功', [
            'order_id' => $order->id,
            'order_no' => $order->order_no,
            'paid_amount' => $order->paid_amount,
            'paytime' => date('Y-m-d H:i:s', $order->paytime)
        ]);
    }

    /**
     * 查询 LINE Pay 支付状态
     *
     * @ApiMethod (GET)
     * @ApiParams (name="order_no", type="string", required=true, description="订单号")
     */
    public function linePayStatus()
    {
        $order_no = $this->request->get('order_no');
        $user = $this->auth->getUser();

        if (!$order_no) {
            $this->error(__('Invalid parameters'));
        }

        $order = Orders::where('order_no', $order_no)->find();
        if (!$order) {
            $this->error('订单不存在');
        }
        if ($order->user_id != $user->id) {
            $this->error('您没有权限查看该订单');
        }

        $status = [
            'order_status' => $order->order_status,
            'order_status_text' => $order->order_status_text,
            'transaction_id' => $order->transaction_id ?? '',
            'total_amount' => $order->total_amount,
            'paid_amount' => $order->paid_amount,
        ];

        $this->success('', $status);
    }

    /**
     * 订单确认收货
     *
     * @ApiMethod (POST)
     * @ApiParams (name="order_id", type="integer", required=true, description="订单ID")
     */
    public function confirm()
    {
        $order_id = $this->request->post('order_id');
        $user = $this->auth->getUser();
        if (!$order_id) {
            $this->error(__('Invalid parameters'));
        }
        $order = Orders::get($order_id);
        if (!$order) {
            $this->error('订单不存在');
        }
        if ($order->user_id != $user->id) {
            $this->error('您没有权限确认该订单');
        }
        if ($order->order_status != 2) {
            $this->error('订单状态不为待收货，不能确认收货');
        }
        $order->order_status = 3; // 已完成
        $order->completetime = time();
        $order->save();

        // 记录完成消息
        $msg = new MessageLog();
        $msg->save([
            'user_id' => $user->id,
            'nickname' => $user->nickname,
            'msg_type' => 3, // 订单完成通知
            'push_status' => 1,
            'order_id' => $order->id,
            'order_no' => $order->order_no,
            'merchant_id' => $order->merchant_id
        ]);

        $this->success('确认收货成功');
    }

    /**
     * 订单评价
     *
     * @ApiMethod (POST)
     * @ApiParams (name="order_id", type="integer", required=true, description="订单ID")
     * @ApiParams (name="score", type="integer", required=true, description="评分")
     * @ApiParams (name="content", type="string", required=true, description="评价内容")
     */
    public function evaluate()
    {
        $order_id = $this->request->post('order_id');
        $score = $this->request->post('score');
        $content = $this->request->post('content');
        $user = $this->auth->getUser();
        if (!$order_id) {
            $this->error(__('Invalid parameters'));
        }
        $order = Orders::get($order_id);
        if (!$order) {
            $this->error('订单不存在');
        }
        if ($order->user_id != $user->id) {
            $this->error('您没有权限评价该订单');
        }
        if ($order->order_status != 3) {
            $this->error('订单状态不为已完成，不能评价');
        }
        $order->order_status = 3; // 保持已完成状态（已评价可单独用字段标记）
        $order->save();
        $evaluate = new GoodsComment();
        $evaluate->save([
            'user_id' => $user->id,
            'order_id' => $order_id,
            'goods_id' => $order->goods_id,
            'merchant_id' => $order->merchant_id,
            'score' => $score,
            'content' => $content
        ]);
        $this->success('评价成功');
    }

    /**
     * 用户发起退款申请
     */
    public function refund()
    {
        $order_id = $this->request->post('order_id');
        $reason = $this->request->post('reason', '');
        $images = $this->request->post('images', '');
        $user = $this->auth->getUser();

        if (!$order_id) {
            $this->error('订单ID不能为空');
        }

        $order = Orders::get($order_id);
        if (!$order) {
            $this->error('订单不存在');
        }

        if ($order->user_id != $user->id) {
            $this->error('您没有权限操作该订单');
        }

        // 验证订单状态：只有已支付（待处理1、待收货2、已完成3）的订单可以申请退款
        // 注意：0是待付款，4是已取消，5是已经在退款中
        $allowStatus = [1, 2, 3];
        if (!in_array($order->order_status, $allowStatus)) {
            $this->error('当前订单状态不允许申请退款');
        }

        Db::startTrans();
        try {
            // 更新订单状态为 退款中(5)
            // 建议在 fa_line_orders 表中增加 refund_reason 等字段，如果没有，以下保存会忽略不存在的字段
            $refund_apply_time = date('Y-m-d H:i:s', time());
            $order->save([
                'order_status' => 5,
                'refund_reason' => $reason,
                'refund_images' => $images,
                'refund_apply_time' => $refund_apply_time,
            ]);

            // 记录消息通知商家有新的退款申请
            $msg = new MessageLog();
            $msg->save([
                'user_id' => $user->id,
                'nickname' => $user->nickname,
                'msg_type' => 5, // 对应 MessageLog 模型中的：售后申请通知
                'push_status' => 1,
                'order_id' => $order->id,
                'order_no' => $order->order_no,
                'merchant_id' => $order->merchant_id
            ]);

            Db::commit();
            $this->success('退款申请已提交，请等待商家处理');
        } catch (\Exception $e) {
            Db::rollback();
            $this->error('提交失败：' . $e->getMessage());
        }
    }

    private function updateShareData($shareCode, $amount)
    {
        $share = Share::where('share_code', $shareCode)->find();
        if ($share) {
            $share->setInc('order_num');
            $share->setInc('order_amount', $amount);
            $share->save(['update_time' => time()]);
        }
    }
}
