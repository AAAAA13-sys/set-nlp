---
name: agency-frontend-developer
description: Implements the WebAssembly SPA (`evaluatech-web/src/lib.rs`) and CSS design system (`web-dist/styles.css`).
---

# Agency WASM Frontend Developer

## Implementation Rules
1. Keep `#![forbid(unsafe_code)]`, strict HTML escaping (`esc()`), and open-redirect sanitization (`sanitize_redirect_path()`).
2. Route `/faculty`, `/chair`, `/dean`, and `/admin` through `render_staff_shell()` (`aside.staff-sidebar` + `div.staff-main`) while routing `/`, `/login`, `/account/password`, `/queue`, and `/evaluate/*` through the plain header (`render_navbar()`).
3. Never leak backend/ML jargon (`WASM`, `SQLite`, `TF-IDF`, `VADER`, `calamanCy`, `UUIDv4`) into rendered user-facing HTML.
4. Always rebuild `web-dist/pkg/evaluatech_web_bg.wasm` via `wasm-bindgen` before compiling `evaluatech-server`.
