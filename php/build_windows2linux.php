<?php
$sourceUrl = 'https://wiki.linuxquestions.org/wiki/Linux_software_equivalent_to_Windows_software';
$rootPackagesFile = dirname(__DIR__, 2) . '/packages.json';
$packagesFile = file_exists($rootPackagesFile) ? $rootPackagesFile : __DIR__ . '/../data/packages.json';
$outputDir = __DIR__ . '/../windows2linux';
$matchedFile = $outputDir . '/comparable-software.csv';
$notFoundFile = $outputDir . '/notfoundpackage.csv';

function w2l_normalize($value) {
    $value = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = strtolower($value);
    $value = preg_replace('/\([^)]*\)/', ' ', $value);
    $value = preg_replace('/[^a-z0-9]+/', '', $value);
    return trim($value);
}

function w2l_clean_name($value) {
    $value = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = preg_replace('/\[[0-9]+\]/', '', $value);
    $value = preg_replace('/\bthanks to\b.*$/i', '', $value);
    $value = preg_replace('/\bsee also\b.*$/i', '', $value);
    $value = preg_replace('/\bmore\b\.?$/i', '', $value);
    $value = trim($value, " \t\n\r\0\x0B.;");
    return preg_replace('/\s+/', ' ', $value);
}

function w2l_split_apps($value) {
    $value = str_replace(["\xc2\xa0", "\n", "\r", ';'], ' ', $value);
    $value = preg_replace('/\s+/', ' ', $value);
    $parts = preg_split('/\s*,\s*|\s+\+\s+/', $value);
    $apps = [];
    foreach ($parts as $part) {
        $part = w2l_clean_name($part);
        if ($part === '' || preg_match('/^(etc|others?|ref|command line tools)$/i', $part)) {
            continue;
        }
        $apps[$part] = $part;
    }
    return array_values($apps);
}

function w2l_fetch($url) {
    $context = stream_context_create([
        'http' => [
            'header' => "User-Agent: LinuxMate/1.0\r\n",
            'timeout' => 20,
        ],
    ]);
    $html = @file_get_contents($url, false, $context);
    if ($html === false || trim($html) === '') {
        throw new RuntimeException('Could not fetch LinuxQuestions software equivalent page.');
    }
    return $html;
}

function w2l_cell_text(DOMXPath $xpath, DOMNode $cell) {
    $nodes = $xpath->query('.//sup|.//*[contains(concat(" ", normalize-space(@class), " "), " reference ")]', $cell);
    foreach (iterator_to_array($nodes) as $node) {
        $node->parentNode?->removeChild($node);
    }
    return trim($cell->textContent);
}

$packages = json_decode(file_get_contents($packagesFile), true);
if (!is_array($packages)) {
    throw new RuntimeException('Could not read packages.json.');
}

$packageIndex = [];
foreach ($packages as $package) {
    $name = $package['name'] ?? '';
    if (!is_string($name) || trim($name) === '') {
        continue;
    }
    $packageIndex[w2l_normalize($name)] = $name;
    $id = $package['id'] ?? '';
    if (is_string($id) && trim($id) !== '') {
        $packageIndex[w2l_normalize($id)] = $name;
    }
    foreach (($package['packages'] ?? []) as $identifier) {
        if (!is_string($identifier) || trim($identifier) === '') {
            continue;
        }
        if (str_contains($identifier, '://')) {
            continue;
        }
        $packageIndex[w2l_normalize($identifier)] = $name;
    }
}

$html = w2l_fetch($sourceUrl);
libxml_use_internal_errors(true);
$dom = new DOMDocument();
$dom->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR);
$xpath = new DOMXPath($dom);
$tables = $xpath->query('//table[contains(concat(" ", normalize-space(@class), " "), " wikitable ")]');

$rowsByWindows = [];

foreach ($tables as $table) {
    $rows = $xpath->query('.//tr[td]', $table);
    foreach ($rows as $row) {
        $cells = $xpath->query('./td', $row);
        if ($cells->length < 2) {
            continue;
        }
        $windowsApps = w2l_split_apps(w2l_cell_text($xpath, $cells->item(0)));
        $linuxApps = w2l_split_apps(w2l_cell_text($xpath, $cells->item(1)));
        if (!$windowsApps || !$linuxApps) {
            continue;
        }

        $foundLinux = [];
        foreach ($linuxApps as $linuxApp) {
            $key = w2l_normalize($linuxApp);
            if (isset($packageIndex[$key])) {
                $foundLinux[$packageIndex[$key]] = $packageIndex[$key];
            }
        }

        foreach ($windowsApps as $windowsApp) {
            $key = w2l_normalize($windowsApp);
            if ($key === '') {
                continue;
            }
            if (!isset($rowsByWindows[$key])) {
                $rowsByWindows[$key] = [
                    'windows' => $windowsApp,
                    'found' => [],
                    'wiki' => [],
                ];
            }
            foreach ($foundLinux as $name) {
                $rowsByWindows[$key]['found'][$name] = $name;
            }
            foreach ($linuxApps as $linuxApp) {
                $rowsByWindows[$key]['wiki'][$linuxApp] = $linuxApp;
            }
        }
    }
}

$matched = [];
$notFound = [];
foreach ($rowsByWindows as $row) {
    if ($row['found']) {
        $matched[] = [$row['windows'], implode('; ', array_values($row['found']))];
    } else {
        $notFound[] = [$row['windows'], implode('; ', array_values($row['wiki']))];
    }
}

usort($matched, fn($a, $b) => strcasecmp($a[0], $b[0]));
usort($notFound, fn($a, $b) => strcasecmp($a[0], $b[0]));

if (!is_dir($outputDir)) {
    mkdir($outputDir, 0775, true);
}

$matchedHandle = fopen($matchedFile, 'w');
fputcsv($matchedHandle, ['windows_software', 'linux_variant']);
foreach ($matched as $row) {
    fputcsv($matchedHandle, $row);
}
fclose($matchedHandle);

$notFoundHandle = fopen($notFoundFile, 'w');
fputcsv($notFoundHandle, ['windows_software', 'linux_equivalents_from_wiki']);
foreach ($notFound as $row) {
    fputcsv($notFoundHandle, $row);
}
fclose($notFoundHandle);

echo sprintf(
    "Wrote %d matched rows to %s and %d not-found rows to %s\n",
    count($matched),
    $matchedFile,
    count($notFound),
    $notFoundFile
);
