<?php

namespace losthost\ProxyMessage;

use TelegramBot\Api\BotApi;
use TelegramBot\Api\Types\Message;
use TelegramBot\Api\Types\MessageEntity;

/**
 * Proxies a message to another chat
 *
 * @author drweb
 */
class Proxy {
    
    protected BotApi $api;
    protected MessageText $prefix;
    protected BotApi $alternative_api;
    protected array $tmpfiles;


    public function __construct(BotApi $api, string|MessageText $prefix='') {
        $this->api = $api;
        if (is_string($prefix)) {
            $this->prefix = new MessageText($prefix, []);
        } else {
            $this->prefix = $prefix;
        }
    }
    
    public function __destruct() {
        if (isset($this->tmpfiles)) {
            foreach ($this->tmpfiles as $file) {
                unlink($file);
            }
        }
    }
    
    public function setAlternativeApi(BotApi $api) {
        $this->alternative_api = $api;
    }


    public function proxy(Message $message, int $to, ?int $message_thread_id=null) {
        if ($message->getAnimation()) {
            $message_id = $this->proxyAnimation($message, $to, $message_thread_id);
        } elseif ($message->getAudio()) {
            $message_id = $this->proxyAudio($message, $to, $message_thread_id);
        } elseif ($message->getDocument()) {
            $message_id = $this->proxyDocument($message, $to, $message_thread_id);
        } elseif ($message->getPhoto()) {
            $message_id = $this->proxyPhoto($message, $to, $message_thread_id);
        } elseif ($message->getSticker()) {
            $message_id = $this->proxySticker($message, $to, $message_thread_id);
        } elseif ($message->getVideo()) {
            $message_id = $this->proxyVideo($message, $to, $message_thread_id);
        } elseif ($message->getVoice()) {
            $message_id = $this->proxyVoice($message, $to, $message_thread_id);
        } elseif ($message->getText()) {
            $message_id = $this->proxyText($message, $to, $message_thread_id);
        } else {
            $message_id = $this->proxyUnknown($message, $to, $message_thread_id);
        }
        message_map::map($message->getChat()->getId(), $message->getMessageId(), $to, $message_id);
    }
    
    protected function getReplyParameters(Message $message, int $to) : ?string {

        if ($message->getReplyToMessage()) {
            $message_id = message_map::find($message->getChat()->getId(), $message->getReplyToMessage()->getMessageId(), $to);
            
            if (!$message_id) {
                return null;
            }
            return json_encode([
                'message_id' => $message_id,
                'allow_sending_without_reply' => true
            ]);
        }        
        
        return null;
    }
    
    protected function getPhotoFileId(Message $message) {
        $current_file_id = 0;
        $current_file_px = 0;
        
        foreach ($message->getPhoto() as $photo_size) {
            $pixels = $photo_size->getWidth() * $photo_size->getHeight();
            if ($pixels > $current_file_px) {
                $current_file_id = $photo_size->getFileId();
                $current_file_px = $pixels;
            }
        }
        
        return $current_file_id;
    }
    
    protected function getPhotoToSend(Message $message) {
        $file_id = $this->getPhotoFileId($message);
        return $this->getFileToSend($file_id);
    }
    
    protected function getFileToSend(string $file_id) {

        error_log("file_id = $file_id");
        if (!isset($this->alternative_api)) {
            error_log("return file_id = $file_id");
            return $file_id;
        }
        
        $tmpfile = tempnam(sys_get_temp_dir(), 'pxy');
        $file_data = $this->alternative_api->downloadFile($file_id);
        file_put_contents($tmpfile, $file_data);
        
        error_log("return tmpfile = $tmpfile");
        $this->tmpfiles[] = $tmpfile;
        return new \CURLFile($tmpfile);
    }

    protected function proxyAnimation(Message $message, int $to, ?int $message_thread_id) : int {

        $text = new MessageText($message->getCaption(), $message->getCaptionEntities());
        $text->addPrefix($this->prefix);
        
        $answer = Message::fromResponse($this->api->call('sendAnimation', [
            'chat_id' => $to,
            'message_thread_id' => $message_thread_id,
            'animation' => $this->getFileToSend($message->getAnimation()->getFileId()),
            'caption' => $text->getText(),
            'caption_entities' => $text->getEntitiesAsJson(),
            'reply_parameters' => $this->getReplyParameters($message, $to)
        ]));
        
        return $answer->getMessageId();
    }
    protected function proxyAudio(Message $message, int $to, ?int $message_thread_id) : int {

        $text = new MessageText($message->getCaption(), $message->getCaptionEntities());
        $text->addPrefix($this->prefix);
        
        $answer = Message::fromResponse($this->api->call('sendAudio', [
            'chat_id' => $to,
            'message_thread_id' => $message_thread_id,
            'audio' => $this->getFileToSend($message->getAudio()->getFileId()),
            'caption' => $text->getText(),
            'caption_entities' => $text->getEntitiesAsJson(),
            'reply_parameters' => $this->getReplyParameters($message, $to)
        ]));
        
        return $answer->getMessageId();
    }
    protected function proxyDocument(Message $message, int $to, ?int $message_thread_id) : int {

        $text = new MessageText($message->getCaption(), $message->getCaptionEntities());
        $text->addPrefix($this->prefix);
        
        $answer = Message::fromResponse($this->api->call('sendDocument', [
            'chat_id' => $to,
            'message_thread_id' => $message_thread_id,
            'document' => $this->getFileToSend($message->getDocument()->getFileId()),
            'caption' => $text->getText(),
            'caption_entities' => $text->getEntitiesAsJson(),
            'reply_parameters' => $this->getReplyParameters($message, $to)
        ]));
        
        return $answer->getMessageId();
    }
    protected function proxyPhoto(Message $message, int $to, ?int $message_thread_id) : int {

        $text = new MessageText($message->getCaption(), $message->getCaptionEntities());
        $text->addPrefix($this->prefix);
        
        $params = [
            'chat_id' => $to,
            'message_thread_id' => $message_thread_id,
            'photo' => $this->getPhotoToSend($message),
            'caption' => $text->getText(),
            'caption_entities' => $text->getEntitiesAsJson(),
//            'show_caption_above_media' => $message['show_caption_above_media'],
//            'has_spoiler' => $message->getHasMediaSpoiler(),
            'reply_parameters' => $this->getReplyParameters($message, $to),
        ];
        
        $answer = Message::fromResponse($this->api->call('sendPhoto', $params));
        
        return $answer->getMessageId();
    }
    protected function proxySticker(Message $message, int $to, ?int $message_thread_id) : int {

        $answer = Message::fromResponse($this->api->call('sendVoice', [
            'chat_id' => $to,
            'message_thread_id' => $message_thread_id,
            'sticker' => $this->getFileToSend($message->getSticker()->getFileId()),
            'reply_parameters' => $this->getReplyParameters($message, $to)
        ]));
        
        return $answer->getMessageId();
    }
    protected function proxyVideo(Message $message, int $to, ?int $message_thread_id) : int {

        $text = new MessageText($message->getCaption(), $message->getCaptionEntities());
        $text->addPrefix($this->prefix);
        
        $answer = Message::fromResponse($this->api->call('sendVideo', [
            'chat_id' => $to,
            'message_thread_id' => $message_thread_id,
            'video' => $this->getFileToSend($message->getVideo()->getFileId()),
            'caption' => $text->getText(),
            'caption_entities' => $text->getEntitiesAsJson(),
            'reply_parameters' => $this->getReplyParameters($message, $to)
        ]));
        
        return $answer->getMessageId();
    }
    protected function proxyVoice(Message $message, int $to, ?int $message_thread_id) : int {

        $text = new MessageText($message->getCaption(), $message->getCaptionEntities());
        $text->addPrefix($this->prefix);
        
        $answer = Message::fromResponse($this->api->call('sendVoice', [
            'chat_id' => $to,
            'message_thread_id' => $message_thread_id,
            'voice' => $this->getFileToSend($message->getVoice()->getFileId()),
            'caption' => $text->getText(),
            'caption_entities' => $text->getEntitiesAsJson(),
            'reply_parameters' => $this->getReplyParameters($message, $to)
        ]));
        
        return $answer->getMessageId();
    }
    protected function proxyText(Message $message, int $to, ?int $message_thread_id) : int {
        
        $text = new MessageText($message->getText(), $message->getEntities());
        $text->addPrefix($this->prefix);
        
        $answer = Message::fromResponse($this->api->call('sendMessage', [
            'chat_id' => $to,
            'message_thread_id' => $message_thread_id,
            'text' => $text->getText(),
            'entities' => $text->getEntitiesAsJson(),
            'reply_parameters' => $this->getReplyParameters($message, $to),
        ]));
        return $answer->getMessageId();
    }
    protected function proxyUnknown(Message $message, int $to, ?int $message_thread_id) : int {
        
        $answer = Message::fromResponse($this->api->call('sendMessage', [
            'chat_id' => $to,
            'message_thread_id' => $message_thread_id,
            'text' => '<i>Тип сообщения не поддерживается.</i>',
            'parse_mode' => 'HTML',
            'reply_parameters' => $this->getReplyParameters($message, $to),
        ]));

        return $answer->getMessageId();
    }
}
