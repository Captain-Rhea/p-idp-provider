<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateOtpTransaction extends AbstractMigration
{
    public function change(): void
    {
        $otpTransaction = $this->table('otp_transaction');
        $otpTransaction->addColumn('user_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('email', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('ref_code', 'string', ['limit' => 50])
            ->addColumn('otp_code', 'string', ['limit' => 6])
            ->addColumn('purpose', 'string', ['limit' => 100])
            ->addColumn('is_used', 'boolean', ['default' => false])
            ->addColumn('expires_at', 'timestamp', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('user_id', 'users', 'user_id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->addIndex(['ref_code'], ['unique' => true])
            ->create();
    }
}
