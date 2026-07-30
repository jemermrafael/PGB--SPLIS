<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectToCanonicalPermalink
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$parameters): Response
    {
        if (! $request->isMethodSafe()) {
            return $next($request);
        }

        $route = $request->route();

        if ($route === null || $route->getName() === null) {
            return $next($request);
        }

        $names = $parameters !== []
            ? $parameters
            : array_keys($route->parameters());

        $needsRedirect = false;
        $routeParameters = $route->parameters();

        foreach ($names as $name) {
            $model = $route->parameter($name);

            if (! is_object($model) || ! method_exists($model, 'getRouteKey')) {
                continue;
            }

            $current = $route->originalParameter($name);

            if (! is_string($current) && ! is_numeric($current)) {
                continue;
            }

            $canonical = $model->getRouteKey();

            if ((string) $current !== (string) $canonical) {
                $routeParameters[$name] = $model;
                $needsRedirect = true;
            }
        }

        if (! $needsRedirect) {
            return $next($request);
        }

        $target = route($route->getName(), $routeParameters, absolute: false);
        $query = $request->getQueryString();

        if ($query) {
            $target .= '?'.$query;
        }

        return redirect()->to($target, 301);
    }
}
