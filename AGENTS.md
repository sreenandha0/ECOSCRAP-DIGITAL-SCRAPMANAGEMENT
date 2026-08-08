# AGENTS.md

# EcoScrap – AI Development Guide

## IMPORTANT

Before making ANY changes to this project, follow these rules.

1. Read this AGENTS.md completely.
2. Analyze the existing project before making modifications.
3. Study `index.css` and treat it as the project's design system.
4. Preserve all PHP backend logic.
5. Use Anime.js v4.5.0 only.
6. Never redesign pages using a different visual language.
7. Maintain consistency across the entire application.

---

# Project Overview

Project Name:
EcoScrap – Smart Scrap Management System

Purpose:
EcoScrap is a modern web application that connects users with scrap collectors to schedule scrap pickups, track requests, verify collections using QR codes, and manage the complete recycling workflow.

The application should feel like a premium SaaS product while keeping all existing business functionality.

---

# Technology Stack

Backend
- PHP
- MySQL

Frontend
- HTML5
- CSS3
- Bootstrap 5
- JavaScript

Animation
- Anime.js v4.5.0

Development Environment
- WAMP Server

---

# Project Structure

```
ecoscrap/

├── admin/
│   ├── dashboard.php
│   ├── manage.php
│   ├── manageuser.php
│   ├── assign_collector.php
│   ├── approve_collectors.php
│   ├── reports.php
│   ├── profile.php
│
├── user/
│   ├── dashboard.php
│   ├── create_request.php
│   ├── history.php
│   ├── track_status.php
│   ├── profile.php
│   ├── qr.php
│
├── scrapcollector/
│   ├── dashboard.php
│   ├── assigned_requests.php
│   ├── verify_qr.php
│   ├── completed.php
│   ├── profile.php
│
├── assets/
│   ├── css/
│   │   └── index.css
│   ├── js/
│   └── images/
│
└── AGENTS.md
```

---

# Design Source of Truth

The landing page and `index.css` are the ONLY design reference.

Before modifying any page:

- Analyze `index.css`.
- Understand all design tokens.
- Reuse existing CSS.
- Match the landing page exactly.

Do NOT invent another design language.

---

# Design Language

Follow the same:

## Colors

Reuse existing color palette.

Never introduce random colors.

---

## Typography

Reuse existing fonts.

Maintain the same typography hierarchy.

---

## Layout

Reuse spacing.

Reuse containers.

Reuse section spacing.

Reuse grid layouts.

---

## Components

Reuse:

Cards

Buttons

Forms

Inputs

Dropdowns

Navigation

Tables

Badges

Alerts

Modals

Search bars

Toolbars

Statistics cards

Profile cards

Timeline

Empty states

Pagination

---

## Visual Style

Follow the existing:

- Glassmorphism
- Shadows
- Blur
- Border Radius
- Gradients
- Hover Effects
- Focus States
- Active States
- Responsive Behaviour

---

# UI Inspiration

The project should feel similar to:

- Linear
- Stripe
- Vercel
- Notion

WITHOUT copying them.

The existing landing page remains the primary reference.

---

# Responsive Design

Support:

Desktop

Laptop

Tablet

Mobile

No horizontal scrolling.

---

# Accessibility

Use:

Semantic HTML

Keyboard navigation

Visible focus states

Proper labels

Readable contrast

Accessible buttons

---

# Anime.js

Use ONLY Anime.js v4.5.0.

Never use deprecated syntax.

Study the uploaded Anime.js documentation before creating animations.

Use animations for:

Page entrance

Section reveal

Statistics cards

History cards

Dashboard cards

Hover interactions

Buttons

Forms

Modal open

Modal close

Sidebar

Loading

Success

Error

Notification

Timeline

QR verification

Micro interactions

Keep animations:

Fast

Smooth

Professional

Subtle

Never distracting.

---

# PHP Rules

NEVER modify:

Business Logic

Authentication

Sessions

Database queries

Database structure

SQL

Form processing

Security

Validation

Role permissions

Unless explicitly requested.

---

# HTML Rules

Improve:

Structure

Semantics

Accessibility

Responsiveness

Consistency

---

# CSS Rules

Reuse existing classes whenever possible.

Only create new CSS when absolutely necessary.

Any new CSS MUST match index.css.

Never duplicate styles unnecessarily.

---

# JavaScript Rules

Write clean modular code.

Prefer reusable functions.

Avoid inline JavaScript.

Use Anime.js for UI animation.

---

# Code Quality

Write:

Clean code

Readable code

Maintainable code

Reusable code

Well-commented code

Consistent naming

---

# User Module

Pages:

dashboard.php

create_request.php

history.php

track_status.php

profile.php

qr.php

Design Goals:

Modern dashboard

Beautiful request form

Premium history cards

Timeline status

Responsive profile

Animated QR screen

---

# Admin Module

Pages:

dashboard.php

manage.php

manageuser.php

assign_collector.php

approve_collectors.php

reports.php

profile.php

Design Goals:

Analytics

Management tables

Modern statistics

Collector assignment workflow

Reports

Charts

Responsive admin interface

---

# Collector Module

Pages:

dashboard.php

assigned_requests.php

verify_qr.php

completed.php

profile.php

Design Goals:

Assigned pickup cards

QR verification

Collection workflow

Completed pickups

Collector profile

---

# General UI Goals

Every page should include:

Premium header

Statistics

Modern cards

Beautiful forms

Consistent buttons

Search

Filters

Responsive layout

Hover states

Loading states

Empty states

Smooth animations

---

# Workflow

Whenever redesigning a page:

1. Analyze the existing page.

2. Preserve backend functionality.

3. Preserve PHP.

4. Preserve SQL.

5. Match index.css.

6. Improve HTML.

7. Improve CSS.

8. Add Anime.js animations.

9. Test responsiveness.

10. Keep consistency with the entire project.

---

# Final Rule

If there is a conflict between creativity and consistency:

Always choose consistency.

The application should look like it was designed by one designer, not multiple different designers.

The landing page and index.css are always the source of truth.