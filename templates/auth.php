<?php
/** @var string $content */
/** @var \Core\Page\Page|null $ctx_page */
/** @var array<string, mixed> $attributes */

use Core\Template\AssetHelper;

$authForm = (string) ($attributes['auth_form'] ?? 'login');
if (!in_array($authForm, ['login', 'reg', 'recover'], true)) {
    $authForm = 'login';
}

$metaTitle = $ctx_page !== null ? (string) ($ctx_page->getMetaTitle() ?? $ctx_page->name ?? 'Вход') : 'Вход';
$tabs = [
    'login' => ['/login', 'Вход'],
    'reg' => ['/reg', 'Регистрация'],
    'recover' => ['/recover', 'Восстановление'],
];

$assetManifest = AssetHelper::getManifest();
$basePath = AssetHelper::getBasePath();
$omgoLogoWithClose = false;
?>
<!doctype html>
<html lang="ru" class="theme-light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($metaTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        html, body { margin: 0; padding: 0; min-height: 100%; }
        body.page-auth { background: #111; color: #1a1a1a; }
        .page-login {
            min-height: 100vh;
            display: flex;
            background: url(/project/overrides/assets/light_bg.jpg) center / cover no-repeat;
            position: relative;
        }
        .page-login::after {
            content: "";
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.4);
            pointer-events: none;
            z-index: 1;
        }
        .page-login__stage,
        .page-login__panel { position: relative; z-index: 2; }
        .page-login__stage { flex: 1 1 auto; }
        .page-login__panel {
            box-sizing: border-box;
            flex: 0 0 440px;
            width: 440px;
            max-width: 100%;
            max-height: 100vh;
            overflow: auto;
            background: #fff;
            padding: 32px 36px 40px;
        }
        .auth-head { margin-bottom: 20px; }
        .auth-head__title {
            font: 600 22px/1.3 Arial, sans-serif;
            margin: 0 0 12px;
        }
        .page-login .brand-logo { display: flex; align-items: center; line-height: 0; }
        .page-login .brand-logo__mobile,
        .page-login .brand-sidebar-close { display: none; }
        .auth-tabs { display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 20px; }
        .auth-tab {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #e4e4e4;
            color: #2574bb;
            text-decoration: none;
            font: 600 14px/1.2 Arial, sans-serif;
            background: #fff;
        }
        .auth-tab.is-active {
            background: #ff7f22;
            border-color: #ff7f22;
            color: #fff;
        }
        .auth-form { display: flex; flex-direction: column; gap: 12px; }
        .auth-note { margin: 0; font: 14px/1.4 Arial, sans-serif; color: #444; }
        .form-field { display: flex; flex-direction: column; gap: 4px; }
        .form-field label { font: 13px/1.3 Arial, sans-serif; color: #333; }
        .auth-form input,
        .auth-form textarea {
            box-sizing: border-box;
            width: 100%;
            border: 1px solid #d5d5d5;
            border-radius: 8px;
            padding: 10px 12px;
            font: 15px/1.4 Arial, sans-serif;
        }
        .auth-form input.error,
        .auth-form textarea.error { border-color: #c33; }
        .auth-form .error-msg { color: #c33; font: 12px/1.3 Arial, sans-serif; }
        .auth-honeypot {
            position: absolute !important;
            left: -10000px !important;
            width: 1px !important;
            height: 1px !important;
            opacity: 0 !important;
        }
        .auth-submit {
            border: 0;
            border-radius: 8px;
            background: #ff7f22;
            color: #fff;
            font: 600 15px/1 Arial, sans-serif;
            padding: 12px 16px;
            cursor: pointer;
        }
        .auth-submit:disabled { opacity: 0.6; cursor: default; }
        @media (max-width: 990px) {
            .page-login { display: block; padding: 24px; }
            .page-login__stage { display: none; }
            .page-login__panel { width: 100%; max-width: none; flex: 1 1 auto; max-height: none; border-radius: 12px; }
        }
        @media (max-width: 768px) {
            .page-login .brand-logo__desktop { display: none; }
            .page-login .brand-logo__mobile { display: block; }
        }
        @media (max-width: 456px) {
            .page-login { padding: 0; }
            .page-login__panel { border-radius: 0; min-height: 100vh; }
        }
    </style>
</head>
<body class="page-auth">
<div class="page-login">
    <div class="page-login__stage"></div>
    <div class="page-login__panel">
        <div class="auth-head">
            <p class="auth-head__title">Лабораторная диагностика</p>
            <?php include (defined('PROJECT_PATH') ? PROJECT_PATH : '') . '/UC/layout/logo.php'; ?>
        </div>
        <nav class="auth-tabs" aria-label="Формы доступа">
            <?php foreach ($tabs as $code => $tab) { ?>
                <a class="auth-tab<?= $code === $authForm ? ' is-active' : '' ?>" href="<?= htmlspecialchars($tab[0], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($tab[1], ENT_QUOTES, 'UTF-8') ?></a>
            <?php } ?>
        </nav>
        <?= $content ?>
    </div>
</div>
<script src="<?= AssetHelper::versionedUrl('/core-assets/core.js') ?>"></script>
<script>
    window.ASSET_MANIFEST = <?= json_encode($assetManifest ?? []) ?>;
    window.ASSET_BASE_PATH = <?= json_encode($basePath) ?>;
    document.addEventListener('DOMContentLoaded', function () {
        if (!window.PluginHydrator) {
            return;
        }
        window.Project = new PluginHydrator();
        window.Project.init();
    });
</script>
</body>
</html>
