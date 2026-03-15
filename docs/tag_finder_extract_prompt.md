# Tag Finder — Extract for Another Project

Use this prompt to extract a **copyable, standalone Tag Finder** (Vue 3 Miller columns) for use in another Frappe app. The Tag Finder drills into DocType Link/Table relationships and generates paste-ready Jinja2 tags.

---

## Prompt to Use

Copy and paste this into your AI assistant when extracting to another project:

---

**Task:** Extract the Tag Finder from the NCE Events app into a standalone, copyable package for another Frappe project. The Tag Finder must:

1. Be Vue 3–based (Miller columns UI)
2. Use only Frappe DocType metadata (`frappe.get_meta`, `frappe.model.with_doctype`) — no NCE-specific DocTypes
3. Have **no dependencies** on code in the NCE Events project
4. Be copyable as a set of files with minimal adaptation

**Files to extract** (from `nce_events/public/js/panel_page_v2/`):

| File | Purpose |
|------|---------|
| `components/TagFinder.vue` | Main floating window, Miller columns container |
| `components/TagColumn.vue` | Single column of field tiles |
| `components/TagDialog.vue` | Tag detail dialog (Copy, fallback, HTML) |
| `composables/useTagFinder.js` | State, loadColumn, buildTag, buildPath, applyFilters |

**Dependencies to remove or replace:**

1. **`nce_events.api.tags.get_pronoun_tags_for_doctype`** — In `useTagFinder.js` line 69, this API is called for pronoun tags (He/She, his/her) when the root DocType has a `gender` field. Replace with:

   - **Option A:** Add a small Python API in the target app (see below)
   - **Option B:** Inline the logic: if `doctype` has a `gender` field, return static pronoun tags (he/she, etc.) without calling the API

2. **Namespace** — Change `nce_events` references to the target app’s namespace (e.g. `my_app`) if used.

3. **DocType picker** — The V1 `schema_explorer.js` uses `WP Tables` to list DocTypes. For a portable version, use `frappe.client.get_list` on `DocType` with `name: ["not in", ["DocType", "Module Def", ...]]` or a config DocType in the target app.

**CSS:** The Vue components use `var(--bg-surface)`, `var(--border-color)`, etc. Either include `theme_defaults.css` or copy the relevant variables into the target app’s CSS.

**API to add (optional):** If you want pronoun tags (He/She, his/her when DocType has `gender`), add `api/tags.py` to the target app. Copy from `nce_events/api/tags.py` — it has no NCE-specific dependencies. Then in `useTagFinder.js`, change the API path from `nce_events.api.tags.get_pronoun_tags_for_doctype` to `your_app.api.tags.get_pronoun_tags_for_doctype`.

**Usage:** Mount `TagFinder` with `rootDoctype` prop. Example:

```vue
<TagFinder v-if="tagFinderDoctype" :root-doctype="tagFinderDoctype" @close="tagFinderDoctype = ''" />
```

**Open programmatically:** `tagFinderDoctype = 'Enrollments'` (or any DocType name).

---

## Files to Copy (paths)

```
nce_events/public/js/panel_page_v2/components/TagFinder.vue
nce_events/public/js/panel_page_v2/components/TagColumn.vue
nce_events/public/js/panel_page_v2/components/TagDialog.vue
nce_events/public/js/panel_page_v2/composables/useTagFinder.js
nce_events/api/tags.py   # optional, for pronoun tags (He/She, his/her)
```

Optional: `nce_events/public/css/theme_defaults.css` (for CSS variables). Vue components use scoped styles and `var(--*)`; provide fallbacks or include theme_defaults.

---

## Source Reference

- **Project:** NCE Events (nce_events)
- **Repo:** `nce_events/public/js/panel_page_v2/`
- **Docs:** `Docs/project_reference.md` — Tag Finder section
