CREATE TABLE IF NOT EXISTS `soap_custom_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_name` varchar(255) NOT NULL,
  `context` enum('subjective','objective','assessment','plan') NOT NULL,
  `category` varchar(100) NOT NULL,
  `template_content` text NOT NULL,
  `date_created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `context_idx` (`context`),
  KEY `category_idx` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;