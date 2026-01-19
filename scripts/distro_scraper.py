#!/usr/bin/env python3
import argparse
import json
import re
import sys
from pathlib import Path

ICONIFY_BASE = "https://api.iconify.design"
DEFAULT_ICON_SET = "simple-icons"
KNOWN_MANAGERS = {"apt", "dnf", "pacman", "zypper", "flatpak", "aur", "snap", "brew"}
FALLBACK_ICON_URL = "icons/tuxbw.svg"

DISTRO_CATALOG = [
    {
        "label": "AlmaLinux",
        "value": "almalinux",
        "icon_name": "almalinux",
        "managers": ["dnf", "flatpak"],
        "aliases": ["alma"],
    },
    {
        "label": "AnduinOS",
        "value": "anduinos",
        "icon_name": "anduinos",
        "managers": ["apt", "flatpak"],
        "aliases": ["anduin os"],
    },
    {
        "label": "antiX",
        "value": "antix",
        "icon_name": "antix",
        "managers": ["apt", "flatpak"],
        "aliases": ["anti x"],
    },
    {
        "label": "Arch",
        "value": "arch",
        "icon_name": "archlinux",
        "icon_color": "1793D1",
        "managers": ["pacman", "aur", "flatpak"],
        "aliases": ["archlinux"],
    },
    {
        "label": "Bazzite",
        "value": "bazzite",
        "icon_name": "bazzite",
        "managers": ["dnf", "flatpak"],
    },
    {
        "label": "BigLinux",
        "value": "biglinux",
        "icon_name": "biglinux",
        "managers": ["pacman", "aur", "flatpak"],
    },
    {
        "label": "Bluestar",
        "value": "bluestar",
        "icon_name": "bluestar",
        "managers": ["pacman", "aur", "flatpak"],
        "aliases": ["bluestar linux"],
    },
    {
        "label": "CachyOS",
        "value": "cachyos",
        "icon_name": "cachyos",
        "managers": ["pacman", "aur", "flatpak"],
    },
    {
        "label": "CentOS",
        "value": "centos",
        "icon_name": "centos",
        "managers": ["dnf", "flatpak"],
        "aliases": ["centos stream"],
    },
    {
        "label": "Debian",
        "value": "debian",
        "icon_name": "debian",
        "icon_color": "A81D33",
        "managers": ["apt", "flatpak"],
    },
    {
        "label": "Elementary OS",
        "value": "elementary",
        "icon_name": "elementary",
        "managers": ["apt", "flatpak"],
        "aliases": ["elementaryos", "elementary os"],
    },
    {
        "label": "EndeavourOS",
        "value": "endeavouros",
        "icon_name": "endeavouros",
        "managers": ["pacman", "aur", "flatpak"],
        "aliases": ["endeavoros"],
    },
    {
        "label": "Fedora",
        "value": "fedora",
        "icon_name": "fedora",
        "icon_color": "51A2DA",
        "managers": ["dnf", "flatpak"],
    },
    {
        "label": "Flatpak",
        "value": "flatpak",
        "icon_name": "flatpak",
        "icon_color": "4A90D9",
        "managers": ["flatpak"],
    },
    {
        "label": "Garuda",
        "value": "garuda",
        "icon_name": "garudalinux",
        "managers": ["pacman", "aur", "flatpak"],
        "aliases": ["garuda linux"],
    },
    {
        "label": "Homebrew",
        "value": "homebrew",
        "icon_name": "homebrew",
        "icon_color": "FBB040",
        "managers": ["brew"],
    },
    {
        "label": "Kali Linux",
        "value": "kali",
        "icon_name": "kalilinux",
        "managers": ["apt", "flatpak"],
        "aliases": ["kali"],
    },
    {
        "label": "KDE neon",
        "value": "kdeneon",
        "icon_name": "kdeneon",
        "managers": ["apt", "flatpak"],
        "aliases": ["kde neon"],
    },
    {
        "label": "Linux Mint",
        "value": "linuxmint",
        "icon_name": "linuxmint",
        "managers": ["apt", "flatpak"],
        "aliases": ["mint"],
    },
    {
        "label": "Manjaro",
        "value": "manjaro",
        "icon_name": "manjaro",
        "managers": ["pacman", "aur", "flatpak"],
    },
    {
        "label": "MiniOS",
        "value": "minios",
        "icon_name": "minios",
        "managers": ["apt", "flatpak"],
    },
    {
        "label": "MX Linux",
        "value": "mxlinux",
        "icon_name": "mxlinux",
        "managers": ["apt", "flatpak"],
        "aliases": ["mx"],
    },
    {
        "label": "Nix",
        "value": "nix",
        "icon_name": "nixos",
        "icon_color": "5277C3",
        "managers": ["flatpak"],
        "aliases": ["nixos"],
    },
    {
        "label": "Nobara",
        "value": "nobara",
        "icon_name": "nobara",
        "managers": ["dnf", "flatpak"],
        "aliases": ["nobara linux"],
    },
    {
        "label": "OpenSUSE",
        "value": "opensuse",
        "icon_name": "opensuse",
        "icon_color": "73BA25",
        "managers": ["zypper", "flatpak"],
        "aliases": ["suse"],
    },
    {
        "label": "PikaOS",
        "value": "pikaos",
        "icon_name": "pikaos",
        "managers": ["apt", "flatpak"],
    },
    {
        "label": "Pop!_OS",
        "value": "popos",
        "icon_name": "popos",
        "managers": ["apt", "flatpak"],
        "aliases": ["pop os", "pop!os"],
    },
    {
        "label": "Q4OS",
        "value": "q4os",
        "icon_name": "q4os",
        "managers": ["apt", "flatpak"],
    },
    {
        "label": "Rocky Linux",
        "value": "rockylinux",
        "icon_name": "rockylinux",
        "managers": ["dnf", "flatpak"],
        "aliases": ["rocky"],
    },
    {
        "label": "Snap",
        "value": "snap",
        "icon_name": "snapcraft",
        "icon_color": "82BEA0",
        "managers": ["snap"],
    },
    {
        "label": "Ubuntu",
        "value": "ubuntu",
        "icon_name": "ubuntu",
        "icon_color": "E95420",
        "managers": ["apt", "flatpak"],
        "aliases": ["ubuntu linux"],
    },
    {
        "label": "Zorin OS",
        "value": "zorin",
        "icon_name": "zorin",
        "managers": ["apt", "flatpak"],
        "aliases": ["zorinos", "zorin"],
    },
]

MISSING_ICON_VALUES = {
    "cachyos",
    "anduinos",
    "nobara",
    "bluestar",
    "bazzite",
    "biglinux",
    "antix",
    "q4os",
    "pikaos",
    "minios",
}


def normalize(value):
    return re.sub(r"[^a-z0-9]+", "", value.lower()).strip()


def slugify(value):
    value = value.strip().lower()
    value = re.sub(r"[^a-z0-9]+", "-", value)
    return value.strip("-")


def build_icon_url(icon_name, icon_set=DEFAULT_ICON_SET, color=None):
    if not icon_name:
        return ""
    url = f"{ICONIFY_BASE}/{icon_set}/{icon_name}.svg"
    if color:
        color = color.lstrip("#")
        url = f"{url}?color=%23{color}"
    return url


def build_lookup(entries):
    lookup = {}
    for entry in entries:
        names = {entry["label"], entry["value"]}
        for alias in entry.get("aliases", []):
            names.add(alias)
        for name in names:
            key = normalize(name)
            if key:
                lookup[key] = entry
    return lookup


def warn_duplicate_labels(entries, context):
    seen = {}
    for idx, entry in enumerate(entries):
        label = entry.get("label", "")
        key = normalize(label)
        if not key:
            continue
        if key in seen:
            first = seen[key]
            first_value = entries[first].get("value", "")
            current_value = entry.get("value", "")
            if first_value != current_value:
                print(
                    f"warning: duplicate label '{label}' in {context} "
                    f"(values '{first_value}' and '{current_value}')",
                    file=sys.stderr,
                )
        else:
            seen[key] = idx


def dedupe_by_value(entries, context):
    seen = {}
    result = []
    for idx, entry in enumerate(entries):
        value = entry.get("value")
        if not value:
            print(f"warning: entry missing value in {context}; skipping", file=sys.stderr)
            continue
        if value in seen:
            first = seen[value]
            print(
                f"warning: duplicate value '{value}' in {context}; "
                f"keeping index {first}, skipping index {idx}",
                file=sys.stderr,
            )
            continue
        seen[value] = idx
        result.append(entry)
    return result


def load_json(path):
    if not path.exists():
        return []
    with path.open("r", encoding="utf-8") as handle:
        return json.load(handle)


def write_json(path, payload):
    with path.open("w", encoding="utf-8") as handle:
        json.dump(payload, handle, indent=2, ensure_ascii=True)
        handle.write("\n")


def parse_managers(text):
    return [entry.strip() for entry in text.split(",") if entry.strip()]


def warn_unknown_managers(managers):
    unknown = sorted({m for m in managers if m not in KNOWN_MANAGERS})
    if unknown:
        print(f"warning: unknown managers found: {', '.join(unknown)}", file=sys.stderr)


def build_entry(entry):
    if entry.get("value") in MISSING_ICON_VALUES:
        icon = FALLBACK_ICON_URL
    else:
        icon = build_icon_url(
            entry.get("icon_name"),
            entry.get("icon_set", DEFAULT_ICON_SET),
            entry.get("icon_color"),
        )
    return {
        "value": entry["value"],
        "label": entry["label"],
        "icon": icon,
        "managers": entry["managers"],
    }


def build_manual_entry(name, args):
    label = args.label or name
    value = args.value or slugify(label)
    if args.icon_url:
        icon = args.icon_url
    else:
        icon = build_icon_url(args.icon_name, args.icon_set, args.icon_color)
    managers = parse_managers(args.managers)
    if not managers:
        raise ValueError("managers are required for manual entries")
    warn_unknown_managers(managers)
    if not icon:
        raise ValueError("icon is required for manual entries")
    return {
        "value": value,
        "label": label,
        "icon": icon,
        "managers": managers,
    }


def add_entries(existing, entries, update_existing=False):
    index = {entry.get("value"): idx for idx, entry in enumerate(existing)}
    added = 0
    updated = 0
    for entry in entries:
        value = entry.get("value")
        if not value:
            continue
        if value in index:
            if update_existing:
                existing[index[value]] = entry
                updated += 1
            else:
                print(
                    f"warning: '{value}' already exists in data; skipping",
                    file=sys.stderr,
                )
            continue
        existing.append(entry)
        added += 1
    return added, updated


def main():
    base_dir = Path(__file__).resolve().parents[1]
    default_data = base_dir / "data" / "distro.json"
    parser = argparse.ArgumentParser(
        description=(
            "Lookup distro metadata and append it to data/distro.json. "
            "Icon URLs use Iconify collections (see icon-sets/collections.md)."
        )
    )
    parser.add_argument("--data", type=Path, default=default_data)
    parser.add_argument("--list", action="store_true", help="List known distros.")
    parser.add_argument(
        "--all", action="store_true", help="Add all known distros."
    )
    parser.add_argument(
        "--add",
        nargs="*",
        default=[],
        help="Add specific distros by name (e.g. --add \"Linux Mint\" Manjaro).",
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
    parser.add_argument("--label", help="Manual label override.")
    parser.add_argument("--value", help="Manual value override.")
    parser.add_argument("--managers", help="Manual managers list (comma-separated).")
    parser.add_argument("--icon-name", help="Manual icon name (Iconify slug).")
    parser.add_argument("--icon-set", default=DEFAULT_ICON_SET)
    parser.add_argument("--icon-color", help="Manual icon color hex (e.g. E95420).")
    parser.add_argument("--icon-url", help="Manual icon URL override.")
    args = parser.parse_args()

    catalog = dedupe_by_value(DISTRO_CATALOG, "catalog")
    warn_duplicate_labels(catalog, "catalog")
    lookup = build_lookup(catalog)
    if args.list:
        for entry in catalog:
            managers = ",".join(entry["managers"])
            print(f"{entry['label']} ({entry['value']}): {managers}")
        return 0

    names = []
    for raw in args.add:
        names.extend([chunk.strip() for chunk in raw.split(",") if chunk.strip()])
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

    if not args.all and not names and not args.fix_icons:
        parser.print_help()
        return 1

    data = load_json(args.data)
    if not isinstance(data, list):
        print("error: distro.json must contain a list", file=sys.stderr)
        return 1
    data = dedupe_by_value(data, "existing data")
    warn_duplicate_labels(data, "existing data")

    if args.fix_icons:
        for entry in data:
            value = entry.get("value")
            if value in MISSING_ICON_VALUES:
                entry["icon"] = FALLBACK_ICON_URL
        write_json(args.data, data)
        print("done: updated icons")
        return 0

    entries = []
    if args.all:
        entries = [build_entry(entry) for entry in catalog]
    else:
        manual_flags = any(
            [args.managers, args.icon_name, args.icon_url, args.icon_color, args.label]
        )
        if manual_flags and len(names) > 1:
            print("error: manual overrides can only be used with one distro", file=sys.stderr)
            return 1
        for name in names:
            key = normalize(name)
            if key in lookup and not manual_flags:
                entries.append(build_entry(lookup[key]))
                continue
            if manual_flags:
                try:
                    entries.append(build_manual_entry(name, args))
                except ValueError as exc:
                    print(f"error: {exc}", file=sys.stderr)
                    return 1
                continue
            print(
                f"error: '{name}' not found. Use --list or provide manual fields.",
                file=sys.stderr,
            )
            return 1

    entries = dedupe_by_value(entries, "requested entries")
    warn_duplicate_labels(entries, "requested entries")
    added, updated = add_entries(data, entries, update_existing=args.update_existing)
    write_json(args.data, data)
    print(f"done: added {added}, updated {updated}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
