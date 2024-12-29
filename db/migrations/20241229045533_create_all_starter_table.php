<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateAllStarterTable extends AbstractMigration
{
    public function change(): void
    {
        // ตาราง roles
        $roles = $this->table('roles');
        $roles->addColumn('name', 'string', ['limit' => 255])
            ->addColumn('description', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['name'], ['unique' => true])
            ->create();

        // ตาราง permissions
        $permissions = $this->table('permissions');
        $permissions->addColumn('name', 'string', ['limit' => 255])
            ->addColumn('description', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['name'], ['unique' => true])
            ->create();

        // ตาราง users
        $users = $this->table('users', ['id' => false, 'primary_key' => ['user_id']]);
        $users->addColumn('user_id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('email', 'string', ['limit' => 255])
            ->addColumn('password', 'string', ['limit' => 255])
            ->addColumn('status', 'integer')
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['email'], ['unique' => true])
            ->create();

        // ตาราง user_info
        $userInfo = $this->table('user_info');
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
        $userRole = $this->table('user_role');
        $userRole->addColumn('user_id', 'biginteger', ['signed' => false])
            ->addColumn('role_id', 'integer', ['signed' => false])
            ->addColumn('assigned_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('user_id', 'users', 'user_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('role_id', 'roles', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        // ตาราง user_permission
        $userPermission = $this->table('user_permission');
        $userPermission->addColumn('user_id', 'biginteger', ['signed' => false])
            ->addColumn('permission_id', 'integer', ['signed' => false])
            ->addColumn('granted_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('user_id', 'users', 'user_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('permission_id', 'permissions', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addIndex(['user_id', 'permission_id'], ['unique' => true])
            ->create();

        // ตาราง status
        $status = $this->table('status');
        $status->addColumn('name', 'string', ['limit' => 255])
            ->addColumn('description', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['name'], ['unique' => true])
            ->create();
    }
}
