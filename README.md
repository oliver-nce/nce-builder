# NCE Klaviyo Integration

WordPress plugin that syncs customer data to Klaviyo including profiles, family members, enrollments, and consent management.

## Features
- **Full Sync orchestration** - Task 9 runs all sync operations in sequence
- **Batch processing** - 450 records per batch (configurable)
- **Automatic rate limiting** - Handles HTTP 429 with exponential backoff
- **Real-time logging** - Progress tracking in database and log files
- **Admin Widget** - One-click sync from WordPress admin

## Available Tasks

| Task | Purpose | Documentation |
|------|---------|---------------|
| **1** | Upload family members to Klaviyo data source | [Task 1](wp-content/wp-custom-scripts/nce-runner-task-1/) |
| **3** | Bulk upsert profiles (attributes only) | [Task 3](wp-content/wp-custom-scripts/nce-runner-task-3/) |
| **4** | Grant email consent (NEW profiles) | [Task 4](wp-content/wp-custom-scripts/nce-runner-task-4/) |
| **5** | Grant SMS consent (NEW profiles) | [Task 5](wp-content/wp-custom-scripts/nce-runner-task-5/) |
| **6** | Bulk unsubscribe (suppression fix) | [Task 6](wp-content/wp-custom-scripts/nce-runner-task-6/) |
| **7** | Fetch & cache profiles with phone numbers | [Task 7](wp-content/wp-custom-scripts/nce-runner-task-7/) |
| **8** | Process cached profiles for SMS consent | [Task 8](wp-content/wp-custom-scripts/nce-runner-task-8/) |
| **9** | **Full Sync** - Runs Tasks 3 → 1 → 4 → 5 → 1b | [Task 9](wp-content/wp-custom-scripts/nce-runner-task-9/) |

See `TASK_SUMMARY.md` for complete usage guide.

## Quick Start

### Full Sync (Recommended)
```bash
# Run complete sync (profiles + family members + consent + enrollments)
curl "https://your-site.com/wp-json/nce/v1/run?task=9"

# With custom lookback (default: 14 hours)
curl "https://your-site.com/wp-json/nce/v1/run?task=9&lookback_hours=24"

# Skip specific tasks
curl "https://your-site.com/wp-json/nce/v1/run?task=9&skip_tasks=1,5"
```

### Individual Tasks
```bash
# Sync profiles only
curl "https://your-site.com/wp-json/nce/v1/run?task=3"

# Grant email consent (last 14 hours)
curl "https://your-site.com/wp-json/nce/v1/run?task=4"

# Grant SMS consent (last 14 hours)
curl "https://your-site.com/wp-json/nce/v1/run?task=5"
```

### Admin Widget

Embed the widget in WordPress admin:
1. Add Custom HTML block to any admin page
2. Paste contents of `wp-content/wp-custom-scripts/nce-runner-task-9/widget.html`
3. Click the button to run full sync

## Task 9: Full Sync Details

Task 9 orchestrates the following sequence:

| Step | Task | Description |
|------|------|-------------|
| 1 | Task 3 | Bulk upsert profiles from database |
| 2 | Task 1 | Upload family member data |
| 3 | Task 4 | Grant email consent for new profiles |
| 4 | Task 5 | Grant SMS consent for new profiles |
| 5 | Task 1b | Upload enrollment data |

**Parameters:**
- `lookback_hours` (default: 14) - How far back to look for new profiles
- `skip_tasks` - Comma-separated task numbers to skip (e.g., "1,5")
- `job_name` - Job identifier for logging

## Tools
- PHPCS + WPCS
- PHPStan
- PHPUnit

## Development
```bash
composer install
composer run lint | fix | stan | test
```

## Documentation
- `TASK_SUMMARY.md` - Complete task reference and usage guide
- `PROJECT_HANDOFF.md` - Detailed architecture and operations guide
- `KLAVIYO_SUPPORT_MEMO.md` - Consent rules and decision logic

## Version Control

All PHP scripts include a timestamp header on line 2:
```php
// LAST UPDATED: 2025-12-12
```

**Before uploading to server:** Note the timestamp  
**After uploading to server:** Verify line 2 matches your local file

## File Locations

```
wp-content/wp-custom-scripts/
├── nce-runner_task_manager.php     # Central dispatcher
├── temp_log.log                    # Execution logs
├── nce-runner-task-1/              # Family members + enrollment upload
├── nce-runner-task-3/              # Bulk upsert profiles
├── nce-runner-task-4/              # Email consent
├── nce-runner-task-5/              # SMS consent
├── nce-runner-task-6/              # Bulk unsubscribe
├── nce-runner-task-7/              # Fetch & cache profiles
├── nce-runner-task-8/              # Process cached profiles
└── nce-runner-task-9/              # Full sync orchestrator
    ├── run_full_sync.php
    └── widget.html
```
