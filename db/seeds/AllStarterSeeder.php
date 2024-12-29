<?php

use Phinx\Seed\AbstractSeed;

class AllStarterSeeder extends AbstractSeed
{
    public function run(): void
    {
        $this->seedPermissions();
        $this->seedRoles();
        $this->seedStatus();
    }

    private function seedPermissions(): void
    {
        $data = [
            // Auth Management Permissions
            ['id' => 1, 'name' => 'view_users', 'description' => 'Permission to view user list', 'created_at' => date('Y-m-d H:i:s')],
            ['id' => 2, 'name' => 'create_users', 'description' => 'Permission to create a new user', 'created_at' => date('Y-m-d H:i:s')],
            ['id' => 3, 'name' => 'edit_users', 'description' => 'Permission to edit user data', 'created_at' => date('Y-m-d H:i:s')],
            ['id' => 4, 'name' => 'delete_users', 'description' => 'Permission to delete a user', 'created_at' => date('Y-m-d H:i:s')],
            ['id' => 5, 'name' => 'view_roles', 'description' => 'Permission to view roles', 'created_at' => date('Y-m-d H:i:s')],
            ['id' => 6, 'name' => 'assign_roles', 'description' => 'Permission to assign roles to users', 'created_at' => date('Y-m-d H:i:s')],
            ['id' => 7, 'name' => 'view_permissions', 'description' => 'Permission to view permissions', 'created_at' => date('Y-m-d H:i:s')],
            ['id' => 8, 'name' => 'edit_permissions', 'description' => 'Permission to edit permissions', 'created_at' => date('Y-m-d H:i:s')],
            ['id' => 9, 'name' => 'manage_system', 'description' => 'Permission to manage the entire system', 'created_at' => date('Y-m-d H:i:s')],
            ['id' => 10, 'name' => 'invite_members', 'description' => 'Permission to invite new members to the system', 'created_at' => date('Y-m-d H:i:s')],

            // Blog Management Permissions
            ['id' => 11, 'name' => 'view_blogs', 'description' => 'Permission to view all blogs', 'created_at' => date('Y-m-d H:i:s')],
            ['id' => 12, 'name' => 'create_blogs', 'description' => 'Permission to create a new blog', 'created_at' => date('Y-m-d H:i:s')],
            ['id' => 13, 'name' => 'edit_blogs', 'description' => 'Permission to edit a blog', 'created_at' => date('Y-m-d H:i:s')],
            ['id' => 14, 'name' => 'delete_blogs', 'description' => 'Permission to delete a blog', 'created_at' => date('Y-m-d H:i:s')],
            ['id' => 15, 'name' => 'publish_blogs', 'description' => 'Permission to publish or unpublish a blog', 'created_at' => date('Y-m-d H:i:s')],

            // Content Management Permissions
            ['id' => 16, 'name' => 'view_contents', 'description' => 'Permission to view all website contents', 'created_at' => date('Y-m-d H:i:s')],
            ['id' => 17, 'name' => 'create_contents', 'description' => 'Permission to create new content', 'created_at' => date('Y-m-d H:i:s')],
            ['id' => 18, 'name' => 'edit_contents', 'description' => 'Permission to edit website content', 'created_at' => date('Y-m-d H:i:s')],
            ['id' => 19, 'name' => 'delete_contents', 'description' => 'Permission to delete website content', 'created_at' => date('Y-m-d H:i:s')],
            ['id' => 20, 'name' => 'publish_contents', 'description' => 'Permission to publish or unpublish website content', 'created_at' => date('Y-m-d H:i:s')],
        ];

        $this->table('permissions')->insert($data)->saveData();
    }

    private function seedRoles(): void
    {
        $data = [
            ['id' => 1, 'name' => 'captain', 'description' => 'Team captain role', 'created_at' => date('Y-m-d H:i:s')],
            ['id' => 2, 'name' => 'owner', 'description' => 'System owner role', 'created_at' => date('Y-m-d H:i:s')],
            ['id' => 3, 'name' => 'admin', 'description' => 'System administrator role', 'created_at' => date('Y-m-d H:i:s')],
        ];

        $this->table('roles')->insert($data)->saveData();
    }

    private function seedStatus(): void
    {
        $data = [
            ['id' => 1, 'name' => 'active', 'description' => 'User is active and can use the system', 'created_at' => date('Y-m-d H:i:s')],
            ['id' => 2, 'name' => 'pending', 'description' => 'User registration is pending approval', 'created_at' => date('Y-m-d H:i:s')],
            ['id' => 3, 'name' => 'suspended', 'description' => 'User account is temporarily suspended', 'created_at' => date('Y-m-d H:i:s')],
            ['id' => 4, 'name' => 'deleted', 'description' => 'User account is deleted (soft delete)', 'created_at' => date('Y-m-d H:i:s')],
        ];

        $this->table('status')->insert($data)->saveData();
    }
}
