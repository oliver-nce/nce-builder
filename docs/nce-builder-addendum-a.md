# NCE Builder — Addendum A: FileMaker-Style Layout Features

> Extends the main architecture plan with support for Tabs, Portals,
> Scripted Buttons, editable related records, and Pathfinder integration.
>
> **This document is a supplement for the Cursor coding agent.**
> Read the main architecture plan first.

---

## A1. Pathfinder — Existing Widget Integration

### What Pathfinder Is

Pathfinder is an existing JavaScript widget (already built) that visually
traverses Frappe DocType relationships via Link fields across multiple hops.
It displays DocTypes as columns, lets you click through Link fields to
navigate the relationship chain, and for any field at any depth it provides:

- **PATH** — The full traversal path (e.g. `Enrollments → player_id (Link) →
  People → family_id (Link) → Families → email`)
- **TAG** — A Jinja expression that resolves the value at runtime
  (e.g. `{{ frappe.db.get_value('Families', frappe.db.get_value('People',
  doc.player_id, 'family_id'), 'email') }}`)
- **FIELD metadata** — Field name, type, parent DocType

### How Pathfinder Is Used in NCE Builder

Pathfinder serves as the **configuration tool** for three features:

1. **Portal definitions** — Click through the chain to define which related
   DocType to show as an embedded list, and how to get there from the current
   document.
2. **Related field display** — Click a single field at any depth to define a
   many-to-one value to show (editable or read-only) on the current form.
3. **Dynamic text blocks** — Insert tags into text/HTML blocks on a form that
   resolve to related field values at render time.

Pathfinder is NOT rebuilt inside NCE Builder. It is called as-is (via dialog,
modal, or embedded panel) and its output (path definition + tags) is stored
in NCE Builder's DocType config fields.

### Pathfinder Output Format

When a user selects a field or defines a path in Pathfinder, the output is
a JSON object that NCE Builder stores and uses at runtime:

```json
{
  "path": [
    { "doctype": "Enrollments", "field": "player_id", "fieldtype": "Link", "target": "People" },
    { "doctype": "People", "field": "family_id", "fieldtype": "Link", "target": "Families" }
  ],
  "terminal_doctype": "Families",
  "terminal_field": "email",
  "terminal_fieldtype": "Data",
  "tag": "{{ frappe.db.get_value('Families', frappe.db.get_value('People', doc.player_id, 'family_id'), 'email') }}"
}
```

For a portal (related list), the output defines the path to the related
DocType but no terminal field — the portal shows multiple fields as columns:

```json
{
  "path": [
    { "doctype": "Customer", "field": "name", "fieldtype": "Data", "target": null }
  ],
  "terminal_doctype": "Sales Order",
  "link_field_in_target": "customer",
  "tag": null
}
```

This means: "Show all Sales Order records where `customer` = current doc's
`name`."

---

## A2. Tabs

### Concept

Tabs group form fields into switchable panels on a single page — just like
FileMaker Tab Controls. Any tab is accessible at any time (not sequential
like a wizard).

### Implementation

Use **Frappe UI Tabs component** as the container, with separate FormKit
schema sections rendered inside each tab pane. All tabs share a single
`<FormKit type="form">` wrapper so the data model stays unified.

### Schema Extension

The NCE Form Definition DocType gets an additional field:

| Field | Type | Description |
|-------|------|-------------|
| `tab_layout` | Code (JSON) | Tab structure definition |

Tab layout format:

```json
{
  "tabs": [
    {
      "key": "details",
      "label": "Details",
      "fields": ["customer_name", "customer_type", "tax_id"]
    },
    {
      "key": "address",
      "label": "Address",
      "fields": ["address_line1", "address_line2", "city", "state", "zip"]
    },
    {
      "key": "financials",
      "label": "Financials",
      "fields": ["credit_limit", "payment_terms", "currency"]
    },
    {
      "key": "related",
      "label": "Orders",
      "portals": ["sales_orders_portal"]
    }
  ]
}
```

### Rendering Pattern

```vue
<template>
  <FormKit type="form" :actions="false" @submit="handleSubmit" v-model="formData">
    <Tabs :tabs="tabDefs" v-model="activeTab">
      <template v-for="tab in tabDefs" :key="tab.key" #[tab.key]>

        <!-- Render FormKit fields assigned to this tab -->
        <FormKitSchema
          v-if="tab.fields"
          :schema="fieldsForTab(tab.key)"
          :data="schemaData"
        />

        <!-- Render portals assigned to this tab -->
        <NcePortal
          v-for="portalName in (tab.portals || [])"
          :key="portalName"
          :config="portalConfigs[portalName]"
          :parent-doc="formData"
        />

      </template>
    </Tabs>

    <div class="flex gap-2 mt-4">
      <Button variant="solid" type="submit" :loading="saveDoc.loading">
        Save
      </Button>
    </div>
  </FormKit>
</template>
```

**Key point:** The `<FormKit type="form">` wraps the entire tab set. FormKit
doesn't care that fields are in different tab panes — it still collects all
values into a single data object. Validation works across all tabs, and the
tab header can show a validation indicator (red dot) if any field in that
tab has errors.

---

## A3. Portals — Editable Related Record Lists

### Concept

A portal is an embedded, editable list of related records from another
DocType, displayed inside the current form page. This is the FileMaker
Portal equivalent.

**Critical distinction from FormKit Repeater:** A Repeater manages an array
of data inside a single form submission (like Frappe child table rows saved
with the parent). A Portal manages **independent Frappe documents** in a
separate DocType, each with its own name, permissions, and save lifecycle.

### Two Portal Types

**One-to-Many Portal** — The current document is the "one" side. The portal
shows records from another DocType that link back to this document.

Example: On a Customer form, show all Sales Orders where
`sales_order.customer = current_customer.name`

**Many-to-Many Portal** — Traverses through an intermediate linking DocType.

Example: On a People form, show Families via Enrollments where
`enrollment.player_id = current_person.name`, then hop to
`enrollment.family_id → Families`

### Portal Configuration

Stored as a child table on NCE Form Definition, or as a separate
NCE Portal Definition DocType:

| Field | Type | Description |
|-------|------|-------------|
| `portal_name` | Data (unique) | Identifier (e.g. "sales_orders_portal") |
| `title` | Data | Display title (e.g. "Sales Orders") |
| `path_config` | Code (JSON) | Pathfinder output — defines the relationship path |
| `display_columns` | Code (JSON) | Which fields to show as columns |
| `editable_fields` | Code (JSON) | Which fields can be edited inline |
| `edit_mode` | Select | inline / dialog / navigate |
| `form_schema` | Code (JSON) | FormKit schema for editing a single row (used in dialog mode) |
| `allow_create` | Check | Show "Add" button |
| `allow_delete` | Check | Show delete action per row |
| `default_sort` | Data | e.g. "creation desc" |
| `page_size` | Int | Rows per page (default 10) |

### How Pathfinder Configures a Portal

1. Open Pathfinder from the portal config screen
2. Starting from the current form's DocType, click through Link fields
   to reach the target DocType
3. Pathfinder outputs the path JSON
4. Store in `path_config`
5. Select which fields from the target DocType to display as columns
6. Select which fields are editable

### Portal Rendering — NcePortal.vue

```vue
<script setup>
import { createListResource, createResource } from 'frappe-ui'

const props = defineProps({
  config: Object,       // Portal definition
  parentDoc: Object,    // Current document's data
})

// Resolve the relationship path to build the filter
// For a simple one-hop: { customer: parentDoc.name }
// For multi-hop: resolve intermediate values first
const filters = computed(() => resolvePortalFilters(props.config.path_config, props.parentDoc))

const records = createListResource({
  doctype: props.config.path_config.terminal_doctype,
  fields: props.config.display_columns.map(c => c.fieldname),
  filters: filters,
  orderBy: props.config.default_sort || 'creation desc',
  pageLength: props.config.page_size || 10,
  auto: true
})

// Edit a single row
const editingRow = ref(null)

const saveRow = createResource({
  url: 'frappe.client.save',
  onSuccess() {
    records.reload()
    editingRow.value = null
    toast({ title: 'Saved', variant: 'success' })
  }
})

function handleRowSubmit(formData) {
  saveRow.submit({
    doc: {
      doctype: props.config.path_config.terminal_doctype,
      ...formData
    }
  })
}

// Create new related record
const createRow = createResource({
  url: 'frappe.client.save',
  onSuccess(newDoc) {
    records.reload()
    toast({ title: 'Created', variant: 'success' })
  }
})

function handleCreate() {
  // Pre-populate the link field that connects back to parent
  const linkField = props.config.path_config.link_field_in_target
  createRow.submit({
    doc: {
      doctype: props.config.path_config.terminal_doctype,
      [linkField]: props.parentDoc.name
    }
  })
}
</script>

<template>
  <div class="border rounded-lg p-4 mt-4">
    <div class="flex justify-between items-center mb-3">
      <h3 class="font-semibold text-sm">{{ config.title }}</h3>
      <Button
        v-if="config.allow_create"
        variant="outline"
        size="sm"
        @click="handleCreate"
      >
        + Add
      </Button>
    </div>

    <!-- Table display -->
    <table class="w-full text-sm">
      <thead>
        <tr>
          <th v-for="col in config.display_columns" :key="col.fieldname"
              class="text-left p-2 border-b font-medium text-gray-600">
            {{ col.label }}
          </th>
          <th class="w-16"></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in records.data" :key="row.name"
            class="border-b hover:bg-gray-50">

          <!-- Read mode -->
          <template v-if="editingRow !== row.name">
            <td v-for="col in config.display_columns" :key="col.fieldname"
                class="p-2">
              {{ row[col.fieldname] }}
            </td>
            <td class="p-2">
              <Button size="sm" variant="ghost" @click="editingRow = row.name">
                Edit
              </Button>
            </td>
          </template>

          <!-- Inline edit mode -->
          <template v-else>
            <td :colspan="config.display_columns.length + 1" class="p-2">
              <FormKit
                type="form"
                :value="row"
                :actions="false"
                @submit="handleRowSubmit"
              >
                <div class="flex gap-2 items-end">
                  <FormKit
                    v-for="col in config.editable_fields"
                    :key="col.fieldname"
                    :type="col.formkit_type || 'text'"
                    :name="col.fieldname"
                    :label="col.label"
                  />
                  <Button variant="solid" size="sm" type="submit"
                          :loading="saveRow.loading">
                    Save
                  </Button>
                  <Button variant="ghost" size="sm"
                          @click="editingRow = null">
                    Cancel
                  </Button>
                </div>
              </FormKit>
            </td>
          </template>

        </tr>
      </tbody>
    </table>

    <!-- Pagination -->
    <div v-if="records.data?.length >= config.page_size"
         class="flex justify-end mt-2">
      <Button size="sm" variant="ghost" @click="records.next()">
        More →
      </Button>
    </div>
  </div>
</template>
```

### Edit Modes

| Mode | Behavior |
|------|----------|
| `inline` | Row expands into an inline FormKit form (shown above) |
| `dialog` | Click Edit → Frappe UI Dialog opens with a full FormKit form |
| `navigate` | Click Edit → navigates to `/nce/form/{form_name}/{doc_name}` |

---

## A4. Editable Many-to-One Related Fields

### Concept

When a form has a Link field (e.g. `customer` on a Sales Order), you can
display and **edit** fields from the linked record directly on the current
form. In FileMaker terms, this is placing fields from a related table on
your layout.

### How Pathfinder Configures This

1. Open Pathfinder from the form definition config
2. Navigate to the related DocType (one or more hops)
3. Select specific fields from the related record
4. Pathfinder outputs the path + field list
5. Store as a "related fields group" on the form definition

### Related Fields Configuration

Added to NCE Form Definition as a child table or JSON field:

| Field | Type | Description |
|-------|------|-------------|
| `group_name` | Data | Identifier (e.g. "customer_details") |
| `title` | Data | Display title (e.g. "Customer Details") |
| `path_config` | Code (JSON) | Pathfinder output — path to related DocType |
| `fields` | Code (JSON) | Which fields to display |
| `editable` | Check | Whether fields can be edited |
| `display_mode` | Select | inline / collapsible / card |

Fields configuration:

```json
{
  "group_name": "customer_details",
  "title": "Customer Details",
  "path_config": {
    "path": [
      { "doctype": "Sales Order", "field": "customer", "fieldtype": "Link", "target": "Customer" }
    ],
    "terminal_doctype": "Customer"
  },
  "fields": [
    { "fieldname": "customer_name", "label": "Customer Name", "editable": true },
    { "fieldname": "territory", "label": "Territory", "editable": true },
    { "fieldname": "credit_limit", "label": "Credit Limit", "editable": false }
  ],
  "editable": true,
  "display_mode": "card"
}
```

### Rendering — NceRelatedFields.vue

```vue
<script setup>
import { createResource } from 'frappe-ui'

const props = defineProps({
  config: Object,
  parentDoc: Object,
})

// Resolve path to get the related document name
const relatedDocName = computed(() =>
  resolvePathToValue(props.config.path_config, props.parentDoc)
)

// Fetch the related document
const relatedDoc = createResource({
  url: 'frappe.client.get',
  params: computed(() => ({
    doctype: props.config.path_config.terminal_doctype,
    name: relatedDocName.value
  })),
  auto: true
})

// Save edits to the related document
const saveRelated = createResource({
  url: 'frappe.client.save',
  onSuccess() {
    relatedDoc.reload()
    toast({ title: 'Related record updated', variant: 'success' })
  }
})

function handleRelatedSubmit(formData) {
  saveRelated.submit({
    doc: {
      doctype: props.config.path_config.terminal_doctype,
      name: relatedDocName.value,
      ...formData
    }
  })
}
</script>

<template>
  <div class="border rounded-lg p-4 mt-4 bg-gray-50">
    <h3 class="font-semibold text-sm mb-3">{{ config.title }}</h3>

    <div v-if="relatedDoc.loading" class="text-gray-400 text-sm">
      Loading...
    </div>

    <div v-else-if="relatedDoc.data">

      <!-- Editable mode: FormKit form for the related record -->
      <FormKit
        v-if="config.editable"
        type="form"
        :value="relatedDoc.data"
        :actions="false"
        @submit="handleRelatedSubmit"
      >
        <div class="grid grid-cols-2 gap-3">
          <FormKit
            v-for="field in config.fields"
            :key="field.fieldname"
            :type="field.formkit_type || 'text'"
            :name="field.fieldname"
            :label="field.label"
            :disabled="!field.editable"
          />
        </div>
        <Button
          variant="outline"
          size="sm"
          type="submit"
          :loading="saveRelated.loading"
          class="mt-2"
        >
          Update {{ config.title }}
        </Button>
      </FormKit>

      <!-- Read-only mode: simple display -->
      <div v-else class="grid grid-cols-2 gap-2 text-sm">
        <div v-for="field in config.fields" :key="field.fieldname">
          <span class="text-gray-500">{{ field.label }}:</span>
          <span class="ml-1 font-medium">
            {{ relatedDoc.data[field.fieldname] }}
          </span>
        </div>
      </div>

    </div>
  </div>
</template>
```

### Save Behavior

When the user clicks Save on the main form, the parent document saves via
its own `createResource` call. If related fields were edited, they save
**separately** via their own `createResource` call to the related DocType.
These are independent Frappe documents — they have their own permissions
and validation. The UI can save both in parallel or sequentially.

---

## A5. Dynamic Text Blocks with Pathfinder Tags

### Concept

A text block on a form can contain Pathfinder tags that resolve to related
field values at render time. These are not form inputs — they're display
elements that show computed/related information.

Tags can combine multiple fields to build a display string:

```
{{ name_first }} {{ name_last }}
```

Renders as: **John Smith**

Tags can traverse relationships:

```
Family: {{ frappe.db.get_value('Families',
  frappe.db.get_value('People', doc.player_id, 'family_id'),
  'first_name') }}
```

### How Tags Are Inserted

1. In the form definition editor, place a text block element
2. Click into the text block, position cursor
3. Open Pathfinder
4. Navigate to the desired field
5. Click "Insert at Cursor" — Pathfinder inserts the tag at the cursor position
6. The tag is stored as part of the form schema

### Schema Representation

Text blocks with tags are stored in the FormKit schema as `$el` nodes
with a special `data-nce-tags` attribute:

```json
{
  "$el": "div",
  "attrs": {
    "class": "p-3 bg-gray-50 rounded-md text-sm",
    "data-nce-tags": true
  },
  "children": "Customer: $resolve('customer.customer_name') — Territory: $resolve('customer.territory')"
}
```

Or using Pathfinder's full path syntax for multi-hop:

```json
{
  "$el": "div",
  "attrs": {
    "class": "p-3 bg-blue-50 rounded-md text-sm font-medium",
    "data-nce-tags": true
  },
  "children": "$resolve('player_id.first_name') $resolve('player_id.last_name') — $resolve('player_id.family_id.first_name') Family"
}
```

### Tag Resolution at Runtime

The `$resolve()` function is passed to FormKitSchema via the `:data` prop.
It uses the Pathfinder path definitions to fetch values:

```typescript
// Passed to <FormKitSchema :data="schemaData" />
const schemaData = {
  resolve: (path: string) => {
    // path = "player_id.family_id.first_name"
    // Walk the path, fetching related docs as needed
    return resolvePathValue(path, currentDoc.value, pathCache)
  }
}
```

The resolver caches fetched related documents so that multiple tags
referencing the same related record don't trigger duplicate API calls.

### Combining Multiple Fields in Display

Pathfinder tags can be placed adjacent to build composite displays:

| Pattern | Result |
|---------|--------|
| `$resolve('first_name') $resolve('last_name')` | John Smith |
| `$resolve('city'), $resolve('state') $resolve('zip')` | Newark, NJ 07101 |
| `$resolve('customer.customer_name') (#$resolve('customer.name'))` | Acme Corp (#CUST-001) |

---

## A6. Scripted Buttons

### Concept

Configurable buttons placed on a form that trigger Frappe server actions,
workflows, navigation, or custom scripts. In FileMaker terms, these are
buttons with attached scripts.

### Button Configuration

Stored as a JSON array in the NCE Form Definition:

| Field | Type | Description |
|-------|------|-------------|
| `buttons` | Code (JSON) | Array of button definitions |

Button definition format:

```json
{
  "buttons": [
    {
      "key": "create_invoice",
      "label": "Create Invoice",
      "variant": "solid",
      "icon": "file-text",
      "position": "toolbar",
      "action": "api_call",
      "api_method": "myapp.api.create_invoice_from_order",
      "params": {
        "sales_order": "$doc.name"
      },
      "confirm": {
        "title": "Create Invoice?",
        "message": "This will create a Sales Invoice for this order."
      },
      "on_success": "reload",
      "visible_when": "$doc.docstatus == 1",
      "roles": ["Accounts User", "Accounts Manager"]
    },
    {
      "key": "send_email",
      "label": "Send Confirmation",
      "variant": "outline",
      "position": "toolbar",
      "action": "api_call",
      "api_method": "frappe.core.doctype.communication.email.make",
      "params": {
        "recipients": "$doc.contact_email",
        "subject": "Order Confirmation: $doc.name"
      },
      "on_success": "toast"
    },
    {
      "key": "approve",
      "label": "Approve",
      "variant": "solid",
      "position": "toolbar",
      "action": "workflow",
      "workflow_action": "Approve",
      "visible_when": "$doc.workflow_state == 'Pending Approval'"
    },
    {
      "key": "view_customer",
      "label": "View Customer →",
      "variant": "ghost",
      "position": "below_form",
      "action": "navigate",
      "navigate_to": "/nce/form/customer-form/$doc.customer"
    },
    {
      "key": "calculate_total",
      "label": "Recalculate",
      "variant": "outline",
      "position": "inline",
      "action": "client_script",
      "script": "formData.total = formData.items.reduce((sum, item) => sum + (item.qty * item.rate), 0)"
    }
  ]
}
```

### Action Types

| Action | What It Does |
|--------|-------------|
| `api_call` | Calls a whitelisted Frappe method via `createResource` |
| `workflow` | Triggers a workflow state transition |
| `navigate` | Vue Router navigation to another NCE page |
| `client_script` | Runs JavaScript locally on the form data |
| `open_dialog` | Opens a Frappe UI Dialog with custom content |
| `open_portal` | Opens a portal view in a dialog |

### Rendering — NceButtons.vue

```vue
<script setup>
import { createResource } from 'frappe-ui'

const props = defineProps({
  buttons: Array,
  doc: Object,
  formData: Object,
  position: String,  // "toolbar", "below_form", "inline"
})

const session = inject('session')  // for role checking

// Filter buttons by position, visibility, and roles
const visibleButtons = computed(() =>
  props.buttons
    .filter(btn => btn.position === props.position)
    .filter(btn => !btn.visible_when || evaluateCondition(btn.visible_when, props.doc))
    .filter(btn => !btn.roles || btn.roles.some(r => session.user.roles.includes(r)))
)

function executeAction(btn) {
  if (btn.confirm) {
    showDialog({
      title: btn.confirm.title,
      message: btn.confirm.message,
      onConfirm: () => doAction(btn)
    })
  } else {
    doAction(btn)
  }
}

function doAction(btn) {
  switch (btn.action) {
    case 'api_call':
      const resource = createResource({
        url: btn.api_method,
        onSuccess: () => handleSuccess(btn),
        onError: (e) => toast({ title: 'Error', message: e.message, variant: 'error' })
      })
      resource.submit(resolveParams(btn.params, props.doc))
      break

    case 'workflow':
      const wf = createResource({
        url: 'frappe.client.set_workflow_action',
        onSuccess: () => handleSuccess(btn)
      })
      wf.submit({
        doctype: props.doc.doctype,
        name: props.doc.name,
        action: btn.workflow_action
      })
      break

    case 'navigate':
      router.push(resolveTemplate(btn.navigate_to, props.doc))
      break

    case 'client_script':
      new Function('formData', 'doc', btn.script)(props.formData, props.doc)
      break
  }
}
</script>

<template>
  <div class="flex gap-2">
    <Button
      v-for="btn in visibleButtons"
      :key="btn.key"
      :variant="btn.variant || 'outline'"
      @click="executeAction(btn)"
    >
      {{ btn.label }}
    </Button>
  </div>
</template>
```

### Button Positions on the Page

```
┌─────────────────────────────────────────────────┐
│  Navbar                                          │
├──────────┬──────────────────────────────────────┤
│          │  Breadcrumbs                           │
│          │  Title          [Toolbar Buttons ▶▶▶]  │
│  Sidebar │──────────────────────────────────────│
│          │  ┌──────────────────────────────┐     │
│          │  │  Tab 1  │  Tab 2  │  Tab 3   │     │
│          │  ├──────────────────────────────┤     │
│          │  │                               │     │
│          │  │  FormKit fields               │     │
│          │  │  [Inline Buttons]             │     │
│          │  │                               │     │
│          │  │  Portal (related list)        │     │
│          │  │                               │     │
│          │  │  Related Fields (many-to-one) │     │
│          │  │                               │     │
│          │  │  Text Block with Tags         │     │
│          │  │                               │     │
│          │  └──────────────────────────────┘     │
│          │                                       │
│          │  [Below Form Buttons]                  │
│          │  [Save]  [Cancel]                      │
├──────────┴──────────────────────────────────────┤
└─────────────────────────────────────────────────┘
```

---

## A7. Updated DocType — NCE Form Definition (Revised)

The main plan's NCE Form Definition gains these additional fields:

| Field | Type | Description |
|-------|------|-------------|
| `tab_layout` | Code (JSON) | Tab structure (Section A2) |
| `portals` | Code (JSON) | Array of portal definitions (Section A3) |
| `related_field_groups` | Code (JSON) | Array of many-to-one groups (Section A4) |
| `text_blocks` | Code (JSON) | Text blocks with Pathfinder tags (Section A5) |
| `buttons` | Code (JSON) | Scripted button definitions (Section A6) |

All of these are configured using Pathfinder for path definitions and stored
as JSON. The form renderer reads them and instantiates the appropriate
components.

---

## A8. Complete Form Page Rendering Order

When FormPage.vue loads, it assembles the full page from the form definition:

```
1. Load NCE Form Definition via createResource
2. Load target document via createResource (if editing)
3. Parse tab_layout, form_schema, portals, related_field_groups, buttons

4. Render Frappe UI shell (AppShell, Sidebar, Navbar, Breadcrumbs)

5. Render toolbar buttons (position: "toolbar")

6. Render <FormKit type="form"> wrapper

7. If tab_layout exists:
     Render Frappe UI Tabs
     For each tab:
       a. Render FormKit fields assigned to this tab
       b. Render NceRelatedFields components (many-to-one groups)
       c. Render NcePortal components (one-to-many lists)
       d. Render text blocks with resolved Pathfinder tags
       e. Render inline buttons (position: "inline")

   If no tab_layout:
     Render all fields, portals, related fields, text blocks sequentially

8. Render below-form buttons (position: "below_form")
9. Render Save / Cancel buttons (Frappe UI Button)
```

---

## A9. Pathfinder Integration Points (Summary)

| Feature | Pathfinder Does | NCE Builder Does |
|---------|----------------|-----------------|
| Portal definition | Defines the path from current DocType to related DocType | Queries related records, renders editable list |
| Many-to-one fields | Defines path to single related record + field list | Fetches related doc, renders editable FormKit group |
| Text block tags | Generates `$resolve()` tags with multi-hop paths | Resolves tags at runtime, caches related docs |
| Link field setup | Identifies available Link fields on a DocType | Renders FormKit Autocomplete wired to createResource |
| Button params | Can provide field paths for button parameters | Resolves `$doc.fieldname` references in button config |

---

## A10. Updated Phasing

These features slot into the existing phases:

**Phase 2 (Form Rendering)** — Add tab layout support

**Phase 3 (Advanced Inputs)** — Add:
- NcePortal.vue (editable related lists)
- NceRelatedFields.vue (editable many-to-one)
- NceButtons.vue (scripted buttons)
- Pathfinder integration (as configuration tool)

**Phase 4 (List Views)** — Portal list rendering shares code with NceList.vue

**Phase 5 (Page Router)** — Button navigation actions

**New Phase 3.5: Text Blocks & Tag Resolution**
- Tag resolver function (`$resolve()`)
- Related doc caching layer
- Pathfinder "Insert at Cursor" integration
- Composite display patterns (multi-field concatenation)
