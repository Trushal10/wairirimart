<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        $query = Contact::query();
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        return Inertia::render('Admin/Contact/Index', [
            'contacts' => $query->latest('id')->paginate(15)->withQueryString(),
            'filters'  => ['search' => $search],
        ]);
    }

    public function delete(Contact $contact)
    {
        $contact->delete();

        return redirect()->back()->with('message', 'Contact deleted successfully');
    }
}
