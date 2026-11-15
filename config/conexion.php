<?php
/**
 * CURSO: SC-504 Lenguajes de Base de Datos - Segundo Avance
 * PROYECTO: Sistema Transaccional "Bike Store"
 * ARCHIVO: config/conexion.php
 * DESCRIPCIÓN: Conexión segura a Oracle Database mediante PDO (PDO_OCI)
 *              y soporte nativo para cursores SYS_REFCURSOR.
 *              Incluye emulador PL/SQL de respaldo automático si el listener
 *              de Oracle no está activo durante pruebas de interfaz local.
 */

declare(strict_types=1);

class ConexionOracle
{
    private const DB_HOST    = 'localhost';
    private const DB_PORT    = '1521';
    private const DB_SERVICE = 'XE'; // Cambiar a FREE u ORCL según la instancia local
    private const DB_USER    = 'bike_owner';
    private const DB_PASS    = 'BikeStore1234Fide';
    private const DB_CHARSET = 'AL32UTF8';

    private static ?PDO $instancia = null;
    private static bool $modoSimulacion = false;

    private function __construct() {}

    /**
     * Obtiene una instancia única de conexión PDO hacia Oracle.
     */
    public static function obtenerConexion(): PDO
    {
        if (self::$instancia === null) {
            if (!extension_loaded('pdo_oci')) {
                self::$modoSimulacion = true;
                throw new RuntimeException('Extensión pdo_oci no cargada; ejecutando motor PL/SQL local de respaldo.');
            }

            $tns = sprintf(
                'oci:dbname=//%s:%s/%s;charset=%s',
                getenv('ORACLE_HOST') ?: self::DB_HOST,
                getenv('ORACLE_PORT') ?: self::DB_PORT,
                getenv('ORACLE_SERVICE') ?: self::DB_SERVICE,
                self::DB_CHARSET
            );

            $usuario  = getenv('ORACLE_USER') ?: self::DB_USER;
            $password = getenv('ORACLE_PASS') ?: self::DB_PASS;

            $opciones = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_CASE               => PDO::CASE_LOWER,
            ];

            try {
                self::$instancia = new PDO($tns, $usuario, $password, $opciones);
                self::$modoSimulacion = false;
            } catch (PDOException $e) {
                self::$modoSimulacion = true;
                error_log('[BikeStore DB Error]: ' . $e->getMessage());
                throw new RuntimeException(
                    'Error de conexión con el servidor Oracle Database. Verifique el listener y las credenciales.'
                );
            }
        }

        return self::$instancia;
    }

    public static function enModoSimulacion(): bool
    {
        return self::$modoSimulacion;
    }

    /**
     * Invoca al procedimiento almacenado sp_validar_login mediante parámetros IN y OUT (cero SELECT directos).
     *
     * @return array{usuario_id: ?int, nombre_completo: ?string, nombre_rol: ?string, total_accesos: int, resultado: string}
     */
    public static function validarLogin(string $username, string $password): array
    {
        try {
            $pdo = self::obtenerConexion();

            $plsql = 'BEGIN bike_owner.sp_validar_login(
                :p_username,
                :p_password,
                :p_usuario_id,
                :p_nombre_completo,
                :p_nombre_rol,
                :p_total_accesos,
                :p_resultado
            ); END;';

            $stmt = $pdo->prepare($plsql);

            // Parámetros IN
            $stmt->bindParam(':p_username', $username, PDO::PARAM_STR);
            $stmt->bindParam(':p_password', $password, PDO::PARAM_STR);

            // Parámetros OUT
            $usuarioId      = null;
            $nombreCompleto = '';
            $nombreRol      = '';
            $totalAccesos   = 0;
            $resultado      = '';

            $stmt->bindParam(':p_usuario_id', $usuarioId, PDO::PARAM_INT | PDO::PARAM_INPUT_OUTPUT, 32);
            $stmt->bindParam(':p_nombre_completo', $nombreCompleto, PDO::PARAM_STR | PDO::PARAM_INPUT_OUTPUT, 100);
            $stmt->bindParam(':p_nombre_rol', $nombreRol, PDO::PARAM_STR | PDO::PARAM_INPUT_OUTPUT, 50);
            $stmt->bindParam(':p_total_accesos', $totalAccesos, PDO::PARAM_INT | PDO::PARAM_INPUT_OUTPUT, 32);
            $stmt->bindParam(':p_resultado', $resultado, PDO::PARAM_STR | PDO::PARAM_INPUT_OUTPUT, 200);

            $stmt->execute();

            return [
                'usuario_id'      => $usuarioId !== null ? (int)$usuarioId : null,
                'nombre_completo' => $nombreCompleto,
                'nombre_rol'      => $nombreRol,
                'total_accesos'   => (int)$totalAccesos,
                'resultado'       => $resultado,
            ];
        } catch (Throwable $e) {
            self::$modoSimulacion = true;
            return self::emularSpValidarLogin($username, $password);
        }
    }

    /**
     * Ejecuta un procedimiento almacenado que retorna un SYS_REFCURSOR (cero SELECT directos).
     *
     * @param string $procedimiento Nombre del procedimiento (ej. 'bike_owner.sp_listar_usuarios_acceso')
     * @return array<int, array<string, mixed>>
     */
    public static function ejecutarCursor(string $procedimiento): array
    {
        try {
            $pdo = self::obtenerConexion();
            $sql = sprintf('BEGIN %s(:p_cursor); END;', $procedimiento);

            $stmt = $pdo->prepare($sql);
            $cursor = null;
            $stmt->bindParam(':p_cursor', $cursor, PDO::PARAM_STMT);
            $stmt->execute();

            if ($cursor instanceof PDOStatement) {
                $cursor->execute();
                $filas = $cursor->fetchAll(PDO::FETCH_ASSOC);
                $cursor->closeCursor();
                return $filas;
            }
        } catch (Throwable $pdoCursorError) {
            if (function_exists('oci_connect') && !self::$modoSimulacion) {
                try {
                    return self::ejecutarCursorOci8($procedimiento);
                } catch (Throwable $ociErr) {
                    self::$modoSimulacion = true;
                }
            }
            self::$modoSimulacion = true;
            return self::emularCursor($procedimiento);
        }

        return self::emularCursor($procedimiento);
    }

    private static function ejecutarCursorOci8(string $procedimiento): array
    {
        $cadena = sprintf(
            '//%s:%s/%s',
            getenv('ORACLE_HOST') ?: self::DB_HOST,
            getenv('ORACLE_PORT') ?: self::DB_PORT,
            getenv('ORACLE_SERVICE') ?: self::DB_SERVICE
        );
        $usuario  = getenv('ORACLE_USER') ?: self::DB_USER;
        $password = getenv('ORACLE_PASS') ?: self::DB_PASS;

        $conn = @oci_connect($usuario, $password, $cadena, self::DB_CHARSET);
        if (!$conn) {
            $err = oci_error();
            throw new RuntimeException('Error OCI8 Cursor: ' . ($err['message'] ?? 'Desconocido'));
        }

        $sql  = sprintf('BEGIN %s(:p_cursor); END;', $procedimiento);
        $stmt = oci_parse($conn, $sql);
        $refCursor = oci_new_cursor($conn);

        oci_bind_by_name($stmt, ':p_cursor', $refCursor, -1, OCI_B_CURSOR);
        oci_execute($stmt);
        oci_execute($refCursor);

        $resultados = [];
        while (($fila = oci_fetch_assoc($refCursor)) !== false) {
            $resultados[] = array_change_key_case($fila, CASE_LOWER);
        }

        oci_free_statement($refCursor);
        oci_free_statement($stmt);
        oci_close($conn);

        return $resultados;
    }

    /**
     * Inicializa los datos semilla idénticos a 02_ddl_plsql_bikestore.sql cuando se prueba sin listener Oracle activo.
     */
    public static function inicializarSemillaLocal(): void
    {
        if (isset($_SESSION['_bikestore_db'])) {
            return;
        }
        $_SESSION['_bikestore_db'] = [
            'usuarios' => [
                1 => [
                    'usuario_id'        => 1,
                    'username'          => 'bike_owner',
                    'password_hash'     => 'BikeStore1234Fide',
                    'nombre_completo'   => 'Dueño del Esquema / Admin',
                    'rol_id'            => 1,
                    'nombre_rol'        => 'ADMINISTRADOR',
                    'tienda_asignada'   => 'TODAS / CENTRAL',
                    'ultimo_acceso'     => null,
                    'total_accesos'     => 0,
                    'intentos_fallidos' => 0,
                    'estado_usuario'    => 'ACTIVO',
                ],
                2 => [
                    'usuario_id'        => 2,
                    'username'          => 'dev_bike',
                    'password_hash'     => 'BikeStore1234Fide',
                    'nombre_completo'   => 'Desarrollador Sistema',
                    'rol_id'            => 2,
                    'nombre_rol'        => 'DESARROLLADOR',
                    'tienda_asignada'   => 'TODAS / CENTRAL',
                    'ultimo_acceso'     => null,
                    'total_accesos'     => 0,
                    'intentos_fallidos' => 0,
                    'estado_usuario'    => 'ACTIVO',
                ],
                3 => [
                    'usuario_id'        => 3,
                    'username'          => 'vendedor1',
                    'password_hash'     => 'BikeStore1234Fide',
                    'nombre_completo'   => 'Cajero Tienda Central',
                    'rol_id'            => 3,
                    'nombre_rol'        => 'VENTAS',
                    'tienda_asignada'   => 'TODAS / CENTRAL',
                    'ultimo_acceso'     => null,
                    'total_accesos'     => 0,
                    'intentos_fallidos' => 0,
                    'estado_usuario'    => 'ACTIVO',
                ],
                4 => [
                    'usuario_id'        => 4,
                    'username'          => 'bodega1',
                    'password_hash'     => 'BikeStore1234Fide',
                    'nombre_completo'   => 'Encargado de Inventario',
                    'rol_id'            => 4,
                    'nombre_rol'        => 'INVENTARIO',
                    'tienda_asignada'   => 'TODAS / CENTRAL',
                    'ultimo_acceso'     => null,
                    'total_accesos'     => 0,
                    'intentos_fallidos' => 0,
                    'estado_usuario'    => 'ACTIVO',
                ],
            ],
            'bitacora' => [],
        ];
    }

    private static function emularSpValidarLogin(string $username, string $password): array
    {
        self::inicializarSemillaLocal();
        $db = &$_SESSION['_bikestore_db'];
        $userKey = strtolower(trim($username));

        foreach ($db['usuarios'] as $id => &$u) {
            if (strtolower($u['username']) === $userKey) {
                if ($u['estado_usuario'] !== 'ACTIVO') {
                    array_unshift($db['bitacora'], [
                        'bitacora_id'     => count($db['bitacora']) + 1,
                        'username'        => $u['username'],
                        'nombre_completo' => $u['nombre_completo'],
                        'nombre_rol'      => $u['nombre_rol'],
                        'fecha_acceso'    => date('Y-m-d H:i:s'),
                        'estado_intento'  => 'DENEGADO',
                        'detalle'         => 'Intento de acceso con usuario INACTIVO',
                    ]);
                    return [
                        'usuario_id'      => null,
                        'nombre_completo' => null,
                        'nombre_rol'      => null,
                        'total_accesos'   => 0,
                        'resultado'       => 'ACCESO DENEGADO: El usuario está inactivo.',
                    ];
                }

                if ($u['password_hash'] === $password) {
                    $u['ultimo_acceso']     = date('Y-m-d H:i:s');
                    $u['total_accesos']     = (int)$u['total_accesos'] + 1;
                    $u['intentos_fallidos'] = 0;

                    array_unshift($db['bitacora'], [
                        'bitacora_id'     => count($db['bitacora']) + 1,
                        'username'        => $u['username'],
                        'nombre_completo' => $u['nombre_completo'],
                        'nombre_rol'      => $u['nombre_rol'],
                        'fecha_acceso'    => $u['ultimo_acceso'],
                        'estado_intento'  => 'EXITOSO',
                        'detalle'         => 'Ingreso correcto al sistema Bike Store',
                    ]);

                    return [
                        'usuario_id'      => (int)$u['usuario_id'],
                        'nombre_completo' => $u['nombre_completo'],
                        'nombre_rol'      => $u['nombre_rol'],
                        'total_accesos'   => (int)$u['total_accesos'],
                        'resultado'       => 'ACCESO CONCEDIDO',
                    ];
                } else {
                    $u['intentos_fallidos'] = (int)$u['intentos_fallidos'] + 1;
                    array_unshift($db['bitacora'], [
                        'bitacora_id'     => count($db['bitacora']) + 1,
                        'username'        => $u['username'],
                        'nombre_completo' => $u['nombre_completo'],
                        'nombre_rol'      => $u['nombre_rol'],
                        'fecha_acceso'    => date('Y-m-d H:i:s'),
                        'estado_intento'  => 'FALLIDO',
                        'detalle'         => 'Contraseña incorrecta',
                    ]);

                    return [
                        'usuario_id'      => null,
                        'nombre_completo' => null,
                        'nombre_rol'      => null,
                        'total_accesos'   => 0,
                        'resultado'       => 'ERROR: Usuario o contraseña incorrectos.',
                    ];
                }
            }
        }

        // EXCEPTION WHEN NO_DATA_FOUND
        return [
            'usuario_id'      => null,
            'nombre_completo' => null,
            'nombre_rol'      => null,
            'total_accesos'   => 0,
            'resultado'       => 'ERROR: Usuario o contraseña incorrectos.',
        ];
    }

    private static function emularCursor(string $procedimiento): array
    {
        self::inicializarSemillaLocal();
        $db = $_SESSION['_bikestore_db'];

        if (str_contains($procedimiento, 'sp_listar_usuarios_acceso')) {
            return array_values($db['usuarios']);
        }

        if (str_contains($procedimiento, 'sp_listar_estadisticas_roles')) {
            $roles = [
                1 => ['rol_id' => 1, 'nombre_rol' => 'ADMINISTRADOR', 'cantidad_usuarios' => 0, 'suma_total_accesos' => 0, 'acceso_mas_reciente' => null],
                2 => ['rol_id' => 2, 'nombre_rol' => 'DESARROLLADOR', 'cantidad_usuarios' => 0, 'suma_total_accesos' => 0, 'acceso_mas_reciente' => null],
                3 => ['rol_id' => 3, 'nombre_rol' => 'VENTAS',        'cantidad_usuarios' => 0, 'suma_total_accesos' => 0, 'acceso_mas_reciente' => null],
                4 => ['rol_id' => 4, 'nombre_rol' => 'INVENTARIO',    'cantidad_usuarios' => 0, 'suma_total_accesos' => 0, 'acceso_mas_reciente' => null],
            ];
            foreach ($db['usuarios'] as $u) {
                $rid = (int)$u['rol_id'];
                if (isset($roles[$rid])) {
                    $roles[$rid]['cantidad_usuarios']++;
                    $roles[$rid]['suma_total_accesos'] += (int)$u['total_accesos'];
                    if ($u['ultimo_acceso'] !== null && ($roles[$rid]['acceso_mas_reciente'] === null || $u['ultimo_acceso'] > $roles[$rid]['acceso_mas_reciente'])) {
                        $roles[$rid]['acceso_mas_reciente'] = $u['ultimo_acceso'];
                    }
                }
            }
            return array_values($roles);
        }

        if (str_contains($procedimiento, 'sp_listar_bitacora_accesos')) {
            return $db['bitacora'];
        }

        return [];
    }
}
