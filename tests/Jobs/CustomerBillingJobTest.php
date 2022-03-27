<?php

declare(strict_types=1);

use App\Jobs\CustomerBillingJob;
use App\Models\CustomerModel;
use App\Models\TransponderModel;
use App\Models\StationModel;
use App\Models\PassthroughModel;
use Faker\Factory;
use PHPUnit\Framework\TestCase;

class CustomerBillingJobTest extends TestCase
{
    // Stores the collection of fake customers
    private array $customers;

    // Stores the collection of fake stations
    private array $stations;

    // Stores the collection of fake transponders
    private array $transponders;

    /**
     * Creates sets of fake customers and stations to use to record pass-throughs
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->stations = [StationModel::fake(true), StationModel::fake(true)];
        $this->customers = [CustomerModel::fake(true), CustomerModel::fake(true)];
        $this->transponders = [
            TransponderModel::fake(true, ['customer_id' => $this->customers[0]['id']]),
            TransponderModel::fake(true, ['customer_id' => $this->customers[1]['id']]),
        ];
    }

    /**
     * Destroys the fake records used by the unit tests
     *
     * @return void
     */
    public function tearDown(): void
    {
        foreach ($this->transponders as $transponder)
        {
            TransponderModel::delete($transponder['serial_number']);
        }

        foreach ($this->customers as $customer)
        {
            CustomerModel::delete($customer['id']);
        }

        foreach ($this->stations as $station)
        {
            StationModel::delete($station['id']);
        }
    }

    public function testComputingAmountsDue(): void
    {
        $faker = Factory::create();

        // Create fake passthroughs
        $passthroughs = [];
        $expectedCost = 0.0;

        for ($i = 0; $i < 10; $i += 1)
        {
            $cost = $faker->randomFloat(2, 1, 10);
            $expectedCost += $cost;
            $passthroughs[] = PassthroughModel::fake(true, [
                'transponder_sn' => $this->transponders[0]['serial_number'],
                'customer_id' => $this->customers[0]['id'],
                'start_station_id' => $this->stations[0]['id'],
                'end_station_id' => $this->stations[1]['id'],
                // Even though the segment is always the same let's imagine we are dealing with
                // different stations, hence different a different cost per segment
                'cost' => $cost,
                'created_at' => '2022-03-27 16:33:20',
                'updated_at' => '2022-03-27 16:33:20',
            ]);
        }

        // Executes job to compute amount due for a specific customer
        $job = new CustomerBillingJob();
        $billings = $job->execute('2022-03-21', '2022-03-28');

        foreach ($billings as $billing)
        {
            if ($billing['id'] == $this->customers[0]['id']) {
                $this->assertSame($expectedCost, (float) $billing['amountdue']);
                break;
            }
        }

        // Removes fake records
        foreach ($passthroughs as $passthrough)
        {
            PassthroughModel::delete($passthrough['id']);
        }
    }
}
