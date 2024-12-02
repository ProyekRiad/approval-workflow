-- wf2_approvals definition
CREATE TABLE `wf2_approvals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `flow_id` int(11) NOT NULL,
  `status` varchar(100) NOT NULL,
  `approval_step_id` int(11) DEFAULT NULL COMMENT 'Kebutuhan untuk cache step terakhir',
  `user_id` int(11) DEFAULT NULL,
  `parameters` text DEFAULT NULL,
  `company_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE = InnoDB AUTO_INCREMENT = 1 DEFAULT CHARSET = utf8mb4;

-- wf2_approval_steps definition
CREATE TABLE `wf2_approval_steps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `approval_id` int(11) NOT NULL,
  `flow_id` int(11) DEFAULT NULL,
  `flow_item_id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `level` int(11) DEFAULT NULL,
  `start_fee` double DEFAULT NULL,
  `end_fee` double DEFAULT NULL,
  `sla` int(11) DEFAULT NULL,
  `category` varchar(1000) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `wf2_approval_steps_wf2_approvals_FK` (`approval_id`),
  CONSTRAINT `wf2_approval_steps_wf2_approvals_FK` FOREIGN KEY (`approval_id`) REFERENCES `wf2_approvals` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 1 DEFAULT CHARSET = utf8mb4;

-- wf2_approval_histories definition
CREATE TABLE `wf2_approval_histories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `approval_id` int(11) NOT NULL,
  `approval_step_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(1000) DEFAULT NULL,
  `flag` varchar(100) DEFAULT NULL,
  `notes` varchar(100) DEFAULT NULL,
  `file` varchar(1000) DEFAULT NULL,
  `date_time` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE = InnoDB AUTO_INCREMENT = 1 DEFAULT CHARSET = utf8mb4;