# NCE Klaviyo Integration

WordPress integration that syncs customer data to Klaviyo including profiles, family members, enrollments, and consent management.

## Features

- **Chained Cron Execution** - Each task runs in its own PHP process with independent timeouts
- **JSON-Based Configuration** - Edit task order, enable/disable, and pauses via `tasks-config.json`
- **Interactive Dashboard** - Embedded in WordPress via shortcode `[nce_job_runner]`
- **Payment Plans Viewer** - View payment plan status via shortcode `[nce_payment_plans]`
- **Global Lock Mechanism** - Prevents overlapping cron runs
- **Batch Processing** - 450 records per batch with automatic rate limiting
- **Comprehensive Logging** - Per-run log files with task timing

## Quick Start

### Dashboard Access
Add shortcode to any WordPress page:
```
[nce_job_runner]
```
- View and edit task configuration
- Run individual tasks or full sync
- View Klaviyo globals table

### Payment Plans Viewer
Add shortcode to any WordPress page:
```
[nce_payment_plans]
```
- View all payment plans grouped by plan ID
- Filter by customer name/email
- See paid vs outstanding amounts

### Run Full Sync via Cron
The chained cron executes tasks in sequence:
1. **Zoho Update** - SQL procedure `update_for_zoho_all`
2. **Profile Upsert** - Two-step email match + consent
3. **Family Members** - Custom object upload
4. **Email Consent** - Grant marketing consent
5. **SMS Consent** - Grant SMS consent
6. **Enrollment** - Custom object upload
7. **Enrollment Events** - Event tracking

## Task Configuration

Edit `wp-content/wp-custom-scripts/tasks-config.json`:

```json
{
  "api_key": "pk_xxx",
  "api_version": "2025-10-15",
  "tasks": [
    {
      "id": "zoho",
      "order": 1,
      "name": "Zoho Update",
      "enabled": true,
      "pause": 5,
      "stop_on_fail": true,
      "type": "sql",
      "procedure": "update_for_zoho_all"
    },
    {
      "id": 3,
      "order": 2,
      "name": "Profile Upsert",
      "enabled": true,
      "pause": 30,
      "stop_on_fail": true,
      "type": "task",
      "file": "nce-runner-task-3/upsert_profiles_two_step.php",
      "function": "nce_task_upsert_klaviyo_profiles_two_step",
      "params": { "job_name": "profiles" }
    }
  ]
}
```

**Task Properties:**
| Property | Description |
|----------|-------------|
| `order` | Execution order (editable in dashboard) |
| `enabled` | Whether task runs in cron chain |
| `pause` | Seconds to wait after task completes |
| `stop_on_fail` | Cancel remaining chain if task fails |
| `type` | `sql` (stored procedure) or `task` (PHP function) |

## Available Tasks

| ID | Name | Purpose |
|----|------|---------|
| zoho | Zoho Update | Run SQL procedure for Zoho sync |
| 3 | Profile Upsert | Two-step upsert (email match + phone patch + consent) |
| 1 | Family Members | Upload family member data to Klaviyo |
| 4 | Email Consent | Grant email marketing consent |
| 5 | SMS Consent | Grant SMS marketing consent |
| 1b | Enrollment | Upload enrollment data to Klaviyo |
| 10 | Enrollment Events | Send enrollment events to Klaviyo |

## REST API Endpoints

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/wp-json/nce/v1/run?task=X&job_name=Y` | GET | Run individual task |
| `/wp-json/nce/v1/tasks-config` | GET | Read task configuration |
| `/wp-json/nce/v1/tasks-config` | POST | Save task configuration |
| `/wp-json/nce/v1/dashboard` | GET | Get dashboard HTML (iframe) |

## File Structure

```
wp-content/
├── plugins/nce-runner/
│   └── nce-runner.php              # REST API + shortcodes
│
├── mu-plugins/
│   ├── klaviyo-cron-loader.php     # Loads cron-handler on every request
│   └── nce-payment-plans-viewer.php # Payment plans shortcode
│
└── wp-custom-scripts/
    ├── tasks-config.json           # Task configuration (editable)
    ├── cron-handler.php            # Chained cron execution
    ├── nce-runner_task_manager.php # Task router
    │
    ├── Job Runner/
    │   └── dashboard.php           # Dashboard UI
    │
    ├── nce-runner-task-1/          # Data object uploads
    ├── nce-runner-task-3/          # Profile sync (two-step)
    ├── nce-runner-task-4/          # Email consent
    ├── nce-runner-task-5/          # SMS consent
    ├── nce-runner-task-9/          # Full sync (legacy)
    ├── nce-runner-task-10/         # Event tracking
    │
    ├── includes/                   # Shared utilities
    └── logs/                       # Execution logs
```

## Cron Setup

### WP Crontrol Plugin
1. Install and activate WP Crontrol
2. Add PHP Cron Event: `klaviyo_chained_sync_cron()`
3. Set schedule (e.g., every 6 hours)

### Manual cPanel Cron
```bash
/usr/local/bin/php /path/to/wp-content/wp-custom-scripts/cron-handler.php
```

## Key Concepts

### Two-Step Profile Upsert
Avoids phone number matching issues:
1. `POST /api/profile-import` - Upsert by email only
2. `PATCH /api/profiles/{id}` - Update phone by profile ID
3. `POST /api/profile-subscription-bulk-create-jobs` - Email consent by ID
4. `POST /api/profile-subscription-bulk-create-jobs` - SMS consent by ID

### Global API Key
API key is stored in `tasks-config.json` and loaded into global variables:
- `$NCE_KLAVIYO_API_KEY`
- `$NCE_KLAVIYO_API_VERSION`

All tasks use these globals instead of per-job lookups.

### Cron Lock Mechanism
Uses WordPress transients to prevent overlapping runs:
- Lock acquired at start of chain
- Released on completion or failure
- 30-minute auto-expiry as safety net

## Logs

Log files are written to `wp-content/wp-custom-scripts/logs/`:
- `cron_sync_YYYY-MM-DD_HH-MM-SS.log` - Cron chain execution
- `task1_write_objects_*.log` - Data object uploads
- `task3_profiles_*.log` - Profile sync

## Documentation

- `docs/AI-AGENT-ONBOARDING.md` - Developer onboarding guide
- `docs/api-endpoints.md` - API reference
- `docs/browser-quick-reference.md` - Quick testing guide

## Version

Last updated: 2025-12-18
