<?php

namespace App\Support;

use App\Models\Contact;

class ContactPayload
{
    public static function make(Contact $contact): array
    {
        return [
            'additional_attributes' => $contact->additional_attributes ?? [],
            'availability_status' => 'offline',
            'email' => $contact->email,
            'id' => $contact->id,
            'name' => $contact->name,
            'phone_number' => $contact->phone_number,
            'blocked' => $contact->blocked,
            'identifier' => $contact->identifier,
            'thumbnail' => '',
            'custom_attributes' => $contact->custom_attributes ?? [],
            'last_activity_at' => $contact->last_activity_at?->timestamp,
            'created_at' => $contact->created_at->timestamp,
            'contact_inboxes' => [],
        ];
    }
}
