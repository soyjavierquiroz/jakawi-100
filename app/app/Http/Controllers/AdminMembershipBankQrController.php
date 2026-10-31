<?php

namespace App\Http\Controllers;

use App\Services\MembershipBankQr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminMembershipBankQrController extends Controller
{
    public function show(MembershipBankQr $qr)
    {
        return $qr->response();
    }

    public function store(Request $request, MembershipBankQr $qr)
    {
        $request->validate(['qr_image' => ['nullable', 'file', 'image', 'mimes:png,jpg,jpeg', 'max:10240'], 'recipient_name' => ['sometimes', 'required', 'string', 'max:255']]);
        if (! $request->hasFile('qr_image') && ! $request->has('recipient_name')) {
            throw ValidationException::withMessages(['qr_image' => 'Selecciona una imagen o completa el destinatario.']);
        }
        DB::transaction(function () use ($request, $qr) {
            if ($request->hasFile('qr_image')) {
                $qr->save($request->file('qr_image'), $request->user());
            }
            if ($request->has('recipient_name')) {
                $qr->saveRecipient($request->string('recipient_name')->toString(), $request->user());
            }
        });

        return redirect('/admin/conciliacion-membresias#configuracion-qr')->with('status', 'Configuración bancaria guardada. Los cambios se muestran en el checkout de Bs 100.');
    }
}
