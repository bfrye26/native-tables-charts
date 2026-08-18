# Native Tables & Charts: UI audit and chart-suite roadmap

## Current chart coverage

Version 2.0 already includes horizontal, vertical, grouped and stacked bars; dual-metric benchmarks; line and area charts; scatter plots; donut, radar and gauge charts; change and dumbbell charts; small multiples; and heatmaps.

## Editor improvements in 2.0.1

- Replaced the chart block's icon-heavy workflow with a named Preview, Data and Split workspace.
- Put desktop, tablet and mobile preview controls next to the preview.
- Grouped chart types by the question they answer and added plain-language descriptions.
- Consolidated data editing and column mapping into one inspector section.
- Added mapping and data validation with an editor status that explains how to recover.
- Moved colour and theme settings to the Gutenberg Styles tab instead of duplicating them in Settings.
- Replaced the unreliable icon-only data action menu with a named More menu.

## Audit findings

### Fixed now

- Remote dataset sync used the general WordPress HTTP client. It now uses `wp_safe_remote_get()` to reject unsafe destinations and limits the response while downloading.
- Editor assets now use file modification times in their versions so WordPress does not leave editors with mismatched JavaScript and CSS after an update.

### Recommended next

1. Replace the browser `window.prompt()` flows for dataset, synced-view and preset names with validated WordPress dialogs that can show errors without interrupting the editor.
2. Add Gutenberg interaction tests for chart-type selection, mapping warnings, Preview/Data/Split switching, reusable dataset loading and keyboard operation. The PHP renderer is well covered, but the editor workflow is not.
3. Add renderer-side mapping validation. The editor now catches common mistakes, but imported or older block attributes can still reach the renderer with incompatible columns and produce a degraded chart.
4. Paginate reusable data in editor state. The grid virtualizes DOM rows, but the block still loads as many as 10,000 rows into memory and disables its server-rendered preview above 500 rows.
5. Split `block-editor.js` into focused components when the next feature is added. Its current single-file structure makes chart, table, data-grid and modal changes harder to test independently.
6. Add contrast checks for custom chart palettes. Accessible data tables are present, but editors can still choose series/text/background combinations with insufficient visual contrast.

## Recommended chart roadmap

Implemented in 3.0.0: the full roadmap below is now available from the Native Data Chart chooser, with native rendering, accessible data output, responsive styling and export support.

### Highest value

- **Histogram:** distributions and performance ranges; requires bin controls and sensible automatic bins.
- **Box plot:** median, quartiles and outliers for benchmark/test data.
- **Bubble chart:** extends scatter with a third metric encoded as size.
- **Waterfall:** explains how gains and losses produce a final total.
- **Bullet chart:** a compact, more information-dense alternative to gauges for targets and thresholds.
- **100% stacked bar:** compares composition when totals differ.
- **Diverging bar:** positive/negative comparisons, survey responses and sentiment.
- **Slope chart:** emphasizes movement between two periods with less noise than grouped bars.

### Broader suite

- **Combo bar and line:** compare volume with a rate or target on a second scale.
- **Range column / range area:** minimum-to-maximum bands, confidence intervals and forecasts.
- **Treemap:** hierarchical part-to-whole data.
- **Funnel:** ordered stage conversion, with clear warnings against misleading area encoding.
- **Candlestick / OHLC:** financial or price-range data.
- **Calendar heatmap:** activity and publishing patterns across dates.

### Advanced, dependency-sensitive

- **Sankey / alluvial:** flows between stages or categories.
- **Choropleth and symbol maps:** geographic data with a bundled, versioned boundary-data strategy.
- **Network graph:** relationships between entities; should be added only with strong layout and accessibility constraints.

The next implementation phase should prioritize histogram, box plot, bubble, waterfall, bullet, diverging bar and 100% stacked bar. Together they expand statistical, editorial and benchmark use cases without requiring a mapping or graph-layout dependency.
