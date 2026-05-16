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
class Merchant extends Backend
{

    /**
     * Merchant模型对象
     * @var \app\admin\model\line\Merchant
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\line\Merchant;
    }

    /**
     * 添加
     */
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

                    // 2. 分配到商家角色组 (假设商家角色组ID为2)
                    $authRule = new \app\admin\model\AuthRule();
                    $group[] = 2; // 假设商家角色组ID为2, 请根据实际情况修改
                    $dataset = [];
                    foreach ($group as $value) {
                        $dataset[] = ['uid' => $admin->id, 'group_id' => $value];
                    }
                    model('AuthGroupAccess')->saveAll($dataset);

                    // 3. 创建商家
                    $params['merchant_id'] = 'M' . date('YmdHis') . Random::alnum(6);
                    $params['admin_id'] = $admin->id;
                    $this->model->data($params, true);
                    $this->model->allowField(true)->save();

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

    /**
     * 编辑
     */
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
                    // 更新商家信息
                    $row->allowField(true)->save($params);

                    if (!$admin->id) {
                        // 如果没有关联管理员，则创建一个 (假设角色组为2)
                        $admin->username = $params['username'];
                        $admin->nickname = $params['name'];
                        $admin->salt = Random::alnum();
                        $admin->password = $this->auth->getEncryptPassword($params['password'] ?: '123456', $admin->salt);
                        $admin->avatar = '/assets/img/avatar.png';
                        $admin->save();

                        // 分配角色组
                        $dataset = [['uid' => $admin->id, 'group_id' => 2]];
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
     * 批量更新状态同步
     */
    public function multi($ids = null)
    {
        $params = $this->request->param();
        if (isset($params['params'])) {
            parse_str($params['params'], $values);
            if (isset($values['status'])) {
                $status = $values['status'];
                $admin_status = $status == 1 ? 'normal' : 'hidden';
                $list = $this->model->where('id', 'in', $ids)->select();
                foreach ($list as $row) {
                    if ($row->admin_id) {
                        \app\admin\model\Admin::where('id', $row->admin_id)->update(['status' => $admin_status]);
                    }
                }
            }
        }
        return parent::multi($ids);
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
     * 商家/店员登录地址
     * /public/admin.php
     */

    /**
     * 同步厂商
     */
    public function sync()
    {
        // 这里的URL需要根据实际情况修改
        $url = "https://myg.sxlingchuangkeji.com/dNcuxKPDUj.php/wanlshop/shop/getAll";
        $content = @file_get_contents($url);
        if (!$content) {
            $this->error("无法连接到同步服务器，请检查接口地址是否正确");
        }
        $res = json_decode($content, true);
        if (!$res || $res['code'] != 1) {
            $this->error("接口返回数据异常");
        }

        $data = $res['data'];
        $count = 0;
        $updated = 0;

        Db::startTrans();
        try {
            foreach ($data as $item) {
                $merchant = $this->model->where('name', $item['shopname'])->find();
                if (!$merchant) {
                    // 1. 创建后台管理员账号
                    $admin = new \app\admin\model\Admin;
                    // 假设用户名使用店铺名拼音或随机，这里简单处理
                    $admin->username = $item['shopname'] . '_' . Random::numeric(4);
                    $admin->nickname = $item['shopname'];
                    $admin->salt = Random::alnum();
                    $admin->password = $this->auth->getEncryptPassword('123456', $admin->salt);
                    $admin->avatar = '/assets/img/avatar.png';
                    $admin->save();

                    // 2. 分配角色组
                    $dataset = [['uid' => $admin->id, 'group_id' => 2]];
                    model('AuthGroupAccess')->saveAll($dataset);

                    // 3. 创建商家
                    $new_merchant = [
                        'merchant_id' => $item['id'],
                        'admin_id' => $admin->id,
                        'name' => $item['shopname'],
                        'address' => $item['city'] ?? '',
                        'status' => $item['status'] == 'normal' ? 1 : 0
                    ];
                    $this->model->create($new_merchant);
                    $count++;
                } else {
                    // 更新现有商家状态
                    $merchant->status = $item['status'] == 'normal' ? 1 : 0;
                    $merchant->save();
                    $updated++;
                }
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }

        $this->success("同步完成：新增 {$count} 条，更新 {$updated} 条");
    }

}
