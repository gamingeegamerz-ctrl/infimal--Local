<?php
namespace App\Traits;

trait AdminCheck
{
    /** Hard-coded list – change/add whenever you want */
    private function isAdmin($user): bool
    {
        if (!$user) return false;

        $emails = [
            'admin@infimal.site',
            'contact@infimal.site',
            'sainikhilsaini143@gmail.com',
            'khileshrathod1729@gmail.com',
            'kanishghongade@gmail.com',
            'gamingeegamerz@gmail.com'
        ];

        return in_array($user->email, $emails, true) || ($user->is_admin ?? false);
    }
}
