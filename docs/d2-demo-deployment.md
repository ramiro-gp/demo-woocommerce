# D2 — Publicación de TRAMA y ORIGEN CASA

Fecha: **25/10/2026, Argentina**. Este documento prepara una instalación futura: **no se creó ni modificó ningún recurso de Hostinger y no se abrió acceso público**. La parte local está verificada. La comprobación interna de la cuenta continúa pendiente porque no hay herramientas Hostinger conectadas en esta sesión.

**D2-A PREPARATION: NOT READY** para cierre íntegro: faltan las lecturas de cuenta, conflictos internos, versiones/aislamiento y consumo real del plan. No son fallos de la carga CSV. **REMOTE DEPLOYMENT: NOT AUTHORIZED**. Cada demo está en **NO-GO remoto** hasta completar sus condiciones; ni este documento ni el push de la rama autorizan D2-B o D2-C.

## Git y alcance preservado

Se hizo `git fetch origin` y se comprobó la ascendencia real:

```text
main / origin/main                         9b5b504577af23a24def7cc502903724ff1917db
  └─ codex/d1-commercial-demos-preparation  9a71bca
       └─ codex/d1-1-seller-experience-audit 17202a3
            └─ codex/d2-commercial-demos
```

D1.1 ya contenía D1, por lo que crear D2 desde `17202a3` es la integración explícita correcta: conserva los dos commits una sola vez, sin cherry-pick, squash ni merge redundante. Las ramas remotas coincidían con las locales al consultar. `main` y el objeto de la etiqueta anotada `template-v1.0.0-rc1` permanecen intactos; la etiqueta resuelve a `9b5b504`. No se hace merge a main ni force push. La documentación/evidencia D1.1 sigue en su commit y no se reemplaza con la de D2.

El inventario real al comenzar encontró D1 `8190/8191` y vendedor `8192/8193` encendidos. Las instalaciones originales `8080/8082` estaban detenidas. Se creó únicamente el laboratorio D2 `8196/8197`, sin reutilizar sus bases. [Carga masiva y resultados](d2-bulk-import.md).

## Infraestructura elegida y comparación

Se mantiene **Hostinger Premium Web Hosting legacy**, informado por el titular: 25 sitios permitidos, 8 existentes, 25 GB, 2 GB de RAM compartida, 1 núcleo, 40 PHP workers, 80 procesos y 400.000 inodos; servidor en Brasil. Son datos del titular, **no una inspección nueva de hPanel**. Dos sitios más representarían 10/25 espacios usados si el inventario sigue igual. Eso no prueba que sobren recursos de ejecución o disco.

| Opción | Decisión y fundamento |
| --- | --- |
| Dos WordPress independientes en el Premium existente | Propuesta preferida para las demos, condicionada a recursos e aislamiento. Sin compra ni cambio de plan |
| Una sola instalación con dos marcas / multisite | No seleccionada: complica separación de configuración, rollback y presentación |
| Clonar tiendas históricas | No seleccionada: riesgo de cuentas, pedidos, solicitudes, secretos y referencias cruzadas |
| Docker en Hostinger | No requerido ni previsto: PHP, base de datos y archivos nativos |
| VPS, upgrade, planes nuevos o servicios pagos | Fuera de alcance; el titular indicó conservar el plan sin extras |

Coste incremental propuesto de hosting/licencias: **0**, condicionado a las cuotas y servicios ya incluidos. WooCommerce y los seis componentes propios no necesitan un plugin pago de importación. Los subdominios no implican comprar dos dominios. No se cotiza ni contrata SMTP, CDN, backups adicionales o cambio de plan. El coste de renovación del plan sigue siendo el de la cuenta; el importe histórico ARS 130.788 no se anualiza ni se garantiza sin conocer ciclo/impuestos. [Evaluación legacy H11](h11-hosting-decision.md).

## Destinos y consultas de lectura

| Instalación | Destino propuesto | Estado |
| --- | --- | --- |
| TRAMA | `trama-demo.ramita.dev` | Sin registros públicos A/AAAA/CNAME observados; asignación interna pendiente |
| ORIGEN CASA | `origen-demo.ramita.dev` | Sin registros públicos A/AAAA/CNAME observados; asignación interna pendiente |

Se consultaron `1.1.1.1` y los servidores públicos `aster.dns-parking.com` y `helios.dns-parking.com`, que aparecen como NS de `ramita.dev`. Los dos nombres y un nombre aleatorio de control devolvieron nombre inexistente para A/AAAA/CNAME. La consulta CAA al dominio padre mediante el resolver de Node devolvió `ENODATA`. [Respuestas fechadas](evidence/d2/dns.json).

Esto no demuestra disponibilidad en la cuenta ni descarta todas las delegaciones, reservas, alias o reglas de comodín. Antes de crear se deben listar sitios, instalaciones WordPress, subdominios, dominios aparcados y zona DNS completa de `ramita.dev`, revisar ambos nombres, comodines y CAA aplicables, y confirmar el propietario/cuenta/plan exactos. No se cambia el dominio raíz, sus NS, correo, MX/TXT ni sitios de clientes.

El plugin Hostinger aporta instrucciones, pero al comenzar no había servidores MCP registrados ni herramientas de lectura de Hostinger disponibles. Se registraron **sus nueve servidores locales**, preservando los bloques existentes, con Node 24.12.0 y el USER_AGENT indicado por el proveedor. La copia previa de configuración queda en `.local/d2/private/codex-config-before-hostinger.toml`, ignorada por Git. **Hace falta reiniciar Codex** para cargar las herramientas y completar el acceso de lectura; no se reinició la aplicación ni se inició OAuth todavía. No se pidieron tokens ni se improvisó una API de cuenta. Este cambio de conexión local no modifica Hostinger. Las consultas públicas DNS y la documentación oficial no sustituyen esa conexión. [Estado sin credenciales](evidence/d2/hostinger-connection.json).

## Requisitos e inventario instalable

| Componente | Versión local comprobada / procedencia |
| --- | --- |
| WordPress | 7.1.3, instalación nueva; confirmar disponibilidad y seguridad antes de ejecutar remoto |
| WooCommerce | 11.2.1, distribución oficial |
| PHP | Línea 8.4; local 8.4.27. Verificar PHP web y CLI en el sitio nuevo |
| DB | Local MariaDB 10.11.19; remoto MySQL 8.0+ o MariaDB 10.6+ recomendado |
| Commerce Base | 1.9.1, paquete limpio H11 |
| Commerce Core | 1.1.2, módulos de la demo según configuración aprobada |
| Commerce Order Integrity | 1.0.0 |
| Commerce Communications | 1.0.1; sin transporte externo |
| Commerce Account Navigation | 1.0.0 |
| Commerce Lookbook | 1.0.0 |
| Commerce Public Demo Guard | 0.1.0, `deployment/demo-public/commerce-demo-public.php`, MU plugin exclusivo de demos, separado del paquete comercial |

Los ZIP limpios H11 y sus checksums están en `.local/h11-release/1.0.0/manifest.json`; no se subieron al hosting. La corrección D2 es configuración de email/fixture, no una versión nueva de Communications ni de esos paquetes. Para cualquier entrega futura, verificar los hashes del manifiesto y que no se incluyan SQL, `.env`, `wp-config.php`, secretos, backups, `.local`, usuarios o históricos. Los assets de marca/editoriales de las demos son fixtures separados del paquete comercial limpio.

La recomendación oficial vigente de WooCommerce es PHP 8.3+ probado hasta 8.4, WordPress 6.9+, MySQL 8.0+/MariaDB 10.6+, HTTPS y límite WordPress de memoria de al menos 256 MB. El encabezado de **nuestro WooCommerce 11.2.1 exige WordPress 7.0**, por lo que se toma el requisito más restrictivo y la línea validada 7.1.3. Verificar extensiones `mysqli`, `curl`, `mbstring`, `fileinfo`, `zip`, XML/DOM e imágenes GD/Imagick para los medios utilizados. No cambiar PHP globalmente en el plan ni afectar a los ocho sitios para satisfacer una demo. [Requisitos oficiales](https://woocommerce.com/document/server-requirements/).

Comprobar en hPanel los valores reales de memoria, upload/post max size, max execution time, espacio temporal, conexiones/tamaño de DB, SSH/WP-CLI, I/O/IOPS y aislamiento de directorios/usuarios. Propuesta inicial de memoria WordPress: 256 MB, sujeta al PHP efectivo del sitio. No multiplicar 40 workers por 256 MB y suponer esa RAM disponible. No se ha validado todavía la compatibilidad técnica de esta cuenta legacy.

## Secuencia exacta propuesta para D2-B, sólo después de autorizar

Ejecutar **primero TRAMA**. Medir y validar antes de crear ORIGEN CASA. Los nombres reales de usuario de hosting, DB, ruta absoluta e IP sólo se completarán a partir de las lecturas de cuenta, nunca por adivinación.

1. **Registrar el estado previo.** Inventario de ocho sitios, registros DNS actuales, versiones y consumo agregado de 7 días; comprobar cuotas, backup/restauración por sitio y acceso. Guardar el registro en privado, sin datos de otros clientes en Git. Si falta margen o aislamiento, NO-GO, sin compras ni modificaciones del plan.
2. **Crear un sitio independiente vacío** en **hPanel → Websites/Sitios web → Add website/Añadir sitio**, dentro del Premium identificado, con el subdominio exacto autorizado y directorio propio. Elegir instalación vacía/custom PHP antes de cargar una demo. No usar clonado, migración de tienda ni sobrescribir `ramita.dev`. Anotar ID del sitio, usuario, directorio y cualquier alias temporal creado. La creación puede asignar DNS/SSL automáticamente: esos efectos deben estar incluidos expresamente en D2-B. Si el panel sólo ofrece un subdirectorio compartido con otra web, detenerse y revisar aislamiento.
3. **Proteger el directorio completo antes de instalar contenido.** Dashboard del sitio nuevo → buscar **Password Protect Directories**, seleccionar su raíz y asignar credenciales distintas de WordPress. Incluir aliases y dominio temporal. Comprobar que `/`, medios y APIs responden **401 sin credenciales**. Durante el aprovisionamiento sólo puede existir la página vacía del proveedor, nunca el catálogo de demostración sin protección. Si el panel no permite garantizar protección antes del contenido, NO-GO.
4. **Base y usuarios propios.** Dashboard del sitio nuevo → **Databases → Management**, crear una DB y un usuario exclusivos, sin permisos sobre otras bases; registrar sus nombres exactos en privado. Preparar un usuario administrativo WordPress distinto por demo con contraseña aleatoria. No importar cuentas D1 ni clientes. Las identidades técnicas reales del titular permanecen privadas; los contactos visibles de las fixtures usan `.test`.
5. **Instalación nueva de WordPress.** **Website → Auto Installer → WordPress**, completar título y administrador; en **Advanced** seleccionar el subdominio/ruta raíz y la DB recién creada. Revisar versión/idioma y confirmar que el panel no propone reutilizar la base de otra web. Si el instalador crea una DB nueva automáticamente, identificarla y comprobar que es exclusiva; no crear una segunda base innecesaria. Verificar `siteurl/home` HTTPS y PHP del sitio, todavía bajo autenticación. No activar indexación ni marketing/asistentes adicionales.
6. **Instalar WooCommerce y componentes.** Plugins → Añadir nuevo → WooCommerce oficial, versión 11.2.1 si sigue siendo apropiada tras revisión; activar y completar sólo lo necesario. No conectar pagos ni servicios externos. Apariencia → Temas → Subir tema, instalar Commerce Base del manifiesto; Plugins → Subir plugin para los cinco módulos. Activar Core, Integrity, Communications, Account Navigation y Lookbook. Verificar HPOS, Cart/Checkout Blocks, traducción es_AR y versiones. El checkout seguirá bloqueado al activar DEMO.
7. **Cargar sólo fixtures públicas revisadas.** Fuentes autorizadas: `demo/catalog/`, los bloques/fixtures de contenido `docker/tools/h4-demo-content.php`, `docker/tools/h5-1-lookbook.php`, y para ORIGEN `demo/origen-casa/{client,content}.json` y sus assets. Nunca usar SQL o un ZIP completo de instalaciones históricas. Las herramientas `scripts/d1.ps1` y `docker/tools/h10-store.php` tienen guardas locales: **no se ejecutan tal cual sobre Hostinger ni se eliminan sus validaciones**. La carga remota se hará con medios nuevos, CSV nativo preparado a partir de esos datos públicos y bloques/ajustes nativos de WordPress. Antes de ejecutar habrá que preparar el CSV de cada catálogo y mapear IDs de páginas/medios/lookbook a los de la nueva instalación; esos IDs no existen aún. Si se prepara un ejecutor de fixtures, se revisa y prueba su guarda específica de staging antes de usarlo. No usar el catálogo sintético de 220 filas D2 como catálogo comercial de las demos.
8. **Identidad y contenido de cada marca.** Configurar título/logo/icono y estilos desde sus fixtures; categorías, atributos, productos variables, Home, navegación y Lookbook con los IDs nuevos. Para ORIGEN aplicar su preset de estilos y colores; no copiar opciones de TRAMA. Revisar las páginas institucionales públicas D1 de privacidad, envíos, devoluciones, contacto y arrepentimiento, con aviso de demostración y sin formularios de datos personales. Aplicar el asunto neutral nativo de Completado como fixture, aunque los correos de la demo queden deshabilitados.
9. **Configurar DEMO y activarlo como MU plugin.** Subir exclusivamente el guard aprobado a `wp-content/mu-plugins/commerce-demo-public.php`. Durante la preparación, cualquier desactivación temporal sólo puede hacerse detrás de Basic Auth ya comprobada. En `wp-config.php` del sitio nuevo establecer `WP_ENVIRONMENT_TYPE='staging'`, `COMMERCE_DEMO_ALLOWED_HOST` igual a su hostname exacto y `COMMERCE_DEMO_PUBLIC=true`. Marcar la instalación limpia con la opción `commerce_demo_public_installation=public-demo-v1` después de verificar ausencia de cuentas visitantes, pedidos/HPOS/CPT y trámites legales. Configurar las opciones/páginas de `deployment/demo-public/setup.php`; ese setup admite staging pero exige la marca y datos vacíos. Revisar previamente su lista de cambios. Si falta una marca/hostname válido, el guard devuelve 503. No reutilizar `COMMERCE_DEMO_ALLOWED_HOST=localhost`.
10. **SSL y DNS.** Verificar propiedad/ruta, sólo los registros de los dos hijos autorizados y los CAA aplicables. Mantener A/AAAA de raíz, NS, MX y TXT existentes. Si hace falta apuntar un hijo, usar la IP obtenida del sitio, jamás una IP inferida. Dashboard → SSL: comprobar emisión válida para el hostname y redirección HTTPS; no emitir ni cambiar certificados de otras webs. `.dev` requiere HTTPS práctico desde el primer acceso del navegador. Si ACME necesita una excepción, limitarla a `/.well-known/acme-challenge/`, sin abrir catálogo, API ni otras rutas. Confirmar HTTPS con credenciales; no quitar Basic Auth.
11. **Noindex y caché.** Ajustes → Lectura → disuadir buscadores, más `blog_public=0`, meta robots y `X-Robots-Tag: noindex, nofollow, noarchive` también en estáticos/errores/401. Adaptar `deployment/demo-lab/headers.conf` a `.htaccess` del sitio; no tocar configuración global de Apache/LiteSpeed. El guard inhabilita sitemap/structured data. Su `robots.txt` permite rastreo para ver noindex; `Disallow` por sí solo no es garantía. Basic Auth es el control de acceso. Mantener caché de página desactivada en la validación privada, sin HTML público cacheado previo al guard. Después, evaluar sólo Home/catálogo y excluir carrito, cuenta, checkout, API, sesiones y respuestas de bloqueo; purgar exclusivamente la demo afectada y repetir QA. No habilitar object cache si el plan no lo ofrece y no se ha comprobado separación de claves.
12. **QA remoto privado y medición.** Ejecutar el checklist siguiente bajo autenticación, sin pruebas de estrés, pedidos reales, SMTP externo ni MP. Comprobar también que sin credenciales sigue 401. Medir el delta agregado después de una sola demo; por la demora de hPanel, revisar al menos dos ventanas de actualización antes de considerar la segunda. Registrar el resultado y pedir nuevas decisiones si hay impacto.
13. **Mantener acceso cerrado.** El fin de D2-B es una demo privada validada, con rollback disponible. La apertura D2-C requiere una segunda autorización posterior específica por hostname; no está incluida en D2-B.

Las rutas de panel se basan en documentación oficial vigente; puede variar su idioma/presentación. [Crear sitio](https://www.hostinger.com/support/1583214-how-to-add-a-website-in-hostinger/), [instalar WordPress en subdominio y elegir DB](https://www.hostinger.com/support/3220304-how-to-install-wordpress-using-auto-installer-in-hostinger/), [proteger directorios](https://www.hostinger.com/support/1583470-how-to-password-protect-a-website-in-hostinger/), [SSL y apuntado](https://www.hostinger.com/support/5613445-how-to-fix-a-failed-lifetime-ssl-installation-in-hostinger/). Consultadas 25/10/2026. No se ejecutó ninguno de esos pasos remotos.

## Seguridad DEMO, privacidad y corrección de correo

No se rehízo el guard D1. Se volvieron a ejecutar **269 comprobaciones de navegador/HTTP y 80 de entrypoints**, más auditoría CLI de ambas instalaciones. [Informe navegador](evidence/d2/demo-qa/public-trama-origen.json), [entrypoints](evidence/d2/demo-qa/entrypoints.json), [TRAMA](evidence/d2/demo-trama.json), [ORIGEN](evidence/d2/demo-origen.json).

Se confirmaron catálogo y carrito con variantes, altas/cambios/borrado del carrito, y bloqueos del lado servidor para checkout clásico/Blocks, Store API checkout y order endpoints, lotes mixtos/anidados, enlaces order-pay/order-received, BACS y todos los gateways, registro/reset/login, XML-RPC y entradas codificadas, emails y formularios legales. Un hostname incorrecto falla cerrado. Las dos DB D1 siguen sin pedidos HPOS/CPT, sin solicitudes/eventos legales y con un único administrador sintético. Mercado Pago **no está instalado ni activo** y el navegador no pidió sus recursos. No se traslada el catálogo de prueba D2 ni los compradores ficticios del laboratorio vendedor.

ORIGEN dejó el asunto traducido que afirmaba “en camino” y usa ahora la opción nativa `woocommerce_customer_completed_order_settings.subject`: **`[{site_title}] Finalizamos tu pedido #{order_number}`**, encabezado **Pedido completado**. El preparador de fixtures H10 admite un diccionario `emails` por cliente, combinado con sus opciones nativas; las instalaciones existentes conservan sus controles de divergencia y no se resembran. No se hardcodeó una marca en Communications. El archivo de texto aprobado de TRAMA no cambió. Para comprobarlo, su laboratorio vendedor —copia de D1 público sin aquel preset— recibió únicamente el asunto/encabezado ya aprobados. Se verificaron ambas capturas en Mailpit, con receptores `.test`, a 390 y 900 px; no hubo correo externo. [Evidencia](evidence/d2/email.json), [configuración nativa](evidence/d2/email-native-setting.png), [correo ORIGEN](evidence/d2/email-origen-390.png).

El guard restringe aplicación, pero el proveedor puede registrar IP, user-agent y accesos técnicos. Antes de abrir se debe comprobar retención, minimización y acceso a logs, analítica ausente, cookies de sesión/preferencia y texto de privacidad adecuado a la demo. No prometer ausencia absoluta de datos técnicos del hosting. Los backups del plan informados en EE.UU. siguen sujetos a revisión; las fixtures no llevan datos privados de clientes.

## Espacio, recursos y límites conservadores

Medición local de las demos D1 existentes, usando `du -sk` y tamaño DB nativo:

| Demo | Archivos en disco (KiB) | Uploads (KiB, incluidos en archivos) | DB (bytes) | Archivos regulares |
| --- | --- | --- | --- | --- |
| TRAMA | 218.680 | 15.412 | 3.964.928 | 9.951 |
| ORIGEN CASA | 204.816 | 1.532 | 3.571.712 | 9.756 |

Los archivos suman aproximadamente **413,6 MiB** y las DB **7,2 MiB**. Contando también directorios se observaron 11.371 rutas en TRAMA y 11.177 en ORIGEN: 22.548 en total, antes de crecimiento, cachés, temporales y backups. No es el tamaño de la instalación remota ni se resta de 25 GB sin conocer el consumo actual de los ocho sitios. [Inventario y checksums de los seis paquetes](evidence/d2/inventory.json).

Presupuesto de admisión propuesto: **1 GB por demo** para archivos/DB/crecimiento/copia de recuperación y **0,5 GB temporal adicional por demo** durante instalación/restauración: reservar **3 GB libres conjuntos**, además del margen operativo que necesiten las ocho webs. Reconfirmar según descompresión y política real de backups. No descargar backups repetidos dentro de `public_html` ni conservar SQL accesible. Son reservas de planificación, no cuotas asignadas ni capacidad garantizada.

La documentación de Hostinger indica que en Web/Cloud los gráficos son **agregados del plan**, se actualizan cada 10–15 minutos y usan GMT+0. Registrar baseline y picos CPU, RAM, workers, procesos, throughput e IOPS, además de espacio/inodos y latencia/5xx de los sitios existentes. No atribuir la diferencia a una demo sin controlar tareas periódicas del plan. [Medición oficial](https://www.hostinger.com/support/2436138-how-to-check-resource-usage-in-hostinger/).

Política inicial propuesta, que deberá acordarse con los valores reales: un instalador/importador por vez; primer ensayo de CSV hasta 25 filas con una sola persona y fuera del pico existente; QA manual de baja frecuencia, sin estrés ni cifra garantizada de visitas. No hacer dos importaciones simultáneas. No iniciar pruebas remotas hasta su autorización separada.

**Detener la segunda instalación o revertir la demo afectada** si se alcanza un límite del plan, aparecen 5xx/timeouts nuevos en una web existente, se detectan lecturas cruzadas de archivos/DB, se pierde Basic Auth/HTTPS, se puede crear un pedido o aparece cualquier envío externo/MP. Umbral preventivo propuesto: CPU/RAM/I/O/workers/procesos sostenidos sobre el 80% en dos ventanas disponibles, poco margen frente al baseline, o latencia de control de una web existente más del doble de su baseline en tres observaciones de baja frecuencia. Es una política operativa conservadora, no una garantía del proveedor. No hay que esperar al segundo intervalo si una web falla. Para disco/inodos, detener antes de consumir la reserva de restauración o caer bajo 20% de espacio libre agregado. Nunca detener todos los procesos ni purgar cachés del plan para probar una demo. [Límites y consecuencias](https://www.hostinger.com/support/1583532-what-to-do-if-your-hosting-plan-limits-are-reached-in-hostinger/).

## Rollback independiente

Antes de subir, conservar fuera de la raíz pública un inventario por demo: ID de sitio, ruta, DB/usuario, versión/hash de paquetes, registros DNS previos sólo de su hijo, Basic Auth, SSL, opciones y copia limpia de su DB/archivos. Probar recuperación de cada demo privada sin restaurar el plan completo ni una web cliente. Las bases y directorios deben ser distintos para que esto sea posible.

Ante un fallo, **mantener/cerrar acceso de esa demo**, interrumpir sólo su importación/tarea y devolver sus archivos+DB al snapshot propio verificado. Si no se puede recuperar, dejar esa demo bloqueada/503 hasta revisión, manteniendo la otra y las ocho webs operativas. No desactivar el guard para “hacerla funcionar”. Eliminar sitios/DB o revertir un registro DNS es otra acción remota con aprobación explícita; no se incluye borrado automático. Retirar DNS por sí solo no cierra accesos cacheados o aliases: el bloqueo del vhost sigue siendo necesario. No usar restauración global ni `down -v` como rollback remoto.

## GO/NO-GO por instalación

| Condición y evidencia exigida | TRAMA | ORIGEN CASA |
| --- | --- | --- |
| Fuentes públicas y guard local probado | PASS local | PASS local |
| Lectura interna: nombre libre y cuenta/plan/ruta/DB identificados | PENDIENTE | PENDIENTE |
| Versiones/extensiones/SSH compatibles y aislamiento efectivo | PENDIENTE | PENDIENTE |
| Recursos agregados de ocho webs y reserva de disco/inodos suficiente | PENDIENTE | PENDIENTE |
| Autorización D2-B para crear recursos concretos | **NO** | **NO** |
| WordPress/DB/admin independientes, sin históricos | NO CREADO | NO CREADO |
| Basic Auth en raíz/medios/API/aliases, 401 anónimo | SIN QA REMOTO | SIN QA REMOTO |
| HTTPS válido y noindex en HTML/estáticos/errores | SIN QA REMOTO | SIN QA REMOTO |
| Catálogo y carrito visual/responsive y variaciones correctos | PASS local / remoto pendiente | PASS local / remoto pendiente |
| Checkout/Store API/directos/gateways/registro/mail/legal bloqueados | PASS local / remoto pendiente | PASS local / remoto pendiente |
| MP ausente, sin recursos externos ni información privada | PASS local / remoto pendiente | PASS local / remoto pendiente |
| Caché sin bypass de guard/auth y privacidad/logs revisados | PENDIENTE REMOTO | PENDIENTE REMOTO |
| Medición después de instalar y rollback individual comprobado | PENDIENTE | PENDIENTE |
| Segunda autorización D2-C para abrir ese hostname | **NO** | **NO** |
| Decisión actual | **NO-GO REMOTO** | **NO-GO REMOTO** |

## Datos pendientes y próxima aprobación

La siguiente acción útil es completar **lecturas ya autorizadas**, conectando Hostinger: sitios/subdominios/DNS, cuotas y uso, PHP/DB/extensiones, aislamiento, backups, rutas, SSH y protección de directorios. No requiere permiso para crear nada ni se interpreta como despliegue.

Después de esas lecturas, la primera aprobación D2-B debe nombrar exactamente el **sitio `trama-demo.ramita.dev` en el Premium verificado**, su directorio independiente, DB/usuario exclusivos y administrador WordPress independiente; instalación nueva de WordPress/WooCommerce y los componentes listados, carga de fixtures públicas bajo Basic Auth, **únicamente** el DNS del hijo si es necesario, SSL para ese hostname y cambios de configuración restringidos a esa demo. Enumerar los efectos automáticos del panel, sin tocar raíz/NS/MX, otros sitios ni facturación. La segunda demo tendrá su propio alcance explícito y dependerá del resultado/recursos de la primera. Los identificadores faltantes se completarán antes de presentar la solicitud ejecutable; **no se pide aprobar recursos desconocidos ahora**.

La autorización D2-C será posterior al QA remoto y exclusiva para retirar Basic Auth y abrir cada demo. No se crea un checkout de prueba que solicite datos personales. Cualquier prueba de carga remota necesita aprobación específica; no está implícita en publicar ni se ejecutará sobre las ocho webs.

**BULK PRODUCT IMPORT: READY**
**BULK STOCK UPDATE: READY**
**D2-A PREPARATION: NOT READY** — pendiente de lecturas de la cuenta Hostinger.
**REMOTE DEPLOYMENT: NOT AUTHORIZED**
