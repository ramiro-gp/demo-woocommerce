# D1 — Revisión independiente del aprovisionamiento

Fecha: 24/10/2026, Argentina. Revisión de fuentes; no inspección de Hostinger ni ensayo remoto. Archivos revisados: `scripts/d1.ps1`, `compose.d1.yaml`, `deployment/demo-lab/baseline.php` y `deployment/demo-public/setup.php`, junto con las guardas de los importadores H4/H5/H5.1/H10 y el módulo legal de Commerce Core.

## Hallazgos corregidos en esta revisión

| Hallazgo | Corrección |
| --- | --- |
| El CLI montaba el repositorio completo y podía leer `.local` histórico, incluidos secretos/backups de otras instalaciones | Sustituido por mounts de deployment, tests y la distribución limpia H11 1.0.0. Conserva sólo los mounts específicos de fixtures, herramientas y estado privado de la marca |
| `up --wait` sólo esperaba que web estuviera running, sin garantizar que el entrypoint hubiera escrito WordPress/configuración | Healthcheck explícito de `wp-config.php` y `wp-load.php` antes del paso CLI de instalación |
| Una copia de todo `assets/images` podía incluir archivos no versionados | Inventario con `git ls-files`; copia de archivos individuales con ruta/extensión permitidas y rechazo de reparse points |
| El manifiesto sólo comprobaba seis entradas | Validación de distribución limpia/versión/flags, seis slugs únicos esperados, nombre ZIP y formato de SHA-256 antes de instalar |
| La consulta normal de pedidos puede omitir estados o almacenamiento alternativo | Baseline y setup comprueban también filas de HPOS, artículos y posts de pedido/refund/placeholders; exigen un solo administrador ficticio |

Se informó aparte al responsable del módulo el conflicto entre el entorno público `staging` y las guardas legales históricas de `_commerce_legal_demo`: las pruebas sólo en `local` no bastan para asegurar enlaces/REST de las páginas explicativas fuera de localhost. Su resolución y verificación corresponden a la revisión del módulo y al informe de validación D1.

## Verificaciones realizadas aquí

- Parser PowerShell sobre `scripts/d1.ps1`: sin errores de sintaxis.
- `docker compose ... -f compose.d1.yaml config --quiet`: sin errores de configuración.
- `git diff --check`: sin errores.
- No se ejecutaron `install`, `setup.php`, cambios de modo ni recreación de contenedores durante esta revisión. El lint PHP por contenedor no se ejecutó desde este agente por restricción de acceso al motor; el informe de validación registra las verificaciones funcionales y lint efectivamente ejecutados por el responsable del laboratorio.

## Límites mantenidos

El instalador rechaza un proyecto/volúmenes D1 ya existentes y no borra ni resetea. Después de un fallo se preservan `.local/d1/<marca>` y los volúmenes para inspección; no se promete reanudar automáticamente. No incluye despliegue, borrados, túneles ni operaciones sobre TRAMA/ORIGEN históricas. Las adaptaciones de guardas H4/H5/H5.1 se generan exclusivamente como copias privadas para los orígenes locales D1 fijados; no modifican los scripts históricos ni se trasladan al procedimiento remoto. Los seis ZIP aprobados sólo se leen y verifican.

Los binds del laboratorio y los usuarios DB separados prueban separación operacional local, no aislamiento de kernel/UID entre sitios en un hosting compartido. El riesgo real de la cuenta Premium continúa pendiente de verificación y aceptación según el plan/runbook.

**PUBLIC DEPLOYMENT: NOT PERFORMED.**
