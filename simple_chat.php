<?php
require __DIR__ . '/vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

// WebSocket sunucusu
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
        
        if ($data['type'] === 'join') {
            $this->users[$from->resourceId] = $data['username'];
            // Yeni kullanıcı katıldı mesajını herkese gönder
            foreach ($this->clients as $client) {
                $client->send(json_encode([
                    "type" => "user_list",
                    "users" => array_values($this->users)
                ]));
            }
        }
        else if ($data['type'] === 'chat') {
            $username = $this->users[$from->resourceId];
            foreach ($this->clients as $client) {
                $client->send(json_encode([
                    "type" => "message",
                    "username" => $username,
                    "message" => $data['message']
                ]));
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        if (isset($this->users[$conn->resourceId])) {
            unset($this->users[$conn->resourceId]);
            // Kullanıcı listesini güncelle
            foreach ($this->clients as $client) {
                $client->send(json_encode([
                    "type" => "user_list",
                    "users" => array_values($this->users)
                ]));
            }
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Hata: {$e->getMessage()}\n";
        $conn->close();
    }
}

// Eğer bu dosya direkt çalıştırılıyorsa (sunucu olarak)
if (php_sapi_name() === 'cli') {
    $server = IoServer::factory(
        new HttpServer(
            new WsServer(
                new Chat()
            )
        ),
        8080
    );
    echo "WebSocket sunucusu 8080 portunda başlatıldı...\n";
    $server->run();
}
// Eğer web tarayıcısından erişiliyorsa (istemci olarak)
else {
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Basit Chat</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; }
        #messages { 
            height: 400px; 
            overflow-y: auto; 
            border: 1px solid #ddd; 
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .message {
            margin-bottom: 10px;
            padding: 10px;
            border-radius: 5px;
        }
        .message.sent {
            background-color: #007bff;
            color: white;
            margin-left: 20%;
        }
        .message.received {
            background-color: #f8f9fa;
            margin-right: 20%;
        }
        #userList {
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-header">
                        Aktif Kullanıcılar
                    </div>
                    <div class="card-body">
                        <div id="userList"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-9">
                <div id="loginForm">
                    <div class="mb-3">
                        <label for="username" class="form-label">Kullanıcı Adınız:</label>
                        <input type="text" class="form-control" id="username">
                    </div>
                    <button class="btn btn-primary" onclick="join()">Sohbete Katıl</button>
                </div>

                <div id="chatForm" style="display: none;">
                    <h3>Sohbet</h3>
                    <div id="messages"></div>
                    <div class="input-group">
                        <input type="text" id="message" class="form-control" placeholder="Mesajınızı yazın...">
                        <button class="btn btn-primary" onclick="sendMessage()">Gönder</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let ws;
        let myUsername = '';

        function connect() {
            ws = new WebSocket('ws://localhost:8080');

            ws.onopen = () => {
                console.log('Bağlantı kuruldu');
            };

            ws.onmessage = (e) => {
                const data = JSON.parse(e.data);
                
                switch(data.type) {
                    case 'connection_success':
                        console.log('Bağlantı başarılı');
                        break;
                    
                    case 'user_list':
                        updateUserList(data.users);
                        break;
                    
                    case 'message':
                        displayMessage(data);
                        break;
                }
            };

            ws.onclose = () => {
                console.log('Bağlantı kapandı');
                setTimeout(connect, 1000);
            };
        }

        function join() {
            myUsername = document.getElementById('username').value.trim();
            if (myUsername) {
                ws.send(JSON.stringify({
                    type: 'join',
                    username: myUsername
                }));
                document.getElementById('loginForm').style.display = 'none';
                document.getElementById('chatForm').style.display = 'block';
            }
        }

        function updateUserList(users) {
            const userList = document.getElementById('userList');
            userList.innerHTML = '<ul class="list-group">' + 
                users.map(user => `<li class="list-group-item">${user}</li>`).join('') +
                '</ul>';
        }

        function sendMessage() {
            const messageInput = document.getElementById('message');
            const message = messageInput.value.trim();
            
            if (message) {
                ws.send(JSON.stringify({
                    type: 'chat',
                    message: message
                }));
                messageInput.value = '';
            }
        }

        function displayMessage(data) {
            const messages = document.getElementById('messages');
            const div = document.createElement('div');
            div.className = `message ${data.username === myUsername ? 'sent' : 'received'}`;
            div.innerHTML = `<strong>${data.username}:</strong> ${data.message}`;
            messages.appendChild(div);
            messages.scrollTop = messages.scrollHeight;
        }

        // Enter tuşu ile mesaj gönderme
        document.addEventListener('DOMContentLoaded', function() {
            const messageInput = document.getElementById('message');
            const usernameInput = document.getElementById('username');
            
            messageInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    sendMessage();
                }
            });
            
            usernameInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    join();
                }
            });
        });

        // Bağlantıyı başlat
        connect();
    </script>
</body>
</html>
<?php
}
?>
