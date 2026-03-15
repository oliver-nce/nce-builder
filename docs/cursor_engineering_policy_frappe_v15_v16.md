# Cursor Engineering Policy — Future‑Proof Frappe Apps (v15 + v16)

**Scope:** Rules for planning, coding, reviewing, and shipping Frappe apps that support **both v15 and v16** from **one codebase**.

This document is written as enforceable policy for Cursor (and human reviewers). Any PR that violates a MUST rule is rejected.

---

## 1) Primary Goal
Single codebase compatible with:
- Frappe v15 benches
- Frappe v16 benches

No forks, no “v16-only” branch logic scattered through the code.

---

## 2) Environment Gates (MUST pass)
### v16 minimum runtime gates
- Python: **3.14+**
- Node: **24+**
- MariaDB: **11.8+**
- Redis: 6+ (Redis 8 preferred)

**Policy:** If any gate fails, the correct action is to fix environment first. Do not “patch around” missing runtime features.

---

## 3) Packaging Policy (MUST)
### 3.1 Frappe dependency range (dual support)
`pyproject.toml` MUST include:
```toml
[tool.bench.frappe-dependencies]
frappe = ">=15.0.0,<17.0.0"
```

### 3.2 Python requirement
```toml
[project]
requires-python = ">=3.10"
```

---

## 4) Version Logic Policy (MUST)
### 4.1 Single source of truth
All version checks MUST live in:
- `your_app/utils/version.py`

Required functions:
- `get_frappe_major_version()`
- `is_v16_or_later()`

No other file may parse `frappe.__version__` directly.

### 4.2 Conditional logic allowed only when required
When conditional logic is required, use:
```python
from your_app.utils.version import is_v16_or_later
```

---

## 5) Database Query Policy (MUST)
### 5.1 Explicit ordering
Any query where order matters MUST provide `order_by`.

Forbidden:
```python
frappe.get_all("X", filters=...)
```

Required:
```python
frappe.get_all("X", filters=..., order_by="creation desc")
```

### 5.2 No string assumptions from Single DocType get_value
Forbidden:
```python
... == "1"
```

Required:
```python
if frappe.db.get_value(...):
    ...
```
or explicit casting.

---

## 6) Permissions & Security Policy (MUST)
### 6.1 has_permission hooks
MUST return boolean in all cases.

Forbidden:
- returning `None`
- returning ambiguous truthy values

Required:
```python
return True
return False
```

### 6.2 Whitelisted methods
Any HTTP-exposed function MUST use `@frappe.whitelist(...)` with explicit guest access only when needed:

```python
@frappe.whitelist(allow_guest=True)
def public():
    ...
```

### 6.3 POST requirements in v16
Endpoints that require POST in v16 MUST be treated as POST-only across the app. Do not create GET alternatives.

---

## 7) Schema & Patches Policy (MUST)
### 7.1 Defensive schema updates
Any patch that adds/changes schema MUST guard with `frappe.db.has_column` (or equivalent existence check).

Required pattern:
```python
if not frappe.db.has_column("DocType", "field"):
    ...
```

Forbidden:
- direct add/alter assuming absent/present

---

## 8) Lean Core / Optional Modules Policy (MUST)
### 8.1 Guard all lean-core imports
If a feature moved out of core in v16 (newsletter/blog/offsite backups/energy points/transaction logs), code MUST:
- declare dependency OR
- guard imports with try/except and handle missing module.

Required:
```python
try:
    ...
except ImportError:
    ...
```

### 8.2 Offsite backups
If remote backups are used, `offsite_backups` app MUST be installed on v16 benches. PRs must include explicit install/runbook steps.

---

## 9) Frontend / JS Policy (MUST)
### 9.1 No implicit globals in Page/Report JS
v16 loads JS in IIFEs. Code MUST not rely on `var/let/const` at top level being global.

Required:
```js
window.someHelper = ...
```
or
```js
frappe.provide('myapp.utils');
myapp.utils.helper = ...
```

---

## 10) Desk / Routes / Workspaces Policy (MUST)
### 10.1 Route change awareness
v16 uses `/desk` instead of `/app`. Hardcoded `/app` links are forbidden.

### 10.2 App landing logo
`hooks.py` MUST include `app_logo_url` and the asset MUST exist:
```python
app_logo_url = "/assets/your_app/images/logo.png"
```
File must be at:
- `your_app/public/images/logo.png`

### 10.3 Workspace JSON update rule
When workspace JSON content changes, the JSON `modified` timestamp MUST be bumped; otherwise migrations can skip import.

---

## 11) PDF Rendering Policy (MUST)
v16 uses Chrome-based PDF rendering in place of wkhtmltopdf.

Policy:
- Chrome/Chromium presence is a deployment requirement.
- Runbooks must include installation/verification steps.

---

## 12) CI/CD Policy (MUST)
### 12.1 Dual bench test matrix
CI must run tests on:
- Frappe v15 bench
- Frappe v16 bench

### 12.2 Required build steps (per bench)
- `bench --site <site> migrate`
- `bench build --app <app>`

### 12.3 Fail-fast criteria
CI fails immediately on:
- missing runtime gates (Python/Node)
- any import error from optional modules without guard
- migration errors
- JS build failures

---

## 13) Release Checklist (MUST)
A release PR must include confirmation of:
- `pyproject.toml` range `>=15,<17`
- version helpers exist and are used
- explicit `order_by` for order-sensitive queries
- `has_permission` returns boolean
- schema patches guarded
- no implicit JS globals
- workspace JSON `modified` bumped
- `app_logo_url` present and asset exists
- lean-core imports guarded or app dependencies installed
- tests pass on v15 and v16 benches

---

## 14) Prohibited Practices (PR rejection)
- Scattered version checks (`frappe.__version__` parsing in random files)
- Implicit ordering reliance in list/get APIs
- Permission hooks that return `None`
- Schema patches without existence checks
- Frontend code relying on global scope leakage
- Unconditional imports of lean-core modules
- Hardcoded `/app` URLs
- Workspace JSON changes without `modified` bump
