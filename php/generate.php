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

$managerPackages = [];
foreach ($distros[$distro]['managers'] as $manager) {
    $managerPackages[$manager] = [];
}

foreach ($selected as $id) {
    if (!isset($packageIndex[$id])) {
        continue;
    }
    $pkg = $packageIndex[$id];
    foreach (array_keys($managerPackages) as $manager) {
        $identifier = $pkg['packages'][$manager] ?? null;
        if ($identifier) {
            $managerPackages[$manager][$identifier] = true;
        }
    }
}

$lines = ['#!/usr/bin/env bash', ''];
$hasAny = false;
foreach ($distros[$distro]['managers'] as $manager) {
    $ids = array_keys($managerPackages[$manager]);
    if (!$ids) {
        continue;
    }
    $hasAny = true;
    $update = $managers[$manager]['update'] ?? null;
    if ($update) {
        $lines[] = $update;
    }
    $lines[] = $managers[$manager]['install'] . ' ' . implode(' ', $ids);
    $lines[] = '';
}

if (!$hasAny) {
    $lines[] = '# No packages available for this distro selection.';
    $lines[] = '# Try another distro or switch to Flatpak/Snap.';
}

$script = rtrim(implode("\n", $lines)) . "\n";

echo json_encode([
    'script' => $script,
    'distro' => $distro,
    'package_count' => count($selected),
]);
