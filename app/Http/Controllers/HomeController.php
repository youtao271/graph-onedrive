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

    public function index($path = '/')
    {
        if ($path === '/')   $this->root();

        // if ($path === 'api')
            return redirect('/api/index');

        $path = '/' . $path;

        [$parent, $key] = array_slice(explode('/', $path), -2);

        if ($files = Cache::get($key)) {

            if ($files['path'] !== $path) {
                return redirect($files['path']);
            }
        } else {
            $parent = $parent ? $parent : '/';
            if ($files = Cache::get($parent)) {
                if (!array_key_exists($key, $files['files'])) {
                    echo '文件不存在1';
                }

                $graph = new GraphRequest;
                // $content = $graph->getFileContent($files['files'][$key]['id']);
                $fileInfo = $graph->downloadFile($files['files'][$key]['id']);
                header('Location: ' . $fileInfo['@microsoft.graph.downloadUrl']);
                // var_dump($fileInfo);
                exit;
            } else {
                echo '文件不存在2';
            }
        }
        var_dump($files);
    }

    private function root()
    {
        var_dump(Cache::get('/'));
        exit;
    }

    public function refresh()
    {
        $graph = new GraphRequest;
        $graph->getFiles();
        echo 'Refresh OK';
    }
}

