<?php

namespace Workdo\Hrm\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;

class SystemSetupController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('hrm.branches.index');
    }
}
