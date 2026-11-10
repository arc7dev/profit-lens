# Dashboard data audit (Free + Pro)

**Status:** analysis only. No code was changed for this document.
**Date:** 2026-11-09
**Code audited:** profit-lens `fix/dashboard-notices-polish` @ `716586d` (PR #50, ships in 1.3.0 — it adds
`products_meta.reconciliation` and per-line cent rounding, both referenced below), profit-lens-pro `develop` @ `6647ae6`.
**Data:** the seeded DrillBitWarehouse dataset in the local Docker environment (2,456 counted orders all-time).

Goal: every number a merchant sees has a clear origin, and the product table can be made to match the cards for the same period.

---

## 0. Summary

| # | Finding | Severity |
|---|---|---|
| F1 | **Pro: Google Ads spend never reaches "Ad Spend by Platform" or "Profit After Ad Spend".** The UI sums the chart series, and the Google Ads source has no `get_entries()`, so its series is all zeros. Confirmed: registry total $123.45, UI total $0.00. | Critical (Pro, paid feature) |
| F2 | **"Total ROAS" is advertised but doesn't exist.** `readme.txt` (Pro features list, line 45; changelog line 123 says "ROAS by campaign") and `ProUpgradeModal.jsx` line 94 promise it. Pro removed ROAS (see `class-plugin-pro.php` / `class-rest-controller-pro.php` docblocks). | High (sales claim) |
| F3 | "Revenue" means two different things: the card is **gross** (refunds are counted as a cost), the table is **net of line refunds**. | High (root cause of table ≠ card) |
| F4 | Refunds subtract `get_total_refunded()`, which **includes refunded tax**, while revenue **excludes tax**. Profit is understated by the tax portion of every refund. Not visible in this dataset (refund tax = $0.00), confirmed from code. | Medium |
| F5 | Gateway fees: one flat estimate (2.9% + $0.30 of the order total, **including tax and shipping**) for **every** payment method, including ones with no processor fee (bank transfer, cheque, cash on delivery). The only override is a PHP filter. | Medium |
| F6 | Shipping "cost" is assumed equal to shipping charged, so it never affects profit but inflates Revenue and Total Costs and lowers Net Margin (40.8% vs 43.9% all-time). | Medium (assumption the merchant can't see) |
| F7 | Ad spend currency: Google Ads `cost_micros` is in the **ad account's** currency and CSV amounts are assumed to be in the store currency. Neither is checked against the store currency. | Medium (Pro) |
| F8 | Hardcoded `$` and `.` in the loss insight (`format_insight()`, `number_format()`), regardless of store currency/locale. | Low |
| F9 | Label drift: "Net Profit" (card) vs the per-product "Net Profit" in Pro's product modal; "Gateway Fees" vs "fees" vs "payment fees"; "COGS" vs "Product Cost" vs "Cost"; Net Margin's subtitle "Revenue minus all costs" describes net profit, not margin. | Low |
| F10 | Refunds and fees are dated by the **parent order's** creation date, not the refund date: a refund issued today changes a past period's profit. | Low (by design, undisclosed) |
| F11 | Cost breakdown percentages are rounded independently and can sum to 100.1% (365-day range). | Cosmetic |

**All Free identities hold to the cent on all three ranges** (section 2), with PR #50 applied.

---

## 1. Inventory of figures

Conventions used throughout:

- **Counted orders** = `type = shop_order`, status **completed or processing**, `date_created` within the range (store timezone, whole days, `'Y-m-d...Y-m-d'`), and `get_currency()` equal to the store base currency. Source: `ProfitLens_Profit_Engine::get_counted_orders()` + the currency check in `aggregate()`. Pending, on-hold, cancelled, failed, refunded and draft orders contribute nothing. Refund records (`shop_order_refund`) are never counted as orders; their amounts reach the parent via `get_total_refunded()`.
- **Range**: `ProfitLens_REST_Controller::get_range_bounds()`. 7d = today and 6 days before, 30d = today and 29 days before, month = 1st of the current month to today, custom = after..before inclusive. Pro duplicates this function (`ProfitLensPro_REST_Controller::get_range_bounds()`).
- **Line amounts**: each line's revenue, refund and cost are rounded to cents once per line in `ProfitLens_Cost_Component_Product::resolve_line_items()` (PR #50).
- **Product cost** per line = unit cost × net quantity (quantity − refunded quantity). Unit cost = the order item's frozen snapshot (`_profitlens_snapshot_unit_cost`, written when the order first becomes completed/processing) if present, else the product's **current** WooCommerce COGS (`get_cogs_total_value()`). Unknown cost = $0 (and the line counts as "uncovered").

### 1.1 Free — KPI cards (`Dashboard.jsx` → `KpiCard.jsx`; computed in `ProfitLens_Profit_Engine::get_summary()`)

| Figure | For a merchant | Formula and WooCommerce fields | Where | Assumptions |
|---|---|---|---|---|
| **Net Profit** (hero, 0 decimals) | What you kept after product costs, shipping, refunds and payment fees. | `Revenue − Total Costs` | `get_summary()` `kpis.net_profit.amount` | Inherits every assumption below. |
| Net Profit subtitle "▲/▼ X% vs prior period" | How this period compares to the same-length period right before it. | `(current − prior) / |prior| × 100`; `null` (no arrow) when prior ≤ 0 or when current has no orders. Prior uses `get_net_profit()` (same math, lite pass). | `ProfitLens_REST_Controller::calculate_change_pct()` | For "all-time" custom ranges the prior period is empty → no comparison. |
| **Net Margin** | Share of revenue you kept. | `Net Profit / Revenue × 100`, 1 decimal. | `kpis.net_margin_pct` | Revenue includes shipping charged (F6), so margin is lower than on product sales alone. Subtitle says "Revenue minus all costs" (F9). |
| **Revenue** (0 decimals) | Money customers paid you for products and shipping, before refunds, excluding tax. | Σ over counted orders of [Σ line items `round(get_total(), 2)` + `get_shipping_total()`]. `get_total()` on a line is after coupons and before tax. | `get_order_revenue()` | Fee lines (`WC_Order_Item_Fee`, e.g. surcharges) are **not** included (0 in this dataset). Gross of refunds (F3). |
| Revenue subtitle "N orders · range" | — | Count of counted orders. | `kpis.revenue.orders_count` | — |
| **Total Costs** (0 decimals) | Everything subtracted from revenue. | Product cost + shipping + refunds + gateway fees (the four cost components). | `cost_totals` summed in `get_summary()` | Subtitle "COGS · fees · shipping · refunds". Refunds are treated as a cost (F3). |

### 1.2 Free — Cost Breakdown (`CostBreakdown.jsx`; `format_cost_breakdown()`)

| Item | Formula | Source class | Assumptions |
|---|---|---|---|
| Product Cost | Σ line cost (all lines, including lines whose product was deleted) | `ProfitLens_Cost_Component_Product` | Current cost unless a snapshot exists; unknown = $0. |
| Shipping | Σ `get_shipping_total()` | `ProfitLens_Cost_Component_Shipping` | **Charged** shipping, not real carrier cost; has a visible note in the payload. Cancels the shipping in Revenue (F6). |
| Refunds | Σ `get_total_refunded()` | `ProfitLens_Cost_Component_Refunds` | Includes refunded tax and shipping (F4). Dated by the parent order (F10). |
| Gateway Fees ("est.") | per order: `get_total() × 2.9% + $0.30` when total > 0 and a payment method is set | `ProfitLens_Cost_Component_Gateway_Fees` (`DEFAULT_RATE`, `DEFAULT_FIXED`, filter `profitlens_gateway_fee_rate`) | Same rate for every gateway (F5). Base includes tax and shipping. Not reduced by refunds. |
| "% of costs" | `amount / Σ amounts × 100`, 1 decimal, computed in JS | `CostBreakdown.jsx` | Rounded independently (F11). |
| Total | Σ items, computed in JS | `CostBreakdown.jsx` | Equals the Total Costs card (verified). |

### 1.3 Free — Profit over time (`ProfitChart.jsx`; `format_chart_series()`)

One point per calendar day in the range: Σ per-order profit (order revenue − that order's four cost components) for orders created that day (`get_date_created()->date('Y-m-d')`, store timezone). Days without orders are 0. Same assumptions as Net Profit.

### 1.4 Free — Notices and insight

| Figure | Formula | Where | Notes |
|---|---|---|---|
| Coverage "X% of this period's revenue has a known product cost" | `revenue_covered / (covered + uncovered)`, over lines with an existing product; "covered" = the line's unit cost is known (snapshot or current). | `format_cost_coverage()` → `CostCoverageNotice.jsx` | Uses net-of-refund line revenue. |
| Coverage "N products have no cost set" | **Catalog-wide** count (published simple/other products + each variation; variable parents excluded), not period-scoped. | `ProfitLens_Cost_Source_Cogs::get_catalog_coverage()` (raw SQL) | The % is about the period, the N is about the whole catalog. A reader can assume both are about the period (F9). |
| Snapshot note "X% … from orders placed before per-order cost tracking" | `100 − snapshot_covered_pct` (PR #50 fixed the inverted figure). | `CostCoverageNotice.jsx` | — |
| Currency notice "N orders in EUR (€x) aren't included" | Orders in the range whose currency ≠ store currency; their revenue in their own currency. | `format_currency_exclusions()` → `CurrencyExclusionNotice.jsx` | — |
| Insight "X lost $N this period — U units sold below cost" | The product with the most negative product profit (net line revenue − line cost). | `format_insight()` → `InsightBar.jsx` | `$` and `number_format()` hardcoded (F8). Products without a cost can never appear (their profit = revenue ≥ 0). |

### 1.5 Free — Profit by product table (`ProductTable.jsx`; `format_products()`, `sum_products()`)

One row per product **or variation** (`$item->get_product()->get_id()`), only lines whose product still exists.

| Column | Formula |
|---|---|
| Units | Σ net quantity (sold − refunded) |
| Revenue | Σ `max(0, line total − refunded for that line)` — **net of line refunds** (F3) |
| Cost | Σ line cost |
| Product profit | Revenue − Cost |
| Product margin | Product profit / Revenue |
| TOTAL | Sums of the unrounded per-product values, rounded once (PR #50) |
| Reconciliation line (PR #50) | `products_meta.reconciliation`: payment fees, refunds not tied to a product, sales of deleted products, net profit |

Sorting, search and pagination are client-side. The TOTAL row always reflects **all** products for the range, not the current page or search filter.

### 1.6 Pro (`ProfitAfterAdSpend.jsx`, mounted on Free's dashboard via `window.profitLensPro.mountProfitAfterAdSpend`)

| Figure | Formula | Where | Notes |
|---|---|---|---|
| **Ad Spend by Platform** (per platform, %, total) | Per series: Σ daily `amount` from `GET /profit-lens-pro/v1/ad-spend/chart`; % = platform / total. | `computePlatformTotals()` in `ProfitAfterAdSpend.jsx`; series from `ProfitLensPro_REST_Controller::get_ad_spend_chart()` | CSV: one series per `source` column value ("CSV · Facebook" → "Facebook"). **API sources without `get_entries()` (Google Ads) produce an all-zero series (F1).** |
| **Profit After Ad Spend** | `Net Profit (Free card) − total ad spend (sum of the series)` | `ProfitAfterAdSpend.jsx` | Net profit comes from Free's payload, unrounded. |
| "X% of net profit went to ad spend" | `ad spend / net profit × 100`; hidden when net profit ≤ 0 | same | — |
| ROAS | **Not shown anywhere** | — | Advertised (F2). |
| Product detail modal: Revenue, Units Sold, Total Cost, Margin %, "Net Profit" | `ProfitLens_Profit_Engine::calculate_product_profit()` — same per-product math as the table | `ProfitLensPro_REST_Controller::get_product_detail()` → `ProductDetailModal.jsx` | Labels "Net Profit"/"Margin %" vs the table's "Product profit"/"Product margin" (F9; rename already scheduled for a Pro PR). |

Ad spend dates: CSV rows carry their own `Y-m-d` date; Google Ads uses `segments.date BETWEEN after AND before` in the ad account's timezone, not the store's.

---

## 2. Identities — verified against the dataset

Method: `wp eval-file` calling the real REST endpoints (`rest_do_request`) for `range=custom`, plus an independent pass over the same orders with WooCommerce CRUD for terms that aren't in the payload (refunds absorbed by rows, deleted-product lines, refund tax, fee lines, payment methods). Ranges: **Aug 1 – Nov 5, 2026**, **365 days** (Nov 10, 2025 – Nov 9, 2026), **full history** (Jan 1, 2000 – Nov 9, 2026).

### 2.1 Inputs

| | Aug 1 – Nov 5 | 365 days | Full history |
|---|---|---|---|
| Counted orders | 130 | 2,146 | 2,456 |
| Revenue card | 37,654.90 | 520,754.79 | 588,020.44 |
| Total Costs card | 21,787.39 | 306,429.85 | 348,139.23 |
| Net Profit card | 15,867.51 | 214,324.94 | 239,881.21 |
| Net Margin | 42.1% | 41.2% | 40.8% |
| Product cost / Shipping / Refunds / Fees | 18,314.73 / 2,341.24 / 0.00 / 1,131.42 | 253,509.24 / 36,667.59 / 466.41 / 15,786.61 | 287,805.41 / 41,850.64 / 640.00 / 17,843.18 |
| Table TOTAL revenue / cost / profit | 35,313.66 / 18,314.73 / 16,998.93 | 483,620.79 / 253,509.24 / 230,111.55 | 545,522.11 / 287,805.41 / 257,716.70 |
| Refunds absorbed by product rows | 0.00 | 466.41 | 640.00 |
| Deleted-product lines: revenue / cost | 0.00 / 0.00 | 0.00 / 0.00 | 7.69 / 0.00 |
| Refund tax on counted orders | 0.00 | 0.00 | 0.00 |
| Orders with fee lines | 0 | 0 | 0 |
| Payment methods | square 97, paypal 32, ppcp 1 | square 1,814, paypal 298, ppcp 34 | square 2,048, paypal 302, ppcp 106 |

### 2.2 Results (difference shown; 0.00 = holds to the cent)

| Identity | Aug–Nov | 365 d | Full |
|---|---|---|---|
| Revenue card = table revenue + refunds absorbed by rows + deleted-product revenue + shipping charged | 0.00 | 0.00 | 0.00 |
| Total Costs card = table cost + deleted-product cost + shipping + refunds + fees | 0.00 | 0.00 | 0.00 |
| Net Profit = Revenue − Total Costs | 0.00 | 0.00 | 0.00 |
| Net Profit = table profit − fees − refunds not tied to a product + deleted-product profit | 0.00 | 0.00 | 0.00 |
| Net Margin = round(Net Profit / Revenue × 100, 1) | 0.0 | 0.0 | 0.0 |
| Σ Profit over time = Net Profit | 0.00 | 0.00 | 0.00 |
| Σ Cost Breakdown = Total Costs | 0.00 | 0.00 | 0.00 |
| Table TOTAL = Σ visible rows (profit, revenue) | 0.00 | 0.00 | 0.00 |
| Σ Cost Breakdown % (as displayed) | 100.0 | **100.1** | 100.0 |

Notes:

- The table TOTAL is allowed to differ by cents from the sum of visible rows (PR #50 rounds once). On this dataset it doesn't.
- The 100.1% is independent rounding of 82.7 + 12.0 + 0.2 + 5.2 (F11). The underlying amounts sum exactly.
- Before PR #50, the Net Profit identity was off by $0.01–$0.02: sub-cent line totals (e.g. 7.5342 after a percentage coupon) were rounded per order for the cards but not for the rows. Root cause and fix are in PR #50.
- `refunds not tied to a product` is $0.00 on all three ranges: the dataset's amount-only refunds sit on orders with status `refunded`, which aren't counted. PR #50's PHPUnit fixture covers the non-zero case.

### 2.3 Pro

| | Aug 1 – Nov 5 | 365 days | Full history |
|---|---|---|---|
| Ad spend as summed by the UI (chart series) | 6,316.31 | 7,705.86 | 7,705.86 |
| Ad spend per the registry (`get_total_spend()`) | 6,316.31 | 7,705.86 | 7,705.86 |
| By platform (CSV) | Facebook 4,140.07 · TikTok 1,468.74 · Instagram 707.50 | Facebook 4,801.97 · TikTok 1,771.04 · Instagram 1,132.85 | same as 365 d |
| Profit After Ad Spend = Net Profit − ad spend | 9,551.20 | 206,619.08 | 232,175.35 |
| ROAS (hypothetical; not displayed): Revenue card / ad spend | 5.96 | 67.58 | 76.31 |
| ROAS (hypothetical) with Revenue excluding shipping | 5.59 | 62.82 | 70.88 |

- **Profit After Ad Spend = Net Profit − Ad spend holds** for CSV-only data, because the UI total and the registry total agree.
- **F1, Google Ads:** a connected source shaped like the real Google Ads source (`get_spend()` only, no `get_entries()`) was injected through `profit_lens_pro_ad_sources`, with spend fixed at $123.45. Result: `get_total_spend()` = **123.45**, chart series total (what the UI sums) = **0.00**. `build_source_series_list()` falls back to `build_single_source_series( $source, array(), $range )` (an empty date map) for any source without `get_entries()`. Every real Google Ads dollar is therefore missing from "Ad Spend by Platform", "Total Ad Spend", "Profit After Ad Spend" and the "% of net profit" caption, and the same applies to the chart on Pro's settings page.
- **ROAS:** no ROAS is computed or shown. If it's reinstated, the Revenue base has to be decided explicitly: with shipping, the 365-day ROAS is 67.58; without it, 62.82 (−7%).

---

## 3. Inconsistencies and risks

1. **F1 — Google Ads spend missing from every Pro total.** Code path above. A merchant connecting Google Ads sees $0 spend and an inflated Profit After Ad Spend. This is the most important finding and is independent of everything else here.
2. **F2 — ROAS advertised, not implemented.** `readme.txt` lines 45 and 123, `ProUpgradeModal.jsx` line 94. Either reinstate ROAS or remove the claims before the 1.3.0 release (this is in WP.org-visible copy).
3. **F3 — Two meanings of "Revenue".** Card: gross, refunds as a cost. Table: net of line refunds, no refunds anywhere. This, plus shipping and fees, is why the table can't add up to the cards today.
4. **F4 — Refunded tax subtracted as a cost.** `get_total_refunded()` is the gross refund amount, including tax (WooCommerce stores refunds as negative-total orders). Revenue excludes tax, so refunding a taxed item lowers profit by the tax too. Not observable here (no taxed refunds); real stores collecting sales tax will be affected.
5. **F5 — One fee estimate for every gateway.** 2.9% + $0.30 on the full order total (tax and shipping included) for any non-empty payment method. Over-estimates for bacs/cheque/cod (true fee $0) and for gateways with lower rates; under-estimates for others. Configurable only through the `profitlens_gateway_fee_rate` PHP filter; nothing in the UI says where "est." comes from.
6. **F6 — Shipping assumption is invisible on the cards.** It's explained only in the cost breakdown item's `note`. Revenue and Total Costs include the same shipping amount, so profit is unaffected, but Net Margin is understated: 40.8% vs 43.9% on product sales (full history).
7. **F7 — Ad spend currency is assumed.** Google Ads returns `cost_micros` in the ad account currency (no `customer.currency_code` read in `ProfitLensPro_Google_Ads_API::get_campaign_spend()`); CSV values are taken as store currency. A EUR ad account on a USD store would be subtracted 1:1.
8. **F8 — Hardcoded currency in the insight.** `__( '%1$s lost $%2$s …' )` and `number_format()` ignore the store's currency symbol, position and separators. Everything else in the UI goes through `formatCurrency()`.
9. **F9 — Labels.**
   - "Net Profit" is the card **and** Pro's per-product modal metric (which is product profit).
   - "Gateway Fees" (breakdown) vs "fees" (Total Costs subtitle) vs "payment fees" (PR #50 reconciliation line).
   - "COGS" (subtitle) vs "Product Cost" (breakdown) vs "Cost" (table).
   - Net Margin's subtitle "Revenue minus all costs" describes Net Profit, not margin.
   - The coverage notice mixes a period % with a catalog-wide product count.
   - "Revenue" (F3).
10. **F10 — Refund timing.** Refunds and fees follow the parent order's date. A refund issued in November for an August order lowers August, and a previously reported period changes after the fact. Defensible (it matches "profit of the orders placed in this period") but not disclosed.
11. **Current-cost fallback.** Orders without a snapshot use today's COGS. Already disclosed by the snapshot note; listed for completeness.
12. **Unknown cost = $0.** Inflates profit by the uncovered revenue; disclosed by the coverage notice (85.4% covered in Aug–Nov).
13. **Fee lines ignored.** `WC_Order_Item_Fee` totals (surcharges, or negative fees used as discounts by some plugins) are neither revenue nor cost. `wp profitlens verify` would flag such orders (it compares against total − tax).
14. **F11 — Breakdown % rounding.** Can display 100.1%.
15. **Rounding on cards.** Cards show 0 decimals ($15,868), while the table and the reconciliation line show cents ($15,867.51). Both are correct, but they read as different numbers side by side.
16. **Duplicated range logic.** Free and Pro each have their own `get_range_bounds()`. They're identical today; nothing enforces it.

---

## 4. Proposal

### 4.1 Make the table TOTAL identical to the cards

Viable. All the terms already exist (the cards' cost components, `products_meta.reconciliation`) or are one accumulator away. The blocker is F3: the table's Revenue is net of refunds and the card's isn't. Two ways to close it:

**Option A — keep the card definitions, add order-level rows to the table.** The rows need a "Refunds absorbed by products" gross-up (+revenue, +cost) so that table Revenue becomes gross like the card:

| Row | Revenue | Cost | Profit |
|---|---|---|---|
| Shipping charged | +S | +S | 0 |
| Payment fees | — | +F | −F |
| Refunds | +refunds absorbed by rows | +all refunds | −refunds not tied to a product |
| Sales of deleted products (if ≠ 0) | +deleted revenue | +deleted cost | ± |

It works, but the "Refunds" row adds back money that the product rows already took off. That's hard to explain.

**Option B (recommended) — define Revenue as net of refunds everywhere.** Revenue card = gross − refunds, and refunds stop being a cost. **Net Profit doesn't change**; Revenue and Total Costs drop by the refunds; Net Margin barely moves. The order-level rows then read naturally:

| Row | Revenue | Cost | Profit |
|---|---|---|---|
| Shipping charged | +S | +S | 0 |
| Payment fees | — | +F | −F |
| Refunds not tied to a product (if ≠ 0) | −U | — | −U |
| Sales of deleted products (if ≠ 0) | +deleted revenue | +deleted cost | ± |
| **TOTAL** | **= Revenue card** | **= Total Costs card** | **= Net Profit card** |

Impact of B on the cards:

| | Revenue now → B | Total Costs now → B | Net Margin now → B |
|---|---|---|---|
| Aug 1 – Nov 5 | 37,654.90 → 37,654.90 | 21,787.39 → 21,787.39 | 42.1% → 42.1% |
| 365 days | 520,754.79 → 520,288.38 | 306,429.85 → 305,963.44 | 41.2% → 41.2% |
| Full history | 588,020.44 → 587,380.44 | 348,139.23 → 347,499.23 | 40.8% → 40.8% |

B also makes F4 visible: refunded tax lands in "Refunds not tied to a product" instead of hiding inside a cost line.

Placement and behavior:

- **Separate `<tbody>` between the product rows and TOTAL, on every page.** Not on the last page only: TOTAL is already shown on every page and always covers all products, so the order-level rows have to sit right above it to explain it. Otherwise page 1 shows a TOTAL that the visible rows can't add up to.
- **Excluded from sort and search.** They aren't products. Search keeps filtering product rows only, and the TOTAL label stays "Total" (all products + order-level). Consider "Total (all products)" when a search filter is active, since TOTAL doesn't follow the filter today either.
- **Styling:** muted label, a short "Order-level" caption on the first row, no profit color on the Shipping row (always 0).
- **This replaces the PR #50 reconciliation line.** Once the rows exist, the sentence is redundant. Keep `products_meta.reconciliation` as the data source and add `shipping_charged` and `refunds_absorbed` (A) or keep it as is (B).
- **Pro CSV export** (`assets/js/export.js`, `exportTable(rows, columns, rangeLabel)`): today it exports product rows only, with no TOTAL. Add an optional 4th parameter `footerRows` (order-level rows + TOTAL), appended after a blank line. This is backward compatible: an older Free calling with 3 arguments gets today's output. Needs a Pro PR and a Free PR, released together.
- **Cost:** no new queries. Shipping, fees and refunds are already in `cost_totals`; "absorbed refunds" (A) and "deleted cost" are one accumulator each in the existing pass.

### 4.2 "How is this calculated?" (one per card)

- **Net Profit:** "Revenue minus product costs, shipping, refunds and estimated payment fees, for completed and processing orders placed in this period."
- **Net Margin:** "Net profit as a percentage of revenue."
- **Revenue:** "What customers paid for products and shipping on completed and processing orders placed in this period, after discounts and before tax." (Option B: "…minus refunds.")
- **Total Costs:** "Product costs from WooCommerce's Cost of Goods field, the shipping you charged, refunds, and payment fees estimated at 2.9% + $0.30 per order." (Option B: drop "refunds".)
- **Cost Breakdown, Payment fees ("est."):** "Estimated at 2.9% + $0.30 of each order's total. Your payment provider's actual fees may differ."
- **Profit over time:** "Net profit for the orders placed on each day."
- **Profit After Ad Spend (Pro):** "Net profit minus the ad spend recorded for the same dates."

The "2.9% + $0.30" text must come from the same constants or filter the engine uses, never be duplicated as a literal in JS.

### 4.3 The shipping assumption

| Option | Pros | Cons | Impact (full history) |
|---|---|---|---|
| **1. Keep and explain** (help text + "est." style note on Revenue/Total Costs) | No number changes; smallest change | Margin stays understated; "Shipping" still looks like a real cost in the breakdown | None. Margin 40.8% |
| **2. Remove from revenue and costs** | Revenue = product sales; margin reflects products; the table and the cards line up without a Shipping row; shipping stops looking like a cost | Revenue no longer matches "what customers paid" or WooCommerce's own totals; merchants who make or lose money on shipping still don't see it | Revenue 588,020.44 → 546,169.80; Total Costs 348,139.23 → 306,288.59; Net Profit unchanged; **margin 40.8% → 43.9%** (Aug–Nov 42.1% → 44.9%; 365 d 41.2% → 44.3%) |
| **3. Configurable shipping cost** (flat per order, or % of shipping charged; default = charged, i.e. today's behavior) | The only option that can show a real shipping profit or loss; still Free (internal data + a setting, no external account) | A new setting to maintain; the default still assumes; a wrong value produces a confident wrong profit | None by default; Net Profit changes once a merchant sets a cost |

**Recommendation:** 1 now, as part of the "How is this calculated?" work, because it's cheap and honest. Consider 3 later, if merchants ask for it. Option 2 alone hides shipping entirely, and combined with B the Shipping row disappears from the table too. That's a product decision, not a correctness fix.

### 4.4 Suggested order of work

1. **F1** (Pro bug): separate fix PR in Pro, before anything else.
2. **F2:** remove the ROAS claims from `readme.txt` and `ProUpgradeModal.jsx` before 1.3.0 ships, or reinstate ROAS (Revenue base to be decided, see 2.3).
3. Option B + order-level rows + Pro CSV footer (Free + Pro PRs).
4. "How is this calculated?" texts + shipping option 1.
5. F4 (refund tax), F5 (per-gateway fees), F7 (ad spend currency, already planned with the Pro label rename), F8, F9.
