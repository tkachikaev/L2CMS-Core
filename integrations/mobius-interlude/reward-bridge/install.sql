CREATE TABLE IF NOT EXISTS `kaev_reward_bridge_state` (
  `bridge_key` varchar(64) NOT NULL,
  `protocol_version` int unsigned NOT NULL,
  `last_heartbeat_at` datetime DEFAULT NULL,
  PRIMARY KEY (`bridge_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `kaev_reward_operations` (
  `operation_uuid` char(36) NOT NULL,
  `payload_hash` char(64) NOT NULL,
  `account_login` varchar(45) NOT NULL,
  `character_id` int unsigned NOT NULL,
  `character_name` varchar(35) NOT NULL,
  `status` enum('pending','processing','delivered','failed','uncertain') NOT NULL DEFAULT 'pending',
  `failure_code` varchar(64) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`operation_uuid`),
  KEY `kaev_reward_operations_queue_index` (`status`,`created_at`),
  KEY `kaev_reward_operations_character_index` (`character_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `kaev_reward_operation_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `operation_uuid` char(36) NOT NULL,
  `line_number` int unsigned NOT NULL,
  `item_id` int unsigned NOT NULL,
  `amount` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kaev_reward_operation_line_unique` (`operation_uuid`,`line_number`),
  CONSTRAINT `kaev_reward_operation_items_operation_fk`
    FOREIGN KEY (`operation_uuid`) REFERENCES `kaev_reward_operations` (`operation_uuid`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
