# Native Tables & Charts — Core/Flexible Table Block Transforms

Date: 2026-08-19
Status: Approved design (implementation pending)

## Goal

Let editors convert an existing Gutenberg `core/table` or
`flexible-table-block/table` block into an `ntc/table` block directly in the
editor (block switcher → "Transform to Native Data Table"). No bulk migration,
no server code.

## Approach

One shared, pure conversion function plus a `transforms.from` entry on the
existing `ntc/table` registration in `assets/js/block-editor.js` (handwritten
file, no build step). The transform is defined by the destination block, so
the source plugins are never touched and survive their own updates. If
Flexible Table Block is deactivated, that transform simply never appears —
core/table always does. Conversion is editor-side and undoable.

## Conversion mapping

Input: block attributes with optional `head`, `body`, `foot` section arrays of
`{ cells: [...] }` rows, plus table-level attributes (`caption`,
`captionSide`, `hasFixedLayout`, `isStackedOnMobile`, `sticky`).

Cell shape (both sources):
- `content` — HTML string (FTB: `source: html`; core: rich text)
- `tag` — `th`/`td`
- core: `align` (`data-align` attribute); FTB: `styles` (inline CSS string)
- core: `colspan`/`rowspan`; FTB: `colSpan`/`rowSpan` (strings)

Output: `ntc/table` block attributes `{ mode:'inline', columns, rows,
config, cellMeta }`.

### Header and rows
- The first `head` row becomes the column header (labels). Extra head rows
  and all `foot` rows are appended to the body rows (NTC has no footer).
- Columns: `{ id: 'c1'…, label, type, unit:'', format:'' }`.
- Type heuristic: a column whose non-empty cells all parse as numbers
  (commas and `%` stripped) gets `type:'number'`, else `'text'`. Keeps NTC
  sorting useful.

### Cell content and cellMeta
- Rows store plain text. If cell content contains HTML tags, the raw HTML is
  stored in `cellMeta['header:C'].html` / `cellMeta['R:C'].html` (NTC
  kses-sanitizes on render) and the plain text goes in the cell.
- `cellMeta` keys: `header:C` for header cells, `R:C` for data cells (R is the
  0-based row index in the output `rows` array).
- Mapped per cell: `colspan`/`rowspan` (only when > 1), `alignment` (core
  `align` / FTB `text-align` style), `textColor` (`color` style),
  `backgroundColor` (`background-color` style), `fontWeight`
  (`font-weight` style or 700/bold), `fontStyle` (`font-style` style).

### Config
- `preset:'editorial'`
- `showHeader` = a head row exists
- `showCaption` + `caption` + `captionSide` (`top`/`bottom`) when a caption
  exists
- `tableLayout:'fixed'` when `hasFixedLayout`, else `'auto'`
- `responsiveMode:'stack'` when FTB `isStackedOnMobile`, else `'scroll'`
- `stickyHeader:true` when FTB `sticky === 'header'`

### Dropped (core formatting only)
Cell padding/borders, per-cell class/id/scope/headers, table-level custom
styles, FTB first-column sticky, core "stripes" style, vertical alignment,
font sizes.

## Implementation details

- Entity decoding is a 5-entry manual replacement (`&amp; &lt; &gt; &quot;
  &#39; &nbsp;`) instead of DOM, so the converter stays testable in the
  existing node vm harness.
- Expose `window.NTC_BLOCK_EDITOR_TEST.convertTableBlock` next to the existing
  test hooks.
- `tests/block-editor-node-test.js`: add assertions for a core/table sample
  (head/body/foot, align, colspan, caption) and an FTB sample (styles →
  colors/alignment/fontWeight, rowSpan, caption side, numeric column).

## Out of scope

- Bulk/site-wide migration (admin page). Revisit only if per-post manual
  conversion proves too slow in practice.
- Reverse transforms (ntc → core / FTB).
- Chart conversion from these sources.
