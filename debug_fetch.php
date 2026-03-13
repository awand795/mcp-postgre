<?php
require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Http;
use GuzzleHttp\Cookie\CookieJar;

$loginPageUrl = 'https://erp-guidance.online/login/';
$email = 'afdolfarosir19@gmail.com';
$password = 'Alfaro19@wp';
$targetUrl = 'https://erp-guidance.online/inventory/dhquc8yrcg-7898a3c623fd9944cb4f5311e7fec166/';

$jar = new CookieJar;

// 1. Get Login Page to extract Nonce
echo "Fetching login page...\n";
$client = new \GuzzleHttp\Client(['cookies' => true, 'verify' => false]);
$res = $client->get($loginPageUrl);
$body = (string)$res->getBody();

$crawler = new \Symfony\Component\DomCrawler\Crawler($body);
$nonce = $crawler->filter('input[name="erpgl_login_nonce"]')->count() 
         ? $crawler->filter('input[name="erpgl_login_nonce"]')->attr('value') 
         : null;
$referer = $crawler->filter('input[name="_wp_http_referer"]')->count() 
           ? $crawler->filter('input[name="_wp_http_referer"]')->attr('value') 
           : '/login/';

echo "Found nonce: $nonce\n";

// 2. Login
echo "Logging in...\n";
$res = $client->post($loginPageUrl, [
    'form_params' => [
        'log' => $email,
        'pwd' => $password,
        'wp-submit' => 'Log In',
        'rememberme' => 'forever',
        'erpgl_login_nonce' => $nonce,
        '_wp_http_referer' => $referer,
        'testcookie' => 1
    ]
]);

echo "Login status: " . $res->getStatusCode() . "\n";

// 3. Fetch Target
echo "Fetching target page...\n";
$res = $client->get($targetUrl);
$html = (string)$res->getBody();

file_put_contents('debug_page.html', $html);
echo "Saved to debug_page.html\n";
