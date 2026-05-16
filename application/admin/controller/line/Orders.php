<?php

namespace app\admin\controller\line;

use app\common\controller\Backend;
use app\admin\model\line\MessageLog;

/**
 * 订单管理管理
 *
 * @icon fa fa-circle-o
 */
class Orders extends Backend
{

    /**
     * Orders模型对象
     * @var \app\admin\model\line\Orders
     */
    protected $model = null;

    protected $relationSearch = true;

    protected $merchant_id = null;
    protected $noNeedRight = ['delivery', 'cancel', 'finish', 'detail', 'log', 'refund', 'refund_process'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\line\Orders;

        // 增加商家/店员权限限制
        if (!$this->auth->isSuperAdmin()) {
            $admin_id = $this->auth->id;
            $merchant = \think\Db::name('line_merchant')->where('admin_id', $admin_id)->find();
            if ($merchant) {
                $this->merchant_id = (string) $merchant['merchant_id'];
            } else {
                $staff = \think\Db::name('line_staff')->where('admin_id', $admin_id)->find();
                if ($staff) {
                    $this->merchant_id = (string) $staff['merchant_id'];
                }
            }
            // 调试：记录当前用户的商户权限
            \think\Log::info("Orders _initialize: admin_id={$admin_id}, merchant_id={$this->merchant_id}, isSuperAdmin=" . ($this->auth->isSuperAdmin() ? 'true' : 'false'));
        }

        $this->view->assign("statusList", [
            0 => '待付款',
            1 => '待处理',
            2 => '待收货',
            3 => '已完成',
            4 => '已取消',
            5 => '退款中',
            6 => '已退款',
            7 => '售后中'
        ]);
    }

    public function index()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $list = $this->model
                ->with(['user', 'address'])
                ->where($where)
                ->where(function ($query) {
                    if ($this->merchant_id) {
                        $query->where('fa_line_orders.merchant_id', $this->merchant_id);
                    }
                })
                ->order($sort, $order)
                ->paginate($limit);

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

    public function delivery($ids = null)
    {
        if (!$ids) {
            $this->error(__('Parameter Error'));
        }
        $row = $this->model->get($ids);

        if (!$row) {
            $this->error(__('No Results were found'));
        }

        // 权限校验
        if (!$this->auth->isSuperAdmin() && $this->merchant_id && (string) $row->merchant_id !== (string) $this->merchant_id) {
            $this->error("无操作权限。调试信息：你的商户ID={$this->merchant_id}, 订单商户ID={$row->merchant_id}");
        }

        // 只有“待处理(1)”状态的订单才能发货
        if ($row->order_status != 1) {
            $this->error('当前订单状态不允许发货');
        }

        $row->save(['order_status' => 2, 'delivery_time' => time()]);
        //发货发送通知
        $msg = new MessageLog();
        $msg->save([
            'user_id' => $row->user_id,
            'nickname' => $row->user ? $row->user->nickname : '系统用户',
            'msg_type' => 2, // 对应 MessageLog 模型中的：订单发货通知
            'push_status' => 1, // 已推送
            'order_id' => $row->id,
            'order_no' => $row->order_no,
            'merchant_id' => $row->merchant_id
        ]);
        $this->success('发货成功');
    }

    public function cancel($ids = null)
    {
        if (!$ids) {
            $this->error(__('Parameter Error'));
        }
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if (!$this->auth->isSuperAdmin() && $this->merchant_id && (string) $row->merchant_id !== (string) $this->merchant_id) {
            $this->error(__('You have no permission'));
        }
        $row->save(['order_status' => 4, 'canceltime' => time()]);
        $this->success();
    }

    public function finish($ids = null)
    {
        if (!$ids) {
            $this->error(__('Parameter Error'));
        }
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if (!$this->auth->isSuperAdmin() && $this->merchant_id && (string) $row->merchant_id !== (string) $this->merchant_id) {
            $this->error(__('You have no permission'));
        }
        $row->save(['order_status' => 3, 'completetime' => time()]);
        $this->success();
    }

    public function detail($ids = null)
    {
        if (!$ids) {
            $this->error(__('Parameter Error'));
        }
        $row = $this->model->with(['user', 'address'])->where('fa_line_orders.id', $ids)->find();
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if (!$this->auth->isSuperAdmin() && $this->merchant_id && (string) $row->merchant_id !== (string) $this->merchant_id) {
            $this->error(__('You have no permission'));
        }
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }

    public function log($ids = null)
    {
        // Placeholder for order logs
        return "日志内容 (ID: $ids)";
    }

    public function refund_process($ids = null)
    {
        if (!$ids) {
            $this->error(__('Parameter Error'));
        }
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if (!$this->auth->isSuperAdmin() && $this->merchant_id && (string) $row->merchant_id !== (string) $this->merchant_id) {
            $this->error(__('You have no permission'));
        }

        if ($this->request->isPost()) {
            $action = $this->request->post('action');
            $admin_remark = $this->request->post('admin_remark');

            if ($action == 'agree') {
                // 同意退款：状态改为 6 (已退款)
                $row->save([
                    'order_status' => 6,
                    'admin_remark' => $admin_remark,
                    'refund_time' => time()
                ]);

                // 通知用户退款成功
                $msg = new MessageLog();
                $msg->save([
                    'user_id' => $row->user_id,
                    'nickname' => $row->user ? $row->user->nickname : '用户',
                    'msg_type' => 6, // 对应模型：售后完成通知
                    'push_status' => 1,
                    'order_id' => $row->id,
                    'order_no' => $row->order_no,
                    'merchant_id' => $row->merchant_id
                ]);

                $this->success('已同意退款并通知用户');
            } else if ($action == 'reject') {
                // 拒绝退款：将状态重置为之前的状态（例如 待处理1）
                $row->save([
                    'order_status' => 7,
                    'admin_remark' => $admin_remark
                ]);

                // 通知用户退款被拒绝
                $msg = new MessageLog();
                $msg->save([
                    'user_id' => $row->user_id,
                    'nickname' => $row->user ? $row->user->nickname : '用户',
                    'msg_type' => 7, // 其他通知/异常通知
                    'push_status' => 1,
                    'order_id' => $row->id,
                    'order_no' => $row->order_no,
                    'merchant_id' => $row->merchant_id
                ]);

                $this->success('已拒绝退款申请');
            }
            $this->error('非法操作');
        }

        $this->view->assign("row", $row);
        return $this->view->fetch();
    }

    public function refund($ids = null)
    {
        if (!$ids) {
            $this->error(__('Parameter Error'));
        }
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if (!$this->auth->isSuperAdmin() && $this->merchant_id && (string) $row->merchant_id !== (string) $this->merchant_id) {
            $this->error(__('You have no permission'));
        }
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }
}
