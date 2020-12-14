<?php
if (!function_exists('curl')) {
    function curl($url, $type = false, $heads = array(), $cookie = '', $nobody = false): array
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.101 Safari/537.36');

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, $type);
        curl_setopt($curl, CURLOPT_NOBODY, $nobody);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_REFERER, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        if (!empty($cookie)) {
            curl_setopt($curl, CURLOPT_COOKIE, $cookie);
        }
        if (count($heads) > 0) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $heads);
        }
        $response = curl_exec($curl);
        if ($errno = curl_errno($curl)) {//出错则显示错误信息
            return array('error' => $errno, 'data' => curl_error($curl));
        }
        curl_close($curl); //关闭curl链接
        return array('error' => 0, 'data' => $response);

    }
}

function apiResponse($data, $msg='操作成功', $error=0) {
    return response([
        'error' => $error,
        'data' => $data,
        'msg' => $msg
    ]);
}
