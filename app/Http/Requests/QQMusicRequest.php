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
            ]
        ]);
    }

    public function getDissList($page = 1, $pagesize = 20, $sortid = 5, $category = 10000000, $raw = false): array
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
        return $data['data']['list'];

    }

    public function getDissInfo($id): array
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
        $data = $this->getContents();
        return $data['cdlist'][0];
    }

    public function getCategories(): array
    {
        $url = '/splcloud/fcgi-bin/fcg_get_diss_tag_conf.fcg';
        $this->result = $this->client->request('GET', $url, [
            'query' => [
                'inCharset' => 'utf8',
                'outCharset' => 'utf-8',
                'format' => 'json'
            ]
        ]);
        $data = $this->getContents();
        return $data['data']['categories'];
    }

    public function getLyric($id): string
    {
        $url = '/lyric/fcgi-bin/fcg_query_lyric_new.fcg';
        $this->result = $this->client->request('GET', $url, [
            'query' => [
                'songmid' => $id,
                'pcachetime' => time()*1000,
                'g_tk' => 5381,
                'loginUin' => '0',
                'hostUin' => 0,
                'inCharset' => 'utf8',
                'outCharset' => 'utf-8',
                'notice' => 0,
                'platform' => 'yqq',
                'needNewCode' => 0,
            ]
        ]);
        $data = $this->getContents();
        return base64_decode($data['lyric']);
    }

    private function freshCookie(): array
    {
        $this->result = $this->client->request('GET', 'https://api.qq.jsososo.com/user/cookie', [
            'headers' => [
                'Host' => 'api.qq.jsososo.com',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/87.0.4280.88 Safari/537.36'
            ]
        ]);
        $cookie = $this->getContents()['data']['userCookie'];
        Cache::put('QQMusic_cookie', $cookie, 12*60*60);
        return $cookie;
    }

    private function getCookie(): CookieJar
    {
        $cookie = Cache::get('QQMusic_cookie');
        // $cookie = [];
        if (!$cookie) $cookie = $this->freshCookie();
        return CookieJar::fromArray($cookie, 'qq.com');
    }

    public function getSongUrl($ids, $mid, $type = ''): string
    {
        $url = 'https://u.y.qq.com/cgi-bin/musicu.fcg';

        $cookieJar = $this->getCookie();
        $filename = $this->getFilename($ids, $type, $mid);
        $this->result = $this->client->request('GET', $url, [
            'cookies' => $cookieJar,
            'query' => [
                '-' => 'getplaysongvkey',
                'g_tk' => 5381,
                'format' => 'json',
                'inCharset' => 'utf8',
                'outCharset' => 'utf-8',
                'platform' => 'yqq.json',
                'data' => json_encode([
                    "req" => [
                        "module" => "CDN.SrfCdnDispatchServer",
                        "method" => "GetCdnDispatch",
                        "param" => [
                            "guid" => "6351115598",
                            "calltype" => 0,
                            "userip" => ""
                        ]
                    ],
                    'req_0' => [
                        "module" => "vkey.GetVkeyServer",
                        "method" => "CgiGetVkey",
                        "param" => [
                            "filename" => [$filename],
                            "guid" => '12345678',
                            "songmid" => [$ids],
                            "songtype" => [0],
                            "uin" => '956581739',
                            "loginflag" => 1,
                            "platform" => "20",
                        ]
                    ],
                    'comm' => [
                        "uin" => '956581739',
                        "format" => "json",
                        "ct" => 24,
                        "cv" => 0
                    ]
                ])
            ]
        ]);
        $data = $this->getContents();
        $purl = $data['req_0']['data']['midurlinfo'][0]['purl'];
        $sip = $data['req_0']['data']['sip'][1];
        return $sip . $purl;
    }

    private function getFilename($id, $type = '128', $mid = ''): string
    {
        $type = $type ?: '128';
        $mid = $mid ?: $id;
        $typeArr = [
            'm4a' => [
                's' => 'C400',
                'e' => '.m4a',

            ],
            '128' => [
                's' => 'M500',
                'e' => '.mp3',
            ],
            '320' => [
                's' => 'M800',
                'e' => '.mp3',
            ],
            'ape' => [
                's' => 'A000',
                'e' => '.ape',
            ],
            'flac' => [
                's' => 'F000',
                'e' => '.flac',
            ]
        ];
        return $typeArr[$type]['s'] . $id . $mid . $typeArr[$type]['e'];
    }

    private function getContents(): array
    {
        $data = $this->result->getBody()->getContents();
        $data = preg_replace('/callback\(|MusicJsonCallback\(|jsonCallback\(|\)$/i', '', $data);
        return json_decode($data, true);
    }

}
