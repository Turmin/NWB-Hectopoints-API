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
        adminRedirect(['type' => 'danger', 'message' => 'Ongeldige sessie. Probeer opnieuw.']);
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

        adminRedirect(['type' => 'danger', 'message' => 'Ongeldige gebruikersnaam of wachtwoord.']);
    }

    if ($action === 'create_admin') {
        adminRequireCsrf();
        if (adminLoadCredentials() !== null) {
            adminRedirect(['type' => 'danger', 'message' => 'Er bestaat al een admin-account.']);
        }

        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $passwordRepeat = (string)($_POST['password_repeat'] ?? '');

        if ($username === '' || strlen($password) < 10 || $password !== $passwordRepeat) {
            adminRedirect(['type' => 'danger', 'message' => 'Kies een gebruikersnaam en twee gelijke wachtwoorden van minimaal 10 tekens.']);
        }

        $path = adminCredentialsPath();
        $content = "<?php\nreturn " . var_export([
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => date('c'),
        ], true) . ";\n";

        if (file_put_contents($path, $content, LOCK_EX) === false) {
            adminRedirect(['type' => 'danger', 'message' => 'Admin credentials konden niet worden opgeslagen.']);
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
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="css/admin-style.css" rel="stylesheet">
</head>
<body>
    <div class="container admin-container">
        <div class="login-shell">
            <div class="admin-card">
                <div class="admin-header">
                    <h1 class="h4 mb-1"><?= h($credentials === null ? 'Hectometer Admin Setup' : 'Hectometer Admin Login') ?></h1>
                    <div><?= h($credentials === null ? 'Maak de eerste admin login aan.' : 'Log in om NWB data en import te beheren.') ?></div>
                </div>
                <div class="card-body">
                    <?php if ($flash): ?>
                        <div class="alert alert-<?= h($flash['type'] ?? 'success') ?>">
                            <?= h($flash['message'] ?? '') ?>
                        </div>
                    <?php endif; ?>
            <?php if ($credentials === null): ?>
                <p class="text-muted">Er is nog geen admin credentials-bestand gevonden. Het bestand wordt buiten de webroot aangemaakt.</p>
                <pre><code><?= h($credentialPath) ?></code></pre>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?= h(adminCsrf()) ?>">
                    <input type="hidden" name="action" value="create_admin">
                    <div class="mb-3">
                        <label class="form-label" for="username">Gebruikersnaam</label>
                        <input class="form-control" id="username" name="username" autocomplete="username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Wachtwoord</label>
                        <input class="form-control" id="password" name="password" type="password" autocomplete="new-password" minlength="10" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password_repeat">Herhaal wachtwoord</label>
                        <input class="form-control" id="password_repeat" name="password_repeat" type="password" autocomplete="new-password" minlength="10" required>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Admin aanmaken</button>
                </form>
            <?php else: ?>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?= h(adminCsrf()) ?>">
                    <input type="hidden" name="action" value="login">
                    <div class="mb-3">
                        <label class="form-label" for="login_username">Gebruikersnaam</label>
                        <input class="form-control" id="login_username" name="username" autocomplete="username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="login_password">Wachtwoord</label>
                        <input class="form-control" id="login_password" name="password" type="password" autocomplete="current-password" required>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Inloggen</button>
                </form>
            <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
    <?php
}
