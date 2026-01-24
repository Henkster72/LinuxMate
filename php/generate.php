<?php
header('Content-Type: application/json; charset=utf-8');

$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!is_array($data)) {
    $data = $_POST;
}

$distro = $data['distro'] ?? 'ubuntu';
$aurHelper = $data['aur_helper'] ?? 'yay';
if (!in_array($aurHelper, ['yay', 'paru'], true)) {
    $aurHelper = 'yay';
}
$selected = $data['packages'] ?? [];
if (!is_array($selected)) {
    $selected = [];
}
$preferFlatpak = $data['prefer_flatpak'] ?? false;
$preferFlatpak = filter_var($preferFlatpak, FILTER_VALIDATE_BOOLEAN);
$sandbox = $data['sandbox'] ?? '';
if (!is_string($sandbox)) {
    $sandbox = '';
}
$sandbox = strtolower(trim($sandbox));
if ($sandbox === 'none') {
    $sandbox = '';
}
if (!$sandbox && $preferFlatpak) {
    $sandbox = 'flatpak';
}
if (!in_array($sandbox, ['flatpak', 'appimage', 'snap', 'custom'], true)) {
    $sandbox = '';
}

$includeUpdate = $data['include_update'] ?? null;
$includeUpdate = filter_var($includeUpdate, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
if ($includeUpdate === null) {
    $includeUpdate = true;
}

$packagesFile = __DIR__ . '/../data/packages.json';
$packages = json_decode(file_get_contents($packagesFile), true) ?? [];
$packageIndex = [];
foreach ($packages as $pkg) {
    $packageIndex[$pkg['id']] = $pkg;
}

// One mapping per manager; packages supply identifiers only.
$managers = [
    'apt' => [
        'label' => 'APT',
        'update' => 'sudo apt update',
        'install' => 'sudo apt install -y',
    ],
    'dnf' => [
        'label' => 'DNF',
        'install' => 'sudo dnf install -y',
    ],
    'pacman' => [
        'label' => 'Pacman',
        'update' => 'sudo pacman -Sy',
        'install' => 'sudo pacman -S --noconfirm',
    ],
    'aur' => [
        'label' => 'AUR',
        'install' => $aurHelper . ' -S --noconfirm',
    ],
    'zypper' => [
        'label' => 'Zypper',
        'update' => 'sudo zypper refresh',
        'install' => 'sudo zypper install -y',
    ],
    'flatpak' => [
        'label' => 'Flatpak',
        'install' => 'flatpak install -y flathub',
    ],
    'snap' => [
        'label' => 'Snap',
        'install' => 'sudo snap install',
    ],
    'brew' => [
        'label' => 'Homebrew',
        'install' => 'brew install',
    ],
];

// Distros only declare manager order; commands are reused.
$distros = [
    'ubuntu' => [
        'label' => 'Ubuntu',
        'managers' => ['apt', 'flatpak'],
    ],
    'debian' => [
        'label' => 'Debian',
        'managers' => ['apt', 'flatpak'],
    ],
    'fedora' => [
        'label' => 'Fedora',
        'managers' => ['dnf', 'flatpak'],
    ],
    'arch' => [
        'label' => 'Arch',
        'managers' => ['pacman', 'aur', 'flatpak'],
    ],
    'opensuse' => [
        'label' => 'openSUSE',
        'managers' => ['zypper', 'flatpak'],
    ],
    'nix' => [
        'label' => 'Nix',
        'managers' => ['flatpak'],
    ],
    'flatpak' => [
        'label' => 'Flatpak only',
        'managers' => ['flatpak'],
    ],
    'snap' => [
        'label' => 'Snap',
        'managers' => ['snap'],
    ],
    'homebrew' => [
        'label' => 'Homebrew',
        'managers' => ['brew'],
    ],
];

if (!isset($distros[$distro])) {
    $distro = 'ubuntu';
}

$managerOrder = $distros[$distro]['managers'];
if ($sandbox && $sandbox !== 'appimage' && $sandbox !== 'custom' && !in_array($sandbox, $managerOrder, true)) {
    $managerOrder[] = $sandbox;
}

$managerPackages = [];
foreach ($managerOrder as $manager) {
    $managerPackages[$manager] = [];
}

$customScripts = [];
$missingCustomScripts = [];
$customScriptUsesCurl = false;
function is_appimage_placeholder($value): bool {
    if (!is_string($value) || $value === '') {
        return false;
    }
    return strpos($value, 'duckduckgo.com/?q=appimage+site:') !== false;
}

function appimage_filename(string $url): string {
    $path = parse_url($url, PHP_URL_PATH);
    $filename = $path ? basename($path) : '';
    $filename = preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename ?? '');
    if (!$filename) {
        return 'appimage-' . substr(sha1($url), 0, 8) . '.AppImage';
    }
    return $filename;
}

function distro_install_command(string $distro, string $package): ?string {
    static $templates = [
        'ubuntu' => 'sudo apt install -y %s',
        'debian' => 'sudo apt install -y %s',
        'fedora' => 'sudo dnf install -y %s',
        'arch' => 'sudo pacman -S --noconfirm %s',
        'opensuse' => 'sudo zypper install -y %s',
        'nix' => 'nix-env -iA nixpkgs.%s',
        'homebrew' => 'brew install %s',
    ];
    if (!isset($templates[$distro])) {
        return null;
    }
    return sprintf($templates[$distro], $package);
}

$appimageDownloads = [];
$appimagePlaceholders = 0;

foreach ($selected as $id) {
    if (!isset($packageIndex[$id])) {
        continue;
    }
    $pkg = $packageIndex[$id];
    $customScript = $pkg['custom_script'] ?? null;
    if (is_string($customScript)) {
        $customScript = trim($customScript);
    } else {
        $customScript = null;
    }
    if ($sandbox === 'custom') {
        if ($customScript) {
            $customScripts[] = [
                'name' => $pkg['name'] ?? $id,
                'script' => $customScript,
            ];
            if (stripos($customScript, 'curl') !== false) {
                $customScriptUsesCurl = true;
            }
            continue;
        } else {
            $missingCustomScripts[] = $pkg['name'] ?? $id;
        }
    }
    $flatpakId = $pkg['packages']['flatpak'] ?? null;
    $appimageId = $pkg['packages']['appimage'] ?? null;
    $snapId = $pkg['packages']['snap'] ?? null;
    if ($sandbox === 'flatpak' && $flatpakId && array_key_exists('flatpak', $managerPackages)) {
        $managerPackages['flatpak'][$flatpakId] = true;
        continue;
    }
    if ($sandbox === 'snap' && $snapId && array_key_exists('snap', $managerPackages)) {
        $managerPackages['snap'][$snapId] = true;
        continue;
    }
    if ($sandbox === 'appimage' && $appimageId) {
        if (is_appimage_placeholder($appimageId)) {
            $appimagePlaceholders++;
        } else {
            $appimageDownloads[$appimageId] = true;
            continue;
        }
    }
    $pkgAssigned = false;
    foreach (array_keys($managerPackages) as $manager) {
        $identifier = $pkg['packages'][$manager] ?? null;
        if (!$identifier) {
            continue;
        }
        if ($manager === 'flatpak' && $pkgAssigned) {
            continue;
        }
        $managerPackages[$manager][$identifier] = true;
        $pkgAssigned = true;
    }
}

$lines = ['#!/usr/bin/env bash', ''];
$hasAny = false;
$hasCustomScripts = false;
foreach ($managerOrder as $manager) {
    $ids = array_keys($managerPackages[$manager]);
    if (!$ids) {
        continue;
    }
    $hasAny = true;
    if ($manager === 'flatpak') {
        $flatpakInstall = distro_install_command($distro, 'flatpak');
        if ($flatpakInstall) {
            $lines[] = '# Add Flatpak on ' . $distros[$distro]['label'] . ': ' . $flatpakInstall;
        } else {
            $lines[] = '# Ensure Flatpak is installed on ' . $distros[$distro]['label'] . ' before running the Flatpak section.';
        }
    }
    $installCmd = $managers[$manager]['install'] . ' ' . implode(' ', $ids);
    $update = $managers[$manager]['update'] ?? null;
    if ($update && $includeUpdate) {
        $lines[] = $update . ' && ' . $installCmd;
    } else {
        $lines[] = $installCmd;
    }
    $lines[] = '';
}

if ($appimageDownloads) {
    $hasAny = true;
    $lines[] = '# AppImage';
    $lines[] = 'mkdir -p "$HOME/Applications"';
    foreach (array_keys($appimageDownloads) as $url) {
        $filename = appimage_filename($url);
        $lines[] = 'curl -L -o "$HOME/Applications/' . $filename . '" "' . $url . '"';
        $lines[] = 'chmod +x "$HOME/Applications/' . $filename . '"';
    }
    $lines[] = '';
}

if ($sandbox === 'custom' && $customScripts) {
    $hasAny = true;
    $hasCustomScripts = true;
    $lines[] = '# Custom installs';
    foreach ($customScripts as $entry) {
        $lines[] = '# ' . $entry['name'];
        $lines[] = $entry['script'];
        $lines[] = '';
    }
}

if ($sandbox === 'custom' && $missingCustomScripts) {
    $hasAny = true;
    $lines[] = '# No custom script available for: ' . implode(', ', $missingCustomScripts);
    $lines[] = '';
}

if (!$hasAny) {
    if ($sandbox === 'custom' && $missingCustomScripts) {
        $lines[] = '# No custom script available for: ' . implode(', ', $missingCustomScripts);
    } elseif ($sandbox === 'appimage' && $appimagePlaceholders > 0) {
        $lines[] = '# AppImage selections are manual for now.';
        $lines[] = '# Use the AppImage search link below the script output.';
    } else {
        $lines[] = '# No packages available for this distro selection.';
        $lines[] = '# Try another distro or switch to Flatpak/AppImage/Snap.';
    }
}

if ($hasCustomScripts) {
    $lines[] = '# Tip: for better managing of custom scripts and packaging, one can run:';
    $lines[] = '# mkdir -p ~/.local/bin ~/.local/opt && grep -qxF \'export PATH="$HOME/.local/bin:$PATH"\' ~/.profile || echo \'export PATH="$HOME/.local/bin:$PATH"\' >> ~/.profile && export PATH="$HOME/.local/bin:$PATH"';
    $lines[] = '# Provided scripts are used at your own risk.';
    $lines[] = '# It is highly recommended to use the dedicated package managers for distros.';
    if ($customScriptUsesCurl) {
        $curlInstall = distro_install_command($distro, 'curl');
        if ($curlInstall) {
            $lines[] = '# Add curl on ' . $distros[$distro]['label'] . ': ' . $curlInstall;
        } else {
            $lines[] = '# Add curl via your distro\'s package manager before running these custom commands.';
        }
    }
}

$script = rtrim(implode("\n", $lines)) . "\n";

echo json_encode([
    'script' => $script,
    'distro' => $distro,
    'package_count' => count($selected),
]);
