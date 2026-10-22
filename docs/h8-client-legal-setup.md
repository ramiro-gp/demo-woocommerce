# H8 — Adaptación legal por cliente

Esta guía prepara una tienda real. H8.2 sólo está habilitado para revisión de la demo local. No exportar su base de datos, cuentas QA, solicitudes, Mailpit, credenciales ni contenido DEMO como instalación de un cliente.

## Lo que aporta el titular

1. Nombre comercial, nombre completo/razón social auténtica, CUIT y domicilio legal; sitio/dominio definitivo. Validación del titular y asesor contable.
2. Email operativo para atención y privacidad, teléfono o canal alternativo, área responsable y personas autorizadas. Horarios reales de consultas/reclamos. Revisar D954 art. 6: relación con el horario de operación y mínimo de ocho horas en días hábiles de lunes a viernes cuando la atención es exclusivamente telefónica/electrónica.
3. Catálogo final: fabricante y registros cuando corresponda, características, riesgos, garantías, precio e impuestos, disponibilidad y condiciones de ofertas. No copiar condiciones ficticias de TRAMA.
4. Medios de pago reales y condiciones financieras respaldadas. H8.2 no autoriza activar Mercado Pago: siguen abiertos autenticidad/HMAC, replay, eventos tardíos, orden temporal, validaciones independientes y compatibilidad H7.1.
5. Logística: transportista contratado, cobertura, preparación, plazos y costos informados, contingencias, domicilio/horario real de retiro. Definir el retiro de productos por arrepentimiento sin costo al consumidor. El cálculo real de envío pertenece a WooCommerce; la página debe coincidir con su configuración.
6. Política voluntaria de cambios, diferenciada de defectos, garantía legal y revocación a distancia. No limitar derechos con ofertas ni un plazo comercial universal. Procedimiento de devolución y reembolso con responsables; no prometer tiempos bancarios no comprobados.
7. Inventario de datos: hosting, correo transaccional, pagos, logística, plugins, cookies, analítica si existiera, ubicación y destinatarios, transferencias internacionales y contratos. Revisar Gravatar y recursos del plugin Mercado Pago, que se observaron en checkout aun con Pro deshabilitado. Finalidades y bases de tratamiento. Inscripción de bases y demás obligaciones de la Ley 25.326 según evaluación legal.
8. Plazos y fundamentos de conservación por categoría: cuentas, pedidos/comprobantes, solicitudes, notas internas, registros de seguridad y backups. Procedimiento de rectificación, exportación y supresión/disociación. No dejar una conservación indefinida sin evaluación.
9. Código Data Fiscal oficial obtenido por el titular desde ARCA, con su hipervínculo y logo. Confirmación contable de aplicabilidad y correspondencia con CUIT/sitio. No proporcionar clave fiscal al desarrollador. No usar QR de otra empresa.
10. Aprobación jurídica expresa de los cinco textos y del circuito de recepción/atención, para la actividad, productos y jurisdicciones concretas. Revisión de excepciones y de documentos contractuales conservables. Aprobación de seguridad y autorización de publicación separadas.

## Configuración técnica

Instalar WooCommerce y Commerce Core en un entorno de prueba del cliente. La primera carga administrativa crea tres tablas del módulo; no crea páginas ni identidad ficticia. Commerce Base presenta el módulo, pero el formulario no depende del tema. Se puede desactivar mediante `commerce_core_legal_enabled` antes de `plugins_loaded`; las tablas se conservan para no perder trámites.

Crear o revisar las cinco páginas Gutenberg con contenido aprobado. No ejecutar `h8-2-setup.php`: exige localhost, backup y una base DEMO concreta. Reutilizar páginas preexistentes sólo tras leerlas y preservar revisiones. La copia inglesa de la demo se conserva en su backup privado; no se debe restaurar ese texto en el cliente.

En **Ajustes → Identidad y legales**, cargar los datos compartidos y seleccionar páginas publicadas. Al guardar se sincronizan privacidad y términos con los ajustes nativos. Revisar también **Ajustes → Privacidad** y **WooCommerce → Ajustes → Avanzado**. Si después se cambia una página desde los ajustes nativos, mantener la selección del módulo consistente y revisar todos los vínculos editoriales entre páginas.

En **WooCommerce → Ajustes → Cuentas y privacidad**, escribir los avisos de registro/checkout y aprobar retención. No exigir marketing. En el editor de checkout usar el bloque nativo **Términos y condiciones**, revisar su texto editable y actualizar sus enlaces si se reemplazan páginas; el texto personalizado de esta demo no se regenera al cambiar IDs. No agregar otro checkbox. Evaluar cuándo conviene usar el texto predeterminado nativo, que resuelve los enlaces de los ajustes.

Preparar la franja de Home y el footer en Commerce Base, o un acceso igualmente visible con el nuevo tema. El texto debe ser exactamente **BOTÓN DE ARREPENTIMIENTO**. Mantener acceso directo para invitados y excluir recordatorios comerciales del trámite. No depender de un menú oculto. Si la tienda comercializa servicios, analizar el **BOTÓN DE BAJA DE SERVICIO** como módulo adicional; no reutilizar la revocación de bienes para dar de baja suscripciones silenciosamente.

Para Data Fiscal copiar **sólo** las dos URL del recurso oficial. El módulo acepta HTTPS, hosts oficiales específicos, ruta de respuesta `/clavefiscal/qr/response.aspx` y logo `/images/f960/DATAWEB.jpg`; no acepta HTML, JS, credenciales ni hosts parecidos. Si ARCA cambia el formato, verificar la fuente y adaptar la allowlist con QA, nunca quitarla indiscriminadamente. El logo aparece sólo cuando están ambas URL. Verificar el vínculo autenticado por el titular y su visualización en Home; una URL permitida no acredita por sí sola identidad fiscal.

## Atención y datos

Revisar **WooCommerce → Arrepentimiento** diariamente. Administradores y gerentes de tienda con `manage_woocommerce` pueden leer y gestionar. Revisar si ese rol es adecuado para el equipo; no otorgarlo a atención externa sin evaluar todo lo que permite en WooCommerce.

La referencia y el email iniciales son declaraciones del solicitante, no pruebas de identidad. Verificar por un medio habitual apropiado (por ejemplo, contacto por un canal previamente asociado al pedido), con finalidad exclusiva de seguridad. No exigir por defecto DNI, fotos, datos bancarios, cuenta nueva ni motivo. Antes de asociar un pedido, confirmar esa verificación y dejar una nota breve, sin documentos ni secretos. No revelar información de una compra en respuesta a un email aún no verificado.

Estados: **recibida**, **en gestión**, **esperando información**, **gestión cerrada**. Cerrar un caso no significa ejecutar una devolución de dinero. Documentar decisiones, coordinación de retiro y resultado en notas; operar pedidos y reembolsos por WooCommerce y el medio de pago correspondiente con autorización del comercio. El módulo no realiza esas operaciones.

Un código se muestra inmediatamente. Intentar correo al consumidor y aviso interno; controlar fallos en la pantalla y reintentar sólo pendientes/fallidos. `sent` significa aceptado por el transporte, no lectura. Un proceso interrumpido puede dejar `sending`: revisar logs/transporte y determinar si ya se envió antes de recuperar ese estado mediante soporte autorizado. No reenviar a ciegas. Definir monitorización y atención para no convertir un recibo en una demora del derecho.

Herramientas → Exportar/Borrar datos personales usa la confirmación nativa por email. El exporter agrega campos de solicitudes; las notas internas requieren revisión manual por posible información del titular y de terceros. El eraser informa retención y revisión, sin eliminar casos automáticamente. Para un cliente debe aprobarse y ejecutarse una política concreta de eliminación/disociación de solicitudes y notas, incluyendo backups. La retención no se presume ilimitada.

## Condiciones previas a publicación

- Identidad y contactos auténticos; canales atendidos; páginas revisadas y aprobadas; Data Fiscal aplicable validado; configuración logística/pagos definitiva.
- Contrato/resumen entregado y conservable con la versión aplicable: la página editable e imprimible no archiva por sí sola la versión de cada compra. Definir soporte y archivo con asesoría.
- HTTPS, acceso administrativo mínimo, backups restaurables, exclusión de caché/CDN de la página del formulario y sus POST. La cookie es HttpOnly/SameSite Lax; en HTTPS lleva Secure. No cachear tokens de sesión.
- Confirmar proxy/red: el límite de IP usa exclusivamente `REMOTE_ADDR`, nunca headers arbitrarios. Detrás de un proxy revisar que no agrupe compradores bajo una IP injustificadamente y conservar límites proporcionales/canal alternativo. No confiar en `X-Forwarded-For` sin configuración segura.
- Revisar límites de 30 envíos/IP/hora y 6/email/hora, con recuperación por atención. No modificar esos controles en producción sin probar abusos y accesibilidad.
- Correo real preparado y probado en staging con destinatarios autorizados. Mailpit sólo es local; H8.2 no configura SMTP productivo. Definir alertas y recuperación de entregas fallidas.
- Repetir guest/logged-in, referencia ausente/desconocida, concurrencia, CSRF, permisos, no divulgación, privacidad, correo fallido y responsive/teclado. Verificar carrito, checkout, BACS, HPOS y módulos existentes proporcionalmente.
- No trasladar metadatos `_commerce_legal_demo`. El módulo bloquea acceso a esas páginas fuera de `local` y oculta sus respuestas REST. La guarda no sustituye un proceso de publicación: crear páginas auténticas y retirar la marca sólo tras aprobación registrada. No desactivar la guarda para publicar textos ficticios.

Conservar aprobaciones, versión de contenido y resultados QA junto con la entrega del cliente. Revalidar la [matriz normativa](h8-legal-compliance-matrix.md) en la fecha de lanzamiento.
