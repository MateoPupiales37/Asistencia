<?php

/**
 * Carga en produccion los datos traidos del servidor local.
 *
 * Se ejecuta sin argumentos desde la consola del propio servidor:
 *
 *     php database/migrar_datos.php
 *
 * Va sin parametros a proposito. Pegar una orden larga en la consola web de
 * Railway suele ensuciarla con los caracteres de control del pegado
 * (^[[200~ al principio y ~ al final), y entonces la ruta del archivo llega
 * mal y falla con "No such file or directory".
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo se ejecuta desde la consola.\n");
}

$archivo = __DIR__ . '/migrar_mis_datos.sql';

if (!is_file($archivo)) {
    echo "\n  No se encuentra migrar_mis_datos.sql\n\n";
    echo "  Suele significar que el servidor todavia tiene una version anterior\n";
    echo "  del codigo. Haz 'git push' desde tu computadora, espera a que\n";
    echo "  Railway termine de desplegar y vuelve a intentarlo.\n\n";
    exit(1);
}

// ---------------------------------------------------------------------
// Conexion con las variables del propio servidor
// ---------------------------------------------------------------------
$host   = getenv('DB_HOST') ?: 'localhost';
$puerto = getenv('DB_PORT') ?: '3306';
$base   = getenv('DB_NAME') ?: 'railway';
$user   = getenv('DB_USER') ?: 'root';
$clave  = getenv('DB_PASS');
$clave  = ($clave === false) ? '' : $clave;

echo "\n  Servidor : {$host}:{$puerto}\n";
echo "  Base     : {$base}\n\n";
echo "  Conectando... ";

try {
    $db = new PDO(
        "mysql:host={$host};port={$puerto};dbname={$base};charset=utf8mb4",
        $user,
        $clave,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 20]
    );
    echo "listo\n";
} catch (PDOException $e) {
    echo "FALLO\n\n  {$e->getMessage()}\n\n";
    exit(1);
}

// ---------------------------------------------------------------------
// Las tablas tienen que existir antes de meterles datos
// ---------------------------------------------------------------------
$tablas = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('usuarios', $tablas, true)) {
    echo "\n  La base todavia no tiene las tablas.\n";
    echo "  Ejecuta primero:  php database/instalar_remoto.php\n\n";
    exit(1);
}

// ---------------------------------------------------------------------
// Ejecutar la migracion
// ---------------------------------------------------------------------
echo "\n  Cargando los datos...\n";

$sql = file_get_contents($archivo);
$sql = preg_replace('/^\s*USE\s+[^;]+;/mi', '', $sql);

$ejecutadas = 0;
$errores = [];

foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $sentencia) {
    $limpia = trim(preg_replace('/^--.*$/m', '', $sentencia));
    if ($limpia === '') {
        continue;
    }

    try {
        $db->exec($limpia);
        $ejecutadas++;
    } catch (PDOException $e) {
        $errores[] = substr(preg_replace('/\s+/', ' ', $limpia), 0, 70) . '  -> ' . $e->getMessage();
    }
}

echo "  {$ejecutadas} sentencia(s) ejecutada(s)\n";

if ($errores) {
    echo "\n  Hubo " . count($errores) . " problema(s):\n";
    foreach ($errores as $e) {
        echo "   - {$e}\n";
    }
}

// ---------------------------------------------------------------------
// Resultado
// ---------------------------------------------------------------------
$contar = static function (string $sql) use ($db): int {
    return (int)($db->query($sql)->fetch(PDO::FETCH_ASSOC)['n'] ?? 0);
};

$usuarios    = $contar("SELECT COUNT(*) n FROM usuarios");
$docentes    = $contar("SELECT COUNT(*) n FROM usuarios WHERE rol = 'docente'");
$materias    = $contar("SELECT COUNT(*) n FROM materias WHERE activa = 1");
$cursos      = $contar("SELECT COUNT(*) n FROM cursos WHERE activo = 1");
$estudiantes = $contar("SELECT COUNT(*) n FROM estudiantes WHERE activo = 1");
$matriculas  = $contar("SELECT COUNT(*) n FROM matriculas");

echo "\n  ---------------------------------------------\n";
printf("  Cuentas       : %d  (%d docentes)\n", $usuarios, $docentes);
printf("  Materias      : %d\n", $materias);
printf("  Cursos        : %d\n", $cursos);
printf("  Estudiantes   : %d\n", $estudiantes);
printf("  Matriculas    : %d\n", $matriculas);
echo "  ---------------------------------------------\n";

// Una materia en un semestre la dicta un solo docente: se comprueba que la
// carga no haya reintroducido el problema que se acababa de corregir
$conflictos = $db->query(
    "SELECT m.nombre, c.semestre, COUNT(DISTINCT c.docente_id) AS d
     FROM cursos c JOIN materias m ON c.materia_id = m.id
     WHERE c.activo = 1
     GROUP BY c.materia_id, c.semestre HAVING d > 1"
)->fetchAll(PDO::FETCH_ASSOC);

if ($conflictos) {
    echo "\n  ATENCION: hay materias con mas de un docente en el mismo semestre:\n";
    foreach ($conflictos as $c) {
        echo "   - {$c['nombre']} / {$c['semestre']}: {$c['d']} docentes\n";
    }
} else {
    echo "\n  Sin conflictos de materia y semestre.\n";
}

if ($estudiantes > 0 && $cursos > 0 && !$errores) {
    echo "\n  MIGRACION CORRECTA.\n";
    echo "  Cada docente entra con la contrasena que ya usaba.\n";
    echo "  El administrador ahora usa la clave de tu servidor local.\n\n";
    exit(0);
}

echo "\n  Revisa los mensajes de arriba.\n\n";
exit(1);
