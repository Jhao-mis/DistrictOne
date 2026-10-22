CREATE TABLE IF NOT EXISTS site_media (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    media_type ENUM('carousel', 'login_background') NOT NULL,
    position TINYINT UNSIGNED NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_site_media_type_position (media_type, position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
