<?php

namespace App\Http\Controllers;

use App\Models\CommerceCustomer;
use App\Models\CommerceOrder;
use App\Models\Website;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class CommerceCustomerController extends Controller
{
    public function localRegister(Request $request, string $slug): RedirectResponse { return $this->register($request, $slug); }
    public function subdomainRegister(Request $request, string $preview): RedirectResponse { return $this->register($request, $preview); }
    public function localLogin(Request $request, string $slug): RedirectResponse { return $this->login($request, $slug); }
    public function subdomainLogin(Request $request, string $preview): RedirectResponse { return $this->login($request, $preview); }
    public function localLogout(Request $request, string $slug): RedirectResponse { return $this->logout($request, $slug); }
    public function subdomainLogout(Request $request, string $preview): RedirectResponse { return $this->logout($request, $preview); }
    public function localLookup(Request $request, string $slug): RedirectResponse { return $this->lookup($request, $slug); }
    public function subdomainLookup(Request $request, string $preview): RedirectResponse { return $this->lookup($request, $preview); }

    private function register(Request $request, string $previewSlug): RedirectResponse
    {
        $website = $this->website($previewSlug);
        $data = $request->validate([
            'first_name' => ['required','string','max:120'],
            'last_name' => ['required','string','max:120'],
            'email' => ['required','email:rfc','max:255'],
            'order_number' => ['required','string','max:40'],
            'password' => ['required','confirmed', Password::min(8)],
        ]);
        $email = mb_strtolower(trim($data['email']));
        $proofOrder = CommerceOrder::query()
            ->where('website_id', $website->id)
            ->whereRaw('LOWER(customer_email) = ?', [$email])
            ->where('order_number', trim($data['order_number']))
            ->first();
        if (! $proofOrder) {
            return back()->withInput($request->except('password','password_confirmation'))->withErrors(['order_number' => 'Use an order number previously placed with this email to verify ownership.']);
        }
        if (CommerceCustomer::where('website_id', $website->id)->where('email', $email)->exists()) {
            return back()->withInput($request->except('password','password_confirmation'))->withErrors(['email' => 'An account already exists for this email.']);
        }
        $customer = CommerceCustomer::create([
            'website_id' => $website->id,
            'email' => $email,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'password' => $data['password'],
            'last_login_at' => now(),
        ]);
        $request->session()->put($this->sessionKey($website), $customer->id);
        $request->session()->regenerate();
        return redirect($this->url($previewSlug, '/account'))->with('commerce_success', 'Account created. Your matching orders are now available.');
    }

    private function login(Request $request, string $previewSlug): RedirectResponse
    {
        $website = $this->website($previewSlug);
        $data = $request->validate(['email' => ['required','email:rfc'], 'password' => ['required','string']]);
        $customer = CommerceCustomer::where('website_id', $website->id)->where('email', mb_strtolower(trim($data['email'])))->first();
        if (! $customer || ! Hash::check($data['password'], $customer->password)) {
            return back()->withInput($request->only('email'))->withErrors(['email' => 'The email or password is incorrect.']);
        }
        $customer->forceFill(['last_login_at' => now()])->save();
        $request->session()->put($this->sessionKey($website), $customer->id);
        $request->session()->regenerate();
        return redirect($this->url($previewSlug, '/account'));
    }

    private function logout(Request $request, string $previewSlug): RedirectResponse
    {
        $website = $this->website($previewSlug);
        $request->session()->forget($this->sessionKey($website));
        $request->session()->regenerateToken();
        return redirect($this->url($previewSlug, '/account/login'));
    }

    private function lookup(Request $request, string $previewSlug): RedirectResponse
    {
        $website = $this->website($previewSlug);
        $data = $request->validate(['order_number' => ['required','string','max:40'], 'email' => ['required','email:rfc','max:255']]);
        $order = CommerceOrder::where('website_id', $website->id)
            ->whereRaw('LOWER(customer_email) = ?', [mb_strtolower(trim($data['email']))])
            ->where('order_number', trim($data['order_number']))
            ->first();
        if (! $order) {
            return back()->withInput()->withErrors(['order_number' => 'We could not find an order matching that number and email.']);
        }
        $request->session()->put('cosmic_commerce_guest_orders.'.$website->id.'.'.$order->public_id, true);
        return redirect($this->url($previewSlug, '/order/'.$order->public_id));
    }

    private function website(string $previewSlug): Website
    {
        $website = Website::where('preview_slug', $previewSlug)->with('commerceSetting')->firstOrFail();
        abort_unless((bool) $website->commerceSetting?->enabled, 404);
        return $website;
    }

    private function sessionKey(Website $website): string { return 'cosmic_commerce_customer.'.$website->id; }
    private function url(string $previewSlug, string $path): string
    {
        return config('cosmic_preview.mode') === 'local' ? '/preview/'.rawurlencode($previewSlug).'/'.ltrim($path, '/') : '/'.ltrim($path, '/');
    }
}
