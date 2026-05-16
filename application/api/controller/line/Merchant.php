<?php

namespace app\api\controller\line;

use app\common\controller\Api;
use app\admin\model\line\Merchant as MerchantModel;
use think\Db;

/**
 * 商家/店铺接口
 */
class Merchant extends Api
{
    protected $noNeedLogin = ['index', 'detail'];
    protected $noNeedRight = '*';

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 店铺列表
     * 
     * @ApiMethod (GET)
     * @ApiParams (name="search", type="string", required=false, description="店铺名称搜索")
     * @ApiParams (name="page", type="integer", required=false, description="页码")
     * @ApiParams (name="limit", type="integer", required=false, description="每页数量")
     */
    public function index()
    {
        $search = $this->request->get('search');
        $page = $this->request->get('page', 1);
        $limit = $this->request->get('limit', 10);

        $where = ['status' => 1]; // 只查询正常的店铺
        if ($search) {
            $where['name'] = ['like', "%{$search}%"];
        }

        $list = MerchantModel::where($where)
            ->field('id,merchant_id,name,address,created_at')
            ->order('id', 'desc')
            ->page($page, $limit)
            ->select();

        $total = MerchantModel::where($where)->count();

        $this->success('', [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ]);
    }

    /**
     * 店铺详情
     * 
     * @ApiMethod (GET)
     * @ApiParams (name="id", type="integer", required=true, description="店铺ID")
     */
    public function detail()
    {
        $id = $this->request->get('id');
        if (!$id) {
            $this->error(__('Parameter Error'));
        }

        $row = MerchantModel::get($id);
        if (!$row || $row->status == 0) {
            $this->error(__('No Results were found'));
        }

        $this->success('', $row);
    }
}
