# Phase 3A: Form Builder — Proof of Concept

## Goal

Build a visual drag-and-drop form builder with a Metabase-style grid canvas.
Users place form elements on a grid, move/resize them, and bind them to
DocType fields via PathFinder (right-click).

## Architecture

```
FormBuilderPage.vue
├── ElementPalette.vue        (left sidebar — drag source)
├── BuilderCanvas.vue          (centre — the grid)
│   └── BuilderElement.vue     (each placed element — draggable/resizable)
├── PropertyPanel.vue          (right sidebar — config for selected element)
└── useBuilderState.ts         (reactive state, serialisation, load/save)
```

Route: `/nce/builder/:formName`

---

## Task 1 — Add `grid_layout` and `grid_config` fields to NCE Form Definition DocType

**File:** `nce_builder/nce_builder/doctype/nce_form_definition/nce_form_definition.json`

Add two new Code (JSON) fields after `tab_layout` in the Layout section:

```json
{
  "fieldname": "grid_layout",
  "fieldtype": "Code",
  "label": "Grid Layout",
  "options": "JSON",
  "description": "JSON array of element positions [{id,type,x,y,w,h,config}]"
},
{
  "fieldname": "grid_config",
  "fieldtype": "Code",
  "label": "Grid Config",
  "options": "JSON",
  "description": "Grid settings {columns,rowHeight,gap}"
}
```

Also add them to `field_order` after `"tab_layout"`.

---

## Task 2 — `useBuilderState.ts` composable

**File:** `frontend/src/composables/useBuilderState.ts`

### TypeScript interfaces

```ts
export interface ElementConfig {
  label: string
  placeholder: string
  fieldPath: string        // PathFinder output, e.g. "doc.customer_name"
  fieldType: string        // Frappe fieldtype, e.g. "Data", "Select"
  terminalDoctype: string  // DocType the field belongs to
  editable: boolean        // true = input, false = read-only display
  frameColor: string       // hex colour for element border/frame
}

export interface BuilderElement {
  id: string               // unique, e.g. "el_" + timestamp
  type: "field" | "caption"
  x: number                // grid column (0-based)
  y: number                // grid row (0-based)
  w: number                // width in grid cells
  h: number                // height in grid cells
  config: ElementConfig
}

export interface GridConfig {
  columns: number          // 12, 16, 24
  rowHeight: number        // pixels per row, default 48
  gap: number              // gap between cells in px, default 4
}

export interface BuilderState {
  formName: string
  title: string
  targetDoctype: string
  gridConfig: GridConfig
  elements: BuilderElement[]
  selectedId: string | null
}
```

### Composable: `useBuilderState(formName: string)`

Returns:
- `state: Reactive<BuilderState>` — the full reactive state
- `selectedElement: ComputedRef<BuilderElement | null>` — currently selected
- `addElement(type, x, y): void` — creates new element at grid position with defaults
- `removeElement(id): void` — deletes element
- `updateElement(id, partial): void` — merges partial config into element
- `moveElement(id, x, y): void` — updates position
- `resizeElement(id, w, h): void` — updates size
- `selectElement(id | null): void` — sets selection
- `serialise(): { grid_layout: string, grid_config: string }` — JSON strings for saving
- `loadFromDefinition(gridLayout: string, gridConfig: string, elements: string): void` — parse JSON and hydrate state
- `save(): Promise<void>` — call frappe.call to save to NCE Form Definition
- `load(): Promise<void>` — call frappe.call to load from NCE Form Definition

Default `ElementConfig`:
```ts
{
  label: "New Field",
  placeholder: "",
  fieldPath: "",
  fieldType: "",
  terminalDoctype: "",
  editable: true,
  frameColor: ""   // empty = no custom frame
}
```

Default `GridConfig`:
```ts
{ columns: 12, rowHeight: 48, gap: 4 }
```

Use `frappe.call` pattern:
```ts
// Save
await fetch("/api/method/frappe.client.set_value", {
  method: "POST",
  headers: {
    "Content-Type": "application/json",
    "X-Frappe-CSRF-Token": window.csrf_token,
  },
  body: JSON.stringify({
    doctype: "NCE Form Definition",
    name: state.formName,
    fieldname: "grid_layout",
    value: JSON.stringify(state.elements),
  }),
})

// Load
const res = await fetch(`/api/resource/NCE Form Definition/${state.formName}`)
const data = await res.json()
```

---

## Task 3 — `BuilderCanvas.vue`

**File:** `frontend/src/components/builder/BuilderCanvas.vue`

### Props
```ts
defineProps<{
  state: BuilderState
}>()
```

### Emits
```ts
defineEmits<{
  "select": [id: string | null]
  "move": [id: string, x: number, y: number]
  "resize": [id: string, w: number, h: number]
  "drop-new": [type: string, x: number, y: number]
}>()
```

### Template structure
```html
<div class="builder-canvas-wrapper" style="overflow-y: auto; flex: 1;">
  <div
    class="builder-canvas"
    ref="canvasRef"
    :style="{
      display: 'grid',
      gridTemplateColumns: `repeat(${state.gridConfig.columns}, 1fr)`,
      gridAutoRows: `${state.gridConfig.rowHeight}px`,
      gap: `${state.gridConfig.gap}px`,
      position: 'relative',
      minHeight: '100%',
      padding: '16px',
    }"
    @click.self="$emit('select', null)"
    @dragover.prevent
    @drop="onDrop"
  >
    <!-- Grid lines overlay (visual only) -->
    <div class="grid-lines-overlay" :style="gridLinesStyle" />

    <!-- Placed elements -->
    <BuilderElement
      v-for="el in state.elements"
      :key="el.id"
      :element="el"
      :selected="el.id === state.selectedId"
      :grid-config="state.gridConfig"
      @select="$emit('select', el.id)"
      @move="(x, y) => $emit('move', el.id, x, y)"
      @resize="(w, h) => $emit('resize', el.id, w, h)"
    />
  </div>
</div>
```

### Grid lines overlay
Render a visual grid of faint lines/cells matching the grid dimensions.
Use CSS: a repeating pattern of borders or background-image with linear-gradient.
Style should match Metabase — very subtle #e8e8e8 dashed or solid 1px lines
forming squares. Use `pointer-events: none` so it doesn't interfere.

### Drop handler
When a new element is dragged from the palette and dropped:
1. Get drop coordinates relative to canvas
2. Convert pixel position to grid cell (x, y) using column width and row height
3. Emit `drop-new` with element type and grid position

---

## Task 4 — `BuilderElement.vue`

**File:** `frontend/src/components/builder/BuilderElement.vue`

### Props
```ts
defineProps<{
  element: BuilderElement
  selected: boolean
  gridConfig: GridConfig
}>()
```

### Emits
```ts
defineEmits<{
  "select": []
  "move": [x: number, y: number]
  "resize": [w: number, h: number]
}>()
```

### Positioning
Use CSS grid placement on the element's root div:
```css
grid-column: calc(x + 1) / span w;
grid-row: calc(y + 1) / span h;
```
(CSS grid is 1-based, our state is 0-based, so add 1)

### Visual appearance
- Shows the element label (caption or field label)
- If type is "field" and editable: show a mock input box (grey background, placeholder text)
- If type is "field" and read-only: show a text display style
- If type is "caption": show text in bold/larger font
- If `frameColor` is set: apply as border-color (2px solid)
- If selected: show a blue highlight border and resize handles

### Interaction — Move (drag from edges)
- When mouse is near any edge (within 8px of top/bottom/left/right but NOT corner):
  - Cursor: `grab` / `grabbing`
  - On mousedown: start tracking mouse movement
  - On mousemove: calculate which grid cell the mouse is over, compute new x,y
  - On mouseup: emit `move(newX, newY)` snapped to grid

### Interaction — Resize (drag from corners)
- When mouse is near any corner (within 12px):
  - Cursor: `nwse-resize` / `nesw-resize` etc.
  - On mousedown: start tracking
  - On mousemove: calculate new w,h based on which corner is being dragged
  - On mouseup: emit `resize(newW, newH)` snapped to grid, minimum 1x1

### Interaction — Select
- Click anywhere on element (that isn't an edge/corner drag): emit `select`

### Context menu (right-click)
- Prevent default browser context menu
- Emit a `contextmenu` event that the parent page will handle
  (to open PathFinder — implemented later, for now just emit)

---

## Task 5 — `ElementPalette.vue`

**File:** `frontend/src/components/builder/ElementPalette.vue`

### Template
A sidebar panel with:
- A heading "Elements"
- Two draggable items:
  1. **Field** — icon + label "Editable Field"
  2. **Caption** — icon + label "Caption / Label"

### Drag behaviour
Each item uses native HTML5 drag:
```html
<div
  draggable="true"
  @dragstart="e => e.dataTransfer.setData('element-type', 'field')"
  class="palette-item"
>
  <span class="icon">▢</span>
  <span>Editable Field</span>
</div>
```

### Styling
- Items have a light background, rounded, with hover effect
- Cursor: `grab`
- Small icon on the left, label text on the right
- Width: full sidebar width minus padding

---

## Task 6 — `PropertyPanel.vue`

**File:** `frontend/src/components/builder/PropertyPanel.vue`

### Props
```ts
defineProps<{
  element: BuilderElement | null
  primaryColor: string
  secondaryColor: string
}>()
```

### Emits
```ts
defineEmits<{
  "update": [id: string, changes: Partial<ElementConfig>]
  "delete": [id: string]
}>()
```

### Template
When no element is selected: show "Select an element to edit its properties"

When element is selected, show a form with:
1. **Label** — text input, bound to `config.label`
2. **Placeholder** — text input (only for type "field")
3. **Editable** — toggle/checkbox (only for type "field")
4. **Frame Color** — use our `SwatchPicker` component
   (import from `@/components/SwatchPicker.vue`)
   Pass `primaryColor` and `secondaryColor` props
5. **Data Binding** section:
   - Display current `fieldPath` if set (read-only text)
   - "Right-click element to bind data" help text
6. **Delete** button at the bottom (red, with confirm)

Each input change emits `update` with the element id and changed config fields.

---

## Task 7 — `FormBuilderPage.vue`

**File:** `frontend/src/pages/FormBuilderPage.vue`

### Route params
- `formName` from route params (create new or load existing)

### Layout — three panels
```html
<div class="h-screen flex flex-col">
  <!-- Top toolbar -->
  <header class="h-14 border-b flex items-center px-4 gap-4 shrink-0 bg-white">
    <h1 class="font-semibold text-lg">{{ state.title || 'New Form' }}</h1>
    <div class="flex-1" />

    <!-- Grid density selector -->
    <label class="text-sm text-gray-500">Grid:</label>
    <select v-model="state.gridConfig.columns" class="text-sm border rounded px-2 py-1">
      <option :value="12">12 col</option>
      <option :value="16">16 col</option>
      <option :value="24">24 col</option>
    </select>

    <!-- Target DocType -->
    <label class="text-sm text-gray-500">DocType:</label>
    <input
      v-model="state.targetDoctype"
      class="text-sm border rounded px-2 py-1 w-48"
      placeholder="e.g. Customer"
    />

    <button @click="save" class="px-4 py-1.5 bg-black text-white text-sm rounded-md">
      Save
    </button>
  </header>

  <!-- Three-panel body -->
  <div class="flex flex-1 overflow-hidden">
    <!-- Left: Palette -->
    <aside class="w-48 border-r bg-gray-50 p-3 shrink-0">
      <ElementPalette />
    </aside>

    <!-- Centre: Canvas -->
    <BuilderCanvas
      :state="state"
      @select="selectElement"
      @move="moveElement"
      @resize="resizeElement"
      @drop-new="addElement"
    />

    <!-- Right: Properties -->
    <aside class="w-72 border-l bg-gray-50 p-3 shrink-0 overflow-y-auto">
      <PropertyPanel
        :element="selectedElement"
        :primary-color="primaryColor"
        :secondary-color="secondaryColor"
        @update="onPropertyUpdate"
        @delete="removeElement"
      />
    </aside>
  </div>
</div>
```

### Script
- Use `useBuilderState(formName)`
- Load existing form definition on mount
- Wire up all event handlers to composable methods
- Load primary/secondary colours from theme settings (or hardcode defaults for PoC)
- `save()` calls `state.save()` with a success toast

### Meta
- This page should use `meta: { standalone: true }` in the router
  so it renders WITHOUT the App Shell sidebar (it has its own layout)

---

## Task 8 — Router and navigation updates

**File:** `frontend/src/router.ts`

Add route:
```ts
{
  path: "/nce/builder/:formName",
  name: "FormBuilder",
  component: () => import("@/pages/FormBuilderPage.vue"),
  meta: { standalone: true },
},
```

**File:** `frontend/src/App.vue`

Add nav link:
```ts
{ label: "Form Builder", to: "/nce/builder/new" },
```

---

## Implementation Order

1. Task 1 — DocType fields (simple JSON edit)
2. Task 2 — `useBuilderState.ts` (state logic, no UI)
3. Task 5 — `ElementPalette.vue` (simple, no dependencies)
4. Task 4 — `BuilderElement.vue` (drag/resize mechanics — hardest part)
5. Task 3 — `BuilderCanvas.vue` (grid + drop zone)
6. Task 6 — `PropertyPanel.vue` (config form)
7. Task 7 — `FormBuilderPage.vue` (wires everything together)
8. Task 8 — Router + nav (trivial)

---

## Technical Notes

### No external drag library
Use native HTML5 drag events for palette → canvas drops.
Use native mousedown/mousemove/mouseup for element move and resize on the canvas.
This avoids adding dependencies and gives us full control over snap-to-grid behaviour.

### Grid cell calculation
```ts
function pixelToGrid(px: number, cellSize: number, gap: number): number {
  return Math.round(px / (cellSize + gap))
}
```
Where `cellSize = canvasWidth / columns` for x, and `rowHeight` for y.

### Minimum element size
- Width: 1 grid cell
- Height: 1 grid cell

### CSS variables for theming
The builder canvas itself uses our theme variables:
- Grid lines: `var(--nce-color-border)` at 50% opacity
- Selected element highlight: `var(--nce-color-primary)`
- Canvas background: `var(--nce-color-bg)`

### PathFinder integration (NOT in this PoC)
Right-click context menu will be wired up in Phase 3B.
For now, `fieldPath` can be typed manually in the PropertyPanel
or left empty.
