# H7 — Guía breve de pruebas de pagos

Sólo demo local, cuentas ficticias y destinatarios controlados. Ninguna transferencia, tarjeta personal, cuenta comercial real ni reembolso monetario. Mantener H2 separado. Ver [arquitectura y resultados](h7-1-payments.md).

## BACS completo

1. Abrir la [tienda](http://localhost:8080/?post_type=product) como invitado en una sesión nueva. Agregar un producto simple o una variación disponible.
2. En [checkout](http://localhost:8080/?page_id=9), usar `example.test` y datos ficticios argentinos. Probar envío estándar $4.500 o retiro gratis. Para gratis, alcanzar $100.000 **después** del descuento; un cupón puede restaurar los $4.500.
3. Seleccionar **Transferencia bancaria**, leer la advertencia demo y realizar el pedido. No hay una cuenta a la que transferir.
4. Verificar “Pedido recibido”, instrucciones demo y pago pendiente de verificación. Recargar no debe crear otro pedido.
5. En [Pedidos](http://localhost:8080/wp-admin/admin.php?page=wc-orders), abrir ese número: `En espera`, método BACS, sin fecha de pago ni transacción; productos, variación, cupón, envío y total coinciden. Revisar notas y stock: BACS puede reducir stock antes de que entre dinero.
6. Sólo en ese pedido de prueba, simular verificación manual cambiando a **Procesando** y guardar. Revisar fecha/estado, nota y que no reduzca otra vez. Repetir guardar el mismo estado no debe repetir efectos.
7. Para liberar stock de una prueba abandonada, cancelar desde WooCommerce; no editar cantidades por un script para fingir el resultado.

## QA reproducible

```powershell
.\scripts\h7-1.ps1 inspect
.\scripts\h7-1.ps1 verify
.\tests\verify-h7-1.ps1
# Preparar stock del browser por cancelación nativa de pruebas anteriores:
.\scripts\h7-1.ps1 cleanup
.\scripts\h7-1.ps1 prepare
$env:NODE_PATH = 'C:\Users\ramiro\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\node_modules'
node tests/verify-h7-1-browser.cjs
```

El helper verifica backup/guards antes de configurar BACS, no pisa configuración comercial y no importa MP. Las suites separan core/CRUD, checkout browser real BACS y mocks de presentación. `cleanup` conserva pedidos y libera stock mediante cancelación nativa; exige todas las guardas de propiedad de las pruebas. Los pedidos de la última corrida se conservan para revisión; ejecutar cleanup antes de repetir el browser. `-IncludeGuard` en la suite PowerShell agrega reejecución de fixtures y pruebas de preservación de ediciones BACS. Evidencia privada bajo `.local/local/h7-1/`. No copiarla completa a Git ni compartir claves de pedidos. El browser utiliza Playwright y Chromium del runtime bundled; adaptar `NODE_PATH` al runtime instalado si cambia de equipo.

## Checkout Pro de prueba

1. Tener identificados los accesos del **vendedor de prueba** de Argentina y de otro comprador de prueba, que usará otra sesión. No pegar secretos en chat, terminal compartida o documentación.

   Si faltan accesos, entrar con la cuenta Developers autorizada a **Tus integraciones → aplicación de pruebas → Cuentas de prueba** y recuperar/verificar el usuario y contraseña del vendedor ficticio. No usar la cuenta personal como vendedora para destrabar la prueba. H2 conserva un archivo privado de comprador, pero éste no sustituye el acceso del vendedor ni prueba vigencia de sus credenciales.
2. Antes de cambiar opciones MP, ejecutar `.\scripts\h7-1.ps1 snapshot-linking`. Vincular al vendedor ficticio desde el [plugin oficial](http://localhost:8080/wp-admin/admin.php?page=mercadopago-settings), conservando Pro deshabilitado hasta verificar identidad. Después de la vinculación y de los ajustes autorizados, ejecutar `.\scripts\h7-1.ps1 record-linking` desde localhost. El registro sólo admite cambios MP y conserva los guards del resto de H6; nunca copia credenciales. Si los últimos ajustes se hicieron bajo HTTPS, detener el túnel y registrar la configuración al restaurar localhost antes de repetir los fixtures.
3. Crear un backup nuevo antes de cada sesión; conservar el anterior. Cerrar QA local antes de ejecutar:

   ```powershell
   .\scripts\h7-1.ps1 backup -BackupId h7-1-antes-de-la-sesion
   .\scripts\h7-https.ps1 start -BackupId h7-1-antes-de-la-sesion
   .\scripts\h7-https.ps1 verify
   ```

4. Usar el origen HTTPS que informa el helper durante toda la prueba. No seguir enlaces de una sesión de túnel anterior. Leer el riesgo de exposición temporal en [H7.1](h7-1-payments.md).
5. Definir privadamente `$sellerNickname` con el nickname del vendedor ficticio autorizado. No contiene un token ni contraseña. Preparar productos ocultos aislados y habilitar Pro sólo después de verificar la cuenta mediante API oficial. El helper no importa credenciales, no cobra y comprueba que BACS permanezca intacto:

   ```powershell
   $h7Compose = @('compose', '--project-name', 'demo-woocommerce-local', '--env-file', '.local/local/.env', '--env-file', '.local/local/h7-1/https/public.env', '-f', 'compose.yaml', '-f', 'compose.h7-https.yaml')
   docker @h7Compose run --rm -T --no-deps wpcli eval-file /opt/local-tools/h7-mp-session.php prepare $sellerNickname --quiet
   docker @h7Compose run --rm -T --no-deps wpcli eval-file /opt/local-tools/h7-mp-session.php enable $sellerNickname --quiet
   docker @h7Compose run --rm -T --no-deps wpcli eval-file /opt/local-tools/h7-mp-seller-check.php verify-linked-test-seller $sellerNickname --quiet
   ```

6. No ejecutar el pago si faltan `test_user`, Argentina, vendedor esperado, credenciales y origen HTTPS. El collector legacy puede estar vacío después del onboarding actual; si está presente, debe coincidir. Verificar también comprador distinto y TLS/URLs/callback. La guía WooCommerce requiere **Modo de ventas (producción)** para este flujo con vendedor ficticio confirmado. El helper utiliza **Checkout Pro**, Redirect, **Mercado Pago**, `binary_mode=no` y retorno manual para aislar las notificaciones. API/Bricks y demás gateways MP quedan apagados. Repetir el preflight después de configurar.
7. Abrir `/?product=h71-mp-simple` o `/?product=h71-mp-variable` en el origen recién creado. Productos ocultos: simple en promoción $50.000; variación S en promoción $52.000. El cupón aislado `h71-mp-10` descuenta 10% sólo sobre esos productos. No reiniciar stock consumido: usar cantidades disponibles y conservar pedidos anteriores. Hacer aprobado, pendiente y rechazado con los medios/tarjetas ficticios de la [guía oficial vigente](https://www.mercadopago.com.ar/developers/es/docs/woocommerce/integration-test) y [tarjetas oficiales](https://www.mercadopago.com.ar/developers/es/docs/woocommerce/additional-content/your-integrations/test/cards). APRO, OTHE y CONT corresponden a los escenarios usados en esta sesión. Elegir una cuota para mantener el total. Abandonar otra operación; no marcar pedidos pagados manualmente para presentar éxito MP.

   Completar cada flujo antes de abrir otro checkout en esa sesión. Para pruebas simultáneas, usar sesiones compradoras aisladas: se observó reutilización del pedido pendiente al abrir un segundo checkout y cambio de método. El informe externo documenta la reparación del dato de QA y el límite pendiente antes de producción.
8. Contrastar servidor, recurso consultado mediante plugin, identidad receptora/pagadora, referencia, importe ARS, estado, transacción, fecha, notas y stock. Verificar total con simple/variable, promoción, cupón, estándar, gratis después de descuentos y retiro. El retorno sólo acredita navegación, nunca pago.
9. Leer los resultados sin modificar estados. `payment` admite el número WooCommerce y el identificador real de operación visto en Mercado Pago; valida propietario y referencia, y contrasta recursos mediante el SDK oficial. No usar IDs ajenos:

   ```powershell
   docker @h7Compose run --rm -T --no-deps wpcli eval-file /opt/local-tools/h7-mp-session.php inspect --quiet
   docker @h7Compose run --rm -T --no-deps wpcli eval-file /opt/local-tools/h7-mp-session.php payment NUMERO_PEDIDO ID_OPERACION --quiet
   docker exec demo-woocommerce-local-wordpress-1 cat /var/log/apache2/h7-callbacks.log
   ```

10. Completar callbacks antes del cierre y apagar:

   ```powershell
   .\scripts\h7-https.ps1 stop
   .\scripts\h7-1.ps1 record-linking
   .\scripts\h7-1.ps1 verify
   ```

El helper bloquea administrador/login/XML-RPC durante HTTPS, agrega noindex y limita la sesión a 90 minutos. Su watchdog deshabilita Pro y restaura localhost; `stop` permite hacerlo antes. No es un servicio permanente. No usar `dev.ps1 start` durante una sesión pública. No dejar URLs temporales en contenido, no publicar base/Mailpit y no activar cobros reales.

## Notificaciones y duplicados

Usar la URL que genera el plugin; no construir un receptor adicional ni cambiar `source_news` para simular otro mecanismo. En 8.9.6 Pro aún utiliza la ruta documentada IPN/Core; ver [auditoría y límite HMAC](h7-mp-audit.md). No asumir firma validada sólo por recibir HTTP 200.

En una prueba externa, comprobar llegada con checkout cerrado, estado obtenido por consulta autenticada, asociación al mismo pedido y actualización sin retorno del comprador. Para repetir exactamente una notificación, usar únicamente la herramienta oficial soportada y el evento original accesible en la cuenta correcta; si no está disponible, registrar **no verificado**. No fabricar un ID/cuerpo y llamarlo replay real. Comparar número de pedidos, fecha/transacción/notas, banderas y stock antes/después. Una notificación atrasada no debe degradar un final más reciente; ese escenario tampoco queda probado por un mock.

El registro Apache guarda sólo timestamp, método, HTTP, marcador QA y clasificación equivalente a `NotificationFactory`; no captura cuerpo, claves, consultas, cookies ni referrer. Se usa `GlobalLog` para cubrir el virtual host de WordPress. Un HTTP 400 marcado `probe` es una prueba negativa interna, no un pago externo.

Para ensayar indisponibilidad del callback, el overlay reconoce exclusivamente el archivo local `/tmp/h7-mp-callback-unavailable` y devuelve 503 mientras exista. **Programar su eliminación automática antes de crearlo**, limitar la falla a una operación ficticia y retirarlo al registrar el primer rechazo. No se habilita al iniciar el túnel y desaparece al recrear el contenedor. Conservar los intentos externos separados de sondas/reconstrucciones. El [informe externo](h7-external-qa.md) describe la prueba efectivamente realizada; no garantiza una política universal de backoff del proveedor.

Clasificar cada resultado: **pago externo con cuentas ficticias**, **simulación oficial de notificación**, **test interno/mock**. Un test PHP de estados, un callback malformado rechazado o una recarga de Order Received no son confirmación externa de pago.

## Errores frecuentes y estados

| Observación | Comprobación/acción |
| --- | --- |
| MP no aparece | Plugin activo, vendedor vinculado, Pro habilitado, modo correcto, HTTPS y disponibilidad nativa; no añadir radio ficticio |
| Error al crear preferencia/retorno | Hostname vigente y HTTPS en todos los enlaces; no usar rama sandbox del proveedor ni credenciales reales como sustituto |
| Pedido sigue pendiente | Consultar estado por plugin; revisar callback y cuenta propietaria. No aprobarlo por URL ni por captura del comprador |
| `in_process` / `on-hold` | Revisión pendiente, puede reducir stock; no afirmar acreditación |
| BACS `on-hold` | Resultado esperado, espera verificación manual; la reducción de stock no prueba dinero |
| Fallido/cancelado | No pagado; verificar reposición nativa si había reducción |
| Callback sin acceso al historial | Verificar cuenta propietaria de la aplicación; documentar el límite, no cambiar a receptor propio |
| Guard H5/H6 falla tras vincular | Usar snapshot/registro protegido H7; no reescribir huellas a ciegas ni repetir seeds |
| Quick Tunnel cambia/se corta | Invalidar enlaces anteriores, restaurar y repetir con sesión estable; si persiste, usar hostname estable de desarrollo |

## Antes de adaptar a producción

Retirar la cuenta de prueba por el plugin y revocar sus accesos según corresponda. No transportar BD/volúmenes demo, pedidos QA, backups ni `.local/` a una instalación de cliente. Vincular la cuenta del comerciante sólo cuando se autorice producción, con HTTPS estable y garantías de autenticidad/replay resueltas. Reemplazar advertencias demo y cuentas BACS desde WooCommerce. Revalidar Blocks, HPOS, stock, totales, notificaciones y seguridad con el entorno final. H7.2 tratará comunicaciones; esta guía no lo implementa.
