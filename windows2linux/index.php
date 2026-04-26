<?php
require_once __DIR__ . '/../php/layout.php';

$matchedFile = __DIR__ . '/comparable-software.csv';
$notFoundFile = __DIR__ . '/notfoundpackage.csv';
$sourceUrl = 'https://wiki.linuxquestions.org/wiki/Linux_software_equivalent_to_Windows_software';
$packagesFile = dirname(__DIR__, 2) . '/packages.json';
if (!file_exists($packagesFile)) {
    $packagesFile = __DIR__ . '/../data/packages.json';
}
$distroFile = __DIR__ . '/../data/distro.json';

$packages = json_decode(file_get_contents($packagesFile), true) ?? [];
$distros = file_exists($distroFile) ? json_decode(file_get_contents($distroFile), true) : [];
$distros = is_array($distros) ? $distros : [];
$defaultDistroLabel = $distros[0]['label'] ?? 'Select';
$version = linuxmate_version(__DIR__ . '/..');

function w2l_read_csv($file) {
    if (!file_exists($file)) {
        return [];
    }
    $handle = fopen($file, 'r');
    if (!$handle) {
        return [];
    }
    fgetcsv($handle);
    $rows = [];
    while (($row = fgetcsv($handle)) !== false) {
        if (!$row || count($row) < 2) {
            continue;
        }
        $rows[] = [
            'windows' => $row[0] ?? '',
            'linux' => $row[1] ?? '',
        ];
    }
    fclose($handle);
    return $rows;
}

function w2l_slugify($text) {
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

function w2l_normalize($value) {
    $value = strtolower((string) $value);
    $value = preg_replace('/\([^)]*\)/', ' ', $value);
    $value = preg_replace('/[^a-z0-9]+/', '', $value);
    return trim($value);
}

function w2l_split_linux_variants($value) {
    $parts = preg_split('/\s*;\s*/', (string) $value);
    return array_values(array_filter(array_map('trim', $parts), static fn($part) => $part !== ''));
}

$packageIndex = [];
foreach ($packages as $package) {
    $name = $package['name'] ?? '';
    if (!is_string($name) || trim($name) === '') {
        continue;
    }
    $packageIndex[w2l_normalize($name)] = $package;
}

$matches = w2l_read_csv($matchedFile);
$notFound = w2l_read_csv($notFoundFile);
$matchCount = count($matches);
$notFoundCount = count($notFound);
$packageHitCount = 0;

$topbarControls = linuxmate_render_installer_controls($distros, $defaultDistroLabel, '..');

ob_start();
?>
                <section>
                    <h3>Getting Started</h3>
                    <ol>
                        <li>Search for a Windows application you know.</li>
                        <li>Select one or more LinuxMate package equivalents.</li>
                        <li>Copy or download the generated install script from the footer.</li>
                    </ol>
                </section>
                <section>
                    <h3>Data Source</h3>
                    <p>The source list is the <a href="<?php echo htmlspecialchars($sourceUrl); ?>" target="_blank" rel="noreferrer">LinuxQuestions equivalent software table</a>. Linux variants are selectable only when they match current LinuxMate package data.</p>
                </section>
<?php
$helpBody = ob_get_clean();
?>
<?php linuxmate_render_head('Windows software equivalents - LinuxMate', 'Find and install LinuxMate packages that are comparable to Windows software listed by LinuxQuestions.', '..'); ?>
<body data-generate-url="../php/generate.php">
    <div class="page">
<?php linuxmate_render_topbar([
    'basePath' => '..',
    'version' => $version,
    'title' => 'LinuxMate',
    'tagline' => 'Windows software equivalents.<br>Select LinuxMate packages by the Windows apps you know.',
    'controlsHtml' => $topbarControls,
    'active' => 'windows2linux',
]); ?>

        <section class="controls">
            <div class="search-stats-row">
                <div class="search-wrap">
                    <span class="search-icon pi pi-magnify" aria-hidden="true"></span>
                    <input id="search" type="search" placeholder="Search Windows apps or Linux package equivalents" />
                    <button id="search-clear" type="button" aria-label="Clear search">
                        <span class="pi pi-cross" aria-hidden="true"></span>
                    </button>
                </div>
                <div class="stats-chips">
                    <span class="stats-chip">Windows matches: <?php echo htmlspecialchars((string) $matchCount); ?></span>
                    <span class="stats-chip">Not found: <?php echo htmlspecialchars((string) $notFoundCount); ?></span>
                    <a class="stats-chip stats-link" href="<?php echo htmlspecialchars($sourceUrl); ?>" target="_blank" rel="noreferrer">
                        Source overview
                        <span class="pi pi-externallink" aria-hidden="true"></span>
                    </a>
                    <a class="stats-chip stats-link" href="comparable-software.csv">
                        Matches CSV
                        <span class="pi pi-download" aria-hidden="true"></span>
                    </a>
                    <a class="stats-chip stats-link" href="notfoundpackage.csv">
                        Not found CSV
                        <span class="pi pi-download" aria-hidden="true"></span>
                    </a>
                </div>
            </div>
            <div class="hint">Tip: Press <span>/</span> to jump to search.</div>
        </section>

        <main class="layout windows2linux-installer">
            <div class="category-grid windows2linux-grid">
                <?php foreach ($matches as $index => $row): ?>
                    <?php
                        $windowsName = $row['windows'];
                        $linuxNames = w2l_split_linux_variants($row['linux']);
                        $matchedPackages = [];
                        foreach ($linuxNames as $linuxName) {
                            $key = w2l_normalize($linuxName);
                            if (isset($packageIndex[$key])) {
                                $matchedPackages[$packageIndex[$key]['id']] = $packageIndex[$key];
                            }
                        }
                        if (!$matchedPackages) {
                            continue;
                        }
                        $packageHitCount += count($matchedPackages);
                        $slug = w2l_slugify($windowsName);
                    ?>
                    <section class="category-card windows2linux-card" data-category="<?php echo htmlspecialchars($slug); ?>" style="--delay: <?php echo $index * 0.01; ?>s;">
                        <header class="category-header">
                            <div class="category-title">
                                <button class="category-toggle" type="button" aria-expanded="true" aria-label="Collapse Windows equivalent">
                                    <span class="pi pi-downcaret" aria-hidden="true"></span>
                                </button>
                                <h3><?php echo htmlspecialchars($windowsName); ?></h3>
                            </div>
                            <div class="windows2linux-card-actions">
                                <a class="windows2linux-source-link" href="<?php echo htmlspecialchars($sourceUrl); ?>" target="_blank" rel="noreferrer" title="Open source reference" aria-label="Open source reference">
                                    <span class="pi pi-externallink" aria-hidden="true"></span>
                                </a>
                                <span class="count"><?php echo count($matchedPackages); ?></span>
                            </div>
                        </header>
                        <ul class="package-list">
                            <?php foreach ($matchedPackages as $pkg): ?>
                                <?php
                                    $managerKeys = [];
                                    foreach (($pkg['packages'] ?? []) as $manager => $identifier) {
                                        if (!$identifier) {
                                            continue;
                                        }
                                        if (
                                            $manager === 'appimage'
                                            && strpos($identifier, 'duckduckgo.com/?q=appimage+site:') !== false
                                        ) {
                                            continue;
                                        }
                                        $managerKeys[] = $manager;
                                    }
                                    $managerList = implode(',', $managerKeys);
                                    $iconHtml = linuxmate_rebase_icon_html(linuxmate_decorate_icon($pkg['icon_svg'] ?? ''), '..');
                                    $url = $pkg['url'] ?? '';
                                    $appimage = $pkg['packages']['appimage'] ?? '';
                                    $customScript = $pkg['custom_script'] ?? '';
                                    $hasCustomScript = is_string($customScript) && trim($customScript) !== '' ? '1' : '0';
                                    $search = strtolower($windowsName . ' ' . ($pkg['name'] ?? '') . ' ' . ($pkg['description'] ?? '') . ' ' . $row['linux']);
                                ?>
                                <li class="package-item" data-search="<?php echo htmlspecialchars($search); ?>" data-managers="<?php echo htmlspecialchars($managerList); ?>" data-category="<?php echo htmlspecialchars(w2l_slugify($pkg['category'] ?? '')); ?>" data-name="<?php echo htmlspecialchars($pkg['name'] ?? ''); ?>" data-description="<?php echo htmlspecialchars($pkg['description'] ?? ''); ?>" data-url="<?php echo htmlspecialchars($url); ?>" data-appimage="<?php echo htmlspecialchars((string) $appimage); ?>" data-custom-script="<?php echo $hasCustomScript; ?>">
                                    <label>
                                        <input class="pkg-check" type="checkbox" data-id="<?php echo htmlspecialchars($pkg['id']); ?>" />
                                        <span class="pkg-icon"><?php echo $iconHtml; ?></span>
                                        <span class="pkg-name"><?php echo htmlspecialchars($pkg['name']); ?></span>
                                    </label>
                                    <div class="package-actions">
                                        <?php if ($url): ?>
                                            <button class="external-link-btn" type="button" data-url="<?php echo htmlspecialchars($url); ?>" title="Open website">
                                                <span class="pi pi-externallink" aria-hidden="true"></span>
                                            </button>
                                        <?php endif; ?>
                                        <button class="info-btn" type="button" aria-label="More info" title="More info">
                                            <span class="pi pi-info" aria-hidden="true"></span>
                                        </button>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endforeach; ?>
            </div>
        </main>

        <?php linuxmate_render_script_footer(); ?>

        <section class="windows2linux-panel windows2linux-notfound">
            <header class="windows2linux-panel-header">
                <div>
                    <h2>Not In LinuxMate Yet</h2>
                    <p>Wiki entries whose Linux equivalents did not match current package data.</p>
                </div>
                <a href="notfoundpackage.csv">
                    CSV
                    <span class="pi pi-download" aria-hidden="true"></span>
                </a>
            </header>
            <div class="windows2linux-table-wrap">
                <table class="windows2linux-table">
                    <thead>
                        <tr>
                            <th>Windows software</th>
                            <th>Linux equivalents from wiki</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($notFound as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['windows']); ?></td>
                                <td><?php echo htmlspecialchars($row['linux']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

<?php linuxmate_render_help_modal('Windows to Linux Help', $helpBody); ?>
<?php linuxmate_render_installer_modals(); ?>
<?php linuxmate_render_page_end(['../js/app.js']); ?>
