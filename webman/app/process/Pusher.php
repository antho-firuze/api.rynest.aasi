<?php

namespace app\process;

use Workerman\Connection\TcpConnection;
use Firuze\Jwt\JwtToken;

class Pusher
{
    // Store online user connections
    protected static $connections = [];

    public function onConnect(TcpConnection $connection)
    {
        echo "onConnect\n";
    }

    public function onWebSocketConnect(TcpConnection $connection, $header)
    {
        echo "onWebSocketConnect\n";

        // Check the app_key
        if (!preg_match('/\/app\/([^\/^\?^ ]+)/', (string)$header, $match)) {
            echo "app_key not found\n$header\n";
            // $connection->pauseRecv();
            $connection->send(json_encode(['error' => 'App_key required']));
            $connection->close();
            return;
        }
        echo "found app_key !\n";
        $app_key = $match[1];
        // echo $app_key;

        // Check the token
        $token = $_GET['token'] ?? null;
        if ($token) {
            $connection->send(json_encode(['error' => 'Token required']));
            $connection->close();
            return;
        }

        // Parsing JWT tokens
        try {
            JwtToken::verify(1, $token);
            $userId = JwtToken::getCurrentId();;
            // $decoded = JWT::decode($token, new Key('your_secret_key', 'HS256'));
            // $userId = $decoded->user_id;
        } catch (\Exception $e) {
            $connection->send(json_encode(['error' => 'Invalid token']));
            $connection->close();
            return;
        }

        // Store user connections
        $connection->userId = $userId;
        self::$connections[$userId] = $connection;

        // Notify everyone that the user is online
        // $this->broadcast([
        //     'type' => 'user_online',
        //     'user_id' => $userId,
        // ]);
    }

    public function onMessage(TcpConnection $connection, $data)
    {
        $message = json_decode($data, true);
        if (!isset($message['type'])) {
            $connection->send(json_encode(['error' => 'Invalid message format']));
            return;
        }

        // Distributed according to message type
        switch ($message['type']) {
            case 'private_message':
                $this->handlePrivateMessage($connection, $message);
                break;

            case 'group_message':
                $this->handleGroupMessage($connection, $message);
                break;

            case 'public_message':
                $this->handlePublicMessage($connection, $message);
                break;

            default:
                $connection->send(json_encode(['error' => 'Unknown message type']));
        }
    }

    public function onClose(TcpConnection $connection)
    {
        if (isset($connection->userId)) {
            unset(self::$connections[$connection->userId]);

            // Notify everyone that user is offline
            $this->broadcast([
                'type' => 'user_offline',
                'user_id' => $connection->userId,
            ]);
        }
    }

    // Private chat
    protected function handlePrivateMessage($connection, $message)
    {
        $toUserId = $message['to_user_id'] ?? null;
        $content = $message['content'] ?? '';

        if ($toUserId && isset(self::$connections[$toUserId])) {
            self::$connections[$toUserId]->send(json_encode([
                'type' => 'private_message',
                'from_user_id' => $connection->userId,
                'content' => $content,
            ]));
        } else {
            $connection->send(json_encode(['error' => 'User not online']));
        }
    }

    // Group chat
    protected function handleGroupMessage($connection, $message)
    {
        $groupId = $message['group_id'] ?? null;
        $content = $message['content'] ?? '';

        $groupMembers = $this->getGroupMembers($groupId);

        foreach ($groupMembers as $userId) {
            if (isset(self::$connections[$userId]) && $userId !== $connection->userId) {
                self::$connections[$userId]->send(json_encode([
                    'type' => 'group_message',
                    'from_user_id' => $connection->userId,
                    'group_id' => $groupId,
                    'content' => $content,
                ]));
            }
        }
    }

    // Public Message
    protected function handlePublicMessage($connection, $message)
    {
        $content = $message['content'] ?? '';
        $this->broadcast([
            'type' => 'public_message',
            'from_user_id' => $connection->userId,
            'content' => $content,
        ]);
    }

    // Broadcast messages to all online users
    protected function broadcast($data)
    {
        foreach (self::$connections as $conn) {
            $conn->send(json_encode($data));
        }
    }

    // Get group members (pseudocode)
    protected function getGroupMembers($groupId)
    {
        return [1, 2, 3];
    }
}
