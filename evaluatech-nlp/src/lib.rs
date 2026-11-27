//! Shared non-ML statistical, CSV-hygiene, and defense-in-depth PII-redaction utilities.
//!
//! All natural language processing (`calamanCy` + `spaCy`), sentiment polarity (`vaderSentiment`),
//! and machine learning (`scikit-learn` Dual-Regime Ensemble) execute in Python (`nlp-python/`).

use serde::{Deserialize, Serialize};

pub const INSTITUTION_NAME: &str = "SCHOOL AAAAA13";
pub const INSTITUTION_REFERENCE_URL: &str = "https://aaaaa13.edu/";
pub const MAX_BATCH_SIZE: usize = 250;
pub const MAX_COMMENT_LENGTH: usize = 2000;
pub const MAX_PROFESSOR_QUESTIONS: usize = 5;
pub const TAG_UNCLASSIFIED: &str = "Unclassified / Needs Review";

pub const PEDAGOGICAL_TAGS: [&str; 5] = [
    "Pedagogical Delivery",
    "Assessment & Grading",
    "Classroom Management",
    "Professional Conduct",
    "Student Support & Mentorship",
];

#[derive(Clone, Debug, Serialize, Deserialize)]
pub struct LikertStats {
    pub count: usize,
    pub mean: f64,
    pub variance: f64,
    pub is_straight_lined: bool,
    pub cluster_means: [f64; 5],
}

pub fn py_round(x: f64, decimals: usize) -> f64 {
    if !x.is_finite() {
        return x;
    }
    let parsed = format!("{x:.decimals$}").parse::<f64>().unwrap_or(x);
    if parsed == 0.0 { 0.0 } else { parsed }
}

/// Compute population mean, population variance, straight-lining flag, and 5-cluster means for 20 or 25 ratings.
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

pub fn sanitize_csv_cell(cell: &str) -> String {
    let trimmed = cell.trim_start();
    if let Some(first) = trimmed.chars().next()
        && matches!(first, '=' | '+' | '-' | '@' | '\t' | '\r')
    {
        return format!("'{cell}");
    }
    cell.to_string()
}

#[inline]
fn is_space(c: char) -> bool {
    c.is_whitespace() || matches!(c, '\x1c'..='\x1f')
}

#[inline]
fn is_word(c: char) -> bool {
    c == '_' || c.is_alphanumeric()
}

#[inline]
fn is_email_local(b: u8) -> bool {
    b.is_ascii_alphanumeric() || matches!(b, b'.' | b'_' | b'%' | b'+' | b'-')
}

#[inline]
fn is_email_domain(b: u8) -> bool {
    b.is_ascii_alphanumeric() || b == b'.' || b == b'-'
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
                if chars[i + k].1.to_ascii_lowercase() != (pb as char) {
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

/// Defense-in-depth PII redaction prior to SQLite storage or Python NLP IPC.
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
                if p < n && (chars[p] == '-' || is_space(chars[p])) {
                    p += 1;
                }
                if p + 3 <= n && chars[p..p + 3].iter().all(char::is_ascii_digit) {
                    p += 3;
                    if p < n && (chars[p] == '-' || is_space(chars[p])) {
                        p += 1;
                    }
                    if p + 4 <= n && chars[p..p + 4].iter().all(char::is_ascii_digit) {
                        let end = p + 4;
                        if end == n || !is_word(chars[end]) {
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

    let id_chars: Vec<char> = phone_out.chars().collect();
    let m = id_chars.len();
    let mut id_out = String::with_capacity(phone_out.len());
    let mut j = 0;
    while j < m {
        let at_start = j == 0 || !is_word(id_chars[j - 1]);
        if at_start
            && j + 9 <= m
            && id_chars[j] == '2'
            && id_chars[j + 1] == '0'
            && id_chars[j + 2].is_ascii_digit()
            && id_chars[j + 3].is_ascii_digit()
            && (id_chars[j + 4] == '-' || is_space(id_chars[j + 4]))
        {
            let tail = j + 5;
            let mut matched_end = None;
            for digits in (4..=6).rev() {
                let end = tail + digits;
                if end <= m
                    && id_chars[tail..end].iter().all(char::is_ascii_digit)
                    && (end == m || !is_word(id_chars[end]))
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

    id_out
        .trim_matches(is_space)
        .chars()
        .take(MAX_COMMENT_LENGTH)
        .collect()
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn likert_stats_and_pii_hygiene_work() {
        let uniform = vec![5; 25];
        let st = compute_likert_stats(&uniform).expect("valid 25 ratings");
        assert!(st.is_straight_lined);
        assert_eq!(st.variance, 0.0);
        assert_eq!(st.mean, 5.0);

        let clean = scrub_pii("Contact 2024-10892 at juan@aaaaa13.edu or 0917-123-4567");
        assert!(!clean.contains("2024-10892"));
        assert!(!clean.contains("juan@aaaaa13.edu"));
        assert!(clean.contains("[REDACTED-ID]"));
        assert!(clean.contains("[REDACTED-EMAIL]"));
        assert!(clean.contains("[REDACTED-PHONE]"));
    }
}
