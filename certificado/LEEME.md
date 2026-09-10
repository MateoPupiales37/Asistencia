# Certificado HTTPS para el aula

## Por qué hace falta

El navegador solo entrega la **cámara** y la **ubicación** en lo que llama
*contextos seguros*: HTTPS, o `localhost`. Entrando por `http://192.168.1.38`
las niega sin siquiera preguntar. Eso es lo que hacía aparecer el aviso
*"Esta página no se abrió por HTTPS, así que el navegador bloquea la cámara"*.

No es un fallo del sistema y no se puede desactivar desde el código: es una
regla del navegador.

## Qué hay aquí

| Archivo | Qué es |
|---|---|
| `istpet.crt` | El certificado, con las IP del equipo dentro. Válido 3 años. |
| `istpet.key` | La clave privada. **No se comparte con nadie.** |
| `openssl.cnf` | La receta con que se generó, por si hay que rehacerlo. |

XAMPP trae uno de fábrica que **caducó en 2019** y era para `localhost`, así
que no servía para entrar por la IP.

## Cómo instalarlo

Con Apache **detenido** desde el panel de XAMPP, en una consola:

```
copy C:\xampp\apache\conf\ssl.crt\server.crt C:\xampp\apache\conf\ssl.crt\server.crt.viejo
copy C:\xampp\apache\conf\ssl.key\server.key C:\xampp\apache\conf\ssl.key\server.key.viejo
copy C:\xampp\htdocs\asistencia\certificado\istpet.crt C:\xampp\apache\conf\ssl.crt\server.crt
copy C:\xampp\htdocs\asistencia\certificado\istpet.key C:\xampp\apache\conf\ssl.key\server.key
```

Vuelve a arrancar Apache y entra por **`https://localhost/asistencia/public/`**.
Desde ahí, el código QR que genere el docente llevará `https://` dentro y el
teléfono sí abrirá la cámara.

Para deshacerlo, copia de vuelta los dos `.viejo`.

## Lo que este certificado NO evita

Sigue siendo **autofirmado**: nadie externo responde por él. La primera vez,
cada teléfono mostrará *"La conexión no es privada"* y habrá que tocar
**Avanzado → Continuar**. Es un aviso por dispositivo, no en cada clase.

Quitar ese aviso del todo exige un dominio propio y un certificado de una
autoridad pública — que es justamente lo que ya tienes en el despliegue de
Railway, donde la cámara funciona sin advertencias.

## Si cambia la IP del equipo

El certificado lleva `192.168.1.38` dentro. Si el router te asigna otra, hay
que rehacerlo: edita las líneas `CN` e `IP.1` de `openssl.cnf` y ejecuta

```
openssl req -x509 -newkey rsa:2048 -nodes -keyout istpet.key -out istpet.crt -days 1095 -config openssl.cnf
```

Lo más cómodo es pedirle al router una **IP fija** para el equipo del aula.
