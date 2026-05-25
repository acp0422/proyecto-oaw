<?php
// conexion.php

function getConexion() {
    $host = 'localhost';
    $db   = 'lector_rss';
    $user = 'root';
    $pass = '';
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lanza excepciones en caso de errores
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Devuelve los datos en arrays asociativos
        PDO::ATTR_EMULATE_PREPARES   => false,                  // Utiliza preparaciones reales para mayor seguridad
    ];

    try {
        return new PDO($dsn, $user, $pass, $options);
    } catch (\PDOException $e) {
        // Para entorno de producción mostrar el error es crucial
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Error de conexión: " . $e->getMessage()]);
        exit;
    }
}