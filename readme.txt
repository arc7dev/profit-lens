=== Profit Lens — Profit Analytics for WooCommerce ===
Contributors: arc7dev
Tags: woocommerce, profit, analytics, cogs, dashboard
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
WC requires at least: 10.3
WC tested up to: 10.3
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

See what your WooCommerce store really earns: net profit and margin after product costs, payment fees, shipping and refunds.

== Description ==

Most WooCommerce stores know how much they sell, but not how much they earn. Without the real margin of each product, there's no way to tell which products to push and which ones are quietly costing you money.

Profit Lens turns your WooCommerce orders into a profit dashboard. It takes your revenue and subtracts product costs, payment gateway fees, shipping and refunds, so you see your net profit and margin for any period — calculated automatically from the orders you already have.

= How it works =

Profit Lens uses the Cost of Goods Sold (COGS) feature built into WooCommerce 10.3 and later. Turn it on, add the unit cost to your products, and the dashboard does the rest. You don't need to cost every product on day one: the dashboard shows how much of your revenue has a known cost, so you always know how far to trust the numbers.

= What you get =

* **Net profit and margin** for any date range, including custom ranges.
* **Profit by order and by product**, in a table you can sort, search and page through.
* **Cost breakdown**: product costs, payment gateway fees, shipping and refunds.
* **Cost coverage**: the share of your revenue backed by a known product cost.
* **Costs locked at the time of sale**: each order keeps the product cost it had when it first reached Completed or Processing status, so editing a cost today doesn't rewrite past profit. (Applies to orders that reach that status after Profit Lens is installed.)
* **Profit over time** chart.
* **Automatic summary** that reads your numbers for you: average margin, most profitable product and more.

= Your data stays on your server =

All calculations run inside your WordPress site. Profit Lens doesn't send your store or order data anywhere, and the free plugin makes no external requests.

= Profit Lens Pro (optional) =

Profit Lens works fully on its own. Profit Lens Pro is a separate add-on for stores that advertise and want to see profit after ad spend:

* Import ad spend from a CSV file, or sync it directly from Google Ads.
* Profit and margin after ad spend.
* Total ROAS (revenue per dollar spent on ads).
* Ad spend broken down by platform.
* Meta Ads sync — coming soon.
* Export the product table to CSV.
* Detailed cost and profit view for each product.
* Control which WordPress users can see the dashboard.

Learn more at [arc7.dev/profit-lens/pro](https://arc7.dev/profit-lens/pro?utm_source=wporg&utm_medium=readme).

== Installation ==

1. Install Profit Lens from **Plugins → Add New**, or upload the plugin folder to `/wp-content/plugins/`, and activate it.
2. Make sure you're running WooCommerce 10.3 or later.
3. Turn on product costs: go to **WooCommerce → Settings → Advanced → Features**, check **Cost of Goods Sold** and save. (It's off by default.)
4. Add a cost to your products from each product's edit screen, including variations. Start with your best sellers if you have a large catalog.
5. Open **WooCommerce → Profit Lens**. If something is still missing, the dashboard will tell you which step to complete.

== Frequently Asked Questions ==

= Do I need to add a cost to every product? =

No. Products without a cost are counted at zero cost, so total profit will read higher than reality until costs are added. The product table flags them, and the dashboard shows what share of your revenue has a known cost.

= What happens to past orders if I change a product's cost? =

Orders that reached Completed or Processing status after Profit Lens was installed keep the cost they had at that time, so their profit doesn't change. Orders that already had one of those statuses before installing Profit Lens use the product's current cost.

= Which orders are counted? =

Orders with the Completed or Processing status.

= Does Profit Lens send my store data anywhere? =

No. Everything is calculated on your own server, and the free plugin makes no external requests, not even for fonts.

= Do I need a Google Ads or Meta Ads account? =

No. The free plugin doesn't use ad data at all. In Profit Lens Pro, ad spend can be imported from a CSV file or synced from Google Ads.

= Why does the dashboard ask me to enable Cost of Goods Sold? =

WooCommerce ships this feature turned off. Until it's on, WooCommerce doesn't store product costs, so there's nothing to calculate profit from. See step 3 under Installation.

= Does Profit Lens support multiple currencies? =

Profit Lens calculates profit in your store's base currency only, and doesn't convert between currencies. If an order was placed in a different currency — for example, your store changed its base currency at some point, or you use a plugin that charges orders in more than one currency — that order is left out of your totals, and the dashboard tells you how many orders and how much revenue were excluded, in their own currency.

== Screenshots ==

1. Dashboard — net profit, cost breakdown, and profit by product.

== Changelog ==

= 1.2.0 =
* Fixed: setup screen now guides users through enabling Cost of Goods Sold and adding product costs.
* Fixed: orders in a currency other than your store's base currency are now excluded from profit totals instead of being mixed in — the dashboard shows how many were excluded and their amount.
* Changed: WordPress now prevents activating Profit Lens without WooCommerce active, instead of activating it dormant with an admin notice. Also corrected the plugin's documented minimum WooCommerce version to 10.3 (the version that added the Cost of Goods Sold field this plugin depends on).

= 1.1.0 =
* Added: Product rows in the profit table now open a detailed view when Profit Lens Pro is active.
* Added: Plugin visibility can now be restricted to specific users via User Control in Profit Lens Pro settings.
* Improved: Ad Spend section updated to support platform breakdown provided by Profit Lens Pro.
* Improved: REST endpoints respect user access control when Profit Lens Pro access rules are configured.

= 1.0.0 =
* First stable release for WordPress.org.
* Fix: custom date range picker no longer bleeds WP admin's blue into the calendar and the Apply button hover state — both now use the plugin's own mint accent.
* Fix: custom date ranges over ~180 days no longer fail silently — aggregate() now processes orders in batches instead of loading the whole range in one query, which was exhausting PHP's memory limit.
* Add: Export CSV button on the profit-by-product table. (Pro)
* Add: upgrade prompt for the Pro ad-spend features (profit after ad spend, ROAS by campaign) when connecting Meta Ads or Google Ads.
* Fix: 3 CSS specificity/theme-color bugs found in a full-dashboard QA pass (profit/loss color coding not rendering, Upgrade to Pro button hover, product search focus outline).
* Cleanup: full WordPress Coding Standards (PHPCS) compliance ahead of release.

= 0.2.1 =
* Fix: rebuild compiled bundle to include snapshot cost disclosure UI (was missing from 0.2.0 build).
* Add PROFITLENS_DEV constant as second gate for dev tools — WP_DEBUG alone is not a reliable proxy for plugin development mode.
* Add .distignore to exclude dev/build files from WordPress.org package.

= 0.2.0 =
* Real profit calculation engine: net profit/margin from WooCommerce COGS,
  gateway fees, shipping, and refunds.
* REST endpoint (GET /profit-lens/v1/summary) wired to the engine.
* Dashboard: KPIs, profit chart, cost breakdown, profit-by-product table,
  custom date range.
* Per-order-line cost snapshot — editing a product's cost no longer
  rewrites past periods' profit retroactively.

= 0.1.0 =
* Initial scaffolding: admin page, REST endpoint contract, and dashboard UI
  wired to example data. No profit calculation yet.

== Source Code ==

The complete source code, including all JavaScript source files, is
publicly available on GitHub:

https://github.com/arc7dev/profit-lens

To build from source:

1. Clone the repository.
2. Run `npm install`.
3. Run `npm run build`.

The `src/` directory contains all unminified JavaScript and React source
files. The `build/` directory contains the compiled assets included in
the plugin.
