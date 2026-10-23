# demo-woocommerce

Base local reproducible para una demo comercial de WordPress y WooCommerce. **H0–H9 están aprobados y consolidados en main (`1921b3f`). H10 se entrega en `codex/h10-rebranding-portability` para revisión, sin merge a main. H11 no comenzó.** H2 conserva su validación histórica de HPOS, Blocks y Mercado Pago con datos ficticios y permanece aislado. Commerce Base mantiene el sistema H3/H4 y el catálogo real de H5.

La identidad de demostración es **TRAMA**. El theme es **Commerce Base** (`commerce-base`) y el plugin funcional es **Commerce Core** (`commerce-core`). La marca se mantiene como datos de WordPress, independiente del theme.

## H10 — Dos tiendas, una base común

**H10 READY como demostración técnica local; producción NOT READY.** TRAMA continúa en [localhost:8080](http://localhost:8080), conservando datos, configuración y uploads. [ORIGEN CASA · DEMO](http://localhost:8082) tiene identidad, Home, diez productos, categorías, atributos de hogar, cuentas, pedido BACS y legales propios, con DB/volúmenes/secretos/Mailpit aislados. Ambas utilizan Commerce Base 1.9.0, Commerce Core y los mismos plugins; Communications 1.0.1 corrige el reflow de direcciones largas en emails.

Revisar [informe y evidencia H10](docs/h10-rebranding-portability.md), [guía práctica de un nuevo cliente](docs/h10-new-client-playbook.md), [checklist de entrega](docs/h10-delivery-checklist.md) y [auditoría de dependencias](docs/h10-dependency-audit.md). Mailpit ORIGEN: [localhost:8027](http://localhost:8027). Mercado Pago está completamente inactivo en ORIGEN; TRAMA conserva H7/H9. No hubo publicación ni inicio de H11.

```powershell
./scripts/h10.ps1 inspect
# Sólo para una DEMO nueva y limpia; nunca sobre TRAMA:
./scripts/h10.ps1 install -Instance nueva-demo -WordPressPort 8083 -MailpitPort 8028
```

El fixture no sobrescribe contenido/stock editado: `seed` y `verify` rechazan diferencias legítimas después de operar. Para navegar no ejecutar seeds. La instalación de prueba `h10-repeat` conserva sus volúmenes y prueba repetibilidad; el informe explica sus puertos y cómo reanudarla. Los bloqueos productivos de H9 permanecen abiertos.

## H9 — Seguridad, rendimiento, accesibilidad y SEO

**H9 READY como hito técnico local; producción NOT READY.** Commerce Core 1.1.2 protege datos legales en errores SQL y refuerza permisos. Las imágenes oficiales Apache/WP-CLI pasan a PHP **8.4.27** por el aviso crítico de seguridad del 21/10/2026. Commerce Base 1.8.0 añade carga contextual de assets, tamaños responsive, correcciones de foco/nombres/estados y metadatos reutilizables sin duplicar JSON-LD de WooCommerce.

Revisar [seguridad e investigación de Mercado Pago](docs/h9-1-security-hardening.md), [comparación por página, accesibilidad y SEO](docs/h9-2-performance-accessibility-seo.md) y [matriz de preparación y requisitos de hosting](docs/h9-production-readiness.md). Lighthouse local no certifica Core Web Vitals ni WCAG AA: las fichas conservan un LCP mobile alto en laboratorio. La DEMO mantiene noindex y no publica sitemap.

**Mercado Pago 8.9.6 permanece activo con Checkout Pro deshabilitado y bloqueos productivos abiertos.** La desactivación completa se probó sólo en clon aislado, donde elimina las cargas MP observadas; no se aplicó a la principal. Sin pagos reales, DNS ni despliegue. BACS sigue siendo DEMO. QA con escrituras sólo en `h9-audit`; no repetir fixtures/mediciones para navegar la tienda principal.

```powershell
.\scripts\h9.ps1 inventory
node tests/verify-h9-secrets.cjs
```

## H8.2 — Legales y consumidor argentino

Commerce Core 1.1 registra solicitudes de arrepentimiento sin login, emite código inmediato, intenta constancia/aviso por Mailpit y permite gestión autorizada sin modificar pedidos ni pagos. Commerce Base 1.7 integra el acceso visible en Home, páginas Gutenberg, identidad compartida, privacidad en registro y bloque nativo de Términos del checkout.

**Contenido legal DEMO, pendiente de datos auténticos y revisión jurídica por comercio.** Data Fiscal está preparado sin CUIT ni QR ficticio. Checkout Pro sigue deshabilitado; no se habilita producción.

Revisar [formulario local](http://localhost:8080/?page_id=514), [informe técnico](docs/h8-2-legal-consumer-rights.md), [matriz normativa vigente](docs/h8-legal-compliance-matrix.md) y [guía por cliente](docs/h8-client-legal-setup.md). No volver a ejecutar el setup sobre páginas editadas.

La [auditoría autónoma final](docs/h8-2-final-audit.md) incorpora correcciones de correo/constancia, pruebas de instalación y restauración aisladas, y la [evaluación específica de recursos externos](docs/h8-2-external-resources-audit.md). H8.2 está técnicamente listo para consolidación como DEMO, con requisitos comerciales/jurídicos y bloqueos productivos pendientes.

## H7.1 — Pagos y pedidos

**Transferencia bancaria nativa (BACS)** disponible en Checkout Blocks: pedido en espera, sin pago automático y con instrucciones DEMO que no permiten transferir dinero. Mercado Pago oficial 8.9.6 conserva Checkout Pro como opción elegida; su habilitación exige vendedor de prueba verificado y HTTPS. No se transportan credenciales mediante fixtures ni se presenta el QA interno como un pago externo confirmado.

Ver [informe H7.1 y resultados reales](docs/h7-1-payments.md), [guía de pruebas](docs/h7-payment-testing.md) y [auditoría MP, autenticidad y límites](docs/h7-mp-audit.md). La documentación distingue pruebas nuevas de la evidencia histórica H2. El theme sólo aclara la confirmación pendiente/pagada usando el pedido WooCommerce. Emails y operación se reservan para H7.2.

```powershell
.\scripts\h7-1.ps1 inspect
.\scripts\h7-1.ps1 verify
.\tests\verify-h7-1.ps1
```

No ejecutar QA mientras haya una sesión de pago manual o HTTPS activa. Los scripts de configuración requieren backup, conservan cuentas/credenciales y bloquean datos bancarios existentes. No realizar pagos, transferencias o reembolsos reales.

## H6.2 — Envíos y preparación logística

WooCommerce Core resuelve la zona Argentina, **Envío estándar $4.500**, **Envío gratis desde $100.000 después de descuentos** y **Retiro gratuito** mediante Local Pickup de Checkout Blocks. Commerce Base 1.5.0 integra su presentación. **Configuración DEMO — reemplazar al adaptar la tienda a un comercio real.** Los 16 productos tienen peso y dimensiones; las 72 variaciones heredan del padre. Ningún transportista instalado.

Ver [configuración, reproducción, QA y URLs H6.2](docs/h6-2-shipping-logistics.md) e [investigación comparativa de Correo Argentino, Andreani y OCA](docs/h6-shipping-carriers.md). Para navegar, agregá un producto desde la [tienda](http://localhost:8080/?post_type=product) y abrí [carrito](http://localhost:8080/?page_id=8) o [checkout](http://localhost:8080/?page_id=9). No ejecutar seeds para navegar.

```powershell
.\scripts\h6-2.ps1 inspect
.\scripts\h6-2.ps1 verify
```

## H6.1 — Carrito y checkout aprobados

**[Carrito](http://localhost:8080/?page_id=8)** y **[checkout](http://localhost:8080/?page_id=9)** integrados con Commerce Base 1.4.0: controles nativos, resumen claro, cupón secundario, vacío diseñado y Cross-Sells reales en carrusel oficial con cards H5. Checkout conserva compra invitada, cuenta opcional, misma dirección nativa y enlace oficial para volver al carrito. Sin gateways ni logística nuevos.

Ver [arquitectura, configuración protegida, QA, límites y URLs H6.1](docs/h6-1-cart-checkout.md). No ejecutar configuración para navegar; agregar un producto desde la tienda. `verify` revisa huellas sin importar datos.

Las correcciones post-H6.1 (Commerce Base 1.4.1) ajustan el mini-cart, las alineaciones de carrito/checkout e incorporan Exit Intent discreto en escritorio: carrito con productos, interacción previa, mínimo 15 segundos y cooldown de 24 horas. Excluye checkout y paneles abiertos; copy configurable mediante `commerce_base_exit_intent_config`.

```powershell
.\scripts\h6-1.ps1 inspect
.\scripts\h6-1.ps1 verify
```

## H5.1 — Shoppable Lookbook aprobado

La sección Lookbook de la **[Home](http://localhost:8080/)** ahora usa un bloque Gutenberg editable con dos productos reales: Remera Base y Pantalón Pausa. El plugin propio **Commerce Lookbook** conserva imagen, referencias y posiciones independientemente del theme; Commerce Base 1.3.0 aporta los tokens visuales. Sin plugins externos ni dependencias nuevas.

Hover/focus abre la píldora; click navega. En touch, primer tap abre y segundo navega. Nombre, precio, permalink y disponibilidad se resuelven con WooCommerce. Ver [arquitectura, edición, assets, reproducción y QA H5.1](docs/h5-1-lookbook.md). Editar desde **[Páginas → Inicio](http://localhost:8080/wp-admin/post.php?post=37&action=edit)**, bloque Shoppable Lookbook.

```powershell
.\scripts\h5-1.ps1 inspect
.\scripts\h5-1.ps1 verify
```

No ejecutar seeds para navegar. La migración local conserva el copy y la estructura H4; H6, pagos, logística, deploy y Commerce Core completo quedan fuera de este hito.

## H5 — Catálogo y producto aprobados

**[Tienda TRAMA](http://localhost:8080/?post_type=product)**: 16 productos reales WooCommerce, 10 variables/6 simples, 72 variaciones, cuatro categorías pobladas, colección Ritmo diario, tres ofertas y cuatro destacados. Commerce Base 1.2.1 incorpora filtros oficiales con panel móvil, ordenamiento, cards compartidas, ficha, galería y guía de talles reemplazable. Home mantiene el diseño aprobado.

Ver [informe, catálogo, fixtures, pruebas y URLs](docs/h5-catalog.md), [correcciones post-H5 y pendientes H6/H8](docs/h5-corrections.md) y [procedencia de las 32 fotografías](docs/h5-assets.md). Los fixtures son idempotentes por SKU y bloquean ediciones manuales. El ajuste nativo de inventario oculta agotados: 15 productos visibles, 16 conservados. Las cards incorporan colores reales y Product Button; el header usa Mini-Cart/store oficial. Mercado Pago sigue disponible en la instancia H2 separada de 18081.

```powershell
.\scripts\h5.ps1 inspect
.\scripts\h5.ps1 verify
```

La documentación incluye el procedimiento de backup/importación. No ejecutar seeds para simplemente navegar ni para sobrescribir cambios del comercio.

## H4 — Home y estructura institucional aprobadas

La demo principal sigue en **[http://localhost:8080](http://localhost:8080/)**. H4 incorporó Inicio, [Conocenos](http://localhost:8080/?page_id=38), [Contacto](http://localhost:8080/?page_id=39), [Preguntas frecuentes](http://localhost:8080/?page_id=40), navegación real, búsqueda y 404. El contenido se guarda como bloques editables de WordPress; la home consulta productos reales de WooCommerce. Las cuatro categorías estructurales de H4 ahora contienen el catálogo H5.

H4 (Commerce Base **1.1.0**) fue aprobado visualmente: hero **Vestir lo cotidiano.**, productos inmediatos, categorías, lookbook, marca y ayuda. Su validación comprende 223 comprobaciones H4 y 64 adicionales de búsqueda, 22 de regresión H3, editor nativo y smoke WooCommerce/Mercado Pago. Ver [implementación y evidencia](docs/h4-validation.md) y [assets/prompt](docs/h4-assets.md). Se conservan permalinks, checkout y separación de `h2-public`; H5 documenta la preparación del índice nativo de atributos local.

```powershell
.\scripts\h4.ps1 inspect
.\scripts\h4.ps1 verify
```

`seed` es una herramienta de preparación local que exige backup y bloquea ediciones manuales; no hace falta ejecutarla para navegar la demo. El showcase H3 sigue disponible como referencia del sistema visual.

## H3 — revisar Commerce Base

La demo principal es la instancia **`local` en http://localhost:8080**. Commerce Base 1.0.3 cerró las correcciones post-H3; H4 extiende esa base. Abrí la [tienda](http://localhost:8080/?post_type=product) o el [showcase del sistema visual](http://localhost:8080/?commerce-base-showcase=1) para revisar el sistema. El showcase es una vista de desarrollo sin página publicada ni indexación; la home real corresponde a H4.

**`localhost:18081` es `h2-public`**, instalación técnica aislada para pagos/pruebas H2. Conserva sus productos, pedidos, credenciales, contenido inicial y Commerce Base 1.0.0 usado en la revisión original de H3. No es la URL de las correcciones actuales. Durante H3 la demo 8080 permaneció sin gateway habilitado; H7.1 incorpora BACS y la preparación de Pro sin trasladar credenciales de H2. Ver [informe post-H3 e instancias](docs/h3-corrections.md).

```powershell
powershell -NoProfile -File .\scripts\dev.ps1 start -Instance local
.\scripts\h3.ps1 sync -Instance local
powershell -NoProfile -File .\tests\verify-h3-local.ps1 -Instance local
```

`sync` copia únicamente el theme al volumen existente, sin mounts ni borrados. Fuente: `commerce-base/`. Ver [dirección y tokens](docs/h3-design-system.md) y [pruebas, capturas y límites](docs/h3-validation.md). H2 se conserva; no se repiten pagos ni se reconfigura el checkout.

## Antes de empezar

- Windows con PowerShell y Docker Desktop instalado y abierto.
- Docker Desktop configurado para ejecutar contenedores Linux, con Docker Compose disponible.
- Conexión a Internet para descargar las imágenes y el idioma de WordPress en la primera instalación.
- Puertos locales 8080 y 8025 libres, o elegir otros al inicializar.

No hace falta un dominio, HTTPS público, hosting ni cuentas de Mercado Pago para H1. PHP, MariaDB y WP-CLI se ejecutan dentro de Docker; no hace falta instalarlos en Windows.

Una **imagen** contiene el software y su versión; un **contenedor** lo ejecuta. Un **volumen** conserva los datos aunque se detenga o se vuelva a crear el contenedor. Este proyecto usa volúmenes separados para WordPress, MariaDB y los correos de Mailpit.

## Primera instalación

Abrí PowerShell en esta carpeta, con Docker Desktop en funcionamiento:

```powershell
powershell -NoProfile -File .\scripts\dev.ps1 doctor
powershell -NoProfile -File .\scripts\dev.ps1 init
powershell -NoProfile -File .\scripts\dev.ps1 start
```

`init` prepara las credenciales y valida la configuración local. `start` descarga las imágenes, inicia los servicios e instala WordPress si todavía no está instalado. Se pueden repetir: conservan una instalación existente y sus opciones. El primer arranque puede tardar varios minutos mientras Docker descarga las imágenes.

Luego abrí:

| Acceso | URL predeterminada |
| --- | --- |
| Sitio | <http://localhost:8080> |
| Administración | <http://localhost:8080/wp-admin> |
| Correos de prueba | <http://localhost:8025> |

El script guarda la configuración y las credenciales en `.local/local/`, que queda fuera de Git. El usuario administrador se genera con el formato `local_owner_<8 caracteres hexadecimales>` y figura en `LOCAL_ADMIN_USER` dentro de `.local/local/.env`. Su contraseña está en `.local/local/secrets/wp-admin-password.txt`; consultala localmente cuando necesites ingresar. No la publiques ni la pegues en incidencias o capturas compartidas.

`.env.example` es una referencia de las variables; no lo copies como configuración activa. El script genera el archivo privado de cada instancia.

La instalación inicial usa español argentino, zona horaria `America/Argentina/Buenos_Aires` y visibilidad para buscadores desactivada. Estas opciones solo se establecen durante la primera instalación.

Para elegir otros puertos desde el comienzo:

```powershell
powershell -NoProfile -File .\scripts\dev.ps1 init -WordPressPort 8081 -MailpitPort 8026
powershell -NoProfile -File .\scripts\dev.ps1 start
```

No edites el puerto de una instalación existente sin considerar que WordPress guarda su URL en la base. Usá una instancia nueva para un entorno con otros puertos.

## Uso diario

```powershell
powershell -NoProfile -File .\scripts\dev.ps1 start
powershell -NoProfile -File .\scripts\dev.ps1 status
powershell -NoProfile -File .\scripts\dev.ps1 stop
```

`start` vuelve a levantar el entorno; `stop` lo detiene y conserva sus datos. Ninguno importa contenido ni elimina volúmenes. `start` preserva WordPress cuando ya está instalado y completa la instalación inicial cuando la base está vacía.

Para revisar problemas o comprobar el correo:

```powershell
powershell -NoProfile -File .\scripts\dev.ps1 logs
powershell -NoProfile -File .\scripts\dev.ps1 logs -Service db
powershell -NoProfile -File .\scripts\dev.ps1 mail-test
```

Mailpit captura los correos dentro del entorno local. No se configura un servidor SMTP real y la base ni el puerto SMTP se publican en Windows.

Para ejecutar WP-CLI usá la llamada directa al script, porque `powershell -File` no transmite arrays de la misma manera:

```powershell
.\scripts\dev.ps1 wp -WpArgs @('core', 'version')
.\scripts\dev.ps1 wp -WpArgs @('option', 'get', 'home')
```

Si la política de PowerShell impide ejecutar scripts, consultá [Operación local](docs/operations.md#powershell-y-requisitos) antes de modificar la política del equipo.

## Respaldo y recuperación

```powershell
powershell -NoProfile -File .\scripts\dev.ps1 backup
```

El respaldo incluye un dump de la base, los archivos de WordPress y un manifiesto con hashes. No incluye los archivos de secretos. Se guarda en `.local/local/backups/<id>/`. Los datos de la base y WordPress pueden contener información sensible: no subas estos respaldos a Git y conservá una copia fuera de esta carpeta si necesitás protegerte frente a una falla del disco.

Restaurar reemplaza los datos de la misma instancia. Exige elegir un respaldo, conservar las versiones compatibles y escribir el nombre completo del proyecto local. La documentación explica el respaldo preventivo y los resets antes de usarlos:

```powershell
powershell -NoProfile -File .\scripts\dev.ps1 restore -BackupId '<id>' -ConfirmName 'demo-woocommerce-local'
```

Ver [procedimientos de respaldo, restauración y reset](docs/operations.md#respaldo-y-restauración).

## Alcance y versiones

| Componente | Versión inicial |
| --- | --- |
| WordPress | 7.1.3 |
| PHP | 8.4, variante Apache para WordPress |
| MariaDB | 10.11.19 |
| WP-CLI | 2.12.0, PHP 8.4 |
| Mailpit | 1.31.4 |

Las imágenes se fijan por versión y digest en `compose.yaml`. PHP y MariaDB son elecciones **provisionales** hasta verificar las versiones de la cuenta Hostinger Premium. No se declara paridad exacta entre Apache local y LiteSpeed del hosting.

Si el WordPress conservado en el volumen no coincide con la versión fijada, `start` se detiene para exigir una migración deliberada; no lo sobrescribe silenciosamente.

H1 no instaló SEOPress, FluentSMTP ni LiteSpeed Cache, ni creó theme/plugin comercial, túnel, checkout, pagos o deploy. H2 agrega únicamente la matriz técnica descrita abajo.

## Documentación

- [H7.2: comunicaciones, emails, operación y revisión local](docs/h7-2-communications-operations.md).
- [Preparación de correo productivo por cliente y Hostinger](docs/h7-email-production.md).
- [H7.1 cerrado en desarrollo y bloqueos productivos de Mercado Pago](docs/h7-1-payments.md).

- [Decisiones de H0 y orden de hitos](docs/h0-decisions.md).
- [Arquitectura local y límites de esta entrega](docs/architecture.md).
- [H8.1: Commerce Core, copia explícita de direcciones y auditoría de responsabilidades](docs/h8-1-commerce-core.md).
- [Comandos, instancias aisladas y recuperación](docs/operations.md).
- [Cierre de H1 y pruebas ejecutadas](docs/h1-validation.md).
- [H4: implementación institucional y validación pendiente](docs/h4-validation.md).

H1 quedó verificado localmente el 10/10/2026: instalación nueva, persistencia, correo, respaldo/restauración y ambos resets en una instancia aislada. También quedó reconciliado en la ruta única `C:\Codigo\demo-woocommerce`, reutilizando volúmenes y generando credenciales locales nuevas, sin copiar runtime desde otra carpeta.

## H2 — compatibilidad comprobada

Desde esta misma carpeta, después de H1:

```powershell
.\scripts\h2.ps1 install
.\scripts\h2.ps1 inspect
.\scripts\h2.ps1 inventory
powershell -NoProfile -File .\tests\verify-h2-local.ps1
```

Se fijaron WooCommerce 11.2.1 y Mercado Pago oficial 8.9.6. El setup crea únicamente productos técnicos, HPOS, Cart/Checkout Blocks y envíos nativos para Argentina. Las pruebas `inventory` usan pedidos aislados y APIs del core sin dinero ni pasarela; no acreditan pagos Mercado Pago. Los plugins viven en el volumen WordPress, no en Git.

Ver [versiones, procedimiento, resultados y límites de H2](docs/h2-validation.md). No ejecutar el fixture sobre una tienda real: reaplica configuración técnica local. Los secretos y reportes permanecen en `.local/`, fuera de Git.

La instancia aislada `h2-public` conserva tres pedidos de invitado: #16 aprobado y devuelto, #17 rechazado y cancelado, y #19 pendiente por Rapipago, aprobado después mediante cambio nativo a saldo ficticio y finalmente devuelto. El pendiente se actualizó aun habiendo cerrado Checkout Pro; la confirmación posterior al primer retorno descontó stock una sola vez. Ambas devoluciones se confirmaron en Mercado Pago, WooCommerce, stock y correo. Stock final: simple 5, variante S 3 y M 0; reservas 0. Cart/Checkout Blocks siguen activos.

El entorno volvió a `http://localhost:18081`, con Mailpit privado en `http://localhost:18026`. Quick Tunnel cambia de hostname en cada sesión y requiere verificar los destinos del plugin antes de volver a pagar. Ver [operación y apagado de HTTPS temporal](docs/h2-temporary-https.md). La viabilidad local comprobada no reemplaza la validación del hosting y la configuración comercial previa a producción.
