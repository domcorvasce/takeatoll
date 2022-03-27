<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface;

/**
 * It provides helpers for all controllers
 */
class Controller
{
    /**
     * Returns a JSON-encoded response object
     *
     * @param ResponseInterface $response Base response object
     * @param array $data Data to serialize
     * @return ResponseInterface
     */
    protected function renderJson(ResponseInterface $response, array $data): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
