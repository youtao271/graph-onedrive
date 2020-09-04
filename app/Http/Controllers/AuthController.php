<?php
// Copyright (c) Microsoft Corporation.
// Licensed under the MIT License.

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Microsoft\Graph\Graph;
use Illuminate\Support\Facades\Cache;
use Microsoft\Graph\Model;
use League\OAuth2\Client\Provider\GenericProvider;
use App\Http\Tokens\GraphToken;

class AuthController extends Controller
{

  private $authHandle;

  public function __construct()
  {
    $this->authHandle = new GraphToken;
  }

  public function login()
  {

    $authUrl = $this->authHandle->getAuthorizationUrl();

    // Save client state so we can validate in callback
    Cache::set('oauthState', $this->authHandle->getState());
    //var_dump(Cache::get('accessToken'));exit;

    // Redirect to AAD signin page
    return redirect()->away($authUrl);
  }

  public function callback(Request $request)
  {
    // Validate state
    $expectedState = Cache::get('oauthState');
    //$request->session()->forget('oauthState');
    $providedState = $request->query('state');


    if (!isset($expectedState)) {
      // If there is no expected state in the session,
      // do nothing and redirect to the home page.
      return redirect('/');
    }

    if (!isset($providedState) || $expectedState != $providedState) {
      return redirect('/')
        ->with('error', 'Invalid auth state')
        ->with('errorDetail', 'The provided auth state did not match the expected value');
    }

    // Authorization code should be in the "code" query param
    $authCode = $request->query('code');
    if (isset($authCode)) {

      try {

        $token = $this->authHandle->getAccessToken($authCode);

        $graph = new Graph();
        $graph->setAccessToken($token);

        $user = $graph->createRequest('GET', '/me')
          ->setReturnType(Model\User::class)
          ->execute();

        return redirect('/');
      }
      // </StoreTokensSnippet>
      catch (League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
        return redirect('/')
          ->with('error', 'Error requesting access token')
          ->with('errorDetail', $e->getMessage());
      }
    }

    return redirect('/')
      ->with('error', $request->query('error'))
      ->with('errorDetail', $request->query('error_description'));
  }

  public function logout()
  {
    $this->authHandle->signout();
    return redirect('/');
  }
}