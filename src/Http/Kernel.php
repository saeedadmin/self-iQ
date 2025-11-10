<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\Controllers\LoginController;

final class Kernel
{
    public function handle(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        $controller = new LoginController();

        if ($path === '/' && $method === 'GET') {
            $controller->show();
            return;
        }

        if ($path === '/send-code' && $method === 'POST') {
            $controller->sendCode();
            return;
        }

        if ($path === '/verify-code' && $method === 'POST') {
            $controller->verifyCode();
            return;
        }

        if ($path === '/submit-password' && $method === 'POST') {
            $controller->submitPassword();
            return;
        }

        if ($path === '/logout' && $method === 'POST') {
            $controller->logout();
            return;
        }

        http_response_code(404);
        echo 'Not Found';
    }
}
