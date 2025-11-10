<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>ورود به ربات</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .container {
            background: #fff;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.1);
            width: 320px;
        }
        h1 {
            margin-top: 0;
            font-size: 20px;
            text-align: center;
        }
        form {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 16px;
        }
        label {
            font-size: 14px;
        }
        input[type="text"],
        input[type="password"] {
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #ccc;
            font-size: 14px;
        }
        button {
            padding: 10px;
            font-size: 15px;
            border: none;
            border-radius: 6px;
            background: #0061ff;
            color: #fff;
            cursor: pointer;
        }
        .alert {
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 16px;
            font-size: 14px;
        }
        .alert-success {
            background: #e0f9e7;
            color: #137333;
        }
        .alert-error {
            background: #fdecea;
            color: #b71c1c;
        }
        .status {
            font-size: 13px;
            color: #666;
            text-align: center;
            margin-bottom: 16px;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>ورود به ربات</h1>

    <?php if (!empty($flash['success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($flash['success'], ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if (!empty($flash['error'])): ?>
        <div class="alert alert-error"><?= htmlspecialchars($flash['error'], ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="status">
        <?php if (!empty($status['authorized'])): ?>
            ✅ ربات به حساب تلگرام متصل است.
        <?php elseif (!empty($status['phone'])): ?>
            شماره وارد شده: <strong><?= htmlspecialchars($status['phone'], ENT_QUOTES, 'UTF-8') ?></strong>
        <?php else: ?>
            لطفاً شماره خود را وارد کنید.
        <?php endif; ?>
    </div>

    <form method="post" action="/send-code">
        <label for="phone">شماره تلفن (با کد کشور)</label>
        <input type="text" name="phone" id="phone" placeholder="مثلاً +989123456789" value="<?= isset($status['phone']) ? htmlspecialchars($status['phone'], ENT_QUOTES, 'UTF-8') : '' ?>" required>
        <button type="submit">ارسال کد</button>
    </form>

    <form method="post" action="/verify-code">
        <label for="code">کد تایید</label>
        <input type="text" name="code" id="code" placeholder="کد ۵ رقمی" required>
        <button type="submit">تایید کد</button>
    </form>

    <?php if (!empty($status['need_password'])): ?>
        <form method="post" action="/submit-password">
            <label for="password">رمز دو مرحله‌ای</label>
            <input type="password" name="password" id="password" placeholder="رمز حساب تلگرام" required>
            <button type="submit">ارسال رمز</button>
        </form>
    <?php endif; ?>

    <form method="post" action="/logout">
        <button type="submit">خروج از حساب</button>
    </form>
</div>
</body>
</html>
