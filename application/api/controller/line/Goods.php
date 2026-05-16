<?php

namespace app\api\controller\line;

use app\common\controller\Api;
use app\admin\model\line\Goods as GoodsModel;
use think\Db;

/**
 * 商品接口
 */
class Goods extends Api
{
    protected $noNeedLogin = ['index', 'detail', 'category'];
    protected $noNeedRight = '*';

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 商品列表
     * 
     * @ApiMethod (GET)
     * @ApiParams (name="search", type="string", required=false, description="搜索关键词")
     * @ApiParams (name="category_id", type="integer", required=false, description="分类ID")
     * @ApiParams (name="category_name", type="string", required=false, description="分类名称")
     * @ApiParams (name="sort", type="string", required=false, description="排序方式: smart=智能, new=最新, sales=销量, price_asc=价格升序, price_desc=价格降序")
     * @ApiParams (name="page", type="integer", required=false, description="页码")
     * @ApiParams (name="limit", type="integer", required=false, description="每页数量")
     */
    public function index()
    {
        $merchantId = $this->request->get('merchant_id');
        $search = $this->request->get('search');
        $categoryId = $this->request->get('category_id');
        $categoryName = $this->request->get('category_name');
        $stockStatus = $this->request->get('stock_status');
        $shareStatus = $this->request->get('share_status');
        $sort = $this->request->get('sort', 'smart');
        $page = $this->request->get('page', 1);
        $limit = $this->request->get('limit', 10);

        $where = [];
        if ($merchantId) {
            $where['merchant_id'] = $merchantId;
        }
        if ($search) {
            $where['title'] = ['like', "%{$search}%"];
        }
        if ($categoryId) {
            $where['category_id'] = $categoryId;
        }
        if ($categoryName) {
            $where['category_name'] = $categoryName;
        }
        if ($stockStatus) {
            $where['stock_status'] = $stockStatus;
        }
        if ($shareStatus !== null && $shareStatus !== '') {
            $where['share_status'] = $shareStatus;
        }

        $query = GoodsModel::where($where);

        // 智能排序逻辑
        switch ($sort) {
            case 'new':
                $query->order('createtime', 'desc');
                break;
            case 'sales':
                $query->order('sales', 'desc');
                break;
            case 'price_asc':
                $query->order('mall_price', 'asc');
                break;
            case 'price_desc':
                $query->order('mall_price', 'desc');
                break;
            case 'smart':
            default:
                // 智能排序：优先权重 weigh，其次销量 sales，最后时间 createtime
                $query->order('weigh', 'desc')
                    ->order('sales', 'desc')
                    ->order('createtime', 'desc');
                break;
        }

        $list = $query->page($page, $limit)
            ->field('id,title,image,mall_price,merchant_price,stock_status,sales,category_name,weigh,createtime')
            ->select();

        $total = GoodsModel::where($where)->count();

        $this->success('', [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ]);
    }

    /**
     * 分类列表
     * 
     * @ApiMethod (GET)
     */
    public function category()
    {
        // 从商品表中提取去重的分类信息
        $list = GoodsModel::where('category_name', 'not null')
            ->group('category_name')
            ->field('category_id, category_name')
            ->order('weigh', 'desc')
            ->select();

        $this->success('', $list);
    }

    /**
     * 商品详情
     * 
     * @ApiMethod (GET)
     * @ApiParams (name="id", type="integer", required=true, description="商品ID")
     */
    public function detail()
    {
        $id = $this->request->get('id');
        if (!$id) {
            $this->error(__('Parameter Error'));
        }

        $row = GoodsModel::get($id);
        if (!$row) {
            $this->error(__('No Results were found'));
        }

        $this->success('', $row);
    }
}
