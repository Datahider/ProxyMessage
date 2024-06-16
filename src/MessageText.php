<?php

namespace losthost\ProxyMessage;

use TelegramBot\Api\Types\MessageEntity;

/**
 * Обработка текста и заголовка сообщения
 *
 * @author drweb
 */
class MessageText {
    
    protected string $text;
    protected ?array $entities;
    
    public function __construct(?string $text, ?array $entities=null) {
        $this->text = $text ? $text : '';
        $this->entities = $entities ? $entities : [];
    }
    
    public function getText() {
        if (empty($this->text)) {
            return null;
        } 
        return $this->text;
    }
    
    public function getEntitiesAsJson() {
        $result = [];
        
        foreach ($this->entities as $entity) {
            $result[] = $entity->toJson();
        }
        
        if (empty($result)) {
            return null;
        }
        return '['. implode(',', $result). ']';
    }
    
    public function getEntities() {
        if (empty($this->entities)) {
            return null;
        }
        return $this->entities;
    }
    
    public function addPrefix(MessageText $prefix) {
        
        $prefix_len = mb_strlen($prefix->getText());
        $this->text = $prefix->getText(). $this->text;
        
        foreach ($this->entities as $entity) {
            $entity->setOffset($prefix_len+$entity->getOffset());
//            $reflection = new \ReflectionClass($entity);
//            $offset = $reflection->getProperty('offset');
//            $offset->setAccessible(true);
//            $offset->setValue($entity, $prefix_len+$offset->getValue($entity));
        }
        
        $this->entities = array_merge($prefix->getEntities(), $this->entities);
    }
}
