<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\ContactRequest;
use App\Models\Contact;
use App\Notifications\ContactNotification;
use Illuminate\Support\Facades\Notification;

class ContactController extends Controller
{
    public function index()
    {
        return view('client.contact');
    }

    public function save(ContactRequest $request)
    {
        $data = $request->validated();
        // Strip anti-spam fields — they aren't columns on the contact model.
        unset($data['recaptcha'], $data['website']);
        $contact = Contact::query()->create($data);

        Notification::route('mail', [
            config('mail.from.address') => config('mail.from.name'),
        ])->notify(new ContactNotification($contact));

        return redirect()->route('client.contact')->with('success', 'Thank you, we have received your request!');
    }
}
