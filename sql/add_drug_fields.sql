-- Add new fields to the drugs table
-- MEDID, BRAND NAME, DOSAGE FORM CODE, MED CLASS CODE, MFR

ALTER TABLE `drugs` 
ADD COLUMN `medid` VARCHAR(20) DEFAULT '' COMMENT 'Medical ID - Required, Alphanumeric, 20 char limit',
ADD COLUMN `brand_name` VARCHAR(100) DEFAULT '' COMMENT 'Brand Name - Required when different than Generic Name, Alphanumeric, 100 char limit',
ADD COLUMN `dosage_form_code` VARCHAR(20) DEFAULT '' COMMENT 'Dosage Form Code - Required, Alphanumeric, 20 char limit',
ADD COLUMN `med_class_code` VARCHAR(2) DEFAULT '' COMMENT 'Med Class Code - Required, Values: 00, 0-5',
ADD COLUMN `mfr` VARCHAR(50) DEFAULT '' COMMENT 'Manufacturer - Required, Alphanumeric, 50 char limit';