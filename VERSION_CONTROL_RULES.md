# Version Control Rules for Klaviyo Scripts

## Purpose
Ensure that the code uploaded to the server matches the latest local version. This prevents running outdated code that may have bugs or missing features.

---

## Rule: Timestamp Comments

**EVERY custom script file MUST start with a timestamp comment on line 2:**

```php
<?php
// LAST UPDATED: YYYY-MM-DD HH:MM:SS
// v{version} - {date}
declare(strict_types=1);
```

### Format Requirements

1. **Line 2** must be: `// LAST UPDATED: YYYY-MM-DD HH:MM:SS`
2. Use 24-hour format (e.g., `16:30:00`, not `4:30 PM`)
3. Use UTC or local timezone consistently (recommend local: America/New_York)
4. Update timestamp whenever **ANY** code change is made

### Example

```php
<?php
// LAST UPDATED: 2025-11-28 16:30:00
// v2.0.0 - 2025-11-28
declare(strict_types=1);

/**
 * Grant Email Marketing Consent for NEW Profiles - Task 4
 */
```

---

## Verification Process

### Before Uploading to Server

1. Check that `LAST UPDATED` timestamp is current
2. Verify version number is incremented if significant changes made
3. Note the timestamp for verification after upload

### After Uploading to Server

1. View the file on the server (via FTP, cPanel, or file manager)
2. Check line 2 for the `LAST UPDATED` timestamp
3. Confirm it matches your local file's timestamp
4. If timestamps don't match → **RE-UPLOAD** the file

### Quick Check via Error Response

When a script runs, the JSON response includes a `timestamp` field. Compare this to when you last updated the code:

```json
{
  "success": true,
  "timestamp": "2025-11-28 16:30:00",
  ...
}
```

If the response timestamp is **before** your local file's `LAST UPDATED` timestamp, the server has the old version.

---

## Files Covered by This Rule

All custom task scripts in:
- `wp-content/wp-custom-scripts/nce-runner-task-*/`

Specifically:
- ✅ `nce-runner-task-3/bulk_upsert_profiles.php`
- ✅ `nce-runner-task-4/grant_email_consent.php`
- ✅ `nce-runner-task-5/grant_sms_consent.php`
- ✅ `nce-runner-task-6/bulk_unsubscribe_emails.php`
- ✅ `nce-runner_task_manager.php` (if modified)

---

## Workflow

### Making Changes

1. **Edit** the file locally
2. **Update** line 2 timestamp to current time
3. **Test** if possible (dry run modes)
4. **Upload** to server
5. **Verify** timestamp on server matches local
6. **Test** on server

### Troubleshooting Version Mismatches

**Symptom:** You fixed a bug locally, but the server still has the error.

**Solution:**
1. Check local file line 2: `// LAST UPDATED: 2025-11-28 16:30:00`
2. Check server file line 2 via FTP/file manager
3. If different → upload failed or wrong file uploaded
4. Re-upload and verify again

---

## Version Number Convention

Use semantic versioning: `vMAJOR.MINOR.PATCH`

- **MAJOR** (v2.0.0): Breaking changes, major refactor
- **MINOR** (v1.1.0): New features, no breaking changes
- **PATCH** (v1.0.1): Bug fixes, minor tweaks

Update both version comment AND timestamp when incrementing version.

---

## Integration with Git

This timestamp system **complements** Git commits but serves a different purpose:

- **Git**: Tracks all changes and history
- **Timestamp**: Quick verification that server = local

Before committing, ensure timestamp is current.

---

## AI Agent Instructions

When modifying any PHP script in `wp-content/wp-custom-scripts/`:

1. ✅ **ALWAYS** update the `LAST UPDATED` timestamp on line 2
2. ✅ Use current local time in `YYYY-MM-DD HH:MM:SS` format
3. ✅ Increment version number if making significant changes
4. ✅ Remind user to verify timestamp after uploading to server

---

## Example: Detecting Outdated Server Code

### Local File (grant_email_consent.php)
```php
<?php
// LAST UPDATED: 2025-11-28 16:30:00
// v2.0.0 - 2025-11-28
```

### Server File (checked via FTP)
```php
<?php
// LAST UPDATED: 2025-11-28 14:00:00
// v1.0.0 - 2025-11-28
```

**Result:** Server is 2.5 hours behind! Re-upload required.

---

## Benefits

✅ **Quick verification** without comparing entire files  
✅ **Prevents running outdated code** with known bugs  
✅ **No complex tooling required** - just check line 2  
✅ **Works across all deployment methods** (FTP, SFTP, cPanel, etc.)  
✅ **Human-readable** timestamps for easy debugging

---

*Last updated: 2025-11-28 16:30:00*

