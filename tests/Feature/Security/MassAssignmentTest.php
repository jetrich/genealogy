<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MassAssignmentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the is_developer field cannot be mass assigned during user creation.
     * This prevents privilege escalation attacks where users try to grant themselves
     * developer privileges during registration.
     */
    public function test_cannot_mass_assign_is_developer_flag_during_creation(): void
    {
        $userData = [
            'firstname' => 'Test',
            'surname' => 'User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'is_developer' => true,  // This should be ignored due to guarded array
        ];

        $user = User::create($userData);

        // Should NOT be a developer (should be false/null)
        $this->assertFalse($user->is_developer);
        $this->assertNull($user->fresh()->is_developer);
    }

    /**
     * Test that the is_developer field cannot be mass assigned during user updates.
     * This prevents privilege escalation attacks where users try to grant themselves
     * developer privileges during profile updates.
     */
    public function test_cannot_mass_assign_is_developer_flag_during_update(): void
    {
        $user = User::factory()->create([
            'is_developer' => false,
        ]);

        // Attempt to update with is_developer = true (should be ignored)
        $user->update([
            'firstname' => 'Updated',
            'is_developer' => true,  // This should be ignored due to guarded array
        ]);

        // Should still NOT be a developer
        $this->assertFalse($user->fresh()->is_developer);
        $this->assertSame('Updated', $user->fresh()->firstname); // Other fields should update normally
    }

    /**
     * Test that the is_developer field can still be set directly (not mass assigned).
     * This ensures that the security fix doesn't break legitimate admin functionality.
     */
    public function test_can_set_is_developer_flag_directly(): void
    {
        $user = User::factory()->create([
            'is_developer' => false,
        ]);

        // Set directly (not mass assignment)
        $user->is_developer = true;
        $user->save();

        // Should now be a developer
        $this->assertTrue($user->fresh()->is_developer);
    }

    /**
     * Test that normal user fields can still be mass assigned.
     * This ensures that the security fix doesn't break normal user functionality.
     */
    public function test_can_mass_assign_normal_user_fields(): void
    {
        $userData = [
            'firstname' => 'John',
            'surname' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'language' => 'en',
            'timezone' => 'UTC',
        ];

        $user = User::create($userData);

        // All normal fields should be set correctly
        $this->assertSame('John', $user->firstname);
        $this->assertSame('Doe', $user->surname);
        $this->assertSame('john@example.com', $user->email);
        $this->assertSame('en', $user->language);
        $this->assertSame('UTC', $user->timezone);
    }
}