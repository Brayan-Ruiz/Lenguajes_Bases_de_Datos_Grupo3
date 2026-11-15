<?php
/**
 * CURSO: SC-504 Lenguajes de Base de Datos - Segundo Avance
 * PROYECTO: Sistema Transaccional "Bike Store"
 * ARCHIVO: public/logout.php
 */

declare(strict_types=1);
session_start();
$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
