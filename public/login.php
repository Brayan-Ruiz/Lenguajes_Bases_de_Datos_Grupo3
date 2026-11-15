<?php
/**
 * CURSO: SC-504 Lenguajes de Base de Datos - Segundo Avance
 * PROYECTO: Sistema Transaccional "Bike Store"
 * ARCHIVO: public/login.php
 * DESCRIPCIÓN: Procesamiento de inicio de sesión invocando exclusivamente
 *              al procedimiento almacenado sp_validar_login (cero SELECT directos).
 */

declare(strict_types=1);
session_start();

require_once __DIR__ . '/../config/conexion.php';

$mensajeError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $mensajeError = 'Por favor ingrese su usuario y contraseña.';
    } else {
        try {
            // Invoca a BEGIN bike_owner.sp_validar_login(:p_username, :p_password, ...); END;
            $respuesta = ConexionOracle::validarLogin($username, $password);

            $usuarioId      = $respuesta['usuario_id'];
            $nombreCompleto = $respuesta['nombre_completo'];
            $nombreRol      = $respuesta['nombre_rol'];
            $totalAccesos   = $respuesta['total_accesos'];
            $resultado      = $respuesta['resultado'];

            if ($resultado === 'ACCESO CONCEDIDO' && !empty($usuarioId)) {
                session_regenerate_id(true);
                $_SESSION['usuario_id']      = (int)$usuarioId;
                $_SESSION['username']        = $username;
                $_SESSION['nombre_completo'] = $nombreCompleto;
                $_SESSION['nombre_rol']      = $nombreRol;
                $_SESSION['total_accesos']   = (int)$totalAccesos;
                $_SESSION['resultado_login'] = $resultado;

                header('Location: listado_accesos.php');
                exit;
            } else {
                $mensajeError = $resultado !== '' ? $resultado : 'Credenciales inválidas.';
            }
        } catch (Throwable $e) {
            $mensajeError = 'Excepción controlada: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bike Store | Control de Acceso Oracle PL/SQL</title>
    <style>
        :root {
            --bg-dark: #0f172a;
            --card-bg: #1e293b;
            --accent: #38bdf8;
            --accent-hover: #0284c7;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --danger: #ef4444;
            --border: #334155;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', system-ui, sans-serif; }
        body {
            background: radial-gradient(circle at top right, #1e293b, var(--bg-dark));
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .login-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 14px;
            width: 100%;
            max-width: 430px;
            padding: 2.25rem;
            box-shadow: 0 20px 35px rgba(0, 0, 0, 0.45);
        }
        .badge {
            display: inline-block;
            background: rgba(56, 189, 248, 0.15);
            color: var(--accent);
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.25rem 0.65rem;
            border-radius: 999px;
            margin-bottom: 0.75rem;
            letter-spacing: 0.04em;
        }
        h1 { font-size: 1.65rem; margin-bottom: 0.35rem; }
        p.subtitle { color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.5rem; }
        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border-left: 4px solid var(--danger);
            color: #fca5a5;
            padding: 0.75rem 1rem;
            border-radius: 6px;
            font-size: 0.88rem;
            margin-bottom: 1.25rem;
        }
        .form-group { margin-bottom: 1.15rem; }
        label { display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.4rem; }
        input {
            width: 100%;
            padding: 0.75rem 0.9rem;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: #0f172a;
            color: var(--text-main);
            font-size: 0.95rem;
        }
        input:focus { outline: 2px solid var(--accent); border-color: transparent; }
        button {
            width: 100%;
            padding: 0.85rem;
            margin-top: 0.5rem;
            border: none;
            border-radius: 8px;
            background: var(--accent);
            color: #0f172a;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            transition: background 0.2s;
        }
        button:hover { background: var(--accent-hover); color: #fff; }
        .footer-note {
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
            font-size: 0.78rem;
            color: var(--text-muted);
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <span class="badge">SC-504 • ORACLE PL/SQL</span>
        <h1>Bike Store</h1>
        <p class="subtitle">Autenticación auditada mediante <code>sp_validar_login</code></p>

        <?php if ($mensajeError !== ''): ?>
            <div class="alert-error">
                <strong>Auditoría Oracle:</strong> <?= htmlspecialchars($mensajeError, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" autocomplete="off">
            <div class="form-group">
                <label for="username">Usuario del Sistema</label>
                <input type="text" id="username" name="username" value="bike_owner" placeholder="Ej. bike_owner, dev_bike, vendedor1" required>
            </div>
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" value="BikeStore1234Fide" placeholder="••••••••••••" required>
            </div>
            <button type="submit">Iniciar Sesión</button>
        </form>

        <div class="footer-note">
            Cero consultas <code>SELECT</code> directas • Cursores y Procedimientos Almacenados
        </div>
    </div>
</body>
</html>
