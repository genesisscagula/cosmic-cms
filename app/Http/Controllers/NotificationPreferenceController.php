<?php
namespace App\Http\Controllers;
use App\Services\NotificationPreferenceService;
use Illuminate\Http\Request;
class NotificationPreferenceController extends Controller
{
    public function update(Request $request, NotificationPreferenceService $service)
    {
        $keys = array_keys(NotificationPreferenceService::DEFAULTS);
        $data = $request->validate(['preferences'=>['required','array'], 'preferences.*'=>['boolean']]);
        $prefs = $service->all($request->user());
        foreach ($keys as $key) if (array_key_exists($key, $data['preferences'])) $prefs[$key] = (bool) $data['preferences'][$key];
        $prefs['security'] = true;
        $request->user()->forceFill(['notification_preferences'=>$prefs])->save();
        return back()->with('status','notification-preferences-updated');
    }
}
