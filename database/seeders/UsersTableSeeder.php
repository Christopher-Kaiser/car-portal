<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersTableSeeder extends Seeder
{
    public function run()
    {
        DB::table('users')->insert([
            [
                'name' => 'Tony Salik',
                'email' => 't.salik@example.com',
                'password' => Hash::make('password'),
                'role' => 'admin'
            ],
            [
                'name' => 'Samson Juss',
                'email' => 's.juss@example.com',
                'password' => Hash::make('password'),
                'role' => 'driver'
            ],
            [
                'name' => 'Derry Halkiss',
                'email' => 'd.halkiss@example.com',
                'password' => Hash::make('password'),
                'role' => 'user'
            ],
            [
                'name' => 'Iddi Amson',
                'email' => 'i.amson@example.com',
                'password' => Hash::make('password'),
                'role' => 'driver'
            ],
            [
                'name' => 'Omar Kudrow',
                'email' => 'o.kudrow@example.com',
                'password' => Hash::make('password'),
                'role' => 'user'
            ],
            [
                'name' => 'John Doe',
                'email' => 'j.doe@example.com',
                'password' => Hash::make('password'),
                'role' => 'driver'
            ],
            [
                'name' => 'Jane Smith',
                'email' => 'j.smith@example.com',
                'password' => Hash::make('password'),
                'role' => 'driver'
            ],
            [
                'name' => 'Said Algabarri',
                'email' => 's.algabarri@example.com',
                'password' => Hash::make('password'),
                'role' => 'driver'
            ],
            [
                'name' => 'Adam Beko',
                'email' => 'a.beko@example.com',
                'password' => Hash::make('password'),
                'role' => 'driver'
            ],
            [
                'name' => 'Alice Somora',
                'email' => 'a.somora@example.com',
                'password' => Hash::make('password'),
                'role' => 'driver'
            ],
        ]);
    }
}
