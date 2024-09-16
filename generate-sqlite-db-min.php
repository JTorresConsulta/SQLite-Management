<?php

ini_set('display_errors', 1);
error_reporting(E_ERROR);

// Nombre de la base de datos SQLite
$dbFile = 'users.db';
$table = "users";

try {
    // Crear o abrir la base de datos SQLite
    $db = new PDO("sqlite:" . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Crear tabla si no existe con solo los campos requeridos
    $createTableQuery = "
        CREATE TABLE IF NOT EXISTS $table (
            email TEXT PRIMARY KEY,
            uid TEXT,
            pass TEXT,
            name TEXT
        )";
    $db->exec($createTableQuery);

    // Función para generar datos aleatorios
    function randomString($length = 10) {
        return substr(str_shuffle("abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, $length);
    }

    function randomEmail() {
        return randomString(5) . '@' . randomString(5) . '.com';
    }

    // Inserción de datos aleatorios
    $insertQuery = "INSERT INTO $table (email, uid, pass, name)
                    VALUES (:email, :uid, :pass, :name)";

    $stmt = $db->prepare($insertQuery);

    for ($i = 0; $i < 50; $i++) {
        $email = randomEmail();
        $uid = randomString(8);
        $pass = randomString(12);
        $name = randomString(10);

        // Ejecutar la inserción
        $stmt->execute([
            ':email' => $email,
            ':uid' => $uid,
            ':pass' => $pass,
            ':name' => $name
        ]);
    }

    echo "Base de datos creada y 50 datos insertados correctamente.";

} catch (PDOException $e) {
    echo "Error al conectar o crear la base de datos: " . $e->getMessage();
}
?>
