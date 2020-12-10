<?php

namespace App\Http\Requests;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Stream;
use Illuminate\Support\Facades\Cache;

class QQMusicRequest
{
    private Client $client;
    private ResponseInterface $result;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://c.y.qq.com',
            'headers' => [
                'Referer' => 'https://y.qq.com/'
            ],
            'query' => [
                'format' => 'json'
            ],
            'cookies' => true
        ]);
    }

    public function getDissList($page = 1, $pagesize = 20, $sortid = 5, $category = 10000000, $raw = false)
    {
        $url = '/splcloud/fcgi-bin/fcg_get_diss_by_tag.fcg';
        $sin = $pagesize * ($page - 1);
        $ein = $pagesize * $page - 1;
        $this->result = $this->client->request('GET', $url, [
            'query' => [
                'inCharset' => 'utf8',
                'outCharset' => 'utf-8',
                'sortId' => $sortid,
                'categoryId' => $category,
                'sin' => $sin,
                'ein' => $ein,
                'format' => 'json'
            ]
        ]);
        $data = $this->getContents();
        $data = $data['data']['list'];

    }

    public function getDissInfo($id)
    {
        $url = '/qzone/fcg-bin/fcg_ucc_getcdinfo_byids_cp.fcg';
        $this->result = $this->client->request('GET', $url, [
            'query' => [
                'type' => 1,
                'utf8' => 1,
                'loginUin' => 0,
                'disstid' => $id,
                'format' => 'json'
            ]
        ]);
        return $this->getContents();
    }

    public function getCategories()
    {
        $url = '/splcloud/fcgi-bin/fcg_get_diss_tag_conf.fcg';
        $this->result = $this->client->request('GET', $url, [
            'query' => [
                'inCharset' => 'utf8',
                'outCharset' => 'utf-8',
                'format' => 'json'
            ]
        ]);
        return $this->getContents();
    }

    public function getMap($id)
    {
        $url = 'https://u.y.qq.com/cgi-bin/musicu.fcg';
        $this->result = $this->client->request('GET', $url, [
            'query' => [
                '-' => 'getplaysongvkey',
                'g_tk' => 5381,
                'format' => 'json',
                'inCharset' => 'utf8',
                'outCharset' => 'utf-8',
                'platform' => 'yqq.json',
                'data' => [
                    'req_0' => [
                        "module" => "vkey.GetVkeyServer",
                        "method" => "CgiGetVkey",
                        "param" => [
                            "filename" => [file],
                            "guid" => mt_rand(),
                            "songmid" => $id,
                            "songtype" => 0,
                            "uin" => 1234567,
                            "loginflag" => 1,
                            "platform" => "20",
                        ]
                    ],
                    'comm' => [
                        "uin" => 1234567,
                        "format" => "json",
                        "ct" => 19,
                        "cv" => 0
                    ]
                ]
            ]
        ]);
        return $this->getContents();
    }

    public function getCookie()
    {
        // $this->result = $this->client->request('GET', 'https://y.qq.com');
        $jar = new CookieJar();
        $this->result = $this->client->request('GET', 'https://www.baidu.com', ['cookies'=>$jar]);
        // $it = $jar->getIterator();
        // while ($it->valid()) {
        //     var_dump(111);
        //     var_dump($it->current());
        //     $it->next();
        // }
        $cookie = $jar->toArray();
        var_dump($cookie);
        var_dump($this->getContents());
        exit;
    }

    private function getContents()
    {
        // var_dump($this->result);
        // $data = $this->result->getBody()->getContents();
        // $data = preg_replace('/callback\(|MusicJsonCallback\(|jsonCallback\(|\)$/i', '', $data);
        return json_decode($this->result->getBody()->getContents(), true);
    }

}
