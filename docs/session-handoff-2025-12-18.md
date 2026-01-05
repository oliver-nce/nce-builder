# Session Handoff - 2025-12-18

## Summary

This session focused on two main areas:
1. **Payment Plans Viewer** - New mu-plugin with `[nce_payment_plans]` shortcode
2. **Dashboard Improvements** - Styling, API key management, iframe isolation

---

## Changes Made

### 1. Payment Plans Viewer (NEW)

**File:** `wp-content/mu-plugins/nce-payment-plans-viewer.php`

**Shortcode:** `[nce_payment_plans]`

**Features:**
- Displays payment plans from `wp_ppn_payments_flat` view
- Grid-based layout with column headers
- Expandable plan details showing individual installments
- Customer name/email search filter
- "To Pay" calculation: Total - Paid Amount

**To Pay Logic:**
```php
// Paid if: Immediate (interval_type = 'default') OR has paid_date
foreach ($installments as $i => $inst) {
    $interval_type = $inst['interval_type'] ?? '';
    $pmt = isset($payments[$i]) ? $payments[$i] : null;
    
    if ($interval_type === 'default' || ($pmt && !empty($pmt['paid_date']))) {
        $total_paid += floatval($inst['amount'] ?? 0);
    }
}
$to_pay = $total - $total_paid;
```

**Styling:**
- NCE brand colors (citron, charcoal, graphite)
- Soft pastel badges (pink for outstanding, green for paid)
- Right-aligned monospace amounts

### 2. Dashboard Improvements

**File:** `wp-content/wp-custom-scripts/Job Runner/dashboard.php`

**Changes:**
- NCE brand color palette applied
- Removed emojis for professional look
- API key editable in dashboard (saves to tasks-config.json)
- "Run" button for each task
- Shift+click for recursive JSON expand/collapse
- Performance optimizations for large JSON

**File:** `wp-content/plugins/nce-runner/nce-runner.php`

**Changes:**
- Dashboard served via REST endpoint `/wp-json/nce/v1/dashboard`
- Shortcode `[nce_job_runner]` embeds via iframe (prevents plugin interference)
- API key loaded from tasks-config.json on REST API calls
- Detailed error reporting if API key missing

### 3. Documentation

**Updated:**
- `README.md` - Complete rewrite with current architecture
- `docs/AI-AGENT-ONBOARDING.md` - Updated developer guide

---

## Current Architecture

### Task Execution Flow

```
[WP Crontrol] → klaviyo_chained_sync_cron()
                    ↓
              Acquire lock (transient)
                    ↓
              Load tasks-config.json
                    ↓
              klaviyo_run_task_at_index(0)
                    ↓
              Execute task → Log result
                    ↓
              Schedule next task in {pause} seconds
                    ↓
              (repeat until all tasks done)
                    ↓
              Release lock
```

### API Key Flow

```
tasks-config.json
      ↓
cron-handler.php OR nce-runner.php
      ↓
$NCE_KLAVIYO_API_KEY (global)
      ↓
All task files use global
```

### Dashboard Flow

```
[nce_job_runner] shortcode
      ↓
Iframe → /wp-json/nce/v1/dashboard
      ↓
dashboard.php (isolated from other plugins)
      ↓
REST API calls to run tasks, save config
```

---

## Files Changed This Session

| File | Change |
|------|--------|
| `wp-content/mu-plugins/nce-payment-plans-viewer.php` | **NEW** - Payment plans shortcode |
| `wp-content/wp-custom-scripts/Job Runner/dashboard.php` | Styling, API key field |
| `wp-content/plugins/nce-runner/nce-runner.php` | Iframe, API key loading |
| `README.md` | Complete rewrite |
| `docs/AI-AGENT-ONBOARDING.md` | Complete rewrite |

---

## Testing Notes

### Payment Plans Viewer
- Deploy `nce-payment-plans-viewer.php` to `mu-plugins/`
- Add `[nce_payment_plans]` to any WordPress page
- Verify "To Pay" shows $0 for fully paid plans
- Verify Immediate payments count as paid

### Dashboard
- Access via `[nce_job_runner]` shortcode page
- Verify API key field shows current value
- Test "Run" button for a task
- Verify JSON viewer expands/collapses

---

## Known Issues

1. **Profile sync is slow** - ~1.2 seconds per profile due to 4 API calls
2. **Cron timing** - Allow 15-30 second pauses between dependent tasks

---

## Next Steps (Suggested)

1. Monitor cron execution via log files
2. Consider batch optimization for profile sync (if Klaviyo provides guidance)
3. Add more payment plan filters (by date, status)

