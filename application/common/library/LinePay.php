<?php

namespace app\common\library;

/**
 * LINE Pay V3 支付助手
 */
class LinePay
{
    private $channelId;
    private $channelSecret;
    private $apiUrl;
    private $isSandbox;

    public function __construct()
    {
        $config = config('line');
        $this->channelId = $config['pay_channel_id'] ?? '';
        $this->channelSecret = $config['pay_channel_secret'] ?? '';
        $this->isSandbox = $config['pay_is_sandbox'] ?? true;
        $this->apiUrl = $this->isSandbox ? 'https://sandbox-api-pay.line.me' : 'https://api-pay.line.me';
    }

    /**
     * 生成 LINE Pay V3 要求的签名标头
     */
    private function getHeaders($uri, $body)
    {
        $nonce = $this->uuidv4();
        $bodyStr = is_string($body) ? $body : json_encode($body);
        $data = $this->channelSecret . $uri . $bodyStr . $nonce;
        $signature = base64_encode(hash_hmac('sha256', $data, $this->channelSecret, true));

        // 记录签名组件用于调试
        \think\Log::record("[LinePay] Signature Components: URI={$uri}, Nonce={$nonce}, Body=" . $bodyStr, 'debug');
        \think\Log::record("[LinePay] Generated Signature: " . $signature, 'debug');

        return [
            "Content-Type: application/json",
            "X-LINE-ChannelId: {$this->channelId}",
            "X-LINE-Authorization-Nonce: {$nonce}",
            "X-LINE-Authorization: {$signature}"
        ];
    }

    /**
     * 发起支付请求
     * @param array $params LINE Pay 支付参数
     * @return array
     */
    public function requestPayment($params)
    {
        $uri = '/v3/payments/request';
        return $this->curlPost($uri, $params);
    }

    /**
     * 确认支付（扣款完成）
     * @param string $transactionId 交易ID
     * @param array $params 确认参数
     * @return array
     */
    public function confirmPayment($transactionId, $params)
    {
        $uri = "/v3/payments/{$transactionId}/confirm";
        return $this->curlPost($uri, $params);
    }

    /**
     * 查询支付状态
     * @param string $transactionId 交易ID
     * @return array
     */
    public function getPaymentDetails($transactionId)
    {
        $uri = "/v3/payments/{$transactionId}";
        return $this->curlGet($uri);
    }

    /**
     * 取消支付
     * @param string $transactionId 交易ID
     * @return array
     */
    public function cancelPayment($transactionId)
    {
        $uri = "/v3/payments/{$transactionId}/cancel";
        return $this->curlPost($uri, []);
    }

    /**
     * 退款
     * @param string $transactionId 交易ID
     * @param float $refundAmount 退款金额
     * @return array
     */
    public function refund($transactionId, $refundAmount = 0)
    {
        $uri = "/v3/payments/{$transactionId}/refund";
        $body = [];
        if ($refundAmount > 0) {
            $body['refundAmount'] = $refundAmount;
        }
        return $this->curlPost($uri, $body);
    }

    /**
     * 构建支付请求参数
     * @param string $orderNo 订单号
     * @param float $amount 金额
     * @param string $currency 币种 (TWD/JPY/THB)
     * @param array $products 商品信息
     * @param string $confirmUrl 支付成功回调URL
     * @param string $cancelUrl 取消支付回调URL
     * @return array
     */
    public function buildPaymentParams($orderNo, $amount, $currency, $products, $confirmUrl, $cancelUrl)
    {
        return [
            'amount' => (float) $amount,
            'currency' => $currency,
            'orderId' => $orderNo,
            'packages' => [
                [
                    'id' => 'pkg_' . $orderNo,
                    'amount' => (float) $amount,
                    'name' => 'Order Package',
                    'products' => $products
                ]
            ],
            'redirectUrls' => [
                'confirmUrl' => $confirmUrl,
                'cancelUrl' => $cancelUrl
            ]
        ];
    }

    /**
     * 获取支付跳转链接
     * @param array $requestResult requestPayment 返回结果
     * @param string $type 链接类型：web (Web), app (App), webConnection (两用)
     * @return string|null
     */
    public function getPaymentUrl($requestResult, $type = 'web')
    {
        if (isset($requestResult['returnCode']) && $requestResult['returnCode'] == '0000') {
            $info = $requestResult['info'];
            if ($type === 'app' && isset($info['paymentUrl']['app'])) {
                return $info['paymentUrl']['app'];
            } elseif ($type === 'webConnection' && isset($info['paymentUrl']['webConnection'])) {
                return $info['paymentUrl']['webConnection'];
            } elseif (isset($info['paymentUrl']['web'])) {
                return $info['paymentUrl']['web'];
            }
        }
        return null;
    }

    private function curlPost($uri, $body)
    {
        $bodyStr = json_encode($body);
        $headers = $this->getHeaders($uri, $bodyStr);

        \think\Log::record("[LinePay] POST Request: {$this->apiUrl}{$uri}", 'info');
        \think\Log::record("[LinePay] Request Body: " . $bodyStr, 'debug');

        $ch = curl_init($this->apiUrl . $uri);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyStr);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            \think\Log::error("[LinePay] CURL Error: " . $error);
            return [
                'returnCode' => '9999',
                'returnMessage' => 'CURL Error: ' . $error
            ];
        }

        \think\Log::record("[LinePay] Response Code: {$httpCode}, Body: " . $response, 'info');

        return json_decode($response, true);
    }

    private function curlGet($uri)
    {
        $nonce = $this->uuidv4();
        // GET 请求没有 body，签名时 body 为空字符串
        $data = $this->channelSecret . $uri . "" . $nonce;
        $signature = base64_encode(hash_hmac('sha256', $data, $this->channelSecret, true));

        $headers = [
            "Content-Type: application/json",
            "X-LINE-ChannelId: {$this->channelId}",
            "X-LINE-Authorization-Nonce: {$nonce}",
            "X-LINE-Authorization: {$signature}"
        ];

        \think\Log::record("[LinePay] GET Request: {$this->apiUrl}{$uri}", 'info');

        $ch = curl_init($this->apiUrl . $uri);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        \think\Log::record("[LinePay] Response Code: {$httpCode}, Body: " . $response, 'info');

        return json_decode($response, true);
    }

    private function uuidv4()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
}
