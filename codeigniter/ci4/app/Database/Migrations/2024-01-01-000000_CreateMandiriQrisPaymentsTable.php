<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMandiriQrisPaymentsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'qr_id' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'reference' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'qr_string' => [
                'type' => 'TEXT',
            ],
            'qr_image_url' => [
                'type' => 'VARCHAR',
                'constraint' => 500,
            ],
            'amount' => [
                'type' => 'DECIMAL',
                'constraint' => '15,2',
            ],
            'status' => [
                'type' => 'ENUM',
                'constraint' => ['PENDING', 'COMPLETED', 'EXPIRED', 'FAILED'],
                'default' => 'PENDING',
            ],
            'transaction_id' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
            'paid_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'expired_at' => [
                'type' => 'DATETIME',
            ],
            'metadata' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('qr_id');
        $this->forge->addUniqueKey('reference');
        $this->forge->addKey('status');
        $this->forge->addKey('created_at');
        $this->forge->createTable('mandiri_qris_payments');
    }
    
    public function down()
    {
        $this->forge->dropTable('mandiri_qris_payments');
    }
}
