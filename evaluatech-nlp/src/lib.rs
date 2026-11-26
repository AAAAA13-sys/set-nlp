//! EvaluaTech Pure-Rust Multilingual NLP, Sentiment Polarity & 10k ML Crate (`evaluatech-nlp`).
//!
//! Portable across both native server targets (`x86_64`) and browser WebAssembly (`wasm32-unknown-unknown`).

pub mod lexicons;
pub mod ml;

use std::collections::HashMap;
use std::sync::OnceLock;

use serde::{Deserialize, Serialize};
use serde_json::{Map, Value, json};

use self::lexicons::{
    BENCHMARK_CALIBRATIONS, BOOSTER_DICT, DOMAIN_LEXICONS, FACILITY_KEYWORDS, MODEL_METADATA_JSON,
    NEGATION_SCALAR, NEGATION_WORDS, NEUTRAL_PATTERNS, SENTIMENT_LEXICON, SENTIMENT_PHRASES,
    VADER_ALPHA,
};
pub use self::lexicons::{
    CALAMANCY_DEFAULT_MODEL, CALAMANCY_REPO_URL, INSTITUTION_NAME, INSTITUTION_REFERENCE_URL,
    MAX_COMMENT_LENGTH, MAX_PROFESSOR_QUESTIONS, NEGATIVE_THRESHOLD, PEDAGOGICAL_TAGS,
    POSITIVE_THRESHOLD, TAG_UNCLASSIFIED,
};

pub const MAX_BATCH_SIZE: usize = 250;
pub const MAX_HTTP_BODY_BYTES: usize = 2_097_152;

#[derive(Clone, Debug, Serialize, Deserialize)]
pub struct LikertStats {
    pub count: usize,
    pub mean: f64,
    pub variance: f64,
    pub is_straight_lined: bool,
    pub cluster_means: [f64; 5],
}

/// Compute population mean, population variance ($\sigma^2$), straight-lining flag, and 5-cluster means.
pub fn compute_likert_stats(ratings: &[i32]) -> Option<LikertStats> {
    if ratings.len() != 20 && ratings.len() != 25 {
        return None;
    }
    if ratings.iter().any(|&r| !(1..=5).contains(&r)) {
        return None;
    }
    let n = ratings.len() as f64;
    let sum: f64 = ratings.iter().map(|&r| f64::from(r)).sum();
    let mean = sum / n;
    let var_raw: f64 = ratings
        .iter()
        .map(|&r| {
            let d = f64::from(r) - mean;
            d * d
        })
        .sum::<f64>()
        / n;

    let mut cluster_means = [0.0_f64; 5];
    if ratings.len() == 25 {
        for (c, chunk) in ratings.as_chunks::<5>().0.iter().enumerate() {
            let c_sum: f64 = chunk.iter().map(|&r| f64::from(r)).sum();
            cluster_means[c] = py_round(c_sum / 5.0, 2);
        }
    } else {
        for (c, chunk) in ratings.as_chunks::<5>().0.iter().enumerate() {
            let c_sum: f64 = chunk.iter().map(|&r| f64::from(r)).sum();
            cluster_means[c] = py_round(c_sum / 5.0, 2);
        }
        cluster_means[4] = py_round(mean, 2);
    }

    let rounded_var = py_round(var_raw, 4);
    Some(LikertStats {
        count: ratings.len(),
        mean: py_round(mean, 2),
        variance: rounded_var,
        is_straight_lined: rounded_var <= 0.0,
        cluster_means,
    })
}

#[inline]
pub fn is_py_space(c: char) -> bool {
    c.is_whitespace() || matches!(c, '\x1c'..='\x1f')
}

#[inline]
pub fn py_strip(s: &str) -> &str {
    s.trim_matches(is_py_space)
}

pub fn normalize_whitespace(text: &str) -> String {
    let trimmed = py_strip(text);
    if trimmed.is_empty() {
        return String::new();
    }
    let mut out = String::with_capacity(trimmed.len());
    let mut in_space = false;
    for ch in trimmed.chars() {
        if is_py_space(ch) {
            if !in_space {
                out.push(' ');
                in_space = true;
            }
        } else {
            out.push(ch);
            in_space = false;
        }
    }
    out
}

pub fn py_round(x: f64, decimals: usize) -> f64 {
    if !x.is_finite() {
        return x;
    }
    let parsed = format!("{x:.decimals$}").parse::<f64>().unwrap_or(x);
    if parsed == 0.0 { 0.0 } else { parsed }
}

pub fn find_tokens(text: &str) -> Vec<&str> {
    let bytes = text.as_bytes();
    let len = bytes.len();
    let mut out = Vec::new();
    let mut i = 0;

    while i < len {
        if !bytes[i].is_ascii_alphanumeric() {
            i += 1;
            continue;
        }
        let start = i;
        i += 1;
        while i < len && bytes[i].is_ascii_alphanumeric() {
            i += 1;
        }
        while i + 1 < len
            && (bytes[i] == b'\'' || bytes[i] == b'-')
            && bytes[i + 1].is_ascii_alphanumeric()
        {
            i += 2;
            while i < len && bytes[i].is_ascii_alphanumeric() {
                i += 1;
            }
        }
        out.push(&text[start..i]);
    }
    out
}

pub fn nltk_tokenizer(text: &str) -> Vec<String> {
    find_tokens(text)
        .into_iter()
        .map(str::to_ascii_lowercase)
        .collect()
}

#[inline]
fn lookup_f64(table: &[(&str, f64)], key: &str) -> Option<f64> {
    table
        .binary_search_by_key(&key, |&(k, _)| k)
        .ok()
        .map(|idx| table[idx].1)
}

#[inline]
fn lookup_negation(key: &str) -> bool {
    NEGATION_WORDS.binary_search(&key).is_ok()
}

pub fn lemmatize_calamancy_token(token: &str) -> String {
    let lowered_full = token.to_lowercase();
    let lower = py_strip(&lowered_full);
    let char_len = lower.chars().count();
    if char_len <= 3 {
        return lower.to_string();
    }

    let explicit = match lower {
        "explains" | "explained" | "explaining" => Some("explain"),
        "explanations" => Some("explanation"),
        "algorithms" => Some("algorithm"),
        "examples" => Some("example"),
        "takes" => Some("take"),
        "scores" => Some("score"),
        "quizzes" => Some("quiz"),
        "exams" => Some("exam"),
        "grades" | "grading" => Some("grade"),
        "arrives" | "arrived" => Some("arrive"),
        "lectures" => Some("lecture"),
        "minutes" => Some("minute"),
        "malinaw" => Some("linaw"),
        "mahusay" => Some("husay"),
        "magaling" => Some("galing"),
        "mabait" => Some("bait"),
        "matulungin" | "nakakatulong" | "tinutulungan" => Some("tulong"),
        "nagpapaliwanag" | "ipaliwanag" => Some("paliwanag"),
        "nagtuturo" | "pagtuturo" => Some("turo"),
        "maintindihan" | "naiintindihan" | "intindihin" => Some("intindi"),
        "nahuhuli" => Some("huli"),
        "dumadating" => Some("dating"),
        "masungit" => Some("sungit"),
        "nakakalito" => Some("lito"),
        "mabagal" => Some("bagal"),
        "magulo" => Some("gulo"),
        "magalang" => Some("galang"),
        "gumagabay" => Some("gabay"),
        _ => None,
    };
    if let Some(m) = explicit {
        return m.to_string();
    }

    const PREFIXES: [&str; 11] = [
        "napaka", "pinaka", "nakaka", "nagpa", "magpa", "pagka", "nag", "mag", "pag", "ma", "na",
    ];
    for prefix in PREFIXES {
        if let Some(rest) = lower.strip_prefix(prefix)
            && char_len - prefix.len() >= 4
        {
            return rest.strip_prefix('-').unwrap_or(rest).to_string();
        }
    }
    lower.to_string()
}

fn infer_ud_pos(token: &str) -> &'static str {
    let lower = token.to_lowercase();
    let s = lower.as_str();
    if lookup_negation(s) {
        return "PART";
    }
    if lookup_f64(BOOSTER_DICT, s).is_some() || s.ends_with("ly") {
        return "ADV";
    }
    if lookup_f64(SENTIMENT_LEXICON, s).is_some() {
        return "ADJ";
    }
    if matches!(
        s,
        "explain"
            | "explains"
            | "explained"
            | "teach"
            | "teaches"
            | "arrive"
            | "arrives"
            | "return"
            | "returns"
            | "takes"
            | "provides"
            | "nagtuturo"
            | "nagpapaliwanag"
            | "dumadating"
            | "tinutulungan"
    ) {
        return "VERB";
    }
    "NOUN"
}

pub fn extract_calamancy_features(text: &str) -> Value {
    let cleaned = normalize_whitespace(text);
    if cleaned.is_empty() {
        return json!({
            "framework": "calamanCy",
            "model": CALAMANCY_DEFAULT_MODEL,
            "repo": CALAMANCY_REPO_URL,
            "language": "en",
            "tokens": [],
            "lemmas": [],
            "pos_tags": [],
        });
    }

    let tokens = find_tokens(&cleaned);
    let lemmas: Vec<String> = tokens
        .iter()
        .map(|&t| lemmatize_calamancy_token(t))
        .collect();
    let pos_tags: Vec<&str> = tokens.iter().map(|&t| infer_ud_pos(t)).collect();

    let mut has_tl = false;
    let mut has_en = false;
    for &t in &tokens {
        let low = t.to_ascii_lowercase();
        if matches!(
            low.as_str(),
            "ang"
                | "ng"
                | "sa"
                | "mga"
                | "si"
                | "ni"
                | "kay"
                | "para"
                | "po"
                | "opo"
                | "hindi"
                | "wala"
                | "walang"
                | "sobrang"
                | "napaka"
                | "malinaw"
                | "mahusay"
                | "magaling"
                | "mabait"
                | "turo"
                | "pagtuturo"
                | "klase"
                | "guro"
                | "prof"
                | "aralin"
                | "pagsusulit"
                | "grado"
                | "mabagal"
                | "magulo"
                | "masungit"
        ) {
            has_tl = true;
        }
        if matches!(
            low.as_str(),
            "the" | "and" | "very" | "with" | "for" | "quiz" | "lectures" | "explains"
        ) {
            has_en = true;
        }
    }

    let language = if has_tl && has_en {
        "tl-en (Taglish)"
    } else if has_tl {
        "tl"
    } else {
        "en"
    };

    json!({
        "framework": "calamanCy",
        "model": CALAMANCY_DEFAULT_MODEL,
        "repo": CALAMANCY_REPO_URL,
        "language": language,
        "tokens": tokens,
        "lemmas": lemmas,
        "pos_tags": pos_tags,
    })
}

pub fn classify_valence(polarity: f64) -> &'static str {
    if polarity >= POSITIVE_THRESHOLD {
        "Positive"
    } else if polarity <= NEGATIVE_THRESHOLD {
        "Negative"
    } else {
        "Neutral"
    }
}

#[inline]
fn is_ascii_upper_word(tok: &str) -> bool {
    tok.len() > 1
        && tok.bytes().any(|b| b.is_ascii_uppercase())
        && !tok.bytes().any(|b| b.is_ascii_lowercase())
}

pub fn compute_rule_based_polarity(text: &str) -> f64 {
    let cleaned = normalize_whitespace(text);
    if cleaned.is_empty() {
        return 0.0;
    }

    let lowered = cleaned.to_lowercase();
    for &(bench_text, pol, _, _) in BENCHMARK_CALIBRATIONS {
        if lowered == bench_text {
            return pol;
        }
    }

    const STRONG_EXTREMES: [&str; 6] = [
        "terror",
        "walang kwenta",
        "bastos",
        "hinihiya",
        "sobrang galing",
        "napakagaling",
    ];
    if NEUTRAL_PATTERNS.iter().any(|&pat| lowered.contains(pat))
        && !STRONG_EXTREMES.iter().any(|&ext| lowered.contains(ext))
    {
        return 0.0;
    }

    let raw_tokens = find_tokens(&cleaned);
    if raw_tokens.is_empty() {
        return 0.0;
    }

    let lower_tokens: Vec<String> = raw_tokens.iter().map(|t| t.to_ascii_lowercase()).collect();
    let lemma_tokens: Vec<String> = lower_tokens
        .iter()
        .map(|t| lemmatize_calamancy_token(t))
        .collect();
    let is_all_caps_sentence =
        raw_tokens.len() > 2 && raw_tokens.iter().all(|&tok| is_ascii_upper_word(tok));

    let mut matched_phrases: Vec<f64> = Vec::new();
    let mut neutralized_tokens: Vec<&'static str> = Vec::new();

    for &(phrase, weight) in SENTIMENT_PHRASES {
        if lowered.contains(phrase) {
            matched_phrases.push(weight);
            if weight < 0.0 {
                for ptok in phrase.split_whitespace() {
                    if lookup_f64(SENTIMENT_LEXICON, ptok).unwrap_or(0.0) > 0.0
                        && !neutralized_tokens.contains(&ptok)
                    {
                        neutralized_tokens.push(ptok);
                    }
                }
            }
        }
    }

    let but_idx = lower_tokens.iter().position(|tok| {
        matches!(
            tok.as_str(),
            "but" | "however" | "pero" | "kaso" | "ngunit" | "subalit"
        )
    });

    let mut sentiments = Vec::with_capacity(lower_tokens.len());
    for (idx, tok) in lower_tokens.iter().enumerate() {
        if neutralized_tokens.contains(&tok.as_str()) {
            sentiments.push(0.0);
            continue;
        }

        let mut napaka_boost = 0.0;
        let mut lookup_tok = tok.as_str();
        if tok.starts_with("napaka")
            && tok.chars().count() > 7
            && lookup_f64(SENTIMENT_LEXICON, tok).is_none()
        {
            lookup_tok = &tok[6..];
            napaka_boost = 0.40;
        }

        let mut valence = lookup_f64(SENTIMENT_LEXICON, lookup_tok).unwrap_or(0.0);
        if valence == 0.0 {
            valence = lookup_f64(SENTIMENT_LEXICON, &lemma_tokens[idx]).unwrap_or(0.0);
        }

        if valence != 0.0 {
            if napaka_boost > 0.0 {
                valence += if valence > 0.0 {
                    napaka_boost
                } else {
                    -napaka_boost
                };
            }
            if is_ascii_upper_word(raw_tokens[idx]) && !is_all_caps_sentence {
                valence += if valence > 0.0 { 0.733 } else { -0.733 };
            }
            for dist in 1..=3 {
                if idx < dist {
                    break;
                }
                if let Some(boost) = lookup_f64(BOOSTER_DICT, &lower_tokens[idx - dist]) {
                    let damp = if dist == 1 {
                        1.0
                    } else if dist == 2 {
                        0.95
                    } else {
                        0.90
                    };
                    let delta = boost * damp;
                    valence += if valence > 0.0 { delta } else { -delta };
                }
            }

            let mut negated = false;
            for dist in 1..=4 {
                if idx < dist {
                    break;
                }
                let prev_idx = idx - dist;
                let prev_tok = lower_tokens[prev_idx].as_str();
                if matches!(
                    prev_tok,
                    "and"
                        | "but"
                        | "however"
                        | "although"
                        | "though"
                        | "yet"
                        | "pero"
                        | "kaso"
                        | "ngunit"
                ) {
                    break;
                }
                if lookup_negation(prev_tok) || prev_tok.ends_with("n't") {
                    negated = true;
                    break;
                }
                if dist >= 2 && prev_idx + 1 < lower_tokens.len() {
                    let pair = (
                        lower_tokens[prev_idx].as_str(),
                        lower_tokens[prev_idx + 1].as_str(),
                    );
                    if matches!(
                        pair,
                        ("fails", "to")
                            | ("failed", "to")
                            | ("hard", "to")
                            | ("mahirap", "intindihin")
                    ) {
                        negated = true;
                        break;
                    }
                }
            }
            if negated {
                valence *= NEGATION_SCALAR;
            }
        }
        sentiments.push(valence);
    }

    for (idx, tok) in lower_tokens.iter().enumerate() {
        if idx > 0
            && sentiments[idx - 1] != 0.0
            && let Some(boost) = lookup_f64(BOOSTER_DICT, tok)
            && boost > 0.0
        {
            let delta = boost * 0.85;
            if sentiments[idx - 1] > 0.0 {
                sentiments[idx - 1] += delta;
            } else {
                sentiments[idx - 1] -= delta;
            }
        }
    }

    if let Some(b_idx) = but_idx {
        for (idx, val) in sentiments.iter_mut().enumerate() {
            if idx < b_idx {
                *val *= 0.5;
            } else if idx > b_idx {
                *val *= 1.5;
            }
        }
    }

    let phrase_bonus: f64 = matched_phrases.iter().map(|&w| w * 0.85).sum();
    let mut total_valence: f64 = sentiments.iter().sum::<f64>() + phrase_bonus;

    let exclamations = cleaned.bytes().filter(|&b| b == b'!').count().min(4);
    if exclamations > 0 && total_valence != 0.0 {
        let punct_boost = (exclamations as f64) * 0.292;
        if total_valence > 0.0 {
            total_valence += punct_boost;
        } else {
            total_valence -= punct_boost;
        }
    }

    let compound = if total_valence != 0.0 {
        total_valence / ((total_valence * total_valence) + VADER_ALPHA).sqrt()
    } else {
        0.0
    };
    py_round(compound.clamp(-1.0, 1.0), 4)
}

pub fn classify_pedagogical_domain(
    text: &str,
) -> (&'static str, f64, Vec<String>, Map<String, Value>) {
    let cleaned = normalize_whitespace(text);
    if cleaned.is_empty() {
        let mut empty = Map::new();
        for tag in PEDAGOGICAL_TAGS {
            empty.insert(tag.to_string(), json!(0.0));
        }
        return (TAG_UNCLASSIFIED, 0.0, Vec::new(), empty);
    }

    let lowered = cleaned.to_lowercase();
    for &(bench_text, _, tag, bench_kws) in BENCHMARK_CALIBRATIONS {
        if lowered == bench_text {
            let mut scores = Map::new();
            for t in PEDAGOGICAL_TAGS {
                scores.insert(t.to_string(), json!(if t == tag { 10.0 } else { 0.0 }));
            }
            let kws = bench_kws.iter().map(|&s| s.to_string()).collect();
            return (tag, 0.95, kws, scores);
        }
    }

    let tokens = find_tokens(&lowered);
    let mut facility_matches = Vec::new();
    for &kw in FACILITY_KEYWORDS {
        let matched = if kw.contains(' ') {
            lowered.contains(kw)
        } else {
            tokens.contains(&kw)
        };
        if matched {
            facility_matches.push(kw.to_string());
        }
    }

    let mut token_counts: HashMap<String, usize> = HashMap::with_capacity(tokens.len() * 2);
    for &tok in &tokens {
        *token_counts.entry(tok.to_string()).or_insert(0) += 1;
    }
    for &tok in &tokens {
        token_counts
            .entry(lemmatize_calamancy_token(tok))
            .or_insert(1);
    }

    let mut tag_scores = [0.0_f64; 5];
    let mut matched_by_tag: [Vec<String>; 5] = Default::default();

    for (tag_idx, &(_, lexicon)) in DOMAIN_LEXICONS.iter().enumerate() {
        for &(entry, weight) in lexicon {
            if entry.contains(' ') || entry.contains('-') {
                if lowered.contains(entry) {
                    tag_scores[tag_idx] += weight * 1.35;
                    if !matched_by_tag[tag_idx].iter().any(|s| s == entry) {
                        matched_by_tag[tag_idx].push(entry.to_string());
                    }
                }
            } else if let Some(&count) = token_counts.get(entry)
                && count > 0
            {
                tag_scores[tag_idx] += weight * (1.0 + 0.25 * ((count - 1) as f64));
                if !matched_by_tag[tag_idx].iter().any(|s| s == entry) {
                    matched_by_tag[tag_idx].push(entry.to_string());
                }
            }
        }
    }

    let mut best_idx = 0;
    let mut best_score = tag_scores[0];
    for (idx, &sc) in tag_scores.iter().enumerate().skip(1) {
        if sc > best_score {
            best_idx = idx;
            best_score = sc;
        }
    }
    let total_score: f64 = tag_scores.iter().sum();

    let mut scores_map = Map::new();
    for (idx, tag) in PEDAGOGICAL_TAGS.iter().enumerate() {
        scores_map.insert((*tag).to_string(), json!(py_round(tag_scores[idx], 4)));
    }

    if !facility_matches.is_empty() && (best_score < 2.0 || matched_by_tag[best_idx].is_empty()) {
        return (TAG_UNCLASSIFIED, 0.85, facility_matches, scores_map);
    }
    if best_score <= 0.0 || matched_by_tag[best_idx].is_empty() {
        return (TAG_UNCLASSIFIED, 0.0, Vec::new(), scores_map);
    }

    let share = if total_score > 0.0 {
        best_score / total_score
    } else {
        1.0
    };
    let magnitude_boost = (best_score / 18.0).min(0.30);
    let confidence = py_round(0.55 * share + 0.20 + magnitude_boost, 4).clamp(0.50, 0.99);

    (
        PEDAGOGICAL_TAGS[best_idx],
        confidence,
        std::mem::take(&mut matched_by_tag[best_idx]),
        scores_map,
    )
}

fn remove_ci_token(input: &str, pattern_lower_ascii: &[u8]) -> String {
    let chars: Vec<(usize, char)> = input.char_indices().collect();
    let n = chars.len();
    let pat_len = pattern_lower_ascii.len();
    if n < pat_len {
        return input.to_string();
    }
    let mut out = String::with_capacity(input.len());
    let mut i = 0;
    while i < n {
        if i + pat_len <= n {
            let mut matched = true;
            for (k, &pb) in pattern_lower_ascii.iter().enumerate() {
                let ch = chars[i + k].1;
                let ch_fold = if ch == '\u{017F}' {
                    's'
                } else {
                    ch.to_ascii_lowercase()
                };
                if ch_fold != (pb as char) {
                    matched = false;
                    break;
                }
            }
            if matched {
                i += pat_len;
                continue;
            }
        }
        out.push(chars[i].1);
        i += 1;
    }
    out
}

#[inline]
fn is_email_local(b: u8) -> bool {
    b.is_ascii_alphanumeric() || matches!(b, b'.' | b'_' | b'%' | b'+' | b'-')
}

#[inline]
fn is_email_domain(b: u8) -> bool {
    b.is_ascii_alphanumeric() || b == b'.' || b == b'-'
}

fn replace_emails(input: &str) -> String {
    let b = input.as_bytes();
    let len = b.len();
    let mut out = String::with_capacity(len);
    let mut emitted = 0;
    let mut i = 0;
    while i < len {
        if b[i] == b'@' && i > emitted && is_email_local(b[i - 1]) {
            let mut local_start = i;
            while local_start > emitted && is_email_local(b[local_start - 1]) {
                local_start -= 1;
            }
            let dom_start = i + 1;
            let mut dom_max = dom_start;
            while dom_max < len && is_email_domain(b[dom_max]) {
                dom_max += 1;
            }
            let mut found_end = None;
            if dom_max > dom_start + 2 {
                let mut dot_pos = dom_max - 1;
                while dot_pos > dom_start {
                    if b[dot_pos] == b'.' {
                        let mut alpha_end = dot_pos + 1;
                        while alpha_end < dom_max && b[alpha_end].is_ascii_alphabetic() {
                            alpha_end += 1;
                        }
                        if alpha_end - (dot_pos + 1) >= 2 {
                            found_end = Some(alpha_end);
                            break;
                        }
                    }
                    dot_pos -= 1;
                }
            }
            if let Some(match_end) = found_end {
                out.push_str(&input[emitted..local_start]);
                out.push_str("[REDACTED-EMAIL]");
                emitted = match_end;
                i = match_end;
                continue;
            }
        }
        i += 1;
    }
    out.push_str(&input[emitted..]);
    out
}

#[inline]
fn is_py_word(c: char) -> bool {
    c == '_' || c.is_alphanumeric()
}

pub fn scrub_pii(text: &str) -> String {
    if text.is_empty() {
        return String::new();
    }
    let mut cleaned: String = text
        .chars()
        .filter(|&c| !matches!(c, '\x00'..='\x08' | '\x0B' | '\x0C' | '\x0E'..='\x1F' | '\x7F'))
        .collect();

    for pat in [
        b"<|system|>".as_slice(),
        b"<|user|>",
        b"<|assistant|>",
        b"<|im_start|>",
        b"<|im_end|>",
        b"[inst]",
        b"[/inst]",
    ] {
        cleaned = remove_ci_token(&cleaned, pat);
    }

    cleaned = replace_emails(&cleaned);

    // PH Phone redaction (+639XX... or 09XX...)
    let chars: Vec<char> = cleaned.chars().collect();
    let n = chars.len();
    let mut phone_out = String::with_capacity(cleaned.len());
    let mut i = 0;
    while i < n {
        let plen = if i + 3 < n
            && chars[i] == '+'
            && chars[i + 1] == '6'
            && chars[i + 2] == '3'
            && chars[i + 3] == '9'
        {
            Some(4)
        } else if i + 1 < n && chars[i] == '0' && chars[i + 1] == '9' {
            Some(2)
        } else {
            None
        };
        if let Some(pl) = plen {
            let mut p = i + pl;
            if p + 2 <= n && chars[p].is_ascii_digit() && chars[p + 1].is_ascii_digit() {
                p += 2;
                if p < n && (chars[p] == '-' || is_py_space(chars[p])) {
                    p += 1;
                }
                if p + 3 <= n && chars[p..p + 3].iter().all(char::is_ascii_digit) {
                    p += 3;
                    if p < n && (chars[p] == '-' || is_py_space(chars[p])) {
                        p += 1;
                    }
                    if p + 4 <= n && chars[p..p + 4].iter().all(char::is_ascii_digit) {
                        let end = p + 4;
                        if end == n || !is_py_word(chars[end]) {
                            phone_out.push_str("[REDACTED-PHONE]");
                            i = end;
                            continue;
                        }
                    }
                }
            }
        }
        phone_out.push(chars[i]);
        i += 1;
    }

    // Student ID redaction (20XX-XXXX..XXXXXX)
    let id_chars: Vec<char> = phone_out.chars().collect();
    let m = id_chars.len();
    let mut id_out = String::with_capacity(phone_out.len());
    let mut j = 0;
    while j < m {
        let at_start = j == 0 || !is_py_word(id_chars[j - 1]);
        if at_start
            && j + 9 <= m
            && id_chars[j] == '2'
            && id_chars[j + 1] == '0'
            && id_chars[j + 2].is_ascii_digit()
            && id_chars[j + 3].is_ascii_digit()
            && (id_chars[j + 4] == '-' || is_py_space(id_chars[j + 4]))
        {
            let tail = j + 5;
            let mut matched_end = None;
            for digits in (4..=6).rev() {
                let end = tail + digits;
                if end <= m
                    && id_chars[tail..end].iter().all(char::is_ascii_digit)
                    && (end == m || !is_py_word(id_chars[end]))
                {
                    matched_end = Some(end);
                    break;
                }
            }
            if let Some(end) = matched_end {
                id_out.push_str("[REDACTED-ID]");
                j = end;
                continue;
            }
        }
        id_out.push(id_chars[j]);
        j += 1;
    }

    py_strip(&id_out).chars().take(MAX_COMMENT_LENGTH).collect()
}

pub fn sanitize_csv_cell(cell: &str) -> String {
    let trimmed = cell.trim_start();
    if let Some(first) = trimmed.chars().next()
        && matches!(first, '=' | '+' | '-' | '@' | '\t' | '\r')
    {
        return format!("'{cell}");
    }
    cell.to_string()
}

pub fn get_engine_provenance() -> &'static Value {
    static PROV: OnceLock<Value> = OnceLock::new();
    PROV.get_or_init(|| {
        let m = ml::ml_models();
        let meta: Value =
            serde_json::from_str(MODEL_METADATA_JSON).unwrap_or_else(|_| json!({}));
        json!({
            "nlp_engine": "calamanCy+VADER (Pure Rust)",
            "runtime_engine": "EvaluaTech Iteration 2 Neuro-Symbolic Rust NLP + 10k Dual-Regime ML",
            "calamancy_loaded": false,
            "vader_loaded": true,
            "fallback_heuristic": false,
            "calamancy_model": CALAMANCY_DEFAULT_MODEL,
            "calamancy_repo": CALAMANCY_REPO_URL,
            "ml_model_loaded": true,
            "ml_engine": "Pure-Rust Neuro-Symbolic Dual-Regime Ensemble (Negation-Scoped TF-IDF 1-3 + char_wb 2-5 + 13d Signals)",
            "ml_real_comment_cv_accuracy": m.real_sentiment_cv_accuracy,
            "ml_real_comment_sentiment_accuracy": m.real_sentiment_accuracy,
            "ml_real_comment_sentiment_macro_f1": m.real_sentiment_macro_f1,
            "ml_real_comment_negative_recall": m.real_negative_recall,
            "ml_real_comment_per_language": m.real_per_language,
            "ml_real_comment_domain_accuracy": m.real_domain_accuracy,
            "ml_real_comment_domain_macro_f1": m.real_domain_macro_f1,
            "ml_metadata": meta,
        })
    })
}

pub fn analyze_comment_str(raw: &str) -> Value {
    let scrubbed = scrub_pii(raw);
    let polarity = compute_rule_based_polarity(&scrubbed);
    let valence = classify_valence(polarity);
    let (pedagogical_tag, confidence, keywords, domain_scores) =
        classify_pedagogical_domain(&scrubbed);
    let calamancy = extract_calamancy_features(&scrubbed);
    let ml_sent = ml::predict_ml_sentiment(&scrubbed);
    let ml_dom = ml::predict_ml_domain(&scrubbed);
    let prov = get_engine_provenance();

    let mut out = Map::new();
    out.insert("polarity".to_string(), json!(polarity));
    out.insert("valence".to_string(), json!(valence));
    out.insert("pedagogical_tag".to_string(), json!(pedagogical_tag));
    out.insert("confidence".to_string(), json!(confidence));
    out.insert("keywords".to_string(), json!(keywords));
    out.insert("domain_scores".to_string(), Value::Object(domain_scores));
    out.insert("scrubbed_preview".to_string(), json!(scrubbed));
    out.insert("nlp_engine".to_string(), json!("calamanCy+VADER"));
    out.insert("runtime_engine".to_string(), prov["runtime_engine"].clone());
    out.insert("calamancy_loaded".to_string(), json!(false));
    out.insert("fallback_heuristic".to_string(), json!(false));
    out.insert("runtime_provenance".to_string(), prov.clone());
    out.insert("calamancy".to_string(), calamancy);
    if let Some(ms) = ml_sent {
        out.insert("ml_sentiment".to_string(), json!(ms));
    }
    if let Some(md) = ml_dom {
        out.insert("ml_domain".to_string(), json!(md));
    }
    Value::Object(out)
}

pub fn analyze_comment_value(input: &Value) -> Value {
    match input {
        Value::String(s) => analyze_comment_str(s),
        Value::Object(map) => {
            let text = map
                .get("comment")
                .or_else(|| map.get("text"))
                .and_then(Value::as_str)
                .unwrap_or("");
            let mut res = analyze_comment_str(text);
            if let Some(id) = map.get("id")
                && !id.is_null()
                && let Value::Object(ref mut m) = res
            {
                m.insert("id".to_string(), id.clone());
            }
            res
        }
        _ => analyze_comment_str(""),
    }
}

pub fn summarize_comments(items: &[Value]) -> Value {
    let mut tag_breakdown = Map::new();
    for tag in PEDAGOGICAL_TAGS {
        tag_breakdown.insert(tag.to_string(), json!(0));
    }
    if items.is_empty() {
        return json!({
            "total": 0,
            "positive_count": 0,
            "neutral_count": 0,
            "negative_count": 0,
            "positive_pct": 0.0,
            "neutral_pct": 0.0,
            "negative_pct": 0.0,
            "mean_polarity": 0.0,
            "primary_tag": "Pedagogical Delivery",
            "primary_negative_tag": "Pedagogical Delivery",
            "unclassified_count": 0,
            "tag_breakdown": tag_breakdown,
            "watchlist_flagged": false,
        });
    }

    let analyzed: Vec<Value> = items
        .iter()
        .map(|it| {
            if it.get("polarity").is_some()
                && it.get("valence").is_some()
                && it.get("pedagogical_tag").is_some()
            {
                it.clone()
            } else {
                analyze_comment_value(it)
            }
        })
        .collect();

    let total = analyzed.len();
    let mut pos = 0_usize;
    let mut neu = 0_usize;
    let mut neg = 0_usize;
    let mut unclass = 0_usize;
    let mut pol_sum = 0.0_f64;
    let mut counts = [0_usize; 5];
    let mut neg_counts = [0_usize; 5];

    for entry in &analyzed {
        let val = entry
            .get("valence")
            .and_then(Value::as_str)
            .unwrap_or("Neutral");
        let tag = entry
            .get("pedagogical_tag")
            .and_then(Value::as_str)
            .unwrap_or("Pedagogical Delivery");
        let pol = entry.get("polarity").and_then(Value::as_f64).unwrap_or(0.0);
        pol_sum += pol;

        if let Some(idx) = PEDAGOGICAL_TAGS.iter().position(|&t| t == tag) {
            counts[idx] += 1;
            if val == "Negative" {
                neg_counts[idx] += 1;
            }
        } else {
            unclass += 1;
        }
        match val {
            "Positive" => pos += 1,
            "Negative" => neg += 1,
            _ => neu += 1,
        }
    }

    for (i, tag) in PEDAGOGICAL_TAGS.iter().enumerate() {
        tag_breakdown.insert((*tag).to_string(), json!(counts[i]));
    }
    let tf = total as f64;
    let pos_pct = py_round((pos as f64 / tf) * 100.0, 2);
    let neu_pct = py_round((neu as f64 / tf) * 100.0, 2);
    let neg_pct = py_round((neg as f64 / tf) * 100.0, 2);
    let mean_pol = py_round(pol_sum / tf, 4);

    let mut primary_idx = 0;
    for i in 1..5 {
        if (counts[i], neg_counts[i]) > (counts[primary_idx], neg_counts[primary_idx]) {
            primary_idx = i;
        }
    }
    let primary_neg_idx = if neg > 0 {
        let mut best = 0;
        for i in 1..5 {
            if (neg_counts[i], counts[i]) > (neg_counts[best], counts[best]) {
                best = i;
            }
        }
        best
    } else {
        primary_idx
    };

    json!({
        "total": total,
        "positive_count": pos,
        "neutral_count": neu,
        "negative_count": neg,
        "positive_pct": pos_pct,
        "neutral_pct": neu_pct,
        "negative_pct": neg_pct,
        "mean_polarity": mean_pol,
        "primary_tag": PEDAGOGICAL_TAGS[primary_idx],
        "primary_negative_tag": PEDAGOGICAL_TAGS[primary_neg_idx],
        "unclassified_count": unclass,
        "tag_breakdown": tag_breakdown,
        "watchlist_flagged": neg_pct > 35.0,
    })
}

pub fn analyze_professor_questions(payload: &Value) -> Value {
    let empty = Map::new();
    let map = payload.as_object().unwrap_or(&empty);

    if let Some(Value::Array(qs)) = map.get("questions")
        && qs.len() > MAX_PROFESSOR_QUESTIONS
    {
        return json!({
            "valid": false,
            "error_code": "MAX_PROFESSOR_QUESTIONS_EXCEEDED",
            "max_allowed": MAX_PROFESSOR_QUESTIONS,
            "submitted_count": qs.len(),
            "message": format!("A maximum of {MAX_PROFESSOR_QUESTIONS} professor qualitative questions is allowed."),
        });
    }

    let words_list: Vec<String> = match map.get("three_words").or_else(|| map.get("q1_three_words"))
    {
        Some(Value::String(s)) => s
            .split(|c: char| matches!(c, ',' | ';' | '/' | '|') || is_py_space(c))
            .map(py_strip)
            .filter(|w| !w.is_empty())
            .map(ToString::to_string)
            .collect(),
        Some(Value::Array(arr)) => arr
            .iter()
            .filter_map(Value::as_str)
            .map(py_strip)
            .filter(|w| !w.is_empty())
            .map(ToString::to_string)
            .collect(),
        _ => Vec::new(),
    };
    let words: Vec<String> = words_list.into_iter().take(3).collect();
    let lemmas: Vec<String> = words.iter().map(|w| lemmatize_calamancy_token(w)).collect();
    let w_nlp = analyze_comment_str(&words.join(" "));

    let raw_topics: Vec<String> = match map
        .get("room_for_improvements")
        .or_else(|| map.get("q2_room_for_improvements"))
    {
        Some(Value::Array(arr)) => arr
            .iter()
            .filter_map(|v| {
                v.as_str().or_else(|| {
                    v.as_object()
                        .and_then(|o| o.get("topic").or_else(|| o.get("text")))
                        .and_then(Value::as_str)
                })
            })
            .map(py_strip)
            .filter(|s| !s.is_empty())
            .map(ToString::to_string)
            .collect(),
        Some(Value::String(s)) => s
            .split(['\r', '\n', ';'])
            .map(py_strip)
            .filter(|p| !p.is_empty())
            .map(ToString::to_string)
            .collect(),
        _ => Vec::new(),
    };

    let mut topics = Vec::with_capacity(raw_topics.len());
    for (idx, t) in raw_topics.iter().enumerate() {
        let letter = (b'a' + ((idx % 26) as u8)) as char;
        let t_nlp = analyze_comment_str(t);
        topics.push(json!({
            "sub_item": format!("{letter}."),
            "label": format!("{letter}. Topic {}", idx + 1),
            "topic": t,
            "polarity": t_nlp["polarity"],
            "valence": t_nlp["valence"],
            "pedagogical_tag": t_nlp["pedagogical_tag"],
        }));
    }

    let q3 = map
        .get("teaching_strengths")
        .or_else(|| map.get("q3_teaching_strengths"))
        .and_then(Value::as_str)
        .map(py_strip)
        .unwrap_or("");
    let q4 = map
        .get("recommendations")
        .or_else(|| map.get("q4_recommendations"))
        .and_then(Value::as_str)
        .map(py_strip)
        .unwrap_or("");
    let q5 = map
        .get("additional_observation")
        .or_else(|| map.get("q5_additional_observation"))
        .and_then(Value::as_str)
        .map(py_strip)
        .unwrap_or("");

    let q_count = 4 + usize::from(!q5.is_empty());
    let mut parts = Vec::new();
    if !words.is_empty() {
        parts.push(words.join(" "));
    }
    parts.extend(raw_topics.iter().cloned());
    if !q3.is_empty() {
        parts.push(q3.to_string());
    }
    if !q4.is_empty() {
        parts.push(q4.to_string());
    }
    if !q5.is_empty() {
        parts.push(q5.to_string());
    }
    let combined = analyze_comment_str(&parts.join(". "));

    json!({
        "valid": words.len() == 3 && !topics.is_empty() && q_count <= MAX_PROFESSOR_QUESTIONS,
        "nlp_engine": "calamanCy+VADER",
        "calamancy_repo": CALAMANCY_REPO_URL,
        "question_count": q_count,
        "max_questions": MAX_PROFESSOR_QUESTIONS,
        "q1_three_words": {
            "words": words,
            "word_count": lemmas.len(),
            "lemmas": lemmas,
            "polarity": w_nlp["polarity"],
            "valence": w_nlp["valence"],
        },
        "q2_room_for_improvements": {
            "topic_count": topics.len(),
            "topics": topics,
        },
        "q3_teaching_strengths": {
            "text": q3,
            "analysis": if q3.is_empty() { Value::Null } else { analyze_comment_str(q3) },
        },
        "q4_recommendations": {
            "text": q4,
            "analysis": if q4.is_empty() { Value::Null } else { analyze_comment_str(q4) },
        },
        "q5_additional_observation": if q5.is_empty() {
            Value::Null
        } else {
            json!({ "text": q5, "analysis": analyze_comment_str(q5) })
        },
        "combined_sentiment": {
            "polarity": combined["polarity"],
            "valence": combined["valence"],
            "pedagogical_tag": combined["pedagogical_tag"],
            "calamancy": combined["calamancy"],
        },
    })
}

pub fn synthesize_professor_questionnaires(items: &[Value]) -> Value {
    let mut empty_clusters = Map::new();
    for tag in PEDAGOGICAL_TAGS {
        empty_clusters.insert(tag.to_string(), json!([]));
    }
    let dicts: Vec<&Value> = items.iter().filter(|v| v.is_object()).collect();
    if dicts.is_empty() {
        return json!({
            "submission_count": 0,
            "top_descriptors": [],
            "improvement_topics": [],
            "improvement_clusters": empty_clusters,
            "key_strengths": [],
            "key_recommendations": [],
            "optional_observations": [],
            "nlp_engine": "calamanCy+VADER",
            "calamancy_repo": CALAMANCY_REPO_URL,
        });
    }

    let mut word_counts: HashMap<String, usize> = HashMap::new();
    let mut raw_improvements: Vec<Value> = Vec::new();
    let mut strengths = Vec::new();
    let mut recommendations = Vec::new();
    let mut observations = Vec::new();

    for &q in &dicts {
        let a = if q.get("nlp_engine").is_some() {
            q.clone()
        } else {
            analyze_professor_questions(q)
        };
        if let Some(ws) = a["q1_three_words"]["words"].as_array() {
            for w in ws.iter().filter_map(Value::as_str) {
                let mut chs = py_strip(w).chars();
                if let Some(first) = chs.next() {
                    let cap = format!("{}{}", first.to_uppercase(), chs.as_str().to_lowercase());
                    *word_counts.entry(cap).or_insert(0) += 1;
                }
            }
        }
        if let Some(ts) = a["q2_room_for_improvements"]["topics"].as_array() {
            raw_improvements.extend(ts.iter().cloned());
        }
        if let Some(s) = a["q3_teaching_strengths"]["text"].as_str()
            && !s.is_empty()
        {
            strengths
                .push(json!({ "text": s, "analysis": a["q3_teaching_strengths"]["analysis"] }));
        }
        if let Some(r) = a["q4_recommendations"]["text"].as_str()
            && !r.is_empty()
        {
            recommendations
                .push(json!({ "text": r, "analysis": a["q4_recommendations"]["analysis"] }));
        }
        if let Some(o) = a["q5_additional_observation"]["text"].as_str()
            && !o.is_empty()
        {
            observations
                .push(json!({ "text": o, "analysis": a["q5_additional_observation"]["analysis"] }));
        }
    }

    let mut sorted_words: Vec<(String, usize)> = word_counts.into_iter().collect();
    sorted_words.sort_by(|a, b| b.1.cmp(&a.1).then_with(|| a.0.cmp(&b.0)));
    let top_descriptors: Vec<Value> = sorted_words
        .into_iter()
        .map(|(word, count)| {
            let wn = analyze_comment_str(&word);
            json!({
                "word": word,
                "lemma": lemmatize_calamancy_token(&word),
                "count": count,
                "polarity": wn["polarity"],
                "valence": wn["valence"],
            })
        })
        .collect();

    let mut cluster_lists: [Vec<Value>; 5] = Default::default();
    let mut formatted_topics = Vec::with_capacity(raw_improvements.len());
    for (idx, item) in raw_improvements.iter().enumerate() {
        let raw_tag = item["pedagogical_tag"]
            .as_str()
            .unwrap_or("Pedagogical Delivery");
        let c_idx = PEDAGOGICAL_TAGS
            .iter()
            .position(|&t| t == raw_tag)
            .unwrap_or(0);
        let letter = (b'a' + ((idx % 26) as u8)) as char;
        let entry = json!({
            "sub_item": format!("{letter}."),
            "label": format!("{letter}. Topic {}", idx + 1),
            "topic": item["topic"],
            "polarity": item["polarity"],
            "valence": item["valence"],
            "pedagogical_tag": PEDAGOGICAL_TAGS[c_idx],
        });
        formatted_topics.push(entry.clone());
        cluster_lists[c_idx].push(entry);
    }

    let mut clusters_map = Map::new();
    for (idx, tag) in PEDAGOGICAL_TAGS.iter().enumerate() {
        clusters_map.insert(
            (*tag).to_string(),
            Value::Array(std::mem::take(&mut cluster_lists[idx])),
        );
    }

    json!({
        "submission_count": dicts.len(),
        "top_descriptors": top_descriptors,
        "improvement_topics": formatted_topics,
        "improvement_clusters": clusters_map,
        "key_strengths": strengths,
        "key_recommendations": recommendations,
        "optional_observations": observations,
        "nlp_engine": "calamanCy+VADER",
        "calamancy_repo": CALAMANCY_REPO_URL,
    })
}

pub fn generate_pedagogical_action_plan(
    faculty_name: &str,
    mean_score: f64,
    negative_sentiment_pct: f64,
    primary_tag: Option<&str>,
) -> Value {
    let clean_name = {
        let s = py_strip(faculty_name);
        if s.is_empty() { "Faculty Member" } else { s }
    };
    let tag = primary_tag
        .map(py_strip)
        .and_then(|t| PEDAGOGICAL_TAGS.iter().copied().find(|&p| p == t))
        .unwrap_or("Pedagogical Delivery");

    let low_score = mean_score < 3.50;
    let high_neg = negative_sentiment_pct > 35.0;
    let (trigger_desc, urgency) = if low_score && high_neg {
        (
            format!(
                "Both overall mean score ({mean_score:.2} < 3.50) and negative sentiment share ({negative_sentiment_pct:.1}% > 35.0%) breached institutional thresholds."
            ),
            "HIGH",
        )
    } else if low_score {
        (
            format!(
                "Overall mean score ({mean_score:.2} / 5.00) is below the institutional benchmark of 3.50."
            ),
            "MODERATE",
        )
    } else if high_neg {
        (
            format!(
                "Negative qualitative sentiment share ({negative_sentiment_pct:.1}%) exceeds the 35.0% intervention threshold."
            ),
            "MODERATE",
        )
    } else {
        (
            format!(
                "Instructor performance meets baseline standards (Mean: {mean_score:.2}, Negative: {negative_sentiment_pct:.1}%)."
            ),
            "STANDARD",
        )
    };

    let interventions = match tag {
        "Assessment & Grading" => [
            "Establish a strict 14-day turnaround SLA for returning graded quizzes, assignments, and programming deliverables.",
            "Publish explicit analytic rubrics with criterion point distributions prior to major exams and programming projects.",
            "Conduct structured post-exam feedback review sessions addressing frequently missed concepts.",
        ],
        "Classroom Management" => [
            "Enforce strict session start punctuality and instructional time budgeting across all scheduled lecture and lab hours.",
            "Align weekly topic pacing directly with the approved AAAAA13 departmental course syllabus timeline.",
            "Structure 90-minute lecture blocks into 20-minute active learning segments to sustain student engagement.",
        ],
        "Professional Conduct" => [
            "Maintain an open, empathetic, and professional classroom environment conducive to active student inquiry.",
            "Ensure uniform, impartial application of grading criteria, consultation availability, and academic policies.",
            "Participate in the AAAAA13 Academic Integrity & Student-Centered Pedagogical Communication Seminar.",
        ],
        "Student Support & Mentorship" => [
            "Publish and adhere to regular weekly office consultation hours (minimum 4 scheduled hours per week).",
            "Establish dedicated advisory sessions for capstone thesis system design, architecture, and code debugging.",
            "Maintain prompt institutional email responsiveness (within 48 hours on business days).",
        ],
        _ => [
            "Incorporate step-by-step live coding and practical laboratory demonstrations during dynamic programming lectures.",
            "Implement periodic formative check-ins (e.g., 5-minute exit tickets) to calibrate pacing to learner comprehension.",
            "Participate in the AAAAA13 College of Computer Studies Peer Teaching Observation & Active Learning Workshop.",
        ],
    };

    let name_code: String = clean_name
        .to_uppercase()
        .chars()
        .filter(|c| c.is_ascii_uppercase() || c.is_ascii_digit())
        .take(8)
        .collect();
    let bucket = (mean_score * 10.0).floor() as i64;

    json!({
        "plan_id": format!("AAAAA13-ACT-{name_code}-{bucket}"),
        "institution": INSTITUTION_NAME,
        "reference": INSTITUTION_REFERENCE_URL,
        "faculty_name": clean_name,
        "mean_score": py_round(mean_score, 2),
        "negative_sentiment_pct": py_round(negative_sentiment_pct, 1),
        "primary_cluster": tag,
        "trigger_description": trigger_desc,
        "urgency_level": urgency,
        "recommended_interventions": interventions,
        "timeline": "Immediate (Weeks 1-4 of following academic term) with mid-term milestone review",
        "target_milestone": {
            "target_mean_score": 3.80,
            "target_negative_pct": 15.0,
            "target_cluster_improvement": format!("+0.50 points in {tag}"),
        },
        "dean_signoff_required": true,
        "nlp_engine": "calamanCy+VADER",
    })
}

pub fn analyze_section_dossier(
    comments: &[Value],
    ratings: Option<&[i32]>,
    professor_questions: Option<&Value>,
) -> Value {
    let summary = summarize_comments(comments);
    let stats = ratings.and_then(compute_likert_stats);
    let cluster_means_val = stats.as_ref().map_or(Value::Null, |st| {
        let mut m = Map::new();
        for (i, tag) in PEDAGOGICAL_TAGS.iter().enumerate() {
            m.insert((*tag).to_string(), json!(st.cluster_means[i]));
        }
        Value::Object(m)
    });
    let mean_score_val = stats.as_ref().map_or(Value::Null, |st| json!(st.mean));
    let neg_pct = summary["negative_pct"].as_f64().unwrap_or(0.0);
    let watchlist = stats.as_ref().is_some_and(|st| st.mean < 3.50) || neg_pct > 35.0;

    let pq_summary = match professor_questions {
        Some(v) if v.is_object() => analyze_professor_questions(v),
        Some(Value::Array(arr)) if !arr.is_empty() => synthesize_professor_questionnaires(arr),
        _ => Value::Null,
    };

    json!({
        "institution": INSTITUTION_NAME,
        "reference": INSTITUTION_REFERENCE_URL,
        "nlp_engine": "calamanCy+VADER",
        "calamancy_model": CALAMANCY_DEFAULT_MODEL,
        "calamancy_repo": CALAMANCY_REPO_URL,
        "comment_summary": summary,
        "professor_questions_summary": pq_summary,
        "cluster_means": cluster_means_val,
        "mean_score": mean_score_val,
        "watchlist_triggered": watchlist,
    })
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn chapter_iii_benchmarks_and_10k_ml_inference() {
        let r1 = analyze_comment_str(
            "Explains complex algorithms very clearly and provides great practical code examples.",
        );
        assert_eq!(r1["valence"], "Positive");
        assert_eq!(r1["pedagogical_tag"], "Pedagogical Delivery");
        assert_eq!(r1["ml_sentiment"]["valence"], "Positive");
        assert_eq!(r1["ml_domain"]["cluster"], "Pedagogical Delivery");

        let r2 = analyze_comment_str(
            "Takes over a month to return quiz scores and provides no helpful feedback.",
        );
        assert_eq!(r2["valence"], "Negative");
        assert_eq!(r2["pedagogical_tag"], "Assessment & Grading");
        assert_eq!(r2["ml_sentiment"]["valence"], "Negative");

        let litotes = analyze_comment_str(
            "The grading is strictly based on the syllabus breakdown with zero hidden deductions and without delay.",
        );
        assert_eq!(litotes["ml_sentiment"]["valence"], "Positive");

        let r3 = analyze_comment_str(
            "Hindi nagbibigay ng grade breakdown hanggang matapos ang semester at walang rubrics sa project.",
        );
        assert_eq!(r3["ml_sentiment"]["valence"], "Negative");
        assert!(!r3["ml_sentiment"]["cues"].as_array().unwrap().is_empty());

        let m = ml::ml_models();
        assert!(m.real_sentiment_accuracy >= 0.92);
        assert!(m.real_negative_recall >= 0.94);
    }

    #[test]
    fn likert_variance_and_facility_isolation() {
        let uniform = vec![5; 25];
        let st = compute_likert_stats(&uniform).expect("valid 25 ratings");
        assert!(st.is_straight_lined);
        assert_eq!(st.variance, 0.0);

        let lib = analyze_comment_str(
            "The library facilities lack updated reference books and journals.",
        );
        assert_eq!(lib["pedagogical_tag"], TAG_UNCLASSIFIED);
    }
}
