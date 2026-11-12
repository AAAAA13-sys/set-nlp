---
name: EvaluaTech
description: Campus Wayfinding — every course, department and task is signed like a campus building.
colors:
  slate-sign: "#0f172a"
  slate-sign-2: "#1e293b"
  alice-ground: "#edf2fb"
  panel: "#ffffff"
  panel-2: "#f5f8fe"
  hairline: "#d7e3fc"
  hairline-strong: "#c1d3fe"
  tint: "#e2eafc"
  tint-2: "#ccdbfd"
  periwinkle-mark: "#abc4ff"
  ink: "#0f172a"
  ink-2: "#334155"
  ink-3: "#475569"
  accent-ink: "#22379a"
  accent-ink-hover: "#1a2b7d"
  done: "#166534"
  done-bg: "#e7f6ec"
  warn: "#92400e"
  warn-bg: "#fdf3e1"
  alert: "#991b1b"
  alert-bg: "#fdecec"
typography:
  page-title:
    fontFamily: "Public Sans, system-ui, sans-serif"
    fontSize: "1.75rem"
    fontWeight: 800
    lineHeight: 1.2
    letterSpacing: "-0.015em"
  section-title:
    fontFamily: "Public Sans, system-ui, sans-serif"
    fontSize: "1.25rem"
    fontWeight: 700
    lineHeight: 1.3
    letterSpacing: "-0.01em"
  body:
    fontFamily: "Public Sans, system-ui, sans-serif"
    fontSize: "15px"
    fontWeight: 400
    lineHeight: 1.55
  label:
    fontFamily: "Public Sans, system-ui, sans-serif"
    fontSize: "0.8rem"
    fontWeight: 600
    lineHeight: 1.4
  figure:
    fontFamily: "Public Sans, system-ui, sans-serif"
    fontSize: "1.35rem"
    fontWeight: 700
    lineHeight: 1.25
    fontFeature: "tnum"
  code-plate:
    fontFamily: "Public Sans, system-ui, sans-serif"
    fontSize: "0.8rem"
    fontWeight: 800
    letterSpacing: "0.04em"
rounded:
  plate: "5px"
  control: "8px"
  panel: "10px"
  pill: "999px"
spacing:
  xs: "4px"
  sm: "8px"
  md: "16px"
  lg: "24px"
  xl: "32px"
components:
  button-primary:
    backgroundColor: "{colors.accent-ink}"
    textColor: "{colors.panel}"
    rounded: "{rounded.control}"
    padding: "8px 16px"
    height: "40px"
  button-primary-hover:
    backgroundColor: "{colors.accent-ink-hover}"
  button-secondary:
    backgroundColor: "{colors.panel}"
    textColor: "{colors.ink}"
    rounded: "{rounded.control}"
    padding: "8px 16px"
    height: "40px"
  button-secondary-hover:
    backgroundColor: "{colors.tint}"
  code-plate:
    backgroundColor: "{colors.slate-sign}"
    textColor: "{colors.panel}"
    typography: "{typography.code-plate}"
    rounded: "{rounded.plate}"
    padding: "4px 8px"
  panel:
    backgroundColor: "{colors.panel}"
    rounded: "{rounded.panel}"
    padding: "24px"
  input:
    backgroundColor: "{colors.panel}"
    textColor: "{colors.ink}"
    rounded: "{rounded.control}"
    padding: "8px 12px"
    height: "40px"
  top-bar:
    backgroundColor: "{colors.slate-sign}"
    textColor: "{colors.panel}"
    height: "60px"
---

# Design System: EvaluaTech

## Overview

**Creative north star: Campus Wayfinding.** The app is signed like a campus. Courses and departments carry slate code plates the way rooms and wings carry signs. The student always has a "you are here" marker on the next thing to do. Supervisors read a directory board of departments rather than a wall of tiles.

The system is quiet and institutional, built for a mandatory task done on phones and lab PCs. It uses the Coolors periwinkle family, which is a binding brand commitment, with slate signage and one deep periwinkle ink for action. Expression stays in four places only: the code plate, the "you are here" marker, the directory board, and the single action colour. Everything else is standard, familiar product UI.

Anti-references: identical summary cards over a generic table; big-number KPI tiles; all-caps eyebrow labels above headings; emoji as icons; gradients and glass.

## Colors

- **Slate sign (`#0f172a`)**: the top bar, code plates and step dots. This is the signage colour.
- **Alice ground (`#edf2fb`)**: the page ground on every authenticated screen.
- **Panel (`#ffffff`) with hairline (`#d7e3fc`)**: the one container treatment. Panel-2 (`#f5f8fe`) is used for table headers and hover.
- **Tint (`#e2eafc`)**: selected rows, the "you are here" row, and the progress track.
- **Periwinkle mark (`#abc4ff`)**: the brand mark and the active-nav underline only.
- **Deep periwinkle ink (`#22379a`)**: the accent. It is reserved for the primary action, the "you are here" marker, selected rating answers and progress fill. White on it is 10.4:1.
- **State colours** carry meaning only: done `#166534`, warning `#92400e`, alert `#991b1b`, each with a pale ground for alerts.

Strategy: restrained. Neutrals come from the periwinkle family, one accent does real jobs, and state colours appear only where they mean something.

## Typography

There is one family: **Public Sans**, for headings, body, labels and data. Monospace is used only for receipt codes. Newsreader was retired in the remodel.

- Page title: 1.75rem/800 (1.4rem on phones).
- Section title: 1.25rem/700.
- Body: 15px/1.55.
- Label: 0.8rem/600, sentence case.
- Figure: 1.35rem/700.
- All numbers use tabular figures.

Headings are sentence case and carry their own weight, with no label above them.

## Layout

- **Container:** 1320px max, with 32px side padding (14px on phones).
- **Page pattern:** every workspace opens with a `.page-head`: an h1, a meta line of facts, and an actions group on the right that wraps below on phones.
- **Student screen:** two columns, the course directory and the dominant Next-evaluation panel (sticky). On phones the panel comes first.
- **Supervisors:** page head, then a tabular figures row, then underline tabs, then panels.
- **Breakpoints:** 900px (two columns become one; the top bar becomes a menu) and 640px (phone density; tables become stacked rows where it matters).
- **Overflow:** no page ever scrolls sideways. Wide tables scroll inside `.table-responsive` or `.table-responsive-wrapper`, which are positioned so screen-reader-only labels stay inside.

## Elevation & Depth

The system is flat: hairlines and tonal grounds instead of shadows. The only shadows are on overlays (dialogs, the receipt) and the landing page's product board: `0 24px 48px -12px rgba(15,23,42,.35)` and `0 30px 60px -30px rgba(15,23,42,.35)`.

## Shapes

- Plates: 5px radius.
- Controls: 8px.
- Panels: 10px.
- Chips and pills: fully round.
- Borders are always 1px hairlines; there are no coloured side stripes.

## Components

- **Code plate** (`.code-plate`, `.is-large`): a slate plate with white 800-weight code text. Use it for course and department codes only, never for whole cells or titles.
- **Here marker** (`.here-marker`): an 800-weight deep-ink label with a pin icon, on the next course only.
- **Status mark** (`.status-mark .is-done/.is-todo/.is-warn/.is-alert`): the single state vocabulary.
  - Done is a filled dot, to-do a hollow ring, warning a square, alert a rotated square.
  - It has no pill background.
  - Legacy `.status-badge` variants map onto it.
- **Directory and board** (`.directory`, `.directory-row`, `.board-row`): signed rows of plate, name, figure and progress. Used for the student's courses and the dean's departments.
- **Buttons:** one pair, primary in deep ink and secondary as white with a hairline. A ghost variant is used for quiet row actions. 40px tall, 44px on touch screens.
- **Tabs:** underline tabs, with a 3px deep-ink underline on the active tab.
- **Figures row** (`.metric-overview-grid`): label/value pairs separated by hairlines, not tiles.
- **Alerts:** tinted ground, 1px border, icon first, left-aligned.
- **Rating row:** 1–5 buttons that fill with deep ink when selected. On phones the statement stacks above the buttons and "Strongly disagree / Strongly agree" anchors sit underneath.
- **Icons:** `AppIcon.vue`, a 24px grid with 2px round strokes, decorative unless labelled.
- **Participation panel** (`ParticipationPanel.vue`): shared by the Dean and Chair dashboards. It shows completion status only.

## Do's and Don'ts

- Do keep exactly one strong action per screen. Secondary actions are quiet.
- Do say in plain words that answers are anonymous wherever completion status appears.
- Do humanize backend codes with `humanizeStatus()` (for example, `PENDING_REVIEW` becomes "Pending review").
- Do report ML accuracy measured on real comments only.
- Don't put eyebrow or kicker labels above headings, number labels that aren't a sequence, or use all-caps chips.
- Don't use emoji or typed glyphs (→ ←) as icons. Use `AppIcon`.
- Don't put components inside `<option>`.
- Don't use big-number KPI tiles, gradient text, glass, or coloured side-stripe borders.
- Don't restyle with inline `style` colour, border or font declarations; inline styles are for layout only.
