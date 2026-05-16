<?php

namespace app\api\controller\line;

use app\common\controller\Api;
use app\common\library\Upload;
use app\common\exception\UploadException;
use think\Config;

/**
 * 公共接口
 */
class Common extends Api
{
    protected $noNeedLogin = ['upload']; // 设置为公共接口，不需要登录即可上传
    protected $noNeedRight = '*';

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 上传文件
     * @ApiMethod (POST)
     * @ApiParams (name="file", type="file", required=true, description="文件流")
     */
    public function upload()
    {
        Config::set('default_return_type', 'json');
        $file = $this->request->file('file');
        if (!$file) {
            $this->error(__('No file upload or exceed maximum file size'));
        }

        try {
            $upload = new Upload($file);
            $attachment = $upload->upload();
        } catch (UploadException $e) {
            $this->error($e->getMessage());
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }

        $this->success(__('Uploaded successful'), [
            'url' => $attachment->url,
            'fullurl' => cdnurl($attachment->url, true)
        ]);
    }
}
