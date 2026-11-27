# Research Department Report: 5-Corpus External NLP Dataset Ingestion & Domain Adaptation

**Authored By:** School AAAAA13 EvaluaTech NLP Research Department (`agency-nlp-research-dept`)
**Ingest Pipeline:** [`nlp-python/import_external_datasets.py`](../../nlp-python/import_external_datasets.py)
**Curated Output:** [`nlp-python/data/external_nlp_datasets.csv`](../../nlp-python/data/external_nlp_datasets.csv) (`2,363` rows)
**Audit Manifest:** [`nlp-python/data/external_datasets_manifest.json`](../../nlp-python/data/external_datasets_manifest.json)

---

## 1. Imported Public NLP Datasets & Provenance

To strengthen out-of-vocabulary lexical coverage across collegiate English, Tagalog, and Taglish without synthetic template artifacts, the Research Department audited public Hugging Face repositories and ingested **five authentic, human-written corpora** while excluding LLM-generated or unlabeled sequence-tagging datasets (`Philseok/student-feedback`, `tyqiangz/multilingual-sentiments`, `NLPC-UOM` BIO-only TSVs):

| Tier | Hugging Face Repository | License | Raw Rows | Accepted Rows | Languages | Role in EvaluaTech Pipeline |
| :---: | :--- | :--- | :---: | :---: | :--- | :--- |
| **Tier 1** | `yhua219/EduRABSA_SA` + `yhua219/EduRABSA_ACD` | CC-BY-4.0 / Academic | `6,500` | **`1,035`** | English | Manually annotated university student reviews across Course, Staff, and Campus Facilities; provides 3-class Sentiment and 1-to-1 mapped Pedagogical/Facility clusters. |
| **Tier 2A** | `Z-H-Che/ratemyprofessors-reviews-3-labels` | Apache-2.0 | `42,557` | **`254`** | English | Authentic university professor evaluations (`0=Neutral`, `1=Negative`, `2=Positive` verified via programmatic VADER monotonicity assertion). |
| **Tier 2B** | `jjjardev/tagasenti` | CC-BY-SA-4.0 | `35,686` | **`995`** | Tagalog (`570`), Taglish (`425`) | Authentic Filipino & Taglish ternary sentiment sentences (`0=Negative`, `1=Neutral`, `2=Positive`) filtered with e-commerce/news blacklists, homograph shields, and an evaluative-discourse gate on `Neutral`. |
| **Tier 3A** | `kkotkar1/course-reviews` | Public Academic | `80,263` | **`60`** | English | Collegiate course feedback (`1,2->Negative`, `3->Neutral`, `5->Positive`, ambiguous `4-star` dropped, MOOC noise removed). |
| **Tier 3B** | `chillies/course-review-multilabel-sentiment-analysis` | Public Academic | `7,387` | **`19`** | English | Multi-label course feedback filtered under strict single-polarity purity (`not_praise` vs. `Improvement Suggestions` / `Technical Feedback`) and ASCII `>= 0.96`. |
| **Total** | **5 Open-Source Corpora** | — | **`172,393`** | **`2,363`** | **EN `1,368` · TL `570` · TL-EN `425`** | Combined with `dev_partition.csv` (`7,000`) + `expanded_training_corpus.csv` (`1,185`) = **`10,548` training samples**. |

---

## 2. Research Department Filtering & Anti-Contamination Controls

1. **Strengthened Two-Stage Blind Holdout Disjointness (`test_partition.csv` Never Inspected)**:
   - **Stage A (Exact Normalized SHA-256)**: `final_test_exact_hash_overlap == 0` against `docs/analysis/test_partition.csv`.
   - **Stage B (5-Gram, Substring & Jaccard Shield)**: Blocks any external comment sharing a contiguous **5-token normalized n-gram** (`final_test_5gram_overlap == 0`), `>= 25`-char substring, or `>= 0.62` token Jaccard similarity across all `3,000` `test_partition.csv` and `7,000` `dev_partition.csv` texts.
2. **Collegiate Discourse Length Envelope**:
   - Retains `1–2` sentence evaluations (`6–38` words, `35–240` characters, `<= 25%` uppercase ratio) matching authentic `ID <= 800` collegiate SET responses so single-pivot contrastive polarity decomposition (`pol1` vs. `pol2`) remains accurate.
3. **E-Commerce, Homograph & Evaluative-`Neutral` Shield on `jjjardev/tagasenti`**:
   - Strips Shopee/Lazada shipping, celebrity, crime, and election domains (`parcel`, `seller`, `courier`, `mura`, `dumating`, `order`, `pulis`, `halalan`).
   - Shields the Tagalog homograph *"mabilis"* unless paired with instructional/communication verbs (`magsalita`, `magturo`, `sumagot`, `mag-reply`, `magbalik`, `matuto`, `maintindihan`).
   - Requires `Neutral` (`label=1`) rows to contain explicit Filipino evaluative hedging or contrastive balance (`medyo`, `sakto`, `tama lang`, `okay`, `sapat`, `katamtaman`, `karaniwan`, `pero`, `kaso`, `naman`), eliminating non-evaluative news headlines.
4. **Zero Language–Sentiment Mutual Information ($I(\text{Language}; \text{Sentiment}) = 0$)**:
   - Enforces the exact `1.00 : 1.00 : 0.85` (`Positive : Negative : Neutral`) class ratio inside **every** language (`English: 480/480/408`, `Tagalog: 200/200/170`, `Taglish: 149/149/127`).

---

## 3. Specialist–Generalist Domain-Adaptation Architecture (`gamma_ext = 0.35`)

Inside [`nlp-python/train_model.py`](../../nlp-python/train_model.py), external sample weights are bounded by the Research Department's `0.35` dynamic mass-cap factor
$$\gamma_{\text{ext}} = \min\!\left(1.0,\; \frac{0.35 \times N_{\text{real}} \times w_{\text{real}}}{\sum_{j \in \text{external}} w_j}\right)$$
and routed across a 4-head calibrated probability ensemble:
1. **Head 1 (`Collegiate Specialist LR`, `w = 0.20`)**: Trained strictly on `Real AAAAA13 + Curated SET` over the `115k`-cap TF-IDF + `500d` `calamanCy`/`spaCy` + `24d` VADER space.
2. **Head 2 (`Domain-Adapted Platt-Calibrated LinearSVC`, `w = 0.35`)**: Trained across `Real AAAAA13 + Curated SET + 2,363 External Corpora` ($\gamma_{\text{ext}}$-weighted).
3. **Head 3 (`Domain-Adapted 524d RBF-SVC`, `w = 0.25`)**: Trained on the `500d` (`tl_calamancy_md-0.2.0` `200d` + `en_core_web_md` `300d`) + `24d` VADER slice across `Real + Curated + External` ($\gamma_{\text{ext}}$-weighted).
4. **Head 4 (`Full-Regime LR`, `w = 0.20`)**: Trained across all `10,548` combined samples (`Real + Curated + External + Synth Dev`).
