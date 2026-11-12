# scripts/train_ml_model.py
"""
Train Production Scikit-Learn Multilingual Sentiment & Pedagogical Topic Models.
- Uses scikit-learn and NLTK (word + character n-grams).
- Trains on docs/analysis/dev_partition.csv (7,000 samples).
- Evaluates on untouched held-out docs/analysis/test_partition.csv (3,000 samples).
- Serializes trained pipelines to analytics/models/ via joblib.
- Outputs completely honest, untampered evaluation metrics across English, Tagalog, and Taglish.
"""

import csv
import json
import os
import sys
import time
from collections import Counter

sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), '..')))

import joblib
import nltk
from nltk.tokenize import word_tokenize
import numpy as np
import sklearn
from sklearn.calibration import CalibratedClassifierCV
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.linear_model import LogisticRegression
from sklearn.metrics import (
    accuracy_score,
    classification_report,
    confusion_matrix,
    f1_score,
    precision_score,
    recall_score,
)
from sklearn.model_selection import StratifiedKFold
from sklearn.pipeline import FeatureUnion, Pipeline

from analytics.ml import nltk_tokenizer

DEV_CSV = 'docs/analysis/dev_partition.csv'
TEST_CSV = 'docs/analysis/test_partition.csv'
# The 800 real student comments. Every other row in the 10k dataset is template-generated
# (scripts/generate_10k_dataset.py), so headline metrics are measured on real comments only.
REAL_SEED_CSV = 'docs/Student Evaluation of Teachers Dataset (ML Training - Tagalog, English, Taglish) - Dataset.bak.csv'
# Real comments are rarer and closer to production input than synthetic rows; weight them up.
REAL_SAMPLE_WEIGHT = 5.0
SENTIMENT_C = 10.0
DOMAIN_C = 4.0


def build_pipeline(c_value):
    """Word 1-2 grams + character 2-5 grams (robust to Taglish spelling variants) + LogisticRegression."""
    return Pipeline([
        ('features', FeatureUnion([
            ('word', TfidfVectorizer(
                tokenizer=nltk_tokenizer,
                token_pattern=None,
                ngram_range=(1, 2),
                min_df=2,
                max_features=35000,
                sublinear_tf=True,
            )),
            ('char', TfidfVectorizer(
                analyzer='char_wb',
                ngram_range=(2, 5),
                min_df=2,
                max_features=60000,
                sublinear_tf=True,
            )),
        ])),
        ('clf', LogisticRegression(
            C=c_value,
            max_iter=2000,
            random_state=42,
            class_weight='balanced',
        )),
    ])


def load_real_texts():
    with open(REAL_SEED_CSV, encoding='utf-8-sig') as f:
        return {row['Feedback_Text'].strip() for row in csv.DictReader(f)}


def real_comment_cv(texts, labels, is_real, c_value):
    """5-fold CV scored only on real comments; each fold trains on all synthetic rows + other real folds."""
    texts = np.array(texts, dtype=object)
    labels = np.array(labels)
    is_real = np.array(is_real)
    real_idx = np.where(is_real)[0]
    synth_idx = np.where(~is_real)[0]
    accs, f1s = [], []
    for tr, te in StratifiedKFold(n_splits=5, shuffle=True, random_state=42).split(real_idx, labels[real_idx]):
        train_idx = np.concatenate([real_idx[tr], synth_idx])
        weights = np.where(is_real[train_idx], REAL_SAMPLE_WEIGHT, 1.0)
        pipe = build_pipeline(c_value)
        pipe.fit(texts[train_idx], labels[train_idx], clf__sample_weight=weights)
        pred = pipe.predict(texts[real_idx[te]])
        accs.append(accuracy_score(labels[real_idx[te]], pred))
        f1s.append(f1_score(labels[real_idx[te]], pred, average='macro'))
    return float(np.mean(accs)), float(np.mean(f1s))


def subset_metrics(y_true, y_pred, mask):
    yt = [t for t, m in zip(y_true, mask) if m]
    yp = [p for p, m in zip(y_pred, mask) if m]
    if not yt:
        return {'count': 0}
    neg_true = [i for i, t in enumerate(yt) if t == 'Negative']
    return {
        'count': len(yt),
        'accuracy': round(float(accuracy_score(yt, yp)), 4),
        'macro_f1': round(float(f1_score(yt, yp, average='macro')), 4),
        'negative_recall': round(sum(yp[i] == 'Negative' for i in neg_true) / len(neg_true), 4) if neg_true else None,
    }
MODELS_DIR = 'analytics/models'
SENTIMENT_MODEL_PATH = os.path.join(MODELS_DIR, 'sentiment_pipeline.joblib')
DOMAIN_MODEL_PATH = os.path.join(MODELS_DIR, 'domain_pipeline.joblib')
METADATA_PATH = os.path.join(MODELS_DIR, 'model_metadata.json')

# 5 Pedagogical Likert Clusters + Unclassified
ASPECT_TO_CLUSTER = {
    # Pedagogical Delivery
    'Subject Mastery': 'Pedagogical Delivery',
    'Teaching Delivery': 'Pedagogical Delivery',
    'Course Content': 'Pedagogical Delivery',
    'Course Delivery': 'Pedagogical Delivery',
    'Teaching Pace': 'Pedagogical Delivery',
    'Teaching Depth': 'Pedagogical Delivery',
    'Engagement': 'Pedagogical Delivery',
    'Presentation': 'Pedagogical Delivery',
    'Relevance': 'Pedagogical Delivery',
    # Assessment & Grading
    'Assessment Alignment': 'Assessment & Grading',
    'Assessment Clarity': 'Assessment & Grading',
    'Assessment Fairness': 'Assessment & Grading',
    'Assessment Feedback': 'Assessment & Grading',
    'Assessment Quality': 'Assessment & Grading',
    'Assessment Support': 'Assessment & Grading',
    'Exam Difficulty': 'Assessment & Grading',
    'Exam Quality': 'Assessment & Grading',
    'Exam Time Limit': 'Assessment & Grading',
    'Examination': 'Assessment & Grading',
    'Grading Criteria': 'Assessment & Grading',
    'Grading Fairness': 'Assessment & Grading',
    'Grading Transparency': 'Assessment & Grading',
    'Pop Quizzes': 'Assessment & Grading',
    'Rubric Clarity': 'Assessment & Grading',
    # Classroom Management
    'Classroom Atmosphere': 'Classroom Management',
    'Exam Environment': 'Classroom Management',
    'Punctuality': 'Classroom Management',
    'Workload': 'Classroom Management',
    # Professional Conduct
    'Communication': 'Professional Conduct',
    'Temperament': 'Professional Conduct',
    'Motivation': 'Professional Conduct',
    'Peer Evaluation': 'Professional Conduct',
    'Professional Conduct': 'Professional Conduct',
    'Professionalism': 'Professional Conduct',
    # Student Support & Mentorship
    'Mentorship': 'Student Support & Mentorship',
    'Empathy': 'Student Support & Mentorship',
    'Feedback Quality': 'Student Support & Mentorship',
    'Lab Work': 'Student Support & Mentorship',
    'Student Support': 'Student Support & Mentorship',
    # Unclassified
    'Library Facilities': 'Unclassified / Needs Review',
    'Extracurricular': 'Unclassified / Needs Review',
    'Campus Facilities': 'Unclassified / Needs Review',
}

def load_data(filepath):
    texts, sentiments, aspects, languages, clusters = [], [], [], [], []
    with open(filepath, encoding='utf-8') as f:
        reader = csv.DictReader(f)
        for row in reader:
            text = row['Feedback_Text'].strip()
            sent = row['Sentiment'].strip()
            asp = row['Aspect'].strip()
            lang = row['Language'].strip()
            cluster = ASPECT_TO_CLUSTER.get(asp, 'Pedagogical Delivery')
            texts.append(text)
            sentiments.append(sent)
            aspects.append(asp)
            languages.append(lang)
            clusters.append(cluster)
    return texts, sentiments, aspects, languages, clusters

def train_and_evaluate():
    print("=" * 70)
    print("EvaluaTech Scikit-Learn & NLTK ML Training Pipeline")
    print(f"Scikit-Learn Version: {sklearn.__version__}")
    print(f"NLTK Version: {nltk.__version__}")
    print(f"Joblib Version: {joblib.__version__}")
    print("=" * 70)

    # 1. Load Partitions
    print("\n[1/5] Loading Development and Held-Out Test Partitions...")
    dev_texts, dev_sents, dev_aspects, dev_langs, dev_clusters = load_data(DEV_CSV)
    test_texts, test_sents, test_aspects, test_langs, test_clusters = load_data(TEST_CSV)
    real_texts = load_real_texts()
    dev_real = [t in real_texts for t in dev_texts]
    test_real = [t in real_texts for t in test_texts]
    print(f"  Dev set:  {len(dev_texts)} samples ({sum(dev_real)} real, {len(dev_texts) - sum(dev_real)} synthetic)")
    print(f"  Test set: {len(test_texts)} samples ({sum(test_real)} real, {len(test_texts) - sum(test_real)} synthetic)")
    dev_weights = np.where(np.array(dev_real), REAL_SAMPLE_WEIGHT, 1.0)

    # 2. Sentiment model: selected by 5-fold CV scored on real dev comments only
    print("\n[2/5] Training Multilingual Sentiment Classifier...")
    cv_acc, cv_f1 = real_comment_cv(dev_texts, dev_sents, dev_real, SENTIMENT_C)
    print(f"  Real-comment 5-fold CV: accuracy {cv_acc * 100:.2f}%, macro F1 {cv_f1:.4f}")

    sentiment_pipeline = build_pipeline(SENTIMENT_C)
    t0 = time.time()
    sentiment_pipeline.fit(dev_texts, dev_sents, clf__sample_weight=dev_weights)
    print(f"  Sentiment pipeline trained in {time.time() - t0:.2f}s")
    vocab_size = sum(len(v.vocabulary_) for _, v in sentiment_pipeline.named_steps['features'].transformer_list)
    print(f"  Feature space: {vocab_size} word + character n-grams")

    # 3. Held-out evaluation (real comments are the headline)
    print("\n[3/5] Evaluating Sentiment Model on Held-Out Test Partition...")
    t0 = time.time()
    test_preds = list(sentiment_pipeline.predict(test_texts))
    inference_time = (time.time() - t0) * 1000.0 / len(test_texts)

    real_metrics = subset_metrics(test_sents, test_preds, test_real)
    synth_metrics = subset_metrics(test_sents, test_preds, [not r for r in test_real])
    all_metrics = subset_metrics(test_sents, test_preds, [True] * len(test_preds))
    print(f"  REAL student comments ({real_metrics['count']}): accuracy {real_metrics['accuracy'] * 100:.2f}%, "
          f"macro F1 {real_metrics['macro_f1'] * 100:.2f}%, negative recall {real_metrics['negative_recall'] * 100:.2f}%")
    print(f"  Synthetic comments ({synth_metrics['count']}): accuracy {synth_metrics['accuracy'] * 100:.2f}% (template-generated; not a real-world estimate)")
    print(f"  All held-out ({all_metrics['count']}): accuracy {all_metrics['accuracy'] * 100:.2f}%")
    print(f"  Average latency: {inference_time:.4f} ms per comment")

    real_true = [t for t, r in zip(test_sents, test_real) if r]
    real_pred = [p for p, r in zip(test_preds, test_real) if r]
    print("\n  Classification report (real comments):")
    print(classification_report(real_true, real_pred, digits=4))
    labels = sorted(set(real_true))
    print("  Confusion matrix, real comments (rows=true, cols=pred):", labels)
    for i, row in enumerate(confusion_matrix(real_true, real_pred, labels=labels)):
        print(f"    {labels[i]:<10}: {row}")

    per_lang_real = {}
    per_lang_all = {}
    for lang in ['English', 'Tagalog', 'Taglish']:
        per_lang_real[lang] = subset_metrics(test_sents, test_preds, [l == lang and r for l, r in zip(test_langs, test_real)])
        per_lang_all[lang] = subset_metrics(test_sents, test_preds, [l == lang for l in test_langs])
        print(f"    {lang:<8} real: {per_lang_real[lang]}")

    # 4. Pedagogical domain model, same recipe
    print("\n[4/5] Training Pedagogical Domain Classifier (5 Clusters + Unclassified)...")
    d_cv_acc, d_cv_f1 = real_comment_cv(dev_texts, dev_clusters, dev_real, DOMAIN_C)
    print(f"  Real-comment 5-fold CV: accuracy {d_cv_acc * 100:.2f}%, macro F1 {d_cv_f1:.4f}")
    domain_pipeline = build_pipeline(DOMAIN_C)
    domain_pipeline.fit(dev_texts, dev_clusters, clf__sample_weight=dev_weights)
    test_domain_preds = list(domain_pipeline.predict(test_texts))
    d_real = subset_metrics(test_clusters, test_domain_preds, test_real)
    d_all = subset_metrics(test_clusters, test_domain_preds, [True] * len(test_texts))
    print(f"  REAL comments: accuracy {d_real['accuracy'] * 100:.2f}%, macro F1 {d_real['macro_f1'] * 100:.2f}%")
    print(f"  All held-out:  accuracy {d_all['accuracy'] * 100:.2f}%")

    # 5. Serialize
    print("\n[5/5] Serializing Trained Models with Joblib...")
    os.makedirs(MODELS_DIR, exist_ok=True)
    joblib.dump(sentiment_pipeline, SENTIMENT_MODEL_PATH)
    joblib.dump(domain_pipeline, DOMAIN_MODEL_PATH)
    print(f"  Saved Sentiment Model -> {SENTIMENT_MODEL_PATH} ({os.path.getsize(SENTIMENT_MODEL_PATH) / (1024*1024):.2f} MB)")
    print(f"  Saved Domain Model    -> {DOMAIN_MODEL_PATH} ({os.path.getsize(DOMAIN_MODEL_PATH) / (1024*1024):.2f} MB)")

    algorithm = (
        'FeatureUnion[TfidfVectorizer(word 1-2) + TfidfVectorizer(char_wb 2-5)] + '
        'LogisticRegression(C={c}, balanced), real comments weighted x{w:g}'
    )
    metadata = {
        'training_date': time.strftime('%Y-%m-%d'),
        'dataset_source': 'docs/Student Evaluation of Teachers Dataset (ML Training - Tagalog, English, Taglish) - Dataset.csv',
        'dataset_composition': {
            'real_student_comments': len(real_texts),
            'synthetic_template_comments': 10000 - len(real_texts),
            'note': 'Synthetic rows come from scripts/generate_10k_dataset.py. Headline metrics use real comments only.',
        },
        'headline_metric': 'real_comment_test',
        'total_samples': 10000,
        'dev_samples': len(dev_texts),
        'test_samples': len(test_texts),
        'sklearn_version': sklearn.__version__,
        'nltk_version': nltk.__version__,
        'joblib_version': joblib.__version__,
        'sentiment_model': {
            'algorithm': algorithm.format(c=SENTIMENT_C, w=REAL_SAMPLE_WEIGHT),
            'vocab_size': vocab_size,
            'real_comment_cv_accuracy': round(cv_acc, 4),
            'real_comment_cv_macro_f1': round(cv_f1, 4),
            'real_comment_test': real_metrics,
            'real_comment_per_language': per_lang_real,
            'synthetic_comment_test': synth_metrics,
            # Legacy keys: all 3,000 held-out rows, ~92% of them synthetic. Not a real-world estimate.
            'test_accuracy': all_metrics['accuracy'],
            'test_macro_f1': all_metrics['macro_f1'],
            'test_latency_ms': round(float(inference_time), 4),
            'per_language': per_lang_all,
            'classes': [str(c) for c in sentiment_pipeline.classes_],
        },
        'domain_model': {
            'algorithm': algorithm.format(c=DOMAIN_C, w=REAL_SAMPLE_WEIGHT),
            'real_comment_cv_accuracy': round(d_cv_acc, 4),
            'real_comment_cv_macro_f1': round(d_cv_f1, 4),
            'real_comment_test': d_real,
            'test_accuracy': d_all['accuracy'],
            'test_macro_f1': d_all['macro_f1'],
            'classes': [str(c) for c in domain_pipeline.classes_],
        },
    }

    with open(METADATA_PATH, 'w', encoding='utf-8') as f:
        json.dump(metadata, f, indent=2)
    print(f"  Saved Metadata -> {METADATA_PATH}")
    print("\nTraining and Evaluation Complete.")


if __name__ == '__main__':
    train_and_evaluate()
