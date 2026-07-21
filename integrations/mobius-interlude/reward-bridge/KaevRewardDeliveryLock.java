/*
 * Shared per-character lock for KaevCMS reward delivery and character login.
 */
package org.l2jmobius.gameserver.integration;

import java.util.concurrent.ConcurrentHashMap;
import java.util.concurrent.locks.ReentrantLock;

public final class KaevRewardDeliveryLock
{
    private static final ConcurrentHashMap<Integer, ReentrantLock> LOCKS = new ConcurrentHashMap<>();

    private KaevRewardDeliveryLock()
    {
    }

    public static ReentrantLock getLock(int characterId)
    {
        if (characterId <= 0)
        {
            throw new IllegalArgumentException("Character ID must be positive.");
        }

        return LOCKS.computeIfAbsent(characterId, ignored -> new ReentrantLock());
    }
}
