<?php
require_once __DIR__ . '/config.php';

/**
 * Converts any YouTube URL format to an embed URL.
 * Handles: youtube.com/watch?v=ID, youtu.be/ID, youtube.com/embed/ID, shorts, etc.
 * Returns empty string if URL is not a valid YouTube URL.
 */
function youtube_embed_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    $video_id = '';

    // youtu.be/ID
    if (preg_match('/youtu\.be\/([a-zA-Z0-9_-]{11})/', $url, $m)) {
        $video_id = $m[1];
    }
    // youtube.com/watch?v=ID
    elseif (preg_match('/youtube\.com\/watch\?.*v=([a-zA-Z0-9_-]{11})/', $url, $m)) {
        $video_id = $m[1];
    }
    // youtube.com/embed/ID (already embed)
    elseif (preg_match('/youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/', $url, $m)) {
        $video_id = $m[1];
    }
    // youtube.com/shorts/ID
    elseif (preg_match('/youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})/', $url, $m)) {
        $video_id = $m[1];
    }
    // youtube.com/v/ID
    elseif (preg_match('/youtube\.com\/v\/([a-zA-Z0-9_-]{11})/', $url, $m)) {
        $video_id = $m[1];
    }

    if ($video_id === '') {
        return '';
    }

    return 'https://www.youtube.com/embed/' . $video_id . '?rel=0&modestbranding=1';
}

/**
 * Formats a date string (Y-m-d) to "April 13, 2026".
 */
function format_date(string $date): string
{
    if ($date === '' || $date === '0000-00-00') {
        return '';
    }
    $ts = strtotime($date);
    if ($ts === false) {
        return $date;
    }
    return date('F j, Y', $ts);
}

/**
 * Escapes a string for safe HTML output.
 */
function sanitize(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Returns the project display name for a given project ID.
 * Returns the ID itself if not found.
 */
function project_name(string $id): string
{
    $projects = PROJECTS;
    return isset($projects[$id]) ? $projects[$id]['name'] : $id;
}

/**
 * Returns the project location for a given project ID.
 */
function project_location(string $id): string
{
    $projects = PROJECTS;
    return isset($projects[$id]) ? $projects[$id]['location'] : '';
}

/**
 * Generates a cryptographically random hex token.
 */
function generate_token(int $len = 32): string
{
    return bin2hex(random_bytes($len));
}

/**
 * Returns a CSS color class/badge color for a project.
 */
function project_badge_color(string $id): string
{
    $colors = [
        'motif'   => '#0053a4',
        'octave'  => '#874545',
        'ochre'   => '#d69100',
        'cadence' => '#666b4a',
        'saarang' => '#2a7a4b',
        'umang'   => '#5b4a8a',
    ];
    return $colors[$id] ?? '#3a3a3a';
}

/**
 * Decodes a JSON field from the DB; returns an array on failure.
 */
function json_decode_safe(string $json): array
{
    if ($json === '' || $json === 'null') {
        return [];
    }
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Returns the public listing page URL for a given project.
 */
function project_listing_url(string $id): string
{
    $links = PROJECT_LINKS;
    return $links[$id] ?? '#';
}
