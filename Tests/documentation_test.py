#!/usr/bin/env python3
"""Gate for deterministic PDFs and required model/application content."""

from __future__ import annotations

import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
subprocess.run(["python3", "Tools/generate-documentation-pdfs.py", "--check"], cwd=ROOT, check=True)

required = {
    "DER.pdf": ["tbproductores", "tbproductoresdireccion", "tbproductoresfinca", "tbbitacora", "RESTRICT"],
    "DAplicacion.pdf": ["JavaScript", "fetch/AJAX", "ProductorController", "respuesta HTTP JSON", "sin recargar"],
    "AvanceSemanal.pdf": ["Cristian Brenes", "cuatro tablas", "utf8mb4_unicode_ci", "ON UPDATE RESTRICT"],
}
for pdf_name, terms in required.items():
    pdf = ROOT / "Documentation" / pdf_name
    completed = subprocess.run(["pdftotext", str(pdf), "-"], check=True, text=True, capture_output=True)
    for term in terms:
        assert term in completed.stdout, f"{pdf_name} no contiene {term!r}"
    assert "CREATE TABLE IF NOT EXISTS tbparticipante" not in completed.stdout

print("OK documentation_test: tres PDF reproducibles y alineados con modelo, aplicación y avance.")
