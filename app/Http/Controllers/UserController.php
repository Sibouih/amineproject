<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Models\role_user;
use App\Models\product_warehouse;
use App\Models\Warehouse;
use App\Models\UserWarehouse;
use App\utils\helpers;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Config;
use File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Intervention\Image\ImageManagerStatic as Image;
use \Nwidart\Modules\Facades\Module;

class UserController extends BaseController
{

    //------------- GET ALL USERS---------\\

    public function index(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'view', User::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField;
        $dir = $request->SortType;
        $helpers = new helpers();
        // Filter fields With Params to retrieve
        $columns = array(0 => 'username', 1 => 'statut', 2 => 'phone', 3 => 'email');
        $param = array(0 => 'like', 1 => '=', 2 => 'like', 3 => 'like');
        $data = array();

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $users = User::where(function ($query) use ($ShowRecord) {
            if (!$ShowRecord) {
                return $query->where('id', '=', Auth::user()->id);
            }
        });

        //Multiple Filter
        $Filtred = $helpers->filter($users, $columns, $param, $request)
        // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('username', 'LIKE', "%{$request->search}%")
                        ->orWhere('firstname', 'LIKE', "%{$request->search}%")
                        ->orWhere('lastname', 'LIKE', "%{$request->search}%")
                        ->orWhere('email', 'LIKE', "%{$request->search}%")
                        ->orWhere('phone', 'LIKE', "%{$request->search}%");
                });
            });
        $totalRows = $Filtred->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $users = $Filtred->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        $roles = Role::where('deleted_at', null)->get(['id', 'name']);
        $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
        // customers offered in the "Point of sale" section of the user form
        $clients = Client::where('deleted_at', '=', null)->orderBy('name')->get(['id', 'name']);

        return response()->json([
            'users' => $users,
            'roles' => $roles,
            'warehouses' => $warehouses,
            'clients' => $clients,
            'totalRows' => $totalRows,
        ]);
    }

    //------------- GET USER Auth ---------\\

    public function GetUserAuth(Request $request)
    {
        $helpers = new helpers();
        $user['avatar'] = Auth::user()->avatar;
        $user['username'] = Auth::user()->username;
        // 1 = this user can only use the POS (front redirects there)
        $user['pos_only'] = (int) Auth::user()->pos_only;
        $user['currency'] = $helpers->Get_Currency();
        $user['logo'] = Setting::first()->logo;
        $user['default_language'] = Setting::first()->default_language;
        $user['footer'] = Setting::first()->footer;
        $user['developed_by'] = Setting::first()->developed_by;
        $permissions = Auth::user()->roles()->first()->permissions->pluck('name');
        $products_alerts = product_warehouse::join('products', 'product_warehouse.product_id', '=', 'products.id')
            ->whereRaw('qte <= stock_alert')
            ->where('product_warehouse.deleted_at', null)
            ->count();


        $allEnabledModules = Module::allEnabled();
        $ModulesEnabled = [];
        foreach($allEnabledModules as $module) {
            $name = $module->getName();
            $lowercaseName = strtolower($name);
            $module_name = config($lowercaseName . '.name');
            $url = config($lowercaseName . '.url');
            $icon = config($lowercaseName . '.icon');
            $permission = config($lowercaseName . '.permission');
            $ModulesEnabled[] = [
                'name' => $module_name,
                'url' => $url,
                'icon' => $icon,
                'permission' => $permission,
            ];
        }

        return response()->json([
            'success' => true,
            'user' => $user,
            'notifs' => $products_alerts,
            'permissions' => $permissions,
            'ModulesEnabled' => $ModulesEnabled, 
        ]);
    }

    //------------- GET USER ROLES ---------\\

    public function GetUserRole(Request $request)
    {

        $roles = Auth::user()->roles()->with('permissions')->first();

        $data = [];
        if ($roles) {
            foreach ($roles->permissions as $permission) {
                $data[] = $permission->name;

            }
            return response()->json(['success' => true, 'data' => $data]);
        }

    }

    //------------- STORE NEW USER ---------\\

    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', User::class);
        $this->validate($request, [
            'email' => 'required|unique:users',
        ], [
            'email.unique' => 'This Email already taken.',
        ]);
        \DB::transaction(function () use ($request) {
            if ($request->hasFile('avatar')) {

                $image = $request->file('avatar');
                $filename = rand(11111111, 99999999) . $image->getClientOriginalName();

                $image_resize = Image::make($image->getRealPath());
                $image_resize->resize(128, 128);
                $image_resize->save(public_path('/images/avatar/' . $filename));

            } else {
                $filename = 'no_avatar.png';
            }

            if($request['is_all_warehouses'] == '1' || $request['is_all_warehouses'] == 'true'){
                $is_all_warehouses = 1;
            }else{
                $is_all_warehouses = 0;
            }

            $User = new User;
            $User->firstname = $request['firstname'];
            $User->lastname  = $request['lastname'];
            $User->username  = $request['username'];
            $User->email     = $request['email'];
            $User->phone     = $request['phone'];
            $User->password  = Hash::make($request['password']);
            $User->avatar    = $filename;
            $User->role_id   = $request['role'];
            $User->is_all_warehouses   = $is_all_warehouses;
            $User->save();

            $role_user = new role_user;
            $role_user->user_id = $User->id;
            $role_user->role_id = $request['role'];
            $role_user->save();

            if(!$User->is_all_warehouses){
                $User->assignedWarehouses()->sync($request['assigned_to']);
            }

            $this->savePosSettings($User, $request);

        }, 10);

        return response()->json(['success' => true]);
    }

    //------------ function show -----------\\

    public function show($id){
        //
        
    }

    public function edit(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', User::class);

        $assigned_warehouses = UserWarehouse::where('user_id', $id)->pluck('warehouse_id')->toArray();
        $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $assigned_warehouses)->pluck('id')->toArray();

        $user = User::findOrFail($id);
        $pos_clients = $user->posClients()->where('clients.deleted_at', '=', null)->pluck('clients.id')->toArray();

        return response()->json([
            'assigned_warehouses' => $warehouses,
            'pos_all_clients' => $user->pos_all_clients === null ? 1 : (int) $user->pos_all_clients,
            'pos_clients' => $pos_clients,
            'pos_default_client_id' => $user->pos_default_client_id,
            'pos_default_warehouse_id' => $user->pos_default_warehouse_id,
            'pos_only' => (int) $user->pos_only,
            'pos_edit_price' => (int) $user->pos_edit_price,
            'pos_edit_confirmed' => (int) $user->pos_edit_confirmed,
        ]);
    }

    //------------- POS settings of a user (customers allowed in the POS, defaults) ---------\\

    private function savePosSettings(User $user, Request $request)
    {
        // older forms do not send the POS section: keep what is saved
        if (!$request->has('pos_all_clients')) {
            return;
        }

        $all_clients = in_array((string) $request['pos_all_clients'], ['1', 'true'], true) ? 1 : 0;

        $client_ids = [];
        if (!$all_clients) {
            $client_ids = Client::where('deleted_at', '=', null)
                ->whereIn('id', array_filter((array) $request['pos_clients']))
                ->pluck('id')->toArray();
            // no customer chosen = no restriction
            if (!count($client_ids)) {
                $all_clients = 1;
            }
        }
        $user->posClients()->sync($all_clients ? [] : $client_ids);

        // default customer must be one the user can use
        $default_client = $request['pos_default_client_id'] ? (int) $request['pos_default_client_id'] : null;
        if ($default_client) {
            $ok = $all_clients
                ? Client::where('deleted_at', '=', null)->where('id', $default_client)->exists()
                : in_array($default_client, $client_ids);
            if (!$ok) {
                $default_client = null;
            }
        }
        if (!$all_clients && count($client_ids) == 1) {
            $default_client = $client_ids[0];
        }

        // default POS warehouse must be one of the user's warehouses
        $default_warehouse = $request['pos_default_warehouse_id'] ? (int) $request['pos_default_warehouse_id'] : null;
        if ($default_warehouse) {
            $query = Warehouse::where('deleted_at', '=', null)->where('id', $default_warehouse);
            if (!$user->is_all_warehouses) {
                $query->whereIn('id', UserWarehouse::where('user_id', $user->id)->pluck('warehouse_id')->toArray());
            }
            if (!$query->exists()) {
                $default_warehouse = null;
            }
        }

        User::whereId($user->id)->update([
            // never on your own account, so an admin cannot lock himself into the POS
            'pos_only' => $user->id != Auth::id() && in_array((string) $request['pos_only'], ['1', 'true'], true) ? 1 : 0,
            // cart permissions in the POS
            'pos_edit_price' => in_array((string) $request['pos_edit_price'], ['1', 'true'], true) ? 1 : 0,
            'pos_edit_confirmed' => in_array((string) $request['pos_edit_confirmed'], ['1', 'true'], true) ? 1 : 0,
            'pos_all_clients' => $all_clients,
            'pos_default_client_id' => $default_client,
            'pos_default_warehouse_id' => $default_warehouse,
        ]);
    }

    //------------- UPDATE  USER ---------\\

    public function update(Request $request, $id)
    {        
        $this->authorizeForUser($request->user('api'), 'update', User::class);
        
        $this->validate($request, [
            'email' => 'required|email|unique:users',
            'email' => Rule::unique('users')->ignore($id),
        ], [
            'email.unique' => 'This Email already taken.',
        ]);

        \DB::transaction(function () use ($id ,$request) {
            $user = User::findOrFail($id);
            $current = $user->password;

            if ($request->NewPassword != 'null') {
                if ($request->NewPassword != $current) {
                    $pass = Hash::make($request->NewPassword);
                } else {
                    $pass = $user->password;
                }

            } else {
                $pass = $user->password;
            }

            $currentAvatar = $user->avatar;
            if ($request->avatar != $currentAvatar) {

                $image = $request->file('avatar');
                $path = public_path() . '/images/avatar';
                $filename = rand(11111111, 99999999) . $image->getClientOriginalName();

                $image_resize = Image::make($image->getRealPath());
                $image_resize->resize(128, 128);
                $image_resize->save(public_path('/images/avatar/' . $filename));

                $userPhoto = $path . '/' . $currentAvatar;
                if (file_exists($userPhoto)) {
                    if ($user->avatar != 'no_avatar.png') {
                        @unlink($userPhoto);
                    }
                }
            } else {
                $filename = $currentAvatar;
            }

            if($request['is_all_warehouses'] == '1' || $request['is_all_warehouses'] == 'true'){
                $is_all_warehouses = 1;
            }else{
                $is_all_warehouses = 0;
            }

            User::whereId($id)->update([
                'firstname' => $request['firstname'],
                'lastname' => $request['lastname'],
                'username' => $request['username'],
                'email' => $request['email'],
                'phone' => $request['phone'],
                'password' => $pass,
                'avatar' => $filename,
                'statut' => $request['statut'],
                'is_all_warehouses' => $is_all_warehouses,
                'role_id' => $request['role'],

            ]);

            role_user::where('user_id' , $id)->update([
                'user_id' => $id,
                'role_id' => $request['role'],
            ]);

            $user_saved = User::where('deleted_at', '=', null)->findOrFail($id);
            $user_saved->assignedWarehouses()->sync($request['assigned_to']);

            $this->savePosSettings($user_saved, $request);

        }, 10);
        
        return response()->json(['success' => true]);

    }


    //------------- UPDATE PROFILE ---------\\

    public function updateProfile(Request $request)
    {
        $id = Auth::user()->id;
        $user = User::findOrFail($id);
        $current = $user->password;

        if ($request->NewPassword != 'undefined') {
            if ($request->NewPassword != $current) {
                $pass = Hash::make($request->NewPassword);
            } else {
                $pass = $user->password;
            }

        } else {
            $pass = $user->password;
        }

        $currentAvatar = $user->avatar;
        if ($request->avatar != $currentAvatar) {

            $image = $request->file('avatar');
            $path = public_path() . '/images/avatar';
            $filename = rand(11111111, 99999999) . $image->getClientOriginalName();

            $image_resize = Image::make($image->getRealPath());
            $image_resize->resize(128, 128);
            $image_resize->save(public_path('/images/avatar/' . $filename));

            $userPhoto = $path . '/' . $currentAvatar;

            if (file_exists($userPhoto)) {
                if ($user->avatar != 'no_avatar.png') {
                    @unlink($userPhoto);
                }
            }
        } else {
            $filename = $currentAvatar;
        }

        User::whereId($id)->update([
            'firstname' => $request['firstname'],
            'lastname' => $request['lastname'],
            'username' => $request['username'],
            'email' => $request['email'],
            'phone' => $request['phone'],
            'password' => $pass,
            'avatar' => $filename,

        ]);

        return response()->json(['avatar' => $filename, 'user' => $request['username']]);

    }

    //----------- IsActivated (Update Statut User) -------\\

    public function IsActivated(request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'update', User::class);

        $user = Auth::user();
        if ($request['id'] !== $user->id) {
            User::whereId($id)->update([
                'statut' => $request['statut'],
            ]);
            return response()->json([
                'success' => true,
            ]);
        } else {
            return response()->json([
                'success' => false,
            ]);
        }
    }

    public function GetPermissions()
    {
        $roles = Auth::user()->roles()->with('permissions')->first();
        $data = [];
        if ($roles) {
            foreach ($roles->permissions as $permission) {
                $item[$permission->name]['slug'] = $permission->name;
                $item[$permission->name]['id'] = $permission->id;

            }
            $data[] = $item;
        }
        return $data[0];

    }

    //------------- GET USER Auth ---------\\

    public function GetInfoProfile(Request $request)
    {
        $data = Auth::user();
        return response()->json(['success' => true, 'user' => $data]);
    }

}
