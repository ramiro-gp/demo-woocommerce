# D1 — Validación local y entrega para revisión

Fecha: **24/10/2026, Argentina**. Preparación de demos públicas; sin despliegue ni intervención en Hostinger. **D1 PREPARATION: READY. PUBLIC DEPLOYMENT: NOT PERFORMED.** READY se refiere a código, arquitectura propuesta, ensayo local y procedimientos revisables. La admisión del Premium y la autorización de publicación siguen pendientes.

## Resultado y alcance

Se prepararon dos WordPress nuevos e independientes en `http://localhost:8190` (TRAMA) y `http://localhost:8191` (ORIGEN CASA), ligados exclusivamente a loopback. Se usaron código aprobado H11 y fixtures versionados, sin copiar bases, usuarios, uploads, credenciales ni historial de las instalaciones originales `8080`/`8082`.

TRAMA conserva 16 productos, diez variables con 72 variaciones y atributos Color/Talle. ORIGEN conserva diez productos, dos variables con ocho variaciones y atributos de hogar. Se mantuvieron fotografías, composición editorial y Lookbook; sólo se corrigió copy incompatible con una demo pública: compras/BACS/cuentas, contacto, FAQ, legales y campos fiscales vacíos. Español de Argentina y formato ARS. Referencia ejecutada: WordPress **7.1.3**, WooCommerce **11.2.1**, PHP **8.4.27**, MariaDB **10.11.19** y HPOS/Blocks. El parche PHP es el observado en la imagen fijada, no una comprobación del hosting.

El módulo opcional [Commerce Public Demo Guard](../deployment/demo-public/README.md) queda separado de Commerce Base/Core y de los seis ZIP. Por defecto no registra hooks. Su activación exige booleano explícito, entorno `local`/`staging`, hostname exacto y marcador de instalación DEMO. Configuración solicitada incorrecta cierra con 503. El helper CLI configura sólo una instalación marcada con administrador ficticio y sin historial; conserva las guardas originales de Core para páginas legales no revisadas.

## Pruebas ejecutadas

| Evidencia | Resultado y significado |
| --- | --- |
| [Navegador público](evidence/d1/public-trama-origen.json) | **269 PASS** en ambas tiendas: Home, catálogo, categorías, búsqueda/filtro, fotos, variaciones, Lookbook, mini-cart y Cart Block, cantidades/eliminación, CTA real de continuar y aviso 403; desktop/mobile y claro/oscuro efectivos. Cero errores JS y cero respuestas 4xx inesperadas durante navegación/carrito |
| [Rutas y métodos](evidence/d1/entrypoints.json) | **80 PASS**: scripts/rutas codificados, XML-RPC, formularios mixtos carrito+cuenta/login, PATCH/override, batches malformados y la excepción OPTIONS exclusivamente CLI cerrada por HTTP |
| [Staging local](evidence/d1/staging.json) | **88 PASS**: mismas instalaciones con tipo WP `staging`; las cinco páginas legales revisadas conservan enlace, HTML y REST legibles; POST de trámites y checkout siguen bloqueados. Luego se restauró `local` con guard activo |
| [Contratos](evidence/d1/contract-active.json), [apagado](evidence/d1/contract-off.json), [producción](evidence/d1/contract-production.json), [sin marcador](evidence/d1/contract-missing-marker.json), [home incorrecto](evidence/d1/contract-wrong-home.json), [Host incorrecto](evidence/d1/contract-wrong-host.json), [metadata staging](evidence/d1/contract-staging-metadata.json) | **75 PASS**, siete procesos PHP: default-off, opt-in, rutas/batches y límites de la excepción de metadata. Las páginas sin revisión o fuera de la configuración legal conservan su protección original. Son contratos con stubs, complementados por integración real, no sustitutos de ella |
| [Auditoría TRAMA](evidence/d1/audit-trama.json), [ORIGEN](evidence/d1/audit-origen.json) | **24 PASS por tienda** después de HTTP/staging: pedidos HPOS/CPT/artículos = 0; solicitudes/eventos legales = 0; un único admin ficticio; sin clientes, MP o bancos; BACS/COD/cheque deshabilitados persistentemente |
| [TRAMA apagado](evidence/d1/trama-reversal-disabled.json), [activo](evidence/d1/trama-reversal-enabled.json), [ORIGEN apagado](evidence/d1/origen-reversal-disabled.json), [activo](evidence/d1/origen-reversal-enabled.json) | **32 PASS**: con guard apagado el motor nativo crea y guarda un pedido BACS ficticio on-hold; restaura stock y elimina exclusivamente ese pedido. Con guard activo se impide creación y se vacían gateways. `wp_mail` devuelve false sin llegar al transporte en ambos modos gracias al sumidero independiente del laboratorio. No se probó checkout de navegador completo en modo apagado ni se hicieron transferencias |
| [Sintaxis PHP](evidence/d1/lint.json) | Ocho archivos PHP válidos; SHA-256 de cada fuente registrada. Parser PowerShell, `node --check`, Compose config y `git diff --check` también aprobados |
| [Secretos](evidence/d1/secrets.json) | Escaneo de candidatos Git y valores privados conocidos H0–H11/D1 aprobado. No se versionan contraseñas, cookies, nonces, tokens, SQL, ZIP, logs o runtime. El scanner no prueba ausencia de secretos desconocidos; se complementa con fuentes limpias y revisión del diff |

Los **437 controles HTTP/UI** corresponden a 269 + 80 + 88. No son pruebas de capacidad del hosting, pentest exhaustivo ni certificación de futuras versiones/plugins. No se repitieron las suites completas H0–H11.

Durante el ensayo se corrigieron hallazgos reales: preset ORIGEN ausente de la distribución comercial limpia (el seed se detuvo antes de importar; se agregó sólo la fuente versionada), directorios/mounts demasiado amplios del CLI, compatibilidad legal en staging, rutas codificadas y estado XML-RPC. Se retiró la carga CDN de emojis: el recorrido final registró **0 solicitudes externas, 0 recursos Mercado Pago y 0 entregas de email**. La primera prueba reversible reducía una unidad de stock ficticio por tienda; se repuso exactamente desde su fixture, se corrigió el test para restaurar inventario y se repitió. Los [inventarios finales TRAMA](evidence/d1/trama-inventory.json) y [ORIGEN](evidence/d1/origen-inventory.json) no presentan diferencias de stock simple respecto de la fuente.

## Carrito, checkout y límites

El producto conserva su condición comprable para mostrar carrito. Se demostraron alta de simple/variación, mini-cart, cambio a tres unidades, eliminación y vaciado; los dos carritos no comparten selección. Precios y descuentos son ilustrativos. Se desactiva envío/cálculo de destino mediante filtro oficial, para que el visitante no ingrese dirección; no se simula una entrega.

Continuar desde el carrito lleva a una respuesta **403 con explicación**, sin formulario de checkout ni campos personales/bancarios. Se bloquean también query URLs, pretty REST, GET/POST de Store API checkout/order, WC REST pedidos, wc-ajax, wc-api, order-pay/order-received, lotes que envuelven operaciones prohibidas y persistencia de `WC_Order` antes de HPOS/CPT. No se depende de CSS/JS ni de ocultar botones. Las páginas informativas siguen accesibles; registro, recuperación, recepción legal y administración HTTP quedan cerrados. No hay usuario administrativo público.

Mercado Pago está **ausente**, no sólo con Checkout Pro deshabilitado. No se conectaron vendedores ni credenciales. El bloqueo productivo H7–H11 permanece abierto; D1 no lo resuelve.

El guard rechaza `wp_mail` y HTTP saliente por APIs WordPress, incluso webhooks/actualizaciones. Esto requiere mantenimiento privado controlado; no cubre código extraño que abra sockets/cURL/mail directamente. El conjunto de plugins permitido y las restricciones del servidor siguen siendo parte del diseño. No hay SMTP ni servicios comerciales en los laboratorios.

## Aislamiento y conservación

- [Aislamiento](evidence/d1/isolation.json): DB, credenciales admin/DB/root, salts, volúmenes, redes y orígenes de uploads distintos. La ruta interna de uploads coincide por la imagen Docker, pero pertenece a volúmenes diferentes. El CLI sólo monta deployment/tests/release aprobado y fuentes DEMO específicas; no recibe runtime histórico.
- [Antes](evidence/d1/original-volumes-before.json) y [después](evidence/d1/original-volumes-after.json): fingerprints idénticos de **los cuatro volúmenes de DB/WordPress originales**, calculados con contenedores sin red y mounts de sólo lectura. Las tiendas originales permanecieron detenidas; no se arrancaron, sincronizaron ni sembraron.
- [Paquetes H11](evidence/d1/approved-zips.json): los **seis ZIP mantienen sus SHA-256**. `main`, `origin/main` y el destino desreferenciado de `template-v1.0.0-rc1` conservan `9b5b504577af23a24def7cc502903724ff1917db`. No se crearon tags, Release ni merge.
- Código/configuración no escribibles por UID web en los laboratorios; uploads siguen escribibles y sin ejecución PHP mediante Apache. Las cabeceras noindex/nofollow/noarchive incluyen medios estáticos gracias al vhost D1, además del módulo HTML/REST. Sitemaps nativos apagados, sin ofertas Product/Offer y canonical propio o ausente; no se toca el SEO del portfolio.
- [Footprints](evidence/d1/footprints.json): 218.680 y 204.816 KiB de archivos WordPress; 22.548 entradas archivo/directorio entre ambas. DB lógica inicial ≈3,8/3,4 MiB. Excluye backups, crecimiento y equivalencia de cuotas remotas; no valida CPU/RAM Premium.

Docker Desktop estaba detenido y falló al arrancar por sockets AF_UNIX obsoletos de `dockerInference` y Secrets Engine, como el antecedente H11. Se preservaron los directorios exactos de sockets con sufijos `.d1-stale-...` y se recuperó el motor **29.6.1**. No se alteraron settings, DockerAI/Gordon, imágenes, bases, WSL ni volúmenes, ni se hizo prune/reset. `EnableDockerAI` se comprobó false antes/después. Sólo se cerraron intentos fallidos, sin un motor operativo, para recuperar el laboratorio. Los contenedores ajenos conservaron sus propias políticas de arranque.

## Reproducción y límites de los scripts

En una máquina con Docker operativo y la distribución H11 privada verificada, desde la raíz del repositorio:

```powershell
./scripts/d1.ps1 -Action install -Brand trama
./scripts/d1.ps1 -Action install -Brand origen
./scripts/d1.ps1 -Action audit -Brand trama
./scripts/d1.ps1 -Action audit -Brand origen
```

`install` acepta sólo proyectos nuevos `commerce-d1-trama`/`commerce-d1-origen`, puertos 8190/8191 y volúmenes vacíos. **Rechaza reinstalar o resetear**: después de un fallo preserva estado para inspección. El ensayo ORIGEN descrito se completó con la fuente de estilo faltante y sus guardas originales; no se promete un reanudar genérico. Las adaptaciones de guardas H4/H5/H5.1 son copias privadas que requieren marcador D1 y URL fija; los originales no se modifican ni se usan remotamente. [Revisión del aprovisionamiento](evidence/d1/preparation-review.md).

Pruebas focalizadas, con Node/Playwright disponibles mediante `NODE_PATH` del runtime local:

```powershell
node tests/verify-d1-public.cjs
node tests/verify-d1-entrypoints.cjs
node tests/verify-d1-secrets.cjs
# Sólo durante el ensayo controlado de staging:
./scripts/d1.ps1 -Action staging-on -Brand trama
./scripts/d1.ps1 -Action staging-on -Brand origen
node tests/verify-d1-staging.cjs
./scripts/d1.ps1 -Action local-on -Brand trama
./scripts/d1.ps1 -Action local-on -Brand origen
```

Para reversión usar `mode-off`, `wp -WpArgs @('eval-file','/workspace/tests/d1-reversal.php','disabled')`, `mode-on` y repetir con `enabled`, sólo en estos laboratorios. Nunca apagar el guard sobre una URL pública. El sumidero `deployment/demo-lab/mail-deny.php` continúa cargado incluso con modo off. `stop` detiene únicamente el proyecto D1 indicado; no borra volúmenes. Los seeds y tests rechazan destinos remotos por diseño.

## Informe de cierre solicitado

| Punto | Estado D1 |
| --- | --- |
| 1–2. Git y rama | Partida limpia, remoto verificado y rama `codex/d1-commercial-demos-preparation` desde main. Cambios revisados y versionados en esa rama; el SHA y estado del push se informan en la entrega |
| 3–4. Alternativas y recomendación | Dos WP independientes en Premium; privado guiado temporal; Playground; presentación estática; hosting independiente sólo con ventaja demostrada. Preferida: Premium condicional |
| 5–6. Conveniencia y simultaneidad | Razonable ensayar tráfico bajo de ambas, gradualmente. Falta consumo agregado, aislamiento y validación real hPanel: no garantía de capacidad |
| 7–8. Requisitos/riesgos | PHP/DB/memoria/SSL/cron/caché/backups compatibles sin cambios globales; cuenta compartida y mantenimiento son riesgos principales |
| 9–11. Modo y funciones | Opcional/default-off; catálogo, variaciones, fotos, contenidos y carrito disponibles; compras/pagos/cuentas/trámites/correo/admin HTTP bloqueados |
| 12–15. QA/carrito/checkout/Store API | PASS documentados arriba; carrito nativo conserva su experiencia, checkout 403 y APIs de pedido cerradas antes de persistir |
| 16–19. Privacidad/MP/SEO/aislamiento | Sólo fuentes ficticias, MP ausente, noindex en páginas/REST/medios, sitemaps/schema comercial apagados; separación local probada, hosting pendiente |
| 20–21. Código/documentos | MU guard/setup, laboratorio/runner/tests/evidencia; ningún cambio de Base/Core/WC/MP. [Plan](demo-commercial-plan.md), [runbook](demo-commercial-deployment-runbook.md), este informe y README del módulo |
| 22. Coste | Incremento de abono esperado ARS 0 si todo está incluido y hay margen. Renovación aportada ARS 130.788: no se inventa ciclo/impuestos. Mano de obra/mantenimiento/backup extras pendientes de acuerdo |
| 23. Aprobaciones | Inspección hPanel, nombres, recursos nuevos/cambios limitados, riesgo compartido, presupuesto operativo, QA remoto y apertura. Ninguna ejecución remota autorizada ahora |
| 24–25. Commit/push/working tree | Se entregan en la rama D1 sin merge ni release; ver estado final de Git comunicado con la entrega |

La propuesta completa y los pendientes técnicos/manuales están en [el plan](demo-commercial-plan.md); el procedimiento futuro y **GO/NO-GO sin casillas remotas marcadas** están en [el runbook](demo-commercial-deployment-runbook.md). El hosting no se inspeccionó autenticado y no se ejecutó ninguna operación remota, DNS, SSL, contratación o publicación. No se abrió túnel ni se expuso la PC a internet. No se inicia otra fase.

**D1 PREPARATION: READY**

**PUBLIC DEPLOYMENT: NOT PERFORMED**
