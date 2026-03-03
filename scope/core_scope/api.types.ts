/**
 * Definiciones de tipos para la API de órdenes de envío (Shipment Orders)
 * Generado desde api.json
 */

/**
 * Representa una entidad comercial (partner/cliente/proveedor)
 */
export interface Partner {
  identifier: string;
  code: string;
  name: string;
  customerIdentificationNumber: string | null;
}

/**
 * Representa una dirección física
 */
export interface Address {
  name: string;
  name2: string | null;
  name3: string | null;
  attention: string | null;
  street: string;
  street2: string | null;
  street3: string | null;
  poBoxNumber: string | null;
  city: string;
  zip: string;
  state: string;
  country: string;
}

/**
 * Representa una entidad con dirección (cliente, shipper, consignee)
 */
export interface AddressedEntity {
  partner: Partner;
  address: Address;
  contact: string | null;
}

/**
 * Representa una ubicación geográfica
 */
export interface Location {
  country: string;
  unlocode: string;
  iataCode: string | null;
  name: string;
}

/**
 * Representa una fecha con estructura específica
 */
export interface DateValue {
  date: string; // Formato: YYYY-MM-DD
}

/**
 * Representa un intervalo de tiempo
 */
export interface TimeInterval {
  startInclusive: DateValue;
  endInclusive: DateValue;
}

/**
 * Representa un valor monetario
 */
export interface MoneyValue {
  value: number;
  currency: string;
}

/**
 * Representa una medida con unidad
 */
export interface Measurement {
  value: number;
  unit: string;
}

/**
 * Información relacionada con transporte por carretera
 */
export interface RoadRelated {
  roadCarrier: string | null;
  pickupWindow: TimeInterval;
  deliveryWindow: TimeInterval;
  truckLicenseNumber: string | null;
}

/**
 * Centro de costos
 */
export interface CostCenter {
  identifier: string;
  code: string;
}

/**
 * Totales de costeo de trabajo (Job Costing Totals)
 */
export interface JobcostingTotals {
  bookedIncome: MoneyValue;
  bookedCost: MoneyValue;
  openAndEstimatedIncome: MoneyValue;
  openAndEstimatedCost: MoneyValue;
  estimatedFixatedCost: MoneyValue;
  transitBookedIncome: MoneyValue;
  transitBookedCost: MoneyValue;
  transitOpenAndEstimatedIncome: MoneyValue;
  transitOpenAndEstimatedCost: MoneyValue;
  transitEstimatedFixatedCost: MoneyValue;
  totalIncome: MoneyValue;
  totalCost: MoneyValue;
  profit: MoneyValue;
  grossMargin: number;
}

/**
 * Datos relacionados con el partner
 */
export interface PartnerRelatedData {
  owner: Partner;
  number: string;
  costCenter: CostCenter;
  financialStatus: 'open' | 'preBilled' | 'billed' | 'closed';
  statusToPreBilledDate: string | null;
  statusToBilledDate: string | null;
  statusToClosedDate: string | null;
  jobcostingTotalsLocalCurrency: JobcostingTotals;
  jobcostingTotalsOrganizationCurrency: JobcostingTotals;
  expectedInvoiceAmount: MoneyValue | null;
  expectedProfit: MoneyValue | null;
  expectedProfitDeviation: number | null;
  expectedProfitStatus: 'notSet' | 'set' | 'checked';
  expectedProfitSetBy: string | null;
  expectedProfitSetOn: string | null;
  expectedProfitSetRemarks: string | null;
  checkedProfit: MoneyValue | null;
  checkedProfitSetBy: string | null;
  checkedProfitSetOn: string | null;
  checkedProfitSetRemarks: string | null;
}

/**
 * Referencia de la orden
 */
export interface Reference {
  code: string;
  number: string;
}

/**
 * Referencias de la orden
 */
export interface References {
  reference: Reference[];
}

/**
 * Hito/Milestone del envío
 */
export interface Milestone {
  code: string;
  description: string;
  completed: boolean;
  active: boolean;
  publicVisible: boolean;
  plannedTime: string; // Formato: ISO 8601
  actualTime: string | null; // Formato: ISO 8601
}

/**
 * Hitos del envío
 */
export interface Milestones {
  milestone: Milestone[];
}

/**
 * Tipos de módulo de envío
 */
export type ShipmentModule = 'simpleShipment' | 'masterShipment' | 'houseShipment';

/**
 * Tipos de transporte
 */
export type ConveyanceType = 'air' | 'sea' | 'road' | 'rail' | 'multimodal';

/**
 * Términos de flete
 */
export type FreightTerm = 'P' | 'C'; // P = Prepaid, C = Collect

/**
 * Alcance del movimiento
 */
export type MovementScope = 
  | 'door2Door' 
  | 'door2Port' 
  | 'port2Door' 
  | 'port2Port'
  | 'door2Depot'
  | 'depot2Door'
  | 'depot2Port'
  | 'port2Depot';

/**
 * Estructura principal de la orden de envío (Shipment Order)
 */
export interface ShipmentOrder {
  identifier: string;
  legacyIdentifier: string;
  lastModified: string; // Formato: ISO 8601
  number: string;
  owner: Partner;
  module: ShipmentModule;
  orderDate: string; // Formato: YYYY-MM-DD
  economicDate: string; // Formato: YYYY-MM-DD
  customer: AddressedEntity;
  customerReferences: string | null;
  cancelled: boolean;
  cancellationReason: string | null;
  blocked: boolean;
  clerk: string;
  shipmentType: string | null;
  conveyanceType: ConveyanceType;
  usi: string;
  consolidated: boolean;
  masterShipmentNumber: string | null;
  masterShipmentIdentifier: string | null;
  transportDocumentNumber: string | null;
  houseDocumentNumber: string | null;
  bookingReferences: string | null;
  shipper: AddressedEntity;
  shipperReferences: string | null;
  requestedPickupTime: string | null;
  consignee: AddressedEntity;
  consigneeReferences: string | null;
  requestedDeliveryTime: string | null;
  lineOfBusiness: string | null;
  placeOfReceipt: Location | null;
  departure: Location;
  destination: Location;
  placeOfDelivery: Location | null;
  std: DateValue | null; // Scheduled Time of Departure
  etd: DateValue | null; // Estimated Time of Departure
  atd: DateValue | null; // Actual Time of Departure
  sta: DateValue | null; // Scheduled Time of Arrival
  eta: DateValue | null; // Estimated Time of Arrival
  ata: DateValue | null; // Actual Time of Arrival
  transportDate: string; // Formato: YYYY-MM-DD
  exportAgent: Partner | null;
  exportGateway: Location | null;
  importAgent: Partner | null;
  importGateway: Location | null;
  gatewayShipmentNumber: string | null;
  incoTerms: string; // EXW, FOB, CIF, DAP, DDP, etc.
  incotermPlace: string;
  mainTransportFreightTerm: FreightTerm;
  movementScope: MovementScope;
  sellingProduct: string | null;
  pieces: number;
  packageTypes: string | null;
  grossWeight: Measurement;
  chargeableWeight: Measurement;
  volume: Measurement;
  loadingMetres: Measurement | null;
  natureOfGoods: string;
  insuranceAmount: MoneyValue | null;
  freightAmount: MoneyValue | null;
  freightTerms: string | null;
  otherChargesAmount: MoneyValue | null;
  otherChargesTerms: string | null;
  notify: AddressedEntity | null;
  terminal: string | null;
  warehouseOrShed: string | null;
  mainTransportSupplier: Partner | null;
  mainTransportBuyingProduct: string | null;
  mainTransportPickupTimeInterval: TimeInterval;
  mainTransportDropoffTimeInterval: TimeInterval;
  airRelated: any | null; // Se puede expandir según necesidades
  seaRelated: any | null; // Se puede expandir según necesidades
  roadRelated: RoadRelated | null;
  salesPerson: string | null;
  salesZipZone: string | null;
  dealDate: string | null;
  salesCommission: number | null;
  remarks: string | null;
  dgr: boolean; // Dangerous Goods Regulation
  dgrInformation: any | null;
  carbonFootprintCalculations: any | null;
  partnerRelatedData: PartnerRelatedData[];
  transportOrders: any | null;
  customsOrders: any | null;
  references: References;
  milestones: Milestones;
  jobcostingEntries: any | null;
  organizationCode: string;
  legalEntityCode: string;
  branchCode: string;
}

/**
 * Type guards para validación en runtime
 */
export const isValidShipmentOrder = (data: any): data is ShipmentOrder => {
  return (
    typeof data === 'object' &&
    typeof data.identifier === 'string' &&
    typeof data.number === 'string' &&
    typeof data.module === 'string' &&
    typeof data.owner === 'object'
  );
};

export const isValidMoneyValue = (data: any): data is MoneyValue => {
  return (
    typeof data === 'object' &&
    typeof data.value === 'number' &&
    typeof data.currency === 'string'
  );
};

export const isValidMeasurement = (data: any): data is Measurement => {
  return (
    typeof data === 'object' &&
    typeof data.value === 'number' &&
    typeof data.unit === 'string'
  );
};
