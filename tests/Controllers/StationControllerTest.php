<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Models\TransponderModel;
use App\Models\CustomerModel;
use Slim\Psr7\Uri;
use Slim\Psr7\Request;
use Slim\Psr7\Headers;
use Slim\Psr7\Factory\StreamFactory;
use Prophecy\PhpUnit\ProphecyTrait;
use Slim\App;
use App\Api;

class StationControllerTest extends TestCase
{
    use ProphecyTrait;

    // Stores an instance of the application
    private App $app;

    // Stores data about a fake customer
    private array $customer;

    // Stores data about a fake transponder
    private array $transponder;

    /**
     * Creates a fake customer, to which we associate a set of fake transponders.
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->app = (new Api())->getApp();
        $this->customer = CustomerModel::fake(true);
        $this->transponder = TransponderModel::fake(true, [
            'customer_id' => $this->customer['id'],
        ]);
    }

    /**
     * Destroys the fake customer created for the tests
     *
     * @return void
     */
    public function tearDown(): void
    {
        CustomerModel::delete($this->customer['id']);
    }

    /**
     * Checks that the request returns a bad response if no JSON body is provided
     *
     * @return void
     */
    public function testBadResponseOnMissingRequestBody(): void
    {
        $request = $this->createRequest('POST', '/api/stations/1/passthroughs');
        $response = $this->app->handle($request);

        $body = (string) $response->getBody();
        $jsonBody = json_decode($body, true);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertFalse($jsonBody['ok']);
        $this->assertSame('Missing request body', $jsonBody['message']);
    }

    /**
     * Checks that -- whenever we attempt to record an exit and we don't have a pending segment --
     * the API call results in a bad request.
     *
     * @return void
     */
    public function testBadResponseOnRecordingExitWithoutPreviousEntrance(): void
    {
        $request = $this->createRequest('POST', '/api/stations/1/passthroughs', [], [
            'serialNumber' => $this->transponder['serial_number'],
            'type' => 'exit',
        ]);

        $response = $this->app->handle($request);
        $body = (string) $response->getBody();
        $jsonBody = json_decode($body, true);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertFalse($jsonBody['ok']);
        $this->assertSame('No segment to close', $jsonBody['message']);
    }

    /**
     * Checks that the recording an exit after an entrance works
     * and results in the computation of the cost for the segment.
     *
     * @return void
     */
    public function testGoodResponseOnRecordingExit(): void
    {
        // Create entrance record
        $request = $this->createRequest('POST', '/api/stations/1/passthroughs', [], [
            'serialNumber' => $this->transponder['serial_number'],
            'type' => 'entrance',
        ]);

        $response = $this->app->handle($request);
        $this->assertSame(200, $response->getStatusCode());

        // Create exit record
        $request = $this->createRequest('POST', '/api/stations/2/passthroughs', [], [
            'serialNumber' => $this->transponder['serial_number'],
            'type' => 'exit',
        ]);

        $response = $this->app->handle($request);
        $body = (string) $response->getBody();
        $jsonBody = json_decode($body, true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($jsonBody['ok']);
        $this->assertTrue($jsonBody['cost'] > 0);
    }

    /**
     * Creates a new HTTP request object
     * See https://github.com/slimphp/Slim-Skeleton/blob/master/tests/TestCase.php#L68-L86
     *
     * @param string $method HTTP method
     * @param string $path Path to invoke
     * @param array $headers
     * @param array $data
     * @return Request
     */
    private function createRequest(
        string $method,
        string $path,
        array $headers = [],
        array $data = []
    ): Request {
        $uri = new Uri('', '', 8000, $path);
        $handle = fopen('php://temp', 'w+');

        if ($data) {
            fwrite($handle, json_encode($data));
        }

        $stream = (new StreamFactory())->createStreamFromResource($handle);
        $h = new Headers();
        $h->addHeader('HTTP_ACCEPT', 'application/json');

        foreach ($headers as $header => $value) {
            $h->addHeader($header, $value);
        }

        return new Request($method, $uri, $h, [], [], $stream);
    }
}
