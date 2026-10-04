# AGENTS.md — Instrucciones para agentes de IA (y personas)

Este archivo es el punto de entrada para **cualquier** agente de IA que trabaje
en este repositorio (Claude Code, Codex, Cursor, Copilot, Gemini, etc.). Léelo
completo antes de hacer cualquier cosa. `CLAUDE.md` solo redirige aquí.

## 1. Obligatorio antes de cambiar algo

1. **Lee `MEMORIA.md` completo.** Contiene las decisiones tomadas, los
   cuidados que evitan romper el sistema y lo que quedó pendiente. No hagas
   cambios sin haberlo leído en esta sesión.
2. Lee `Documentation/Arquitectura.md` para ubicar vistas, rutas y lógica.
3. Revisa en qué rama estás (`git branch --show-current`). No trabajes
   directo en `main` ni en `dev`: crea o usa tu rama de trabajo.

## 2. Obligatorio después de cada cambio

- **Actualiza `MEMORIA.md`** en la sección "Registro de cambios" con: fecha,
  rama, qué cambió, archivos principales y cualquier cuidado nuevo. Si una
  decisión anterior deja de ser válida, corrígela en su sección (no dejes
  información contradictoria).
- Si el cambio agrega un pendiente o resuelve uno, actualiza "Pendientes".
- El cambio de `MEMORIA.md` va en el mismo commit que el código.

## 3. Proyecto en una línea

Ganado Cerca (TinderCows): marketplace de ganado (comprar, vender, fletes).
PHP 8.3 MVC sin framework + MySQL 8 en local (Postgres de Supabase en
producción) + JavaScript en módulos ES sin bundler + Supabase Auth.

## 4. Cómo levantar y probar

```bash
cp .env.example .env            # solo la primera vez; no subas .env
docker compose up --build -d    # app en http://localhost:8080
docker compose ps
```

Pruebas (corre las que tocan tu cambio y, antes de hacer commit, todas):

```bash
# Frontend (usar el glob; "node --test Tests/frontend/" falla en Windows)
node --test Tests/frontend/*.test.mjs
node Tests/frontend_contract_test.js
node Tests/ui_test.js
node Tests/frontend_contrast_test.mjs
node Tests/frontend/official_app_shell.eval.mjs

# Backend / esquema (dentro del contenedor)
docker compose exec -T app php Tests/<archivo>_test.php
docker compose exec -T app php services/supabase-database/tests/schema_test.php
```

Hay 4 pruebas de frontend que fallaban antes de esta línea de trabajo y no
son regresiones (ver `MEMORIA.md` → "Estado de pruebas"). Cualquier otro fallo
sí lo es: no hagas commit con pruebas nuevas en rojo.

## 5. Reglas de trabajo

**Generales**
- Lee el código que vas a tocar y a quien lo llama antes de editar. Reutiliza
  lo que ya existe (helpers, componentes, estilos); no dupliques.
- Cambios mínimos y enfocados. Nada de dependencias nuevas sin necesidad real.
- No cambies endpoints ni contratos de la API sin avisarlo y sin actualizar
  sus pruebas y `MEMORIA.md`.
- Si una petición choca con cómo está construida la app, pregunta antes de
  cambiar la lógica.
- Responde y documenta en español.

**Base de datos (backend)**
- El esquema no usa PK, FK, UNIQUE, DEFAULT, AUTO_INCREMENT, triggers ni
  rutinas: las reglas viven en PHP. Nombres en minúscula `tb<tabla><campo>`.
- Una columna nueva se agrega en **cuatro** lugares, o producción se rompe:
  1. `Database/SqlScripts/000instalacioncompleta.sql`
  2. `Database/Migrations/0NN<nombre>.sql` (bases MySQL existentes)
  3. `services/supabase-database/schema.sql` (Postgres)
  4. `services/supabase-database/migrate.php` → `EXPECTED_COLUMNS` y
     `ensureCurrentColumns()` con `ADD COLUMN IF NOT EXISTS`
  Y documenta en `Documentation/DiccionarioDatos.md` y `DER.md`, y regenera
  sus PDF con `python Tools/generate-documentation-pdfs.py`.
- Todo SQL con sentencias preparadas (`PDO::prepare`).

**Frontend**
- **No cambies la paleta.** Usa solo las variables `--tc-*` y las mezclas
  `color-mix` que ya existen. Coral (`--tc-primary`) solo para la acción
  principal de cada pantalla. Revisa contraste AA en tema claro y oscuro.
- **Caché:** al cambiar un CSS o JS, sube su `?v=` en todas las vistas que lo
  cargan. Si agregas un `export` a un módulo compartido, versiona su `import`
  en los módulos que lo usan (ej. `'./explore.js?v=foto-1'`); si no, un
  navegador con la copia vieja rompe la página.
- Formularios: usa el sistema de `onboarding.css` (`signup-*`, `auth-field`).
  Tarjetas de publicación: `buildCard()` de `Public/js/explore.js` (variante
  `{ compacta: true }` en la portada). Carrusel: `Public/js/shared/carousel.js`.
- Responsive (≈390px sin scroll horizontal), foco de teclado visible y
  etiquetas asociadas a sus campos.

**Seguridad**
- Destinos `?next=`: usa `Public/js/shared/next.js` (`safeNext`); nunca
  redirijas a un valor sin validar.
- Imágenes: solo URLs `https://` (validadas en PHP y en JS con `safeImageUrl`).
- No guardes contraseñas ni secretos en el navegador ni en el repo.

**Git**
- Commits con mensaje descriptivo en español. No hagas push ni merge a `dev`
  o `main` sin que la persona lo pida.
- Si cambias archivos con `git stash`, Git puede dejar finales de línea CRLF en
  la copia local; dos pruebas buscan `\n` en `base.css` y `api.js` (ver
  `MEMORIA.md`).
