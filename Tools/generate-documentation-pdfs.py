#!/usr/bin/env python3
"""Render the required Markdown documents as deterministic, text PDFs."""

from __future__ import annotations

import argparse
import re
import textwrap
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
DOCUMENTS = {
    "AvanceSemanal.md": "AvanceSemanal.pdf",
    "DAplicacion.md": "DAplicacion.pdf",
    "DER.md": "DER.pdf",
    "DiccionarioDatos.md": "DiccionarioDatos.pdf",
    "Decisiones.md": "Decisiones.pdf",
}


def printable(text: str) -> str:
    replacements = {"—": "-", "–": "-", "→": "->", "←": "<-", "…": "...", " ": " "}
    for source, target in replacements.items():
        text = text.replace(source, target)
    text = re.sub(r"`([^`]*)`", r"\1", text)
    text = text.replace("**", "").replace("__", "")
    return text.encode("cp1252", "replace").decode("cp1252")


def document_lines(markdown: str) -> list[tuple[str, int, str]]:
    lines: list[tuple[str, int, str]] = []
    code = False
    for raw in markdown.splitlines():
        stripped = raw.rstrip()
        if stripped.startswith("```"):
            code = not code
            continue
        if not stripped:
            lines.append(("regular", 10, ""))
            continue
        heading = re.match(r"^(#{1,3})\s+(.*)$", stripped)
        if heading:
            level = len(heading.group(1))
            lines.append(("bold", {1: 17, 2: 14, 3: 12}[level], printable(heading.group(2))))
            continue
        style = "mono" if code else "regular"
        width = 88 if code else 100
        text = printable(stripped)
        indent = len(text) - len(text.lstrip())
        prefix = text[:indent]
        wrapped = textwrap.wrap(text.lstrip(), width=max(20, width - indent),
            subsequent_indent="  ", replace_whitespace=False, drop_whitespace=False) or [""]
        lines.extend((style, 9, prefix + part) for part in wrapped)
    return lines


def escape_pdf(text: str) -> bytes:
    return text.replace("\\", "\\\\").replace("(", "\\(").replace(")", "\\)").encode("cp1252")


def build_pdf(markdown: str, title: str) -> bytes:
    pages: list[list[tuple[str, int, float, str]]] = [[]]
    y = 802.0
    for style, size, text in document_lines(markdown):
        leading = 8 if text == "" else size + (6 if style == "bold" else 4)
        if y - leading < 45:
            pages.append([])
            y = 802.0
        pages[-1].append((style, size, y, text))
        y -= leading

    page_object_numbers = [6 + index * 2 for index in range(len(pages))]
    objects: dict[int, bytes] = {
        1: b"<< /Type /Catalog /Pages 2 0 R >>",
        2: ("<< /Type /Pages /Count %d /Kids [%s] >>" %
            (len(pages), " ".join(f"{number} 0 R" for number in page_object_numbers))).encode(),
        3: b"<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>",
        4: b"<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>",
        5: b"<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>",
    }
    fonts = {"regular": "F1", "bold": "F2", "mono": "F3"}
    for index, page in enumerate(pages):
        page_number = page_object_numbers[index]
        content_number = page_number + 1
        commands = [f"BT /F2 8 Tf 1 0 0 1 500 820 Tm ({index + 1}/{len(pages)}) Tj ET".encode()]
        for style, size, position, text in page:
            commands.append(
                f"BT /{fonts[style]} {size} Tf 1 0 0 1 44 {position:.1f} Tm (".encode()
                + escape_pdf(text) + b") Tj ET"
            )
        stream = b"\n".join(commands) + b"\n"
        objects[page_number] = (
            f"<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] "
            f"/Resources << /Font << /F1 3 0 R /F2 4 0 R /F3 5 0 R >> >> "
            f"/Contents {content_number} 0 R >>"
        ).encode()
        objects[content_number] = f"<< /Length {len(stream)} >>\nstream\n".encode() + stream + b"endstream"

    result = bytearray(b"%PDF-1.4\n%\xe2\xe3\xcf\xd3\n")
    offsets = [0] * (max(objects) + 1)
    for number in sorted(objects):
        offsets[number] = len(result)
        result.extend(f"{number} 0 obj\n".encode())
        result.extend(objects[number])
        result.extend(b"\nendobj\n")
    xref = len(result)
    result.extend(f"xref\n0 {len(offsets)}\n".encode())
    result.extend(b"0000000000 65535 f \n")
    for offset in offsets[1:]:
        result.extend(f"{offset:010d} 00000 n \n".encode())
    result.extend(f"trailer\n<< /Size {len(offsets)} /Root 1 0 R >>\nstartxref\n{xref}\n%%EOF\n".encode())
    return bytes(result)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--check", action="store_true", help="fail if committed PDFs differ from their Markdown")
    args = parser.parse_args()
    for source_name, output_name in DOCUMENTS.items():
        source = ROOT / "Documentation" / source_name
        output = ROOT / "Documentation" / output_name
        expected = build_pdf(source.read_text(encoding="utf-8"), source.stem)
        if args.check:
            if not output.is_file() or output.read_bytes() != expected:
                raise SystemExit(f"PDF desactualizado: {output.relative_to(ROOT)}")
        else:
            output.write_bytes(expected)
            print(f"Generado {output.relative_to(ROOT)}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
