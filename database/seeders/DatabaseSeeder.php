<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models;
use Illuminate\Support\Facades\Hash;
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
           RolesAndPermissionsSeeder::class,
        ]);
//         $superAdmin = User::create([
//                'name' => 'Super Admin',
//                'email' => 'super@admin.com',
//                'password' => Hash::make('admin'),
//            ]);

//         $superAdmin->assignRole('super_admin');

    }
}
