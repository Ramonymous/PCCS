<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            'manage',
            'manage_pcc',
            'receive_hpm',
            'delivery_hpm',
            'weld.unlock-scanner',
            'qa.unlock-scanner',
            'delivery.unlock-scanner',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles and assign permissions
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::all());

        $weldRole = Role::firstOrCreate(['name' => 'weld']);
        $weldRole->givePermissionTo(['weld.unlock-scanner']);
        
        $qualityRole = Role::firstOrCreate(['name' => 'quality']);
        $qualityRole->givePermissionTo(['qa.unlock-scanner']);

        $ppicRole = Role::firstOrCreate(['name' => 'ppic']);
        $ppicRole->givePermissionTo(['manage', 'manage_pcc', 'receive_hpm', 'delivery_hpm', 'delivery.unlock-scanner']);

        $this->command->info('Roles and permissions seeded successfully!');
    }
}
