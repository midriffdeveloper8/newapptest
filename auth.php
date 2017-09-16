<?php
require 'vendor/autoload.php';

use Carbon\Carbon;
use GuzzleHttp\Client;

$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();

$db = new Mysqli(getenv('MYSQL_HOST'), getenv('MYSQL_USER'), getenv('MYSQL_PASS'), getenv('MYSQL_DB')); 

$api_key = getenv('SHOPIFY_APIKEY');
$secret_key = getenv('SHOPIFY_SECRET');

$query = $_GET; 
if (!isset($query['code'], $query['hmac'], $query['shop'], $query['state'], $query['timestamp'])) {
    exit; //or redirect to an error page
}

$one_minute_ago = Carbon::now()->subSeconds(60)->timestamp;
if ($query['timestamp'] < $one_minute_ago) {
    exit; //or redirect to an error page
}

$hmac = $query['hmac'];
$store = $query['shop'];
unset($query['hmac']);

foreach ($query as $key => $val) {
    $params[] = "$key=$val";
} 

asort($params);
$params = implode('&', $params);

$calculated_hmac = hash_hmac('sha256', $params, $secret_key);
if($hmac == $calculated_hmac){

    $client = new Client();

    $response = $client->request(
        'POST', 
        "https://{$store}/admin/oauth/access_token",
        [
            'form_params' => [
                'client_id' => $api_key,
                'client_secret' => $secret_key,
                'code' => $query['code']
            ]
        ]
    );

    $data = json_decode($response->getBody()->getContents(), true);
    $access_token = $data['access_token'];

    $nonce = $query['state'];

    if ($select = $db->prepare("SELECT id FROM installs WHERE store = ? AND nonce = ?")) {

        $select->bind_param('ss', $store, $nonce);
        $select->execute();
        $select->bind_result($id);
        $select->fetch();
        $select->close();

        if ($id > 0) {
            $db->query("UPDATE installs SET access_token = '$access_token' WHERE id = '$id'");
        }
    }

}