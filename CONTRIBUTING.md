# Contributing to LinuxMate

Thanks for helping improve LinuxMate! The goal is to keep contributions easy, clean, and correct.

LinuxMate is licensed under **GNU GPL v3.0**, and contributions are accepted under the same license.

---

## ⚠️ Before You Start

Your PR will be rejected if you:
- ❌ Submit unverified package names
- ❌ Use the wrong casing for distro packages (e.g., openSUSE is case-sensitive)
- ❌ Put AUR-only packages under official Arch (`pacman`) packages
- ❌ Use partial Flatpak IDs instead of full App IDs
- ❌ Add PPAs or unofficial repos for apt packages
- ❌ Use incorrect Homebrew formats (GUI apps must use `--cask`)

---

## ✅ Where to edit

All apps live in:
- `data/packages.json`

Icons live in:
- `icons/` (referenced from `data/packages.json`)

---

## 📦 Adding or Updating Apps

Each entry in `data/packages.json` should look like this:

```json
{
  "id": "app-id",
  "name": "App Name",
  "description": "Short description",
  "category": "Category",
  "packages": {
    "apt": "exact-package-name",
    "dnf": "exact-package-name",
    "pacman": "exact-package-name",
    "flatpak": "com.vendor.AppId",
    "snap": "snap-name",
    "brew": "formula-or-cask"
  },
  "icon_svg": "<svg>..." 
}
```

### Rules

- **Exact package names only** (no guesses).
- **Flatpak IDs must be full App IDs** (e.g., `org.mozilla.firefox`).
- **Snap packages that require classic confinement must include `--classic`**.
- **Homebrew GUI apps must use `--cask`**.
- If a package is not available for a manager, **omit the key entirely**.

---

## 🔍 Verification Sources (Required)

Check official sources before submitting:

- Repology: https://repology.org/
- Arch (official): https://archlinux.org/packages/
- AUR: https://aur.archlinux.org/
- Ubuntu: https://packages.ubuntu.com/
- Debian: https://packages.debian.org/
- Fedora: https://packages.fedoraproject.org/
- openSUSE: https://software.opensuse.org/
- NixOS: https://search.nixos.org/packages
- Flatpak: https://flathub.org/
- Snap: https://snapcraft.io/
- Homebrew: https://formulae.brew.sh/

---

## ✅ Quick Checklist

- [ ] Package names verified from official sources
- [ ] Category is valid and consistent
- [ ] Flatpak IDs are full App IDs
- [ ] Snap classic packages include `--classic`
- [ ] Homebrew GUI apps use `--cask`
- [ ] Icons render at 24x24px

---

## 🐛 Bugs / Feature Requests

Open an issue on GitHub:
- https://github.com/Henkster72/LinuxMate/issues

---

Thanks for making LinuxMate better.
