<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Http\Tokens\GraphToken;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model;
use App\Http\Requests\GraphRequest;

class AuthController extends Controller
{

    private GraphToken $authHandle;

    public function __construct()
    {
        $this->authHandle = new GraphToken;
    }

    public function login()
    {
        $accessToken = Cache::get('accessToken');
        if (0 && $accessToken) {
            if ($accessToken->hasExpired()) {
                $refreshToken = $accessToken->getRefreshToken();
                $this->authHandle->refreshAccessToken($refreshToken);
            }
            return redirect('/');
        }

        $authUrl = $this->authHandle->getAuthorizationUrl();
        //var_dump($authUrl);exit;

        Cache::put('oauthState', $this->authHandle->getState());

        return redirect()->away($authUrl);
    }

    public function callback(Request $request)
    {
        $expectedState = Cache::get('oauthState');
        $providedState = $request->query('state');

        if (!isset($expectedState)) {
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
                //获取accessToken并存储在Redis中
                $this->authHandle->getAccessToken($authCode);
                return redirect('/');
            } catch (League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
                return redirect('/')
                    ->with('error', 'Error requesting access token')
                    ->with('errorDetail', $e->getMessage());
            }
        }

        return redirect('/')
            ->with('error', $request->query('error'))
            ->with('errorDetail', $request->query('error_description'));
    }

    public function notify(Request $request)
    {
        if($validationToken=$request->input('validationToken')){
            Cache::put('validationToken', $request->input('validationToken'));
            return $request->input('validationToken');
        }
        if($value = $request->input('value')){
            Cache::put('notifyValue', $request->input('value'));
            $guzzle = new Client();
            $guzzle->get(config('app.url').'/refresh');
        }
        return response('Accepted', 202);
    }

    public function getValidationToken()
    {
        return Cache::get('validationToken');
    }

    public function logout()
    {
        $this->authHandle->signout();
        return redirect('/');
    }

}
