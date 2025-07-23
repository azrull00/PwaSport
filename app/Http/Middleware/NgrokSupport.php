<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class NgrokSupport
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if we're running via ngrok
        if ($this->isNgrokRequest($request)) {
            // Force HTTPS scheme for all URLs
            URL::forceScheme('https');
            
            // Update app URL dynamically
            $ngrokUrl = $request->getScheme() . '://' . $request->getHost();
            config(['app.url' => $ngrokUrl]);
            
            // Set the root URL for asset generation
            URL::forceRootUrl($ngrokUrl);
            
            // Add ngrok headers to allow iframe embedding (optional)
            $response = $next($request);
            
            if (method_exists($response, 'header')) {
                $response->header('X-Frame-Options', 'ALLOWALL');
                $response->header('Content-Security-Policy', "frame-ancestors *");
            }
            
            return $response;
        }
        
        return $next($request);
    }
    
    /**
     * Check if the request is coming via ngrok
     */
    private function isNgrokRequest(Request $request): bool
    {
        $host = $request->getHost();
        
        // Check for ngrok domains
        return str_contains($host, 'ngrok.io') || 
               str_contains($host, 'ngrok.app') || 
               str_contains($host, 'ngrok-free.app') ||
               str_contains($host, 'ngrok.dev');
    }
}
