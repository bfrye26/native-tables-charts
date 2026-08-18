# Native Tables & Charts 1.0

## Release goal

Version 1.0 is a Gutenberg-native replacement for the League Table workflow plus a native charting system. The central design is a shared dataset layer: the same data can power a semantic table, a responsive chart, or several synced visualizations without exporting screenshots or relying on an external chart service.

## 1.0.1 editor UX revision

The Gutenberg authoring workflow was revised after real in-editor testing to keep long articles readable while editing. The underlying data model and frontend output are unchanged.

### Chart authoring modes

- **Preview** is the default whenever a chart block is opened. Only the rendered visualization and a compact data summary are shown in the article canvas.
- **Data** temporarily replaces the chart with the spreadsheet editor. Choosing **Done editing data** returns to Preview.
- **Split** is an optional power-user view that places data and the live chart beside one another on wide editor canvases and stacks them on narrower screens.
- **Focus Mode** opens the spreadsheet in a large WordPress modal so wide or long datasets are not constrained by the post content column.

Desktop, tablet and mobile controls now resize the active chart preview itself. The old permanently stacked responsive-preview section has been removed.

### Data editor controls

The permanent two-row command strip has been replaced by a compact toolbar containing Add Row, Add Column, Paste/Import, More and Focus Mode. Selection-specific row/column/cell commands appear contextually, while less common dataset, export, movement, merge and destructive commands live under **More**.

Reusable datasets and synced views display a warning before data editing because changes can affect other visualizations. A compact summary such as `14 rows • 2 metrics • Synced view` remains visible under the normal preview and acts as a shortcut back into Data mode.

### Tables

Table blocks still open directly into Data mode because the editable grid is their primary authoring surface. Authors can switch to Preview from the block toolbar when they want to inspect final responsive styling, and Focus Mode is available for large tables.

## Requirements

- WordPress 6.6 or newer
- PHP 8.1 or newer
- Gutenberg/block editor for authoring
- A modern browser supported by the installed WordPress version

## Blocks

### `ntc/table`

Dynamic table block rendered by PHP. The editor uses an interactive spreadsheet-style interface, while the public page receives semantic table markup.

### `ntc/chart`

Dynamic chart block rendered by PHP. Horizontal/vertical/grouped/stacked benchmark styles primarily use semantic HTML/CSS; line/scatter/donut visualizations use responsive SVG. Every chart exposes the underlying values in an HTML data table.

## Data model

Version 1.0 creates five site-prefixed database tables:

- `ntc_datasets`: dataset identity, columns, description and ownership
- `ntc_rows`: row-oriented JSON storage indexed by dataset and row number
- `ntc_views`: reusable table/chart presentation configurations
- `ntc_presets`: custom table/chart style presets
- `ntc_backups`: original post content for League Table migration rollback

A dataset supports up to 10,000 rows and 40 columns. Storing rows individually allows reusable datasets to receive row-level patches rather than replacing one very large serialized value after every edit.

## Inline, reusable and synced content

### Inline

Data remains in the Gutenberg block. Best for one-off article tables and charts.

### Reusable dataset

The block references a Data Library dataset. Any visualization using that dataset reads the same row/column data.

### Synced view

A view stores the visual configuration for a table or chart in addition to referencing the shared dataset. This is appropriate when both data and presentation should be reused.

### Snapshot / detach

A reusable block can be detached back into an inline copy. This is useful for preserving historical benchmark results before a shared dataset is changed later.

## Gutenberg data editor

The shared data editor includes:

- virtualized rows for large datasets
- keyboard navigation
- rectangular selection
- copy/cut/clear
- multi-cell TSV paste
- Excel/Google Sheets paste
- insert, duplicate, move and delete row operations
- insert, duplicate, move and delete column operations
- merge/unmerge cell operations for tables
- file/paste import for CSV, TSV and JSON
- browser-side CSV/TSV parsing including quoted multiline fields
- downloadable CSV/TSV/JSON export
- reusable dataset creation
- synced view creation/update
- custom style preset saving
- table/chart transformation
- server-rendered desktop/tablet/mobile previews for normal-sized datasets

For editor responsiveness, the in-editor server preview is intentionally disabled for datasets over 500 rows. The data remains editable and the normal WordPress Preview is available.

## Table feature set

### Structure

- up to 40 columns
- up to 10,000 rows
- header row
- caption top/bottom
- automatic ranking/position column on either side
- automatic/fixed table layout
- widths/minimum widths
- scroll-container height/width
- sticky table header

### Sorting

- optional default sorting
- up to five priorities
- visitor-triggered sorting
- position numbers can update after visitor sorting
- automatic, text, number, percent, currency, URL, time, ISO date, US long date and short date parsers
- short date order: DD/MM/YYYY, YYYY/MM/DD, MM/DD/YYYY
- US and EU decimal parsing

The front-end sorter is a small local vanilla-JavaScript enhancement and does not depend on jQuery.

### Responsive presentation

- horizontal scrolling
- selected-column hiding
- stacked mobile cards
- configurable phone/tablet breakpoints
- per-breakpoint header/body/caption sizes
- phone/tablet image visibility
- phone/tablet per-column visibility
- container-query rules with media-query fallback
- editor preview widths for desktop/tablet/mobile

### Global styling

- built-in style presets
- custom/imported/exported presets
- header/body/caption font family
- font weight/style/size
- header/odd/even row background/text/link colours
- caption colour and alignment
- border colour/width
- cell padding
- border radius
- top/bottom margins
- per-column widths and alignment
- automatic row/column colour rules
- automatic row/column alignment rules

### Cell styling and media

- text/background colour
- font weight/style
- horizontal/vertical alignment
- cell link and optional new-tab behaviour
- link colour
- WordPress Media Library image on the left/right
- image alt text
- optional image link/new-tab behaviour
- rowspan/colspan merging
- custom HTML
- copy/paste/reset cell properties

Custom HTML is always sanitized. By default the renderer uses WordPress's allowed post HTML and protocols. Administrators can instead enter a League Table-style restricted tag/attribute list and protocol list under `Tables & Charts > Settings`. Developers can adjust the parsed allow-lists with the `ntc_allowed_html` and `ntc_allowed_protocols` filters.

### Formulas

Version 1.0 includes League Table-compatible operations:

- sum
- subtraction
- minimum
- maximum
- average

Formula source cells can use 1-based column references or A1-style references. Average calculations support decimal precision and half-up, half-down, half-even and half-odd rounding.

## Chart feature set

### Types

- horizontal bar
- dual-metric benchmark
- vertical bar
- grouped bar
- stacked bar
- multi-series line
- scatter-style points
- donut

### Benchmark/editorial controls

- title
- subtitle
- higher/lower/neutral direction
- custom direction label
- legend label
- sort annotation
- axis label
- label column
- one or more value columns depending on chart type
- sort column/direction
- exact-label highlight list
- units
- decimal formatting
- show/hide values
- footer
- secondary footer
- source
- bar height/gap
- configurable responsive breakpoint
- automatic, 16:9, 4:3 or square chart aspect ratio
- show/hide responsive numeric axes for horizontal and dual-metric benchmark charts
- show/hide benchmark grid lines
- chart/background/text/muted/grid/primary/secondary/highlight colours

### Responsive output

Charts render into their own responsive containers. Horizontal and dual-metric benchmark charts use independently calculated rounded numeric scales, with optional axis ticks and grid lines. Dual-metric panels stack at the selected breakpoint. Horizontal benchmark labels move above bars on narrow containers instead of shrinking the entire visualization like a screenshot. Dense line/scatter SVGs can horizontally scroll on very small containers rather than reducing labels to unreadable sizes.

### Accessibility/fallback

- semantic figure markup
- chart-level accessible labels
- focusable benchmark rows with exact-value labels
- SVG titles for points/segments where applicable
- always-available "View chart data" HTML table
- server-rendered output remains available without front-end JavaScript

## Built-in presets and templates

### Table style presets

- Editorial
- Comparison
- Ranking
- Specifications
- Minimal
- Compact
- Dark

### Chart style presets

- Benchmark Dark
- Benchmark Light
- Benchmark Compact
- Editorial Light
- Editorial Dark
- Minimal
- High Contrast
- Feature
- Comparison
- Technical

### Inserter templates/variations

- Blank Data Table
- Product Comparison Table
- Specifications Table
- Ranking Table
- Benchmark Results Table
- Horizontal Benchmark Chart
- Dual-Metric Benchmark Chart
- Grouped Comparison Chart

Custom table/chart style presets can be saved in Gutenberg and imported/exported as JSON.

## Data Library

`Tables & Charts > Data Library` provides:

- reusable dataset search
- row count
- synced view count and names
- approximate post usage count
- links to the first matching posts
- updated timestamp
- raw JSON data export
- self-contained dataset + synced-view bundle export
- dataset + synced-view duplication
- deletion

The Data Library is for reusable-data management. Spreadsheet editing itself remains in Gutenberg, so authors work in the same environment where the table/chart will be used.

## Import/export

### Data

- CSV
- TSV
- JSON

Both the browser-side and REST-side CSV/TSV importers understand quoted fields, including multiline quoted values.

### Presets

Custom table/chart style presets can be exported or imported as JSON.

### Native complete backup

`Tables & Charts > Tools` can export a portable JSON backup containing every reusable dataset, its synced table/chart views, and custom presets when the current user has permission to manage them. Individual datasets can be exported as self-contained bundles from the Data Library. The Tools importer accepts complete backups, dataset bundles and raw Native Tables & Charts JSON data exports and creates new records rather than overwriting existing datasets.

### League Table XML

The migration page can import League Table XML exports and create Native Tables datasets/views even when the original League Table plugin is not active.

## League Table migration

Version 1.0 was mapped against the uploaded League Table 2.25 database/export structure.

Detected legacy database tables:

- `dalt_table`
- `dalt_data`
- `dalt_cell`

Migration includes:

- table rows and headers
- descriptions/captions
- position column
- up to five enabled sorting priorities, preserving League Table's disabled/descending/ascending rule state
- date formats
- widths/layout/container dimensions
- responsive breakpoints and hidden columns
- header/body/caption typography
- colours and borders
- odd/even row styling
- autocolours and autoalignment
- cell formatting
- links
- left/right images and links
- formulas
- merged cells
- custom HTML
- legacy cell-property visibility switches and custom-HTML tag/protocol allow-list settings when Native Tables & Charts has not already been configured

Content conversion supports:

- `[lt id="..."]`
- `dalt/table` Gutenberg blocks

Before changing a post, the original `post_content` is recorded in `ntc_backups` under a migration batch ID. The Migration screen can restore those post-content backups. Native Tables & Charts never deletes the legacy League Table tables. It intentionally does not reproduce League Table's option to bypass KSES entirely: custom HTML is always sanitized, while the legacy tag/protocol allow-lists can be preserved.


## Editor feature controls

Under `Tables & Charts > Settings`, administrators can individually enable or disable the advanced cell controls exposed to editors. Version 1.0 includes switches for text/background colours, alignment, font weight/style, text links and new-tab behaviour, left/right images and image links, formulas and formula data, custom HTML, row spans and column spans. The renderer and migrated data remain compatible even when a control is hidden from the editor UI. Administrators can also configure the custom-HTML tag/attribute and URL-protocol allow-lists; leaving those fields blank uses WordPress's normal post-content allow-lists.

## Permissions

Custom capabilities created by Version 1.0:

- `ntc_create_datasets`
- `ntc_edit_datasets`
- `ntc_delete_datasets`
- `ntc_manage_presets`
- `ntc_import`
- `ntc_export`
- `ntc_manage_settings`
- `ntc_migrate`

Administrators receive all capabilities. Editors receive editorial data/preset/import/export capabilities but not plugin settings or League Table migration capability.

## Front-end asset policy

- Table/chart presentation CSS loads with the blocks.
- No external CDN assets are used.
- No jQuery dependency is used.
- The local front-end JavaScript sorter is enqueued only when visitor-triggered manual sorting is enabled.
- Charts require no external JavaScript library or chart-rendering service.

## Uninstall behaviour

Plugin data is retained by default. Administrators can explicitly enable data deletion under `Tables & Charts > Settings` before uninstalling. Custom role capabilities are removed during uninstall either way.

## Verification performed for this build

The release package has been checked with:

- PHP syntax linting on all plugin PHP files
- JavaScript syntax checking on both plugin JavaScript files
- JSON parsing of both `block.json` files
- a server-render smoke harness for table/chart output, including rounded benchmark axes, aspect-ratio settings and grid/axis toggles
- a Gutenberg registration smoke harness confirming both block types and all eight inserter variations register
- targeted checks for League Table 2.25 XML/schema/formula/date/rounding/sort-state compatibility
- syntax/static checks covering native backup/import code and configurable custom-HTML sanitization

This build environment does not contain a complete running WordPress installation, so an actual browser-based WordPress integration/E2E run could not be performed here. Before deploying to production, install the ZIP on staging, activate it, create/edit both block types, verify front-end rendering in the site's theme, and run the League Table dry-run/migration there.

## Recommended production rollout

1. Take a database and file backup.
2. Install Version 1.0 on staging.
3. Create new inline table and chart blocks and verify theme compatibility.
4. Create a reusable dataset and use it in both a table and a chart.
5. Check mobile/tablet/desktop layouts.
6. Run League Table Migration > Dry Run.
7. Migrate with content conversion enabled.
8. Spot-check migrated tables with formulas, merged cells, images, custom HTML and responsive hiding.
9. Test old article URLs and cache/CDN behaviour.
10. Disable League Table only after verification.
11. Deploy the tested plugin and migration procedure to production.

## 1.0.2 Gutenberg integration correction

- Added `useBlockProps()` to the editor wrapper for both blocks. This restores the selected-block toolbar, Gutenberg selection behavior, standard content-width sizing, alignment support, and block-support attributes.
- The chart/table canvas now follows the post editor content width by default. Wide and full widths are opt-in through Gutenberg alignment controls.
- Added a native Styles Inspector tab with common chart/table appearance controls.
- Added a toolbar Style action that opens a compact quick-style editor without exposing the full data workspace.
- Updated dynamic frontend rendering to use `get_block_wrapper_attributes()` so WordPress alignment and spacing support is also respected on the published page.


## 1.0.3 Width model correction

- Replaced the native Gutenberg alignment dropdown for these blocks with a plugin-specific Width control labelled **Content width**, **Wide width**, and **Full width**. The old `None` label was technically Gutenberg's unaligned/content state but was confusing in a data-visualization block.
- Removed the editor-root `width:100%; max-width:100%` rule that could defeat the theme's content-size constraint and make an unaligned chart appear wider than paragraphs.
- Content width now uses `--wp--style--global--content-size` when available, with an 840px fallback for themes that do not expose a content size. Wide width similarly uses `--wp--style--global--wide-size` with a 1200px fallback.
- Wide and Full remain deliberate breakout options and emit `alignwide`/`alignfull` classes so compatible themes can apply their normal breakout layout rules.
- Existing blocks that stored Gutenberg's old `align` attribute continue to open at their prior width until the editor explicitly changes the new Width setting.


## 1.0.5 Chart-focus gutter correction

- Chart focus now has its own dedicated 34px gutter immediately to the left of the row-number gutter. The focus icon and row number no longer share the same layout box, so they cannot overlap at any zoom level or row number width.
- The focus control uses WordPress Dashicons (`star-empty` / `star-filled`) instead of font-dependent Unicode stars, improving baseline and sizing consistency across browsers.
- The focus gutter is present only for chart data editors. Table-only grids retain the compact row-number gutter.
- Removed the redundant hover delete button from the row header. Deleting rows remains available from the contextual row toolbar, keeping the row gutter dedicated to row identity and chart focus.

## 1.0.6 editor UX refinement

- Replaced the Dashicons-dependent chart-focus glyph with a self-contained star control that renders consistently in the block editor without requiring the Dashicons font.
- Expanded the chart-focus gutter and labelled the header **Focus** so the column purpose is immediately clear.
- Focused rows now get a subtle tinted row treatment plus a highlight edge, so the selected chart focus is visible even without relying on the star alone.
- The focus button now exposes `aria-pressed` for assistive technology.


## 2.0.0 advanced chart roadmap

Version 2.0.0 delivers the advanced chart expansion planned after 1.1.1 in four phases:

- **Major chart expansion** — tooltips, date/time x-axis, area, radar, gauge, change, dumbbell, small multiples and heatmap chart types.
- **Analysis features** — reference lines, top-N with "Others", conditional series colour rules, legend toggles, annotations, and a range brush for line/area charts.
- **Review workflow** — Review Card block pattern and schema.org Review/aggregateRating structured data.
- **Data depth** — post-driven datasets (live charts from post meta) with caching.

Documented rendering semantics: conditional series colour rules on multi-row line and area charts colour the whole stroke by the first point's value, and grouped and stacked bars no longer carry `ntc-series-N` classes, so custom CSS targeting those classes must be updated.

## 1.1.1 picker list and migration batching

- The dataset/view picker is now a selectable list: click to select, double-click or "Use selection" to confirm, with row counts shown beside dataset names.
- Reusable-dataset row writes are batched into 500-row INSERT statements (previously one statement per row), making CSV/TSV/XML imports and League Table migration several times faster.
- League Table migration and rollback now convert or restore up to 200 posts per request, auto-resuming in the browser with a live progress notice, so large sites no longer hit PHP execution timeouts.

## 1.0.8 hardening and cleanup

- Added a GPLv2 `license.txt` and enabled block-editor JavaScript translations via `wp_set_script_translations`.
- Added a plugin-load database check so `dbDelta` upgrades run when the plugin is updated, not only on activation.
- Reusable-dataset rows are no longer passed through the post-content sanitizer, which could corrupt literal values such as `x < y`. Output remains escaped at render time.
- The table Inspector now resolves built-in/custom style-preset values, so controls show effective colours instead of empty fields.
- Hardened the rendering layer: style variables scrub CSS-breaking characters, alignments/font weights are whitelisted, and column widths accept only safe CSS lengths.
- Removed unused configuration keys (`containerMode`, `rowStripe`, `heightMode`, `showDataTable`) and the unimplemented expression-evaluator stub.

## 1.0.7 chart presentation and theme browser

- Added explicit typography controls for chart titles, subtitles, row/category labels, values, axes, legends and footers.
- Added Compact, Comfortable and Presentation typography presets, plus Custom values.
- Added responsive mobile typography variables so charts remain readable instead of shrinking all text with the overall visualization.
- Added Auto, Spacious, Comfortable, Compact and Custom chart-density modes. Auto adapts bar height and row spacing to the current dataset size.
- Added chart-data output modes: Screen readers only (new default), Collapsible “View chart data”, Always visible table, and Disabled.
- Added a visual chart-theme browser with miniature live-style previews instead of requiring editors to interpret preset names from a text dropdown.
- Expanded built-in chart themes to ten: Benchmark Dark, Benchmark Light, Benchmark Compact, Editorial Light, Editorial Dark, Minimal, High Contrast, Feature, Comparison and Technical.
- Separated the editor language for Chart Layout from Chart Theme.
- Added visual preset browsing for table styles as well as chart themes.
- Increased the default benchmark typography and made small datasets use a more spacious automatic bar layout.

## 1.1.0 features and best practices

Version 1.1.0 adds opt-in editor and front-end features on top of the 1.0 dataset layer, plus a hardening pass. Everything new is enabled per block or per dataset from the editor; existing content keeps its current behaviour.

### New opt-in features

- Schema.org Dataset JSON-LD on dataset-backed blocks for search engines.
- Sparkline and delta-badge column types in tables.
- Front-end table search and pagination.
- Heatmap colour scales.
- Remote CSV/TSV dataset sync via WP-Cron, plus last-updated captions for dataset-backed blocks.
- Automatic dark chart theme (adapts to visitor dark-mode preference).
- Front-end CSV and PNG export buttons.
- Classic-editor `[ntc_dataset]` shortcode.

### Best-practice fixes

- REST arguments are validated before use.
- CSV exports guard against formula injection.
- Saving a custom preset with an existing slug no longer overwrites that preset.
- The presets list now requires the edit capability.
- View creation validates the referenced dataset.
- Orphaned migration options are removed on uninstall and cron events are cleared on deactivate/uninstall.
- Arrow-key navigation works in the data grid.

### Code quality

All PHP now passes the WordPress-Core coding standard.
