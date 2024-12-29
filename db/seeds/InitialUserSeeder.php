<?php

use Phinx\Seed\AbstractSeed;

class InitialUserSeeder extends AbstractSeed
{
    public function run(): void
    {
        $userData = [
            'email' => 'rhea.captain@gmail.com',
            'password' => password_hash('P@ssword', PASSWORD_DEFAULT),
            'status' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $userTable = $this->table('users');
        $userTable->insert($userData)->saveData();
        $userId = $this->getAdapter()->getConnection()->lastInsertId();

        $userInfoData = [
            'user_id' => $userId,
            'first_name' => 'Rhea',
            'last_name' => 'Captain',
            'nickname' => 'Rhea',
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $userInfoTable = $this->table('user_info');
        $userInfoTable->insert($userInfoData)->saveData();

        $roleData = [
            'user_id' => $userId,
            'role_id' => 1,
            'assigned_at' => date('Y-m-d H:i:s'),
        ];

        $userRoleTable = $this->table('user_role');
        $userRoleTable->insert($roleData)->saveData();

        $permissions = $this->fetchAll('SELECT id FROM permissions');
        $userPermissions = array_map(function ($permission) use ($userId) {
            return [
                'user_id' => $userId,
                'permission_id' => $permission['id'],
                'granted_at' => date('Y-m-d H:i:s'),
            ];
        }, $permissions);

        $userPermissionTable = $this->table('user_permission');
        $userPermissionTable->insert($userPermissions)->saveData();
    }
}
