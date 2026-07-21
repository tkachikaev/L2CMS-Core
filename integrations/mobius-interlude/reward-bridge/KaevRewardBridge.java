/*
 * KaevCMS Reward Bridge for L2J Mobius CT0 Interlude.
 *
 * This script deliberately creates object IDs through the running GameServer's
 * IdManager. Do not replace this bridge with direct CMS inserts into `items`.
 */
package custom.KaevRewardBridge;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.util.ArrayList;
import java.util.List;
import java.util.concurrent.locks.ReentrantLock;
import java.util.logging.Level;
import java.util.logging.Logger;

import org.l2jmobius.commons.database.DatabaseFactory;
import org.l2jmobius.commons.threads.ThreadPool;
import org.l2jmobius.gameserver.data.xml.ItemData;
import org.l2jmobius.gameserver.integration.KaevRewardDeliveryLock;
import org.l2jmobius.gameserver.managers.IdManager;
import org.l2jmobius.gameserver.model.World;
import org.l2jmobius.gameserver.model.item.ItemTemplate;
import org.l2jmobius.gameserver.model.script.Script;

public class KaevRewardBridge extends Script
{
    private static final Logger LOGGER = Logger.getLogger(KaevRewardBridge.class.getName());
    private static final String BRIDGE_KEY = "mobius_reward_bridge_v2";
    private static final int PROTOCOL_VERSION = 2;
    private static final long POLL_INTERVAL_MS = 5000L;
    private static final long HEARTBEAT_INTERVAL_MS = 30000L;
    private static final int MAX_OPERATIONS_PER_CYCLE = 10;
    private static final int MAX_OBJECTS_PER_OPERATION = 1000;

    private long _lastHeartbeatAt;

    private KaevRewardBridge()
    {
        heartbeat();
        ThreadPool.scheduleAtFixedRate(this::runCycle, POLL_INTERVAL_MS, POLL_INTERVAL_MS);
        LOGGER.info("KaevRewardBridge: Started protocol v" + PROTOCOL_VERSION + ".");
    }

    private void runCycle()
    {
        if ((System.currentTimeMillis() - _lastHeartbeatAt) >= HEARTBEAT_INTERVAL_MS)
        {
            heartbeat();
        }

        for (int i = 0; i < MAX_OPERATIONS_PER_CYCLE; i++)
        {
            if (!processNext())
            {
                break;
            }
        }
    }

    private void heartbeat()
    {
        final String heartbeatSql = "INSERT INTO kaev_reward_bridge_state "
            + "(bridge_key, protocol_version, last_heartbeat_at) VALUES (?, ?, UTC_TIMESTAMP()) "
            + "ON DUPLICATE KEY UPDATE protocol_version = VALUES(protocol_version), "
            + "last_heartbeat_at = VALUES(last_heartbeat_at)";
        final String staleSql = "UPDATE kaev_reward_operations SET status = 'uncertain', "
            + "failure_code = 'reward_bridge_processing_stale', updated_at = UTC_TIMESTAMP() "
            + "WHERE status = 'processing' AND updated_at < (UTC_TIMESTAMP() - INTERVAL 2 MINUTE)";

        try (Connection connection = DatabaseFactory.getConnection();
            PreparedStatement heartbeat = connection.prepareStatement(heartbeatSql);
            PreparedStatement stale = connection.prepareStatement(staleSql))
        {
            heartbeat.setString(1, BRIDGE_KEY);
            heartbeat.setInt(2, PROTOCOL_VERSION);
            heartbeat.executeUpdate();
            stale.executeUpdate();
            _lastHeartbeatAt = System.currentTimeMillis();
        }
        catch (SQLException exception)
        {
            LOGGER.log(Level.WARNING, "KaevRewardBridge: Heartbeat failed. Is install.sql applied?", exception);
        }
    }

    private boolean processNext()
    {
        final Operation operation;

        try (Connection connection = DatabaseFactory.getConnection())
        {
            connection.setAutoCommit(false);

            try
            {
                operation = lockNextOperation(connection);
                if (operation == null)
                {
                    connection.commit();
                    return false;
                }

                final String characterFailure = validateCharacter(connection, operation);
                if (characterFailure != null)
                {
                    markFailed(connection, operation.uuid, characterFailure, "pending");
                    connection.commit();
                    return true;
                }

                try
                {
                    validateObjectCount(loadAndValidateItems(connection, operation.uuid));
                }
                catch (ConfirmedFailureException exception)
                {
                    markFailed(connection, operation.uuid, exception.failureCode, "pending");
                    connection.commit();
                    return true;
                }

                markProcessing(connection, operation.uuid);
                connection.commit();
            }
            catch (SQLException | RuntimeException exception)
            {
                rollbackQuietly(connection);
                LOGGER.log(Level.WARNING, "KaevRewardBridge: Pending operation could not be claimed.", exception);
                return true;
            }
            finally
            {
                restoreAutoCommit(connection);
            }
        }
        catch (SQLException | RuntimeException exception)
        {
            LOGGER.log(Level.WARNING, "KaevRewardBridge: Could not obtain a database connection.", exception);
            return false;
        }

        deliverClaimed(operation);
        return true;
    }

    private void deliverClaimed(Operation operation)
    {
        final ReentrantLock rewardDeliveryLock = KaevRewardDeliveryLock.getLock(operation.characterId);
        rewardDeliveryLock.lock();

        try
        {
            deliverClaimedWhileLoginBlocked(operation);
        }
        finally
        {
            rewardDeliveryLock.unlock();
        }
    }

    private void deliverClaimedWhileLoginBlocked(Operation operation)
    {
        final List<Integer> allocatedObjectIds = new ArrayList<>();

        try (Connection connection = DatabaseFactory.getConnection())
        {
            connection.setAutoCommit(false);

            try
            {
                if (!lockProcessingOperation(connection, operation.uuid))
                {
                    connection.commit();
                    return;
                }

                final List<RewardItem> items;
                try
                {
                    items = loadAndValidateItems(connection, operation.uuid);
                    validateObjectCount(items);
                }
                catch (ConfirmedFailureException exception)
                {
                    markFailed(connection, operation.uuid, exception.failureCode, "processing");
                    connection.commit();
                    return;
                }

                final String characterFailure = validateCharacter(connection, operation);
                if (characterFailure != null)
                {
                    markFailed(connection, operation.uuid, characterFailure, "processing");
                    connection.commit();
                    return;
                }

                insertItems(connection, operation, items, allocatedObjectIds);
                markDelivered(connection, operation.uuid);

                if (World.getInstance().getPlayer(operation.characterId) != null)
                {
                    connection.rollback();
                    markFailedSafely(operation.uuid, "character_online");
                    return;
                }

                connection.commit();
            }
            catch (SQLException | RuntimeException exception)
            {
                rollbackQuietly(connection);
                markUncertainSafely(operation.uuid);
                LOGGER.log(
                    Level.WARNING,
                    "KaevRewardBridge: Delivery outcome is uncertain; allocated object IDs remain reserved until restart (count="
                        + allocatedObjectIds.size() + ").",
                    exception
                );
            }
            finally
            {
                restoreAutoCommit(connection);
            }
        }
        catch (SQLException | RuntimeException exception)
        {
            markUncertainSafely(operation.uuid);
            LOGGER.log(Level.WARNING, "KaevRewardBridge: Could not open the delivery transaction.", exception);
        }
    }

    private Operation lockNextOperation(Connection connection) throws SQLException
    {
        final String sql = "SELECT operation_uuid, account_login, character_id "
            + "FROM kaev_reward_operations WHERE status = 'pending' "
            + "ORDER BY created_at, operation_uuid LIMIT 1 FOR UPDATE";

        try (PreparedStatement statement = connection.prepareStatement(sql);
            ResultSet result = statement.executeQuery())
        {
            if (!result.next())
            {
                return null;
            }

            return new Operation(
                result.getString("operation_uuid"),
                result.getString("account_login"),
                result.getInt("character_id")
            );
        }
    }

    private boolean lockProcessingOperation(Connection connection, String operationUuid) throws SQLException
    {
        final String sql = "SELECT operation_uuid FROM kaev_reward_operations "
            + "WHERE operation_uuid = ? AND status = 'processing' FOR UPDATE";

        try (PreparedStatement statement = connection.prepareStatement(sql))
        {
            statement.setString(1, operationUuid);

            try (ResultSet result = statement.executeQuery())
            {
                return result.next();
            }
        }
    }

    private String validateCharacter(Connection connection, Operation operation) throws SQLException
    {
        if (World.getInstance().getPlayer(operation.characterId) != null)
        {
            return "character_online";
        }

        final String sql = "SELECT online FROM characters WHERE charId = ? AND account_name = ? FOR UPDATE";

        try (PreparedStatement statement = connection.prepareStatement(sql))
        {
            statement.setInt(1, operation.characterId);
            statement.setString(2, operation.accountLogin);

            try (ResultSet result = statement.executeQuery())
            {
                if (!result.next())
                {
                    return "character_not_owned";
                }

                if ((result.getInt("online") != 0) || (World.getInstance().getPlayer(operation.characterId) != null))
                {
                    return "character_online";
                }

                return null;
            }
        }
    }

    private List<RewardItem> loadAndValidateItems(Connection connection, String operationUuid)
        throws SQLException, ConfirmedFailureException
    {
        final String sql = "SELECT item_id, amount FROM kaev_reward_operation_items "
            + "WHERE operation_uuid = ? ORDER BY line_number";
        final List<RewardItem> items = new ArrayList<>();

        try (PreparedStatement statement = connection.prepareStatement(sql))
        {
            statement.setString(1, operationUuid);

            try (ResultSet result = statement.executeQuery())
            {
                while (result.next())
                {
                    final int itemId = result.getInt("item_id");
                    final long rawAmount = result.getLong("amount");
                    final ItemTemplate template = ItemData.getInstance().getTemplate(itemId);

                    if ((template == null) || (rawAmount <= 0) || (rawAmount > Integer.MAX_VALUE))
                    {
                        throw new ConfirmedFailureException("invalid_reward_item");
                    }

                    if ((template.getDuration() >= 0) || (template.getTime() >= 0))
                    {
                        throw new ConfirmedFailureException("temporary_item_unsupported");
                    }

                    items.add(new RewardItem(itemId, (int) rawAmount, template.isStackable()));
                }
            }
        }

        if (items.isEmpty())
        {
            throw new ConfirmedFailureException("empty_reward_delivery");
        }

        return items;
    }

    private void validateObjectCount(List<RewardItem> items) throws ConfirmedFailureException
    {
        long objectCount = 0;
        for (RewardItem item : items)
        {
            objectCount += item.stackable ? 1 : item.amount;
            if (objectCount > MAX_OBJECTS_PER_OPERATION)
            {
                throw new ConfirmedFailureException("too_many_item_objects");
            }
        }
    }

    private void insertItems(
        Connection connection,
        Operation operation,
        List<RewardItem> items,
        List<Integer> allocatedObjectIds
    ) throws SQLException
    {
        final String sql = "INSERT INTO items "
            + "(owner_id, item_id, count, loc, loc_data, enchant_level, object_id, "
            + "custom_type1, custom_type2, mana_left, time) "
            + "VALUES (?, ?, ?, 'INVENTORY', 0, 0, ?, 0, 0, -1, -1)";

        try (PreparedStatement statement = connection.prepareStatement(sql))
        {
            for (RewardItem item : items)
            {
                final int rows = item.stackable ? 1 : item.amount;
                final int count = item.stackable ? item.amount : 1;

                for (int i = 0; i < rows; i++)
                {
                    final int objectId = IdManager.getInstance().getNextId();
                    allocatedObjectIds.add(objectId);

                    statement.setInt(1, operation.characterId);
                    statement.setInt(2, item.itemId);
                    statement.setInt(3, count);
                    statement.setInt(4, objectId);
                    statement.addBatch();
                }
            }

            statement.executeBatch();
        }
    }

    private void markProcessing(Connection connection, String operationUuid) throws SQLException
    {
        final String sql = "UPDATE kaev_reward_operations SET status = 'processing', failure_code = NULL, "
            + "started_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() "
            + "WHERE operation_uuid = ? AND status = 'pending'";
        updateOperationState(connection, sql, operationUuid);
    }

    private void markDelivered(Connection connection, String operationUuid) throws SQLException
    {
        final String sql = "UPDATE kaev_reward_operations SET status = 'delivered', failure_code = NULL, "
            + "completed_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() "
            + "WHERE operation_uuid = ? AND status = 'processing'";
        updateOperationState(connection, sql, operationUuid);
    }

    private void markFailed(
        Connection connection,
        String operationUuid,
        String failureCode,
        String expectedStatus
    ) throws SQLException
    {
        final String sql = "UPDATE kaev_reward_operations SET status = 'failed', failure_code = ?, "
            + "started_at = COALESCE(started_at, UTC_TIMESTAMP()), completed_at = UTC_TIMESTAMP(), "
            + "updated_at = UTC_TIMESTAMP() WHERE operation_uuid = ? AND status = ?";

        try (PreparedStatement statement = connection.prepareStatement(sql))
        {
            statement.setString(1, failureCode);
            statement.setString(2, operationUuid);
            statement.setString(3, expectedStatus);
            if (statement.executeUpdate() != 1)
            {
                throw new SQLException("Reward operation changed while it was locked.");
            }
        }
    }

    private void updateOperationState(
        Connection connection,
        String sql,
        String operationUuid
    ) throws SQLException
    {
        try (PreparedStatement statement = connection.prepareStatement(sql))
        {
            statement.setString(1, operationUuid);
            if (statement.executeUpdate() != 1)
            {
                throw new SQLException("Reward operation changed while it was locked.");
            }
        }
    }

    private void markFailedSafely(String operationUuid, String failureCode)
    {
        try (Connection connection = DatabaseFactory.getConnection())
        {
            markFailed(connection, operationUuid, failureCode, "processing");
        }
        catch (SQLException | RuntimeException exception)
        {
            markUncertainSafely(operationUuid);
            LOGGER.log(Level.WARNING, "KaevRewardBridge: Could not persist a confirmed failure.", exception);
        }
    }

    private void markUncertainSafely(String operationUuid)
    {
        final String sql = "UPDATE kaev_reward_operations SET status = 'uncertain', "
            + "failure_code = 'reward_bridge_outcome_uncertain', updated_at = UTC_TIMESTAMP() "
            + "WHERE operation_uuid = ? AND status = 'processing'";

        try (Connection connection = DatabaseFactory.getConnection();
            PreparedStatement statement = connection.prepareStatement(sql))
        {
            statement.setString(1, operationUuid);
            statement.executeUpdate();
        }
        catch (SQLException | RuntimeException exception)
        {
            LOGGER.log(Level.SEVERE, "KaevRewardBridge: Could not persist uncertain operation state.", exception);
        }
    }

    private void rollbackQuietly(Connection connection)
    {
        try
        {
            connection.rollback();
        }
        catch (SQLException rollbackException)
        {
            LOGGER.log(Level.SEVERE, "KaevRewardBridge: Transaction rollback failed.", rollbackException);
        }
    }

    private void restoreAutoCommit(Connection connection)
    {
        try
        {
            connection.setAutoCommit(true);
        }
        catch (SQLException ignored)
        {
        }
    }

    private static final class ConfirmedFailureException extends Exception
    {
        private static final long serialVersionUID = 1L;
        private final String failureCode;

        private ConfirmedFailureException(String failureCode)
        {
            this.failureCode = failureCode;
        }
    }

    private static final class Operation
    {
        private final String uuid;
        private final String accountLogin;
        private final int characterId;

        private Operation(String uuid, String accountLogin, int characterId)
        {
            this.uuid = uuid;
            this.accountLogin = accountLogin;
            this.characterId = characterId;
        }
    }

    private static final class RewardItem
    {
        private final int itemId;
        private final int amount;
        private final boolean stackable;

        private RewardItem(int itemId, int amount, boolean stackable)
        {
            this.itemId = itemId;
            this.amount = amount;
            this.stackable = stackable;
        }
    }

    public static void main(String[] args)
    {
        new KaevRewardBridge();
    }
}
