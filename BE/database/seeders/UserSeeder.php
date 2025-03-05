<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('users')->insert([
            'name' => 'Hoàng',
            'username' => 'hoang2k4',
            'email' => 'phuongminhhoang77@gmail.com',
            'avatar' => 'https://res.cloudinary.com/dkrn3fe2o/image/upload/v1739066958/ovmdtlu6ihcldyx9jckg.jpg',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('12345678'),
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Tạo 10 user giả mạo
        \App\Models\User::factory(10)->create();
        }
    }

