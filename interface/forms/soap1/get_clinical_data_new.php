<?php

/**
 * Clinical Data API for SOAP Form Drag and Drop
 */

// Function to get LBF forms data
function getLBFForms($pid) {
    error_log("getLBFForms called for pid: $pid"); // Debug log
    
    $relevantForms = [
        'Clinical Referral Form',
        'General Referral Form',
        'UMC Imaging Order'
    ];
    
    $forms = array();
    
    // Get all LBF forms for this patient
    $query = "SELECT f.id, f.encounter, f.form_name, f.form_id, f.formdir, fe.date 
              FROM forms f 
              JOIN form_encounter fe ON f.encounter = fe.encounter 
              WHERE f.pid = ? 
              AND f.formdir = 'LBF' 
              AND f.deleted = 0 
              ORDER BY fe.date DESC";
    
    $result = sqlStatement($query, array($pid));
    
    while ($row = sqlFetchArray($result)) {
        if (in_array($row['form_name'], $relevantForms)) {
            error_log("Processing form: {$row['form_name']} with form_id: {$row['form_id']}"); // Debug log
            
            // Get all fields except signature
            $formFields = array();
            $fieldsQuery = sqlStatement(
                "SELECT field_id, field_value 
                FROM lbf_data 
                WHERE form_id = ? 
                AND field_id != 'patient_sig'",
                array($row['form_id'])
            );
            
            while ($field = sqlFetchArray($fieldsQuery)) {
                $formFields[$field['field_id']] = $field['field_value'];
            }
            
            error_log("Form fields found: " . print_r($formFields, true)); // Debug log
            
            // Format content
            $content = '';
            
            if (!empty($formFields['referral_to'])) {
                $content .= "Referral to: " . $formFields['referral_to'] . "\n";
            }
            
            if (!empty($formFields['referral_reason'])) {
                $content .= "Referral For: " . $formFields['referral_reason'];
            }
            
            if (!empty($formFields['diagnoses'])) {
                if (!empty($content)) {
                    $content .= "\n";
                }
                $content .= "Diagnosis: " . $formFields['diagnoses'];
            }
            
            // Only add form if we have content
            if (!empty($content)) {
                $forms[] = array(
                    'type' => 'LBF',
                    'name' => $row['form_name'],
                    'encounter' => $row['encounter'],
                    'date' => $row['date'],
                    'header' => $row['form_name'] . ' - (' . date('m/d/Y', strtotime($row['date'])) . ')',
                    'content' => $content,
                    'form_id' => $row['form_id']
                );
                error_log("Added form to results with content: $content"); // Debug log
            }
        }
    }
    
    error_log("Returning " . count($forms) . " forms"); // Debug log
    return $forms;
}

// Rest of your existing code...
