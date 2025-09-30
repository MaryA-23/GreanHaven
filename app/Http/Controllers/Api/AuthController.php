<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;


class AuthController extends Controller
{
    /**
     * Register a new user.
     */

     public function register(Request $request){
        $request->validate([
            "first_name" => 'required',
            "last_name"=> 'required',
            "email"=> 'required|string|unique:users',
            'password'=> 'required|string|min:8|confirmed',
            ]);

            $user = User::create([
                'first_name' => $request->first_name,
                'last_name'  => $request->last_name,
                'email'      => $request->email,
                'password'   => Hash::make($request->password),
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'succes' => true,   
                'message' => 'user registered successfully',
                'token'=> $token,
                ],201);

            }  

     /**
      * login user and return token
      */

      public function login(Request $request)
      {
        $request->validate([

            'email' => 'required|email',
            'password'=> 'required',
        ]);

        if (!Auth::attempt($request->only('email','password'))) {
            throw ValidationException::withMessages([
                'email'=> ['Use valid account information that matches the database records'],
                ]);
      }

      $user = User::where('email',$request->email)->firstorFail();
      $token = $user->createToken('auth_token')->plainTextToken;

      return response()->json([
        'success' => true,
        'message'=> 'Login Successful',
        'user' => $user,
        'token'=> $token,
      ]);

      
    }
    /**
       * logout user (revoke token)
       * optional: call this from frontend on logout
       */
    public function logout(Request $request){
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            'success'=> true,
            'message'=> 'Logged out Successfully',
            ]);
    }

    /**
     * get authenticated user.
     */

     public function user(Request $request)
     { 
        return response()->json([
            'success'=> true,
            'user'=> $request->user(),
            ]);

     }
}
 