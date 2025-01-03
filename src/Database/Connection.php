<?php

namespace App\Database;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;

class Connection
{
    /**
     * Initialize the database connection.
     *
     * @throws \RuntimeException If required environment variables are missing.
     */
    public static function initialize(): void
    {
        // ตรวจสอบตัวแปร .env ที่จำเป็น
        $requiredEnv = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
        foreach ($requiredEnv as $key) {
            if (empty($_ENV[$key])) {
                throw new \RuntimeException("Environment variable '{$key}' is not set.");
            }
        }

        // ตั้งค่า Capsule Manager
        $capsule = new Capsule();
        $capsule->addConnection([
            'driver'    => 'mysql',
            'host'      => $_ENV['DB_HOST'],
            'database'  => $_ENV['DB_NAME'],
            'username'  => $_ENV['DB_USER'],
            'password'  => $_ENV['DB_PASS'],
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
        ]);

        // ตั้งค่า Event Dispatcher และ Container
        $capsule->setEventDispatcher(new Dispatcher(new Container()));

        // ทำให้ Capsule Manager ใช้งานได้ทั่วทั้งโปรเจกต์
        $capsule->setAsGlobal();

        // เริ่มการทำงานของ Eloquent ORM
        $capsule->bootEloquent();
    }
}
