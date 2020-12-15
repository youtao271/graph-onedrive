<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\QQMusicRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class QQMusicController extends Controller
{
    private QQMusicRequest $QQMusic;

    public function __construct()
    {
        $this->QQMusic = new QQMusicRequest();
    }

    public function disslist()
    {
        return apiResponse($this->QQMusic->getDissList());
    }

    public function dissinfo(Request $request)
    {
        $id = $request->input('id');
        return apiResponse($this->QQMusic->getDissInfo($id));
    }

    public function song(Request $request)
    {
        $id = $request->input('id');
        $mid = $request->input('mid', '');
        $type = $request->input('type', '128');
        $url = $this->QQMusic->getSongUrl($id, $mid, $type);
        return apiResponse($url);
    }

}
