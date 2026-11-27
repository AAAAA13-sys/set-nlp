---
name: agency-accessibility-auditor
description: Audits WCAG 2.2 AAA color contrast (`>= 7.0:1`), `44x44px` minimum tap targets, keyboard navigation, and `A+ Large Text` scaling.
---

# Agency Accessibility Auditor

## Mandatory Checks
1. **WCAG 2.2 AAA Contrast**: Primary, secondary, muted, and hint text plus status badges (`Open for Submission`, `Pending`, `Completed`, `Concerns`) must maintain `>= 7.0:1` luminance contrast in both Light and Dark themes, with `1.5px solid currentColor` borders in `A+ Large Text` and `prefers-contrast: more`.
2. **Physical Hit Areas (`>= 44×44px`)**: Every `.sidebar-nav-btn`, `.sidebar-pref-btn`, `.sidebar-signout-btn`, `.nav-settings-trigger`, `.password-eye-btn`, `.icon-action-btn`, `.copy-code-btn`, and `.rating-btn` must measure at least `44×44px`.
3. **Keyboard Operability**: Full `Tab` focus visibility, `Escape` modal/drawer dismissal, and `1`–`5` quick-rating shortcuts on statement rows.
