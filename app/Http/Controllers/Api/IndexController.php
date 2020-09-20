<?php
// Copyright (c) Microsoft Corporation.
// Licensed under the MIT License.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GraphRequest;
use Illuminate\Support\Facades\Cache;

class IndexController extends Controller
{

    public function index($path = '/')
    {
        if ($path === 'all') {
            $ret = $this->getAll();
            return $this->response($ret);
        }
        $path = '/' . $path;

        [$parent, $key] = array_slice(explode('/', $path), -2);

        if ($files = Cache::get($key)) {

            return $this->response($files);

        } else {

            if ($path === '/index')   return $this->response(Cache::get('/'));

            $parent = $parent ? $parent : '/';

            if ($files = Cache::get($parent)) {
                if (!array_key_exists($key, $files['files'])) {
                    return $this->response('', '404', '文件不存在1');
                }

                $graph = new GraphRequest;
                $content = $graph->getFileContent($files['files'][$key]['id']);
                return $this->response($content);
            } else {
                return $this->response('', '404', '文件不存在2');
            }
        }
    }

    private function getAll()
    {
        $ret = [];
        $stack = ['/'];
        while ($id = array_shift($stack)) {
            $data = Cache::get($id);
            if (empty($data['files']))   continue;
            foreach ($data['files'] as $file) {
                $file['pid'] = $id === '/' ? 0 : $id;
                array_push($ret, $file);
                if ($file['folder']) array_push($stack, $file['id']);
            }
        }
        return $ret;
    }

    public function all()
    {
        $ret = [];
        $stack = ['/'];
        while ($id = array_shift($stack)) {
            $data = Cache::get($id);
            if (empty($data['files']))   continue;
            foreach ($data['files'] as $file) {
                $file['pid'] = $id === '/' ? 0 : $id;
                array_push($ret, $file);
                if ($file['folder']) array_push($stack, $file['id']);
            }
        }
        return $this->response($ret);
    }

    public function content($id)
    {
        $content = Cache::get($id);
        if(!$content){
            $graph = new GraphRequest;
            $content = $graph->getFileContent($id);
            Cache::add($id, $content, 5*60);
        }

        return $this->response($content);
    }

    private function response($data=null, $code='200', $msg='加载成功'){
        $ret = [
            'code' => $code,
            'msg'  => $msg,
        ];
        if($data)   $ret['data'] = $data;
        return response()->json($ret);
    }
}
