# RAG Context — Future‑Proof Frappe Apps (v15 + v16)

**Use case:** Retrieval context for an AI coding agent writing and reviewing Frappe apps that must run on **Frappe v15 and v16** from a **single codebase**.

**Non‑negotiables**
- Single codebase, no forks.
- Develop to v15 APIs, comply with v16 strictness.
- Never rely on implicit behaviors (sorting, type casting, JS globals, schema state).
- Centralize version logic; never scatter checks.

---

## 0) Quick Facts (High Signal)
- v16 uses **bleeding‑edge runtimes**; mismatches cause Python syntax errors or asset build failures.
- v16 shifts Desk routes from `/app` to `/desk`.
- v16 enforces stricter security (CSRF/POST requirements) and stricter hook return semantics.
- v16 modularizes “lean core” features into separate apps (install explicitly or guard).

---

## 1) Runtime / Dependency Matrix (Treat as a hard gate)
| Component | v15 | v16 | Consequence if wrong |
|---|---:|---:|---|
| Python | 3.10–3.12 | **3.14+** | Python `SyntaxError` / runtime incompat |
| Node.js | 18+ | **24+** | `bench build` fails |
| MariaDB | 10.6.6+ | **11.8+** | perf / lock handling regressions |
| Redis | 6.0 | 6.0+ (Redis 8 rec.) | cache/locking regressions |
| Package manager | pip | **uv** | install workflow differs |

**Agent rule:** If environment versions do not meet the v16 gates, stop and fix environment first.

---

## 2) Core v16 Architectural Shifts
- **SQLite support (v16):** single‑file DB option; useful for portable/offline/CI.
- **Lock‑free caching (v16):** removes blocking under concurrency; improves throughput.
- **Shorter UUIDs (v16):** faster writes at scale.

---

## 3) Cross‑Version Compatibility Principle
**Write v15‑compatible code** but **follow v16 strictness**:
- Explicit returns (`True/False`) for permission hooks.
- Explicit `order_by` in list queries.
- Defensive schema patches (`has_column` before add/alter).
- Explicit JS scoping (no implicit globals).
- Explicit whitelisting and guest access flags.

---

## 4) Version Detection (Centralized Only)
**Location:** `your_app/utils/version.py`

```python
import frappe

def get_frappe_major_version():
    try:
        return int(frappe.__version__.split(".")[0])
    except (ValueError, IndexError):
        return 15

def is_v16_or_later():
    return get_frappe_major_version() >= 16
```

**Agent rule:** all version‑conditionals must call these helpers; no inline checks elsewhere.

---

## 5) Packaging / Dependency Metadata
### 5.1 Dual‑support dependency range
`pyproject.toml`:
```toml
[tool.bench.frappe-dependencies]
frappe = ">=15.0.0,<17.0.0"
```

### 5.2 Python requirement declaration
```toml
[project]
requires-python = ">=3.10"
```

---

## 6) Database Semantics (Sorting + Type Casting)
### 6.1 Implicit sorting changed in v16
- v16 defaults list/get APIs to **`creation desc`**
- v15 commonly behaved like **`modified desc`**

**Rule:** If order matters, always set `order_by`.

✅ Correct:
```python
frappe.get_all("My DocType", filters=..., order_by="creation desc")
```

### 6.2 `db.get_value` (Single DocTypes) returns types in v16
- v16 returns proper Python types (int/float/datetime), not strings.

✅ Correct:
```python
if frappe.db.get_value("My Settings", "My Settings", "enabled"):
    ...
```

---

## 7) Permissions + Security Strictness
### 7.1 `has_permission` hook must return boolean
✅ Correct:
```python
def has_permission(doc, ptype, user):
    if condition:
        return True
    return False
```

### 7.2 Whitelisted methods + CSRF/POST enforcement
- v16 enforces stricter CSRF.
- Certain endpoints require **POST** (examples: `logout`, `web_logout`, `upload_file`).

✅ Correct public endpoint:
```python
@frappe.whitelist(allow_guest=True)
def my_public_function():
    ...
```

---

## 8) Schema / Patches (Defensive Only)
**Rule:** Never assume schema state during migrate.

✅ Correct:
```python
from frappe.database.schema import add_column
if not frappe.db.has_column("My DocType", "my_field"):
    add_column("My DocType", "my_field", "varchar(140)")
```

---

## 9) Lean Core Modularization (Must guard imports)
Some v15 “built‑ins” move to separate apps in v16:
- Offsite Backups (`offsite_backups`)
- Energy Points
- Blog
- Newsletter
- Transaction Logs

**Rule:** Either declare dependency, or guard imports.

✅ Correct:
```python
try:
    from frappe.email.doctype.newsletter.newsletter import Newsletter
except ImportError:
    Newsletter = None
```

---

## 10) Frontend / Desk / Workspaces
### 10.1 Route change
- v15: `/app`
- v16: `/desk`

**Rule:** never hardcode `/app` routes; treat route as configurable.

### 10.2 App landing page (v16) needs logo
`hooks.py`:
```python
app_logo_url = "/assets/your_app/images/logo.png"
```
Place at: `your_app/public/images/logo.png`

### 10.3 Workspace JSON re-import requires `modified` bump
**Rule:** Always bump workspace JSON `"modified"` timestamp when changing workspace content; otherwise `bench migrate` may not import it.

### 10.4 Deploy sequence (safe default)
```bash
bench --site all clear-cache
bench --site all migrate
bench build --app your_app
```

---

## 11) JavaScript (IIFE scope isolation in v16)
v16 loads Page/Report/Dashboard JS as IIFEs:
- top-level `var/let/const` does **not** leak to global scope.

✅ Correct:
```js
window.myHelper = function () { ... };
```
or
```js
frappe.provide('myapp.utils');
myapp.utils.helper = function () { ... };
```

---

## 12) PDF Rendering
- v16 moving from `wkhtmltopdf` to **Chrome-based** rendering.
**Rule:** ensure Chrome/Chromium exists on the server.

---

## 13) Migration Workflow (v15 → v16)
1. Install Python 3.14 and Node 24
2. Update app `pyproject.toml` dependencies
3. Switch branch:
```bash
bench switch-to-branch version-16 <app_name> --upgrade
```
4. Install lean-core apps if used (example: `offsite_backups`)
5. Rebuild assets:
```bash
bench build
```

---

## 14) Test Strategy (Dual target)
- CI must test against both v15 and v16 benches.
- Compatibility logic must be centralized in `utils/version.py`.

---

## 15) “Do Not” List (Hard failures)
- No scattered version checks.
- No implicit ordering in queries when order matters.
- No permission hooks returning `None`.
- No schema patches without `has_column` guard.
- No JS reliance on implicit globals.
- No unguarded imports for lean-core modules.
- No forgetting workspace JSON `modified` bump.
