<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Token;
use App\User;
use Auth;
use Hash;

class AuthController extends Controller
{
    public function login()
    {
        if (Session::get('log') == 'true') {
            $user = User::find(auth()->user()->id);
            if (Session::get('email', $user->email)) {
                return redirect()->back();
            }
            return redirect('/');
        }
    	return view('auth/login');
    }

    public function postLogin(Request $request)
    {
        $this->validate($request, [
            'email' => 'required|email',
            'password' => 'required|min:6',
        ]);

        $user = User::where('email', $request->email)->get()->first();
        if ($user) {
        	if (Hash::check($request->password, $user->password)) {
        		if ($user->is_verified == 1) {
	                Auth::attempt($request->only('email', 'password'));
	                Session::put('log', 'true');
	                Session::put('email', $user->email);
	                return redirect('/main');
	            } else {
	                return redirect('/verify/' . $request->email);
	            }
        	} else {
        		return redirect('/')->with('auth', 'no credential');
        	}
        }
        
        return redirect('/')->with('auth', 'no credential');
    }

    public function register()
    {
        if (Session::get('log') == 'true') {
            $user = User::find(auth()->user()->id);
            if (Session::get('email', $user->email)) {
                return redirect()->back();
            }
            return redirect('/');
        }

    	return view('auth/register');
    }

    public function postRegister(Request $request)
    {
        $this->validate($request, [
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6|confirmed',
            'password_confirmation' => 'required|min:6',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'remember_token' => Str::random(10),
            'is_verified' => 0,
        ]);

        $token = Token::create([
            'email' => $user->email,
            'token' => strtoupper(Str::random(5)),
        ]);

        $to_email = $request->email;
        $to_name = $request->name;

        $data = [
            'name' => $request->name,
            'token' => $token->token,
        ];

        Mail::send('email/confirmation', ['data' => $data], function ($m) use ($to_email, $to_name) {
            $m->subject('Account Verification');
            $m->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'));
            $m->to($to_email, $to_name);
        });

        return redirect('/verify/' . $request->email)->with('auth', 'user created');
    }

    public function verify($email)
    {
        if (Session::get('log') == 'true') {
            $user = User::find(auth()->user()->id);
            if (Session::get('email', $user->email)) {
                return redirect()->back();
            }
            return redirect('/');
        }

        $user = User::where('email', $email)->get()->first();
        if ($user == null) {
            return redirect()->back()->with('auth', 'no verif');
        }
        $token = Token::where('email', $email)->get();
        $finalToken = null;
        foreach ($token as $t) {
            if (strlen($t->token) === 5) {
                $finalToken = $t->token;
            } else {
                $finalToken = $finalToken;
            }
        }
        if (!$finalToken) {
            return redirect('/')->with('auth', 'no verif');
        }
        return view('auth/verify', compact(['user']));
    }

    public function postVerify(Request $request, $email) 
    {
        $token = strtoupper($request->input1 . $request->input2 . $request->input3 . $request->input4 . $request->input5);
        $token = Token::where('email', $email)->where('token', $token)->get()->first();
        if (!$token) {
            return redirect('/verify/' . $email)->with('auth', 'no token found');
        } else {
            $userID = User::where('email', $email)->get()->first()->id;
            $user = User::find($userID);
            $user->update(['is_verified' => 1]);
            Token::find($token->id)->delete();
            return redirect('/')->with('auth', 'verify');
        }
    }

    public function resendVerify($email)
    {
        if (Session::get('log') == 'true') {
            $user = User::find(auth()->user()->id);
            if (Session::get('email', $user->email)) {
                return redirect()->back();
            }
            return redirect('/');
        }

        $user = User::where('email', $email)->get()->first();
        if ($user == null) {
            return redirect('/')->with('auth', 'no verif');
        }
        $getAllToken = Token::where('email', $email)->get();
        $token = null;
        foreach ($getAllToken as $getToken) {
            if (strlen($getToken->token) === 5) {
                $token = $getToken->token;
            } else {
                $token = $token;
            }
        }
        if (!$token) {
            return redirect('/')->with('auth', 'no verif');
        }

        $tokenRow = Token::find(Token::where('email', $email)->where('token', $token)->get()->first()->id);
        $tokenRow->update(['token' => strtoupper(Str::random(5))]);

        $to_email = $email;
        $to_name = $user->name;

        $data = [
            'name' => $user->name,
            'token' => $tokenRow->token,
        ];

        Mail::send('email/confirmation', ['data' => $data], function ($m) use ($to_email, $to_name) {
            $m->subject('Account Verification');
            $m->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'));
            $m->to($to_email, $to_name);
        });

        return redirect('/verify/' . $email)->with('auth', 'resend');
    }

    public function forgetPassword()
    {
    	if (Session::get('log') == 'true') {
            $user = User::find(auth()->user()->id);
            if (Session::get('email', $user->email)) {
                return redirect()->back();
            }
            return redirect('/');
        }
        
        return view('auth/forget');
    }

    public function postForgetPassword(Request $request)
    {
        if (Session::get('log') == 'true') {
            $user = User::find(auth()->user()->id);
            if (Session::get('email', $user->email)) {
                return redirect()->back();
            }
            return redirect('/');
        }
        
        $this->validate($request, [
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->get()->first();
        if (!$user) {
            return redirect('/forget')->with('auth', 'email not found');
        }

        $fetchToken = Token::where('email', $request->email)->get();
        $forgetToken = null;
        foreach ($fetchToken as $fetchT) {
        	if (strlen($fetchT->token) == 30) {
        		$forgetToken = $fetchT->token;
        	} else {
        		$forgetToken = null;
        	}
        }

        if ($forgetToken == null) {
        	$token = Token::create([
	            'email' => $request->email,
	            'token' => Str::random(30),
	        ]);

	        $data = [
	            'name' => $user->name,
	            'email' => $user->email,
	            'token' => $token->token,
	        ];

	        $to_email = $user->email;
	        $to_name = $user->name;

	        Mail::send('email/forget', ['data' => $data], function ($m) use ($to_email, $to_name) {
	            $m->subject('Password Reset');
	            $m->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'));
	            $m->to($to_email, $to_name);
	        });	
        } else {
        	$token = Token::find(Token::where('email', $request->email)->where('token', $forgetToken)->get()->first()->id);
        	$token->update([
        		'token' => Str::random(30),
        	]);

        	$data = [
	            'name' => $user->name,
	            'email' => $user->email,
	            'token' => $token->token,
	        ];

	        $to_email = $user->email;
	        $to_name = $user->name;

	        Mail::send('email/forget', ['data' => $data], function ($m) use ($to_email, $to_name) {
	            $m->subject('Password Reset');
	            $m->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'));
	            $m->to($to_email, $to_name);
	        });	
        }

        return redirect('/forget')->with('auth', 'reset link send');
    }

    public function resetPassword($email, $token)
    {
        if (Session::get('log') == 'true') {
            $user = User::find(auth()->user()->id);
            if (Session::get('email', $user->email)) {
                return redirect()->back();
            }
            return redirect('/');
        }

        $token = Token::where('email', $email)->where('token', $token)->get()->first();

        if (!$token) {
            return redirect('/')->with('auth', 'no verif');
        }

        return view('auth/reset', [
            'email' => $email,
            'token' => $token,
        ]);
    }

    public function postResetPassword(Request $request, $email, $token)
    {
        if (Session::get('log') == 'true') {
            $user = User::find(auth()->user()->id);
            if (Session::get('email', $user->email)) {
                return redirect()->back();
            }
            return redirect('/');
        }

        $oldToken = Token::where('email', $email)->where('token', $token)->get()->first();

        if (!$oldToken) {
            return redirect('/')->with('auth', 'no verif');
        }

        $this->validate($request, [
            'password' => 'required|min:6|confirmed',
            'password_confirmation' => 'required|min:6',
        ]);

        $user = User::find(User::where('email', $email)->get()->first()->id);
        $user->update([
            'password' => bcrypt($request->password),
        ]);

        $oldToken2 = Token::find($oldToken->id);
        $oldToken2->delete();

        return redirect('/')->with('auth', 'password changed');
    }

    public function logout()
    {
        if (Session::get('log') == 'true') {
            $user = User::find(auth()->user()->id);
            if (Session::get('email') == $user->email) {
                Auth::logout();
                Session::pull('log', 'true');
                Session::pull('email', $user->email);
                return redirect('/')->with('auth', 'logout');
            }
            return redirect('/');
        }
        return redirect('/');
    }
}