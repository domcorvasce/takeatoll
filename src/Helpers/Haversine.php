<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Implements the Haversine formula for computing the distance between two points.
 * See https://en.wikipedia.org/wiki/Haversine_formula
 */
class Haversine
{
    /**
     * Computes the Haversine formula between two points
     *
     * @param array $pointA
     * @param array $pointB
     * @return float The distance in kilometers between the two points
     */
    public static function compute(array $pointA, array $pointB): float
    {
        $earthRadius = 6371;

        $latFrom = deg2rad($pointA[0]);
        $lngFrom = deg2rad($pointA[1]);

        $latTo = deg2rad($pointB[0]);
        $lngTo = deg2rad($pointB[1]);

        $latDelta = $latTo - $latFrom;
        $lngDelta = $lngTo - $lngFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) + cos($latFrom) * cos($latTo) * pow(sin($lngDelta / 2), 2)));
        $distance = $angle * $earthRadius;

        return round($distance, 4);
    }
}
