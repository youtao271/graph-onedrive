<?php

namespace App\Http\Requests;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Stream;
use Microsoft\Graph\Exception\GraphException;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model;
use Illuminate\Support\Facades\Cache;
use App\Http\Tokens\GraphToken;
use stdClass;
use GuzzleHttp\Exception\RequestException;

class GraphRequest
{
    private $graph;
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
            Cache::put('user', $user);
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

        Cache::put('/', $data);

        return $data;
    }

    private function getFileItems($id)
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
            $guzzle->get(config('app.url').'/refresh')->getStatusCode();

            $ret = ['code'=>$status, 'msg'=>'创建文件夹成功'];
        } catch (RequestException $e) {
            report($e);
            $code = $e->getCode();
            preg_match('/\"message\": \"(.*?)\",/', $e->getMessage(), $match);
            $message = $code===409 ? '文件夹重名，请修改后重试！' : $match[1];
            $ret = ['code'=>$code, 'msg'=>$message];
        }
        return $ret;
    }

    public function deleteItem($id){
        try {
            $status = $this->graph->createRequest("DELETE", "/me/drive/items/{$id}")->execute()->getStatus();

            $guzzle = new Client();
            $guzzle->get(config('app.url').'/refresh')->getStatusCode();

            $ret = ['code'=>$status, 'msg'=>'删除项目成功'];
        } catch (RequestException $e) {
            report($e);
            $code = $e->getCode();
            $message = $e->getMessage();
            $ret = ['code'=>$code, 'msg'=>$message];
        }
        return $ret;
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

    public function webhooks($sub)
    {
        $subResult = $this->graph->createRequest("POST", "/subscriptions")
            ->attachBody($sub)
            ->setReturnType(Model\Subscription::class)
            ->execute();
        return $subResult->getResource();
    }
}
