<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;

class AboutController extends Controller
{
    public function index()
    {
        return view('client.about');
    }
}
