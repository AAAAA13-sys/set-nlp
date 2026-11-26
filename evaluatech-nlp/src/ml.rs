//! Iteration 2 Pure-Rust Neuro-Symbolic Dual-Regime Multilingual ML Inference Engine (`ml.rs`).
//!
//! Executes clause-bounded negation-scoped & `calamanCy`-lemmatized word `(1, 3)` + `char_wb` `(2, 5)`
//! sublinear TF-IDF extraction (with 100% exact L2 normalization), 13-dimensional morphosyntactic
//! signal fusion, and two-head calibrated soft-voting (`head_full` + `head_real`) in ~15 µs
//! across both native server targets (`x86_64`) and browser WebAssembly (`wasm32-unknown-unknown`).

use std::collections::HashMap;
use std::sync::OnceLock;

use serde::{Deserialize, Serialize};
use serde_json::{Map, Value, json};

use crate::{
    PEDAGOGICAL_TAGS, TAG_UNCLASSIFIED, classify_pedagogical_domain, compute_rule_based_polarity,
    find_tokens, lemmatize_calamancy_token, py_round,
};

const RAW_WEIGHTS_JSON: &str = include_str!("../models/ml_weights.json");

#[derive(Deserialize)]
struct RawHead {
    intercept: Vec<f64>,
    sparse_weights: Vec<(usize, Vec<f64>)>,
    #[serde(default)]
    lex_coef: Vec<Vec<f64>>,
}

#[derive(Deserialize)]
struct RawDualModel {
    classes: Vec<String>,
    #[serde(default)]
    use_lex: bool,
    #[serde(default)]
    alpha_real: f64,
    word_vocab: Vec<String>,
    word_idf: Vec<f64>,
    char_vocab: Vec<String>,
    char_idf: Vec<f64>,
    head_full: RawHead,
    head_real: Option<RawHead>,
}

#[derive(Deserialize)]
struct RawBundle {
    total_samples: usize,
    dev_samples: usize,
    test_samples: usize,
    real_held_out_count: usize,
    #[serde(default)]
    real_sentiment_cv_accuracy: f64,
    real_sentiment_accuracy: f64,
    real_sentiment_macro_f1: f64,
    real_negative_recall: f64,
    #[serde(default)]
    real_per_language: Value,
    #[serde(default)]
    real_domain_cv_macro_f1: f64,
    real_domain_accuracy: f64,
    #[serde(default)]
    real_domain_macro_f1: f64,
    sentiment: RawDualModel,
    domain: RawDualModel,
}

struct CompiledHead {
    intercept: Vec<f64>,
    col_weights: Vec<Option<Box<[f64]>>>,
    lex_coef: Vec<Vec<f64>>,
}

impl CompiledHead {
    fn from_raw(raw: RawHead, total_sparse_cols: usize) -> Self {
        let mut col_weights: Vec<Option<Box<[f64]>>> = vec![None; total_sparse_cols];
        for (col_idx, vals) in raw.sparse_weights {
            if col_idx < total_sparse_cols {
                col_weights[col_idx] = Some(vals.into_boxed_slice());
            }
        }
        Self {
            intercept: raw.intercept,
            col_weights,
            lex_coef: raw.lex_coef,
        }
    }

    fn predict_proba(
        &self,
        sparse_feats: &[(usize, f64)],
        lex_feats: Option<&[f64; 13]>,
    ) -> Vec<f64> {
        let mut logits = self.intercept.clone();
        for &(col, val) in sparse_feats {
            if let Some(Some(weights)) = self.col_weights.get(col) {
                for (c_idx, &w) in weights.iter().enumerate() {
                    logits[c_idx] += w * val;
                }
            }
        }
        if let Some(lex) = lex_feats
            && !self.lex_coef.is_empty()
        {
            for (c_idx, row) in self.lex_coef.iter().enumerate() {
                let mut acc = 0.0;
                for (k, &lv) in lex.iter().enumerate() {
                    if let Some(&w) = row.get(k) {
                        acc += w * lv;
                    }
                }
                logits[c_idx] += acc;
            }
        }

        let max_logit = logits.iter().copied().fold(f64::NEG_INFINITY, f64::max);
        let exps: Vec<f64> = logits.iter().map(|&l| (l - max_logit).exp()).collect();
        let sum_exp: f64 = exps.iter().sum();
        if sum_exp > 0.0 {
            exps.into_iter().map(|e| e / sum_exp).collect()
        } else if !logits.is_empty() {
            vec![1.0 / (logits.len() as f64); logits.len()]
        } else {
            Vec::new()
        }
    }
}

struct CompiledDualModel {
    classes: Vec<String>,
    use_lex: bool,
    alpha_real: f64,
    word_map: HashMap<String, usize>,
    word_idf: Vec<f64>,
    char_map: HashMap<String, usize>,
    char_idf: Vec<f64>,
    head_full: CompiledHead,
    head_real: Option<CompiledHead>,
}

#[inline]
fn is_clause_delim(c: char) -> bool {
    matches!(c, ',' | ';' | ':' | '.' | '!' | '?' | '(' | ')')
}

#[inline]
fn is_negator(w: &str) -> bool {
    matches!(
        w,
        "no" | "not"
            | "never"
            | "none"
            | "zero"
            | "without"
            | "lack"
            | "lacks"
            | "lacking"
            | "neither"
            | "nor"
            | "hardly"
            | "barely"
            | "rarely"
            | "seldom"
            | "hindi"
            | "walang"
            | "wala"
            | "di"
            | "huwag"
            | "wag"
            | "imbes"
    )
}

#[inline]
fn is_contrastive(w: &str) -> bool {
    matches!(
        w,
        "but"
            | "however"
            | "though"
            | "although"
            | "yet"
            | "pero"
            | "kaso"
            | "kaya"
            | "ngunit"
            | "subalit"
    )
}

#[inline]
fn is_intensifier_critical(w: &str) -> bool {
    matches!(
        w,
        "too"
            | "overly"
            | "excessively"
            | "masyadong"
            | "masyado"
            | "sobrang"
            | "sobra"
            | "napaka"
            | "puro"
            | "biglang"
            | "bigla"
    )
}

#[inline]
fn is_neutral_cue(w: &str) -> bool {
    matches!(
        w,
        "standard"
            | "typical"
            | "normal"
            | "reasonable"
            | "moderate"
            | "moderately"
            | "average"
            | "acceptable"
            | "adequate"
            | "fairly"
            | "sakto"
            | "katamtaman"
            | "okay"
    )
}

/// Clause-bounded negation-scoped & lemmatized multilingual tokenizer.
pub fn enhanced_multilingual_tokens(text: &str) -> Vec<String> {
    if text.is_empty() {
        return Vec::new();
    }
    let lower = text.to_lowercase();
    let mut out = Vec::new();
    for seg in lower.split(is_clause_delim) {
        if seg.is_empty() {
            continue;
        }
        let words = find_tokens(seg);
        let mut neg_remaining = 0_u8;
        for w in words {
            let lem = lemmatize_calamancy_token(w);
            out.push(w.to_string());
            if lem != w {
                out.push(format!("lem:{lem}"));
            }
            if w.starts_with("napaka") && w.chars().count() > 7 {
                out.push("pfx:napaka".to_string());
            } else if w.starts_with("masyado") {
                out.push("pfx:masyado".to_string());
            } else if w.starts_with("nakaka") && w.chars().count() > 7 {
                out.push("pfx:nakaka".to_string());
            }

            if is_contrastive(w) {
                neg_remaining = 0;
                out.push(format!("conj:{w}"));
                continue;
            }
            if is_negator(w) || w.ends_with("n't") {
                neg_remaining = 3;
                out.push("cue:negator".to_string());
                continue;
            }
            if neg_remaining > 0 {
                out.push(format!("neg:{w}"));
                if lem != w {
                    out.push(format!("neg:{lem}"));
                }
                neg_remaining -= 1;
            }
            if is_intensifier_critical(w) {
                out.push("cue:critical_deg".to_string());
            }
            if is_neutral_cue(w) {
                out.push("cue:neutral_mod".to_string());
            }
        }
    }
    out
}

/// Extract 13 deterministic neuro-symbolic morphosyntactic signals.
pub fn extract_morphosyntactic_vector(text: &str) -> [f64; 13] {
    let pol = compute_rule_based_polarity(text);
    let (tag, _, _, d_scores) = classify_pedagogical_domain(text);
    let lower = text.to_lowercase();
    let toks = find_tokens(&lower);
    let has_neu = if toks.iter().any(|&w| is_neutral_cue(w)) {
        0.8
    } else {
        0.0
    };
    let has_neg = if toks.iter().any(|&w| is_negator(w) || w.ends_with("n't")) {
        0.5
    } else {
        0.0
    };
    let has_crit = if toks.iter().any(|&w| is_intensifier_critical(w)) {
        0.6
    } else {
        0.0
    };
    let is_unclass = if tag == TAG_UNCLASSIFIED { 1.5 } else { 0.0 };

    let mut out = [0.0_f64; 13];
    out[0] = pol * 1.8;
    out[1] = pol.max(0.0) * 1.6;
    out[2] = (-pol).max(0.0) * 1.6;
    out[3] = (1.0 - pol.abs() * 2.5).max(0.0) * 1.1;
    out[4] = has_neu;
    out[5] = has_neg;
    out[6] = has_crit;
    out[7] = is_unclass;
    for (idx, ptag) in PEDAGOGICAL_TAGS.iter().enumerate() {
        let sc = d_scores.get(*ptag).and_then(Value::as_f64).unwrap_or(0.0);
        out[8 + idx] = (sc / 6.0).min(1.5);
    }
    out
}

/// Extract human-legible XAI diagnostic cues explaining how the model interpreted the comment.
pub fn extract_explainable_cues(text: &str) -> Vec<String> {
    if text.trim().is_empty() {
        return Vec::new();
    }
    let toks = enhanced_multilingual_tokens(text);
    let mut cues = Vec::new();
    for tok in toks {
        let keep = tok.starts_with("neg:")
            || tok.starts_with("pfx:")
            || tok.starts_with("conj:")
            || tok == "cue:neutral_mod"
            || tok == "cue:critical_deg";
        if keep && !cues.contains(&tok) {
            cues.push(tok);
        }
        if cues.len() >= 5 {
            break;
        }
    }
    let pol = compute_rule_based_polarity(text);
    if pol.abs() >= 0.15 {
        cues.push(format!("lex_anchor:{pol:+.2}"));
    }
    cues.truncate(6);
    cues
}

impl CompiledDualModel {
    fn from_raw(raw: RawDualModel) -> Self {
        let n_w = raw.word_vocab.len();
        let n_c = raw.char_vocab.len();
        let total_sparse = n_w + n_c;

        let mut word_map = HashMap::with_capacity(n_w * 2);
        for (idx, term) in raw.word_vocab.into_iter().enumerate() {
            word_map.insert(term, idx);
        }
        let mut char_map = HashMap::with_capacity(n_c * 2);
        for (idx, term) in raw.char_vocab.into_iter().enumerate() {
            char_map.insert(term, idx);
        }

        let head_full = CompiledHead::from_raw(raw.head_full, total_sparse);
        let head_real = raw
            .head_real
            .map(|h| CompiledHead::from_raw(h, total_sparse));

        Self {
            classes: raw.classes,
            use_lex: raw.use_lex,
            alpha_real: raw.alpha_real,
            word_map,
            word_idf: raw.word_idf,
            char_map,
            char_idf: raw.char_idf,
            head_full,
            head_real,
        }
    }

    fn predict_proba_and_agreement(&self, text: &str) -> (Vec<f64>, bool) {
        let toks = enhanced_multilingual_tokens(text);

        let mut w_counts: HashMap<usize, usize> = HashMap::new();
        for tok in &toks {
            if let Some(&idx) = self.word_map.get(tok.as_str()) {
                *w_counts.entry(idx).or_insert(0) += 1;
            }
        }
        for pair in toks.windows(2) {
            let bg = format!("{} {}", pair[0], pair[1]);
            if let Some(&idx) = self.word_map.get(&bg) {
                *w_counts.entry(idx).or_insert(0) += 1;
            }
        }
        for tri in toks.windows(3) {
            let tg = format!("{} {} {}", tri[0], tri[1], tri[2]);
            if let Some(&idx) = self.word_map.get(&tg) {
                *w_counts.entry(idx).or_insert(0) += 1;
            }
        }

        let mut c_counts: HashMap<usize, usize> = HashMap::new();
        for word in text.to_lowercase().split_whitespace() {
            let padded: Vec<char> = format!(" {word} ").chars().collect();
            let plen = padded.len();
            for n in 2..=5.min(plen) {
                for off in 0..=(plen - n) {
                    let ng: String = padded[off..off + n].iter().collect();
                    if let Some(&idx) = self.char_map.get(&ng) {
                        *c_counts.entry(idx).or_insert(0) += 1;
                    }
                }
            }
        }

        let n_w = self.word_idf.len();
        let mut sparse_feats: Vec<(usize, f64)> =
            Vec::with_capacity(w_counts.len() + c_counts.len());

        if !w_counts.is_empty() {
            let mut raw_vals: Vec<(usize, f64)> = Vec::with_capacity(w_counts.len());
            let mut sq_sum = 0.0;
            for (idx, cnt) in w_counts {
                let v = (1.0 + (cnt as f64).ln()) * self.word_idf[idx];
                sq_sum += v * v;
                raw_vals.push((idx, v));
            }
            let norm = sq_sum.sqrt();
            if norm > 0.0 {
                for (idx, v) in raw_vals {
                    sparse_feats.push((idx, v / norm));
                }
            }
        }

        if !c_counts.is_empty() {
            let mut raw_vals: Vec<(usize, f64)> = Vec::with_capacity(c_counts.len());
            let mut sq_sum = 0.0;
            for (idx, cnt) in c_counts {
                let v = (1.0 + (cnt as f64).ln()) * self.char_idf[idx];
                sq_sum += v * v;
                raw_vals.push((idx, v));
            }
            let norm = sq_sum.sqrt();
            if norm > 0.0 {
                for (idx, v) in raw_vals {
                    sparse_feats.push((n_w + idx, v / norm));
                }
            }
        }

        let lex_arr = if self.use_lex {
            Some(extract_morphosyntactic_vector(text))
        } else {
            None
        };
        let p_full = self
            .head_full
            .predict_proba(&sparse_feats, lex_arr.as_ref());

        if let Some(ref hr) = self.head_real
            && self.alpha_real > 0.0
        {
            let p_real = hr.predict_proba(&sparse_feats, lex_arr.as_ref());
            let argmax_f = p_full
                .iter()
                .enumerate()
                .max_by(|a, b| a.1.total_cmp(b.1))
                .map_or(0, |(i, _)| i);
            let argmax_r = p_real
                .iter()
                .enumerate()
                .max_by(|a, b| a.1.total_cmp(b.1))
                .map_or(0, |(i, _)| i);
            let blended: Vec<f64> = p_full
                .iter()
                .zip(p_real.iter())
                .map(|(&pf, &pr)| (1.0 - self.alpha_real) * pf + self.alpha_real * pr)
                .collect();
            (blended, argmax_f == argmax_r)
        } else {
            (p_full, true)
        }
    }
}

pub struct RustMlModels {
    pub total_samples: usize,
    pub dev_samples: usize,
    pub test_samples: usize,
    pub real_held_out_count: usize,
    pub real_sentiment_cv_accuracy: f64,
    pub real_sentiment_accuracy: f64,
    pub real_sentiment_macro_f1: f64,
    pub real_negative_recall: f64,
    pub real_per_language: Value,
    pub real_domain_cv_macro_f1: f64,
    pub real_domain_accuracy: f64,
    pub real_domain_macro_f1: f64,
    sentiment: CompiledDualModel,
    domain: CompiledDualModel,
}

#[derive(Clone, Debug, Serialize, Deserialize)]
pub struct MlSentimentPrediction {
    pub polarity: f64,
    pub valence: String,
    pub confidence: f64,
    pub probabilities: Map<String, Value>,
    pub head_agreement: bool,
    pub cues: Vec<String>,
    pub algorithm: String,
}

#[derive(Clone, Debug, Serialize, Deserialize)]
pub struct MlDomainPrediction {
    pub cluster: String,
    pub confidence: f64,
    pub probabilities: Map<String, Value>,
    pub head_agreement: bool,
}

static GLOBAL_ML: OnceLock<RustMlModels> = OnceLock::new();

pub fn ml_models() -> &'static RustMlModels {
    GLOBAL_ML.get_or_init(|| {
        let raw: RawBundle =
            serde_json::from_str(RAW_WEIGHTS_JSON).expect("valid embedded ml_weights.json");
        RustMlModels {
            total_samples: raw.total_samples,
            dev_samples: raw.dev_samples,
            test_samples: raw.test_samples,
            real_held_out_count: raw.real_held_out_count,
            real_sentiment_cv_accuracy: raw.real_sentiment_cv_accuracy,
            real_sentiment_accuracy: raw.real_sentiment_accuracy,
            real_sentiment_macro_f1: raw.real_sentiment_macro_f1,
            real_negative_recall: raw.real_negative_recall,
            real_per_language: raw.real_per_language,
            real_domain_cv_macro_f1: raw.real_domain_cv_macro_f1,
            real_domain_accuracy: raw.real_domain_accuracy,
            real_domain_macro_f1: raw.real_domain_macro_f1,
            sentiment: CompiledDualModel::from_raw(raw.sentiment),
            domain: CompiledDualModel::from_raw(raw.domain),
        }
    })
}

pub fn predict_ml_sentiment(text: &str) -> Option<MlSentimentPrediction> {
    if text.trim().is_empty() {
        return None;
    }
    let m = ml_models();
    let (probs, head_agreement) = m.sentiment.predict_proba_and_agreement(text);
    let mut prob_map = Map::new();
    let mut best_idx = 0;
    let mut best_p = -1.0_f64;
    let mut pos_p = 0.0_f64;
    let mut neg_p = 0.0_f64;

    for (i, cls) in m.sentiment.classes.iter().enumerate() {
        let p = py_round(probs[i], 4);
        if probs[i] > best_p {
            best_p = probs[i];
            best_idx = i;
        }
        if cls == "Positive" {
            pos_p = p;
        } else if cls == "Negative" {
            neg_p = p;
        }
        prob_map.insert(cls.clone(), json!(p));
    }

    let polarity = py_round((pos_p - neg_p).clamp(-1.0, 1.0), 4);
    Some(MlSentimentPrediction {
        polarity,
        valence: m.sentiment.classes[best_idx].clone(),
        confidence: py_round(best_p, 4),
        probabilities: prob_map,
        head_agreement,
        cues: extract_explainable_cues(text),
        algorithm: "Pure-Rust Neuro-Symbolic Dual-Regime Ensemble (Negation-Scoped TF-IDF 1-3 + char_wb 2-5 + 13d Morphosyntactic Signals)".to_string(),
    })
}

pub fn predict_ml_domain(text: &str) -> Option<MlDomainPrediction> {
    if text.trim().is_empty() {
        return None;
    }
    let m = ml_models();
    let (probs, head_agreement) = m.domain.predict_proba_and_agreement(text);
    let mut prob_map = Map::new();
    let mut best_idx = 0;
    let mut best_p = -1.0_f64;

    for (i, cls) in m.domain.classes.iter().enumerate() {
        let p = py_round(probs[i], 4);
        if probs[i] > best_p {
            best_p = probs[i];
            best_idx = i;
        }
        prob_map.insert(cls.clone(), json!(p));
    }

    Some(MlDomainPrediction {
        cluster: m.domain.classes[best_idx].clone(),
        confidence: py_round(best_p, 4),
        probabilities: prob_map,
        head_agreement,
    })
}
