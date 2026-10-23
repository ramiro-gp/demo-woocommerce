# TRAMA / Commerce Base — Estado del proyecto

Referencia de cierre: **23/10/2026, Argentina**. Repositorio: [ramiro-gp/demo-woocommerce](https://github.com/ramiro-gp/demo-woocommerce).

**TEMPLATE TECHNICAL READY.**

**PRODUCTION NOT READY.**

## Roadmap y referencia Git

**H0–H11 están completados y aprobados técnicamente.** El cierre consolida H11 en `main` mediante fast-forward, conservando sus commits y la actualización de Premium legacy `baae9e4`. No hay squash, reescritura de historia ni inicio de H12.

La etiqueta anotada de referencia es [template-v1.0.0-rc1](https://github.com/ramiro-gp/demo-woocommerce/tree/template-v1.0.0-rc1). **RC1 identifica una versión candidata de la base técnica; no certifica una tienda ni WordPress/WooCommerce para producción.** Es independiente de la distribución H11 1.0.0 y de las versiones propias del theme/plugins. No se crea una GitHub Release ni se adjuntan ZIP al publicar la etiqueta.

| Hitos | Resultado técnico y referencia |
| --- | --- |
| H0–H2 | Decisiones, entorno local reproducible, instalación y compatibilidad HPOS/Blocks; pruebas de pagos ficticios con alcance y bloqueos propios. [H0](h0-decisions.md), [H1](h1-validation.md), [H2](h2-validation.md) |
| H3–H5.1 | Sistema visual, Home/institucionales, catálogo, productos/variantes/swatches y Lookbook reutilizable. [H3](h3-validation.md), [H4](h4-validation.md), [H5](h5-catalog.md), [H5.1](h5-1-lookbook.md) |
| H6–H7 | Carrito, Mini-Cart, Checkout Blocks, logística, pedidos/BACS y comunicaciones; MP sigue bloqueado. [Carrito](h6-1-cart-checkout.md), [logística](h6-2-shipping-logistics.md), [pagos](h7-1-payments.md), [comunicaciones](h7-2-communications-operations.md) |
| H8–H9 | Commerce Core, direcciones, circuito de arrepentimiento y gestión legal; seguridad, rendimiento, accesibilidad y SEO con límites documentados. [Core](h8-1-commerce-core.md), [legal](h8-2-final-audit.md), [seguridad](h9-1-security-hardening.md), [rendimiento/SEO](h9-2-performance-accessibility-seo.md) |
| H10 | Portabilidad, rebranding, segunda tienda y repetición con datos independientes. [Informe](h10-rebranding-portability.md), [auditoría de dependencias](h10-dependency-audit.md) |
| H11 | Distribución limpia, instalación/repetición, backup/restauración, reemplazo de código sin perder pedidos recientes, decisión de hosting y operación documentadas. [Clasificación y evidencia](h11-production-readiness.md) |

Los informes de cada hito conservan su fecha, versión y contexto histórico. Los estados intermedios que describen no sustituyen este resumen ni convierten un PASS local en validación remota.

## Arquitectura y componentes

WordPress mantiene usuarios, contenido, medios y configuración. WooCommerce mantiene catálogo, stock y pedidos mediante **HPOS**, con **Cart/Checkout Blocks y Store API** nativos. El theme presenta la tienda; los módulos funcionales se distribuyen como extensiones independientes. No hay un multisite obligatorio ni dependencia de la identidad TRAMA para operar la base reutilizable.

| Componente | Versión del código y ZIP de referencia | Responsabilidad |
| --- | --- | --- |
| Commerce Base | **1.9.1** | Theme compartido, presentación, componentes y estilos configurables por comercio |
| Commerce Core | **1.1.2** | Direcciones, hardening y módulo legal independiente de pedidos/pagos |
| Commerce Order Integrity | 1.0.0 | Guardas de integridad de pedido/sesión; su presencia no aprueba MP |
| Commerce Communications | 1.0.1 | Presentación de comunicaciones nativas, sin transporte comercial incorporado |
| Commerce Account Navigation | 1.0.0 | Configuración de navegación sobre la cuenta nativa |
| Commerce Lookbook | 1.0.0 | Bloque editorial con imagen/productos propios de cada instalación |

La línea comprobada en el laboratorio H11 es WordPress **7.1.3**, WooCommerce **11.2.1**, PHP **8.4.27**, MariaDB **10.11.19** y WP-CLI **2.12.0**. Docker se usa para desarrollo/QA; el despliegue preparado utiliza PHP/DB del hosting e instaladores nativos/WP-CLI. El funcionamiento local no certifica compatibilidad, recursos, seguridad ni carga del proveedor remoto.

## Demostraciones y separación de datos

| Instalación | Estado documentado y alcance del cierre |
| --- | --- |
| TRAMA — `http://localhost:8080` | DEMO original; H10 comprobó preservación de usuarios, configuración, pedidos, legales y uploads. No se modifica ni se revalida en vivo durante este cierre |
| ORIGEN CASA — `http://localhost:8082` | Segunda DEMO de hogar: marca, Home, diez productos, atributos, imágenes y configuración propios; datos/secretos/volúmenes/Mailpit separados. MP completamente inactivo según la validación H10 |
| Repetición e instalaciones H11 | Aprovisionamiento limpio, ZIP instalados sin binds de código reutilizable, QA sintética y restauración en destino independiente. Volúmenes, secretos y backups privados conservados; no se arrancan, restauran ni limpian para consolidar Git |

La validación de rebranding H10 y la instalación genérica H11 demuestran que el código compartido no necesita los usuarios, IDs, catálogo, medios o datos de TRAMA. La configuración y contenido reales se crean por cliente. [Aislamiento/preservación H10](evidence/h10/isolation-final.json), [instalación limpia H11](evidence/h11/fresh-install.json).

H10 utilizó Commerce Base 1.9.0 en sus demos. La revisión 1.9.1 del repositorio/ZIP H11 no se sincroniza automáticamente a esos volúmenes. No se declara que las instalaciones estén encendidas ni se confunde la versión del código con una consulta de runtime no realizada.

## Entregables y distribución

- Código fuente del theme, Commerce Core y las extensiones independientes, con módulo legal y configuración por tienda.
- Fixtures DEMO separados en `demo/`, optativos y sin habilitación comercial implícita.
- Scripts de instalación/desarrollo y laboratorio en `scripts/`; inicialización segura/preflight en `deployment/`.
- **Seis ZIP instalables** de la distribución H11 **1.0.0**, existentes en `.local/h11-release/1.0.0/`, excluidos de Git junto con el runtime. [Manifiesto de los paquetes probados](evidence/h11/release-manifest.json).
- [Manual de instalación, actualización, backup y recuperación](h11-deployment-runbook.md), [adaptación de un nuevo cliente](h10-new-client-playbook.md), [playbook/checklist de lanzamiento](h11-client-launch-checklist.md), [hosting](h11-hosting-decision.md) y [estado productivo detallado](h11-production-readiness.md).
- Evidencias sanitizadas de H0–H11 y documentación de QA; datos crudos, credenciales, bases, uploads de runtime y copias privadas fuera de Git.

En este cierre los seis ZIP existentes y sus **80 entradas** coinciden con los SHA-256/inventarios aprobados y las versiones de los archivos fuente actuales. Se revisaron rutas, exclusiones, patrones de credenciales y coincidencias con secretos privados locales, sin imprimir sus valores. No se detectaron secretos/datos privados en los paquetes; tampoco en los documentos/candidatos mediante `verify-h11-secrets.cjs` y los controles H10/H9 que incorpora. No se regeneraron paquetes válidos ni se modificaron versiones internas.

Los ZIP contienen sólo código/activos reutilizables admitidos: no incluyen DB, usuarios/pedidos/solicitudes QA, credenciales, `.env`, `wp-config.php`, logs, backups, uploads históricos, MP ni herramientas Docker/Mailpit. El contenido editorial DEMO excluido sigue en las fuentes opcionales; nunca se publica una copia histórica de TRAMA como tienda de un cliente. El material `support/` generado en H11 es una instantánea de aquella revisión; los documentos vigentes de cierre son los versionados en `docs/` de esta referencia Git, sin reconstruir artefactos por cambios documentales.

## Infraestructura elegida y límites

El titular mantiene **Hostinger Premium Web Hosting legacy**, con **25 sitios permitidos y 8 creados**, **25 GB**, **2048 MB de RAM y 1 núcleo compartidos**, **400.000 inodos**, **40 PHP workers y 80 procesos**. El servidor informado está en Brasil y los backups en Estados Unidos. Los 0,04 GB/763 inodos mostrados no acreditan consumo agregado ni capacidad de CPU/RAM/carga.

hPanel muestra renovación **ARS 130.788**, autorrenovación activada, próxima facturación **15/08/2029** y vencimiento **29/08/2029**. El precio no se garantiza para 2029; ciclo/impuestos y validación técnica remota siguen pendientes. No se actualiza a Unlimited, contrata, renueva anticipadamente ni cambia facturación/DNS. Para ecommerce reales se priorizan cuentas administradas independientes cuando aislamiento, ventas o recursos lo justifiquen. [Datos aportados por el titular y evaluación](h11-hosting-decision.md).

DockerAI/Gordon conserva su estado desactivado documentado. El titular no autoriza modificar ajustes globales ni reiniciar Docker para reactivarlo. Este cierre no interviene en Docker/Hostinger, datos de tiendas, backups ni volúmenes.

## Bloqueos antes de un lanzamiento real

1. **Mercado Pago:** resolver seguridad/autenticidad, replay/temporalidad, validaciones, compatibilidad y privacidad; una versión anunciada o un BACS local no cierra la [matriz de riesgos](h11-mercadopago-review.md). MP permanece ausente/inactivo en instalaciones nuevas que no lo usen; no se activa Pro real.
2. **Correo transaccional real:** proveedor, remitentes, cuotas, SPF/DKIM/DMARC, privacidad, errores/rebotes y pruebas de recepción con destinatarios expresamente autorizados. Mailpit sólo acredita captura local.
3. **Staging remoto y hosting productivo:** instalación restringida, PHP/DB/extensiones/permisos, aislamiento, cron, colas, caché y capacidad; Premium aún no está validado para WooCommerce en producción.
4. **HTTPS y DNS por sitio:** dominio/canonical, certificado, redirecciones, cookies y preservación de servicios existentes; cambios sólo con autorización específica.
5. **Datos fiscales y comerciales auténticos:** titular, CUIT, domicilio, contactos, Data Fiscal, moneda/impuestos/facturación, catálogo, logística y conciliación de pagos reales.
6. **Políticas legales aprobadas:** términos, privacidad, retención/transferencias, envíos, cambios, garantías y arrepentimiento revisados por titular/asesores; los textos DEMO no son definitivos.
7. **Backups externos y recuperación operativa:** cifrado/acceso, ubicación, retención, copia independiente, restore remoto, responsables, conciliación posterior, cobertura y RPO/RTO acordados. Los tiempos del ensayo local no son un SLA.
8. **QA de un cliente concreto:** contenido, catálogo, stock, direcciones, checkout, pagos autorizados, emails, legal, administración y operación en su destino real.
9. **Autorización final de lanzamiento:** aprobación expresa de la entrega, sitio/dominio, publicación, DNS, emails y pagos según alcance. La aprobación técnica del roadmap no la sustituye.

## Alcance del cierre y siguiente decisión

Se verifican ruta/remoto/ramas/historial, actualización final de Hostinger, estructura de módulos/fixtures/scripts/manuales, versiones, ZIP, secretos, conectividad de objetos Git y limpieza. Se actualizan README, este resumen y las referencias de consolidación H11. La integración es fast-forward, el push normal y la etiqueta anotada se crea sólo si no existe localmente ni en origin; no se sobrescriben etiquetas ni se eliminan ramas.

No se repiten las suites funcionales H0–H11 ni se cambian código aprobado, datos, evidencias históricas, paquetes válidos o infraestructura. No hay despliegue, pagos, SMTP, DNS, compra, GitHub Release ni inicio de otro proyecto/hito.

No queda una acción manual indispensable para cerrar el roadmap técnico. Los nueve bloqueos anteriores sí deben resolverse y aprobarse para un proyecto real posterior.

**ROADMAP H0–H11: CLOSED**

**PRODUCTION: NOT READY**
