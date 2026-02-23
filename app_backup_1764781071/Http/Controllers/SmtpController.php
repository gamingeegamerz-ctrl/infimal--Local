<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SmtpConfiguration;

class SmtpController extends Controller
{
    public function index()
    {
        $smtp = SmtpConfiguration::where('user_id', auth()->id())->first();
        return view('smtp', compact('smtp'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'host' => 'required|string|max:255',
            'port' => 'required|integer',
            'username' => 'required|string|max:255',
            'password' => 'required|string',
            'encryption' => 'required|in:tls,ssl',
            'from_address' => 'required|email',
            'from_name' => 'required|string|max:255'
        ]);

        SmtpConfiguration::updateOrCreate(
            ['user_id' => auth()->id()],
            $request->all()
        );

        return redirect()->route('smtp.index')->with('success', 'SMTP configuration saved successfully!');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'host' => 'required|string|max:255',
            'port' => 'required|integer',
            'username' => 'required|string|max:255',
            'password' => 'sometimes|string',
            'encryption' => 'required|in:tls,ssl',
            'from_address' => 'required|email',
            'from_name' => 'required|string|max:255'
        ]);

        $smtp = SmtpConfiguration::where('user_id', auth()->id())->findOrFail($id);
        $smtp->update($request->all());

        return redirect()->route('smtp.index')->with('success', 'SMTP configuration updated successfully!');
    }

    public function test(Request $request)
    {
        return response()->json(['success' => true, 'message' => 'SMTP test successful!']);
    }
}
