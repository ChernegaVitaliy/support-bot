<?php

namespace App\Controller;

use App\Services\TelegramService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MiniAppController
{
    public function __construct(private TelegramService $telegram)
    {
    }

    #[Route('/', name: 'miniapp_index')]
    public function index(): Response
    {
        return new Response('<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Support Mini App</title>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h1 { color: #333; font-size: 24px; }
        p { color: #666; line-height: 1.5; }
        .btn { background: #0088cc; color: white; border: none; padding: 12px 24px; border-radius: 8px; font-size: 16px; cursor: pointer; margin-top: 10px; }
        .btn:hover { background: #0077b3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎫 Support Mini App</h1>
        <p>This is a Telegram Mini App built with Symfony.</p>
        <p id="user-info">Loading user info...</p>
        <button class="btn" onclick="sendData()">Send Test Data</button>
    </div>
    <script>
        const tg = window.Telegram.WebApp;
        tg.expand();

        const user = tg.initDataUnsafe.user;
        if (user) {
            document.getElementById("user-info").innerHTML = 
                "Hello, " + (user.first_name || "User") + "!<br>ID: " + user.id;
        }

        async function sendData() {
            const response = await fetch("/api/miniapp/test", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ text: "Hello from Mini App!" })
            });
            const data = await response.json();
            alert(data.message);
        }
    </script>
</body>
</html>');
    }

    #[Route('/api/miniapp/test', name: 'miniapp_test', methods: ['POST'])]
    public function testApi(): JsonResponse
    {
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        return new JsonResponse([
            'message' => 'Mini App API is working!',
            'received' => $data,
            'bot_ready' => true,
        ]);
    }
}
