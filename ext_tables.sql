CREATE TABLE tx_easysearch_words (
	uid int(11) unsigned NOT NULL auto_increment,
	word varchar(20) DEFAULT '' NOT NULL,
	PRIMARY KEY (uid),
	KEY word_id (word,uid)
) ENGINE=InnoDB;

CREATE TABLE tx_easysearch_rel (
	word_uid int(11) unsigned NOT NULL,
	amount int(11) unsigned NOT NULL,
	page_uid int(11) unsigned NOT NULL,
	KEY word_page (word_uid,page_uid)
) ENGINE=InnoDB;

CREATE TABLE tx_easysearch_exclude (
	uid int(11) unsigned NOT NULL auto_increment,
	word varchar(20) DEFAULT '' NOT NULL,
	KEY word_id (word,uid)
) ENGINE=InnoDB;