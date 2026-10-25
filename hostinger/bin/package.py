#!/usr/bin/env python3
"""Package only the Hostinger PHP runtime; exclude secrets and customer files."""
from pathlib import Path
import sys
import zipfile

root = Path(__file__).resolve().parents[1]
if len(sys.argv) != 2:
    raise SystemExit('Usage: python3 hostinger/bin/package.py /absolute/output.zip')
output = Path(sys.argv[1])
if not output.is_absolute() or output.suffix.lower() != '.zip':
    raise SystemExit('Use an absolute .zip output path.')
for part in (root / 'public_html', root / 'laezer-private'):
    if output.is_relative_to(part):
        raise SystemExit('Keep the output archive outside the packaged directories.')
required = ['public_html/index.php', 'public_html/.htaccess', 'laezer-private/app/schema.sql', 'laezer-private/app/seed.json', 'laezer-private/app/templates/site.html']
for name in required:
    if not (root / name).is_file():
        raise SystemExit('Missing runtime file: ' + name)
output.parent.mkdir(parents=True, exist_ok=True)
with zipfile.ZipFile(output, 'w', zipfile.ZIP_DEFLATED) as archive:
    archive.write(root / 'README.md', 'README.md')
    for folder in ('public_html', 'laezer-private'):
        for path in sorted((root / folder).rglob('*')):
            relative = path.relative_to(root)
            if path.is_symlink():
                raise SystemExit('Symlinks are not allowed in the release.')
            if not path.is_file() or relative.parts[:2] == ('laezer-private', 'uploads') or relative.as_posix() == 'laezer-private/config.php':
                continue
            if path.name.startswith('.env') or path.suffix in ('.pem', '.sqlite', '.db', '.zip'):
                raise SystemExit('Unexpected secret/runtime file: ' + str(relative))
            archive.write(path, relative.as_posix())
with zipfile.ZipFile(output) as archive:
    if archive.testzip() is not None:
        raise SystemExit('Release ZIP verification failed.')
print(output)
