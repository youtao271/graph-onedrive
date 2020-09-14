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
        $stack = [['key' => '/', 'id' => 0]];
        while ($cur = array_shift($stack)) {
            $data = Cache::get($cur['key']);
            if (empty($data['files']))   continue;
            foreach ($data['files'] as $file) {
                $file['pid'] = $cur['id'];
                array_push($ret, $file);
                if ($file['folder']) array_push($stack, ['key' => $file['name'], 'id' => $file['id']]);
            }
        }
        return $ret;
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
