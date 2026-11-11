import csv
import json
import random
from collections import Counter, defaultdict

SOURCE_CSV = 'docs/Student Evaluation of Teachers Dataset (ML Training - Tagalog, English, Taglish) - Dataset.csv'
DEV_CSV = 'docs/analysis/dev_partition.csv'
TEST_CSV = 'docs/analysis/test_partition.csv'
METADATA_JSON = 'docs/analysis/dataset_partitions.json'

RANDOM_SEED = 42

def main():
    with open(SOURCE_CSV, encoding='utf-8-sig') as f:
        reader = csv.DictReader(f)
        fieldnames = reader.fieldnames
        rows = list(reader)

    print(f"Loaded {len(rows)} rows from {SOURCE_CSV}")

    # Identify duplicate texts
    by_text = defaultdict(list)
    for r in rows:
        by_text[r['Feedback_Text'].strip().lower()].append(r)

    duplicate_ids = set()
    for text, group in by_text.items():
        if len(group) > 1:
            for g in group:
                duplicate_ids.add(g['ID'])
            print(f"Identified duplicate group of {len(group)} items: IDs {[g['ID'] for g in group]}")

    by_stratum = defaultdict(list)
    for r in rows:
        by_stratum[(r['Language'], r['Sentiment'])].append(r)

    # Calculate exact 70/30 counts using largest remainder method for exactly 7,000 dev rows
    total_dev_target = 7000
    strata_dev_targets = {}
    remainders = []

    for stratum, items in sorted(by_stratum.items()):
        raw_count = len(items) * 0.70
        base_count = int(raw_count)
        rem = raw_count - base_count
        strata_dev_targets[stratum] = base_count
        remainders.append((rem, stratum))

    current_allocated = sum(strata_dev_targets.values())
    shortage = total_dev_target - current_allocated
    remainders.sort(key=lambda x: x[0], reverse=True)
    for i in range(shortage):
        strata_dev_targets[remainders[i][1]] += 1

    print("Target Dev Counts per Stratum:")
    for stratum, count in sorted(strata_dev_targets.items()):
        print(f"  {stratum}: {count} / {len(by_stratum[stratum])} (Test: {len(by_stratum[stratum]) - count})")

    rng = random.Random(RANDOM_SEED)

    dev_rows = []
    test_rows = []

    for stratum, items in sorted(by_stratum.items()):
        forced_dev = [item for item in items if item['ID'] in duplicate_ids]
        candidates = [item for item in items if item['ID'] not in duplicate_ids]

        rng.shuffle(candidates)

        target_dev = strata_dev_targets[stratum]
        needed_from_candidates = target_dev - len(forced_dev)
        assert needed_from_candidates >= 0, f"Forced dev ({len(forced_dev)}) exceeds target ({target_dev}) for stratum {stratum}"

        stratum_dev = forced_dev + candidates[:needed_from_candidates]
        stratum_test = candidates[needed_from_candidates:]

        assert len(stratum_dev) == target_dev, f"Stratum {stratum} dev mismatch: {len(stratum_dev)} != {target_dev}"
        assert len(stratum_dev) + len(stratum_test) == len(items), f"Stratum {stratum} sum mismatch"

        dev_rows.extend(stratum_dev)
        test_rows.extend(stratum_test)

    assert len(dev_rows) == 7000, f"Expected 7000 dev rows, got {len(dev_rows)}"
    assert len(test_rows) == 3000, f"Expected 3000 test rows, got {len(test_rows)}"

    dev_id_set = {r['ID'] for r in dev_rows}
    test_id_set = {r['ID'] for r in test_rows}
    assert len(dev_id_set.intersection(test_id_set)) == 0, "Overlap found between dev and test sets!"
    assert duplicate_ids.issubset(dev_id_set), "Duplicate rows not fully in dev set!"

    # Sort deterministically by ID (integer)
    dev_rows.sort(key=lambda x: int(x['ID']))
    test_rows.sort(key=lambda x: int(x['ID']))

    # Write dev partition
    with open(DEV_CSV, 'w', newline='', encoding='utf-8') as f:
        writer = csv.DictWriter(f, fieldnames=fieldnames)
        writer.writeheader()
        writer.writerows(dev_rows)

    # Write test partition
    with open(TEST_CSV, 'w', newline='', encoding='utf-8') as f:
        writer = csv.DictWriter(f, fieldnames=fieldnames)
        writer.writeheader()
        writer.writerows(test_rows)

    # Calculate summary metrics
    def summarize(partition_rows):
        lang_counts = Counter(r['Language'] for r in partition_rows)
        sent_counts = Counter(r['Sentiment'] for r in partition_rows)
        aspect_counts = Counter(r['Aspect'] for r in partition_rows)
        strata_counts = {f"{k[0]}_{k[1]}": v for k, v in Counter((r['Language'], r['Sentiment']) for r in partition_rows).items()}
        return {
            'total_count': len(partition_rows),
            'languages': dict(lang_counts),
            'sentiments': dict(sent_counts),
            'top_aspects': dict(aspect_counts.most_common(10)),
            'strata': strata_counts,
            'id_list': [int(r['ID']) for r in partition_rows],
        }

    metadata = {
        'dataset_source': SOURCE_CSV,
        'random_seed': RANDOM_SEED,
        'partition_date': '2026-11-11',
        'policy': 'Stratified 70/30 split across Language x Sentiment; near-duplicates grouped into Dev.',
        'duplicate_groups': [{'text': t, 'ids': [g['ID'] for g in g_items]} for t, g_items in by_text.items() if len(g_items) > 1],
        'development_partition': summarize(dev_rows),
        'held_out_test_partition': summarize(test_rows),
    }

    with open(METADATA_JSON, 'w', encoding='utf-8') as f:
        json.dump(metadata, f, indent=2)

    print(f"\nSuccessfully created partitions:")
    print(f"  Dev Partition: {len(dev_rows)} rows -> {DEV_CSV}")
    print(f"  Test Partition: {len(test_rows)} rows -> {TEST_CSV}")
    print(f"  Metadata: {METADATA_JSON}")

if __name__ == '__main__':
    main()
