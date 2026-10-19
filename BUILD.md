# Production build

The plugin ships **built assets** in git (`assets/admin/*.js`, `languages/*.mo`, `vendor/` after build). Before release or deploy, run the production builder from the plugin root.

## Requirements

- PHP 8.1–8.4 with Composer 2.x
- Node.js 18+ and npm
- `msgfmt` (gettext) for Persian `.mo` files — optional but recommended

## Build

```bash
cd wp-content/plugins/bertanex-flow
npm run build:production
```

This runs `scripts/build-production.sh`, which:

1. **`composer install --no-dev`** — `vendor/` (Action Scheduler, autoload)
2. **Vite production bundle** — `assets/admin/rules-visual-editor.bundle.js` (minified, no source maps)
3. **`msgfmt`** — `languages/bertanex-flow-fa_IR.mo` and `bertanex-flow-fa.mo`
4. **Release ZIP** — `../bertanex-flow-{version}.zip` (one level above the plugin root, e.g. `wp-content/plugins/bertanex-flow-0.1.0.zip`)

## Development-only commands

| Command | Purpose |
|---------|---------|
| `npm run dev` | Rebuild the rules UI bundle on file changes (development mode, source maps) |
| `npm run build` | Rules UI production bundle only (no Composer / translations) |
| `npm run translations` | Compile `.po` → `.mo` only |

## Release archive

The production build writes a WordPress-ready zip automatically:

```text
wp-content/plugins/bertanex-flow-{version}.zip
  └── bertanex-flow/
        ├── bertanex-flow.php
        ├── vendor/
        ├── assets/
        └── …
```

Exclusions follow `.distignore` (no `.git/`, `admin-rules-ui/node_modules/`, build docs, etc.).

## Verify

After build, confirm:

- `vendor/autoload.php` exists
- `assets/admin/rules-visual-editor.bundle.js` is non-empty
- Admin **Automation Rules** page loads the visual builder without console errors
