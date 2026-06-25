<?php
/**
 * Database Configuration for SQL Server
 * IoT-SRS Project
 */

return [
    'server' => getenv('DB_SERVER') ?: 'localhost',
    'database' => getenv('DB_NAME') ?: 'iot_srs_db',
    'username' => getenv('DB_USER') ?: 'sa',
    'password' => getenv('DB_PASS') ?: '',
    'charset' => 'UTF-8',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8,
    ]
];
