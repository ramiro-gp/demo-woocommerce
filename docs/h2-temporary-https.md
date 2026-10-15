# H2: entorno HTTPS temporal

**Tres sesiones funcionales completadas, túnel apagado al cierre del 14/10/2026 (Argentina).** WordPress volvió a `http://localhost:18081`; Mailpit permanece privado en `http://localhost:18026`. Vendedor de prueba vinculado, pagos de prueba concluidos y saldo ficticio aprobado devuelto. Ver [matriz y límites de H2](h2-validation.md).

## Elección e aislamiento

| Alternativa | Decisión |
| --- | --- |
| Subdominio real con WordPress aislado | El usuario no disponía de uno |
| Cloudflare Quick Tunnel | Elegido: sin cuenta ni dominio, binario oficial ya instalado, sesión supervisada |
| ngrok gratuito | Evaluado; requiere cuenta y authtoken, innecesarios para estas sesiones |

[Quick Tunnels oficial](https://developers.cloudflare.com/tunnel/get-started/quick-tunnels/), [límites ngrok](https://ngrok.com/docs/pricing-limits/free-plan-limits).

Toda implementación permanece en `C:\Codigo\demo-woocommerce`. La instancia `h2-public` es runtime separado del mismo código, con proyecto Compose `demo-woocommerce-h2-public`, volúmenes propios de WordPress/MariaDB/Mailpit, base y accesos nuevos. No se copiaron secretos ni pedidos de `local`.

WordPress 7.1.3 / PHP 8.4.26 / MariaDB 10.11.19 / WooCommerce 11.2.1 / MP oficial 8.9.6. HPOS, Blocks, invitado, Argentina/es_AR/ARS y fixtures técnicos. No se instaló infraestructura permanente ni se modificó hosting o tienda de producción.

El binario existente `C:\Program Files (x86)\cloudflared\cloudflared.exe` (2026.9.1) se ejecutó oculto y sin autoupdate; no se creó un servicio. El túnel solo apunta a `127.0.0.1:18081`. Publica este WordPress, incluido su login, con autorización expresa del usuario. MariaDB y SMTP no tienen puertos públicos, Mailpit queda fuera del túnel y métricas se ligan a `127.0.0.1:18181`.

El overlay cambia `LOCAL_SITE_URL` de WordPress y WP-CLI, utilizado por WP_HOME/WP_SITEURL, sin reemplazos SQL. La imagen oficial reconoce `X-Forwarded-Proto: https`. Se conserva entorno `local` y correo a Mailpit. [WordPress detrás de proxy](https://developer.wordpress.org/advanced-administration/security/https/), [encabezado Cloudflare](https://developers.cloudflare.com/fundamentals/reference/http-headers/#x-forwarded-proto).

## Operación

Solo después de preparar la instancia y contar con autorización de publicación temporal:

```powershell
.\scripts\h2-https.ps1 start
.\scripts\h2-https.ps1 status
.\scripts\h2-https.ps1 verify
# Concluir pagos, callbacks y devoluciones antes de apagar:
.\scripts\h2-https.ps1 stop
```

`start` comprueba la instancia fija, detiene primero su WordPress, inicia cloudflared, obtiene la URL, aplica el overlay y valida TLS, portada/login, redirecciones y Store API. Estado, hostname, fechas y logs quedan únicamente en `.local/h2-public/https/`. Administrador local en `.local/h2-public/.env`, contraseña en su archivo privado `secrets/wp-admin-password.txt`.

`stop` verifica PID, ejecutable y fecha exacta antes de terminar el proceso propio, restaura localhost y conserva volúmenes/logs. Ante fallo de cierre intenta dejar WordPress detenido hasta confirmar que no existe túnel hacia el origen. El helper y el verificador de proxy comparten mutex para impedir cambios de origen concurrentes.

No ejecutar `dev.ps1 start`, `h2.ps1 install` ni resets sobre `h2-public` mientras HTTPS está activo. Mantener equipo/Docker encendidos durante pruebas. Quick Tunnel cambia hostname y no garantiza disponibilidad: no continuar checkouts antiguos después de apagar/reiniciar. Verificar origen, URLs generadas por el plugin y cuentas vinculadas antes de pagar de nuevo. No se garantiza recuperar callbacks dirigidos a una URL anterior. [Limitaciones oficiales](https://developers.cloudflare.com/tunnel/get-started/quick-tunnels/#limitations).

No se aplicó autenticación interactiva global al túnel, porque impediría los callbacks del proveedor. El aislamiento depende de la instancia de prueba, acceso administrativo, sesión limitada y apagado verificado; no es configuración de producción.

## Verificación y correcciones del helper

Antes de publicar, `verify-h2-https-origin.ps1` simuló el proxy solo por loopback con Host reservado `h2-origin-check.invalid`. Pasaron enlaces HTTPS canónicos, tienda/login HTTP 200, cookie Secure y Store API/ARS. Su `finally` restauró localhost. Esa simulación no se confundió con certificado público ni callback externo.

Las sesiones públicas pasaron validación TLS normal, portada/login HTTP 200, origen canónico, 22 enlaces HTTPS de Store API y acceso real a wp-admin. Los callbacks HTTP 200 se acreditaron posteriormente con los pagos, no con estas comprobaciones de conectividad.

Se corrigieron tres problemas del helper de Windows:

- Logs de `Start-Process` abiertos: lectura con `FileShare.ReadWrite`.
- Colisión con variable reservada `HOME`: nombre específico para la respuesta de portada.
- Timestamps JSON diferentes en PowerShell 5.1/7: conservar `DateTime` UTC o parsear string ISO con Roundtrip, sin perder fracciones ni zona. La guarda mantuvo igualdad exacta de PID/ruta/inicio; no se relajó.

Las recuperaciones detuvieron el proceso propio y restauraron origen local. Inicio/apagado conservaron datos. Parsers 5.1/7 verificados.

## Sesiones y cuentas

El complemento de Codex Mercado Pago 1.0.1 usa el MCP oficial para asistencia y cuentas. Es independiente del plugin WooCommerce 8.9.6 que procesa pagos. Se creó DemoWooH2 en Developers y se verificaron exactamente un vendedor y un comprador de prueba argentinos distintos. La creación automática inicial informó fallo parcial de aplicación; el vendedor ya existía y no se duplicó.

El usuario inició sesión como vendedor de prueba, aceptó términos/reCAPTCHA y autorizó vincularlo desde WooCommerce. El onboarding generó su aplicación nativa. El lector oficial confirmó identidad, `test_user` y MLA antes de habilitar Pro. Se cerró la sesión del vendedor antes de ingresar como comprador y se cerró wp-admin antes de comprar como invitado.

La guía específica indica Modo Ventas con la cuenta de prueba vinculada. Se usaron solo saldo/datos ficticios. Credenciales y códigos permanecen privados. [Guía de pruebas WooCommerce](https://www.mercadopago.com.ar/developers/es/docs/woocommerce/integration-test).

| Sesión | Resultado | Cierre |
| --- | --- | --- |
| Primera, 13/10 | #16 aprobado y devuelto; #17 inicialmente detenido por verificación adicional del comprador | Túnel terminado, localhost y datos conservados |
| Segunda, 14/10 | Recuperación nativa del mismo #17; OTHE rechazado, cancelado al retornar, stock intacto | Túnel terminado mientras se esperaba autorización de datos para compras restantes |
| Tercera, 14/10 | Autorización recibida; #19 pendiente Rapipago con checkout cerrado, retorno pendiente, cambio nativo a saldo ficticio, aprobación y devolución total | Apagado a las 21:12:35 Argentina; HTTP local 200, ningún cloudflared hacia 18081 |

En la tercera sesión CONT volvió a pedir validación. El código de prueba fue rechazado; la alternativa autorizada coincidía con él y no se duplicó el intento. Rapipago permitió comprobar pendiente sin eludir autenticación ni pagar dinero real. Desde actividad del comprador se cambió legítimamente el medio del mismo pedido a saldo ficticio; el cupón anterior quedó cancelado. Se creó uno de los tres pedidos adicionales permitidos.

La confirmación UI del refund #16 dejó de responder; la prevalidación de la alternativa API detectó que ya se había completado y evitó duplicarlo. El refund #19 se ejecutó por API nativa de WooCommerce con el administrador existente y el plugin oficial. El primer intento sin usuario fue rechazado por permisos antes de contactar la devolución externa; se preservó esa protección.

## Estado final y límites

- Tres pedidos de checkout: #16 devuelto, #17 cancelado, #19 devuelto; dos refunds únicos y saldo ficticio restaurado.
- Stock simple 5, S 3, M 0; reservas 0. HPOS, Blocks y vinculación persistentes.
- Callback pendiente recibido con checkout cerrado; aprobación #16 antes del retorno; confirmación #19 posterior a su primer retorno pendiente. Horas y límites de precisión en la matriz.
- Repetición exacta de notificación sin reproducir: faltaba el payload original y el historial de la aplicación del vendedor rechazó acceso por distinto propietario OAuth. No se cambió de receptor ni se inventaron notificaciones.
- Estado privado final en `.local/h2-public/https/state.json`; inspector 14/14 y Store API 10/10 después de restaurar localhost. Captura final de administración en `.local/h2-public/h2-final-orders-hpos.jpg`.

H2 queda cerrado funcionalmente con ese límite condicional explícito. Antes de producción se necesita HTTPS estable, validación equivalente de hosting y configuración comercial. No hay despliegue ni H3 en esta entrega.
