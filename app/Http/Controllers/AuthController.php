<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Http\Tokens\GraphToken;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model;
use App\Http\Requests\GraphRequest;

class AuthController extends Controller
{

    private $authHandle;

    public function __construct()
    {
        $this->authHandle = new GraphToken;
    }

    public function login()
    {
        $accessToken = Cache::get('accessToken');
        if ($accessToken) {
            if ($accessToken->hasExpired()) {
                $refreshToken = $accessToken->getRefreshToken();
                $this->authHandle->refreshAccessToken($refreshToken);
            }
            return redirect('/');
        }

        $authUrl = $this->authHandle->getAuthorizationUrl();
        //var_dump($authUrl);exit;

        Cache::set('oauthState', $this->authHandle->getState());

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

    public function testSubscribe()
    {
        var_dump(Cache::get('validationToken'));
    }
    public function subscribe()
    {

        /* 
    https://graph.microsoft.com/v1.0/subscriptions
    {
      "changeType": "updated",
      "notificationUrl": "https://localhost/notify",
      "resource": "/drive/root",
      "expirationDateTime":"2020-09-20T18:23:45.9356913Z",
      "clientState": "secretClientValue",
      "latestSupportedTlsVersion": "v1_2"
    } 
    */

        $guzzle = new \GuzzleHttp\Client();
        $url = 'https://login.microsoftonline.com/common/oauth2/token?api-version=1.0';
        /* $token = json_decode($guzzle->post($url, [
            'form_params' => [
                'client_id' => config('services.graph.clientId'),
                'client_secret' => config('services.graph.clientSecret'),
                'resource' => 'https://graph.microsoft.com/',
                'grant_type' => 'client_credentials',
            ],
        ])->getBody()->getContents());
        $accessToken = $token->access_token; */

        $GraphRequest = new GraphRequest;
        $accessToken = $GraphRequest->getToken();

        $url = 'https://graph.microsoft.com/v1.0/subscriptions';

        try {
            $res = json_decode($guzzle->post($url, [
                'headers' => [
                    'authorization' => 'Bearer ' . $accessToken
                ],
                'json' => [
                    'changeType' => 'updated',
                    'notificationUrl' => config('services.graph.notificationUrl'),
                    'resource' => 'me/drive/root',
                    'expirationDateTime' => '2020-09-20T18:23:45.9356913Z',
                    "clientState" => "secretClientValue",
                    "latestSupportedTlsVersion" => "v1_2"
                ],
            ])->getBody()->getContents());
            var_dump($res);
        } catch (\Exception $e) {
            var_dump($e->getMessage());
        }




        var_dump($accessToken);
        exit;

        $graph = new Graph();
        $graph->setAccessToken($token);

        $user = $graph->createRequest('GET', '/me')
            ->setReturnType(Model\User::class)
            ->execute();
    }

    public function notify()
    {
        /* if ($validationToken = $_GET['validationToken']) {
            Cache::set('validationToken', $validationToken);
        } else {
            Cache::set('validationToken', 'validationTokenTest');
        } */
        return response($_REQUEST['validationtoken'])->header('Content-Type', 'text/plain');
    }

    public function logout()
    {
        $this->authHandle->signout();
        return redirect('/');
    }


    public function testWebhooks()
    {
        $sub = new Model\Subscription();
        $sub->setChangeType("updated");
        $sub->setNotificationUrl("https://pan.9dutv.com/notify");
        $sub->setResource("/me/drive/root");
        $time = new \DateTime();
        $time->add(new \DateInterval("PT1H"));
        $sub->setExpirationDateTime($time);

        $GraphRequest = new GraphRequest;
        $res = $GraphRequest->webhooks($sub);

        var_dump($res);
    }
}
