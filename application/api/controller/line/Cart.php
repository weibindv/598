<?php

namespace app\api\controller\line;

use app\common\controller\Api;
use app\admin\model\line\Cart as CartModel;
use app\admin\model\line\Goods as GoodsModel;

/**
 * 购物车接口
 */
class Cart extends Api
{
    protected $noNeedLogin = [];
    protected $noNeedRight = '*';

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 购物车列表
     */
    public function index()
    {
        $userId = $this->auth->id;
        $list = CartModel::with(['goods'])
            ->where('user_id', $userId)
            ->order('updatetime', 'desc')
            ->select();

        $this->success('', $list);
    }

    /**
     * 加入购物车
     * @ApiParams (name="goods_id", type="integer", required=true, description="商品ID")
     * @ApiParams (name="goods_num", type="integer", required=true, description="商品数量")
     */
    public function add()
    {
        $goodsId = $this->request->post('goods_id');
        $goodsNum = $this->request->post('goods_num', 1);
        $userId = $this->auth->id;

        if (!$goodsId || $goodsNum <= 0) {
            $this->error(__('Parameter Error'));
        }

        $goods = GoodsModel::get($goodsId);
        if (!$goods) {
            $this->error(__('No Results were found'));
        }

        // 检查购物车是否已有该商品
        $cart = CartModel::where(['user_id' => $userId, 'goods_id' => $goodsId])->find();
        if ($cart) {
            $cart->goods_num += $goodsNum;
            $cart->save();
        } else {
            CartModel::create([
                'user_id' => $userId,
                'goods_id' => $goodsId,
                'goods_num' => $goodsNum
            ]);
        }

        $this->success(__('Operation completed'));
    }

    /**
     * 更新购物车数量
     * @ApiParams (name="id", type="integer", required=true, description="购物车记录ID")
     * @ApiParams (name="goods_num", type="integer", required=true, description="商品数量")
     */
    public function update()
    {
        $id = $this->request->post('id');
        $goodsNum = $this->request->post('goods_num');
        $userId = $this->auth->id;

        if (!$id || $goodsNum === null || $goodsNum < 0) {
            $this->error(__('Parameter Error'));
        }

        $cart = CartModel::where(['id' => $id, 'user_id' => $userId])->find();
        if (!$cart) {
            $this->error(__('No Results were found'));
        }

        if ($goodsNum == 0) {
            $cart->delete();
        } else {
            $cart->goods_num = $goodsNum;
            $cart->save();
        }

        $this->success(__('Operation completed'));
    }

    /**
     * 删除购物车商品
     * @ApiParams (name="ids", type="string", required=true, description="购物车记录ID，多个用逗号隔开")
     */
    public function delete()
    {
        $ids = $this->request->post('ids');
        $userId = $this->auth->id;

        if (!$ids) {
            $this->error(__('Parameter Error'));
        }

        $idArr = explode(',', $ids);
        CartModel::where('user_id', $userId)->where('id', 'in', $idArr)->delete();

        $this->success(__('Operation completed'));
    }

    /**
     * 清空购物车
     */
    public function clear()
    {
        $userId = $this->auth->id;
        CartModel::where('user_id', $userId)->delete();
        $this->success(__('Operation completed'));
    }
}
