const distroSelect = document.getElementById('distro');
const aurWrap = document.getElementById('aur-wrap');
const aurHelper = document.getElementById('aur-helper');
const scriptOutput = document.getElementById('script-output');
const selectedCount = document.getElementById('selected-count');
const searchInput = document.getElementById('search');
const searchClear = document.getElementById('search-clear');
const searchWrap = document.querySelector('.search-wrap');
const clearBtn = document.getElementById('clear');
const copyBtn = document.getElementById('copy');
const downloadBtn = document.getElementById('download');
const copyText = copyBtn?.querySelector('.copy-text');
const shareBtn = document.getElementById('share');
const shareText = shareBtn?.querySelector('.share-text');
const helpButton = document.getElementById('help-button');
const themeToggle = document.getElementById('theme-toggle');
const themeIcon = document.getElementById('theme-icon');
const helpModal = document.getElementById('help-modal');
const previewModal = document.getElementById('preview-modal');
const infoModal = document.getElementById('info-modal');
const previewList = document.getElementById('preview-list');
const infoTitle = document.getElementById('info-title');
const infoBody = document.getElementById('info-body');
const distroButton = document.getElementById('distro-button');
const distroMenu = document.getElementById('distro-menu');
const distroButtonIcon = document.querySelector('.distro-button-icon');
const distroButtonLabel = document.querySelector('.distro-button-label');
const distroShell = document.querySelector('.distro-shell');
const initialUrlState = (() => {
    const params = new URLSearchParams(window.location.search);
    const distro = params.get('distro');
    const apps = params.get('apps');
    return {
        distro,
        apps: apps ? apps.split(',').map((id) => id.trim()).filter(Boolean) : [],
    };
})();

const packageItems = Array.from(document.querySelectorAll('.package-item'));
const checkboxes = Array.from(document.querySelectorAll('.pkg-check'));
const selected = new Set();
const packageMeta = new Map();
let focusIndex = 0;
let pendingRequest = null;
let debounceTimer = null;
const feedbackTimers = new WeakMap();

packageItems.forEach((item) => {
    const checkbox = item.querySelector('.pkg-check');
    packageMeta.set(checkbox.dataset.id, {
        name: item.dataset.name,
        description: item.dataset.description,
        element: item,
    });
    checkbox.addEventListener('focus', () => {
        const visibleItems = getVisibleItems();
        const idx = visibleItems.indexOf(item);
        if (idx >= 0) {
            focusIndex = idx;
        }
    });
});

const getActiveManagers = () => {
    const option = distroSelect.options[distroSelect.selectedIndex];
    const list = option.dataset.managers || '';
    return list
        .split(',')
        .map((entry) => entry.trim())
        .filter(Boolean);
};

const updateDistroButton = () => {
    const option = distroSelect.options[distroSelect.selectedIndex];
    if (distroButtonIcon) {
        distroButtonIcon.src = option.dataset.icon || '';
    }
    if (distroButtonLabel) {
        distroButtonLabel.textContent = option.textContent;
    }
    if (distroMenu) {
        distroMenu.querySelectorAll('.distro-option').forEach((item) => {
            item.classList.toggle(
                'is-active',
                item.dataset.value === option.value
            );
        });
    }
};

const buildDistroMenu = () => {
    if (!distroMenu) {
        return;
    }
    distroMenu.innerHTML = '';
    Array.from(distroSelect.options).forEach((option) => {
        const li = document.createElement('li');
        li.className = 'distro-option';
        li.dataset.value = option.value;
        li.setAttribute('role', 'option');
        if (option.value === distroSelect.value) {
            li.classList.add('is-active');
        }

        const img = document.createElement('img');
        img.src = option.dataset.icon || '';
        img.alt = '';

        const label = document.createElement('span');
        label.textContent = option.textContent;

        li.append(img, label);
        li.addEventListener('click', () => {
            distroSelect.value = option.value;
            distroSelect.dispatchEvent(new Event('change', { bubbles: true }));
            distroMenu.classList.remove('is-open');
            if (distroButton) {
                distroButton.setAttribute('aria-expanded', 'false');
            }
        });
        distroMenu.appendChild(li);
    });
};

const toggleDistroMenu = () => {
    if (!distroMenu || !distroButton) {
        return;
    }
    const isOpen = distroMenu.classList.toggle('is-open');
    distroButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
};

const closeDistroMenu = () => {
    if (!distroMenu || !distroButton) {
        return;
    }
    distroMenu.classList.remove('is-open');
    distroButton.setAttribute('aria-expanded', 'false');
};

const triggerButtonFeedback = (button, textEl) => {
    if (!button) {
        return;
    }
    button.classList.add('is-copied');
    if (textEl) {
        textEl.textContent = button.dataset.copied || 'Copied!';
    }
    const existing = feedbackTimers.get(button);
    if (existing) {
        clearTimeout(existing);
    }
    const timer = setTimeout(() => {
        button.classList.remove('is-copied');
        if (textEl) {
            textEl.textContent = button.dataset.label || 'Copy';
        }
        feedbackTimers.delete(button);
    }, 3000);
    feedbackTimers.set(button, timer);
};

const copyTextToClipboard = async (text) => {
    if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(text);
        return;
    }
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand('copy');
    textarea.remove();
};

const buildShareUrl = () => {
    const base = `${window.location.origin}${window.location.pathname}`;
    const params = new URLSearchParams();
    if (distroSelect.value) {
        params.set('distro', distroSelect.value);
    }
    if (selected.size) {
        const apps = Array.from(selected).sort().join(',');
        params.set('apps', apps);
    }
    const query = params.toString();
    return query ? `${base}?${query}` : base;
};

const updateSelectedCount = () => {
    selectedCount.textContent = selected.size;
};

const getVisibleItems = () =>
    packageItems.filter((item) => {
        if (item.hidden || item.classList.contains('is-disabled')) {
            return false;
        }
        const card = item.closest('.category-card');
        if (card && card.classList.contains('is-collapsed')) {
            return false;
        }
        return item.offsetParent !== null;
    });

const focusItemByIndex = (index) => {
    const items = getVisibleItems();
    if (!items.length) {
        return;
    }
    const safeIndex = ((index % items.length) + items.length) % items.length;
    focusIndex = safeIndex;
    const checkbox = items[safeIndex].querySelector('.pkg-check');
    checkbox.focus({ preventScroll: false });
};

const focusItemByDirection = (direction) => {
    const items = getVisibleItems();
    if (!items.length) {
        return;
    }
    const active = document.activeElement?.closest('.package-item');
    const base = items.includes(active) ? active : items[focusIndex] || items[0];
    if (!base) {
        return;
    }
    const baseRect = base.getBoundingClientRect();
    const baseX = baseRect.left + baseRect.width / 2;
    const baseY = baseRect.top + baseRect.height / 2;
    let best = null;
    let bestScore = Number.POSITIVE_INFINITY;

    items.forEach((item) => {
        if (item === base) {
            return;
        }
        const rect = item.getBoundingClientRect();
        const x = rect.left + rect.width / 2;
        const y = rect.top + rect.height / 2;
        const dx = x - baseX;
        const dy = y - baseY;

        if (direction === 'left' && dx >= -2) {
            return;
        }
        if (direction === 'right' && dx <= 2) {
            return;
        }
        if (direction === 'up' && dy >= -2) {
            return;
        }
        if (direction === 'down' && dy <= 2) {
            return;
        }

        const primary = direction === 'left' || direction === 'right'
            ? Math.abs(dx)
            : Math.abs(dy);
        const secondary = direction === 'left' || direction === 'right'
            ? Math.abs(dy)
            : Math.abs(dx);
        const score = primary * primary + secondary * secondary * 0.6;
        if (score < bestScore) {
            bestScore = score;
            best = item;
        }
    });

    if (best) {
        const checkbox = best.querySelector('.pkg-check');
        checkbox.focus({ preventScroll: false });
        const idx = items.indexOf(best);
        if (idx >= 0) {
            focusIndex = idx;
        }
        return;
    }

    if (direction === 'left' || direction === 'up') {
        focusItemByIndex(focusIndex - 1);
    } else {
        focusItemByIndex(focusIndex + 1);
    }
};

const updateAvailability = () => {
    const activeManagers = new Set(getActiveManagers());
    const showAur = activeManagers.has('aur');
    aurWrap.hidden = !showAur;

    packageItems.forEach((item) => {
        const checkbox = item.querySelector('.pkg-check');
        const managers = (item.dataset.managers || '')
            .split(',')
            .map((entry) => entry.trim())
            .filter(Boolean);
        const available = managers.some((manager) => activeManagers.has(manager));
        item.classList.toggle('is-disabled', !available);
        checkbox.disabled = !available;
        if (!available) {
            checkbox.checked = false;
            selected.delete(checkbox.dataset.id);
        }
    });

    updateSelectedCount();
};

const fetchScript = async () => {
    if (pendingRequest) {
        pendingRequest.abort();
    }
    const controller = new AbortController();
    pendingRequest = controller;

    try {
        const response = await fetch('php/generate.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                distro: distroSelect.value,
                packages: Array.from(selected),
                aur_helper: aurHelper ? aurHelper.value : 'yay',
            }),
            signal: controller.signal,
        });
        if (!response.ok) {
            throw new Error('Failed to generate script.');
        }
        const payload = await response.json();
        scriptOutput.textContent = payload.script || '';
    } catch (error) {
        if (error.name !== 'AbortError') {
            scriptOutput.textContent = '# Unable to generate script right now.';
        }
    }
};

const scheduleUpdate = () => {
    updateSelectedCount();
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(fetchScript, 120);
};

const openModal = (modal) => {
    if (!modal) {
        return;
    }
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');
};

const closeModal = (modal) => {
    if (!modal) {
        return;
    }
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    if (!document.querySelector('.modal.is-open')) {
        document.body.classList.remove('modal-open');
    }
};

const closeAllModals = () => {
    document.querySelectorAll('.modal.is-open').forEach((modal) => {
        closeModal(modal);
    });
};

const openPreview = () => {
    const items = Array.from(selected)
        .map((id) => packageMeta.get(id)?.name)
        .filter(Boolean)
        .sort((a, b) => a.localeCompare(b));

    previewList.innerHTML = '';
    if (!items.length) {
        const empty = document.createElement('li');
        empty.textContent = 'No apps selected yet.';
        previewList.appendChild(empty);
    } else {
        items.forEach((name) => {
            const li = document.createElement('li');
            li.textContent = name;
            previewList.appendChild(li);
        });
    }
    openModal(previewModal);
};

const setTheme = (theme) => {
    document.body.dataset.theme = theme;
    themeToggle.setAttribute('aria-pressed', theme === 'dark');
    localStorage.setItem('linuxmate-theme', theme);
    if (themeIcon) {
        themeIcon.className = theme === 'dark' ? 'pi pi-sun' : 'pi pi-moon';
    }
};

const toggleTheme = () => {
    const current = document.body.dataset.theme === 'dark' ? 'dark' : 'light';
    const next = current === 'dark' ? 'light' : 'dark';
    setTheme(next);
};

checkboxes.forEach((checkbox) => {
    checkbox.addEventListener('change', (event) => {
        const id = event.target.dataset.id;
        if (event.target.checked) {
            selected.add(id);
        } else {
            selected.delete(id);
        }
        scheduleUpdate();
    });
});

packageItems.forEach((item) => {
    const infoBtn = item.querySelector('.info-btn');
    infoBtn.addEventListener('click', (event) => {
        event.stopPropagation();
        const name = item.dataset.name;
        const description = item.dataset.description;
        const note = item.classList.contains('is-disabled')
            ? 'This app is not available for the selected distro. Try Flatpak or Snap.'
            : 'Available for this distro.';
        infoTitle.textContent = name;
        infoBody.textContent = description + ' ' + note;
        openModal(infoModal);
    });
});

document.querySelectorAll('.category-toggle').forEach((toggle) => {
    toggle.addEventListener('click', () => {
        const card = toggle.closest('.category-card');
        if (!card) {
            return;
        }
        const collapsed = card.classList.toggle('is-collapsed');
        toggle.setAttribute('aria-expanded', (!collapsed).toString());
        const activeItem = document.activeElement?.closest('.package-item');
        if (collapsed && activeItem && card.contains(activeItem)) {
            focusItemByDirection('right');
        }
    });
});

searchInput.addEventListener('input', (event) => {
    const query = event.target.value.trim().toLowerCase();
    if (searchWrap) {
        searchWrap.classList.toggle('has-value', query.length > 0);
    }
    const tokens = query.split(/\s+/).filter(Boolean);
    packageItems.forEach((item) => {
        const base = item.dataset.search
            ? item.dataset.search
            : `${item.dataset.name} ${item.dataset.description}`.toLowerCase();
        const match = tokens.length
            ? tokens.every((token) => base.includes(token))
            : true;
        item.hidden = !match;
    });
    document.querySelectorAll('.category-card').forEach((card) => {
        const visible = card.querySelector('.package-item:not([hidden])');
        card.hidden = !visible;
    });
    focusIndex = 0;
});

searchClear?.addEventListener('click', () => {
    searchInput.value = '';
    searchInput.dispatchEvent(new Event('input', { bubbles: true }));
    searchInput.focus();
});

clearBtn.addEventListener('click', () => {
    selected.clear();
    checkboxes.forEach((checkbox) => {
        checkbox.checked = false;
    });
    scheduleUpdate();
});

const copyToClipboard = async () => {
    const text = scriptOutput.textContent.trim();
    if (!text) {
        return;
    }
    await copyTextToClipboard(text);
    triggerButtonFeedback(copyBtn, copyText);
};

copyBtn.addEventListener('click', () => {
    copyToClipboard().catch(() => {});
});

shareBtn?.addEventListener('click', () => {
    const url = buildShareUrl();
    copyTextToClipboard(url)
        .then(() => triggerButtonFeedback(shareBtn, shareText))
        .catch(() => {});
});

downloadBtn.addEventListener('click', () => {
    const text = scriptOutput.textContent.trim();
    if (!text) {
        return;
    }
    const blob = new Blob([text], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'linuxmate-install.sh';
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
});

helpButton.addEventListener('click', () => {
    openModal(helpModal);
});

themeToggle.addEventListener('click', toggleTheme);

aurHelper?.addEventListener('change', scheduleUpdate);

distroSelect.addEventListener('change', () => {
    updateAvailability();
    scheduleUpdate();
    updateDistroButton();
});

distroButton?.addEventListener('click', (event) => {
    event.stopPropagation();
    toggleDistroMenu();
});

distroShell?.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        toggleDistroMenu();
    }
    if (event.key === 'Escape') {
        closeDistroMenu();
    }
});

document.addEventListener('click', (event) => {
    if (!distroShell || !distroMenu) {
        return;
    }
    if (!distroShell.contains(event.target)) {
        closeDistroMenu();
    }
});

window.addEventListener('keydown', (event) => {
    const target = event.target;
    const isTyping =
        target.matches('input, textarea, select') &&
        !target.classList.contains('pkg-check');

    if (event.key === 'Escape') {
        if (distroMenu && distroMenu.classList.contains('is-open')) {
            closeDistroMenu();
            return;
        }
        closeAllModals();
        return;
    }

    if (document.querySelector('.modal.is-open')) {
        return;
    }

    if (distroMenu && distroMenu.classList.contains('is-open')) {
        return;
    }

    if (isTyping) {
        return;
    }

    switch (event.key) {
        case '?':
            event.preventDefault();
            openModal(helpModal);
            break;
        case '/':
            event.preventDefault();
            searchInput.focus();
            break;
        case 'Tab':
            event.preventDefault();
            openPreview();
            break;
        case 't':
            event.preventDefault();
            toggleTheme();
            break;
        case 'y':
            event.preventDefault();
            copyToClipboard().catch(() => {});
            break;
        case 'd':
            event.preventDefault();
            downloadBtn.click();
            break;
        case 'c':
            event.preventDefault();
            clearBtn.click();
            break;
        case '1':
            if (!aurWrap.hidden) {
                aurHelper.value = 'yay';
                scheduleUpdate();
            }
            break;
        case '2':
            if (!aurWrap.hidden) {
                aurHelper.value = 'paru';
                scheduleUpdate();
            }
            break;
        case 'ArrowDown':
        case 'j':
        case 'ArrowRight':
        case 'l':
            event.preventDefault();
            focusItemByDirection(
                event.key === 'ArrowDown' || event.key === 'j' ? 'down' : 'right'
            );
            break;
        case 'ArrowUp':
        case 'k':
        case 'ArrowLeft':
        case 'h':
            event.preventDefault();
            focusItemByDirection(
                event.key === 'ArrowUp' || event.key === 'k' ? 'up' : 'left'
            );
            break;
        case ' ':
            event.preventDefault();
            const items = getVisibleItems();
            if (!items.length) {
                break;
            }
            const checkbox = items[focusIndex].querySelector('.pkg-check');
            if (!checkbox.disabled) {
                checkbox.checked = !checkbox.checked;
                checkbox.dispatchEvent(new Event('change', { bubbles: true }));
            }
            break;
        default:
            break;
    }
});

Array.from(document.querySelectorAll('[data-close]')).forEach((button) => {
    button.addEventListener('click', () => {
        const targetId = button.dataset.close;
        const modal = document.getElementById(targetId);
        closeModal(modal);
    });
});

document.querySelectorAll('.modal-backdrop').forEach((backdrop) => {
    backdrop.addEventListener('click', () => {
        const targetId = backdrop.dataset.close;
        const modal = document.getElementById(targetId);
        closeModal(modal);
    });
});

const applyUrlSelections = () => {
    if (initialUrlState.distro) {
        const match = Array.from(distroSelect.options).some(
            (option) => option.value === initialUrlState.distro
        );
        if (match) {
            distroSelect.value = initialUrlState.distro;
        }
    }

    if (initialUrlState.apps.length) {
        initialUrlState.apps.forEach((id) => {
            const checkbox = document.querySelector(
                `.pkg-check[data-id=\"${CSS.escape(id)}\"]`
            );
            if (checkbox && !checkbox.disabled) {
                checkbox.checked = true;
                selected.add(id);
            }
        });
    }
};

const savedTheme = localStorage.getItem('linuxmate-theme');
if (savedTheme) {
    setTheme(savedTheme);
}

updateAvailability();
buildDistroMenu();
applyUrlSelections();
updateAvailability();
updateSelectedCount();
buildDistroMenu();
updateDistroButton();
scheduleUpdate();

const initialQuery = searchInput.value.trim();
if (searchWrap) {
    searchWrap.classList.toggle('has-value', initialQuery.length > 0);
}
