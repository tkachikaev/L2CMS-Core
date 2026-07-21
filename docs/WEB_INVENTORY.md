# Web inventory

KaevCMS 0.25.2 stores rewards in the CMS database until a player selects a character on the same GameServer. The bundled Mobius CT0 Interlude driver can now deliver simple permanent items through Kaev Reward Bridge.

## Server isolation

Every grant, inventory item and delivery contains a `game_server_id`. A reward cannot be moved to another server. When only one server has rewards, the player interface hides the server switcher. With several servers, the inventory is separated into server tabs.

## Grant API

Modules and core services grant rewards through `App\Services\Rewards\RewardInventoryService`. A grant has a globally unique `grant_key`; repeating the same key returns the original grant instead of crediting items twice.

```php
$inventory->grant(
    user: $user,
    server: $server,
    grantKey: 'promo:activation:123',
    sourceType: 'promo_code',
    sourceReference: 'WELCOME2026',
    items: [
        new RewardGrantItem(itemId: 57, amount: 1_000_000, name: 'Adena'),
    ],
);
```

A source must build the key from its own immutable operation identifier, not from user input.

## Transfer lifecycle

1. The player selects one to fifty available reward rows.
2. KaevCMS verifies that every row belongs to the authenticated user and selected GameServer.
3. The selected character is resolved from game accounts linked to the same LoginServer and GameServer.
4. A CMS database transaction locks the user and inventory rows, creates one idempotent delivery and marks the rows `reserved`.
5. `ProcessRewardDelivery` is persisted on the Laravel `database` connection in the `rewards` queue.
6. The job checks the character and driver capability again, then places one operation in the bridge queue using the immutable operation UUID and SHA-256 payload hash.
7. `ConfirmRewardDelivery` checks the same bridge operation without submitting the items again.
8. A confirmed `delivered` result changes inventory rows to `delivered`. A confirmed `failed` result that guarantees no item was committed returns them to `available`.
9. A missing, stale or otherwise uncertain result changes the CMS operation to `review` and keeps the rows `reserved`.

The browser request token, CMS row locks, bridge UUID primary key and payload hash protect the flow from repeated clicks, queue retries and payload substitution.

## Why direct CMS inserts are disabled

Mobius allocates object IDs in the running GameServer's in-memory `IdManager`. `MAX(object_id) + 1`, an SQL lock or a CMS transaction cannot reserve an ID in that memory and can collide with normal game activity. KaevCMS therefore does not write directly to `items` while GameServer is running.

Kaev Reward Bridge runs inside GameServer, obtains every ID through the same `IdManager` and writes the item rows and terminal operation state in one game-database transaction. A commit error is treated as uncertain: allocated IDs are not released or reused, the operation is not submitted again automatically, and the CMS rewards remain reserved for review.

## Bridge installation

The integration package is located at:

```text
integrations/mobius-interlude/reward-bridge/
```

Installation summary:

1. Stop GameServer and back up its database and source tree.
2. Apply `install.sql` to the selected Mobius GameServer database.
3. Verify the bundled `CharacterSelect` SHA-256, apply `CharacterSelect.patch`, and copy `KaevRewardDeliveryLock.java` into the Mobius core source tree.
4. Copy `KaevRewardBridge.java` to `dist/game/data/scripts/custom/KaevRewardBridge/`.
5. Build and start GameServer.
6. Wait for the bridge heartbeat. KaevCMS enables transfer controls only when protocol v2 is installed and the heartbeat is fresh.

The login hook is mandatory for protocol v2. It serializes inventory loading and reward delivery for the same character, closing the race created by Mobius loading inventory before it writes the online flag. The detailed procedure, source hash and limits are in the integration README.

## Driver contract

`GameWorldDriver` exposes:

- `rewardDeliveryCapabilities()`;
- `deliverRewards()`;
- `rewardDeliveryStatus()`.

The Mobius driver reports the delivery mode `mobius_reward_bridge_v2` only when all required queue columns exist, protocol version 2 is advertised and the latest heartbeat is no older than two minutes. Missing tables, incompatible protocol and an offline bridge are separate fail-closed capability reasons.

Bridge states map as follows:

- `pending` and recent `processing` — keep waiting;
- `delivered` — confirm delivery;
- `failed` — confirmed failure, rewards may be returned;
- `uncertain`, missing operation, unknown status or stale `processing` — manual review, rewards stay reserved.

## Queue requirement

Reward jobs always use the durable Laravel database queue named `rewards`, independent of `QUEUE_CONNECTION`. The existing `kaevcms:queue-drain` Scheduler command discovers this queue automatically. Production must run Laravel Scheduler or a database queue worker; otherwise deliveries remain safely pending in KaevCMS.

## Supported bridge protocol v2

- offline characters only;
- simple `item_id + amount` rewards;
- stackable and non-stackable permanent items;
- at most 1,000 created item objects per operation;
- transactionally committed `items` rows;
- idempotency by operation UUID and payload hash.

Not supported:

- online delivery;
- enchantment;
- elemental attributes;
- augmentation;
- temporary or shadow items;
- partial withdrawal from one reward row;
- movement between GameServers.

## Administration

The read-only **Reward deliveries** journal is available to owners and administrators. It shows the player, GameServer, character, item snapshots, status and safe failure code. Editors cannot access it. Operations in `review` require checking the game database and bridge logs before any manual correction.
