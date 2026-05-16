<?php

namespace app\api\controller\line;

use app\common\controller\Api;
use app\admin\model\line\GoodsComment as CommentModel;
use app\admin\model\line\Goods as GoodsModel;

/**
 * 商品评论接口
 */
class GoodsComment extends Api
{
    protected $noNeedLogin = ['index'];
    protected $noNeedRight = '*';

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 评论列表
     * @ApiParams (name="goods_id", type="integer", required=true, description="商品ID")
     * @ApiParams (name="page", type="integer", required=false, description="页码")
     * @ApiParams (name="limit", type="integer", required=false, description="每页数量")
     */
    public function index()
    {
        $goodsId = $this->request->get('goods_id');
        $page = $this->request->get('page', 1);
        $limit = $this->request->get('limit', 10);

        if (!$goodsId) {
            $this->error(__('Parameter Error'));
        }

        $list = CommentModel::with([
            'user' => function ($query) {
                $query->withField('id,nickname,avatar');
            }
        ])
            ->where('goods_id', $goodsId)
            ->where('status', 1)
            ->order('createtime', 'desc')
            ->page($page, $limit)
            ->select();

        $total = CommentModel::where('goods_id', $goodsId)->where('status', 1)->count();

        $this->success('', [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ]);
    }

    /**
     * 添加评论
     * @ApiParams (name="goods_id", type="integer", required=true, description="商品ID")
     * @ApiParams (name="order_id", type="integer", required=false, description="订单ID")
     * @ApiParams (name="score", type="integer", required=true, description="评分1-5")
     * @ApiParams (name="content", type="string", required=true, description="内容")
     * @ApiParams (name="images", type="string", required=false, description="图片地址，多个逗号隔开")
     * @ApiParams (name="videos", type="string", required=false, description="视频地址，多个逗号隔开")
     */
    public function add()
    {
        $goodsId = $this->request->post('goods_id');
        $orderId = $this->request->post('order_id', 0);
        $score = $this->request->post('score', 5);
        $content = $this->request->post('content');
        $images = $this->request->post('images', '');
        $videos = $this->request->post('videos', '');
        $userId = $this->auth->id;

        if (!$goodsId || !$content) {
            $this->error(__('Parameter Error'));
        }

        $goods = GoodsModel::get($goodsId);
        if (!$goods) {
            $this->error(__('No Results were found'));
        }

        $comment = CommentModel::create([
            'user_id' => $userId,
            'goods_id' => $goodsId,
            'order_id' => $orderId,
            'score' => $score,
            'content' => $content,
            'images' => $images,
            'videos' => $videos,
            'status' => 1
        ]);

        if ($comment) {
            $this->success(__('Operation completed'));
        } else {
            $this->error(__('Operation failed'));
        }
    }

    /**
     * 删除评论
     */
    public function delete()
    {
        $id = $this->request->post('id');
        $userId = $this->auth->id;

        if (!$id) {
            $this->error(__('Parameter Error'));
        }

        $comment = CommentModel::where(['id' => $id, 'user_id' => $userId])->find();
        if (!$comment) {
            $this->error(__('No Results were found'));
        }

        $comment->delete();
        $this->success(__('Operation completed'));
    }

    /**
     * 修改评论
     */
    public function update()
    {
        $id = $this->request->post('id');
        $score = $this->request->post('score');
        $content = $this->request->post('content');
        $images = $this->request->post('images');
        $videos = $this->request->post('videos');
        $userId = $this->auth->id;

        if (!$id) {
            $this->error(__('Parameter Error'));
        }

        $comment = CommentModel::where(['id' => $id, 'user_id' => $userId])->find();
        if (!$comment) {
            $this->error(__('No Results were found'));
        }

        if ($score !== null)
            $comment->score = $score;
        if ($content !== null)
            $comment->content = $content;
        if ($images !== null)
            $comment->images = $images;
        if ($videos !== null)
            $comment->videos = $videos;

        $comment->save();
        $this->success(__('Operation completed'));
    }
}
