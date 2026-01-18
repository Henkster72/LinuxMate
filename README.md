# LinuxMate v0.1 🐧✨

<img src="icons/tuxbw.svg" alt="LinuxMate logo" width="96" />

LinuxMate is a lightweight, dependency-free bulk app installer UI for Linux — built with **PHP**, **vanilla JavaScript**, and **plain CSS**.

It renders server-side, generates **clean batched install scripts** per package manager, and runs happily on basic PHP hosting.  
No build step. No tooling ceremony. Just open it and go.

---

## What it does

- ✅ Pick apps, choose a distro, and generate a **single batched install script** (not one command per app).
- ✅ Uses a **DRY data model** in `data/packages.json` (metadata + package IDs — no hardcoded commands).
- ✅ Groups selected apps per package manager:
  - `apt` → one `apt install ...` line
  - `dnf` → one `dnf install ...` line
  - `pacman` → one `pacman -S ...` line
  - `flatpak` → one `flatpak install ...` line
- ✅ Supports **icons** via inline SVG or local files for fast rendering (no CDN required).
- ✅ Shareable via **URL parameters** (bookmark a selection and send it to a friend like it’s 2009, but in a good way).

---

## Why it’s nice

- ⚡ **Fast first load** (server renders HTML immediately)
- 🧼 **Simple to deploy** (copy files, done)
- 🧠 **Easy to extend** (add apps in JSON, not in code)
- 🧩 **Browser-friendly** (minimal JS — only what you actually need)

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

* Local icons live in `icons/`
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
