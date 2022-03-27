<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class StationController extends Controller
{
    /**
     * Records a pass-through a specific motorway station.
     *
     * @param ServerRequestInterface $request Request object
     * @param ResponseInterface $response Base response object
     * @param array $args Path parameters
     * @return ResponseInterface Modified response object
     */
    public function store(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return $this->renderJson($response, ['ok' => true]);
    }
}
