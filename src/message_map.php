<?php

namespace losthost\ProxyMessage;

use losthost\DB\DBObject;
use losthost\DB\DB;
use losthost\DB\DBView;

/**
 * Хранит связи между оригинальным сообщением и пересланными в другие чаты
 *
 * @author drweb
 */
class message_map extends DBObject {
    
    const METADATA = [
        'id' => 'BIGINT(20) NOT NULL AUTO_INCREMENT',
        'chat_id' => 'BIGINT(20) NOT NULL',
        'message_id' => 'BIGINT(20) NOT NULL',
        'origin' => 'BIGINT(20) NULL',
        'PRIMARY KEY' => 'id',
        'UNIQUE INDEX CMO' => ['chat_id', 'message_id', 'origin']
    ];
    
    static public function map(int $origin_chat_id, int $origin_message_id, $forwarded_chat_id, $forwarded_message_id) {
        
        if (!DB::inTransaction()) {
            DB::beginTransaction();
            $commit = true;
        } else {
            $commit = false;
        }
        try {
            $origin = new message_map(['chat_id' => $origin_chat_id, 'message_id' => $origin_message_id, 'origin' => null], true);
            $origin->isNew() && $origin->write();

            $forwarded = new message_map(['chat_id' => $forwarded_chat_id, 'message_id' => $forwarded_message_id, 'origin' => $origin->id], true);
            $forwarded->isNew() && $forwarded->write();
            $commit && DB::commit();
        } catch (\Exception $e) {
            $commit && DB::rollBack();
            throw $e;
        }
    }
    
    static public function find(int $chat_id, int $message_id, int $target_chat_id) {
        $sql = <<<FIN
                SELECT 
                    linked.message_id AS message_id
                FROM 
                    [message_map] AS origin 
                    LEFT JOIN [message_map] AS linked ON linked.chat_id = :target_1 AND linked.origin = origin.id
                WHERE
                    origin.message_id = :message_1 AND origin.chat_id = :chat_1 AND origin.origin IS NULL

                UNION ALL

                SELECT 
                    linked.message_id AS message_id
                FROM 
                    [message_map] AS origin 
                    LEFT JOIN [message_map] AS linked ON linked.chat_id = :target_2 AND linked.origin IS NULL
                WHERE
                    origin.message_id = :message_2 AND origin.chat_id = :chat_2 AND origin.origin IS NOT NULL
                FIN;
        
        $value = new DBView($sql, [
            'chat_1' => $chat_id,
            'message_1' => $message_id,
            'target_1' => $target_chat_id,
            'chat_2' => $chat_id,
            'message_2' => $message_id,
            'target_2' => $target_chat_id,
        ]);
        
        if ($value->next()) {
            return $value->message_id;
        } 
        return null;
    }
}
