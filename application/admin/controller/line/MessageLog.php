<?php

namespace app\admin\controller\line;

use app\common\controller\Backend;

/**
 * 消息记录管理
 *
 * @icon fa fa-circle-o
 */
class MessageLog extends Backend
{
    /**
     * MessageLog模型对象
     * @var \app\admin\model\line\MessageLog
     */
    protected $model = null;

    protected $merchant_id = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\line\MessageLog;

        // 增加商家/店员权限限制
        if (!$this->auth->isSuperAdmin()) {
            $admin_id = $this->auth->id;
            $merchant = \think\Db::name('line_merchant')->where('admin_id', $admin_id)->find();
            if ($merchant) {
                $this->merchant_id = $merchant['merchant_id'];
            } else {
                $staff = \think\Db::name('line_staff')->where('admin_id', $admin_id)->find();
                if ($staff) {
                    $this->merchant_id = $staff['merchant_id'];
                }
            }
        }

        $this->view->assign("msgTypeList", $this->model->getMsgTypeList());
        $this->view->assign("pushStatusList", $this->model->getPushStatusList());
    }

    /**
     * 查看
     */
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
                ->where($where)
                ->where(function ($query) {
                    if ($this->merchant_id) {
                        $query->where('merchant_id', $this->merchant_id);
                    }
                })
                ->order($sort, $order)
                ->paginate($limit);

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 详情
     */
    public function detail($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if ($this->merchant_id && $row->merchant_id != $this->merchant_id) {
            $this->error(__('You have no permission'));
        }
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }
}
