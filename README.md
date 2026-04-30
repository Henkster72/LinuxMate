# LinuxMate v0.9 🐧✨

<img src="assets-icons/tuxbw.svg" alt="LinuxMate logo" width="96" />

LinuxMate is a lightweight, dependency-free bulk app installer UI for Linux — built with **PHP**, **vanilla JavaScript**, and **plain CSS**.

Live demo: [https://linuxmate.org/](https://linuxmate.org/)

It renders server-side, generates **clean batched install scripts** per package manager, and runs happily on basic PHP hosting.  
No build step. No tooling ceremony. Just open it and go.

---

## Changelog

- v0.9: Add Discord community link and move install methods into app info

- v0.8: Windows-to-Linux now shows a visible LinuxQuestions source overview link, links the Help data source text, and hides the browser-native search clear icon so filtering only shows one clear button.
- v0.7: Load distros from `data/distro.json`, make sandbox modes respect non-null package IDs, and list AppImage placeholders as commented search URLs in scripts.
- v0.61: Moved the refresh toggle into the footer, hid script-action labels on small screens, and tailored mobile topbar selects for consistent outlines and heights.
- v0.6: Added custom-install footer tips (path/curl setup advice plus Flatpak reminder), capped the mobile script footer height, and improved CSV sync so “None” cells keep existing AppImage/custom-script data.
- v0.52: Align package action buttons with a right-side wrapper.
- v0.51: Mobile topbar/footer spacing and typography tuned to avoid overlap.
- v0.5: Sandbox adds Snap/AppImage/custom scripts, AppImage search helpers, and Pop Icon linux fallbacks.
- v0.4: Sandbox selector (Flatpak/Snap/AppImage), desktop-environment warning, and icons now live in `assets-icons/`.
- v0.3: Tighten the hero/search area, inline stats, version badge, and single clear button.
- v0.21: Fix search filtering by honoring the `hidden` attribute in CSS.
- v0.2: Move distros to JSON, show AUR helper only when needed, and refresh stats + mobile footer.
- v0.1: Initial release.

---

## What it does

- ✅ Pick apps, choose a distro, and generate a **single batched install script** (not one command per app).
- ✅ Uses a **DRY data model** in `data/packages.json` (metadata + package IDs — no hardcoded commands).
- ✅ Groups selected apps per package manager:
  - `apt` → one `apt install ...` line
  - `dnf` → one `dnf install ...` line
  - `pacman` → one `pacman -S ...` line
  - `flatpak` → one `flatpak install ...` line
- ✅ Sandbox selector can prefer Flatpak, Snap, or AppImage when available and shows a warning while selecting desktop environments/window managers.
- ✅ Supports **icons** via inline SVG or local files for fast rendering (no CDN required).
- ✅ Shareable via **URL parameters** (bookmark a selection and send it to a friend like it’s 2009, but in a good way).

---

## Why it’s nice

- ⚡ **Fast first load** (server renders HTML immediately)
- 🧼 **Simple to deploy** (copy files, done)
- 🧠 **Easy to extend** (add apps in JSON, not in code)
- 🧩 **Browser-friendly** (minimal JS — only what you actually need)
- 📘 **Further reading**: see [Bye Windows, Hello Linux and LinuxMate](https://www.allroundwebsite.com/blog/bye-windows-hello-linux-and-linuxmate/) for the story behind this tool.

---

## Run locally

```bash
php -S 127.0.0.1:8000
````

Then open: `http://127.0.0.1:8000`

---

## URL parameters (shareable selections)

LinuxMate can load preselected distro/apps from the URL (handy for sharing presets):

Example idea:

* `?distro=ubuntu&apps=firefox,vlc,gimp`

(Exact parameter names depend on your implementation — update this section if you change them.)

---

## Customize apps and icons

### Edit the app list

* Update `data/packages.json` to add, remove, or modify packages.

Recommended package entry fields:

* `id`, `name`, `description`, `category`
* `packages` object mapping package managers → package IDs
* `icon_svg` (inline SVG) or `icon_path` (local file)

### Icons

 * Local icons live in `assets-icons/`
* You can embed SVG inline in `packages.json` for fewer file reads, or reference files for easier swapping.

---

## Deploy

Copy the project to any PHP-capable host and open `index.php`.

If your repo uses an `output/` directory, deploy that instead:

* Upload `output/` to your server
* Visit `output/index.php`

---

## Fork and rebrand

1. Fork the repo
2. Update the title + links in `index.php` and this README
3. Replace `icons/tuxbw.svg` with your logo
4. Change the project name everywhere you feel like it (go wild)

## Contributing

See `CONTRIBUTING.md`.

---

## URL parameters (shareable selections)

LinuxMate can load preselected distro/apps from the URL (handy for sharing presets):

Example:

- `?distro=ubuntu&apps=firefox,vlc,gimp`

---

## License

GNU GPL v3.0
