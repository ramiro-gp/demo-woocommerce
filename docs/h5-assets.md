# Assets de catálogo H5

Se aplicaron frontend-design (Vercel, copia privada fijada en H3) e imagegen. Las 32 fotografías se generaron para esta demo mediante el tool integrado image_gen; no se usó API key, CLI de generación ni fotografías descargadas de marcas. Son representaciones ficticias de producto, sin logos ni textos.

Dirección: flat lay o detalle de producto, fondo de piedra gris cálida, luz lateral suave y tejido visible. Formato final 4:5. Ocho prendas tienen una foto por color; las otras ocho piezas incluyen vista principal y detalle. La galería WooCommerce referencia estos medios reales.

Los prompts completos de cada asset están en [image-prompts.json](../demo/catalog/image-prompts.json). El manifiesto [image-manifest.json](../demo/catalog/image-manifest.json) registra archivo, dimensiones, bytes, SHA-256, generador y nombre del PNG maestro. Los PNG originales permanecen privados bajo el directorio generated_images de Codex; el sitio depende exclusivamente de las fuentes WebP versionadas en [demo/catalog/images](../demo/catalog/images).

Exportación con Pillow: corrección de orientación, RGB, ajuste mínimo a 1000×1250, WebP calidad 82/método 6. Las 32 fuentes suman 5.216.026 bytes. No se versionan PNG maestros, thumbnails ni uploads. WordPress genera srcset y tamaños al importar: thumbnail de producto 600×750 y miniatura de galería 80×100. Home utiliza cuatro thumbnails reales; el resto del catálogo conserva lazy loading. La imagen principal de producto recibe la prioridad nativa de WooCommerce y sizes contextual.

El script [prepare-h5-images.py](../scripts/prepare-h5-images.py) reproduce la exportación a partir del mapa privado .local/local/h5/generated-map.json. Para recrear el catálogo no se necesita regenerar imágenes ni acceder a ese mapa: basta con importar las fuentes WebP incluidas mediante scripts/h5.ps1. Para una generación nueva, conservar un mapa nuevo y registrar prompts/procedencia antes de sustituir assets deliberadamente.

Se inspeccionaron los resultados y las capturas de catálogo/galería en claro y oscuro. Las fotografías conservan su color, sin filtros de inversión. Las imágenes editoriales H3/H4 siguen en su ubicación original; no se regeneraron ni se rediseñó Home.
