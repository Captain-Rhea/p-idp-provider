<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateAllStarterTable extends AbstractMigration
{
    public function change(): void
    {
        // ตาราง status
        $status = $this->table('status', ['if_not_exists' => true]);
        $status->addColumn('name', 'string', ['limit' => 255])
            ->addColumn('description', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('created_at', 'timestamp', ['null' => true])
            ->addIndex(['name'], ['unique' => true])
            ->create();

        // ตาราง users
        $users = $this->table('users', ['id' => false, 'primary_key' => ['user_id'], 'if_not_exists' => true]);
        $users->addColumn('user_id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('email', 'string', ['limit' => 255])
            ->addColumn('password', 'string', ['limit' => 255])
            ->addColumn('status_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('avatar_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('avatar_base_url', 'text')
            ->addColumn('avatar_lazy_url', 'text')
            ->addColumn('created_at', 'timestamp', ['null' => true])
            ->addColumn('updated_at', 'timestamp', ['null' => true])
            ->addIndex(['email'], ['unique' => true])
            ->addForeignKey('status_id', 'status', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->create();

        // ตาราง roles
        $roles = $this->table('roles', ['if_not_exists' => true]);
        $roles->addColumn('name', 'string', ['limit' => 255])
            ->addColumn('description', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('created_at', 'timestamp', ['null' => true])
            ->addIndex(['name'], ['unique' => true])
            ->create();

        // ตาราง permissions
        $permissions = $this->table('permissions', ['if_not_exists' => true]);
        $permissions->addColumn('name', 'string', ['limit' => 255])
            ->addColumn('description', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('created_at', 'timestamp', ['null' => true])
            ->addIndex(['name'], ['unique' => true])
            ->create();

        // ตาราง user_info
        $userInfo = $this->table('user_info', ['if_not_exists' => true]);
        $userInfo->addColumn('user_id', 'biginteger', ['signed' => false])
            ->addColumn('phone', 'string', ['limit' => 15, 'null' => true])
            ->addColumn('created_at', 'timestamp', ['null' => true])
            ->addColumn('updated_at', 'timestamp', ['null' => true])
            ->addForeignKey('user_id', 'users', 'user_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        // ตาราง user_info_translation
        $userInfo = $this->table('user_info_translation', ['if_not_exists' => true]);
        $userInfo->addColumn('user_id', 'biginteger', ['signed' => false])
            ->addColumn('language_code', 'string', ['limit' => 10])
            ->addColumn('first_name', 'string', ['limit' => 255])
            ->addColumn('last_name', 'string', ['limit' => 255])
            ->addColumn('nickname', 'string', ['limit' => 255])
            ->addColumn('created_at', 'timestamp', ['null' => true])
            ->addColumn('updated_at', 'timestamp', ['null' => true])
            ->addForeignKey('user_id', 'users', 'user_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        // ตาราง user_role
        $userRole = $this->table('user_role', ['if_not_exists' => true]);
        $userRole->addColumn('user_id', 'biginteger', ['signed' => false])
            ->addColumn('role_id', 'integer', ['signed' => false])
            ->addForeignKey('user_id', 'users', 'user_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('role_id', 'roles', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        // ตาราง user_permission
        $userPermission = $this->table('user_permission', ['if_not_exists' => true]);
        $userPermission->addColumn('user_id', 'biginteger', ['signed' => false])
            ->addColumn('permission_id', 'integer', ['signed' => false])
            ->addForeignKey('user_id', 'users', 'user_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('permission_id', 'permissions', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addIndex(['user_id', 'permission_id'], ['unique' => true])
            ->create();

        // ตาราง login_transaction
        $loginTransaction = $this->table('login_transaction', ['if_not_exists' => true]);
        $loginTransaction->addColumn('user_id', 'biginteger', ['signed' => false])
            ->addColumn('status', 'string', ['limit' => 50])
            ->addColumn('ip_address', 'string', ['limit' => 45])
            ->addColumn('user_agent', 'text', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['null' => true])
            ->addForeignKey('user_id', 'users', 'user_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        // ตาราง invite_member
        $inviteMember = $this->table('invite_member', ['if_not_exists' => true]);
        $inviteMember->addColumn('inviter_id', 'biginteger', ['signed' => false])
            ->addColumn('recipient_email', 'string', ['limit' => 255])
            ->addColumn('status_id', 'integer', ['signed' => false])
            ->addColumn('domain', 'string', ['limit' => 255])
            ->addColumn('path', 'string', ['limit' => 255])
            ->addColumn('ref_code', 'string', ['limit' => 50])
            ->addColumn('role_id', 'integer', ['signed' => false])
            ->addColumn('expires_at', 'timestamp', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['null' => true])
            ->addColumn('updated_at', 'timestamp', ['null' => true])
            ->addForeignKey('inviter_id', 'users', 'user_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('status_id', 'status', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addIndex(['ref_code'], ['unique' => true])
            ->create();

        // ตาราง forgot_password
        $forgotPassword = $this->table('forgot_password', ['id' => false, 'primary_key' => ['forgot_id'], 'if_not_exists' => true]);
        $forgotPassword->addColumn('forgot_id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('recipient_email', 'string', ['limit' => 255])
            ->addColumn('domain', 'string', ['limit' => 255])
            ->addColumn('path', 'string', ['limit' => 255])
            ->addColumn('reset_key', 'string', ['limit' => 100])
            ->addColumn('is_used', 'boolean', ['default' => false])
            ->addColumn('expires_at', 'timestamp', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['null' => true])
            ->addColumn('updated_at', 'timestamp', ['null' => true])
            ->addIndex(['recipient_email', 'reset_key'], ['unique' => true])
            ->create();

        // สร้างตาราง api_connection
        $table = $this->table('api_connection', ['id' => 'id']);
        $table->addColumn('connection_name', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('connection_key', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addIndex(['connection_key'], ['unique' => true, 'name' => 'idx_connection_key'])
            ->create();

        // ตาราง otps
        $otps = $this->table('otps', ['id' => true, 'signed' => false]);
        $otps->addColumn('recipient_email', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('otp_code', 'string', ['limit' => 6, 'null' => false])
            ->addColumn('type', 'string', ['limit' => 20, 'null' => false])
            ->addColumn('ref', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('expires_at', 'datetime', ['null' => false])
            ->addColumn('is_used', 'boolean', ['default' => false])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('updated_at', 'datetime', ['null' => true])
            ->addIndex(['recipient_email'])
            ->addIndex(['otp_code'])
            ->addIndex(['ref'])
            ->create();
    }
}
