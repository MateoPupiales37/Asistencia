<?php

/**
 * Punto de entrada raíz para entornos sin mod_rewrite o servidores locales.
 * Delega la ejecución directamente al Front Controller en public/index.php.
 */

require_once __DIR__ . '/public/index.php';
