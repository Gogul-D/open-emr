--
-- Table structure for table `Order_and_Measures_TB_form`
--

CREATE TABLE IF NOT EXISTS `Order_and_Measures_TB_form` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `date` datetime DEFAULT NULL,
  `pid` bigint(20) DEFAULT NULL,
  `user` varchar(255) DEFAULT NULL,
  `groupname` varchar(255) DEFAULT NULL,
  `authorized` tinyint(4) DEFAULT NULL,
  `activity` tinyint(4) DEFAULT NULL,

/* Section 01 (To-whom --> Client) */
  'client_name' varchar(255) DEFAULT NULL,          /* To-whom's full Name */
  'client_address' varchar(255) DEFAULT NULL,       /* To-whom's address */
  'client_phone_num' varchar(15) DEFAULT NULL,      /* To-whom's phone number */

/* Section 02 (Health authority) */
  'health_auth_sig' varchar(1) DEFAULT NULL,      /* Health authority's signature */
  'health_auth_name' varchar(1) DEFAULT NULL,     /* Health authority printed name */
  'sig_date' char(2) DEFAULT NULL,                /* Health authority's signature date (ex: 31) */
  'sig_month' char(2) DEFAULT NULL,               /* Health authority's signature month (ex: 'July') */
  'sig_year' char(2)  DEFAULT NULL,               /* Health authority's signatures year (ex: 20 _(24)_ */
  'authority_city' varchar(200) DEFAULT NULL,     /* Health authority's city/county */
  'auth_dir_region' varchar(200) DEFAULT NULL,    /* Authority or Director Region */

  /* Section 03 (Client's Acknowledgement) */
  'client_sig' varchar(1) DEFAULT NULL,           /* client's acknowledgment signature */
  'client_sig_date' datetime DEFAULT NULL,        /* client's signature date */
  'witness_name' varchar(200) DEFAULT NULL,       /* witness printed name */
  'witness_date' char(8) DEFAULT NULL,            /* witness date */

  /* Section 04 (Instructions for Client) */
  'client_print_name'             varchar(255) DEFAULT NULL,     /* client's printed name */
  'client_print_name_date'        datetime DEFAULT NULL,         /* client name date */
  'physician_print_name'          varchar(255) DEFAULT NULL,     /* physician printed name */
  'sec_01_client_initials'        varchar(2) DEFAULT NULL,       /* clinent's initials for agreeing to keep appointment to self */
  'sec_02_client_initials'        varchar(2) DEFAULT NULL,       /* clinent's initials for agreeing to take medicine treatment */

  'sec_03_client_location'          varchar(255) DEFAULT NULL,   /* clinent's initials for agreeing to take DOT */
  'sec_03_client_initials'          varchar(2) DEFAULT NULL,     /* location to take DOT */
  'sec_03_a_client_initials'        varchar(2) DEFAULT NULL,     /* clinent's initials for acknowledge taking DOT will help cure TB */

  'sec_04_client_initials'          varchar(2) DEFAULT NULL,     /* clinent's initials for agreeing to not return to work/school till notified otherwise */
  'sec_05_client_initials'          varchar(2) DEFAULT NULL,     /* clinent's initials for agreeing to not allow anyone other than the house residents to enter till notified otherwise */

  'sec_06_a_client_initials'        varchar(2) DEFAULT NULL,     /* clinent's initials for agreeing to not leave their home or till notified otherwise */
  'sec_06_a_client_checkbox_01'     char(1) DEFAULT NULL,        /* checkbox to confirm acknowledgement of possible TB spreading */
  'sec_06_a_client_chb01_initials'  varchar(2) DEFAULT NULL,     /* clinent's initials for agreeing of possible exposure */
  'sec_06_a_physician_sig'          char(1) DEFAULT NULL,        /* physician's signature for acknowledgment of report */
  'sec_06_a_checkbox_01_date'       datetime DEFAULT NULL,       /* checkbox 01 date (filled out) */
  'sec_06_b_client_checkbox_02'     char(1) DEFAULT NULL,        /* checkbox to confirm agreement of returning to work/school */
  'sec_06_b_client_chb02_initials'  varchar(2) DEFAULT NULL,     /* clinent's initials for agreeing to return to work/school */
  'sec_06_b_physician_sig'          char(1) DEFAULT NULL,        /* physician's signature for acknowledgment of report */
  'sec_06_b_checkbox_02_date'       datetime DEFAULT NULL,       /* checbox 02 date (filled out) */

  'sec_07_special_orders'           text,                        /* free-txt for any special orders */
  'sec_07_client_initials'          varchar(2) DEFAULT NULL,     /* client's initials for acknowledeging the special orders requested */
   PRIMARY KEY (`id`)
) ENGINE=InnoDB;
