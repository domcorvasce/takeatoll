# takeatoll

## Overview

> Electronic toll collection (ETC) is a wireless system to automatically collect the usage fee or toll charged to vehicles using toll roads, HOV lanes, toll bridges, and toll tunnels. It is a faster alternative which is replacing toll booths, where vehicles must stop and the driver manually pays the toll with cash or a card [...] [Read on Wikipedia](https://en.wikipedia.org/wiki/Electronic_toll_collection)

Suppose that all ETCs owned by a motorway owner use a REST API to store and fetch data. For the sake of simplicity, let's ignore authentication.

Everytime a vehicle reaches a station, the **transponder** &mdash; a device mounted on the vehicle &mdash; communicates its *serial number* to the ETC using wireless communication. The serial number identifies the transponder, the vehicle on which it is mounted, and the customer whom owns the vehicle.

We can imagine that &mdash; once the serial number is received &mdash; the ETC makes a call to the REST API to store the pass through that specific station. The HTTP request could look something like this:

```
POST /api/stations/{stationId}/logs

{
  "serialNumber": 31032022,
  "type": "entrance"
}
```

The API processes the request and &mdash; after the necessary checks &mdash; it writes a new record onto the database. At the end of each month, a *batch job* pulls the pass-throughs recorded in the last month for all customers, and computes how much money each customer owes.

## Database

The database is build around four main entities: customers, transponders, stations, and pass-throughs; these entities are connected through many-to-one relationships, hence why we opted for a *relational database* &mdash; which has a better support to *joins* compared to a document-based database.

We omitted the vehicles entity for simplicity.

### Configuration options

> 🤔 <b>Reflection time</b>: We can assume that &mdash; having to deal with 500 stations &dash; we are not going to have people manually set up a cost for each segment. It's more probable that system administrators are going to enforce a certain **rule** to compute the cost of a segment based on certain factors.

This table tracks which charge system we want to use, along with storing configuration options that can be used to compute the cost of a segment.

| Name  | Type        | Primary Key (?) | References | Constraints |
|-------|-------------|-----------------|------------|-------------|
| name  | varchar(64) | yes             |            | not null    |
| value | varchar(64) |                 |            | not null    |

### Customers

| Name       | Type         | Primary Key (?) | References | Constraints             |
|------------|--------------|-----------------|------------|-------------------------|
| id         | uint         | yes             |            | not null, autoincrement |
| first_name | varchar(100) |                 |            | not null                |
| last_name  | varchar(100) |                 |            | not null                |
| email      | varchar(255) |                 |            | unique                  |
| password   | text         |                 |            | not null                |
| created_at | timestamp    |                 |            | not null                |
| updated_at | timestamp    |                 |            |                         |
| deleted_at | timestamp    |                 |            |                         |

### Transponders

| Name          | Type      | Primary Key (?) | References     | Constraints |
|---------------|-----------|-----------------|----------------|-------------|
| serial_number | uint      | yes             |                |             |
| customer_id   | uint      |                 | `customers.id` | not null    |
| created_at    | timestamp |                 |                | not null    |
| deleted_at    | timestamp |                 |                |             |

### Stations

We need a **unique** constraint on the tuple `(lat, lng)`.

| Name       | Type         | Primary Key (?) | References | Constraints             |
|------------|--------------|-----------------|------------|-------------------------|
| id         | uint         | yes             |            | not null, autoincrement |
| name       | varchar(512) |                 |            | not null, unique        |
| lat        | float        |                 |            | not null                |
| lng        | float        |                 |            | not null                |
| created_at | timestamp    |                 |            | not null                |
| updated_at | timestamp    |                 |            |                         |
| deleted_at | timestamp    |                 |            |                         |

### Passthroughs

The table is designed to store a trip's segments in an organised way.

Each station can trigger one of two signals: `entrance`, or `exit`.

|              | Entrance | Exit    |
|--------------|----------|---------|
| **Entrance** | Valid    | Valid   |
| **Exit**     | Valid    | Invalid |

Suppose you have to go from Bari to Milan passing by Bologna. There are two sequences which can describe this trip:

1. Bari: entrance, Bologna: entrance, Milan: exit
2. Bari: entrance, Bologna: exit, Bologna: entrance, Milan: exit

> 🤔 <b>Reflection time</b>: This problem resembles the **valid parentheses problem** which involves checking that every opening parenthesis has a corresponding closing parenthesis in the correct order (e.g. `()(())` is fine, but `())(()` is not)). I didn't notice at first, because I mostly relied on my intuition to come with the algorithm below, but my solution resembles the classic stack-based solution to the valid parentheses problem.

The two sequences are equivalent, and we can convert both into a set of records by using the following algorithm:

1. On firing `entrance`, create a new record and set `start_station_id` to the current station ID. Additionally, if there is a record &mdash; apart from the one we just created &mdash; with a null `end_station_id`, set the `end_station_id` to the current station ID.

2. On firing `exit`, check if there is a record with a null `end_station_id`. If so, set the `end_station_id` to the current station ID. Otherwise throw an error because the signal is invalid.

Back to our sample trip, this algorithm would produce 2 records:

1. start station: Bari, end station: Bologna
2. start station: Bologna, end station: Milan

We decide to compute the cost of each pass-through ahead of time. When it comes time to set the reference to the end station, we use the [look up](#cache) the cost associated to the segment, and store the cost along with the pass-through record. Therefore, we can compute the amount due for each customer by running a simple aggregate query:

```sql
SELECT customers.id, SUM(passthroughs.cost)
FROM customers
RIGHT JOIN passthroughs ON passthroughs.customer_id = customers.id
GROUP BY customers.id
```

| Name             | Type      | Primary Key (?) | References                  | Constraints             |
|------------------|-----------|-----------------|-----------------------------|-------------------------|
| id               | uint      | yes             |                             | not null, autoincrement |
| transponder_sn   | uint      |                 | `tranponders.serial_number` | not null                |
| customer_id      | uint      |                 | `customers.id`              | not null                |
| start_station_id | uint      |                 | `stations.id`               | not null                |
| end_station_id   | uint      |                 | `stations.id`               | nullable                |
| cost             | float     |                 |                             | nullable                |
| created_at       | timestamp |                 |                             | not null                |
| updated_at       | timestamp |                 |                             |                         |

## Cache

We may decide to charge based on the **travelled distance**, or based on which specific pair of stations (**segment**) a customer passes through. Either way, we can maintain a lookup table to quickly retrieve the cost associated to a segment. If we decide to charge per travelled distance, the table will store the distance between each pair of stations.

Suppose we have 500 stations and we can reach any destination from each station. We can safely assume that we start station and end station won't match. Then we are talking about the 2-[permutation](https://www.probabilitycourse.com/chapter2/2_1_2_ordered_without_replacement.php) of a set of 500 elements. We have 249500 permutations, or segments. Suppose each segment can be represented with 96 bits: 32 bits for the start station ID, 32 bits for the end station ID, and 32 bits for the cost associated to the segment. Then we may need up to 249500&times;96 bits &mdash; or 3MB &mdash; to store the cost of all segments.

Given the small upper bound for the size of the lookup table and the high ratio of reads to writes, we may consider using an in-memory data store such as [Redis](https://redis.io/) or [memcached](https://memcached.org/).

We are going to populate the data store gradually, as the cost of segments are requested. Instead, every time an exit from a station must be recorded, a procedure will look up the cost of the segment in the cache. If there is a *cache miss*, then the procedure will take care of computing the cost and caching it.
