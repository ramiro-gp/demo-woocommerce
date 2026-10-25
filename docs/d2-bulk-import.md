# D2 — Importación nativa de productos e inventario

Fecha: **25/10/2026, Argentina**. Resultado local: **BULK PRODUCT IMPORT: READY** y **BULK STOCK UPDATE: READY**, con revisión previa de archivos y coordinación de movimientos de stock. No es una sincronización automática ni una certificación de rendimiento remoto.

## Entorno y aislamiento

Se creó `commerce-d2-bulk` en `localhost:8196`, Mailpit en `localhost:8197`, con volúmenes nuevos `commerce-d2-bulk_db`, `_wp` y `_mail`, DB `d2_bulk`, usuario DB propio y contraseñas aleatorias ignoradas en `.local/d2`. WordPress se instaló vacío: **no se importó ninguna base de datos**. Se copiaron sólo código, traducciones y una fotografía pública del laboratorio limpio D1, montado como sólo lectura. Clientes/pedidos/solicitudes no se transfirieron. Las bases originales `8080/8082`, las demos `8190/8191` y los laboratorios vendedores `8192/8193` no reciben este catálogo.

| Componente instalado | Versión observada |
| --- | --- |
| WordPress | 7.1.3 |
| WooCommerce | 11.2.1 |
| PHP | 8.4.27 |
| MariaDB | 10.11.19 |

El usuario de todas las importaciones/exportaciones UI tiene **únicamente `shop_manager`**. El administrador se utilizó para instalar/configurar el laboratorio vacío, nunca para sortear permisos del importador. Los permisos nativos `manage_woocommerce`, `edit_products`, `import`, `export` están presentes; no se añadieron capacidades ni roles. Evidencia: [estado vacío y rol](evidence/d2/facts-empty.json), [resultado inicial](evidence/d2/bulk-initial.json).

## Cantidades, tiempos y resultados

Los tiempos se midieron desde Ejecutar el importador hasta su pantalla final; excluyen abrir Excel, preparar archivos, subirlos y revisar el mapeo. Las operaciones se hicieron en serie por la UI nativa, incluidas sus peticiones AJAX por lotes.

| Operación | Filas / resultado | Tiempo local |
| --- | --- | --- |
| Alta inicial | 120 simples + 20 padres variables + 80 variaciones = **220 filas**, 140 productos visibles y 200 SKU que administran existencias | **18,892 s** |
| Actualización exclusiva `SKU,Stock` | **200 SKU actualizados**, sin altas ni duplicados | **6,155 s** |
| Actualización de precios + stock | Un simple y una variación; precios 12345.67 y 23456.78 guardados | **1,579 s** |
| Archivo con SKU inexistente | 1 omitido, sin creación | 1,563 s |
| Dos filas de alta con SKU ya existente | 2 omitidas; total conservado | 1,506 s |
| SKU repetido en una actualización | 2 actualizaciones; la última cantidad prevalece | 1,586 s |
| Archivo parcialmente incorrecto | 2 actualizaciones y 1 fallo por tipo inválido | 1,850 s |
| Stock no numérico `abc` | Se acepta y convierte a **0**; riesgo confirmado | 1,825 s |
| CSV anterior a un movimiento de stock | Sobrescribe la reducción nativa posterior | 1,687 s |
| Reposición final de los 200 valores de prueba | Restablece las cantidades de la fixture después de los casos de diagnóstico | **4,943 s** |

La reposición final sólo afectó al laboratorio sin ventas reales. Conservó los dos precios modificados en la prueba de precios. El resultado final vuelve a tener los 200 valores esperados, 140 productos/80 variaciones y ningún SKU duplicado. El código de prueba genera fixtures y automatiza el navegador; **no implementa otro importador**. No se instaló ningún plugin de importación.

### Comprobaciones de conservación

La prueba comparó todos los registros antes/después del archivo de stock: ID, tipo, padre, nombre, descripción, estado de publicación, precio normal, precio de oferta, ID de imagen principal, galería, categorías y atributos. **Todos permanecieron iguales**. Sólo se excluyeron de esa comparación cantidad, administración de stock y disponibilidad, porque son precisamente el cambio solicitado. Las imágenes ya estaban en Medios y el CSV las identificó por nombre de archivo; no hubo descargas externas durante la importación. No se midió el coste de descargar fotografías nuevas de internet.

Cada padre tiene cuatro variaciones con SKU y stock independientes. Las cantidades cero quedaron `outofstock`, tanto en simples como en variaciones. Se probaron tres categorías para simples más Indumentaria para variables, precios diferentes y algunas ofertas. Los atributos de las 80 variaciones se comprobaron contra sus padres. Además se seleccionó Talle S en la ficha del producto importado, se resolvió su ID correcto y se añadió al carrito; la Store API rechazó otra variación agotada. No se creó un pedido ni se modificaron existencias por estas pruebas de carrito. [Datos iniciales](evidence/d2/facts-initial.json), [datos después del stock y antes de cambiar precios](evidence/d2/facts-before-prices.json), [aserciones de stock](evidence/d2/bulk-stock.json), [precios](evidence/d2/bulk-prices.json), [reposición final](evidence/d2/bulk-final-stock.json), [QA de variaciones importadas](evidence/d2/bulk-variation-qa.json).

## Errores y límites que debe conocer el vendedor

| Caso real probado | Comportamiento observado / acción necesaria |
| --- | --- |
| SKU inexistente + Actualizar existentes | Omite la fila. Revisar informe; no crea el artículo |
| SKU existente + modo alta | Omite para no duplicar |
| SKU repetido dentro de una actualización | **No lo rechaza**. La última fila gana; revisar duplicados antes de importar |
| `Stock=0` | Cantidad cero y agotado |
| `Stock=abc` | **Se guarda cero sin error**. Validar todos los enteros antes del CSV |
| Encabezado `Stok` | No se asigna automáticamente a stock. Se detuvo la prueba en el mapeo sin ejecutar |
| Punto y coma leído como coma | No produce las dos columnas esperadas. Se detuvo antes de modificar datos |
| Tipo no admitido entre dos filas válidas | Las dos filas válidas se guardan; la fila rechazada conserva sus datos |
| Archivo parcialmente inválido | No hay transacción de archivo ni rollback global. Exportar estado actual y corregir selectivamente |
| Ventas/movimientos posteriores a exportar | El valor importado **sustituye** al actual. No se concilian pedidos ni diferencias |

La simulación de venta usó `wc_update_product_stock(..., 2, 'decrease')`, la función nativa de inventario, y **no creó un pedido real**. El CSV antiguo restauró el valor anterior a ese descuento. No es un test de concurrencia simultánea ni de reserva de pedidos: demuestra la sobrescritura absoluta que debe evitar el procedimiento comercial. [Evidencia](evidence/d2/bulk-stale.json).

READY significa que el flujo nativo sirve para esta tarea con un archivo validado, un respaldo reciente y una ventana acordada sin movimientos. No significa que WooCommerce rechace todos los archivos erróneos ni que permita sincronizar un Excel antiguo mientras se reciben ventas. No se propone otro backoffice ni un importador XLSX propio.

## Archivos para clientes y uso desde Excel

Las dos plantillas finales son CSV UTF-8 con BOM y encabezados ingleses exactos reconocidos por 11.2.1. Se entregan sin datos QA:

- [Alta de productos](templates/woocommerce-products.csv): tipo, SKU, nombre, estado, descripción, precios, categorías, stock, imágenes, padre y primera familia de atributo.
- [Inventario](templates/woocommerce-stock.csv): sólo `SKU,Stock`.

Las instrucciones para simples, variables, Excel en español, ceros iniciales, delimitador regional, exportación previa, revisión de errores y recuperación están en [Carga masiva desde Excel](d1-seller-guide.md#carga-masiva-desde-excel), con capturas del usuario Shop Manager. Las columnas de estado y relaciones son `Published`, `Type`, `Parent`; no se introducen campos ficticios. En productos variables el padre precede a sus filas `variation`; cada una tiene SKU, precio, atributo y stock propios. Un padre no debe figurar en la actualización de stock si las variaciones administran sus propias cantidades.

## Recursos medidos y alcance

Durante los casos de error se tomaron 15 muestras Docker, conservadas en [resources.json](evidence/d2/resources.json). Máximos observados por contenedor: web **259,9 MiB / 74,24% CPU**, DB **105,2 MiB / 23,89% CPU**, Mailpit **9,316 MiB / 2,63% CPU**. Estos máximos no necesariamente ocurrieron simultáneamente. Docker reportó al final de esa ventana I/O acumulado web `0B / 127MB`, DB `5,41MB / 106MB`, Mailpit `0B / 500kB`; **no son tasas ni IOPS ni incrementos atribuibles a un archivo**. El pico PHP mostrado en los snapshots pertenece al proceso CLI que lee hechos, no al request de importación.

No se capturó el pico completo de recursos de la importación inicial; no se deduce de las muestras posteriores. El runtime dispone de aproximadamente 15,2 GiB compartidos por Docker y otros servicios. Esto **no equivale a 2 GB/1 núcleo de Hostinger**, ni predice duración o visitas allí. Se propone un primer lote remoto de **25 filas totales**, un solo importador/operador, siempre con autorización específica de la prueba remota. Subir a 50 sólo después de revisar errores, tiempos y gráficos del plan sin afectar sus ocho sitios. Una familia variable debe permanecer en orden padre/variaciones y caber en el lote; no se ejecutó ninguna carga remota.

## Reproducción y estado del laboratorio

Desde PowerShell en el repositorio, con acceso al motor Docker y Playwright instalado:

```powershell
./scripts/d2.ps1 -Action install
$env:NODE_PATH='<node_modules del runtime con Playwright>'
$env:D2_STAGE='initial'; node tests/d2-bulk.cjs
$env:D2_STAGE='stock'; node tests/d2-bulk.cjs
$env:D2_STAGE='prices'; node tests/d2-bulk.cjs
$env:D2_STAGE='edges'; node tests/d2-bulk.cjs
$env:D2_STAGE='stale'; node tests/d2-bulk.cjs
$env:D2_STAGE='final-stock'; node tests/d2-bulk.cjs
$env:D2_STAGE='variation-qa'; node tests/d2-bulk.cjs
```

`install` rechaza una instancia existente. Las fases de diagnóstico cambian únicamente SKU `D2-*` en el proyecto marcado `native-csv-v1`; no se deben repetir altas sobre otro catálogo. Al terminar, el laboratorio queda **detenido**, con volúmenes conservados. `scripts/d2.ps1 -Action start` lo reabre para revisión; `-Action stop` detiene sólo este proyecto. No hay borrado automático ni comando que elimine volúmenes de otras instalaciones. Las credenciales y el CSV de exportación completo quedan en `.local/d2/private`, nunca en Git.

La preparación tuvo correcciones de automatización: se sustituyó el bootstrap inicial por `wp core install` y se precisó el selector del checkbox, que también tiene un input oculto. No se amplió ningún permiso para resolver esos errores. Las ejecuciones finales indicadas en los informes pasaron.

Fuentes oficiales consultadas el 25/10/2026: [importador/exportador nativo](https://woocommerce.com/document/product-csv-importer-exporter/) y [requisitos del servidor](https://woocommerce.com/document/server-requirements/). Las afirmaciones de comportamiento y tiempos de este informe proceden de la versión instalada y los ensayos locales, no de extrapolaciones de esas páginas.
