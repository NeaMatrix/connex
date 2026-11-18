<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Debug;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds "connex_debug" (the raw Connex exchanges of this request) to JSON
 * responses when the debug panel is enabled.
 */
class AttachConnexDebug
{
    public function __construct(
        private readonly DebugRecorder $recorder,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->recorder->enabled() && $response instanceof JsonResponse) {
            $data = $response->getData(true);

            if (is_array($data)) {
                $response->setData($data + ['connex_debug' => $this->recorder->all()]);
            }
        }

        return $response;
    }
}
