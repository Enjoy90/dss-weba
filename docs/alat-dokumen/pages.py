import json
import re
import sys

import fitz

doc = fitz.open(sys.argv[1])
targets = json.load(open(sys.argv[2], encoding='utf-8'))
texts = targets['headings'] + targets['captions']


def printed_number(page):
    blocks = [b for b in page.get_text('blocks') if b[4].strip()]
    bottom = max(blocks, key=lambda b: b[1])
    match = re.search(r'(\d+)\s*$', bottom[4].strip())
    return int(match.group(1)) if match else None


pages = {}
for text in texts:
    probe = text[:60]
    found = [i for i in range(1, doc.page_count) if doc[i].search_for(probe)]
    if found:
        pages[text] = printed_number(doc[found[-1]])
json.dump(pages, open(sys.argv[3], 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
missing = [t for t in texts if pages.get(t) is None]
print('found', len(pages) - len(missing), 'of', len(texts), 'missing', missing[:5])
