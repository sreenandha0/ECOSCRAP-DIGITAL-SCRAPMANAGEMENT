---

name: ecoscrap-development
description: Develop, debug, review, and safely modify the EcoScrap Smart Scrap Management System. Use when working on PHP, MySQL, User, Scrap Collector, Administrator, pickup workflows, authentication, database queries, UI, bugs, testing, or new features.
----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

# EcoScrap Development Skill

## Purpose

This skill provides development and debugging guidelines for the EcoScrap Smart Scrap Management System.

Use this skill when:

* fixing bugs
* adding or modifying features
* reviewing existing code
* debugging PHP, JavaScript, HTML, or CSS
* working with the MySQL database
* modifying User functionality
* modifying Scrap Collector functionality
* modifying Administrator functionality
* working with pickup requests
* checking authentication or authorization
* testing an existing workflow

---

## Project Overview

EcoScrap is a web-based Smart Scrap Management System.

The system has three main roles:

* User
* Scrap Collector
* Administrator

Always use the term **Scrap Collector** for the collector role.

Do not rename the role to Driver, Agent, Delivery Person, or another term.

---

## Technology Stack

The existing project uses:

* PHP
* MySQL
* HTML5
* CSS3
* JavaScript
* Bootstrap
* WAMP
* Git

Do not introduce a different framework or replace the existing technology stack unless explicitly requested.

---

# Development Workflow

Before modifying any code:

1. Understand the requested task.
2. Search the existing project for related files.
3. Inspect the relevant PHP files.
4. Inspect related HTML, CSS, and JavaScript.
5. Check the database queries involved.
6. Verify the actual database tables and columns when relevant.
7. Check authentication and authorization.
8. Check how IDs and parameters are passed between pages.
9. Check related workflows.
10. Make the smallest necessary change.
11. Review the affected functionality after the change.

Do not immediately rewrite an entire file when a smaller fix is possible.

---

# Source of Truth

The existing EcoScrap codebase is the source of truth.

Never assume that a feature, table, column, status, function, file, or URL exists.

Before using something:

* search the repository
* inspect the existing implementation
* verify the database structure if applicable

Never invent:

* database columns
* table names
* PHP files
* functions
* URLs
* status values
* features
* relationships

If documentation or previous instructions conflict with the actual code, identify the discrepancy instead of silently assuming which one is correct.

---

# User Role

The User functionality may include:

* registration and login
* submitting scrap pickup requests
* entering scrap details
* entering pickup address
* selecting pickup date and time
* tracking pickup requests
* viewing pickup details
* receiving notifications
* rating completed pickups

Before modifying any User feature, verify how it is actually implemented in the repository.

---

# Scrap Collector Role

The Scrap Collector functionality may include:

* login
* profile management
* service area management
* availability management
* viewing assigned pickups
* accepting or rejecting assignments
* processing pickups
* QR-related pickup verification

Verify the existing implementation before making assumptions.

---

# Administrator Role

The Administrator may manage:

* Users
* Scrap Collectors
* pickup requests
* request approval/rejection
* Scrap Collector assignment
* system activity

Verify the actual implementation before modifying Administrator functionality.

---

# Pickup Workflow

When working on pickup functionality, trace the complete workflow.

The expected conceptual workflow is:

User
→ Submit Scrap Request
→ Pending
→ Administrator Review
→ Approved / Rejected
→ Scrap Collector Assignment
→ Scrap Collector Accept / Reject
→ Pickup Processing
→ QR Verification
→ Completed
→ Rating

Important:

Do not assume that every status above exists in the database.

Check the actual database status values and the conditions used by the PHP code.

If the database and application code use inconsistent status values, report the inconsistency before making a major change.

---

# Database Rules

Before changing database-related functionality:

1. Identify the relevant table.
2. Check the actual columns.
3. Inspect existing queries.
4. Check relationships between records.
5. Check how IDs are passed.
6. Check ownership and authorization.

Do not invent columns or tables.

Use prepared statements for database queries where applicable.

Do not rename or remove database columns without checking all code that depends on them.

Do not change the database schema unless the task requires it.

---

# Authentication and Authorization

For every protected page or action:

* verify that the user is logged in
* verify the correct role
* verify ownership of the requested record
* prevent access to another user's records
* prevent unauthorized Scrap Collector access
* prevent unauthorized Administrator access

When a URL contains an ID such as:

`track_status.php?id=123`

do not trust the ID by itself.

Verify that the logged-in user is authorized to access that record.

---

# UI Rules

Preserve the existing EcoScrap interface.

Before changing UI:

1. Inspect the existing CSS.
2. Reuse existing components.
3. Preserve the current design language.
4. Maintain responsive behavior.
5. Avoid changing unrelated pages.

Do not introduce a completely different design unless explicitly requested.

Do not modify unrelated UI while fixing a backend or functionality issue.

---

# Bug-Fixing Procedure

When fixing a bug:

### Step 1 — Understand

Identify exactly what the user expects and what currently happens.

### Step 2 — Locate

Find the relevant files, functions, queries, and pages.

### Step 3 — Trace

Follow the complete flow:

Frontend
→ PHP
→ Database
→ Response
→ UI

when applicable.

### Step 4 — Identify the Root Cause

Do not fix only the visible symptom if the underlying problem is elsewhere.

### Step 5 — Apply the Smallest Fix

Change only what is necessary.

### Step 6 — Check Related Code

Search for other pages or functions using the same data, ID, status, or database field.

### Step 7 — Verify

Check that the original problem is resolved and related functionality still works.

---

# File Modification Rules

Only modify files that are relevant to the requested task.

Do not:

* rewrite unrelated files
* delete working functionality
* create duplicate functionality
* rename files unnecessarily
* replace the existing architecture without justification
* modify unrelated UI
* overwrite unrelated user changes

Before making a large change, explain which files will be affected and why.

---

# Security Rules

When modifying code, check for:

* SQL injection
* unauthorized record access
* missing authentication
* missing authorization
* insecure file uploads
* unsafe user input
* insecure direct object references
* exposed sensitive information
* unsafe session handling
* insecure password handling

Do not weaken existing security controls just to make a feature work.

---

# Testing

After making changes:

1. Check PHP syntax.
2. Check database queries.
3. Check authentication.
4. Check authorization.
5. Check the affected page.
6. Check related pages.
7. Check the relevant user role.
8. Check error handling.
9. Check that unrelated functionality was not changed.

Clearly distinguish between:

* Verified
* Inspected
* Not tested
* Requires manual testing

Never claim that something works if it has not been verified.

---

# Git Safety

Before making large changes:

1. Check the current Git status.
2. Understand existing uncommitted changes.
3. Do not discard existing work.

Do not automatically:

* reset the repository
* revert user changes
* delete branches
* create commits

unless explicitly requested.

---

# Communication

Before a significant modification, provide:

1. What was found.
2. Which files are involved.
3. What needs to change.
4. Why the change is necessary.

After the modification, provide:

1. Files changed.
2. Changes made.
3. Verification performed.
4. Remaining issues, if any.

Keep explanations clear and practical.

---

# Example

If the user says:

"Fix the View Pickup button so it opens the selected pickup."

Follow this process:

1. Locate the page containing the View Pickup button.
2. Inspect how the pickup/activity ID is generated.
3. Inspect the URL or request sent by the button.
4. Locate the target page.
5. Inspect how the target page reads the ID.
6. Check the database query.
7. Verify that the logged-in User owns the pickup.
8. Check related User pickup pages.
9. Make the smallest required fix.
10. Verify that selecting Pickup A opens Pickup A and not another pickup.
11. Check that unauthorized users cannot access the pickup by changing the ID manually.

Do not redesign the page unless requested.

---

# Important Rule

**Inspect first. Modify second. Verify third.**

The existing EcoScrap implementation must always be inspected before making assumptions or changing code.
