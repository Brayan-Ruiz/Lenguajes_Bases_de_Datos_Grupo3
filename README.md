# 🚲 Proyecto Bike Store - Sistema Transaccional

Repositorio oficial del proyecto para el curso **SC-504 Lenguajes de Base de Datos**[cite: 1, 3]. El sistema modela una solución transaccional centralizada para la gestión de inventarios, clientes, órdenes y facturación de una empresa de bicicletas

📋 Descripción del Proyecto

🛠️ Lo que ya está hecho
* **Modelo Físico y Relacional:** Creación de tablas normalizadas en Oracle para marcas, categorías, productos, precios, tiendas, inventarios, clientes, direcciones, teléfonos, órdenes y facturación, asegurando la integridad referencial mediante llaves primarias y foráneas
* **Esquema de Control de Acceso (DCL):** Definición de roles de base de datos (`rol_admin_bike`, `rol_desarrollador_bike`, `rol_ventas_bike`, `rol_inventario_bike`) y usuarios estructurados según el perfil de seguridad
* **Módulo de Autenticación y Auditoría (PL/SQL):** Implementación de tablas para control de sesiones (`bike_roles_sistema`, `bike_usuarios_sistema`, `bike_bitacora_accesos`), vistas analíticas, funciones de validación, cursores y el procedimiento almacenado principal (`sp_validar_login`) que registra el conteo de accesos y previene consultas directas desde la aplicación.

### ⏳ Lo que hace falta por hacer (Segundo Avance)
* **Conexión Front-End:** Integración de la base de datos con la interfaz gráfica utilizando el lenguaje de programación seleccionado por el equipo
* **Completar Objetivos PL/SQL:** Asegurar el cumplimiento de los volúmenes de objetos requeridos (procedimientos, vistas, funciones, cursores y triggers) correspondientes al 50% de avance del curso[cite: 1].
* **Diccionario de Datos:** Generación y exportación de la documentación técnica estructurada desde Oracle SQL Developer

---

🔐 Credenciales de Acceso a la Base de Datos (Roles)

Para la conexión, pruebas y autenticación en el sistema transaccional de Oracle, se han configurado los siguientes usuarios y roles:

| Rol / Perfil | Usuario (Username) | Contraseña (Password) | Permisos y Funcionalidad |
| :--- | :--- | :--- | :--- |
| **Dueño de Esquema** | `bike_owner` | `BikeStore1234Fide` | Administración y control total de objetos del esquema transaccional. |
| **Administrador** | `bike_admin_usr` | `BikeStore1234Fide` | Gestión general del sistema, roles y auditoría de accesos. |
| **Desarrollador** | `bike_dev_usr` | `BikeStore1234Fide` | Programación y mantenimiento de estructuras PL/SQL, vistas y procedimientos. |
| **Ventas / Cajero** | `bike_ventas_usr` | `BikeStore1234Fide` | Operaciones de clientes, órdenes, facturación y ejecución de login. |
| **Inventario / Bodega** | `bike_inventario_usr` | `BikeStore1234Fide` | Control de productos, marcas, categorías y existencias en bodega. |
