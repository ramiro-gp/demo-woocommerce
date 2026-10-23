# H11 — Preparación técnica, riesgos y autorización de lanzamiento

Fecha: **23/10/2026, Argentina**. Alcance: distribución reutilizable, investigación oficial, procedimientos y laboratorio exclusivamente local. **H11 TECHNICAL READY. PRODUCTION NOT READY; lanzamiento NO AUTORIZADO.** Nada de este informe autoriza ventas, pagos, correo real, contratación, DNS o publicación.

## 1. H10 consolidado y alcance preservado

Repositorio `ramiro-gp/demo-woocommerce`. Antes de trabajar se comprobó la rama aprobada, árbol limpio, fetch, coincidencia con el remoto y los commits completos:

- `2adc01169c7bae44721946578158e55b67f2f94b`.
- `40dd2a1ba4588e1fd5d618fcdaae433c31d23912`.

El scanner H10/H9 pasó; no se encontraron secretos conocidos, dumps, uploads privados ni archivos runtime entre candidatos versionados. Se adelantó `main` **sólo mediante fast-forward**, se hizo push normal y se confirmó `main = origin/main = 40dd2a1ba4588e1fd5d618fcdaae433c31d23912`. H11 trabaja en `codex/h11-production-readiness`; no se integra a main. [Registro sanitizado](evidence/h11/bootstrap.json).

No se ejecutaron seeds, pedidos, restauraciones ni cambios de contenido en TRAMA u ORIGEN CASA. No se alteraron pruebas históricas H7–H10. El cambio compartido mínimo es **Commerce Base 1.9.1**: si la vista de muestra opcional no se distribuye, su URL responde 404; con el archivo original presente conserva su comportamiento anterior. Las instalaciones históricas no recibieron sincronización del theme.

## 2. DockerAI y recuperación de la herramienta

`EnableDockerAI` controla **Gordon de Docker Desktop**; no es un servicio de este proyecto ni se necesita en producción. El respaldo `settings-store.json.h10-stale-20261022-213920` confirma que antes de H10 estaba en `true`; el actual sigue en `false`. H10 lo desactivó para recuperar el fallo de inicialización del Inference manager; no significa que antes estuviera funcionando correctamente, pues Docker Desktop estaba detenido/fallando.

En H11 el intento de arrancar Desktop reprodujo el error AF_UNIX del socket `dockerInference`. Se cerró el intento fallido y se preservaron exclusivamente los dos directorios de sockets, con sufijo `.h11-stale-<fecha>`, sin borrar volúmenes, imágenes, bases o WSL. El motor volvió a arrancar, versión 29.6.1. Los contenedores ajenos se dejaron bajo sus propias políticas de reinicio; no se ejecutaron comandos de modificación sobre ellos. No hubo prune, reset de fábrica ni reinstalación.

**DockerAI no se restauró.** Reactivarlo implica una configuración global y un posible reinicio con impacto en otros proyectos, además de riesgo de reproducir el fallo. Requiere aprobación explícita y una prueba controlada/fix del proveedor. Los respaldos se conservan; no se debe copiar el JSON antiguo entero sobre configuraciones nuevas. [Documentación Gordon](https://docs.docker.com/ai/gordon/how-to/docker-desktop/), [incidencia oficial aún abierta consultada](https://github.com/docker/desktop-feedback/issues/531).

## 3. Decisión económica y aislamiento

El titular confirmó **Premium contratado con hasta 25 sitios y aproximadamente 3 en uso**. Vencimiento, renovación/moneda y límites efectivos siguen pendientes por su decisión expresa; no bloquean H11. No se equipara ese contrato al Premium nuevo. No hubo acceso a su hPanel ni modificación de configuración global para instalar un conector.

La [comparación completa](h11-hosting-decision.md) recomienda conservar por ahora el plan ya pagado para el uso compatible con su contrato. Para el primer ecommerce real, preferir titularidad/cuenta propias del cliente y hosting administrado: candidato económico Unlimited/Business sujeto a contrato, presupuesto de renovación y QA privada remota. Landings de bajo riesgo pueden agruparse; ecommerce moderado o independiente necesita evaluación y aislamiento propios. Bases separadas dentro de una misma cuenta de sistema no constituyen aislamiento de seguridad completo. El VPS autogestionado queda descartado como opción inicial por su carga operativa.

| Referencia pública, no cotización de la cuenta | Primer período | Renovación publicada |
| --- | --- | --- |
| Hostinger Unlimited AR | ARS 239.952 adelantados por 48 meses; equivalente ARS 4.999/mes | ARS 21.999/mes; equivalente aritmético ARS 263.988/año |
| SiteGround GrowBig | USD 59,88 por 12 meses, cálculo de USD 4,99/mes | USD 359,88 por 12 meses, cálculo de USD 29,99/mes |
| Premium del titular | Ya contratado; importe y vencimiento no confirmados | **Pendiente de hPanel/factura** |

Precios públicos consultados el 23/10/2026; impuestos/extras no incluidos. No se presenta el equivalente anual de una promoción de 48 meses como contratación anual disponible. Cada cliente con cuenta individual suma el plan completo, dominio, transporte de email, backup externo, licencias que se elijan y mantenimiento acordado. Dentro del cupo del Premium podría no haber cargo incremental de instalación, pero no se garantiza capacidad ni costo operativo cero. [Hostinger AR](https://www.hostinger.com/ar/hosting-web), [SiteGround WooCommerce](https://www.siteground.com/woocommerce-hosting.htm).

## 4. Distribución y compatibilidad

`scripts/build-release.ps1` genera una distribución **1.0.0**, con seis ZIP instalables, versiones individuales e inventario SHA-256. Utiliza exclusivamente archivos confirmados en Git y rechaza cambios de código sin commit. Dos construcciones con la misma revisión produjeron los mismos hashes ZIP. [Manifiesto de los paquetes probados](evidence/h11/release-manifest.json), [control de exclusiones](evidence/h11/release.json).

| Paquete | Versión |
| --- | --- |
| Commerce Base | 1.9.1 |
| Commerce Core | 1.1.2 |
| Commerce Communications | 1.0.1 |
| Commerce Order Integrity | 1.0.0 |
| Commerce Account Navigation | 1.0.0 |
| Commerce Lookbook | 1.0.0 |

Los artefactos están en `.local/h11-release/1.0.0/`, ignorados por Git; se regeneran desde las fuentes. El material de entrega `support/` mantiene documentación y preflight/inicialización separados del webroot. Los ZIP no contienen WordPress, WooCommerce, MP, DB, credenciales, usuarios/pedidos/legales QA, logs, backups, uploads, fotografías editoriales DEMO, patrones hero/story, showcase ni preset ORIGEN CASA. Los originales DEMO siguen en el repositorio, optativos y fuera de esta distribución.

Producción utiliza WordPress/WooCommerce oficiales, PHP y DB del hosting, instaladores nativos/WP-CLI y sus propios medios/configuración. **No utiliza Compose en producción**. `initialize.php` es opt-in y sólo acepta una instalación nueva: comprueba usuarios/contenido/historia/ajustes, mantiene noindex/Coming Soon y apaga gateways. El contenido comercial se configura desde cero; no se entrega una copia de las demos.

El laboratorio usa WP 7.1.3, WooCommerce 11.2.1, PHP 8.4.27, MariaDB 10.11.19 y WP-CLI 2.12.0, con imágenes fijadas. Tres proyectos/volúmenes propios: fuente `qa` :8184, destino `restore` :8185 y repetición sin fixture :8188. Mailpit sólo loopback :8186/:8187/:8189; MariaDB sin puerto publicado. El código funcional se instaló desde los ZIP, **sin bind del repositorio**. Los únicos montajes de herramientas son de laboratorio. [Infraestructura](evidence/h11/infrastructure.json).

La repetición `install -Instance repeat` completó de principio a fin con el script corregido, sin importar catálogo, usuarios ni historial de otra instalación. Se verificó PHP **web**, no sólo CLI: 256 MiB, errores ocultos, logging, OPcache, uploads escribibles, editor desactivado y archivos/directorios de código no escribibles para el proceso web. Los permisos por propietario root/grupo del laboratorio simulan un canal controlado de despliegue; no prueban separación de usuarios en un hosting compartido. WP-CLI puede sobrescribir memoria/display_errors, por lo que su diagnóstico no reemplaza el SAPI web.

## 5. QA representativa y recuperación

| Evidencia | Resultado y límite |
| --- | --- |
| [Navegador fuente](evidence/h11/browser.json) y [checkout nativo inicial](evidence/h11/browser-native-checkout.json) | PASS: Home/catálogo/variable/swatches/Lookbook, Mini-Cart, Carrito/Checkout Blocks, BACS/HPOS, cuenta/dirección y permisos/gestión legal. Se conserva evidencia del submit inicial para que una repetición sin duplicar pedido no lo oculte |
| [Emails](evidence/h11/emails.json) | PASS: 21 mensajes sólo capturados, nuevo pedido/BACS/Processing/Completed/cancelación/reembolso, recuperación de contraseña y constancia/notificación legal; todos sintéticos. No certifica recepción SMTP real |
| [Cron y Action Scheduler](evidence/h11/operations.json) | PASS: limpieza nativa de transient vencido y sesión QA vencida. La instalación nueva requirió completar la migración nativa de Action Scheduler antes del runner por grupo. No valida cron autónomo del hosting |
| [HTTP/caché](evidence/h11/http-cache.json) | PASS: privados y Store API no-store/private, noindex/robots/sitemap, cabeceras, bloqueo de archivos sensibles y ausencia del showcase/imagen DEMO excluidos. Sin CDN/LiteSpeed/Redis ni TLS remoto probado |
| [Origen](evidence/h11/source-snapshot.json) y [backup](evidence/h11/backup.json) | DB completa y archivos copiados con web quiescida, SHA-256, sólo QA privada. Sin cifrado ni copia externa productiva |
| [Código antes](evidence/h11/code-before.json) y [después](evidence/h11/code-after.json) | PASS: reinstalación de los mismos ZIP sin importar DB, incluyendo pedido QA **36 creado después del backup**. Digests de negocio idénticos. No afirma haber probado una migración de versiones distintas |
| [Guard de destino](evidence/h11/guards.json) | PASS: cliente QA nuevo hace rechazar el destino; tras retirar sólo ese probe con identidad comprobada, acepta el estado vacío. Tar exige rutas seguras y archivos/directorios regulares |
| [Restauración](evidence/h11/restore.json), [recuperación](evidence/h11/recovery.json) y [navegador del destino](evidence/h11/restore-browser.json) | PASS: 70 checks de integridad y 74 de navegador; mismo digest del punto original antes de logins. Cliente recupera acceso, pedido/dirección; gestor abre HPOS y solicitud legal/estado/asociación/eventos. Las sesiones cambian legítimamente después de autenticarse; no se guardaron formularios comerciales. No se restauró sobre el origen con actividad posterior |

Navegador: cero errores JS no controlados y cero recursos locales fallidos. Los intentos nativos de emojis a `s.w.org` se registraron y bloquearon; no se promete ausencia universal de terceros. Mercado Pago no pidió recursos. Se revisaron capturas de Home móvil/escritorio, producto y Lookbook; el contenido es inequívocamente DEMO. No se repitieron las miles de pruebas históricas.

Los helpers HTTP temporales se retiraron, con 404 comprobado. Al cerrar se detienen únicamente los tres proyectos H11, conservando sus volúmenes/secretos/backup privados. Las instalaciones históricas y contenedores ajenos no recibieron comandos de stop/reset/restore.

El backup completo incluye HPOS y tablas auxiliares, posts/meta, usuarios, catálogo/taxonomías, options, stock, sesiones, Action Scheduler, las tres tablas legales y archivos/configuración. La comparación lógica usa tablas comerciales, IDs/estados, opciones seleccionadas y hashes de medios; excluye los cambios efímeros de sesiones/colas/logs/transients. La copia privada conserva esos datos aunque no se compare su actividad posterior byte a byte.

Se recuperaron **2 productos, 2 variaciones, 3 usuarios, 4 pedidos y 1 refund, 2 solicitudes legales con 9 eventos y la imagen original con 6 derivados**. El backup QA tardó 10,81 s (DB 1.083.325 bytes y archivo comprimido 50.725.237 bytes); la fase de restauración tardó 21,70 s antes de QA posterior. Son tiempos del equipo y conjunto locales, no objetivos prometidos para un cliente. El pedido 36 posterior al backup se conserva en el origen tras reemplazar código y no aparece en la restauración al punto anterior: justamente por eso no debe usarse ese backup para sobrescribir el origen activo.

Incidencia resuelta del laboratorio: el cliente MariaDB 11.8.9 de la imagen WP-CLI exigía TLS frente al servidor local sin TLS. El export acepta `--skip-ssl`; el import WP-CLI 2.12 hace una consulta preparatoria que no lo propaga. El import del ensayo usa el cliente MariaDB nativo, leyendo el secreto desde archivo hacia entorno, sin contraseña en argumentos/salida. El primer export fallido reinició la web en `finally`; los import fallidos dejaron el destino detenido. **Esta excepción de TLS se limita a la red local aislada y no se traslada a producción.**

El clon retiene configuración/salts propios de destino para no reutilizar credenciales de otro entorno; se recuperan los datos y el código. Formularios/sesiones/tokens previos pueden quedar invalidados. En recuperación de la misma tienda conservar salts del conjunto salvo rotación deliberada por incidente; no prometer continuidad de formularios abiertos después de una rotación.

## 6. Operación futura, responsables y bloqueos

| Área | Preparado / pendiente | Responsable y criterio de cierre |
| --- | --- | --- |
| Hosting | Comparación preparada; contrato y prueba real pendientes | Titular aporta factura/hPanel; técnico verifica PHP web/CLI, DB, recursos, cron, caché, SSL, SSH y restore en destino autorizado |
| Backups | Procedimiento y ensayo local; operación externa pendiente | Técnico/proveedor y suplente acordados: DB horaria/RPO orientativo ≤1 h, archivos diarios, completo semanal, retención y copia externa cifrada. RTO 4–8 h orientativo sujeto a cobertura/ensayo, sin SLA implícito |
| Actualizaciones | Reemplazar código, vaciar cachés y comprobar esquema compatible | Técnico: backup actual y staging; jamás empujar DB de staging sobre pedidos recientes. Cambios de esquema/versiones requieren ensayo propio |
| Recuperación | Restaurar aislado, proteger primero estado actual y conciliar posteriores | Técnico + comercio: inventario de pedidos/pagos/stock/cuentas/solicitudes posteriores y conciliación. Un backup antiguo no contiene actividad posterior |
| Email | Un transporte SMTP/API futuro; SPF/DKIM/DMARC y matriz preparados | Titular elige proveedor/remitentes/destinatarios y autoriza prueba; técnico comprueba recepción autenticada, límites/rebotes/alertas/privacidad. Sin emails reales durante H11 |
| Dominio/DNS/HTTPS | Procedimientos NIC/Hostinger/otros y preservación de MX/TXT existentes | Titular autoriza cambio concreto; técnico inventaría zona, prueba TLS/canonical/www/IPv6 y prepara reversión. No transferencias ni modificaciones realizadas |
| Caché | Exclusiones privadas y Store API comprobadas en origen local | Técnico valida caché real del proveedor, cookies y sesiones; un único sistema elegido. OPcache local sí, object cache sólo si disponible; sin plugins superpuestos por defecto |
| Seguridad | Roles mínimos, MFA/canales, parches, logs privados, alertas e incidentes definidos | Cliente/proveedor/técnico asignan cuentas, accesos, aislamiento y cobertura. No se ha configurado MFA/monitoring remoto ni offsite en una cuenta real |
| Mercado Pago | **Bloqueo intacto**, nueva instalación sin MP | Proveedor + técnico: matriz [H11 MP](h11-mercadopago-review.md) y pruebas oficiales. 8.9.7 anunciada no cierra autenticidad/replay/temporalidad/importe/moneda/asociación |
| Legal/comercial | Políticas/datos DEMO sólo de prueba | Titular/asesores: nombre/razón social/CUIT/domicilio/contacto/Data Fiscal, impuestos/facturación, envío/cambios/garantías/arrepentimiento/privacidad/retención y canales atendidos. Aprobación fechada |
| SEO/analítica | DEMO noindex; activación futura preparada | Titular autoriza cuentas/indexación; técnico valida canonical/sitemap/metadatos/structured data/OG. Sin Search Console, analítica ni campañas conectadas |
| Mantenimiento | Plan diario/semanal/mensual e incidentes documentado | Cobertura y suplente por contrato; técnico atiende infraestructura, comercio atiende ventas/stock/pagos/reclamos. No se promete soporte 24/7 |

El [runbook](h11-deployment-runbook.md) contiene los pasos de instalación, actualización, recuperación y operación. El [checklist/playbook](h11-client-launch-checklist.md) cubre desde el presupuesto aceptado hasta accesos, capacitación y mantenimiento, con responsabilidades y autorización por alcance.

## 7. Clasificación separada

| Área | Estado H11 | Fundamento |
| --- | --- | --- |
| A. Código de la plantilla | **READY** | Código funcional/ZIP limpios y QA representativa; no incluye aprobación de MP |
| B. Instalación local reproducible | **READY** | Instalación nueva por ZIP y repetición completa con script final |
| C. Hosting seleccionado y validado | **PENDIENTE DE DATOS** | Contrato Premium y prueba del host candidato aún no verificados |
| D. Staging remoto | **NO REALIZADO** | No hubo entorno remoto autorizado |
| E. Seguridad y backups | **CONDICIONAL** | Controles/procedimientos locales; cifrado/offsite/MFA/monitoring y recuperación remota pendientes |
| F. Emails reales | **NO CONFIGURADOS** | Sólo Mailpit; no autorización de envío real |
| G. Métodos de pago reales | **NOT READY** | BACS ficticio; MP bloqueado; sin método real aprobado |
| H. Cumplimiento comercial/legal del cliente | **PENDIENTE DE DATOS** | Sin comercio real identificado, datos y políticas definitivas/aprobadas |
| I. Autorización final de lanzamiento | **NO AUTORIZADO** | Prohibición expresa de publicar/contratar/DNS/cobros sin aprobación |

## 8. Acciones manuales y límites de entrega

1. Completar cuando esté disponible la ficha real de hPanel/factura, sin compartir secretos. Comparar presupuesto total de alta/renovación/upgrade, impuestos y extras; no contratar desde este informe.
2. Elegir el cliente/modelo de titularidad/aislamiento, responsable operativo, cobertura y presupuesto real. Reunir identidad, catálogo, medios, condiciones, datos fiscales y revisión del titular/asesores.
3. Autorizar separadamente un staging remoto restringido; ejecutar allí compatibilidad, restore, cron sin visitas, caché y TLS reales. El laboratorio no certifica LiteSpeed ni recursos del Premium heredado.
4. Elegir y autorizar proveedor/dominio/destinatarios para correo real; resolver pagos reales en un trabajo separado, conservando el bloqueo MP.
5. Revisar H11 antes de integrar su rama a main. Aprobar cada publicación/DNS/pago/envío concreto sólo cuando sus gates estén cerrados.
6. Si se desea recuperar Gordon, solicitar y aprobar una prueba global controlada tras revisar el fallo de Docker; no se reactiva como parte del lanzamiento de una tienda.

No hubo publicación, compras, cambios DNS, pagos reales, envío externo ni cuentas Google conectadas. El entorno local HTTP no demuestra Secure cookies/TLS, entregabilidad, rendimiento bajo carga, límites contractuales ni aislamiento entre clientes del proveedor. Los tiempos locales del ensayo no son un RTO productivo.

**H11 TECHNICAL READY.**

**PRODUCTION NOT READY.**
