<?php
/**
 * OneSign - Database Connection Helper
 * Menggunakan PDO (PHP Data Objects) dengan Prepared Statements
 * Politeknik Negeri Jakarta - Jurusan TIK
 */

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $host     = '127.0.0.1';
            $port     = '3306'; // Pastikan sesuai XAMPP Anda
            $dbname   = 'onesign_db';
            $username = 'root';
            $password = '';     // Kosongkan jika XAMPP tanpa password

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instance = new PDO($dsn, $username, $password, $options);
            } catch (PDOException $e) {
                die('Koneksi basis data gagal: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
            }
        }

        return self::$instance;
    }
}
