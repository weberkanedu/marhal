<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Kişi menüsü → Güvenlik → "Cihazlarım": kullanıcı kendi eski cihazını kaldırır (cihaz sınırında yer açar).
 * Başkasının cihazı 404; kullanılan cihaz kaldırılamaz.
 */
class DeviceController extends Controller
{
    public function destroy(Request $request, int $device): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $row = UserDevice::query()->where('user_id', $user->id)->whereKey($device)->firstOrFail();

        abort_if($row->id === $request->session()->get('device_id'), 422, 'Kullandığınız cihaz kaldırılamaz.');

        $row->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$row->label} cihazı kaldırıldı."]);

        return back();
    }
}
