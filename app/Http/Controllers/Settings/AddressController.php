<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AddressUpdateRequest;
use App\Models\Address;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Where an account is billed, and where its post goes.
 */
class AddressController extends Controller
{
    /**
     * Show the account's addresses.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/Addresses', [
            'billing' => $user->billingAddress,
            'delivery' => $user->deliveryAddress,
            'phone' => $user->phone,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Save the account's addresses.
     */
    public function update(AddressUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->update(['phone' => $request->validated('phone')]);

        $user->addresses()->updateOrCreate(
            ['type' => Address::BILLING],
            $request->validated('billing'),
        );

        // Same as billing is held as the absence of a delivery address, so
        // ticking the box puts the old one away rather than leaving a stale
        // copy behind the form to be picked up by whatever posts the parcel.
        if ($request->boolean('delivery_same_as_billing')) {
            $user->addresses()->where('type', Address::DELIVERY)->delete();
        } else {
            $user->addresses()->updateOrCreate(
                ['type' => Address::DELIVERY],
                $request->validated('delivery'),
            );
        }

        return to_route('addresses.edit')->with('status', 'Addresses saved.');
    }
}
