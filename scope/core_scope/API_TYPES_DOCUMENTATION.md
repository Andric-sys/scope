# Tipificación de Datos - API de Órdenes de Envío

## Descripción General

Este documento describe la estructura tipificada de los datos contenidos en `api.json`, que representa una **Orden de Envío (Shipment Order)** de un sistema de logística y transporte internacional.

## Archivo Generado

📄 **api.types.ts** - Definiciones de tipos TypeScript

## Estructura Principal

### `ShipmentOrder`
Interfaz principal que representa una orden de envío completa con toda su información logística, financiera y de seguimiento.

## Categorías de Datos

### 1. **Identificación y Configuración Básica**
- `identifier`: UUID único de la orden
- `legacyIdentifier`: Identificador del sistema anterior
- `number`: Número de orden (ej: "CGL23010004")
- `module`: Tipo de envío (simpleShipment, masterShipment, houseShipment)
- `orderDate`: Fecha de creación de la orden
- `economicDate`: Fecha económica de la transacción

### 2. **Entidades Involucradas**
Cada entidad incluye información del partner y dirección completa:

#### `Owner` (Propietario)
Empresa propietaria de la orden - CORE GLOBAL LOGISTICS MANAGEMENT S.A. DE C.V.

#### `Customer` (Cliente)
Cliente que solicita el servicio - NAPS-GUANAJUATO

#### `Shipper` (Embarcador)
Origen de la mercancía - VIKING DRILL AND TOOL (St Paul, MN, USA)

#### `Consignee` (Consignatario)
Destinatario final - NAPS-GUANAJUATO (Irapuato, GUA, México)

### 3. **Información de Transporte**

#### Tipo de Transporte
- `conveyanceType`: Modalidad (air, sea, road, rail, multimodal)
- Ejemplo actual: **"road"** (transporte terrestre)

#### Ubicaciones
- `departure`: Origen - St Paul, USA (USSTP)
- `destination`: Destino - San Miguel de Allende, MX (MXSMG)

#### Fechas de Transporte
- `etd`: Estimated Time of Departure - 2022-12-22
- `atd`: Actual Time of Departure - 2022-12-22
- `eta`: Estimated Time of Arrival - 2022-12-27
- `ata`: Actual Time of Arrival - 2022-12-27

#### Información Específica de Carretera
- `roadRelated`: Ventanas de recogida y entrega
- `pickupWindow`: 2022-12-22
- `deliveryWindow`: 2022-12-27

### 4. **Mercancía**

#### Medidas y Cantidades
- `pieces`: Cantidad de piezas (1)
- `grossWeight`: Peso bruto (1.360 kg)
- `chargeableWeight`: Peso facturable (1365.300 kg)
- `volume`: Volumen (4.096 m³)
- `natureOfGoods`: Descripción de mercancía ("BROCA")

### 5. **Términos Comerciales**

#### Incoterms
- `incoTerms`: "DAP" (Delivered at Place)
- `incotermPlace`: "IRAPUATO"
- `mainTransportFreightTerm`: "P" (Prepaid - Flete pagado)
- `movementScope`: "port2Door" (Puerto a Puerta)

### 6. **Datos Financieros** (`partnerRelatedData`)

#### Estado Financiero
- `financialStatus`: "closed"
- `statusToClosedDate`: "2024-01-12"
- `costCenter`: Centro de costos (2000)

#### Totales en Moneda Local (MXN)
```typescript
jobcostingTotalsLocalCurrency: {
  bookedIncome: 3,897.66 MXN
  bookedCost: 1,500.00 MXN
  totalIncome: 16,461.66 MXN
  totalCost: 14,064.00 MXN
  profit: 2,397.66 MXN
  grossMargin: 0.615 (61.5%)
}
```

#### Totales en Moneda Organizacional (USD)
```typescript
jobcostingTotalsOrganizationCurrency: {
  bookedIncome: 222.22 USD
  bookedCost: 85.52 USD
  totalIncome: 938.53 USD
  totalCost: 829.24 USD
  profit: 109.29 USD
  grossMargin: 0.615 (61.5%)
}
```

#### Tipos de Ingresos y Costos
- **bookedIncome/Cost**: Ingresos/costos contabilizados
- **transitBookedIncome/Cost**: Ingresos/costos de tránsito contabilizados
- **openAndEstimatedIncome/Cost**: Ingresos/costos abiertos y estimados
- **estimatedFixatedCost**: Costos estimados fijados
- **totalIncome/Cost**: Totales
- **profit**: Ganancia neta
- **grossMargin**: Margen bruto

### 7. **Referencias** (`references`)
Códigos de referencia utilizados en el proceso:

| Código | Valor | Descripción |
|--------|-------|-------------|
| AA MX | CESAR VALDEZ | Agente aduanal México |
| AA US | NORMA VALDEZ | Agente aduanal USA |
| AD | NLD | Aduana |
| REF BODEGA | T226878 | Referencia de bodega |
| SRN | CGL23010004 | Número de referencia del sistema |
| IMP/EXP | IMP | Tipo (Importación) |
| REF ANT | CG221483 | Referencia anterior |
| C. PED | A1 | Clasificación de pedimento |
| REF CGT | CGT23010043 | Referencia CGT |
| FAC IMP EXP | CAMS44422 | Factura |
| NO PED | 23 24 3678 3400013 | Número de pedimento |
| IV | AB 14425 | Invoice |
| SCP_TO | CGL23010004 | Scope Transport Order |

### 8. **Hitos/Milestones** (`milestones`)
Seguimiento del estado de la orden a través del proceso logístico:

| Hito | Completado | Fecha Planeada | Fecha Real |
|------|-----------|---------------|------------|
| CON AVISO DE ENTRADA | ✅ | 2022-12-27 | 2022-12-27 16:09 |
| EN PREVIO | ✅ | 2022-12-28 | 2022-12-28 11:47 |
| INF PENDIENTE CLIENTE | ✅ | 2022-12-28 | 2023-01-04 14:13 |
| EN PAGO DE PED | ✅ | 2023-01-04 | 2023-01-04 17:08 |
| EN DESPACHO | ✅ | 2023-01-04 | 2023-01-04 |
| EN RUTA AL CLIENTE | ✅ | 2023-01-04 | 2023-01-04 |
| ENTREGADO | ✅ | 2023-01-09 | 2023-01-09 |
| CON SOPORTE DE FAC | ✅ | 2023-01-20 | 2023-02-01 |

### 9. **Indicadores Especiales**
- `cancelled`: false (No cancelada)
- `blocked`: false (No bloqueada)
- `consolidated`: false (No consolidada)
- `dgr`: false (Sin mercancía peligrosa)

## Uso de los Tipos

### En TypeScript:
```typescript
import { ShipmentOrder, isValidShipmentOrder } from './api.types';

// Cargar datos
const orderData: ShipmentOrder = require('./api.json');

// Validar
if (isValidShipmentOrder(orderData)) {
  console.log(`Orden ${orderData.number} validada correctamente`);
  console.log(`Ganancia: ${orderData.partnerRelatedData[0].jobcostingTotalsOrganizationCurrency.profit.value} ${orderData.partnerRelatedData[0].jobcostingTotalsOrganizationCurrency.profit.currency}`);
}
```

### En PHP (documentación de estructura):
```php
<?php
// Aunque los tipos están en TypeScript, puedes usarlos como referencia
// para validar la estructura en PHP

$jsonData = file_get_contents('api.json');
$order = json_decode($jsonData, true);

// Acceso a datos
echo "Orden: " . $order['number'];
echo "Cliente: " . $order['customer']['partner']['name'];
echo "Estado: " . $order['partnerRelatedData'][0]['financialStatus'];
echo "Ganancia: " . $order['partnerRelatedData'][0]['jobcostingTotalsOrganizationCurrency']['profit']['value'];
```

## Tipos Auxiliares Importantes

### `Partner`
Entidad comercial con identificador, código y nombre

### `Address`
Dirección completa con calle, ciudad, código postal, estado y país

### `MoneyValue`
Valor monetario con cantidad y moneda

### `Measurement`
Medida física con valor y unidad

### `TimeInterval`
Intervalo de tiempo con fecha de inicio y fin

### `Milestone`
Hito del proceso con código, descripción, estado y fechas

## Enumeraciones

### `ShipmentModule`
- simpleShipment
- masterShipment
- houseShipment

### `ConveyanceType`
- air (aéreo)
- sea (marítimo)
- road (terrestre)
- rail (ferroviario)
- multimodal

### `FreightTerm`
- P (Prepaid - Pagado)
- C (Collect - Por cobrar)

### `MovementScope`
- door2Door
- door2Port
- port2Door
- port2Port
- door2Depot
- depot2Door
- depot2Port
- port2Depot

### `FinancialStatus`
- open (abierta)
- preBilled (pre-facturada)
- billed (facturada)
- closed (cerrada)

## Notas

- Todas las fechas usan formato ISO 8601
- Las fechas simples usan formato YYYY-MM-DD
- Los montos pueden estar en diferentes monedas (MXN, USD, etc.)
- Las medidas pueden tener diferentes unidades (kg, m³, etc.)
- Los identificadores son UUID v4
- El sistema maneja múltiples propietarios a través de `partnerRelatedData`

---

**Generado**: 2 de febrero de 2026  
**Fuente**: api.json  
**Versión**: 1.0
