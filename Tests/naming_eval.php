<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [];

function evaluate(string $name, bool $passed, string $evidence): void
{
    global $checks;
    $checks[] = ['criterio' => $name, 'cumple' => $passed, 'evidencia' => $evidence];
}

function read_eval(string $root, string $path): string
{
    $value = @file_get_contents($root . '/' . $path);
    return is_string($value) ? $value : '';
}

$readme = read_eval($root, 'README.md');
$decisions = read_eval($root, 'Documentation/Decisiones.md');
$dictionary = read_eval($root, 'Documentation/DiccionarioDatos.md');
$schema = implode("\n", array_map('file_get_contents', glob($root . '/Database/SqlScripts/*.sql') ?: []));
$api = read_eval($root, 'Public/api/productores.php');

evaluate('base_oficial', str_contains($readme, 'dbtindercows') && str_contains($schema, 'dbtindercows'), 'README y SQL nombran dbtindercows');
evaluate('api_espanol', str_contains($readme, '/api/productores.php') && $api !== '', 'README y endpoint usan productores.php');
$decisionCodes = [];
preg_match_all('/DEC-00[1-8]/', $decisions, $decisionMatches);
$decisionCodes = array_unique($decisionMatches[0] ?? []);
evaluate('decisiones_001_008', count($decisionCodes) === 8, 'Documentation/Decisiones.md contiene DEC-001 a DEC-008');
evaluate('modelo_participante_roles', str_contains($decisions, 'tbparticipante') && str_contains($decisions, 'tbparticipanterol'), 'Decisiones explica participante y roles');
evaluate('direccion_principal', str_contains($decisions, 'dirección principal activa') && str_contains($schema, 'uq_tbparticipantedireccion_principal_activa'), 'Política documentada y restricción física');
evaluate('identidad_unica', str_contains($decisions, 'número normalizado') && str_contains($schema, 'uq_tbparticipanteidentificacion_tipo_numero_normalizado'), 'Política documentada y UNIQUE compuesto');
evaluate('auditoria_no_autenticada', str_contains($decisions, 'NO_AUTENTICADO') && str_contains($schema, 'tbusuarioId'), 'Actor no autenticado y usuario anulable');
evaluate('asociacion_no_propiedad', str_contains($decisions, 'asociación') && str_contains($decisions, 'no propiedad'), 'La documentación no afirma propiedad');
evaluate('diccionario_fisico', $dictionary !== '' && str_contains($dictionary, 'tbparticipante') && str_contains($dictionary, 'tbbitacora'), 'Diccionario cubre entidades de inicio a fin');
evaluate('readme_operativo', str_contains($readme, 'docker compose') && str_contains($readme, '3307') && str_contains($readme, '8081'), 'README contiene arranque y puertos');

$passed = count(array_filter($checks, static fn (array $check): bool => $check['cumple']));
$total = count($checks);
$score = $total === 0 ? 0 : (int) round(100 * $passed / $total);
$report = ['eval' => 'contrato_documentacion_productores', 'score' => $score, 'threshold' => 100, 'checks' => $checks];
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

if ($score !== 100) {
    $failed = array_column(array_filter($checks, static fn (array $check): bool => !$check['cumple']), 'criterio');
    throw new RuntimeException('Eval incompleta: ' . implode(', ', $failed));
}
