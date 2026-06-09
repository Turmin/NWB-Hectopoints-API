<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../lib/Nwb.php';

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

function adminCredentialsPaths(): array
{
    return [
        dirname(__DIR__, 2) . '/admin.credentials.php',
        dirname(__DIR__, 2) . '/hectometer.admin.credentials.php',
        dirname(__DIR__, 2) . '/knmi.admin.credentials.php',
        __DIR__ . '/admin_credentials.php',
    ];
}

function adminCredentialsPath(): string
{
    foreach (adminCredentialsPaths() as $path) {
        if (is_file($path)) {
            return $path;
        }
    }

    return adminCredentialsPaths()[0];
}

function adminLoadCredentials(): ?array
{
    $path = adminCredentialsPath();
    if (!is_file($path)) {
        return null;
    }

    $credentials = require $path;
    if (!is_array($credentials) || empty($credentials['username']) || empty($credentials['password_hash'])) {
        return null;
    }

    return $credentials;
}

function adminIsLoggedIn(): bool
{
    return ($_SESSION['admin_logged_in'] ?? false) === true;
}

function adminCsrf(): string
{
    return (string)($_SESSION['admin_csrf'] ?? '');
}

function adminRequireCsrf(): void
{
    $token = (string)($_POST['csrf'] ?? '');
    if (!hash_equals(adminCsrf(), $token)) {
        adminRedirect(['type' => 'alert', 'message' => 'Ongeldige sessie. Probeer opnieuw.']);
    }
}

function adminFlash(): ?array
{
    $flash = $_SESSION['admin_flash'] ?? null;
    unset($_SESSION['admin_flash']);

    return is_array($flash) ? $flash : null;
}

function adminRedirect(?array $flash = null, string $target = '/admin/'): void
{
    if ($flash !== null) {
        $_SESSION['admin_flash'] = $flash;
    }

    header('Location: ' . $target);
    exit;
}

function adminHandleAuthPost(): void
{
    $action = (string)($_POST['action'] ?? '');
    if ($action === '') {
        return;
    }

    if ($action === 'logout') {
        adminRequireCsrf();
        $_SESSION = [];
        session_destroy();
        session_start();
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
        adminRedirect(['type' => 'success', 'message' => 'Uitgelogd.']);
    }

    if ($action === 'login') {
        adminRequireCsrf();
        $credentials = adminLoadCredentials();
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if (
            $credentials !== null
            && hash_equals((string)$credentials['username'], $username)
            && password_verify($password, (string)$credentials['password_hash'])
        ) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user'] = $username;
            adminRedirect(['type' => 'success', 'message' => 'Ingelogd.']);
        }

        adminRedirect(['type' => 'alert', 'message' => 'Ongeldige gebruikersnaam of wachtwoord.']);
    }

    if ($action === 'create_admin') {
        adminRequireCsrf();
        if (adminLoadCredentials() !== null) {
            adminRedirect(['type' => 'alert', 'message' => 'Er bestaat al een admin-account.']);
        }

        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $passwordRepeat = (string)($_POST['password_repeat'] ?? '');

        if ($username === '' || strlen($password) < 10 || $password !== $passwordRepeat) {
            adminRedirect(['type' => 'alert', 'message' => 'Kies een gebruikersnaam en twee gelijke wachtwoorden van minimaal 10 tekens.']);
        }

        $path = adminCredentialsPath();
        $content = "<?php\nreturn " . var_export([
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => date('c'),
        ], true) . ";\n";

        if (file_put_contents($path, $content, LOCK_EX) === false) {
            adminRedirect(['type' => 'alert', 'message' => 'Admin credentials konden niet worden opgeslagen.']);
        }

        adminRedirect(['type' => 'success', 'message' => 'Admin-account aangemaakt. Log nu in.']);
    }
}

function adminRenderAuthPage(string $title = 'NWB beheer'): void
{
    $credentials = adminLoadCredentials();
    $flash = adminFlash();
    $credentialPath = adminCredentialsPath();
    ?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?></title>
    <link rel="stylesheet" href="/admin/css/admin-style.css">
</head>
<body>
    <main class="admin-container login-shell">
        <header class="admin-header">
            <div>
                <p class="eyebrow">Hectometerpaaltjes</p>
                <h1><?= h($credentials === null ? 'Admin setup' : 'Inloggen') ?></h1>
            </div>
            <a class="button button-light" href="/">Website</a>
        </header>

        <?php if ($flash): ?>
            <section class="admin-card <?= h($flash['type'] ?? 'success') ?>">
                <p><?= h($flash['message'] ?? '') ?></p>
            </section>
        <?php endif; ?>

        <section class="admin-card">
            <?php if ($credentials === null): ?>
                <h2>Admin-account aanmaken</h2>
                <p>Er is nog geen admin credentials-bestand gevonden. Het bestand wordt buiten de webroot aangemaakt.</p>
                <pre><code><?= h($credentialPath) ?></code></pre>
                <form method="post" class="admin-form">
                    <input type="hidden" name="csrf" value="<?= h(adminCsrf()) ?>">
                    <input type="hidden" name="action" value="create_admin">
                    <label>
                        <span>Gebruikersnaam</span>
                        <input name="username" type="text" autocomplete="username" required>
                    </label>
                    <label>
                        <span>Wachtwoord</span>
                        <input name="password" type="password" autocomplete="new-password" required minlength="10">
                    </label>
                    <label>
                        <span>Herhaal wachtwoord</span>
                        <input name="password_repeat" type="password" autocomplete="new-password" required minlength="10">
                    </label>
                    <button class="button" type="submit">Account aanmaken</button>
                </form>
            <?php else: ?>
                <h2>Inloggen</h2>
                <form method="post" class="admin-form">
                    <input type="hidden" name="csrf" value="<?= h(adminCsrf()) ?>">
                    <input type="hidden" name="action" value="login">
                    <label>
                        <span>Gebruikersnaam</span>
                        <input name="username" type="text" autocomplete="username" required>
                    </label>
                    <label>
                        <span>Wachtwoord</span>
                        <input name="password" type="password" autocomplete="current-password" required>
                    </label>
                    <button class="button" type="submit">Inloggen</button>
                </form>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
    <?php
}
