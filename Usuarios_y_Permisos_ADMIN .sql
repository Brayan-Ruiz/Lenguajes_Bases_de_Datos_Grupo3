-- =====================================================================
-- BLOQUE 1: DCL - DUEÑO DE ESQUEMA, ROLES Y USUARIOS DE ORACLE
-- (Ejecutar en Oracle SQL Developer conectado como ADMIN / SYSTEM)
-- =====================================================================

-- 1. Limpieza directa con IF EXISTS de Usuarios y Roles
DROP USER IF EXISTS bike_owner CASCADE;
DROP USER IF EXISTS bike_admin_usr CASCADE;
DROP USER IF EXISTS bike_dev_usr CASCADE;
DROP USER IF EXISTS bike_ventas_usr CASCADE;
DROP USER IF EXISTS bike_inventario_usr CASCADE;

DROP ROLE IF EXISTS rol_admin_bike;
DROP ROLE IF EXISTS rol_desarrollador_bike;
DROP ROLE IF EXISTS rol_ventas_bike;
DROP ROLE IF EXISTS rol_inventario_bike;

-- 2. Creación del dueño del esquema (Código de tu compañera)
CREATE USER bike_owner IDENTIFIED BY "BikeStore1234Fide";

GRANT CREATE SESSION, CREATE TABLE, CREATE VIEW,
      CREATE SEQUENCE, CREATE PROCEDURE, CREATE TRIGGER
   TO bike_owner;

ALTER USER bike_owner QUOTA UNLIMITED ON DATA;

-- 3. Creación de Roles del Sistema Transaccional (Objetivo Específico 2)
CREATE ROLE rol_admin_bike;
CREATE ROLE rol_desarrollador_bike;
CREATE ROLE rol_ventas_bike;
CREATE ROLE rol_inventario_bike;

GRANT CREATE SESSION, CREATE TABLE, CREATE VIEW,
      CREATE SEQUENCE, CREATE PROCEDURE, CREATE TRIGGER
   TO rol_admin_bike;

GRANT CREATE SESSION, CREATE VIEW, CREATE PROCEDURE, CREATE SEQUENCE, CREATE TRIGGER
   TO rol_desarrollador_bike;

GRANT CREATE SESSION TO rol_ventas_bike;
GRANT CREATE SESSION TO rol_inventario_bike;

-- 4. Creación de Usuarios y asignación de Roles
CREATE USER bike_admin_usr IDENTIFIED BY "BikeStore1234Fide";
ALTER USER bike_admin_usr QUOTA UNLIMITED ON DATA;
GRANT rol_admin_bike TO bike_admin_usr;

CREATE USER bike_dev_usr IDENTIFIED BY "BikeStore1234Fide";
ALTER USER bike_dev_usr QUOTA 50M ON DATA;
GRANT rol_desarrollador_bike TO bike_dev_usr;

CREATE USER bike_ventas_usr IDENTIFIED BY "BikeStore1234Fide";
GRANT rol_ventas_bike TO bike_ventas_usr;

CREATE USER bike_inventario_usr IDENTIFIED BY "BikeStore1234Fide";
GRANT rol_inventario_bike TO bike_inventario_usr;