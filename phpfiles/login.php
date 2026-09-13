<?php

use Core\Security\RedirectValidator;
use Services\AuthService;

/** @var AuthService|null $auth */
$auth = auth();

$redirectTo = RedirectValidator::fromGet('redirect', '/manager/');

if ($auth && $auth->isAuthenticated()) {
    header('Location: ' . $redirectTo, true, 302);
    exit;
}

$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $redirect = RedirectValidator::fromPost('redirect', $redirectTo);

    if ($auth && $auth->login($username, $password)) {
        header('Location: ' . $redirect, true, 302);
        exit;
    }
    $error = 'Неверный логин или пароль';
}
?>
<section class="form form-manager-login" id="manager-login" style="margin-top: 80px">
    <div class="form-container">
        <div class="form-title">
            <div>Введите логин и пароль учётной записи</div>
        </div>
        <form method="post" action="" autocomplete="on">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirectTo, ENT_QUOTES, 'UTF-8') ?>">
            <div class="input-container">
                <div>
                    <label for="login-username"></label>
                    <input type="text"
                           id="login-username"
                           name="username"
                           placeholder="Логин"
                           autocomplete="username"
                           required>
                </div>
                <div>
                    <label for="login-password"></label>
                    <input type="password"
                           id="login-password"
                           name="password"
                           placeholder="Пароль"
                           autocomplete="current-password"
                           required>
                </div>
            </div>
            <?php if ($error): ?>
                <div class="error-message"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <div class="form-auth-footer">
                <button type="submit" class="buttonDark">Войти</button>
            </div>
        </form>
    </div>
</section>
