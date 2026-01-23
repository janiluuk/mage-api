<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class InstanceManagementController extends Controller
{
    /**
     * Display the instance management admin panel.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('admin.instances');
    }
}
