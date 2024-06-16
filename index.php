<?php

use losthost\ProxyMessage\message_map;
use losthost\DB\DB;
use TelegramBot\Api\BotApi;
use losthost\ProxyMessage\Proxy;

require 'vendor/autoload.php';
require 'etc/config.php';

DB::connect($db_host, $db_user, $db_pass, $db_name, $db_prefix);
DB::dropAllTables(true, true);

message_map::initDataStructure();

message_map::map(1, 1000, 2, 111);
message_map::map(1, 1000, 3, 222);

echo message_map::find(1, 1000, 2), "\n";
echo message_map::find(3, 222, 1), "\n";

$api = new BotApi($bot_token);
$api->setCurlOption(CURLOPT_CAINFO, $ca_cert);
$proxy = new Proxy($api);

$updates = $api->getUpdates();

echo count($updates), "\n";

foreach ($updates as $update) {
    $message = $update->getMessage();
    if ($message && $message->getChat()->getId() == $bot_chat) {
        $proxy->proxy($message, $test_chat, $test_thread);
    } elseif ($message && $message->getChat()->getId() == $test_chat && $message->getMessageThreadId() == $test_thread) {
        $proxy->proxy($message, $bot_chat);
    }
}
