<?php

namespace App\Http\Tokens;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Microsoft\Graph\Graph;
use Illuminate\Support\Facades\Cache;
use Microsoft\Graph\Model;
use League\OAuth2\Client\Provider\GenericProvider;

class GraphToken
{

  private $authClient;

  public function __construct()
  {
    $this->authClient = new GenericProvider([
      'clientId'                => config('services.graph.clientId'),
      'clientSecret'            => config('services.graph.clientSecret'),
      'redirectUri'             => config('services.graph.redirectUri'),
      'urlAuthorize'            => config('services.graph.urlAuthorize'),
      'urlAccessToken'          => config('services.graph.urlAccessToken'),
      'urlResourceOwnerDetails' => '',
      'scopes'                  => config('services.graph.scopes')
    ]);
  }

  public function getAuthorizationUrl()
  {
    return $this->authClient->getAuthorizationUrl();
  }

  public function getState()
  {
    return $this->authClient->getState();
  }

  public function storeToken($accessToken)
  {

    Cache::set('accessToken', $accessToken);
  }

  public function clearTokens()
  {
    session()->forget('accessToken');
    session()->forget('refreshToken');
    session()->forget('tokenExpires');
    session()->forget('userName');
    session()->forget('userEmail');
  }

  public function getAccessToken($authCode)
  {

    $accessToken = $this->authClient->getAccessToken('authorization_code', [
      'code' => $authCode
    ]);
    $this->storeToken($accessToken);
    return $accessToken->getToken();
  }

  public function refreshAccessToken($refreshToken)
  {

    $accessToken = $this->authClient->getAccessToken('refresh_token', [
      'refresh_token' => $refreshToken
    ]);
    $this->storeToken($accessToken);
    return $accessToken->getToken();
  }

  public function signout()
  {
    Cache::forget('accessToken');
    Cache::forget('user');
  }
}
