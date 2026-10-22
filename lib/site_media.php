<?php

function site_media_table_available(mysqli $conn): bool
{
    static $available = null;

    if ($available === null) {
        $result = $conn->query(
            "SELECT COUNT(*) AS table_count
             FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = 'site_media'"
        );
        $available = (int) $result->fetch_assoc()['table_count'] > 0;
    }

    return $available;
}

function site_media_carousel_images(mysqli $conn): array
{
    if (!site_media_table_available($conn)) {
        return site_media_default_carousel_images();
    }

    $images = [];
    $result = $conn->query(
        "SELECT file_name FROM site_media WHERE media_type = 'carousel' ORDER BY position ASC"
    );

    while ($row = $result->fetch_assoc()) {
        $images[] = '../uploads/site_media/' . rawurlencode($row['file_name']);
    }

    if ($images !== []) {
        return $images;
    }

    return site_media_default_carousel_images();
}

function site_media_default_carousel_images(): array
{
    return ['../assets/img/carousel/1.png'];
}

function site_media_login_background(mysqli $conn): string
{
    if (!site_media_table_available($conn)) {
        return 'assets/img/backgrounds/login.jpg';
    }

    $result = $conn->query(
        "SELECT file_name FROM site_media WHERE media_type = 'login_background' LIMIT 1"
    );
    $row = $result->fetch_assoc();

    return $row
        ? 'uploads/site_media/' . rawurlencode($row['file_name'])
        : 'assets/img/backgrounds/login.jpg';
}
