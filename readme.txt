=== Profit Lens — Profit Analytics for WooCommerce ===
Contributors: arc7dev
Tags: woocommerce, profit, analytics, cogs, dashboard
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 7.4
WC requires at least: 8.0
WC tested up to: 10.3
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

See your store's real profit, not just sales — right inside WooCommerce.

== Description ==

WooCommerce shows you revenue. Profit Lens shows you what's left after the
real costs: product cost, payment gateway fees, shipping, and refunds.

= What Profit Lens does (free, self-hosted, no external account) =

* Net profit and net margin for any date range, calculated from the Cost of
  Goods field already built into WooCommerce (10.3+).
* Per-order and per-product profit breakdown.
* Cost breakdown: product cost, gateway fees, shipping, refunds.
* Cost coverage: how much of your revenue is backed by a known product
  cost, so you know how much to trust the profit number.
* Everything is calculated on your server. Nothing about your store or
  your orders is sent anywhere.

= What's in Profit Lens Pro =

Profit after ad spend and ROAS by campaign, once you connect a Meta Ads or
Google Ads account — available at [arc7.dev](https://arc7.dev). The free
plugin in this repository is fully functional on its own; Pro adds
analytics that depend on an external ad account, not features held back
from the free version.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/profit-lens`, or install
   through the WordPress plugins screen directly.
2. Activate the plugin.
3. Make sure WooCommerce is active and your products have a cost set
   (Product data → General → Cost of goods, WooCommerce 10.3+).
4. Go to WooCommerce → Profit Lens.

== Frequently Asked Questions ==

= Does this send my store data anywhere? =

No. All calculation happens on your own server, using WooCommerce's REST
API and database. Profit Lens Free makes no external requests — including
for fonts, which are bundled with the plugin instead of loaded from Google
Fonts.

= Do I need Meta Ads or Google Ads to use this? =

No. Everything in the free plugin works from data WooCommerce already has.
Connecting an ad account is only needed for the Pro features (profit after
ad spend, ROAS by campaign).

= What if some of my products don't have a cost set? =

Profit Lens still shows you profit for the products that do, and tells you
what share of your revenue is covered by a known cost — so you can see at
a glance how much to trust the number instead of assuming it's complete.

== Screenshots ==

1. Dashboard — net profit, cost breakdown, and profit by product.

== Changelog ==

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
