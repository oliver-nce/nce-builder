# NCE Klaviyo Integration

WordPress plugin that uploads customer data from database to Klaviyo's Data Source API in batches.

## Features
- **Synchronous execution** - Completes in 2-3 minutes for 17k records
- **Batch processing** - 450 records per batch (configurable)
- **Automatic rate limiting** - Handles HTTP 429 with exponential backoff
- **Real-time logging** - Progress tracking in database and log files

## Available Tasks

| Task | Purpose | Documentation |
|------|---------|---------------|
| **3** | Bulk upsert profiles (attributes only) | [Task 3](wp-content/wp-custom-scripts/nce-runner-task-3/) |
| **4** | Grant email consent (NEW profiles) | [Task 4](wp-content/wp-custom-scripts/nce-runner-task-4/) |
| **5** | Grant SMS consent (NEW profiles) | [Task 5](wp-content/wp-custom-scripts/nce-runner-task-5/) |
| **6** | Bulk unsubscribe (suppression fix) | [Task 6](wp-content/wp-custom-scripts/nce-runner-task-6/) |
| **7** | Backfill SMS consent (ALL profiles) | [Task 7](wp-content/wp-custom-scripts/nce-runner-task-7/) |

See `TASK_SUMMARY.md` for complete usage guide.

## Quick Start

### Standard Workflow (New Signups)
```bash
# 1. Sync profiles
curl "https://your-site.com/wp-json/nce/v1/run?task=3"

# 2. Grant email consent (last 2 hours)
curl "https://your-site.com/wp-json/nce/v1/run?task=4"

# 3. Grant SMS consent (last 2 hours, if verified opt-in)
curl "https://your-site.com/wp-json/nce/v1/run?task=5"
```

### One-Time SMS Backfill
```bash
# Test first
curl "https://your-site.com/wp-json/nce/v1/run?task=7&dry_run=true"

# Process profiles
curl "https://your-site.com/wp-json/nce/v1/run?task=7"
```

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
- `VERSION_CONTROL_RULES.md` - Timestamp verification system for server deployments
- `KLAVIYO_SUPPORT_MEMO.md` - Consent rules and decision logic

## Version Control

All PHP scripts include a timestamp header on line 2:
```php
// LAST UPDATED: 2025-11-28 16:30:00
```

**Before uploading to server:** Note the timestamp  
**After uploading to server:** Verify line 2 matches your local file

See `VERSION_CONTROL_RULES.md` for complete guidelines.
