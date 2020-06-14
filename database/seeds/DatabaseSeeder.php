<?php

use App\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('offers')->insert([
            'id' => 1,
            'name' => 'Basic',
            'capacity' => 32212254720,
            'description' => '30GB space & file sharing'
        ]);
        /*DB::table('offers')->insert([
            'id' => 2,
            'name' => 'Pro',
            'capacity' => 21474836480,
            'description' => '20GB space'
        ]);
        DB::table('offers')->insert([
            'id' => 3,
            'name' => 'Business',
            'capacity' => 32212254720,
            'description' => '30GB space'
        ]);*/

        factory(User::class, 10)->create([
            'offer_id' => 1,
        ]);
        // $this->call(UsersTableSeeder::class);
    }
}
