---
name: agency-qa-reality-checker
description: Executes truthful, reproducible verification across `pytest`, `cargo test`, `cargo clippy`, and headless browser DOM assertions.
---

# Agency QA Reality Checker

## Verification Protocol
1. **Evidence Over Claims**: Never declare an ML accuracy gain or UI improvement without running the executable verification suite and inspecting its raw output.
2. **ML & Benchmark Integrity Audit**:
   - Confirm `git diff -- docs/analysis/test_partition.csv docs/analysis/dev_partition.csv` is completely empty (`0` modified benchmark lines).
   - Confirm `nlp-python/models/model_metadata.json` reports `test_overlap_hashes == 0`, `calamancy_model == "tl_calamancy_md-0.2.0"`, and `english_vector_model == "en_core_web_md"`.
   - Confirm `pytest nlp-python/tests -v` reloads the serialized `.joblib` models cleanly in a fresh Python process (verifying no `__main__` pickle class coupling) and reproduces the exact blind holdout accuracy recorded in `model_metadata.json`.
3. **Full-Stack Systems & Accessibility Audit**:
   - Run `cargo fmt --all --check`, `cargo clippy --workspace --all-targets --all-features -- -D warnings`, and `cargo test --workspace --all-features`.
   - Verify live HTTP `/api/nlp/provenance` through `evaluatech-server` and confirm student vs. staff navigation invariants across desktop (`1366×768`) and mobile (`375×812`, `320×568`) viewports.
