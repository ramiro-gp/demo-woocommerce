# H7 — Correo transaccional para producción

Estado: preparación documental de H7.2. Investigación consultada el **19/10/2026**. No se accedió a una cuenta Hostinger, auditó un contrato concreto, contrató correo, configuró SMTP real ni modificó DNS. Estos pasos pertenecen a la preparación productiva de cada cliente. El cierre de comunicaciones no levanta los bloqueos productivos de Mercado Pago documentados en [H7.1](h7-1-payments.md).

## Estrategia y límites de esta entrega

| Entorno | Transporte | Qué demuestra |
| --- | --- | --- |
| Desarrollo actual | Mailpit dentro de Docker; bandeja en <http://localhost:8025> | Disparadores, destinatarios, contenido, HTML, texto y enlaces locales |
| Preparación productiva por cliente | Un proveedor SMTP autenticado o transaccional, con dominio autorizado | Conectividad, autenticación y entrega real después de las verificaciones de este documento |

WooCommerce conserva los eventos y emails transaccionales; WordPress conserva `wp_mail()`. El transporte se configura por instalación sin crear otro motor de notificaciones, replicar pedidos ni hacer envíos desde el theme. Un resultado exitoso de `wp_mail()` sólo confirma que el método procesó la solicitud sin error; no confirma recepción. [Referencia oficial de WordPress](https://developer.wordpress.org/reference/functions/wp_mail/).

`docker/local-mail.php` es una herramienta exclusiva de desarrollo, montada como MU-plugin. En entorno `local` dirige PHPMailer a `mailpit:1025`, sin autenticación ni TLS dentro de la red privada de Docker. No se distribuye en el paquete comercial ni se lleva a producción. No usar esa configuración como ejemplo productivo. [Arquitectura local](architecture.md).

## Qué incluye realmente Hostinger Premium

**Premium Web Hosting y Business Premium Email son productos distintos.** El nombre del hosting no identifica por sí solo el servicio de correo contratado. La documentación vigente diferencia estas versiones de Premium:

| Alta del plan de hosting | Versión | Buzones indicados en la tabla de Premium |
| --- | --- | --- |
| Hasta el 23/04/2025 | v1 | 100 |
| Del 24/04/2025 al 16/12/2025 | v2 | 2 |
| Desde el 17/12/2025 | v3 | 2 |

La oferta de email incluida en los planes nuevos es temporal: un año y 1 GB por buzón; después requiere revisar la continuidad o contratar correo. Los planes heredados tienen condiciones diferentes. Esta tabla no prueba derechos de una cuenta particular ni promete buzones ilimitados. Confirmar versión, alcance por dominio, cantidad disponible, fecha de fin de prueba y renovación en el contrato y en **Emails → Mailboxes → View limits**. [Versiones y límites oficiales](https://www.hostinger.com/support/10717644-new-web-and-cloud-hosting-limits-at-hostinger/).

### Cuotas de correo publicadas

| Servicio de email | Almacenamiento por buzón | Tasa diaria de entrada/salida publicada |
| --- | --- | --- |
| Business Starter de prueba anual | 1 GB | 100/día |
| Business Starter de pago | 5 GB | 1000/día |
| Business Standard de pago | 20 GB | 3000/día |
| Business Premium de pago | 50 GB | 3000/día |

Los límites se aplican por buzón y la tasa diaria usa una ventana móvil de 24 horas. No se interpreta como reinicio a medianoche. Hostinger puede cambiarlos: el panel del servicio es la referencia operativa antes del despliegue. La tabla general publica hasta 100 destinatarios por mensaje y 35 MB por mensaje saliente, incluidos adjuntos. Estos máximos no son objetivos de uso para una tienda. [Parámetros de Hostinger Mail](https://www.hostinger.com/support/4625828-parameters-and-limits-of-hostinger-email/).

Para estimar capacidad, contar los mensajes realmente habilitados: comprador, administrador, cambios de estado, notas, recuperación de contraseña y reenvíos. Un pedido puede producir varios mensajes durante su ciclo. Reservar margen para picos y operación humana si el buzón se comparte. No convertir una cuota de mensajes en una promesa de cantidad de pedidos.

Hostinger también limita el envío sin autenticación mediante PHP `mail()` a 10 mensajes/minuto y 100/día en su guía específica. Es un límite distinto del SMTP del buzón. La estrategia recomendada usa SMTP autenticado y conserva las cuotas del plan elegido. [Limitación de PHP mail y recomendación SMTP](https://www.hostinger.com/support/11393648-php-mail-limitation-explained-how-to-improve-email-delivery-with-smtp/).

## Configuración SMTP de Hostinger Mail

Para un cliente cuyo buzón esté efectivamente alojado en **Hostinger Mail**, verificar los datos actuales en **Emails → Connect Apps & Devices**:

| Campo | Configuración documentada |
| --- | --- |
| Servidor saliente | `smtp.hostinger.com` |
| Conexión principal publicada | Puerto `465`, SSL/TLS implícito |
| Alternativa publicada | Puerto `587`, TLS/STARTTLS |
| Autenticación | Activada |
| Usuario | Dirección completa del buzón autorizado |
| Contraseña | Contraseña de ese buzón; no la contraseña de hPanel |

Elegir una combinación coherente de puerto y cifrado. Mantener la validación de certificados TLS; no desactivarla para resolver errores. Si el cliente usa otro servicio de correo, obtener su configuración de su proveedor. No asumir que toda cuenta asociada al hosting usa estos servidores. [Configuración oficial](https://www.hostinger.com/support/1575756-how-to-get-email-account-configuration-details-for-hostinger-email/).

En la futura instalación productiva, configurar **un solo componente de transporte** para `wp_mail()` y validar su compatibilidad con las versiones instaladas de WordPress y PHP. H0 dejó FluentSMTP diferido hasta necesitar transporte real; H7.2 no lo instala ni configura. No superponer plugins SMTP, hooks de captura local y otro relay sobre el mismo envío. Mantener visibles los fallos del transporte; no ocultarlos mediante un retorno automático a correo sin autenticación.

## Identidad y configuración por cliente

Registrar estos valores antes de habilitar entrega real:

| Dato | Decisión necesaria |
| --- | --- |
| Cuenta y dominio | Propiedad del cliente, responsable técnico y servicio elegido |
| Nombre del remitente | Nombre comercial configurado en WordPress/WooCommerce |
| Dirección From | Buzón o remitente del dominio autorizado por el proveedor |
| Reply-To | Dirección atendida por el comercio; probar la respuesta real |
| Destinatarios administrativos | Buzones del comercio configurados en los emails nativos correspondientes |
| URLs e imágenes | Origen público HTTPS definitivo, accesible desde el cliente de correo |
| Cuota y continuidad | Volumen esperado, límite vigente, vencimiento y responsable de renovación |

Como criterio inicial, usar el mismo buzón autenticado como remitente cuando se elija Hostinger SMTP. Los alias u otros remitentes necesitan autorización y prueba explícitas. Para proveedores transaccionales, usar una dirección del dominio verificado. No usar el email del comprador como From; una respuesta puede dirigirse al destinatario adecuado mediante Reply-To respetando el comportamiento del email concreto. No forzar un Reply-To global que altere las notificaciones administrativas sin revisarlas.

Reemplazar los valores ficticios y la identidad demo mediante configuración. No usar TRAMA, `example.test`, direcciones personales del desarrollador, dominio de prueba, URL de túnel o `localhost` como valores productivos. Ajustar el origen canónico de WordPress y los recursos del email antes de enviar a compradores. La previsualización local no demuestra que una imagen sea accesible desde Internet ni certifica Gmail, Outlook o sus aplicaciones móviles.

## DNS: SPF, DKIM y DMARC

Primero identificar quién administra la zona por sus nameservers y qué servicios ya reciben y envían correo. Exportar la zona actual y preparar el cambio concreto. Usar los registros indicados para **el proveedor y dominio del cliente**. No reemplazar MX de un proveedor existente sólo para habilitar un relay saliente. MX decide dónde se recibe correo. [Conexión de dominio y función de los registros](https://www.hostinger.com/support/8650765-set-up-a-domain-for-hostinger-email/).

Si se elige Hostinger Mail para ese dominio, su guía publica:

| Registro | Referencia de la guía; verificar antes de aplicar |
| --- | --- |
| SPF TXT de la raíz | `v=spf1 include:_spf.mail.hostinger.com ~all` |
| DKIM | Tres CNAME con selectores `hostingermail-a`, `hostingermail-b` y `hostingermail-c` bajo `._domainkey`, hacia los destinos publicados por Hostinger |
| DMARC TXT en `_dmarc` | Política inicial de observación `p=none` con dirección de reportes controlada por el cliente |

No publicar un segundo SPF: combinar los emisores legítimos en el registro existente. No copiar un DKIM de otro proveedor ni adivinar selectores. Preparar el buzón de reportes antes de añadir `rua`. Revisar los resultados de autenticación y los emisores legítimos antes de endurecer DMARC a `quarantine` o `reject`. La propagación puede llevar hasta 24 horas según Hostinger. [Guía DNS manual y valores completos](https://www.hostinger.com/support/8671319-set-up-a-domain-for-hostinger-email-manually/).

Para un proveedor transaccional externo, seguir su validación de dominio, DKIM y Return-Path. Conservar la recepción existente salvo una migración de correo deliberada. El objetivo de aceptación es que el mensaje real presente SPF/DKIM válidos y DMARC aprobado con alineación del dominio From mediante el mecanismo correspondiente; publicar registros sin probar el mensaje no cierra esta verificación.

## Alternativa transaccional: Postmark

Recomendación de selección: Hostinger SMTP puede servir para un comercio pequeño después de confirmar servicio, cuota y entrega. Elegir un proveedor transaccional cuando los avisos de pedidos necesiten más margen de volumen, separación del correo humano o diagnóstico de entregas y rebotes. La elección final corresponde al cliente; esta investigación no activa un servicio.

**Postmark** es una alternativa concreta compatible con SMTP. Para transacciones publica `smtp.postmarkapp.com` y STARTTLS en `587` —también admite `25` y `2525`—. Su documentación permite un SMTP Token por stream: Access Key como usuario y Secret Key como contraseña. Habilitar acceso SMTP y utilizar el stream transaccional; no mezclar campañas con avisos de pedidos. [Documentación SMTP](https://postmarkapp.com/developer/user-guide/send-email-with-smtp).

Validar el remitente o, preferentemente, el dominio del cliente y configurar la autenticación que indique el proveedor. [Sender Signatures y verificación](https://postmarkapp.com/developer/user-guide/managing-your-account/managing-sender-signatures). Postmark ofrece información de rebotes mediante panel/API/webhooks; su integración automática no forma parte de H7.2. [Rebotes](https://www.postmarkapp.com/developer/webhooks/bounce-webhook).

La oferta Developer publicada incluye 100 mensajes **por mes**, destinada a pruebas; no asumir que cubre la operación de una tienda. Revisar tarifa, límites, retención y facturación al contratar, sin fijar precios en el código comercial. [Planes oficiales](https://www.postmarkapp.com/pricing).

## Seguridad de credenciales y datos

- Credenciales y cuentas pertenecen a cada cliente. No reutilizar un token de agencia o desarrollador entre comercios; separar además pruebas y producción.
- Configurar secretos en el mecanismo privado admitido por el transporte y el hosting, fuera del repositorio. Si el conector elegido guarda secretos en la base, tratar sus opciones y backups como información sensible; no asumir cifrado por defecto.
- No poner contraseñas, tokens, archivos `.env` privados, volcados de opciones o configuración sensible en Git, capturas, tickets ni comandos con credenciales literales. Las variables de ejemplo no crean por sí mismas una integración de secretos.
- Aplicar acceso administrativo mínimo, rotar credenciales ante exposición o cambio de responsable y retirarlas al finalizar una relación comercial.
- Limitar logs y su retención. No conservar cuerpos completos, direcciones, claves de pedidos o enlaces de restablecimiento en registros públicos. Para soporte, usar identificador de mensaje, fecha, código de error y evidencia redactada.
- El SMTP transporta los emails oficiales. No incluir secretos de Mercado Pago, metadatos internos ni información de pago que no corresponda al comprador.

## Procedimiento de verificación productiva

Este procedimiento se ejecutará en el hito productivo autorizado, con buzones de prueba propiedad del cliente o expresamente autorizados. No enviar correos a compradores históricos para probar.

1. **Confirmar servicio.** Registrar cuenta, dominio, versión del hosting, producto de email, cuotas y continuidad. Elegir Hostinger o el proveedor transaccional. Verificar que el servidor de la aplicación pueda acceder al endpoint y puerto elegidos.
2. **Preparar identidad.** Configurar nombre comercial, From, Reply-To, destinatarios administrativos y URLs HTTPS. Retirar la captura Mailpit del despliegue. Confirmar que no haya otro transporte activo ni valores ficticios.
3. **Validar DNS.** Comprobar registros publicados con consultas DNS y el panel del proveedor. Esperar propagación. No cambiar la recepción de correo sin que forme parte del cambio aprobado.
4. **Probar transporte desde WordPress en el hosting.** Enviar un solo mensaje a un destinatario controlado mediante la herramienta del conector. Confirmar autenticación y TLS. Desactivar la depuración detallada después del diagnóstico; no exponer credenciales.
5. **Probar el evento real.** Crear un pedido BACS sintético y revisar aviso al comprador y al administrador. Simular verificación bancaria, pasar a Processing y luego Completed por WooCommerce. Validar un reenvío explícito. No efectuar cobros ni reembolsos monetarios para probar SMTP.
6. **Comprobar recepción y encabezados.** En buzones controlados de Gmail y Outlook, comprobar bandeja/spam, From, Reply-To, Return-Path, DKIM y `Authentication-Results`; revisar SPF, DKIM y DMARC. Confirmar también que responder llega al comercio.
7. **Revisar contenido.** Comparar asunto, HTML y alternativa de texto si está configurada; comprobar total, variantes, envío, acentos, móvil, imágenes bloqueadas y links nativos de acceso. No eliminar claves o permisos de los enlaces para hacerlos públicos.
8. **Registrar resultado privado.** Conservar fecha, proveedor, ID del mensaje, resultado de autenticación y recepción, más capturas redactadas. Una respuesta SMTP de aceptación no demuestra colocación en bandeja principal ni entrega de todos los mensajes futuros.
9. **Definir operación.** Asignar responsable de cuotas, renovación, errores y rebotes. Mantener un procedimiento para corregir una dirección y reenviar explícitamente desde WooCommerce cuando corresponda, sin duplicar automáticamente todos los mensajes.

La reputación depende de autenticación, contenido, volumen y comportamiento de destinatarios. Mantener asuntos claros, direcciones válidas, mensajes transaccionales relevantes y volumen dentro de cuota. Si se utiliza un analizador externo de entregabilidad, enviar sólo contenido sintético autorizado. No prometer entrega inmediata o ausencia de spam por tener SPF/DKIM configurados. [Buenas prácticas oficiales de Hostinger](https://www.hostinger.com/support/5240869-improve-email-deliverability-at-hostinger/).

## Si los mensajes no llegan

| Observación | Revisar primero |
| --- | --- |
| No se generó el email esperado | Estado/evento real de WooCommerce, email habilitado y destinatario del tipo correcto; consultar la matriz de H7.2 |
| Error de autenticación SMTP | Usuario completo, credencial del servicio correcto, permisos SMTP, buzón activo y credencial vigente |
| Timeout o error TLS | Endpoint, salida de red desde el hosting, combinación puerto/cifrado y certificados; no desactivar TLS como solución |
| Límite o rechazo temporal | Cuota y ventana móvil del buzón/proveedor, mensajes acumulados y picos; no reintentar masivamente |
| SMTP aceptó y el mensaje no aparece | Spam, cuarentena, rebote, supresión y registros del proveedor; comprobar destinatario y encabezados del mensaje real |
| SPF/DKIM/DMARC fallan | DNS autoritativo, SPF único, selector y firma del proveedor efectivo, propagación y alineación con From |
| Imágenes o links rotos | Origen HTTPS de WordPress, recursos accesibles, rutas de preview y ausencia de localhost/túneles vencidos |

No modificar estados del pedido para forzar repetidamente un email durante el diagnóstico. Separar el problema de transporte del estado comercial, conservar la evidencia y utilizar una acción nativa de reenvío cuando sea adecuada. No añadir un sistema paralelo de notificaciones o deduplicación para compensar una configuración incorrecta.

## Pendientes antes de producción

Faltan la cuenta y contrato reales, dominio y DNS efectivos, proveedor elegido, configuración privada del transporte y pruebas de entrega desde el hosting a buzones autorizados. H7.2 sólo prepara este recorrido. No certifica una cuenta Hostinger, entrega en clientes externos ni habilita cobros reales. Mailpit continúa siendo el mecanismo de desarrollo.
