# killposer

Iinteractively find and delete Composer `vendor/` directories to reclaim disk space - like [`npkill`](https://github.com/voidcosmos/npkill) but for PHP.

```
$ killposer ~/projects

  Scanning for Composer projects... ✓

┌ Select vendor directories to delete ──────────────────────────────┐
│ › ◼ my-app                     · 48.3 MB · 2025-11-02                │
│   ◻ old-api                    · 31.1 MB · 2024-06-14                │
│   ◻ legacy-plugin              · 12.7 MB · 2023-09-30                │
└───────────────────────────────────────── Space to toggle, Enter ─┘

  Delete 1 vendor directory totalling 48.3 MB? No / Yes

  Deleted /home/user/projects/my-app/vendor
```

## Installation

killposer requires PHP 8.4+. Using [Composer](https://getcomposer.org/), you can install it globally with the following command:

```bash
composer global require imliam/killposer
```

Alternatively, you can use [cpx](https://cpx.dev) to run killposer without installing it:

```bash
cpx imliam/killposer ~/projects
```

## Usage

```
killposer [options] [<path>]
```

| Argument / Option | Description                        | Default                   |
| ----------------- | ---------------------------------- | ------------------------- |
| `path`            | Directory to scan                  | Current working directory |
| `--depth=N`       | Maximum directory depth to scan    | `2`                       |
| `-f`, `--force`   | Delete without confirmation prompt | -                         |

Scan the current directory:
```bash
killposer
```

Scan a specific path:
```bash
killposer ~/projects
```

Scan deeper (e.g. a monorepo with nested packages):
```bash
killposer ~/projects --depth=4
```

Delete without a confirmation prompt (e.g. in a script):
```bash
killposer ~/projects --force
```

## How it works

killposer walks the given directory up to `--depth` levels deep, looking for folders that contain both a `composer.json` and a `vendor/` directory. Results are sorted by vendor size (largest first) so the biggest wins are at the top.

Once you confirm, it deletes only the `vendor/` directory - your source code is untouched. Run `composer install` in any project to restore it.
