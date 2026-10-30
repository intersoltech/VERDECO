# Verdeco Cost Control — SCADA & Machine Ingestion API (v1)

This document describes the REST API v1 endpoints for ingesting automated machine metrics, utility meter pulses, continuous weight counter ticks, and downtime signals into the Verdeco Cost Control system.

---

## 1. Authentication & Security

All API endpoints are prefixed with `/api/v1` and authenticated using **Laravel Sanctum Personal Access Tokens** via the HTTP `Authorization` header:

```http
Authorization: Bearer <API_TOKEN>
```

### Granular Token Abilities (Scopes)
When provisioning tokens for PLC gateways, smart meters, or SCADA servers, assign only the necessary abilities:
- `ingest:meter-readings`: Ingest electricity ($kWh$), natural gas ($m^3$), water ($m^3$), nitrogen ($m^3$).
- `ingest:production-ticks`: Ingest pelletizer scales and continuous bagger weight pulses.
- `ingest:downtime-events`: Ingest line stop/start signals and equipment fault codes.
- `ingest:bulk`: Upload bulk historical CSV or JSON logs.
- `read:operations`: Read real-time plant KPIs, running batches, and open alerts.
- `*`: Global administrative access.

### Multi-Plant Tenant Scoping
Each token belongs to an authorized plant operator or service account.
- If the token belongs to an account assigned to **Verdeco Dallas (`VDC`)**, attempting to push readings for **Verdeco Fontana (`FON`)** returns `403 Forbidden`.
- Group administrator accounts can access and ingest for all plants across the organization.

---

## 2. Ingestion Endpoints

### 2.1 Ingest Meter Reading
**`POST /api/v1/ingest/meter-readings`**
Required Ability: `ingest:meter-readings`

Ingests cumulative or interval meter readings (e.g. electrical meters on extruders, SSP crystallizers, main transformers).

#### Request Body
```json
{
  "plant_code": "VDC",
  "line_code": "VDC-L01",
  "production_batch_id": 142,
  "meter_type": "electricity_kwh",
  "reading_value": 45.2,
  "unit": "kwh",
  "is_delta": true,
  "external_id": "MTR-VDC-L01-20261030-0900",
  "read_at": "2026-10-30T09:00:00Z"
}
```

#### Fields:
- `plant_code` (string, required): 3-letter plant identifier (e.g. `VDC`, `FON`, `TER`).
- `line_code` (string, optional): Equipment line identifier (e.g. `VDC-L01`).
- `production_batch_id` / `batch_number` (optional): Links reading directly to a production batch; if omitted, automatically links to the active running batch on the line.
- `meter_type` (string, required): One of `electricity_kwh`, `natural_gas_m3`, `water_m3`, `nitrogen_m3`.
- `reading_value` (numeric, required): Measured quantity.
- `unit` (string, default: `kwh`).
- `is_delta` (boolean, default: `true`): If `true`, increments the running batch's cumulative consumption; if `false`, records absolute meter counter.
- `external_id` (string, optional): Unique idempotency key. Network retries with the same `external_id` will return `200 OK` without double-counting.
- `read_at` (ISO 8601 timestamp, optional): Defaults to server time if omitted.

#### Response (201 Created / 200 OK on Duplicate)
```json
{
  "success": true,
  "data": {
    "status": "processed",
    "reading_id": 512,
    "reading_type": "meter_reading",
    "meter_type": "electricity_kwh",
    "reading_value": 45.2,
    "unit": "kwh",
    "line_id": 1,
    "batch_id": 142,
    "read_at": "2026-10-30T09:00:00+00:00"
  }
}
```

---

### 2.2 Ingest Production Counter Tick
**`POST /api/v1/ingest/production-ticks`**
Required Ability: `ingest:production-ticks`

Captures weight ticks from bulk-bag packaging scales, octabin filling stations, or continuous optical pellet flow meters.

#### Request Body
```json
{
  "plant_code": "VDC",
  "line_code": "VDC-L01",
  "batch_number": "VDC-20261030-01",
  "weight": 2204.62,
  "unit": "lb",
  "external_id": "SCALE-TICK-99412",
  "read_at": "2026-10-30T09:15:00Z"
}
```

#### Automatic Unit Conversion
Verdeco stores all production weights internally in **Kilograms ($kg$)**.
If the physical scale reports in pounds (`unit: "lb"` or `"lbs"`), the system converts the weight to kilograms using high-precision decimal math:
$$\text{weight}_{kg} = \frac{\text{weight}_{lb}}{2.20462}$$

#### Response (201 Created)
```json
{
  "success": true,
  "data": {
    "status": "processed",
    "reading_id": 513,
    "reading_type": "production_tick",
    "weight_kg": 1000.0,
    "original_weight": 2204.62,
    "original_unit": "lb",
    "line_id": 1,
    "batch_id": 142,
    "read_at": "2026-10-30T09:15:00+00:00"
  }
}
```

---

### 2.3 Ingest SCADA Downtime Event
**`POST /api/v1/ingest/downtime-events`**
Required Ability: `ingest:downtime-events`

Logs equipment stops, screen-pack changes, extruder motor trips, or vacuum loss directly into operational downtime records.

#### Request Body
```json
{
  "plant_code": "VDC",
  "line_code": "VDC-L01",
  "downtime_type": "unplanned",
  "reason_code": "screen_pack_change",
  "start_at": "2026-10-30T08:15:00Z",
  "end_at": "2026-10-30T08:55:00Z",
  "duration_minutes": 40,
  "notes": "Melt pressure exceeded 210 bar before automated filter shift",
  "external_id": "SCADA-ALARM-00412"
}
```

#### Side-Effects & Event Pipeline
Creating a downtime event through this API automatically:
1. Records a persistent `DowntimeEvent` in the operational database.
2. Dispatches `DowntimeLogged` event to recalculate daily line and plant KPI rollups.
3. Evaluates the `unplanned_downtime_threshold` alert rule.

---

### 2.4 Bulk CSV / JSON Ingestion
**`POST /api/v1/ingest/bulk-upload`**
Required Ability: `ingest:bulk`

Allows batch upload of historical data or scheduled file transfers from factory floor edge PCs.

#### Headers
```http
Content-Type: application/json
```
#### Request Body
```json
{
  "plant_code": "VDC",
  "format": "csv",
  "payload": "timestamp,line_code,reading_type,meter_type,value,unit,external_id\n2026-10-30 08:00:00,VDC-L01,meter_reading,electricity_kwh,150.0,kwh,HIST-01\n2026-10-30 08:30:00,VDC-L01,production_tick,,1000,kg,HIST-02\n2026-10-30 09:00:00,VDC-L01,downtime_event,screen_pack_change,25,minutes,HIST-03"
}
```

#### Response (200 OK)
```json
{
  "success": true,
  "summary": {
    "total": 3,
    "processed": 3,
    "duplicates": 0,
    "errors": 0,
    "details": [...]
  }
}
```

---

## 3. Real-Time Operational Read Endpoints

### 3.1 List Authorized Plants
**`GET /api/v1/plants`**
Required Ability: `read:operations`

Returns all active plants and configured equipment lines accessible to the token.

### 3.2 Real-Time Operations Summary
**`GET /api/v1/operations/summary?plant_code=VDC`**
Required Ability: `read:operations`

Returns today's consolidated operations pulse:
- Output kg, Yield %, Unit Cost ($/kg$), Energy Intensity ($kWh/kg$)
- Active running batches and progress
- Downtime events recorded today
- Active open alerts count

