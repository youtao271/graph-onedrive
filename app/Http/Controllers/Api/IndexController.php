<?php
// Copyright (c) Microsoft Corporation.
// Licensed under the MIT License.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GraphRequest;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class IndexController extends Controller
{

    public function index($path = '/')
    {
        if ($path === '/' || $path === 'all') {
            return $this->response(Cache::get('/'));
        }

        $file = $this->getFile($path);

        if (!$file) return $this->response('', '404', '文件不存在');

        if (!$file['folder']) return $this->response($file);

        return $this->response($this->getFileList($path));
    }

    public function content($id)
    {
        $file = $this->getFile($id);

        if (!$file) return $this->response('', '404', '文件不存在');

        if ($file['folder']) return $this->response('', '403', '这是一个文件夹');

        $fileType = $this->getFileType($file['name']);
        $content = Cache::get($id);
        if (!$content) {
            $content = $this->getFileContent($id, $fileType);
        }

        return $this->response(['type'=>$fileType, 'data'=>$content]);
    }

    public function create(Request $request){
        $id = $request->input('id');
        $name = $request->input('name');
        $graph = new GraphRequest;
        $ret = $graph->createDirectory($id, $name);

        return $this->response(null, $ret['code'], $ret['msg']);
    }

    public function delete(Request $request){
        $ids = $request->input('ids');
        $graph = new GraphRequest;
        $ret = [];
        foreach ($ids as $id){
            $ret = $graph->deleteItem($id);
        }

        $guzzle = new Client();
        $guzzle->get(config('app.url').'/refresh')->getStatusCode();

        return $this->response(null, $ret['code'], $ret['msg']);
    }

    public function upload(Request $request){
        $id = $request->input('id');
        $name = $request->input('name');
        $graph = new GraphRequest;
        $ret = $graph->getUploadScript($id, $name);
        return $this->response($ret);
    }

    private function getFile($id){
        $file = array_filter(Cache::get('/'), function ($item) use ($id) {
            return $item['id'] === $id;
        });
        return array_pop($file);
    }

    private function getFileList($id)
    {
        return array_filter(Cache::get('/'), function ($item) use ($id) {
            return $item['pid'] === $id;
        });
    }

    private function getFileType($name)
    {
        $fileExt = pathinfo($name, PATHINFO_EXTENSION);
        $fileType = '';
        foreach (config('ext') as $key => $ext){
            if(in_array($fileExt, $ext)){
                $fileType = $key;
                break;
            }
        }
        return $fileType;
    }

    private function getFileContent($id, $type){
        if($type === 'code' || $type === 'md'){
            $ret = $this->getCodeContent($id);
        }else{
            $ret = $this->getFileUrl($id);
        }
        return $ret;
    }

    private function getCodeContent($id){
        $graph = new GraphRequest;
        $content = $graph->getFileContent($id);
        Cache::put($id, $content, 5 * 60);
        return $content;
    }

    private function getFileUrl($id){
        $graph = new GraphRequest;
        $url = $graph->getFileUrl($id);
        Cache::add($id, $url, 5 * 60);
        return $url;
    }

    private function response($data = null, $code = '200', $msg = '加载成功')
    {
        $ret = [
            'code' => $code,
            'msg' => $msg,
        ];
        if ($data) $ret['data'] = $data;
        return response()->json($ret);
    }
}
