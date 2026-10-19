# Evidencia visual H7.2

PNG locales del 19/10/2026, sólo pedidos nuevos sintéticos y `example.test`. Sin claves de pedidos, cookies, credenciales, enlaces de restablecimiento visibles ni datos de compradores reales. HTML/raw y rutas privadas permanecen bajo `.local/local/h7-2/`, ignorado por Git.

| Archivo | Evidencia |
| --- | --- |
| [email-bacs-desktop.png](email-bacs-desktop.png) | Email nativo on-hold #429 en viewport 900px, contenido máximo 600px |
| [email-bacs-mobile.png](email-bacs-mobile.png) | Mismo mensaje, 390px |
| [email-processing.png](email-processing.png) | Pedido confirmado, preparación pendiente |
| [email-completed.png](email-completed.png) | Finalización de gestión, sin promesa de entrega |
| [email-refund-partial.png](email-refund-partial.png) | Registro parcial manual #435 |
| [email-refund-total.png](email-refund-total.png) | Total manual #435, cantidades/importes/envío |
| [email-admin-new.png](email-admin-new.png) | Aviso al comercio, productos/variantes/total |
| [mailpit-bacs-ui.png](mailpit-bacs-ui.png) | Captura real Mailpit con From/To/Reply-To/asunto y preview |
| [account-on-hold-mobile.png](account-on-hold-mobile.png) | Mi Cuenta #425 antes de confirmación, BACS nativo coherente |
| [order-received-mobile.png](order-received-mobile.png) | Checkout invitado #424, transferencia pendiente, cupón y envío |

Los mensajes se capturaron en el momento de cada evento. El pedido #429 después terminó completado; eso no modifica un email histórico. #424 permanece en espera para revisión. Las previews completas de doce tipos de email, 24 PNG, están disponibles en `.local/local/h7-2/previews/`; las capturas operativas de cuenta y administrador están en `browser/`.

Validación de layout: Chromium, Firefox y WebKit, 320/390/600/900px; texto normal con contraste de al menos 4,5:1. No es una certificación de render ni entrega real en Gmail/Outlook. Las URLs localhost son exclusivamente locales.
