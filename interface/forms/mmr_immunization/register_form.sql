-- Register the MMR Immunization form in OpenEMR's form registry.
-- Run ONCE after deploying the folder to interface/forms/mmr_immunization/
-- and after running table.sql.
--
-- OR use: Admin > Forms > Forms Administration > Install
-- (which does this automatically if the folder is in interface/forms/)

INSERT INTO registry (
    name,
    state,
    directory,
    sql_run,
    unpackaged,
    date,
    priority,
    category,
    nickname
) VALUES (
    'MMR Immunization',   -- Display name shown in encounter
    1,                    -- 1 = active / visible
    'mmr_immunization',   -- Folder name (MUST match exactly)
    1,                    -- 1 = table.sql has already been run
    1,
    NOW(),
    0,
    'Clinical',           -- Group / category in form picker
    'mmr_immunization'
);
