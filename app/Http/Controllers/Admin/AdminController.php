<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Admin;
use App\Notifications\AdminCreationNotification;
use App\Notifications\AdminRoleChangedNotification;
use Illuminate\Support\Facades\DB;
use App\Keygen\Keygen;

use Ramsey\Uuid\Uuid;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    //allowing Admins Login
    public function login(){
        try{
            $rules=[
                'email'     =>'required|email',
                'password'  =>'required|string|min:8'
            ];

            $validation=Validator::make(request()->all(),$rules);

            if($validation->fails()){
                return response()->json([
                    'status'    =>'failed',
                    'message'   =>$validation->errors()->first()
                ],422);
            }

            $admin = Admin::query()->where('email',request()->email)->first();

            if(!$admin){
                return response()->json([
                    'status'    =>'failed',
                    'message'   =>'Admin is not found'
                ],404);
            }

            if(password_verify(request()->password,$admin->password)){

                // $access_token=$admin->createToken('Admin_login_access_token')->accessToken;

                return response()->json([
                    'status'        =>'success',
                    'message'       =>'login successful',
                    // 'access_token'  =>$access_token,
                    'admin_details' =>$admin
            ],200);
            }else{
                return response()->json([
                    'status'    =>'failed',
                    'message'   =>'wrong credentials'
                ],403);
            }

        }catch(\Exception $e){
            return response()->json([
                'status'    =>'failed',
                'message'   =>$e->getMessage()
            ],500);
        }

    }

    //permitting super admin to add other admins
    public function addnewuser(Request $request){
        try{

            $rules = [
                'surname'       =>'required|string',
                'othernames'    =>'required|string',
                'email'         =>'required|email',
                'role'          =>'required|string',
                'phone'         =>'required|string'
            ];

            $validation=Validator::make(request()->all(),$rules);

            if($validation->fails()){
                return response()->json([
                    'status'    =>'failed',
                    'message'   =>$validation->errors()->first()
                ],422);
            }

            $uuid = request()->input('uuid');
            // $superadmin=auth()->guard('admin')->user()->role;
            $superadmin = Admin::query()->where('uuid',$uuid)->first();
            if($superadmin->role !=='super_admin'){
                return response()->json([
                    'status'         =>'failed',
                    'message'       =>'You are not a super admin'

                ],403);

            }


            //generate a random password for the new admin
            $password = Keygen::numeric(8)->prefix('ADM')->generate();

            //creating of new admin
            $newadmin = Admin::create([
                'surname'         =>request()->surname,
                'othernames'      =>request()->othernames,
                'fullname'        =>request()->surname.' '.request()->othernames,
                'email'           =>request()->email,
                'phone'           =>request()->phone,
                'role'            =>request()->role,
                'remember_token'  =>Str::random(10),
                'password'        =>Hash::make($password),
                'uuid'            =>Uuid::uuid4()->toString(),
            ]);

            //notify newadmin about account creation
            if(!$newadmin){
                return response()->json([
                    'status'    =>'failed',
                    'message'   =>'something went wrong admin could not be created'
                ],402);
            }

            $mail_details = [
                'name'      =>request()->othernames,
                'email'     =>request()->email,
                'password'  => $password,
                'url'       =>'www.nubiaemr/adminportal'
            ];

            $newadmin->notify(new AdminCreationNotification($mail_details));
            return response()->json([
                'status'    =>'success',
                'message'   =>'new admin created successfully'
            ],200);

        }catch(\Exception $e){
            return response()->json([
                'status'    =>'failed',
                'message'   =>$e->getMessage()
            ],500);
        }
    }

    public function changerole(Request $request){ //role change of an admin

        try{
            $rules = [
                'uuid' => 'required|string',
                'admin' => 'required|string',
                'role' => 'required|string'
            ];


            $validation=Validator::make(request()->all(), $rules);

            if($validation->fails()){
                return response()->json([
                    'status'    =>'failed',
                    'message'   =>$validation->errors()->first()
                ],422);
            }

            $uuid = request()->input('uuid');  //adding the login-user uuid in other to validate the status
            $AdminUuid = request()->input('admin'); //adding the uuid to find the particular admin wish to update

            $loggedInUser = Admin::query()->where('uuid', $uuid)->first();


            if($loggedInUser->role =='super_admin'){

                $toSuperAdmin = Admin::where('uuid', $AdminUuid)->first();
                $status = $toSuperAdmin->update([
                    'role' => request()->role
                ]);
                // $status -> save();

                    $role =request()->role;
                    $roletext = null;
                    if($role=='super_admin'){
                        $roletext = 'Super Admin';
                    } else {
                        $roletext = 'Admin';
                    }
                $mail_details = [
                    'name'      =>$toSuperAdmin->othernames,
                    'email'     =>$toSuperAdmin->email,
                    'role'      =>$roletext
                ];

                $toSuperAdmin->notify(new AdminRoleChangedNotification($mail_details));
                return response()->json([
                    'status'    =>'success',
                    'message'   =>'admin updated successfully'
                ],200);

                      return response()->json([
                        'status'    =>'success',
                        'message'   =>'Admin role updated successfully']);
             }else{
                      return response()->json([
                        'status'    =>'failed',
                        'message'   =>'you are not eligible for this action'
                      ],200);
            }
        }catch(\Exception $e){
            return response()->json([
                'status'    =>'failed',
                'message'   =>$e->getMessage()
            ],500);
        }
    }


}
