<?php

function seasonal_theme_is_available(mysqli $conn): bool
{
    $result = $conn->query(
        "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seasonal_theme_settings' LIMIT 1"
    );
    if ($result === false) {
        error_log('Seasonal themes: unable to check settings table: ' . $conn->error);
        return false;
    }

    return $result->num_rows > 0;
}

function seasonal_theme_current(mysqli $conn): string
{
    if (!seasonal_theme_is_available($conn)) {
        return 'none';
    }

    $result = $conn->query("SELECT theme_key FROM seasonal_theme_settings WHERE id = 1");
    if ($result === false) {
        error_log('Seasonal themes: unable to read active theme: ' . $conn->error);
        return 'none';
    }

    $setting = $result->fetch_assoc();
    $theme = $setting['theme_key'] ?? 'none';
    $allowedThemes = ['none', 'new-year', 'chinese-new-year', 'valentines', 'summer', 'rainy-season', 'halloween', 'christmas'];
    if (!in_array($theme, $allowedThemes, true)) {
        error_log('Seasonal themes: invalid active theme stored in database.');
        return 'none';
    }

    return $theme;
}

function seasonal_theme_render(mysqli $conn): void
{
    $theme = htmlspecialchars(seasonal_theme_current($conn), ENT_QUOTES, 'UTF-8');
    $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $baseUrl = '';
    if (preg_match('~/(?:modules|super_admin|admin|mis|user)/~', $scriptPath, $match, PREG_OFFSET_CAPTURE)) {
        $baseUrl = substr($scriptPath, 0, $match[0][1]);
    } else {
        $baseUrl = rtrim(str_replace('\\', '/', dirname($scriptPath)), '/');
        if ($baseUrl === '.') {
            $baseUrl = '';
        }
    }
    $stylesheetUrl = $baseUrl . '/assets/css/seasonal-themes.css';

    $themeJson = json_encode($theme, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $stylesheetJson = json_encode($stylesheetUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    echo '<script>
        document.documentElement.setAttribute("data-seasonal-theme", ' . $themeJson . ');
        var seasonalThemeStylesheet = document.createElement("link");
        seasonalThemeStylesheet.rel = "stylesheet";
        seasonalThemeStylesheet.href = ' . $stylesheetJson . ';
        document.head.appendChild(seasonalThemeStylesheet);
    </script>' . "\n";
}
