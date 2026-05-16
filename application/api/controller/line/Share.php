<?php

namespace app\api\controller\line;

use app\common\controller\Api;
use app\admin\model\line\Share as ShareModel;
use app\admin\model\line\Goods as GoodsModel;
use fast\Random;

/**
 * 商品分享接口
 */
class Share extends Api
{
    protected $noNeedLogin = [];
    protected $noNeedRight = '*';

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 记录分享行为
     * 
     * @ApiMethod (POST)
     * @ApiParams (name="goods_id", type="integer", required=true, description="商品ID")
     * @ApiParams (name="share_type", type="integer", required=false, description="分享渠道 1=Line")
     * @ApiParams (name="channel", type="string", required=false, description="自定义分享渠道标记")
     * @ApiParams (name="share_url", type="string", required=false, description="分享链接/小程序路径")
     */
    public function record()
    {
        $user_id = $this->auth->id;
        $goods_id = $this->request->post('goods_id');
        $share_type = $this->request->post('share_type', 1);
        $channel = $this->request->post('channel', 'LINE');
        $share_url = $this->request->post('share_url', '');

        if (!$goods_id) {
            $this->error('Missing goods_id');
        }

        $goods = GoodsModel::get($goods_id);
        if (!$goods) {
            $this->error('Goods not found');
        }

        // 查找该用户对该商品的该渠道是否已有记录
        $share = ShareModel::where([
            'user_id' => $user_id,
            'goods_id' => $goods_id,
            'share_type' => $share_type
        ])->find();

        if ($share) {
            $share->setInc('share_num');
            $share->save([
                'last_share_time' => time(),
                'status' => 1,
                'channel' => $channel ?: $share->channel,
                'share_url' => $share_url ?: $share->share_url
            ]);
        } else {
            $share_code = Random::alnum(16);
            $share = ShareModel::create([
                'user_id' => $user_id,
                'goods_id' => $goods_id,
                'share_num' => 1,
                'share_code' => $share_code,
                'share_url' => $share_url,
                'share_type' => $share_type,
                'channel' => $channel,
                'last_share_time' => time(),
                'status' => 1
            ]);
        }

        $this->success('Success', $share);
    }

    /**
     * 分享记录列表
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

        $list = ShareModel::with(['goods'])
            ->where(['user_id' => $user_id, 'status' => 1])
            ->order('last_share_time', 'desc')
            ->page($page, $limit)
            ->select();

        $total = ShareModel::where(['user_id' => $user_id, 'status' => 1])->count();

        $this->success('', [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ]);
    }
}
