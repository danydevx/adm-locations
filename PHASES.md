# ADM Bike Woo Locations - Plan de Implementación

## Fase 1 - Arquitectura y Base de Datos

### Objetivo

Crear los cimientos del plugin.

### Tareas

* Auditoría de la estructura actual.

* Crear activador/desactivador.

* Crear versión de base de datos.

* Crear tablas:

  * states
  * municipalities
  * postcodes
  * shipping_rules

* Crear repositorios.

* Crear capa de acceso a datos.

* Crear seed de ejemplo ADM Bike.

### Entregables

* Instalación limpia.
* Migraciones.
* Tablas funcionales.
* Repositorios funcionales.

---

## Fase 2 - Catálogo Geográfico

### Objetivo

Administrar la información geográfica.

### Tareas

CRUD para:

* Estados
* Municipios
* Códigos Postales

### Funcionalidades

* Crear
* Editar
* Eliminar
* Activar
* Desactivar
* Buscar
* Filtrar
* Paginación

### Entregables

Panel funcional para administrar ubicaciones.

---

## Fase 3 - Reglas de Cobertura

### Objetivo

Administrar reglas de envío.

### Tipos

* Envío Gratis
* Envío con Costo
* Sin Cobertura

### Tareas

CRUD de reglas.

### Soportar

* Estado
* Municipio
* Código Postal exacto
* Rango de CP

### Extras

* Prioridades
* Detección de conflictos
* Vista previa

### Entregables

Motor de reglas funcional.

---

## Fase 4 - Checkout Inteligente

### Objetivo

Guiar al cliente.

### Flujo

Estado

↓

Municipio

↓

Código Postal

### Tareas

* Reemplazar campos WooCommerce.
* Cargar municipios dinámicamente.
* Cargar códigos postales dinámicamente.
* Guardar selección.

### Entregables

Checkout funcional con selects dependientes.

---

## Fase 5 - Motor de Cálculo de Envíos

### Objetivo

Aplicar reglas automáticamente.

### Tareas

Calcular:

* Gratis
* Con costo
* Sin cobertura

### Prioridad

1. CP exacto
2. Rango CP
3. Municipio
4. Estado

### Entregables

WooCommerce calcula correctamente.

---

## Fase 6 - AJAX y REST API

### Objetivo

Servir datos dinámicamente.

### Endpoints

GET /states

GET /municipalities

GET /postcodes

GET /coverage

### Entregables

API estable.

---

## Fase 7 - Importación y Exportación

### Objetivo

Administración masiva.

### Importar

CSV:

* Estados
* Municipios
* CP
* Reglas

### Exportar

CSV completo.

### Extras

* Vista previa.
* Validación.
* Duplicados.
* Rollback.

### Entregables

Importador profesional.

---

## Fase 8 - Optimización y Release

### Objetivo

Preparar producción.

### Tareas

* Seguridad.
* Rendimiento.
* Índices SQL.
* Traducciones.
* Logs.
* Documentación.
* Testing.

### Compatibilidad

* WordPress
* WooCommerce
* HPOS

### Entregables

Versión 1.0 estable.

---

# Versión 1.1

## Mejoras

* Búsqueda rápida de CP.
* Selector inteligente.
* Historial de zonas.
* Clonar reglas.
* Cobertura por colonia.

---

# Versión 2.0

## Funciones Avanzadas

* Google Maps.
* Geocoding.
* Distancia por kilómetros.
* Polígonos de cobertura.
* Cobertura por coordenadas.
* API pública.
* Integración con paqueterías.

---

# Versión 3.0

## SaaS Multiempresa

* Múltiples tiendas.
* Sincronización remota.
* Marketplace de zonas.
* Actualización automática de CP.
* Panel centralizado.

