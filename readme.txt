=== Orpot Mexico Woo Reglas ===
Contributors: Orpot
Author: Daniel Lopez
Author URI: https://orpot.com
Tags: woocommerce, shipping, locations, states, municipalities, postcodes
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 8.2
Stable tag: 0.2.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Gestor avanzado de cobertura de envíos para WooCommerce por Estado, Municipio y Código Postal.

== Description ==

Orpot Mexico Woo Reglas te permite definir cobertura de envío por jerarquía: Estado -> Municipio -> Código Postal.

Características principales:

* Selectores guiados para estado, municipio y código postal en el checkout
* Reglas de envío gratis, pagado o bloqueado
* Coincidencia exacta por estado, municipio, código postal o rango de códigos postales
* Vista previa de reglas para probar ubicaciones antes de publicar

== Installation ==

1. Sube la carpeta del plugin a `/wp-content/plugins/admbike-woo-locations/`.
2. Activa el plugin desde la pantalla de Plugins.
3. Configura primero los estados desde `Orpot Woo Locations`.
4. Agrega los municipios y define su cobertura postal.
5. Carga los códigos postales si necesitas coincidencia exacta.
6. Crea reglas de envío y activa el método de envío en WooCommerce.

Tip: si no ves municipios o códigos postales en los desplegables, revisa que el nivel anterior esté activo.

== FAQ ==


= ¿Este plugin requiere WooCommerce? =

Sí. WooCommerce debe estar activo.


= ¿Puedo usarlo sin cobertura personalizada? =

Funciona mejor cuando defines estados, municipios y códigos postales.


= ¿Por qué no veo mi estado o municipio? =

Revisa que el estado esté activo, que el código sea correcto y que el rango o la lista de cobertura estén completos.


= ¿Cómo pruebo una regla? =

Usa la vista previa de Reglas de envío y simula una ubicación con estado, municipio y código postal.


= ¿Qué pasa si una regla está bloqueada? =

El checkout no permitirá completar el pedido para esa ubicación.

== Screenshots ==

1. Páginas de administración de Orpot Woo Locations.
2. Selectores de ubicación en checkout.
3. Vista previa de reglas y mensajes de cobertura.

== Changelog ==

= 0.2.2 =
* Rebrand to Orpot Woo Locations.
* Improved admin help and Spanish UI.

= 0.2.1 =
* Initial public release.

== Upgrade Notice ==

= 0.2.2 =
Rebrand and help improvements.

= 0.2.1 =
Initial public release.
