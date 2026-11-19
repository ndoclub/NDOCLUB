<?php
/**
 * Configuração do No Days Off Club.
 * Copie este arquivo para "config.php" (se ainda não existir) e ajuste.
 * NUNCA envie o config.php para o GitHub (já está no .gitignore).
 */
return [
    'app' => [
        'name'     => 'No Days Off Club',
        'timezone' => 'America/Sao_Paulo',
        // true mostra erros detalhados na tela (só no seu computador). Em produção: false.
        'debug'    => true,
    ],

    // XAMPP padrão: usuário "root" e senha vazia.
    'db' => [
        'driver'  => 'mysql',
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'nodaysoffclub',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    'upload' => [
        'max_image_mb' => 5,
        'max_pdf_mb'   => 10,
    ],
];
