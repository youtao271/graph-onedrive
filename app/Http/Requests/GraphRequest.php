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
        $files = $this->graph->createCollectionRequest("GET", "/me/drive/root/children")
        ->setReturnType(Model\DriveItem::class)
        ->getPage();

        return $files;
    }
    

}