-- MMR Immunization Form - Database Table
-- Place this file at: interface/forms/mmr_immunization/table.sql
-- Run via: mysql -u root -p openemr < table.sql
--       OR: Admin > Forms > Forms Administration > Install

CREATE TABLE IF NOT EXISTS `form_mmr_immunization` (

    -- Standard OpenEMR form fields (required on every form)
    id              bigint(20)   NOT NULL auto_increment,
    date            datetime     DEFAULT NULL,
    pid             bigint(20)   DEFAULT NULL,
    user            varchar(255) DEFAULT NULL,
    groupname       varchar(255) DEFAULT NULL,
    authorized      tinyint(4)   DEFAULT NULL,
    activity        tinyint(4)   DEFAULT NULL,

    -- Vaccination Info
    vaccination_site        varchar(255) DEFAULT NULL,
    vaccination_date        date         DEFAULT NULL,
    vaccine_administrator   varchar(255) DEFAULT NULL,

    -- Medical and Social History (YES/NO radio fields)
    sick_today              char(3)  DEFAULT NULL,   -- 'YES' or 'NO'
    severe_allergy          char(3)  DEFAULT NULL,
    vaccines_last_4wks      char(3)  DEFAULT NULL,
    steroids_immuno         char(3)  DEFAULT NULL,
    blood_transfusion       char(3)  DEFAULT NULL,
    pregnant                char(3)  DEFAULT NULL,

    -- Vaccine Consent Section
    consent_patient_name    varchar(255) DEFAULT NULL,
    consent_date            date         DEFAULT NULL,
    consent_relationship    varchar(100) DEFAULT NULL,

    -- Texas Immunization Registry (ImmTrac2) Consent
    immtrac2_agree          varchar(50)  DEFAULT NULL,  -- 'YES', 'NO', 'Unassigned'
    immtrac2_date           date         DEFAULT NULL,
    immtrac2_relationship   varchar(100) DEFAULT NULL,

    -- Signature flags (actual images stored in onsite_signatures table)
    consent_sig             tinyint(1)   DEFAULT 0     COMMENT '1 = patient signed vaccine consent',
    immtrac2_sig            tinyint(1)   DEFAULT 0     COMMENT '1 = patient signed ImmTrac2 consent',

    PRIMARY KEY (id)

) ENGINE=InnoDB;

-- -------------------------------------------------------
-- Run this ALTER if the table already exists:
-- -------------------------------------------------------
-- ALTER TABLE `form_mmr_immunization`
--     ADD COLUMN IF NOT EXISTS `consent_sig`  TINYINT(1) DEFAULT 0 COMMENT '1 = patient signed vaccine consent',
--     ADD COLUMN IF NOT EXISTS `immtrac2_sig` TINYINT(1) DEFAULT 0 COMMENT '1 = patient signed ImmTrac2 consent';
