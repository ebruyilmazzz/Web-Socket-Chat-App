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
    protected $typing = [];
    protected $messageStatuses = [];

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        echo " sunucu başlatıldı...\n";
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        $conn->send(json_encode(["type" => "connection_success"]));
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        $fromId = $from->resourceId;
        
        echo "Mesaj alındı: " . $msg . "\n";
        
        switch ($data['type']) {
            case 'join':
                $this->users[$fromId] = [
                    'username' => $data['username'],
                    'connection' => $from,
                    'online' => true,
                    'last_seen' => date('H:i'),
                    'avatar' => 'https://ui-avatars.com/api/?name=' . urlencode($data['username']) . '&background=random'
                ];
                
                $this->broadcastUserList();
                break;

            case 'private_message':
                if (isset($this->users[$data['to']])) {
                    $messageId = uniqid('msg_');
                    $timestamp = date('H:i');
                    
                    $messageData = [
                        'type' => 'private_message',
                        'messageId' => $messageId,
                        'fromUsername' => $this->users[$fromId]['username'],
                        'message' => $data['message'],
                        'timestamp' => $timestamp,
                        'status' => 'sent'
                    ];
                    
                    // Alıcıya göndermek için
                    $this->users[$data['to']]['connection']->send(json_encode($messageData));
                    
                    // Göndericiye iletmek için
                    $messageData['status'] = 'delivered';
                    $from->send(json_encode($messageData));
                    
                    // Mesaj durumunu saklama işlemi
                    $this->messageStatuses[$messageId] = [
                        'from' => $fromId,
                        'to' => $data['to'],
                        'status' => 'delivered',
                        'timestamp' => $timestamp
                    ];
                }
                break;

            case 'typing_status':
                if (isset($this->users[$data['to']])) {
                    $this->users[$data['to']]['connection']->send(json_encode([
                        'type' => 'typing_status',
                        'from' => $fromId,
                        'isTyping' => $data['isTyping']
                    ]));
                }
                break;

            case 'message_read':
                if (isset($data['messageId']) && isset($this->messageStatuses[$data['messageId']])) {
                    $messageId = $data['messageId'];
                    $senderId = $this->messageStatuses[$messageId]['from'];
                    
                    if (isset($this->users[$senderId])) {
                        $this->messageStatuses[$messageId]['status'] = 'read';
                        $this->users[$senderId]['connection']->send(json_encode([
                            'type' => 'message_status',
                            'messageId' => $messageId,
                            'status' => 'read'
                        ]));
                    }
                }
                break;
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        
        if (isset($this->users[$conn->resourceId])) {
            $this->users[$conn->resourceId]['online'] = false;
            $this->users[$conn->resourceId]['last_seen'] = date('Y-m-d H:i:s');
            $this->broadcastUserList();
            unset($this->users[$conn->resourceId]);
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Hata: {$e->getMessage()}\n";
        $conn->close();
    }

    protected function broadcastUserList() {
        $userList = [];
        foreach ($this->users as $id => $user) {
            $userList[] = [
                'id' => $id,
                'username' => $user['username'],
                'online' => $user['online'],
                'last_seen' => $user['last_seen']
            ];
        }
        
        foreach ($this->clients as $client) {
            $client->send(json_encode([
                "type" => "user_list",
                "users" => $userList
            ]));
        }
    }
}

// Sunucu olarak çalıştırılıyorsa
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
// Web tarayıcısından erişiliyorsa
else {
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp Benzeri Chat</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link href="https://emoji-css.afeld.me/emoji.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #00a884;
            --secondary-color: #667781;
            --chat-bg: #efeae2;
            --message-out: #d9fdd3;
            --message-in: #ffffff;
            --header-bg: #f0f2f5;
        }

        body { 
            padding: 20px; 
            background-color: #111b21;
            color: #e9edef;
            height: 100vh;
            margin: 0;
            font-family: Segoe UI, Helvetica Neue, Helvetica, Lucida Grande, Arial, Ubuntu, Cantarell, Fira Sans, sans-serif;
        }

        .chat-container {
            background-color: #222e35;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.4);
            overflow: hidden;
            height: 90vh;
            margin-top: 20px;
        }

        #messages { 
            height: calc(90vh - 140px);
            overflow-y: auto; 
            padding: 20px;
            background-color: var(--chat-bg);
            background-image: url('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADIAAAAyBAMAAADsEZWCAAAAG1BMVEVHcEzn5+f///+/v7/f39+fn5+Pj4+vr6+AgID4ZvVsAAAACXBIWXMAAA7DAAAOwwHHb6hkAAAAQklEQVQ4jWNgQAX8/AwMDPYKDAz8Bgwg4MEABhwOxAkmBnwAWYyRYYC1YCQYBpDqsmHgsuEfmBQJIkU+gkgWQgIAz/0Inf8hHBgAAAAASUVORK5CYII=');
            background-repeat: repeat;
            color: #111b21;
        }

        .message {
            margin-bottom: 10px;
            padding: 8px 12px;
            border-radius: 8px;
            max-width: 65%;
            word-wrap: break-word;
            position: relative;
            box-shadow: 0 1px 0.5px rgba(0,0,0,.13);
        }

        .message.sent {
            background-color: var(--message-out);
            margin-left: auto;
            border-top-right-radius: 8px;
            border-bottom-right-radius: 0;
        }

        .message.received {
            background-color: var(--message-in);
            border-top-left-radius: 8px;
            border-bottom-left-radius: 0;
        }

        .message::before {
            content: "";
            position: absolute;
            bottom: 0;
            width: 12px;
            height: 12px;
        }

        .message.sent::before {
            right: -12px;
            border-bottom-left-radius: 16px;
            box-shadow: -6px 0 0 var(--message-out);
            background-color: var(--chat-bg);
        }

        .message.received::before {
            left: -12px;
            border-bottom-right-radius: 16px;
            box-shadow: 6px 0 0 var(--message-in);
            background-color: var(--chat-bg);
        }

        .message-time {
            font-size: 0.7rem;
            color: var(--secondary-color);
            position: absolute;
            bottom: 4px;
            right: 8px;
            margin-left: 8px;
        }

        .message-status {
            margin-left: 4px;
            font-size: 0.8rem;
            color: var(--secondary-color);
        }

        .user-list {
            border-right: 1px solid #222e35;
            height: 90vh;
            overflow-y: auto;
            background-color: #111b21;
        }

        .user-item {
            padding: 12px 15px;
            border-bottom: 1px solid #222e35;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .user-item:hover {
            background-color: #2a3942;
        }

        .user-item.active {
            background-color: #2a3942;
        }

        .user-status {
            font-size: 0.8rem;
            color: var(--secondary-color);
        }

        .user-avatar {
            width: 49px;
            height: 49px;
            background-color: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-size: 1.2rem;
            color: white;
            text-transform: uppercase;
        }

        .chat-header {
            padding: 10px 16px;
            background-color: #202c33;
            border-bottom: 1px solid #222e35;
            height: 60px;
        }

        .chat-input {
            padding: 10px;
            background-color: #202c33;
            border-top: 1px solid #222e35;
        }

        .input-group {
            background: #2a3942;
            border-radius: 8px;
            padding: 8px;
        }

        .form-control {
            background-color: transparent;
            border: none;
            border-radius: 8px;
            color: #e9edef;
            padding: 8px 12px;
        }

        .form-control:focus {
            box-shadow: none;
            background-color: transparent;
            color: #e9edef;
        }

        .form-control::placeholder {
            color: #8696a0;
        }

        .typing-indicator {
            font-size: 0.8rem;
            color: var(--primary-color);
            padding: 5px 15px;
            font-style: italic;
        }

        .online-status {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }

        .online-status.online {
            background-color: var(--primary-color);
        }

        .online-status.offline {
            background-color: var(--secondary-color);
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: #008f6f;
            border-color: #008f6f;
        }

        .btn-send {
            width: 40px;
            height: 40px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin-left: 8px;
        }

        .emoji-picker {
            position: absolute;
            bottom: 100%;
            left: 0;
            z-index: 1000;
            display: none;
        }

        .emoji-btn {
            background: none;
            border: none;
            color: #8696a0;
            font-size: 1.5rem;
            padding: 0 8px;
            cursor: pointer;
        }

        .emoji-btn:hover {
            color: var(--primary-color);
        }

        /* Scrollbar Tasarımı */
        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: #374045;
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #445055;
        }

        /* Login Form */
        .login-container {
            background-color: #222e35;
            border-radius: 10px;
            padding: 30px;
            max-width: 400px;
            margin: 40px auto;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .login-container h3 {
            color: var(--primary-color);
            margin-bottom: 25px;
            text-align: center;
        }

        .form-label {
            color: #e9edef;
        }

        .login-input {
            background-color: #2a3942;
            border: 1px solid #374045;
            color: #e9edef;
        }

        .login-input:focus {
            background-color: #2a3942;
            border-color: var(--primary-color);
            color: #e9edef;
        }

    </style>
</head>
<body>
    <div class="container">
        <div id="loginForm">
            <div class="login-container">
                <h3> Chat</h3>
                <div class="mb-3">
                    <label for="username" class="form-label">Kullanıcı Adınız</label>
                    <input type="text" class="form-control login-input" id="username" placeholder="Kullanıcı adınızı girin...">
                </div>
                <button class="btn btn-primary w-100" onclick="join()">Sohbete Katıl</button>
            </div>
        </div>

        <div id="chatForm" style="display: none;">
            <div class="row chat-container">
                <div class="col-md-4 p-0">
                    <div class="chat-header">
                        <div class="d-flex align-items-center">
                            <div class="user-avatar" id="myAvatar"></div>
                            <div>
                                <h6 class="mb-0" id="myUsername"></h6>
                                <small class="user-status">çevrimiçi</small>
                            </div>
                        </div>
                    </div>
                    <div class="user-list" id="userList"></div>
                </div>
                <div class="col-md-8 p-0">
                    <div class="chat-header">
                        <div class="d-flex align-items-center">
                            <div class="user-avatar" id="selectedUserAvatar"></div>
                            <div>
                                <h6 class="mb-0" id="selectedUserHeader">Sohbet başlatmak için kullanıcı seçin</h6>
                                <small class="user-status" id="selectedUserStatus"></small>
                            </div>
                        </div>
                    </div>
                    <div id="messages"></div>
                    <div class="typing-indicator" id="typingIndicator" style="display: none;"></div>
                    <div class="chat-input">
                        <div class="input-group">
                            <button class="emoji-btn" onclick="toggleEmojiPicker()">
                                <i class="bi bi-emoji-smile"></i>
                            </button>
                            <div class="emoji-picker" id="emojiPicker">
                                <!-- Emoji picker will be initialized here -->
                            </div>
                            <input type="text" id="message" class="form-control" placeholder="Mesaj yazın...">
                            <button class="btn btn-primary btn-send" onclick="sendMessage()">
                                <i class="bi bi-send"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@joeattardi/emoji-button@4.6.0/dist/index.min.js"></script>
    <script>
        let ws;
        let myUsername = '';
        let selectedUser = null;
        let conversations = {};
        let typingTimeout;
        let picker;

        function initEmojiPicker() {
            picker = new EmojiButton({
                position: 'top-start',
                theme: 'dark',
                autoHide: false,
                rows: 4,
                recentsCount: 16,
            });

            const messageInput = document.getElementById('message');
            const emojiBtn = document.querySelector('.emoji-btn');

            picker.on('emoji', selection => {
                messageInput.value += selection.emoji;
                messageInput.focus();
            });

            emojiBtn.addEventListener('click', () => {
                picker.togglePicker(emojiBtn);
            });
        }

        function connect() {
            ws = new WebSocket('ws://localhost:8080');

            ws.onopen = () => {
                console.log('Bağlantı kuruldu');
                initEmojiPicker();
            };

            ws.onmessage = (e) => {
                const data = JSON.parse(e.data);
                
                switch(data.type) {
                    case 'connection_success':
                        console.log('Bağlantı başarılı');
                        break;
                    
                    case 'users_list':
                        updateUserList(data.users);
                        break;
                    
                    case 'private_message':
                        displayMessage(data);
                        // Okundu bilgisi gönder
                        if (selectedUser && data.from === selectedUser.id) {
                            ws.send(JSON.stringify({
                                type: 'read_receipt',
                                messageId: data.messageId
                            }));
                        }
                        break;

                    case 'typing':
                        if (selectedUser && data.from === selectedUser.id) {
                            const indicator = document.getElementById('typingIndicator');
                            if (data.isTyping) {
                                indicator.textContent = `${data.fromUsername} yazıyor...`;
                                indicator.style.display = 'block';
                            } else {
                                indicator.style.display = 'none';
                            }
                        }
                        break;

                    case 'message_status':
                        updateMessageStatus(data.messageId, data.status);
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
                document.getElementById('loginForm').style.display = 'none';
                document.getElementById('chatContainer').style.display = 'flex';
                
                ws.send(JSON.stringify({
                    type: 'join',
                    username: myUsername
                }));
            }
            if (myUsername) {
                ws.send(JSON.stringify({
                    type: 'join',
                    username: myUsername
                }));
                document.getElementById('loginForm').style.display = 'none';
                document.getElementById('chatForm').style.display = 'block';
                document.getElementById('myUsername').textContent = myUsername;
            }
        }

        function updateUserList(users) {
            const userList = document.getElementById('userList');
            userList.innerHTML = '';
            
            users.forEach(user => {
                if (user.username !== myUsername) {
                    const div = document.createElement('div');
                    div.className = `user-item ${selectedUser?.id === user.id ? 'active' : ''}`;
                    div.innerHTML = `
                        <div class="d-flex align-items-center">
                            <div class="user-avatar">
                                ${user.username.charAt(0).toUpperCase()}
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-0">${user.username}</h6>
                                <small class="user-status">
                                    <span class="online-status ${user.online ? 'online' : 'offline'}"></span>
                                    ${user.online ? 'çevrimiçi' : 'son görülme: ' + formatLastSeen(user.last_seen)}
                                </small>
                            </div>
                        </div>
                    `;
                    div.onclick = () => selectUser(user);
                    userList.appendChild(div);
                }
            });
        }

        function selectUser(user) {
            selectedUser = user;
            document.querySelectorAll('.user-item').forEach(item => item.classList.remove('active'));
            document.querySelector(`.user-item:nth-child(${Array.from(document.querySelectorAll('.user-item')).findIndex(el => el.textContent.includes(user.username)) + 1})`).classList.add('active');
            
            document.getElementById('selectedUserHeader').textContent = user.username;
            document.getElementById('selectedUserAvatar').textContent = user.username.charAt(0).toUpperCase();
            document.getElementById('selectedUserStatus').innerHTML = `
                <span class="online-status ${user.online ? 'online' : 'offline'}"></span>
                ${user.online ? 'çevrimiçi' : 'son görülme: ' + formatLastSeen(user.last_seen)}
            `;
            
            document.getElementById('messages').innerHTML = '';
            if (conversations[user.id]) {
                conversations[user.id].forEach(msg => displayMessage(msg));
            }
        }

        function sendMessage() {
            const messageInput = document.getElementById('message');
            const message = messageInput.value.trim();
            
            if (message && selectedUser) {
                const messageData = {
                    type: 'private_message',
                    to: selectedUser.id,
                    message: message,
                    fromUsername: myUsername
                };
                
                ws.send(JSON.stringify(messageData));
                messageInput.value = '';
            }
        }

        function displayMessage(data) {
            if (!selectedUser) return;

            const isRelevantMessage = 
                (data.from === selectedUser.id && data.fromUsername !== myUsername) || 
                (data.fromUsername === myUsername && data.to === selectedUser.id);

            if (!isRelevantMessage) return;

            const messages = document.getElementById('messages');
            const div = document.createElement('div');
            const isSent = data.fromUsername === myUsername;
            
            div.className = `message ${isSent ? 'sent' : 'received'}`;
            div.innerHTML = `
                ${data.message}
                <div class="message-time">
                    ${data.timestamp || formatTime(new Date())}
                    ${isSent ? `<span class="message-status" data-message-id="${data.messageId}">
                        ${getStatusIcon(data.status)}
                    </span>` : ''}
                </div>
            `;
            
            messages.appendChild(div);
            messages.scrollTop = messages.scrollHeight;

            // Sohbeti kaydet
            const conversationId = isSent ? data.to : data.from;
            if (!conversations[conversationId]) {
                conversations[conversationId] = [];
            }
            conversations[conversationId].push(data);
        }

        function updateMessageStatus(messageId, status) {
            const statusElement = document.querySelector(`[data-message-id="${messageId}"]`);
            if (statusElement) {
                statusElement.innerHTML = getStatusIcon(status);
            }
        }

        function getStatusIcon(status) {
            switch(status) {
                case 'sent':
                    return '<i class="bi bi-check"></i>';
                case 'delivered':
                    return '<i class="bi bi-check-all"></i>';
                case 'read':
                    return '<i class="bi bi-check-all text-primary"></i>';
                default:
                    return '<i class="bi bi-clock"></i>';
            }
        }

        function formatTime(date) {
            return date.toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
        }

        function formatLastSeen(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const diff = now - date;
            
            if (diff < 60000) return 'az önce';
            if (diff < 3600000) return `${Math.floor(diff/60000)} dakika önce`;
            if (diff < 86400000) return `${Math.floor(diff/3600000)} saat önce`;
            return date.toLocaleDateString('tr-TR');
        }

        // Yazıyor... göstergesi
        document.getElementById('message').addEventListener('input', function() {
            if (selectedUser) {
                ws.send(JSON.stringify({
                    type: 'typing',
                    to: selectedUser.id,
                    isTyping: true
                }));
                
                clearTimeout(typingTimeout);
                typingTimeout = setTimeout(() => {
                    ws.send(JSON.stringify({
                        type: 'typing',
                        to: selectedUser.id,
                        isTyping: false
                    }));
                }, 1000);
            }
        });

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
