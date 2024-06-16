<?php

namespace losthost\ProxyMessage;

use TelegramBot\Api\BotApi;
use TelegramBot\Api\Types\Message;

/**
 * Proxies a message to another chat
 *
 * @author drweb
 */
class Proxy {
    
    protected BotApi $api;
    
    public function __construct(BotApi $api) {
        $this->api = $api;
    }
    
    public function proxy(Message $message, int $to, ?int $message_thread_id=null) {
        if ($message->getText()) {
            $message_id = $this->proxyText($message, $to, $message_thread_id);
            message_map::map($message->getChat()->getId(), $message->getMessageId(), $to, $message_id);
        } else {
            throw new \Exception('Unknown message type.');
        }
    }
    
    protected function proxyText(Message $message, int $to, ?int $message_thread_id) : int {
        
        if ($message->getReplyToMessage()) {
            $reply_params = json_encode([
                'message_id' => message_map::find($message->getChat()->getId(), $message->getReplyToMessage()->getMessageId(), $to),
                'allow_sending_without_reply' => true
            ]);
        } else {
            $reply_params = null;
        }        

        $answer = Message::fromResponse($this->api->call('sendMessage', [
            'chat_id' => $to,
            'message_thread_id' => $message_thread_id,
            'text' => $message->getText(),
            'entities' => $message->getEntities(),
            'reply_parameters' => $reply_params
        ]));
        return $answer->getMessageId();
    }
}
