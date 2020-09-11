<?php

namespace App\Http\Requests;

use Microsoft\Graph\Graph;
use Microsoft\Graph\Model;
use Illuminate\Support\Facades\Cache;
use App\Http\Tokens\GraphToken;

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
            Cache::set('user', $user);
        }
        return $user;
    }

    public function getFiles()
    {
        $files = $this->graph->createCollectionRequest("GET", '/me/drive/root/children?$select=id,name,folder,fileSystemInfo,size')
            ->setReturnType(Model\DriveItem::class)
            ->execute();
        if (empty($files))   return '';

        $stack = ['/'];
        $path = '/';
        while ($key = array_shift($stack)) {
            [$name, $id] = [...explode(':', $key), ''];
            if ($name !== '/')   $path .= $path === '/' ? $name : '/' . $name;
            $fileList = [];
            $files = $files ? $files : $this->getFileItems($id);
            foreach ($files as $file) {
                $tmp = [
                    'id' => $file->getId(),
                    'name' => $file->getName(),
                    'folder' => $file->getFolder() ? $file->getFolder()->getChildCount() : 0,
                    'ctime' => $file->getFileSystemInfo()->getCreatedDateTime()->format('Y-m-d H:i:s'),
                    'mtime' => $file->getFileSystemInfo()->getLastModifiedDateTime()->format('Y-m-d H:i:s'),
                    'size' => $file->getSize(),
                ];
                $fileList[$tmp['name']] = $tmp;
                if ($tmp['folder'])  array_push($stack, $tmp['name'] . ':' . $tmp['id']);
            }
            $files = null;
            Cache::set($name, ['path' => $path, 'files' => $fileList]);
        }

        return Cache::get('/');
    }

    private function getFileItems($id)
    {
        return $this->graph->createCollectionRequest("GET", "/drives/me/items/{$id}/children")
            ->setReturnType(Model\DriveItem::class)
            ->execute();
    }

    public function getFileContent($id)
    {

        // $ret = (Array)$files[7];
        // $file = array_pop($ret);
        // var_dump(array_shift($file));exit;
        return $this->graph->createRequest("GET", "/me/drive/items/{$id}/content")
            ->setReturnType(\GuzzleHttp\Psr7\Stream::class)
            ->execute()->getContents();
    }

    public function downloadFile($id)
    {
        // Header ( "Content-type: application/octet-stream" );
        // header('Content-Disposition: attachment;filename="' . urlencode($name));
        // header('Cache-Control: max-age=0');
        // echo $this->graph->createRequest("GET", "/me/drive/items/{$id}/content")
        //     ->setReturnType(\GuzzleHttp\Psr7\Stream::class)
        //     ->execute()->getContents();   

        $info = $this->graph->createRequest("GET", "/me/drive/items/{$id}/?\$select=@microsoft.graph.downloadUrl")
            ->setReturnType(Model\DriveItem::class)
            ->execute();
        $file = (array)$info;
        return array_pop($file);
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
