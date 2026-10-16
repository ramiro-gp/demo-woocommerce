"""Export generated PNG masters as versioned 4:5 WebP catalog sources."""
import json
import hashlib
from pathlib import Path
from PIL import Image, ImageOps

root = Path(__file__).resolve().parents[1]
mapping = json.loads((root / '.local/local/h5/generated-map.json').read_text(encoding='utf-8'))
target = root / 'demo/catalog/images'
target.mkdir(parents=True, exist_ok=True)
manifest = []
for item in mapping:
    source = Path(item['path'])
    output = target / item['file']
    with Image.open(source) as image:
        image = ImageOps.exif_transpose(image).convert('RGB')
        image = ImageOps.fit(image, (1000, 1250), method=Image.Resampling.LANCZOS)
        image.save(output, 'WEBP', quality=82, method=6)
    manifest.append({'file': item['file'], 'width': 1000, 'height': 1250, 'bytes': output.stat().st_size, 'sha256': hashlib.sha256(output.read_bytes()).hexdigest(), 'generator': 'Built-in image_gen', 'source_png': source.name})
(root / 'demo/catalog/image-manifest.json').write_text(json.dumps(manifest, indent=2) + '\n', encoding='utf-8')
print(f'{len(manifest)} WebP sources exported; PNG masters stay private.')
