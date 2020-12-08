<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GraphRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class IndexController extends Controller
{

    public function index(Request $request)
    {
        $id = $request->input('id', 'root');
        $data = $this->getItems($id);
        return response($data);
    }

    private function getItems($id): array
    {
        $data = [];
        $files = Cache::get($id);
        foreach ($files as $file) {
            array_push($data, $file);
            if ($file['folder'] && $file['children']) {
                array_push($data, ...$this->getItems($file['id']));
            }
        }
        return $data;
    }

    public function update(Request $request)
    {
        $id = $request->input('id', 'root');
        $flag = !!$request->input('flag', false);
        $graph = new GraphRequest;
        $graph->storeFile($id, $flag);

        return response($this->getItems('root'));
    }

    public function move(Request $request)
    {
        $pid = $request->input('id', 'root');
        $data = $request->input('data');
        $graph = new GraphRequest;
        foreach ($data as $item) {
            $graph->moveItem($pid, $item);
        }

        return response('移动文件或文件夹完成');
    }

    public function content(Request $request)
    {
        $id = $request->input('id');
        $pid = $request->input('pid', 'root');
        $file = $this->getFile($id, $pid);

        if (!$file) return response('文件不存在', '404');

        if ($file['folder']) return response('这是一个文件夹', '403');

        $fileType = $this->getFileType($file['name']);
        $content = Cache::get($id);
        if (!$content) {
            $content = $this->getFileContent($id, $fileType);
        }

        return response(['type' => $fileType, 'data' => $content]);
    }

    public function download(Request $request)
    {
        $id = $request->input('id', '');
        if (!$id) return response('文件不存在', '404');

        $graph = new GraphRequest();
        $url = $graph->getFileUrl($id);
        return response($url);
    }

    public function create(Request $request)
    {
        $id = $request->input('id');
        $name = $request->input('name');
        $graph = new GraphRequest;
        $ret = $graph->createDirectory($id, $name);
        $data = [
            'msg' => '创建文件夹成功',
            'data' => $this->getItems('root')
        ];

        return response('创建文件夹成功', $ret);
    }

    public function delete(Request $request)
    {
        $data = $request->input('data');
        $graph = new GraphRequest;
        $ret = 200;
        foreach ($data as $item) {
            $ret = $graph->deleteItem($item);
        }
        $ret = $ret===204 ? 200 : $ret;
        $data = [
            'msg' => '删除文件或文件夹成功',
            'data' => $this->getItems('root')
        ];

        return response($data, $ret);
    }

    public function upload(Request $request)
    {
        $id = $request->input('id');
        $name = $request->input('name');
        $size = $request->input('size');
        $graph = new GraphRequest;
        $ret = $graph->getUploadScript($id, $name, $size);
        return response($ret);
    }

    public function password(Request $request)
    {
        $id = $request->input('id', null);
        $value = $request->input('value', null);
        if(!$id || !$value) return response('参数错误', 400);

        $ret = $this->getCodeContent($id);
        if($ret === $value) return response($value);
        return response('密码错误', 401);
    }

    private function getFile($id, $pid)
    {
        $files = array_filter(Cache::get($pid), function ($item) use ($id) {
            return $item['id'] === $id;
        });
        return array_pop($files);
    }

    private function getFileType($name)
    {
        if($name === '.password')   return 'pwd';
        $fileExt = pathinfo($name, PATHINFO_EXTENSION);
        $fileType = '';
        foreach (config('ext') as $key => $ext) {
            if (in_array($fileExt, $ext)) {
                $fileType = $key;
                break;
            }
        }
        return $fileType;
    }

    private function getFileContent($id, $type)
    {
        if ($type === 'code' || $type === 'md' || $type === 'pwd') {
            $ret = $this->getCodeContent($id);
        } else {
            $ret = $this->getFileUrl($id);
        }
        return $ret;
    }

    private function getCodeContent($id)
    {
        $graph = new GraphRequest;
        $content = $graph->getFileContent($id);
        Cache::put($id, $content, 5 * 60);
        return $content;
    }

    private function getFileUrl($id)
    {
        $graph = new GraphRequest;
        $url = $graph->getFileUrl($id);
        Cache::add($id, $url, 5 * 60);
        return $url;
    }
}
