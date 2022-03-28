<?php

declare(strict_types=1);

namespace App;

use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;

/**
 * @OA\Info(title="Takeatoll API", version="1.0")
 */
class Api
{
    /**
     * Stores an instance of the Slim application
     *
     * @var App
     */
    private App $app;

    /**
     * Registers the routes for the RESTful API
     */
    public function __construct()
    {
        $this->app = AppFactory::create();

        $this->app->group('/api', function (RouteCollectorProxy $group) {
            /**
             * @OA\Post(
             *   path="/api/stations/{stationId}/passthroughs",
             *   description="Register pass-through a specific motorway station",
             *   @OA\PathParameter(
             *     name="stationId",
             *     description="ID of the motorway station",
             *     required=true,
             *     @OA\Schema(type="number")
             *   ),
             *   @OA\Response(response="200", description="Successful request")
             * )
             */
            $group->post('/stations/{stationId:[1-9][0-9]*}/passthroughs', 'App\Controllers\StationController:store');
        });
    }

    /**
     * Returns the instance of Slim behind this application
     *
     * @return App
     */
    public function getApp(): App
    {
        return $this->app;
    }

    /**
     * Starts the execution of the RESTful API
     *
     * @return void
     */
    public function start(): void
    {
        $this->app->run();
    }
}
