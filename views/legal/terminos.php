<?php
$titulo = 'Términos de uso y tratamiento de datos - ISTPET';
$vista  = 'terminos';
$ocultarNavbar = empty($_SESSION['usuario_id']);
require dirname(__DIR__) . '/layouts/header.php';
?>

<div class="legal-pagina">
    <nav class="breadcrumb">
        <a href="<?= $base ?>/">Inicio</a>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current">Términos y tratamiento de datos</span>
    </nav>

    <div class="card legal-card">
        <h1 class="legal-titulo">Términos de uso y tratamiento de datos personales</h1>
        <p class="legal-meta">
            Versión <?= htmlspecialchars($version) ?> &bull;
            Instituto Superior Tecnológico Mayor Pedro Traversari (ISTPET)
        </p>

        <div class="legal-resumen">
            <strong>En resumen:</strong> para confirmar que estás en el aula, el sistema
            necesita tu <strong>ubicación</strong> en el momento de registrarte, y para leer
            el código de la clase necesita tu <strong>cámara</strong>. No se guarda ningún
            recorrido ni se te sigue fuera de ese momento, y puedes retirar tu permiso
            cuando quieras.
        </div>

        <h2>1. Quién trata tus datos</h2>
        <p>
            El responsable es el <strong>Instituto Superior Tecnológico Mayor Pedro
            Traversari</strong>, que usa este sistema únicamente para el control de
            asistencia académica.
        </p>

        <h2>2. Qué datos se tratan y para qué</h2>

        <div class="legal-tabla-envoltorio">
            <table class="table">
                <thead>
                    <tr>
                        <th>Dato</th>
                        <th>Para qué se usa</th>
                        <th>Cuándo se toma</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="font-medium">Ubicación (GPS)</td>
                        <td>
                            Comprobar que estás dentro del área de la clase.
                            Es lo que impide que alguien se registre desde fuera del aula
                            con una foto del código QR.
                        </td>
                        <td>Solo en el instante en que pulsas registrar</td>
                    </tr>
                    <tr>
                        <td class="font-medium">Cámara</td>
                        <td>
                            Leer el código QR de la clase. La imagen se procesa
                            <strong>dentro de tu propio teléfono</strong>: no se envía
                            ni se almacena ninguna fotografía.
                        </td>
                        <td>Solo mientras el escáner está abierto</td>
                    </tr>
                    <tr>
                        <td class="font-medium">Nombre, cédula y código</td>
                        <td>Identificarte en la lista de asistencia de tu docente.</td>
                        <td>Al matricularte y al registrar asistencia</td>
                    </tr>
                    <tr>
                        <td class="font-medium">Fecha y hora</td>
                        <td>Registrar tu entrada y tu salida de la clase.</td>
                        <td>En cada registro</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <h2>3. Qué NO hace el sistema</h2>
        <ul class="legal-lista">
            <li>No te sigue ni guarda tu recorrido: la ubicación se consulta una sola vez, al registrarte.</li>
            <li>No graba, guarda ni envía fotos o vídeo de tu cámara.</li>
            <li>No accede a tus contactos, archivos ni a ninguna otra aplicación.</li>
            <li>No comparte tus datos con empresas ni con terceros ajenos al instituto.</li>
            <li>No usa tus datos con fines comerciales ni publicitarios.</li>
        </ul>

        <h2>4. Quién puede ver tus datos</h2>
        <p>
            Tu docente ve la lista de asistencia de sus propias clases. La administración
            del instituto ve los reportes consolidados para su supervisión. Nadie más
            tiene acceso.
        </p>

        <h2>5. Cuánto tiempo se conservan</h2>
        <p>
            Los registros de asistencia se conservan mientras dure tu vinculación
            académica con el instituto, porque forman parte del expediente del curso.
            La coordenada concreta de un registro solo se usa para calcular la distancia
            en ese momento.
        </p>

        <h2>6. Si no das el permiso</h2>
        <p>
            Puedes negarte. En ese caso el sistema <strong>no te bloquea</strong>: podrás
            registrar tu asistencia igual, pero quedará marcada como
            <em>sin verificar la ubicación</em> y tu docente decidirá si la acepta,
            normalmente confirmando a simple vista que estás en el aula. También puede
            registrarte manualmente.
        </p>

        <h2>7. Tus derechos</h2>
        <p>
            Puedes pedir en cualquier momento que se te muestren, corrijan o eliminen tus
            datos, y puedes retirar este permiso cuando quieras. Para ello, habla con tu
            docente o con la administración del instituto.
        </p>

        <h2>8. Seguridad</h2>
        <p>
            Las contraseñas del personal se guardan cifradas con Bcrypt y nunca en texto
            legible. Las cuentas pueden protegerse además con verificación en dos pasos.
            Todas las consultas a la base de datos usan sentencias preparadas.
        </p>

        <div class="legal-pie">
            <a href="<?= $base ?>/" class="btn btn-outline">&larr; Volver al inicio</a>
            <?php if (!empty($_SESSION['usuario_id'])): ?>
                <a href="<?= $base ?>/docente/perfil" class="btn btn-primary">Ir a mi perfil</a>
            <?php else: ?>
                <a href="<?= $base ?>/asistencia" class="btn btn-primary">Registrar asistencia</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
