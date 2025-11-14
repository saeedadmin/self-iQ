<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Config\SettingsFactory;
use RuntimeException;
use Tak\Liveproto\Network\Client;
use Tak\Liveproto\Enums\Authentication;
use Throwable;

final class LoginController
{
    private const SESSION_KEY = 'liveproto_session';
    private ?Client $client = null;

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
            $client = $this->getClient();
            $client->connect();

            if ($client->isAuthorized()) {
                $this->setFlash('success', 'حساب از قبل لاگین شده است.');
                $this->redirect('/');
            }

            $phoneNumber = preg_replace('/[^\d]/', '', $phone);
            $sentCode = $client->send_code(phone_number: $phoneNumber);

            $_SESSION[self::SESSION_KEY] = array_merge(
                $_SESSION[self::SESSION_KEY] ?? [],
                [
                    'phone' => $phone,
                    'phone_code_hash' => $sentCode->phone_code_hash ?? null,
                    'phone_registered' => $sentCode->phone_registered ?? null,
                    'next_type' => $sentCode->next_type ?? null,
                    'timeout' => $sentCode->timeout ?? null,
                ]
            );

            $this->setFlash('success', 'کد تایید ارسال شد.');
        } catch (Throwable $e) {
            $this->setFlash('error', 'خطا در ارسال کد: ' . $e->getMessage());
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
            $state = $_SESSION[self::SESSION_KEY] ?? [];
            if (!empty($state['phone_code_hash'])) {
                $client->send_code(phone_number: preg_replace('/[^\d]/', '', $state['phone'] ?? ''), phone_code_hash: $state['phone_code_hash']);
            }
            if (!$client->isAuthorized()) {
                $client->connect();
            }
            $client->sign_in(code: $phoneCode);

            $this->setFlash('success', 'ورود با موفقیت انجام شد.');
        } catch (Throwable $e) {
            if (str_contains($e->getMessage(), 'SESSION_PASSWORD_NEEDED')) {
                $_SESSION[self::SESSION_KEY]['need_password'] = true;
                $this->setFlash('error', 'رمز دو مرحله‌ای لازم است.');
            } else {
                $this->setFlash('error', 'خطا در تایید کد: ' . $e->getMessage());
            }
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
            $client->sign_in(password: $password);

            unset($_SESSION[self::SESSION_KEY]['need_password']);
            $this->setFlash('success', 'ورود کامل شد.');
        } catch (Throwable $e) {
            $this->setFlash('error', 'خطا در تایید رمز: ' . $e->getMessage());
        }

        $this->redirect('/');
    }

    public function logout(): void
    {
        $_SESSION[self::SESSION_KEY] = [];
        try {
            $client = $this->getClient();
            $client->disconnect();
            $client->logout();
            $this->setFlash('success', 'خروج انجام شد.');
        } catch (Throwable $e) {
            $this->setFlash('error', 'خطا در خروج: ' . $e->getMessage());
        }

        $this->redirect('/');
    }

    private function getClient(): Client
    {
        if ($this->client instanceof Client) {
            return $this->client;
        }

        $sessionName = getenv('SESSION_NAME');
        if ($sessionName === false || $sessionName === '') {
            throw new RuntimeException('SESSION_NAME env variable is not set.');
        }

        $settings = SettingsFactory::make();
        $this->client = new Client($sessionName, 'mysql', $settings);

        return $this->client;
    }

    private function getAuthStatus(Client $client): array
    {
        $state = $_SESSION[self::SESSION_KEY] ?? [];
        $step = $client->getStep();

        return [
            'phone' => $state['phone'] ?? '',
            'need_password' => ($state['need_password'] ?? false) || $step === Authentication::NEED_PASSWORD,
            'authorized' => $client->isAuthorized(),
        ];
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
