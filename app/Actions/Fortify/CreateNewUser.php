<?php

namespace App\Actions\Fortify;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        $invitation = Invitation::validToken($input['invitation_token'] ?? null);

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'invitation_token' => ['required', 'string'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
        ])->after(function ($validator) use ($input, $invitation) {
            if (! $invitation) {
                $validator->errors()->add('invitation_token', 'A valid invitation is required to register.');

                return;
            }

            if ($invitation->email !== $input['email']) {
                $validator->errors()->add('email', 'The registration email must match the invitation email.');
            }
        })->validate();

        return DB::transaction(function () use ($input, $invitation) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
            ]);

            $user->roles()->syncWithoutDetaching([$invitation->role_id]);
            $invitation->markAccepted();

            return $user;
        });
    }
}
