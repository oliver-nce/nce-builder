# Klaviyo API – AI PHP Coding Project  
## Context Document Index

This index defines the authoritative structure of the RAG context used for an AI agent generating PHP code against the Klaviyo APIs.

Scope is intentionally limited to:
- Profile creation and updates
- Consent, opt-in, opt-out, suppression, and unsuppression
- Correct handling of transactional vs marketing email/SMS
- Explicit exclusion of list-based “subscriptions” as a primary concern

---

## 1. Core API & Profile Handling

- Klaviyo_API_Overview.md
- Klaviyo_Profile_Object.md
- Klaviyo_Profile_Create_and_Update.md
- Klaviyo_Profile_Bulk_Import_Limits.md

---

## 2. Consent, Suppression & Messaging Rules

- Klaviyo_Consent_Overview.md
- Klaviyo_Email_Consent.md
- Klaviyo_SMS_Consent.md
- Klaviyo_Suppression_vs_Unsubscribe.md
- Klaviyo_Transactional_vs_Marketing_Behavior.md

---

## 3. RAG Control & Decision Chunks  
*(Authoritative reasoning and control logic for the agent)*

- RAG_Chunk_A_Core_Rules.md
- RAG_Chunk_B_Headers.md
- RAG_Chunk_C_Subscribe.md
- RAG_Chunk_D_Unsubscribe.md
- RAG_Chunk_E_Suppress.md
- RAG_Chunk_F_Unsuppress.md
- RAG_Chunk_G_Decision_Table.md
- Klaviyo Transactional vs Marketing Consent SMS & Email (Summary).md

---

## 4. PHP Implementation References

- PHP_Klaviyo_Auth_and_Headers.md
- PHP_Profile_Create_Update_Examples.md
- PHP_Consent_and_Suppression_Examples.md
- PHP_Error_Handling_and_Retry.md

---

## Notes

- Bulk profile import endpoints **do NOT modify consent, subscription, or suppression state**.
- Transactional email and SMS are permitted regardless of marketing consent.
- Marketing email/SMS require explicit profile-level consent.
- Lists (“subscriptions”) are not the authoritative consent model and are secondary to profile settings.
- Code blocks are duplicated across chunks where necessary to prevent cross-chunk splitting.

End of index.