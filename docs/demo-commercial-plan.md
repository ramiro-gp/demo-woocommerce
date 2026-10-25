# D1 — Plan de demos comerciales TRAMA y ORIGEN CASA

Fecha de preparación: **24/10/2026, Argentina**. Alcance: investigación pública, código opcional y laboratorio local aislado. **PUBLIC DEPLOYMENT: NOT PERFORMED.** Este documento no autoriza crear sitios, DNS, certificados, cuentas, servicios ni publicar material. H0–H11 permanece cerrado; D1 no resuelve el bloqueo productivo de Mercado Pago ni habilita otra etapa.

## Recomendación

Conservar **Hostinger Premium Web Hosting legacy** y preparar dos WordPress independientes, uno por demo. Es la primera alternativa a validar porque ya existe la suscripción y se necesita catálogo, filtros, variaciones y carrito reales, sin depender de una PC. Los candidatos son `trama-demo.ramita.dev` y `origen-demo.ramita.dev`; **son propuestas, no destinos creados ni nombres aprobados**.

Es razonable **ensayar la coexistencia de las dos demos con tráfico bajo**, incorporando primero una y luego la otra, siempre que se compruebe margen real del plan y no empeoren las webs actuales. No hay evidencia suficiente para garantizar hoy su capacidad simultánea. Si los ocho sitios ya consumen el margen disponible, corresponde NO-GO para añadir WooCommerce, conservar el plan y presentar una alternativa de presentación o alojamiento independiente. La falta de pagos reduce trabajo comercial, pero no elimina PHP, DB, búsquedas, sesiones, robots ni mantenimiento.

Hostinger recomienda crear un subdominio como **web independiente** desde «Añadir/Crear sitio» cuando alojará WordPress propio; la otra modalidad crea una subcarpeta del sitio existente. D1 elige la primera. Reservar conservadoramente **dos cupos de sitio** y verificar el contador legacy antes/después en la etapa autorizada: de 8 a 10 si no cambió el inventario. La documentación general no acredita el cómputo contractual de esta cuenta. [Guía oficial de subdominios](https://www.hostinger.com/support/1583405-how-to-create-and-delete-subdomains-in-hostinger/), [añadir una web](https://www.hostinger.com/support/1583214-how-to-add-a-website-in-hostinger/).

## Alternativas y qué experiencia entregan

| Opción | Experiencia real | Coste y preparación | Seguridad, recursos y límites | Decisión D1 |
| --- | --- | --- | --- | --- |
| A. Dos WordPress independientes en Premium actual | WooCommerce persistente, catálogo y carrito funcionales; enlaces simultáneos, sin PC encendida | Incremento de abono esperado: ARS 0 dentro de cupos y funciones existentes, sujeto a confirmación; instalación y mantenimiento de dos sitios | CPU/RAM/I/O/cuenta compartidos; SSL por hostname, DB/usuarios/uploads separados; requiere controles DEMO y recuperación | Preferida, **condicional a admisión de carga y aislamiento aceptado** |
| B1. Una demo privada temporal en hosting, con autenticación HTTP y fecha de retirada | WordPress real mientras esté disponible; acceso mediante enlace más secreto comunicado por canal privado | Puede usar un cupo existente autorizado; mismos costes operativos; retirar/restringir después | Reduce exposición casual, no suprime mantenimiento; noindex además de autenticación; dos simultáneas consumen recursos igualmente | Útil para presentaciones guiadas futuras; no construir sistema privado ahora |
| B2. WordPress Playground en navegador del visitante | WordPress ejecutado en su navegador; no es el mismo servidor PHP/MySQL ni una tienda persistente compartida | Sin hosting PHP propio; hay trabajo adicional de blueprint, distribución y QA de compatibilidad | Almacenamiento del navegador y límites del dispositivo; carga inicial de WooCommerce; acceso al paquete no equivale a autenticación privada | Alternativa económica para sesiones individuales si se valida aparte; no se afirma paridad D1 |
| B3. Docker local más túnel | Tienda funcional mientras la PC y el túnel estén activos | No requiere otro hosting, pero exige PC/red disponibles | Expone el equipo, disponibilidad y secretos locales; contradice la necesidad planteada | **Descartada y expresamente no autorizada** |
| C1. Exportación estática | Catálogo visual; sólo lo que se exporta. Carrito/variaciones/filtros necesitarían lógica adicional y no probarían WooCommerce | Poco consumo de hosting; coste de exportación y adaptación | Sin PHP/DB pública; buen aislamiento técnico; validar enlaces y assets | Respaldo de presentación si se acepta perder funcionalidad; no sustituye la demo pedida |
| C2. Capturas o vídeo | Recorrido visual no interactivo | Distribución sencilla, sin servidor WordPress | Sin carrito navegable ni experiencia independiente | Material de apoyo, nunca presentado como tienda funcional |
| C3. Hosting WordPress independiente contratado aparte | Tienda persistente y más separación respecto de la cuenta actual | Nuevo abono, renovación y operación por cotizar; no se selecciona un proveedor ni se contrata | Ventaja real cuando CPU, aislamiento o criticidad impiden A | Contingencia sólo con evidencia y aprobación; no implica actualizar Premium |

Playground dispone de modos guardado/autoguardado en el navegador y temporal; el temporal se pierde al refrescar/cerrar, y el almacenamiento puede desaparecer al limpiar datos. Esto requiere un procedimiento propio de entrega y no demuestra compatibilidad del proyecto con HPOS/Blocks. [Limitaciones oficiales de Playground](https://developer.wordpress.org/playground/developers/limitations/).

La modalidad privada propuesta es protección de **toda** la instalación en el servidor, no un formulario de contraseña dentro de Home. Hostinger documenta protección de directorios; habría que probar también REST, medios, login y caché, y mantener secretos fuera de enlaces/Git. [Protección oficial](https://www.hostinger.com/support/1583470-how-to-password-protect-a-website-in-hostinger/). Un checkout guiado sólo se consideraría en otro laboratorio con personas ficticias, correo descartado, sin gateways reales y una autorización posterior. Quitar el modo público en una URL accesible no la convierte en demo privada.

## Plan contratado y capacidad

Los siguientes datos provienen del titular, verificados por él en hPanel y reiterados en el pedido D1. **No representan una inspección autenticada del agente.** No se instalaron conectores ni se modificó la configuración de la cuenta para esta investigación.

| Parámetro legacy | Valor aportado | Interpretación |
| --- | --- | --- |
| Sitios/complementos | 25 permitidos; 8 creados | Cupo administrativo, no 25 WooCommerce activos |
| Disco / inodos | 25 GB / 400.000 | Compartidos; faltan uso agregado y espacio temporal |
| RAM / CPU | 2048 MB / 1 núcleo | Compartidos entre todas las webs y procesos |
| Workers / procesos | 40 PHP workers / 80 procesos máximos | Límites, no concurrencia ni rendimiento garantizados |
| Ubicación | Brasil | Medir latencia desde Argentina; no inferir TTFB |
| Renovación | ARS 130.788 mostrados | Ciclo, impuestos y vigencia pendientes; no anualizar |
| Facturación / vencimiento | 15/08/2029 / 29/08/2029 | Campos distintos; no se cambia autorrenovación |

La guía oficial actual indica que «Resources Usage» en web/cloud agrega los sitios del plan, muestra CPU, memoria, I/O, workers, IOPS y procesos, con gráficos GMT+0 y actualización cada 10–15 minutos. Verificar que esa semántica corresponde a la pantalla legacy y guardar sólo métricas sanitizadas. Las antiguas lecturas de disco/inodos de H11 no acreditan margen de CPU/RAM. [Uso de recursos](https://www.hostinger.com/support/2436138-how-to-check-resource-usage-in-hostinger/).

### Admisión propuesta para una etapa autorizada

1. Registrar siete días representativos de carga base de las ocho webs: máximos/picos, saturaciones y errores, horario de tareas, disco/inodos y tamaño DB. Anotar periodo y zona horaria. No ejecutar carga sobre sitios de clientes.
2. Confirmar cuotas I/O/IOPS, conexiones/tamaño DB, política de CPU y aislamiento efectivo del usuario de sistema. No trasladar cifras de Premium nuevo al legacy.
3. Medir footprint de cada instalación local limpia, incluyendo miniaturas, sesiones y crecimiento. Reservar espacio para actualización, descompresión y al menos una recuperación de las **demos**; el tamaño comprimido de los ZIP no sirve como presupuesto de disco.
4. Antes de promover, definir con el titular un margen de admisión. Como guard operativo conservador propuesto, exigir 30% libre en disco/inodos y ausencia de saturaciones recurrentes; revisar CPU/RAM/I/O/colas por separado. Este porcentaje es una decisión del proyecto, **no una garantía del proveedor ni una prueba de capacidad**.
5. Preparar primero una demo protegida, con caché de catálogo y sin promoción. Comparar con la base durante una ventana representativa; luego añadir la segunda. Ensayo humano moderado de dos visitantes, sin stress test del plan compartido. Abrir acceso sólo después de QA y aprobación.
6. Detener la demo recién añadida si aparecen límites, errores 5xx/timeouts, colas crecientes o degradación reproducible de las webs actuales. No pedir un upgrade automático. Revertir sólo sus recursos y revisar la alternativa.

Dos carritos simultáneos funcionan en bases/sesiones independientes; siguen compitiendo por cómputo compartido. Una eventual difusión masiva, crawler agresivo o campaña cambia esta evaluación y exige revisar capacidad antes de compartir enlaces ampliamente.

El laboratorio D1 midió aproximadamente **214 MiB de archivos TRAMA y 200 MiB ORIGEN**, más **3,8 y 3,4 MiB de tablas/índices DB**, respectivamente. Entre ambas hay 22.548 entradas de archivos/directorios contadas en los árboles WordPress; el consumo real de inodos incluye otros elementos del hosting. Son referencias iniciales locales, sin backups/caché/growth ni equivalencia con una cuota remota. No constituyen prueba de CPU/RAM ni de capacidad del Premium. [Medición de archivos](evidence/d1/footprints.json), [TRAMA](evidence/d1/trama-inventory.json), [ORIGEN](evidence/d1/origen-inventory.json).

## Requisitos y comprobaciones de hPanel pendientes

La referencia local aprobada es WordPress 7.1.3 / WooCommerce 11.2.1 / PHP 8.4 y MariaDB 10.11.19, más los seis paquetes H11. La recomendación oficial actual para WooCommerce 10.8+ pide WordPress ≥6.9, PHP ≥8.3 (probado hasta 8.4), MySQL ≥8.0 o MariaDB ≥10.6, HTTPS y límite de memoria WordPress ≥256 MB. Estos mínimos no certifican una instalación concreta ni sustituyen la matriz del proyecto. [Requisitos WooCommerce](https://woocommerce.com/document/server-requirements/).

| Verificar sin modificar webs actuales | Dónde y qué registrar |
| --- | --- |
| Cupo y raíz de cada futuro sitio | Websites/Crear sitio: posibilidad de subdominio independiente y consumo de slot. File Manager/SSH: ruta absoluta resuelta; no adivinar `/public_html` del portfolio |
| PHP web y CLI, módulos y límites | PHP Info/Configuration, WooCommerce Status y preflight privado; `mysqli`, curl, mbstring, OpenSSL, XML/DOM, zip, fileinfo, GD/Imagick; OPcache, `memory_limit`, subidas, ejecución y espacio temporal. Sin `phpinfo()` público |
| DB y usuario propios | Databases/Management: versión/motor, utf8mb4/InnoDB, capacidad, cuota y conexiones; una DB y credencial distinta por demo, permisos limitados a su DB |
| SSL y dominio | Security/SSL: emisión para hostname exacto, renovación y HTTPS. No cambiar certificado ni HSTS del portfolio |
| Cron/Action Scheduler | Advanced/Cron Jobs, binario/ruta soportados y frecuencia; limpieza de sesiones y colas; nada de tareas de marketing, importaciones recurrentes o envíos |
| Backups | Files/Backups: frecuencia/retención reales, selección por sitio y DB, descarga independiente, recuperación medida y responsable; ninguna restauración a nivel cuenta |
| Aislamiento | UID/permisos/lectura cruzada, SFTP/SSH delegado, DB grants, límites de cuenta; un directorio y contraseña propios no demuestran aislamiento del sistema operativo |
| Coste y contrato | Facturación: ciclo/impuestos de ARS 130.788, extras realmente incluidos; soporte legacy cuando el panel no lo explique |

**Precaución concreta:** Hostinger documenta que cambiar PHP en «PHP Configuration» afecta las webs del plan (excepto Builder). No usar ese selector para preparar una demo. Mantener el runtime existente si cumple; si no, evaluar únicamente el override por directorio documentado y comprobar PHP servido, extensiones y opciones en el destino protegido. Si no puede cambiarse sólo la demo, NO-GO hasta otra decisión. [Alcance global de PHP](https://www.hostinger.com/support/1575755-how-to-change-the-php-version-of-your-hostinger-hosting-plan/), [override por directorio](https://www.hostinger.com/support/4047803-how-to-change-the-php-version-for-subfolders-or-subdomains-in-hostinger/).

SSL para dominios/subdominios está incluido en la oferta web/cloud publicada y se emite automáticamente al crearlos, pero debe verificarse el estado efectivo del hostname legacy antes de abrir acceso. [SSL oficial](https://www.hostinger.com/support/1575761-how-to-claim-a-free-ssl-that-comes-with-a-hostinger-hosting-plan/). El staging integrado está publicado para Business/Unlimited o superiores; no se presupone incluido en Premium. [WordPress Overview](https://www.hostinger.com/support/5609910-how-to-use-the-wordpress-overview-in-hostinger-dashboard-at-hostinger/).

## Arquitectura de exposición y modo DEMO

El módulo MU opcional de [deployment/demo-public](../deployment/demo-public/) se instala sólo en demos, fuera de los seis ZIP aprobados. Es independiente de marca y conserva Commerce Base/Core sin forks ni condicionales TRAMA/ORIGEN. Por defecto está apagado; requiere flag booleano explícito, hostname exacto, entorno `local`/`staging` y marcador DB `commerce_demo_public_installation=public-demo-v1`. El estado activo debe ser verificable desde HTTP y CLI. Una configuración incompleta cuando se solicitó activarlo responde 503 y conserva bloqueo de pedidos/correo.

| Disponible al visitante | Bloqueado en el servidor |
| --- | --- |
| Home, categorías, búsqueda y filtros | Checkout y generación de pedidos, incluso acceso directo |
| Fotos, fichas, precios ilustrativos y variaciones | Pagos, BACS, order-pay y callbacks de gateways |
| Añadir al carrito, cantidades y eliminación | Store API checkout/order y lotes que puedan crear pedidos |
| Promociones editoriales y Lookbook | Registro, recuperación y modificación de cuentas |
| Páginas legales explicativas | Recepción de arrepentimiento y formularios reales |
| Aviso DEMO visible y discreto | Emails externos, XML-RPC y administración por visitantes |

El módulo preserva la condición de producto comprable para demostrar el carrito. Combina bloqueo temprano de rutas y defensas oficiales de WordPress/WooCommerce; no depende de esconder botones ni de JavaScript. Sólo se mantienen las APIs necesarias de catálogo/carrito; el resto no debe habilitarse por conveniencia. El aviso indica: «Tienda de demostración. Los productos y precios son ilustrativos. No se realizan compras reales». El intento de checkout explica el límite y no solicita datos bancarios ni personales.

También desactiva el cálculo de envíos/direcciones mediante `wc_shipping_enabled`, sin impedir artículos/cantidades/cupones del carrito. `cart/update-customer` queda cerrado para no recolectar dirección/email. Bloquea `wp_mail` antes del transporte y HTTP saliente de WordPress; por eso actualizaciones y mantenimiento requieren una ventana con protección externa, flag temporalmente apagado, QA y reactivación antes de reabrir. El guard no puede controlar una llamada directa a sockets/`mail()` de código ajeno: no instalar integraciones o transportes externos. `setup.php` prepara opciones/páginas sobre una DB limpia, con un solo administrador `.test`; nunca se usa para transformar una tienda comercial existente.

El módulo no sustituye controles del servidor. Proteger `wp-login.php` y `wp-admin` con acceso administrativo restringido (preferible SSH/WP-CLI y restricción HTTP/IP del host), negar ejecución PHP en uploads, listados, archivos de configuración, `.git`, copias y logs. Mantener sólo plugins necesarios, permisos WordPress soportados, edición de archivos deshabilitada y contraseñas/salts exclusivos. No dejar un usuario público de administración. No prometer que ocultar versiones protege vulnerabilidades. [Hardening oficial WordPress](https://developer.wordpress.org/advanced-administration/security/hardening/).

**Mercado Pago debe estar ausente**, no sólo con Checkout Pro apagado. Sin carpeta/plugin activo, credenciales, SDK ni requests a sus dominios. H9 detectó recursos incluso con gateway deshabilitado; D1 no modifica el plugin oficial ni cierra ese bloqueo. Ningún proveedor de pago debe instalarse como parte de la demo pública.

## Privacidad, contenido e identidad

Partir de WordPress/DB nuevos. Importar únicamente fixtures versionados DEMO: catálogo/imágenes/editorial TRAMA y fixture independiente `demo/origen-casa`; medios regenerados en uploads propios. Los importadores H3–H5/H10 tienen guardas locales: no quitarlas para publicar ni usarlos contra una tienda real. El runbook define la transferencia de contenido limpio revisado; nunca importar una base histórica y «borrar clientes después».

No transferir pedidos, clientes, notas, arrepentimientos, direcciones, correos reales, teléfonos, datos fiscales, contraseñas/salts, tokens, logs, backups ni sesiones de las instalaciones existentes. El administrador futuro se crea directamente en cada destino con secreto independiente y acceso restringido. Los datos ficticios de catálogo/precios no autorizan usar una persona real como cliente de prueba. La única identidad personal necesaria será la del operador administrativo, guardada fuera del contenido público y excluida de la exportación.

Conservar diseños aprobados, imágenes y textos editoriales; TRAMA mantiene Talle/Color y ORIGEN Material/Acabado/Capacidad/Medidas. Revisar contactos, WhatsApp, enlaces sociales, QR fiscal, banco, garantías/envíos y textos de legales antes de exposición. Si una pieza presenta el catálogo como oferta real o invita a enviar una solicitud, sustituir **sólo esa pieza** por explicación DEMO, conservando el original versionado para comercios reales. El aviso general no justifica dejar datos operativos ni promesas contradictorias.

Separar DB, usuario DB, administrador, salts, uploads, opciones, cookies/sesiones y credenciales. No configurar cookies para `.ramita.dev`; verificar que una sesión del subdominio A no autentica ni comparte carrito con B o con el portfolio. HTTPS, código común y cuentas separadas no eliminan el riesgo de compartir UID/cuenta: un compromiso podría alcanzar otras webs. Si no puede aceptarse/reducirse ese riesgo, usar hosting independiente o presentación estática.

## SEO y seguridad operativa

Cada demo debe responder `noindex, nofollow` mediante robots meta y `X-Robots-Tag`, sin sitemap público ni marcado Product/Offer/Organization que la presente como comercio activo. Conservar canonical de la **propia URL DEMO** o retirarlo coherentemente si un complemento lo agrega; no canonicalizar productos ficticios al portfolio ni a una marca real. Revisar HTML/JSON-LD final tras caché, no sólo opciones. No crear Search Console ni incluir demos en sitemap del portfolio; un enlace opcional desde `ramita.dev` requerirá aprobación posterior y `rel=nofollow`.

Para que un buscador lea noindex no debe impedirse ese rastreo únicamente con `robots.txt`. La política pública preferida permite leer la respuesta noindex y no anuncia sitemaps. Si la configuración WordPress devuelve `Disallow: /`, eso no se considera prueba de desindexación: prevalece comprobar meta/cabecera en URLs accesibles. En privado, autenticación efectiva más noindex; noindex no es acceso restringido. [Directiva noindex oficial de Google](https://developers.google.com/search/docs/crawling-indexing/block-indexing).

El header PHP no alcanza archivos estáticos servidos directamente: añadir `X-Robots-Tag` a imágenes/PDF/otros medios desde la configuración **del vhost demo** cuando se validen las directivas del host; alternativamente no publicar PDFs ni medios sensibles y protegerlos durante preparación. No aplicar una regla en la raíz de `ramita.dev` ni robots a toda la cuenta.

Antes de cada actualización: backup de la demo, ensayo local limpio con el módulo, QA enfocada de rutas y carrito, actualización por sitio y rollback disponible. Revisar semanalmente avisos de seguridad y estado de WordPress/Woo/plugins; priorizar parches explotables según severidad y disponibilidad del responsable. Monitorizar disponibilidad, 5xx/latencia, saturaciones, disco/inodos, sesiones y Action Scheduler; retirar una demo abandonada. El correo bloqueado implica que no se puede confiar en emails WordPress para alertas o recuperar administradores: gestionar acceso mediante hPanel/SSH seguro.

Hostinger publica backups semanales para web/cloud; confirmar frecuencia/retención legacy en el panel. Los diarios pueden ser un extra en Premium, así que D1 no los compra ni los presupone. El contenido reproducible admite objetivo inicial propuesto RPO de última edición aprobada y RTO de un día hábil, sujeto a restauración medida. Guardar código/fixtures y backup cifrado de configuración fuera de la cuenta; no usar Git público para credenciales ni copias DB. [Descarga de backups](https://www.hostinger.com/support/5981435-how-to-download-backups-at-hostinger/), [diarios opcionales](https://www.hostinger.com/support/1665153-how-to-activate-daily-backups-in-hostinger/).

## Costes y decisiones pendientes

| Concepto | Estimación D1 | Pendiente |
| --- | --- | --- |
| Abono adicional por dos demos dentro del Premium | **ARS 0 esperado**, si los dos sitios/cupos y funciones están incluidos y hay recursos | Confirmar legacy; no implica capacidad garantizada |
| Subdominios de dominio ya propio | Sin compra de dominio adicional prevista | Aprobar nombres y verificar DNS/gestión; no crear ahora |
| SSL por demo | Incluido según documentación pública | Confirmar emisión/renovación en la cuenta |
| Suscripción actual | ARS 130.788 mostrado; facturación 15/08/2029 | Ciclo, impuestos y futuro precio; no dividir entre sitios |
| Correo / pagos / nuevos plugins de pago | ARS 0 de servicios nuevos: no se necesitan para este alcance | Verificar que ningún servicio quede habilitado |
| Backups externos / restauración | Puede aprovechar almacenamiento ya disponible y cifrado | Medio, tamaño, coste incremental y responsable por acordar; no contratar |
| Trabajo de instalación, QA y mantenimiento | Tiempo de operación de dos WordPress | Honorarios/horas no cotizados; definir responsable y frecuencia |
| Alternativa de hosting independiente | Sin compra; importe pendiente de cotización específica | Sólo si la evidencia descarta Premium para esta carga |

Antes de desplegar, el titular deberá aprobar: modalidad pública y alcance; nombres; creación de dos webs/DB/usuarios/SSL; cambios DNS estrictamente de esos hosts si fueran necesarios; riesgo compartido y margen de capacidad; administrador y acceso; backup/mantenimiento; contenido sanitizado y QA; ventana de admisión gradual y criterio de retirada. No se solicita aprobación de algo remoto en D1 ni se ejecuta el runbook.

La preparación se evalúa con las evidencias locales D1 y la revisión del módulo. La ausencia de métricas privadas de hPanel limita la **decisión de despliegue**, no debe maquillarse como validación remota. Los resultados, commit y estado final se registran en [validación D1](demo-commercial-validation.md); este plan no convierte un check pendiente en PASS. El procedimiento remoto futuro y su GO/NO-GO están en [el runbook D1](demo-commercial-deployment-runbook.md).

## Fuentes y vigencia

Investigación pública oficial consultada el **24/10/2026**. Las páginas se actualizan; sus referencias describen procedimientos generales, no el contrato legacy ni una inspección autenticada. Las decisiones de margen, canary, roles y recuperación anteriores son propuestas del proyecto. Las cifras particulares de la cuenta son las aportadas por el titular. H10/H11 siguen como antecedente local: [portabilidad](h10-rebranding-portability.md), [hosting H11](h11-hosting-decision.md), [runbook H11](h11-deployment-runbook.md).

**PUBLIC DEPLOYMENT: NOT PERFORMED.**
