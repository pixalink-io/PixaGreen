<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

describe('User Model', function () {
    it('can create a user', function () {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
        ]);

        expect($user)->toBeInstanceOf(User::class);
        expect($user->name)->toBe('John Doe');
        expect($user->email)->toBe('john@example.com');
        expect(Hash::check('password123', $user->password))->toBeTrue();
    });

    it('has correct fillable attributes', function () {
        $fillable = ['name', 'email', 'password'];
        $user = new User;

        expect($user->getFillable())->toBe($fillable);
    });

    it('has correct hidden attributes', function () {
        $hidden = ['password', 'remember_token'];
        $user = new User;

        expect($user->getHidden())->toBe($hidden);
    });

    it('casts email_verified_at to datetime', function () {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'email_verified_at' => now(),
        ]);

        expect($user->email_verified_at)->toBeInstanceOf(Illuminate\Support\Carbon::class);
    });

    it('automatically hashes password', function () {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'plain-password',
        ]);

        expect($user->password)->not->toBe('plain-password');
        expect(Hash::check('plain-password', $user->password))->toBeTrue();
    });

    it('uses factory for testing', function () {
        $user = User::factory()->create();

        expect($user)->toBeInstanceOf(User::class);
        expect($user->name)->not->toBeNull();
        expect($user->email)->not->toBeNull();
        expect($user->password)->not->toBeNull();
    });

    it('can create user with factory state', function () {
        $user = User::factory()->unverified()->create();

        expect($user->email_verified_at)->toBeNull();
    });

    it('hides sensitive attributes in json', function () {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'remember_token' => 'some-token',
        ]);

        $json = $user->toArray();

        expect($json)->not->toHaveKey('password');
        expect($json)->not->toHaveKey('remember_token');
        expect($json)->toHaveKey('name');
        expect($json)->toHaveKey('email');
    });

    it('can be used for authentication', function () {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
        ]);

        expect($user->email)->toBe('john@example.com');
        expect(Hash::check('password123', $user->password))->toBeTrue();
    });

    it('has hasFactory trait', function () {
        $user = new User;

        expect($user)->toHaveMethod('factory');
        expect(User::factory())->toBeInstanceOf(Illuminate\Database\Eloquent\Factories\Factory::class);
    });

    it('has notifiable trait', function () {
        $user = new User;

        expect($user)->toHaveMethod('notify');
        expect($user)->toHaveMethod('notifyNow');
    });
});
