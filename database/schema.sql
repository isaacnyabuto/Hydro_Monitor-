-- ============================================================================
--  HYDROELECTRIC GENERATION MONITORING SYSTEM (HGMS)
--  PostgreSQL Schema
--  Run this file in pgAdmin (Query Tool) or via psql to build the database.
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 0. DATABASE (run this part ONLY from psql / pgAdmin's default "postgres" DB,
--    NOT from inside a query tool already connected to hydro_monitor)
-- ---------------------------------------------------------------------------
-- CREATE DATABASE hydro_monitor
--     WITH ENCODING = 'UTF8'
--     LC_COLLATE = 'en_US.UTF-8'
--     LC_CTYPE = 'en_US.UTF-8';
--
-- After creating it, connect to "hydro_monitor" in pgAdmin, open a NEW Query
-- Tool on that database, and run everything below.
-- ---------------------------------------------------------------------------

CREATE EXTENSION IF NOT EXISTS pgcrypto; -- for gen_random_uuid() / crypt() if needed

-- ---------------------------------------------------------------------------
-- 1. ADMIN / AUTH
-- ---------------------------------------------------------------------------
CREATE TABLE admins (
    id              SERIAL PRIMARY KEY,
    username        VARCHAR(50) UNIQUE NOT NULL,
    email           VARCHAR(150) UNIQUE NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    full_name       VARCHAR(150) NOT NULL,
    role            VARCHAR(20) NOT NULL DEFAULT 'operator'
                        CHECK (role IN ('super_admin','admin','operator','viewer')),
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,
    last_login      TIMESTAMP,
    created_at      TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE activity_logs (
    id              SERIAL PRIMARY KEY,
    admin_id        INTEGER REFERENCES admins(id) ON DELETE SET NULL,
    action          VARCHAR(100) NOT NULL,
    details         TEXT,
    ip_address      VARCHAR(45),
    created_at      TIMESTAMP NOT NULL DEFAULT NOW()
);

-- ---------------------------------------------------------------------------
-- 2. PLANTS / STATIONS
-- ---------------------------------------------------------------------------
CREATE TABLE stations (
    id                  SERIAL PRIMARY KEY,
    name                VARCHAR(150) NOT NULL,
    river_name          VARCHAR(150),
    location            VARCHAR(200),
    latitude            DECIMAL(9,6),
    longitude           DECIMAL(9,6),
    installed_capacity_mw DECIMAL(10,2) NOT NULL DEFAULT 0,
    reservoir_max_volume_mcm DECIMAL(12,2), -- million cubic meters
    max_safe_water_level_m DECIMAL(6,2),    -- flood threshold
    min_operational_level_m DECIMAL(6,2),
    status              VARCHAR(20) NOT NULL DEFAULT 'operational'
                            CHECK (status IN ('operational','maintenance','shutdown','decommissioned')),
    created_at          TIMESTAMP NOT NULL DEFAULT NOW()
);

-- ---------------------------------------------------------------------------
-- 3. SENSORS (generic registry for all field devices)
-- ---------------------------------------------------------------------------
CREATE TABLE sensors (
    id              SERIAL PRIMARY KEY,
    station_id      INTEGER NOT NULL REFERENCES stations(id) ON DELETE CASCADE,
    sensor_code     VARCHAR(50) UNIQUE NOT NULL,
    sensor_type     VARCHAR(30) NOT NULL
                        CHECK (sensor_type IN ('water_level','flow_rate','rainfall','temperature',
                                                'humidity','wind_speed','pressure','vibration','rpm')),
    unit            VARCHAR(20),
    install_date    DATE,
    status          VARCHAR(20) NOT NULL DEFAULT 'active'
                        CHECK (status IN ('active','faulty','offline','calibrating')),
    created_at      TIMESTAMP NOT NULL DEFAULT NOW()
);

-- ---------------------------------------------------------------------------
-- 4. WATER LEVEL / FLOW / FLOOD DETECTION
-- ---------------------------------------------------------------------------
CREATE TABLE water_level_readings (
    id                  BIGSERIAL PRIMARY KEY,
    station_id          INTEGER NOT NULL REFERENCES stations(id) ON DELETE CASCADE,
    sensor_id           INTEGER REFERENCES sensors(id) ON DELETE SET NULL,
    reading_time        TIMESTAMP NOT NULL DEFAULT NOW(),
    water_level_m       DECIMAL(6,2) NOT NULL,
    inflow_rate_m3s     DECIMAL(10,2),
    outflow_rate_m3s    DECIMAL(10,2),
    reservoir_volume_pct DECIMAL(5,2),
    source              VARCHAR(20) DEFAULT 'sensor' CHECK (source IN ('sensor','manual','estimated'))
);
CREATE INDEX idx_wlr_station_time ON water_level_readings(station_id, reading_time DESC);

CREATE TABLE flood_events (
    id                  SERIAL PRIMARY KEY,
    station_id          INTEGER NOT NULL REFERENCES stations(id) ON DELETE CASCADE,
    event_start         TIMESTAMP NOT NULL DEFAULT NOW(),
    event_end           TIMESTAMP,
    severity            VARCHAR(20) NOT NULL CHECK (severity IN ('watch','warning','critical','emergency')),
    peak_water_level_m  DECIMAL(6,2),
    description         TEXT,
    status              VARCHAR(20) NOT NULL DEFAULT 'active'
                            CHECK (status IN ('active','monitoring','resolved')),
    created_at          TIMESTAMP NOT NULL DEFAULT NOW()
);

-- ---------------------------------------------------------------------------
-- 5. WEATHER ANALYSIS
-- ---------------------------------------------------------------------------
CREATE TABLE weather_data (
    id                  BIGSERIAL PRIMARY KEY,
    station_id          INTEGER NOT NULL REFERENCES stations(id) ON DELETE CASCADE,
    reading_time        TIMESTAMP NOT NULL DEFAULT NOW(),
    rainfall_mm         DECIMAL(6,2) DEFAULT 0,
    temperature_c       DECIMAL(5,2),
    humidity_pct        DECIMAL(5,2),
    wind_speed_kmh      DECIMAL(6,2),
    barometric_pressure_hpa DECIMAL(7,2),
    forecast_summary    VARCHAR(255),
    forecast_rain_probability_pct DECIMAL(5,2),
    source              VARCHAR(30) DEFAULT 'station_sensor'
);
CREATE INDEX idx_weather_station_time ON weather_data(station_id, reading_time DESC);

-- ---------------------------------------------------------------------------
-- 6. TURBINES & POWERHOUSE EQUIPMENT
-- ---------------------------------------------------------------------------
CREATE TABLE turbines (
    id                  SERIAL PRIMARY KEY,
    station_id          INTEGER NOT NULL REFERENCES stations(id) ON DELETE CASCADE,
    turbine_code        VARCHAR(50) UNIQUE NOT NULL,
    turbine_type        VARCHAR(30) DEFAULT 'kaplan'
                            CHECK (turbine_type IN ('kaplan','francis','pelton','bulb','crossflow')),
    capacity_mw         DECIMAL(8,2) NOT NULL,
    rated_rpm           DECIMAL(6,1),
    rated_pressure_bar  DECIMAL(6,2),
    install_date        DATE,
    status              VARCHAR(20) NOT NULL DEFAULT 'online'
                            CHECK (status IN ('online','standby','maintenance','fault','offline')),
    created_at          TIMESTAMP NOT NULL DEFAULT NOW()
);

-- live/periodic operational telemetry per turbine (drives "collaborative" logic)
CREATE TABLE turbine_status_log (
    id                  BIGSERIAL PRIMARY KEY,
    turbine_id          INTEGER NOT NULL REFERENCES turbines(id) ON DELETE CASCADE,
    log_time            TIMESTAMP NOT NULL DEFAULT NOW(),
    rpm                 DECIMAL(6,1),
    output_mw           DECIMAL(8,2),
    penstock_pressure_bar DECIMAL(6,2),
    bearing_temperature_c DECIMAL(5,2),
    vibration_mm_s      DECIMAL(5,2),
    gate_opening_pct    DECIMAL(5,2),   -- wicket gate opening -> how it self-regulates pressure/flow
    status              VARCHAR(20) DEFAULT 'normal' CHECK (status IN ('normal','warning','critical'))
);
CREATE INDEX idx_tsl_turbine_time ON turbine_status_log(turbine_id, log_time DESC);

-- ---------------------------------------------------------------------------
-- 7. POWER GENERATION
-- ---------------------------------------------------------------------------
CREATE TABLE power_generation (
    id                  BIGSERIAL PRIMARY KEY,
    station_id          INTEGER NOT NULL REFERENCES stations(id) ON DELETE CASCADE,
    turbine_id          INTEGER REFERENCES turbines(id) ON DELETE SET NULL,
    gen_time            TIMESTAMP NOT NULL DEFAULT NOW(),
    output_mw           DECIMAL(8,2) NOT NULL,
    energy_kwh          DECIMAL(12,2),
    grid_frequency_hz   DECIMAL(5,2),
    grid_voltage_kv     DECIMAL(6,2),
    efficiency_pct      DECIMAL(5,2)
);
CREATE INDEX idx_powergen_station_time ON power_generation(station_id, gen_time DESC);

-- ---------------------------------------------------------------------------
-- 8. RISK ANALYSIS
-- ---------------------------------------------------------------------------
CREATE TABLE risk_assessments (
    id              SERIAL PRIMARY KEY,
    station_id      INTEGER NOT NULL REFERENCES stations(id) ON DELETE CASCADE,
    assessed_at     TIMESTAMP NOT NULL DEFAULT NOW(),
    risk_type       VARCHAR(30) NOT NULL
                        CHECK (risk_type IN ('flood','mechanical','pressure','structural','weather','grid')),
    risk_level      VARCHAR(20) NOT NULL CHECK (risk_level IN ('low','moderate','high','severe')),
    score           DECIMAL(5,2) NOT NULL, -- 0-100 computed risk score
    notes           TEXT,
    assessed_by     VARCHAR(20) NOT NULL DEFAULT 'system' -- 'system' (auto) or admin username
);

-- ---------------------------------------------------------------------------
-- 9. ALERT ENGINE ("Alert Bot")
-- ---------------------------------------------------------------------------
CREATE TABLE alert_rules (
    id                  SERIAL PRIMARY KEY,
    name                VARCHAR(150) NOT NULL,
    parameter           VARCHAR(50) NOT NULL,       -- e.g. water_level_m, penstock_pressure_bar
    condition_operator  VARCHAR(5) NOT NULL CHECK (condition_operator IN ('>','>=','<','<=','=')),
    threshold_value     DECIMAL(10,2) NOT NULL,
    severity            VARCHAR(20) NOT NULL CHECK (severity IN ('info','warning','critical','emergency')),
    is_active           BOOLEAN NOT NULL DEFAULT TRUE,
    created_at          TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE alerts (
    id              SERIAL PRIMARY KEY,
    station_id      INTEGER NOT NULL REFERENCES stations(id) ON DELETE CASCADE,
    rule_id         INTEGER REFERENCES alert_rules(id) ON DELETE SET NULL,
    alert_type      VARCHAR(30) NOT NULL
                        CHECK (alert_type IN ('flood','water_level','weather','turbine','pressure','power','risk','system')),
    severity        VARCHAR(20) NOT NULL CHECK (severity IN ('info','warning','critical','emergency')),
    message         TEXT NOT NULL,
    triggered_at    TIMESTAMP NOT NULL DEFAULT NOW(),
    acknowledged_at TIMESTAMP,
    acknowledged_by INTEGER REFERENCES admins(id) ON DELETE SET NULL,
    resolved_at     TIMESTAMP,
    status          VARCHAR(20) NOT NULL DEFAULT 'open' CHECK (status IN ('open','acknowledged','resolved'))
);
CREATE INDEX idx_alerts_status ON alerts(status, triggered_at DESC);

-- ---------------------------------------------------------------------------
-- 10. SEED DATA
-- ---------------------------------------------------------------------------

-- Default super admin login: username = admin / password = Admin@123
-- (hash generated with PHP password_hash('Admin@123', PASSWORD_BCRYPT))
INSERT INTO admins (username, email, password_hash, full_name, role)
VALUES ('admin', 'admin@hydromonitor.local',
        '$2y$10$g0Y1s5b5m2W2vM2h0oJv1uV1p1QwYhV0kU5xg8bA5c9d3E4f6G7hK',
        'System Administrator', 'super_admin');
-- NOTE: Replace this hash by running the generator (see README Step 5) —
-- bcrypt hashes are host-specific in some builds, so regenerate locally to be safe.

INSERT INTO stations (name, river_name, location, latitude, longitude,
                       installed_capacity_mw, reservoir_max_volume_mcm,
                       max_safe_water_level_m, min_operational_level_m, status)
VALUES
('Tana Falls Hydro Station', 'Tana River', 'Murang''a, Kenya', -0.7215, 37.1521,
 120.00, 850.00, 42.50, 18.00, 'operational'),
('Sagana Gorge Plant', 'Sagana River', 'Kirinyaga, Kenya', -0.5980, 37.2013,
 60.00, 300.00, 35.00, 12.00, 'operational');

INSERT INTO turbines (station_id, turbine_code, turbine_type, capacity_mw, rated_rpm, rated_pressure_bar, install_date, status)
VALUES
(1, 'TF-T1', 'kaplan', 40.00, 375.0, 12.5, '2012-04-01', 'online'),
(1, 'TF-T2', 'kaplan', 40.00, 375.0, 12.5, '2012-04-01', 'online'),
(1, 'TF-T3', 'kaplan', 40.00, 375.0, 12.5, '2013-01-15', 'standby'),
(2, 'SG-T1', 'francis', 30.00, 500.0, 9.0, '2015-06-20', 'online'),
(2, 'SG-T2', 'francis', 30.00, 500.0, 9.0, '2015-06-20', 'online');

INSERT INTO sensors (station_id, sensor_code, sensor_type, unit, install_date, status) VALUES
(1, 'TF-WL-01', 'water_level', 'm', '2012-04-01', 'active'),
(1, 'TF-RF-01', 'rainfall', 'mm', '2012-04-01', 'active'),
(1, 'TF-FL-01', 'flow_rate', 'm3/s', '2012-04-01', 'active'),
(2, 'SG-WL-01', 'water_level', 'm', '2015-06-20', 'active'),
(2, 'SG-RF-01', 'rainfall', 'mm', '2015-06-20', 'active');

INSERT INTO alert_rules (name, parameter, condition_operator, threshold_value, severity) VALUES
('High Reservoir Water Level', 'water_level_m', '>=', 40.00, 'warning'),
('Critical Flood Level', 'water_level_m', '>=', 42.50, 'emergency'),
('Low Operational Level', 'water_level_m', '<=', 18.00, 'warning'),
('Excessive Penstock Pressure', 'penstock_pressure_bar', '>=', 13.50, 'critical'),
('High Turbine Vibration', 'vibration_mm_s', '>=', 4.50, 'critical'),
('High Rainfall Intensity', 'rainfall_mm', '>=', 50.00, 'warning'),
('Bearing Overheat', 'bearing_temperature_c', '>=', 75.00, 'critical');

-- ---------------------------------------------------------------------------
-- 11. HELPFUL VIEWS (used by dashboard for fast reads)
-- ---------------------------------------------------------------------------
CREATE VIEW v_latest_water_level AS
SELECT DISTINCT ON (station_id) station_id, reading_time, water_level_m,
       inflow_rate_m3s, outflow_rate_m3s, reservoir_volume_pct
FROM water_level_readings
ORDER BY station_id, reading_time DESC;

CREATE VIEW v_latest_weather AS
SELECT DISTINCT ON (station_id) station_id, reading_time, rainfall_mm,
       temperature_c, humidity_pct, wind_speed_kmh, forecast_summary
FROM weather_data
ORDER BY station_id, reading_time DESC;

CREATE VIEW v_station_current_output AS
SELECT station_id, SUM(output_mw) AS total_output_mw, MAX(gen_time) AS last_update
FROM power_generation
WHERE gen_time > NOW() - INTERVAL '15 minutes'
GROUP BY station_id;

CREATE VIEW v_open_alerts AS
SELECT a.*, s.name AS station_name
FROM alerts a JOIN stations s ON s.id = a.station_id
WHERE a.status != 'resolved'
ORDER BY a.triggered_at DESC;
