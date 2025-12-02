# Task 7 Quick Start: Backfill SMS Consent

## ⚠️ WARNING: Read This First!

**DO NOT RUN** this task unless ALL users with phone numbers have provided **explicit SMS opt-in consent**. This is a legal requirement under TCPA/CTIA regulations.

---

## Quick Test (Dry Run)

```bash
curl "https://your-site.com/wp-json/nce/v1/run?task=7&dry_run=true"
```

**What it does:** Counts how many profiles have phone numbers (no changes made)

**Example response:**
```json
{
  "success": true,
  "message": "DRY RUN: Found profiles with phone_number (no changes made)",
  "total_found": 850,
  "with_valid_phone": 850,
  "sample_phones": ["+15551234567", "+15559876543"]
}
```

---

## Grant Consent (Live Run)

```bash
curl "https://your-site.com/wp-json/nce/v1/run?task=7"
```

**What it does:** Fetches ALL profiles, filters for valid US phones, grants SMS consent

**Example response:**
```json
{
  "success": true,
  "version": "2.1.0",
  "message": "SMS consent backfill completed - processed ALL profiles",
  "total_fetched": 8500,
  "valid_us_phones": 8000,
  "invalid_filtered": 500,
  "total_granted": 8000,
  "completed": true
}
```

**One run processes ALL profiles!** No need to run multiple times.

---

## US Phone Number Validation

Only **valid US phone numbers** are processed:

✅ **Valid format:** `+1XXXXXXXXXX` (11 digits total)  
❌ **Rejected:** Non-US country codes, invalid formats, missing +1 prefix

**Examples:**
- ✅ `+12125551234` (valid)
- ❌ `+442071234567` (UK number)
- ❌ `(212) 555-1234` (wrong format)
- ❌ `null` or empty (invalid)

---

## Typical Workflow

1. **Test:** `task=7&dry_run=true` → Count valid US phones
2. **Process:** `task=7` → Grant SMS consent to ALL
3. **Repeat:** Keep running `task=7` until `hit_limit: false`
4. **Verify:** `task=7&dry_run=true` → Should show `total_found: 0`

---

## Task 5 vs Task 7

| Task | Purpose | When to Use |
|------|---------|-------------|
| **Task 5** | NEW profiles only (lookback window) | After each bulk upsert, automatic |
| **Task 7** | ALL profiles (backfill) | One-time operation for existing data |

---

## Full Documentation

See `README.md` in this folder for complete details, parameters, safety limits, and troubleshooting.

---

*Last updated: 2025-11-28 16:45:00*

