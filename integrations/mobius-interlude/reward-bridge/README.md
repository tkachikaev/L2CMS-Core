# Kaev Reward Bridge — L2J Mobius CT0 Interlude

Bridge protocol: `mobius_reward_bridge_v2`.

## Why it is required

The running Mobius GameServer allocates `object_id` values in its in-memory `IdManager`. A CMS process cannot safely reserve an ID with SQL while GameServer is running. The bridge therefore processes the queue inside GameServer and uses the same `IdManager` as normal game logic.

Mobius also restores a character inventory before it persists `characters.online = 1`. A database-only online check has a race: login can start while a reward is being committed and load an inventory snapshot without the new item. Protocol v2 closes that race with one shared per-character lock used by both `CharacterSelect` and the bridge.

## Installation

1. Stop GameServer.
2. Back up the game database and Mobius source tree.
3. Apply `install.sql` to the GameServer database used by the selected KaevCMS GameServer profile.
4. From the Mobius repository root, verify that the current file matches the bundled baseline:

   ```powershell
   (Get-FileHash '.\L2J_Mobius_CT_0_Interlude\java\org\l2jmobius\gameserver\network\clientpackets\CharacterSelect.java' -Algorithm SHA256).Hash.ToLower()
   ```

   Expected SHA-256 is stored in `CharacterSelect.official.sha256`. Do not apply the patch blindly if the hash differs; port the small lock block manually to the actual source version.
5. Apply `CharacterSelect.patch` from the Mobius repository root:

   ```powershell
   git apply --check 'C:\path\to\CharacterSelect.patch'
   git apply 'C:\path\to\CharacterSelect.patch'
   ```

6. Copy `KaevRewardDeliveryLock.java` to:

   `L2J_Mobius_CT_0_Interlude/java/org/l2jmobius/gameserver/integration/KaevRewardDeliveryLock.java`
7. Create `L2J_Mobius_CT_0_Interlude/dist/game/data/scripts/custom/KaevRewardBridge/` and copy `KaevRewardBridge.java` there.
8. Build and start GameServer normally.
9. Wait up to 30 seconds. KaevCMS enables transfers only after it sees a compatible, recent protocol-v2 heartbeat.

Reapplying `install.sql` is safe for the protocol-v2 schema because every table uses `CREATE TABLE IF NOT EXISTS`.

## What the login patch does

The patch locks only the selected character while Mobius runs `client.load(...)` and persists the online flag. The bridge obtains the same lock before its final owner/offline checks and item transaction.

- If login starts first, the bridge waits and then rejects delivery because the character is online.
- If delivery starts first, login waits until the item transaction commits and then loads the updated inventory.
- Other characters are not blocked.

The patch does not change authentication, packet handling, character data, item rules or normal inventory behavior.

## Supported in protocol v2

- offline characters only;
- simple `item_id + amount` rewards;
- stackable and non-stackable permanent items;
- maximum 1,000 created item objects per operation;
- no enchantment, augmentation, attributes or temporary items;
- transactional insert into `items`;
- idempotency by KaevCMS operation UUID and payload hash.

The bridge checks `characters.online`, ownership and the running GameServer's in-memory player registry while the shared login lock is held. It uses the same permanent-item defaults as Mobius (`INVENTORY`, enchant 0, custom types 0, mana -1, time -1).

## Failure rules

Confirmed validation errors mark the bridge operation as `failed`; KaevCMS may then return the reward to the web inventory.

The operation is first claimed as `processing`. Item rows and `delivered` are then committed together. If the commit result is lost or another runtime/database error occurs after object IDs were allocated, the operation becomes `uncertain`. Those IDs intentionally remain reserved in `IdManager` until GameServer restarts, and KaevCMS keeps the rewards blocked for manual review instead of risking a duplicate issue.

A `processing` operation older than two minutes is also converted to `uncertain`. Review GameServer logs and the `items` table before correcting such an operation.
