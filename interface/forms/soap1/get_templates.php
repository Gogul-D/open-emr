<?php
/**
 * Templates API for SOAP Form
 * 
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

// Start output buffering to catch any unwanted output
ob_start();

require_once(__DIR__ . "/../../globals.php");
require_once("$srcdir/api.inc.php");
require_once("$srcdir/sql.inc.php");

use OpenEMR\Common\Csrf\CsrfUtils;

// Clear any unwanted output that might have been generated during includes
ob_clean();

// Set content type
header('Content-Type: application/json');

// Verify CSRF token using the same method as the working file
if (!CsrfUtils::verifyCsrfToken($_GET["csrf_token"] ?? $_POST["csrf_token"] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF validation failed']);
    ob_end_clean();
    exit();
}

// Get parameters (supporting both GET and POST)
$context = $_POST['context'] ?? $_GET['context'] ?? '';
$category = $_POST['category'] ?? $_GET['category'] ?? '';

$templates = [
    'EXPRESS VISITS' => [
        'FEMALE Express' => "XX yr old female Pt presents to clinic for STD screening. Pt denies any symptoms; no clinician assessment performed. Blood collected for HIV/RPR screening; vaginal swab collected for GC/CT testing. Urine pregnancy test obtained to rule out pregnancy. Stat RPR today was non-reactive; reviewed negative RPR result with Pt and a hard copy was provided. Pregnancy test was negative, hard copy was provided. Educated Pt on STD prevention strategies including consistent condom usage and to retest PRN or annually per CDC recommendations. Clinic will contact Pt to notify of any positive results. Pt to RTC in 7-10 days for hard copy of results with an ID as needed. Pt voiced understanding of education and agrees with plan of care. Spent 15 minutes reviewing patient's chart/medical history, formulating POC and answering questions. Spent 10 minutes on education (safe sex, condom use, STDs).",
        
        'HETEROSEXUAL MALE Express' => "XX yr old heterosexual male Pt presents to clinic for STD screening. Pt denies any symptoms; no clinician assessment performed. Blood collected for HIV/RPR screening; urine collected for GC/CT testing. Stat RPR today was non-reactive; reviewed negative RPR result with Pt and a hard copy was provided. Educated Pt on STD prevention strategies including consistent condom usage and to retest PRN or annually per CDC recommendations. Clinic will contact Pt to notify of any positive results. Pt to RTC in 7-10 days for hard copy of results with an ID as needed. Pt voiced understanding of education and agrees with plan of care. Spent 15 minutes reviewing patient's chart/medical history, formulating POC and answering questions. Spent 10 minutes on education (safe sex, condom use, STDs).",
        
        'ASYMPTOMATIC MSM' => "XX yr old MSM presents to clinic for STD screening. Pt denies any symptoms at this time and is seeking routine testing. Blood collected for HIV/RPR syphilis testing; urine, throat and rectal swabs were collected for GC/CT screening. Per clinician assessment, no signs of STDs were evidenced. Stat RPR today was non-reactive. Reviewed negative RPR results with patient and a hard copy was provided. Educated Pt on STD prevention strategies including consistent condom usage and to retest every 3-6 months (or PRN) per CDC recommendations. Clinic will contact Pt to notify of any positive results. Pt to RTC in 7-10 days for hard copy of results with an ID as needed. Pt voiced understanding of education and agrees with plan of care. Spent 15 minutes on PE, reviewing patient's chart/medical history, formulating POC, and answering questions. Spent 15 minutes on education (safe sex, consistent condom use, STDs/PrEP)."
    ],
    
    'TOC MSM' => [
        'MSM Test of Cure' => "XX yr old MSM presents to clinic for STD screening. Pt denies any symptoms at this time and is here for TOC; Pt was treated for oropharyngeal GC at last visit. Throat swab was collected for GC/CT screening. No clinician assessment, Pt denies any new sexual contact since prior to last testing/tx. Educated Pt on STD prevention strategies including consistent condom usage and to retest every 3-6 months (or PRN) per CDC recommendations. Clinic will contact Pt to notify of any positive results. Pt to RTC in 7-10 days for hard copy of results with an ID as needed. Pt voiced understanding of education and agrees with plan of care. Spent 20 minutes reviewing patient's chart/medical history, collecting specimen, formulating POC, and answering questions. Spent 10 minutes on education (safe sex, consistent condom use, STDs)."
    ],
    
    'RESULTS NORMAL' => [
        'Female Results' => "XX yr old female presents to clinic for results review from initial visit on XX /XX /20XX. Pt states no symptoms today. Reviewed results with Pt and provided hard copies. Reviewed STD prevention strategies such as consistent condom usage. Pt aware to RTC to retest annually (or PRN) per CDC recommendations. Patient voiced understanding of results and education. Spent 35 minutes reviewing patient's chart/medical history, formulating POC and answering questions. Spent 10 minutes on education (safe sex, condom use, STDs).",
        
        'Heterosexual Male Results' => "XX yr old heterosexual male presents to clinic for results review from initial visit on XX/XX  /20XX. Pt states no symptoms today. Reviewed results with Pt and provided hard copies. Reviewed STD prevention strategies such as consistent condom usage. Pt aware to RTC to retest annually (or PRN) per CDC recommendations. Patient voiced understanding of results and education. Spent 35 minutes reviewing patient's chart/medical history, formulating POC and answering questions. Spent 10 minutes on education (safe sex, condom use, STDs).",
        
        'MSM Results' => "XX yr old MSM presents to clinic for results review from initial visit on XX/XX//20XX. Pt states no symptoms today. Reviewed results with Pt and provided hard copies. Reviewed STD prevention strategies such as consistent condom usage. Pt aware to RTC in 3-6 months to retest per CDC recommendations. Patient voiced understanding of results and education. Spent 35 minutes reviewing patient's chart/medical history, formulating POC and answering questions. Spent 10 minutes on education (safe sex, condom use, STDs).",
        
        'MSM Results and TOC' => "XX yr old MSM presents to clinic for results from initial visit on XX/XX/20XX and TOC. Pt states no symptoms today. Reviewed results with Pt and provided hard copies. Throat swab was collected for TOC - GC/CT screening at this time. Pt was treated for oropharyngeal GC at last visit. No RN assessment. Educated Pt on STD prevention strategies including condom usage. Pt to RTC in 2 weeks for review of TOC results. Pt voiced understanding of education and agrees with plan of care. Spent 35 minutes reviewing patient's chart/medical history, collecting specimen, formulating POC, and answering questions. Spent 10 minutes on education (safe sex, consistent condom use, STDs)."
    ],
    
    'RESULTS ABNORMAL' => [
        'CT Treatment - Doxycycline' => "XX yr old presents to clinic for results review from initial visit on XX/XX/20XX and chlamydia treatment. Reviewed results with Pt and hard copies were provided. Notified Pt of need for treatment for positive CT results per SDOs. Pt agreed and received doxycycline 100 mg PO BID x7 days. Educated Pt to eat before taking doxycycline, and to avoid dairy, iron preparations, or antacids for 1 hour before and after taking medications. Reviewed increased risk of photosensitivity while taking doxycycline and instructed pt to wear sun block lotion if exposed to the sun. Pt educated to refrain from sexual contact and ETOH x7 days. Reviewed ER warning signs; go to ER if SOB, rashes, hives or itchiness. Reviewed STD prevention strategies including consistent condom usage. CT CDC fact sheet provided. Educated Pt to notify partner(s) from the last 2 months to come in to get tested and treated, and to RTC in 3 months to retest per CDC recommendations. Pt voiced understanding of education and agrees with plan of care. Spent 35 minutes reviewing patient's chart/medical history, collecting specimen, administering treatment, formulating POC and answering questions. Spent 10 minutes on education (safe sex, condom use, STDs).",
        
        'CT and GC Treatment - Doxycycline' => "XX yr old presents to clinic for results review from initial visit on XX/XX/20XX and chlamydia/gonorrhea treatment. Reviewed results with Pt and hard copies were provided. Notified Pt of need for CT/GC treatment per SDOs. Pt agreed and received doxycycline 100 mg PO BID x7 days and Ceftriaxone 500 mg, IM was administered to the deltoid; Pt tolerated injection well. Educated Pt to eat before taking doxycycline, and to avoid dairy, iron preparations, or antacids for 1 hour before and after taking medications. Reviewed increased risk of photosensitivity while taking doxycycline and instructed pt to wear sun block lotion if exposed to the sun. Pt educated to refrain from sexual contact and ETOH x7 days. Reviewed ER warning signs; go to ER if SOB, rashes, hives or itchiness. Reviewed STD prevention strategies including consistent condom usage and need for Pt to notify partner/partners from the last 2 months to get tested and treated. CT & GC CDC fact sheets provided. Educated Pt to notify partner(s) from the last 2 months to come in to get tested and treated, and to RTC in 3 months to retest per CDC recommendations. Pt voiced understanding of education and agrees with plan of care. Spent 35 minutes reviewing patient's chart/medical history, collecting specimen, administering treatment, formulating POC and answering questions. Spent 10 minutes on education (safe sex, condom use, STDs).",
        
        'CT Treatment - Azithromycin' => "XX yr old presents to clinic for results review from initial visit on XX/XX/20XX and chlamydia treatment. Reviewed results with Pt and hard copies were provided. Notified Pt of need for CT treatment per SDOs. Pt agreed and azithromycin 1 gm PO stat dose was administered to Pt at this time. Doxycycline was not provided because pregnancy could not be ruled out at this time. Educated Pt to eat as soon as possible to prevent emesis and to avoid dairy products for the hour following consumption of medication. Pt educated to refrain from sexual contact and ETOH x7 days. Reviewed ER warning signs; go to ER if SOB, rashes, hives or itchiness. CT CDC fact sheet provided. Educated Pt to notify partner(s) from the last 2 months to come in to get tested and treated, and to RTC in 3 months to retest per CDC recommendations. Pt voiced understanding of education and agrees with plan of care. Spent 35 minutes reviewing patient's chart/medical history, collecting specimen, administering treatment, formulating POC and answering questions. Spent 10 minutes on education (safe sex, condom use, STDs).",
        
        'CT and GC Treatment - Azithromycin' => "XX yr old female presents to clinic for results review from initial visit on XX/XX/20XX and chlamydia/gonorrhea treatment. Reviewed results with Pt and hard copies were provided. Notified Pt of need for CT/GC treatment per SDOs. Pt agreed and azithromycin 1 gm PO stat dose was administered to Pt at this time. Doxycycline was not provided because pregnancy could not be ruled out at this time. Educated Pt to eat as soon as possible to prevent emesis and to avoid dairy products for the hour following consumption of medication. Ceftriaxone 500 mg, IM was administered to the deltoid; Pt tolerated injection well. Pt educated to refrain from sexual contact and ETOH x7 days. Reviewed ER warning signs; go to ER if SOB, rashes, hives or itchiness. CT & GC CDC fact sheets provided. Educated Pt to notify partner(s) from the last 2 months to come in to get tested and treated, and to RTC in 3 months to retest per CDC recommendations. Pt voiced understanding of education and agrees with plan of care. Spent 20 minutes reviewing patient's chart/medical history, collecting specimen, administering treatment, formulating POC and answering questions. Spent 10 minutes on education (safe sex, condom use, STDs).",
        
        'GC Treatment' => "XX yr old presents to clinic for results review from initial visit on XX/XX/20XX and gonorrhea treatment. Reviewed results with Pt and hard copies were provided. Notified Pt of need for GC treatment per SDOs. Pt agreed and Ceftriaxone 500 mg, IM was administered to the deltoid; Pt tolerated injection well. Pt educated to refrain from sexual contact and ETOH x7 days. Reviewed ER warning signs; go to ER if SOB, rashes, hives or itchiness. GC CDC fact sheet provided. Educated Pt to notify partner(s) from the last 2 months to come in to get tested and treated, and to RTC in 3 months to retest per CDC recommendations. Pt voiced understanding of education and agrees with plan of care. Spent 20 minutes reviewing patient's chart/medical history, collecting specimen, administering treatment, formulating POC and answering questions. Spent 10 minutes on education (safe sex, condom use, STDs).",
        
        'MSM External Records GC Treatment' => "XX yr old MSM presents to clinic for results review from Austin clinic visit on 03/2025, gonorrhea treatment and STD screening. Blood collected for RPR/HIV screening and urine, throat and rectal swabs also collected for GC/CT screening. Reviewed nonreactive RPR results with Pt and reviewed external results with positive rectal Gonorrhea results. Notified Pt of need for GC treatment. Pt agreed and Ceftriaxone 500 mg, IM was administered to the right deltoid; Pt tolerated injection well. Pt educated to refrain from sexual contact and ETOH x7 days. Reviewed ER warning signs; go to ER if SOB, rashes, hives or itchiness. GC CDC fact sheet provided. Educated Pt to notify partner(s) from the last 2 months to come in to get tested and treated, and to RTC in 3 months to retest per CDC recommendations. Clinic will contact patient for any positive results. Discussed PrEP with Pt. He stated he is interested in PrEP and was provided with a referral and resource list for PrEP clinic with Centro San Vicente. Pt voiced understanding of education and agrees with plan of care. Spent 20 minutes reviewing patient's chart/medical history, collecting specimen, administering treatment, formulating POC and answering questions. Spent 10 minutes on education (safe sex, condom use, STDs)."
    ],
    
    'FEMALE CONDITIONS' => [
        'Bacterial Vaginosis' => "XX yr old heterosexual female Pt presents to clinic for STD screening. Pt C/O discharge and odor x1 month. Blood collected for HIV/RPR screening; vaginal swab collected for GC/CT testing, urine pregnancy test obtained to rule out pregnancy and wet mount collected for BV/trich/yeast screening. Per clinician assessment; small white thin discharge, odorous, pH 5.5, per lab: no yeast, no trich, clue cells found (4 of 4 criteria met for bacterial vaginosis). Reviewed abnormal wet mount results, negative pregnancy test, and non-reactive RPR results with Pt. Notified Pt per SDOs, she may receive treatment for meeting BV criteria. Pt agreed and received metronidazole 500 mg PO BID x7 days. Reviewed need for patient to take medication with food and to avoid sexual contact X7 days and ETOH x10 days. Reviewed ER warning signs; go to ER if SOB, rashes, hives or itchiness. BV CDC fact sheet provided. Instructed patient to avoid vaginal wipes, soaps, or douching, and to avoid thong underwear. Educated Pt on prevention strategies including consistent condom usage and to retest PRN or annually per CDC recommendations. Pt to RTC in 7-10 days for hard copy of results as needed. Clinic will contact Pt to notify of any positive results. Pt voiced understanding of education and agrees with plan of care. Spent 20 minutes reviewing patient's chart/medical history, collecting specimen, administering treatment, formulating POC and answering questions. Spent 15 minutes on education (safe sex, condom use, STDs).",
        
        'BV and Yeast - Fluconazole' => "XX yr old heterosexual female Pt presents to clinic for STD screening. Pt is seeking routine STD testing and C/O vaginal itching and discharge. Blood collected for HIV/RPR screening; vaginal swab collected for GC/CT testing, urine HCG obtained to rule out pregnancy, and wet mount collected for BV/trich/yeast screening. Per clinician assessment; moderate yellow discharge, odorous, pH 6.0, per lab: yeast found, no trich, clue cells found - 4 of 4 criteria met for bacterial vaginosis). Reviewed abnormal wet mount results, negative pregnancy test, and non-reactive RPR results with Pt. Notified Pt per SDOs, she may receive treatment for BV and yeast. Pt agreed and received metronidazole 500 mg PO BID X7 days. Fluconazole 150 mg PO stat dose was administered at this time. Pt tolerated medication well. Reviewed possible side effects with patient (headache, nausea, abdominal pain). Pt educated to refrain from sexual contact x7 days and ETOH x10 days. BV and yeast CDC fact sheets provided. Instructed patient to avoid vaginal wipes, soaps, douching, and thong underwear to prevent recurrent or future vaginal infections. Patient stated understanding. Reviewed ER warning signs; go to ER if SOB, rashes, hives or itchiness. Pt to RTC in 7-10 days for review of results. Pt voiced understanding of education and agrees with plan of care. Spent 20 minutes reviewing patient's chart, obtaining medical history, collecting specimen, administering treatment, formulating POC and answering questions. Spent 10 minutes on education (safe sex, condom use, STDs).",
        
        'Yeast Infection - Fluconazole' => "XX yr old heterosexual female Pt presents to clinic for STD screening. Pt is seeking routine STD testing and C/O vaginal discharge and itching x3 days. Blood collected for HIV/RPR screening; vaginal swab collected for GC/CT testing, urine HCG obtained to rule out pregnancy, and wet mount collected for BV/trich/yeast screening. Per clinician assessment; moderate white curdy discharge, non-odorous, pH 4.5, per lab: yeast found, no trich, no clue cells. Reviewed abnormal wet mount results and non-reactive RPR results with Pt. Notified Pt she may receive treatment for yeast. Pt agreed and Fluconazole 150 mg PO stat dose was administered at this time. Pt tolerated medication well. Reviewed possible side effects with patient (headache, nausea, abdominal pain). Pt educated to refrain from sexual contact until clear. Yeast CDC fact sheet provided. Instructed patient to avoid vaginal wipes, soaps, vaginal douching, or tight fitted clothing. She was also instructed to avoid thong underwear. Reviewed ER warning signs; go to ER if SOB, rashes, hives or itchiness. Educated Pt on STD prevention strategies including consistent condom usage and to retest PRN or annually per CDC recommendations. Pt to RTC in 7-10 days for hard copy of results with an ID as needed. Clinic will contact Pt to notify of any positive results. Pt voiced understanding of education and agrees with plan of care. Spent minutes reviewing patient's chart/medical history, collecting specimen, administering treatment, formulating POC and answering questions. Spent 15 minutes on education (safe sex, condom use, STDs).",
        
        'Trichomoniasis' => "XX yr old female Pt presents to clinic for STD screening. Pt is C/O vaginal discharge, and pruritus x3 weeks ago, she also reports exposure to trichomoniasis. Blood collected for HIV/RPR screening; vaginal swab collected for GC/CT testing, and wet mount collected for BV/trich/yeast screening. Urine pregnancy test obtained. Per RN assessment: moderate yellow vaginal discharge, odorous, pH 6.5, per lab - trichomoniasis found, no yeast, no clue cells (3 of 4 criteria met for BV). Reviewed abnormal wet mount results with Pt. Notified Pt she may receive treatment for trichomoniasis. Pt agreed and received treatment with metronidazole 500 mg PO BID x7 days. Reviewed need for patient to avoid sexual contact x14 days and ETOH x8 days. Reviewed ER warning signs; go to ER if SOB, rashes, hives or itchiness. Trichomoniasis CDC fact sheet provided. Pt to RTC in 7-10 days with ID for hardcopy of results. Pt voiced understanding of education and agrees with plan of care. Spent 45 minutes on PE, reviewing patient's chart/medical history, collecting specimen, formulating POC, administering treatment and answering questions. Spent 15 minutes on education (safe sex, consistent condom use, STDs).",
        
        'HSV' => "XX yr old female presents to clinic for STD screening. Pt is C/O bumps to genital area x6 days. Blood collected for HIV/RPR testing and vaginal swab for CT/GC screening. Urine pregnancy test collected to rule out pregnancy. Per RN assessment; multiple herpes lesions noted throughout vulva. Patient states this has never happened to her before, making this her first outbreak. Notified Pt of non-reactive RPR results and negative pregnancy test, she may receive treatment for HSV. Pt agreed and was provided with Valacyclovir 1 gm PO BID x10 days. Pt educated on ER warning signs (rashes, hives, itchiness, shortness of breath). Pt educated to refrain from sexual contact x10 days/until clear. Herpes pamphlet provided. PT to RTC in 7-10 days for review of result. Pt voiced understanding of education and agrees with plan of care. Spent 35 minutes on PE, reviewing patient's chart/medical history, collecting specimen, formulating POC, administering treatment and answering questions. Spent 10 minutes on education (safe sex, consistent condom use, STDs).",
        
        'PID' => "XX yr old female Pt presents to clinic for STD screening. Pt is C/O vaginal discharge, itching, and intermittent pelvic cramping x1 week. Reports having unprotected intercourse before symptoms started. Blood collected for HIV/RPR screening; vaginal swab collected for GC/CT testing. Pregnancy test obtained to rule out pregnancy. Wet mount collected for BV/trich/yeast screening. Per clinician assessment; vulvar and vaginal erythema noted, moderate white vaginal discharge, odorous, pH 4.5. Per Lab; no clue cells, no trich, and no yeast, many WBCs and bacteria seen (Pt meets 2 of 4 criteria for BV). Reviewed abnormal wet mount results, negative pregnancy test, and stat non-reactive RPR results with Pt, hard copies were provided. Notified Pt per SDOs, she may receive treatment for PID. Pt agreed and ceftriaxone 500 mg IM was administered to Lt deltoid. Doxycycline 100 mg PO BID x14 days was provided to Pt at this time. Educated Pt to eat before taking doxycycline, and to avoid dairy, iron preparations, or antacids for 1 hour before and after taking medications. Reviewed increased risk of photosensitivity while taking doxycycline and instructed Pt to wear sun block lotion if exposed to the sun. Reviewed need for patient to avoid sexual contact x14 days and ETOH x15 days. Reviewed ER warning signs; go to ER if SOB, rashes, hives or itchiness. Educated Pt on prevention strategies including consistent condom usage. Pt to RTC in 48 hrs. for review of symptoms (Scheduled XX/XX/20XX). If symptoms have not improved and/or worsen, Pt will be referred to the ER. Pt provided with PID CDC fact sheet. Pt voiced understanding of education and agrees with plan of care. Spent 20 minutes reviewing patient's chart/medical history, formulating POC and answering questions. Spent 15 minutes on education (safe sex, condom use, STDs).",
        
        'Mucopurulent Cervicitis - Doxycycline' => "XX yr old female Pt presents to clinic for STD screening/retesting Pt is seeking routine STD testing and C/O. Blood collected for HIV/RPR screening; vaginal swab collected for GC/CT testing, and wet mount collected for BV/trich/yeast screening. Per RN assessment; copious yellow mucous discharge, pH 4.5, odorous, per lab: no trich, no yeast, no clue cells. Lab stated many bacteria was seen. Stat RPR today was non-reactive. Reviewed abnormal wet mount results and non-reactive RPR results with Pt. Notified Pt per SDOs, she may receive treatment for mucopurulent cervicitis. Pt agreed and received doxycycline 100 mg PO BID x7 days, and ceftriaxone 500 mg IM was administered to deltoid. Pt tolerated injection well. Educated Pt to eat before taking doxycycline, and to avoid dairy, iron preparations, or antacids for 1 hour before and after taking medications. Reviewed increased risk of photosensitivity while taking doxycycline and instructed pt to wear sun block lotion if exposed to the sun. Pt educated to refrain from sexual contact and ETOH x7 days. Reviewed ER warning signs; go to ER if SOB, rashes, hives or itchiness. Pt to RTC in 2 weeks for review of results. Pt voiced understanding of education and agrees with plan of care. Spent 45 minutes reviewing patient's chart/medical history, collecting specimen, administering treatment, formulating POC and answering questions. Spent 15 minutes on education (safe sex, condom use, STDs)."
    ],
    
    'EXPOSURE TREATMENTS' => [
        'BV with CT Exposure - Doxycycline' => "XX yr old female presents to clinic for STD screening. Pt is C/O vaginal discharge, pruritus and odor x1 week and she reports exposure to chlamydia. Blood collected for HIV/RPR testing, vaginal swab for CT/GC screening, and wet mount for BV/Trich/Yeast testing was collected. Per RN assessment, moderate thin, grey vaginal discharge, odorous, and pH of 5.0 was noted; per lab clue cells were found. Reviewed abnormal wet mount results with Pt (4 of 4 criteria met for BV) and non-reactive stat RPR, hard copies were provided. Notified Pt per SDOs, she may receive treatment for BV and exposure to CT. Pt agreed and received treatment with metronidazole 500 mg PO BID x7 days and doxycycline 100 mg PO BID x7 days. Educated Pt to eat before taking doxycycline, and to avoid dairy, iron preparations, or antacids for 1 hour before and after taking medications. Reviewed need for patient to avoid sexual contact x14 days and ETOH x8 days. Reviewed ER warning signs; go to ER if SOB, rashes, hives or itchiness. BV and Chlamydia CDC fact sheets provided. Pt to RTC in 2 weeks for review of results. Pt voiced understanding of education and agrees with plan of care. Spent 45 minutes on PE, reviewing patient's chart/medical history, collecting specimen, formulating POC, administering treatment and answering questions. Spent 15 minutes on education (safe sex, consistent condom use, STDs).",
        
        'BV with CT Exposure - Azithromycin' => "XX yr old female presents to clinic for STD screening. Pt is seeking routine STD testing. Pt C/O vaginal itching and odorous vaginal discharge x1 month and states exposure to chlamydia. Blood collected for HIV/RPR testing, vaginal swab for CT/GC screening, and wet mount for BV/Trich/Yeast testing was collected. Per RN assessment, copious thin, grey vaginal discharge, odorous, and pH of 6.5 was noted; per lab clue cells were found. Reviewed abnormal wet mount results with Pt (4 of 4 criteria met for BV). Notified Pt of abnormal wet mount results and non-reactive stat RPR. Notified Pt per SDOs, she may receive treatment for BV and exposure to CT. Pt agreed and received treatment with metronidazole 500 mg PO BID x7 days. Azithromycin 1 gm PO stat dose was administered to Pt at this time. Educated Pt to eat as soon as possible to prevent emesis and to avoid dairy products for the hour following consumption of medication. Reviewed need for patient to avoid sexual contact x14 days and ETOH x8 days. Reviewed ER warning signs; go to ER if SOB, rashes, hives or itchiness. BV and Chlamydia CDC fact sheets provided. Pt to RTC in 2 weeks for review of results. Pt voiced understanding of education and agrees with plan of care. Spent 45 minutes on PE, reviewing patient's chart/medical history, collecting specimen, formulating POC, administering treatment and answering questions. Spent 15 minutes on education (safe sex, consistent condom use, STDs).",
        
        'BV with GC and CT Exposure - Doxycycline' => "XX yr old heterosexual female presents to clinic for STD screening. Pt is seeking routine STD testing and is C/O vaginal discharge and odor x2 days; she denies pruritus or pain. She reports exposure to chlamydia and gonorrhea. Blood collected for HIV/RPR testing, urine HCG obtained to rule out pregnancy, vaginal swab for CT/GC screening, and wet mount for BV/Trich/Yeast testing was collected. Per clinician assessment, minimal white discharge, pH 5.5, odorous. Per lab, clue cells seen, no trichomoniasis, no yeast. Reviewed abnormal wet mount results with Pt (4 of 4 criteria met for BV). Notified Pt of abnormal wet mount results, negative pregnancy test, and non-reactive stat RPR result, hard copies of results were provided. Notified Pt per SDOs, she may receive treatment for BV and exposure to chlamydia and gonorrhea. Pt agreed and received treatment with metronidazole 500 mg PO BID x7 days. Ceftriaxone 500 mg IM was administered to the left deltoid, Pt tolerated injection well. Pt also received doxycycline 100 mg PO BID x7 days. Educated Pt to eat before taking doxycycline, and to avoid dairy, iron preparations, or antacids for 1 hour before and after taking medications. Reviewed increased risk of photosensitivity while taking doxycycline and instructed pt to wear sun block lotion if exposed to the sun. Reviewed need for patient to avoid sexual contact x14 days and ETOH X10 days. Reviewed ER warning signs; go to ER if SOB, rashes, hives or itchiness. BV, Chlamydia and gonorrhea CDC fact sheets were provided. Educated Pt on STD prevention strategies including consistent condom usage and to retest PRN or annually per CDC recommendations. Pt to RTC in 7-10 days for hard copy of results with an ID as needed. Clinic will contact Pt to notify of any positive results. Pt voiced understanding of education and agrees with plan of care. Spent 30 minutes reviewing patient's chart/medical history, collecting specimen, administering treatment, formulating POC and answering questions. Spent 10 minutes on education (safe sex, condom use, STDs)."
    ]
];

// Get custom templates from database
$customTemplates = getCustomTemplates($context, $category);

// Merge predefined and custom templates
$allTemplates = mergeTemplates($templates, $customTemplates);

// Generate response
if (isset($allTemplates[$context])) {
    if ($category && isset($allTemplates[$context][$category])) {
        $response = [$category => $allTemplates[$context][$category]];
    } else {
        $response = $allTemplates[$context];
    }
} else {
    $response = [];
}

// Output JSON response
echo json_encode($response, JSON_UNESCAPED_UNICODE);

// Clean up output buffering
ob_end_flush();

/**
 * Get custom templates from database
 */
function getCustomTemplates($context = '', $category = '') {
    // Create table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS `soap_custom_templates` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `context` varchar(255) NOT NULL,
        `category` varchar(255) NOT NULL,
        `template_name` varchar(255) NOT NULL,
        `template_content` text NOT NULL,
        `created_by` int(11) NOT NULL,
        `created_date` datetime DEFAULT CURRENT_TIMESTAMP,
        `is_public` tinyint(1) DEFAULT 1,
        `is_active` tinyint(1) DEFAULT 1,
        PRIMARY KEY (`id`),
        KEY `idx_context_category` (`context`, `category`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    try {
        sqlStatement($sql);
    } catch (Exception $e) {
        // Table might already exist, continue
    }
    
    // Build query
    $whereClause = "WHERE is_active = 1 AND is_public = 1";
    $params = [];
    
    if (!empty($context)) {
        $whereClause .= " AND context = ?";
        $params[] = $context;
        
        if (!empty($category)) {
            $whereClause .= " AND category = ?";
            $params[] = $category;
        }
    }
    
    $sql = "SELECT context, category, template_name, template_content 
            FROM soap_custom_templates 
            $whereClause 
            ORDER BY context, category, template_name";
    
    try {
        $result = sqlStatement($sql, $params);
        $customTemplates = [];
        
        while ($row = sqlFetchArray($result)) {
            $customTemplates[$row['context']][$row['category']][$row['template_name']] = $row['template_content'];
        }
        
        return $customTemplates;
    } catch (Exception $e) {
        // Return empty array if there's an error
        return [];
    }
}

/**
 * Merge predefined and custom templates
 */
function mergeTemplates($predefined, $custom) {
    $merged = $predefined;
    
    foreach ($custom as $context => $categories) {
        foreach ($categories as $category => $templates) {
            foreach ($templates as $name => $content) {
                // Add custom template, mark as custom if it conflicts with predefined
                $templateName = $name;
                if (isset($merged[$context][$category][$name])) {
                    $templateName = $name . " (Custom)";
                }
                $merged[$context][$category][$templateName] = $content;
            }
        }
    }
    
    return $merged;
}