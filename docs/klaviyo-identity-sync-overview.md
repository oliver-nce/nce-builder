# Klaviyo Identity Sync - Overview

## The Problem

When a visitor browses your site without logging in, Klaviyo creates an **anonymous profile** (just a cookie, no email). 

If that same person later places an order or logs in, Klaviyo might create a **second profile** with their email — losing all their previous browsing history.

**Result:** Fragmented customer data, incomplete analytics, broken personalization.

---

## Our Solution

We built a lightweight WordPress plugin that fires **the moment a user logs in or registers**.

It immediately tells Klaviyo:
> "This browser cookie belongs to this email address."

Klaviyo then **merges** the anonymous browsing history into the identified profile.

---

## What It Does

| Trigger | Action |
|---------|--------|
| User logs in | Link cookie → email |
| User registers | Link cookie → email |

---

## What It Doesn't Do

- ❌ Doesn't duplicate what WooCommerce/Klaviyo already does
- ❌ Doesn't slow down page loads (5ms timeout)
- ❌ Doesn't affect orders or checkout

---

## Business Value

| Before | After |
|--------|-------|
| Anonymous browsing lost | Browsing history preserved |
| Duplicate profiles | Single customer view |
| Incomplete journey data | Full customer journey |
| Broken abandoned cart flows | Accurate targeting |

---

## Safety Features

- **Spam filter:** Only syncs users with complete profiles (has last name)
- **Role filter:** Only syncs "parent" accounts (not admins, coaches, etc.)
- **Idempotent:** Safe to run multiple times, won't create duplicates

---

## How It Fits With Other Systems

```
┌─────────────────────────────────────────────────────────┐
│                    KLAVIYO PROFILE                      │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  ┌──────────────┐   ┌──────────────┐   ┌─────────────┐ │
│  │ Our Plugin   │ + │ WooCommerce  │ + │  Task 3     │ │
│  │              │   │  + Klaviyo   │   │             │ │
│  │ Identity     │   │  Orders      │   │ Custom Data │ │
│  │ (login/reg)  │   │  Billing     │   │ Enrichment  │ │
│  └──────────────┘   └──────────────┘   └─────────────┘ │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

---

## Limitations

### What We Can Fix

| User Behavior | Activity Attached? |
|---------------|-------------------|
| Browse → Login (same session) | ✅ Yes |
| Browse → Buy (same session) | ✅ Yes |
| Browse → Register (same session) | ✅ Yes |

### What We Cannot Fix

| User Behavior | Activity Attached? |
|---------------|-------------------|
| Browse → Leave → Never return | ❌ Lost forever |
| Browse → Return with new cookie → Login | ❌ Old activity lost |
| Browse on Device A → Login on Device B | ❌ Device A activity lost |

### Why?

This is a **Klaviyo platform limitation**, not our plugin.

Klaviyo links profiles via:
1. **Cookie** (expires, clears with browser)
2. **Email** (permanent)

If a user never provides their email during that cookie's lifetime, the activity cannot be recovered. This is true for all marketing platforms (Klaviyo, HubSpot, Marketo, etc.).

---

## Summary

**What we guarantee:** When a user logs in or registers, we immediately link their browsing history to their profile.

**What nobody can fix:** If a user browses anonymously and never returns (or returns with a different browser/device), that anonymous activity cannot be recovered.

---

## One Sentence

**We ensure every customer has one complete profile in Klaviyo, with their full browsing and purchase history attached — as long as they log in or register during the same browser session.**

