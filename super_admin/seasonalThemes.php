<?php
require_once '../db.php';
require_once '../lib/seasonal_theme.php';
require_once 'login_verification.php';

if (!access_is_super_admin()) {
    http_response_code(403);
    exit('Only Super Admins can manage seasonal themes.');
}

$themes = [
    'none' => [
        'name' => 'DistrictOne Default',
        'description' => 'Return to the regular DistrictOne colors.',
        'accent' => '#7cb9ff',
        'soft' => '#eaf3ff',
        'season' => 'All year',
        'icon' => '',
    ],
    'new-year' => [
        'name' => 'New Year',
        'description' => 'A celebratory gold and midnight-blue palette.',
        'accent' => '#a77b18',
        'soft' => '#fbf3d9',
        'season' => 'January',
        'icon' => '🎆',
    ],
    'chinese-new-year' => [
        'name' => 'Chinese New Year',
        'description' => 'A festive red-and-gold look with lanterns and firecrackers.',
        'accent' => '#c83e3e',
        'soft' => '#fff0d1',
        'season' => 'Lunar New Year',
        'icon' => '🏮',
    ],
    'valentines' => [
        'name' => "Valentine's Day",
        'description' => 'Warm rose accents and soft blush backgrounds.',
        'accent' => '#d64f78',
        'soft' => '#fff0f4',
        'season' => 'February',
        'icon' => '💝',
    ],
    'summer' => [
        'name' => 'Summer',
        'description' => 'Bright, sunny colors for the hot and dry season.',
        'accent' => '#e58a27',
        'soft' => '#fff2df',
        'season' => 'March–May',
        'icon' => '☀️',
    ],
    'rainy-season' => [
        'name' => 'Rainy Season',
        'description' => 'Cool, calm teal tones for the rainy months.',
        'accent' => '#328b9c',
        'soft' => '#e7f5f7',
        'season' => 'June–October',
        'icon' => '☔',
    ],
    'halloween' => [
        'name' => 'Halloween',
        'description' => 'A playful mix of pumpkin orange and deep violet.',
        'accent' => '#d76a24',
        'soft' => '#fff0e5',
        'season' => 'October',
        'icon' => '🎃',
    ],
    'christmas' => [
        'name' => 'Christmas',
        'description' => 'Festive evergreen accents with a warm, bright feel.',
        'accent' => '#34845b',
        'soft' => '#e9f5ed',
        'season' => 'November–December',
        'icon' => '🎄',
    ],
];

$tableAvailable = seasonal_theme_is_available($conn);
$activeTheme = $tableAvailable ? seasonal_theme_current($conn) : 'none';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = '';
    $messageType = 'error';
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');
    $themeKey = (string) ($_POST['theme_key'] ?? '');

    if (!$tableAvailable) {
        $message = 'Seasonal themes are not initialized. Run database/seasonal_theme_migration.sql and reload this page.';
    } elseif (empty($_SESSION['seasonal_theme_csrf']) || !hash_equals($_SESSION['seasonal_theme_csrf'], $csrfToken)) {
        $message = 'Your session expired. Refresh this page and try again.';
    } elseif (!isset($themes[$themeKey])) {
        $message = 'Choose one of the available themes.';
    } else {
        $stmt = $conn->prepare(
            'INSERT INTO seasonal_theme_settings (id, theme_key) VALUES (1, ?) ON DUPLICATE KEY UPDATE theme_key = VALUES(theme_key)'
        );
        if ($stmt === false) {
            error_log('Seasonal themes: unable to prepare theme update: ' . $conn->error);
            $message = 'The theme could not be saved. Check the server error log.';
        } else {
            $stmt->bind_param('s', $themeKey);
            if ($stmt->execute()) {
                $_SESSION['seasonal_theme_flash'] = ['success', $themes[$themeKey]['name'] . ' theme activated for everyone.'];
                $stmt->close();
                header('Location: seasonalThemes.php');
                exit();
            }

            error_log('Seasonal themes: unable to save theme: ' . $stmt->error);
            $message = 'The theme could not be saved. Check the server error log.';
            $stmt->close();
        }
    }

    $_SESSION['seasonal_theme_flash'] = [$messageType, $message];
    header('Location: seasonalThemes.php');
    exit();
}

if (empty($_SESSION['seasonal_theme_csrf'])) {
    $_SESSION['seasonal_theme_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['seasonal_theme_csrf'];
$flash = $_SESSION['seasonal_theme_flash'] ?? null;
unset($_SESSION['seasonal_theme_flash']);
?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default"
    data-assets-path="../assets/" data-template="vertical-menu-template-free">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Seasonal Themes | DistrictOne</title>
    <link rel="icon" type="image/x-icon" href="../assets/img/favicon/districtone.png">
    <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css">
    <link rel="stylesheet" href="../assets/vendor/css/core.css">
    <link rel="stylesheet" href="../assets/vendor/css/theme-default.css">
    <link rel="stylesheet" href="../assets/css/demo.css">
    <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css">
    <script src="../assets/vendor/js/helpers.js"></script>
    <script src="../assets/js/config.js"></script>
    <style>
        body { background: #f7f8fa; }
        .seasonal-page { max-width: 1120px; margin: 32px auto; padding: 0 20px 40px; }
        .seasonal-heading { margin-bottom: 22px; }
        .seasonal-heading h1 { font-size: 25px; font-weight: 700; margin-bottom: 5px; }
        .seasonal-heading p, .seasonal-muted { color: #767e8c; margin: 0; }
        .seasonal-status, .seasonal-card { background: #fff; border: 1px solid #e8eaee; border-radius: 14px; box-shadow: 0 8px 24px -12px rgba(20,20,43,.1); }
        .seasonal-status { padding: 16px 20px; margin-bottom: 18px; display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .seasonal-status strong { display: block; font-size: 15px; margin-bottom: 3px; }
        .seasonal-status-label { display: inline-flex; align-items: center; gap: 8px; font-weight: 700; }
        .seasonal-status-dot { width: 10px; height: 10px; border-radius: 50%; background: var(--active-accent); }
        .seasonal-alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; }
        .seasonal-alert.success { background: #e8f7ee; color: #176b37; }
        .seasonal-alert.error { background: #fff0f0; color: #a12626; }
        .seasonal-card { padding: 22px; }
        .seasonal-card h2 { font-size: 17px; font-weight: 700; margin-bottom: 5px; }
        .seasonal-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; margin-top: 20px; }
        .seasonal-choice { position: relative; display: block; cursor: pointer; margin: 0; }
        .seasonal-choice input { position: absolute; opacity: 0; }
        .seasonal-choice-content { position: relative; display: block; height: 100%; min-height: 142px; padding: 16px; overflow: hidden; border: 1px solid #e8eaee; border-radius: 11px; transition: border-color .15s ease, box-shadow .15s ease, background .15s ease; }
        .seasonal-choice input:checked + .seasonal-choice-content { border: 2px solid var(--theme-accent); padding: 15px; background: var(--theme-soft); box-shadow: 0 4px 14px -8px var(--theme-accent); }
        .seasonal-choice input:focus-visible + .seasonal-choice-content { outline: 3px solid rgba(37, 99, 235, .35); outline-offset: 2px; }
        .seasonal-choice-top { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 12px; }
        .seasonal-choice-top strong { font-size: 15px; color: #303744; }
        .seasonal-swatch { display: flex; gap: 5px; }
        .seasonal-swatch span { width: 13px; height: 13px; border-radius: 50%; }
        .seasonal-choice p { min-height: 38px; margin: 0 0 10px; color: #767e8c; font-size: 13px; line-height: 1.45; }
        .seasonal-choice small { color: #9299a5; font-weight: 600; }
        .seasonal-art { position: absolute; top: 42px; right: 14px; font-size: 34px; line-height: 1; opacity: .9; filter: drop-shadow(0 3px 5px rgba(20, 20, 43, .12)); }
        .seasonal-choice p, .seasonal-choice small { position: relative; z-index: 1; max-width: calc(100% - 38px); }
        .seasonal-active-badge { display: inline-flex; margin-left: 6px; padding: 3px 7px; border-radius: 999px; background: var(--theme-soft); color: var(--theme-accent); font-size: 10px; font-weight: 700; vertical-align: middle; }
        .seasonal-actions { display: flex; justify-content: flex-end; margin-top: 20px; }
        .seasonal-actions .btn { min-width: 176px; }
        @media (max-width: 575px) {
            .seasonal-page { padding: 0 12px 30px; }
            .seasonal-card { padding: 16px; }
            .seasonal-status { align-items: flex-start; flex-direction: column; }
            .seasonal-actions .btn { width: 100%; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <main class="content-wrapper">
        <div class="seasonal-page">
            <header class="seasonal-heading">
                <h1>Seasonal Themes</h1>
                <p>Set a shared seasonal look for DistrictOne. Changes apply to everyone using the site.</p>
            </header>

            <?php if ($flash): ?>
                <div class="seasonal-alert <?= htmlspecialchars($flash[0], ENT_QUOTES, 'UTF-8'); ?>" role="status">
                    <?= htmlspecialchars($flash[1], ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if (!$tableAvailable): ?>
                <div class="seasonal-alert error" role="alert">
                    Seasonal themes are not initialized. Run <code>database/seasonal_theme_migration.sql</code>
                    against the DistrictOne database, then reload this page.
                </div>
            <?php endif; ?>

            <section class="seasonal-status" aria-live="polite">
                <div>
                    <strong>Currently active</strong>
                    <span class="seasonal-muted">The active selection is shared across the site.</span>
                </div>
                <span class="seasonal-status-label" style="--active-accent: <?= htmlspecialchars($themes[$activeTheme]['accent'], ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="seasonal-status-dot"></span>
                    <?= htmlspecialchars($themes[$activeTheme]['name'], ENT_QUOTES, 'UTF-8'); ?>
                </span>
            </section>

            <section class="seasonal-card">
                <h2>Choose a theme</h2>
                <p class="seasonal-muted">Activate one when you are ready, or switch back to the default look at any time.</p>

                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="seasonal-grid">
                        <?php foreach ($themes as $key => $theme): ?>
                            <label class="seasonal-choice" style="--theme-accent: <?= htmlspecialchars($theme['accent'], ENT_QUOTES, 'UTF-8'); ?>; --theme-soft: <?= htmlspecialchars($theme['soft'], ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="radio" name="theme_key" value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" <?= $activeTheme === $key ? 'checked' : ''; ?>>
                                <span class="seasonal-choice-content">
                                    <span class="seasonal-choice-top">
                                        <strong>
                                            <?= htmlspecialchars($theme['name'], ENT_QUOTES, 'UTF-8'); ?>
                                            <?php if ($activeTheme === $key): ?><span class="seasonal-active-badge">Active</span><?php endif; ?>
                                        </strong>
                                        <?php if ($theme['icon'] !== ''): ?><span class="seasonal-art" aria-hidden="true"><?= htmlspecialchars($theme['icon'], ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                                        <span class="seasonal-swatch" aria-hidden="true">
                                            <span style="background: <?= htmlspecialchars($theme['accent'], ENT_QUOTES, 'UTF-8'); ?>"></span>
                                            <span style="background: <?= htmlspecialchars($theme['soft'], ENT_QUOTES, 'UTF-8'); ?>"></span>
                                        </span>
                                    </span>
                                    <p><?= htmlspecialchars($theme['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    <small><?= htmlspecialchars($theme['season'], ENT_QUOTES, 'UTF-8'); ?></small>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="seasonal-actions">
                        <button type="submit" class="btn btn-primary" <?= !$tableAvailable ? 'disabled' : ''; ?>>Activate selected theme</button>
                    </div>
                </form>
            </section>
        </div>
    </main>
    <script src="../assets/vendor/js/menu.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>
