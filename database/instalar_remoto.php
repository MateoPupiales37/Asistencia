<?php

/**
 * Instala la base de datos en un servidor remoto (Railway, Render, etc.).
 *
 * Se ejecuta desde la consola pasandole la URL de conexion publica, la misma
 * que Railway muestra en MySQL -> Variables -> MYSQL_PUBLIC_URL:
 *
 *     php database/instalar_remoto.php "mysql://root:CLAVE@host.proxy.rlwy.net:12345/railway"
 *
 * Se hace asi, con la URL completa de una sola pieza, porque copiar a mano el
 * host, el puerto y la contrasena por separado es donde todo el mundo se
 * equivoca. El script la parte solo.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo se ejecuta desde la consola.\n");
}

/*
 * ¿Se esta ejecutando DENTRO del servidor de Railway?
 *
 * Importa mucho: la direccion mysql.railway.internal solo existe dentro de su
 * red privada. Desde una computadora de casa no resuelve, pero desde la
 * consola del propio servicio funciona perfectamente, y ademas es el camino
 * mas comodo porque las credenciales ya estan en el entorno.
 */
$dentroDeRailway = getenv('RAILWAY_ENVIRONMENT') !== false
                || getenv('RAILWAY_PROJECT_ID') !== false
                || getenv('RAILWAY_SERVICE_ID') !== false;

$url = $argv[1] ?? '';

// Sin argumentos: se intenta con las variables de entorno del propio servidor
if ($url === '' && getenv('DB_HOST') !== false) {
    $url = sprintf(
        'mysql://%s:%s@%s:%s/%s',
        rawurlencode((string)getenv('DB_USER')),
        rawurlencode((string)getenv('DB_PASS')),
        getenv('DB_HOST'),
        getenv('DB_PORT') ?: '3306',
        getenv('DB_NAME') ?: 'railway'
    );
    echo "\n  Usando las variables de entorno del servidor (DB_HOST, DB_USER...)\n";
}

if ($url === '') {
    echo <<<TXT

  FALTA LA URL DE CONEXION

  Tienes dos caminos:

  A) DESDE RAILWAY (mas facil)
     Servicio "web" -> pestana "Console" -> escribe:

       php database/instalar_remoto.php

     Alli las credenciales ya estan en el entorno y no hay que copiar nada.

  B) DESDE TU COMPUTADORA
     Servicio MySQL -> pestana "Variables" -> copia MYSQL_PUBLIC_URL
     (la que apunta a .proxy.rlwy.net, NO la que dice .internal)

       php database/instalar_remoto.php "mysql://root:CLAVE@host.proxy.rlwy.net:12345/railway"


TXT;
    exit(1);
}

// ---------------------------------------------------------------------
// 1. Partir la URL
// ---------------------------------------------------------------------
$partes = parse_url($url);

if ($partes === false || empty($partes['host'])) {
    exit("  La URL no tiene un formato valido. Debe empezar por mysql://\n");
}

$host   = $partes['host'];
$puerto = $partes['port'] ?? 3306;
$user   = urldecode($partes['user'] ?? 'root');
$clave  = urldecode($partes['pass'] ?? '');
$base   = ltrim($partes['path'] ?? '/railway', '/');

// La direccion interna solo se alcanza desde dentro de la red de Railway.
// Si el script corre alli, es la correcta; si corre en una computadora de
// casa, no resuelve y hay que usar la publica.
if (str_contains($host, '.internal') && !$dentroDeRailway) {
    echo "\n  ESA ES LA DIRECCION INTERNA DE RAILWAY\n\n";
    echo "  Solo funciona entre servicios de Railway, no desde tu computadora.\n";
    echo "  Tienes dos salidas:\n\n";
    echo "  A) Ejecutalo DENTRO de Railway (mas facil):\n";
    echo "     Servicio \"web\" -> pestana \"Console\" -> escribe:\n\n";
    echo "       php database/instalar_remoto.php\n\n";
    echo "  B) O usa la direccion publica desde aqui:\n";
    echo "     MySQL -> Variables -> MYSQL_PUBLIC_URL (la de .proxy.rlwy.net)\n\n";
    exit(1);
}

echo "\n  Servidor : {$host}:{$puerto}\n";
echo "  Base     : {$base}\n";
echo "  Usuario  : {$user}\n\n";

// ---------------------------------------------------------------------
// 2. Conectar
// ---------------------------------------------------------------------
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
    echo "  Revisa que copiaste MYSQL_PUBLIC_URL completa y entre comillas.\n\n";
    exit(1);
}

// ---------------------------------------------------------------------
// 3. Comprobar si ya habia datos
// ---------------------------------------------------------------------
$tablas = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

if ($tablas) {
    echo "\n  La base ya tiene " . count($tablas) . " tabla(s): " . implode(', ', $tablas) . "\n";
    echo "  Volver a instalar BORRARIA todo lo que haya dentro.\n";
    echo "  Escribe BORRAR para continuar, o Enter para cancelar: ";

    $respuesta = trim((string)fgets(STDIN));

    if ($respuesta !== 'BORRAR') {
        echo "\n  Cancelado. No se toco nada.\n\n";
        exit(0);
    }

    echo "\n  Eliminando las tablas anteriores... ";
    $db->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($tablas as $tabla) {
        $db->exec("DROP TABLE IF EXISTS `{$tabla}`");
    }
    $db->exec('SET FOREIGN_KEY_CHECKS = 1');
    echo "listo\n";
}

// ---------------------------------------------------------------------
// 4. Ejecutar el instalador
// ---------------------------------------------------------------------
$archivo = __DIR__ . '/instalacion_completa.sql';

if (!is_file($archivo)) {
    exit("\n  No se encontro {$archivo}\n\n");
}

$sql = file_get_contents($archivo);

// La linea "USE asistencia_qr" no sirve en Railway: alli la base se llama
// "railway" y ya viene seleccionada en la conexion.
$sql = preg_replace('/^\s*USE\s+[^;]+;/mi', '', $sql);

echo "\n  Creando las tablas...\n";

$sentencias = 0;
$errores = [];

// Se ejecuta sentencia por sentencia para poder decir cual fallo
foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $sentencia) {
    // Se saltan los comentarios sueltos
    $limpia = trim(preg_replace('/^--.*$/m', '', $sentencia));
    if ($limpia === '') {
        continue;
    }

    try {
        $db->exec($limpia);
        $sentencias++;
    } catch (PDOException $e) {
        $errores[] = substr($limpia, 0, 70) . '...  -> ' . $e->getMessage();
    }
}

echo "  {$sentencias} sentencia(s) ejecutada(s)\n";

if ($errores) {
    echo "\n  Hubo " . count($errores) . " problema(s):\n";
    foreach ($errores as $e) {
        echo "   - {$e}\n";
    }
}

// ---------------------------------------------------------------------
// 5. Comprobar el resultado
// ---------------------------------------------------------------------
$tablas = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$admin  = $db->query("SELECT correo FROM usuarios WHERE rol = 'admin' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

echo "\n  ---------------------------------------------\n";
echo "  Tablas creadas : " . count($tablas) . "\n";
echo "  Administrador  : " . ($admin['correo'] ?? 'NO SE CREO') . "\n";
echo "  Contrasena     : Istpet2026\n";
echo "  ---------------------------------------------\n";

if (count($tablas) >= 11 && $admin) {
    echo "\n  INSTALACION CORRECTA.\n";
    echo "  Entra a tu dominio de Railway y cambia esa contrasena.\n\n";
    exit(0);
}

echo "\n  La instalacion quedo incompleta. Revisa los mensajes de arriba.\n\n";
exit(1);
