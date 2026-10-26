CREATE TABLE IF NOT EXISTS seasonal_theme_settings (
    id TINYINT UNSIGNED NOT NULL,
    theme_key ENUM('none', 'new-year', 'chinese-new-year', 'valentines', 'summer', 'rainy-season', 'halloween', 'christmas') NOT NULL DEFAULT 'none',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE seasonal_theme_settings
    MODIFY theme_key ENUM('none', 'new-year', 'chinese-new-year', 'valentines', 'summer', 'rainy-season', 'halloween', 'christmas') NOT NULL DEFAULT 'none';

INSERT INTO seasonal_theme_settings (id, theme_key)
VALUES (1, 'none')
ON DUPLICATE KEY UPDATE id = VALUES(id);
