<?php

use Core\Security\RedirectValidator;

/** @var array<string, mixed> $attributes */

$authUser = function_exists('auth') ? auth() : null;
if ($authUser && $authUser->isAuthenticated()) {
    header('Location: /manager/', true, 302);
    exit;
}

$form = (string) ($attributes['auth_form'] ?? 'login');
if (!in_array($form, ['login', 'reg', 'recover'], true)) {
    $form = 'login';
}

$h = static function (string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
};

$redirectTo = RedirectValidator::fromGet('redirect', '/manager/');
?>
<?php if ($form === 'login') { ?>
<form class="auth-form" method="post" action="/jsapi/form/auth" data-plugin="form-handler" data-alias="form.auth" autocomplete="on">
    <input type="hidden" name="redirect" value="<?= $h($redirectTo) ?>">
    <div class="form-field">
        <label for="auth-username">Логин</label>
        <input id="auth-username" type="text" name="username" placeholder="E-Mail, логин или телефон" autocomplete="username" required>
    </div>
    <div class="form-field">
        <label for="auth-password">Пароль</label>
        <input id="auth-password" type="password" name="password" placeholder="Пароль" autocomplete="current-password" required>
    </div>
    <button type="submit" class="auth-submit">Войти</button>
</form>
<?php } elseif ($form === 'recover') { ?>
<form class="auth-form" method="post" action="/jsapi/form/recover" data-plugin="form-handler" data-alias="form.recover" autocomplete="off">
    <input type="text" name="email" value="" tabindex="-1" autocomplete="off" class="auth-honeypot" aria-hidden="true">
    <p class="auth-note">Укажите e-mail учётной записи. Новый пароль придёт письмом.</p>
    <div class="form-field">
        <label for="auth-semail">E-Mail</label>
        <input id="auth-semail" type="email" name="semail" placeholder="Укажите свой E-Mail" autocomplete="email" required>
    </div>
    <button type="submit" class="auth-submit">Восстановить пароль</button>
</form>
<?php } else { ?>
<form class="auth-form" method="post" action="/jsapi/form/reg" data-plugin="form-handler" data-alias="form.reg" autocomplete="off">
    <input type="text" name="email" value="" tabindex="-1" autocomplete="off" class="auth-honeypot" aria-hidden="true">
    <p class="auth-note">Поля, требующие заполнения, помечены *.</p>
    <div class="form-field">
        <label for="reg-surname">Фамилия *</label>
        <input id="reg-surname" type="text" name="surname" placeholder="Фамилия *" autocomplete="family-name" required>
    </div>
    <div class="form-field">
        <label for="reg-name">Имя *</label>
        <input id="reg-name" type="text" name="name" placeholder="Имя *" autocomplete="given-name" required>
    </div>
    <div class="form-field">
        <label for="reg-patron">Отчество *</label>
        <input id="reg-patron" type="text" name="patron" placeholder="Отчество *" autocomplete="additional-name" required>
    </div>
    <div class="form-field">
        <label for="reg-phone">Телефон *</label>
        <input id="reg-phone" type="tel" name="phone" placeholder="+7 (999) 123-45-67" autocomplete="tel" required>
    </div>
    <div class="form-field">
        <label for="reg-semail">Емейл *</label>
        <input id="reg-semail" type="email" name="semail" placeholder="Емейл *" autocomplete="email" required>
    </div>
    <div class="form-field">
        <label for="reg-med">Лечебно-профилактическое учреждение *</label>
        <input id="reg-med" type="text" name="med" placeholder="Лечебно-профилактическое учреждение *" required>
    </div>
    <div class="form-field">
        <label for="reg-address">Адрес *</label>
        <textarea id="reg-address" name="address" rows="3" placeholder="Адрес *, например: Москва, Ленинградский проспект, 11" required></textarea>
    </div>
    <button type="submit" class="auth-submit">Зарегистрироваться</button>
</form>
<?php } ?>
