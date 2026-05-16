<?php

namespace app\api\controller\line;

use app\common\controller\Api;
use app\admin\model\line\Browse as BrowseModel;
use app\admin\model\line\Goods as GoodsModel;

/**
 * 浏览历史接口
 */
class Browse extends Api
{
    protected $noNeedLogin = [];
    protected $noNeedRight = '*';

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 记录浏览行为
     * 
     * @ApiMethod (POST)
     * @ApiParams (name="goods_id", type="integer", required=true, description="商品ID")
     * @ApiParams (name="stay_time", type="integer", required=false, description="停留时长(秒)")
     */
    public function record()
    {
        $user_id = $this->auth->id;
        $goods_id = $this->request->post('goods_id');
        $stay_time = $this->request->post('stay_time', 0);

        if (!$goods_id) {
            $this->error('Missing goods_id');
        }

        $goods = GoodsModel::get($goods_id);
        if (!$row = $goods) {
            $this->error('Goods not found');
        }

        $browse = BrowseModel::where(['user_id' => $user_id, 'goods_id' => $goods_id])->find();

        if ($browse) {
            $browse->setInc('browse_num');
            $browse->save([
                'browse_time' => time(),
                'stay_time' => $browse->stay_time + $stay_time,
                'status' => 1 // 恢复正常状态
            ]);
        } else {
            BrowseModel::create([
                'user_id' => $user_id,
                'goods_id' => $goods_id,
                'browse_num' => 1,
                'browse_time' => time(),
                'stay_time' => $stay_time,
                'goods_title' => $goods->title,
                'goods_image' => $goods->image,
                'goods_price' => $goods->mall_price,
                'status' => 1
            ]);
        }

        $this->success('Success');
    }

    /**
     * 浏览历史列表
     * 
     * @ApiMethod (GET)
     * @ApiParams (name="page", type="integer", required=false, description="页码")
     * @ApiParams (name="limit", type="integer", required=false, description="每页数量")
     */
    public function index()
    {
        $user_id = $this->auth->id;
        $page = $this->request->get('page', 1);
        $limit = $this->request->get('limit', 10);

        $list = BrowseModel::where(['user_id' => $user_id, 'status' => 1])
            ->order('browse_time', 'desc')
            ->page($page, $limit)
            ->select();

        $total = BrowseModel::where(['user_id' => $user_id, 'status' => 1])->count();

        $this->success('', [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ]);
    }

    /**
     * 删除浏览记录
     * 
     * @ApiMethod (POST)
     * @ApiParams (name="ids", type="string", required=true, description="记录ID，多个逗号隔开")
     */
    public function delete()
    {
        $user_id = $this->auth->id;
        $ids = $this->request->post('ids');
        if (!$ids) {
            $this->error('Parameter missing');
        }

        BrowseModel::where('user_id', $user_id)
            ->where('id', 'in', $ids)
            ->update(['status' => 0]);

        $this->success('Deleted');
    }
}
