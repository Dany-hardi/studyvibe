-- Class table inheritance: one table for the abstract superclass, one table per concrete subclass sharing its primary key.
CREATE TABLE owner (
    id         BIGSERIAL PRIMARY KEY,
    full_name  VARCHAR(120) NOT NULL,
    email      VARCHAR(190) NOT NULL UNIQUE
);

CREATE TABLE vehicle (
    id            BIGSERIAL PRIMARY KEY,
    vehicle_type  CHAR(1)      NOT NULL CHECK (vehicle_type IN ('C', 'T', 'M')),   -- discriminator: Car, Truck, Motorbike
    plate         VARCHAR(7)   NOT NULL UNIQUE CHECK (plate ~ '^[A-Z]{2}[0-9]{3}[A-Z]{2}$'),
    brand         VARCHAR(60)  NOT NULL,
    year          SMALLINT     NOT NULL CHECK (year >= 1950 AND year <= 2100),      -- the upper bound "current year + 1" cannot be a CHECK
                                                                                      -- (CURRENT_DATE is not immutable): a BEFORE INSERT/UPDATE
                                                                                      -- trigger, or the application, enforces it
    owner_id      BIGINT       NOT NULL REFERENCES owner (id),                       -- exactly one owner; an owner may own none
    UNIQUE (id, vehicle_type)                                                         -- target of the composite foreign keys below
);

CREATE TABLE car (
    id            BIGINT PRIMARY KEY,
    vehicle_type  CHAR(1) NOT NULL DEFAULT 'C' CHECK (vehicle_type = 'C'),
    doors         SMALLINT NOT NULL CHECK (doors BETWEEN 2 AND 5),
    FOREIGN KEY (id, vehicle_type) REFERENCES vehicle (id, vehicle_type) ON DELETE CASCADE
);

CREATE TABLE truck (
    id            BIGINT PRIMARY KEY,
    vehicle_type  CHAR(1) NOT NULL DEFAULT 'T' CHECK (vehicle_type = 'T'),
    payload_kg    NUMERIC(10,2) NOT NULL CHECK (payload_kg > 0),
    axles         SMALLINT NOT NULL CHECK (axles >= 2),
    FOREIGN KEY (id, vehicle_type) REFERENCES vehicle (id, vehicle_type) ON DELETE CASCADE
);

CREATE TABLE motorbike (
    id            BIGINT PRIMARY KEY,
    vehicle_type  CHAR(1) NOT NULL DEFAULT 'M' CHECK (vehicle_type = 'M'),
    engine_cc     INTEGER NOT NULL CHECK (engine_cc > 0),
    FOREIGN KEY (id, vehicle_type) REFERENCES vehicle (id, vehicle_type) ON DELETE CASCADE
);
