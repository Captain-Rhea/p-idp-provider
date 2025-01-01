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
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['name'], ['unique' => true])
            ->create();

        // ตาราง users
        $users = $this->table('users', ['id' => false, 'primary_key' => ['user_id'], 'if_not_exists' => true]);
        $users->addColumn('user_id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('email', 'string', ['limit' => 255])
            ->addColumn('password', 'string', ['limit' => 255])
            ->addColumn('status_id', 'integer', ['signed' => false, 'null' => true]) // เชื่อมกับ status
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['email'], ['unique' => true])
            ->addForeignKey('status_id', 'status', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->create();

        // ตาราง roles
        $roles = $this->table('roles', ['if_not_exists' => true]);
        $roles->addColumn('name', 'string', ['limit' => 255])
            ->addColumn('description', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['name'], ['unique' => true])
            ->create();

        // ตาราง permissions
        $permissions = $this->table('permissions', ['if_not_exists' => true]);
        $permissions->addColumn('name', 'string', ['limit' => 255])
            ->addColumn('description', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['name'], ['unique' => true])
            ->create();

        // ตาราง user_info
        $userInfo = $this->table('user_info', ['if_not_exists' => true]);
        $userInfo->addColumn('user_id', 'biginteger', ['signed' => false])
            ->addColumn('first_name', 'string', ['limit' => 255])
            ->addColumn('last_name', 'string', ['limit' => 255])
            ->addColumn('nickname', 'string', ['limit' => 255])
            ->addColumn('phone', 'string', ['limit' => 15, 'null' => true])
            ->addColumn('avatar_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('avatar_url', 'text')
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('user_id', 'users', 'user_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        // ตาราง user_role
        $userRole = $this->table('user_role', ['if_not_exists' => true]);
        $userRole->addColumn('user_id', 'biginteger', ['signed' => false])
            ->addColumn('role_id', 'integer', ['signed' => false])
            ->addColumn('assigned_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('user_id', 'users', 'user_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('role_id', 'roles', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        // ตาราง user_permission
        $userPermission = $this->table('user_permission', ['if_not_exists' => true]);
        $userPermission->addColumn('user_id', 'biginteger', ['signed' => false])
            ->addColumn('permission_id', 'integer', ['signed' => false])
            ->addColumn('granted_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
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
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('user_id', 'users', 'user_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        // ตาราง invite_member
        $inviteMember = $this->table('invite_member', ['if_not_exists' => true]);
        $inviteMember->addColumn('inviter_id', 'biginteger', ['signed' => false])
            ->addColumn('email', 'string', ['limit' => 255])
            ->addColumn('status', 'enum', ['values' => ['pending', 'accepted', 'rejected', 'expired'], 'default' => 'pending'])
            ->addColumn('ref_code', 'string', ['limit' => 50])
            ->addColumn('expires_at', 'timestamp', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('inviter_id', 'users', 'user_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addIndex(['ref_code'], ['unique' => true])
            ->create();

        // ตาราง forgot_password
        $forgotPassword = $this->table('forgot_password', ['id' => false, 'primary_key' => ['forgot_id'], 'if_not_exists' => true]);
        $forgotPassword->addColumn('forgot_id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('email', 'string', ['limit' => 255])
            ->addColumn('reset_key', 'string', ['limit' => 100])
            ->addColumn('is_used', 'boolean', ['default' => false])
            ->addColumn('sent_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('expires_at', 'timestamp', ['null' => true])
            ->addIndex(['email', 'reset_key'], ['unique' => true])
            ->create();
    }
}
