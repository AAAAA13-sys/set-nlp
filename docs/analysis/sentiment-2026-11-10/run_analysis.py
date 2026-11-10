"""Reproducible local dataset evaluation; does not train or change the app engine."""
import csv
from decimal import Decimal, ROUND_HALF_UP
import hashlib
import importlib.metadata
import json
import math
import subprocess
import sys
import time
from collections import Counter, defaultdict
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]
sys.path.insert(0, str(ROOT))
from analytics import engine
from analytics.app import handle_request

OUT = Path(__file__).resolve().parent
SOURCE = ROOT / 'docs' / 'Student Evaluation of Teachers Dataset (ML Training - Tagalog, English, Taglish) - Dataset.csv'
LABELS = ['Positive', 'Neutral', 'Negative']
original_hash = hashlib.sha256(SOURCE.read_bytes()).hexdigest()
with SOURCE.open(encoding='utf-8-sig', newline='') as stream:
    rows = list(csv.DictReader(stream))
assert rows and all(row['Sentiment'] in LABELS for row in rows)
assert all(row['Feedback_Text'].strip() and len(row['Feedback_Text']) <= engine.MAX_COMMENT_LENGTH for row in rows)

predictions = []
started = time.perf_counter()
for offset in range(0, len(rows), engine.MAX_BATCH_SIZE):
    batch = rows[offset:offset + engine.MAX_BATCH_SIZE]
    status, payload = handle_request('POST', '/analyze/batch', {
        'comments': [{'id': row['ID'], 'comment': row['Feedback_Text']} for row in batch]
    })
    assert status == 200, payload
    assert payload['count'] == len(batch)
    predictions.extend(payload['results'])
elapsed = time.perf_counter() - started
assert len(predictions) == len(rows)
assert all(p['id'] == r['ID'] and -1 <= p['polarity'] <= 1 for r, p in zip(rows, predictions))

records = []
for row, prediction in zip(rows, predictions):
    scrubbed = engine.scrub_pii(row['Feedback_Text'])
    records.append({
        'Dataset_Row_ID': row['ID'],
        'Feedback_Text_Scrubbed': scrubbed,
        'Reference_Language': row['Language'],
        'Reference_Sentiment': row['Sentiment'],
        'Predicted_Sentiment': prediction['valence'],
        'Polarity': prediction['polarity'],
        'Sentiment_Matches_Reference': prediction['valence'] == row['Sentiment'],
        'Reference_Aspect': row['Aspect'],
        'Predicted_Pedagogical_Domain': prediction['pedagogical_tag'],
        'Domain_Heuristic_Score_Not_Probability': prediction['confidence'],
        'Matched_Domain_Keywords': ' | '.join(prediction['keywords']),
        'Reference_Rating_Score': row['Rating_Score'],
        'NLP_Tokens': ' | '.join(prediction['calamancy']['tokens']),
        'NLP_Lemmas': ' | '.join(prediction['calamancy']['lemmas']),
        'NLP_POS_Heuristic_Tags': ' | '.join(prediction['calamancy']['pos_tags']),
        'Text_Changed_By_Scrubber': scrubbed != row['Feedback_Text'],
    })

def metrics(items):
    matrix = {a: {b: 0 for b in LABELS} for a in LABELS}
    for item in items:
        matrix[item['Reference_Sentiment']][item['Predicted_Sentiment']] += 1
    classes = {}
    for label in LABELS:
        tp = matrix[label][label]
        actual = sum(matrix[label].values())
        predicted = sum(matrix[a][label] for a in LABELS)
        precision = tp / predicted if predicted else 0
        recall = tp / actual if actual else 0
        f1 = 2 * precision * recall / (precision + recall) if precision + recall else 0
        classes[label] = {'support': actual, 'precision': precision, 'recall': recall, 'f1': f1}
    return {'rows': len(items), 'correct': sum(matrix[a][a] for a in LABELS),
            'accuracy': sum(matrix[a][a] for a in LABELS) / len(items),
            'macro_f1': sum(c['f1'] for c in classes.values()) / len(LABELS),
            'per_class': classes, 'confusion_matrix_actual_rows_predicted_columns': matrix}

by_text = defaultdict(list)
for item in records:
    by_text[item['Feedback_Text_Scrubbed'].strip()].append(item)
duplicates = [v for v in by_text.values() if len(v) > 1]
summary = {
    'source': str(SOURCE), 'source_sha256': original_hash,
    'engine_sha256': hashlib.sha256((ROOT / 'analytics/engine.py').read_bytes()).hexdigest(),
    'commit': subprocess.check_output(['git','rev-parse','HEAD'], cwd=ROOT, text=True).strip(),
    'generated_at_utc': datetime.now(timezone.utc).isoformat(),
    'runtime': {'python': sys.version.split()[0], 'vader_version': importlib.metadata.version('vaderSentiment'),
                'calamancy_loaded': engine._CALAMANCY_NLP is not None,
                'effective_mode': 'Project rule-based polarity blended with VADER; heuristic token/lemma/POS fallback; keyword-based domains',
                'endpoint': '/analyze/batch via handle_request (in-process, no HTTP server)',
                'batch_size_limit': engine.MAX_BATCH_SIZE, 'batch_count': math.ceil(len(rows)/engine.MAX_BATCH_SIZE),
                'analysis_seconds': round(elapsed, 4)},
    'reference_distribution': dict(Counter(x['Reference_Sentiment'] for x in records)),
    'predicted_distribution': dict(Counter(x['Predicted_Sentiment'] for x in records)),
    'metrics': metrics(records),
    'by_reference_language': {language: metrics([x for x in records if x['Reference_Language'] == language])
                              for language in sorted({x['Reference_Language'] for x in records})},
    'predicted_domains': dict(Counter(x['Predicted_Pedagogical_Domain'] for x in records)),
    'reference_aspects': dict(Counter(x['Reference_Aspect'] for x in records)),
    'data_quality': {'blank_comments': 0, 'overlength_comments': 0,
                     'duplicate_extra_rows': sum(len(x)-1 for x in duplicates),
                     'duplicate_groups': [{'row_ids':[r['Dataset_Row_ID'] for r in group],
                                           'reference_labels':sorted({r['Reference_Sentiment'] for r in group})} for group in duplicates],
                     'deduplicated_accuracy': metrics([v[0] for v in by_text.values()])['accuracy'],
                     'scrubber_changed_rows': sum(x['Text_Changed_By_Scrubber'] for x in records),
                     'no_domain_keyword_matches': sum(not x['Matched_Domain_Keywords'] for x in records),
                     'exact_benchmark_calibration_matches':sum(engine._normalize_whitespace(x['Feedback_Text_Scrubbed']).lower() in engine._BENCHMARK_CALIBRATIONS for x in records)},
    'limitations': [
        'Dataset reference labels were supplied, not independently adjudicated.',
        'No model training or tuning occurred; this is a dataset agreement evaluation, not an independent holdout certification.',
        'calamanCy and spaCy are unavailable in this Python environment; trained-model tagging was not evaluated.',
        'The API engine label names calamanCy even when the heuristic fallback runs.',
        'Domain confidence is a heuristic match score, not calibrated sentiment confidence.',
        'Reference aspects have a different taxonomy from the five output domains; no domain accuracy is claimed.',
        'Library facilities and extracurricular feedback extend beyond teacher-focused domain coverage.',
        'Dataset row IDs are source row identifiers, not student identities.',
        'Timing measures one local in-process run, not production HTTP latency or a load benchmark.'
    ]
}

def export_csv(path, items):
    with path.open('w', encoding='utf-8-sig', newline='') as stream:
        writer=csv.DictWriter(stream,fieldnames=list(records[0]));writer.writeheader()
        for item in items:
            writer.writerow({k:("'"+v if isinstance(v,str) and v.startswith(('=','+','-','@')) else v) for k,v in item.items()})

export_csv(OUT/'sentiment_results.csv', records)
errors=[x for x in records if not x['Sentiment_Matches_Reference']]
export_csv(OUT/'sentiment_review_queue.csv', errors)
(OUT/'sentiment_summary.json').write_text(json.dumps(summary,indent=2,ensure_ascii=False),encoding='utf-8')

m=summary['metrics']
agreement_display=(Decimal(m['correct']) * 100 / Decimal(len(records))).quantize(Decimal('0.01'),rounding=ROUND_HALF_UP)
report=['# EvaluaTech dataset sentiment and NLP analysis','',
        f"Source: `{SOURCE.name}`. {len(records)} rows. Original file preserved.",'',
        '## Result','',f"Sentiment label agreement: **{agreement_display}%** ({m['correct']}/{len(records)}). Macro F1: **{m['macro_f1']:.4f}**. Rows for review: **{len(errors)}**.",'',
        '**Actual runtime:** custom educational rules blended with installed VADER, heuristic tokenization/lemmatization/POS, and keyword-based pedagogical tagging. The trained calamanCy model did not run.','',
        '| Sentiment | Reference count | Predicted count | Precision | Recall | F1 |','|---|---:|---:|---:|---:|---:|']
for label in LABELS:
    c=m['per_class'][label]
    report.append(f"| {label} | {summary['reference_distribution'].get(label,0)} | {summary['predicted_distribution'].get(label,0)} | {c['precision']:.2%} | {c['recall']:.2%} | {c['f1']:.4f} |")
report+=['','## Agreement by supplied language','','| Language | Rows | Correct | Agreement | Macro F1 |','|---|---:|---:|---:|---:|']
for lang, value in summary['by_reference_language'].items():
    report.append(f"| {lang} | {value['rows']} | {value['correct']} | {value['accuracy']:.2%} | {value['macro_f1']:.4f} |")
report+=['','## Confusion matrix','', 'Rows are reference labels; columns are predictions.','',
         '| Reference / Predicted | Positive | Neutral | Negative |','|---|---:|---:|---:|']
for label, vals in m['confusion_matrix_actual_rows_predicted_columns'].items():
    report.append('| '+label+' | '+' | '.join(str(vals[x]) for x in LABELS)+' |')
report+=['','## Pedagogical tagging','','| Predicted domain | Rows |','|---|---:|']
for label,count in summary['predicted_domains'].items():report.append(f'| {label} | {count} |')
report+=['',f"{summary['data_quality']['no_domain_keyword_matches']} rows had no matched domain keywords. The engine still assigns a domain; inspect these before using the categories in reports.",'',
         '## Selected mismatches','', 'Examples are selected by reference/prediction pair, not presented as a random sample. Reference labels may also need review.','']
seen_pairs=set()
for item in errors:
    pair=(item['Reference_Sentiment'],item['Predicted_Sentiment'])
    if pair in seen_pairs:continue
    seen_pairs.add(pair)
    report.append(f"- Row {item['Dataset_Row_ID']} ({item['Reference_Language']}): reference **{pair[0]}**, predicted **{pair[1]}**, polarity {item['Polarity']}. {item['Feedback_Text_Scrubbed']}")
report+=['','## Data quality and limitations','']+[f'- {s}' for s in summary['limitations']]
report += ['',f"Duplicate extra rows: {summary['data_quality']['duplicate_extra_rows']}. Exact hardcoded benchmark matches: {summary['data_quality']['exact_benchmark_calibration_matches']}. No blank or overlength comments.",'',
           '## Reproducibility','',f"Engine commit: `{summary['commit']}`. Engine SHA-256: `{summary['engine_sha256']}`.",
           f"Source SHA-256: `{original_hash}`.",f"VADER: {summary['runtime']['vader_version']}; Python: {summary['runtime']['python']}. {summary['runtime']['batch_count']} endpoint batches; {elapsed:.3f} seconds total.",
           '', 'Files: sentiment_results.csv (all predictions), sentiment_review_queue.csv (mismatches), sentiment_summary.json (full metrics).']
(OUT/'SENTIMENT_REPORT.md').write_text('\n'.join(report)+'\n',encoding='utf-8')
assert hashlib.sha256(SOURCE.read_bytes()).hexdigest()==original_hash
with (OUT/'sentiment_results.csv').open(encoding='utf-8-sig',newline='') as f:assert len(list(csv.DictReader(f)))==len(rows)
print(json.dumps({k:summary[k] for k in ['runtime','reference_distribution','predicted_distribution','metrics','by_reference_language','data_quality']},indent=2))
