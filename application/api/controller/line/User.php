<?php

namespace app\api\controller\line;

use app\common\controller\Api;
use app\admin\model\line\LineUser;
use app\admin\model\line\User as UserModel;
use app\admin\model\line\Merchant as MerchantModel;
use think\Db;

/**
 * LINE用户接口
 */
class User extends Api
{
    protected $noNeedLogin = ['login', 'mockLogin', 'emailLogin', 'emailRegister', 'webhook', 'loginCallback'];
    protected $noNeedRight = '*';

    // LINE Channel Credentials
    protected $channelId = '2010062669';
    protected $channelSecret = '3a3ecc4f63561f378ba567511862c518';

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 获取用户信息
     * @ApiMethod (GET)
     * @ApiHeaders (name="Authorization", type="string", required=true, description="Bearer Token")
     */
    public function getUserProfile()
    {
        // 获取当前登录用户（Auth 类已自动验证 token）
        $user = $this->auth->getUser();
        if (!$user) {
            $this->error('User not logged in');
        }

        // 查询本地 LineUser 记录
        $lineUser = LineUser::where('user_id', $user->id)->find();

        // 增加 need_profile 逻辑：如果昵称、手机号或门店ID为空，则认为资料不完善
        $need_profile = false;
        if (empty($user->nickname) || empty($user->mobile) || empty($user->merchant_id)) {
            $need_profile = true;
        }

        $this->success('Get user profile success', [
            'user' => $user,                // 系统用户信息
            'line_user' => $lineUser,        // LineUser 记录
            'need_profile' => $need_profile  // 是否需要完善资料
        ]);
    }

    /**
     * 获取门店列表 (用于前端下拉选择)
     * @ApiMethod (GET)
     */
    public function getMerchantList()
    {
        $list = MerchantModel::where('status', 1)
            ->field('id,merchant_id,name,address')
            ->order('id', 'asc')
            ->select();
        $this->success('', $list);
    }

    /**
     * 完善个人资料并绑定门店
     * @ApiMethod (POST)
     * @ApiParams (name="nickname", type="string", required=true, description="姓名")
     * @ApiParams (name="mobile", type="string", required=true, description="手机号码")
     * @ApiParams (name="email", type="string", required=false, description="邮箱")
     * @ApiParams (name="merchant_id", type="string", required=true, description="门店ID(merchant_id)")
     */
    public function completeProfile()
    {
        $user = $this->auth->getUser();
        if (!$user) {
            $this->error('User not logged in');
        }

        $nickname = $this->request->post('nickname');
        $mobile = $this->request->post('mobile');
        $email = $this->request->post('email', '');
        $merchant_id = $this->request->post('merchant_id');

        if (empty($nickname) || empty($mobile) || empty($merchant_id)) {
            $this->error('姓名、手机号和门店必填');
        }

        Db::startTrans();
        try {
            // 1. 更新系统用户表 (fa_user)
            \app\common\model\User::where('id', $user->id)->update([
                'nickname' => $nickname,
                'mobile' => $mobile,
                'email' => $email,
                'merchant_id' => $merchant_id
            ]);

            // 2. 更新 LineUser 表（如果存在记录，同步更新昵称和门店）
            $lineUser = LineUser::where('user_id', $user->id)->find();
            if ($lineUser) {
                $lineUser->allowField(true)->save([
                    'line_display_name' => $nickname,
                    'merchant_id' => $merchant_id
                ]);
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error('保存失败：' . $e->getMessage());
        }

        $this->success('资料完善成功');
    }

    /**
     * 更新用户头像和昵称
     * @ApiMethod (POST)
     * @ApiParams (name="nickname", type="string", required=false, description="用户昵称")
     * @ApiParams (name="avatar", type="string", required=false, description="用户头像URL")
     */
    public function updateProfile()
    {
        $user = $this->auth->getUser();
        if (!$user) {
            $this->error('User not logged in');
        }

        $nickname = $this->request->post('nickname', '');
        $avatar = $this->request->post('avatar', '');

        if (empty($nickname) && empty($avatar)) {
            $this->error('No fields to update');
        }

        Db::startTrans();
        try {
            // 更新系统用户表
            $updateData = [];
            if (!empty($nickname)) {
                $updateData['nickname'] = $nickname;
            }
            if (!empty($avatar)) {
                $updateData['avatar'] = $avatar;
            }
            if (!empty($updateData)) {
                \app\common\model\User::where('id', $user->id)->update($updateData);
            }

            // 更新 LineUser 表（使用 allowField 自动过滤不存在的字段）
            $lineUser = LineUser::where('user_id', $user->id)->find();
            if ($lineUser) {
                $lineUpdateData = [];
                if (!empty($nickname)) {
                    $lineUpdateData['name'] = $nickname;
                }
                if (!empty($avatar)) {
                    $lineUpdateData['avatar'] = $avatar;
                }
                if (!empty($lineUpdateData)) {
                    $lineUser->allowField(true)->save($lineUpdateData);
                }
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error('Update failed: ' . $e->getMessage());
        }

        // 返回更新后的用户信息
        $updatedUser = \app\common\model\User::where('id', $user->id)->find();
        $updatedLineUser = LineUser::where('user_id', $user->id)->find();

        $this->success('Update success', [
            'user' => $updatedUser,
            'line_user' => $updatedLineUser
        ]);
    }

    /**
     * 邮箱登录
     */
    public function emailLogin()
    {
        $email = $this->request->post('email');
        $password = $this->request->post('password');
        if (!$email || !$password) {
            $this->error(__('Invalid parameters'));
        }
        $ret = $this->auth->login($email, $password);
        if ($ret) {
            $user = $this->auth->getUser();
            // 更新 LineUser 的登录时间
            LineUser::where('user_id', $user->id)->update(['last_login_time' => time()]);

            $data = [
                'userinfo' => $this->auth->getUserinfo(),
                'line_user' => LineUser::where('user_id', $user->id)->find()
            ];
            $this->success(__('Logged in successful'), $data);
        } else {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 邮箱注册
     */
    public function emailRegister()
    {
        $email = $this->request->post('email');
        $password = $this->request->post('password');
        $nickname = $this->request->post('nickname', '');
        if (!$email || !$password) {
            $this->error(__('Invalid parameters'));
        }

        Db::startTrans();
        try {
            // 1. 注册系统用户
            $username = $email;
            $ret = $this->auth->register($username, $password, $email, '', ['nickname' => $nickname]);
            if (!$ret) {
                throw new \Exception($this->auth->getError());
            }
            $user = $this->auth->getUser();

            // 2. 同步创建 LineUser 记录
            $time = time();
            $lineUser = LineUser::create([
                'user_id' => $user->id,
                'email' => $email,
                'name' => $nickname,
                'status' => 1,
                'register_time' => $time,
                'last_login_time' => $time,
                'line_status' => 0, // 初始未绑定 LINE
            ]);

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }

        $data = [
            'userinfo' => $this->auth->getUserinfo(),
            'line_user' => $lineUser
        ];
        $this->success(__('Sign up successful'), $data);
    }

    /**
     * LINE登录/自动注册
     */
    public function login()
    {
        $accessToken = $this->request->post('accessToken');
        if (empty($accessToken)) {
            $this->error('Token missing');
        }

        $lineData = $this->getLineProfile($accessToken);
        $lineUserId = $lineData['userId'];
        $displayName = $lineData['displayName'] ?? '';
        $pictureUrl = $lineData['pictureUrl'] ?? '';

        $result = $this->doLoginSync($lineUserId, $displayName, $pictureUrl);
        $this->success('Login success', $result);
    }

    /**
     * 检查当前用户是否已绑定 LINE
     */
    public function checkBind()
    {
        $user = $this->auth->getUser();
        $lineUser = LineUser::where('user_id', $user->id)->find();

        $this->success('', [
            'is_bind' => $lineUser ? true : false,
            'line_user' => $lineUser
        ]);
    }

    /**
     * 绑定 LINE 账号
     * @ApiParams (name="accessToken", type="string", required=true, description="LINE Access Token")
     */
    public function bind()
    {
        $accessToken = $this->request->post('accessToken');
        if (empty($accessToken)) {
            $this->error('Token missing');
        }

        $lineData = $this->getLineProfile($accessToken);
        $lineUserId = $lineData['userId'];
        $displayName = $lineData['displayName'] ?? '';
        $pictureUrl = $lineData['pictureUrl'] ?? '';

        $user = $this->auth->getUser();

        // 检查该 LINE 账号是否已被其它账号绑定
        $existLine = LineUser::where('line_user_id', $lineUserId)->find();
        if ($existLine && $existLine->getUserId() > 0 && $existLine->getUserId() != $user->id) {
            $this->error('This LINE account has been bound to another user');
        }

        Db::startTrans();
        try {
            if (!$existLine) {
                $existLine = LineUser::create([
                    'line_user_id' => $lineUserId,
                    'user_id' => $user->id,
                    'line_display_name' => $displayName,
                    'line_picture_url' => $pictureUrl,
                    'line_status' => 1,
                    'status' => 1,
                    'register_time' => time(),
                ]);
            } else {
                $existLine->save([
                    'user_id' => $user->id,
                    'line_display_name' => $displayName,
                    'line_picture_url' => $pictureUrl,
                    'line_status' => 1,
                ]);
            }

            // 更新当前系统用户的头像和昵称
            $user->save([
                'nickname' => $displayName,
                'avatar' => $pictureUrl
            ]);

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error('Bind failed: ' . $e->getMessage());
        }

        $this->success('Bind success');
    }

    /**
     * 获取 LINE 用户信息
     */
    private function getLineProfile($accessToken)
    {
        // 1. 验证 Token
        $verifyUrl = "https://api.line.me/oauth2/v2.1/verify?access_token=" . $accessToken;
        $verifyResponse = $this->curlGet($verifyUrl);
        $verifyData = json_decode($verifyResponse, true);

        if (isset($verifyData['error'])) {
            $this->error('Token verification failed: ' . ($verifyData['error_description'] ?? $verifyData['error']));
        }

        // if (!isset($verifyData['client_id']) || $verifyData['client_id'] != $this->channelId) {
        //     $this->error('Invalid Access Token or Client ID mismatch');
        // }

        // 2. 请求用户信息
        $profileUrl = "https://api.line.me/v2/profile";
        $profileResponse = $this->curlGet($profileUrl, ["Authorization: Bearer $accessToken"]);
        $userProfile = json_decode($profileResponse, true);

        if (isset($userProfile['error'])) {
            $this->error('Failed to get user profile: ' . ($userProfile['error_description'] ?? $userProfile['error']));
        }

        if (!isset($userProfile['userId'])) {
            $this->error('Failed to get user profile: userId missing');
        }

        return $userProfile;
    }

    /**
     * 模拟测试登录
     */
    public function mockLogin()
    {
        $lineUserId = $this->request->post('userId', 'mock_user_123');
        $displayName = $this->request->post('displayName', 'Mock User');
        $pictureUrl = $this->request->post('pictureUrl', '');

        $result = $this->doLoginSync($lineUserId, $displayName, $pictureUrl);
        $this->success('Mock login success', $result);
    }

    /**
     * LINE OAuth2 回调登录
     */
    public function loginCallback()
    {
        $code = $this->request->post('code');
        $state = $this->request->post('state');

        if (!$code) {
            $this->error('Authorization failed or user cancelled');
        }

        // 1. 换取 Access Token
        $tokenUrl = "https://api.line.me/oauth2/v2.1/token";
        
        // 【重要】这里的 redirectUri 必须与您发起授权时的地址完全一致
        // 根据您的 URL，应该是 H5 的首页地址
        $redirectUri = 'https://400line.new.lingchuang.co/h5/'; 

        $postData = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $this->channelId,
            'client_secret' => $this->channelSecret,
        ];

        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

        $response = curl_exec($ch);
        $tokenData = json_decode($response, true);
        curl_close($ch);

        if (!isset($tokenData['access_token'])) {
            // 打印出详细错误以便排查
            $errorMsg = $tokenData['error_description'] ?? ($tokenData['error'] ?? 'Unknown error');
            \think\Log::error('LINE Token Exchange Error: ' . json_encode($tokenData));
            $this->error('Token exchange failed: ' . $errorMsg);
        }

        // 2. 获取并同步用户信息
        $lineData = $this->getLineProfile($tokenData['access_token']);
        $lineUserId = $lineData['userId'];
        $displayName = $lineData['displayName'] ?? '';
        $pictureUrl = $lineData['pictureUrl'] ?? '';

        $result = $this->doLoginSync($lineUserId, $displayName, $pictureUrl);

        $this->success('Login success', $result);
    }

    /**
     * 执行登录同步逻辑
     */
    private function doLoginSync($lineUserId, $displayName, $pictureUrl)
    {
        Db::startTrans();
        try {
            // 1. 查找 LineUser 表中是否已记录该 LINE 账号
            $lineUser = LineUser::where('line_user_id', $lineUserId)->find();
            $time = time();

            if (!$lineUser) {
                // 如果没有记录，创建 LineUser
                $lineUser = LineUser::create([
                    'line_user_id' => $lineUserId,
                    'line_display_name' => $displayName,
                    'line_picture_url' => $pictureUrl,
                    'user_id' => 0, // 初始关联ID为0
                    'line_status' => 1,
                    'status' => 1,
                    'register_time' => $time,
                    'last_login_time' => $time,
                ]);
            } else {
                // 更新现有记录的头像和昵称
                $lineUser->save([
                    'line_display_name' => $displayName,
                    'line_picture_url' => $pictureUrl,
                    'last_login_time' => $time,
                ]);
            }

            // 2. 关联系统用户
            $user = null;
            $currentBoundUserId = $lineUser->getUserId();
            if ($currentBoundUserId > 0) {
                $user = \app\common\model\User::get($currentBoundUserId);
            }

            if (!$user) {
                // 尝试通过用户名查找或创建
                $username = 'line_' . $lineUserId;
                $user = \app\common\model\User::where('username', $username)->find();

                if (!$user) {
                    // 注册新系统用户
                    $ret = $this->auth->register($username, \fast\Random::alnum(), '', '', [
                        'nickname' => $displayName,
                        'avatar' => $pictureUrl,
                    ]);
                    if (!$ret) {
                        throw new \Exception($this->auth->getError());
                    }
                    $user = $this->auth->getUser();
                } else {
                    $this->auth->direct($user->id);
                }

                // 更新 LineUser 关联 ID
                $lineUser->save(['user_id' => $user->id]);
            } else {
                // 已存在关联用户，执行直接登录
                $this->auth->direct($user->id);
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error('Login failed: ' . $e->getMessage());
        }

        return [
            'userinfo' => $this->auth->getUserinfo(),
            'line_user' => $lineUser
        ];
    }

    /**
     * LINE Webhook 回调
     */
    public function webhook()
    {
        $signature = $this->request->header('x-line-signature');
        $body = $this->request->getInput();

        if (empty($signature) || !$this->verifySignature($body, $signature)) {
            $this->error('Invalid signature');
        }

        $payload = json_decode($body, true);
        $events = $payload['events'] ?? [];

        foreach ($events as $event) {
            $this->handleEvent($event);
        }

        return json(['status' => 'ok']);
    }

    /**
     * 处理 Webhook 事件
     */
    private function handleEvent($event)
    {
        $type = $event['type'] ?? '';
        $lineUserId = $event['source']['userId'] ?? '';

        switch ($type) {
            case 'follow':
                // 关注事件：可以在这里自动同步用户信息或发送欢迎语
                // 这里我们仅记录或执行静默同步（如果尚未存在）
                $this->doLoginSync($lineUserId, 'LINE User', '');
                break;

            case 'message':
                // 消息事件：处理用户发送的消息
                $messageText = $event['message']['text'] ?? '';
                // TODO: 可以在这里对接 AI 或 关键词回复
                break;

            case 'unfollow':
                // 取关事件
                LineUser::where('line_user_id', $lineUserId)->update(['line_status' => 0]);
                break;
        }
    }

    /**
     * 验证 LINE 签名
     */
    private function verifySignature($body, $signature)
    {
        $hash = hash_hmac('sha256', $body, $this->channelSecret, true);
        $expectedSignature = base64_encode($hash);
        return hash_equals($expectedSignature, $signature);
    }

    private function curlGet($url, $headers = [])
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }
}
