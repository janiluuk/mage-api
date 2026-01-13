<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class FileBrowserController extends Controller
{
    public function index()
    {
        return view('admin.files');
    }
}
