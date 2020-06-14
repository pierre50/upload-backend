<?php
namespace App\Http\Controllers;
use App\Rules\ValidatePassword;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Class UserController
 *
 * @package App\Http\Controllers
 * @resource User
 */
class UserController extends Controller
{
    /**
     * Сurrent authenticated user
     *
     * Return current authenticated user data
     *
     * @param Request $request
     * @return mixed
     */
    public function me(Request $request)
    {
        $user = $request->user();
        $user->size = $user->size();
        $user->offer = $user->offer();
        return $request->user();
    }

    /**
     * Users list
     *
     * Display a listing of users
     *
     * @param Request $request
     * @return array
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function index(Request $request)
    {
        $this->authorize('index', User::class);
        $users = (new User)->latest();
        if ($request->get('query')) {
            $users->where('name', 'like', '%' . $request->get('query') . '%')
                ->orWhere('email', 'like', '%' . $request->get('query') . '%');
        }
        return ['users' => $users->paginate()];
    }

    /**
     * Show user
     *
     * Display the specified user.
     *
     * @param User $user
     * @return array
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function show(User $user)
    {
        return ['user' => $user];
    }

    /**
     * Update user
     *
     * Update the specified user in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param User $user
     * @return array
     */
    public function update(Request $request, User $user)
    {
        $this->validate($request, [
            'name' => 'required|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
        ]);
        $user->fill($request->only('name', 'email'));
        $user->save();
        $user->size = $user->size();
        $user->offer = $user->offer();
        return ['user' => $user];
    }

    /**
     * Change password
     *
     * Change the user password.
     *
     * @param  \Illuminate\Http\Request $request
     * @param User $user
     * @return string
     */
    public function changePassword(Request $request, User $user)
    {
        $this->validate($request, [
            'old_password' => ['min:6', new ValidatePassword($user)],
            'password' => 'min:6|confirmed|different:old_password',
        ]);
        if ($request->get('password')) {
            $user->password = bcrypt($request->get('password'));
        }

        $user->save();
        return "success";
    }

    /**
     * Delete user
     *
     * Remove the specified user from storage.
     *
     * @param User $user
     * @return array
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @throws \Exception
     */
    public function delete(Request $request)
    {
        $user = $request->user();
        $user->delete();
        return "success";
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return array
     */
    public function files(Request $request)
    {
        $user = $request->user();
        $files = $user->fileEntries()->get();
        $items = [];
        $directories = Storage::disk('files')->allDirectories(Auth::id());
        foreach($directories as $directory){
            $item = [
                "name" => basename($directory),
                "path" => $directory
            ];
            array_push($items,$item);
        }
        foreach($files as $file){
            if (Storage::disk('files')->exists($file->path)) {
                $item = [
                    "name" => $file->filename,
                    "hash" => $file->hash,
                    "path" => $file->path,
                    "uploadDate" => $file->created_at->timestamp,
                    "size" => $file->size,
                    "mime" => $file->mime
                ];

                array_push($items ,$item);
            }
        }
        return $items;
    }

}