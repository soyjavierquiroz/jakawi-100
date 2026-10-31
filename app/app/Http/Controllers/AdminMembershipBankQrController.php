<?php

namespace App\Http\Controllers;

use App\Services\MembershipBankQr;
use Illuminate\Http\Request;

class AdminMembershipBankQrController extends Controller
{
    public function show(MembershipBankQr $qr)
    {
        return $qr->response();
    }

    public function store(Request $request, MembershipBankQr $qr)
    {
        $request->validate(['qr_image' => ['required', 'file', 'image', 'mimes:png,jpg,jpeg', 'max:10240']]);
        $qr->save($request->file('qr_image'), $request->user());

        return redirect('/admin/conciliacion-membresias#configuracion-qr')->with('status', 'QR bancario guardado. Ya se muestra en el checkout de Bs 100.');
    }
}
