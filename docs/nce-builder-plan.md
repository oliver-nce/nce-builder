# NCE Builder — Architecture Plan

> Frappe v15 custom app for site-wide theming and dynamic form rendering
> using FormKit (KickStart + Pro + Themes) with Tailwind CSS.
>
> **This document is the design spec for a Cursor coding agent.**

---

## 1. Overview

NCE Builder is a Frappe v15 custom app that provides:

1. **Site-wide theming** — A single "Theme Settings" DocType stores Tailwind CSS
   variable sets (generated via FormKit Themes editor). All Vue pages across the
   bench consume these variables for consistent styling.

2. **Dynamic form rendering** — Form definitions are stored as FormKit JSON
   schema in a custom DocType. Each definition targets a Frappe DocType and
   renders themed, validated forms that read/write data via Frappe UI's
   `createResource` system.

3. **In-app Frappe UI pages** — All pages are native Frappe UI pages using
   Frappe UI's layout components (AppShell, Sidebar, Navbar) as the visual
   shell. FormKit handles only form data entry within those pages. Built-in
   routes serve both list views and form views:
   - `/nce/list/{doctype}` → themed record list
   - `/nce/form/{form_name}` → themed FormKit form (create / edit)
   - `/nce/form/{form_name}/{doc_name}` → edit existing document

4. **Reusable foundation** — Other custom apps and ERPNext modules can import
   NCE Builder's theme config and form components.

---

## 2. Core Architecture Principle

> **FormKit is the Data Engine. Frappe UI is the Visual Shell.**

These two libraries have complementary strengths. Do NOT use FormKit for
everything. The responsibility split is:

### Frappe UI handles:
- **Navigation & layout** — `AppShell`, `Sidebar`, `Navbar`, `Breadcrumbs`
- **Feedback & dialogs** — `Toast`, `Dialog`, `Alert` for success/error messages
- **Data fetching** — `createResource` and `createListResource` for all API calls
- **Buttons & actions** — `Button` component (including inside FormKit forms)
- **List/table views** — `ListView` or similar data display components
- **Authentication** — Frappe's native session management (no custom auth layer)

### FormKit handles:
- **Form rendering** — All data-entry forms via `<FormKitSchema>`
- **Validation** — Client-side rules declared in schema, 20+ built-in rules
- **Complex inputs** — Pro inputs (Autocomplete, Datepicker, Repeater, etc.)
- **Form state** — Loading, submission, error handling within the form boundary
- **Schema storage** — JSON-serializable form definitions stored in DocTypes

### Neither library needs:
- **Custom REST API wrapper** — Frappe UI's `createResource` replaces any
  hand-rolled `frappe-api.ts`. Do NOT build a custom API utility layer.
- **Custom auth system** — Frappe handles sessions, CSRF, and permissions natively.

---

## 3. Technology Stack

### Core (all included)

| Component | Package | License | Cost |
|-----------|---------|---------|------|
| Frappe Framework v15 | `frappe` | MIT | Free |
| Frappe UI | `frappe-ui` | MIT | Free |
| Vue 3 | `vue` | MIT | Free |
| Tailwind CSS | `tailwindcss` | MIT | Free |
| Vite | `vite` | MIT | Free |
| FormKit Core SDK | `@formkit/vue` | MIT | Free |
| FormKit Themes | `@formkit/themes` | MIT | Free |
| FormKit Drag & Drop | `@formkit/drag-and-drop` | MIT | Free |
| FormKit AutoAnimate | `@formkit/auto-animate` | MIT | Free |

### Paid

| Component | Package | License | Cost |
|-----------|---------|---------|------|
| FormKit Pro inputs | `@formkit/pro` | Commercial | $149 one-time (single domain) |
| FormKit KickStart | SaaS tool | Commercial | ~$100/year ($8.33/mo annual) |

**Total: ~$249 first year, ~$100/year ongoing.**

### FormKit Key URLs

- Main site: https://formkit.com
- Theme editor: https://themes.formkit.com/editor
- KickStart: https://kickstart.formkit.com
- Schema docs: https://formkit.com/essentials/schema
- Styling docs: https://formkit.com/essentials/styling
- Pro inputs: https://formkit.com/pro
- Drag & Drop: https://drag-and-drop.formkit.com
- GitHub: https://github.com/formkit/formkit

---

## 4. Frappe App Structure

```
apps/nce_builder/
├── nce_builder/
│   ├── __init__.py
│   ├── hooks.py                    # App hooks (includes CSS + JS, routes)
│   ├── api.py                      # Whitelisted API methods
│   ├── nce_builder/
│   │   ├── doctype/
│   │   │   ├── nce_theme_settings/  # Single DocType — theme config
│   │   │   │   ├── nce_theme_settings.py
│   │   │   │   └── nce_theme_settings.json
│   │   │   ├── nce_form_definition/ # DocType — form schemas
│   │   │   │   ├── nce_form_definition.py
│   │   │   │   └── nce_form_definition.json
│   │   │   └── nce_list_config/     # DocType — list view configs
│   │   │       ├── nce_list_config.py
│   │   │       └── nce_list_config.json
│   │   └── page/
│   │       └── nce_theme_preview/   # Desk page — theme preview panel
│   │           ├── nce_theme_preview.js
│   │           └── nce_theme_preview.html
│   ├── public/
│   │   ├── css/
│   │   │   └── nce_theme.css        # Generated CSS (injected site-wide)
│   │   └── js/
│   │       └── nce_builder.js       # Built Vue app (injected for desk pages)
│   └── www/                         # Website routes (fallback)
├── frontend/                        # Vue 3 app (Vite + Frappe UI + FormKit)
│   ├── package.json
│   ├── vite.config.js
│   ├── tailwind.config.js
│   ├── formkit.config.ts            # FormKit + Pro + Frappe UI class bridge
│   ├── formkit.theme.ts             # Downloaded from themes.formkit.com
│   ├── src/
│   │   ├── main.ts                  # Vue app bootstrap
│   │   ├── App.vue                  # Frappe UI AppShell wrapper
│   │   ├── router.ts                # Vue Router (/nce/*)
│   │   ├── composables/
│   │   │   ├── useTheme.ts          # Load theme from DocType, inject CSS vars
│   │   │   └── useFormSchema.ts     # Load form definition via createResource
│   │   ├── components/
│   │   │   ├── NceForm.vue          # Generic FormKit schema renderer
│   │   │   ├── NceList.vue          # List view (Frappe UI ListView)
│   │   │   ├── NceLinkField.vue     # Link field → FormKit Autocomplete
│   │   │   ├── NceChildTable.vue    # Child table → FormKit Repeater
│   │   │   └── ThemePreview.vue     # Live theme preview panel
│   │   ├── pages/
│   │   │   ├── FormPage.vue         # /nce/form/:formName/:docName?
│   │   │   ├── ListPage.vue         # /nce/list/:doctype
│   │   │   └── ThemeSettingsPage.vue # /nce/theme-settings
│   │   └── utils/
│   │       ├── schema-helpers.ts    # Map DocType fields → FormKit schema
│   │       └── theme-injector.ts    # Apply CSS variables to :root
│   └── proxyOptions.js              # Vite dev proxy to Frappe (port 8000)
├── setup.py
└── README.md
```

**Key differences from a standalone SPA:**
- No `frappe-api.ts` — all data access uses Frappe UI's `createResource`
- `App.vue` wraps everything in Frappe UI's `AppShell`
- Pages use Frappe UI layout components (Sidebar, Navbar, Breadcrumbs)
- FormKit is used only inside the form area of each page

---

## 5. DocType Designs

### 5.1 NCE Theme Settings (Single DocType)

> One active theme per site. Stores the output from FormKit's theme editor
> as a JSON blob, plus individual overrides.

| Field | Type | Description |
|-------|------|-------------|
| `theme_name` | Data | Human-readable label (e.g. "Corporate Blue") |
| `theme_json` | Code (JSON) | Full theme config from themes.formkit.com |
| `tailwind_overrides` | Code (JSON) | Additional CSS variable overrides |
| `primary_color` | Color | Quick-access primary brand color |
| `font_family` | Data | Primary font family name |
| `border_radius` | Select | none / sm / md / lg / full |
| `spacing_scale` | Select | tight / normal / relaxed |
| `dark_mode` | Check | Enable dark mode variant |
| `custom_css` | Code (CSS) | Escape hatch for manual CSS |
| `compiled_css` | Long Text | Auto-generated CSS output (read-only) |
| `preview_html` | Long Text | Sample HTML for preview panel (read-only) |

**Server-side logic (`nce_theme_settings.py`):**

```
on_update:
  1. Parse theme_json + tailwind_overrides
  2. Generate CSS variable declarations (:root { --color-primary: ...; })
  3. Write to compiled_css field
  4. Write compiled CSS to public/css/nce_theme.css
  5. Clear Frappe cache (so site picks up new CSS)
```

### 5.2 NCE Form Definition

> Each record defines a form for a specific Frappe DocType.
> The schema is FormKit JSON — generated via KickStart or hand-written.

| Field | Type | Description |
|-------|------|-------------|
| `form_name` | Data (unique) | URL-safe identifier (e.g. "customer-form") |
| `title` | Data | Display title (e.g. "Customer Registration") |
| `target_doctype` | Link (DocType) | Which Frappe DocType this form targets |
| `form_schema` | Code (JSON) | FormKit schema array |
| `field_mapping` | Code (JSON) | Map FormKit field names → DocType field names (if different) |
| `validation_rules` | Code (JSON) | Additional server-side validation rules |
| `on_submit_action` | Select | save / submit / workflow / custom_api |
| `custom_api_method` | Data | Dotted path to whitelisted method (if action = custom_api) |
| `on_load_script` | Code (JS) | Client script run when form loads |
| `on_submit_script` | Code (JS) | Client script run before submission |
| `enabled` | Check | Whether this form is active |
| `requires_login` | Check | Whether authentication is required |
| `allowed_roles` | Table (child) | Roles that can access this form |

### 5.3 NCE List Config

> Optional: defines how records are listed for a given DocType.

| Field | Type | Description |
|-------|------|-------------|
| `list_name` | Data (unique) | URL-safe identifier |
| `target_doctype` | Link (DocType) | Which DocType to list |
| `title` | Data | Display title |
| `columns` | Code (JSON) | Array of {fieldname, label, width, sortable} |
| `default_filters` | Code (JSON) | Default filter set |
| `default_sort` | Data | e.g. "creation desc" |
| `page_size` | Int | Records per page (default 20) |
| `row_click_action` | Select | open_form / open_frappe / custom |
| `linked_form` | Link (NCE Form Definition) | Which form to open on row click |
| `searchable_fields` | Small Text | Comma-separated field names |
| `enabled` | Check | Active flag |

---

## 6. Data Access — Frappe UI Resources (NOT raw REST)

> **IMPORTANT:** Do not build a custom API wrapper. Use Frappe UI's resource
> system for ALL data operations. It handles caching, loading states, error
> handling, CSRF, and reactivity automatically.

### 6.1 Fetching a Single Document

```typescript
import { createResource } from 'frappe-ui'

// Load a Customer document for editing
const customer = createResource({
  url: 'frappe.client.get',
  params: {
    doctype: 'Customer',
    name: 'CUST-001'
  },
  auto: true  // fetch immediately
})

// Access data: customer.data
// Loading state: customer.loading
// Error: customer.error
```

### 6.2 Fetching a Document List

```typescript
import { createListResource } from 'frappe-ui'

const customers = createListResource({
  doctype: 'Customer',
  fields: ['name', 'customer_name', 'territory', 'customer_type'],
  filters: { territory: 'United States' },
  orderBy: 'creation desc',
  pageLength: 20,
  auto: true
})

// Access data: customers.data
// Pagination: customers.next(), customers.previous()
// Reload: customers.reload()
```

### 6.3 Saving a Document (from FormKit @submit)

```typescript
import { createResource } from 'frappe-ui'

const saveDoc = createResource({
  url: 'frappe.client.save',
  onSuccess(data) {
    // Use Frappe UI Toast for feedback
    toast({ title: 'Saved', variant: 'success' })
  },
  onError(error) {
    toast({ title: 'Error', message: error.message, variant: 'error' })
  }
})

// Called from FormKit's @submit handler:
function handleSubmit(formData) {
  saveDoc.submit({
    doc: {
      doctype: 'Customer',
      ...formData
    }
  })
}
```

### 6.4 Loading Form Schema (via createResource)

```typescript
const formDef = createResource({
  url: 'frappe.client.get',
  params: {
    doctype: 'NCE Form Definition',
    name: 'customer-form'
  },
  auto: true
})

// Then pass to FormKit:
// <FormKitSchema :schema="JSON.parse(formDef.data.form_schema)" />
```

### 6.5 Link Field Search (for FormKit Autocomplete)

```typescript
const searchCustomers = createResource({
  url: 'frappe.client.get_list',
  // params set dynamically on each search
})

// Wire to FormKit Pro Autocomplete's onSearch:
function onLinkSearch(query) {
  searchCustomers.submit({
    doctype: 'Customer',
    filters: { customer_name: ['like', `%${query}%`] },
    fields: ['name', 'customer_name'],
    limit_page_length: 10
  })
}
```

---

## 7. Data Flow

### 7.1 Theme Flow

```
themes.formkit.com (visual editor)
        │
        ▼
  Download formkit.theme.ts
        │
        ▼
  Paste theme JSON into NCE Theme Settings DocType
        │
        ▼
  on_update hook:
    - Parse JSON → extract CSS variables
    - Merge with tailwind_overrides
    - Generate compiled_css
    - Write to public/css/nce_theme.css
        │
        ▼
  hooks.py → app_include_css → nce_theme.css injected on every page
        │
        ▼
  Vue app reads CSS variables for Tailwind + FormKit styling
```

### 7.2 Form Rendering Flow

```
User visits /nce/form/customer-form
        │
        ▼
  Vue Router → FormPage.vue
  (wrapped in Frappe UI AppShell + Sidebar + Navbar)
        │
        ▼
  useFormSchema('customer-form')
    → createResource({ url: 'frappe.client.get',
        params: { doctype: 'NCE Form Definition', name: 'customer-form' }})
    → Returns: form_schema, target_doctype, field_mapping, etc.
        │
        ▼
  If URL has /:docName → createResource for target document
    → createResource({ url: 'frappe.client.get',
        params: { doctype, name }})
    → Returns: document data
        │
        ▼
  NceForm.vue
    → <FormKitSchema :schema="formSchema" :data="docData" />
    → FormKit renders themed form with data populated
    → Pro inputs (datepicker, dropdown, repeater) used where needed
        │
        ▼
  User fills/edits form → @submit fires
        │
        ▼
  on_submit_action routes to createResource calls:
    - "save"     → frappe.client.save
    - "submit"   → frappe.client.submit
    - "workflow"  → frappe.client.set_workflow_action
    - "custom"   → custom_api_method
        │
        ▼
  Frappe UI Toast shows success/error feedback
  Frappe handles permissions, validation, workflows server-side
```

### 7.3 List Rendering Flow

```
User visits /nce/list/Customer
        │
        ▼
  Vue Router → ListPage.vue
  (wrapped in Frappe UI AppShell + Sidebar + Navbar)
        │
        ▼
  createResource to load NCE List Config (or use defaults)
        │
        ▼
  createListResource(doctype, filters, sort, pageSize)
        │
        ▼
  NceList.vue renders themed list using Frappe UI ListView
    → Pagination, sorting, search (all from Frappe UI)
    → Row click → navigate to /nce/form/{linked_form}/{docName}
```

### 7.4 Link Field Traversal

```
Form has a Link field (e.g. "customer" → Customer DocType)
        │
        ▼
  NceLinkField.vue (wraps FormKit Pro Autocomplete)
    → User types → onSearch fires createResource for get_list
    → Results populate Autocomplete dropdown
    → User selects value
        │
        ▼
  @input event fires
    → createResource({ url: 'frappe.client.get',
        params: { doctype, name }})
    → Fetch the full related document
        │
        ▼
  Populate dependent fields reactively
    (e.g. customer_name, territory, default_currency)
        │
        ▼
  Optional: show inline related record panel (expandable)
```

---

## 8. Bridging FormKit Styling to Frappe UI

Since both libraries use Tailwind CSS, visual integration is about mapping
FormKit's class system to match Frappe UI's design tokens.

### 8.1 FormKit Config with Frappe UI Class Bridge

Use `generateClasses` from `@formkit/themes` to make FormKit inputs look native
to Frappe UI. This goes in `formkit.config.ts`:

```typescript
import { defineFormKitConfig } from '@formkit/vue'
import { generateClasses } from '@formkit/themes'
import { createProPlugin, inputs } from '@formkit/pro'
import { rootClasses } from './formkit.theme'  // from themes.formkit.com

const pro = createProPlugin('fk-YOUR-PROJECT-KEY', inputs)

export default defineFormKitConfig({
  config: {
    // Option A: Use rootClasses from themes.formkit.com (recommended)
    rootClasses,

    // Option B: Manual class bridge to match Frappe UI exactly
    // (use this if you want pixel-perfect Frappe UI matching instead of
    //  the FormKit theme editor output)
    //
    // classes: generateClasses({
    //   global: {
    //     outer: 'mb-4',
    //     label: 'block text-sm font-medium text-gray-700 mb-1',
    //     input: 'w-full px-3 py-2 border border-gray-300 rounded-md
    //             shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm',
    //     help: 'text-xs text-gray-500 mt-1',
    //     message: 'text-red-600 text-xs mt-1',
    //   }
    // })
  },
  plugins: [pro],
})
```

### 8.2 Component "Inception" — Frappe UI Components Inside FormKit

You can use Frappe UI components inside FormKit forms. This is the key to
making forms feel native while using FormKit's data engine.

**Example: Frappe UI Button as FormKit form submit:**

```vue
<FormKit type="form" :actions="false" @submit="handleSubmit">
  <FormKit type="text" name="title" label="Issue Title" />
  <FormKit type="textarea" name="description" label="Description" />

  <!-- Frappe UI Button instead of FormKit's default submit -->
  <Button
    variant="solid"
    type="submit"
    :loading="saveDoc.loading"
  >
    Save to Frappe
  </Button>
</FormKit>
```

**Example: Frappe UI Dialog for confirmation after FormKit submit:**

```vue
<script setup>
import { Dialog, toast } from 'frappe-ui'

function handleSubmit(formData) {
  showConfirmDialog({
    title: 'Confirm Submission',
    message: 'Are you sure you want to save this record?',
    onConfirm: () => {
      saveDoc.submit({ doc: { doctype: 'Customer', ...formData }})
    }
  })
}
</script>
```

**Example: Custom FormKit input wrapping a Frappe UI component:**

You can define a custom FormKit input that uses any Frappe UI component as its
base. This lets you use Frappe UI's DatePicker or FileUploader while keeping
FormKit's validation and data-binding.

```typescript
// Register as a custom FormKit input
import { createInput } from '@formkit/vue'
import { DatePicker } from 'frappe-ui'

const frappeDate = createInput(DatePicker, {
  props: ['format', 'placeholder'],
})

// Then use in schema: { "$formkit": "frappeDate", "name": "due_date" }
```

---

## 9. Link Field → FormKit Pro Autocomplete Wiring

This is a critical integration point. Frappe Link fields need to search
DocTypes server-side. FormKit Pro's Autocomplete input handles this via its
`options` prop with a loader function.

### 9.1 NceLinkField Component Pattern

```vue
<script setup>
import { createResource } from 'frappe-ui'

const props = defineProps({
  doctype: String,      // e.g. 'Customer'
  label: String,
  name: String,
  searchField: { type: String, default: 'name' },
  displayField: { type: String, default: 'name' },
})

const search = createResource({ url: 'frappe.client.get_list' })

// FormKit Autocomplete options loader
async function loadOptions({ search: query }) {
  if (!query || query.length < 2) return []

  await search.submit({
    doctype: props.doctype,
    filters: { [props.searchField]: ['like', `%${query}%`] },
    fields: ['name', props.displayField],
    limit_page_length: 10,
  })

  return (search.data || []).map(doc => ({
    label: doc[props.displayField] || doc.name,
    value: doc.name,
  }))
}
</script>

<template>
  <FormKit
    type="autocomplete"
    :name="name"
    :label="label"
    :options="loadOptions"
    selection-appearance="text"
  />
</template>
```

### 9.2 Using in FormKit Schema

In the schema JSON, reference the custom link field component:

```json
{
  "$cmp": "NceLinkField",
  "props": {
    "doctype": "Customer",
    "name": "customer",
    "label": "Customer",
    "searchField": "customer_name",
    "displayField": "customer_name"
  }
}
```

---

## 10. FormKit Schema Examples

### 10.1 Simple Customer Form

```json
[
  {
    "$formkit": "text",
    "name": "customer_name",
    "label": "Customer Name",
    "validation": "required",
    "help": "Full legal name of the customer"
  },
  {
    "$formkit": "dropdown",
    "name": "customer_type",
    "label": "Customer Type",
    "options": ["Company", "Individual"],
    "validation": "required"
  },
  {
    "$formkit": "text",
    "name": "tax_id",
    "label": "Tax ID",
    "validation": "matches:/^[0-9]{2}-[0-9]{7}$/"
  },
  {
    "$formkit": "dropdown",
    "name": "territory",
    "label": "Territory",
    "options": "$territories"
  }
]
```

The `$territories` reference is resolved at runtime by passing data:

```vue
<FormKitSchema :schema="schema" :data="{ territories: territoryList }" />
```

### 10.2 Form with Child Table (Repeater)

```json
[
  {
    "$formkit": "text",
    "name": "sales_order_name",
    "label": "Sales Order",
    "validation": "required"
  },
  {
    "$formkit": "repeater",
    "name": "items",
    "label": "Order Items",
    "addLabel": "+ Add Item",
    "children": [
      {
        "$formkit": "autocomplete",
        "name": "item_code",
        "label": "Item",
        "options": "$items"
      },
      {
        "$formkit": "number",
        "name": "qty",
        "label": "Quantity",
        "validation": "required|min:1"
      },
      {
        "$formkit": "currency",
        "name": "rate",
        "label": "Rate"
      }
    ]
  }
]
```

### 10.3 Conditional Logic

```json
[
  {
    "$formkit": "dropdown",
    "name": "customer_type",
    "label": "Type",
    "options": ["Company", "Individual"]
  },
  {
    "if": "$get(customer_type).value === 'Company'",
    "then": {
      "$formkit": "text",
      "name": "company_name",
      "label": "Company Name",
      "validation": "required"
    },
    "else": {
      "$formkit": "text",
      "name": "full_name",
      "label": "Full Name",
      "validation": "required"
    }
  }
]
```

---

## 11. Theme System Detail

### 11.1 How themes.formkit.com Output Works

The theme editor generates a `formkit.theme.ts` file containing a `rootClasses`
function. This function maps each FormKit input section (outer, label, input,
help, messages, etc.) to Tailwind CSS classes.

Example output structure:

```typescript
export const rootClasses = function (sectionKey, node) {
  return {
    'outer': 'mb-4 formkit-disabled:opacity-50',
    'label': 'block mb-1 font-bold text-sm text-gray-700',
    'input': 'w-full px-3 py-2 border border-gray-300 rounded-md ...',
    // ... etc for every section of every input type
  }[sectionKey]
}
```

### 11.2 Bridging to Frappe — CSS Custom Properties

The theme JSON stored in the DocType maps to CSS custom properties that both
FormKit and Frappe UI components consume:

```css
/* Generated by NCE Theme Settings on_update */
:root {
  --nce-color-primary: #3B82F6;
  --nce-color-secondary: #10B981;
  --nce-font-family: 'Inter', sans-serif;
  --nce-border-radius: 0.375rem;
  --nce-spacing-base: 1rem;
  /* ... */
}
```

These variables are consumed by:
1. The FormKit theme (via Tailwind's `var()` references)
2. Frappe UI components (via Tailwind config mapping)
3. The list component styling
4. Any other Vue component on the page

### 11.3 Theme Preview Panel

The Theme Settings DocType includes a Vue-powered preview panel that:
- Renders sample FormKit inputs (text, select, checkbox, datepicker)
- Shows a mini form with validation
- Updates in real-time as you change theme values
- Provides a "copy CSS" button for manual use

---

## 12. hooks.py — Full Integration

```python
app_name = "nce_builder"
app_title = "NCE Builder"

# Inject theme CSS on every page (site-wide theming)
app_include_css = ["/assets/nce_builder/css/nce_theme.css"]

# Inject built Vue/FormKit JS on desk pages
# (only needed if mounting Vue components on Frappe desk pages
#  outside the SPA — e.g. custom desk page widgets)
# app_include_js = ["/assets/nce_builder/js/nce_builder.js"]

# SPA route rules — Frappe serves the Vue app for /nce/* paths
website_route_rules = [
    {"from_route": "/nce/<path:app_path>", "to_route": "nce"},
]

# DocType events
doc_events = {
    "NCE Theme Settings": {
        "on_update": "nce_builder.api.regenerate_theme_css"
    }
}
```

**Note on `app_include_js`:** When building as a Frappe UI in-app SPA (the
recommended approach), you do NOT need `app_include_js` — the Vue app is served
as its own entry point via `website_route_rules`. Only add `app_include_js` if
you also want to inject FormKit components into standard Frappe desk pages.

---

## 13. Page Structure — Frappe UI as the Shell

Every page in NCE Builder uses Frappe UI for its chrome and FormKit only
for the form area. Here's the typical page anatomy:

```
┌─────────────────────────────────────────────────┐
│  Frappe UI Navbar                                │
├──────────┬──────────────────────────────────────┤
│          │  Frappe UI Breadcrumbs                │
│  Frappe  │──────────────────────────────────────│
│  UI      │                                       │
│  Sidebar │  Page Content Area                    │
│          │  ┌───────────────────────────────┐    │
│  - Lists │  │  FormKit Form                  │    │
│  - Nav   │  │  (schema-rendered inputs)      │    │
│  - Links │  │  ...                           │    │
│          │  │  [Frappe UI Button: Save]       │    │
│          │  └───────────────────────────────┘    │
│          │                                       │
│          │  Frappe UI Toast (success/error)       │
├──────────┴──────────────────────────────────────┤
│  Frappe UI Footer (optional)                     │
└─────────────────────────────────────────────────┘
```

**App.vue** wraps everything in Frappe UI's AppShell:

```vue
<template>
  <AppShell>
    <template #sidebar>
      <Sidebar :links="navLinks" />
    </template>
    <template #default>
      <router-view />
    </template>
  </AppShell>
</template>
```

**FormPage.vue** uses Frappe UI layout with FormKit in the content area:

```vue
<template>
  <div>
    <Breadcrumbs :items="breadcrumbs" />
    <h1 class="text-xl font-semibold mb-4">{{ formDef.data?.title }}</h1>

    <!-- FormKit handles the form, Frappe UI handles everything else -->
    <FormKit type="form" :actions="false" @submit="handleSubmit">
      <FormKitSchema
        :schema="JSON.parse(formDef.data?.form_schema || '[]')"
        :data="schemaData"
      />
      <div class="flex gap-2 mt-4">
        <Button variant="solid" type="submit" :loading="saveDoc.loading">
          Save
        </Button>
        <Button variant="outline" @click="router.back()">
          Cancel
        </Button>
      </div>
    </FormKit>
  </div>
</template>
```

---

## 14. Phased Implementation Plan

### Phase 1: Foundation
- [ ] Scaffold Frappe app (`bench new-app nce_builder`)
- [ ] Set up Vue 3 frontend (Doppio or manual Vite + Frappe UI setup)
- [ ] Install FormKit Core + Pro + Themes + Frappe UI
- [ ] Configure `formkit.config.ts` with Pro plugin and theme
- [ ] Create NCE Theme Settings DocType
- [ ] Build theme injection pipeline (DocType → CSS file → hooks.py)
- [ ] Build ThemePreview.vue with live sample inputs
- [ ] Set up App.vue with Frappe UI AppShell

### Phase 2: Form Rendering
- [ ] Create NCE Form Definition DocType
- [ ] Build NceForm.vue (generic schema renderer)
- [ ] Build FormPage.vue with Frappe UI layout shell
- [ ] Wire data loading via `createResource`
- [ ] Wire form submission via `createResource` + Frappe UI Toast feedback
- [ ] Test with a simple DocType (e.g. ToDo or custom test DocType)

### Phase 3: Advanced Inputs & Component Inception
- [ ] Build NceLinkField.vue (FormKit Autocomplete → createResource get_list)
- [ ] Build NceChildTable.vue (FormKit Repeater → child table mapping)
- [ ] Add link field traversal (fetch related docs on select via createResource)
- [ ] Add dependent field population
- [ ] Add conditional logic support in schemas
- [ ] Wrap Frappe UI Button as FormKit form action (component inception)
- [ ] Create custom FormKit inputs from Frappe UI components where useful

### Phase 4: List Views
- [ ] Create NCE List Config DocType
- [ ] Build NceList.vue using Frappe UI ListView + `createListResource`
- [ ] Build ListPage.vue with Frappe UI layout shell
- [ ] Wire row click → form navigation
- [ ] Add search functionality

### Phase 5: Page Router & Navigation
- [ ] Configure Vue Router for /nce/* paths
- [ ] Set up hooks.py website_route_rules
- [ ] Build Frappe UI Sidebar navigation
- [ ] Build Breadcrumbs for all pages
- [ ] Add authentication / role checks
- [ ] Add Frappe UI Dialog for confirmations

### Phase 6: KickStart Integration
- [ ] Use KickStart to generate schemas from DocType field lists
- [ ] Workflow: screenshot DocType → KickStart → paste schema → NCE Form Definition
- [ ] Build helper utility: auto-generate base schema from DocType metadata

### Phase 7: Polish & Multi-theme (Later)
- [ ] Multiple theme support (convert Single DocType → regular DocType)
- [ ] Theme switcher component
- [ ] Per-page / per-app theme assignment
- [ ] Export/import theme configurations

---

## 15. Development Workflow with KickStart

Recommended workflow for creating new forms:

1. **Get DocType field info** (using createResource or Frappe console):
   ```javascript
   createListResource({
     doctype: 'DocField',
     filters: { parent: 'Customer', parenttype: 'DocType' },
     fields: ['fieldname', 'fieldtype', 'label', 'options', 'reqd'],
     pageLength: 100
   })
   ```

2. **Feed to KickStart:**
   - Paste the field list as a prompt or attach as text
   - Prompt: "Create a form for these Frappe DocType fields: [paste]"
   - Or screenshot the Frappe form and upload it

3. **Export as FormKit schema** (JSON format)

4. **Store in NCE Form Definition:**
   - Create new NCE Form Definition record
   - Set target_doctype = "Customer"
   - Paste schema into form_schema field
   - Set field_mapping if names differ

5. **Test at** `/nce/form/customer-form`

---

## 16. Security Considerations

- **All data access goes through Frappe UI's `createResource`** — which in turn
  uses Frappe's REST API. Permissions, roles, and DocType-level security are
  enforced server-side. NCE Builder never bypasses Frappe's permission model.

- **Session management is native Frappe** — no custom auth layer. The Vue app
  runs inside Frappe's authenticated session.

- **CSRF protection** — handled automatically by Frappe UI's resource system.
  In development, the Vite proxy forwards cookies. In production, the CSRF
  token is attached to `window.csrf_token`.

- **NCE Form Definition has `allowed_roles`** — the Vue frontend checks roles
  before rendering and the API rejects unauthorized requests.

- **`requires_login` flag** — forms can be public (for portals) or
  authenticated-only.

- **FormKit Pro telemetry** — sends only domain name and input names used.
  No form data or PII. Can be disabled with Enterprise license ($1,250).

- **Schema injection** — the `form_schema` field accepts arbitrary JSON. Add
  server-side validation in `nce_form_definition.py` to reject schemas
  containing `$cmp` references to unauthorized components.

---

## 17. Key Decisions & Trade-offs

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Architecture | FormKit = data engine, Frappe UI = visual shell | Each library used for its strength |
| Page rendering | Frappe UI in-app pages (NOT standalone SPA) | Native Frappe look, session mgmt, Sidebar/Navbar/Toast |
| Form framework | FormKit (not Vueform) | Better ecosystem, schema-first, theme editor, KickStart, open-source core |
| Data access | Frappe UI `createResource` / `createListResource` | Native caching, reactivity, CSRF, loading states — no custom wrapper needed |
| Page layout | Frappe UI AppShell + Sidebar + Navbar | Native Frappe look and feel, consistent with other Frappe apps |
| List component | Frappe UI ListView (upgrade to TanStack Table if needed) | Native integration, less code, consistent UX |
| Theme storage | Single DocType + CSS file | Simple, site-wide, upgradable to multi-theme later |
| Form definitions | JSON schema in DocType | Serializable, editable, version-controllable, KickStart-compatible |
| Page routing | Vue Router inside Frappe UI app via website_route_rules | Clean URLs, SPA navigation within Frappe shell |
| Frontend scaffold | Doppio / frappe-ui-starter | Proven pattern for Frappe + Vue 3 + Tailwind |
| Feedback | Frappe UI Toast + Dialog | Consistent with Frappe ecosystem UX |
| Submit buttons | Frappe UI Button inside FormKit form (`:actions="false"`) | Native look, loading states from createResource |

---

## 18. External References

### Frappe
- Frappe Framework: https://frappe.io/framework
- Frappe UI: https://github.com/frappe/frappe-ui
- Frappe UI docs: https://ui.frappe.io
- Frappe UI createResource: https://ui.frappe.io/docs/resources
- Doppio (SPA scaffolder): https://github.com/NagariaHussain/doppio
- Frappe UI starter template: https://github.com/netchampfaris/frappe-ui-starter
- Frappe REST API: https://frappeframework.com/docs/user/en/api/rest

### FormKit
- Main site: https://formkit.com
- Schema docs: https://formkit.com/essentials/schema
- Styling / theming: https://formkit.com/essentials/styling
- Theme editor: https://themes.formkit.com/editor
- Theme creation guide: https://formkit.com/guides/create-a-tailwind-theme
- Pro inputs: https://formkit.com/pro
- KickStart: https://kickstart.formkit.com
- Drag & Drop: https://drag-and-drop.formkit.com
- AutoAnimate: https://auto-animate.formkit.com
- GitHub: https://github.com/formkit/formkit

### Tailwind CSS
- Theme variables (v4): https://tailwindcss.com/docs/theme
- Install with Vue 3: https://tailwindcss.com/docs/guides/vue-3-vite
