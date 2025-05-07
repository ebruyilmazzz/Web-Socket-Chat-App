<?php
require __DIR__ . '/vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class Chat implements MessageComponentInterface {
    protected $clients;
    protected $users = [];

    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        $conn->send(json_encode(["type" => "connection_success"]));
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        
        switch ($data['type']) {
            case 'join':
                $this->users[$from->resourceId] = [
                    'id' => $from->resourceId,
                    'username' => $data['username'],
                    'connection' => $from,
                    'online' => true
                ];
                
                // Tüm kullanıcıları gönder
                $usersList = [];
                foreach ($this->users as $id => $user) {
                    $usersList[] = [
                        'id' => $id,
                        'username' => $user['username'],
                        'online' => $user['online']
                    ];
                }
                
                foreach ($this->clients as $client) {
                    $client->send(json_encode([
                        'type' => 'users_list',
                        'users' => $usersList
                    ]));
                }
                break;

            case 'private_message':
                if (isset($this->users[$data['to']])) {
                    $messageData = [
                        'type' => 'private_message',
                        'from' => $from->resourceId,
                        'fromUsername' => $this->users[$from->resourceId]['username'],
                        'to' => $data['to'],
                        'toUsername' => $this->users[$data['to']]['username'],
                        'message' => $data['message'],
                        'timestamp' => date('H:i')
                    ];
                    // Veritabanına kaydetme kodun varsa burada ekle
                    $this->users[$data['to']]['connection']->send(json_encode($messageData));
                    $from->send(json_encode($messageData));
                }
                break;

            case 'typing':
                if (isset($this->users[$data['to']])) {
                    $this->users[$data['to']]['connection']->send(json_encode([
                        'type' => 'typing',
                        'from' => $from->resourceId,
                        'fromUsername' => $this->users[$from->resourceId]['username'],
                        'isTyping' => $data['isTyping']
                    ]));
                }
                break;
                
            case 'message':
                $senderId = $this->users[$from->resourceId]['id'];
                $receiverId = $data['receiverId'];
                $message = $data['message'];
                
                // Mesajı veritabanına kaydet
                $stmt = $this->db->prepare("
                    INSERT INTO messages (sender_id, receiver_id, message) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$senderId, $receiverId, $message]);
                
                // Mesajı alıcıya ve göndericiye ilet
                $messageData = [
                    'type' => 'message',
                    'senderId' => $senderId,
                    'receiverId' => $receiverId,
                    'message' => $message,
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                
                foreach ($this->users as $user) {
                    if ($user['id'] == $receiverId || $user['id'] == $senderId) {
                        $user['connection']->send(json_encode($messageData));
                    }
                }
                break;
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        
        if (isset($this->users[$conn->resourceId])) {
            unset($this->users[$conn->resourceId]);
            
            // Kullanıcı listesini güncelle
            $usersList = [];
            foreach ($this->users as $user) {
                $usersList[] = [
                    'id' => $user['id'],
                    'username' => $user['username']
                ];
            }
            
            foreach ($this->clients as $client) {
                $client->send(json_encode([
                    'type' => 'users',
                    'users' => $usersList
                ]));
            }
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }
}

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new Chat()
        )
    ),
    8080
);

echo "WebSocket server started on port 8080...\n";
$server->run();
