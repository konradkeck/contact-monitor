<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class GroupsAndAdminSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            'Admin'   => Group::adminPermissions(),
            'Analyst' => Group::analystPermissions(),
            'Viewer'  => Group::viewerPermissions(),
        ];

        foreach ($groups as $name => $permissions) {
            Group::firstOrCreate(['name' => $name], ['permissions' => $permissions]);
        }

        $adminGroup = Group::where('name', 'Admin')->first();

        User::firstOrCreate(
            ['email' => 'Konrad.keck@inbs.software'],
            [
                'name'     => 'Konrad Keck',
                'password' => Hash::make('Dupsko123'),
                'group_id' => $adminGroup->id,
            ]
        );
    }
}
