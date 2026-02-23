<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class RefundController extends Controller
{
    /**
     * Display refund policy page
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('refund');
    }
}
