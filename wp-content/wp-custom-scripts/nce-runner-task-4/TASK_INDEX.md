# Task 4 Folder: Consent & Subscription Management

This folder contains scripts for managing Klaviyo profile consent and subscriptions.

---

## Available Tasks

### Task 5: Grant Email Consent (NEW Profiles)
**File**: `grant_email_consent.php`  
**Purpose**: Grant email marketing consent to recently created profiles  
**Lookback**: Default 48 hours (configurable)  

```bash
POST {"task": 5, "job_name": "profiles", "lookback_hours": 48}
```

**Use case**: Run after bulk profile upsert (Task 3) to grant consent to new signups

---

### Task 6: Grant SMS Consent (NEW Profiles)
**File**: `grant_sms_consent.php`  
**Purpose**: Grant SMS marketing consent to recently created profiles  
**Lookback**: Default 48 hours (configurable)  
⚠️ **Requires explicit SMS opt-in checkbox**

```bash
POST {"task": 6, "job_name": "profiles", "lookback_hours": 48}
```

**Use case**: Run after Task 5 if you have explicit SMS opt-in on website

---

### Task 7: Bulk Unsubscribe Emails
**File**: `bulk_unsubscribe_emails.php`  
**Purpose**: Unsubscribe email addresses from marketing (suppression list)  
**Sources**: Parameter, database table, or query  

```bash
# From parameter
POST {"task": 7, "emails": "user1@ex.com,user2@ex.com"}

# From database table
POST {"task": 7, "source_table": "suppression_list", "source_column": "email"}

# From query in globals
POST {"task": 7, "job_name": "suppression"}
```

**Use case**: Process unsubscribe requests, bounced emails, or suppression lists

---

## Quick Comparison

| Task | Action | Targets | Time Filter | Input |
|------|--------|---------|-------------|-------|
| 5 | Grant email consent | NEW profiles | Lookback window | Database query |
| 6 | Grant SMS consent | NEW profiles | Lookback window | Database query |
| 7 | Unsubscribe | Any profiles | None | Email list |

---

## Typical Workflow

### For New Signups
```bash
# 1. Create/update profiles (Task 3)
POST {"task": 3, "job_name": "profiles"}

# 2. Grant email consent to new profiles
POST {"task": 5, "job_name": "profiles", "lookback_hours": 48}

# 3. Grant SMS consent (if explicit opt-in exists)
POST {"task": 6, "job_name": "profiles", "lookback_hours": 48}
```

### For Suppression List
```bash
# Unsubscribe bounced/opted-out users
POST {"task": 7, "source_table": "suppression_list", "source_column": "email"}
```

---

## Documentation Files

- **`README.md`** - Full docs for Tasks 5 & 6 (consent grant)
- **`QUICK_START.md`** - Quick reference for Tasks 5 & 6
- **`IMPLEMENTATION_SUMMARY.md`** - Implementation details for Tasks 5 & 6
- **`UNSUBSCRIBE_README.md`** - Full docs for Task 7 (unsubscribe)
- **`TASK_INDEX.md`** - This file

---

## Key Concepts

### Consent vs Suppression vs Unsubscribe

| Term | Meaning | Marketing Email | Transactional Email | API |
|------|---------|----------------|-------------------|-----|
| **Consent** | Permission to send marketing | ✅ Allowed | ✅ Allowed | subscribe-jobs |
| **Unsubscribe** | Opt-out from marketing | ❌ Blocked | ✅ **Still works** | subscription-delete-jobs |
| **Suppress** | Hard block | ❌ Blocked | ❌ **Also blocked** | suppression-jobs |

### NEW Profiles (Tasks 5 & 6)
- Use **lookback window** to find recently created profiles
- Default: 48 hours
- Requires timestamp field: `created_at`, `created`, or `inserted_at`
- Efficient: doesn't reprocess entire database

### Email List (Task 7)
- Reads from multiple sources (parameter, table, query)
- No time filtering
- Validates emails automatically
- Can process any list size

---

## Configuration

All tasks read from `wp_klaviyo_globals` table:

```sql
SELECT * FROM wp_klaviyo_globals WHERE job_name = 'profiles'
```

Required fields:
- `api_key` - Klaviyo private API key
- `api_version` - API revision (e.g., '2025-10-15')
- `query` - SQL query (Tasks 5 & 6 only)

---

## Common Parameters

### Tasks 5 & 6 (Consent Grant)
```json
{
  "task": 5 or 6,
  "job_name": "profiles",
  "lookback_hours": 48
}
```

### Task 7 (Unsubscribe)
```json
{
  "task": 7,
  "job_name": "suppression",
  "emails": "comma,separated,list",           // Option 1
  "source_table": "table_name",               // Option 2a
  "source_column": "column_name"              // Option 2b
}
```

---

## Rate Limits

All tasks respect Klaviyo rate limits:
- **Batch size**: 100 profiles/emails per request
- **Delay**: 0.5 seconds between batches
- **API limits**: 10/sec burst, 150/min sustained

---

## Logs

All tasks write to:
```
/wp-content/wp-custom-scripts/temp_log.log
```

Each task overwrites the log file, so save between runs if needed.

---

## SMS Legal Compliance (Task 6)

⚠️ **CRITICAL**: Task 6 requires explicit SMS opt-in

**DO NOT run Task 6 unless:**
- ✅ Website has separate SMS checkbox
- ✅ Clear consent language
- ✅ NOT pre-checked
- ✅ User actively checks it

**T&C text alone is NOT sufficient.**

Penalties: $500-$1,500 per message (TCPA)

---

## Task Registration

From `nce-runner_task_manager.php`:

```php
5 => [
    'folder'   => 'nce-runner-task-4',
    'file'     => 'grant_email_consent.php',
    'function' => 'nce_task_grant_email_consent',
    'description' => 'Grant email consent for NEW profiles (lookback window)'
],
6 => [
    'folder'   => 'nce-runner-task-4',
    'file'     => 'grant_sms_consent.php',
    'function' => 'nce_task_grant_sms_consent',
    'description' => 'Grant SMS consent for NEW profiles (requires explicit opt-in)'
],
7 => [
    'folder'   => 'nce-runner-task-4',
    'file'     => 'bulk_unsubscribe_emails.php',
    'function' => 'nce_task_bulk_unsubscribe_emails',
    'description' => 'Bulk unsubscribe emails from marketing (suppression list)'
],
```

---

## Summary

✅ **Task 5**: Email consent for NEW profiles (lookback)  
✅ **Task 6**: SMS consent for NEW profiles (lookback + explicit opt-in)  
✅ **Task 7**: Unsubscribe any emails (suppression list)  

All tasks are production-ready with error handling, logging, and rate limiting.

