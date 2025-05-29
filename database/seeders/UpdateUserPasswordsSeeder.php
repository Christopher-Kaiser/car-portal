<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UpdateUserPasswordsSeeder extends Seeder
{
    public function run()
    {
        DB::table('users')->where('id', 14)->update(['password' => Hash::make('password')]);
        DB::table('users')->where('id', 15)->update(['password' => Hash::make('password')]);
        DB::table('users')->where('id', 16)->update(['password' => Hash::make('password')]);
        DB::table('users')->where('id', 17)->update(['password' => Hash::make('password')]);
        DB::table('users')->where('id', 18)->update(['password' => Hash::make('password')]);
        DB::table('users')->where('id', 19)->update(['password' => Hash::make('password')]);
        DB::table('users')->where('id', 20)->update(['password' => Hash::make('password')]);
        DB::table('users')->where('id', 21)->update(['password' => Hash::make('password')]);
        DB::table('users')->where('id', 22)->update(['password' => Hash::make('password')]);
        DB::table('users')->where('id', 23)->update(['password' => Hash::make('password')]);
        DB::table('users')->where('id', 24)->update(['password' => Hash::make('password')]);
        DB::table('users')->where('id', 25)->update(['password' => Hash::make('password')]);
        DB::table('users')->where('id', 26)->update(['password' => Hash::make('password')]);
        DB::table('users')->where('id', 27)->update(['password' => Hash::make('password')]);
        DB::table('users')->where('id', 28)->update(['password' => Hash::make('password')]);
    }
}
