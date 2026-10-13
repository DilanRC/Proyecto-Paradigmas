#!/usr/bin/env python3
"""Read-only CLI diagnostic: isolated copy, unreachable local DB, no real credentials."""
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory(prefix='ganado-diagnostic-test-') as temporary:
    fixture = Path(temporary)
    (fixture / 'Tools').mkdir()
    (fixture / 'Configuration').mkdir()
    shutil.copy2(ROOT / 'Tools/diagnostico-local.php', fixture / 'Tools')
    for name in ['Configuration.php', 'Compatibility.php', 'Database.php', 'LocalDatabaseCredentials.php']:
        shutil.copy2(ROOT / 'Configuration' / name, fixture / 'Configuration')
    env = os.environ.copy()
    env.update(DB_HOST='127.0.0.1', DB_PORT='1', DB_NAME='diagnostic_fixture', DB_USER='private_user',
               DB_PASS='private_password', POSTGRES_URL='', POSTGRES_URL_NON_POOLING='')
    result = subprocess.run([env.get('PHP_BINARY', 'php'), str(fixture / 'Tools/diagnostico-local.php')],
                            env=env, capture_output=True, text=True, timeout=15)
    assert result.stdout, result.stderr
    report = json.loads(result.stdout)
    assert result.returncode == 1 and report['base_datos']['conexion'] == 'fallo'
    assert report['php_apache'].startswith('desconocido') and report['zona_horaria_aplicacion'] == 'UTC'
    assert report['asistente_web']['disponible_en_este_php'] == (
        report['asistente_web']['plataforma_linux']
        and report['asistente_web']['mysqli_disponible']
        and report['asistente_web']['posix_disponible']
    )
    assert 'private_user' not in result.stdout + result.stderr and 'private_password' not in result.stdout + result.stderr
print('OK local_diagnostic_test: fallo seguro, JSON, UTC, PHP Apache desconocido y credenciales ocultas.')
