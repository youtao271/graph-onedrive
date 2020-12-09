<?php

namespace App\Http\Controllers;

use App\Http\Requests\GraphRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    public function index()
    {

        return view('pan/index');

    }

    public function update(Request $request)
    {
        $id = $request->input('id', 'root');
        $flag = !!$request->input('flag', false);
        $graph = new GraphRequest;
        $graph->storeFile($id, $flag);
        echo 'Stored';
    }

    public function updateThumbnails()
    {
        $data = $this->getItems();
        if(!$data)  exit('无数据');
        foreach ($data as $item) {
            if(isset($item['thumbnail'])) {
                $check = curl($item['thumbnail'], false, [], '', true);
                if($check['error']) $this->update($item['pid']);
            }
        }
        echo '更新缩略图完成';
    }

    public function download(Request $request){
        $id = $request->input('id', '');
        if(!$id)    return false;

        $graph = new GraphRequest();
        $url = $graph->getFileUrl($id);

        return redirect()->away($url);
    }

    public function test()
    {
        $graph = new GraphRequest;
        // $thumbnail = $graph->getFileThumbnail('01WQGIH6VLFYBWZQV22ZA3VKBX24TNJW6S');
        // $thumbnail = $graph->getFileContent('01WQGIH6RZHJE6C35LCJAI3PLNEVFPHRJX');
        // $thumbnail = $graph->deleteItem([
        //     'id' => '01WQGIH6V4CBSZWZWENJE33BGX3OL46AEK',
        //     'pid' => '01WQGIH6R6S72WUYBPS5G3TXGBLILA3GKP'
        // ]);
        // var_dump($thumbnail);
        // $graph->moveItem('01WQGIH6R6S72WUYBPS5G3TXGBLILA3GKP', [
        //     'id' => '01WQGIH6S4D3EW4NXQIVGLKQUUQLMMNESF',
        //     'name' => 'wap.jpg',
        //     'pid' => 'root'
        // ]);
        $ret = $graph->getFileThumbnail('01WQGIH6WWLA5ZFHXFF5H2FXEX7PIAJKC7');
        var_dump($ret);

        $id = 'root';
        $data = $this->getItems($id);
        var_dump($data);
        // var_dump($graph->subscribe());exit;
        // var_dump($graph->resubscribe('19837082-ea0c-42e2-9e7c-250c6c683c64'));exit;
        // var_dump($graph->getSubscriptions());
        // var_dump($graph->getSubscriptionInfo('19837082-ea0c-42e2-9e7c-250c6c683c64'));exit;
    }

    private function getItems($id='root'): array
    {
        $data = [];
        $files = Cache::get($id);
        foreach ($files as $file){
            array_push($data, $file);
            if($file['folder'] && $file['children']) {
                array_push($data, ...$this->getItems($file['id']));
            }
        }
        return $data;
    }

    public function resubscribe(){
        $date = date('c', strtotime('+ 30 day'));
        $graph = new GraphRequest;
        $subscriptions = $graph->getSubscriptions();
        if(!$subscriptions) return false;
        foreach ($subscriptions as $subscription){
            $id = $subscription->getId();
            $ret = $graph->resubscribe($id, $date);
            var_dump($id);
            var_dump($ret);
            var_dump($ret->getExpirationDateTime()->format('c'));
            ob_flush();
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
}

