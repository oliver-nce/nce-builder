# Form Rendering System - Test Checklist

## Overview
This checklist covers testing of the NCE Builder's grid-based form rendering system with edit locking and record management.

## Prerequisites
- [ ] NCE Builder app installed on Frappe site
- [ ] At least one DocType available for testing (e.g., Customer, Lead, or custom DocType)
- [ ] User with appropriate permissions to create/edit records

## Test Areas

### 1. Form Builder
- [ ] Create a new form from `/nce/forms` page
- [ ] Set form title and target DocType
- [ ] Drag and drop field elements onto canvas
- [ ] Drag and drop caption elements onto canvas
- [ ] Resize elements by dragging corners
- [ ] Move elements by dragging edges
- [ ] Right-click on field element to bind data using PathFinder
- [ ] Configure element properties (label, placeholder, editable flag)
- [ ] Use Preview button to test with sample data
- [ ] Save form successfully
- [ ] Verify "Open Live Form" link appears after saving

### 2. Form List Page (`/nce/forms`)
- [ ] View list of all created forms
- [ ] See form title, DocType, and enabled status
- [ ] "Edit" button opens form in builder
- [ ] "Use" button opens form for data entry
- [ ] "+ New Form" button creates new form

### 3. Form Rendering - New Records
- [ ] Open form via "Use" button from form list
- [ ] Record selector shows if no record specified
- [ ] "+ Create New [DocType]" button works
- [ ] Form displays in grid layout matching builder design
- [ ] All field elements render as appropriate input types:
  - [ ] Text fields (Data, Small Text)
  - [ ] Number fields (Int, Float, Currency)
  - [ ] Date fields
  - [ ] Checkboxes
  - [ ] Text areas (Long Text, Text Editor)
- [ ] Caption elements display correctly
- [ ] Read-only fields cannot be edited
- [ ] Editable fields accept input
- [ ] Save button creates new record
- [ ] After save, redirects to edit mode with record name

### 4. Form Rendering - Existing Records
- [ ] Record selector shows list of existing records
- [ ] Records display with name and modification time
- [ ] Title field shown if DocType has one
- [ ] Clicking record opens it for editing
- [ ] Form loads with existing data populated
- [ ] All field values display correctly
- [ ] Linked field data resolves through chains

### 5. Edit Locking System
- [ ] When user A opens a record, lock is acquired
- [ ] When user B tries to open same record:
  - [ ] Warning shows that user A is editing
  - [ ] Option to view in read-only mode
  - [ ] Read-only mode prevents editing
- [ ] Lock expires after 15 minutes of inactivity
- [ ] Lock released when user navigates away
- [ ] Same user can re-acquire their own lock

### 6. Data Saving
- [ ] Changes to direct fields save correctly
- [ ] Changes to linked fields (through chains) save to correct DocType
- [ ] Optimistic locking detects concurrent modifications
- [ ] Conflict warning shown if record modified by another user
- [ ] Save confirmation message appears
- [ ] Modified timestamp updates after save

### 7. Navigation & User Experience
- [ ] Back button returns to previous page
- [ ] Form title displays correctly
- [ ] "Editing: [record name]" shown for existing records
- [ ] "New [DocType]" shown for new records
- [ ] Loading states display during data fetch
- [ ] Error messages shown for failed operations

### 8. Grid Renderer Features
- [ ] Grid layout matches builder configuration
- [ ] Cell size and gap settings applied correctly
- [ ] Elements span correct grid cells
- [ ] Responsive behavior on window resize
- [ ] Frame colors applied if configured
- [ ] Form scrolls if content exceeds viewport

## Common Test Scenarios

### Scenario 1: Simple Contact Form
1. Create form targeting "Contact" DocType
2. Add fields: first_name, last_name, email, phone
3. Add caption: "Contact Information"
4. Save and test with new/existing contacts

### Scenario 2: Linked Data Form
1. Create form targeting "Quotation" DocType
2. Add fields from quotation
3. Add linked fields from customer (via party_name)
4. Verify customer fields resolve correctly
5. Test editing both direct and linked fields

### Scenario 3: Multi-User Editing
1. User A opens a Customer record
2. User B attempts to open same record
3. Verify lock warning appears
4. User B views in read-only mode
5. User A saves changes
6. User B refreshes to see updates

### Scenario 4: Complex Grid Layout
1. Create form with 24-column grid
2. Place small fields (2-3 columns wide)
3. Place large text areas (8-12 columns wide)
4. Mix captions and fields
5. Test at different screen sizes

## Performance Checks
- [ ] Form loads within 2 seconds for simple forms
- [ ] Preview mode loads sample data quickly
- [ ] Field resolution completes within 1 second
- [ ] Save operations complete within 2 seconds
- [ ] No console errors during normal operation

## Browser Compatibility
Test on:
- [ ] Chrome/Chromium
- [ ] Firefox
- [ ] Safari
- [ ] Edge

## Known Limitations
- Edit locks require manual release or timeout
- Linked field editing only supports single-hop links in UI
- No validation rules configuration yet
- No conditional field visibility yet
- No formula fields yet

## Notes for Testers
- Clear browser cache if styles don't update
- Check browser console for any errors
- Test with different user roles/permissions
- Try edge cases like very long field values
- Test with poor network connection

---

**Last Updated:** Form rendering system with GridFormRenderer component and edit locking
**Version:** Phase 3A + Grid Rendering Implementation