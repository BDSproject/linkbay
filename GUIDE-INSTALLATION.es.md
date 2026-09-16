# LinkBay — Guía de instalación y uso

Gracias por elegir **LinkBay**, el conector eBay para PrestaShop.
Esta guía le acompaña paso a paso, de la instalación a la sincronización de stocks y precios.

---

## 1. Presentación

LinkBay **importa sus anuncios de eBay** en PrestaShop y **sincroniza el stock** en ambos sentidos, de forma continua mediante el cron. También gestiona **variaciones**, **impuestos**, **multi-mercado**, un **stock de reserva** y **precios dinámicos**.

> **No se requiere cuenta de desarrollador de eBay**: la aplicación eBay es compartida y la proporciona Beassisted. Solo tiene que pegar su **código eBay (RuName)** incluido con su licencia.

---

## 2. Requisitos

| Elemento | Requisito |
|---|---|
| PrestaShop | 1.7.8 o superior |
| PHP | 7.4 o superior |
| eBay | Una cuenta **vendedor** activa |
| Código eBay (RuName) | Incluido con su licencia (p. ej. `BEASSISTED-BEASSIST-beassi-xxxxxxx`) |

---

## 3. Instalación

1. En el back office: **Módulos → Gestor de módulos**.
2. Haga clic en **«Añadir un módulo»** y suba `linkbay.zip`.
3. Haga clic en **«Instalar»**. El módulo crea sus propias tablas y su menú **LinkBay** (no se modifica ninguna tabla de PrestaShop).

---

## 4. Conexión a eBay

1. Vaya a **LinkBay → Configuración**.
2. En la tarjeta **«Ajustes de la API de eBay»**, pegue su **código eBay (RuName)** — el valor incluido con su licencia (p. ej. `BEASSISTED-BEASSIST-beassi-xxxxxxx`). Es el único valor que debe introducir: la aplicación eBay es compartida y ya está preconfigurada por Beassisted.
3. Haga clic en **«Guardar ajustes»** y después en **«Conectar con eBay»**.
4. Inicie sesión en su cuenta **vendedora** de eBay y **autorice** la aplicación.

> 💬 ¿Necesita ayuda? **contact@beassistnow.com**

---

## 5. Ajustes recomendados

En **LinkBay → Configuración**:

- **Nombre de usuario de eBay**: su vendedor (por ejemplo `bridgefurb`).
- **Marketplaces**: marque el/los sitio(s) eBay donde vende (Francia, Alemania, Reino Unido…).
- **Regla de impuestos**: elija la regla PrestaShop aplicada **a los productos importados**. eBay incluye el IVA; el módulo convierte al precio sin impuestos.
  - ⚠️ Si usa un **módulo de IVA de terceros**, seleccione **«Sin regla de impuestos»** para evitar conflictos.
- **Categoría por defecto**: la categoría PrestaShop de destino.
- **Stock de reserva**: mantenga un margen de seguridad (p. ej., envíe el 80 % del stock real).
- **Precios dinámicos**: cree sus reglas (p. ej., «si stock > 50, baje un 5 %; si stock < 5, suba un 10 %»).

---

## 6. Uso

### Importación automática
El **cron** importa sus anuncios activos y sincroniza el stock. La frecuencia es configurable (por defecto, 15 min).

### Importación manual
**LinkBay → Importar**: recorra las categorías de eBay mediante el **desplegable**, marque los anuncios y pulse **«Importar la selección»**.

### Panel de control
**LinkBay → Panel**: una lista de problemas a corregir, con **«Reparar automáticamente»** (p. ej., combinaciones obsoletas, fallos de importación, precios). Un clic.

---

## 7. Compatibilidad con sus otros módulos

- LinkBay gestiona **solo los productos asignados a eBay**. Nunca modifica los productos que gestiona en otro lugar.
- Nunca cambia los **ajustes fiscales globales** de la tienda.
- La tarjeta **«Compatibilidad y conflictos»** lista los módulos activos que también reaccionan a los cambios de producto.
- **IVA**: si un módulo de terceros gestiona el IVA, elija **«Sin regla de impuestos»** en LinkBay — deja que ese módulo trabaje (sin conflicto).

---

## 8. Solución de problemas

| Problema | Solución |
|---|---|
| «invalid_grant» al volver de eBay | La conexión ya se realizó (doble llamada del callback). Recargue el back office. |
| Anuncios no encontrados | Compruebe su **nombre de usuario de eBay** y los **marketplaces** seleccionados. |
| Precios sin cambios | Asegúrese de que una regla de precios coincide con el stock actual. |
| Conexión perdida | Pulse de nuevo **«Conectar con eBay»** en la configuración. |

---

## 9. Soporte

📧 **contact@beassistnow.com** — Beassisted está a su disposición para la configuración y cualquier duda.

---

*LinkBay — Módulos PrestaShop de precisión · © Beassisted*
