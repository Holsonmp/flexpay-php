<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateFlexPayCheckouts extends Migration
{
    public function up(): void
    {
        $this->db->query('CREATE TABLE flexpay_checkout_sessions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            checkout_id VARCHAR(80) NOT NULL UNIQUE,
            merchant_reference VARCHAR(128) NOT NULL UNIQUE,
            provider_transaction_id VARCHAR(128) NULL UNIQUE,
            status VARCHAR(32) NOT NULL,
            version INT UNSIGNED NOT NULL,
            payload_json MEDIUMTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX idx_flexpay_checkout_status (status)
        )');
        $this->db->query('CREATE TABLE flexpay_checkout_transitions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            checkout_id VARCHAR(80) NOT NULL,
            `from` VARCHAR(32) NOT NULL,
            `to` VARCHAR(32) NOT NULL,
            occurred_at VARCHAR(40) NOT NULL,
            source VARCHAR(32) NOT NULL,
            version INT UNSIGNED NOT NULL,
            UNIQUE KEY uq_flexpay_transition (checkout_id, version)
        )');
        $this->db->query('CREATE TABLE flexpay_checkout_callbacks (
            event_id VARCHAR(80) PRIMARY KEY,
            checkout_id VARCHAR(80) NOT NULL,
            processed_at DATETIME NOT NULL
        )');
    }

    public function down(): void
    {
        $this->forge->dropTable('flexpay_checkout_callbacks', true);
        $this->forge->dropTable('flexpay_checkout_transitions', true);
        $this->forge->dropTable('flexpay_checkout_sessions', true);
    }
}
