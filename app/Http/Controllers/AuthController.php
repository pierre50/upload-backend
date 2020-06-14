<?php
namespace App\Http\Controllers;
use App\User;
use Mail;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;

/**
 * Class AuthController
 *
 * @package App\Http\Controllers
 * @resource Authentication
 */
class AuthController extends Controller
{
    use AuthenticatesUsers;
    /**
     * Login
     *
     * Handle a login request to the application.
     *
     * @param  \Illuminate\Http\Request  $request
     */
    public function login(Request $request)
    {
        $this->validateLogin($request);
        // If the class is using the ThrottlesLogins trait, we can automatically throttle
        // the login attempts for this application. We'll key this by the username and
        // the IP address of the client making these requests into this application.
        if ($this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);
            $seconds = $this->limiter()->availableIn($this->throttleKey($request));
            abort(429, Lang::get('auth.throttle', ['seconds' => $seconds]));
        }
        if ($this->attemptLogin($request)) {
            $this->clearLoginAttempts($request);
            $user = $this->guard()->user();
            return ['user' => $user, 'access_token' => $user->makeApiToken()];
        }
        // If the login attempt was unsuccessful we will increment the number of attempts
        // to login and redirect the user back to the login form. Of course, when this
        // user surpasses their maximum number of attempts they will get locked out.
        $this->incrementLoginAttempts($request);
        abort(401, Lang::get('auth.failed'));
    }


    /**
     * Registration
     *
     * Handle a registration request for the application.
     *
     * @param  \Illuminate\Http\Request $request
     * @return array
     */
    public function register(Request $request)
    {
        $this->validator($request->all())->validate();
        event(new Registered($user = $this->create($request->all())));
        $data = [ 
            'user' => $user,
        ];
        /*Mail::send('emails.register', $data , function ($message) use ($user) {
            $message->from('admin@supfile.com', 'Supfile');
            $message->to($user->email)->subject('Welcome to Supfile');
        });*/
        return ['user' => $user, 'access_token' => $user->makeApiToken()];
    }
    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'name' => 'required|max:255',
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|min:6|confirmed',
            'offer_id' => 'required'
        ]);
    }

    /**
     * Forgot password
     *
     *
     * @param  \Illuminate\Http\Request $request
     */
    public function forgot(Request $request){
        $this->validate($request, [
            'email' => 'required',
        ]);
        $email = $request->input('email');
        $user = User::where('email', $email)->first();
        $new_password = $this->generatePassword(12);
        $user->password = bcrypt($new_password);
        $user->save();
        $data = [ 
            'user' => $user,
            'newpassword' => $new_password
        ];
        Mail::send('emails.forgotpassword', $data , function ($message) use ($user) {
            $message->from('admin@supfile.com', 'Supfile');
            $message->to($user->email)->subject('Your new password');
        });

        return "success";
    } 

    protected function generatePassword($length = 8) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $count = mb_strlen($chars);
    
        for ($i = 0, $result = ''; $i < $length; $i++) {
            $index = rand(0, $count - 1);
            $result .= mb_substr($chars, $index, 1);
        }
    
        return $result;
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return User
     */
    protected function create(array $data)
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'offer_id' => $data['offer_id'],
            'password' => bcrypt($data['password']),
        ]);
    }
}