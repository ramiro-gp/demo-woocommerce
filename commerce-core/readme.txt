=== Commerce Core ===
Requires at least: 6.5
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.1.2
License: GPL-2.0-or-later
Text Domain: commerce-core

Optional conveniences over native WooCommerce accounts and consumer rights requests.

== Description ==

V1 adds an explicit billing-to-shipping copy action to My Account's shipping
address form. Existing form values require confirmation before replacement.
Customers review and edit the result, then use WooCommerce's native Save button.
Later billing edits do not synchronize shipping. No checkout control is added.

Compatible standard fields only: name, company, country, state, city, postcode,
street, second address line, and phone when both native schemas expose it.
Email and arbitrary custom fields are never mapped. Additional fields remain
manual and WooCommerce validates the submitted form.

Tested with WooCommerce 11.2.1, HPOS and Cart/Checkout Blocks. No theme dependency.
The default UI copy is Spanish and uses the commerce-core text domain.

== Installation ==

1. Install and activate WooCommerce.
2. Upload this directory under wp-content/plugins/ or install its ZIP.
3. Activate Commerce Core in Plugins.

Legal v1.1 adds separate request, event and short-lived abuse-limit tables on the
first admin/CLI initialization. No customer/order tables are duplicated. There is
no uninstall data deletion. Deactivation preserves requests and native addresses.

== Optional module ==

Disable from a site-specific plugin loaded before plugins_loaded, or an MU plugin:

add_filter('commerce_core_address_copy_enabled', '__return_false');

Use a theme for presentation. The section class is commerce-core-address-copy.
Without JavaScript the normal WooCommerce form remains available for manual entry.

Legal can be disabled with commerce_core_legal_enabled. Configure published pages
and authentic identity in Settings > Identidad y legales. Consumer cases use
WooCommerce > Arrepentimiento and require manage_woocommerce. Local DEMO setup is
explicit and guarded; it is never an activation seed for a real commerce.

== Address-copy privacy ==

Reads only the authenticated customer's saved billing address. No custom endpoint,
tracking cookie, address logs, browser storage, external request or extra retention.
WooCommerce owns validation, CSRF protection, customer selection and persistence.

== Legal privacy ==

Requests store name, contact email, optional unverified reference/message, code,
management state and event history. No public order lookup or automatic order,
stock, payment or refund writes. A session cookie binds the public form; temporary
HMAC abuse buckets do not store raw IPs. Native WordPress exporters are extended;
erasure reports retained cases for legal review. Read docs/h8-2-legal-consumer-rights.md
and docs/h8-client-legal-setup.md in the project before configuring a real client.
