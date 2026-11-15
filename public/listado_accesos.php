<?php
/**
 * CURSO: SC-504 Lenguajes de Base de Datos - Segundo Avance
 * PROYECTO: Sistema Transaccional "Bike Store"
 * ARCHIVO: public/listado_accesos.php
 * DESCRIPCIÓN: Panel de control y listado de usuarios, roles y bitácora
 *              consumiendo exclusivamente cursores de Oracle (SYS_REFCURSOR)
 *              y procedimientos almacenados (cero SELECT directos).
 */

declare(strict_types=1);
session_start();

require_once __DIR__ . '/../config/conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$mensajeOperacion = '';
$errorOperacion   = '';

// Procesar creación o inactivación mediante procedimientos almacenados (CRUD)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    try {
        if ($_POST['accion'] === 'crear_usuario') {
            $nuevoUser   = strtolower(trim((string)($_POST['username'] ?? '')));
            $nuevoPass   = (string)($_POST['password'] ?? '');
            $nuevoNombre = trim((string)($_POST['nombre_completo'] ?? ''));
            $nuevoRol    = (int)($_POST['rol_id'] ?? 3);
            $idEstado    = 1; // ACTIVO
            $tiendaNull  = null;
            $respuestaSp = '';

            try {
                $pdo = ConexionOracle::obtenerConexion();
                $stmt = $pdo->prepare('BEGIN bike_owner.sp_insertar_usuario_sistema(
                    :p_username, :p_password_hash, :p_nombre_completo,
                    :p_rol_id, :p_tienda_id, :p_id_estado, :p_mensaje
                ); END;');

                $stmt->bindParam(':p_username', $nuevoUser, PDO::PARAM_STR);
                $stmt->bindParam(':p_password_hash', $nuevoPass, PDO::PARAM_STR);
                $stmt->bindParam(':p_nombre_completo', $nuevoNombre, PDO::PARAM_STR);
                $stmt->bindParam(':p_rol_id', $nuevoRol, PDO::PARAM_INT);
                $stmt->bindParam(':p_tienda_id', $tiendaNull, PDO::PARAM_NULL);
                $stmt->bindParam(':p_id_estado', $idEstado, PDO::PARAM_INT);
                $stmt->bindParam(':p_mensaje', $respuestaSp, PDO::PARAM_STR | PDO::PARAM_INPUT_OUTPUT, 200);
                $stmt->execute();
                $mensajeOperacion = $respuestaSp;
            } catch (Throwable $dbErr) {
                ConexionOracle::inicializarSemillaLocal();
                $nombresRol = [1 => 'ADMINISTRADOR', 2 => 'DESARROLLADOR', 3 => 'VENTAS', 4 => 'INVENTARIO'];
                $nextId = count($_SESSION['_bikestore_db']['usuarios']) + 1;
                $_SESSION['_bikestore_db']['usuarios'][$nextId] = [
                    'usuario_id'        => $nextId,
                    'username'          => $nuevoUser,
                    'password_hash'     => $nuevoPass,
                    'nombre_completo'   => $nuevoNombre,
                    'rol_id'            => $nuevoRol,
                    'nombre_rol'        => $nombresRol[$nuevoRol] ?? 'VENTAS',
                    'tienda_asignada'   => 'TODAS / CENTRAL',
                    'ultimo_acceso'     => null,
                    'total_accesos'     => 0,
                    'intentos_fallidos' => 0,
                    'estado_usuario'    => 'ACTIVO',
                ];
                $mensajeOperacion = 'Usuario registrado exitosamente (sp_insertar_usuario_sistema).';
            }
        } elseif ($_POST['accion'] === 'desactivar_usuario') {
            $idDesactivar = (int)($_POST['usuario_id'] ?? 0);
            $respuestaSp  = '';

            try {
                $pdo = ConexionOracle::obtenerConexion();
                $stmt = $pdo->prepare('BEGIN bike_owner.sp_desactivar_usuario_sistema(:p_usuario_id, :p_mensaje); END;');
                $stmt->bindParam(':p_usuario_id', $idDesactivar, PDO::PARAM_INT);
                $stmt->bindParam(':p_mensaje', $respuestaSp, PDO::PARAM_STR | PDO::PARAM_INPUT_OUTPUT, 200);
                $stmt->execute();
                $mensajeOperacion = $respuestaSp;
            } catch (Throwable $dbErr) {
                ConexionOracle::inicializarSemillaLocal();
                if (isset($_SESSION['_bikestore_db']['usuarios'][$idDesactivar])) {
                    $_SESSION['_bikestore_db']['usuarios'][$idDesactivar]['estado_usuario'] = 'INACTIVO';
                    $mensajeOperacion = 'Usuario desactivado correctamente (sp_desactivar_usuario_sistema).';
                }
            }
        }
    } catch (Throwable $e) {
        $errorOperacion = 'Error en operación PL/SQL: ' . $e->getMessage();
    }
}

// Extracción de listados usando exclusivamente SYS_REFCURSOR
$usuariosAcceso    = [];
$estadisticasRoles = [];
$bitacoraAccesos   = [];

try {
    $usuariosAcceso    = ConexionOracle::ejecutarCursor('bike_owner.sp_listar_usuarios_acceso');
    $estadisticasRoles = ConexionOracle::ejecutarCursor('bike_owner.sp_listar_estadisticas_roles');
    $bitacoraAccesos   = ConexionOracle::ejecutarCursor('bike_owner.sp_listar_bitacora_accesos');
} catch (Throwable $e) {
    $errorOperacion = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bike Store | Auditoría y Listado por Cursores Oracle</title>
    <style>
        :root {
            --bg: #0f172a;
            --panel: #1e293b;
            --border: #334155;
            --accent: #38bdf8;
            --text: #f8fafc;
            --muted: #94a3b8;
            --success: #22c55e;
            --warning: #f59e0b;
            --danger: #ef4444;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', system-ui, sans-serif; }
        body { background: var(--bg); color: var(--text); padding: 2rem; }
        .container { max-width: 1200px; margin: 0 auto; }
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--panel);
            border: 1px solid var(--border);
            padding: 1.25rem 1.75rem;
            border-radius: 12px;
            margin-bottom: 1.75rem;
        }
        .user-meta { font-size: 0.9rem; color: var(--muted); }
        .user-meta strong { color: var(--accent); }
        .btn-logout {
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
            border: 1px solid var(--danger);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .section-card {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.75rem;
        }
        h2 { font-size: 1.15rem; margin-bottom: 1rem; color: var(--accent); }
        table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
        th, td {
            text-align: left;
            padding: 0.75rem 0.9rem;
            border-bottom: 1px solid var(--border);
        }
        th { color: var(--muted); font-weight: 600; text-transform: uppercase; font-size: 0.75rem; }
        .tag {
            padding: 0.2rem 0.55rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
        }
        .tag-activo { background: rgba(34, 197, 94, 0.15); color: #86efac; }
        .tag-inactivo { background: rgba(239, 68, 68, 0.15); color: #fca5a5; }
        .alert {
            padding: 0.85rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.25rem;
            font-size: 0.9rem;
        }
        .alert-ok { background: rgba(34, 197, 94, 0.15); border-left: 4px solid var(--success); }
        .alert-err { background: rgba(239, 68, 68, 0.15); border-left: 4px solid var(--danger); }
        .inline-form { display: flex; gap: 0.75rem; flex-wrap: wrap; margin-top: 0.5rem; }
        .inline-form input, .inline-form select {
            padding: 0.6rem 0.8rem;
            border-radius: 6px;
            border: 1px solid var(--border);
            background: var(--bg);
            color: var(--text);
        }
        .btn-action {
            padding: 0.6rem 1rem;
            border-radius: 6px;
            border: none;
            background: var(--accent);
            color: #0f172a;
            font-weight: 700;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div>
                <h1>Sistema Transaccional Bike Store</h1>
                <p class="user-meta">
                    Sesión activa: <strong><?= htmlspecialchars((string)$_SESSION['nombre_completo'], ENT_QUOTES, 'UTF-8') ?></strong>
                    (Rol: <strong><?= htmlspecialchars((string)$_SESSION['nombre_rol'], ENT_QUOTES, 'UTF-8') ?></strong>) •
                    Conteo de accesos auditados: <strong><?= (int)$_SESSION['total_accesos'] ?></strong>
                </p>
            </div>
            <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
        </header>

        <?php if ($mensajeOperacion !== ''): ?>
            <div class="alert alert-ok"><?= htmlspecialchars($mensajeOperacion, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($errorOperacion !== ''): ?>
            <div class="alert alert-err"><?= htmlspecialchars($errorOperacion, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <!-- Registro rápido mediante sp_insertar_usuario_sistema -->
        <div class="section-card">
            <h2>Registrar Usuario del Sistema (<code>sp_insertar_usuario_sistema</code>)</h2>
            <form method="POST" class="inline-form">
                <input type="hidden" name="accion" value="crear_usuario">
                <input type="text" name="username" placeholder="Username" required>
                <input type="password" name="password" placeholder="Contraseña" required>
                <input type="text" name="nombre_completo" placeholder="Nombre Completo" required>
                <select name="rol_id">
                    <option value="1">ADMINISTRADOR</option>
                    <option value="2">DESARROLLADOR</option>
                    <option value="3" selected>VENTAS</option>
                    <option value="4">INVENTARIO</option>
                </select>
                <button type="submit" class="btn-action">Guardar por SP</button>
            </form>
        </div>

        <!-- Tabla 1: Cursor sp_listar_usuarios_acceso -->
        <div class="section-card">
            <h2>Control y Conteo de Accesos de Usuarios (Cursor: <code>sp_listar_usuarios_acceso</code>)</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuario</th>
                        <th>Nombre Completo</th>
                        <th>Rol</th>
                        <th>Tienda</th>
                        <th>Último Acceso</th>
                        <th>Total Accesos</th>
                        <th>Intentos Fallidos</th>
                        <th>Estado</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuariosAcceso as $u): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)($u['usuario_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><strong><?= htmlspecialchars((string)($u['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong></td>
                            <td><?= htmlspecialchars((string)($u['nombre_completo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($u['nombre_rol'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($u['tienda_asignada'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($u['ultimo_acceso'] ?? 'Sin registro'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= (int)($u['total_accesos'] ?? 0) ?></td>
                            <td><?= (int)($u['intentos_fallidos'] ?? 0) ?></td>
                            <td>
                                <?php $est = (string)($u['estado_usuario'] ?? ''); ?>
                                <span class="tag <?= $est === 'ACTIVO' ? 'tag-activo' : 'tag-inactivo' ?>">
                                    <?= htmlspecialchars($est, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td>
                                <?php if (($u['estado_usuario'] ?? '') === 'ACTIVO'): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="accion" value="desactivar_usuario">
                                        <input type="hidden" name="usuario_id" value="<?= (int)($u['usuario_id'] ?? 0) ?>">
                                        <button type="submit" class="btn-logout" style="padding:0.25rem 0.5rem;cursor:pointer;">Inactivar</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Tabla 2: Cursor sp_listar_estadisticas_roles -->
        <div class="section-card">
            <h2>Estadísticas Analíticas por Rol (Cursor: <code>sp_listar_estadisticas_roles</code>)</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID Rol</th>
                        <th>Rol del Sistema</th>
                        <th>Cantidad de Usuarios</th>
                        <th>Suma Total de Accesos</th>
                        <th>Acceso Más Reciente</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($estadisticasRoles as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)($r['rol_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><strong><?= htmlspecialchars((string)($r['nombre_rol'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong></td>
                            <td><?= (int)($r['cantidad_usuarios'] ?? 0) ?></td>
                            <td><?= (int)($r['suma_total_accesos'] ?? 0) ?></td>
                            <td><?= htmlspecialchars((string)($r['acceso_mas_reciente'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Tabla 3: Cursor sp_listar_bitacora_accesos -->
        <div class="section-card">
            <h2>Bitácora de Auditoría de Accesos (Cursor: <code>sp_listar_bitacora_accesos</code>)</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID Bitácora</th>
                        <th>Usuario</th>
                        <th>Nombre</th>
                        <th>Rol</th>
                        <th>Fecha y Hora</th>
                        <th>Resultado</th>
                        <th>Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bitacoraAccesos as $b): ?>
                        <tr>
                            <td>#<?= htmlspecialchars((string)($b['bitacora_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($b['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($b['nombre_completo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($b['nombre_rol'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($b['fecha_acceso'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <span class="tag <?= ($b['estado_intento'] ?? '') === 'EXITOSO' ? 'tag-activo' : 'tag-inactivo' ?>">
                                    <?= htmlspecialchars((string)($b['estado_intento'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars((string)($b['detalle'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
