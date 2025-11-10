<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Config\SettingsFactory;
use danog\MadelineProto\API;
use danog\MadelineProto\RPCErrorException;
use RuntimeException;
use Throwable;

final class LoginController
{
    private const SESSION_KEY = 'madeline_session';
    private ?API $client = null;
    private ?bool $authorized = null;

    public function show(): void
    {
        $client = $this->getClient();
        $status = $this->getAuthStatus($client);
        $flash = $this->consumeFlash();

        include __DIR__ . '/../../Views/login.php';
    }

    public function sendCode(): void
    {
        $phone = trim($_POST['phone'] ?? '');
        if ($phone === '') {
            $this->setFlash('error', 'شماره تلفن را وارد کنید.');
            $this->redirect('/');
        }

        try {
            $MadelineProto = $this->getClient();
            if ($this->isAuthorized($MadelineProto)) {
                $this->setFlash('success', 'حساب از قبل لاگین شده است.');
                $this->redirect('/');
            }

            $MadelineProto->phoneLogin($phone);

            $_SESSION[self::SESSION_KEY]['phone'] = $phone;
            $this->setFlash('success', 'کد تایید ارسال شد.');
        } catch (RPCErrorException $e) {
            $this->setFlash('error', 'خطای تلگرام: ' . $e->getMessage());
        } catch (Throwable $e) {
            $this->setFlash('error', 'خطای داخلی: ' . $e->getMessage());
        }

        $this->redirect('/');
    }

    public function verifyCode(): void
    {
        $phoneCode = trim($_POST['code'] ?? '');
        if ($phoneCode === '') {
            $this->setFlash('error', 'کد تایید را وارد کنید.');
            $this->redirect('/');
        }

        try {
            $client = $this->getClient();
            $client->completePhoneLogin($phoneCode);
            $this->authorized = true;

            $this->setFlash('success', 'ورود با موفقیت انجام شد.');
        } catch (RPCErrorException $e) {
            if ($e->rpc === 'SESSION_PASSWORD_NEEDED') {
                $_SESSION[self::SESSION_KEY]['need_password'] = true;
                $this->setFlash('error', 'رمز دو مرحله‌ای لازم است.');
            } else {
                $this->setFlash('error', 'خطای تلگرام: ' . $e->getMessage());
            }
        } catch (Throwable $e) {
            $this->setFlash('error', 'خطای داخلی: ' . $e->getMessage());
        }

        $this->redirect('/');
    }

    public function submitPassword(): void
    {
        $password = trim($_POST['password'] ?? '');
        if ($password === '') {
            $this->setFlash('error', 'رمز دو مرحله‌ای را وارد کنید.');
            $this->redirect('/');
        }

        try {
            $client = $this->getClient();
            $client->complete2faLogin($password);

            $this->setFlash('success', 'ورود کامل شد.');
            unset($_SESSION[self::SESSION_KEY]['need_password']);
            $this->authorized = true;
        } catch (RPCErrorException $e) {
            $this->setFlash('error', 'خطای تلگرام: ' . $e->getMessage());
        } catch (Throwable $e) {
            $this->setFlash('error', 'خطای داخلی: ' . $e->getMessage());
        }

        $this->redirect('/');
    }

    public function logout(): void
    {
        $_SESSION[self::SESSION_KEY] = [];
        try {
            $client = $this->getClient();
            $client->logout();
            $this->setFlash('success', 'خروج انجام شد.');
            $this->authorized = false;
        } catch (Throwable $e) {
            $this->setFlash('error', 'خطا در خروج: ' . $e->getMessage());
        }

        $this->redirect('/');
    }

    private function getClient(): API
    {
        if ($this->client instanceof API) {
            return $this->client;
        }

        $sessionFile = getenv('SESSION_FILE');
        if ($sessionFile === false || $sessionFile === '') {
            throw new RuntimeException('SESSION_FILE env variable is not set.');
        }

        $settings = SettingsFactory::make();

        $this->client = new API($sessionFile, $settings);

        return $this->client;
    }

    private function getAuthStatus(API $client): array
    {
        $state = $_SESSION[self::SESSION_KEY] ?? [];
        return [
            'phone' => $state['phone'] ?? '',
            'need_password' => $state['need_password'] ?? false,
            'authorized' => $this->isAuthorized($client),
        ];
    }

    private function isAuthorized(API $client): bool
    {
        if ($this->authorized !== null) {
            return $this->authorized;
        }

        try {
            $this->authorized = !empty($client->getSelf());
        } catch (Throwable $e) {
            $this->authorized = false;
        }

        return $this->authorized;
    }

    private function setFlash(string $key, string $message): void
    {
        $_SESSION['flash'][$key] = $message;
    }

    private function consumeFlash(): array
    {
        $flash = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);

        return $flash;
    }

    private function redirect(string $to): void
    {
        header('Location: ' . $to);
        exit;
    }
}
