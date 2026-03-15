<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Resolve a "Back to [Name]" link from the HTTP Referer.
     * Returns ['url' => ..., 'label' => ...] or null.
     */
    protected function resolveBackLink(Request $request): ?array
    {
        $referer = $request->headers->get('referer');
        if (! $referer) {
            return null;
        }

        $path = parse_url($referer, PHP_URL_PATH);
        if (! $path) {
            return null;
        }

        // Don't show a back-link to the page we're already on (e.g. after a reload).
        if ('/' . ltrim($request->path(), '/') === $path) {
            return null;
        }

        if (preg_match('#^/companies/(\d+)$#', $path, $m)) {
            $entity = \App\Models\Company::find((int) $m[1]);

            return $entity ? ['url' => $referer, 'label' => $entity->name] : null;
        }

        if (preg_match('#^/people/(\d+)$#', $path, $m)) {
            $entity = \App\Models\Person::find((int) $m[1]);

            return $entity ? ['url' => $referer, 'label' => $entity->full_name] : null;
        }

        if (preg_match('#^/conversations/(\d+)$#', $path, $m)) {
            $entity = \App\Models\Conversation::find((int) $m[1]);

            return $entity ? ['url' => $referer, 'label' => $entity->subject ?? 'Conversation'] : null;
        }

        return null;
    }
}
