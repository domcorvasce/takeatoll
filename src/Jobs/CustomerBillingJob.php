<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Model;

class CustomerBillingJob
{
    /**
     * Computes the amount due of customers for a certain timeframe
     *
     * @param string $startDate The start date of the billing period
     * @param string $endDate The end date of the billing period
     * @return array The amount due of each customer for the whole billing period
     */
    public function execute(string $startDate, string $endDate): array
    {
        $query = <<<query
            SELECT customers.id, SUM(passthroughs.cost) AS amountdue
            FROM customers
            RIGHT JOIN passthroughs ON passthroughs.customer_id = customers.id
            WHERE passthroughs.updated_at BETWEEN ? AND ?
            GROUP BY customers.id
        query;

        $model = new Model();
        $statement = $model->getPDO()->prepare($query);
        $statement->execute([$startDate, $endDate]);

        // In a real world scenario, this data would be passed to another procedure
        // which would take care of charging the customers for the amount due
        return $statement->fetchAll();
    }
}
