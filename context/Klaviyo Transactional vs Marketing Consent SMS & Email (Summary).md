# Klaviyo Transactional vs Marketing Consent — SMS & Email (Summary)

## Core Distinction
- **Email** and **SMS** are governed by different consent rules.
- **Email** allows implied consent for transactional messages.
- **SMS does NOT** allow implied consent in most jurisdictions.

---

## Email Consent (Klaviyo)
- Transactional emails (receipts, confirmations, account notices) **do not require marketing opt-in**.
- Marketing emails **require explicit consent**.
- Email suppression / opt-out blocks **marketing only**, not transactional email.
- Transactional vs marketing is determined by **message intent**, not API flag alone.

---

## SMS Consent (Klaviyo)
- **SMS is regulated by carrier + law (TCPA, CTIA, GDPR)**.
- There is **no concept of implied transactional SMS consent**.
- SMS can only be sent if the phone number already has a **lawful basis**.

### Lawful SMS basis includes:
- Explicit SMS opt-in  
- User-initiated interaction expecting SMS (e.g. password reset request)

---

## Transactional SMS Rules
Transactional SMS is allowed **only if ALL conditions are true**:
1. Phone number was lawfully obtained for SMS
2. Message is strictly operational (no marketing intent)
3. Message is flagged/sent as transactional
4. Recipient is not STOP-opted or carrier-suppressed

If a profile has replied **STOP**:
- **No SMS can ever be sent**, transactional or marketing.

---

## What Transactional SMS Does NOT Bypass
- Lack of SMS opt-in
- Carrier-level suppression
- STOP/unsubscribe
- Regional legal restrictions

---

## Klaviyo Data Model Notes
- Klaviyo does **not** store a separate “transactional SMS consent” flag.
- SMS eligibility is evaluated **at send time** using:
  - Consent state
  - Phone status
  - Jurisdiction
  - Message classification

---

## Engineering Guidance (Safe Practice)
- ✅ Do NOT auto-enable SMS consent via API
- ✅ Send transactional email when SMS consent is missing
- ✅ Use explicit SMS opt-in at capture time
- ❌ Do not send SMS “because it’s transactional”

---

## One-Sentence Truth
**Transactional SMS is not implied consent — SMS must already be lawful for that recipient.**