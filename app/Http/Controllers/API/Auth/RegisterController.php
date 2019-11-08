<?php

namespace App\Http\Controllers\API\Auth;

use App\Classes\Helper;
use App\Rules\UnregisteredPhone;
use App\OTP;
use App\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RegisterController
{

    public function user(Request $request)
    {
       $data = $request->validate([
           'phone' => ['required', 'phone:NG', new UnregisteredPhone],
           'first_name' => ['required', 'string'],
           'last_name' => ['required', 'string'],
           'email' => ['required', 'email', 'unique:users'],
           'password' => ['required', 'string', 'min:8', 'max:100']
       ]);

        DB::beginTransaction();
       try {

            $data['phone'] = $this->formatPhoneNumber($data['phone']);

            // Confirm OTP
           $otp_record = OTP::query()->where('phone', $data['phone'])->first();
           if (! $otp_record) {
               return response()->json([
                   'message' => "The phone number must be verified before registration.",
               ], 400);
           } elseif (!$otp_record->verified) {
               return response()->json([
                   'message' => "Verify the OTP for {$data['phone']} before attempting to register the number.  ",
               ], 400);
           }

            $data['password'] = Hash::make( $data['password']);

            $user = $this->createUser($data);

            $token = $this->createToken($user);

            if (!$token) {
                return response()->json(['message' => 'An error was encountered.'], 501);
            }

            $otp_record->delete();

           event(new Registered($user));

           DB::commit();

          return $this->createResponse($token, $user);

       } catch (\Exception $e) {

           DB::rollBack();

           $message = "========== ERROR ON ACCOUNT CREATION ========== \n";
            Helper::logException($e, $message);

           return response()->json(['message' => 'An Error was encountered. Try Again'], 501);
       }
    }

    public function partner(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'unique:users'],
            'first_name' => ['required', 'string', 'min:3'],
            'last_name' => ['required', 'string', 'min:3'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $data['password'] = Hash::make($data['password']);

        $user = $this->createUser($data, 'partner');

        $token = $this->createToken($user);

        if (!$token) {
            return response()->json(['message' => 'An error was encountered.'], 501);
        }

        return $this->createResponse($token, $user);
    }

    public function admin(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'unique:users'],
            'first_name' => ['required', 'string', 'min:3'],
            'last_name' => ['required', 'string', 'min:3'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $data['password'] = Hash::make($data['password']);

        $user = $this->createUser($data, 'admin');

        if (! $user) {
            return response()->json(['message' => 'An error was encountered'], 501);
        }

        return response()->json(['message' => 'Admin user created', 'data' => $user]);
    }

    private function createUser(array $data, $role = 'user')
    {
        $data['role'] = $role;

        return User::create($data);
    }

    private function createToken(User $user)
    {
        return auth()->login($user);
    }

    private function formatPhoneNumber(string $phone)
    {
        return Helper::formatPhoneNumber($phone);
    }

    private function createResponse(string $token, User $user)
    {
        return response()->json([
            'message' => 'Account has been created.',
            'data' => [
                'access_token' => $token,
                'expires_in' => auth()->factory()->getTTL() * 60,
                'user' => $user,
            ]
        ], 201);
    }
}
