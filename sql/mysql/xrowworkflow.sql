DROP TABLE IF EXISTS xrowworkflow;
CREATE TABLE xrowworkflow (
  contentobject_id int(11) default NULL,
  start int(11) NOT NULL default '0',
  end int(11) NOT NULL default '0',
  PRIMARY KEY  (contentobject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;