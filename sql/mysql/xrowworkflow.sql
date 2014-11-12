//UPDATE: ALTER TABLE `xrowworkflow` ADD COLUMN `action` VARCHAR(255) AFTER `type`;
//UPDATE: ALTER TABLE `xrowworkflow` ADD COLUMN `type` INT(1) AFTER `action`;
DROP TABLE IF EXISTS xrowworkflow;
CREATE TABLE xrowworkflow (
  `contentobject_id` int(11) default NULL,
  `start` int(11) NOT NULL default '0',
  `end` int(11) NOT NULL default '0',
  `action` varchar(255) default NULL,
  `type` int(1) NOT NULL default '0',
  PRIMARY KEY  (contentobject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;