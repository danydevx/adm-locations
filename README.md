# Orpot Mexico Woo Reglas

**Contribuyentes:** admbike  
**Etiquetas:** woocommerce, envíos, ubicaciones, estados, municipios, códigos postales  
**Requiere al menos:** 6.0  
**Probado hasta:** 6.4  
**Requiere PHP:** 8.2  
**Licencia:** GPL v2 o posterior  
**URI de la licencia:** https://www.gnu.org/licenses/gpl-2.0.html

Administración avanzada de cobertura de envíos para WooCommerce mediante Estados, Municipios y Códigos Postales.

---

# Descripción

Orpot Mexico Woo Reglas permite definir áreas de cobertura para envíos utilizando una estructura jerárquica:

**Estado → Municipio → Código Postal**

En lugar de depender únicamente de las Zonas de Envío de WooCommerce, este complemento ofrece:

- Selectores guiados durante el proceso de compra (listas desplegables en cascada: Estado → Municipio → Código Postal)
- Reglas de envío de diferentes tipos: Gratis, Con costo y Bloqueado
- Coincidencia de reglas por:
  - Código Postal exacto
  - Rango de Códigos Postales
  - Municipio
  - Estado
- Detección automática de conflictos cuando existen reglas superpuestas
- Indicador de cobertura en las páginas de Carrito y Finalizar Compra

---

# Instalación

## Requisitos

- WordPress 6.0 o superior
- WooCommerce 8.0 o superior
- PHP 8.2 o superior

## Pasos

1. Suba la carpeta del plugin a:

```
/wp-content/plugins/admbike-woo-locations/
```

2. Active el plugin desde el menú **Plugins** de WordPress.

3. Vaya al menú **ADM Bike Locations**.

4. Agregue sus Estados, Municipios y Códigos Postales.

5. Cree las reglas de envío para definir cobertura, costos o zonas bloqueadas.

6. Active el método de envío desde:

**WooCommerce → Ajustes → Envíos**

---

# Activar el método de envío

1. Ir a:

**WooCommerce → Ajustes → Envíos**

2. Hacer clic en **Agregar método de envío**.

3. Seleccionar **ADM Bike Locations**.

4. Confirmar con **Agregar método de envío**.

5. Configurar el nombre del método y el estado de impuestos.

6. Arrastrar el método a la zona deseada o crear una nueva zona.

---

# Configuración

## Agregar Estados

1. Ir a:

**ADM Bike Locations → Estados**

2. Hacer clic en **Agregar nuevo**.

3. Capturar:

- Código del estado (ejemplo: `JAL`)
- Nombre (ejemplo: `Jalisco`)

4. Activarlo.

5. Guardar.

---

## Agregar Municipios

1. Ir a:

**ADM Bike Locations → Municipios**

2. Agregar un nuevo municipio.

3. Seleccionar el Estado correspondiente.

4. Escribir el nombre del municipio.

5. Activarlo.

6. Guardar.

---

## Agregar Códigos Postales

1. Ir a:

**ADM Bike Locations → Códigos Postales**

2. Agregar un nuevo código.

3. Seleccionar Estado y Municipio.

4. Escribir el Código Postal de cinco dígitos.

5. Activarlo.

6. Guardar.

---

# Crear Reglas de Envío

Las reglas determinan el costo y disponibilidad del envío.

Las reglas se evalúan por prioridad:

**Un número menor tiene mayor prioridad.**

## Pasos

1. Ir a:

**ADM Bike Locations → Reglas de Envío**

2. Crear una nueva regla.

3. Seleccionar el **Tipo de coincidencia**:

- Estado
- Municipio
- Código Postal
- Rango de Códigos Postales

4. Seleccionar la ubicación correspondiente.

5. Elegir el **Tipo de regla**:

### Envío Gratis

El cliente no paga envío.

### Envío con costo

Permite indicar el importe del envío.

### Bloqueado

Impide realizar envíos a esa ubicación.

6. Definir la prioridad.

7. Agregar notas (opcional).

8. Guardar.

---

# Ejemplo de prioridad

Supongamos las siguientes reglas:

- Estado **Jalisco** → Envío Gratis (Prioridad 50)
- Municipio **Guadalajara** → Envío Gratis (Prioridad 10)

Si un cliente pertenece a Guadalajara, prevalecerá la regla del municipio, ya que su prioridad (**10**) es mayor que la del estado (**50**).

---

# Uso desde el Administrador

## Listas desplegables en cascada durante el Checkout

Cuando el cliente llega al proceso de compra:

1. Selecciona su Estado.
2. El sistema carga únicamente los Municipios pertenecientes a ese Estado.
3. Después muestra únicamente los Códigos Postales correspondientes a ese Municipio.

Esto facilita el proceso para clientes que no conocen su Código Postal.

---

## Vista previa de cobertura

Al editar una regla existe una sección de **Vista previa**, donde es posible verificar qué regla será aplicada antes de guardar.

---

## Detección de conflictos

Cuando una regla se superpone con otra de mayor prioridad, el sistema mostrará una advertencia para evitar configuraciones incorrectas.

---

# Solución de Problemas

## El método de envío no aparece

Verifique que:

1. El método esté habilitado en:

**WooCommerce → Ajustes → Envíos**

2. Exista al menos una Zona de Envío activa con el método **ADM Bike Locations**.

3. Existan Estados, Municipios y Códigos Postales registrados.

4. Exista al menos una regla activa que coincida con la dirección del cliente.

---

## Se muestra "Sin cobertura"

Verifique que:

1. El Código Postal exista.

2. El Municipio esté activo.

3. El Estado esté activo.

4. Exista una regla aplicable por:

- Código Postal
- Municipio
- Estado

(en ese orden de prioridad)

5. La regla no esté marcada como **Bloqueada**.

---

## Las listas desplegables no cargan

Verifique que:

1. WooCommerce esté instalado y activo.

2. No existan errores JavaScript en la consola del navegador.

3. El script:

```html
<script id="admbike-location-data">
```

esté presente en el código fuente de la página.

4. No exista conflicto con otro plugin del Checkout.

---

# Registro de Depuración (Debug)

Puede activar el modo de depuración agregando lo siguiente al archivo:

```php
wp-config.php
```

```php
define('ADMBIKE_WOO_LOCATIONS_DEBUG', true);
```

Los registros se almacenarán en:

```
wp-content/uploads/admbike-woo-locations.log
```

---

# Tablas de Base de Datos

El plugin crea cuatro tablas personalizadas:

- `admbike_states` — Estados
- `admbike_municipalities` — Municipios
- `admbike_postcodes` — Códigos Postales
- `admbike_shipping_rules` — Reglas de cobertura de envíos

---

# API REST

El plugin expone una API REST en:

```
/wp-json/admbike-woo-locations/v1/
```

| Endpoint | Método | Descripción |
|----------|--------|-------------|
| `/states` | GET | Obtiene todos los estados activos |
| `/municipalities?state_id={id}` | GET | Obtiene los municipios de un estado |
| `/postcodes?municipality_id={id}` | GET | Obtiene los códigos postales de un municipio |
| `/coverage?postcode={code}` | GET | Verifica la cobertura de un código postal |

---

# Hooks y Filtros

## Actions

- `admbike_woo_locations_activated`  
  Se ejecuta al activar el plugin.

- `admbike_shipping_rule_applied`  
  Se ejecuta cuando una regla de envío es aplicada durante el Checkout.

---

## Filters

- `admbike_woo_locations_checkout_fields`  
  Permite modificar las etiquetas de los campos del Checkout.

- `admbike_woo_locations_shipping_rate`  
  Permite modificar el costo del envío antes de agregarlo al pedido.

---

# Historial de Cambios

## 0.1.0

- Lanzamiento inicial.
- Administración de Estados.
- Administración de Municipios.
- Administración de Códigos Postales.
- CRUD de reglas de envío.
- Prioridades y detección de conflictos.
- Selectores guiados en el Checkout.
- Indicador de cobertura.
- API REST para consulta de cobertura.
- Sistema de registro de depuración (Debug).
