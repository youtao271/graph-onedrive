<?php
// Copyright (c) Microsoft Corporation.
// Licensed under the MIT License.

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\GraphRequest;
use App\TokenStore\TokenCache;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    public function welcome()
    {

        $accessToken = Cache::get('accessToken');
        $user = Cache::get('user');

        // var_dump(time());
        // var_dump($accessToken);
        // var_dump($accessToken->getRefreshToken());
        // var_dump($accessToken->hasExpired());

        $graph = new GraphRequest;
        $roots = $graph->getFiles();

        var_dump(Cache::get('/'));
        var_dump(Cache::get('test'));
        var_dump(Cache::get('课件'));
    }

    public function index($path='/')
    {
        if($path === '/')   $this->root();

        //var_dump($path);exit;

        [$parent, $key] = array_slice(['', ...array_slice(explode('/', $path), -2)], -2);
        var_dump(['', ...array_slice(explode('/', $path), -2)]);
        var_dump($parent, $key);

        if($files = Cache::get($key)){

        }else{
            $files = Cache::get($parent);
        }

        if($files['path'] !== $path)    echo 'not found';
        var_dump($files);

    }

    private function root()
    {
        var_dump(Cache::get('/'));
        exit;
    }
}
