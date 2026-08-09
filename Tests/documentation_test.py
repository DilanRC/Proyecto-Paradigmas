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

required = {
    "DER.pdf": ["tbproductor", "tbproductordireccion", "tbfinca", "tbbitacora", "cero claves", "restricciones, índices", "AUTO_INCREMENT"],
    "DAplicacion.pdf": ["JavaScript", "fetch/AJAX", "ProductorController", "PDO::prepare()", "respuesta HTTP JSON", "sin recargar"],
    "AvanceSemanal.pdf": ["Cristian Brenes", "cuatro tablas", "restricciones", "cero índices", "tbproductorid", "sentencias preparadas"],
}
for pdf_name, terms in required.items():
    pdf = ROOT / "Documentation" / pdf_name
    completed = subprocess.run(["pdftotext", str(pdf), "-"], check=True, text=True, capture_output=True)
    for term in terms:
        assert term in completed.stdout, f"{pdf_name} no contiene {term!r}"
    assert "CREATE TABLE IF NOT EXISTS tbparticipante" not in completed.stdout

print("OK documentation_test: tres PDF reproducibles y alineados con modelo, aplicación y avance.")
