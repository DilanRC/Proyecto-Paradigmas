#!/usr/bin/env python3
"""Gate for deterministic PDFs and required model/application content."""

from __future__ import annotations

import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
subprocess.run(["python3", "Tools/generate-documentation-pdfs.py", "--check"], cwd=ROOT, check=True)

readme = (ROOT / "README.md").read_text(encoding="utf-8")
assert "POST no acepta `direccionPrincipal`" in readme
assert "La dirección se completa en un PUT posterior" in readme
xampp = (ROOT / "Documentation/Instalacion-XAMPP.md").read_text(encoding="utf-8")
assert "/opt/lampp/Proyecto-Paradigmas/Public" in xampp
assert "Mantenga la raíz del" in xampp and "fuera de `htdocs`" in xampp
assert "debe ser HTTP 404" in xampp
assert "ni la exponga como subcarpeta" in readme
assert "PHP 8.0 o posterior" in xampp and "PHP 8.0 o posterior" in readme
assert "la versión de Ubuntu y de XAMPP del profesor Brenes es desconocida" in xampp
assert "PHP Apache es desconocida" in xampp
assert "8.0.30" in xampp and "10.4.32" in xampp and "5.7.44" in xampp
assert "024jsontextportable.sql" in xampp and "LONGTEXT" in xampp
assert "no requieren Node ni Composer para XAMPP" in xampp
assert "Auth, Storage, Realtime y mapas" in xampp
assert "TraceEnable Off" in xampp and "ErrorDocument 405 /api/metodo-no-permitido.php" in xampp
assert "PHP 8.0 no puede" not in xampp and "requiere PHP 8.3" not in xampp

required = {
    "DER.pdf": ["tbproductor", "tbproductordireccion", "tbfinca", "tbbitacora", "tbcomprador", "llave primaria por tabla", "restricciones, índices", "AUTO_INCREMENT"],
    "DAplicacion.pdf": ["JavaScript", "fetch/AJAX", "ProductorController", "PDO::prepare()", "respuesta HTTP JSON", "sin recargar"],
    "AvanceSemanal.pdf": ["Cristian Brenes", "cinco tablas", "tbcomprador", "restricciones", "cero índices", "tbproductorid", "sentencias preparadas"],
}
for pdf_name, terms in required.items():
    pdf = ROOT / "Documentation" / pdf_name
    completed = subprocess.run(["pdftotext", str(pdf), "-"], check=True, text=True, capture_output=True)
    for term in terms:
        assert term in completed.stdout, f"{pdf_name} no contiene {term!r}"
    assert "CREATE TABLE IF NOT EXISTS tbparticipante" not in completed.stdout

print("OK documentation_test: tres PDF reproducibles y alineados con modelo, aplicación y avance.")
