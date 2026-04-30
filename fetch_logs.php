<?php
require_once __DIR__ . '/sitestats-config.php';
// Ensure headers for CORS
$allowed_origins = get_sitestats_allowed_origins([
    "https://allroundwebsite.com",
    "https://www.allroundwebsite.com"
]);

if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
}
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

// Parse incoming data
$data = json_decode(file_get_contents('php://input'), true);
$password = $data['password'] ?? '';

// Authenticate password
$config = get_sitestats_config();
$storedHash = $config['password_hash'] ?? '';
if ($storedHash) {
    if (!password_verify($password, $storedHash)) {
        echo json_encode(['success' => false, 'message' => 'Incorrect password']);
        exit;
    }
} else {
    if ($password !== 'your-password-here') {
        echo json_encode(['success' => false, 'message' => 'Incorrect password']);
        exit;
    }
}

// Initialize logs array
$logs = [
    'pageviews' => [],
    'sessions' => [],
    'illegal_requests' => [],
    'errors_without_ips' => 0, // Initialize as count
    'top_pages' => [],
    'referrer_counts' => [],
    'bots_daily' => [],
    'bots_latest' => '',
    'bots_total' => 0,
    'bots_top_pages' => [],
    'bots_top_families' => []
];

// Read logs from files
$pageviews_log = is_readable('pageviews.log')
    ? file('pageviews.log', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
    : [];
$sessions_log = is_readable('sessions.log')
    ? file('sessions.log', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
    : [];
$error_log = is_readable('error.log')
    ? file('error.log', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
    : [];
$bots_log = is_readable('bots.log')
    ? file('bots.log', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
    : [];

// Load security watch location cache
$security_location_file = __DIR__ . '/security-log-location.json';
$security_locations = [];
$security_locations_dirty = false;
if (is_readable($security_location_file)) {
    $security_locations = json_decode(file_get_contents($security_location_file), true);
    if (!is_array($security_locations)) {
        $security_locations = [];
    }
}

// Initialize variables for new features
$page_counts = []; // To store counts of each page
$referrer_counts = [
    'Facebook' => 0,
    'Google' => 0,
    'Local' => 0
];
$proof_of_work_counts = [];


// Process pageviews log
foreach ($pageviews_log as $line) {
    list($timestamp, $ip, $url, $os, $browser) = explode(', ', $line);
    $logs['pageviews'][] = [
        'timestamp' => $timestamp,
        'ip' => $ip,
        'url' => $url,
        'os' => $os,
        'browser' => $browser
    ];

    if (stripos($url, '____proof-of-work') !== false) {
        $powTimestamp = strtotime($timestamp);
        $powTimestamp = $powTimestamp ?: 0;
        if (!isset($proof_of_work_counts[$ip])) {
            $proof_of_work_counts[$ip] = [
                'count' => 0,
                'last_attempt' => $timestamp,
                'last_attempt_timestamp' => $powTimestamp
            ];
        }
        $proof_of_work_counts[$ip]['count']++;
        if ($powTimestamp > ($proof_of_work_counts[$ip]['last_attempt_timestamp'] ?? 0)) {
            $proof_of_work_counts[$ip]['last_attempt_timestamp'] = $powTimestamp;
            $proof_of_work_counts[$ip]['last_attempt'] = $timestamp;
        }
    }

    // Extract the page path from the URL
    $parsed_url = parse_url($url);
    $path = $parsed_url['path'] ?? '/'; // Use '/' if path is empty

    // Increment page count
    if (isset($page_counts[$path])) {
        $page_counts[$path]++;
    } else {
        $page_counts[$path] = 1;
    }

    // Check for referrers
    if (strpos($url, 'fbclid') !== false) {
        $referrer_counts['Facebook']++;
    }
    if (strpos($url, 'gclid') !== false) {
        $referrer_counts['Google']++;
    }
    if (strpos($url, 'file:') === 0) {
        $referrer_counts['Local']++;
    }
}

// Sort pages by count in descending order
arsort($page_counts);

// Get top 10 pages
$top_pages = array_slice($page_counts, 0, 10, true);

// Add to logs
$logs['top_pages'] = $top_pages;
$logs['referrer_counts'] = $referrer_counts;

function detectBotFamily($userAgent) {
    $ua = strtolower($userAgent);
    $families = [
        'Googlebot' => 'googlebot',
        'Bingbot' => 'bingbot',
        'AhrefsBot' => 'ahrefsbot',
        'SemrushBot' => 'semrushbot',
        'YandexBot' => 'yandex',
        'DuckDuckBot' => 'duckduckbot',
        'Applebot' => 'applebot',
        'Baidu' => 'baiduspider',
        'Bytespider' => 'bytespider',
        'Facebook' => 'facebookexternalhit',
        'Twitterbot' => 'twitterbot',
        'LinkedInBot' => 'linkedinbot',
        'Slackbot' => 'slackbot',
        'PetalBot' => 'petalbot',
        'MJ12bot' => 'mj12bot',
        'DotBot' => 'dotbot',
        'GPTBot' => 'gptbot',
        'CCBot' => 'ccbot'
    ];
    foreach ($families as $label => $needle) {
        if ($needle && strpos($ua, $needle) !== false) {
            return $label;
        }
    }
    if (strpos($ua, 'bot') !== false || strpos($ua, 'crawler') !== false || strpos($ua, 'spider') !== false) {
        return 'Other bots';
    }
    return 'Unknown';
}

// Process bots log
$bot_daily = [];
$bot_family_totals = [];
$bot_page_counts = [];
$bot_total = 0;
$bot_latest_ts = 0;
$bot_latest = '';
foreach ($bots_log as $line) {
    if (!preg_match('/^\[(.*?)\]/', $line, $dateMatches)) {
        continue;
    }
    $dateString = $dateMatches[1];
    $timestamp = strtotime($dateString);
    if (!$timestamp) {
        continue;
    }
    $dateKey = date('Y-m-d', $timestamp);
    $userAgent = '';
    if (preg_match('/UserAgent:\s*(.*)$/', $line, $uaMatches)) {
        $userAgent = trim($uaMatches[1]);
    }
    $family = detectBotFamily($userAgent);

    if (!isset($bot_daily[$dateKey])) {
        $bot_daily[$dateKey] = [
            'date' => $dateKey,
            'total' => 0,
            'families' => []
        ];
    }
    $bot_daily[$dateKey]['total']++;
    $bot_daily[$dateKey]['families'][$family] = ($bot_daily[$dateKey]['families'][$family] ?? 0) + 1;

    $bot_family_totals[$family] = ($bot_family_totals[$family] ?? 0) + 1;
    $bot_total++;
    if ($timestamp > $bot_latest_ts) {
        $bot_latest_ts = $timestamp;
        $bot_latest = date('Y-m-d H:i:s', $timestamp);
    }

    $page = '';
    if (preg_match('/Page:\s*([^,]+),\s*UserAgent:/', $line, $pageMatches)) {
        $page = trim($pageMatches[1]);
    }
    if ($page) {
        $parsed = parse_url($page);
        $path = (is_array($parsed) && isset($parsed['path'])) ? $parsed['path'] : $page;
    } else {
        $path = 'Unknown';
    }
    $bot_page_counts[$path] = ($bot_page_counts[$path] ?? 0) + 1;
}

ksort($bot_daily);
arsort($bot_family_totals);
arsort($bot_page_counts);

$bots_top_families = [];
foreach (array_slice($bot_family_totals, 0, 10, true) as $family => $count) {
    $bots_top_families[] = ['family' => $family, 'count' => $count];
}

$bots_top_pages = [];
foreach (array_slice($bot_page_counts, 0, 10, true) as $page => $count) {
    $bots_top_pages[] = ['page' => $page, 'count' => $count];
}

$logs['bots_daily'] = array_values($bot_daily);
$logs['bots_latest'] = $bot_latest;
$logs['bots_total'] = $bot_total;
$logs['bots_top_pages'] = $bots_top_pages;
$logs['bots_top_families'] = $bots_top_families;

// Process sessions log
$session_locations = [];
foreach ($sessions_log as $line) {
    $line = trim($line);
    if ($line === '') {
        continue;
    }
    $parts = explode(', ', $line);
    if (count($parts) < 3) {
        continue;
    }
    $timestamp = array_shift($parts);
    $ip = array_shift($parts);
    $action = array_shift($parts);

    $detail = '';
    $deviceType = '';
    $location = '';
    $countryCode = '';

    if ($action === 'Session started') {
        if ($parts) {
            $deviceType = array_shift($parts);
        }
        if ($parts) {
            $countryCode = array_pop($parts);
            $location = $parts ? implode(', ', $parts) : '';
        }
    } elseif ($action === 'Session ended') {
        if ($parts) {
            $detail = array_shift($parts); // e.g., "Duration: XXX seconds"
        }
        if ($parts) {
            $deviceType = array_shift($parts);
        }
        if ($parts) {
            $countryCode = array_pop($parts);
            $location = $parts ? implode(', ', $parts) : '';
        }
    } else {
        // Unknown action
        $detail = '';
        $deviceType = '';
        $location = '';
        $countryCode = '';
    }

    $logs['sessions'][] = [
        'timestamp' => $timestamp,
        'ip' => $ip,
        'action' => $action,
        'detail' => $detail,
        'deviceType' => $deviceType,
        'location' => $location,
        'countryCode' => $countryCode
    ];

    if ($action === 'Session started' && $ip) {
        $session_locations[$ip] = $location;
    }
}

// Process error log to get illegal requests
$ip_counts = [];
$errors_without_ips_count = 0; // Initialize as count
$errors_without_ips_latest_timestamp = 0;
$errors_without_ips_latest = '';

function parseErrorContext($line) {
    if (!preg_match('/Context:\s*(.*)$/', $line, $matches)) {
        return [];
    }
    $context = $matches[1];
    $keys = ['IP', 'Geo', 'ISP', 'Org', 'ASN', 'UA', 'Referer', 'Origin', 'Method', 'URI'];
    $positions = [];
    foreach ($keys as $key) {
        $pos = strpos($context, $key . ':');
        if ($pos !== false) {
            $positions[] = ['key' => $key, 'pos' => $pos];
        }
    }
    if (!$positions) {
        return [];
    }
    usort($positions, function ($a, $b) {
        return $a['pos'] <=> $b['pos'];
    });
    $fields = [];
    $count = count($positions);
    for ($i = 0; $i < $count; $i++) {
        $key = $positions[$i]['key'];
        $start = $positions[$i]['pos'] + strlen($key . ':');
        $end = ($i + 1 < $count) ? $positions[$i + 1]['pos'] : strlen($context);
        $value = trim(substr($context, $start, $end - $start));
        $value = ltrim($value, ', ');
        if ($value !== '') {
            $fields[strtolower($key)] = $value;
        }
    }
    return $fields;
}

function getGeoCacheData($ip, $geo_cache) {
    if (!isset($geo_cache[$ip]['data']) || !is_array($geo_cache[$ip]['data'])) {
        return [];
    }
    return $geo_cache[$ip]['data'];
}

function lookupSecurityLocation($ip, array &$cache, &$dirty) {
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return '';
    }
    $entry = $cache[$ip] ?? null;
    if ($entry && !empty($entry['location'])) {
        return $entry['location'];
    }
    $url = "http://ip-api.com/json/" . urlencode($ip) . "?fields=status,message,city,regionName,country,countryCode";
    $response = @file_get_contents($url);
    if (!$response) {
        return '';
    }
    $data = json_decode($response, true);
    if (!is_array($data) || ($data['status'] ?? '') !== 'success') {
        return '';
    }
    $parts = array_filter([$data['city'] ?? '', $data['regionName'] ?? '', $data['country'] ?? '']);
    $location = $parts ? implode(', ', $parts) : '';
    if ($location) {
        $cache[$ip] = [
            'location' => $location,
            'countryCode' => $data['countryCode'] ?? '',
            'last_updated' => time()
        ];
        $dirty = true;
    }
    return $location;
}

function persistSecurityLocations($file, array $cache) {
    if (!$file) {
        return;
    }
    file_put_contents($file, json_encode($cache, JSON_PRETTY_PRINT), LOCK_EX);
}

foreach ($error_log as $line) {
    // Extract date
    if (preg_match('/^\[(.*?)\]/', $line, $dateMatches)) {
        $dateString = $dateMatches[1];
        $timestamp = strtotime($dateString);
        $formattedDate = date('Y-m-d H:i:s', $timestamp);
    } else {
        $formattedDate = 'Unknown Date';
        $timestamp = 0;
    }

    // Extract IP address if available
    $ip = null;
    if (preg_match('/IP: ([^,]+)/', $line, $matches)) {
        $ip = trim($matches[1]);
    }
    $context = parseErrorContext($line);
    if (!$ip && isset($context['ip'])) {
        $ip = $context['ip'];
    }

    // Extract error type or message
    if (preg_match('/\] ([^:]+):/', $line, $matches)) {
        $errorType = trim($matches[1]);
    } elseif (preg_match('/\] ([^\]]+)\]/', $line, $matches)) {
        $errorType = trim($matches[1]);
    } else {
        $errorType = 'Unknown error';
    }

    // Process lines with IPs
    if ($ip) {
        // Count the number of illegal requests per IP
        if (isset($ip_counts[$ip])) {
            $ip_counts[$ip]['count']++;
            // Update last attempt if this attempt is newer
            if ($timestamp > $ip_counts[$ip]['last_attempt_timestamp']) {
                $ip_counts[$ip]['last_attempt'] = $formattedDate;
                $ip_counts[$ip]['last_attempt_timestamp'] = $timestamp;
                $ip_counts[$ip]['geo'] = $context['geo'] ?? $ip_counts[$ip]['geo'];
                $ip_counts[$ip]['isp'] = $context['isp'] ?? $ip_counts[$ip]['isp'];
                $ip_counts[$ip]['org'] = $context['org'] ?? $ip_counts[$ip]['org'];
                $ip_counts[$ip]['asn'] = $context['asn'] ?? $ip_counts[$ip]['asn'];
                $ip_counts[$ip]['ua'] = $context['ua'] ?? $ip_counts[$ip]['ua'];
                $ip_counts[$ip]['referer'] = $context['referer'] ?? $ip_counts[$ip]['referer'];
                $ip_counts[$ip]['origin'] = $context['origin'] ?? $ip_counts[$ip]['origin'];
                $ip_counts[$ip]['method'] = $context['method'] ?? $ip_counts[$ip]['method'];
                $ip_counts[$ip]['uri'] = $context['uri'] ?? $ip_counts[$ip]['uri'];
            }
        } else {
            $ip_counts[$ip] = [
                'count' => 1,
                'last_attempt' => $formattedDate,
                'last_attempt_timestamp' => $timestamp,
                'geo' => $context['geo'] ?? '',
                'isp' => $context['isp'] ?? '',
                'org' => $context['org'] ?? '',
                'asn' => $context['asn'] ?? '',
                'ua' => $context['ua'] ?? '',
                'referer' => $context['referer'] ?? '',
                'origin' => $context['origin'] ?? '',
                'method' => $context['method'] ?? '',
                'uri' => $context['uri'] ?? ''
            ];
        }
    } else {
        // Increment the count of errors without IPs
        $errors_without_ips_count++;
        if ($timestamp > $errors_without_ips_latest_timestamp) {
            $errors_without_ips_latest_timestamp = $timestamp;
            $errors_without_ips_latest = $formattedDate;
        }
    }
}

foreach ($proof_of_work_counts as $ip => $powData) {
    if (!isset($ip_counts[$ip])) {
        $ip_counts[$ip] = [
            'count' => 0,
            'last_attempt' => $powData['last_attempt'] ?? '',
            'last_attempt_timestamp' => $powData['last_attempt_timestamp'] ?? 0,
            'geo' => '',
            'isp' => '',
            'org' => '',
            'asn' => '',
            'ua' => '',
            'referer' => '',
            'origin' => '',
            'method' => '',
            'uri' => '',
            'proof_of_work' => $powData['count']
        ];
        continue;
    }
    $ip_counts[$ip]['proof_of_work'] = ($ip_counts[$ip]['proof_of_work'] ?? 0) + ($powData['count'] ?? 0);
    $existingTimestamp = $ip_counts[$ip]['last_attempt_timestamp'] ?? 0;
    if (($powData['last_attempt_timestamp'] ?? 0) > $existingTimestamp) {
        $ip_counts[$ip]['last_attempt_timestamp'] = $powData['last_attempt_timestamp'];
        $ip_counts[$ip]['last_attempt'] = $powData['last_attempt'];
    }
}

// Load geo cache to avoid slow network lookups during dashboard fetch.
$geo_cache = [];
$geo_cache_file = __DIR__ . '/ip_geo_cache.json';
if (is_readable($geo_cache_file)) {
    $geo_cache = json_decode(file_get_contents($geo_cache_file), true);
    if (!is_array($geo_cache)) {
        $geo_cache = [];
    }
}

// Prepare top IPs by count and by recency
$max_illegal_requests = 50;
$ip_counts_by_count = $ip_counts;
uasort($ip_counts_by_count, function ($a, $b) {
    return ($b['count'] ?? 0) <=> ($a['count'] ?? 0);
});
$ip_counts_by_recent = $ip_counts;
uasort($ip_counts_by_recent, function ($a, $b) {
    $timeA = $a['last_attempt_timestamp'] ?? 0;
    $timeB = $b['last_attempt_timestamp'] ?? 0;
    if ($timeA === $timeB) {
        return ($b['count'] ?? 0) <=> ($a['count'] ?? 0);
    }
    return $timeB <=> $timeA;
});
$top_by_count = array_slice($ip_counts_by_count, 0, $max_illegal_requests, true);
$top_by_recent = array_slice($ip_counts_by_recent, 0, $max_illegal_requests, true);
$top_ips = $top_by_count + $top_by_recent;

// Get geolocation for top IPs
$illegal_requests = [];
foreach ($top_ips as $ip => $data) {
    // Get geographic data if IP is valid
    $geoFromLog = $data['geo'] ?? '';
    $ispFromLog = $data['isp'] ?? '';
    $orgFromLog = $data['org'] ?? '';
    $asnFromLog = $data['asn'] ?? '';
    $uaFromLog = $data['ua'] ?? '';
    $refererFromLog = $data['referer'] ?? '';
    $originFromLog = $data['origin'] ?? '';
    $methodFromLog = $data['method'] ?? '';
    $uriFromLog = $data['uri'] ?? '';
    $location = $geoFromLog;
    $isp = $ispFromLog;
    $org = $orgFromLog;
    $asn = $asnFromLog;
    $geoCacheData = getGeoCacheData($ip, $geo_cache);

    if (filter_var($ip, FILTER_VALIDATE_IP)) {
        if (!$location && $geoCacheData) {
            $geoParts = array_filter([$geoCacheData['city'] ?? '', $geoCacheData['region'] ?? '', $geoCacheData['country'] ?? '']);
            $location = $geoParts ? implode(', ', $geoParts) : $location;
        }
        if (!$isp && $geoCacheData) {
            $isp = $geoCacheData['isp'] ?? $isp;
        }
        if (!$org && $geoCacheData) {
            $org = $geoCacheData['org'] ?? $org;
        }
        if (!$asn && $geoCacheData) {
            $asn = $geoCacheData['asn'] ?? $asn;
        }
    }

    $locationMissing = !$location || strcasecmp(trim($location), 'unknown') === 0;
    if ($locationMissing && isset($session_locations[$ip]) && $session_locations[$ip]) {
        $location = $session_locations[$ip];
        $locationMissing = !$location || strcasecmp(trim($location), 'unknown') === 0;
    }
    if ($locationMissing && isset($security_locations[$ip]['location']) && $security_locations[$ip]['location']) {
        $location = $security_locations[$ip]['location'];
        $locationMissing = !$location || strcasecmp(trim($location), 'unknown') === 0;
    }
    if ($locationMissing) {
        $location = lookupSecurityLocation($ip, $security_locations, $security_locations_dirty);
        $locationMissing = !$location || strcasecmp(trim($location), 'unknown') === 0;
    }
    if ($locationMissing) {
        $location = 'Unknown';
    }

    $illegal_requests[] = [
        'ip' => $ip,
        'count' => $data['count'],
        'proof_of_work' => $data['proof_of_work'] ?? 0,
        'location' => $location,
        'isp' => $isp,
        'org' => $org,
        'asn' => $asn,
        'ua' => $uaFromLog,
        'referer' => $refererFromLog,
        'origin' => $originFromLog,
        'method' => $methodFromLog,
        'uri' => $uriFromLog,
        'last_attempt' => $data['last_attempt'],
        'last_attempt_timestamp' => $data['last_attempt_timestamp']
    ];
}

if ($errors_without_ips_count > 0) {
    $illegal_requests[] = [
        'ip' => 'No IP (invalid form payload)',
        'count' => $errors_without_ips_count,
        'location' => 'Unknown',
        'isp' => '',
        'org' => '',
        'asn' => '',
        'ua' => '',
        'referer' => '',
        'origin' => '',
        'method' => '',
        'uri' => '',
        'last_attempt' => $errors_without_ips_latest ?: 'Unknown',
        'last_attempt_timestamp' => $errors_without_ips_latest_timestamp
    ];
}

// Add data to logs
$logs['illegal_requests'] = $illegal_requests;
$logs['errors_without_ips'] = $errors_without_ips_count;
$logs['errors_without_ips_latest'] = $errors_without_ips_latest;
$logs['errors_without_ips_latest_timestamp'] = $errors_without_ips_latest_timestamp;

// Calculate unique visitors per day
$unique_visitors = [];
foreach ($logs['pageviews'] as $pageview) {
    $date = explode(' ', $pageview['timestamp'])[0];
    if (!isset($unique_visitors[$date])) {
        $unique_visitors[$date] = [];
    }
    $unique_visitors[$date][$pageview['ip']] = true;
}

$unique_visitors_count = [];
foreach ($unique_visitors as $date => $ips) {
    $unique_visitors_count[] = [
        'date' => $date,
        'count' => count($ips),
        'pageviews' => array_values(array_filter($logs['pageviews'], function ($pageview) use ($date) {
            return explode(' ', $pageview['timestamp'])[0] === $date;
        }))
    ];
}

// Calculate other statistics
$total_sessions = count(array_filter($logs['sessions'], function ($session) {
    return $session['action'] === 'Session started';
}));
$total_pageviews = count($logs['pageviews']);
$total_bounces = count(array_filter($logs['sessions'], function ($session) {
    return $session['action'] === 'Session ended' && strpos($session['detail'], 'Duration: 0') !== false;
}));

$ended_sessions = 0;
$total_duration = array_reduce($logs['sessions'], function ($carry, $session) use (&$ended_sessions) {
    if ($session['action'] === 'Session ended') {
        $duration = intval(preg_replace('/\D/', '', explode(' ', $session['detail'])[1]));
        $carry += $duration;
        $ended_sessions++;
    }
    return $carry;
}, 0);

$average_duration = $ended_sessions > 0 ? ($total_duration / $ended_sessions) : 0;

$average_pages = $total_pageviews / max($total_sessions, 1);
$bounce_rate = $total_bounces / max($total_sessions, 1);

// Extract unique visitors with locations
$visitor_locations = $session_locations;

if ($security_locations_dirty) {
    persistSecurityLocations($security_location_file, $security_locations);
}

// Send JSON response
echo json_encode([
    'success' => true,
    'logs' => [
        'pageviews' => $unique_visitors_count,
        'sessions' => $logs['sessions'],
        'visitor_locations' => $visitor_locations,
        'illegal_requests' => $logs['illegal_requests'],
        'errors_without_ips' => $logs['errors_without_ips'],
        'errors_without_ips_latest' => $logs['errors_without_ips_latest'],
        'errors_without_ips_latest_timestamp' => $logs['errors_without_ips_latest_timestamp'],
        'top_pages' => $logs['top_pages'],
        'referrer_counts' => $logs['referrer_counts'],
        'bots_daily' => $logs['bots_daily'],
        'bots_latest' => $logs['bots_latest'],
        'bots_total' => $logs['bots_total'],
        'bots_top_pages' => $logs['bots_top_pages'],
        'bots_top_families' => $logs['bots_top_families']
    ],
    'stats' => [
        'total_sessions' => $total_sessions,
        'total_pageviews' => $total_pageviews,
        'average_duration' => $average_duration,
        'average_pages' => $average_pages,
        'bounce_rate' => $bounce_rate
    ]
]);
exit;
?>
