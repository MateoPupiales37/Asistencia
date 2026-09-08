<?php

require_once __DIR__ . '/BaseController.php';

// Controlador Home: portada del sistema y pagina de error

class HomeController extends BaseController
{
    public function index(): void
    {
        $this->iniciarSesion();

        $this->vista('home.index', [
            'base' => self::obtenerRutaBase(),
            'rol'  => $_SESSION['usuario_rol'] ?? null,
            'nombre' => $_SESSION['usuario_nombre'] ?? null
        ]);
    }

    /**
     * Entrega un token CSRF nuevo.
     *
     * Lo usa la capa AJAX: si el usuario deja una pantalla abierta mucho rato
     * y el token caduca, en vez de mostrarle un error se pide uno nuevo por
     * aqui y se reintenta la accion una sola vez, de forma transparente.
     */
    public function csrf(): void
    {
        $this->iniciarSesion();
        $this->json(['csrf' => self::tokenCsrf()]);
    }

    // Pagina de error usada por el enrutador: 404 (no existe) o 405 (metodo incorrecto)
    public function noEncontrado(int $codigo = 404): void
    {
        $this->iniciarSesion();
        http_response_code($codigo);

        $this->vista('errors.404', [
            'base'   => self::obtenerRutaBase(),
            'codigo' => $codigo
        ]);
    }
}
