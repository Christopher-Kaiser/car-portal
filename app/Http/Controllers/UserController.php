<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function dashboard()
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('entry')->withErrors(['error' => 'Unauthorized']);
        }

        if ($user->role !== 'user') {
            return redirect()->route('entry')->withErrors(['error' => 'Only users can access this dashboard']);
        }

        return view('user');
    }
}