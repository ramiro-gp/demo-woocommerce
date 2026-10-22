"""Extract original product stock/cross-sells from existing backups; never execute SQL."""
import csv
import hashlib
import json
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[2]
if str(ROOT).lower() != r'c:\codigo\demo-woocommerce':
    raise RuntimeError('Use the canonical QA workspace.')
BACKUPS = ROOT / '.local/local/backups'


def products_from_backup(name):
    products, metadata = {}, {}
    table = None
    for line in (BACKUPS / name / 'database.sql').open(encoding='utf-8'):
        match = re.match(r'INSERT INTO `([^`]+)` VALUES', line)
        if match:
            table = match.group(1) if match.group(1) in ('wp_posts', 'wp_postmeta') else None
        if table and line.startswith('('):
            row = next(csv.reader([line[1:line.rfind(')')]], delimiter=',', quotechar="'", escapechar='\\', doublequote=False))
            if table == 'wp_posts':
                assert len(row) == 23
                if row[20] in ('product', 'product_variation'):
                    products[int(row[0])] = {'post_modified': row[14]}
            else:
                assert len(row) == 4
                if row[2] in ('_sku', '_stock', '_crosssell_ids', 'total_sales'):
                    metadata.setdefault(int(row[1]), {})[row[2]] = row[3]
        if line.rstrip().endswith(';'):
            table = None
    return {i: {**post, **metadata.get(i, {})} for i, post in products.items() if metadata.get(i, {}).get('_sku', '').startswith('TRM-')}


if __name__ == '__main__':
    source = 'h7-1-before-payments'
    original = products_from_backup(source)
    assert len(original) == 88
    origin = {
        'backup': source,
        'backup_sha256': hashlib.sha256((BACKUPS / source / 'database.sql').read_bytes()).hexdigest(),
        'products': {i: {'sku': p['_sku'], 'stock': p.get('_stock'), 'cross_sell_ids': p.get('_crosssell_ids')} for i, p in original.items()},
    }
    output = BACKUPS / 'h8-1-fixture-audit-origin.json'
    if output.exists() and json.loads(output.read_text(encoding='utf-8')) != json.loads(json.dumps(origin)):
        raise RuntimeError('Preserve differing prior audit origin; do not overwrite.')
    output.write_text(json.dumps(origin), encoding='utf-8')
    timeline = {}
    for name in ('h7-2-before-communications-20261019', 'h7-2-before-account-corrections-20261020', 'h8-1-before-commerce-core-20261020'):
        current = products_from_backup(name)
        changes = []
        for product_id, before in original.items():
            after = current[product_id]
            changed = {key: [before.get(key), after.get(key)] for key in ('_stock', '_crosssell_ids', 'total_sales', 'post_modified') if before.get(key) != after.get(key)}
            if changed:
                changes.append({'id': product_id, 'sku': before['_sku'], 'changes': changed})
        timeline[name] = changes
    folder = ROOT / '.local/local/h8-1-final-20261021'
    folder.mkdir(parents=True, exist_ok=True)
    (folder / 'fixture-backup-timeline.json').write_text(json.dumps(timeline, ensure_ascii=False, indent=2), encoding='utf-8')
    print(json.dumps({'result': 'passed', 'original_product_objects': len(original), 'source_backup_verified': True, 'changed_objects_per_backup': {name: len(changes) for name, changes in timeline.items()}, 'sql_executed': False, 'backups_modified': False}))
