#!/usr/bin/env python3
import argparse
import json
import re
import sys
from pathlib import Path

ICONIFY_BASE = "https://api.iconify.design"
DEFAULT_ICON_SET = "simple-icons"
DEFAULT_ICON_NAME = "linux"
FALLBACK_ICON_URL = "assets-icons/tuxbw.svg"
MANAGER_KEYS = ["apt", "dnf", "pacman", "flatpak", "zypper"]


def entry(name, category, **kwargs):
    payload = {"name": name, "category": category}
    payload.update(kwargs)
    return payload


PACKAGE_CATALOG = [
    entry("Ansible", "Dev: Tools"),
    entry("Apache", "VPN & Network", packages={"apt": "apache2", "dnf": "httpd", "zypper": "apache2"}),
    entry("Bash", "Terminal", icon_name="gnubash"),
    entry("Binutils", "System"),
    entry("Blender", "Creative"),
    entry("Boost", "Dev: Tools"),
    entry("Chromium", "Web Browsers"),
    entry("Claws Mail", "Communication", id="claws-mail"),
    entry("CMake", "Dev: Tools"),
    entry("Coreutils", "System"),
    entry("Cppcheck", "Dev: Tools"),
    entry("CUPS", "System", id="cups"),
    entry("Curl", "CLI Tools", id="curl"),
    entry("Darktable", "Creative"),
    entry("DjVuLibre", "Media", id="djvulibre"),
    entry("DOSBox", "Gaming", id="dosbox"),
    entry("Dovecot", "VPN & Network"),
    entry("Doxygen", "Dev: Tools"),
    entry("Emacs", "Dev: Editors"),
    entry("Evince", "Office"),
    entry("FFmpeg", "Media", id="ffmpeg"),
    entry("Firefox", "Web Browsers"),
    entry("Fish", "Terminal"),
    entry("FreeCAD", "Creative", id="freecad"),
    entry("Freeciv", "Gaming"),
    entry("GCC", "Dev: Tools", id="gcc"),
    entry("GDB", "Dev: Tools", id="gdb"),
    entry("Geeqie", "Media"),
    entry("GIMP", "Creative", id="gimp"),
    entry("Git", "Dev: Tools", id="git"),
    entry("GnuPG", "Security", id="gnupg"),
    entry("Go", "Dev: Languages", id="go"),
    entry("Godot", "Creative"),
    entry("Graphviz", "Dev: Tools"),
    entry("GRUB", "System", id="grub"),
    entry("GTK", "Dev: Tools", id="gtk"),
    entry("HAProxy", "VPN & Network", id="haproxy"),
    entry("Hyprland", "Window Managers"),
    entry("i3", "Window Managers"),
    entry("ImageMagick", "Creative", id="imagemagick"),
    entry("Inkscape", "Creative"),
    entry("jq", "CLI Tools", id="jq"),
    entry("Krita", "Creative"),
    entry("Kubernetes", "Dev: Tools", id="kubernetes"),
    entry("LAME", "Media", id="lame"),
    entry("LibreOffice", "Office", id="libreoffice"),
    entry("Linux", "System", id="linux"),
    entry("LLVM", "Dev: Tools", id="llvm"),
    entry("MariaDB", "Databases", id="mariadb"),
    entry("Maxima", "Office"),
    entry("Project MC", "Gaming", id="project-mc", icon_name="minecraft"),
    entry("Mesa", "System"),
    entry("Meson", "Dev: Tools"),
    entry("MPlayer", "Media", id="mplayer"),
    entry("Mutt", "Communication"),
    entry("MySQL", "Databases", id="mysql"),
    entry("Neofetch", "Terminal"),
    entry("Neovim", "Dev: Editors"),
    entry("Nginx", "VPN & Network", id="nginx"),
    entry("Nmap", "Security", id="nmap"),
    entry("Node.js", "Dev: Languages", id="node-js", package="nodejs", icon_name="nodedotjs"),
    entry("Octave", "Office"),
    entry("Okular", "Office"),
    entry("OpenSSH", "Security", id="openssh"),
    entry("OpenSSL", "Security", id="openssl"),
    entry("OpenTTD", "Gaming", id="openttd"),
    entry("OpenVPN", "VPN & Network", id="openvpn"),
    entry("p7zip", "CLI Tools", id="p7zip", icon_name="7zip"),
    entry("Pidgin", "Communication"),
    entry("pip", "Dev: Tools", id="pip", packages={"apt": "python3-pip", "dnf": "python3-pip", "pacman": "python-pip", "zypper": "python3-pip"}),
    entry("Postfix", "VPN & Network", id="postfix"),
    entry("PostgreSQL", "Databases", id="postgresql"),
    entry("Privoxy", "VPN & Network", id="privoxy"),
    entry("Python", "Dev: Languages", id="python", packages={"apt": "python3", "dnf": "python3", "zypper": "python3"}),
    entry("QEMU", "System", id="qemu"),
    entry("Qt", "Dev: Tools", id="qt"),
    entry("rdesktop", "File Sharing", id="rdesktop"),
    entry("Redis", "Databases", id="redis"),
    entry("rsync", "File Sharing", id="rsync"),
    entry("rTorrent", "File Sharing", id="rtorrent"),
    entry("Rust", "Dev: Languages", id="rust"),
    entry("Samba", "File Sharing"),
    entry("SANE Backends", "System", id="sane-backends"),
    entry("Scribus", "Creative"),
    entry("ScummVM", "Gaming", id="scummvm"),
    entry("Smartmontools", "System", id="smartmontools"),
    entry("SQLite", "Databases", id="sqlite"),
    entry("Squid", "VPN & Network", id="squid"),
    entry("Stellarium", "Media"),
    entry("sudo", "System", id="sudo"),
    entry("Thunderbird", "Communication"),
    entry("tmux", "Terminal", id="tmux"),
    entry("Tor", "Security", id="tor"),
    entry("Transmission", "File Sharing"),
    entry("Unbound", "VPN & Network", id="unbound"),
    entry("Valgrind", "Dev: Tools", id="valgrind"),
    entry("Vim", "Dev: Editors"),
    entry("VirtualBox", "System", id="virtualbox"),
    entry("VLC", "Media", id="vlc"),
    entry("Wayland", "System"),
    entry("Project Wesnoth", "Gaming", id="project-wesnoth", icon_name="linux"),
    entry("Wget", "CLI Tools", id="wget"),
    entry("Wine", "System"),
    entry("Wireshark", "Security"),
    entry("Xorg Server", "System", id="xorg-server"),
    entry("Xterm", "Terminal", id="xterm"),
    entry("yt-dlp", "CLI Tools", id="yt-dlp"),
    entry("ZeroMQ", "Dev: Tools", id="zeromq"),
    entry("Zsh", "Terminal", id="zsh"),
    entry("GNOME", "Desktop Environments", id="gnome"),
    entry("KDE Plasma", "Desktop Environments", id="kde-plasma", package="plasma-desktop"),
    entry("Xfce", "Desktop Environments", id="xfce"),
    entry("Cinnamon", "Desktop Environments", id="cinnamon"),
    entry("MATE", "Desktop Environments", id="mate"),
    entry("LXQt", "Desktop Environments", id="lxqt"),
    entry("Budgie", "Desktop Environments", id="budgie"),
    entry("Sway", "Window Managers", id="sway"),
    entry("Openbox", "Window Managers", id="openbox"),
    entry("Awesome", "Window Managers", id="awesome"),
    entry("Bspwm", "Window Managers", id="bspwm"),
    entry("Fluxbox", "Window Managers", id="fluxbox"),
]

MISSING_ICON_IDS = {
    "claws-mail",
    "mutt",
    "pidgin",
    "djvulibre",
    "lame",
    "mplayer",
    "geeqie",
    "evince",
    "maxima",
    "cups",
    "grub",
    "sane-backends",
    "smartmontools",
    "sudo",
    "openbox",
    "awesome",
    "fluxbox",
    "imagemagick",
    "godot",
    "scribus",
    "haproxy",
    "postfix",
    "privoxy",
    "squid",
    "unbound",
    "tor",
    "gcc",
    "gdb",
    "pip",
    "valgrind",
    "xterm",
    "neofetch",
    "jq",
    "yt-dlp",
    "xorg-server",
    "stellarium",
    "binutils",
    "coreutils",
    "kde-plasma",
    "mate",
    "lxqt",
    "dosbox",
    "openttd",
    "scummvm",
    "freeciv",
    "budgie",
    "rdesktop",
    "rtorrent",
    "samba",
    "graphviz",
    "mesa",
    "cppcheck",
    "meson",
}


def normalize(value):
    return re.sub(r"[^a-z0-9]+", "", value.lower()).strip()


def slugify(value):
    value = value.strip().lower()
    value = re.sub(r"[^a-z0-9]+", "-", value)
    return value.strip("-")


def build_icon_url(icon_name, icon_set=DEFAULT_ICON_SET, color=None):
    icon_name = icon_name or DEFAULT_ICON_NAME
    url = f"{ICONIFY_BASE}/{icon_set}/{icon_name}.svg"
    if color:
        color = color.lstrip("#")
        url = f"{url}?color=%23{color}"
    return url


def build_icon_svg(url):
    return (
        '<img class="icon-external" loading="lazy" decoding="async" '
        f'src="{url}" alt="" />'
    )


def build_packages(base, overrides=None):
    packages = {key: base for key in MANAGER_KEYS}
    packages["flatpak"] = None
    if overrides:
        for key, value in overrides.items():
            if key in packages:
                packages[key] = value
    return packages


def build_entry(spec):
    name = spec["name"]
    category = spec["category"]
    entry_id = spec.get("id") or slugify(name)
    description = spec.get("description") or f"{name} for {category}."
    base_package = spec.get("package") or entry_id
    packages = build_packages(base_package, spec.get("packages"))
    if entry_id in MISSING_ICON_IDS:
        icon_svg = build_icon_svg(FALLBACK_ICON_URL)
    else:
        icon_name = spec.get("icon_name") or slugify(spec.get("id", name))
        icon_set = spec.get("icon_set", DEFAULT_ICON_SET)
        icon_color = spec.get("icon_color")
        icon_url = build_icon_url(icon_name, icon_set, icon_color)
        icon_svg = build_icon_svg(icon_url)
    return {
        "id": entry_id,
        "name": name,
        "description": description,
        "category": category,
        "packages": packages,
        "icon_svg": icon_svg,
    }


def ensure_catalog_ids(entries):
    normalized = []
    for entry in entries:
        if not entry.get("id"):
            entry = {**entry, "id": slugify(entry["name"])}
        normalized.append(entry)
    return normalized


def build_lookup(entries):
    lookup = {}
    for entry in entries:
        names = {entry["name"], entry.get("id", "")}
        for alias in entry.get("aliases", []):
            names.add(alias)
        for name in names:
            key = normalize(name)
            if key:
                lookup[key] = entry
    return lookup


def load_json(path):
    if not path.exists():
        return []
    with path.open("r", encoding="utf-8") as handle:
        return json.load(handle)


def write_json(path, payload):
    with path.open("w", encoding="utf-8") as handle:
        json.dump(payload, handle, indent=4, ensure_ascii=True)
        handle.write("\n")


def warn_duplicate_names(entries, context):
    seen = {}
    for idx, entry in enumerate(entries):
        name = entry.get("name", "")
        key = normalize(name)
        if not key:
            continue
        if key in seen:
            first = seen[key]
            first_id = entries[first].get("id", "")
            current_id = entry.get("id", "")
            if first_id != current_id:
                print(
                    f"warning: duplicate name '{name}' in {context} "
                    f"(ids '{first_id}' and '{current_id}')",
                    file=sys.stderr,
                )
        else:
            seen[key] = idx


def dedupe_by_id(entries, context):
    seen = {}
    result = []
    for idx, entry in enumerate(entries):
        entry_id = entry.get("id")
        if not entry_id:
            print(f"warning: entry missing id in {context}; skipping", file=sys.stderr)
            continue
        if entry_id in seen:
            first = seen[entry_id]
            print(
                f"warning: duplicate id '{entry_id}' in {context}; "
                f"keeping index {first}, skipping index {idx}",
                file=sys.stderr,
            )
            continue
        seen[entry_id] = idx
        result.append(entry)
    return result


def add_entries(existing, entries, update_existing=False):
    index = {entry.get("id"): idx for idx, entry in enumerate(existing)}
    added = 0
    updated = 0
    for entry in entries:
        entry_id = entry.get("id")
        if not entry_id:
            continue
        if entry_id in index:
            if update_existing:
                existing[index[entry_id]] = entry
                updated += 1
            else:
                print(
                    f"warning: '{entry_id}' already exists in data; skipping",
                    file=sys.stderr,
                )
            continue
        existing.append(entry)
        added += 1
    return added, updated


def main():
    base_dir = Path(__file__).resolve().parents[1]
    default_data = base_dir / "data" / "packages.json"
    parser = argparse.ArgumentParser(
        description=(
            "Lookup package metadata and append it to data/packages.json. "
            "Icon URLs use Iconify collections."
        )
    )
    parser.add_argument("--data", type=Path, default=default_data)
    parser.add_argument("--list", action="store_true", help="List known packages.")
    parser.add_argument("--all", action="store_true", help="Add all known packages.")
    parser.add_argument(
        "--add",
        nargs="*",
        default=[],
        help="Add specific packages by name (e.g. --add \"GNU Bash\" ffmpeg).",
    )
    parser.add_argument(
        "--update-existing",
        action="store_true",
        help="Overwrite existing entries with known data.",
    )
    parser.add_argument(
        "--fix-icons",
        action="store_true",
        help="Replace known-missing icon slugs with the fallback icon.",
    )
    args = parser.parse_args()

    catalog = ensure_catalog_ids(PACKAGE_CATALOG)
    catalog = dedupe_by_id(catalog, "catalog")
    warn_duplicate_names(catalog, "catalog")
    lookup = build_lookup(catalog)
    if args.list:
        for entry in catalog:
            print(f"{entry['name']} ({entry.get('id', '')}): {entry['category']}")
        return 0

    names = []
    for raw in args.add:
        names.extend([chunk.strip() for chunk in raw.split(",") if chunk.strip()])

    if not args.all and not names and not args.fix_icons:
        parser.print_help()
        return 1

    data = load_json(args.data)
    if not isinstance(data, list):
        print("error: packages.json must contain a list", file=sys.stderr)
        return 1
    data = dedupe_by_id(data, "existing data")
    warn_duplicate_names(data, "existing data")

    if names:
        deduped = []
        seen_names = set()
        for name in names:
            key = normalize(name)
            if key in seen_names:
                print(
                    f"warning: duplicate name '{name}' in request; skipping",
                    file=sys.stderr,
                )
                continue
            seen_names.add(key)
            deduped.append(name)
        names = deduped

    if args.fix_icons:
        for entry in data:
            entry_id = entry.get("id")
            if entry_id in MISSING_ICON_IDS:
                entry["icon_svg"] = build_icon_svg(FALLBACK_ICON_URL)
        write_json(args.data, data)
        print("done: updated icons")
        return 0

    entries = []
    if args.all:
        entries = [build_entry(entry) for entry in catalog]
    else:
        for name in names:
            key = normalize(name)
            if key in lookup:
                entries.append(build_entry(lookup[key]))
                continue
            print(
                f"error: '{name}' not found. Use --list to view catalog.",
                file=sys.stderr,
            )
            return 1

    entries = dedupe_by_id(entries, "requested entries")
    warn_duplicate_names(entries, "requested entries")
    added, updated = add_entries(data, entries, update_existing=args.update_existing)
    write_json(args.data, data)
    print(f"done: added {added}, updated {updated}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
