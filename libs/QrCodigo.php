<?php

/**
 * Generador de codigos QR en PHP puro (sin librerias externas ni internet).
 *
 * Antes el sistema pedia la imagen del QR a un servicio en linea
 * (api.qrserver.com), asi que en un aula sin conexion no se podia proyectar
 * nada. Esta clase construye el QR localmente y lo devuelve como SVG.
 *
 * Implementa: modo byte, nivel de correccion M (recupera ~15%),
 * versiones 1 a 10 (hasta 213 caracteres), seleccion automatica de mascara.
 *
 * Uso:
 *     echo QrCodigo::svg('https://ejemplo.com/clase?c=A1B2C3D4', 260);
 */
class QrCodigo
{
    // Capacidad maxima en bytes por version (modo byte, nivel M)
    private const CAPACIDAD = [
        1 => 14,  2 => 26,  3 => 42,  4 => 62,  5 => 84,
        6 => 106, 7 => 122, 8 => 152, 9 => 180, 10 => 213
    ];

    // Estructura de bloques por version (nivel M):
    // [codewords totales, ECC por bloque, bloques grupo1, datos grupo1, bloques grupo2, datos grupo2]
    private const BLOQUES = [
        1  => [26,  10, 1, 16, 0, 0],
        2  => [44,  16, 1, 28, 0, 0],
        3  => [70,  26, 1, 44, 0, 0],
        4  => [100, 18, 2, 32, 0, 0],
        5  => [134, 24, 2, 43, 0, 0],
        6  => [172, 16, 4, 27, 0, 0],
        7  => [196, 18, 4, 31, 0, 0],
        8  => [242, 22, 2, 38, 2, 39],
        9  => [292, 22, 3, 36, 2, 37],
        10 => [346, 26, 4, 43, 1, 44],
    ];

    // Centros de los patrones de alineacion por version
    private const ALINEACION = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
        6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50]
    ];

    // Informacion de formato ya calculada (nivel M, mascaras 0 a 7)
    private const FORMATO = [0x5412, 0x5125, 0x5E7C, 0x5B4B, 0x45F9, 0x40CE, 0x4F97, 0x4AA0];

    // Informacion de version (solo se dibuja desde la version 7)
    private const VERSION_INFO = [7 => 0x07C94, 8 => 0x085BC, 9 => 0x09A99, 10 => 0x0A4D3];

    /** @var array<int,array<int,int>> Matriz de modulos: 1 = oscuro, 0 = claro */
    private array $matriz = [];
    /** @var array<int,array<int,bool>> Modulos reservados (patrones fijos) */
    private array $reservado = [];
    private int $tamano = 0;
    private int $version = 1;

    // ==================================================================
    // API publica
    // ==================================================================

    /**
     * Devuelve el QR como SVG listo para incrustar en el HTML.
     *
     * @param string $texto  Contenido a codificar
     * @param int    $lado   Tamano del SVG en pixeles
     * @param string $etiqueta Texto alternativo accesible
     */
    public static function svg(string $texto, int $lado = 260, string $etiqueta = 'Codigo QR'): string
    {
        $qr = new self();
        $matriz = $qr->generar($texto);
        $modulos = count($matriz);
        $borde = 4;                         // Zona tranquila obligatoria del estandar
        $total = $modulos + $borde * 2;

        // Un unico <path> con todos los modulos oscuros: mucho mas liviano
        // que dibujar un <rect> por modulo.
        $trazo = '';
        foreach ($matriz as $y => $fila) {
            foreach ($fila as $x => $oscuro) {
                if ($oscuro) {
                    $trazo .= 'M' . ($x + $borde) . ' ' . ($y + $borde) . 'h1v1h-1z';
                }
            }
        }

        $lado = max(80, min(1200, $lado));
        $alt  = htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8');

        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $lado . '" height="' . $lado . '"'
             . ' viewBox="0 0 ' . $total . ' ' . $total . '" shape-rendering="crispEdges"'
             . ' role="img" aria-label="' . $alt . '">'
             . '<rect width="' . $total . '" height="' . $total . '" fill="#ffffff"/>'
             . '<path d="' . $trazo . '" fill="#000000"/>'
             . '</svg>';
    }

    /** Devuelve el SVG como data URI, util dentro de un atributo src */
    public static function dataUri(string $texto, int $lado = 260): string
    {
        return 'data:image/svg+xml;base64,' . base64_encode(self::svg($texto, $lado));
    }

    // ==================================================================
    // Construccion del codigo
    // ==================================================================

    /** @return array<int,array<int,int>> */
    public function generar(string $texto): array
    {
        $longitud = strlen($texto);
        $this->version = $this->elegirVersion($longitud);

        if ($this->version === 0) {
            throw new InvalidArgumentException(
                'El texto es demasiado largo para un codigo QR (maximo ' . self::CAPACIDAD[10] . ' caracteres).'
            );
        }

        $this->tamano = 21 + ($this->version - 1) * 4;

        $datos = $this->construirCodewords($texto);
        $this->iniciarMatriz();
        $this->dibujarPatronesFijos();
        $this->colocarDatos($datos);

        // Se prueban las 8 mascaras y se conserva la de menor penalizacion,
        // que es la que el lector interpreta con mas fiabilidad.
        $mejorMascara = 0;
        $mejorPuntaje = PHP_INT_MAX;
        $matrizBase   = $this->matriz;

        for ($m = 0; $m < 8; $m++) {
            $this->matriz = $matrizBase;
            $this->aplicarMascara($m);
            $this->dibujarFormato($m);
            $puntaje = $this->evaluar();

            if ($puntaje < $mejorPuntaje) {
                $mejorPuntaje = $puntaje;
                $mejorMascara = $m;
            }
        }

        $this->matriz = $matrizBase;
        $this->aplicarMascara($mejorMascara);
        $this->dibujarFormato($mejorMascara);

        return $this->matriz;
    }

    private function elegirVersion(int $longitud): int
    {
        foreach (self::CAPACIDAD as $version => $maximo) {
            if ($longitud <= $maximo) {
                return $version;
            }
        }
        return 0;
    }

    /** Genera el flujo final de codewords (datos + correccion de errores) */
    private function construirCodewords(string $texto): array
    {
        [, $eccPorBloque, $bloques1, $datos1, $bloques2, $datos2] = self::BLOQUES[$this->version];

        // --- 1. Flujo de bits: modo byte + longitud + datos ---
        $bits = '0100';                                   // Indicador de modo byte
        $bitsLongitud = ($this->version >= 10) ? 16 : 8;  // A partir de v10 la cuenta ocupa 16 bits
        $bits .= str_pad(decbin(strlen($texto)), $bitsLongitud, '0', STR_PAD_LEFT);

        for ($i = 0, $n = strlen($texto); $i < $n; $i++) {
            $bits .= str_pad(decbin(ord($texto[$i])), 8, '0', STR_PAD_LEFT);
        }

        $totalDatos = $bloques1 * $datos1 + $bloques2 * $datos2;
        $capacidadBits = $totalDatos * 8;

        // Terminador: hasta 4 ceros si queda espacio
        $bits .= str_repeat('0', min(4, $capacidadBits - strlen($bits)));

        // Relleno hasta completar el ultimo byte
        if (strlen($bits) % 8 !== 0) {
            $bits .= str_repeat('0', 8 - (strlen($bits) % 8));
        }

        // Bytes de relleno alternados que exige el estandar
        $relleno = [0xEC, 0x11];
        $i = 0;
        while (strlen($bits) < $capacidadBits) {
            $bits .= str_pad(decbin($relleno[$i % 2]), 8, '0', STR_PAD_LEFT);
            $i++;
        }

        $codewords = [];
        foreach (str_split($bits, 8) as $byte) {
            $codewords[] = bindec($byte);
        }

        // --- 2. Reparto en bloques y calculo de la correccion de errores ---
        $bloquesDatos = [];
        $bloquesEcc   = [];
        $posicion     = 0;

        foreach ([[$bloques1, $datos1], [$bloques2, $datos2]] as [$cantidad, $tamanoBloque]) {
            for ($b = 0; $b < $cantidad; $b++) {
                $bloque = array_slice($codewords, $posicion, $tamanoBloque);
                $posicion += $tamanoBloque;
                $bloquesDatos[] = $bloque;
                $bloquesEcc[]   = $this->reedSolomon($bloque, $eccPorBloque);
            }
        }

        // --- 3. Intercalado: primero los datos, luego la correccion ---
        $salida = [];
        $maxDatos = max($datos1, $datos2);

        for ($i = 0; $i < $maxDatos; $i++) {
            foreach ($bloquesDatos as $bloque) {
                if (isset($bloque[$i])) {
                    $salida[] = $bloque[$i];
                }
            }
        }
        for ($i = 0; $i < $eccPorBloque; $i++) {
            foreach ($bloquesEcc as $bloque) {
                if (isset($bloque[$i])) {
                    $salida[] = $bloque[$i];
                }
            }
        }

        return $salida;
    }

    // ==================================================================
    // Reed-Solomon sobre el campo de Galois GF(256)
    // ==================================================================

    private static ?array $exp = null;
    private static ?array $log = null;

    private static function tablasGalois(): void
    {
        if (self::$exp !== null) {
            return;
        }

        self::$exp = array_fill(0, 512, 0);
        self::$log = array_fill(0, 256, 0);

        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$exp[$i] = $x;
            self::$log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D;   // Polinomio primitivo del estandar QR
            }
        }
        for ($i = 255; $i < 512; $i++) {
            self::$exp[$i] = self::$exp[$i - 255];
        }
    }

    private static function multiplicar(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }
        return self::$exp[(self::$log[$a] + self::$log[$b]) % 255];
    }

    /** Calcula los codewords de correccion de errores de un bloque */
    private function reedSolomon(array $datos, int $cantidadEcc): array
    {
        self::tablasGalois();

        // Polinomio generador de grado $cantidadEcc
        $generador = [1];
        for ($i = 0; $i < $cantidadEcc; $i++) {
            $nuevo = array_fill(0, count($generador) + 1, 0);
            foreach ($generador as $j => $coef) {
                $nuevo[$j]     ^= self::multiplicar($coef, 1);
                $nuevo[$j + 1] ^= self::multiplicar($coef, self::$exp[$i]);
            }
            $generador = $nuevo;
        }

        // Division polinomica: el residuo son los codewords de correccion
        $residuo = array_merge($datos, array_fill(0, $cantidadEcc, 0));

        for ($i = 0, $n = count($datos); $i < $n; $i++) {
            $factor = $residuo[$i];
            if ($factor === 0) {
                continue;
            }
            foreach ($generador as $j => $coef) {
                $residuo[$i + $j] ^= self::multiplicar($coef, $factor);
            }
        }

        return array_slice($residuo, count($datos), $cantidadEcc);
    }

    // ==================================================================
    // Dibujo de la matriz
    // ==================================================================

    private function iniciarMatriz(): void
    {
        $this->matriz    = array_fill(0, $this->tamano, array_fill(0, $this->tamano, 0));
        $this->reservado = array_fill(0, $this->tamano, array_fill(0, $this->tamano, false));
    }

    private function dibujarPatronesFijos(): void
    {
        $n = $this->tamano;

        // Los tres ojos de las esquinas, con su separador blanco
        foreach ([[0, 0], [$n - 7, 0], [0, $n - 7]] as [$fx, $fy]) {
            $this->dibujarOjo($fx, $fy);
        }

        // Patrones de sincronismo (linea alternada de la fila y columna 6)
        for ($i = 8; $i < $n - 8; $i++) {
            $valor = ($i % 2 === 0) ? 1 : 0;
            $this->fijar($i, 6, $valor);
            $this->fijar(6, $i, $valor);
        }

        // Patrones de alineacion
        $centros = self::ALINEACION[$this->version];
        foreach ($centros as $cy) {
            foreach ($centros as $cx) {
                // No se dibujan encima de los ojos de las esquinas
                if (($cx <= 8 && $cy <= 8) || ($cx <= 8 && $cy >= $n - 9) || ($cx >= $n - 9 && $cy <= 8)) {
                    continue;
                }
                $this->dibujarAlineacion($cx, $cy);
            }
        }

        // Modulo oscuro obligatorio
        $this->fijar(8, $n - 8, 1);

        // Reservar el espacio de la informacion de formato
        for ($i = 0; $i < 9; $i++) {
            $this->reservar($i, 8);
            $this->reservar(8, $i);
        }
        for ($i = 0; $i < 8; $i++) {
            $this->reservar($n - 1 - $i, 8);
            $this->reservar(8, $n - 1 - $i);
        }

        // Informacion de version (solo desde la version 7)
        if ($this->version >= 7) {
            $this->dibujarVersion();
        }
    }

    private function dibujarOjo(int $x0, int $y0): void
    {
        // Cuadro de 7x7 mas el separador blanco de 1 modulo alrededor
        for ($dy = -1; $dy <= 7; $dy++) {
            for ($dx = -1; $dx <= 7; $dx++) {
                $x = $x0 + $dx;
                $y = $y0 + $dy;
                if ($x < 0 || $y < 0 || $x >= $this->tamano || $y >= $this->tamano) {
                    continue;
                }

                $enBorde  = ($dx === 0 || $dx === 6 || $dy === 0 || $dy === 6);
                $enCentro = ($dx >= 2 && $dx <= 4 && $dy >= 2 && $dy <= 4);
                $dentro   = ($dx >= 0 && $dx <= 6 && $dy >= 0 && $dy <= 6);

                $this->fijar($x, $y, ($dentro && ($enBorde || $enCentro)) ? 1 : 0);
            }
        }
    }

    private function dibujarAlineacion(int $cx, int $cy): void
    {
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $borde  = (abs($dx) === 2 || abs($dy) === 2);
                $centro = ($dx === 0 && $dy === 0);
                $this->fijar($cx + $dx, $cy + $dy, ($borde || $centro) ? 1 : 0);
            }
        }
    }

    private function dibujarVersion(): void
    {
        $bits = self::VERSION_INFO[$this->version];
        $n = $this->tamano;

        for ($i = 0; $i < 18; $i++) {
            $bit = ($bits >> $i) & 1;
            $fila = intdiv($i, 3);
            $col  = $i % 3;
            $this->fijar($fila, $n - 11 + $col, $bit);
            $this->fijar($n - 11 + $col, $fila, $bit);
        }
    }

    private function dibujarFormato(int $mascara): void
    {
        $bits = self::FORMATO[$mascara];
        $n = $this->tamano;

        for ($i = 0; $i < 15; $i++) {
            $bit = ($bits >> $i) & 1;

            // Copia junto al ojo superior izquierdo
            if ($i < 6) {
                $this->matriz[$i][8] = $bit;
            } elseif ($i === 6) {
                $this->matriz[7][8] = $bit;
            } elseif ($i === 7) {
                $this->matriz[8][8] = $bit;
            } elseif ($i === 8) {
                $this->matriz[8][7] = $bit;
            } else {
                $this->matriz[8][14 - $i] = $bit;
            }

            // Copia repartida entre los otros dos ojos
            if ($i < 8) {
                $this->matriz[8][$n - 1 - $i] = $bit;
            } else {
                $this->matriz[$n - 15 + $i][8] = $bit;
            }
        }
    }

    /** Recorrido en zigzag de abajo a arriba, en columnas de dos modulos */
    private function colocarDatos(array $codewords): void
    {
        $n = $this->tamano;
        $bits = '';
        foreach ($codewords as $byte) {
            $bits .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
        }

        $indice = 0;
        $subiendo = true;

        for ($col = $n - 1; $col > 0; $col -= 2) {
            // La columna 6 es el patron de sincronismo vertical: se salta
            if ($col === 6) {
                $col--;
            }

            for ($paso = 0; $paso < $n; $paso++) {
                $fila = $subiendo ? ($n - 1 - $paso) : $paso;

                foreach ([$col, $col - 1] as $x) {
                    if ($this->reservado[$fila][$x]) {
                        continue;
                    }
                    // Si se acaban los bits, los modulos restantes quedan claros
                    $this->matriz[$fila][$x] = ($indice < strlen($bits)) ? (int)$bits[$indice] : 0;
                    $indice++;
                }
            }

            $subiendo = !$subiendo;
        }
    }

    private function aplicarMascara(int $mascara): void
    {
        for ($y = 0; $y < $this->tamano; $y++) {
            for ($x = 0; $x < $this->tamano; $x++) {
                if ($this->reservado[$y][$x]) {
                    continue;
                }
                if ($this->condicionMascara($mascara, $y, $x)) {
                    $this->matriz[$y][$x] ^= 1;
                }
            }
        }
    }

    private function condicionMascara(int $m, int $i, int $j): bool
    {
        return match ($m) {
            0 => ($i + $j) % 2 === 0,
            1 => $i % 2 === 0,
            2 => $j % 3 === 0,
            3 => ($i + $j) % 3 === 0,
            4 => (intdiv($i, 2) + intdiv($j, 3)) % 2 === 0,
            5 => (($i * $j) % 2) + (($i * $j) % 3) === 0,
            6 => ((($i * $j) % 2) + (($i * $j) % 3)) % 2 === 0,
            7 => ((($i + $j) % 2) + (($i * $j) % 3)) % 2 === 0,
            default => false,
        };
    }

    /** Puntaje de penalizacion del estandar: cuanto mas bajo, mas legible */
    private function evaluar(): int
    {
        $n = $this->tamano;
        $puntaje = 0;

        // Regla 1: series de 5 o mas modulos del mismo color
        for ($i = 0; $i < $n; $i++) {
            foreach ([true, false] as $porFila) {
                $serie = 1;
                for ($j = 1; $j < $n; $j++) {
                    $actual   = $porFila ? $this->matriz[$i][$j]     : $this->matriz[$j][$i];
                    $anterior = $porFila ? $this->matriz[$i][$j - 1] : $this->matriz[$j - 1][$i];

                    if ($actual === $anterior) {
                        $serie++;
                    } else {
                        if ($serie >= 5) {
                            $puntaje += 3 + ($serie - 5);
                        }
                        $serie = 1;
                    }
                }
                if ($serie >= 5) {
                    $puntaje += 3 + ($serie - 5);
                }
            }
        }

        // Regla 2: bloques de 2x2 del mismo color
        for ($y = 0; $y < $n - 1; $y++) {
            for ($x = 0; $x < $n - 1; $x++) {
                $v = $this->matriz[$y][$x];
                if ($v === $this->matriz[$y][$x + 1]
                    && $v === $this->matriz[$y + 1][$x]
                    && $v === $this->matriz[$y + 1][$x + 1]) {
                    $puntaje += 3;
                }
            }
        }

        // Regla 3: patrones que se confunden con los ojos de las esquinas
        $patronA = [1, 0, 1, 1, 1, 0, 1, 0, 0, 0, 0];
        $patronB = array_reverse($patronA);

        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j <= $n - 11; $j++) {
                $fila = array_slice($this->matriz[$i], $j, 11);
                if ($fila === $patronA || $fila === $patronB) {
                    $puntaje += 40;
                }

                $columna = [];
                for ($k = 0; $k < 11; $k++) {
                    $columna[] = $this->matriz[$j + $k][$i];
                }
                if ($columna === $patronA || $columna === $patronB) {
                    $puntaje += 40;
                }
            }
        }

        // Regla 4: desequilibrio entre modulos oscuros y claros
        $oscuros = 0;
        foreach ($this->matriz as $fila) {
            $oscuros += array_sum($fila);
        }
        $porcentaje = ($oscuros * 100) / ($n * $n);
        $puntaje += (int)(abs($porcentaje - 50) / 5) * 10;

        return $puntaje;
    }

    private function fijar(int $x, int $y, int $valor): void
    {
        if ($x < 0 || $y < 0 || $x >= $this->tamano || $y >= $this->tamano) {
            return;
        }
        $this->matriz[$y][$x]    = $valor;
        $this->reservado[$y][$x] = true;
    }

    private function reservar(int $x, int $y): void
    {
        if ($x >= 0 && $y >= 0 && $x < $this->tamano && $y < $this->tamano) {
            $this->reservado[$y][$x] = true;
        }
    }
}
