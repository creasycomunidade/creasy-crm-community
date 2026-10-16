"""Build a secret-free, precompiled Community archive and separate collector."""
from pathlib import Path
import argparse, hashlib, json, zipfile

ROOT = Path(__file__).resolve().parent.parent
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--vendor-from', required=True, help='Previously verified production ZIP; only vendor/ entries are reused.')
args = parser.parse_args()
entries = {}
for folder in ['app','config','routes','resources','lang','database/migrations','database/seeders','database/factories','database/community-schema','tests']:
    for p in (ROOT/folder).rglob('*'):
        if p.is_file() and not p.is_symlink() and p.suffix not in ['.log','.bak','.sqlite','.zip']:
            entries[p.relative_to(ROOT).as_posix()] = p.read_bytes()
for p in (ROOT/'public').rglob('*'):
    rel = p.relative_to(ROOT).as_posix()
    if rel.startswith('public/storage/') or p.is_symlink() or not p.is_file() or rel == 'public/hot': continue
    entries[rel] = p.read_bytes()
files = ['artisan','.env.example','.htaccess','.branding','composer.json','composer.lock','package.json','package-lock.json','bootstrap/app.php','bootstrap/providers.php','vite.config.js','tailwind.config.js','postcss.config.js','jsconfig.json','vitest.config.js','phpunit.xml','LICENSE','LICENSING.md','THIRD_PARTY_NOTICES.md','PRIVACIDADE.md','docs/INSTALACAO-SEM-TERMINAL.md','docs/REMOVER-TELEMETRIA.md','tools/telemetry-server/README.md','tools/telemetry-server/.htaccess','tools/telemetry-server/private/.htaccess','tools/telemetry-server/private/bootstrap.php','tools/telemetry-server/public/.htaccess','tools/telemetry-server/public/index.php','tools/telemetry-server/public/api/installations.php','tools/telemetry-server/public/api/heartbeat.php','installer/build-env/.gitignore','installer/build-community-public.py']
for name in files: entries[name] = (ROOT/name).read_bytes()
entries['README.md'] = (ROOT/'docs/README-COMMUNITY.md').read_bytes()
entries['.gitignore'] = b'''/node_modules/
/.env
/.env.*
!/.env.example
/.codex*/
/.agents/
/dist/
/.phpunit*
/bootstrap/cache/*
!/bootstrap/cache/.gitignore
/storage/**
!/storage/**/
!/storage/**/.gitignore
/public/hot
/public/storage/*
!/public/storage/.htaccess
*.log
*.sqlite
*.sql.gz
'''
entries['public/storage/.htaccess'] = b'Options -Indexes\n<FilesMatch "\\.(php[0-9]?|phtml|phar)$">\nRequire all denied\n</FilesMatch>\n'
for folder in ['storage/app/private','storage/app/public','storage/framework/cache/data','storage/framework/sessions','storage/framework/views','storage/logs','bootstrap/cache']:
    entries[folder+'/.gitignore'] = b'*\n!.gitignore\n'
with zipfile.ZipFile(args.vendor_from) as z:
    for name in z.namelist():
        if name.startswith('vendor/') and not name.endswith('/'):
            entries[name] = z.read(name)
assert 'vendor/autoload.php' in entries and 'public/build/manifest.json' in entries
assert '.env' not in entries and 'storage/installed.lock' not in entries
assert b'"handsontable"' not in entries['package-lock.json']
assert not any('handsontable' in n.lower() for n in entries)
# Reject known private values without printing them or including them in reports.
import re
private_env = (ROOT/'.env').read_text(encoding='utf-8') if (ROOT/'.env').exists() else ''
secrets = []
for line in private_env.splitlines():
    if '=' not in line: continue
    key,value=line.split('=',1); value=value.strip().strip('"').strip("'")
    if re.search(r'(PASSWORD|SECRET|TOKEN|APP_KEY)$',key) and len(value)>=12 and not re.fullmatch(r'\$\{[A-Z0-9_]+\}',value): secrets.append(value.encode())
for name,data in entries.items():
    assert not any(value in data for value in secrets), 'Private value detected in '+name
    if name.startswith(('app/','resources/','config/')):
        assert b'waha.ats.api.br' not in data and b'creasy_local' not in data, 'Local reference in '+name
entries['release-manifest.json'] = json.dumps({n:hashlib.sha256(b).hexdigest() for n,b in entries.items()},indent=2).encode()
out=ROOT/'dist';out.mkdir(exist_ok=True)
target=out/'CREASY-CRM-COMMUNITY-GITHUB.zip'
with zipfile.ZipFile(target,'w',zipfile.ZIP_DEFLATED) as z:
    for n,b in entries.items():z.writestr(n,b)
with zipfile.ZipFile(target) as z: assert z.testzip() is None
target.with_suffix('.zip.sha256').write_text(hashlib.sha256(target.read_bytes()).hexdigest()+'  '+target.name+'\n')
collector=out/'CREASY-TELEMETRIA-SERVIDOR.zip'
with zipfile.ZipFile(collector,'w',zipfile.ZIP_DEFLATED) as z:
    for p in (ROOT/'tools/telemetry-server').rglob('*'):
        if p.is_file() and p.suffix not in ['.sqlite','.txt','.lock']:
            z.write(p,p.relative_to(ROOT/'tools/telemetry-server').as_posix())
print(json.dumps({'crm':str(target),'files':len(entries),'bytes':target.stat().st_size,'collector':str(collector),'secrets_included':False},indent=2))
