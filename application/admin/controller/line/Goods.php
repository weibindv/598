<?php

namespace app\admin\controller\line;

use app\common\controller\Backend;

/**
 * 商品同步管理
 *
 * @icon fa fa-circle-o
 */
class Goods extends Backend
{

    /**
     * Goods模型对象
     * @var \app\admin\model\line\Goods
     */
    protected $model = null;

    protected $merchant_id = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\line\Goods;

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

        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("stockStatusList", $this->model->getStockStatusList());
        $this->view->assign("shareStatusList", $this->model->getShareStatusList());
    }



    /**
     * 查看
     */
    public function index()
    {
        // 设置过滤方法
        $this->request->filter(['strip_tags', 'htmlspecialchars']);
        if ($this->request->isAjax()) {
            // 设置内存限制防止大数据量时内存溢出
            ini_set('memory_limit', '512M');

            // 获取分页、排序、筛选参数
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            // 限制最大拉取数量，防止内存溢出。默认 10，最高 500
            $limit = ($limit && $limit > 0) ? (int)$limit : 10;
            $limit = $limit > 500 ? 500 : $limit;

            // 查询总记录数
            $total = $this->model
                ->where($where)
                ->where(function($query) {
                    if ($this->merchant_id) {
                        $query->where('merchant_id', $this->merchant_id);
                    }
                })
                ->count();

            // 查询当前页数据，只查询列表需要的字段以节省内存
            $list = $this->model
                ->where($where)
                ->where(function($query) {
                    if ($this->merchant_id) {
                        $query->where('merchant_id', $this->merchant_id);
                    }
                })
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->field('id,title,image,mall_price,merchant_price,stock_status,share_status,weigh,createtime,merchant_id')
                ->select();

            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }

    public function share()
    {
        $id = $this->request->get('ids');
        $status = $this->request->get('status');
        if ($id) {
            $row = $this->model->get($id);
            if (!$row) {
                $this->error(__('No Results were found'));
            }
            $row->share_status = $status;
            if ($row->save()) {
                $this->success(__('Operation completed'));
            }
        }
        $this->error(__('Parameter Error'));
    }

    public function fetch_and_sync()
    {
        $offset = $this->request->param('offset', 0);
        $limit = $this->request->param('limit', 300);
        $url = "https://myg.sxlingchuangkeji.com/dNcuxKPDUj.php/shop_goods/get_goods_list?sort=weigh&order=desc&offset={$offset}&limit={$limit}";
        
        if ($this->merchant_id) {
            $url .= "&merchant_id=" . $this->merchant_id;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_ENCODING, ''); // 启用压缩，显著提高大数据量获取速度
        $output = curl_exec($ch);

        if ($output === false) {
            $error = curl_error($ch);
            curl_close($ch);
            $this->error("远程数据获取失败: " . $error);
        }
        curl_close($ch);

        $res = json_decode($output, true);
        if (!$res || !isset($res['rows'])) {
            $this->error("远程数据解析失败或格式错误");
        }

        $titles = [];
        $remote_url = "https://myg.sxlingchuangkeji.com";
        foreach ($res['rows'] as $item) {
            $titles[] = $item['title'];
            $id = $item['id'];

            // 处理图片路径，补全域名
            $image_fields = ['image', 'image2', 'image3', 'image4', 'image5', 'image6', 'image7', 'image8', 'image9', 'image10', 'images', 'imagess'];
            foreach ($image_fields as $field) {
                if (isset($item[$field]) && $item[$field] && strpos($item[$field], '/uploads') === 0) {
                    if ($field == 'images' || $field == 'imagess') {
                        // 处理逗号分隔的多图
                        $imgs = explode(',', $item[$field]);
                        foreach ($imgs as &$img) {
                            if (strpos($img, '/uploads') === 0)
                                $img = $remote_url . $img;
                        }
                        $item[$field] = implode(',', $imgs);
                    } else {
                        $item[$field] = $remote_url . $item[$field];
                    }
                }
            }

            // 处理分类和店铺信息
            if (isset($item['category']['name'])) {
                $item['category_name'] = $item['category']['name'];
            }
            if (isset($item['shop']['shopname'])) {
                $item['shop_shopname'] = $item['shop']['shopname'];
            }
            if (isset($item['shop']['id'])) {
                $item['merchant_id'] = $item['shop']['id'];
            }

            // 移除嵌套对象和不需要的字段
            unset(
                $item['category'],
                $item['shop'],
                $item['category_type_text'],
                $item['category_flag_text'],
                $item['category_status_text'],
                $item['shop_state_text'],
                $item['shop_status_text'],
                $item['flag_text'],
                $item['stock_text'],
                $item['specs_text'],
                $item['distribution_text'],
                $item['activity_text'],
                $item['status_text']
            );

            // 过滤并清理数据
            $item['zhu_id'] = !empty($item['zhu_id']) ? $item['zhu_id'] : 0;
            $item['base_number'] = !empty($item['base_number']) ? $item['base_number'] : 0;
            $item['sort'] = !empty($item['sort']) ? $item['sort'] : 0;
            $item['stock'] = !empty($item['stock']) ? $item['stock'] : 0;
            $item['sales'] = !empty($item['sales']) ? $item['sales'] : 0;

            $row = $this->model->where('id', $id)->find();

            // 新商品默认未分享
            if (!$row) {
                $item['share_status'] = '0';
            }

            if ($row) {
                // 价格逻辑：商城价格使用拉取过来的 price
                $item['mall_price'] = $item['price'];
                if ($this->merchant_id) {
                    $item['merchant_id'] = $this->merchant_id;
                }

                // 商家价格逻辑：如果本地已存在且不为空/0，则不改变；否则用 price 填充
                if (!empty($row->merchant_price) && floatval($row->merchant_price) > 0) {
                    unset($item['merchant_price']);
                } else {
                    $item['merchant_price'] = $item['price'];
                }

                $row->allowField(true)->save($item);
            } else {
                // 新商品逻辑
                $item['mall_price'] = $item['price'];
                if ($this->merchant_id) {
                    $item['merchant_id'] = $this->merchant_id;
                }
                if (empty($item['merchant_price']) || floatval($item['merchant_price']) <= 0) {
                    $item['merchant_price'] = $item['price'];
                }
                // 使用新实例进行保存，避免模型实例复用导致的冲突
                $newRow = new \app\admin\model\line\Goods;
                $newRow->allowField(true)->save($item);
            }
        }

        $this->success("同步中", null, ['total' => $res['total'], 'titles' => $titles, 'count' => count($res['rows'])]);
    }

    public function sync_data()
    {
        // Keep this for manual sync if needed
        if ($this->request->isPost()) {
            $data = $this->request->post('data/a');
            if ($data) {
                foreach ($data as $item) {
                    $id = $item['id'];
                    $row = $this->model->where('id', $id)->find();
                    if ($row) {
                        $row->save($item);
                    } else {
                        $this->model->save($item);
                    }
                }
                $this->success("同步成功");
            }
        }
        $this->error("无效的请求");
    }
    public function detail($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }

        $stockStatusList = $this->model->getStockStatusList();
        $shareStatusList = $this->model->getShareStatusList();

        $stock_status = $row['stock_status'] ?? '';
        $share_status = $row['share_status'] ?? '';

        $row['stock_status_text'] = isset($stockStatusList[$stock_status]) ? $stockStatusList[$stock_status] : '';
        $row['share_status_text'] = isset($shareStatusList[$share_status]) ? $shareStatusList[$share_status] : '';

        $this->view->assign("row", $row);
        return $this->view->fetch();
    }

}
