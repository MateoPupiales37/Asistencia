<?php
/**
 * Grafico de pastel (dona) dibujado con SVG puro, sin librerias externas.
 *
 * Variables esperadas:
 *   $datos   array de ['etiqueta' => string, 'total' => int]
 *   $tituloGrafico  titulo del grafico
 *   $vacio          (opcional) texto a mostrar cuando no hay datos
 *   $enlaceBase     (opcional) URL a la que lleva cada porcion; se le agrega
 *                   el valor de 'filtro' de cada dato como parametro
 *
 * Se dibuja con stroke-dasharray sobre un circulo: cada porcion es un tramo
 * del contorno, asi que no hace falta calcular arcos ni rutas complicadas.
 */

$datos  = $datos  ?? [];
// Variable propia: $titulo pertenece al titulo de la pagina
$tituloGrafico = $tituloGrafico ?? 'Distribución';
$vacio  = $vacio  ?? 'Sin datos suficientes todavía.';
$enlaceBase = $enlaceBase ?? null;

$total = 0;
foreach ($datos as $d) {
    $total += (int)$d['total'];
}

// Paleta institucional: azul y dorado del ISTPET, mas apoyos legibles
$paleta = ['#2C356D', '#B79B4A', '#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#0EA5E9'];

// Geometria de la dona
$radio        = 60;
$circunferencia = 2 * M_PI * $radio;
$acumulado    = 0.0;
?>

<div class="pastel-card">
    <h3 class="pastel-titulo"><?= htmlspecialchars($tituloGrafico) ?></h3>

    <?php if ($total === 0): ?>
        <p class="pastel-vacio"><?= htmlspecialchars($vacio) ?></p>
    <?php else: ?>
        <div class="pastel-cuerpo">
            <svg class="pastel-svg" viewBox="0 0 160 160" role="img"
                 aria-label="<?= htmlspecialchars($tituloGrafico) ?>: <?= (int)$total ?> registros">
                <g transform="rotate(-90 80 80)">
                    <?php foreach ($datos as $i => $d):
                        $valor   = (int)$d['total'];
                        $porcion = $valor / $total;
                        $largo   = $porcion * $circunferencia;
                        $color   = $paleta[$i % count($paleta)];
                        $etiqueta = (string)($d['etiqueta'] ?? '');
                    ?>
                        <circle cx="80" cy="80" r="<?= $radio ?>"
                                fill="none"
                                stroke="<?= $color ?>"
                                stroke-width="30"
                                stroke-dasharray="<?= round($largo, 2) ?> <?= round($circunferencia - $largo, 2) ?>"
                                stroke-dashoffset="<?= round(-$acumulado, 2) ?>">
                            <title><?= htmlspecialchars($etiqueta) ?>: <?= $valor ?> (<?= round($porcion * 100) ?>%)</title>
                        </circle>
                    <?php $acumulado += $largo; endforeach; ?>
                </g>
                <!-- Total al centro de la dona -->
                <text x="80" y="76" text-anchor="middle" class="pastel-num"><?= (int)$total ?></text>
                <text x="80" y="93" text-anchor="middle" class="pastel-sub">registros</text>
            </svg>

            <ul class="pastel-leyenda">
                <?php foreach ($datos as $i => $d):
                    $valor = (int)$d['total'];
                    $pct   = round(($valor / $total) * 100);
                    // Cada porcion puede llevar a un reporte ya filtrado.
                    // El atributo se arma aparte para no anidar comillas
                    // dentro del HTML, que es ilegible y se rompe facil.
                    $enlace = ($enlaceBase !== null && isset($d['filtro']))
                        ? $enlaceBase . (str_contains($enlaceBase, '?') ? '&' : '?') . $d['filtro']
                        : null;

                    $atributos = '';
                    if ($enlace !== null) {
                        $destino   = htmlspecialchars($enlace, ENT_QUOTES, 'UTF-8');
                        $atributos = ' class="clicable" title="Ver estos registros en el reporte"'
                                   . ' onclick="window.location=&quot;' . $destino . '&quot;"';
                    }
                ?>
                    <li<?= $atributos ?>>
                        <span class="pastel-punto" style="background: <?= $paleta[$i % count($paleta)] ?>"></span>
                        <span class="pastel-etiqueta"><?= htmlspecialchars((string)($d['etiqueta'] ?? '')) ?></span>
                        <span class="pastel-valor"><?= $valor ?> <small>(<?= $pct ?>%)</small></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>

<?php
// Se limpian para que el proximo grafico de esta misma pagina no herede
// el titulo ni el mensaje de vacio del anterior.
unset($tituloGrafico, $vacio, $datos, $enlaceBase);
