# H9 — Matriz de preparación

Actualizado el 22/10/2026, Argentina. **H9 READY como hito técnico local**, sujeto a la evidencia de cierre enlazada. **Producción NOT READY.** No autoriza cobros, publicación ni envío de información real al proveedor. H10 y H11 no se iniciaron.

| Área | Estado | Evidencia / condición pendiente |
| --- | --- | --- |
| H8.2 consolidado | Listo técnicamente | `2b998dc` por fast-forward, main/origin/main iguales; H9 permanece en su rama. |
| Código propio | Listo técnicamente | [H9.1](h9-1-security-hardening.md): privacidad de errores SQL, autorización efectiva de asociación, revisión de cinco plugins y theme. Sin crítico propio comprobado abierto. |
| Runtime PHP | Listo técnicamente local / pendiente del hosting | 8.4.27 oficial probado y aplicado localmente; exigir parche de CVE-2026-109618/109619 en el SAPI web real de hosting, no sólo CLI. |
| Pedidos/cuentas/HPOS | Listo técnicamente | Propiedad A/B, roles, nonce/capacidad, revocación/vencimiento y BACS/stock/envíos/cupón en clon; históricos principal preservados. |
| Legales y privacidad del comercio | **Pendiente del comercio / bloqueo productivo** | Identidad, CUIT/Data Fiscal, canales, condiciones reales, conservación/exportación y revisión jurídica. [Guía H8](h8-client-legal-setup.md). |
| BACS | Listo con configuración / pendiente del comercio | Sigue DEMO sin datos que permitan transferir. Requiere cuenta bancaria auténtica, conciliación y correo antes de vender. No es pago automático. |
| MP autenticidad/replay/temporalidad | **Pendiente del proveedor / bloqueo productivo** | Sin solución oficial verificable hallada en 8.9.6. [Matriz de riesgos H9.1](h9-1-security-hardening.md#mercado-pago-diagnóstico-productivo). Pro permanece disabled. |
| MP importe/moneda/asociación | **Pendiente del proveedor / bloqueo productivo** | Validación independiente y conciliación no garantizadas por los manejadores revisados. Sin pagos reales ni un replay externo nuevo. |
| MP compatibilidad | Pendiente del proveedor | Tested up to WC 11.0.1; WC instalado 11.2.1. QA local no amplía soporte oficial. |
| MP scripts/cookies/telemetría | **Pendiente del proveedor y comercio / bloqueo productivo** | Plugin activo carga recursos aun sin ofrecer Pro. Desactivación completa elimina cargas en clon; falta decisión operativa y evaluación de tratamientos antes de publicar. |
| Caché/CDN | Listo con configuración / pendiente del hosting | [Reglas y prueba de origen](h9-2-performance-accessibility-seo.md#estrategia-de-caché-woocommerce). No se instaló cache productivo; validar exclusiones y dos clientes en hosting real. |
| Rendimiento | Evaluado y optimizado localmente | Comparación por página, pesos/requests y límites. LCP mobile de fichas sigue alto; no hay datos de campo/p75/INP. No certifica Core Web Vitals productivos. |
| Accesibilidad | Evaluada, sin hallazgo automático grave restante | Axe 22 casos y 89 checks de interacción/reflow. Pendiente evaluación con lector de pantalla, zoom nativo y juicio humano; no certificación AA. |
| SEO | Listo con configuración / pendiente del comercio | Metadatos de datos reales, canonical y JSON-LD nativos. DEMO noindex/sitemap privado. Completar categorías/imagen social; configurar visibilidad sólo al publicar contenido real autorizado. |
| Correo | **Pendiente del comercio/hosting / bloqueo productivo** | Mailpit sólo DEV; SMTP comercial, dominio remitente, SPF/DKIM/DMARC, entrega y monitoreo según [H7](h7-email-production.md). |
| HTTPS, dominio y proxy | **Pendiente del hosting / bloqueo productivo** | Certificado/renovación, URLs definitivas, TLS origin y cookies Secure. Sin DNS ni despliegue en H9. |
| Backups/actualizaciones/logs | Listo con configuración / pendiente del hosting | Backup DEV comprobado y datos preservados; faltan retención offsite, restauración productiva, rotación y responsables de parches. No trasladar logs/secrets/fixtures a producción. |
| H10 | Pendiente, no iniciado | Portabilidad/rebranding, datos editables por cliente y URLs/contenidos propios sobre la base auditada. |
| H11 | Pendiente, no iniciado | Verificar plan/servicios reales, configurar hosting/seguridad/cache/correo/backup/cron/TLS y preparar QA de despliegue autorizado. |

## Requisitos técnicos para verificar en Hostinger en H11

No se consultó ni cambió la cuenta/plan. La documentación pública orienta qué verificar; no acredita cuotas/capacidades contratadas. Docker es herramienta DEV, **no requisito de instalar Docker en hosting compartido**. Sin paquetes de servidor ni DNS modificados.

| Requisito | Base observada / comprobación futura |
| --- | --- |
| PHP | Proyecto probado con 8.4.27. WC 11.2.1 declara mínimo PHP 7.4 y WP 7.0, pero un mínimo de API no implica una versión PHP segura. Elegir serie soportada y parche vigente; no migrar a 7.4. Comparar extensiones y SAPI web/CLI reales. |
| Extensiones | mysqli/mysqlnd, curl, mbstring, intl, openssl, dom/xml, zip, fileinfo, exif, GD o Imagick con WebP; OPcache recomendado. El entorno local también tiene bcmath, sodium y zlib. Confirmar las efectivamente requeridas por plugins/medios, no habilitar todas sin motivo. |
| Límites PHP | Local: memory_limit 512M, upload_max_filesize 32M, post_max_size 40M, execution_time 120s. No son mínimos garantizados ni cuotas asumidas. Dimensionar importación/imágenes/checkout dentro de CPU, RAM, workers, I/O e inodes del plan real; post debe superar upload. |
| DB | MariaDB 10.11 LTS local, InnoDB y utf8mb4, transacciones/índices y soporte HPOS. [Recomendaciones WC](https://woocommerce.com/document/server-requirements/) como referencia; verificar versión real, límites/conexiones, credenciales y backups. No exponer DB. |
| Servidor/reescrituras | Local Apache con compresión gzip verificada por transferencias y headers. Hostinger puede usar LiteSpeed; revisar traducción de reglas sensibles, uploads no ejecutables, headers y permalinks. No copiar ciegamente directivas LoadModule de Apache al hosting. |
| HTTPS | HTTPS estable en frontend/admin/APIs/callbacks, URL base y proxy fiables, cookies Secure. HSTS sólo después de validar dominio/subdominios/certificado. Configuración no deducida de localhost HTTP. |
| Cron/colas | Elegir WP-Cron soportado o scheduler del hosting con frecuencia adecuada, evitar doble disparador, comprobar Action Scheduler, correo y limpieza nativos. Capacidad/cuota/frecuencia del plan pendientes. |
| Archivos | wp-content/uploads persistente, derivados responsive, respaldos fuera de webroot, permisos según UID/grupo, código protegido y secretos fuera de Git. No transferir `.local`, fixtures, dumps, Mailpit ni herramientas QA. |
| Caché/compresión | Validar gzip/Brotli, OPcache y página/CDN con exclusiones WC y legal antes de activarlos. No asumir Redis, LiteSpeed Cache, CDN o ESI incluidos. Medir otra vez con tráfico/hosting representativos. |
| Correo | Sustituir captura DEV por transporte autorizado; límites de envíos, DNS de autenticación y entrega requieren comercio/proveedor. Revisar que Local Mail Capture no esté activo en entorno incorrecto. |

Fuentes primarias: [extensiones/opciones PHP en hPanel](https://www.hostinger.com/support/4667515-how-to-manage-php-extensions-and-options-in-hostinger/), [opciones soportadas](https://www.hostinger.com/support/which-php-extensions-and-configuration-options-are-supported-at-hostinger/), [cron por plan](https://www.hostinger.com/support/1583765-how-many-cron-jobs-can-you-set-up-in-hostinger/) y [cache manager](https://www.hostinger.com/support/6215624-how-to-use-cache-manager-at-hostinger/). Se revisan al preparar H11, sin dar por contratado un producto.

## Evidencia y criterio de cierre

- [Inventario/seguridad/riesgos MP](h9-1-security-hardening.md).
- [Mediciones, accesibilidad, SEO y caché](h9-2-performance-accessibility-seo.md).
- [Preservación comercial y de originales](evidence/h9-2/preservation.json).
- [Regresiones de navegador](evidence/h9-2/browser.json), [checkout](evidence/h9-1/checkout.json), [persistencia](evidence/h9-1/verify-checkout.json) y [aislamiento de caché](evidence/h9-2/cache.json).

El bloqueo de MP depende del proveedor/decisión comercial y está separado del hito técnico. Las limitaciones de laboratorio y validación humana de accesibilidad permanecen declaradas. La consolidación de H9 en main requiere revisión posterior del usuario; H9 no realiza esa consolidación.

Inspección principal: [Home](http://localhost:8080/), [catálogo](http://localhost:8080/?post_type=product), [producto variable](http://localhost:8080/?product=remera-base), [arrepentimiento](http://localhost:8080/?page_id=514) y [robots nativo](http://localhost:8080/robots.txt). Para carrito/checkout, agregar un producto desde la UI; no ejecutar seeds para navegar. Clon QA 8199 detenido al cierre, con sus datos privados conservados.
