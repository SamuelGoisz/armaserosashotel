<?php

$host = 'localhost';
$port = '5432';
$dbname = 'sistema_hoteis';
$user = 'postgres';
$password = '2096';

try{
    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
        );
}catch (PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}
