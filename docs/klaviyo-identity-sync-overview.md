# Klaviyo Integration: Three Systems Working Together

## Overview

We use **three systems** to manage Klaviyo profiles:

| System | What It Does |
|--------|--------------|
| **Official Klaviyo Plugin** | Tracks WooCommerce orders + billing info |
| **Our MU-Plugin** | Links anonymous sessions to identified users |
| **Our Task 3** | Pushes WordPress users to Klaviyo with custom data |

---

## 1. Official Klaviyo for WooCommerce Plugin

**When it fires:** Order placed

**What it sends to Klaviyo:**
- Order event (products, totals)
- Billing info (name, address, phone)
- Creates/updates profile with that email

**Limitation:** Before an order, Klaviyo only has anonymous visitor records (cookie-based). No link to WordPress user.

---

## 2. Our MU-Plugin: `klaviyo-identity-sync.php`

**Purpose:** Link anonymous Klaviyo visitors to real WordPress users at login/registration.

**When it fires:**
| WordPress Hook | Trigger |
|----------------|---------|
| `wp_login` | User logs in |
| `user_register` | New user creates account |

**What it sends:**
```php
{ email, external_id }
```

**What it does NOT do:**
- ❌ Does not create profiles
- ❌ Does not send name, address, or custom data

**What it DOES do:**
- ✅ Tells Klaviyo: "This session cookie belongs to this email"
- ✅ Klaviyo merges anonymous activity into the identified profile

**Filters:**
- Only `parent` role users
- Skips users without `last_name` (spam filter)

---

## 3. Our Task 3: `bulk_upsert_profiles.php`

**Purpose:** Push all new/updated WordPress users to Klaviyo — even if they never interact with WooCommerce.

**When it runs:** Scheduled batch job (cron or manual)

**What it sends:**
- Profile attributes from custom SQL view (`wp_klaviyo_profiles_view`)
- Custom fields: `family_id`, `player_ids`, order history, etc.
- Sets new profiles to **Subscribed** (updates don't change subscription)

**Key point:** This runs for ALL WordPress users, not just customers who placed orders.

---

## How They Work Together

```
USER JOURNEY
═══════════════════════════════════════════════════════════════

Step 1: User browses site (not logged in)
        └─→ Klaviyo Plugin: Creates anonymous visitor record

Step 2: User logs in or registers
        └─→ OUR MU-PLUGIN: "This cookie = this email"
            Anonymous activity now linked to email

Step 3: User places order
        └─→ Klaviyo Plugin: Adds billing info + order event
            Profile now has name, address, phone

Step 4: Batch job runs (every few minutes)
        └─→ OUR TASK 3: Adds custom attributes
            Profile now has family_id, player_ids, etc.
```

---

## Comparison

| Feature | Klaviyo Plugin | Our MU-Plugin | Our Task 3 |
|---------|----------------|---------------|------------|
| **Trigger** | Order placed | Login/register | Scheduled |
| **Speed** | Real-time | Real-time | Minutes |
| **Creates profiles** | ✓ (at order) | ✗ | ✓ |
| **Identity linking** | ✗ | ✓ | ✗ |
| **Billing info** | ✓ | ✗ | ✗ |
| **Order events** | ✓ | ✗ | ✗ |
| **Custom attributes** | ✗ | ✗ | ✓ |
| **Sets subscription** | ✗ | ✗ | ✓ (new only) |
| **external_id** | ✗ | ✓ | ✓ |

---

## The Gap Our Plugin Fills

```
WITHOUT our plugin:
────────────────────────────────────────────────────
User browses   → Anonymous Profile A created
User logs in   → [nothing happens]
User orders    → Profile B created with billing
                 Profile A orphaned forever!

WITH our plugin:
────────────────────────────────────────────────────
User browses   → Anonymous Profile A created
User logs in   → OUR PLUGIN: "Profile A = this email"
User orders    → Billing added to Profile A
                 All activity preserved! ✓
```

---

## Limitations

### What We Can Fix

| User Behavior | Activity Attached? |
|---------------|-------------------|
| Browse → Login (same session) | ✅ Yes |
| Browse → Buy (same session) | ✅ Yes |
| Browse → Register (same session) | ✅ Yes |

### What Nobody Can Fix

| User Behavior | Activity Attached? |
|---------------|-------------------|
| Browse → Leave → Never return | ❌ Lost |
| Browse → Return with new cookie | ❌ Lost |
| Browse on Device A → Login on Device B | ❌ Lost |

This is a **platform limitation** (Klaviyo, HubSpot, all marketing platforms). If a user never identifies themselves during that cookie's lifetime, activity cannot be recovered.

---

## Summary

| System | Role |
|--------|------|
| **Klaviyo Plugin** | Tracks orders + sends billing at checkout |
| **Our MU-Plugin** | Links anonymous visitors to users at login |
| **Our Task 3** | Enriches all WordPress users with custom data |

**Together:** Complete profiles with identity resolution and custom attributes.

---

*Last updated: 2025-12-14*
