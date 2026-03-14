<?php

namespace Tests;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function actingAsAdmin(): static
    {
        $group = Group::firstOrCreate(['name' => 'Admin'], [
            'permissions' => Group::adminPermissions(),
        ]);
        $user = User::firstOrCreate(['email' => 'admin@test.local'], [
            'name'     => 'Admin',
            'password' => bcrypt('password'),
            'group_id' => $group->id,
        ]);
        return $this->actingAs($user);
    }

    protected function actingAsViewer(): static
    {
        $group = Group::firstOrCreate(['name' => 'Viewer'], [
            'permissions' => Group::viewerPermissions(),
        ]);
        $user = User::firstOrCreate(['email' => 'viewer@test.local'], [
            'name'     => 'Viewer',
            'password' => bcrypt('password'),
            'group_id' => $group->id,
        ]);
        return $this->actingAs($user);
    }

    protected function actingAsAnalyst(): static
    {
        $group = Group::firstOrCreate(['name' => 'Analyst'], [
            'permissions' => Group::analystPermissions(),
        ]);
        $user = User::firstOrCreate(['email' => 'analyst@test.local'], [
            'name'     => 'Analyst',
            'password' => bcrypt('password'),
            'group_id' => $group->id,
        ]);
        return $this->actingAs($user);
    }
}
