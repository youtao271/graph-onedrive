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
    public function index()
    {

        return view('pan/index');

    }

    public function store()
    {
        $graph = new GraphRequest;
        $graph->storeFile();
        echo 'Stored';
    }

    public function test()
    {
        $id = 'root';
        $data = [];
        $this->getItems($id, $data);
        var_dump($data);
    }

    private function getItems($id, &$data){
        $files = Cache::get($id);
        foreach ($files as $file){
            array_push($data, $file);
            if($file['folder'] && $file['children'])   $this->getItems($file['id'], $data);
        }
    }

    public function welcome($path = '/')
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

