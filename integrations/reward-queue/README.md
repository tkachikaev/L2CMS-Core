# KaevCMS GameServer reward queue

KaevCMS writes selected web-inventory rewards to one neutral table named `kaev_reward_queue` in the selected GameServer database.

KaevCMS does **not** write to `items`, allocate `object_id`, patch GameServer sources, run a heartbeat or require a protocol version. After a row is written successfully, processing belongs to the GameServer administrator.

## Installation

Run `install.sql` once in every GameServer database that should accept rewards.


The CMS only requires the documented columns. Additional columns and indexes are allowed when extra columns are nullable, generated or have defaults. Future compatible changes must be additive so an administrator-owned consumer is not broken by a KaevCMS update.

## Queue meaning

One selected reward creates one row. Rows from one player action share the same `request_uuid` and have different `line_number` values.

Initial status is `pending`. An administrator-owned consumer may use any workflow, but these values are recommended:

- `pending` — waiting for a consumer;
- `processing` — claimed by a consumer;
- `delivered` — the consumer confirmed item delivery;
- `failed` — the consumer confirmed a failure.

KaevCMS does not poll or reinterpret these statuses. Its responsibility ends after it verifies that the expected rows exist in the queue. Consumers must keep the immutable request, account, character and item fields unchanged and should update the status instead of deleting a row immediately.

## Processing options

- a plugin or script inside GameServer;
- a stored procedure or scheduled database event;
- an external service;
- manual processing by an administrator.

`consumer-template.sql` is deliberately incomplete. There is no safe universal SQL statement for inserting into every Lineage II inventory schema. The administrator must implement object ID allocation and item fields according to the selected distribution.

`pending.sql`, `mark-delivered.example.sql` and `mark-failed.example.sql` are operational examples.
