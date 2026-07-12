<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\AdminUserResource;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        //validate the request
        $request -> validate([
            'uuid' => 'required',
            'website_uuid' => 'required',
        ]);

        //check if the admin exists
        $admin = Admin::where('uuid', $request->uuid)->first();
        if(!$admin){
            return response()->json([
                'status' => 'failed',
                'message' => 'Admin not found'
            ], 404);        
            }

            try {
              $users = DB::connection('system')
            ->table($request->website_uuid . '.users')
            ->orderBy('created_at', 'desc')
            ->get();
            dd($users->first());

                // return response()->json([
                //     'status' => 'success',
                //     'total_users' => $users->count(),
                //     'users' => AdminUserResource::collection($users),
                // ], 200);
            } catch (\Exception $e) {                
                return response()->json([
                    'status' => 'failed',
                    'message' => $e->getMessage()
                ], 500);    
            }
            }
}
