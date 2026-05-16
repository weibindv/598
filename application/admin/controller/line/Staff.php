<?php

namespace app\admin\controller\line;

use app\common\controller\Backend;
use think\Db;
use fast\Random;
use app\admin\model\AuthGroupAccess;

/**
 * 
 *
 * @icon fa fa-circle-o
 */
class Staff extends Backend
{

    /**
     * Staff模型对象
     * @var \app\admin\model\line\Staff
     */
    protected $model = null;

    protected $merchant_id = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\line\Staff;

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
    }



    public function index()
    {
        //设置过滤方法
        $this->request->filter(['trim', 'strip_tags', 'htmlspecialchars']);
        if ($this->request->isAjax()) {
            //如果发送的查询条件中包含关联查询，则需要注释下两行
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $merchant_id = $this->request->get('merchant_id') ?: $this->merchant_id;
            
            $list = $this->model
                ->where($where)
                ->where(function($query) use ($merchant_id){
                    if ($merchant_id) {
                        $query->where('merchant_id', $merchant_id);
                    }
                })
                ->order($sort, $order)
                ->paginate($limit);

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        $merchant_id = $this->request->get('merchant_id');
        $merchant = \app\admin\model\line\Merchant::get($merchant_id);
        $this->view->assign('merchant', $merchant);
        return $this->view->fetch();
    }

    public function add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                Db::startTrans();
                try {
                    // 1. 创建后台管理员账号
                    $admin = new \app\admin\model\Admin;
                    $admin->username = $params['username'];
                    $admin->nickname = $params['name'];
                    $admin->salt = Random::alnum();
                    $admin->password = $this->auth->getEncryptPassword($params['password'], $admin->salt);
                    $admin->avatar = '/assets/img/avatar.png'; // 默认头像
                    $admin->save();

                    // 2. 分配到店员角色组 (ID为6)
                    $group[] = 6; 
                    $dataset = [];
                    foreach ($group as $value) {
                        $dataset[] = ['uid' => $admin->id, 'group_id' => $value];
                    }
                    model('AuthGroupAccess')->saveAll($dataset);

                    // 3. 创建店员
                    $params['admin_id'] = $admin->id;
                    $params['status'] = 1; // 默认开通
                    $this->model->allowField(true)->save($params);
                    
                    Db::commit();
                } catch (\Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                $this->success();
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        return $this->view->fetch();
    }

    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $admin = \app\admin\model\Admin::get($row->admin_id);
        if (!$admin) {
            $admin = new \app\admin\model\Admin();
        }
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                Db::startTrans();
                try {
                    // 更新店员信息
                    $row->allowField(true)->save($params);

                    if (!$admin->id) {
                        // 如果没有关联管理员，则创建一个 (角色组为6)
                        $admin->username = $params['username'];
                        $admin->nickname = $params['name'];
                        $admin->salt = Random::alnum();
                        $admin->password = $this->auth->getEncryptPassword($params['password'] ?: '123456', $admin->salt);
                        $admin->avatar = '/assets/img/avatar.png';
                        $admin->save();

                        // 分配角色组
                        $dataset = [['uid' => $admin->id, 'group_id' => 6]];
                        model('AuthGroupAccess')->saveAll($dataset);

                        $row->admin_id = $admin->id;
                        $row->save();
                    } else {
                        // 更新管理员信息
                        if (isset($params['password']) && $params['password']) {
                            $admin->password = $this->auth->getEncryptPassword($params['password'], $admin->salt);
                        }
                        if (isset($params['username'])) {
                            $admin->username = $params['username'];
                        }
                        $admin->nickname = $params['name'];
                        if (isset($params['status'])) {
                            $admin->status = $params['status'] == 1 ? 'normal' : 'hidden';
                        }
                        $admin->save();
                    }

                    Db::commit();
                } catch (\Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                $this->success();
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $this->view->assign("row", $row);
        $this->view->assign("admin", $admin);
        return $this->view->fetch();
    }

    /**
     * 删除同步
     */
    public function del($ids = null)
    {
        if ($ids) {
            $list = $this->model->where('id', 'in', $ids)->select();
            foreach ($list as $row) {
                if ($row->admin_id) {
                    \app\admin\model\Admin::destroy($row->admin_id);
                }
            }
        }
        return parent::del($ids);
    }

    /**
     * 重置密码
     */
    public function reset_pwd($ids = null)
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $row = $this->model->get($ids);
                if (!$row) {
                    $this->error(__('No Results were found'));
                }
                $admin = \app\admin\model\Admin::get($row->admin_id);
                if ($admin) {
                    $admin->password = $this->auth->getEncryptPassword($params['password'], $admin->salt);
                    $admin->save();
                }
                $this->success();
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        return $this->view->fetch();
    }

    /**
     * 更新状态
     */
    public function status($ids = null)
    {
        $status = $this->request->param('status');
        if ($ids) {
            $this->model->where('id', 'in', $ids)->update(['status' => $status]);
            
            // 同步更新管理员状态
            $admin_status = $status == 1 ? 'normal' : 'hidden';
            $staffs = $this->model->where('id', 'in', $ids)->select();
            foreach ($staffs as $staff) {
                \app\admin\model\Admin::where('id', $staff->admin_id)->update(['status' => $admin_status]);
            }
            $this->success();
        }
        $this->error(__('Parameter %s can not be empty', 'ids'));
    }
}
