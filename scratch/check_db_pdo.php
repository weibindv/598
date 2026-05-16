<?php
$host = '127.0.0.1';
$port = '13306';
$db   = '400line';
$user = 'root';
$pass = 'waoowaoo123';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
     $stmt = $pdo->query("DESCRIBE fa_line_orders");
     $columns = $stmt->fetchAll();
     echo "Columns in fa_line_orders:\n";
     foreach ($columns as $col) {
         echo $col['Field'] . "\n";
     }

     echo "\nColumns in fa_line_message_log:\n";
     $stmt = $pdo->query("DESCRIBE fa_line_message_log");
     $columns = $stmt->fetchAll();
     foreach ($columns as $col) {
         echo $col['Field'] . "\n";
     }
} catch (\PDOException $e) {
     echo "Error: " . $e->getMessage();
}
