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

    var_dump(time());
    var_dump($accessToken);
    var_dump($accessToken->getRefreshToken());
    var_dump($accessToken->hasExpired());

    $graph = new GraphRequest;
    var_dump($graph->getUserInfo());
    var_dump($graph->getFiles());
  }
}
