<?php

namespace VentDepot\Core;

class Application
{
    /**
     * Handle the incoming request
     *
     * @param array $request
     * @return string
     */
    public function handle(array $request): string
    {
        // For now, just return a simple response
        // In a real application, this would route to controllers
        
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        
        // Remove query string
        if (strpos($uri, '?') !== false) {
            $uri = substr($uri, 0, strpos($uri, '?'));
        }
        
        // Simple routing
        switch ($uri) {
            case '/':
                return $this->renderHomePage();
            case '/api':
                return $this->renderApiInfo();
            default:
                return $this->renderNotFound();
        }
    }
    
    /**
     * Render the home page
     *
     * @return string
     */
    private function renderHomePage(): string
    {
        return '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>VentDepot - E-commerce Marketplace</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; }
                h1 { color: #333; }
                p { color: #666; }
            </style>
        </head>
        <body>
            <h1>Welcome to VentDepot</h1>
            <p>Your e-commerce marketplace platform is successfully installed!</p>
            <p><a href="/api">API Documentation</a></p>
        </body>
        </html>';
    }
    
    /**
     * Render API information
     *
     * @return string
     */
    private function renderApiInfo(): string
    {
        return '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>API - VentDepot</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; }
                h1 { color: #333; }
                p { color: #666; }
            </style>
        </head>
        <body>
            <h1>VentDepot API</h1>
            <p>Please refer to the <a href="/api/README.md">API Documentation</a> for detailed information.</p>
            <p><a href="/">Back to Home</a></p>
        </body>
        </html>';
    }
    
    /**
     * Render 404 page
     *
     * @return string
     */
    private function renderNotFound(): string
    {
        http_response_code(404);
        return '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>404 - Not Found</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; }
                h1 { color: #333; }
                p { color: #666; }
            </style>
        </head>
        <body>
            <h1>404 - Page Not Found</h1>
            <p>The requested page could not be found.</p>
            <p><a href="/">Back to Home</a></p>
        </body>
        </html>';
    }
}