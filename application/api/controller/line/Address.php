<?php

namespace app\api\controller\line;

use app\common\controller\Api;
use app\admin\model\line\UserAddress;

/**
 * 用户地址接口
 */
class Address extends Api
{
    protected $noNeedLogin = [];
    protected $noNeedRight = '*';

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 地址列表
     */
    public function index()
    {
        $page = $this->request->request('page', 1);
        $limit = $this->request->request('limit', 10);
        $userId = $this->auth->id;

        // 获取总数
        $total = UserAddress::where('user_id', $userId)->count();

        // 获取列表
        $list = UserAddress::where('user_id', $userId)
            ->order('is_default', 'desc')
            ->order('updatetime', 'desc')
            ->page($page, $limit)
            ->select();

        $this->success('', [
            'list'  => $list,
            'total' => $total,
            'page'  => (int)$page,
            'limit' => (int)$limit
        ]);
    }

    /**
     * 添加地址
     * @ApiParams (name="name", type="string", required=true, description="收货人姓名")
     * @ApiParams (name="mobile", type="string", required=true, description="联系电话")
     * @ApiParams (name="province", type="string", required=true, description="省份")
     * @ApiParams (name="city", type="string", required=true, description="城市")
     * @ApiParams (name="district", type="string", required=true, description="区县")
     * @ApiParams (name="address", type="string", required=true, description="详细地址")
     * @ApiParams (name="is_default", type="integer", required=false, description="是否默认:0=否,1=是")
     */
    public function add()
    {
        $params = $this->request->post();
        $userId = $this->auth->id;

        if (empty($params['name']) || empty($params['mobile']) || empty($params['address'])) {
            $this->error(__('Parameter Error'));
        }

        $isDefault = isset($params['is_default']) ? (int) $params['is_default'] : 0;

        if ($isDefault) {
            // 将该用户其它地址设为非默认
            UserAddress::where('user_id', $userId)->update(['is_default' => 0]);
        }

        $address = UserAddress::create([
            'user_id' => $userId,
            'name' => $params['name'],
            'mobile' => $params['mobile'],
            'province' => $params['province'] ?? '',
            'city' => $params['city'] ?? '',
            'district' => $params['district'] ?? '',
            'address' => $params['address'],
            'is_default' => $isDefault
        ]);

        if ($address) {
            $this->success(__('Operation completed'), $address);
        } else {
            $this->error(__('Operation failed'));
        }
    }

    /**
     * 编辑地址
     * @ApiParams (name="id", type="integer", required=true, description="地址ID")
     */
    public function update()
    {
        $id = $this->request->post('id');
        $params = $this->request->post();
        $userId = $this->auth->id;

        if (!$id) {
            $this->error(__('Parameter Error'));
        }

        $address = UserAddress::where(['id' => $id, 'user_id' => $userId])->find();
        if (!$address) {
            $this->error(__('No Results were found'));
        }

        $isDefault = isset($params['is_default']) ? (int) $params['is_default'] : $address->is_default;

        if ($isDefault && $address->is_default == 0) {
            UserAddress::where('user_id', $userId)->update(['is_default' => 0]);
        }

        $data = [
            'name' => $params['name'] ?? $address->getData('name'),
            'mobile' => $params['mobile'] ?? $address->mobile,
            'province' => $params['province'] ?? $address->province,
            'city' => $params['city'] ?? $address->city,
            'district' => $params['district'] ?? $address->district,
            'address' => $params['address'] ?? $address->address,
            'is_default' => $isDefault
        ];

        $address->save($data);
        $this->success(__('Operation completed'));
    }

    /**
     * 删除地址
     */
    public function delete()
    {
        $id = $this->request->post('id');
        $userId = $this->auth->id;

        if (!$id) {
            $this->error(__('Parameter Error'));
        }

        $address = UserAddress::where(['id' => $id, 'user_id' => $userId])->find();
        if (!$address) {
            $this->error(__('No Results were found'));
        }

        $address->delete();
        $this->success(__('Operation completed'));
    }

    /**
     * 设置默认地址
     */
    public function setDefault()
    {
        $id = $this->request->post('id');
        $userId = $this->auth->id;

        if (!$id) {
            $this->error(__('Parameter Error'));
        }

        $address = UserAddress::where(['id' => $id, 'user_id' => $userId])->find();
        if (!$address) {
            $this->error(__('No Results were found'));
        }

        UserAddress::where('user_id', $userId)->update(['is_default' => 0]);
        $address->is_default = 1;
        $address->save();

        $this->success(__('Operation completed'));
    }

    /**
     * 地址详情
     */
    public function detail()
    {
        $id = $this->request->get('id');
        $userId = $this->auth->id;

        if (!$id) {
            $this->error(__('Parameter Error'));
        }

        $address = UserAddress::where(['id' => $id, 'user_id' => $userId])->find();
        if (!$address) {
            $this->error(__('No Results were found'));
        }

        $this->success('', $address);
    }
}
