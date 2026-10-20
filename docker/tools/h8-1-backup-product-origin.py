"""Read only two known synthetic product rows from the pre-H8.1 dump. No SQL execution."""
import csv
import json
from pathlib import Path
import re

root = Path(__file__).resolve().parents[2]
assert str(root).lower() == r'c:\codigo\demo-woocommerce'
folder = root / '.local/local/backups'
source = folder / 'h8-1-before-commerce-core-20261020/database.sql'
out = {}
table = None
for line in source.open(encoding='utf-8'):
    if line.startswith('INSERT INTO `wp_posts` VALUES'):
        table = 'posts'
    elif line.startswith('INSERT INTO `wp_postmeta` VALUES'):
        table = 'postmeta'
    if table == 'posts':
        # These owned fixture posts have empty descriptions and no parentheses.
        # Refuse any changed row shape; never evaluate SQL or parse customer data.
        for match in re.finditer(r'\((294|295),([^)]*)\)', line):
            row = next(csv.reader([match.group(1) + ',' + match.group(2)], delimiter=',', quotechar="'", escapechar='\\'))
            assert len(row) == 23 and row[20] == 'product'
            assert row[5] == 'H7.1 concurrencia ' + ('A' if row[0] == '294' else 'B')
            out.setdefault(row[0], {})['post_modified'] = row[14]
    elif table == 'postmeta':
        for match in re.finditer(r"\(\d+,(294|295),'_stock','([^']*)'\)", line):
            out.setdefault(match.group(1), {})['stock'] = int(match.group(2))
    if line.rstrip().endswith(';'):
        table = None
assert set(out) == {'294', '295'} and all('stock' in value and 'post_modified' in value for value in out.values()), out
(folder / 'h8-1-qa-product-origin.json').write_text(json.dumps(out), encoding='utf-8')
print(json.dumps({'result': 'passed', 'owned_product_rows': len(out), 'backup_modified': False, 'sql_executed': False}))
