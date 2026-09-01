#!/usr/bin/env python3
"""Regenera Public/js/shared/poblados.js desde las dos fuentes oficiales.

Uso:
    python3 Tools/generar-catalogo-territorial.py \
        --ign  territorio-costa-rica-oficial-2026.md \
        --inec "Localidades 2024.zip"

Estructura territorial: IGN DTA 2026 (7/84/494), autoridad unica.
Nombres de localidad: IGN 2026, reparados con INEC Localidades 2024 solo cuando
la correspondencia es inequivoca dentro del MISMO distrito. Ver DEC-FRONT-14.

SHA-256 esperados:
  IGN  Centros Poblados y Localidades (2026).xlsx
       ab4b1bf9a753e49c423398f6f746edad577ac8121bec76f2648e14281b0ca6cf
  INEC Localidades 2024.zip
       dc27c14948e289a1bd0f531e93051609b837ccd9f998cf43ef794e649cb3f9f6
"""
import argparse, collections, csv, hashlib, json, struct, unicodedata, zipfile
from pathlib import Path


def sin_tildes(texto):
    return ''.join(c for c in unicodedata.normalize('NFD', texto or '')
                   if unicodedata.category(c) != 'Mn')


def comparable(texto):
    """Sin tildes, en mayuscula y con espacios normalizados. NO quita 'nd'."""
    return ' '.join(sin_tildes(texto).upper().split())


def leer_dbf(datos):
    """Lee un DBF de shapefile sin dependencias externas."""
    nreg, largo_cab, largo_reg = struct.unpack('<IHH', datos[4:12])
    campos, pos = [], 32
    while datos[pos:pos + 1] not in (b'\r', b''):
        d = datos[pos:pos + 32]
        campos.append((d[:11].split(b'\x00')[0].decode('latin-1'), d[16]))
        pos += 32
    filas = []
    for i in range(nreg):
        r = datos[largo_cab + i * largo_reg: largo_cab + (i + 1) * largo_reg]
        if not r or r[0:1] == b'*':
            continue
        o, fila = 1, {}
        for nombre, ancho in campos:
            fila[nombre] = r[o:o + ancho].decode('utf-8', 'replace').strip()
            o += ancho
        filas.append(fila)
    return filas


def reinsertar(ign, inec):
    """Repone 'nd' en el nombre del IGN donde INEC lo indica.

    INEC publica en mayuscula y sin tildes, asi que solo aporta la POSICION: la
    grafia sale del IGN. La caja de la secuencia repuesta se decide por el
    nombre completo; mirar la letra contigua daba 'INDiana', porque la anterior
    es la inicial mayuscula de la palabra.
    """
    a, b = sin_tildes(ign).upper(), comparable(inec)
    if len(a) != len(ign):
        return None
    letras = [c for c in ign if c.isalpha()]
    par = 'ND' if letras and all(c.isupper() for c in letras) else 'nd'
    salida, i, j = [], 0, 0
    while j < len(b):
        if i < len(a) and a[i] == b[j]:
            salida.append(ign[i]); i += 1; j += 1
        elif b[j:j + 2] == 'ND':
            salida.append(par); j += 2
        else:
            return None
    if i != len(ign):
        return None
    resultado = ''.join(salida)
    return resultado if comparable(resultado) == b else None


def main():
    p = argparse.ArgumentParser()
    p.add_argument('--ign', required=True, help='markdown con el bloque JSON de la DTA 2026')
    p.add_argument('--inec', required=True, help='Localidades 2024.zip del INEC')
    p.add_argument('--salida', default='Public/js/shared/poblados.js')
    p.add_argument('--manifiesto', default='Documentation/correcciones-localidades.csv')
    args = p.parse_args()

    for ruta in (args.ign, args.inec):
        print(f'  sha256 {Path(ruta).name}: {hashlib.sha256(Path(ruta).read_bytes()).hexdigest()}')

    texto = Path(args.ign).read_text(encoding='utf-8').splitlines()
    inicio = next(i for i, l in enumerate(texto) if l.strip() == '```json')
    fin = next(i for i in range(len(texto) - 1, 0, -1) if texto[i].strip() == '```')
    ign = json.loads('\n'.join(texto[inicio + 1:fin]))

    with zipfile.ZipFile(args.inec) as z:
        dbf = leer_dbf(z.read(next(n for n in z.namelist() if n.endswith('.dbf'))))
    inec_por_distrito = collections.defaultdict(set)
    for fila in dbf:
        if fila.get('NOMB_LOC'):
            inec_por_distrito[fila['COD_UGED']].add(fila['NOMB_LOC'])

    catalogo, manifiesto = collections.OrderedDict(), []
    for prov in ign['provincias']:
        for canton in prov['cantones']:
            for distrito in canton['distritos']:
                codigo = distrito['codigo']
                brutos = []
                for loc in ign['localidadesPorDistrito'].get(codigo, []):
                    nombre = (loc.get('nombre') or loc.get('nombreNoOficial') or '').strip()
                    if nombre:
                        brutos.append(nombre)

                # 1) reparacion con INEC, siempre dentro del mismo distrito
                por_comparable = collections.defaultdict(set)
                for n in inec_por_distrito.get(codigo, ()):
                    por_comparable[comparable(n)].add(n)
                paso1 = []
                for nombre in brutos:
                    c = comparable(nombre)
                    if c in por_comparable:
                        paso1.append(nombre); continue
                    cands = {k for k in por_comparable if 'ND' in k and k.replace('ND', '') == c}
                    arreglado = None
                    if len(cands) == 1:
                        variantes = por_comparable[cands.pop()]
                        if len(variantes) == 1:
                            arreglado = reinsertar(nombre, next(iter(variantes)))
                    if arreglado:
                        manifiesto.append([codigo, nombre, arreglado, 'INEC_LOCALIDADES_2024'])
                        paso1.append(arreglado)
                    else:
                        paso1.append(nombre)

                # 2) el XLSX a veces trae el mismo lugar dos veces, una sana y
                #    otra mutilada; entonces la evidencia esta dentro del propio dato
                sanos = {comparable(n): n for n in paso1 if 'ND' in comparable(n)}
                final = []
                for nombre in paso1:
                    c = comparable(nombre)
                    if 'ND' not in c:
                        hermanos = [v for k, v in sanos.items() if k.replace('ND', '') == c]
                        if len(hermanos) == 1:
                            manifiesto.append([codigo, nombre, hermanos[0], 'IGN_2026_REGISTRO_HERMANO'])
                            final.append(hermanos[0]); continue
                    final.append(nombre)

                vistos, lista = set(), []
                for nombre in final:
                    c = comparable(nombre)
                    if c not in vistos:
                        vistos.add(c); lista.append(nombre)
                catalogo[codigo] = sorted(lista, key=comparable)

    with open(args.manifiesto, 'w', newline='', encoding='utf-8') as f:
        w = csv.writer(f)
        w.writerow(['codigoDistrito', 'ign_2026_original', 'nombre_corregido', 'fuenteNombre'])
        w.writerows(manifiesto)

    datos = '{\n' + '\n'.join(
        f"    '{c}': [" + ', '.join(json.dumps(n, ensure_ascii=False) for n in v) + '],'
        for c, v in catalogo.items()) + '\n}'
    print(f'  distritos {len(catalogo)}  localidades '
          f'{sum(len(v) for v in catalogo.values())}  correcciones {len(manifiesto)}')
    print('  Bloque de datos generado. La cabecera y las funciones de '
          f'{args.salida} se conservan a mano; sustituya solo el objeto POBLADOS.')
    Path(args.salida).with_suffix('.datos.js').write_text(datos, encoding='utf-8')


if __name__ == '__main__':
    main()
