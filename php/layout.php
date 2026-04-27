<?php
function linuxmate_version($rootDir) {
    $readme = file_exists($rootDir . '/README.md') ? file_get_contents($rootDir . '/README.md') : '';
    $version = 'v0.21';
    if (preg_match('/LinuxMate\s+(v[0-9.]+)/', $readme, $match)) {
        $version = $match[1];
    }
    return $version;
}

function linuxmate_asset($path, $basePath = '') {
    if ($basePath === '') {
        return ltrim($path, '/');
    }
    return rtrim($basePath, '/') . '/' . ltrim($path, '/');
}

function linuxmate_render_head($title, $description, $basePath = '') {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="<?php echo htmlspecialchars($description); ?>" />
    <title><?php echo htmlspecialchars($title); ?></title>
    <link rel="icon" href="<?php echo htmlspecialchars(linuxmate_asset('favicon.ico', $basePath)); ?>" />
    <link rel="stylesheet" href="<?php echo htmlspecialchars(linuxmate_asset('popicon.css', $basePath)); ?>" />
    <link rel="stylesheet" href="<?php echo htmlspecialchars(linuxmate_asset('css/style.css', $basePath)); ?>" />
</head>
<?php
}

function linuxmate_render_topbar($options = []) {
    $basePath = $options['basePath'] ?? '';
    $version = $options['version'] ?? '';
    $title = $options['title'] ?? 'LinuxMate';
    $tagline = $options['tagline'] ?? 'The Linux Bulk App Installer.<br>Install and update all your Linux programs at once.';
    $note = $options['note'] ?? 'Story behind the tool: <a href="https://www.allroundwebsite.com/blog/bye-windows-hello-linux-and-linuxmate/" target="_blank" rel="noreferrer">Bye Windows, Hello Linux and LinuxMate</a>.';
    $controlsHtml = $options['controlsHtml'] ?? '';
    $active = $options['active'] ?? 'installer';
    ?>
        <header class="topbar">
            <div class="brand">
                <div class="logo" aria-hidden="true">
                    <span class="pi pi-linux logo-icon" aria-hidden="true"></span>
                    <span class="logo-badge pi pi-packageclose"></span>
                </div>
                <div class="brand-text">
                    <div class="brand-title">
                        <h1><?php echo htmlspecialchars($title); ?></h1>
                        <?php if ($version): ?>
                            <span class="version-badge"><?php echo htmlspecialchars($version); ?></span>
                        <?php endif; ?>
                    </div>
                    <p><?php echo $tagline; ?></p>
                    <p class="brand-note"><?php echo $note; ?></p>
                </div>
            </div>
            <div class="topbar-controls">
                <div class="top-links">
                    <a href="<?php echo htmlspecialchars(linuxmate_asset('', $basePath) ?: './'); ?>" <?php echo $active === 'installer' ? 'aria-current="page"' : ''; ?>>
                        <i class="pi pi-packageclose"></i>
                        Installer
                    </a>
                    <a href="<?php echo htmlspecialchars(linuxmate_asset('windows2linux/', $basePath)); ?>" <?php echo $active === 'windows2linux' ? 'aria-current="page"' : ''; ?>>
                        <i class="pi pi-arrowbow"></i>
                        Windows to Linux
                    </a>
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
                <?php echo $controlsHtml; ?>
            </div>
        </header>
<?php
}

function linuxmate_render_installer_controls($distros, $defaultDistroLabel, $basePath = '') {
    ob_start();
    ?>
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
                                        if ($icon && !preg_match('~^(https?:)?//|^data:|^/~', $icon)) {
                                            $icon = linuxmate_asset($icon, $basePath);
                                        }
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
<?php
    return ob_get_clean();
}

function linuxmate_decorate_icon($html) {
    if (strpos($html, '<img') === 0 && strpos($html, 'loading=') === false) {
        return preg_replace('/<img\b/', '<img loading="lazy" decoding="async"', $html, 1);
    }
    return $html;
}

function linuxmate_rebase_icon_html($html, $basePath = '') {
    if ($basePath === '') {
        return $html;
    }
    return preg_replace_callback('/\bsrc=(["\'])(?!https?:|\/\/|data:|\/)([^"\']+)\1/i', static function ($match) use ($basePath) {
        return 'src=' . $match[1] . linuxmate_asset($match[2], $basePath) . $match[1];
    }, $html);
}

function linuxmate_render_script_footer() {
    ?>
        <footer class="script-footer">
            <div class="script-shell">
                <div class="panel-header">
                    <div>
                        <h3>Install script</h3>
                        <p>DRY commands, grouped per package manager.</p>
                    </div>
                    <div class="script-actions">
                        <button id="update-toggle" type="button" aria-pressed="true" data-enabled="true">
                            <span class="pi pi-recycle" aria-hidden="true"></span>
                            <span id="update-toggle-label" class="button-label">Refresh before install</span>
                        </button>
                        <button id="clear" type="button">
                            <span class="pi pi-bin" aria-hidden="true"></span>
                            <span class="button-label">Clear</span>
                        </button>
                        <button id="copy" type="button" data-label="Copy" data-copied="Copied!">
                            <span class="pi pi-copy" aria-hidden="true"></span>
                            <span class="button-label copy-text">Copy</span>
                        </button>
                        <button id="share" type="button" data-label="Share" data-copied="Copied!">
                            <span class="pi pi-arrowbow" aria-hidden="true"></span>
                            <span class="button-label share-text">Share</span>
                        </button>
                        <button id="download" type="button">
                            <span class="pi pi-download" aria-hidden="true"></span>
                            <span class="button-label">Download</span>
                        </button>
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
<?php
}

function linuxmate_render_help_modal($title, $bodyHtml) {
    ?>
    <div class="modal" id="help-modal" role="dialog" aria-modal="true" aria-hidden="true">
        <div class="modal-backdrop" data-close="help-modal"></div>
        <div class="modal-card" role="document">
            <header>
                <h2><?php echo htmlspecialchars($title); ?></h2>
                <button class="modal-close" type="button" data-close="help-modal">
                    <span class="pi pi-cross" aria-hidden="true"></span>
                    Close
                </button>
            </header>
            <div class="modal-body">
                <?php echo $bodyHtml; ?>
            </div>
        </div>
    </div>
<?php
}

function linuxmate_render_installer_modals() {
    ?>
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
<?php
}

function linuxmate_render_page_end($scripts = []) {
    foreach ($scripts as $script) {
        $src = is_array($script) ? ($script['src'] ?? '') : $script;
        if ($src === '') {
            continue;
        }
        ?>
    <script src="<?php echo htmlspecialchars($src); ?>"></script>
<?php
    }
    ?>
</body>
</html>
<?php
}
