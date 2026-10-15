# Assets editoriales H4

Se aplicaron los skills `frontend-design` de Vercel y `imagegen`. Frontend-design se leyó desde la copia privada `.local/skills/frontend-design/SKILL.md`, fijada en H3 al [commit cf865e94 de vercel-labs/open-agents](https://github.com/vercel-labs/open-agents/blob/cf865e94de7729751c747171785b6ce57e7b178c/.agents/skills/frontend-design/SKILL.md). La dirección editorial textil, las fuentes, los tokens y los controles de H3 se conservaron.

## Archivos del theme

| Archivo | Dimensiones | Peso | Uso |
| --- | --- | ---: | --- |
| `commerce-base/assets/images/editorial-specimen.webp` | 960×1200 | 122150 bytes | Hero y Conocenos; imagen generada propia de H3 |
| `commerce-base/assets/images/editorial-detail.webp` | 1536×1024 | 176374 bytes | Lookbook H4 |
| `commerce-base/assets/images/editorial-detail-small.webp` | 768×512 | 50370 bytes | Variante responsive del lookbook |
| `commerce-base/assets/images/product-placeholder.svg` | 640×800 | SVG H3 | Fotografía pendiente; reemplaza sólo el fallback estándar de WooCommerce |

La imagen nueva se generó con el tool integrado `imagegen`, sin API key ni CLI alternativo. El PNG seleccionado se convirtió a WebP con Pillow (calidad 82, método 6); se produjo una variante de 768px a calidad 80. Los archivos finales están dentro del repositorio y no dependen de URLs remotas. No se reemplazó una fotografía comercial ni se modificó media, stock o datos de productos. El fallback preserva cualquier imagen de placeholder propia configurada por el comercio.

Son fotografías editoriales demo. H5 podrá sustituirlas o mantenerlas como material institucional; las fotografías definitivas de productos siguen pendientes. No representan una ficha técnica, fabricación real ni un servicio comercial comprobado.

## Prompt final

```text
Use case: photorealistic-natural. Asset: wide editorial photograph for the fictional urban apparel demo TRAMA, a refined textile brand website lookbook, no final product catalog. Landscape composition 3:2. Close natural editorial photograph of an anonymous adult sitting sideways on a low warm concrete ledge, cropped above the shoulders and below the knees, relaxed ecru heavyweight cotton t-shirt, dark charcoal loose trousers, a folded olive overshirt beside them. Strong diagonal late-afternoon sunlight and architectural shadows across warm rough limestone, tactile visible cotton and woven fabric. Quiet contemporary Buenos Aires urban atmosphere. The body occupies the right two-thirds and shadowed stone occupies left third. Muted olive, cream, charcoal, subtle film grain, realistic texture, restrained high-end fashion editorial, unposed and human. No words, letters, logos, watermarks, graphics, collage, brands, UI, ornate props or extra limbs. Save a project image suitable for optimization as editorial-detail.webp.
```

El resultado se inspeccionó visualmente antes de incorporarlo. No hay texto/logos, sujetos identificables ni material tomado de otra marca.

## Entrega y edición

Las imágenes son bloques `core/image` editables. Las dimensiones y atributos de entrega se añaden al render para los assets locales del theme; el HTML guardado conserva el serializer nativo de Gutenberg. El hero usa `loading="eager"` y `fetchpriority="high"`. El lookbook usa lazy loading, `srcset` y `sizes`; las fotografías conservan su color original en claro/oscuro. No hay filtros de color sobre ellas.
