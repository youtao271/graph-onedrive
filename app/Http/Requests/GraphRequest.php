<?php

namespace App\Http\Requests;

use Microsoft\Graph\Graph;
use Microsoft\Graph\Model;
use Illuminate\Support\Facades\Cache;
use App\Http\Tokens\GraphToken;

class GraphRequest {
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
        return $accessToken->hasExpired() ? $this->refreshToken($accessToken->getRefreshToken()) : $accessToken->getToken();
    }

    public function refreshToken($refreshToken)
    {
        $graphToken = new GraphToken;
        return $graphToken->refreshAccessToken($refreshToken);
    }

    public function getUserInfo()
    {
        $user = Cache::get('user');
        if(!$user){
            $user = $this->graph->createRequest('GET', '/me')
            ->setReturnType(Model\User::class)
            ->execute();
            Cache::set('user', $user);
        }
        return $user;
    }

    public function getFiles()
    {
        $files = $this->graph->createCollectionRequest("GET", '/me/drive/root/children?\$expand=children')
        ->setReturnType(Model\DriveItem::class)
        ->execute();

        if($files){
            foreach($files as $file){
                var_dump(
                    [
                        'id' => $file->getId(),
                        'name' => $file->getName(),
                        'webUrl' => $file->getWebUrl(),
                        'folder' => $file->getFolder()?$file->getFolder()->getChildCount():0,
                        'ctime' => $file->getFileSystemInfo()->getCreatedDateTime()->format('Y-m-d H:i:s'),
                        'mtime' => $file->getFileSystemInfo()->getLastModifiedDateTime()->format('Y-m-d H:i:s'),
                        'content' => $file->getContent(),
                    ]
                );
            }
        }

        return $files;
    }

    private function getFileItems($id){
        $files = $this->graph->createCollectionRequest("GET", "/drives/me/items/{$id}/children")
        ->setReturnType(Model\DriveItem::class)
        ->execute();

    }
    

}