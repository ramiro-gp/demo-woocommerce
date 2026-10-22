# H8.2 — Auditoría final autónoma

Fecha: **21/10/2026, America/Buenos_Aires**. Base `6bcf8cb9b986248eb290fec515b25b7b266fd951`, rama `codex/h8-2-legal-consumer-rights`. H8.1 permanece consolidado en main `18da5be`. Sin H9, merge H8.2, despliegue o pagos reales.

## Veredicto y bloques

**H8.2 está técnicamente listo para consolidarse en main como base DEMO reutilizable**, después de las correcciones aquí verificadas. La consolidación no se ejecutó. No está autorizado ni preparado para producción de un comercio real sin sus datos, procedimientos y revisión jurídica, y Mercado Pago conserva bloqueos explícitos.

| Bloque del pedido | Resultado | Evidencia y alcance |
| --- | --- | --- |
| 1 Implementación/documentación | **PASS con correcciones** | Código y páginas contrastados. Se corrigieron fallo previo al transporte y visibilidad de constancia; se completó inventario de cookies |
| 2 Botón | **PASS técnico** | Home 390 oscuro/1366 claro, primer viewport, enlace directo, sin login, teclado y sin JS. Normativa contrastada, sin certificación jurídica |
| 3 Formulario completo | **PASS** | Invitado/autenticado, sin referencia/motivo, referencia ajena/inexistente; pedido propio en instalación independiente; campos inválidos, token ausente/inválido/expirado, nonce, reenvío, recarga y concurrencia |
| 4 Correos/fallos | **PASS** | SMTP rechazado, excepción de plantilla y fallos parciales consumidor/admin; constancia y registro preservados; reintento autorizado sólo de correos faltantes |
| 5 Administración | **PASS** | Administrador y shop manager, denegación a cliente/anónimo, nonce ligado al caso, ID manipulado, estado/nota/eventos y vínculo manual HPOS tras confirmación de verificación |
| 6 Persistencia/reproducción | **PASS** | WordPress/Woo desde cero en otro proyecto, tres tablas InnoDB, índices únicos, migración dbDelta con datos, desactivación/reactivación y restauración exacta de tablas legales |
| 7 Abuso | **PASS técnico** | Cookie de sesión, HttpOnly/Lax; sin JS/CAPTCHA; claves temporales HMAC sin IP en claro; séptimo envío/email obtiene 429 con orientación de recuperación |
| 8 Legales/checkout | **PASS técnico** | Cinco páginas DEMO, privacidad ID3 reutilizada, links y checkbox nativos; sin localhost en Core/tema distribuibles. Identidad y fiscalidad pendientes explícitas |
| 9 Externos/privacidad | **PASS investigación; producción bloqueada** | Investigación específica MP, causa de carga, datos potenciales y ausencia de opción granular identificada en 8.9.6. Atribución Woo desactivada con ajuste oficial |
| 10 Visual | **PASS focalizado** | Ocho casos nuevos de Home/formulario/privacidad/login, dos anchos/temas y movimiento reducido; teclado/foco, administración y checkout revisados. No se repitió la matriz histórica de 50 páginas |
| 11 Regresión | **PASS proporcional** | 13 huellas de comercio y 2 de solicitudes/eventos históricos conservadas. H8.1, Communications, Integrity, HPOS, BACS, carrito/checkout/envíos y catálogo preservados |
| 12 Correcciones/Git | **PASS** | Cambios acotados, QA y documentos versionados; secretos/runtime privados excluidos; rama H8.2 sin merge |

## Defectos comprobados y correcciones

1. **Excepción al renderizar correo impedía devolver constancia.** El `try/catch` sólo rodeaba `wp_mail`; `WC()->mailer()->wrap_message` quedaba fuera. Probe real: registro guardado, excepción escapada, consumidor en `sending`, administrador en `pending`, sin objeto de recibo. Se incluyeron preparación, plantilla y transporte en la protección por destinatario, limpiando únicamente los buffers abiertos durante el intento y retirando el callback temporal. Tras corregir: recibo devuelto, ambos estados `failed`, posteriores reintentos operativos. No se registra el contenido de excepciones ni datos personales en eventos.
2. **Constancia fuera del primer viewport móvil después de enviar.** La página volvía al título/índice y el código quedaba debajo del pliegue. Se utiliza el fragmento HTML `#cc-form-result`, destino con `tabindex=-1` para constancia/errores. Funciona sin JS y no lleva códigos ni datos personales en la URL. Se comprobó código visible a 390/1366, recarga con el mismo código y foco del destino. [Captura móvil](evidence/h8-2-final/receipt-visible-390-dark.png).
3. **Inventario de privacidad incompleto.** Se detectaron `sbjs_*`, cookie A/B de MP y almacenamiento de sesión de ese plugin. Se desactivó atribución de campañas mediante la opción oficial WooCommerce; no cambió metadata histórica. Se actualizó privacidad editable y seed DEMO, manteniendo el bloqueo productivo de recursos MP. [Investigación específica](h8-2-external-resources-audit.md).

El runner también necesitó dos ajustes propios: no tocar localStorage en documentos de origen opaco, y esperar el POST/enviar por teclado en el escenario sin JS con movimiento reducido. El primer fallo no era un error de aplicación; el segundo fue una espera de estabilidad que no llegó a emitir ningún POST. Las comprobaciones afectadas se ejecutaron nuevamente; no se ocultó una falla funcional ni se introdujo JavaScript en la prueba sin JS.

## Recepción, correos y seguridad

[Auditoría browser](evidence/h8-2-final/browser-audit.json): **122 comprobaciones**,18 solicitudes únicas y8 casos visuales. [Gestión/constancia final](evidence/h8-2-final/management-audit.json): **11 comprobaciones adicionales**. Las18 solicitudes del circuito principal tuvieron exactamente dos correos finales cada una, HTML+texto plano en español, capturados sólo en Mailpit. Las pruebas de SMTP/plantilla comenzaron con cero correos y las parciales con uno; reintentar dejó exactamente dos, sin repetir el ya enviado.

El [reporte de persistencia](evidence/h8-2-final/persistence-audit.json) consulta la base y verifica una sola fila por código, ambos estados `sent` y ausencia de vinculación automática en esas 18 recepciones. Las dos constancias añadidas después para comprobar navegación están en el reporte de gestión; una se vinculó manualmente al pedido histórico QA#454 después de rechazar la operación sin confirmación. Se agregó una nota explícita de verificación simulada para QA; el pedido se leyó, no se modificó. La referencia propia real se probó con usuario y pedido nuevos, sin ítems/stock, dentro de la instalación desechable.

No existe endpoint de lectura por código. Agregar código/email/ID a la URL no otorga datos; el formulario no confirma si existe una compra. Cliente y anónimo no pueden gestionar. La manipulación de ID con nonce de otro caso devuelve 403. La UI escapa contenido; las consultas usan parámetros. El código no almacena DNI, banco o IP en claro en solicitudes.

En abuso se utilizó un namespace de contador exclusivo del run, mediante un MU-plugin temporal restringido a `local` y URL exacta. Así no se borraron contadores existentes ni se debilitaron límites reales. Se mantuvieron 30/IP/hora y 6/email/hora; el séptimo envío válido devolvió 429. El token firmado expirado se generó con firma válida y tiempo anterior a 24 h para comprobar vencimiento real, no solamente una cadena inválida. La cookie de protección no tiene vencimiento persistente y no hace indispensable JavaScript.

El helper simula además conexión SMTP rechazada a un puerto local, plantilla que lanza excepción y fallos parciales. Se retiró en `finally`. [Log web verificado](evidence/h8-2-final/log-audit.json): sin emails QA ni cuerpos de formulario; 2075 bytes antes/después, sin nuevas excepciones del producto. No se copian logs con datos privados a Git. Se observó un aviso preexistente de Action Scheduler atrasado en administración; H8.2 no crea tareas programadas y no se ejecutó la cola histórica para hacer pasar pruebas.

## Instalación, actualización y backup

Proyecto independiente **`demo-woocommerce-h82-audit`**, puertos 8182/8127, red y volúmenes `db_data`, `wordpress_data`, `mailpit_data` exclusivos. Se instaló WordPress 7.1.3 desde la imagen vacía y WooCommerce 11.2.1 desde los binarios ya disponibles, sin copiar la base principal. Activación de Core creó tablas vacías y esquema 1. No se sembró identidad ni páginas DEMO automáticamente.

Pruebas: motores InnoDB, claves primarias, índice por email, índices únicos de código/token y por solicitud en eventos; esquema 0→1 mediante el camino dbDelta con un registro existente; desactivación/reactivación nativas y hash del registro idéntico: **10/10 checks** del verificador aislado. No hay hook de uninstall que borre solicitudes. No se forzó una eliminación física del plugin bind-mounted en modo lectura; la conservación al reactivar y el camino de actualización sí se ejecutaron.

Se exportaron las tres tablas legales con WP-CLI, `--single-transaction` y sin locks de tablas, y se importaron **sólo en el entorno desechable**. [Restauración](evidence/h8-2-final/restore-audit.json): requests/events/limits recuperados con el mismo conteo y SHA256 de todas las filas. El backup general existente exporta la base completa, por lo que incluye estas tablas. Dumps, claves y datos brutos se conservan privados en `.local`; no se restauró ni reseteó la tienda principal. Los plazos de retención, disociación y copias del cliente siguen requiriendo decisión jurídica/operativa.

Al cerrar se retiraron únicamente los contenedores, red y tres volúmenes identificados del proyecto desechable. Se conservaron sus reportes y archivos privados de reproducción; los servicios de la tienda principal continúan disponibles.

Backup completo final de la instalación principal: `.local/local/backups/h8-2-after-final-audit-20261021/`, fuera de Git. Incluye base y archivos WordPress con las correcciones; no se utilizó para sobrescribir la instalación principal.

## Normativa y publicación

Se releyeron fuentes oficiales de [D954/2025](https://www.argentina.gob.ar/normativa/nacional/disposici%C3%B3n-954-2025-417152/texto), [D3/2026](https://www.argentina.gob.ar/normativa/nacional/disposici%C3%B3n-3-2026-423007/texto) y [relaciones de D954](https://www.argentina.gob.ar/normativa/nacional/disposici%C3%B3n-954-2025-417152/normas-modifican). El acceso visible/directo, ausencia de registro obligatorio y código inmediato corresponden a los mecanismos técnicos examinados. D3 permite verificaciones razonables por seguridad, sin convertir el ingreso en un trámite previo burocrático. R424/2020 no se usa como fundamento vigente.

Se contrastaron nuevamente Ley 24.240, Ley 25.326, orientación AAIP, relaciones oficiales de R270 y micrositio ARCA. No se identificó un cambio que invalide la matriz al 21/10/2026. La matriz conserva D890/2025 y derogación de R274/2021. Ninguna conclusión equivale a cumplimiento jurídico integral.

Faltan por comercio: titular/CUIT/domicilio, contactos/horarios/área, logística real, política comercial, Data Fiscal auténtico, proveedores/transferencias, retención y operación de derechos, soporte conservable del contrato, revisión de seguridad y aprobación jurídica. Mercado Pago mantiene sus bloqueos H7.1 y ahora la evaluación específica de recursos/A-B/telemetría aun deshabilitado. No se autorizan producción ni cobros.

## Reproducción y revisión

Herramientas: `h8-2-final-audit.php` (snapshot de históricos, probe, evidencia y minimización local); `h8-2-isolated-audit.php` (sólo localhost 8182); `h8-2-final-http.php` (MU-plugin temporal); runners `verify-h8-2-final-audit.cjs` y `verify-h8-2-final-management.cjs`. Requieren fixtures privados y runtime Playwright. Nunca ejecutar el verificador aislado ni imports sobre la instalación principal. Los resultados de restore sustituyen los datos de prueba del entorno desechable, por eso la verificación de lifecycle se ejecuta antes de importar.

URLs principales: [Home](http://localhost:8080/), [formulario](http://localhost:8080/?page_id=514), [privacidad](http://localhost:8080/?page_id=3), [términos](http://localhost:8080/?page_id=511), [envíos](http://localhost:8080/?page_id=512), [cambios](http://localhost:8080/?page_id=513), [gestión](http://localhost:8080/wp-admin/admin.php?page=commerce-legal), [Mailpit](http://localhost:8025/).
