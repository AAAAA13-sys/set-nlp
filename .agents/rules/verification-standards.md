# Rule: Multi-Tier Verification Standards

No change may be committed or pushed to `main` until all five verification gates succeed cleanly:

1. **Python ML & `calamanCy` Verification**:
   - `.\.venv\Scripts\python.exe nlp-python\train_model.py` completes with `0` test-set overlap violations and writes updated `.joblib` artifacts + `model_metadata.json` containing unaltered holdout metrics.
   - `.\.venv\Scripts\python.exe -m pytest nlp-python\tests -v` passes `100%`.

2. **WebAssembly Bundle Compilation**:
   - `cargo build -p evaluatech-web --target wasm32-unknown-unknown --release` + `wasm-bindgen` updates `web-dist/pkg/evaluatech_web_bg.wasm` prior to compiling `evaluatech-server`.

3. **Zero-Unsafe Rust Formatting, Lint & Integration Suite**:
   - `cargo fmt --all --check`
   - `cargo clippy --workspace --all-targets --all-features -- -D warnings` (`0` warnings)
   - `cargo test --workspace --all-features` (`100%` pass, including live Rust-to-Python NLP submission tests)

4. **Headless Browser DOM & Responsive Verification**:
   - Live server check confirming plain Student layout, persistent Staff Left Sidebar across `faculty` / `chair` / `dean` / `admin`, zero global `Scale` link, `>= 44px` tap targets, and `0px` horizontal overflow at `375px` and `320px`.

5. **Git Attribution & Hygiene Check**:
   - `git diff --cached --check` (`0` whitespace/BOM errors)
   - Commit author & committer strictly `AAAAA13-sys <108907185+AAAAA13-sys@users.noreply.github.com>` with zero AI trailers.
