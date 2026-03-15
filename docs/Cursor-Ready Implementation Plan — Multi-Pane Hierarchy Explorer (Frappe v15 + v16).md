# **Cursor-Ready Implementation Plan — Multi-Pane Hierarchy Explorer (Frappe v15 + v16)**





This plan produces a **reusable Desk Page** that displays a **Finder-style column view** for related DocTypes, up to **4 levels deep**, with **pane headers**, **actions**, **exports**, and **report links**, using a **single v15/v16-compatible codebase**.



------





## **0) Definition of Done**





The feature is complete when all of the following are true:



- A Desk Page renders a **left-to-right pane stack**: Level 1 → Level 2 → Level 3 → Level 4.

- Clicking a row in pane N **spawns pane N+1** using configured relationships.

- Each pane includes:

  

  - Header with summary metrics (record count + 2 configurable summary fields)
  - Action buttons: **Export CSV**, **Export JSON**, **Open Pane in New Tab**, **Open Record in New Tab**
  - Optional **Report Links** section (opens in new browser tab)

  

- Works on **Frappe v15 and v16**:

  

  - No implicit JS global reliance
  - Uses Desk routing that works in both

  

- Config is reusable: the same page code works for different DocType chains by changing a single config object.





------





## **1) Repo / App Layout**





Create (or use) an app, example: your_app.



**Files to add:**

```
your_app/
  your_app/
    utils/
      version.py
    api/
      hierarchy_explorer.py
  public/
    js/
      hierarchy_explorer/
        hierarchy_explorer.js
        store.js
        config.js
        ui/
          Pane.js
          PaneHeader.js
          PaneTable.js
          Actions.js
          ReportLinks.js
    css/
      hierarchy_explorer.css
  hooks.py
```

**Notes**



- JS is split into small modules so it stays maintainable as sophistication grows.
- One server API file handles all data retrieval and exports.





------





## **2) Core Data Model (Configuration-Driven)**





Create a single config format that describes the hierarchy.





### **2.1 Config object format (JS)**





public/js/hierarchy_explorer/config.js:



- root.doctype

- levels[] (max 4)

  

  - doctype
  - title
  - link_field_to_parent (child’s Link field pointing to parent)
  - fields[] (columns in table)
  - summary_fields[] (fields shown in header)
  - order_by
  - report_links[] (optional)

  

- max_depth fixed at 4 for first release





This is the only thing that changes between contexts.



------





## **3) Server-Side APIs (Single Entry Points)**





Create **two** whitelisted server functions:





### **3.1** 

### **get_children**





**Purpose:** fetch records for a pane based on parent selection.



Inputs:



- doctype
- fields (list)
- filters (map)
- order_by
- limit
- start





Outputs:



- rows[]
- count
- summary (computed summary values used by header)







### **3.2** 

### **export_rows**





**Purpose:** export current pane dataset.



Inputs:



- same as get_children
- format = "csv" or "json"





Outputs:



- For JSON: return JSON payload directly
- For CSV: return CSV content and filename; client downloads





**Implementation rule:** all ordering is explicit (order_by always set).



------





## **4) Desk Page UI Implementation**







### **4.1 Page entry**





Create a Desk Page JS entry that:



- Bootstraps the page layout
- Loads config
- Renders **Pane 1** on page load
- Manages state for selected rows and panes







### **4.2 Pane rendering rules**





- Pane index starts at 1.

- Clicking a row in Pane N:

  

  - Stores the selected record
  - Clears panes N+1…end
  - Loads data for Pane N+1 using configured relationship and link_field_to_parent

  

- Maximum depth enforced at 4.







### **4.3 UI components**





- Pane.js composes:

  

  - PaneHeader
  - Actions
  - ReportLinks (optional)
  - PaneTable

  







### **4.4 “Open in new tab/window”**





- **Open Record in New Tab**: open the record form URL for the selected row.
- **Open Pane in New Tab**: open the same page with query params that recreate the pane state (root + selection path).





State serialization:



- URL query param: path=<json> where path includes [{doctype, name}, ...] up to current depth.





------





## **5) Compatibility Rules (v15 + v16)**





These are enforced during implementation:



- JS modules never rely on implicit global variables.
- All app-level globals attach via frappe.provide('your_app.hierarchy') and stored under that namespace.
- No hardcoded /app routes. Use Frappe route helpers (Desk-safe) and build URLs from frappe.router when needed.
- All server queries specify order_by.
- Permission-safe behavior: server API respects Frappe permissions by default; no bypass.





------





## **6) Milestones (Implementation Steps)**







### **Milestone 1 — Scaffold + Pane 1 listing**





Deliverables:



- Page loads and shows Pane 1 list for root DocType.
- Pagination works (limit + start).
- Header shows record count.





Tasks:



1. Add utils/version.py (centralized version helpers).
2. Add api/hierarchy_explorer.py with get_children.
3. Add page JS hierarchy_explorer.js that renders a single pane with a table.
4. Add minimal CSS for split view columns.





Acceptance:



- Page renders with real data from frappe.get_list / frappe.get_all.
- Works on v15 and v16 benches.





------





### **Milestone 2 — Two-level navigation**





Deliverables:



- Clicking a row in Pane 1 loads Pane 2 with child records.
- Pane 2 clears/reloads correctly when selection changes.





Tasks:



1. Add config schema with levels[0] and levels[1].

2. Implement state store (store.js) to track:

   

   - panes[]
   - selected_path[]

   

3. Implement pane spawning logic.





Acceptance:



- Two panes show correct data tied by link relationship.





------





### **Milestone 3 — Full depth (up to 4 levels)**





Deliverables:



- Pane 3 and Pane 4 open correctly.
- Selection changes prune deeper panes and reload correctly.





Tasks:



1. Extend config to include up to 4 levels.
2. Enforce max depth.
3. Add UI polish for horizontal scrolling and fixed pane widths.





Acceptance:



- User traverses 4 levels without reload.





------





### **Milestone 4 — Pane header summary + actions**





Deliverables:



- Pane header shows:

  

  - count
  - two configurable summary fields (from record or computed)

  

- Actions:

  

  - Export JSON
  - Export CSV
  - Open record in new tab
  - Open pane in new tab

  





Tasks:



1. Add summary_fields support on server return.
2. Build Actions.js and wire button handlers.
3. Implement export_rows API.





Acceptance:



- Exports produce correct dataset matching current pane filters/order.





------





### **Milestone 5 — Report links per pane**





Deliverables:



- A pane shows optional report links configured in report_links[].
- Clicking opens report in new browser tab.





Tasks:



1. Add ReportLinks.js.

2. Define config format for a link:

   

   - label
   - route or URL builder

   

3. Ensure new-tab behavior.





Acceptance:



- Report links open correctly and do not break navigation state.





------





### **Milestone 6 — Deep-linking (URL state restore)**





Deliverables:



- Opening a “Pane in New Tab” reproduces the same state (pane chain + selected path).
- Page reads path= param and loads panes sequentially.





Tasks:



1. Implement serializePath() and restorePath() in store.

2. On load, detect path and replay selection chain:

   

   - load pane1
   - select record
   - load pane2
   - etc.

   





Acceptance:



- State restore works for 1–4 depth.





------





## **7) Performance Requirements (First Release)**





Hard rules:



- Each pane load is a single server call.
- Default limit per pane: 50 rows.
- A “Load more” mechanism exists for larger sets.





No caching layer is added in the first release. If needed, caching is implemented after correctness.



------





## **8) Testing Plan (Must Run on v15 and v16)**







### **8.1 Server tests**





- get_children respects filters and order_by.
- export_rows matches get_children dataset exactly.
- Permission behavior: user without permission receives empty list or permission error (standard Frappe behavior).







### **8.2 UI tests (manual acceptance)**





- Pane spawning, pruning, and reload correctness
- Export buttons download correct data
- Open-in-new-tab works and restores state
- Report link opens new tab







### **8.3 CI matrix**





- Run unit tests and basic smoke test on:

  

  - v15 bench
  - v16 bench

  

- Required commands:

  

  - bench --site <site> migrate
  - bench build --app your_app

  





------





## **9) Release Checklist (PR Gate)**





PR is accepted only if:



- Config supports at least 2 levels and the page proves the pattern.
- No scattered version checks exist outside utils/version.py.
- Every server query sets order_by.
- Exports match visible dataset.
- URL restore works for at least 2-depth path.
- Verified manually on both v15 and v16 benches.





------





## **10) Next Iteration (After First Release)**





These are explicitly deferred until after the base system works:



- Advanced header metrics (aggregations, totals)
- Saved views / user presets
- Column resizing, pinned columns, inline filters per pane
- Caching / prefetching / optimistic loading
- Custom per-pane row actions beyond open/export





------



If you paste your **actual DocType chain** (root doctype + the link field names for each level), I’ll produce the **exact config.js** for your first working context and the corresponding server-side filter construction rules.