-- wf_department_users definition
CREATE TABLE `wf_department_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `job_level` enum('STAFF', 'MANAGER', 'HEAD') NOT NULL,
  `company_id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE = InnoDB AUTO_INCREMENT = 5 DEFAULT CHARSET = utf8mb4;

-- wf_asset_coordinator_users definition
CREATE TABLE `wf_asset_coordinator_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `asset_category_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE = InnoDB AUTO_INCREMENT = 2 DEFAULT CHARSET = utf8mb4;

-- wf_approver_groups definition
CREATE TABLE `wf_approver_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `company_id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE = InnoDB AUTO_INCREMENT = 5 DEFAULT CHARSET = utf8mb4;

-- wf_approver_group_users definition
CREATE TABLE `wf_approver_group_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `approver_group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `wf_approver_group_users_FK` (`approver_group_id`),
  CONSTRAINT `wf_approver_group_users_FK` FOREIGN KEY (`approver_group_id`) REFERENCES `wf_approver_groups` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 5 DEFAULT CHARSET = utf8mb4;

-- wf_flows definition
CREATE TABLE `wf_flows` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('PR', 'PO') NOT NULL,
  `company_id` int(11) NOT NULL,
  `is_active` int(11) NOT NULL DEFAULT 0,
  `label` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE = InnoDB AUTO_INCREMENT = 3 DEFAULT CHARSET = utf8mb4;

-- wf_flow_steps definition
CREATE TABLE `wf_flow_steps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order` int(11) NOT NULL,
  `flow_id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `condition` varchar(1000) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `wf_flow_steps_FK` (`flow_id`),
  CONSTRAINT `wf_flow_steps_FK` FOREIGN KEY (`flow_id`) REFERENCES `wf_flows` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 11 DEFAULT CHARSET = utf8mb4;

-- wf_flow_step_approvers definition
CREATE TABLE `wf_flow_step_approvers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `flow_step_id` int(11) NOT NULL,
  `type` enum('USER', 'GROUP', 'SYSTEM_GROUP') NOT NULL,
  `data` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `wf_flow_step_approvers_FK` (`flow_step_id`),
  CONSTRAINT `wf_flow_step_approvers_FK` FOREIGN KEY (`flow_step_id`) REFERENCES `wf_flow_steps` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 11 DEFAULT CHARSET = utf8mb4;

-- wf_approvals definition
CREATE TABLE `wf_approvals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `flow_id` int(11) NOT NULL,
  `status` enum('ON_PROGRESS', 'APPROVED', 'REJECTED') NOT NULL,
  `flow_step_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `parameters` varchar(1000) DEFAULT NULL,
  `company_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `wf_approvals_FK` (`flow_id`),
  KEY `wf_approvals_FK_1` (`flow_step_id`),
  CONSTRAINT `wf_approvals_FK` FOREIGN KEY (`flow_id`) REFERENCES `wf_flows` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `wf_approvals_FK_1` FOREIGN KEY (`flow_step_id`) REFERENCES `wf_flow_steps` (`id`)
) ENGINE = InnoDB AUTO_INCREMENT = 61 DEFAULT CHARSET = utf8mb4;

-- wf_approval_active_users definition
CREATE TABLE `wf_approval_active_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `approval_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `wf_approval_active_users_FK` (`approval_id`),
  CONSTRAINT `wf_approval_active_users_FK` FOREIGN KEY (`approval_id`) REFERENCES `wf_approvals` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 289 DEFAULT CHARSET = utf8mb4;

-- wf_approval_histories definition
CREATE TABLE `wf_approval_histories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `approval_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `flow_step_id` int(11) DEFAULT NULL,
  `title` varchar(100) DEFAULT NULL,
  `flag` varchar(100) DEFAULT NULL,
  `notes` varchar(100) DEFAULT NULL,
  `file` varchar(100) DEFAULT NULL,
  `date_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `wf_approval_histories_FK` (`approval_id`),
  KEY `wf_approval_histories_wf_flow_steps_FK` (`flow_step_id`),
  CONSTRAINT `wf_approval_histories_FK` FOREIGN KEY (`approval_id`) REFERENCES `wf_approvals` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `wf_approval_histories_wf_flow_steps_FK` FOREIGN KEY (`flow_step_id`) REFERENCES `wf_flow_steps` (`id`) ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 6612 DEFAULT CHARSET = utf8mb4;