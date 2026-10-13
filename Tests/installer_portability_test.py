#!/usr/bin/env python3
"""Installer regression checks against a fake client, never a database."""
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[1]
linux_installer = (ROOT / 'instalar-local.sh').read_text()
windows_installer = (ROOT / 'instalar-local.ps1').read_text()
credentials = (ROOT / 'Configuration/LocalDatabaseCredentials.php').read_text()
local_diagnostic = (ROOT / 'Tools/diagnostico-local.php').read_text()
database_configuration = (ROOT / 'Configuration/Database.php').read_text()
installer_service = (ROOT / 'Application/Service/LocalDatabaseInstaller.php').read_text()
assert 'DB_PASS="$(configuracion DB_PASS \'\')"' in linux_installer
assert '[string]::IsNullOrWhiteSpace($dbPass)' in windows_installer
assert '$sqlUsuario | & $cli -u root --default-character-set=utf8mb4' in windows_installer
assert '& $cli -u root -e $sqlUsuario' not in windows_installer
assert not (ROOT / 'Public/instalacion.php').exists()
assert "PHP_OS_FAMILY !== 'Linux' || !function_exists('posix_geteuid')" in credentials
assert "extension_loaded('mysqli')" in installer_service
assert "'mysqli', 'posix'" in local_diagnostic and 'asistente_web' not in local_diagnostic
assert "$ok = $report['php_minimo_8_0'] && $report['extensiones']['pdo_mysql'] && $report['extensiones']['mbstring']" in local_diagnostic
assert "$ok = $report['php_minimo_8_0']" in local_diagnostic and "&& $report['extensiones']['posix']" not in local_diagnostic.split('$ok = ', 1)[1]
assert 'LocalDatabaseBootstrap::applies()' in database_configuration and 'LocalDatabaseBootstrap::connection()' in database_configuration
with tempfile.TemporaryDirectory(prefix='ganado-installer-test-') as temporary:
    fixture = Path(temporary)
    shutil.copy2(ROOT / 'instalar-local.sh', fixture)
    client = fixture / 'mysql-test'
    client.write_text('''#!/usr/bin/env python3
import json, os, sys
from pathlib import Path
args = sys.argv[1:]
body = sys.stdin.read() if '-e' not in args else ''
with open(os.environ['TEST_CLIENT_LOG'], 'a') as out:
    out.write(json.dumps({'args': args, 'sql': body}) + '\\n')
query = args[args.index('-e') + 1] if '-e' in args else body
if '-u' in args and args[args.index('-u') + 1] != 'root' and os.environ.get('TEST_REFUSE_APP') == '1':
    sys.exit(1)
if 'mysql.user' in query: print(os.environ.get('TEST_USER_EXISTS', '0'))
elif 'information_schema.tables' in query: print('1')
elif 'COUNT(*) FROM tbpagometodo' in query: print('1')
elif 'SHOW TABLES' in query: print('tbpersona')
elif 'tbadministradorcorreoelectronico' in query: print('demo@example.test')
else: print('1')
''')
    client.chmod(0o700)
    log = fixture / 'calls.jsonl'
    secret = "p'ass\\word"
    environment = os.environ.copy()
    for key in ['DB_NAME', 'DB_USER', 'DB_PASS', 'DB_HOST', 'DB_PORT', 'DB_ROOT_PASS', 'MYSQL_CLIENT', 'XAMPP_ROOT', 'DB_ACCOUNT_HOST']:
        environment.pop(key, None)
    environment.update(TEST_CLIENT_LOG=str(log), MYSQL_CLIENT=str(client), DB_HOST='127.0.0.1', DB_PORT='13306', DB_PASS=secret)
    def run(**updates):
        log.write_text('')
        return subprocess.run(['bash', str(fixture / 'instalar-local.sh')], env={**environment, **updates}, capture_output=True, text=True)
    result = run()
    assert result.returncode == 0, result.stderr
    calls = [json.loads(line) for line in log.read_text().splitlines()]
    assert calls and all('--host=127.0.0.1' in c['args'] and '--port=13306' in c['args'] and '--protocol=TCP' in c['args'] and c['args'][0] == '--no-defaults' for c in calls)
    sql = '\n'.join(c['sql'] for c in calls)
    assert "p''ass\\word" in sql and 'NO_BACKSLASH_ESCAPES' in sql
    assert secret not in result.stdout + result.stderr and all(secret not in ' '.join(c['args']) for c in calls)
    result = run(DB_PASS='')
    assert result.returncode != 0 and 'no hay una contrasena predeterminada' in result.stderr
    assert log.read_text() == ''
    result = run(TEST_USER_EXISTS='1')
    assert result.returncode == 0
    assert 'CREATE USER' not in log.read_text() and 'ALTER USER' not in log.read_text()
    result = run(TEST_USER_EXISTS='1', TEST_REFUSE_APP='1')
    assert result.returncode != 0 and 'GRANT' not in log.read_text() and 'DROP DATABASE' not in log.read_text()
    for values in [dict(DB_NAME='evil`;DROP DATABASE x'), dict(DB_PORT='0'), dict(DB_NAME='otra_base')]:
        result = run(**values)
        assert result.returncode != 0 and log.read_text() == ''
    environment.pop('MYSQL_CLIENT')
    alternate = fixture / 'custom-xampp' / 'bin'
    alternate.mkdir(parents=True)
    shutil.copy2(client, alternate / 'mysql')
    result = run(XAMPP_ROOT=str(alternate.parent))
    assert result.returncode == 0 and str(alternate / 'mysql') in result.stdout
print('OK installer_portability_test: host/puerto, cliente, credenciales existentes, SQL literal y rechazo seguro.')
