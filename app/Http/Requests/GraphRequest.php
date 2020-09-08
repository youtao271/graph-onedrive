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
        $files = $this->graph->createCollectionRequest("GET", '/me/drive/root/children?')
            ->setReturnType(Model\DriveItem::class)
            ->execute();
        //->getBody();
        if(empty($files))   return '';

        $stack = ['/'];
        while ($key = array_shift($stack)) {
            $fileList = [];
            $files = $files ? $files : $this->getFileItems($key);
            foreach ($files as $file) {
                $tmp = [
                    'id' => $file->getId(),
                    'name' => $file->getName(),
                    'webUrl' => $file->getWebUrl(),
                    'folder' => $file->getFolder() ? $file->getFolder()->getChildCount() : 0,
                    'ctime' => $file->getFileSystemInfo()->getCreatedDateTime()->format('Y-m-d H:i:s'),
                    'mtime' => $file->getFileSystemInfo()->getLastModifiedDateTime()->format('Y-m-d H:i:s'),
                    'size' => $file->getSize(),
                ];
                array_push($fileList, $tmp);
                if ($tmp['folder'])  array_push($stack, $tmp['id']);
            }
            $files = null;
            Cache::set($key, $fileList);
        }

        //var_dump($fileList, $stack);exit;

        return Cache::get('/');
    }

    private function getFileItems($id)
    {
        return $this->graph->createCollectionRequest("GET", "/drives/me/items/{$id}/children")
            ->setReturnType(Model\DriveItem::class)
            ->execute();
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
