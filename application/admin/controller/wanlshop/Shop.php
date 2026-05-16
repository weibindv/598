<?php

namespace app\admin\controller\wanlshop;

use app\common\controller\Backend;
use think\Db;
use fast\Random;

/**
 * 商家管理
 *
 * @icon fa fa-circle-o
 */
class Shop extends Backend
{

    protected $noNeedLogin = ['index'];
    protected $noNeedRight = ['index'];

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
     * 查看 (公开接口)
     */
    public function index()
    {
        // 设置过滤方法
        $this->request->filter(['strip_tags', 'htmlspecialchars']);
        if ($this->request->isAjax() || $this->request->param('addtabs') || $this->request->param('format') == 'json') {
            // 如果不是AJAX请求但带了addtabs参数(模拟FastAdmin表格加载)，或者显式要求json
            
            // 获取分页、排序、筛选参数
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $total = $this->model
                ->where($where)
                ->count();

            $list = $this->model
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 同步远程商家数据
     */
    public function fetch_and_sync()
    {
        $offset = $this->request->param('offset', 0);
        $limit = $this->request->param('limit', 100);
        
        // 远程接口地址
        $url = "https://myg.sxlingchuangkeji.com/dNcuxKPDUj.php/wanlshop/shop/index?sort=weigh&order=desc&offset={$offset}&limit={$limit}";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        // 模拟 AJAX 请求，有时远程接口需要这个头才返回 JSON
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-Requested-With: XMLHttpRequest'
        ]);
        
        $output = curl_exec($ch);
        curl_close($ch);

        $res = json_decode($output, true);
        
        if (!$res || !isset($res['rows'])) {
            // 如果失败了，尝试不带头再试一次 (有些配置可能相反)
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $output = curl_exec($ch);
            curl_close($ch);
            $res = json_decode($output, true);
        }

        if (!$res || !isset($res['rows'])) {
            $this->error("远程数据获取失败或需要登录");
        }

        $names = [];
        foreach ($res['rows'] as $item) {
            $names[] = $item['shopname'] ?? ($item['name'] ?? 'Unknown');
            
            // 尝试匹配本地字段
            $data = [
                'name'           => $item['shopname'] ?? ($item['name'] ?? ''),
                'address'        => $item['city'] ?? ($item['address'] ?? ''),
                'contact_person' => $item['contact_person'] ?? '',
                'contact_phone'  => $item['mobile'] ?? ($item['contact_phone'] ?? ''),
                'status'         => (isset($item['status']) && $item['status'] == 'normal') ? 1 : 1, // 默认正常
            ];

            // 使用 shopname 或 id 作为唯一标识尝试查找
            $remote_id = $item['id'];
            $merchant_id = 'M' . $remote_id; // 或者使用原始 ID

            $row = $this->model->where('name', $data['name'])->find();
            if ($row) {
                $row->save($data);
            } else {
                $data['merchant_id'] = 'M' . date('YmdHis') . Random::alnum(6);
                $this->model->create($data);
            }
        }

        $this->success("同步完成", null, ['total' => $res['total'], 'names' => $names, 'count' => count($res['rows'])]);
    }
}
