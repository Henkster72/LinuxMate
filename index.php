<?php
$packagesFile = __DIR__ . '/data/packages.json';
$packages = json_decode(file_get_contents($packagesFile), true) ?? [];
$distroFile = __DIR__ . '/data/distro.json';
$distros = [];
if (file_exists($distroFile)) {
    $distros = json_decode(file_get_contents($distroFile), true);
}
$distros = is_array($distros) ? $distros : [];
$defaultDistroLabel = $distros[0]['label'] ?? 'Select';
$packageCount = is_array($packages) ? count($packages) : 0;
$distroCount = count($distros);

$categoryOrder = [
    'Web Browsers',
    'Communication',
    'Media',
    'Gaming',
    'Office',
    'System',
    'Desktop Environments',
    'Window Managers',
    'Creative',
    'VPN & Network',
    'File Sharing',
    'Security',
    'Databases',
    'Dev: Editors',
    'Dev: Languages',
    'Dev: Tools',
    'Terminal',
    'CLI Tools',
];

function slugify_category($text) {
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

function decorate_icon($html) {
    if (strpos($html, '<img') === 0 && strpos($html, 'loading=') === false) {
        return preg_replace('/<img\b/', '<img loading="lazy" decoding="async"', $html, 1);
    }
    return $html;
}

$categories = [];
foreach ($packages as $pkg) {
    $slug = slugify_category($pkg['category']);
    if (!isset($categories[$slug])) {
        $categories[$slug] = [
            'label' => $pkg['category'],
            'items' => [],
        ];
    }
    $categories[$slug]['items'][] = $pkg;
}

$orderedCategories = [];
foreach ($categoryOrder as $label) {
    $slug = slugify_category($label);
    if (isset($categories[$slug])) {
        $orderedCategories[$slug] = $categories[$slug];
    }
}

$jsBytes = file_exists(__DIR__ . '/js/app.js') ? filesize(__DIR__ . '/js/app.js') : 0;
$cssBytes = file_exists(__DIR__ . '/css/style.css') ? filesize(__DIR__ . '/css/style.css') : 0;
$kb = fn($bytes) => number_format($bytes / 1024, 1) . ' KB';
$readme = file_exists(__DIR__ . '/README.md') ? file_get_contents(__DIR__ . '/README.md') : '';
$version = 'v0.21';
if (preg_match('/LinuxMate\\s+(v[0-9.]+)/', $readme, $match)) {
    $version = $match[1];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>LinuxMate - Lightweight PHP Clone</title>
    <link rel="icon" href="favicon.ico" />
    <link rel="stylesheet" href="popicon.css" />
    <link rel="stylesheet" href="css/style.css" />
</head>
<body>
    <div class="page">
        <header class="topbar">
            <div class="brand">
                <div class="logo" aria-hidden="true">
                    <span class="pi pi-linux logo-icon" aria-hidden="true"></span>
                    <span class="logo-badge pi pi-packageclose"></span>
                </div>
                <div class="brand-text">
                    <div class="brand-title">
                        <h1>LinuxMate</h1>
                        <span class="version-badge"><?php echo htmlspecialchars($version); ?></span>
                    </div>
                    <p>The Linux Bulk App Installer.<br>
                    Install and update all your Linux programs at once.</p>
                    <p class="brand-note">
                        Story behind the tool: <a href="https://www.allroundwebsite.com/blog/bye-windows-hello-linux-and-linuxmate/" target="_blank" rel="noreferrer">Bye Windows, Hello Linux and LinuxMate</a>.
                    </p>
                </div>
            </div>
            <div class="topbar-controls">
                <div class="top-links">
                    <a href="https://github.com/Henkster72/LinuxMate" target="_blank" rel="noreferrer">
                        <i class="pi pi-code"></i>
                        GitHub
                    </a>
                    <a href="https://github.com/Henkster72/LinuxMate/blob/main/CONTRIBUTING.md" target="_blank" rel="noreferrer">
                        <i class="pi pi-heart"></i>
                        Contribute
                    </a>
                    <button id="help-button" class="ghost" type="button">
                        <i class="pi pi-question"></i>
                        Help
                    </button>
                    <button id="theme-toggle" class="ghost" type="button" aria-pressed="false">
                        <span id="theme-icon" class="pi pi-moon"></span>
                        Theme
                    </button>
                </div>
                <div class="select-row">
                    <label class="select distro-select">
                        <span>Distro</span>
                        <div class="select-shell distro-shell">
                            <button id="distro-button" class="distro-button" type="button" aria-haspopup="listbox" aria-controls="distro-menu" aria-expanded="false">
                                <img class="distro-button-icon" alt="" />
                                <span class="distro-button-label"><?php echo htmlspecialchars($defaultDistroLabel); ?></span>
                            </button>
                            <span class="select-caret pi pi-downcaret" aria-hidden="true"></span>
                            <select id="distro" class="native-select" aria-hidden="true" tabindex="-1">
                                <?php foreach ($distros as $index => $distro): ?>
                                    <?php
                                        $value = $distro['value'] ?? '';
                                        $label = $distro['label'] ?? $value;
                                        $icon = $distro['icon'] ?? '';
                                        $managers = $distro['managers'] ?? [];
                                        $managerList = is_array($managers) ? implode(',', $managers) : '';
                                    ?>
                                    <option value="<?php echo htmlspecialchars($value); ?>"
                                        data-icon="<?php echo htmlspecialchars($icon); ?>"
                                        data-managers="<?php echo htmlspecialchars($managerList); ?>"
                                        <?php echo $index === 0 ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <ul id="distro-menu" class="distro-menu" role="listbox" aria-label="Select distro"></ul>
                        </div>
                    </label>
                    <label class="select sandbox-select">
                        <span>Sandbox</span>
                        <div class="select-shell">
                            <select id="sandbox">
                                <option value="none">None</option>
                                <option value="flatpak">Flatpak</option>
                                <option value="snap">Snap</option>
                                <option value="appimage">AppImage</option>
                                <option value="custom">Custom script</option>
                            </select>
                            <span class="select-caret pi pi-downcaret" aria-hidden="true"></span>
                        </div>
                    </label>
                    <div id="aur-slot"></div>
                </div>
                <template id="aur-template">
                    <label class="select aur-select" id="aur-wrap">
                        <span>AUR helper</span>
                        <div class="select-shell">
                            <select id="aur-helper">
                                <option value="yay">yay</option>
                                <option value="paru">paru</option>
                            </select>
                            <span class="select-caret pi pi-downcaret" aria-hidden="true"></span>
                        </div>
                    </label>
                </template>
            </div>
        </header>

        <section class="controls">
            <div class="search-stats-row">
                <div class="search-wrap">
                    <span class="search-icon pi pi-magnify" aria-hidden="true"></span>
                    <input id="search" type="search" placeholder="Search apps or categories" />
                    <button id="search-clear" type="button" aria-label="Clear search">
                        <span class="pi pi-cross" aria-hidden="true"></span>
                    </button>
                </div>
                <div class="stats-chips">
                    <span class="stats-chip">Packages: <?php echo htmlspecialchars((string) $packageCount); ?></span>
                    <span class="stats-chip">Distros: <?php echo htmlspecialchars((string) $distroCount); ?></span>
                </div>
            </div>
            <div class="hint">Tip: Press <span>/</span> to jump to search.</div>
        </section>

        <main class="layout">
            <div class="category-grid">
                <?php $index = 0; ?>
                <?php foreach ($orderedCategories as $slug => $category): ?>
                    <section class="category-card" data-category="<?php echo htmlspecialchars($slug); ?>" style="--delay: <?php echo $index * 0.02; ?>s;">
                        <header class="category-header">
                            <div class="category-title">
                                <button class="category-toggle" type="button" aria-expanded="true" aria-label="Collapse category">
                                    <span class="pi pi-downcaret" aria-hidden="true"></span>
                                </button>
                                <h3><?php echo htmlspecialchars($category['label']); ?></h3>
                            </div>
                            <span class="count"><?php echo count($category['items']); ?></span>
                        </header>
                        <ul class="package-list">
                            <?php foreach ($category['items'] as $pkg): ?>
                                <?php
                                    $search = strtolower($pkg['name'] . ' ' . ($pkg['description'] ?? ''));
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
                                    $iconHtml = decorate_icon($pkg['icon_svg']);
                                    $url = $pkg['url'] ?? '';
                                    $appimage = $pkg['packages']['appimage'] ?? '';
                                ?>
                                <li class="package-item" data-search="<?php echo htmlspecialchars($search); ?>" data-managers="<?php echo htmlspecialchars($managerList); ?>" data-category="<?php echo htmlspecialchars($slug); ?>" data-name="<?php echo htmlspecialchars($pkg['name']); ?>" data-description="<?php echo htmlspecialchars($pkg['description']); ?>" data-url="<?php echo htmlspecialchars($url); ?>" data-appimage="<?php echo htmlspecialchars((string) $appimage); ?>">
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
                    <?php $index++; ?>
                <?php endforeach; ?>
            </div>
        </main>

        <footer class="script-footer">
            <div class="script-shell">
                <div class="panel-header">
                    <div>
                        <h3>Install script</h3>
                        <p>DRY commands, grouped per package manager.</p>
                    </div>
                    <div class="script-actions">
                        <button id="clear" type="button"><span class="pi pi-bin" aria-hidden="true"></span> Clear</button>
                        <button id="copy" type="button" data-label="Copy" data-copied="Copied!">
                            <span class="pi pi-copy" aria-hidden="true"></span>
                            <span class="copy-text">Copy</span>
                        </button>
                        <button id="share" type="button" data-label="Share" data-copied="Copied!">
                            <span class="pi pi-arrowbow" aria-hidden="true"></span>
                            <span class="share-text">Share</span>
                        </button>
                        <button id="download" type="button"><span class="pi pi-download" aria-hidden="true"></span> Download</button>
                    </div>
                </div>
                <pre id="script-output"># Select apps above to generate a single batched command.</pre>
                <div class="panel-footer">
                    <span>Selected: <strong id="selected-count">0</strong></span>
                    <span class="note">PHP + JS + CSS, no build step.</span>
                    <span class="footer-warning" hidden>
                        <span class="warning-icon" aria-hidden="true">!</span>
                        Warning: Desktop Environments and Window Managers are often bundled with distros (Arch is a common exception). Installing separately can cause conflicts.
                    </span>
                    <a id="appimage-link" class="repology-link" href="#" target="_blank" rel="noreferrer" hidden>
                        <span class="link-text">AppImage search</span>
                        <span class="pi pi-externallink" aria-hidden="true"></span>
                    </a>
                    <a class="repology-link" href="https://repology.org/" target="_blank" rel="noreferrer">
                        <span class="link-text">Repology</span>
                        <span class="pi pi-externallink" aria-hidden="true"></span>
                    </a>
                    <a class="repology-link" href="https://www.allroundwebsite.com/" target="_blank" rel="noreferrer">
                        <span class="link-text">Made possible by Allroundwebsite</span>
                        <span class="pi pi-externallink" aria-hidden="true"></span>
                    </a>
                </div>
            </div>
        </footer>

        <section class="comparison">
            <h2>Why this stack is leaner than the original</h2>
            <div class="comparison-grid">
                <div>
                    <h3>DRY data model</h3>
                    <p>Packages define identifiers once. Install commands are generated by grouping per manager, not per app.</p>
                </div>
                <div>
                    <h3>Fast on shared hosting</h3>
                    <p>No Node.js, no build pipeline, no runtime bundlers. Just PHP and static assets.</p>
                </div>
                <div>
                    <h3>Browser friendly</h3>
                    <p>Minimal JS, no hydration, instant render from server output.</p>
                </div>
            </div>
        </section>
    </div>

    <div class="modal" id="help-modal" role="dialog" aria-modal="true" aria-hidden="true">
        <div class="modal-backdrop" data-close="help-modal"></div>
        <div class="modal-card" role="document">
            <header>
                <h2>Help</h2>
                <button class="modal-close" type="button" data-close="help-modal">
                    <span class="pi pi-cross" aria-hidden="true"></span>
                    Close
                </button>
            </header>
            <div class="modal-body">
                <section>
                    <h3>Keyboard Shortcuts</h3>
                    <div class="shortcut-grid">
                        <div><span class="keys">Arrow keys</span><span>Navigate through apps</span></div>
                        <div><span class="keys">hjkl</span><span>Vim-style navigation</span></div>
                        <div><span class="keys">Space</span><span>Select or deselect app</span></div>
                        <div><span class="keys">/</span><span>Focus search box</span></div>
                        <div><span class="keys">y</span><span>Copy install command</span></div>
                        <div><span class="keys">d</span><span>Download install script</span></div>
                        <div><span class="keys">c</span><span>Clear all selections</span></div>
                        <div><span class="keys">t</span><span>Toggle light/dark theme</span></div>
                        <div><span class="keys">Tab</span><span>Preview current selection</span></div>
                        <div><span class="keys">Esc</span><span>Close this modal</span></div>
                        <div><span class="keys">?</span><span>Show this help</span></div>
                        <div><span class="keys">1 / 2</span><span>Switch AUR helper (yay/paru)</span></div>
                    </div>
                </section>
                <section>
                    <h3>Getting Started</h3>
                    <ol>
                        <li>Pick your distro — Select your Linux distribution from the dropdown at the top. This determines which package manager commands are generated.</li>
                        <li>Select apps — Browse the categories and click on apps to add them to your selection. Selected apps are highlighted. Use keyboard shortcuts to navigate faster.</li>
                        <li>Copy or download — Copy the generated install command to your clipboard, or download a complete shell script.</li>
                        <li>Run in terminal — Open your terminal, paste the command (Ctrl+Shift+V), and press Enter. The script will handle the rest.</li>
                    </ol>
                </section>
                <section>
                    <h3>Good to Know</h3>
                    <p>Greyed out apps are not available in the selected distro's official repositories. Try switching to Flatpak or Snap, or open the info button for alternative install hints.</p>
                </section>
            </div>
        </div>
    </div>

    <div class="modal" id="preview-modal" role="dialog" aria-modal="true" aria-hidden="true">
        <div class="modal-backdrop" data-close="preview-modal"></div>
        <div class="modal-card" role="document">
            <header>
                <h2>Selection Preview</h2>
                <button class="modal-close" type="button" data-close="preview-modal">
                    <span class="pi pi-cross" aria-hidden="true"></span>
                    Close
                </button>
            </header>
            <div class="modal-body">
                <p class="modal-note">Currently selected apps:</p>
                <ul id="preview-list" class="preview-list"></ul>
            </div>
        </div>
    </div>

    <div class="modal" id="info-modal" role="dialog" aria-modal="true" aria-hidden="true">
        <div class="modal-backdrop" data-close="info-modal"></div>
        <div class="modal-card" role="document">
            <header>
                <h2 id="info-title">App info</h2>
                <button class="modal-close" type="button" data-close="info-modal">
                    <span class="pi pi-cross" aria-hidden="true"></span>
                    Close
                </button>
            </header>
        <div class="modal-body">
            <p id="info-body"></p>
            <p id="info-link" class="info-link-row" hidden></p>
        </div>
    </div>
</div>

    <script src="js/app.js"></script>
</body>
</html>
