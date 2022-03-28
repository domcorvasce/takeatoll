<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ConfigurationOptionModel;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use App\Models\Model;
use App\Models\TransponderModel;
use App\Models\PassthroughModel;
use App\Models\StationModel;
use App\Helpers\Haversine;

const ENTRANCE_TYPE = 'entrance';
const EXIT_TYPE = 'exit';

class StationController extends Controller
{
    /**
     * Executes some checks which are common to all actions within this controller.
     * If a check fails, it provides a response object which must be returned by the action.
     *
     * @param ResponseInterface $response Base response object
     * @param array $args Path parameters
     * @return ?ResponseInterface
     */
    private function preExecute(ResponseInterface $response, array $args): ?ResponseInterface
    {
        $stationId = $args['stationId'];

        // Checks that the requested station exists
        if (!$stationId || !StationModel::findOne($stationId)) {
            return $this->renderJson($response, [
                'ok' => false,
                'message' => 'Station not found'
            ])->withStatus(404);
        }

        return null;
    }

    /**
     * Executes some checks ahead of processing the "store" action
     *
     * @param ResponseInterface $response Base response object
     * @param array $body Request body
     * @return ?ResponseInterface
     */
    private function validateStore(ResponseInterface $response, array $body): ?ResponseInterface
    {
        // Checks whether the client passed a request body
        if (empty($body)) {
            return $this->renderJson($response, [
                'ok' => false,
                'message' => 'Missing request body',
            ])->withStatus(400);
        }

        // Checks whether the client passed a valid access type
        if (empty($body['type']) || !in_array($body['type'], [ENTRANCE_TYPE, EXIT_TYPE])) {
            return $this->renderJson($response, [
                'ok' => false,
                'message' => 'Invalid access type',
            ])->withStatus(400);
        }

        // Checks whether the client is referencing a valid transponder
        if (!$body['serialNumber']) {
            return $this->renderJson($response, [
                'ok' => false,
                'message' => 'Missing serial number for transponder',
            ])->withStatus(400);
        }

        return null;
    }

    /**
     * Records a pass-through a specific motorway station
     *
     * @param ServerRequestInterface $request Request object
     * @param ResponseInterface $response Base response object
     * @param array $args Path parameters
     * @return ResponseInterface Modified response object
     */
    public function store(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $stationId = (int) $args['stationId'];
        $body = json_decode((string) $request->getBody() ?: '{}', true);

        // Validates the request against general checks for all actions
        if ($genericError = $this->preExecute($response, $args)) {
            return $genericError;
        }

        // Validates the request against specific checks for this action
        if ($validationError = $this->validateStore($response, $body)) {
            return $validationError;
        }

        // Fetches data about the transponder associated to the request
        $transponderSN = $body['serialNumber'];
        $transponder = TransponderModel::findOne($transponderSN);

        if (!$transponder) {
            return $this->renderJson($response, [
                'ok' => false,
                'message' => 'Transponder not found',
            ])->withStatus(404);
        }

        // Dispatches the right sub-procedure based on the provided action type
        switch ($body['type']) {
            case ENTRANCE_TYPE:
                return $this->storeEntrance($response, $stationId, $transponder);
            case EXIT_TYPE:
                return $this->storeExit($response, $stationId);
            default:
                break;
        }

        return $this->renderJson($response, [
            'ok' => false,
            'message' => 'Invalid action type',
        ])->withStatus(400);
    }

    /**
     * Stores an entrance record in the pass-throughs table
     *
     * @param ResponseInterface $response Base response object
     * @param int $stationId Station ID
     * @param array $transponder Data about the transponder involved in the pass-through
     * @return ResponseInterface
     */
    private function storeEntrance(ResponseInterface $response, int $stationId, array $transponder): ResponseInterface
    {
        $serialNumber = $transponder['serial_number'];
        $customerId = $transponder['customer_id'];

        try {
            // Even if we are recording an entrance, we may have pending segments that must be closed
            // This case may happen when an entrance log is followed by another entrance log
            $this->closeSegment($stationId);

            // We are creating a new segment which will be closed once an exit action is triggered
            $passthrough = PassthroughModel::create([
                'transponder_sn' => $serialNumber,
                'customer_id' => $customerId,
                'start_station_id' => $stationId,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return $this->renderJson($response, [
                'ok' => true,
                'passthrough' => $passthrough,
            ]);
        } catch (\Exception $e) {
            // NOTE: In a real world scenario, I would write the error to a log file
            // Additionally, the response would include a generic error message
            return $this->renderJson($response, [
                'ok' => false,
                'message' => $e->getMessage(),
            ])->withStatus(500);
        }
    }

    /**
     * Stores an exit record in the pass-throughs table
     *
     * @param ResponseInterface $response Base response object
     * @param int $stationId Station ID
     * @param array $transponder Data about the transponder involved in the pass-through
     * @return ResponseInterface
     */
    private function storeExit(ResponseInterface $response, int $stationId)
    {
        try {
            $closedSegment = $this->closeSegment($stationId);

            // Each exit log must result in a segment being closed
            if (!$closedSegment['closed']) {
                return $this->renderJson($response, [
                    'ok' => false,
                    'message' => 'No segment to close',
                ])->withStatus(400);
            }
        } catch (\Exception $e) {
            return $this->renderJson($response, [
                'ok' => false,
                'message' => $e->getMessage(),
            ])->withStatus(500);
        }

        return $this->renderJson($response, [
            'ok' => true,
            'cost' => $closedSegment['cost']
        ]);
    }

    /**
     * Closes a previous opened segment setting the segment's end station ID to the provided station ID.
     * In addition to setting the reference to the end station, the function also computes and stores
     * the cost associated to the segment we just closed.
     *
     * @param int $stationId Station ID
     * @return array Indicates whether a segment has been closed and the cost associated to it
     */
    private function closeSegment(int $stationId): array
    {
        // Attempts to close a segment
        $model = new Model();
        $query = $model->getPDO()->prepare('UPDATE passthroughs SET end_station_id = ? WHERE end_station_id IS NULL');
        $query->execute([$stationId]);

        $closedSegments = $query->rowCount();
        $response = ['closed' => $closedSegments > 0];

        // If we were able to close a segment, then we are ready to compute its cost
        if ($closedSegments) {
            $passthrough = PassthroughModel::fetchMostRecent();
            $query = $model->getPDO()->prepare('UPDATE passthroughs SET cost = ? WHERE id = ? IS NULL');

            $cost = $this->computeSegmentCost($passthrough['start_station_id'], $passthrough['end_station_id']);
            $query->execute([$cost, $passthrough['id']]);

            $response['cost'] = $cost;
            return $response;
        }

        return $response;
    }

    /**
     * Computes the cost of a segment
     *
     * @param int $startStationId Start station ID
     * @param int $endStationId End station ID
     * @return float
     */
    private function computeSegmentCost(int $startStationId, int $endStationId): float
    {
        // Let's assume the configuration option is always available
        $pricePerDistanceUnit = ConfigurationOptionModel::findOne('pricePerDistanceUnit');

        // NOTE: I've omitted the cache implementation for simplicity
        // But you can imagine a look up statement based on the IDs of the start station and end station
        $startStation = StationModel::findOne($startStationId);
        $endStation = StationModel::findOne($endStationId);

        $startStationCoords = [(float) $startStation['lat'], (float) $startStation['lng']];
        $endStationCoords = [(float) $endStation['lat'], (float) $endStation['lng']];
        $distance = Haversine::compute($startStationCoords, $endStationCoords);

        return $distance * (float) $pricePerDistanceUnit['value'];
    }
}
