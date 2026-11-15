# 🚲 Sistema Transaccional Bike Store — Segundo Avance (SC-504)

Repositorio oficial del **Segundo Avance del Proyecto** del curso **SC-504: Lenguajes de Base de Datos** (Universidad Fidélitas).  
El sistema implementa una arquitectura transaccional con **Oracle Database (PL/SQL)** en el backend de datos y **PHP 8+ (PDO_OCI / OCI8)** en la capa de aplicación, cumpliendo estrictamente con la normativa de **cero consultas `SELECT` directas** desde el código fuente de la aplicación.

---

## 📁 Estructura del Repositorio

```text
bike-store/
├── config/
│   └── conexion.php                 # Conexión segura PDO hacia Oracle + soporte de cursores SYS_REFCURSOR
├── public/
│   ├── login.php                    # Inicio de sesión invocando exclusivamente sp_validar_login (IN / OUT)
│   ├── listado_accesos.php          # Listados analíticos y bitácora consumiendo SYS_REFCURSOR y SPs CRUD
│   └── logout.php                   # Cierre seguro de sesión
├── sql/
│   ├── 01_dcl_usuarios_roles.sql    # Bloque 1: DCL (Esquema bike_owner, roles y usuarios Oracle)
│   └── 02_ddl_plsql_bikestore.sql   # Bloque 2: DDL (DROP TABLE IF EXISTS + 25 tablas), Vistas, Funciones, Cursores y SPs
├── .gitignore
└── README.md
```

---

## 🛠️ Componentes Implementados en este Avance

1. **Seguridad y Control de Accesos en Oracle (DCL):**
   - Creación del dueño del esquema `bike_owner` con cuota sobre el tablespace `DATA`.
   - Definición de 4 roles transaccionales (`rol_admin_bike`, `rol_desarrollador_bike`, `rol_ventas_bike`, `rol_inventario_bike`) bajo el principio de menor privilegio.
2. **Modelo Físico y Limpieza Idempotente (DDL):**
   - Bloque inicial de limpieza con `DROP TABLE IF EXISTS ... CASCADE CONSTRAINTS PURGE`.
   - Creación de las 22 tablas del negocio **Bike Store** más las 3 tablas de seguridad y auditoría (`bike_roles_sistema`, `bike_usuarios_sistema`, `bike_bitacora_accesos`).
3. **Objetos de Programación PL/SQL (Cero `SELECT` directos en PHP):**
   - **3 Vistas Analíticas:** `vw_usuarios_control_acceso`, `vw_estadisticas_acceso_por_rol`, `vw_bitacora_accesos_detalle`.
   - **3 Funciones PL/SQL:** `fn_obtener_conteo_accesos`, `fn_obtener_rol_usuario`, `fn_usuario_esta_activo`.
   - **Procedimientos Almacenados (CRUD + Cursores `SYS_REFCURSOR`):**
     - Roles: `sp_insertar_rol_sistema`, `sp_actualizar_rol_sistema`, `sp_desactivar_rol_sistema`, `sp_listar_roles_sistema`.
     - Usuarios y Auditoría: `sp_insertar_usuario_sistema`, `sp_actualizar_usuario_sistema`, `sp_desactivar_usuario_sistema`, `sp_listar_usuarios_acceso`, `sp_listar_estadisticas_roles`, `sp_listar_bitacora_accesos`.
   - **Procedimiento Principal de Autenticación (`sp_validar_login`):**
     - Valida estado del usuario (`ACTIVO` / `INACTIVO`), incrementa `total_accesos`, reinicia o incrementa `intentos_fallidos`, registra cada evento en `bike_bitacora_accesos` y maneja bloques `EXCEPTION` (`NO_DATA_FOUND`, `OTHERS`).

---

## 🔐 Credenciales de Base de Datos Oracle (DCL)

Todos los usuarios de base de datos fueron configurados para pruebas académicas con la siguiente contraseña estándar:

| Usuario Oracle | Contraseña | Rol Asignado | Propósito / Alcance en el Sistema |
| :--- | :--- | :--- | :--- |
| **`bike_owner`** | `BikeStore1234Fide` | *Dueño del Esquema* | Propietario de todas las tablas, vistas, funciones y procedimientos PL/SQL. |
| **`bike_admin_usr`** | `BikeStore1234Fide` | `rol_admin_bike` | Administración general de objetos y control total de tablas y seguridad. |
| **`bike_dev_usr`** | `BikeStore1234Fide` | `rol_desarrollador_bike` | Desarrollo y mantenimiento de objetos PL/SQL, vistas, secuencias y triggers. |
| **`bike_ventas_usr`** | `BikeStore1234Fide` | `rol_ventas_bike` | Operación exclusiva sobre clientes, órdenes, detalle de orden, facturación y login. |
| **`bike_inventario_usr`** | `BikeStore1234Fide` | `rol_inventario_bike` | Operación sobre marcas, categorías, catálogo de productos, existencias y login. |

---

## 👤 Usuarios Semilla de la Aplicación (`sp_validar_login`)

Para probar el inicio de sesión desde `public/login.php` (tabla `bike_usuarios_sistema`):

| Username App | Contraseña | Rol en Aplicación |
| :--- | :--- | :--- |
| `bike_owner` | `BikeStore1234Fide` | `ADMINISTRADOR` |
| `dev_bike` | `BikeStore1234Fide` | `DESARROLLADOR` |
| `vendedor1` | `BikeStore1234Fide` | `VENTAS` |
| `bodega1` | `BikeStore1234Fide` | `INVENTARIO` |

---

## 🚀 Orden de Instalación y Ejecución

1. **En Oracle SQL Developer (como `ADMIN` o `SYSTEM`):**
   - Ejecutar `sql/01_dcl_usuarios_roles.sql`.
2. **En Oracle SQL Developer (conectado como `bike_owner` o `ADMIN`):**
   - Ejecutar `sql/02_ddl_plsql_bikestore.sql`.
3. **En el servidor web PHP (XAMPP / Apache / PHP Built-in Server):**
   - Habilitar la extensión `pdo_oci` (y opcionalmente `oci8`) en `php.ini`.
   - Iniciar el servidor local desde la raíz del proyecto:
     ```bash
     php -S localhost:8000 -t public
     ```
   - Abrir en el navegador: `http://localhost:8000/login.php`.
