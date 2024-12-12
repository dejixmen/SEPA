<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminUser extends Command
{
    protected $signature = 'admin:create {email} {password}';
    protected $description = 'Create an admin user for SEPA management';

    public function handle()
    {
        $email = $this->argument('email');
        $password = $this->argument('password');

        try {
            $user = User::create([
                'name' => 'Admin',
                'email' => $email,
                'password' => Hash::make($password),
            ]);

            $this->info('Admin user created successfully!');
            $this->info("Email: {$email}");
            $this->info('You can now login to the SEPA management panel.');

        } catch (\Exception $e) {
            $this->error('Failed to create admin user: ' . $e->getMessage());
        }
    }
} 