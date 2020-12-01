<?php

namespace App\Http\Requests;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Stream;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model;
use Illuminate\Support\Facades\Cache;
use App\Http\Tokens\GraphToken;
use stdClass;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ClientException;
use Microsoft\Graph\Exception\GraphException;

class GraphRequest
{
    private Graph $graph;
    private $token;

    public function __construct()
    {
        $this->graph = new Graph;
        $this->token = $this->getToken();
        $this->graph->setAccessToken($this->token);
    }

    public function getToken()
    {
        $accessToken = Cache::get('accessToken');
        return $accessToken->hasExpired() ?
            $this->refreshToken($accessToken->getRefreshToken()) :
            $accessToken->getToken();
    }

    public function refreshToken($refreshToken)
    {
        $graphToken = new GraphToken;
        return $graphToken->refreshAccessToken($refreshToken);
    }

    public function getUserInfo()
    {
        $user = Cache::get('user');
        if (!$user) {
            $user = $this->graph->createRequest('GET', '/me')
                ->setReturnType(Model\User::class)
                ->execute();
            Cache::forever('user', $user);
        }
        return $user;
    }

    public function getFiles()
    {
        $files = $this->graph->createCollectionRequest("GET", '/me/drive/root/children?$select=id,name,folder,fileSystemInfo,size')
            ->setReturnType(Model\DriveItem::class)
            ->execute();
        if (empty($files)) return '';

        $stack = ['/:/'];
        $path = '/';
        $data = [];
        while ($key = array_shift($stack)) {
            [$name, $id] = [...explode(':', $key), ''];
            if ($name !== '/') $path .= $path === '/' ? $name : '/' . $name;
            $files = $files ? $files : $this->getFileItems($id);
            foreach ($files as $file) {
                $tmp = [
                    'id' => $file->getId(),
                    'pid' => $id === '/' ? 0 : $id,
                    'name' => $file->getName(),
                    'folder' => $file->getFolder() ? $file->getFolder()->getChildCount() : 0,
                    'ctime' => $file->getFileSystemInfo()->getCreatedDateTime()->format('Y-m-d H:i:s'),
                    'mtime' => $file->getFileSystemInfo()->getLastModifiedDateTime()->format('Y-m-d H:i:s'),
                    'size' => $file->getSize(),
                ];
                if ($file->getFolder()) {
                    $tmp['folder'] = true;
                    $tmp['children'] = $file->getFolder()->getChildCount();
                }
                $data[] = $tmp;
                if ($tmp['folder']) array_push($stack, $tmp['name'] . ':' . $tmp['id']);
            }
            $files = null;
        }

        Cache::forever('/', $data);

        return $data;
    }

    public function storeFile($id='root', $flag=false)
    {
        $files = $this->getFileItems($id);
        $data = [];
        foreach ($files as $file) {
            $tmp = [
                'id' => $file->getId(),
                'pid' => $id === '/' ? 0 : $id,
                'name' => $file->getName(),
                'folder' => $file->getFolder() ? $file->getFolder()->getChildCount() : 0,
                'ctime' => $file->getFileSystemInfo()->getCreatedDateTime()->format('Y-m-d H:i:s'),
                'mtime' => $file->getFileSystemInfo()->getLastModifiedDateTime()->format('Y-m-d H:i:s'),
                'size' => $file->getSize(),
            ];
            if ($file->getFolder()) {
                $tmp['folder'] = true;
                $tmp['children'] = $file->getFolder()->getChildCount();
                var_dump($tmp['name']. '-----' .$tmp['id']. '-----' .$tmp['children']);
                // ob_flush();
                // flush();
                if($tmp['children'] && $flag) {
                    $this->storeFile($tmp['id'], $flag);
                }
            }
            array_push($data, $tmp);
        }
        Cache::forever($id, $data);
    }

    public function getFileItems($id='root')
    {
        $items = $this->graph->createCollectionRequest("GET", "/drives/me/items/{$id}/children")
            ->setReturnType(Model\DriveItem::class)
            ->execute();
        return $items;
    }

    public function getFileContent($id)
    {
        return $this->graph->createRequest("GET", "/me/drive/items/{$id}/content")
            ->setReturnType(Stream::class)
            ->execute()->getContents();
    }

    public function downloadFile($id)
    {
        $info = $this->graph->createRequest("GET", "/me/drive/items/{$id}/?\$select=@microsoft.graph.downloadUrl")
            ->setReturnType(Model\DriveItem::class)
            ->execute();
        $file = (array)$info;
        return array_pop($file);
    }

    public function getFileUrl($id)
    {
        $info = $this->graph->createRequest("GET", "/me/drive/items/{$id}/?\$select=@microsoft.graph.downloadUrl")
            ->setReturnType(Model\DriveItem::class)
            ->execute();
        $file = (array)$info;
        return array_pop($file)['@microsoft.graph.downloadUrl'];
    }

    public function createDirectory($id, $name)
    {
        $data = [
            "name" => $name,
            "folder" => new StdClass(),
        ];
        try {
            $status = $this->graph->createRequest("POST", "/me/drive/items/{$id}/children")
                ->attachBody($data)
                ->execute()->getStatus();

            $guzzle = new Client();
            $guzzle->get(config('app.url').'/refresh');

            $ret = ['code'=>$status, 'msg'=>'创建文件夹成功'];
        } catch (RequestException $e) {
            report($e);
            $code = $e->getCode();
            preg_match('/\"message\": \"(.*?)\",/', $e->getMessage(), $match);
            $message = $code===409 ? '文件夹重名，请修改后重试！' : $match[1];
            $ret = ['code'=>$code, 'msg'=>$message];
        } catch (GraphException $e) {
            // Todo
        }
        return $ret;
    }

    private function deleteCache($item){
        Cache::forget($item['id']);
        $parent = Cache::get($item['pid']);
        foreach ($parent as $key => $val){
            if($val['id'] === $item['id']) {
                unset($parent[$key]);
                break;
            }
        }
        Cache::forever($item['pid'], $parent);
    }

    public function deleteItem($item){
        try {
            $status = $this->graph->createRequest("DELETE", "/me/drive/items/{$item['id']}")->execute()->getStatus();
            $msg = '删除失败，请稍后重试！';
            if($status === 204){
                $msg = '删除文件或文件夹成功!';
                $this->deleteCache($item);
            }
            $ret = ['code'=>$status, 'msg'=>$msg];
        } catch (RequestException $e) {
            report($e);
            $code = $e->getCode();
            $message = $e->getMessage();
            $ret = ['code'=>$code, 'msg'=>$message];
        } catch (GraphException $e) {
            // Todo
            report($e);
            $code = $e->getCode();
            $message = $e->getMessage();
            $ret = ['code'=>$code, 'msg'=>$message];
        }
        return $ret;
    }

    public function getUploadScript($id, $name, $size){
        try {
            return $this->graph->createRequest("POST", "/me/drive/items/{$id}:/{$name}:/createUploadSession")
                ->addHeaders(["Content-Type" => "application/json"])
                ->attachBody([
                        "@microsoft.graph.conflictBehavior" => "rename",
                        "description"    => 'File description here',
                        "name"    => $name,
                        "fileSize"    => $size,
                        // "DeferCommit" => true
                ])
                ->setReturnType(Model\UploadSession::class)
                ->execute()->getUploadUrl();
        } catch (RequestException $e) {
            report($e);
            $code = $e->getCode();
            $message = $e->getMessage();
            $ret = ['code'=>$code, 'msg'=>$message];
        } catch (GraphException $e) {

        }
        return $ret;
    }

    //创建订阅
    public function subscribe(){
        try {
            return $this->graph->createRequest("POST", "/subscriptions")
                ->addHeaders(["Content-Type" => "application/json"])
                ->attachBody([
                    'changeType' => 'updated',
                    'notificationUrl' => 'https://pan.9dutv.com/notify',
                    'resource' => '/drives/me/root',
                    'expirationDateTime' => '2020-12-30T18:23:45.9356913Z',
                    "clientState" => "secretClientValue",
                    "latestSupportedTlsVersion" => "v1_2"
                ])
                ->setReturnType(Model\Subscription::class)
                ->execute();
        } catch (GraphException $e) {
        }
    }
    //续订
    public function resubscribe($id, $date){
        try {
            return $this->graph->createRequest("PATCH", "/subscriptions/{$id}")
                ->setReturnType(Model\Subscription::class)
                ->attachBody([
                    'expirationDateTime' => $date,
                ])
                ->execute();
        } catch (ClientException $e) {
            $res = $e->getResponse()->getBody()->getContents();
            $res = json_decode($res, true);
            $message = $res['error']['message'];
            return response($message, $e->getCode());
        } catch (GraphException $e) {
        }
    }

    //获取订阅列表
    public function getSubscriptions(){
        try {
            return $this->graph->createRequest("GET", "/subscriptions")
                ->setReturnType(Model\Subscription::class)
                ->execute();
        } catch (GraphException $e) {

        }
    }

    //获取订阅详情
    public function getSubscriptionInfo($id){
        try {
            return $this->graph->createRequest("GET", "/subscriptions/{$id}")
                ->setReturnType(Model\Subscription::class)
                ->execute();
        } catch (GraphException $e) {
        }
    }

    public function sendMail()
    {
        $mailBody = array("Message" => array(
            "subject" => "Test Email",
            "body" => array(
                "contentType" => "html",
                "content" => 'DUMMY_EMAIL'
            ),
            "from" => array(
                "emailAddress" => array(
                    "name" => 'mcgrady',
                )
            ),
            "toRecipients" => array(
                array(
                    "emailAddress" => array(
                        "name" => 'mcgrady',
                        "address" => 'youtao271@163.com'

                    )
                )
            )
        ));

        try {
            return $this->graph->createRequest("POST", "/me/sendMail")
                ->attachBody($mailBody)
                ->execute();
        } catch (League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
            var_dump($e->getMessage());
        }
    }
}
