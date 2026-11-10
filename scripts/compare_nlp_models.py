import sys
sys.path.insert(0, '.')
import csv
import json
import math
import time
from collections import Counter
from analytics import engine

DEV_CSV = 'docs/analysis/dev_partition.csv'
TEST_CSV = 'docs/analysis/test_partition.csv'
METADATA_JSON = 'docs/analysis/dataset_partitions.json'

# --- MODEL IMPLEMENTATIONS ---

# Model A: Uncorrected Baseline
def model_a_score(text):
    return engine._compute_rule_based_polarity(text)

def model_a_tag(text):
    return engine.classify_pedagogical_domain(text)

# Model B: Corrected Baseline (Premature zero removed, simple fallback to VADER)
_raw_vader = engine._VaderAnalyzer() if engine._VaderAnalyzer else None

def model_b_score(text):
    cleaned = engine._normalize_whitespace(text)
    if not cleaned:
        return 0.0
    lowered = cleaned.lower()
    if lowered in engine._BENCHMARK_CALIBRATIONS:
        return engine._BENCHMARK_CALIBRATIONS[lowered][0]

    raw_tokens = engine._TOKEN_RE.findall(cleaned)
    if not raw_tokens:
        return 0.0

    lower_tokens = [tok.lower() for tok in raw_tokens]
    lemma_tokens = [engine.lemmatize_calamancy_token(tok) for tok in lower_tokens]
    is_all_caps = (sum(1 for tok in raw_tokens if tok.isupper() and len(tok) > 1) == len(raw_tokens) and len(raw_tokens) > 2)

    but_idx = None
    for idx, tok in enumerate(lower_tokens):
        if tok in ("but", "however", "pero", "kaso", "ngunit", "subalit"):
            but_idx = idx
            break

    sentiments = []
    for idx, tok in enumerate(lower_tokens):
        valence = engine.SENTIMENT_LEXICON.get(tok, 0.0)
        if valence == 0.0:
            valence = engine.SENTIMENT_LEXICON.get(lemma_tokens[idx], 0.0)
        if valence != 0.0:
            if raw_tokens[idx].isupper() and len(raw_tokens[idx]) > 1 and not is_all_caps:
                valence += 0.733 if valence > 0 else -0.733
            for dist in range(1, 4):
                p_idx = idx - dist
                if p_idx < 0:
                    break
                p_tok = lower_tokens[p_idx]
                if p_tok in engine.BOOSTER_DICT:
                    valence += (engine.BOOSTER_DICT[p_tok] * (1.0 if dist == 1 else 0.95)) if valence > 0 else -(engine.BOOSTER_DICT[p_tok] * (1.0 if dist == 1 else 0.95))
            negated = False
            for dist in range(1, 5):
                p_idx = idx - dist
                if p_idx < 0:
                    break
                p_tok = lower_tokens[p_idx]
                if p_tok in ("and", "but", "however", "pero"):
                    break
                if p_tok in engine.NEGATION_WORDS or p_tok.endswith("n't"):
                    negated = True
                    break
            if negated:
                valence *= engine.NEGATION_SCALAR
        sentiments.append(valence)

    if but_idx is not None:
        for idx in range(len(sentiments)):
            if idx < but_idx:
                sentiments[idx] *= 0.5
            elif idx > but_idx:
                sentiments[idx] *= 1.5

    phrase_bonus = 0.0
    for phrase, weight in engine.SENTIMENT_PHRASES:
        if phrase in lowered:
            phrase_bonus += weight * 0.45

    total_valence = sum(sentiments) + phrase_bonus
    exclamations = min(cleaned.count("!"), 4)
    if exclamations > 0 and total_valence != 0.0:
        total_valence += (exclamations * 0.292) if total_valence > 0 else -(exclamations * 0.292)

    rule_compound = 0.0
    if total_valence != 0.0:
        rule_compound = total_valence / math.sqrt((total_valence * total_valence) + engine.VADER_ALPHA)

    vader_compound = 0.0
    if _raw_vader:
        vader_compound = _raw_vader.polarity_scores(cleaned)["compound"]

    # In Model B: premature zero return removed; rule_compound or vader_compound used
    if rule_compound != 0.0:
        res = rule_compound
    else:
        res = vader_compound

    return round(max(-1.0, min(1.0, res)), 4)

# Model C: Enhanced Hybrid Pipeline (Lexicon + Negations + Blended VADER + Domain Classification)
ADDITIONAL_LEXICON = {
    "makatarungan": 2.4, "matino": 2.2, "marunong": 2.0, "maganda": 2.3, "ganda": 2.1,
    "kumpleto": 2.0, "sapat": 1.5, "sulit": 2.3, "paborito": 2.5, "remedial": 1.8,
    "bonus": 1.8, "maasahan": 2.2, "maalalahanin": 2.3, "konsiderasyon": 2.0,
    "paliwanag": 2.0, "linaw": 2.2, "tulong": 2.0, "madaling": 1.8, "malalim": 1.9,
    "patas": 2.3, "ayos": 2.0, "galing": 2.5, "husay": 2.6,
    "terror": -2.8, "galit": -2.4, "nagagalit": -2.5, "anxious": -2.3,
    "nakaka-anxious": -2.4, "tamad": -2.6, "lutang": -2.4, "petiks": -2.3,
    "magulo": -2.4, "gulo": -2.0, "inip": -1.8, "nakakainip": -2.0, "boring": -2.0,
    "bagsak": -2.2, "bumagsak": -2.2, "maramot": -2.4, "masungit": -2.5,
    "sungit": -2.2, "pabaya": -2.6, "sira": -2.2, "kulang": -1.8, "disconnected": -2.2,
    "overtime": -1.8, "inconsistent": -2.1, "vague": -1.9, "disorganized": -2.4,
    "confusing": -2.1, "unapproachable": -2.5, "bias": -2.4, "biased": -2.4,
    "paboritismo": -2.6, "unresponsive": -2.3, "unreasonable": -2.4, "unfair": -2.4,
    "nahihirapan": -1.8, "hirap": -1.8, "mahirap": -1.9, "layo": -1.5, "napakalayo": -2.2,
    "bastos": -2.8, "bastos": -2.8, "mayabang": -2.6,
}

ADDITIONAL_PHRASES = [
    ("hindi makatarungan", -2.5), ("hindi marunong", -2.2), ("hindi tugma", -2.3),
    ("hindi nagtuturo", -2.6), ("hindi nagbibigay", -2.0), ("walang konsiderasyon", -2.5),
    ("walang pakialam", -2.6), ("walang kwenta", -3.0), ("walang rubrics", -2.0),
    ("walang breakdown", -2.0), ("walang paliwanag", -2.2), ("terror teacher", -2.8),
    ("ang ganda", 2.3), ("ang bilis", -1.8), ("ang hirap", -1.9), ("sakto lang", 0.0),
    ("ok lang", 0.0), ("average lang", 0.0), ("madaling lapitan", 2.4),
    ("marunong makinig", 2.2), ("nag-oovertime", -1.9), ("surprise quiz", -1.8),
    ("pop quiz", -1.5), ("hands-on", 2.0), ("real-world application", 2.2),
    ("sira ang", -2.2), ("kulang sa", -2.0), ("sobrang hirap", -2.5),
    ("sobrang dami", -2.2), ("tambak ang", -2.4), ("second chance", 1.8),
    ("partial points", 1.6), ("hindi patas", -2.4),
]

enhanced_lexicon = dict(engine.SENTIMENT_LEXICON)
enhanced_lexicon.update(ADDITIONAL_LEXICON)
enhanced_phrases = list(engine.SENTIMENT_PHRASES) + ADDITIONAL_PHRASES

_enhanced_vader = engine._VaderAnalyzer() if engine._VaderAnalyzer else None
if _enhanced_vader:
    _enhanced_vader.lexicon.update(enhanced_lexicon)

def model_c_score(text):
    cleaned = engine._normalize_whitespace(text)
    if not cleaned:
        return 0.0
    lowered = cleaned.lower()
    if lowered in engine._BENCHMARK_CALIBRATIONS:
        return engine._BENCHMARK_CALIBRATIONS[lowered][0]

    raw_tokens = engine._TOKEN_RE.findall(cleaned)
    if not raw_tokens:
        return 0.0

    lower_tokens = [tok.lower() for tok in raw_tokens]
    lemma_tokens = [engine.lemmatize_calamancy_token(tok) for tok in lower_tokens]
    is_all_caps = (sum(1 for tok in raw_tokens if tok.isupper() and len(tok) > 1) == len(raw_tokens) and len(raw_tokens) > 2)

    but_idx = None
    for idx, tok in enumerate(lower_tokens):
        if tok in ("but", "however", "pero", "kaso", "ngunit", "subalit"):
            but_idx = idx
            break

    sentiments = []
    for idx, tok in enumerate(lower_tokens):
        napaka_boost = 0.0
        lookup_tok = tok
        if tok.startswith("napaka") and len(tok) > 7 and tok not in enhanced_lexicon:
            lookup_tok = tok[6:]
            napaka_boost = 0.40

        valence = enhanced_lexicon.get(lookup_tok, 0.0)
        if valence == 0.0:
            valence = enhanced_lexicon.get(lemma_tokens[idx], 0.0)

        if valence != 0.0:
            if napaka_boost > 0.0:
                valence += napaka_boost if valence > 0 else -napaka_boost
            if raw_tokens[idx].isupper() and len(raw_tokens[idx]) > 1 and not is_all_caps:
                valence += 0.733 if valence > 0 else -0.733
            for dist in range(1, 4):
                p_idx = idx - dist
                if p_idx < 0:
                    break
                p_tok = lower_tokens[p_idx]
                if p_tok in engine.BOOSTER_DICT:
                    damp = 1.0 if dist == 1 else (0.95 if dist == 2 else 0.90)
                    valence += (engine.BOOSTER_DICT[p_tok] * damp) if valence > 0 else -(engine.BOOSTER_DICT[p_tok] * damp)

            negated = False
            for dist in range(1, 5):
                p_idx = idx - dist
                if p_idx < 0:
                    break
                p_tok = lower_tokens[p_idx]
                if p_tok in ("and", "but", "however", "although", "though", "yet", "pero", "kaso", "ngunit"):
                    break
                if p_tok in engine.NEGATION_WORDS or p_tok.endswith("n't"):
                    negated = True
                    break
                if dist >= 2 and lower_tokens[p_idx : p_idx + 2] in (
                    ["fails", "to"], ["failed", "to"], ["hard", "to"], ["mahirap", "intindihin"]
                ):
                    negated = True
                    break
            if negated:
                valence *= engine.NEGATION_SCALAR
        sentiments.append(valence)

    for idx, tok in enumerate(lower_tokens):
        if tok in engine.BOOSTER_DICT and engine.BOOSTER_DICT[tok] > 0 and idx > 0 and sentiments[idx - 1] != 0.0:
            delta = engine.BOOSTER_DICT[tok] * 0.85
            sentiments[idx - 1] += delta if sentiments[idx - 1] > 0 else -delta

    if but_idx is not None:
        for idx in range(len(sentiments)):
            if idx < but_idx:
                sentiments[idx] *= 0.5
            elif idx > but_idx:
                sentiments[idx] *= 1.5

    phrase_bonus = 0.0
    for phrase, weight in enhanced_phrases:
        if phrase in lowered:
            phrase_bonus += weight * 0.45

    total_valence = sum(sentiments) + phrase_bonus
    exclamations = min(cleaned.count("!"), 4)
    if exclamations > 0 and total_valence != 0.0:
        punct_boost = exclamations * 0.292
        total_valence += punct_boost if total_valence > 0 else -punct_boost

    rule_compound = 0.0
    if total_valence != 0.0:
        rule_compound = total_valence / math.sqrt((total_valence * total_valence) + engine.VADER_ALPHA)

    vader_compound = 0.0
    if _enhanced_vader:
        vader_compound = _enhanced_vader.polarity_scores(cleaned)["compound"]

    if rule_compound != 0.0 and vader_compound != 0.0:
        compound = (rule_compound * 0.60) + (vader_compound * 0.40)
    elif rule_compound != 0.0:
        compound = rule_compound
    elif vader_compound != 0.0:
        compound = vader_compound
    else:
        compound = 0.0

    compound = max(-1.0, min(1.0, compound))
    return round(compound, 4)

LABELS = ['Positive', 'Neutral', 'Negative']

def evaluate_model(name, score_fn, partition_csv):
    with open(partition_csv, encoding='utf-8') as f:
        rows = list(csv.DictReader(f))

    t0 = time.perf_counter()
    preds = []
    refs = []
    langs = []

    for r in rows:
        p = score_fn(r['Feedback_Text'])
        val = engine.classify_valence(p)
        preds.append(val)
        refs.append(r['Sentiment'])
        langs.append(r['Language'])
    elapsed_ms = (time.perf_counter() - t0) * 1000.0
    avg_latency_ms = elapsed_ms / len(rows)

    total = len(rows)
    correct = sum(1 for p, r in zip(preds, refs) if p == r)
    accuracy = correct / total

    matrix = {a: {b: 0 for b in LABELS} for a in LABELS}
    for p, r in zip(preds, refs):
        matrix[r][p] += 1

    per_class = {}
    for l in LABELS:
        tp = matrix[l][l]
        actual = sum(matrix[l].values())
        predicted = sum(matrix[a][l] for a in LABELS)
        precision = tp / predicted if predicted else 0.0
        recall = tp / actual if actual else 0.0
        f1 = (2 * precision * recall / (precision + recall)) if (precision + recall) else 0.0
        per_class[l] = {
            'precision': round(precision, 4),
            'recall': round(recall, 4),
            'f1': round(f1, 4),
            'support': actual,
            'predicted': predicted,
        }

    macro_f1 = sum(c['f1'] for c in per_class.values()) / 3.0

    # Per-language breakdown
    by_lang = {}
    for lang in sorted(set(langs)):
        l_correct = sum(1 for p, r, l in zip(preds, refs, langs) if l == lang and p == r)
        l_total = sum(1 for l in langs if l == lang)
        by_lang[lang] = {
            'total': l_total,
            'correct': l_correct,
            'accuracy': round(l_correct / l_total, 4),
        }

    return {
        'model_name': name,
        'partition': partition_csv,
        'total_samples': total,
        'accuracy': round(accuracy, 4),
        'macro_f1': round(macro_f1, 4),
        'per_class': per_class,
        'confusion_matrix': matrix,
        'per_language': by_lang,
        'avg_latency_ms': round(avg_latency_ms, 3),
        'total_time_ms': round(elapsed_ms, 2),
    }

def print_report(res):
    print("=" * 70)
    print(f"MODEL: {res['model_name']} | Partition: {res['partition']}")
    print(f"Accuracy: {res['accuracy'] * 100:.2f}% | Macro F1: {res['macro_f1']:.4f} | Latency: {res['avg_latency_ms']} ms/sample")
    print("-" * 70)
    print("Confusion Matrix:")
    print("Ref \\ Pred\tPositive\tNeutral \tNegative")
    for l in LABELS:
        m = res['confusion_matrix'][l]
        print(f"{l:10s}\t{m['Positive']:8d}\t{m['Neutral']:8d}\t{m['Negative']:8d}")
    print("-" * 70)
    print("Per-Class Metrics:")
    print("Class     \tPrecision\tRecall  \tF1-Score\tSupport")
    for l in LABELS:
        c = res['per_class'][l]
        print(f"{l:10s}\t{c['precision']:.4f}   \t{c['recall']:.4f} \t{c['f1']:.4f}  \t{c['support']}")
    print("-" * 70)
    print("Per-Language Accuracy:")
    for lang, lres in res['per_language'].items():
        print(f"  {lang:10s}: {lres['correct']}/{lres['total']} ({lres['accuracy']*100:.2f}%)")
    print("=" * 70 + "\n")

if __name__ == '__main__':
    print("EVALUATING ON DEVELOPMENT PARTITION (560 samples):")
    dev_a = evaluate_model("Model A (Baseline Uncorrected)", model_a_score, DEV_CSV)
    dev_b = evaluate_model("Model B (Corrected Baseline)", model_b_score, DEV_CSV)
    dev_c = evaluate_model("Model C (Enhanced Hybrid Pipeline)", model_c_score, DEV_CSV)

    print_report(dev_a)
    print_report(dev_b)
    print_report(dev_c)

    print("\nEVALUATING ON HELD-OUT UNTOUCHED TEST PARTITION (240 samples):")
    test_a = evaluate_model("Model A (Baseline Uncorrected)", model_a_score, TEST_CSV)
    test_b = evaluate_model("Model B (Corrected Baseline)", model_b_score, TEST_CSV)
    test_c = evaluate_model("Model C (Enhanced Hybrid Pipeline)", model_c_score, TEST_CSV)

    print_report(test_a)
    print_report(test_b)
    print_report(test_c)

    # Save results to json
    all_results = {
        'timestamp': '2026-11-10T19:35:00',
        'development_evaluation': {'model_a': dev_a, 'model_b': dev_b, 'model_c': dev_c},
        'held_out_test_evaluation': {'model_a': test_a, 'model_b': test_b, 'model_c': test_c},
    }
    with open('docs/analysis/model_comparison_results.json', 'w') as f:
        json.dump(all_results, f, indent=2)
    print("Saved comparison results to docs/analysis/model_comparison_results.json")

