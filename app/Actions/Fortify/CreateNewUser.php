<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;

final class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'firstname' => ['nullable', 'string', 'max:255'],
            'surname'   => ['required', 'string', 'max:255'],
            'email'     => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'language'  => ['required', Rule::in(array_values(config('app.available_locales')))],
            'timezone'  => ['required', Rule::in(array_values(timezone_identifiers_list()))],
            'password'  => $this->passwordRules(),
            'terms'     => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : '',
        ])->validate();

        return DB::transaction(fn () => tap(User::create([
            'firstname' => $input['firstname'] ?? null,
            'surname'   => $input['surname'],
            'email'     => $input['email'],
            'language'  => $input['language'],
            'timezone'  => $input['timezone'],
            'password'  => Hash::make($input['password']),
        ]), function (User $user): void {
            $this->createTeam($user);
            $this->grantDefaultPermissions($user);
        }));
    }

    /**
     * Create a personal team for the user.
     */
    protected function createTeam(User $user): void
    {
        $team = $user->ownedTeams()->save(Team::forceCreate([
            'user_id'       => $user->id,
            'name'          => 'Team ' . $user->name,
            'personal_team' => true,
        ]));

        // Set the current_team_id to the newly created personal team
        $user->current_team_id = $team->id;
        $user->save();
    }

    /**
     * Grant default permissions to new users for basic genealogy operations.
     */
    protected function grantDefaultPermissions(User $user): void
    {
        // Find a developer user who can grant permissions
        $developer = User::where('is_developer', true)->first();
        
        if (!$developer) {
            // If no developer exists, try to find user ID 1 from seeding
            $developer = User::find(1);
        }
        
        if ($developer) {
            try {
                // Grant essential permissions for genealogy operations
                $permissions = [
                    'person:create' => 'Default permission for genealogy operations',
                    'person:read' => 'Default permission for viewing people',
                    'person:update' => 'Default permission for editing people',
                    'couple:create' => 'Default permission for creating relationships',
                    'couple:read' => 'Default permission for viewing relationships',
                    'couple:update' => 'Default permission for editing relationships',
                ];

                foreach ($permissions as $permission => $justification) {
                    // Check if permission exists before granting
                    if (\App\Models\Permission::where('name', $permission)->exists()) {
                        if (!$user->hasPermission($permission)) {
                            $user->grantPermission($permission, $developer, $justification);
                        }
                    }
                }
            } catch (\Exception $e) {
                // Log the error but don't fail registration
                \Log::warning('Failed to grant default permissions to new user', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}
