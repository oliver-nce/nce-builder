# CODE APPROVAL RULE

**CRITICAL PROJECT RULE - ALWAYS FOLLOW**

## Rule: No Code Until User Approves

**NEVER write, edit, or modify ANY code files until the user explicitly approves the proposed changes.**

### Process:
1. **Discuss** the approach with the user
2. **Explain** what changes would be made
3. **Wait** for explicit approval (e.g., "yes", "do it", "go ahead", "implement")
4. **Only then** make the code changes

### What Requires Approval:
- Any code edits (search_replace, write, edit_notebook)
- New file creation
- File deletion
- Any changes to PHP, JavaScript, CSS, etc.

### What Does NOT Require Approval:
- Reading files
- Searching codebase
- Running terminal commands for inspection
- Creating documentation (unless user says otherwise)
- Answering questions

### Examples:

**❌ BAD:**
User: "we need a start_from parameter"
Assistant: *immediately edits code*

**✅ GOOD:**
User: "we need a start_from parameter"
Assistant: "I'll add `start_from` parameter (default 1) and `max_batches` (default 30). This will let you run batches 1-30, then 31-60, etc. Want me to implement?"
User: "yes"
Assistant: *makes changes*

---

**Last Updated:** 2025-11-28
**Status:** ACTIVE - ENFORCE ON ALL CODE CHANGES

