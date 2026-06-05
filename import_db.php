<?php
$sqlFile = file_get_contents('sql.sql');
$pdo = new PDO('mysql:host=127.0.0.1;dbname=cinema', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Split SQL statements and execute them
$statements = array_filter(array_map('trim', explode(';', $sqlFile)));
foreach ($statements as $statement) {
    if (!empty($statement)) {
        try {
            $pdo->exec($statement);
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }
}
echo "Database imported successfully!\n";
?>
