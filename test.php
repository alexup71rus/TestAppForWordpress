<?php

//echo phpinfo();
echo 'Текущая версия PHP: ' . phpversion().'<br>';
$functions = array(
    'mcrypt_get_iv_size',
    'mcrypt_create_iv',
    'mcrypt_decrypt',
);
foreach ($functions as $function) {
    echo $function . ':';
    var_dump(function_exists($function));
    echo '<br>';
}
echo 'eval:';
eval('echo "true";');
echo '<br>';
$hosts = array(
    'http://google.com/',
    'http://yandex.ru/',
    'http://plughunt.com/'
);
echo 'connect to host: <br>';
foreach ($hosts as $host) {
    echo $host . ' -> ';
    $response = test_connect($host);
    if (isset($response) && $response !== false) {
        echo 'true';
    } else
        echo 'false';
    echo '<br>';
}

function test_connect($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    //curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}
