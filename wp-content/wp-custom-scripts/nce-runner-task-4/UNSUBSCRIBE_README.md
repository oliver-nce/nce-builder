# Task 7: Bulk Unsubscribe Emails

Unsubscribe email addresses from marketing communications in bulk.

---

## Important: Unsubscribe vs Suppress

| Action | Unsubscribe (Task 7) | Suppress |
|--------|---------------------|----------|
| Marketing emails | ❌ Blocked | ❌ Blocked |
| Transactional emails | ✅ **Still works** | ❌ Blocked (manual only) |
| Reversible | ✅ Yes (re-subscribe) | ⚠️ Only manual suppress |
| Use case | User opts out | Hard block/compliance |
| API | `profile-subscription-bulk-delete-jobs` | `profile-suppression-bulk-create-jobs` |

**This task does UNSUBSCRIBE (not suppress)**, which removes marketing consent but allows transactional emails.

---

## Usage

### Option 1: Email List Parameter (Simplest)
```bash
POST /wp-json/nce-runner/v1/run-task
{
  "task": 7,
  "emails": "user1@example.com,user2@example.com,user3@example.com"
}
```

### Option 2: From Database Table
```bash
POST /wp-json/nce-runner/v1/run-task
{
  "task": 7,
  "job_name": "suppression",
  "source_table": "klaviyo_suppression_list",
  "source_column": "email"
}
```

### Option 3: From Query (wp_klaviyo_globals)
Store query in `wp_klaviyo_globals` table:
```sql
INSERT INTO wp_klaviyo_globals (job_name, api_key, query)
VALUES (
  'suppression',
  'your-api-key',
  'SELECT email FROM wp_klaviyo_suppression_list WHERE status = "unsubscribe"'
);
```

Then run:
```bash
POST /wp-json/nce-runner/v1/run-task
{
  "task": 7,
  "job_name": "suppression"
}
```

---

## Parameters

| Parameter | Required | Default | Description |
|-----------|----------|---------|-------------|
| `task` | Yes | - | Must be `7` |
| `job_name` | No | 'suppression' | Config name in wp_klaviyo_globals |
| `emails` | Conditional | - | Comma-separated email list |
| `source_table` | Conditional | - | Table name (without prefix) |
| `source_column` | Conditional | - | Column containing emails |

**Note**: Must provide ONE of: `emails`, `source_table`+`source_column`, or `query` in globals.

---

## Email Source Priority

1. **`emails` parameter** (if provided)
2. **`source_table` + `source_column`** (if provided)
3. **`query` in wp_klaviyo_globals** (fallback)

---

## What It Does

1. **Reads email list** from one of the sources
2. **Validates emails** (skips invalid ones)
3. **Batches** into groups of 100
4. **Calls Klaviyo API** `profile-subscription-bulk-delete-jobs`
5. **Removes marketing consent** (unsubscribes)
6. **Transactional emails still work**

---

## Response Format

```json
{
  "success": true,
  "message": "Bulk unsubscribe completed",
  "job_name": "suppression",
  "total_found": 500,
  "invalid_emails": 5,
  "valid_emails": 495,
  "unsubscribed": 495,
  "batches_processed": 5,
  "failed_batches": 0,
  "duration_seconds": 4.2
}
```

---

## Example Workflows

### Workflow 1: Process Suppression List from CSV
1. Import CSV to database table `wp_klaviyo_suppression_list`
2. Run Task 7:
```bash
POST {
  "task": 7,
  "source_table": "klaviyo_suppression_list",
  "source_column": "email"
}
```

### Workflow 2: Unsubscribe Specific Users
```bash
POST {
  "task": 7,
  "emails": "user1@example.com,user2@example.com"
}
```

### Workflow 3: Process Unsubscribe Requests Daily
Store query in `wp_klaviyo_globals`:
```sql
SELECT email FROM wp_user_preferences 
WHERE unsubscribe_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
```

Run daily via cron:
```bash
POST {"task": 7, "job_name": "suppression"}
```

---

## Database Setup Example

### Create Suppression Table
```sql
CREATE TABLE wp_klaviyo_suppression_list (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  reason VARCHAR(100),
  added_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  processed TINYINT(1) DEFAULT 0,
  INDEX idx_email (email),
  INDEX idx_processed (processed)
);
```

### Insert Emails
```sql
INSERT INTO wp_klaviyo_suppression_list (email, reason)
VALUES 
  ('user1@example.com', 'Bounced'),
  ('user2@example.com', 'Complained'),
  ('user3@example.com', 'User request');
```

### Run Task 7
```bash
POST {
  "task": 7,
  "source_table": "klaviyo_suppression_list",
  "source_column": "email"
}
```

### Mark as Processed (Optional)
```sql
UPDATE wp_klaviyo_suppression_list 
SET processed = 1, processed_date = NOW()
WHERE email IN (/* list of emails from response */);
```

---

## Important Notes

### 1. This is UNSUBSCRIBE, not SUPPRESS
- **Unsubscribe**: Removes email marketing consent
- **Transactional emails still work** (receipts, password resets, etc.)
- Use this for normal opt-outs

### 2. For Hard Block (Suppress)
If you need to block ALL emails (including transactional), use:
- API: `profile-suppression-bulk-create-jobs` (different endpoint)
- RAG Chunk E pattern
- Only for compliance/legal requirements

### 3. Email Validation
Invalid emails are automatically skipped:
- Must be valid format
- Minimum 3 characters
- Passes `filter_var(FILTER_VALIDATE_EMAIL)`

### 4. Batch Processing
- 100 emails per batch (Klaviyo recommendation)
- 0.5 second delay between batches
- Rate limit: 10/sec burst, 150/min sustained

---

## Logs

Check `/wp-content/wp-custom-scripts/temp_log.log`:

```
[10:00:00] BULK UNSUBSCRIBE EMAILS - Job: suppression
[10:00:00] Reading emails from table: wp_klaviyo_suppression_list
[10:00:01] Found 500 email addresses to unsubscribe
[10:00:01] Skipped 5 invalid email addresses
[10:00:01] 495 valid emails to process
[10:00:01] Processing 5 batch(es) of up to 100 emails each
[10:00:01] Sample emails: user1@ex.com, user2@ex.com, ...
[10:00:02] ✓ Batch 1 submitted successfully (Job ID: abc123)
...
[10:00:05] Emails unsubscribed: 495
```

---

## Troubleshooting

### "No email source specified"
**Solution**: Provide one of:
- `emails` parameter
- `source_table` + `source_column`
- `query` in wp_klaviyo_globals

### "Table does not exist"
**Solution**: Check table name (without `wp_` prefix)
```bash
# Wrong
"source_table": "wp_klaviyo_suppression_list"

# Correct
"source_table": "klaviyo_suppression_list"
```

### "Invalid table or column name"
**Solution**: Only use alphanumeric and underscore characters

### "No valid emails to unsubscribe"
**Solution**: 
- Check email format in source
- Verify column name is correct
- Check for NULL/empty values

---

## Security Notes

✅ **Table/column name validation** (prevents SQL injection)  
✅ **Uses prepared statements** where applicable  
✅ **Email validation** before processing  
✅ **Rate limiting** to respect Klaviyo limits  

---

## When to Use This Task

**Use Task 7 (Unsubscribe) when:**
- ✅ User opts out of marketing emails
- ✅ Processing unsubscribe requests
- ✅ Cleaning bounced/invalid emails
- ✅ Need to allow transactional emails

**Use Suppress (different task) when:**
- Hard block required for compliance
- Legal requirement to block ALL emails
- Permanent removal from ALL communications

---

## Summary

✅ **Bulk unsubscribe** from email marketing  
✅ **Multiple input sources** (param, table, query)  
✅ **Validation** and error handling  
✅ **Batching** for efficiency  
✅ **Transactional emails still work**  
✅ **Reversible** (can re-subscribe later)  

**Endpoint**: `profile-subscription-bulk-delete-jobs`  
**Batch size**: 100 emails  
**Default job_name**: `'suppression'`

