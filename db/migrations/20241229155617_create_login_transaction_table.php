<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateLoginTransactionTable extends AbstractMigration
{
    public function change(): void
    {
        $loginTransaction = $this->table('login_transaction');
        $loginTransaction->addColumn('user_id', 'biginteger', ['signed' => false])
            ->addColumn('status', 'string', ['limit' => 50])
            ->addColumn('ip_address', 'string', ['limit' => 45])
            ->addColumn('user_agent', 'text', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('user_id', 'users', 'user_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
