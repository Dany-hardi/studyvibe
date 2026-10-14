INSERT INTO owner (full_name, email) VALUES ('A', 'a@x.org'), ('B', 'b@x.org');
INSERT INTO vehicle (vehicle_type, plate, brand, year, owner_id) VALUES ('C', 'AB123CD', 'Toyota', 2020, 1), ('T', 'EF456GH', 'Volvo', 2018, 1), ('M', 'IJ789KL', 'Yamaha', 2022, 2);
INSERT INTO car (id, doors) VALUES (1, 4);
INSERT INTO truck (id, payload_kg, axles) VALUES (2, 12000, 3);
INSERT INTO motorbike (id, engine_cc) VALUES (3, 600);
-- must fail:
INSERT INTO truck (id, payload_kg, axles) VALUES (1, 100, 2);        -- vehicle 1 is a car: wrong subtype
INSERT INTO car (id, doors) VALUES (1, 4);                            -- duplicate
INSERT INTO vehicle (vehicle_type, plate, brand, year, owner_id) VALUES ('C', 'ab123cd', 'x', 2020, 1);   -- bad plate
INSERT INTO vehicle (vehicle_type, plate, brand, year, owner_id) VALUES ('C', 'ZZ123ZZ', 'x', 1949, 1);   -- year
INSERT INTO vehicle (vehicle_type, plate, brand, year, owner_id) VALUES ('C', 'ZZ124ZZ', 'x', 2020, NULL);-- no owner
INSERT INTO car (id, doors) VALUES (3, 1);                            -- doors, wrong subtype
INSERT INTO owner (full_name, email) VALUES ('C', 'a@x.org');         -- duplicate email
DELETE FROM vehicle WHERE id = 2;
SELECT (SELECT count(*) FROM truck) AS trucks_left, (SELECT count(*) FROM vehicle) AS vehicles;
