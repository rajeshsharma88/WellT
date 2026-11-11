<?php
/**
 * =========================================================================
 * 3-PAGE CLOAKING & FUNNEL ROUTER (Flow B)
 * =========================================================================
 * 
 * 1. Safe Page (safe.html): Compliant Hindi Male Wellness Blog
 *    -> Shown to Ad Reviewers, Bots, Crawlers & Desktop users
 * 2. Quiz Page (wpage.html): Age Selection Questionnaire
 *    -> Shown to genuine mobile ad traffic (or reached via blog CTA button)
 * 3. Offer Page: https://aarogyaindia.net/wella/
 *    -> Main sales landing page reached after completing the quiz
 */

$config = [
    // Safe / White Page (Compliant Hindi Blog for Ad Reviewers & Bots):
    'safe_page'          => 'safe.html',

    // Quiz Page (Age Questionnaire for Real Mobile Ad Traffic):
    'quiz_page'          => 'wpage.html',

    // What real visitors should see first:
    // 'quiz' = Real ad clicks see the Age Quiz immediately (Highest Conversion)
    // 'blog' = Real ad clicks see the Blog first, then click button to go to Quiz
    'human_entry'        => 'quiz',

    // Filter Controls:
    'block_known_bots'   => true, // Send all ad review crawlers to safe.html
    'mobile_only'        => true, // Send desktop/laptop users to safe.html (ad reviewers use desktop)
    'block_ipv6'         => true, // Send IPv6 traffic to safe.html

    // Warmup Buffer:
    // Set to 0 while testing on your own phone!
    // Set to 20-30 when submitting a new ad campaign for review.
    'warmup_clicks'      => 0,
];

// -------------------------------------------------------------------------
// Helper: Detect visitor IP
// -------------------------------------------------------------------------
function getVisitorIp() {
    $headers = [
        'HTTP_CF_CONNECTING_IP', // Cloudflare
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'REMOTE_ADDR'
    ];
    foreach ($headers as $key) {
        if (!empty($_SERVER[$key])) {
            $list = explode(',', $_SERVER[$key]);
            $candidate = trim($list[0]);
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

function servePage($fileName, $actionHeader = '', $reason = '') {
    if (!empty($actionHeader)) {
        header('X-Cloaking-Action: ' . $actionHeader);
    }
    if (!empty($reason)) {
        header('X-Filter-Reason: ' . preg_replace('/[^a-zA-Z0-9 .:_/-]/', '', $reason));
    }
    
    $filePath = __DIR__ . '/' . $fileName;
    if (file_exists($filePath) && is_file($filePath)) {
        include($filePath);
    } else {
        echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Welcome</title></head><body><h1>Welcome</h1><p>Page loading...</p></body></html>";
    }
    exit;
}

// -------------------------------------------------------------------------
// Manual Preview Override (e.g. ?page=blog or ?page=quiz)
// -------------------------------------------------------------------------
if (isset($_GET['page'])) {
    if ($_GET['page'] === 'blog') {
        servePage($config['safe_page'], 'Manual-Preview', 'Blog requested via ?page=blog');
    } elseif ($_GET['page'] === 'quiz') {
        servePage($config['quiz_page'], 'Manual-Preview', 'Quiz requested via ?page=quiz');
    }
}

$ip = getVisitorIp();
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

// Check 1: Warmup buffer (First N clicks always see safe blog)
if ($config['warmup_clicks'] > 0) {
    $counterFile = __DIR__ . '/.traffic_counter.dat';
    $current = (int)(@file_get_contents($counterFile) ?: 0) + 1;
    @file_put_contents($counterFile, (string)$current, LOCK_EX);

    if ($current <= $config['warmup_clicks']) {
        servePage($config['safe_page'], 'SafePage-Warmup', 'Warmup click: ' . $current . '/' . $config['warmup_clicks']);
    }
}

// Check 2: IPv6 Filter
if ($config['block_ipv6'] && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
    servePage($config['safe_page'], 'SafePage-IPv6', 'IPv6 address detected');
}

// Check 3: Bot & Ad Review Crawler Filter
if ($config['block_known_bots']) {
    $bots = [
        'googlebot', 'adsbot', 'mediapartners-google', 'feedfetcher-google',
        'facebookexternalhit', 'facebot', 'facebookcatalog',
        'tiktokbot', 'bytespider', 'bytedance',
        'bingbot', 'msnbot', 'adidxbot',
        'slurp', 'duckduckbot', 'baiduspider', 'yandexbot',
        'twitterbot', 'linkedinbot', 'pinterestbot', 'snapchat',
        'crawler', 'spider', 'headlesschrome', 'lighthouse', 'inspect'
    ];
    $uaLower = strtolower($userAgent);
    foreach ($bots as $bot) {
        if (strpos($uaLower, $bot) !== false) {
            servePage($config['safe_page'], 'SafePage-Bot', 'Bot detected: ' . $bot);
        }
    }
}

// Check 4: Mobile-Only Filter (Ad reviewers usually browse from Desktop PCs)
if ($config['mobile_only']) {
    $isMobile = preg_match('/(android|iphone|ipad|ipod|mobile|blackberry|iemobile|opera mini)/i', $userAgent);
    if (!$isMobile) {
        servePage($config['safe_page'], 'SafePage-Desktop', 'Desktop user filtered');
    }
}

// -------------------------------------------------------------------------
// All checks passed -> Genuine Mobile Visitor
// -------------------------------------------------------------------------
if ($config['human_entry'] === 'blog') {
    servePage($config['safe_page'], 'Human-Blog', 'Blog entry selected');
} else {
    servePage($config['quiz_page'], 'Human-Quiz', 'Real mobile visitor served Quiz');
}

