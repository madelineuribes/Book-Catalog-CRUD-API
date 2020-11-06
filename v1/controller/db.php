<?php
    class DB {
        private static $connection;

        public static function connectDB() {
            if(self::$connection === null) {
                self::$connection = new PDO('mysql:host=localhost;dbname=book_catalog;charset=utf8', 'root', '');
                self::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            }
            return self::$connection;
        }      
    }