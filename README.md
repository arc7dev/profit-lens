# Profit Lens

Profit analytics plugin for WooCommerce, by [Arc7](https://arc7.dev).

Shows a store's real profit — not just revenue — by subtracting product
cost (COGS), payment gateway fees, shipping, and refunds. Everything free
runs from data already inside WooCommerce, calculated on your own server.
See `readme.txt` for the WordPress.org listing copy and the free/pro split.

This file is for people working on the plugin itself.

## Requirements

- WordPress 6.4+
- PHP 7.4+
- WooCommerce 8.0+
- Node.js 20+ (22.9 pinned via `volta`) and npm, for the dashboard build
- Composer, for dev tooling only (PHPUnit, PHPCS) — **not** required at
  runtime; the plugin ships its own autoloader (see
  `includes/class-plugin.php`) so it works without ever running
  `composer install` in production.

## Getting set up

```bash
git clone https://github.com/arc7dev/profit-lens.git
cd profit-lens
npm install
npm run fonts   # copies Inter/JetBrains Mono from @fontsource into assets/
npm run build   # compiles src/ into build/
composer install  # optional, only if you're running tests or PHPCS
```

Drop (or symlink) the resulting folder into `wp-content/plugins/`, activate
it from wp-admin, and go to **WooCommerce → Profit Lens**.

## Why `build/` and `assets/fonts/` are committed

Unlike most JS-heavy plugins, this repo versions its compiled output
(`build/`) and self-hosted font files (`assets/fonts/*.woff2`,
`assets/css/fonts.css`) instead of gitignoring them. The repo root *is*
the folder you drop into `wp-content/plugins/` — a plain `git clone`
should be installable immediately, without asking the end user to run
`npm`.

Practical consequence: **if you change anything under `src/`, run
`npm run build` and commit the result together with your source change.**
A PR that touches `src/` without a matching `build/` update is out of
sync with what ships — a GitHub Actions workflow (`build-check.yml`) now
catches this automatically, see Development below. A full hands-off
release process (a GitHub Action that rebuilds on tag and publishes to
the WordPress.org SVN repo) is still not implemented.

`node_modules/` and `vendor/` are gitignored as usual — pure tooling,
never touched by WordPress at runtime.

## Development

```bash
npm run start      # webpack in watch mode
npm run lint:js     # eslint (@wordpress/scripts config)
npm run lint:css    # stylelint
npm run format      # prettier, writes in place
composer test        # PHPUnit (needs the WP test suite, see tests/bootstrap.php)
composer lint         # PHPCS (WordPress Coding Standards ruleset not configured yet — falls back to PEAR defaults)
```

PRs are checked automatically via GitHub Actions (PHPUnit + build sync).

## Architecture

- **PHP does all the calculation**, server-side. React only renders data
  the backend already computed — see `includes/class-rest-controller.php`
  for the REST contract (`GET /wp-json/profit-lens/v1/summary`).
- **`includes/calculation/`** is the calculation engine, split into two
  interfaces: `ProfitLens_Cost_Source` (what does a PRODUCT cost —
  WooCommerce's native COGS field is the one Free implementation) and
  `ProfitLens_Cost_Component` (how much does a concept subtract from an
  ORDER's profit — four Free implementations: Product, GatewayFees,
  Shipping, Refunds). Both feed `ProfitLens_Profit_Engine`. Pro adds a
  fifth cost source/component the same way, without touching the rest of
  the engine.
- **`includes/class-cost-snapshotter.php`** (`ProfitLens_Cost_Snapshotter`)
  freezes each order line's resolved product cost as order item meta
  (`_profitlens_snapshot_unit_cost`) the first time an order reaches
  completed/processing, so editing a product's cost later doesn't
  retroactively rewrite past periods' profit. Hooked on both
  `woocommerce_new_order` and `woocommerce_order_status_changed` — an
  order created directly in a "counted" status only fires the former.
  `cost_coverage.snapshot_covered_pct` in the REST response exposes what
  fraction of a period is protected by a snapshot vs. still resolved live.
- **No PSR-4 autoload at runtime.** Class files follow WordPress-core
  naming (`class-admin.php`, not `Admin.php`), loaded by a small
  `spl_autoload_register()` map in `class-plugin.php`. Composer's
  `classmap` autoload exists only for PHPUnit.
- **`src/hooks/useSummary.js`** fetches the real REST endpoint via
  `@wordpress/api-fetch`. **`src/data/mock.js`** still mirrors that
  response's shape, but only for `Dashboard.jsx`'s dev-only mock switcher
  (gated by `WP_DEBUG` + `PROFITLENS_DEV`, see `class-assets.php`) — it's
  not on the real data path.

## Free vs. Pro

Everything in this repository is fully functional on its own — no
trialware, no feature gating (required for WordPress.org compliance,
Guideline 5). Pro (ad spend profit, ROAS by campaign) requires connecting
a Meta Ads or Google Ads account and is sold and served from
[arc7.dev](https://arc7.dev), not from this repo.

## Contributing

Issues and PRs welcome. Keep the `profitlens_` / `ProfitLens_` prefix on
any new function or class, and make sure `npm run lint:js`,
`npm run lint:css`, `php -l`, and `composer lint` (PHPCS against the
WordPress Coding Standards ruleset in `phpcs.xml`) pass before opening a
PR. If your change touches `src/`, include the rebuilt `build/` in the
same commit (see above).

PRs are also checked automatically via GitHub Actions — PHPUnit and the
build sync check.

## License

GPL v2 or later — see [`LICENSE`](./LICENSE).
